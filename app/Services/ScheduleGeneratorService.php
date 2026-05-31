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
     * @param  array  $preferences  ['libur_days' => [empName => dayKey], 'holder_name' => string|null]
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
        $holderIdx = $this->findEmployeeIdxByName($employees, $holderName);

        $matrix = $this->buildMatrix($weekStartCarbon, $employees, $liburDays, $holderName);

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

        $holderName = $prefs['holder_name'] ?? null;
        if (! $holderName || ! in_array($holderName, $names, true)) {
            $holderIdx = $this->computeRotationHolderIdx($weekStart);
            $holderName = $names[$holderIdx];
        }

        return [
            'libur_days' => $liburDays,
            'holder_name' => $holderName,
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

    private function buildMatrix(Carbon $weekStart, array $employees, array $liburDays, string $holderName): array
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

        return $this->distributeThreePersonDays($matrix, $threePersonDayIndices, $employees, $holderName);
    }

    /**
     * 4 days × 3 persons. Holder 4× FULL? Tidak realistic — holder 2× FULL on 3-person + 2× FULL on 2-person = 4 total.
     * Pattern di 4 three-person days:
     *   Day 0: holder=FULL,  nonH1=PAGI,  nonH2=SORE
     *   Day 1: holder=PAGI,  nonH1=SORE,  nonH2=FULL  (nonH2 dapat 1 FULL)
     *   Day 2: holder=FULL,  nonH1=SORE,  nonH2=PAGI
     *   Day 3: holder=SORE,  nonH1=FULL,  nonH2=PAGI  (nonH1 dapat 1 FULL)
     *
     * Hitungan FULL across 4 three-person days:
     *   - Holder: 2× FULL
     *   - nonH1:  1× FULL
     *   - nonH2:  1× FULL
     *
     * Kombinasi dengan 2-person days (3 days, 1 untuk masing-masing libur):
     *   - Holder libur 0× → masuk 3× → 3× FULL
     *   - nonH1 libur 1× → masuk 2× → 2× FULL
     *   - nonH2 libur 1× → masuk 2× → 2× FULL
     *
     * Holder = 0× libur (karena holder = E1 yang non-libur). Wait — holder bisa libur juga.
     * Re-think: holder bisa siapa saja, juga libur 1× per minggu.
     *
     * Kalau holder libur, dia masuk 6 hari = 4× FULL (3 dari 2-person + 1 dari 3-person).
     * Kalau holder libur Senin, maka 2-person days = Sel libur nonH1, Rab libur nonH2.
     * Holder masuk: Sel, Rab (FULL), Kam-Min (3-person = 2× FULL + 1× lain).
     */
    private function distributeThreePersonDays(array $matrix, array $threePersonIndices, array $employees, string $holderName): array
    {
        $nonHolders = [];
        foreach ($employees as $e) {
            if ($e['name'] !== $holderName) {
                $nonHolders[] = $e;
            }
        }

        if (count($nonHolders) !== 2) {
            return $matrix;
        }

        // Hitung berapa kali holder & non-holder masuk di 2-person days
        $holderTwoPersonCount = 0;
        $nonH1TwoPersonCount = 0;
        $nonH2TwoPersonCount = 0;

        foreach ($matrix as $day) {
            // Skip 3-person days (assignments belum diisi)
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
                if ($a['shift'] === self::SHIFT_FULL) {
                    if ($a['employee']['name'] === $holderName) {
                        $holderTwoPersonCount++;
                    } elseif ($a['employee']['name'] === $nonHolders[0]['name']) {
                        $nonH1TwoPersonCount++;
                    } else {
                        $nonH2TwoPersonCount++;
                    }
                }
            }
        }

        // Target: holder total 4× FULL across whole week.
        // Holder needs: 4 - holderTwoPersonCount more FULL on 3-person days.
        $holderTargetFull = max(0, 4 - $holderTwoPersonCount);
        $threePersonCount = count($threePersonIndices);

        // Build patterns dynamically
        // Holder gets FULL on first $holderTargetFull three-person days, then alternates PAGI/SORE
        // Non-holders share remaining FULL slots and PAGI/SORE
        foreach ($threePersonIndices as $i => $dayIdx) {
            // Determine holder's shift for this 3-person day
            if ($i < $holderTargetFull) {
                $holderShift = self::SHIFT_FULL;
            } elseif (($i - $holderTargetFull) % 2 === 0) {
                $holderShift = self::SHIFT_PAGI;
            } else {
                $holderShift = self::SHIFT_SORE;
            }

            // Non-holders take remaining slots
            // Each 3-person day must have 1 FULL + 1 PAGI + 1 SORE
            $remainingShifts = [self::SHIFT_FULL, self::SHIFT_PAGI, self::SHIFT_SORE];
            $remainingShifts = array_values(array_diff($remainingShifts, [$holderShift]));
            // remainingShifts has 2 items

            // Assign non-holders alternating to balance
            // Strategy: nonH1 takes first remaining on even-index days, nonH2 takes second
            if ($i % 2 === 0) {
                $nonH1Shift = $remainingShifts[0];
                $nonH2Shift = $remainingShifts[1];
            } else {
                $nonH1Shift = $remainingShifts[1];
                $nonH2Shift = $remainingShifts[0];
            }

            foreach ($matrix[$dayIdx]['assignments'] as $aIdx => $assign) {
                $empName = $assign['employee']['name'];
                if ($empName === $holderName) {
                    $shift = $holderShift;
                } elseif ($empName === $nonHolders[0]['name']) {
                    $shift = $nonH1Shift;
                } else {
                    $shift = $nonH2Shift;
                }
                $matrix[$dayIdx]['assignments'][$aIdx]['shift'] = $shift;
                $matrix[$dayIdx]['assignments'][$aIdx]['shift_meta'] = self::SHIFTS[$shift];
            }
        }

        return $matrix;
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
