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
    //  PERIOD HELPERS
    // =========================================================================

    /**
     * Get the current rolling 30-day period.
     *
     * @return array{start:string, end:string, key:string, label:string}
     */
    public function getCurrentPeriod(): array
    {
        $today = date('Y-m-d');
        $end = $today;
        $start = date('Y-m-d', strtotime('-29 days', strtotime($today)));
        $key = $start . '_' . $end;

        return [
            'start' => $start,
            'end'   => $end,
            'key'   => $key,
            'label' => date('d/m', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end)),
        ];
    }

    /**
     * Convert a Y-m month string to a date range covering that calendar month.
     * Backward compatibility for callers still passing $month.
     *
     * @return array{start:string, end:string, key:string}
     */
    public function monthToPeriod(string $month): array
    {
        $start = $month . '-01';
        $end = $month . '-31';
        $key = $start . '_' . $end;

        return ['start' => $start, 'end' => $end, 'key' => $key];
    }

    /**
     * Resolve period from optional start/end dates or fall back to current period.
     *
     * @return array{start:string, end:string, key:string}
     */
    public function resolvePeriod(?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate !== null && $endDate !== null) {
            return ['start' => $startDate, 'end' => $endDate, 'key' => $startDate . '_' . $endDate];
        }

        return $this->getCurrentPeriod();
    }

    /**
     * Get the configured period working days (default 30 for rolling 30-day window).
     */
    public function getPeriodWorkingDays(?array $config = null): int
    {
        $config ??= $this->getBonusConfig();

        return (int) ($config['working_days_per_period'] ?? $config['working_days_per_month'] ?? 30);
    }

    /**
     * Format period dates into a human-readable label.
     */
    public function formatPeriodLabel(string $startDate, string $endDate): string
    {
        return date('d/m', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate));
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
            'is_active'               => true,
            'working_days_per_month'   => 26,  // backward compat — prefer working_days_per_period
            'working_days_per_period'  => 30,  // rolling 30-day window
            'total_bonus_pool'         => 500000,
            'perfect_day_bonus'        => 5,
            'daily_max_points'         => 30,

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
     * Build the canonical period points capacity breakdown.
     *
     * @param int|null $workingDays  Override working days; defaults to config value.
     */
    public function getPeriodPointsCapacity(?array $config = null, ?int $workingDays = null): array
    {
        $config ??= $this->getBonusConfig();

        $workingDays ??= $this->getPeriodWorkingDays($config);
        $dailyMaxPoints = (int) ($config['daily_max_points'] ?? 20);
        $perfectDayBonus = (int) ($config['perfect_day_bonus'] ?? 5);
        $dailyMaxWithPerfect = $dailyMaxPoints + $perfectDayBonus;
        $serviceMaxPerDay = $this->getCategoryMaxPoints($config, 'service', 5);
        $salesMaxPerDay = $this->getCategoryMaxPoints($config, 'sales', 5);
        $serviceMax = $serviceMaxPerDay * $workingDays;
        $salesMax = $salesMaxPerDay * $workingDays;

        return [
            'working_days'                => $workingDays,
            'daily_max_points'            => $dailyMaxPoints,
            'perfect_day_bonus'           => $perfectDayBonus,
            'daily_max_with_perfect'      => $dailyMaxWithPerfect,
            'monthly_service_max_per_day' => $serviceMaxPerDay,
            'monthly_sales_max_per_day'   => $salesMaxPerDay,
            'monthly_service_max'         => $serviceMax,
            'monthly_sales_max'           => $salesMax,
            'theoretical_max'             => ($dailyMaxWithPerfect * $workingDays) + $serviceMax + $salesMax,
        ];
    }

    // backward compat
    public function getMonthlyPointsCapacity(?array $config = null): array
    {
        return $this->getPeriodPointsCapacity($config, null);
    }

    /**
     * Build canonical waiter-facing progress data for a date range.
     *
     * @param  string       $waiterId
     * @param  string|null  $startDate  Format 'Y-m-d', default = 30 days ago
     * @param  string|null  $endDate    Format 'Y-m-d', default = today
     * @return array
     */
    public function getWaiterProgress(string $waiterId, ?string $startDate = null, ?string $endDate = null): array
    {
        $period = $this->resolvePeriod($startDate, $endDate);
        $startDate = $period['start'];
        $endDate = $period['end'];
        $periodKey = $period['key'];

        $config = $this->getBonusConfig();
        $workingDays = $this->getPeriodWorkingDays($config);
        $capacity = $this->getPeriodPointsCapacity($config, $workingDays);

        $periodPoints = $this->getDailyPointsInRange($waiterId, $startDate, $endDate);
        $penalties = $this->getPenaltiesByPeriod($startDate, $endDate, $waiterId);
        $bonusSummary = $this->getBonusSummary($waiterId, $periodKey);
        $leaderboard = $this->getLeaderboard($startDate, $endDate);
        $manualBonusTotal = $this->sumManualBonusForPeriod($waiterId, $startDate, $endDate);

        // Sales target: tetap pakai bulan kalender dari endDate untuk backward compat
        $salesTargetMonth = substr($endDate, 0, 7);
        $salesTarget = $this->getSalesTarget($waiterId, $salesTargetMonth);

        $totalEarned = 0;
        $penaltySignedTotal = 0;
        $totalPenalties = 0;
        $daysScored = 0;
        $perfectDays = 0;

        foreach ($periodPoints as $record) {
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

        $campaignPoints = 0;
        $campaignBreakdown = ['total_approved' => 0, 'total_pending' => 0, 'total_rejected' => 0, 'approved_claims' => [], 'pending_claims' => [], 'all_claims' => []];
        try {
            $campaignService = app(\App\Services\SalesCampaignService::class);
            $campaignPoints = (int) $campaignService->getUserCampaignPointsByRange($waiterId, $startDate, $endDate);
            $campaignBreakdown = $campaignService->getUserCampaignBreakdownByRange($waiterId, $startDate, $endDate);
        } catch (\Throwable $e) {
            // Fail open: if campaign service unavailable, treat as 0 points
        }

        $netPoints = max(0, $totalEarned + $servicePoints + $salesPoints + $penaltySignedTotal + $manualBonusTotal + $campaignPoints);
        $theoreticalMax = (int) $capacity['theoretical_max'];
        $percentage = $theoreticalMax > 0 ? round(($netPoints / $theoreticalMax) * 100, 1) : 0.0;

        return [
            'config'               => $config,
            'monthly_points'        => $periodPoints,  // keep key name for view compat
            'penalties'             => $penalties,
            'sales_target'          => $salesTarget,
            'bonus_summary'         => $bonusSummary,
            'leaderboard'           => $leaderboard,
            'total_earned'          => $totalEarned,
            'total_penalties'        => $totalPenalties,
            'penalty_signed_total'   => $penaltySignedTotal,
            'service_points'         => $servicePoints,
            'sales_points'           => $salesPoints,
            'manual_bonus_total'     => $manualBonusTotal,
            'campaign_points'        => $campaignPoints,
            'campaign_breakdown'     => $campaignBreakdown,
            'net_points'             => $netPoints,
            'days_scored'            => $daysScored,
            'perfect_days'           => $perfectDays,
            'percentage'             => $percentage,
            'period_start'           => $startDate,
            'period_end'             => $endDate,
            'period_key'             => $periodKey,
            'period_label'           => $this->formatPeriodLabel($startDate, $endDate),
        ] + $capacity;
    }

    /**
     * Backward compat: getWaiterMonthlyProgress accepts Y-m month string.
     * Converts to date range and delegates to getWaiterProgress.
     */
    public function getWaiterMonthlyProgress(string $waiterId, string $month): array
    {
        $period = $this->monthToPeriod($month);

        return $this->getWaiterProgress($waiterId, $period['start'], $period['end']);
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
     * Service and Sales are scored per period (percentage) at finalization time.
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
     * Get all daily points for a waiter in a date range.
     *
     * @param  string  $waiterId
     * @param  string  $startDate  Format 'Y-m-d'
     * @param  string  $endDate    Format 'Y-m-d'
     * @return array  date => daily_record
     */
    public function getDailyPointsInRange(string $waiterId, string $startDate, string $endDate): array
    {
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
     * Backward compat: get monthly daily points from Y-m string.
     */
    public function getMonthlyDailyPoints(string $waiterId, string $month): array
    {
        $startDate = $month . '-01';
        $endDate = $month . '-31';

        return $this->getDailyPointsInRange($waiterId, $startDate, $endDate);
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
     */
    public function autoScoreDailyPoints(string $waiterId, string $date, ?array $attendance = null, array $waiterTasks = [], array $waiterReports = []): array
    {
        $config = $this->getBonusConfig();

        // DISCIPLINE (max 5)
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

        // OPERATIONAL (max 10)
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
            $operationalScore = $operationalMax;
            $operationalReason = 'Tidak ada tugas umum dijadwalkan (default poin penuh)';
        }

        // ATTITUDE (max 5)
        $attitudeScore = 0;
        $attitudeReason = 'Belum submit laporan';

        if (! empty($waiterReports)) {
            $attitudeScore = 5;
            $attitudeReason = 'Laporan kegiatan disubmit';
        }

        // RACK_RECHECK (max 10)
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
                $sumPoints = 0;
                foreach ($reviewedRackTasks as $rt) {
                    $sumPoints += max(0, min(10, (int) ($rt['recheck_points'] ?? 0)));
                }
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

        if ($penaltyType === 'mandatory_task_missed' && $relatedTaskId !== '') {
            $dedupKey = sha1(implode('|', [$penaltyType, $waiterId, $relatedTaskId]));
        } else {
            $dedupKey = sha1(implode('|', [$penaltyType, $waiterId, $date, $relatedTaskId]));
        }
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

        $penaltyKey = $this->database->getReference('waiter_penalties')->push()->getKey();

        try {
            $this->database->runTransaction(function ($transaction) use (&$claimed, &$indexRecord, $indexRef, $waiterId, $penaltyType, $date, $pointsDeducted, $relatedTaskId, $penaltyKey) {
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
                    'penalty_id' => $penaltyKey,
                ];

                $transaction->set($indexRef, $newRecord);
                $claimed = true;
                $indexRecord = $newRecord;
            });
        } catch (TransactionFailed $e) {
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

        $this->database->getReference('waiter_penalties/' . $penaltyKey)->set($record);

        if (config('features.mysql_penalties')) {
            try {
                \App\Models\WaiterPenalty::updateOrCreate(
                    ['firebase_legacy_key' => (string) $penaltyKey],
                    [
                        'waiter_id' => (string) $record['waiter_id'],
                        'waiter_name' => $record['waiter_name'] ?: null,
                        'penalty_type' => $record['penalty_type'] ?? null,
                        'penalty_label' => $record['penalty_label'] ?? null,
                        'points_deducted' => (int) $record['points_deducted'],
                        'date' => $record['date'],
                        'month' => $record['month'] ?? null,
                        'reason' => $record['reason'] ?: null,
                        'evidence_photo_url' => $record['evidence_photo_url'] ?: null,
                        'related_task_id' => $record['related_task_id'] ?: null,
                        'event_created_at' => $record['created_at'] ?? null,
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $penaltyId = $penaltyKey;

        return [
            'success'         => true,
            'penalty_id'      => $penaltyId,
            'points_deducted' => $pointsDeducted,
        ];
    }

    /**
     * Get penalties filtered by date range and optionally by waiter.
     *
     * @param  string       $startDate  Format 'Y-m-d'
     * @param  string       $endDate    Format 'Y-m-d'
     * @param  string|null  $waiterId
     * @return array
     */
    public function getPenaltiesByPeriod(string $startDate, string $endDate, ?string $waiterId = null): array
    {
        if (config('features.mysql_penalties')) {
            $effectiveFrom = $this->getEffectiveFromDate();
            $query = \App\Models\WaiterPenalty::query()
                ->whereBetween('date', [$startDate, $endDate]);
            if ($waiterId !== null) {
                $query->where('waiter_id', $waiterId);
            }
            if ($effectiveFrom !== null) {
                $query->where('date', '>=', $effectiveFrom);
            }

            return $query->orderByDesc('event_created_at')
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->firebase_legacy_key ?: (string) $row->id,
                        'waiter_id' => $row->waiter_id,
                        'waiter_name' => $row->waiter_name,
                        'penalty_type' => $row->penalty_type,
                        'penalty_label' => $row->penalty_label,
                        'points_deducted' => $row->points_deducted,
                        'date' => optional($row->date)->format('Y-m-d'),
                        'month' => $row->month,
                        'reason' => $row->reason,
                        'evidence_photo_url' => $row->evidence_photo_url,
                        'related_task_id' => $row->related_task_id,
                        'created_at' => $row->event_created_at,
                    ];
                })->all();
        }

        // Query by date range using startAt/endAt on the 'date' child key
        $reference = $this->database->getReference('waiter_penalties')
            ->orderByChild('date')
            ->startAt($startDate)
            ->endAt($endDate);
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
     * Backward compat: get penalties by Y-m month.
     */
    public function getPenaltiesByMonth(string $month, ?string $waiterId = null): array
    {
        return $this->getPenaltiesByPeriod($month . '-01', $month . '-31', $waiterId);
    }

    /**
     * Delete a penalty record AND its dedupe index entry.
     */
    public function deletePenalty(string $penaltyId): void
    {
        $penaltyRef = $this->database->getReference('waiter_penalties/' . $penaltyId);
        $penalty = $penaltyRef->getValue();

        if (! is_array($penalty)) {
            return;
        }

        $penaltyType = (string) ($penalty['penalty_type'] ?? '');
        $waiterId = (string) ($penalty['waiter_id'] ?? '');
        $date = (string) ($penalty['date'] ?? '');
        $relatedTaskId = (string) ($penalty['related_task_id'] ?? '');

        if ($penaltyType !== '' && $waiterId !== '' && $date !== '') {
            $dedupKey = sha1(implode('|', [$penaltyType, $waiterId, $date, $relatedTaskId]));

            $this->database->getReference()->update([
                'waiter_penalties/' . $penaltyId => null,
                'waiter_penalties_index/' . $dedupKey => null,
            ]);

            return;
        }

        $penaltyRef->remove();
    }

    // ========================================================================
    // MANUAL BONUS POINTS (supervisor adjustment, additive ke daily/period)
    // ========================================================================

    /**
     * Apply 1 manual bonus untuk 1 karyawan.
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
            'bonus_id'    => $bonusId,
            'waiter_id'   => $waiterId,
            'waiter_name' => $waiterName,
            'month'       => $month,
            'date'        => $date,
            'points'      => $points,
            'reason'      => $reason,
            'category'    => $points >= 0 ? 'manual_bonus' : 'manual_deduction',
            'created_by'  => $createdBy,
            'created_at'  => time(),
        ];
        $ref->set($record);

        if (config('features.mysql_manual_bonuses')) {
            try {
                \App\Models\WaiterManualBonus::updateOrCreate(
                    ['firebase_legacy_key' => (string) $bonusId],
                    [
                        'waiter_id' => (string) $record['waiter_id'],
                        'waiter_name' => $record['waiter_name'] ?: null,
                        'month' => $record['month'] ?? null,
                        'date' => $record['date'],
                        'points' => (int) $record['points'],
                        'reason' => $record['reason'] ?: null,
                        'category' => $record['category'] ?? null,
                        'created_by' => $record['created_by'] ?? null,
                        'event_created_at' => $record['created_at'] ?? null,
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'success'  => true,
            'bonus_id' => $bonusId,
            'points'   => $points,
            'message'  => 'Manual bonus tersimpan.',
        ];
    }

    /**
     * Apply manual bonus ke banyak karyawan sekaligus.
     */
    public function applyManualBonusBulk(array $waiterIds, int $points, string $reason, string $date, string $createdBy = 'supervisor'): array
    {
        $applied = 0;
        $failed = 0;
        $results = [];

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
                'waiter_id'   => $wid,
                'waiter_name' => $waiterMap[$wid] ?? '',
                'points'      => $points,
                'reason'      => $reason,
                'date'        => $date,
                'created_by'  => $createdBy,
            ]);
            if ($r['success']) {
                $applied++;
            } else {
                $failed++;
            }
            $results[] = [
                'waiter_id'   => $wid,
                'waiter_name' => $waiterMap[$wid] ?? '',
                'success'     => $r['success'],
                'message'     => $r['message'] ?? '',
                'bonus_id'    => $r['bonus_id'] ?? null,
            ];
        }

        return [
            'success' => $applied > 0,
            'applied' => $applied,
            'failed'  => $failed,
            'results' => $results,
        ];
    }

    /**
     * Get manual bonuses filtered by date range.
     * Read full node + filter PHP-side (low volume).
     *
     * @return array<int, array>
     */
    public function getManualBonusesByPeriod(string $startDate, string $endDate, ?string $waiterId = null): array
    {
        if (config('features.mysql_manual_bonuses')) {
            $query = \App\Models\WaiterManualBonus::query()
                ->whereBetween('date', [$startDate, $endDate]);
            if ($waiterId !== null) {
                $query->where('waiter_id', $waiterId);
            }

            return $query->orderByDesc('date')
                ->orderByDesc('event_created_at')
                ->get()
                ->map(function ($row) {
                    return [
                        'bonus_id' => $row->firebase_legacy_key ?: (string) $row->id,
                        'waiter_id' => $row->waiter_id,
                        'waiter_name' => $row->waiter_name,
                        'month' => $row->month,
                        'date' => optional($row->date)->format('Y-m-d'),
                        'points' => $row->points,
                        'reason' => $row->reason,
                        'category' => $row->category,
                        'created_by' => $row->created_by,
                        'created_at' => $row->event_created_at,
                    ];
                })->all();
        }

        // Bound by date child server-side (needs .indexOn ["date"]) instead of
        // reading the entire waiter_manual_bonuses node then filtering in PHP.
        $snapshot = $this->database->getReference('waiter_manual_bonuses')
            ->orderByChild('date')
            ->startAt($startDate)
            ->endAt($endDate)
            ->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $items = [];
        foreach ((array) $snapshot->getValue() as $id => $row) {
            $row = (array) $row;
            $rowDate = (string) ($row['date'] ?? '');
            if ($rowDate < $startDate || $rowDate > $endDate) {
                continue;
            }
            if ($waiterId !== null && ($row['waiter_id'] ?? '') !== $waiterId) {
                continue;
            }
            $row['bonus_id'] = $id;
            $items[] = $row;
        }

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
     * Backward compat: get manual bonuses by Y-m month.
     */
    public function getManualBonusesByMonth(string $month, ?string $waiterId = null): array
    {
        return $this->getManualBonusesByPeriod($month . '-01', $month . '-31', $waiterId);
    }

    /**
     * Sum total manual bonus poin untuk waiter dalam date range.
     */
    public function sumManualBonusForPeriod(string $waiterId, string $startDate, string $endDate): int
    {
        $items = $this->getManualBonusesByPeriod($startDate, $endDate, $waiterId);
        $total = 0;
        foreach ($items as $b) {
            $total += (int) ($b['points'] ?? 0);
        }

        return $total;
    }

    /**
     * Backward compat: sum manual bonus by Y-m month.
     */
    public function sumManualBonusForMonth(string $waiterId, string $month): int
    {
        return $this->sumManualBonusForPeriod($waiterId, $month . '-01', $month . '-31');
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
     * Bangun timeline kronologis "kapan poin masuk" untuk satu waiter dalam date
     * range. Gabungan dari 3 sumber: rack_recheck, manual_bonus, penalty.
     *
     * @param  string  $waiterId
     * @param  string  $startDate  Format 'Y-m-d'
     * @param  string  $endDate    Format 'Y-m-d'
     * @return array<int, array>
     */
    public function getWaiterPointEvents(string $waiterId, string $startDate, string $endDate): array
    {
        $events = [];

        // --- 1. rack_recheck events ---
        $candidates = [];

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

                $taskDate = (string) (substr((string) ($task['scheduled_for_date'] ?? ''), 0, 10));
                if ($taskDate < $startDate || $taskDate > $endDate) {
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
            $bonuses = $this->getManualBonusesByPeriod($startDate, $endDate, $waiterId);
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
            $penalties = $this->getPenaltiesByPeriod($startDate, $endDate, $waiterId);
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
     * Wipe ALL bonus-related historical data.
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
     * Auto-apply penalties based on PRE-FETCHED data.
     */
    public function autoApplyPenalties(string $waiterId, string $waiterName, string $date, ?array $attendance = null, array $waiterTasks = [], array $existingPenalties = []): array
    {
        $applied = [];

        $existingKeys = [];
        foreach ($existingPenalties as $p) {
            if (($p['date'] ?? '') === $date) {
                $existingKeys[] = ($p['penalty_type'] ?? '') . '_' . ($p['related_task_id'] ?? '');
            }
        }

        // 1. LATE ARRIVAL
        $attendanceStatus = (string) ($attendance['status'] ?? '');
        if ($attendance && ((int) ($attendance['late_minutes'] ?? 0)) > 0 && $attendanceStatus !== 'absent') {
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

        // 2. ABSENT
        if ($attendanceStatus === 'absent') {
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

        // 3. MANDATORY TASK MISSED
        if (in_array($attendanceStatus, ['sick', 'day_off'], true)) {
            return $applied;
        }

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
     * Set a period sales target for a waiter.
     *
     * @param  string  $waiterId
     * @param  string  $periodKey   Format 'Y-m-d_Y-m-d' (or Y-m for backward compat)
     * @param  int     $targetAmount
     * @param  string  $role
     */
    public function setSalesTarget(string $waiterId, string $periodKey, int $targetAmount, string $role): void
    {
        $path = 'waiter_sales_targets/' . $waiterId . '/' . $periodKey;

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
            'period_key'          => $periodKey,
            'target_amount'       => $targetAmount,
            'role'                => $role,
            'current_achievement' => $currentAchievement,
            'daily_sales'         => $dailySales ?: null,
            'updated_at'          => time(),
        ]);
    }

    /**
     * Record daily sales for a waiter and update cumulative achievement.
     *
     * Uses the current period key derived from the date.
     */
    public function recordDailySales(string $waiterId, string $date, int $amount, int $itemsSold = 0): void
    {
        $period = $this->getCurrentPeriod();
        $periodKey = $period['key'];

        // Try current period first, fallback to month-based lookup
        $path = 'waiter_sales_targets/' . $waiterId . '/' . $periodKey;
        $snapshot = $this->database->getReference($path)->getSnapshot();

        // If no period-based target, try month-based for backward compat
        if (! $snapshot->exists()) {
            $month = substr($date, 0, 7);
            $path = 'waiter_sales_targets/' . $waiterId . '/' . $month;
            $snapshot = $this->database->getReference($path)->getSnapshot();
        }

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
     * Get sales target and achievement for a waiter in a period/month.
     */
    public function getSalesTarget(string $waiterId, string $periodKey): ?array
    {
        $snapshot = $this->database->getReference('waiter_sales_targets/' . $waiterId . '/' . $periodKey)->getSnapshot();

        return $snapshot->exists() ? (array) $snapshot->getValue() : null;
    }

    /**
     * Get all waiters' sales targets for a given period/month.
     */
    public function getAllSalesTargets(string $periodKey): array
    {
        $snapshot = $this->database->getReference('waiter_sales_targets')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $results = [];

        foreach ((array) $snapshot->getValue() as $waiterId => $keys) {
            if (! is_array($keys) || ! isset($keys[$periodKey])) {
                continue;
            }

            $results[$waiterId] = (array) $keys[$periodKey];
        }

        return $results;
    }

    // =========================================================================
    //  PERIOD BONUS CALCULATION
    // =========================================================================

    /**
     * Calculate the full period bonus for a waiter.
     *
     * @param  string    $waiterId
     * @param  string    $startDate                 Format 'Y-m-d'
     * @param  string    $endDate                   Format 'Y-m-d'
     * @param  int|null  $servicePercentage  0-100
     * @param  int|null  $salesPercentage    0-100
     * @return array
     */
    public function calculateBonus(string $waiterId, string $startDate, string $endDate, ?int $servicePercentage = null, ?int $salesPercentage = null): array
    {
        $periodKey = $startDate . '_' . $endDate;
        $config = $this->getBonusConfig();
        $workingDays = $this->getPeriodWorkingDays($config);
        $capacity = $this->getPeriodPointsCapacity($config, $workingDays);
        $maxBonusTotal = (int) ($config['total_bonus_pool'] ?? 500000);

        $serviceMaxPerDay = (int) $capacity['monthly_service_max_per_day'];
        $salesMaxPerDay = (int) $capacity['monthly_sales_max_per_day'];
        $theoreticalMax = (int) $capacity['theoretical_max'];

        // Read existing percentages from summary if not provided
        if ($servicePercentage === null || $salesPercentage === null) {
            $existingSummary = $this->getBonusSummary($waiterId, $periodKey);
            if ($servicePercentage === null) {
                $servicePercentage = (int) ($existingSummary['period_service_percentage'] ?? $existingSummary['monthly_service_percentage'] ?? 0);
            }
            if ($salesPercentage === null) {
                $salesPercentage = (int) ($existingSummary['period_sales_percentage'] ?? $existingSummary['monthly_sales_percentage'] ?? 0);
            }
        }

        $servicePercentage = max(0, min(100, $servicePercentage));
        $salesPercentage = max(0, min(100, $salesPercentage));

        $servicePoints = (int) round(($servicePercentage / 100) * $serviceMaxPerDay * $workingDays);
        $salesPoints = (int) round(($salesPercentage / 100) * $salesMaxPerDay * $workingDays);

        // Daily points
        $dailyPoints = $this->getDailyPointsInRange($waiterId, $startDate, $endDate);
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

        // Penalties
        $penalties = $this->getPenaltiesByPeriod($startDate, $endDate, $waiterId);
        $totalPenalties = 0;
        $penaltyCount = count($penalties);

        foreach ($penalties as $penalty) {
            $totalPenalties += (int) ($penalty['points_deducted'] ?? 0);
        }

        // Manual bonus
        $manualBonuses = $this->getManualBonusesByPeriod($startDate, $endDate, $waiterId);
        $totalManualBonus = 0;
        $manualBonusCount = count($manualBonuses);
        foreach ($manualBonuses as $mb) {
            $totalManualBonus += (int) ($mb['points'] ?? 0);
        }

        // Campaign points
        $campaignPoints = 0;
        try {
            $campaignService = app(SalesCampaignService::class);
            $campaignPoints = $campaignService->getUserCampaignPointsByRange($waiterId, $startDate, $endDate);
        } catch (\Throwable $e) {
            // SalesCampaignService not available — skip
        }

        $netPoints = $totalEarned + $servicePoints + $salesPoints + $totalPenalties + $totalManualBonus + $campaignPoints;
        $netPoints = max(0, $netPoints);
        $pointsPercentage = $theoreticalMax > 0
            ? round(($netPoints / $theoreticalMax) * 100, 2)
            : 0;

        $pointsTierResult = $this->resolvePointsTier($pointsPercentage, $config);
        $pointsBonus = (int) ($pointsTierResult['bonus_amount'] ?? 0);

        // Sales target — try period key first, fall back to month
        $salesTarget = $this->getSalesTarget($waiterId, $periodKey);
        if ($salesTarget === null) {
            $month = substr($endDate, 0, 7);
            $salesTarget = $this->getSalesTarget($waiterId, $month);
        }
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

        $totalBonus = min($pointsBonus + $salesBonus, $maxBonusTotal);

        $waiter = $this->firebase->getWaiterById($waiterId);

        return [
            'waiter_id'              => $waiterId,
            'waiter_name'            => (string) ($waiter['name'] ?? ''),
            'waiter_email'           => (string) ($waiter['email'] ?? ''),
            'period_key'             => $periodKey,
            'period_start'           => $startDate,
            'period_end'             => $endDate,
            'period_label'           => $this->formatPeriodLabel($startDate, $endDate),

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

            'period_service_percentage' => $servicePercentage,
            'period_sales_percentage'   => $salesPercentage,
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
     * Backward compat: calculate bonus by Y-m month.
     */
    public function calculateMonthlyBonus(string $waiterId, string $month, ?int $monthlyServicePercentage = null, ?int $monthlySalesPercentage = null): array
    {
        return $this->calculateBonus($waiterId, $month . '-01', $month . '-31', $monthlyServicePercentage, $monthlySalesPercentage);
    }

    /**
     * Calculate and finalize the period bonus, saving to Firebase.
     *
     * @param  string    $waiterId
     * @param  string    $startDate          Format 'Y-m-d'
     * @param  string    $endDate            Format 'Y-m-d'
     * @param  int|null  $servicePercentage  0-100
     * @param  int|null  $salesPercentage    0-100
     * @return array
     */
    public function finalizeBonus(string $waiterId, string $startDate, string $endDate, ?int $servicePercentage = null, ?int $salesPercentage = null): array
    {
        $periodKey = $startDate . '_' . $endDate;
        $path = 'waiter_bonus_summary/' . $waiterId . '/' . $periodKey;
        $finalizedSummary = null;
        $alreadyFinalized = false;

        $this->database->runTransaction(function ($transaction) use ($path, $waiterId, $startDate, $endDate, $servicePercentage, $salesPercentage, &$finalizedSummary, &$alreadyFinalized) {
            $reference = $this->database->getReference($path);
            $snapshot = $transaction->snapshot($reference);
            $existing = $snapshot->exists() ? (array) $snapshot->getValue() : null;

            if (($existing['status'] ?? '') === 'finalized') {
                $alreadyFinalized = true;
                $finalizedSummary = $existing;

                return;
            }

            $summary = $this->calculateBonus($waiterId, $startDate, $endDate, $servicePercentage, $salesPercentage);
            $summary['status'] = 'finalized';
            $summary['finalized_at'] = time();
            $transaction->set($reference, $summary);
            $finalizedSummary = $summary;
        });

        if (config('features.mysql_bonus_summary') && is_array($finalizedSummary)) {
            try {
                \App\Models\WaiterBonusSummary::updateOrCreate(
                    ['waiter_id' => (string) $waiterId, 'period_key' => (string) $periodKey],
                    [
                        'status' => $finalizedSummary['status'] ?? 'finalized',
                        'finalized_at' => $finalizedSummary['finalized_at'] ?? time(),
                        'summary' => $finalizedSummary,
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($alreadyFinalized) {
            return array_merge($finalizedSummary ?? [], [
                'success' => false,
                'already_finalized' => true,
                'message' => 'Bonus periode ini sudah difinalisasi.',
            ]);
        }

        $totalBonus = (int) ($finalizedSummary['total_bonus'] ?? 0);
        if ($totalBonus > 0) {
            try {
                $payroll = app(\App\Services\PayrollService::class);
                $payroll->creditBonusIfEligible($waiterId, $periodKey, $totalBonus, $startDate, $endDate);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return array_merge($finalizedSummary ?? [], ['success' => true]);
    }

    /**
     * Backward compat: finalize bonus by Y-m month.
     */
    public function finalizeMonthlyBonus(string $waiterId, string $month, ?int $monthlyServicePercentage = null, ?int $monthlySalesPercentage = null): array
    {
        return $this->finalizeBonus($waiterId, $month . '-01', $month . '-31', $monthlyServicePercentage, $monthlySalesPercentage);
    }

    /**
     * Override the bonus amount for a waiter in a given period.
     */
    public function overrideBonus(string $waiterId, string $periodKey, int $amount, string $reason): void
    {
        $path = 'waiter_bonus_summary/' . $waiterId . '/' . $periodKey;

        $this->database->getReference($path)->update([
            'admin_override'  => true,
            'override_amount' => $amount,
            'override_reason' => $reason,
            'total_bonus'     => $amount,
            'updated_at'      => time(),
        ]);
    }

    /**
     * Get the stored period bonus summary for a waiter.
     */
    public function getBonusSummary(string $waiterId, string $periodKey): ?array
    {
        $snapshot = $this->database->getReference('waiter_bonus_summary/' . $waiterId . '/' . $periodKey)->getSnapshot();

        return $snapshot->exists() ? (array) $snapshot->getValue() : null;
    }

    /**
     * Backward compat: get summary by Y-m month.
     */
    public function getMonthlyBonusSummary(string $waiterId, string $month): ?array
    {
        return $this->getBonusSummary($waiterId, $month . '-01_' . $month . '-31');
    }

    /**
     * Get all waiters' bonus summaries for a given period key.
     */
    public function getAllBonusSummaries(string $periodKey): array
    {
        if (config('features.mysql_bonus_summary')) {
            $results = [];
            foreach (\App\Models\WaiterBonusSummary::forPeriod($periodKey)->get() as $row) {
                $results[$row->waiter_id] = is_array($row->summary) ? $row->summary : [];
            }
            return $results;
        }

        $snapshot = $this->database->getReference('waiter_bonus_summary')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $results = [];

        foreach ((array) $snapshot->getValue() as $waiterId => $keys) {
            if (! is_array($keys) || ! isset($keys[$periodKey])) {
                continue;
            }

            $results[$waiterId] = (array) $keys[$periodKey];
        }

        return $results;
    }

    /**
     * Backward compat: get all summaries by Y-m month.
     */
    public function getAllMonthlyBonusSummaries(string $month): array
    {
        return $this->getAllBonusSummaries($month . '-01_' . $month . '-31');
    }

    // =========================================================================
    //  LEADERBOARD
    // =========================================================================

    /**
     * Generate and save a leaderboard for all active waiters in a date range.
     */
    public function generateLeaderboard(?string $startDate = null, ?string $endDate = null): array
    {
        $period = $this->resolvePeriod($startDate, $endDate);
        $startDate = $period['start'];
        $endDate = $period['end'];
        $periodKey = $period['key'];

        $waiters = $this->firebase->getActiveWaiters();
        $entries = [];

        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '') {
                continue;
            }

            $summary = $this->getBonusSummary($waiterId, $periodKey);
            if ($summary === null) {
                $summary = $this->calculateBonus($waiterId, $startDate, $endDate);
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

        usort($entries, function ($a, $b) {
            $cmp = $b['total_points'] <=> $a['total_points'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['perfect_days'] <=> $a['perfect_days'];
        });

        $ranked = [];
        foreach ($entries as $index => $entry) {
            $entry['rank'] = $index + 1;
            $ranked[] = $entry;
        }

        $leaderboard = [
            'period_key'    => $periodKey,
            'period_start'  => $startDate,
            'period_end'    => $endDate,
            'period_label'  => $this->formatPeriodLabel($startDate, $endDate),
            'generated_at'  => time(),
            'total_waiters' => count($ranked),
            'rankings'      => $ranked,
        ];

        $this->database->getReference('waiter_leaderboard/' . $periodKey)->set($leaderboard);

        return $leaderboard;
    }

    /**
     * Live leaderboard: hitung dari scratch setiap call.
     *
     * @param  string|null  $startDate  Format 'Y-m-d', default = 30 days ago
     * @param  string|null  $endDate    Format 'Y-m-d', default = today
     */
    public function getLeaderboard(?string $startDate = null, ?string $endDate = null): array
    {
        $period = $this->resolvePeriod($startDate, $endDate);
        $startDate = $period['start'];
        $endDate = $period['end'];
        $periodKey = $period['key'];

        $waiters = $this->firebase->getActiveWaiters();
        $rankings = [];

        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '') {
                continue;
            }

            $summary = $this->getBonusSummary($waiterId, $periodKey);
            if ($summary === null) {
                $summary = $this->calculateBonus($waiterId, $startDate, $endDate);
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
            'period_key'    => $periodKey,
            'period_start'  => $startDate,
            'period_end'    => $endDate,
            'period_label'  => $this->formatPeriodLabel($startDate, $endDate),
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
     */
    public function resolvePointsTier(float $percentage, array $config): array
    {
        $tiers = $config['point_bonus_tiers'] ?? $this->getDefaultConfig()['point_bonus_tiers'];

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
     * Resolve the sales bonus tier based on achievement percentage.
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
     */
    public function calculatePerfectDayBonus(array $categoryScores, array $config): int
    {
        $categories = $config['point_categories'] ?? $this->getDefaultConfig()['point_categories'];
        $bonus = (int) ($config['perfect_day_bonus'] ?? 5);

        foreach ($categories as $key => $meta) {
            if (($meta['scoring_type'] ?? 'daily') === 'monthly') {
                continue;
            }
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
