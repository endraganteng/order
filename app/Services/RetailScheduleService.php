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
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $snap = $this->database->getReference('retail_schedule_preferences')->getSnapshot();
        if (! $snap->exists()) {
            $cached = [];
            return $cached;
        }

        $cached = (array) $snap->getValue();
        return $cached;
    }

    public function savePreferences(array $prefs): void
    {
        $payload = [
            'libur_days' => $prefs['libur_days'] ?? [],
            'holder_name' => $prefs['holder_name'] ?? null,
            'holder_mode' => $prefs['holder_mode'] ?? 'auto', // 'auto' | 'locked'
            'employees' => $prefs['employees'] ?? [], // array of waiter_ids (3 ids)
            'shift_modes' => $prefs['shift_modes'] ?? [], // [empName => 'default'|'prefer_full'|'prefer_short']
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
                $legacyKey = null;
                if (config('features.legacy_write_work_shifts')) {
                    $legacyKey = (string) $this->database->getReference('work_shifts')->push($template)->getKey();
                }
                $resolved[$tag] = $legacyKey;

                if (config('features.mysql_work_shifts')) {
                    try {
                        $attrs = [
                            'name' => $template['name'] ?? '',
                            'clock_in_time' => $template['clock_in_time'] ?? null,
                            'clock_out_time' => $template['clock_out_time'] ?? null,
                            'late_tolerance_minutes' => (int) ($template['late_tolerance_minutes'] ?? 0),
                            'is_active' => (bool) ($template['is_active'] ?? true),
                            'retail_tag' => $template['retail_tag'] ?? null,
                            'event_created_at' => $template['created_at'] ?? null,
                            'event_updated_at' => $template['updated_at'] ?? null,
                        ];
                        if ($legacyKey !== null) {
                            $model = \App\Models\WorkShift::updateOrCreate(['firebase_legacy_key' => $legacyKey], $attrs);
                        } else {
                            $model = \App\Models\WorkShift::create($attrs);
                            $resolved[$tag] = (string) $model->id;
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        return $resolved;
    }

    private function getAllShifts(): array
    {
        if (config('features.mysql_work_shifts')) {
            $result = [];
            foreach (\App\Models\WorkShift::all() as $row) {
                $key = $row->firebase_legacy_key ?: (string) $row->id;
                $result[$key] = [
                    'name' => $row->name,
                    'clock_in_time' => $row->clock_in_time,
                    'clock_out_time' => $row->clock_out_time,
                    'late_tolerance_minutes' => $row->late_tolerance_minutes,
                    'is_active' => $row->is_active,
                    'retail_tag' => $row->retail_tag,
                ];
            }
            return $result;
        }

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

    // =========================================================================
    //  WAITER PORTAL — read-only access
    // =========================================================================

    /**
     * Resolve 3 retail employees dari preferences (atau fallback ke name-match).
     * Mengembalikan array of {id, name, role, ...}.
     */
    public function loadRetailEmployees(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $prefs = $this->getPreferences();
            $employeeIds = $prefs['employees'] ?? [];
            if (count($employeeIds) === 3) {
                $resolved = [];
                foreach ($employeeIds as $wid) {
                    if (! $wid) {
                        continue;
                    }
                    $waiter = $this->firebase->getWaiterById($wid);
                    if ($waiter) {
                        $resolved[] = [
                            'id' => $waiter['id'] ?? $wid,
                            'name' => $waiter['name'] ?? '?',
                            'role' => $waiter['waiter_role'] ?? null,
                        ];
                    }
                }
                if (count($resolved) === 3) {
                    $cached = $resolved;
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Fallback: name-match Anjar/Rendy/Bagas
        $targets = ['anjar', 'randy', 'bagas'];
        $resolved = array_fill(0, 3, null);
        try {
            $allWaiters = $this->firebase->getAllowedEmails();
            foreach ($allWaiters as $w) {
                $name = strtolower($w['name'] ?? '');
                foreach ($targets as $i => $t) {
                    if (str_contains($name, $t) && $resolved[$i] === null) {
                        $resolved[$i] = [
                            'id' => $w['id'] ?? null,
                            'name' => $w['name'] ?? '?',
                            'role' => $w['waiter_role'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $cached = array_values(array_filter($resolved));
        return $cached;
    }

    /**
     * Cek apakah waiter_id adalah retail employee.
     */
    public function isRetailEmployee(string $waiterId): bool
    {
        if (! $waiterId) {
            return false;
        }
        $employees = $this->loadRetailEmployees();
        foreach ($employees as $emp) {
            if (($emp['id'] ?? null) === $waiterId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate weekly schedule lalu filter ke waiter spesifik.
     *
     * @return array|null  Returns ['week_start', 'week_iso', 'days' => [...], 'summary', 'shift_today', ...] atau null kalau bukan retail.
     */
    public function getWaiterWeekSchedule(string $waiterId, string $weekStart, ScheduleGeneratorService $generator): ?array
    {
        $employees = $this->loadRetailEmployees();
        if (count($employees) !== 3) {
            return null;
        }

        $waiterEmp = null;
        foreach ($employees as $e) {
            if (($e['id'] ?? null) === $waiterId) {
                $waiterEmp = $e;
                break;
            }
        }
        if (! $waiterEmp) {
            return null;
        }

        // Build prefs same as admin controller
        $prefs = $this->getPreferences();
        $genPrefs = [
            'libur_days' => $prefs['libur_days'] ?? null,
            'holder_name' => ($prefs['holder_mode'] ?? 'auto') === 'locked' ? ($prefs['holder_name'] ?? null) : null,
            'shift_modes' => $prefs['shift_modes'] ?? null,
        ];

        // Compute week ISO untuk lookup override
        $weekStartCarbon = \Carbon\Carbon::parse($weekStart);
        if (! $weekStartCarbon->isMonday()) {
            $weekStartCarbon = $weekStartCarbon->startOfWeek(\Carbon\Carbon::MONDAY);
        }
        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        $weekOverride = $this->getWeekOverride($weekIso);
        $override = null;
        if ($weekOverride && ! empty($weekOverride['cells'])) {
            $override = ['cells' => $weekOverride['cells']];
            if (! empty($weekOverride['holder_used'])) {
                $genPrefs['holder_name'] = $weekOverride['holder_used'];
            }
        }

        $schedule = $generator->generate($weekStartCarbon->toDateString(), $employees, $genPrefs, $override);

        // Filter: ambil cuma assignments untuk waiter ini
        $today = date('Y-m-d');
        $days = [];
        $totalHours = 0.0;
        $liburDay = null;
        $shiftToday = null;
        $todayDate = null;

        foreach ($schedule['matrix'] as $day) {
            foreach ($day['assignments'] as $a) {
                if ($a['employee']['name'] !== $waiterEmp['name']) {
                    continue;
                }
                $isToday = $day['date'] === $today;
                $duration = $a['shift_meta']['duration'] ?? 0;
                $totalHours += $duration;

                if ($a['shift'] === ScheduleGeneratorService::SHIFT_LIBUR) {
                    $liburDay = $day['day_label'];
                }

                if ($isToday) {
                    $shiftToday = [
                        'day_label' => $day['day_label'],
                        'date' => $day['date'],
                        'date_label' => $day['date_label'],
                        'shift' => $a['shift'],
                        'shift_meta' => $a['shift_meta'],
                    ];
                    $todayDate = $day['date'];
                }

                $days[] = [
                    'day_key' => $day['day_key'],
                    'day_label' => $day['day_label'],
                    'date' => $day['date'],
                    'date_label' => $day['date_label'],
                    'is_weekend' => $day['is_weekend'],
                    'is_today' => $isToday,
                    'shift' => $a['shift'],
                    'shift_meta' => $a['shift_meta'],
                ];
                break;
            }
        }

        return [
            'waiter' => $waiterEmp,
            'week_start' => $schedule['week_start'],
            'week_iso' => $schedule['week_iso'],
            'days' => $days,
            'total_hours' => $totalHours,
            'libur_day' => $liburDay,
            'shift_today' => $shiftToday,
            'is_holder' => $schedule['holder_name'] === $waiterEmp['name'],
            'has_override' => $override !== null,
        ];
    }
}
