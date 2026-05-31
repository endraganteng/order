<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * ScheduleGeneratorService
 *
 * Standalone schedule generator untuk toko retail dengan 3 karyawan.
 * Tidak terintegrasi dengan sistem shift attendance existing — purely planning tool.
 *
 * Spec rules (lihat jadwal.md):
 *  - Toko buka 06:30–21:00 setiap hari
 *  - 3 karyawan, masing-masing libur 1x/minggu
 *  - Libur dilarang Sabtu & Minggu
 *  - Senin–Rabu: 2 orang masuk (1 libur), keduanya FULL
 *  - Kamis–Minggu: 3 orang masuk = 1 FULL + 1 PAGI + 1 SORE
 *  - Rotasi 4x FULL holder per minggu (deterministic by ISO week)
 *  - Total 10 FULL slot/minggu = 1×4 + 2×3
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

    /**
     * Canonical template: E1 = 4x FULL holder.
     * E0/E1/E2 di-map ke karyawan actual via buildMapping().
     *
     *  Day:    Sen     Sel     Rab     Kam    Jum    Sab    Min
     *  E0:     LIBUR   FULL    FULL    PAGI   SORE   FULL   PAGI
     *  E1:     FULL    LIBUR   FULL    SORE   FULL   PAGI   FULL  (4x FULL: Sen,Rab,Jum,Min)
     *  E2:     FULL    FULL    LIBUR   FULL   PAGI   SORE   SORE
     */
    private const CANONICAL = [
        'E0' => ['LIBUR', 'FULL',  'FULL',  'PAGI', 'SORE', 'FULL', 'PAGI'],
        'E1' => ['FULL',  'LIBUR', 'FULL',  'SORE', 'FULL', 'PAGI', 'FULL'],
        'E2' => ['FULL',  'FULL',  'LIBUR', 'FULL', 'PAGI', 'SORE', 'SORE'],
    ];

    /**
     * Base rotation: minggu 2026-W22 = Rendy (idx 1) jadi 4x holder.
     * Rotation order: Rendy → Bagas → Anjar → repeat.
     */
    private const BASE_WEEK = '2026-W22';
    private const ROTATION_ORDER = [1, 2, 0];

    /**
     * Generate jadwal mingguan untuk roster employees.
     *
     * @param  string  $weekStart  ISO date Senin (YYYY-MM-DD)
     * @param  array<int, array{id?: string, name: string}>  $employees  3 karyawan dengan urutan tetap
     * @param  int|null  $holderIdx  Override 4x FULL holder. null = auto rotate.
     * @return array{
     *   week_start: string,
     *   week_iso: string,
     *   employees: array,
     *   holder_idx: int,
     *   holder_name: string,
     *   matrix: array,
     *   summary: array,
     *   breaks: array,
     *   validation: array
     * }
     */
    public function generate(string $weekStart, array $employees, ?int $holderIdx = null): array
    {
        if (count($employees) !== 3) {
            throw new \InvalidArgumentException('Generator butuh tepat 3 karyawan.');
        }

        $weekStartCarbon = Carbon::parse($weekStart);
        if (! $weekStartCarbon->isMonday()) {
            // Snap ke Senin minggu yang sama
            $weekStartCarbon = $weekStartCarbon->startOfWeek(Carbon::MONDAY);
        }

        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        if ($holderIdx === null) {
            $holderIdx = $this->computeHolderIdx($weekStartCarbon);
        }

        $mapping = $this->buildMapping($employees, $holderIdx);
        $matrix = $this->buildMatrix($mapping, $weekStartCarbon);
        $summary = $this->calculateSummary($matrix, $employees);
        $breaks = $this->calculateBreaks($matrix);
        $validation = $this->validate($matrix, $employees, $holderIdx);

        return [
            'week_start' => $weekStartCarbon->toDateString(),
            'week_iso' => $weekIso,
            'employees' => $employees,
            'holder_idx' => $holderIdx,
            'holder_name' => $employees[$holderIdx]['name'],
            'matrix' => $matrix,
            'summary' => $summary,
            'breaks' => $breaks,
            'validation' => $validation,
        ];
    }

    /**
     * Hitung holder index by ISO week relative to BASE_WEEK.
     */
    public function computeHolderIdx(Carbon $weekStart): int
    {
        $current = $weekStart->isoFormat('GGGG-[W]WW');
        $diff = $this->isoWeekDiff(self::BASE_WEEK, $current);
        $rotIdx = (($diff % 3) + 3) % 3; // safe modulo for negatives

        return self::ROTATION_ORDER[$rotIdx];
    }

    /**
     * Selisih minggu antara dua ISO week strings (e.g. "2026-W22" → "2026-W30" = 8).
     */
    private function isoWeekDiff(string $from, string $to): int
    {
        $fromCarbon = $this->parseIsoWeek($from);
        $toCarbon = $this->parseIsoWeek($to);

        return (int) $fromCarbon->diffInWeeks($toCarbon, false);
    }

    private function parseIsoWeek(string $iso): Carbon
    {
        // Format: "2026-W22"
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $iso, $m)) {
            throw new \InvalidArgumentException("Invalid ISO week: $iso");
        }

        return Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Map E0/E1/E2 ke karyawan actual.
     * E1 = holder. E0 = first non-holder. E2 = second non-holder.
     */
    private function buildMapping(array $employees, int $holderIdx): array
    {
        $holder = $employees[$holderIdx];
        $others = [];
        foreach ($employees as $i => $emp) {
            if ($i !== $holderIdx) {
                $others[] = $emp;
            }
        }

        return ['E0' => $others[0], 'E1' => $holder, 'E2' => $others[1]];
    }

    /**
     * Build matrix: array of 7 days, each day has 3 employee assignments.
     */
    private function buildMatrix(array $mapping, Carbon $weekStart): array
    {
        $matrix = [];
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

            foreach (['E0', 'E1', 'E2'] as $slot) {
                $shift = self::CANONICAL[$slot][$dayIdx];
                $emp = $mapping[$slot];
                $row['assignments'][] = [
                    'employee' => $emp,
                    'slot' => $slot,
                    'shift' => $shift,
                    'shift_meta' => self::SHIFTS[$shift],
                ];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * Hitung total full shift, partial shift, dan total jam per karyawan.
     */
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
                $duration = $assign['shift_meta']['duration'];

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

    /**
     * Jadwal istirahat:
     *  - Senin–Rabu: fleksibel di toko (cuma 2 orang)
     *  - Kamis–Minggu: 3 slot bergiliran 12:00–15:00
     */
    private function calculateBreaks(array $matrix): array
    {
        $breakSlots = ['12:00–13:00', '13:00–14:00', '14:00–15:00'];
        $breaks = [];

        foreach ($matrix as $day) {
            $isThreePersonDay = ! in_array($day['day_key'], ['monday', 'tuesday', 'wednesday'], true);

            if ($isThreePersonDay) {
                // Find PAGI, FULL, SORE workers (skip LIBUR — won't occur on Thu-Sun)
                $byShift = [];
                foreach ($day['assignments'] as $a) {
                    $byShift[$a['shift']] = $a['employee']['name'];
                }

                $breaks[] = [
                    'day_label' => $day['day_label'],
                    'date_label' => $day['date_label'],
                    'mode' => 'rotation',
                    'note' => null,
                    'slots' => [
                        ['time' => $breakSlots[0], 'employee' => $byShift[self::SHIFT_PAGI] ?? '-'],
                        ['time' => $breakSlots[1], 'employee' => $byShift[self::SHIFT_FULL] ?? '-'],
                        ['time' => $breakSlots[2], 'employee' => $byShift[self::SHIFT_SORE] ?? '-'],
                    ],
                ];
            } else {
                $workers = [];
                foreach ($day['assignments'] as $a) {
                    if ($a['shift'] !== self::SHIFT_LIBUR) {
                        $workers[] = $a['employee']['name'];
                    }
                }

                $breaks[] = [
                    'day_label' => $day['day_label'],
                    'date_label' => $day['date_label'],
                    'mode' => 'flex',
                    'note' => 'Istirahat fleksibel di toko, makan bergantian, tidak boleh meninggalkan area.',
                    'workers' => $workers,
                    'slots' => [],
                ];
            }
        }

        return $breaks;
    }

    /**
     * Validate jadwal terhadap semua rules di spec.
     *
     * @return array{valid: bool, errors: array, warnings: array}
     */
    public function validate(array $matrix, array $employees, int $holderIdx): array
    {
        $errors = [];
        $warnings = [];

        // Rule 1: Setiap karyawan libur tepat 1x/minggu
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
                $errors[] = "$name punya $count hari libur (harusnya tepat 1x).";
            }
        }

        // Rule 2: Sabtu & Minggu — semua karyawan masuk (no LIBUR)
        foreach ($matrix as $day) {
            if (in_array($day['day_key'], ['saturday', 'sunday'], true)) {
                foreach ($day['assignments'] as $a) {
                    if ($a['shift'] === self::SHIFT_LIBUR) {
                        $errors[] = "{$a['employee']['name']} libur di {$day['day_label']} (dilarang Sabtu/Minggu).";
                    }
                }
            }
        }

        // Rule 3: Tidak ada 2 karyawan libur di hari yang sama
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

        // Rule 4: Minimal 2 orang masuk per hari
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

        // Rule 5: Jika 3 orang masuk, harus 1 FULL + 1 PAGI + 1 SORE
        foreach ($matrix as $day) {
            $shiftCounts = [self::SHIFT_FULL => 0, self::SHIFT_PAGI => 0, self::SHIFT_SORE => 0, self::SHIFT_LIBUR => 0];
            foreach ($day['assignments'] as $a) {
                $shiftCounts[$a['shift']]++;
            }
            $working = 3 - $shiftCounts[self::SHIFT_LIBUR];
            if ($working === 3) {
                if ($shiftCounts[self::SHIFT_FULL] !== 1 || $shiftCounts[self::SHIFT_PAGI] !== 1 || $shiftCounts[self::SHIFT_SORE] !== 1) {
                    $errors[] = "{$day['day_label']}: 3 orang masuk tapi distribusi shift bukan FULL+PAGI+SORE.";
                }
            } elseif ($working === 2) {
                if ($shiftCounts[self::SHIFT_FULL] !== 2) {
                    $errors[] = "{$day['day_label']}: 2 orang masuk harusnya keduanya FULL (sekarang {$shiftCounts[self::SHIFT_FULL]} FULL).";
                }
            }
        }

        // Rule 6: Coverage 06:30–21:00 minimal 2 orang
        $coverageIssues = $this->checkCoverage($matrix);
        $errors = array_merge($errors, $coverageIssues);

        // Rule 7: Holder dapat tepat 4x FULL
        $holderName = $employees[$holderIdx]['name'];
        $holderFullCount = 0;
        $othersFullCount = [];
        foreach ($matrix as $day) {
            foreach ($day['assignments'] as $a) {
                if ($a['shift'] === self::SHIFT_FULL) {
                    if ($a['employee']['name'] === $holderName) {
                        $holderFullCount++;
                    } else {
                        $othersFullCount[$a['employee']['name']] = ($othersFullCount[$a['employee']['name']] ?? 0) + 1;
                    }
                }
            }
        }
        if ($holderFullCount !== 4) {
            $errors[] = "Holder $holderName dapat $holderFullCount FULL (harusnya 4x).";
        }
        foreach ($othersFullCount as $name => $count) {
            if ($count !== 3) {
                $warnings[] = "$name dapat $count FULL (idealnya 3x untuk non-holder).";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Cek apakah toko ter-cover minimal 2 orang dari 06:30 sampai 21:00 setiap hari.
     * Pakai 30-min slots.
     */
    private function checkCoverage(array $matrix): array
    {
        $errors = [];
        // Operating hours: 06:30 → 21:00, 30-min slots = 29 slots
        // Tapi cukup cek titik kritis: 06:30 (PAGI/FULL only), 12:00 (SORE join), 15:30 (PAGI ends), 21:00 (end)
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
}
