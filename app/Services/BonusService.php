<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Exception\Database\TransactionFailed;

class BonusService
{
    protected FirebaseService $firebase;

    protected Database $database;

    public function __construct(FirebaseService $firebase, Database $database)
    {
        $this->firebase = $firebase;
        $this->database = $database;
    }

    // =========================================================================
    //  CONFIG
    // =========================================================================

    /**
     * Get bonus configuration from Firebase, falling back to defaults.
     * Uses deep merge so newly-added categories (e.g. rack_recheck) survive
     * even when Firebase has older config.
     */
    public function getBonusConfig(): array
    {
        $snapshot = $this->database->getReference('bonus_config')->getSnapshot();
        $defaults = $this->getDefaultConfig();

        if (! $snapshot->exists()) {
            return $defaults;
        }

        $stored = (array) $snapshot->getValue();

        // Deep merge: ensure stored point_categories preserved, but missing keys
        // (like newly-added 'rack_recheck') filled from defaults.
        $merged = array_merge($defaults, $stored);
        if (isset($defaults['point_categories']) && is_array($defaults['point_categories'])) {
            $storedCats = isset($stored['point_categories']) && is_array($stored['point_categories'])
                ? $stored['point_categories']
                : [];
            // Stored categories override defaults, but missing categories are added from defaults.
            $merged['point_categories'] = $storedCats + $defaults['point_categories'];
        }

        return $merged;
    }

    /**
     * Return hardcoded default configuration.
     */
    public function getDefaultConfig(): array
    {
        return [
            'is_active' => true,
            'working_days_per_month' => 26,
            'total_bonus_pool' => 500000,
            'perfect_day_bonus' => 5,
            'daily_max_points' => 30,

            'point_categories' => [
                'discipline'   => ['name' => 'Disiplin', 'max_daily_points' => 5, 'sort_order' => 1, 'scoring_type' => 'daily'],
                'operational'  => ['name' => 'Operasional', 'max_daily_points' => 10, 'sort_order' => 2, 'scoring_type' => 'daily'],
                'service'      => ['name' => 'Pelayanan', 'max_daily_points' => 5, 'sort_order' => 3, 'scoring_type' => 'monthly'],
                'sales'        => ['name' => 'Penjualan', 'max_daily_points' => 5, 'sort_order' => 4, 'scoring_type' => 'monthly'],
                'attitude'     => ['name' => 'Sikap', 'max_daily_points' => 5, 'sort_order' => 5, 'scoring_type' => 'daily'],
                'rack_recheck' => ['name' => 'Recheck Rak', 'max_daily_points' => 10, 'sort_order' => 6, 'scoring_type' => 'daily'],
            ],

            'penalty_types' => [
                'late_arrival'          => ['label' => 'Terlambat masuk', 'points' => -5],
                'absent'                => ['label' => 'Tidak hadir / no-show', 'points' => -15],
                'mandatory_task_missed' => ['label' => 'Tugas wajib tidak dikerjakan', 'points' => -10],
                'careless_work'         => ['label' => 'Tugas dikerjakan asal-asalan', 'points' => -10],
                'missing_photo_proof'   => ['label' => 'Bukti foto tidak ada', 'points' => -5],
                'valid_complaint'       => ['label' => 'Komplain pelanggan valid', 'points' => -10],
            ],

            'point_bonus_tiers' => [
                'tier_1' => ['min_percentage' => 80, 'bonus_amount' => 300000],
                'tier_2' => ['min_percentage' => 70, 'bonus_amount' => 250000],
                'tier_3' => ['min_percentage' => 60, 'bonus_amount' => 200000],
                'tier_4' => ['min_percentage' => 0,  'bonus_amount' => 0],
            ],

            'sales_bonus_tiers' => [
                'tier_1' => ['min_percentage' => 100, 'bonus_amount' => 200000],
                'tier_2' => ['min_percentage' => 80,  'bonus_amount' => 150000],
                'tier_3' => ['min_percentage' => 60,  'bonus_amount' => 100000],
                'tier_4' => ['min_percentage' => 0,   'bonus_amount' => 0],
            ],

            'sales_target_roles' => ['bird_specialist', 'fishing_specialist'],
        ];
    }

    /**
     * Get the configured max points for a category.
     */
    protected function getCategoryMaxPoints(array $config, string $categoryKey, int $default = 0): int
    {
        return (int) ($config['point_categories'][$categoryKey]['max_daily_points'] ?? $default);
    }

    /**
     * Build the canonical monthly points capacity breakdown.
     */
    public function getMonthlyPointsCapacity(?array $config = null): array
    {
        $config ??= $this->getBonusConfig();

        $workingDays = (int) ($config['working_days_per_month'] ?? 26);
        $dailyMaxPoints = (int) ($config['daily_max_points'] ?? 20);
        $perfectDayBonus = (int) ($config['perfect_day_bonus'] ?? 5);
        $dailyMaxWithPerfect = $dailyMaxPoints + $perfectDayBonus;
        $monthlyServiceMaxPerDay = $this->getCategoryMaxPoints($config, 'service', 5);
        $monthlySalesMaxPerDay = $this->getCategoryMaxPoints($config, 'sales', 5);
        $monthlyServiceMax = $monthlyServiceMaxPerDay * $workingDays;
        $monthlySalesMax = $monthlySalesMaxPerDay * $workingDays;

        return [
            'working_days' => $workingDays,
            'daily_max_points' => $dailyMaxPoints,
            'perfect_day_bonus' => $perfectDayBonus,
            'daily_max_with_perfect' => $dailyMaxWithPerfect,
            'monthly_service_max_per_day' => $monthlyServiceMaxPerDay,
            'monthly_sales_max_per_day' => $monthlySalesMaxPerDay,
            'monthly_service_max' => $monthlyServiceMax,
            'monthly_sales_max' => $monthlySalesMax,
            'theoretical_max' => ($dailyMaxWithPerfect * $workingDays) + $monthlyServiceMax + $monthlySalesMax,
        ];
    }

    /**
     * Build canonical waiter-facing monthly progress data.
     */
    public function getWaiterMonthlyProgress(string $waiterId, string $month): array
    {
        $config = $this->getBonusConfig();
        $capacity = $this->getMonthlyPointsCapacity($config);
        $monthlyPoints = $this->getMonthlyDailyPoints($waiterId, $month);
        $penalties = $this->getPenaltiesByMonth($month, $waiterId);
        $salesTarget = $this->getSalesTarget($waiterId, $month);
        $bonusSummary = $this->getMonthlyBonusSummary($waiterId, $month);
        $leaderboard = $this->getLeaderboard($month);
        $manualBonusTotal = $this->sumManualBonusForMonth($waiterId, $month);

        $totalEarned = 0;
        $penaltySignedTotal = 0;
        $totalPenalties = 0;
        $daysScored = 0;
        $perfectDays = 0;

        foreach ($monthlyPoints as $record) {
            $record = (array) $record;
            $totalEarned += (int) ($record['daily_total'] ?? 0);
            $daysScored++;

            if ((int) ($record['perfect_day_bonus'] ?? 0) > 0) {
                $perfectDays++;
            }
        }

        foreach ($penalties as $penalty) {
            $pointsDeducted = (int) ($penalty['points_deducted'] ?? 0);
            $penaltySignedTotal += $pointsDeducted;
            $totalPenalties += abs($pointsDeducted);
        }

        $servicePoints = (int) ($bonusSummary['service_points'] ?? 0);
        $salesPoints = (int) ($bonusSummary['sales_points'] ?? 0);
        // Manual bonus (signed) ikut dihitung agar konsisten dengan calculateMonthlyBonus
        // — supaya yang waiter lihat di dashboard match dengan summary bulan admin.
        $netPoints = max(0, $totalEarned + $servicePoints + $salesPoints + $penaltySignedTotal + $manualBonusTotal);
        $theoreticalMax = (int) $capacity['theoretical_max'];
        $percentage = $theoreticalMax > 0 ? round(($netPoints / $theoreticalMax) * 100, 1) : 0.0;

        return [
            'config' => $config,
            'monthly_points' => $monthlyPoints,
            'penalties' => $penalties,
            'sales_target' => $salesTarget,
            'bonus_summary' => $bonusSummary,
            'leaderboard' => $leaderboard,
            'total_earned' => $totalEarned,
            'total_penalties' => $totalPenalties,
            'penalty_signed_total' => $penaltySignedTotal,
            'service_points' => $servicePoints,
            'sales_points' => $salesPoints,
            'manual_bonus_total' => $manualBonusTotal,
            'net_points' => $netPoints,
            'days_scored' => $daysScored,
            'perfect_days' => $perfectDays,
            'percentage' => $percentage,
        ] + $capacity;
    }

    /**
     * Save bonus configuration to Firebase.
     */
    public function updateBonusConfig(array $data): void
    {
        $this->database->getReference('bonus_config')->set($data);
    }

    // =========================================================================
    //  DAILY SCORING
    // =========================================================================

    /**
     * Score daily points for a waiter on a given date.
     *
     * Only daily-scored categories are written:
     * - discipline (max 5)
     * - operational (max 10) — auto from non-rack_check task ratio
     * - attitude (max 5)
     * - rack_recheck (max 10) — manual from Finance review
     *
     * Service and Sales are scored monthly (percentage) at finalization time.
     * Monthly categories (service, sales) are excluded from daily records.
     *
     * @param  string  $waiterId
     * @param  string  $date          Format 'Y-m-d'
     * @param  array   $categoryScores  e.g. ['discipline' => 5, 'operational' => 10, 'attitude' => 5]
     * @param  string  $notes
     * @return array
     */
    public function scoreDailyPoints(string $waiterId, string $date, array $categoryScores, string $notes = '', array $metadata = []): array
    {
        $config = $this->getBonusConfig();
        $categories = $config['point_categories'] ?? [];
        $existingRecord = $this->getDailyPoints($waiterId, $date);

        if (($metadata['preserve_admin_override'] ?? false) && ! empty($existingRecord['admin_override'])) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Skipped auto-score because record is admin overridden.',
                'daily_total' => (int) ($existingRecord['daily_total'] ?? 0),
                'categories' => (array) ($existingRecord['categories'] ?? []),
                'raw_total' => (int) ($existingRecord['raw_total'] ?? 0),
                'perfect_day_bonus' => (int) ($existingRecord['perfect_day_bonus'] ?? 0),
            ];
        }

        $validated = [];
        $rawTotal = 0;

        foreach ($categories as $key => $meta) {
            // Skip monthly-scored categories in daily records
            if (($meta['scoring_type'] ?? 'daily') === 'monthly') {
                continue;
            }
            $max = (int) ($meta['max_daily_points'] ?? 0);
            $score = isset($categoryScores[$key]) ? max(0, min((int) $categoryScores[$key], $max)) : 0;
            $validated[$key] = $score;
            $rawTotal += $score;
        }

        $perfectDayBonus = $this->calculatePerfectDayBonus($validated, $config);
        $dailyTotal = $rawTotal + $perfectDayBonus;

        $record = [
            'waiter_id'         => $waiterId,
            'date'              => $date,
            'month'             => substr($date, 0, 7),
            'categories'        => $validated,
            'raw_total'         => $rawTotal,
            'perfect_day_bonus' => $perfectDayBonus,
            'daily_total'       => $dailyTotal,
            'notes'             => $notes,
            'scored_at'         => time(),
            'updated_at'        => time(),
            'score_source'      => (string) ($metadata['score_source'] ?? 'manual'),
            'admin_override'    => (bool) ($metadata['admin_override'] ?? false),
        ];

        if (isset($metadata['auto_details']) && is_array($metadata['auto_details'])) {
            $record['auto_details'] = $metadata['auto_details'];
        }

        if ($existingRecord && isset($existingRecord['created_at'])) {
            $record['created_at'] = (int) $existingRecord['created_at'];
        } else {
            $record['created_at'] = time();
        }

        $this->database->getReference('waiter_daily_points/' . $waiterId . '/' . $date)->set($record);

        return [
            'success'          => true,
            'daily_total'      => $dailyTotal,
            'perfect_day'      => $perfectDayBonus > 0,
            'categories'       => $validated,
            'raw_total'        => $rawTotal,
            'perfect_day_bonus' => $perfectDayBonus,
        ];
    }

    public function saveAdminDailyScore(string $waiterId, string $date, array $categoryScores, string $notes = ''): array
    {
        return $this->scoreDailyPoints($waiterId, $date, $categoryScores, $notes, [
            'score_source' => 'admin',
            'admin_override' => true,
        ]);
    }

    public function saveAutoDailyScore(string $waiterId, string $date, array $categoryScores, string $notes = '', array $autoDetails = []): array
    {
        return $this->scoreDailyPoints($waiterId, $date, $categoryScores, $notes, [
            'score_source' => 'auto',
            'admin_override' => false,
            'preserve_admin_override' => true,
            'auto_details' => $autoDetails,
        ]);
    }

    /**
     * Targeted merge of `rack_recheck` category into the existing daily record.
     *
     * Sengaja BUKAN lewat scoreDailyPoints — Finance recheck harus tetap menulis
     * poin rak meskipun supervisor sudah pernah save manual (admin_override=true)
     * di hari yang sama. Tanpa method ini, review Finance silently di-skip.
     *
     * Yang di-preserve: discipline/operational/attitude (manual maupun auto),
     * admin_override flag, score_source, created_at.
     * Yang di-overwrite: categories[rack_recheck], raw_total, perfect_day_bonus,
     * daily_total, auto_details[rack_recheck_*], updated_at, scored_at, notes.
     */
    public function mergeRackRecheckPoints(
        string $waiterId,
        string $date,
        int $rackRecheckScore,
        string $notes = '',
        array $autoDetails = []
    ): array {
        $config = $this->getBonusConfig();
        $categories = $config['point_categories'] ?? [];

        $rackMeta = $categories['rack_recheck'] ?? null;
        if (! is_array($rackMeta) || ($rackMeta['scoring_type'] ?? 'daily') === 'monthly') {
            return [
                'success' => false,
                'message' => 'Kategori rack_recheck tidak aktif di config.',
            ];
        }

        $rackMax = (int) ($rackMeta['max_daily_points'] ?? 10);
        $rackRecheckScore = max(0, min($rackMax, $rackRecheckScore));

        $existing = $this->getDailyPoints($waiterId, $date);
        $existingCategories = is_array($existing['categories'] ?? null) ? $existing['categories'] : [];

        // Build the daily-scored category map: keep all existing daily values,
        // override just rack_recheck.
        $merged = [];
        foreach ($categories as $key => $meta) {
            if (($meta['scoring_type'] ?? 'daily') === 'monthly') {
                continue;
            }
            $merged[$key] = (int) ($existingCategories[$key] ?? 0);
        }
        $merged['rack_recheck'] = $rackRecheckScore;

        $rawTotal = array_sum($merged);
        $perfectDayBonus = $this->calculatePerfectDayBonus($merged, $config);
        $dailyTotal = $rawTotal + $perfectDayBonus;

        $now = time();
        $existingAutoDetails = is_array($existing['auto_details'] ?? null) ? $existing['auto_details'] : [];
        $mergedAutoDetails = $existingAutoDetails;
        foreach ($autoDetails as $k => $v) {
            // Hanya merge field rack_recheck_* supaya tidak menimpa reason kategori lain.
            if (str_starts_with((string) $k, 'rack_recheck_')) {
                $mergedAutoDetails[$k] = $v;
            }
        }

        $record = [
            'waiter_id'         => $waiterId,
            'date'              => $date,
            'month'             => substr($date, 0, 7),
            'categories'        => $merged,
            'raw_total'         => $rawTotal,
            'perfect_day_bonus' => $perfectDayBonus,
            'daily_total'       => $dailyTotal,
            'notes'             => $notes !== '' ? $notes : (string) ($existing['notes'] ?? ''),
            'scored_at'         => $now,
            'updated_at'        => $now,
            // Preserve provenance: kalau record ada, jangan ubah source/admin_override.
            'score_source'      => (string) ($existing['score_source'] ?? 'auto'),
            'admin_override'    => (bool) ($existing['admin_override'] ?? false),
            'auto_details'      => $mergedAutoDetails,
            'created_at'        => isset($existing['created_at'])
                ? (int) $existing['created_at']
                : $now,
        ];

        $this->database->getReference('waiter_daily_points/' . $waiterId . '/' . $date)->set($record);

        return [
            'success'           => true,
            'merged'            => true,
            'daily_total'       => $dailyTotal,
            'raw_total'         => $rawTotal,
            'perfect_day'       => $perfectDayBonus > 0,
            'perfect_day_bonus' => $perfectDayBonus,
            'categories'        => $merged,
            'admin_override'    => (bool) ($existing['admin_override'] ?? false),
        ];
    }

    /**
     * Get daily points record for a waiter on a specific date.
     */
    public function getDailyPoints(string $waiterId, string $date): ?array
    {
        $snapshot = $this->database->getReference('waiter_daily_points/' . $waiterId . '/' . $date)->getSnapshot();

        return $snapshot->exists() ? (array) $snapshot->getValue() : null;
    }

    /**
     * Get all daily points for a waiter in a given month.
     *
     * @param  string  $waiterId
     * @param  string  $month  Format 'Y-m' e.g. '2025-07'
     * @return array  date => daily_record
     */
    public function getMonthlyDailyPoints(string $waiterId, string $month): array
    {
        // Use startAt/endAt on date keys to avoid reading all months
        // Keys are formatted as 'YYYY-MM-DD', so we can range query
        $startDate = $month . '-01';
        $endDate = $month . '-31'; // Safe upper bound (Firebase string comparison)

        // Respect SOP launch date: skip records before effective_from.
        $effectiveFrom = $this->getEffectiveFromDate();
        if ($effectiveFrom !== null && $effectiveFrom > $startDate) {
            $startDate = $effectiveFrom;
            if ($startDate > $endDate) {
                return [];
            }
        }

        $snapshot = $this->database->getReference('waiter_daily_points/' . $waiterId)
            ->orderByKey()
            ->startAt($startDate)
            ->endAt($endDate)
            ->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $filtered = [];
        foreach ((array) $snapshot->getValue() as $date => $record) {
            $filtered[$date] = (array) $record;
        }

        ksort($filtered);

        return $filtered;
    }

    /**
     * Resolve the effective SOP launch date.
     * Returns null when scoring is "always live" (no launch threshold).
     */
    public function getEffectiveFromDate(): ?string
    {
        $config = $this->getBonusConfig();
        $value = $config['effective_from'] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        // Validate strict YYYY-MM-DD shape; reject anything else as "no threshold".
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Returns true when SOP scoring is in effect for the given date.
     */
    public function isDateOnOrAfterEffective(string $date): bool
    {
        $effective = $this->getEffectiveFromDate();
        if ($effective === null) {
            return true;
        }

        return $date >= $effective;
    }

    // =========================================================================
    //  AUTO DAILY SCORING
    // =========================================================================

    /**
     * Auto-calculate daily scores for a waiter based on PRE-FETCHED data.
     *
     * Daily auto-scored categories:
     * - discipline (max 5): from attendance record
     * - operational (max 10): from non-rack_check task completion ratio
     * - attitude (max 5): from activity report submission
     * - rack_recheck (max 10): from sum of Finance recheck_points on rack_check tasks
     *
     * Service and Sales are scored monthly (percentage) at finalization time.
     *
     * @param  string      $waiterId
     * @param  string      $date        Format 'Y-m-d'
     * @param  array|null  $attendance  Pre-fetched attendance record (null = no record)
     * @param  array       $waiterTasks Pre-fetched tasks for this waiter on this date
     * @param  array       $waiterReports Pre-fetched activity reports for this waiter on this date
     * @return array
     */
    public function autoScoreDailyPoints(string $waiterId, string $date, ?array $attendance = null, array $waiterTasks = [], array $waiterReports = []): array
    {
        $config = $this->getBonusConfig();

        // -----------------------------------------------------------------
        //  DISCIPLINE (max 5) — from attendance
        // -----------------------------------------------------------------
        $disciplineScore = 0;
        $disciplineReason = 'Tidak ada data absensi';

        if ($attendance && ! empty($attendance['clock_in'])) {
            $status = $attendance['status'] ?? 'present';

            if ($status === 'present') {
                $disciplineScore = 5;
                $disciplineReason = 'Tepat waktu';
            } elseif ($status === 'late') {
                $lateMinutes = (int) ($attendance['late_minutes'] ?? 0);
                $deduction = (int) floor($lateMinutes / 10);
                $disciplineScore = max(0, 5 - $deduction);
                $disciplineReason = 'Terlambat ' . $lateMinutes . ' menit (-' . $deduction . ')';
            } else {
                $disciplineScore = 0;
                $disciplineReason = 'Status: ' . $status;
            }
        }

        // -----------------------------------------------------------------
        //  OPERATIONAL (max 10) — from non-rack_check task completion
        //  rack_check tasks are scored separately via Finance recheck.
        // -----------------------------------------------------------------
        $operationalMax = (int) ($config['point_categories']['operational']['max_daily_points'] ?? 10);
        $nonRackTasks = array_values(array_filter($waiterTasks, function ($task) {
            return ($task['task_type'] ?? 'general') !== 'rack_check';
        }));
        $totalTasks = count($nonRackTasks);

        if ($totalTasks > 0) {
            $completedTasks = count(array_filter($nonRackTasks, function ($task) {
                return ($task['status'] ?? 'pending') === 'done';
            }));

            $operationalScore = (int) round(($completedTasks / $totalTasks) * $operationalMax);
            $operationalReason = $completedTasks . '/' . $totalTasks . ' tugas umum selesai';
        } else {
            // Tidak ada task umum dijadwalkan = waiter tidak bisa kontrol; default full poin agar fair.
            $operationalScore = $operationalMax;
            $operationalReason = 'Tidak ada tugas umum dijadwalkan (default poin penuh)';
        }

        // -----------------------------------------------------------------
        //  ATTITUDE (max 5) — from activity report
        // -----------------------------------------------------------------
        $attitudeScore = 0;
        $attitudeReason = 'Belum submit laporan';

        if (! empty($waiterReports)) {
            $attitudeScore = 5;
            $attitudeReason = 'Laporan kegiatan disubmit';
        }

        // -----------------------------------------------------------------
        //  RACK_RECHECK (max 10) — sum of Finance recheck_points on rack_check tasks
        // -----------------------------------------------------------------
        $rackRecheckMax = (int) ($config['point_categories']['rack_recheck']['max_daily_points'] ?? 10);
        $rackTasks = array_values(array_filter($waiterTasks, function ($task) {
            return ($task['task_type'] ?? 'general') === 'rack_check';
        }));
        $rackRecheckScore = 0;
        $rackRecheckReason = 'Belum ada cek rak';
        if (count($rackTasks) > 0) {
            $reviewedRackTasks = array_values(array_filter($rackTasks, function ($task) {
                if (! isset($task['recheck_points'])) {
                    return false;
                }

                // Firebase REST kadang me-return boolean sebagai string ("false"/"true").
                // empty("false") === false → menganggap masih pending. Pakai parser eksplisit.
                $pending = $task['recheck_pending'] ?? null;
                if ($pending === null || $pending === false || $pending === 0 || $pending === '0' || $pending === '') {
                    return true;
                }
                $bool = filter_var($pending, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $bool === false;
            }));
            $totalRackTasks = count($rackTasks);
            $reviewedCount = count($reviewedRackTasks);
            if ($reviewedCount > 0) {
                // Average poin per rak yang sudah direview, di-scale ke max 10
                $sumPoints = 0;
                foreach ($reviewedRackTasks as $rt) {
                    $sumPoints += max(0, min(10, (int) ($rt['recheck_points'] ?? 0)));
                }
                // Average yang sudah direview, lalu pro-rate dengan total rak hari itu
                // Supaya kalau Finance baru review 2 dari 5 rak, waiter dapat sebagian.
                $avgPoints = $sumPoints / $reviewedCount;
                $rackRecheckScore = (int) round($avgPoints * ($reviewedCount / $totalRackTasks));
                $rackRecheckScore = max(0, min($rackRecheckMax, $rackRecheckScore));
                $rackRecheckReason = $reviewedCount . '/' . $totalRackTasks . ' rak direview Finance, total ' . $sumPoints . ' poin';
            } else {
                $rackRecheckReason = '0/' . $totalRackTasks . ' rak direview Finance (menunggu)';
            }
        }

        return [
            'discipline'   => $disciplineScore,
            'operational'  => $operationalScore,
            'attitude'     => $attitudeScore,
            'rack_recheck' => $rackRecheckScore,
            'auto_details' => [
                'discipline_reason'   => $disciplineReason,
                'operational_reason'  => $operationalReason,
                'attitude_reason'     => $attitudeReason,
                'rack_recheck_reason' => $rackRecheckReason,
            ],
        ];
    }

    // =========================================================================
    //  PENALTIES
    // =========================================================================

    /**
     * Apply a penalty to a waiter.
     *
     * @param  array  $data  Keys: waiter_id, waiter_name, penalty_type, date, reason, evidence_photo_url, related_task_id
     * @return array
     */
    public function applyPenalty(array $data): array
    {
        $config = $this->getBonusConfig();
        $penaltyTypes = $config['penalty_types'] ?? [];

        $penaltyType = (string) ($data['penalty_type'] ?? '');
        if (! isset($penaltyTypes[$penaltyType])) {
            return [
                'success' => false,
                'message' => 'Tipe penalti tidak valid: ' . $penaltyType,
            ];
        }

        $pointsDeducted = (int) ($penaltyTypes[$penaltyType]['points'] ?? 0);
        $date = (string) ($data['date'] ?? date('Y-m-d'));
        $waiterId = (string) ($data['waiter_id'] ?? '');
        $relatedTaskId = (string) ($data['related_task_id'] ?? '');
        $dedupKey = sha1(implode('|', [$penaltyType, $waiterId, $date, $relatedTaskId]));
        $indexRef = $this->database->getReference('waiter_penalties_index/'.$dedupKey);

        $existingIndex = $indexRef->getValue();
        if (is_array($existingIndex)) {
            return [
                'success' => true,
                'penalty_id' => (string) ($existingIndex['penalty_id'] ?? ''),
                'points_deducted' => (int) ($existingIndex['points_deducted'] ?? $pointsDeducted),
                'deduplicated' => true,
            ];
        }

        $claimed = false;
        $indexRecord = null;
        try {
            $this->database->runTransaction(function ($transaction) use (&$claimed, &$indexRecord, $indexRef, $waiterId, $penaltyType, $date, $pointsDeducted, $relatedTaskId) {
                $snapshot = $transaction->snapshot($indexRef);
                $current = $snapshot->exists() ? (array) $snapshot->getValue() : null;

                if ($current !== null) {
                    $indexRecord = $current;

                    return;
                }

                $newRecord = [
                    'waiter_id' => $waiterId,
                    'penalty_type' => $penaltyType,
                    'date' => $date,
                    'related_task_id' => $relatedTaskId,
                    'points_deducted' => $pointsDeducted,
                    'created_at' => time(),
                    'penalty_id' => null,
                ];

                $transaction->set($indexRef, $newRecord);
                $claimed = true;
                $indexRecord = $newRecord;
            });
        } catch (TransactionFailed $e) {
            // Lost the race — re-read so the deduplication branch below can use it.
            $indexRecord = $indexRef->getValue();
        }

        if (! $claimed && is_array($indexRecord)) {
            return [
                'success' => true,
                'penalty_id' => (string) ($indexRecord['penalty_id'] ?? ''),
                'points_deducted' => (int) ($indexRecord['points_deducted'] ?? $pointsDeducted),
                'deduplicated' => true,
            ];
        }

        $record = [
            'waiter_id'          => $waiterId,
            'waiter_name'        => (string) ($data['waiter_name'] ?? ''),
            'penalty_type'       => $penaltyType,
            'penalty_label'      => (string) ($penaltyTypes[$penaltyType]['label'] ?? $penaltyType),
            'points_deducted'    => $pointsDeducted,
            'date'               => $date,
            'month'              => substr($date, 0, 7),
            'reason'             => (string) ($data['reason'] ?? ''),
            'evidence_photo_url' => (string) ($data['evidence_photo_url'] ?? ''),
            'related_task_id'    => $relatedTaskId,
            'created_at'         => time(),
        ];

        $ref = $this->database->getReference('waiter_penalties')->push($record);
        $penaltyId = (string) $ref->getKey();

        if ($claimed) {
            $indexRef->update([
                'penalty_id' => $penaltyId,
                'created_at' => time(),
            ]);
        }

        return [
            'success'         => true,
            'penalty_id'      => $penaltyId,
            'points_deducted' => $pointsDeducted,
        ];
    }

    /**
     * Get penalties filtered by month and optionally by waiter.
     *
     * @param  string       $month     Format 'Y-m'
     * @param  string|null  $waiterId
     * @return array
     */
    public function getPenaltiesByMonth(string $month, ?string $waiterId = null): array
    {
        // Use Firebase query on 'month' index instead of reading entire node
        $reference = $this->database->getReference('waiter_penalties')
            ->orderByChild('month')
            ->equalTo($month);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $effectiveFrom = $this->getEffectiveFromDate();
        $penalties = [];

        foreach ((array) $snapshot->getValue() as $id => $penalty) {
            $penalty = (array) $penalty;

            if ($waiterId !== null && (string) ($penalty['waiter_id'] ?? '') !== $waiterId) {
                continue;
            }

            // Respect SOP launch date: skip penalties created before effective_from.
            if ($effectiveFrom !== null) {
                $penaltyDate = (string) ($penalty['date'] ?? '');
                if ($penaltyDate !== '' && $penaltyDate < $effectiveFrom) {
                    continue;
                }
            }

            $penalties[] = array_merge(['id' => $id], $penalty);
        }

        usort($penalties, function ($a, $b) {
            return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
        });

        return $penalties;
    }

    /**
     * Delete a penalty record.
     */
    public function deletePenalty(string $penaltyId): void
    {
        $this->database->getReference('waiter_penalties/' . $penaltyId)->remove();
    }

    // ========================================================================
    // MANUAL BONUS POINTS (supervisor adjustment, additive ke daily/monthly)
    // ========================================================================
    //
    // Schema Firebase: waiter_manual_bonuses/{bonusId} = {
    //   waiter_id: string,
    //   waiter_name: string,
    //   month: 'YYYY-MM',
    //   date: 'YYYY-MM-DD',
    //   points: int (boleh + atau -),
    //   reason: string,
    //   category: 'manual_bonus' | 'manual_deduction',
    //   created_by: string (admin email/id),
    //   created_at: timestamp,
    // }
    //
    // Bedanya dengan penalty: penalty terikat dedup key (per task), manual bonus
    // adalah adjustment bebas oleh supervisor. Tidak dedup, bisa ada banyak per
    // hari per karyawan (akumulatif).

    /**
     * Apply 1 manual bonus untuk 1 karyawan.
     *
     * @param  array{waiter_id:string, waiter_name?:string, points:int, reason:string, date?:string, created_by?:string}  $data
     * @return array{success:bool, bonus_id?:string, message?:string, points?:int}
     */
    public function applyManualBonus(array $data): array
    {
        $waiterId = trim((string) ($data['waiter_id'] ?? ''));
        $points = (int) ($data['points'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        $date = (string) ($data['date'] ?? date('Y-m-d'));
        $createdBy = (string) ($data['created_by'] ?? 'supervisor');

        if ($waiterId === '') {
            return ['success' => false, 'message' => 'waiter_id wajib.'];
        }
        if ($points === 0) {
            return ['success' => false, 'message' => 'Poin tidak boleh 0.'];
        }
        if ($reason === '') {
            return ['success' => false, 'message' => 'Alasan wajib diisi.'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $waiterName = trim((string) ($data['waiter_name'] ?? ''));
        if ($waiterName === '') {
            $w = $this->firebase->getWaiterById($waiterId);
            $waiterName = (string) ($w['name'] ?? '');
        }

        $month = substr($date, 0, 7);
        $ref = $this->database->getReference('waiter_manual_bonuses')->push();
        $bonusId = $ref->getKey();
        $record = [
            'bonus_id' => $bonusId,
            'waiter_id' => $waiterId,
            'waiter_name' => $waiterName,
            'month' => $month,
            'date' => $date,
            'points' => $points,
            'reason' => $reason,
            'category' => $points >= 0 ? 'manual_bonus' : 'manual_deduction',
            'created_by' => $createdBy,
            'created_at' => time(),
        ];
        $ref->set($record);

        return [
            'success' => true,
            'bonus_id' => $bonusId,
            'points' => $points,
            'message' => 'Manual bonus tersimpan.',
        ];
    }

    /**
     * Apply manual bonus ke banyak karyawan sekaligus.
     *
     * @param  array<int, string>  $waiterIds
     * @return array{success:bool, applied:int, failed:int, results:array<int, array>}
     */
    public function applyManualBonusBulk(array $waiterIds, int $points, string $reason, string $date, string $createdBy = 'supervisor'): array
    {
        $applied = 0;
        $failed = 0;
        $results = [];

        // Pre-fetch waiter names supaya hemat read.
        $allWaiters = $this->firebase->getAllowedEmails();
        $waiterMap = [];
        foreach ($allWaiters as $w) {
            $wid = (string) ($w['id'] ?? '');
            if ($wid !== '') {
                $waiterMap[$wid] = (string) ($w['name'] ?? '');
            }
        }

        foreach ($waiterIds as $wid) {
            $wid = trim((string) $wid);
            if ($wid === '') {
                continue;
            }
            $r = $this->applyManualBonus([
                'waiter_id' => $wid,
                'waiter_name' => $waiterMap[$wid] ?? '',
                'points' => $points,
                'reason' => $reason,
                'date' => $date,
                'created_by' => $createdBy,
            ]);
            if ($r['success']) {
                $applied++;
            } else {
                $failed++;
            }
            $results[] = [
                'waiter_id' => $wid,
                'waiter_name' => $waiterMap[$wid] ?? '',
                'success' => $r['success'],
                'message' => $r['message'] ?? '',
                'bonus_id' => $r['bonus_id'] ?? null,
            ];
        }

        return [
            'success' => $applied > 0,
            'applied' => $applied,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Get manual bonuses untuk satu bulan.
     *
     * Catatan: TIDAK pakai orderByChild('month') untuk hindari Firebase index requirement.
     * Read full node + filter PHP-side. Aman karena volume rendah (manual entry, ratusan per bulan max).
     *
     * @return array<int, array>  list of bonus records, sorted by date desc
     */
    public function getManualBonusesByMonth(string $month, ?string $waiterId = null): array
    {
        $snapshot = $this->database->getReference('waiter_manual_bonuses')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $items = [];
        foreach ((array) $snapshot->getValue() as $id => $row) {
            $row = (array) $row;
            if (($row['month'] ?? '') !== $month) {
                continue;
            }
            if ($waiterId !== null && ($row['waiter_id'] ?? '') !== $waiterId) {
                continue;
            }
            $row['bonus_id'] = $id;
            $items[] = $row;
        }

        // Sort by date desc, fallback ke created_at
        usort($items, function ($a, $b) {
            $dateCmp = strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
        });

        return $items;
    }

    /**
     * Sum total manual bonus poin untuk waiter di bulan tertentu.
     */
    public function sumManualBonusForMonth(string $waiterId, string $month): int
    {
        $items = $this->getManualBonusesByMonth($month, $waiterId);
        $total = 0;
        foreach ($items as $b) {
            $total += (int) ($b['points'] ?? 0);
        }

        return $total;
    }

    /**
     * Hapus manual bonus by ID.
     */
    public function deleteManualBonus(string $bonusId): bool
    {
        if (trim($bonusId) === '') {
            return false;
        }
        try {
            $this->database->getReference('waiter_manual_bonuses/' . $bonusId)->remove();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =========================================================================
    //  POINT EVENTS TIMELINE (untuk dashboard waiter)
    // =========================================================================

    /**
     * Bangun timeline kronologis "kapan poin masuk" untuk satu waiter di satu
     * bulan. Gabungan dari 3 sumber yang menambah poin (atau mengurangi):
     *
     *   - rack_recheck : task rack_check yang sudah direview Finance
     *                    → tipe='rack_recheck', points = recheck_points,
     *                      created_at = recheck_at
     *   - manual_bonus : penambahan poin manual oleh supervisor
     *                    → tipe='manual_bonus' / 'manual_deduction'
     *   - penalty      : pengurangan poin oleh sistem/admin
     *                    → tipe='penalty', points negatif
     *
     * Sort: terbaru di atas (created_at desc, fallback date desc).
     *
     * @return array<int, array{
     *   type: string,
     *   points: int,
     *   label: string,
     *   reason: string,
     *   date: string,
     *   created_at: int,
     *   actor: string,
     *   ref_id: string,
     * }>
     */
    public function getWaiterPointEvents(string $waiterId, string $month): array
    {
        $events = [];

        // --- 1. rack_recheck events ---
        // Ambil semua waiter_tasks lalu filter PHP-side. Volume rendah (≤ ratusan
        // task aktif per bulan), dan kita tidak punya index `recheck_at` di Firebase.
        $candidates = [];

        // 1a. Query by assigned_waiter_id (single + role-based yang sudah resolve)
        try {
            $snapshot = $this->database->getReference('waiter_tasks')
                ->orderByChild('assigned_waiter_id')
                ->equalTo($waiterId)
                ->getSnapshot();
            if ($snapshot->exists()) {
                $candidates = (array) $snapshot->getValue();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 1b. Query by completed_by_waiter_id (catch task role-based yang
        // assigned_waiter_id-nya null). Kalau index tidak ada di Firebase rules,
        // skip silently — query 1a sudah cover sebagian besar kasus.
        try {
            $snapshotCompl = $this->database->getReference('waiter_tasks')
                ->orderByChild('completed_by_waiter_id')
                ->equalTo($waiterId)
                ->getSnapshot();
            if ($snapshotCompl->exists()) {
                foreach ((array) $snapshotCompl->getValue() as $id => $task) {
                    if (! isset($candidates[$id])) {
                        $candidates[$id] = $task;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Index missing on Firebase = ok, lanjut tanpa data tambahan.
            // Log untuk awareness saja.
            \Log::debug('point-events: completed_by_waiter_id index missing, skipping query 1b', [
                'waiter_id' => $waiterId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            foreach ($candidates as $id => $task) {
                $task = (array) $task;
                if (($task['task_type'] ?? '') !== 'rack_check') {
                    continue;
                }
                if (! isset($task['recheck_points'])) {
                    continue;
                }
                $pending = $task['recheck_pending'] ?? null;
                $bool = filter_var($pending, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === true) {
                    continue;
                }

                $taskMonth = (string) (substr((string) ($task['scheduled_for_date'] ?? ''), 0, 7));
                if ($taskMonth !== $month) {
                    continue;
                }

                $points = max(0, (int) ($task['recheck_points'] ?? 0));
                $events[] = [
                    'type'       => 'rack_recheck',
                    'points'     => $points,
                    'label'      => 'Cek Rak',
                    'reason'     => trim((string) ($task['rack_name'] ?? $task['title'] ?? 'Rak')) .
                                    (! empty($task['recheck_notes']) ? ' — ' . trim((string) $task['recheck_notes']) : ''),
                    'date'       => (string) ($task['scheduled_for_date'] ?? ''),
                    'created_at' => (int) ($task['recheck_at'] ?? $task['completed_at'] ?? 0),
                    'actor'      => trim((string) ($task['recheck_by_name'] ?? 'Finance')),
                    'ref_id'     => (string) $id,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // --- 2. manual_bonus events ---
        try {
            $bonuses = $this->getManualBonusesByMonth($month, $waiterId);
            foreach ($bonuses as $b) {
                $points = (int) ($b['points'] ?? 0);
                $events[] = [
                    'type'       => $points >= 0 ? 'manual_bonus' : 'manual_deduction',
                    'points'     => $points,
                    'label'      => $points >= 0 ? 'Bonus Manual' : 'Pengurangan Manual',
                    'reason'     => (string) ($b['reason'] ?? ''),
                    'date'       => (string) ($b['date'] ?? ''),
                    'created_at' => (int) ($b['created_at'] ?? 0),
                    'actor'      => (string) ($b['created_by'] ?? 'supervisor'),
                    'ref_id'     => (string) ($b['bonus_id'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // --- 3. penalty events ---
        try {
            $penalties = $this->getPenaltiesByMonth($month, $waiterId);
            foreach ($penalties as $p) {
                $events[] = [
                    'type'       => 'penalty',
                    'points'     => (int) ($p['points_deducted'] ?? 0),
                    'label'      => (string) ($p['penalty_label'] ?? ($p['penalty_type'] ?? 'Penalti')),
                    'reason'     => (string) ($p['reason'] ?? ''),
                    'date'       => (string) ($p['date'] ?? ''),
                    'created_at' => (int) ($p['created_at'] ?? 0),
                    'actor'      => 'Sistem',
                    'ref_id'     => (string) ($p['id'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Sort terbaru di atas
        usort($events, function ($a, $b) {
            $cmp = ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return $events;
    }


    /**
     * Wipe ALL bonus-related historical data: daily points, penalties, monthly summaries,
     * leaderboards, sales targets. Used when supervisor wants to mark a fresh SOP launch.
     *
     * Returns counts of removed entries per node, plus the totals removed.
     * Bonus_config is preserved (only data is wiped, not the config).
     */
    public function resetBonusData(): array
    {
        $paths = [
            'waiter_daily_points',
            'waiter_penalties',
            'waiter_penalties_index',
            'waiter_monthly_bonus',
            'waiter_bonus_summary',
            'bonus_leaderboards',
            'waiter_sales_targets',
        ];

        $counts = [];
        $total = 0;

        foreach ($paths as $path) {
            $snap = $this->database->getReference($path)->getSnapshot();
            $value = $snap->exists() ? $snap->getValue() : null;
            $count = is_array($value) ? count($value) : 0;
            $counts[$path] = $count;
            $total += $count;

            if ($count > 0) {
                $this->database->getReference($path)->remove();
            }
        }

        return [
            'counts' => $counts,
            'total' => $total,
        ];
    }

    /**
     * Auto-apply penalties based on PRE-FETCHED data for a given waiter and date.
     *
     * Automatically detects:
     * 1. late_arrival — from attendance record (late_minutes > 0)
     * 2. mandatory_task_missed — from tasks with status 'overdue' on that date
     *
     * Skips if penalty already exists for same waiter + type + date.
     *
     * @param  string      $waiterId
     * @param  string      $waiterName
     * @param  string      $date             Format 'Y-m-d'
     * @param  array|null  $attendance       Pre-fetched attendance record
     * @param  array       $waiterTasks      Pre-fetched tasks for this waiter on this date
     * @param  array       $existingPenalties Pre-fetched penalties for this waiter this month
     * @return array   List of penalties applied
     */
    public function autoApplyPenalties(string $waiterId, string $waiterName, string $date, ?array $attendance = null, array $waiterTasks = [], array $existingPenalties = []): array
    {
        $applied = [];

        // Build existing keys to avoid duplicates
        $existingKeys = [];
        foreach ($existingPenalties as $p) {
            if (($p['date'] ?? '') === $date) {
                $existingKeys[] = ($p['penalty_type'] ?? '') . '_' . ($p['related_task_id'] ?? '');
            }
        }

        // -----------------------------------------------------------------
        //  1. LATE ARRIVAL — from attendance
        // -----------------------------------------------------------------
        if ($attendance && ((int) ($attendance['late_minutes'] ?? 0)) > 0) {
            $key = 'late_arrival_';
            if (! in_array($key, $existingKeys)) {
                $lateMin = (int) $attendance['late_minutes'];
                $result = $this->applyPenalty([
                    'waiter_id'   => $waiterId,
                    'waiter_name' => $waiterName,
                    'penalty_type' => 'late_arrival',
                    'date'        => $date,
                    'reason'      => 'Terlambat ' . $lateMin . ' menit (otomatis dari absensi)',
                    'related_task_id' => '',
                ]);
                if ($result['success'] ?? false) {
                    $applied[] = $result;
                }
            }
        }

        // -----------------------------------------------------------------
        //  2. ABSENT / NO-SHOW — from explicit attendance status
        // -----------------------------------------------------------------
        if (($attendance['status'] ?? '') === 'absent') {
            $key = 'absent_';
            if (! in_array($key, $existingKeys, true)) {
                $result = $this->applyPenalty([
                    'waiter_id'   => $waiterId,
                    'waiter_name' => $waiterName,
                    'penalty_type' => 'absent',
                    'date'        => $date,
                    'reason'      => 'Tidak hadir pada hari kerja (otomatis dari absensi)',
                    'related_task_id' => '',
                ]);
                if ($result['success'] ?? false) {
                    $applied[] = $result;
                }
            }
        }

        // -----------------------------------------------------------------
        //  3. MANDATORY TASK MISSED — from overdue tasks
        // -----------------------------------------------------------------
        $overdueTasks = array_filter($waiterTasks, function ($task) {
            return ($task['status'] ?? '') === 'overdue';
        });

        foreach ($overdueTasks as $taskId => $task) {
            $key = 'mandatory_task_missed_' . $taskId;
            if (! in_array($key, $existingKeys)) {
                $taskTitle = $task['title'] ?? 'Tugas';
                $result = $this->applyPenalty([
                    'waiter_id'   => $waiterId,
                    'waiter_name' => $waiterName,
                    'penalty_type' => 'mandatory_task_missed',
                    'date'        => $date,
                    'reason'      => 'Tugas "' . $taskTitle . '" tidak dikerjakan (otomatis)',
                    'related_task_id' => is_string($taskId) ? $taskId : ($task['id'] ?? ''),
                ]);
                if ($result['success'] ?? false) {
                    $applied[] = $result;
                }
            }
        }

        return $applied;
    }

    // =========================================================================
    //  SALES TARGETS
    // =========================================================================

    /**
     * Set a monthly sales target for a waiter.
     *
     * @param  string  $waiterId
     * @param  string  $month         Format 'Y-m'
     * @param  int     $targetAmount  Target in Rupiah
     * @param  string  $role          e.g. 'bird_specialist', 'fishing_specialist'
     */
    public function setSalesTarget(string $waiterId, string $month, int $targetAmount, string $role): void
    {
        $path = 'waiter_sales_targets/' . $waiterId . '/' . $month;

        $existing = $this->database->getReference($path)->getSnapshot();
        $currentAchievement = 0;
        $dailySales = [];

        if ($existing->exists()) {
            $val = (array) $existing->getValue();
            $currentAchievement = (int) ($val['current_achievement'] ?? 0);
            $dailySales = $val['daily_sales'] ?? [];
        }

        $this->database->getReference($path)->set([
            'waiter_id'           => $waiterId,
            'month'               => $month,
            'target_amount'       => $targetAmount,
            'role'                => $role,
            'current_achievement' => $currentAchievement,
            'daily_sales'         => $dailySales ?: null,
            'updated_at'          => time(),
        ]);
    }

    /**
     * Record daily sales for a waiter and update cumulative achievement.
     */
    public function recordDailySales(string $waiterId, string $date, int $amount, int $itemsSold = 0): void
    {
        $month = substr($date, 0, 7);
        $path = 'waiter_sales_targets/' . $waiterId . '/' . $month;

        $snapshot = $this->database->getReference($path)->getSnapshot();

        if (! $snapshot->exists()) {
            return;
        }

        $target = (array) $snapshot->getValue();

        $dailySalesRecord = [
            'date'       => $date,
            'amount'     => $amount,
            'items_sold' => $itemsSold,
            'recorded_at' => time(),
        ];

        $this->database->getReference($path . '/daily_sales/' . $date)->set($dailySalesRecord);

        // Recalculate current achievement from all daily sales
        $allDailySalesSnapshot = $this->database->getReference($path . '/daily_sales')->getSnapshot();
        $totalAchievement = 0;

        if ($allDailySalesSnapshot->exists()) {
            foreach ((array) $allDailySalesSnapshot->getValue() as $daySale) {
                $totalAchievement += (int) (is_array($daySale) ? ($daySale['amount'] ?? 0) : 0);
            }
        }

        $this->database->getReference($path)->update([
            'current_achievement' => $totalAchievement,
            'updated_at'          => time(),
        ]);
    }

    /**
     * Get sales target and achievement for a waiter in a month.
     */
    public function getSalesTarget(string $waiterId, string $month): ?array
    {
        $snapshot = $this->database->getReference('waiter_sales_targets/' . $waiterId . '/' . $month)->getSnapshot();

        return $snapshot->exists() ? (array) $snapshot->getValue() : null;
    }

    /**
     * Get all waiters' sales targets for a given month.
     */
    public function getAllSalesTargets(string $month): array
    {
        $snapshot = $this->database->getReference('waiter_sales_targets')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $results = [];

        foreach ((array) $snapshot->getValue() as $waiterId => $months) {
            if (! is_array($months) || ! isset($months[$month])) {
                continue;
            }

            $results[$waiterId] = (array) $months[$month];
        }

        return $results;
    }

    // =========================================================================
    //  MONTHLY BONUS CALCULATION
    // =========================================================================

    /**
     * Calculate the full monthly bonus for a waiter.
     *
     * Steps:
     *  1. Load config (working_days, tiers)
     *  2. Sum all daily points for the month (3 auto categories only)
     *  3. Sum all penalties for the month
     *  4. Read monthly service/sales percentages (from bonus summary node)
     *  5. Calculate service_points = (service_pct/100) × 5 × working_days
     *  6. Calculate sales_points = (sales_pct/100) × 5 × working_days
     *  7. Net points = daily_earned + service_points + sales_points + penalties
     *  8. Theoretical max = (daily_max_with_perfect × working_days) + (5 × working_days) + (5 × working_days)
     *  9. Percentage = net_points / theoretical_max × 100
     * 10. Resolve points tier → points_bonus
     * 11. Get sales target → achievement percentage → sales_bonus
     * 12. total_bonus = points_bonus + sales_bonus (capped at max_bonus_total)
     *
     * @param  int|null  $monthlyServicePercentage  0-100, null = read from existing summary
     * @param  int|null  $monthlySalesPercentage    0-100, null = read from existing summary
     * @return array  Full summary matching waiter_bonus_summary Firebase structure
     */
    public function calculateMonthlyBonus(string $waiterId, string $month, ?int $monthlyServicePercentage = null, ?int $monthlySalesPercentage = null): array
    {
        $config = $this->getBonusConfig();
        $capacity = $this->getMonthlyPointsCapacity($config);
        $workingDays = (int) $capacity['working_days'];
        $maxBonusTotal = (int) ($config['total_bonus_pool'] ?? 500000);

        $monthlyServiceMaxPerDay = (int) $capacity['monthly_service_max_per_day'];
        $monthlySalesMaxPerDay = (int) $capacity['monthly_sales_max_per_day'];
        $theoreticalMax = (int) $capacity['theoretical_max'];

        // --- Read existing monthly percentages from summary if not provided ---
        if ($monthlyServicePercentage === null || $monthlySalesPercentage === null) {
            $existingSummary = $this->getMonthlyBonusSummary($waiterId, $month);
            if ($monthlyServicePercentage === null) {
                $monthlyServicePercentage = (int) ($existingSummary['monthly_service_percentage'] ?? 0);
            }
            if ($monthlySalesPercentage === null) {
                $monthlySalesPercentage = (int) ($existingSummary['monthly_sales_percentage'] ?? 0);
            }
        }

        // Clamp percentages to 0-100
        $monthlyServicePercentage = max(0, min(100, $monthlyServicePercentage));
        $monthlySalesPercentage = max(0, min(100, $monthlySalesPercentage));

        // --- Calculate monthly category points ---
        $servicePoints = (int) round(($monthlyServicePercentage / 100) * $monthlyServiceMaxPerDay * $workingDays);
        $salesPoints = (int) round(($monthlySalesPercentage / 100) * $monthlySalesMaxPerDay * $workingDays);

        // --- Daily points ---
        $dailyPoints = $this->getMonthlyDailyPoints($waiterId, $month);
        $totalEarned = 0;
        $daysScored = 0;
        $perfectDays = 0;

        foreach ($dailyPoints as $record) {
            $record = (array) $record;
            $totalEarned += (int) ($record['daily_total'] ?? 0);
            $daysScored++;
            if ((int) ($record['perfect_day_bonus'] ?? 0) > 0) {
                $perfectDays++;
            }
        }

        // --- Penalties ---
        $penalties = $this->getPenaltiesByMonth($month, $waiterId);
        $totalPenalties = 0;
        $penaltyCount = count($penalties);

        foreach ($penalties as $penalty) {
            $totalPenalties += (int) ($penalty['points_deducted'] ?? 0);
        }

        // --- Manual bonus (supervisor adjustment) ---
        $manualBonuses = $this->getManualBonusesByMonth($month, $waiterId);
        $totalManualBonus = 0;
        $manualBonusCount = count($manualBonuses);
        foreach ($manualBonuses as $mb) {
            $totalManualBonus += (int) ($mb['points'] ?? 0);
        }

        // --- Net points & percentage ---
        // Net = daily auto points + monthly service/sales points + penalties (negative) + manual bonus + campaign bonus
        $campaignPoints = 0;
        try {
            $campaignService = app(SalesCampaignService::class);
            $campaignPoints = $campaignService->getUserCampaignPoints($waiterId, $month);
        } catch (\Throwable $e) {
            // SalesCampaignService not available — skip
        }

        $netPoints = $totalEarned + $servicePoints + $salesPoints + $totalPenalties + $totalManualBonus + $campaignPoints;
        $netPoints = max(0, $netPoints);
        $pointsPercentage = $theoreticalMax > 0
            ? round(($netPoints / $theoreticalMax) * 100, 2)
            : 0;

        // --- Points tier ---
        $pointsTierResult = $this->resolvePointsTier($pointsPercentage, $config);
        $pointsBonus = (int) ($pointsTierResult['bonus_amount'] ?? 0);

        // --- Sales target ---
        $salesTarget = $this->getSalesTarget($waiterId, $month);
        $salesTargetAmount = 0;
        $salesAchievement = 0;
        $salesPercentage = 0.0;
        $salesBonus = 0;
        $salesTierResult = ['tier' => 'no_target', 'bonus_amount' => 0];
        $salesRole = '';

        if ($salesTarget !== null) {
            $salesTargetAmount = (int) ($salesTarget['target_amount'] ?? 0);
            $salesAchievement = (int) ($salesTarget['current_achievement'] ?? 0);
            $salesRole = (string) ($salesTarget['role'] ?? '');
            $salesPercentage = $salesTargetAmount > 0
                ? round(($salesAchievement / $salesTargetAmount) * 100, 2)
                : 0;
            $salesTierResult = $this->resolveSalesTier($salesPercentage, $config);
            $salesBonus = (int) ($salesTierResult['bonus_amount'] ?? 0);
        }

        // --- Total bonus ---
        $totalBonus = min($pointsBonus + $salesBonus, $maxBonusTotal);

        // --- Waiter info ---
        $waiter = $this->firebase->getWaiterById($waiterId);

        return [
            'waiter_id'              => $waiterId,
            'waiter_name'            => (string) ($waiter['name'] ?? ''),
            'waiter_email'           => (string) ($waiter['email'] ?? ''),
            'month'                  => $month,

            'working_days'           => $workingDays,
            'days_scored'            => $daysScored,
            'theoretical_max'        => $theoreticalMax,

            'total_points_earned'    => $totalEarned,
            'perfect_days'           => $perfectDays,
            'penalty_count'          => $penaltyCount,
            'total_penalties'        => $totalPenalties,
            'manual_bonus_count'     => $manualBonusCount,
            'total_manual_bonus'     => $totalManualBonus,
            'campaign_points'        => $campaignPoints,

            // Monthly scoring percentages
            'monthly_service_percentage' => $monthlyServicePercentage,
            'monthly_sales_percentage'   => $monthlySalesPercentage,
            'service_points'             => $servicePoints,
            'sales_points'               => $salesPoints,

            'net_points'             => $netPoints,
            'points_percentage'      => $pointsPercentage,
            'points_tier'            => $pointsTierResult['tier'],
            'points_bonus'           => $pointsBonus,

            'sales_role'             => $salesRole,
            'sales_target_amount'    => $salesTargetAmount,
            'sales_achievement'      => $salesAchievement,
            'sales_percentage'       => $salesPercentage,
            'sales_tier'             => $salesTierResult['tier'],
            'sales_bonus'            => $salesBonus,

            'total_bonus'            => $totalBonus,

            'status'                 => 'calculated',
            'admin_override'         => false,
            'override_amount'        => null,
            'override_reason'        => null,
            'calculated_at'          => time(),
        ];
    }

    /**
     * Calculate and finalize the monthly bonus, saving to Firebase.
     *
     * @param  int|null  $monthlyServicePercentage  0-100
     * @param  int|null  $monthlySalesPercentage    0-100
     */
    public function finalizeMonthlyBonus(string $waiterId, string $month, ?int $monthlyServicePercentage = null, ?int $monthlySalesPercentage = null): array
    {
        $path = 'waiter_bonus_summary/' . $waiterId . '/' . $month;
        $finalizedSummary = null;
        $alreadyFinalized = false;

        $this->database->runTransaction(function ($transaction) use ($path, $waiterId, $month, $monthlyServicePercentage, $monthlySalesPercentage, &$finalizedSummary, &$alreadyFinalized) {
            $reference = $this->database->getReference($path);
            $snapshot = $transaction->snapshot($reference);
            $existing = $snapshot->exists() ? (array) $snapshot->getValue() : null;

            if (($existing['status'] ?? '') === 'finalized') {
                $alreadyFinalized = true;
                $finalizedSummary = $existing;

                return;
            }

            $summary = $this->calculateMonthlyBonus($waiterId, $month, $monthlyServicePercentage, $monthlySalesPercentage);
            $summary['status'] = 'finalized';
            $summary['finalized_at'] = time();
            $transaction->set($reference, $summary);
            $finalizedSummary = $summary;
        });

        if ($alreadyFinalized) {
            return array_merge($finalizedSummary ?? [], [
                'success' => false,
                'already_finalized' => true,
                'message' => 'Bonus bulan ini sudah difinalisasi.',
            ]);
        }

        // Auto-credit ke saldo payroll kalau karyawan eligible.
        $totalBonus = (int) ($finalizedSummary['total_bonus'] ?? 0);
        if ($totalBonus > 0) {
            try {
                $payroll = app(\App\Services\PayrollService::class);
                $payroll->creditMonthlyBonusIfEligible($waiterId, $month, $totalBonus);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return array_merge($finalizedSummary ?? [], ['success' => true]);
    }

    /**
     * Override the bonus amount for a waiter in a given month.
     */
    public function overrideBonus(string $waiterId, string $month, int $amount, string $reason): void
    {
        $path = 'waiter_bonus_summary/' . $waiterId . '/' . $month;

        $this->database->getReference($path)->update([
            'admin_override'  => true,
            'override_amount' => $amount,
            'override_reason' => $reason,
            'total_bonus'     => $amount,
            'updated_at'      => time(),
        ]);
    }

    /**
     * Get the stored monthly bonus summary for a waiter.
     */
    public function getMonthlyBonusSummary(string $waiterId, string $month): ?array
    {
        $snapshot = $this->database->getReference('waiter_bonus_summary/' . $waiterId . '/' . $month)->getSnapshot();

        return $snapshot->exists() ? (array) $snapshot->getValue() : null;
    }

    /**
     * Get all waiters' bonus summaries for a given month.
     */
    public function getAllMonthlyBonusSummaries(string $month): array
    {
        $snapshot = $this->database->getReference('waiter_bonus_summary')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $results = [];

        foreach ((array) $snapshot->getValue() as $waiterId => $months) {
            if (! is_array($months) || ! isset($months[$month])) {
                continue;
            }

            $results[$waiterId] = (array) $months[$month];
        }

        return $results;
    }

    // =========================================================================
    //  LEADERBOARD
    // =========================================================================

    /**
     * Generate and save a leaderboard for all active waiters in a month.
     */
    public function generateLeaderboard(string $month): array
    {
        $waiters = $this->firebase->getActiveWaiters();
        $entries = [];

        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '') {
                continue;
            }

            // Prefer finalized summary; fall back to live calculation
            $summary = $this->getMonthlyBonusSummary($waiterId, $month);
            if ($summary === null) {
                $summary = $this->calculateMonthlyBonus($waiterId, $month);
            }

            $entries[] = [
                'waiter_id'         => $waiterId,
                'waiter_name'       => (string) ($waiter['name'] ?? ''),
                'total_points'      => (int) ($summary['net_points'] ?? 0),
                'points_percentage' => (float) ($summary['points_percentage'] ?? 0),
                'perfect_days'      => (int) ($summary['perfect_days'] ?? 0),
                'penalty_count'     => (int) ($summary['penalty_count'] ?? 0),
                'total_bonus'       => (int) ($summary['total_bonus'] ?? 0),
                'points_bonus'      => (int) ($summary['points_bonus'] ?? 0),
                'sales_bonus'       => (int) ($summary['sales_bonus'] ?? 0),
            ];
        }

        // Sort by total_points descending, then by perfect_days descending as tiebreaker
        usort($entries, function ($a, $b) {
            $cmp = $b['total_points'] <=> $a['total_points'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['perfect_days'] <=> $a['perfect_days'];
        });

        // Assign ranks
        $ranked = [];
        foreach ($entries as $index => $entry) {
            $entry['rank'] = $index + 1;
            $ranked[] = $entry;
        }

        $leaderboard = [
            'month'        => $month,
            'generated_at' => time(),
            'total_waiters' => count($ranked),
            'rankings'     => $ranked,
        ];

        $this->database->getReference('waiter_leaderboard/' . $month)->set($leaderboard);

        return $leaderboard;
    }

    /**
     * Live leaderboard: hitung dari scratch setiap call.
     *
     * Sebelumnya leaderboard di-snapshot sekali sehari (cron 06:00). Akibatnya
     * manual_bonus / rack_recheck / penalty yang masuk siang/sore tidak terlihat
     * sampai keesokan harinya. Sekarang dihitung live agar selalu match dengan
     * angka yang waiter lihat di dashboard.
     *
     * Volume: ~8 waiter aktif × ~6 firebase reads = acceptable per page load.
     * Snapshot di /waiter_leaderboard/{month} tetap dibuat oleh cron untuk
     * audit history bulanan, tapi BUKAN dipakai untuk display.
     */
    public function getLeaderboard(string $month): array
    {
        $waiters = $this->firebase->getActiveWaiters();
        $rankings = [];

        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '') {
                continue;
            }

            // Prefer finalized summary; fall back to live calculation.
            $summary = $this->getMonthlyBonusSummary($waiterId, $month);
            if ($summary === null) {
                $summary = $this->calculateMonthlyBonus($waiterId, $month);
            }

            $rankings[] = [
                'waiter_id'         => $waiterId,
                'waiter_name'       => (string) ($waiter['name'] ?? ''),
                'total_points'      => (int) ($summary['net_points'] ?? 0),
                'points_percentage' => (float) ($summary['points_percentage'] ?? 0),
                'perfect_days'      => (int) ($summary['perfect_days'] ?? 0),
                'penalty_count'     => (int) ($summary['penalty_count'] ?? 0),
                'total_bonus'       => (int) ($summary['total_bonus'] ?? 0),
                'points_bonus'      => (int) ($summary['points_bonus'] ?? 0),
                'sales_bonus'       => (int) ($summary['sales_bonus'] ?? 0),
            ];
        }

        // Sort by total_points desc, perfect_days desc tiebreaker.
        usort($rankings, function ($a, $b) {
            $cmp = $b['total_points'] <=> $a['total_points'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['perfect_days'] <=> $a['perfect_days'];
        });

        foreach ($rankings as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }
        unset($entry);

        return [
            'month'         => $month,
            'generated_at'  => time(),
            'total_waiters' => count($rankings),
            'rankings'      => $rankings,
            'live'          => true,
        ];
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    /**
     * Resolve the points bonus tier based on percentage of theoretical max.
     *
     * @return array  ['tier' => string, 'bonus_amount' => int]
     */
    public function resolvePointsTier(float $percentage, array $config): array
    {
        $tiers = $config['point_bonus_tiers'] ?? $this->getDefaultConfig()['point_bonus_tiers'];

        // Tiers are checked from highest to lowest min_percentage
        $sorted = $tiers;
        uasort($sorted, function ($a, $b) {
            return ((int) ($b['min_percentage'] ?? 0)) <=> ((int) ($a['min_percentage'] ?? 0));
        });

        foreach ($sorted as $tierKey => $tier) {
            if ($percentage >= (float) ($tier['min_percentage'] ?? 0)) {
                return [
                    'tier'         => $tierKey,
                    'bonus_amount' => (int) ($tier['bonus_amount'] ?? 0),
                ];
            }
        }

        // Fallback: lowest tier
        return [
            'tier'         => 'tier_4',
            'bonus_amount' => 0,
        ];
    }

    /**
     * Resolve the sales bonus tier based on achievement percentage.
     *
     * @return array  ['tier' => string, 'bonus_amount' => int]
     */
    public function resolveSalesTier(float $percentage, array $config): array
    {
        $tiers = $config['sales_bonus_tiers'] ?? $this->getDefaultConfig()['sales_bonus_tiers'];

        $sorted = $tiers;
        uasort($sorted, function ($a, $b) {
            return ((int) ($b['min_percentage'] ?? 0)) <=> ((int) ($a['min_percentage'] ?? 0));
        });

        foreach ($sorted as $tierKey => $tier) {
            if ($percentage >= (float) ($tier['min_percentage'] ?? 0)) {
                return [
                    'tier'         => $tierKey,
                    'bonus_amount' => (int) ($tier['bonus_amount'] ?? 0),
                ];
            }
        }

        return [
            'tier'         => 'tier_4',
            'bonus_amount' => 0,
        ];
    }

    /**
     * Calculate perfect day bonus.
     * Awards bonus points when ALL daily-scored categories have a value > 0.
     * Monthly categories (service, sales) are excluded from perfect day check.
     *
     * Special case: rack_recheck is excluded from perfect day check if waiter
     * had ZERO rack_check tasks that day (kategori tidak relevan untuk waiter
     * tersebut, mis. role kasir/finance).
     */
    public function calculatePerfectDayBonus(array $categoryScores, array $config): int
    {
        $categories = $config['point_categories'] ?? $this->getDefaultConfig()['point_categories'];
        $bonus = (int) ($config['perfect_day_bonus'] ?? 5);

        foreach ($categories as $key => $meta) {
            // Skip monthly-scored categories
            if (($meta['scoring_type'] ?? 'daily') === 'monthly') {
                continue;
            }
            // Skip rack_recheck kalau tidak ada di scores (waiter tanpa task rack_check)
            if ($key === 'rack_recheck' && ! isset($categoryScores[$key])) {
                continue;
            }
            if (! isset($categoryScores[$key]) || (int) $categoryScores[$key] <= 0) {
                return 0;
            }
        }

        return $bonus;
    }
}
