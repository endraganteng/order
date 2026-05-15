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
     */
    public function getBonusConfig(): array
    {
        $snapshot = $this->database->getReference('bonus_config')->getSnapshot();

        if ($snapshot->exists()) {
            return array_merge($this->getDefaultConfig(), (array) $snapshot->getValue());
        }

        return $this->getDefaultConfig();
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
            'daily_max_points' => 20,

            'point_categories' => [
                'discipline'  => ['name' => 'Disiplin', 'max_daily_points' => 5, 'sort_order' => 1, 'scoring_type' => 'daily'],
                'operational' => ['name' => 'Operasional', 'max_daily_points' => 10, 'sort_order' => 2, 'scoring_type' => 'daily'],
                'service'     => ['name' => 'Pelayanan', 'max_daily_points' => 5, 'sort_order' => 3, 'scoring_type' => 'monthly'],
                'sales'       => ['name' => 'Penjualan', 'max_daily_points' => 5, 'sort_order' => 4, 'scoring_type' => 'monthly'],
                'attitude'    => ['name' => 'Sikap', 'max_daily_points' => 5, 'sort_order' => 5, 'scoring_type' => 'daily'],
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
        $netPoints = max(0, $totalEarned + $servicePoints + $salesPoints + $penaltySignedTotal);
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
     * Only daily-scored categories (discipline, operational, attitude) are written.
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

    // =========================================================================
    //  AUTO DAILY SCORING
    // =========================================================================

    /**
     * Auto-calculate daily scores for a waiter based on PRE-FETCHED data.
     *
     * Daily auto-scored categories (3 only):
     * - discipline (max 5): from attendance record
     * - operational (max 10): from task completion ratio
     * - attitude (max 5): from activity report submission
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
        //  OPERATIONAL (max 10) — from task completion
        // -----------------------------------------------------------------
        $operationalScore = 0;
        $operationalReason = 'Tidak ada tugas dijadwalkan';

        $totalTasks = count($waiterTasks);

        if ($totalTasks > 0) {
            $completedTasks = count(array_filter($waiterTasks, function ($task) {
                return ($task['status'] ?? 'pending') === 'done';
            }));

            $operationalScore = (int) round(($completedTasks / $totalTasks) * 10);
            $operationalReason = $completedTasks . '/' . $totalTasks . ' tugas selesai';
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

        return [
            'discipline'  => $disciplineScore,
            'operational' => $operationalScore,
            'attitude'    => $attitudeScore,
            'auto_details' => [
                'discipline_reason'  => $disciplineReason,
                'operational_reason' => $operationalReason,
                'attitude_reason'    => $attitudeReason,
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
            $txResult = $indexRef->runTransaction(function ($current) use (&$claimed, $waiterId, $penaltyType, $date, $pointsDeducted, $relatedTaskId) {
                if ($current !== null) {
                    return $current;
                }

                $claimed = true;

                return [
                    'waiter_id' => $waiterId,
                    'penalty_type' => $penaltyType,
                    'date' => $date,
                    'related_task_id' => $relatedTaskId,
                    'points_deducted' => $pointsDeducted,
                    'created_at' => time(),
                    'penalty_id' => null,
                ];
            });

            if (is_object($txResult) && method_exists($txResult, 'snapshot')) {
                $indexRecord = $txResult->snapshot()->getValue();
            } else {
                $indexRecord = $indexRef->getValue();
            }
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

        $penalties = [];

        foreach ((array) $snapshot->getValue() as $id => $penalty) {
            $penalty = (array) $penalty;

            if ($waiterId !== null && (string) ($penalty['waiter_id'] ?? '') !== $waiterId) {
                continue;
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

        // --- Net points & percentage ---
        // Net = daily auto points + monthly service/sales points + penalties (negative)
        $netPoints = $totalEarned + $servicePoints + $salesPoints + $totalPenalties;
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
     * Get the pre-computed leaderboard for a month.
     */
    public function getLeaderboard(string $month): array
    {
        $snapshot = $this->database->getReference('waiter_leaderboard/' . $month)->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $leaderboard = (array) $snapshot->getValue();
        $rankings = $leaderboard['rankings'] ?? [];

        if (! is_array($rankings)) {
            $leaderboard['rankings'] = [];
            $leaderboard['total_waiters'] = 0;

            return $leaderboard;
        }

        $activeWaiterIds = array_fill_keys(array_map(function ($waiter) {
            return (string) ($waiter['id'] ?? '');
        }, $this->firebase->getActiveWaiters()), true);

        unset($activeWaiterIds['']);

        $filteredRankings = array_values(array_filter($rankings, function ($entry) use ($activeWaiterIds) {
            $waiterId = (string) ((is_array($entry) ? ($entry['waiter_id'] ?? '') : '') ?: '');

            return $waiterId !== '' && isset($activeWaiterIds[$waiterId]);
        }));

        foreach ($filteredRankings as $index => &$entry) {
            if (is_array($entry)) {
                $entry['rank'] = $index + 1;
            }
        }
        unset($entry);

        $leaderboard['rankings'] = $filteredRankings;
        $leaderboard['total_waiters'] = count($filteredRankings);

        return $leaderboard;
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
            if (! isset($categoryScores[$key]) || (int) $categoryScores[$key] <= 0) {
                return 0;
            }
        }

        return $bonus;
    }
}
