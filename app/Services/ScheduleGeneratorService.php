<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * ScheduleGeneratorService
 *
 * Generator jadwal toko retail untuk 3 karyawan, support full customization:
 * - Setiap karyawan bisa pilih hari libur (Sen/Sel/Rab/Kam/Jum)
 * - Holder 4× FULL bisa di-pick manual atau auto rotate
 * - Per-week override (manual edit cell)
 *
 * Rules dijaga oleh validator:
 *  - Toko buka 06:30–21:00 setiap hari
 *  - Min 2 orang masuk per hari
 *  - Setiap orang libur tepat 1× per minggu
 *  - Libur dilarang Sat/Sun
 *  - 3 libur days harus berbeda (max 1 orang libur per hari)
 *  - 2-person day: keduanya FULL
 *  - 3-person day: 1 FULL + 1 PAGI + 1 SORE
 *  - Holder dapat 4× FULL total
 */
class ScheduleGeneratorService
{
    public const SHIFT_FULL = 'FULL';
    public const SHIFT_PAGI = 'PAGI';
    public const SHIFT_SORE = 'SORE';
    public const SHIFT_LIBUR = 'LIBUR';

    public const SHIFTS = [
        self::SHIFT_FULL => ['start' => '06:30', 'end' => '21:00', 'duration' => 14.5, 'label' => 'Full Shift'],
        self::SHIFT_PAGI => ['start' => '06:30', 'end' => '15:30', 'duration' => 9.0, 'label' => 'Shift Pagi'],
        self::SHIFT_SORE => ['start' => '12:00', 'end' => '21:00', 'duration' => 9.0, 'label' => 'Shift Sore'],
        self::SHIFT_LIBUR => ['start' => null, 'end' => null, 'duration' => 0.0, 'label' => 'Libur'],
    ];

    public const DAY_LABELS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    public const DAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    public const WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    /** Base rotation week: 2026-W22 = Rendy (idx 1). */
    private const BASE_WEEK = '2026-W22';
    private const ROTATION_ORDER = [1, 2, 0]; // Rendy → Bagas → Anjar

    /**
     * Generate jadwal mingguan dengan preferences + optional override.
     *
     * @param  string  $weekStart  ISO date Senin (YYYY-MM-DD)
     * @param  array<int, array{id?: ?string, name: string}>  $employees  3 karyawan
     * @param  array  $preferences  ['libur_days' => [empName => dayKey], 'holder_name' => string|null, 'shift_modes' => [empName => mode]]
     * @param  array|null  $override  Per-week matrix override
     */
    public function generate(string $weekStart, array $employees, array $preferences = [], ?array $override = null): array
    {
        if (count($employees) !== 3) {
            throw new \InvalidArgumentException('Generator butuh tepat 3 karyawan.');
        }

        $weekStartCarbon = Carbon::parse($weekStart);
        if (! $weekStartCarbon->isMonday()) {
            $weekStartCarbon = $weekStartCarbon->startOfWeek(Carbon::MONDAY);
        }
        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        $resolved = $this->resolvePreferences($employees, $preferences, $weekStartCarbon);
        $liburDays = $resolved['libur_days'];
        $holderName = $resolved['holder_name'];
        $shiftModes = $resolved['shift_modes'];
        $holderIdx = $this->findEmployeeIdxByName($employees, $holderName);

        $matrix = $this->buildMatrix($weekStartCarbon, $employees, $liburDays, $holderName, $shiftModes);

        if (is_array($override) && isset($override['cells'])) {
            $matrix = $this->applyCellOverrides($matrix, $override['cells']);
        }

        $summary = $this->calculateSummary($matrix, $employees);
        $breaks = $this->calculateBreaks($matrix);
        $validation = $this->validate($matrix, $employees, $holderName);

        return [
            'week_start' => $weekStartCarbon->toDateString(),
            'week_iso' => $weekIso,
            'employees' => $employees,
            'preferences' => $resolved,
            'holder_idx' => $holderIdx,
            'holder_name' => $holderName,
            'libur_days' => $liburDays,
            'shift_modes' => $shiftModes,
            'matrix' => $matrix,
            'summary' => $summary,
            'breaks' => $breaks,
            'validation' => $validation,
            'is_overridden' => $override !== null,
        ];
    }

    private function resolvePreferences(array $employees, array $prefs, Carbon $weekStart): array
    {
        $names = array_column($employees, 'name');

        $defaultLibur = [
            $names[0] => 'monday',
            $names[1] => 'tuesday',
            $names[2] => 'wednesday',
        ];

        $liburDays = $prefs['libur_days'] ?? $defaultLibur;
        foreach ($names as $name) {
            if (! isset($liburDays[$name])) {
                $liburDays[$name] = $defaultLibur[$name] ?? 'monday';
            }
        }

        // Shift mode per employee: 'default' | 'prefer_full' | 'prefer_short'
        $shiftModes = $prefs['shift_modes'] ?? [];
        foreach ($names as $name) {
            if (! isset($shiftModes[$name]) || ! in_array($shiftModes[$name], ['default', 'prefer_full', 'prefer_short'], true)) {
                $shiftModes[$name] = 'default';
            }
        }

        $holderName = $prefs['holder_name'] ?? null;
        if (! $holderName || ! in_array($holderName, $names, true)) {
            $holderIdx = $this->computeRotationHolderIdx($weekStart);
            $holderName = $names[$holderIdx];
        }

        return [
            'libur_days' => $liburDays,
            'holder_name' => $holderName,
            'shift_modes' => $shiftModes,
        ];
    }

    public function computeRotationHolderIdx(Carbon $weekStart): int
    {
        $current = $weekStart->isoFormat('GGGG-[W]WW');
        $diff = $this->isoWeekDiff(self::BASE_WEEK, $current);
        $rotIdx = (($diff % 3) + 3) % 3;

        return self::ROTATION_ORDER[$rotIdx];
    }

    private function isoWeekDiff(string $from, string $to): int
    {
        $f = $this->parseIsoWeek($from);
        $t = $this->parseIsoWeek($to);

        return (int) $f->diffInWeeks($t, false);
    }

    private function parseIsoWeek(string $iso): Carbon
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $iso, $m)) {
            throw new \InvalidArgumentException("Invalid ISO week: $iso");
        }

        return Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek(Carbon::MONDAY);
    }

    private function findEmployeeIdxByName(array $employees, string $name): int
    {
        foreach ($employees as $i => $e) {
            if ($e['name'] === $name) {
                return $i;
            }
        }

        return 0;
    }

    private function buildMatrix(Carbon $weekStart, array $employees, array $liburDays, string $holderName, array $shiftModes = []): array
    {
        $matrix = [];
        $threePersonDayIndices = [];

        foreach (self::DAY_KEYS as $dayIdx => $dayKey) {
            $date = $weekStart->copy()->addDays($dayIdx);
            $row = [
                'day_key' => $dayKey,
                'day_label' => self::DAY_LABELS[$dayIdx],
                'date' => $date->toDateString(),
                'date_label' => $date->isoFormat('D MMM'),
                'is_weekend' => in_array($dayKey, ['saturday', 'sunday'], true),
                'assignments' => [],
            ];

            $liburEmployees = [];
            foreach ($employees as $e) {
                if (($liburDays[$e['name']] ?? null) === $dayKey) {
                    $liburEmployees[] = $e['name'];
                }
            }

            if (count($liburEmployees) > 0) {
                foreach ($employees as $e) {
                    $isLibur = in_array($e['name'], $liburEmployees, true);
                    $shift = $isLibur ? self::SHIFT_LIBUR : self::SHIFT_FULL;
                    $row['assignments'][] = [
                        'employee' => $e,
                        'shift' => $shift,
                        'shift_meta' => self::SHIFTS[$shift],
                    ];
                }
            } else {
                foreach ($employees as $e) {
                    $row['assignments'][] = [
                        'employee' => $e,
                        'shift' => null,
                        'shift_meta' => null,
                    ];
                }
                $threePersonDayIndices[] = $dayIdx;
            }

            $matrix[] = $row;
        }

        return $this->distributeThreePersonDays($matrix, $threePersonDayIndices, $employees, $holderName, $shiftModes);
    }

    /**
     * Distribute shifts pada 3-person days dengan considerasi shift_modes.
     *
     * Shift modes (per employee):
     *   - 'default'      : ikut aturan distribusi normal
     *   - 'prefer_full'  : selalu FULL pada 3-person days
     *   - 'prefer_short' : selalu PAGI/SORE (rotasi)
     */
    private function distributeThreePersonDays(array $matrix, array $threePersonIndices, array $employees, string $holderName, array $shiftModes = []): array
    {
        if (count($threePersonIndices) === 0) {
            return $matrix;
        }

        $names = array_column($employees, 'name');

        // Default mode kalau belum di-set
        foreach ($names as $n) {
            if (! isset($shiftModes[$n])) {
                $shiftModes[$n] = 'default';
            }
        }

        // Identifikasi prefer_full employees (max 1 yang akan FULL setiap 3-person day)
        $preferFullNames = array_values(array_filter($names, fn ($n) => $shiftModes[$n] === 'prefer_full'));
        $preferShortNames = array_values(array_filter($names, fn ($n) => $shiftModes[$n] === 'prefer_short'));
        $defaultNames = array_values(array_filter($names, fn ($n) => $shiftModes[$n] === 'default'));

        // Kalau ada lebih dari 1 prefer_full, pertahankan first sebagai full, sisanya jadi default (warning di validate)
        $primaryFull = $preferFullNames[0] ?? null;

        // Distribute pattern: alternate PAGI/SORE between non-FULL employees
        $pagiToggle = 0;

        foreach ($threePersonIndices as $i => $dayIdx) {
            $assignments = [];

            // Step 1: Tentukan siapa FULL hari ini
            $fullName = null;
            if ($primaryFull) {
                $fullName = $primaryFull;
            } elseif (! empty($defaultNames)) {
                // Default: holder gets FULL on first half, alternate
                if (in_array($holderName, $defaultNames, true)) {
                    $holderTwoPersonCount = $this->countTwoPersonFull($matrix, $holderName);
                    $holderTargetFull = max(0, 4 - $holderTwoPersonCount);
                    if ($i < $holderTargetFull) {
                        $fullName = $holderName;
                    }
                }

                if (! $fullName) {
                    // Round-robin among default employees
                    $candidates = array_values(array_filter($defaultNames, fn ($n) => $n !== $holderName));
                    if (! empty($candidates)) {
                        $fullName = $candidates[$i % count($candidates)];
                    } else {
                        $fullName = $defaultNames[$i % count($defaultNames)];
                    }
                }
            } else {
                // Semua prefer_short — tetap perlu 1 FULL untuk coverage
                $fullName = $names[$i % count($names)];
            }

            // Step 2: Sisa 2 orang dapat PAGI/SORE
            $remainingNames = array_values(array_filter($names, fn ($n) => $n !== $fullName));
            // Untuk balance, alternate siapa PAGI / SORE setiap 3-person day
            if ($pagiToggle % 2 === 0) {
                $pagiName = $remainingNames[0] ?? null;
                $soreName = $remainingNames[1] ?? null;
            } else {
                $pagiName = $remainingNames[1] ?? null;
                $soreName = $remainingNames[0] ?? null;
            }
            $pagiToggle++;

            // Step 3: Apply assignments
            foreach ($matrix[$dayIdx]['assignments'] as $aIdx => $assign) {
                $empName = $assign['employee']['name'];
                if ($empName === $fullName) {
                    $shift = self::SHIFT_FULL;
                } elseif ($empName === $pagiName) {
                    $shift = self::SHIFT_PAGI;
                } elseif ($empName === $soreName) {
                    $shift = self::SHIFT_SORE;
                } else {
                    $shift = self::SHIFT_PAGI;
                }
                $matrix[$dayIdx]['assignments'][$aIdx]['shift'] = $shift;
                $matrix[$dayIdx]['assignments'][$aIdx]['shift_meta'] = self::SHIFTS[$shift];
            }
        }

        return $matrix;
    }

    /**
     * Hitung berapa kali employee dapat FULL di 2-person days.
     */
    private function countTwoPersonFull(array $matrix, string $employeeName): int
    {
        $count = 0;
        foreach ($matrix as $day) {
            $isThreePerson = false;
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === null) {
                    $isThreePerson = true;
                    break;
                }
            }
            if ($isThreePerson) {
                continue;
            }
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_FULL && $a['employee']['name'] === $employeeName) {
                    $count++;
                }
            }
        }

        return $count;
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
                    if (isset(self::SHIFTS[$newShift])) {
                        $assign['shift'] = $newShift;
                        $assign['shift_meta'] = self::SHIFTS[$newShift];
                        $assign['is_manual'] = true;
                    }
                }
            }
            unset($assign);
        }
        unset($day);

        return $matrix;
    }

    private function calculateSummary(array $matrix, array $employees): array
    {
        $summary = [];
        foreach ($employees as $emp) {
            $summary[$emp['name']] = [
                'name' => $emp['name'],
                'full_count' => 0,
                'pagi_count' => 0,
                'sore_count' => 0,
                'libur_count' => 0,
                'libur_day' => null,
                'total_hours' => 0.0,
            ];
        }

        foreach ($matrix as $day) {
            foreach ($day['assignments'] as $assign) {
                $name = $assign['employee']['name'];
                $shift = $assign['shift'];
                $duration = $assign['shift_meta']['duration'] ?? 0;

                $summary[$name]['total_hours'] += $duration;

                if ($shift === self::SHIFT_FULL) {
                    $summary[$name]['full_count']++;
                } elseif ($shift === self::SHIFT_PAGI) {
                    $summary[$name]['pagi_count']++;
                } elseif ($shift === self::SHIFT_SORE) {
                    $summary[$name]['sore_count']++;
                } elseif ($shift === self::SHIFT_LIBUR) {
                    $summary[$name]['libur_count']++;
                    $summary[$name]['libur_day'] = $day['day_label'];
                }
            }
        }

        return array_values($summary);
    }

    private function calculateBreaks(array $matrix): array
    {
        $breakSlots = ['12:00–13:00', '13:00–14:00', '14:00–15:00'];
        $breaks = [];

        foreach ($matrix as $day) {
            $byShift = [];
            $working = [];
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] !== self::SHIFT_LIBUR) {
                    $working[] = $a['employee']['name'];
                    $byShift[$a['shift']] = $a['employee']['name'];
                }
            }

            $isThreePerson = count($working) === 3;
            $hasOverlap = isset($byShift[self::SHIFT_PAGI]) && isset($byShift[self::SHIFT_SORE]) && isset($byShift[self::SHIFT_FULL]);

            if ($isThreePerson && $hasOverlap) {
                $breaks[] = [
                    'day_label' => $day['day_label'],
                    'date_label' => $day['date_label'],
                    'mode' => 'rotation',
                    'note' => null,
                    'slots' => [
                        ['time' => $breakSlots[0], 'employee' => $byShift[self::SHIFT_PAGI]],
                        ['time' => $breakSlots[1], 'employee' => $byShift[self::SHIFT_FULL]],
                        ['time' => $breakSlots[2], 'employee' => $byShift[self::SHIFT_SORE]],
                    ],
                ];
            } else {
                $breaks[] = [
                    'day_label' => $day['day_label'],
                    'date_label' => $day['date_label'],
                    'mode' => 'flex',
                    'note' => 'Istirahat fleksibel di toko, makan bergantian, tidak boleh meninggalkan area.',
                    'workers' => $working,
                    'slots' => [],
                ];
            }
        }

        return $breaks;
    }

    public function validate(array $matrix, array $employees, string $holderName): array
    {
        $errors = [];
        $warnings = [];

        // Rule 1: Setiap karyawan libur tepat 1×
        $liburCount = array_fill_keys(array_column($employees, 'name'), 0);
        foreach ($matrix as $day) {
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_LIBUR) {
                    $liburCount[$a['employee']['name']]++;
                }
            }
        }
        foreach ($liburCount as $name => $count) {
            if ($count !== 1) {
                $errors[] = "$name punya $count hari libur (harusnya tepat 1×).";
            }
        }

        // Rule 2: Sat/Sun no LIBUR
        foreach ($matrix as $day) {
            if (in_array($day['day_key'], ['saturday', 'sunday'], true)) {
                foreach ($day['assignments'] as $a) {
                    if ($a['shift'] === self::SHIFT_LIBUR) {
                        $errors[] = "{$a['employee']['name']} libur di {$day['day_label']} (dilarang Sabtu/Minggu).";
                    }
                }
            }
        }

        // Rule 3: Max 1 libur per hari
        foreach ($matrix as $day) {
            $liburToday = 0;
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_LIBUR) {
                    $liburToday++;
                }
            }
            if ($liburToday > 1) {
                $errors[] = "{$day['day_label']}: $liburToday orang libur (max 1).";
            }
        }

        // Rule 4: Min 2 orang masuk
        foreach ($matrix as $day) {
            $working = 0;
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] !== self::SHIFT_LIBUR) {
                    $working++;
                }
            }
            if ($working < 2) {
                $errors[] = "{$day['day_label']}: hanya $working orang masuk (min 2).";
            }
        }

        // Rule 5: Distribusi shift
        foreach ($matrix as $day) {
            $shiftCounts = [self::SHIFT_FULL => 0, self::SHIFT_PAGI => 0, self::SHIFT_SORE => 0, self::SHIFT_LIBUR => 0];
            foreach ($day['assignments'] as $a) {
                $shiftCounts[$a['shift']]++;
            }
            $working = 3 - $shiftCounts[self::SHIFT_LIBUR];
            if ($working === 3) {
                if ($shiftCounts[self::SHIFT_FULL] !== 1 || $shiftCounts[self::SHIFT_PAGI] !== 1 || $shiftCounts[self::SHIFT_SORE] !== 1) {
                    $warnings[] = "{$day['day_label']}: 3 orang masuk tapi distribusi shift bukan FULL+PAGI+SORE (manual edit?).";
                }
            } elseif ($working === 2) {
                if ($shiftCounts[self::SHIFT_FULL] !== 2) {
                    $warnings[] = "{$day['day_label']}: 2 orang masuk idealnya keduanya FULL (sekarang {$shiftCounts[self::SHIFT_FULL]} FULL).";
                }
            }
        }

        // Rule 6: Coverage
        $errors = array_merge($errors, $this->checkCoverage($matrix));

        // Rule 7: Holder ~ 4× FULL (warning only)
        $holderFullCount = 0;
        foreach ($matrix as $day) {
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_FULL && $a['employee']['name'] === $holderName) {
                    $holderFullCount++;
                }
            }
        }
        if ($holderFullCount !== 4) {
            $warnings[] = "Holder $holderName dapat $holderFullCount FULL (idealnya 4×).";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function checkCoverage(array $matrix): array
    {
        $errors = [];
        $criticalSlots = [
            '06:30' => ['FULL', 'PAGI'],
            '12:00' => ['FULL', 'PAGI', 'SORE'],
            '15:30' => ['FULL', 'SORE'],
            '20:30' => ['FULL', 'SORE'],
        ];

        foreach ($matrix as $day) {
            foreach ($criticalSlots as $time => $allowedShifts) {
                $count = 0;
                foreach ($day['assignments'] as $a) {
                    if (in_array($a['shift'], $allowedShifts, true)) {
                        $count++;
                    }
                }
                if ($count < 2) {
                    $errors[] = "{$day['day_label']} jam $time: hanya $count orang on duty (min 2).";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate preferences sebelum simpan.
     */
    public function validatePreferences(array $employees, array $prefs): array
    {
        $errors = [];
        $names = array_column($employees, 'name');

        $liburDays = $prefs['libur_days'] ?? [];

        $usedDays = [];
        foreach ($names as $name) {
            $day = $liburDays[$name] ?? null;
            if (! $day) {
                $errors[] = "$name belum dipilih hari libur.";

                continue;
            }
            if (! in_array($day, self::WEEKDAY_KEYS, true)) {
                $errors[] = "$name pilih hari libur invalid ($day) — harus Senin–Jumat.";

                continue;
            }
            if (isset($usedDays[$day])) {
                $errors[] = "Tabrakan: $name dan {$usedDays[$day]} sama-sama libur ".self::DAY_LABELS[array_search($day, self::DAY_KEYS)].".";
            } else {
                $usedDays[$day] = $name;
            }
        }

        if (! empty($prefs['holder_name']) && ! in_array($prefs['holder_name'], $names, true)) {
            $errors[] = "Holder name '{$prefs['holder_name']}' bukan karyawan yang valid.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
