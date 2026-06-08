<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class BonusFirebaseService
{
    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Get bonus configuration
     */
    public function getBonusConfig(): array
    {
        $snapshot = $this->database->getReference('bonus_config')->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : [];
    }

    /**
     * Update bonus configuration
     */
    public function updateBonusConfig(array $data): void
    {
        $data['updated_at'] = time();
        $this->database->getReference('bonus_config')->set($data);
    }

    /**
     * Get daily points for a waiter on a specific date
     */
    public function getDailyPoints(string $waiterId, string $date): ?array
    {
        $snapshot = $this->database->getReference("waiter_daily_points/{$waiterId}/{$date}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
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
        $snapshot = $this->database->getReference("waiter_daily_points/{$waiterId}")
            ->orderByKey()
            ->startAt($startDate)
            ->endAt($endDate)
            ->getSnapshot();
        if (!$snapshot->exists()) return [];

        $result = [];
        foreach ($snapshot->getValue() as $date => $record) {
            $result[$date] = $record;
        }
        ksort($result);
        return $result;
    }

    /**
     * Get all waiters' daily points for a specific date
     */
    public function getAllDailyPointsByDate(string $date): array
    {
        $snapshot = $this->database->getReference('waiter_daily_points')->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $all = $snapshot->getValue();
        $result = [];
        foreach ($all as $waiterId => $days) {
            if (isset($days[$date])) {
                $result[$waiterId] = $days[$date];
            }
        }
        return $result;
    }

    /**
     * Delete a penalty
     */
    public function deletePenalty(string $penaltyId): void
    {
        app(\App\Repositories\Contracts\BonusRepositoryInterface::class)->deletePenalty($penaltyId);
    }

    /**
     * Get sales target for a waiter/month
     */
    public function getSalesTarget(string $waiterId, string $month): ?array
    {
        $snapshot = $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Get all sales targets for a month
     */
    public function getAllSalesTargets(string $month): array
    {
        $snapshot = $this->database->getReference('waiter_sales_targets')->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $all = $snapshot->getValue();
        $result = [];
        foreach ($all as $waiterId => $months) {
            if (isset($months[$month])) {
                $target = $months[$month];
                $target['waiter_id'] = $waiterId;
                $result[] = $target;
            }
        }
        return $result;
    }

    /**
     * Record daily sales for a waiter
     */
    public function recordDailySales(string $waiterId, string $month, string $date, array $salesData): void
    {
        $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}/daily_sales/{$date}")->set($salesData);
        
        // Recalculate current_achievement
        $snapshot = $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}/daily_sales")->getSnapshot();
        $totalAchievement = 0;
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $day => $record) {
                $totalAchievement += (int)($record['amount'] ?? 0);
            }
        }
        
        $targetSnapshot = $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}/target_amount")->getSnapshot();
        $targetAmount = $targetSnapshot->exists() ? (int)$targetSnapshot->getValue() : 0;
        $percentage = $targetAmount > 0 ? round(($totalAchievement / $targetAmount) * 100, 1) : 0;
        
        $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}")->update([
            'current_achievement' => $totalAchievement,
            'achievement_percentage' => $percentage,
            'last_updated_at' => time(),
        ]);
    }

    /**
     * Get bonus summary for a waiter by period key.
     */
    public function getBonusSummary(string $waiterId, string $periodKey): ?array
    {
        return app(\App\Repositories\Contracts\BonusRepositoryInterface::class)->bonusSummary($waiterId, $periodKey);
    }

    /**
     * Get all bonus summaries for a period key.
     */
    public function getAllBonusSummaries(string $periodKey): array
    {
        return app(\App\Repositories\Contracts\BonusRepositoryInterface::class)->allBonusSummaries($periodKey);
    }

    /**
     * Get leaderboard for a month
     */
    public function getLeaderboard(string $month): ?array
    {
        $snapshot = $this->database->getReference("waiter_leaderboard/{$month}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Get waiter bonus history for multiple months
     */
    public function getWaiterBonusHistory(string $waiterId, int $periodsBack = 6): array
    {
        $history = [];
        $now = now();

        for ($i = 0; $i < $periodsBack; $i++) {
            $end = $now->copy()->subDays($i * 30)->format('Y-m-d');
            $start = $now->copy()->subDays(($i + 1) * 30 - 1)->format('Y-m-d');
            $periodKey = $start . '_' . $end;
            $periodLabel = date('d/m', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end));

            $summary = $this->database->getReference("waiter_bonus_summary/{$waiterId}/{$periodKey}")->getSnapshot();
            if ($summary->exists()) {
                $data = $summary->getValue();
                $data['period_key'] = $periodKey;
                $data['period_label'] = $periodLabel;
                $history[] = $data;
            } else {
                $history[] = ['period_key' => $periodKey, 'period_label' => $periodLabel, 'net_points' => 0, 'total_bonus' => 0];
            }
        }

        return array_reverse($history);
    }

    /**
     * Tandai task butuh recompute bonus poin (worker akan retry).
     */
    public function flagTaskBonusPending(string $taskId, string $waiterId, array $context = []): void
    {
        try {
            if ($taskId === '' || $waiterId === '') {
                return;
            }
            $payload = [
                'bonus_pending_recompute' => true,
                'bonus_pending_at' => time(),
                'bonus_pending_waiter_id' => $waiterId,
                'bonus_pending_context' => $context,
            ];
            $this->database->getReference('waiter_tasks/'.$taskId)->update($payload);
            // Index lookup: bonus_pending_recompute_index/{waiterId}/{date}/{taskId} = true
            $date = $context['date'] ?? date('Y-m-d');
            $this->database->getReference('bonus_pending_recompute_index/'.$waiterId.'/'.$date.'/'.$taskId)->set([
                'created_at' => time(),
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Tandai waiter butuh recompute bonus (untuk event non-task seperti activity_report / clock_in).
     */
    public function flagWaiterBonusPending(string $waiterId, string $date, array $context = []): void
    {
        try {
            if ($waiterId === '' || $date === '') {
                return;
            }
            $key = sha1(($context['source'] ?? 'unknown').'|'.$waiterId.'|'.$date.'|'.time());
            $this->database->getReference('bonus_pending_waiter_index/'.$waiterId.'/'.$date.'/'.$key)->set([
                'created_at' => time(),
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Ambil semua bonus pending recompute (max N).
     *
     * @return array list of ['type'=>'task'|'waiter','waiter_id','date','task_id','context','created_at']
     */
    public function getBonusPendingRecomputes(int $limit = 100): array
    {
        $items = [];

        try {
            // Task-based
            $taskSnap = $this->database->getReference('bonus_pending_recompute_index')->getSnapshot();
            if ($taskSnap->exists()) {
                foreach ((array) $taskSnap->getValue() as $waiterId => $byDate) {
                    foreach ((array) $byDate as $date => $byTask) {
                        foreach ((array) $byTask as $taskId => $payload) {
                            $items[] = [
                                'type' => 'task',
                                'waiter_id' => (string) $waiterId,
                                'date' => (string) $date,
                                'task_id' => (string) $taskId,
                                'context' => (array) ($payload['context'] ?? []),
                                'created_at' => (int) ($payload['created_at'] ?? 0),
                            ];
                            if (count($items) >= $limit) {
                                return $items;
                            }
                        }
                    }
                }
            }

            // Waiter-event-based
            $waiterSnap = $this->database->getReference('bonus_pending_waiter_index')->getSnapshot();
            if ($waiterSnap->exists()) {
                foreach ((array) $waiterSnap->getValue() as $waiterId => $byDate) {
                    foreach ((array) $byDate as $date => $byKey) {
                        foreach ((array) $byKey as $key => $payload) {
                            $items[] = [
                                'type' => 'waiter',
                                'waiter_id' => (string) $waiterId,
                                'date' => (string) $date,
                                'task_id' => '',
                                'context_key' => (string) $key,
                                'context' => (array) ($payload['context'] ?? []),
                                'created_at' => (int) ($payload['created_at'] ?? 0),
                            ];
                            if (count($items) >= $limit) {
                                return $items;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $items;
    }

    /**
     * Hapus flag bonus pending setelah recompute sukses.
     */
    public function clearBonusPendingFlag(array $item): void
    {
        try {
            $waiterId = (string) ($item['waiter_id'] ?? '');
            $date = (string) ($item['date'] ?? '');
            if ($waiterId === '' || $date === '') {
                return;
            }
            $type = (string) ($item['type'] ?? '');

            if ($type === 'task') {
                $taskId = (string) ($item['task_id'] ?? '');
                if ($taskId === '') {
                    return;
                }
                $this->database->getReference('bonus_pending_recompute_index/'.$waiterId.'/'.$date.'/'.$taskId)->remove();
                $this->database->getReference('waiter_tasks/'.$taskId)->update([
                    'bonus_pending_recompute' => false,
                    'bonus_pending_cleared_at' => time(),
                ]);
            } elseif ($type === 'waiter') {
                $key = (string) ($item['context_key'] ?? '');
                if ($key === '') {
                    return;
                }
                $this->database->getReference('bonus_pending_waiter_index/'.$waiterId.'/'.$date.'/'.$key)->remove();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function getWaiterMonthlyPointsForPriority(string $waiterId, string $date): int
    {
        if ($waiterId === '' || $date === '') {
            return 0;
        }

        try {
            $progress = app(BonusService::class)->getWaiterProgress($waiterId);

            return max(0, (int) ($progress['net_points'] ?? 0));
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }
}
