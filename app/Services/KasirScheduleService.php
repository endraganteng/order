<?php

namespace App\Services;

use Carbon\Carbon;
use Kreait\Firebase\Contract\Database;

/**
 * KasirScheduleService
 *
 * Generator + persistence untuk jadwal kasir (terpisah dari retail).
 *
 * Setup:
 *  - 2 kasir tetap (default: CAHYA + arla, role 'kasir')
 *  - 1 backup finance (default: annisa, role 'finance') yang cover saat kasir libur
 *
 * Aturan:
 *  - Toko buka 06:30–21:00 setiap hari
 *  - Setiap kasir libur 1× per minggu (Senin atau Selasa)
 *  - Kedua kasir tidak boleh libur di hari yang sama
 *  - Setiap shift slot wajib ada 1 orang on-duty
 *  - Hari kasir libur → backup masuk (mengisi salah satu shift)
 *  - Hari biasa (Rab-Min) → backup off (sudah ada 2 kasir)
 *
 * Shift codes:
 *  - SHIFT_1 (pagi): weekday 06:30–15:30 (9j) | weekend 06:30–17:00 (10.5j)
 *  - SHIFT_2 (sore): weekday 12:30–21:00 (8.5j) | weekend 10:30–21:00 (10.5j)
 *  - LIBUR
 *
 * Firebase paths:
 *  - /kasir_schedule_preferences
 *  - /kasir_schedules/{week_iso}
 */
class KasirScheduleService
{
    public const SHIFT_1 = 'SHIFT_1';
    public const SHIFT_2 = 'SHIFT_2';
    public const SHIFT_LIBUR = 'LIBUR';

    public const WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    public const WEEKEND_KEYS = ['saturday', 'sunday'];
    public const DAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    public const DAY_LABELS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public const LIBUR_OPTIONS = ['monday', 'tuesday'];

    /**
     * Get shift meta untuk hari spesifik (weekday vs weekend).
     */
    public static function getShiftMeta(string $shift, string $dayKey): array
    {
        $isWeekend = in_array($dayKey, self::WEEKEND_KEYS, true);

        if ($shift === self::SHIFT_1) {
            return $isWeekend
                ? ['start' => '06:30', 'end' => '17:00', 'duration' => 10.5, 'label' => 'Shift 1 Weekend']
                : ['start' => '06:30', 'end' => '15:30', 'duration' => 9.0, 'label' => 'Shift 1'];
        }

        if ($shift === self::SHIFT_2) {
            return $isWeekend
                ? ['start' => '10:30', 'end' => '21:00', 'duration' => 10.5, 'label' => 'Shift 2 Weekend']
                : ['start' => '12:30', 'end' => '21:00', 'duration' => 8.5, 'label' => 'Shift 2'];
        }

        return ['start' => null, 'end' => null, 'duration' => 0.0, 'label' => 'Libur'];
    }

    protected Database $database;
    protected FirebaseService $firebase;

    public function __construct(Database $database, FirebaseService $firebase)
    {
        $this->database = $database;
        $this->firebase = $firebase;
    }

    // =========================================================================
    //  PREFERENCES
    // =========================================================================

    public function getPreferences(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $snap = $this->database->getReference('kasir_schedule_preferences')->getSnapshot();
        $cached = $snap->exists() ? (array) $snap->getValue() : [];
        return $cached;
    }

    public function savePreferences(array $prefs): void
    {
        $payload = [
            'kasir_ids' => $prefs['kasir_ids'] ?? [], // 2 waiter_ids
            'backup_id' => $prefs['backup_id'] ?? null, // 1 waiter_id (finance)
            'libur_days' => $prefs['libur_days'] ?? [], // [empName => 'monday'|'tuesday']
            'updated_at' => time(),
        ];

        $this->database->getReference('kasir_schedule_preferences')->set($payload);
    }

    public function resetPreferences(): void
    {
        $this->database->getReference('kasir_schedule_preferences')->remove();
    }

    public function getWeekOverride(string $weekIso): ?array
    {
        $snap = $this->database->getReference('kasir_schedules/'.$weekIso)->getSnapshot();

        return $snap->exists() ? (array) $snap->getValue() : null;
    }

    public function saveWeekSchedule(string $weekIso, array $data): void
    {
        $payload = [
            'week_iso' => $weekIso,
            'cells' => $data['cells'] ?? [],
            'libur_days_used' => $data['libur_days_used'] ?? [],
            'saved_at' => time(),
            'saved_by' => $data['saved_by'] ?? 'admin',
        ];

        $this->database->getReference('kasir_schedules/'.$weekIso)->set($payload);
    }

    public function deleteWeekSchedule(string $weekIso): void
    {
        $this->database->getReference('kasir_schedules/'.$weekIso)->remove();
    }

    // =========================================================================
    //  EMPLOYEE RESOLUTION
    // =========================================================================

    /**
     * Load kasir + backup berdasarkan preferences atau auto-detect dari role.
     *
     * @return array{kasirs: array, backup: ?array}
     */
    public function loadEmployees(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        // 1. Coba dari preferences
        try {
            $prefs = $this->getPreferences();
            $kasirIds = $prefs['kasir_ids'] ?? [];
            $backupId = $prefs['backup_id'] ?? null;

            if (count($kasirIds) === 2) {
                $kasirs = [];
                foreach ($kasirIds as $wid) {
                    $w = $this->firebase->getWaiterById($wid);
                    if ($w) {
                        $kasirs[] = $this->normalizeWaiter($w);
                    }
                }
                $backup = null;
                if ($backupId) {
                    $w = $this->firebase->getWaiterById($backupId);
                    if ($w) {
                        $backup = $this->normalizeWaiter($w);
                    }
                }
                if (count($kasirs) === 2) {
                    $cached = ['kasirs' => $kasirs, 'backup' => $backup];
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 2. Auto-detect dari role
        $kasirs = [];
        $backup = null;
        try {
            foreach ($this->firebase->getAllowedEmails() as $w) {
                if (! ($w['is_active'] ?? false)) {
                    continue;
                }
                $role = strtolower((string) ($w['waiter_role'] ?? ''));
                if ($role === 'kasir' && count($kasirs) < 2) {
                    $kasirs[] = $this->normalizeWaiter($w);
                } elseif ($role === 'finance' && ! $backup) {
                    $backup = $this->normalizeWaiter($w);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $cached = ['kasirs' => $kasirs, 'backup' => $backup];
        return $cached;
    }

    private function normalizeWaiter(array $w): array
    {
        return [
            'id' => $w['id'] ?? null,
            'name' => $w['name'] ?? '?',
            'role' => $w['waiter_role'] ?? null,
        ];
    }

    public function loadAllActiveWaiters(): array
    {
        try {
            $waiters = [];
            foreach ($this->firebase->getAllowedEmails() as $w) {
                if (! ($w['is_active'] ?? false)) {
                    continue;
                }
                $waiters[] = $this->normalizeWaiter($w);
            }
            usort($waiters, fn ($a, $b) => strcmp(strtolower($a['name']), strtolower($b['name'])));

            return $waiters;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Apakah waiter terlibat di kasir schedule (sebagai kasir atau backup)?
     */
    public function isKasirOrBackup(string $waiterId): bool
    {
        if (! $waiterId) {
            return false;
        }
        $emp = $this->loadEmployees();
        foreach ($emp['kasirs'] as $k) {
            if (($k['id'] ?? null) === $waiterId) {
                return true;
            }
        }
        if (($emp['backup']['id'] ?? null) === $waiterId) {
            return true;
        }

        return false;
    }

    // =========================================================================
    //  GENERATOR
    // =========================================================================

    /**
     * Default libur rotation: minggu ganjil/genap.
     * Minggu ganjil ISO week → kasir1 libur Senin, kasir2 libur Selasa
     * Minggu genap → kasir1 libur Selasa, kasir2 libur Senin
     */
    private function defaultLiburDays(array $kasirs, Carbon $weekStart): array
    {
        $weekNum = (int) $weekStart->isoFormat('W');
        $isOdd = ($weekNum % 2) === 1;

        return [
            $kasirs[0]['name'] => $isOdd ? 'monday' : 'tuesday',
            $kasirs[1]['name'] => $isOdd ? 'tuesday' : 'monday',
        ];
    }

    /**
     * Generate jadwal mingguan kasir.
     */
    public function generate(string $weekStart, ?array $override = null): array
    {
        $emp = $this->loadEmployees();
        $kasirs = $emp['kasirs'];
        $backup = $emp['backup'];

        if (count($kasirs) !== 2) {
            throw new \RuntimeException('Generator butuh tepat 2 kasir.');
        }

        $weekStartCarbon = Carbon::parse($weekStart);
        if (! $weekStartCarbon->isMonday()) {
            $weekStartCarbon = $weekStartCarbon->startOfWeek(Carbon::MONDAY);
        }
        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        // Resolve preferences
        $prefs = $this->getPreferences();
        $liburDays = $prefs['libur_days'] ?? [];

        // Validate libur_days
        if (count($liburDays) !== 2 ||
            ! in_array($liburDays[$kasirs[0]['name']] ?? null, self::LIBUR_OPTIONS, true) ||
            ! in_array($liburDays[$kasirs[1]['name']] ?? null, self::LIBUR_OPTIONS, true) ||
            $liburDays[$kasirs[0]['name']] === $liburDays[$kasirs[1]['name']]
        ) {
            $liburDays = $this->defaultLiburDays($kasirs, $weekStartCarbon);
        }

        $matrix = $this->buildMatrix($weekStartCarbon, $kasirs, $backup, $liburDays);

        // Apply override
        if (is_array($override) && isset($override['cells'])) {
            $matrix = $this->applyCellOverrides($matrix, $override['cells']);
        }

        $summary = $this->calculateSummary($matrix, $kasirs, $backup);
        $validation = $this->validate($matrix, $kasirs, $backup);

        return [
            'week_start' => $weekStartCarbon->toDateString(),
            'week_iso' => $weekIso,
            'kasirs' => $kasirs,
            'backup' => $backup,
            'libur_days' => $liburDays,
            'matrix' => $matrix,
            'summary' => $summary,
            'validation' => $validation,
            'is_overridden' => $override !== null,
        ];
    }

    private function buildMatrix(Carbon $weekStart, array $kasirs, ?array $backup, array $liburDays): array
    {
        $matrix = [];

        // Pre-compute pattern: alternate Shift 1 / Shift 2 for each kasir across non-libur days
        // Goal: each kasir gets balanced 3× Shift 1 + 3× Shift 2 across 6 working days
        $kasir1Toggle = 0;
        $kasir2Toggle = 0;

        foreach (self::DAY_KEYS as $dayIdx => $dayKey) {
            $date = $weekStart->copy()->addDays($dayIdx);
            $isWeekend = in_array($dayKey, self::WEEKEND_KEYS, true);
            $row = [
                'day_key' => $dayKey,
                'day_label' => self::DAY_LABELS[$dayIdx],
                'date' => $date->toDateString(),
                'date_label' => $date->isoFormat('D MMM'),
                'is_weekend' => $isWeekend,
                'assignments' => [],
            ];

            $kasir1Libur = ($liburDays[$kasirs[0]['name']] ?? null) === $dayKey;
            $kasir2Libur = ($liburDays[$kasirs[1]['name']] ?? null) === $dayKey;

            // === Kasir 1 ===
            if ($kasir1Libur) {
                $row['assignments'][] = $this->makeAssignment($kasirs[0], self::SHIFT_LIBUR, $dayKey);
            } else {
                // Alternate: even toggle = SHIFT_1, odd = SHIFT_2
                $shift = ($kasir1Toggle % 2 === 0) ? self::SHIFT_1 : self::SHIFT_2;
                $row['assignments'][] = $this->makeAssignment($kasirs[0], $shift, $dayKey);
                $kasir1Toggle++;
            }

            // === Kasir 2 ===
            if ($kasir2Libur) {
                $row['assignments'][] = $this->makeAssignment($kasirs[1], self::SHIFT_LIBUR, $dayKey);
            } else {
                // Take opposite of kasir 1 if both work today, else alternate own
                if (! $kasir1Libur && ! $kasir2Libur) {
                    // Both work — take opposite of kasir 1
                    $kasir1Shift = $row['assignments'][0]['shift'];
                    $shift = ($kasir1Shift === self::SHIFT_1) ? self::SHIFT_2 : self::SHIFT_1;
                } else {
                    // Kasir 1 libur, kasir 2 must take both shifts? No — backup covers other.
                    // Kasir 2 takes own alternation
                    $shift = ($kasir2Toggle % 2 === 0) ? self::SHIFT_1 : self::SHIFT_2;
                }
                $row['assignments'][] = $this->makeAssignment($kasirs[1], $shift, $dayKey);
                $kasir2Toggle++;
            }

            // === Backup (annisa) ===
            if ($backup) {
                if ($kasir1Libur || $kasir2Libur) {
                    // Backup fills opposite shift of the working kasir
                    $workingKasirShift = $kasir1Libur
                        ? $row['assignments'][1]['shift'] // kasir 2 working
                        : $row['assignments'][0]['shift']; // kasir 1 working
                    $backupShift = ($workingKasirShift === self::SHIFT_1) ? self::SHIFT_2 : self::SHIFT_1;
                    $row['assignments'][] = $this->makeAssignment($backup, $backupShift, $dayKey, true);
                } else {
                    // Both kasir working, backup off (LIBUR sebagai backup, bukan formal libur)
                    $row['assignments'][] = $this->makeAssignment($backup, self::SHIFT_LIBUR, $dayKey, true);
                }
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    private function makeAssignment(array $employee, string $shift, string $dayKey, bool $isBackup = false): array
    {
        return [
            'employee' => $employee,
            'shift' => $shift,
            'shift_meta' => self::getShiftMeta($shift, $dayKey),
            'is_backup' => $isBackup,
        ];
    }

    private function applyCellOverrides(array $matrix, array $cells): array
    {
        foreach ($matrix as &$day) {
            $dayCells = $cells[$day['day_key']] ?? [];
            if (empty($dayCells)) {
                continue;
            }
            foreach ($day['assignments'] as &$assign) {
                $empName = $assign['employee']['name'];
                if (isset($dayCells[$empName])) {
                    $newShift = $dayCells[$empName];
                    if (in_array($newShift, [self::SHIFT_1, self::SHIFT_2, self::SHIFT_LIBUR], true)) {
                        $assign['shift'] = $newShift;
                        $assign['shift_meta'] = self::getShiftMeta($newShift, $day['day_key']);
                        $assign['is_manual'] = true;
                    }
                }
            }
            unset($assign);
        }
        unset($day);

        return $matrix;
    }

    private function calculateSummary(array $matrix, array $kasirs, ?array $backup): array
    {
        $summary = [];
        $allEmps = array_merge($kasirs, $backup ? [$backup] : []);
        foreach ($allEmps as $e) {
            $summary[$e['name']] = [
                'name' => $e['name'],
                'role' => $e['role'] ?? null,
                'shift_1_count' => 0,
                'shift_2_count' => 0,
                'libur_count' => 0,
                'libur_day' => null,
                'total_hours' => 0.0,
            ];
        }

        foreach ($matrix as $day) {
            foreach ($day['assignments'] as $a) {
                $name = $a['employee']['name'];
                if (! isset($summary[$name])) {
                    continue;
                }
                $shift = $a['shift'];
                $summary[$name]['total_hours'] += $a['shift_meta']['duration'] ?? 0;

                if ($shift === self::SHIFT_1) {
                    $summary[$name]['shift_1_count']++;
                } elseif ($shift === self::SHIFT_2) {
                    $summary[$name]['shift_2_count']++;
                } elseif ($shift === self::SHIFT_LIBUR) {
                    $summary[$name]['libur_count']++;
                    if (! $summary[$name]['libur_day']) {
                        $summary[$name]['libur_day'] = $day['day_label'];
                    }
                }
            }
        }

        return array_values($summary);
    }

    public function validate(array $matrix, array $kasirs, ?array $backup): array
    {
        $errors = [];
        $warnings = [];
        $kasirNames = array_column($kasirs, 'name');

        // Rule 1: Setiap kasir libur tepat 1×
        $liburCount = array_fill_keys($kasirNames, 0);
        foreach ($matrix as $day) {
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_LIBUR && in_array($a['employee']['name'], $kasirNames, true)) {
                    $liburCount[$a['employee']['name']]++;
                }
            }
        }
        foreach ($liburCount as $name => $count) {
            if ($count !== 1) {
                $errors[] = "$name punya $count hari libur (harusnya 1×).";
            }
        }

        // Rule 2: Libur harus Senin/Selasa
        foreach ($matrix as $day) {
            if (in_array($day['day_key'], self::WEEKEND_KEYS, true)) {
                foreach ($day['assignments'] as $a) {
                    if ($a['shift'] === self::SHIFT_LIBUR && in_array($a['employee']['name'], $kasirNames, true)) {
                        $errors[] = "{$a['employee']['name']} libur di {$day['day_label']} (harus Senin/Selasa).";
                    }
                }
            } elseif (in_array($day['day_key'], ['wednesday', 'thursday', 'friday'], true)) {
                foreach ($day['assignments'] as $a) {
                    if ($a['shift'] === self::SHIFT_LIBUR && in_array($a['employee']['name'], $kasirNames, true)) {
                        $errors[] = "{$a['employee']['name']} libur di {$day['day_label']} (harus Senin/Selasa).";
                    }
                }
            }
        }

        // Rule 3: Tidak boleh 2 kasir libur di hari yang sama
        foreach ($matrix as $day) {
            $liburKasir = 0;
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_LIBUR && in_array($a['employee']['name'], $kasirNames, true)) {
                    $liburKasir++;
                }
            }
            if ($liburKasir > 1) {
                $errors[] = "{$day['day_label']}: $liburKasir kasir libur (max 1).";
            }
        }

        // Rule 4: Setiap shift slot harus ada 1 orang
        foreach ($matrix as $day) {
            $shift1 = 0;
            $shift2 = 0;
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_1) {
                    $shift1++;
                }
                if ($a['shift'] === self::SHIFT_2) {
                    $shift2++;
                }
            }
            if ($shift1 < 1) {
                $errors[] = "{$day['day_label']}: Shift 1 kosong (tidak ada kasir).";
            }
            if ($shift2 < 1) {
                $errors[] = "{$day['day_label']}: Shift 2 kosong (tidak ada kasir).";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function validatePreferences(array $kasirs, array $prefs): array
    {
        $errors = [];

        if (count($kasirs) !== 2) {
            $errors[] = 'Butuh tepat 2 kasir.';
        }

        $liburDays = $prefs['libur_days'] ?? [];
        $usedDays = [];
        foreach ($kasirs as $k) {
            $name = $k['name'];
            $day = $liburDays[$name] ?? null;
            if (! $day) {
                $errors[] = "$name belum dipilih hari libur.";

                continue;
            }
            if (! in_array($day, self::LIBUR_OPTIONS, true)) {
                $errors[] = "$name pilih hari libur invalid ($day) — harus Senin/Selasa.";

                continue;
            }
            if (isset($usedDays[$day])) {
                $errors[] = "$name dan {$usedDays[$day]} sama-sama libur ".self::DAY_LABELS[array_search($day, self::DAY_KEYS)].".";
            } else {
                $usedDays[$day] = $name;
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function getWaiterWeekSchedule(string $waiterId, string $weekStart): ?array
    {
        $emp = $this->loadEmployees();
        $allEmps = array_merge($emp['kasirs'], $emp['backup'] ? [$emp['backup']] : []);

        $waiterEmp = null;
        foreach ($allEmps as $e) {
            if (($e['id'] ?? null) === $waiterId) {
                $waiterEmp = $e;
                break;
            }
        }
        if (! $waiterEmp) {
            return null;
        }

        // Auto-load week override (kalau ada) supaya manual edit dari admin
        // tercermin di hasil. Tanpa ini, generate() pakai default rotation
        // dan override di /kasir_schedules/{weekIso} di-ignore.
        $weekIso = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->isoFormat('GGGG-[W]WW');
        $override = $this->getWeekOverride($weekIso);

        $schedule = $this->generate($weekStart, $override);

        $today = date('Y-m-d');
        $days = [];
        $totalHours = 0.0;
        $liburDay = null;
        $shiftToday = null;

        foreach ($schedule['matrix'] as $day) {
            foreach ($day['assignments'] as $a) {
                if ($a['employee']['name'] !== $waiterEmp['name']) {
                    continue;
                }
                $isToday = $day['date'] === $today;
                $totalHours += $a['shift_meta']['duration'] ?? 0;
                if ($a['shift'] === self::SHIFT_LIBUR && ! $a['is_backup']) {
                    $liburDay = $day['day_label'];
                }
                if ($isToday) {
                    $shiftToday = [
                        'day_label' => $day['day_label'],
                        'date' => $day['date'],
                        'date_label' => $day['date_label'],
                        'shift' => $a['shift'],
                        'shift_meta' => $a['shift_meta'],
                        'is_backup' => $a['is_backup'] ?? false,
                    ];
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
                    'is_backup' => $a['is_backup'] ?? false,
                ];
                break;
            }
        }

        $isBackupRole = ($emp['backup']['id'] ?? null) === $waiterId;

        return [
            'waiter' => $waiterEmp,
            'is_backup_role' => $isBackupRole,
            'week_start' => $schedule['week_start'],
            'week_iso' => $schedule['week_iso'],
            'days' => $days,
            'total_hours' => $totalHours,
            'libur_day' => $liburDay,
            'shift_today' => $shiftToday,
            'has_override' => $schedule['is_overridden'],
        ];
    }
}
