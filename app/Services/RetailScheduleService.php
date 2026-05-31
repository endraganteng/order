<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

/**
 * RetailScheduleService
 *
 * Persist preferences (libur days + holder) dan per-week overrides untuk jadwal retail.
 * Juga handle apply ke /waiter_schedule_template existing untuk attendance integration.
 *
 * Firebase paths:
 *   /retail_schedule_preferences   — global libur days + holder lock (1 record)
 *   /retail_schedules/{week_iso}   — per-week overrides + saved schedule
 */
class RetailScheduleService
{
    protected Database $database;
    protected FirebaseService $firebase;

    public function __construct(Database $database, FirebaseService $firebase)
    {
        $this->database = $database;
        $this->firebase = $firebase;
    }

    // =========================================================================
    //  PREFERENCES (global, applies forward)
    // =========================================================================

    public function getPreferences(): array
    {
        $snap = $this->database->getReference('retail_schedule_preferences')->getSnapshot();
        if (! $snap->exists()) {
            return [];
        }

        return (array) $snap->getValue();
    }

    public function savePreferences(array $prefs): void
    {
        $payload = [
            'libur_days' => $prefs['libur_days'] ?? [],
            'holder_name' => $prefs['holder_name'] ?? null,
            'holder_mode' => $prefs['holder_mode'] ?? 'auto', // 'auto' | 'locked'
            'updated_at' => time(),
        ];

        $this->database->getReference('retail_schedule_preferences')->set($payload);
    }

    public function resetPreferences(): void
    {
        $this->database->getReference('retail_schedule_preferences')->remove();
    }

    // =========================================================================
    //  PER-WEEK OVERRIDES (manual cell edits + saved snapshot)
    // =========================================================================

    public function getWeekOverride(string $weekIso): ?array
    {
        $snap = $this->database->getReference('retail_schedules/'.$weekIso)->getSnapshot();
        if (! $snap->exists()) {
            return null;
        }

        return (array) $snap->getValue();
    }

    /**
     * Save full schedule snapshot for a week.
     * $data should include 'matrix' (or 'cells') untuk overrides + metadata.
     */
    public function saveWeekSchedule(string $weekIso, array $data): void
    {
        $payload = [
            'week_iso' => $weekIso,
            'cells' => $data['cells'] ?? [],
            'libur_days_used' => $data['libur_days_used'] ?? [],
            'holder_used' => $data['holder_used'] ?? null,
            'saved_at' => time(),
            'saved_by' => $data['saved_by'] ?? 'admin',
        ];

        $this->database->getReference('retail_schedules/'.$weekIso)->set($payload);
    }

    public function deleteWeekSchedule(string $weekIso): void
    {
        $this->database->getReference('retail_schedules/'.$weekIso)->remove();
    }

    // =========================================================================
    //  APPLY TO ATTENDANCE (push ke /waiter_schedule_template existing)
    // =========================================================================

    /**
     * Apply jadwal mingguan ke /waiter_schedule_template untuk attendance integration.
     *
     * Mapping shift retail → work_shifts existing:
     *   FULL  → cari/buat shift dengan clock_in 06:30, clock_out 21:00
     *   PAGI  → cari/buat shift dengan clock_in 06:30, clock_out 15:30
     *   SORE  → cari/buat shift dengan clock_in 12:00, clock_out 21:00
     *   LIBUR → "off"
     *
     * Updates per-day mapping di /waiter_schedule_template/{waiter_id}.
     *
     * @param  array  $matrix  Generated schedule matrix (7 days × 3 employees)
     * @return array{success: bool, applied: int, errors: array, shift_ids: array}
     */
    public function applyToAttendance(array $matrix): array
    {
        $errors = [];
        $applied = 0;

        try {
            $shiftIds = $this->ensureRetailShifts();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'applied' => 0,
                'errors' => ['Gagal memastikan shift template: '.$e->getMessage()],
                'shift_ids' => [],
            ];
        }

        // Build per-waiter weekly map
        $waiterDayShift = []; // waiter_id => [day_key => shift_id|'off']
        foreach ($matrix as $day) {
            $dayKey = $day['day_key'];
            foreach ($day['assignments'] as $a) {
                $waiterId = $a['employee']['id'] ?? null;
                if (! $waiterId) {
                    $errors[] = "Karyawan {$a['employee']['name']} tidak punya waiter_id (belum terdaftar di /allowed_emails).";

                    continue;
                }

                $shift = $a['shift'];
                if ($shift === ScheduleGeneratorService::SHIFT_LIBUR) {
                    $value = 'off';
                } else {
                    $value = $shiftIds[$shift] ?? null;
                    if (! $value) {
                        $errors[] = "Shift $shift tidak punya template ID di /work_shifts.";

                        continue;
                    }
                }

                $waiterDayShift[$waiterId][$dayKey] = $value;
            }
        }

        // Write to Firebase
        foreach ($waiterDayShift as $waiterId => $dayMap) {
            $payload = $dayMap;
            $payload['updated_at'] = time();
            $this->database->getReference('waiter_schedule_template/'.$waiterId)->update($payload);
            $applied++;
        }

        return [
            'success' => empty($errors),
            'applied' => $applied,
            'errors' => $errors,
            'shift_ids' => $shiftIds,
        ];
    }

    /**
     * Memastikan 3 shift retail (FULL, PAGI, SORE) ada di /work_shifts.
     * Buat kalau belum ada (tagged dengan 'retail' = true).
     *
     * @return array{FULL: string, PAGI: string, SORE: string}
     */
    public function ensureRetailShifts(): array
    {
        $expected = [
            ScheduleGeneratorService::SHIFT_FULL => [
                'name' => 'Retail Full',
                'clock_in_time' => '06:30',
                'clock_out_time' => '21:00',
                'late_tolerance_minutes' => 15,
                'is_active' => true,
                'retail_tag' => 'FULL',
            ],
            ScheduleGeneratorService::SHIFT_PAGI => [
                'name' => 'Retail Pagi',
                'clock_in_time' => '06:30',
                'clock_out_time' => '15:30',
                'late_tolerance_minutes' => 15,
                'is_active' => true,
                'retail_tag' => 'PAGI',
            ],
            ScheduleGeneratorService::SHIFT_SORE => [
                'name' => 'Retail Sore',
                'clock_in_time' => '12:00',
                'clock_out_time' => '21:00',
                'late_tolerance_minutes' => 15,
                'is_active' => true,
                'retail_tag' => 'SORE',
            ],
        ];

        $existing = $this->getAllShifts();
        $resolved = [];

        foreach ($expected as $tag => $template) {
            // Find by retail_tag first
            $found = null;
            foreach ($existing as $id => $shift) {
                if (($shift['retail_tag'] ?? null) === $tag) {
                    $found = $id;
                    break;
                }
            }

            if ($found) {
                $resolved[$tag] = $found;
            } else {
                // Create new
                $template['created_at'] = time();
                $template['updated_at'] = time();
                $newRef = $this->database->getReference('work_shifts')->push($template);
                $resolved[$tag] = $newRef->getKey();
            }
        }

        return $resolved;
    }

    private function getAllShifts(): array
    {
        $snap = $this->database->getReference('work_shifts')->getSnapshot();
        if (! $snap->exists()) {
            return [];
        }

        return (array) $snap->getValue();
    }

    /**
     * Convert matrix to cells map (for storage).
     *
     * Output: ['monday' => ['Anjar' => 'FULL', ...], ...]
     */
    public function matrixToCells(array $matrix): array
    {
        $cells = [];
        foreach ($matrix as $day) {
            $cells[$day['day_key']] = [];
            foreach ($day['assignments'] as $a) {
                $cells[$day['day_key']][$a['employee']['name']] = $a['shift'];
            }
        }

        return $cells;
    }
}
