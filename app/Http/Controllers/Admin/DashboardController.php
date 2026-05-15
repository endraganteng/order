<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function __invoke(Request $request)
    {
        $waiters = $this->firebase->getAllowedEmails();
        $settings = $this->firebase->getSettings();
        [$periodStartTs, $periodEndTs, $orderPeriodLabel, $startDate, $endDate, $dateRangeInput] = $this->resolveDateRange($request);
        $orders = $this->firebase->getOrdersByDateRange($periodStartTs, $periodEndTs);
        $waiterTasks = $this->firebase->getWaiterTasks();
        $waiterActivityReports = $this->firebase->getWaiterActivityReports();
        $waiterIdentityDirectory = $this->buildIdentityDirectory($waiters);

        $userStats = $this->buildOrderStats(
            $orders,
            $periodStartTs,
            $periodEndTs,
            $waiterIdentityDirectory
        );

        $waiterTaskRanking = $this->buildTaskCompletionRanking(
            $waiterTasks,
            $periodStartTs,
            $periodEndTs,
            $waiterIdentityDirectory
        );

        $waiterFollowUpBoard = $this->buildFollowUpBoard(
            $waiters,
            $waiterTasks,
            $waiterActivityReports,
            $periodStartTs,
            $periodEndTs,
            $waiterIdentityDirectory
        );

        $orderStatsSummary = [
            'total_orders' => array_sum(array_map(function ($stat) {
                return (int) ($stat['order_count'] ?? 0);
            }, $userStats)),
            'waiter_with_orders' => count($userStats),
        ];

        return view('admin.dashboard', compact(
            'waiters',
            'settings',
            'userStats',
            'waiterTaskRanking',
            'orderPeriodLabel',
            'periodStartTs',
            'periodEndTs',
            'orderStatsSummary',
            'waiterFollowUpBoard',
            'startDate',
            'endDate',
            'dateRangeInput'
        ));
    }

    protected function normalizeTimestamp($timestamp): int
    {
        $value = (int) $timestamp;
        if ($value <= 0) {
            return 0;
        }

        if ($value > 9999999999) {
            $value = (int) floor($value / 1000);
        }

        return $value;
    }

    protected function resolvePeriodRange(string $period): array
    {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));

        if ($period === 'weekly') {
            $weekStart = strtotime('monday this week', $todayStart);
            if ($weekStart === false) {
                $weekStart = $todayStart;
            }

            return [(int) $weekStart, (int) ($weekStart + (7 * 24 * 60 * 60) - 1), 'Minggu Ini'];
        }

        if ($period === 'monthly') {
            $monthStart = strtotime(date('Y-m-01 00:00:00'));
            $monthEnd = strtotime(date('Y-m-t 23:59:59'));

            return [(int) $monthStart, (int) $monthEnd, 'Bulan Ini'];
        }

        return [$todayStart, (int) ($todayStart + (24 * 60 * 60) - 1), 'Hari Ini'];
    }

    protected function resolveDateRange(Request $request): array
    {
        $startDateInput = trim((string) $request->input('start_date', ''));
        $endDateInput = trim((string) $request->input('end_date', ''));
        $dateRangeInput = trim((string) $request->input('date_range', ''));

        if (($startDateInput === '' || $endDateInput === '') && $dateRangeInput !== '') {
            $parts = preg_split('/\s*-\s*/', $dateRangeInput) ?: [];
            if (count($parts) >= 2) {
                $startDateInput = $startDateInput !== '' ? $startDateInput : trim((string) ($parts[0] ?? ''));
                $endDateInput = $endDateInput !== '' ? $endDateInput : trim((string) ($parts[1] ?? ''));
            }
        }

        $startDate = $this->normalizeDateString($startDateInput);
        $endDate = $this->normalizeDateString($endDateInput);

        if ($startDate === '' && $endDate === '') {
            $legacyPeriodInput = strtolower(trim((string) $request->input('order_period', 'daily')));
            $legacyPeriod = in_array($legacyPeriodInput, ['daily', 'weekly', 'monthly'], true) ? $legacyPeriodInput : 'daily';
            [$legacyStartTs, $legacyEndTs] = $this->resolvePeriodRange($legacyPeriod);

            $startDate = date('Y-m-d', $legacyStartTs);
            $endDate = date('Y-m-d', $legacyEndTs);
        } elseif ($startDate === '' && $endDate !== '') {
            $startDate = $endDate;
        } elseif ($endDate === '' && $startDate !== '') {
            $endDate = $startDate;
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $periodStartTs = (int) strtotime($startDate.' 00:00:00');
        $periodEndTs = (int) strtotime($endDate.' 23:59:59');
        $orderPeriodLabel = $this->resolveRangeLabel($startDate, $endDate);

        return [
            $periodStartTs,
            $periodEndTs,
            $orderPeriodLabel,
            $startDate,
            $endDate,
            date('d M Y', $periodStartTs).' - '.date('d M Y', $periodEndTs),
        ];
    }

    protected function normalizeDateString(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $parsed = strtotime($date.' 00:00:00');
            if ($parsed === false) {
                return '';
            }

            return date('Y-m-d', $parsed);
        }

        $parsed = strtotime($date);
        if ($parsed === false) {
            return '';
        }

        return date('Y-m-d', $parsed);
    }

    protected function resolveRangeLabel(string $startDate, string $endDate): string
    {
        $today = date('Y-m-d');
        if ($startDate === $today && $endDate === $today) {
            return 'Today';
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($startDate === $yesterday && $endDate === $yesterday) {
            return 'Yesterday';
        }

        $last7Start = date('Y-m-d', strtotime('-6 day'));
        if ($startDate === $last7Start && $endDate === $today) {
            return 'Last 7 Days';
        }

        $last30Start = date('Y-m-d', strtotime('-29 day'));
        if ($startDate === $last30Start && $endDate === $today) {
            return 'Last 30 Days';
        }

        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        if ($startDate === $weekStart && $endDate === $weekEnd) {
            return 'Minggu Ini';
        }

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        if ($startDate === $monthStart && $endDate === $monthEnd) {
            return 'This Month';
        }

        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
        if ($startDate === $lastMonthStart && $endDate === $lastMonthEnd) {
            return 'Last Month';
        }

        return 'Rentang Kustom';
    }

    protected function buildIdentityKey(string $waiterId, string $waiterName, string $waiterEmail): string
    {
        $waiterId = trim($waiterId);
        $waiterName = trim($waiterName);
        $waiterEmail = strtolower(trim($waiterEmail));

        if ($waiterEmail !== '') {
            return 'email:'.$waiterEmail;
        }

        if ($waiterId !== '') {
            return 'id:'.$waiterId;
        }

        if ($waiterName !== '') {
            return 'name:'.strtolower($waiterName);
        }

        return 'unknown';
    }

    protected function buildIdentityDirectory(array $waiters): array
    {
        $byId = [];
        $byEmail = [];

        foreach ($waiters as $waiter) {
            $id = trim((string) ($waiter['id'] ?? ''));
            $name = trim((string) ($waiter['name'] ?? ''));
            $email = strtolower(trim((string) ($waiter['email'] ?? '')));

            $profile = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
            ];

            if ($id !== '') {
                $byId[$id] = $profile;
            }

            if ($email !== '') {
                $byEmail[$email] = $profile;
            }
        }

        return [
            'by_id' => $byId,
            'by_email' => $byEmail,
        ];
    }

    protected function resolveCanonicalProfile(string $waiterId, string $waiterName, string $waiterEmail, array $directory): array
    {
        $waiterId = trim($waiterId);
        $waiterName = trim($waiterName);
        $waiterEmail = strtolower(trim($waiterEmail));

        $byId = is_array($directory['by_id'] ?? null) ? $directory['by_id'] : [];
        $byEmail = is_array($directory['by_email'] ?? null) ? $directory['by_email'] : [];

        $masterProfile = null;
        if ($waiterEmail !== '' && isset($byEmail[$waiterEmail])) {
            $masterProfile = $byEmail[$waiterEmail];
        } elseif ($waiterId !== '' && isset($byId[$waiterId])) {
            $masterProfile = $byId[$waiterId];
        }

        $canonicalId = $waiterId;
        $canonicalName = $waiterName;
        $canonicalEmail = $waiterEmail;

        if (is_array($masterProfile)) {
            if (($masterProfile['id'] ?? '') !== '') {
                $canonicalId = (string) $masterProfile['id'];
            }

            if (($masterProfile['name'] ?? '') !== '') {
                $canonicalName = (string) $masterProfile['name'];
            }

            if (($masterProfile['email'] ?? '') !== '') {
                $canonicalEmail = (string) $masterProfile['email'];
            }
        }

        $identityKey = $this->buildIdentityKey($canonicalId, $canonicalName, $canonicalEmail);

        return [
            'identity_key' => $identityKey,
            'waiter_id' => $canonicalId,
            'waiter_name' => $canonicalName,
            'waiter_email' => $canonicalEmail,
        ];
    }

    protected function buildOrderStats(array $orders, int $startTs, int $endTs, array $waiterDirectory = []): array
    {
        $stats = [];

        foreach ($orders as $order) {
            $createdAt = $this->normalizeTimestamp($order['created_at'] ?? 0);
            if ($createdAt < $startTs || $createdAt > $endTs) {
                continue;
            }

            $resolvedWaiter = $this->resolveCanonicalProfile(
                (string) ($order['waiter_id'] ?? ''),
                (string) ($order['waiter_name'] ?? ''),
                (string) ($order['waiter_email'] ?? ''),
                $waiterDirectory
            );

            $waiterId = (string) ($resolvedWaiter['waiter_id'] ?? '');
            $waiterName = trim((string) ($resolvedWaiter['waiter_name'] ?? ''));
            $waiterEmail = strtolower(trim((string) ($resolvedWaiter['waiter_email'] ?? '')));
            $identityKey = (string) ($resolvedWaiter['identity_key'] ?? 'unknown');

            if (! isset($stats[$identityKey])) {
                $stats[$identityKey] = [
                    'waiter_id' => $waiterId,
                    'waiter_name' => $waiterName !== '' ? $waiterName : 'Waiter Tidak Diketahui',
                    'waiter_email' => $waiterEmail,
                    'order_count' => 0,
                    'last_order_at' => 0,
                ];
            }

            $stats[$identityKey]['order_count']++;
            if ($createdAt > $stats[$identityKey]['last_order_at']) {
                $stats[$identityKey]['last_order_at'] = $createdAt;
            }

            if ($stats[$identityKey]['waiter_name'] === 'Waiter Tidak Diketahui' && $waiterName !== '') {
                $stats[$identityKey]['waiter_name'] = $waiterName;
            }

            if ($stats[$identityKey]['waiter_email'] === '' && $waiterEmail !== '') {
                $stats[$identityKey]['waiter_email'] = $waiterEmail;
            }

            if ($stats[$identityKey]['waiter_id'] === '' && $waiterId !== '') {
                $stats[$identityKey]['waiter_id'] = $waiterId;
            }
        }

        $result = array_values($stats);
        usort($result, function ($a, $b) {
            if (($b['order_count'] ?? 0) === ($a['order_count'] ?? 0)) {
                return ((int) ($b['last_order_at'] ?? 0)) <=> ((int) ($a['last_order_at'] ?? 0));
            }

            return ((int) ($b['order_count'] ?? 0)) <=> ((int) ($a['order_count'] ?? 0));
        });

        return $result;
    }

    protected function buildTaskCompletionRanking(array $tasks, int $startTs, int $endTs, array $waiterDirectory = []): array
    {
        $stats = [];

        foreach ($tasks as $task) {
            if ((string) ($task['status'] ?? '') !== 'done') {
                continue;
            }

            $completedAt = $this->normalizeTimestamp($task['completed_at'] ?? 0);
            if ($completedAt < $startTs || $completedAt > $endTs) {
                continue;
            }

            $resolvedWaiter = $this->resolveCanonicalProfile(
                (string) ($task['completed_by_waiter_id'] ?? $task['assigned_waiter_id'] ?? ''),
                (string) ($task['completed_by_waiter_name'] ?? $task['assigned_waiter_name'] ?? ''),
                (string) ($task['completed_by_waiter_email'] ?? ''),
                $waiterDirectory
            );

            $waiterId = (string) ($resolvedWaiter['waiter_id'] ?? '');
            $waiterName = trim((string) ($resolvedWaiter['waiter_name'] ?? ''));
            $waiterEmail = strtolower(trim((string) ($resolvedWaiter['waiter_email'] ?? '')));
            $identityKey = (string) ($resolvedWaiter['identity_key'] ?? 'unknown');
            $taskType = (string) ($task['task_type'] ?? 'general');

            if (! isset($stats[$identityKey])) {
                $stats[$identityKey] = [
                    'waiter_id' => $waiterId,
                    'waiter_name' => $waiterName !== '' ? $waiterName : 'Waiter Tidak Diketahui',
                    'waiter_email' => $waiterEmail,
                    'completed_count' => 0,
                    'rack_done_count' => 0,
                    'general_done_count' => 0,
                    'last_completed_at' => 0,
                ];
            }

            $stats[$identityKey]['completed_count']++;
            if ($taskType === 'rack_check') {
                $stats[$identityKey]['rack_done_count']++;
            } else {
                $stats[$identityKey]['general_done_count']++;
            }

            if ($completedAt > $stats[$identityKey]['last_completed_at']) {
                $stats[$identityKey]['last_completed_at'] = $completedAt;
            }

            if ($stats[$identityKey]['waiter_name'] === 'Waiter Tidak Diketahui' && $waiterName !== '') {
                $stats[$identityKey]['waiter_name'] = $waiterName;
            }

            if ($stats[$identityKey]['waiter_email'] === '' && $waiterEmail !== '') {
                $stats[$identityKey]['waiter_email'] = $waiterEmail;
            }

            if ($stats[$identityKey]['waiter_id'] === '' && $waiterId !== '') {
                $stats[$identityKey]['waiter_id'] = $waiterId;
            }
        }

        $result = array_values($stats);
        usort($result, function ($a, $b) {
            $totalCompare = ((int) ($b['completed_count'] ?? 0)) <=> ((int) ($a['completed_count'] ?? 0));
            if ($totalCompare !== 0) {
                return $totalCompare;
            }

            $rackCompare = ((int) ($b['rack_done_count'] ?? 0)) <=> ((int) ($a['rack_done_count'] ?? 0));
            if ($rackCompare !== 0) {
                return $rackCompare;
            }

            return ((int) ($b['last_completed_at'] ?? 0)) <=> ((int) ($a['last_completed_at'] ?? 0));
        });

        return $result;
    }

    protected function buildFollowUpBoard(
        array $waiters,
        array $tasks,
        array $activityReports,
        int $startTs,
        int $endTs,
        array $waiterDirectory = []
    ): array {
        $board = [];

        $upsertWaiter = function (
            string $waiterId,
            string $waiterName,
            string $waiterEmail,
            ?string $waiterRole,
            bool $isActive
        ) use (&$board, $waiterDirectory) {
            $resolvedWaiter = $this->resolveCanonicalProfile($waiterId, $waiterName, $waiterEmail, $waiterDirectory);

            $identityKey = (string) ($resolvedWaiter['identity_key'] ?? 'unknown');
            $canonicalId = (string) ($resolvedWaiter['waiter_id'] ?? '');
            $canonicalName = trim((string) ($resolvedWaiter['waiter_name'] ?? ''));
            $canonicalEmail = strtolower(trim((string) ($resolvedWaiter['waiter_email'] ?? '')));

            $normalizedRole = strtolower(trim((string) $waiterRole));
            if (! in_array($normalizedRole, ['kasir', 'pelayan', 'backup'], true)) {
                $normalizedRole = 'pelayan';
            }

            if (! isset($board[$identityKey])) {
                $board[$identityKey] = [
                    'waiter_key' => $identityKey,
                    'waiter_id' => $canonicalId,
                    'waiter_name' => $canonicalName !== '' ? $canonicalName : 'Waiter Tidak Diketahui',
                    'waiter_email' => $canonicalEmail,
                    'waiter_role' => $normalizedRole,
                    'is_active' => $isActive,
                    'general_total_count' => 0,
                    'rack_total_count' => 0,
                    'general_done_count' => 0,
                    'rack_done_count' => 0,
                    'general_open_count' => 0,
                    'rack_open_count' => 0,
                    'report_count' => 0,
                ];

                return $identityKey;
            }

            if ($board[$identityKey]['waiter_name'] === 'Waiter Tidak Diketahui' && $canonicalName !== '') {
                $board[$identityKey]['waiter_name'] = $canonicalName;
            }

            if ($board[$identityKey]['waiter_email'] === '' && $canonicalEmail !== '') {
                $board[$identityKey]['waiter_email'] = $canonicalEmail;
            }

            if ($board[$identityKey]['waiter_id'] === '' && $canonicalId !== '') {
                $board[$identityKey]['waiter_id'] = $canonicalId;
            }

            if (! in_array((string) ($board[$identityKey]['waiter_role'] ?? ''), ['kasir', 'pelayan', 'backup'], true)
                && in_array($normalizedRole, ['kasir', 'pelayan', 'backup'], true)) {
                $board[$identityKey]['waiter_role'] = $normalizedRole;
            }

            if ($isActive) {
                $board[$identityKey]['is_active'] = true;
            }

            return $identityKey;
        };

        foreach ($waiters as $waiter) {
            $upsertWaiter(
                (string) ($waiter['id'] ?? ''),
                (string) ($waiter['name'] ?? ''),
                (string) ($waiter['email'] ?? ''),
                (string) ($waiter['waiter_role'] ?? 'pelayan'),
                (bool) ($waiter['is_active'] ?? true)
            );
        }

        foreach ($tasks as $task) {
            $trackingDate = $this->resolveTrackingDate($task);
            $trackingTimestamp = strtotime($trackingDate.' 00:00:00');
            if ($trackingTimestamp === false || $trackingTimestamp < $startTs || $trackingTimestamp > $endTs) {
                continue;
            }

            $taskType = (string) ($task['task_type'] ?? 'general');
            $isRackCheck = $taskType === 'rack_check';
            $isDone = (string) ($task['status'] ?? '') === 'done';

            $waiterId = (string) ($isDone
                ? ($task['completed_by_waiter_id'] ?? $task['assigned_waiter_id'] ?? '')
                : ($task['assigned_waiter_id'] ?? ''));
            $waiterName = (string) ($isDone
                ? ($task['completed_by_waiter_name'] ?? $task['assigned_waiter_name'] ?? '')
                : ($task['assigned_waiter_name'] ?? ''));
            $waiterEmail = (string) ($isDone
                ? ($task['completed_by_waiter_email'] ?? '')
                : ($task['assigned_waiter_email'] ?? ''));
            $waiterRole = (string) ($task['assigned_waiter_role'] ?? 'pelayan');

            $identityKey = $upsertWaiter($waiterId, $waiterName, $waiterEmail, $waiterRole, true);

            if ($isRackCheck) {
                $board[$identityKey]['rack_total_count']++;
            } else {
                $board[$identityKey]['general_total_count']++;
            }

            if ($isDone) {
                if ($isRackCheck) {
                    $board[$identityKey]['rack_done_count']++;
                } else {
                    $board[$identityKey]['general_done_count']++;
                }
            } else {
                if ($isRackCheck) {
                    $board[$identityKey]['rack_open_count']++;
                } else {
                    $board[$identityKey]['general_open_count']++;
                }
            }
        }

        foreach ($activityReports as $report) {
            $reportDate = $this->normalizeDateString((string) ($report['report_date'] ?? ''));
            $reportTimestamp = $reportDate !== '' ? strtotime($reportDate.' 00:00:00') : false;
            if ($reportTimestamp === false) {
                $createdAt = $this->normalizeTimestamp($report['created_at'] ?? 0);
                if ($createdAt <= 0) {
                    continue;
                }

                $reportTimestamp = strtotime(date('Y-m-d', $createdAt).' 00:00:00');
            }

            if ($reportTimestamp === false || $reportTimestamp < $startTs || $reportTimestamp > $endTs) {
                continue;
            }

            $identityKey = $upsertWaiter(
                (string) ($report['waiter_id'] ?? ''),
                (string) ($report['waiter_name'] ?? ''),
                (string) ($report['waiter_email'] ?? ''),
                'pelayan',
                true
            );

            $board[$identityKey]['report_count']++;
        }

        $rows = [];
        $activeWaiterCount = 0;
        $activeWaiterAttentionCount = 0;

        foreach ($board as $item) {
            $isActive = (bool) ($item['is_active'] ?? true);
            if ($isActive) {
                $activeWaiterCount++;
            }

            $generalDoneCount = (int) ($item['general_done_count'] ?? 0);
            $rackDoneCount = (int) ($item['rack_done_count'] ?? 0);
            $generalTotalCount = (int) ($item['general_total_count'] ?? 0);
            $rackTotalCount = (int) ($item['rack_total_count'] ?? 0);
            $generalOpenCount = (int) ($item['general_open_count'] ?? 0);
            $rackOpenCount = (int) ($item['rack_open_count'] ?? 0);
            $reportCount = (int) ($item['report_count'] ?? 0);
            $totalOpenCount = $generalOpenCount + $rackOpenCount;

            $missingGeneralDone = $generalTotalCount > 0 && $generalDoneCount === 0;
            $missingRackDone = $rackTotalCount > 0 && $rackDoneCount === 0;
            $missingReport = $reportCount === 0;
            $hasOpenTask = $totalOpenCount > 0;

            $needsAttention = $missingGeneralDone || $missingRackDone || $missingReport || $hasOpenTask;
            if (! $needsAttention) {
                continue;
            }

            if ($isActive) {
                $activeWaiterAttentionCount++;
            }

            $attentionTags = [];
            if ($missingGeneralDone) {
                $attentionTags[] = 'Belum kerjakan tugas umum';
            }
            if ($missingRackDone) {
                $attentionTags[] = 'Belum kerjakan cek rak';
            }
            if ($hasOpenTask) {
                $attentionTags[] = 'Masih ada tugas belum selesai';
            }
            if ($missingReport) {
                $attentionTags[] = 'Belum isi laporan';
            }

            $rows[] = array_merge($item, [
                'total_open_count' => $totalOpenCount,
                'general_total_count' => $generalTotalCount,
                'rack_total_count' => $rackTotalCount,
                'missing_general_done' => $missingGeneralDone,
                'missing_rack_done' => $missingRackDone,
                'missing_report' => $missingReport,
                'has_open_task' => $hasOpenTask,
                'attention_tags' => $attentionTags,
                'needs_attention' => true,
            ]);
        }

        usort($rows, function ($a, $b) {
            $openCompare = ((int) ($b['total_open_count'] ?? 0)) <=> ((int) ($a['total_open_count'] ?? 0));
            if ($openCompare !== 0) {
                return $openCompare;
            }

            $reportCompare = ((bool) ($b['missing_report'] ?? false)) <=> ((bool) ($a['missing_report'] ?? false));
            if ($reportCompare !== 0) {
                return $reportCompare;
            }

            $rackCompare = ((bool) ($b['missing_rack_done'] ?? false)) <=> ((bool) ($a['missing_rack_done'] ?? false));
            if ($rackCompare !== 0) {
                return $rackCompare;
            }

            return strcmp(
                strtolower((string) ($a['waiter_name'] ?? '')),
                strtolower((string) ($b['waiter_name'] ?? ''))
            );
        });

        return [
            'rows' => $rows,
            'has_attention' => count($rows) > 0,
            'active_waiter_count' => $activeWaiterCount,
            'active_waiter_attention_count' => $activeWaiterAttentionCount,
            'period_label' => $startTs === $endTs
                ? date('d M Y', $startTs)
                : date('d M Y', $startTs).' - '.date('d M Y', $endTs),
        ];
    }

    protected function resolveTrackingDate($task): string
    {
        if (! empty($task['scheduled_for_date'])) {
            return $task['scheduled_for_date'];
        }

        if (! empty($task['created_at'])) {
            return date('Y-m-d', (int) $task['created_at']);
        }

        return date('Y-m-d');
    }
}
