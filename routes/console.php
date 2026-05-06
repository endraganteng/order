<?php

use App\Services\BonusService;
use App\Services\FirebaseService;
use App\Services\FonnteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('waiter:process-tasks', function () {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);
    $generated = $firebase->generateDueRecurringWaiterTasks();
    $overdueResult = $firebase->markOverdueWaiterTasks();
    $notified = 0;

    foreach (($overdueResult['overdue_tasks'] ?? []) as $task) {
        $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
        if ($waiterId === '') {
            continue;
        }

        $waiter = $firebase->getWaiterById($waiterId);
        if (! $waiter) {
            continue;
        }

        $fonnte->notifyTaskOverdue($waiter, $task);
        $notified++;
    }

    $this->info("Generated recurring tasks: {$generated}");
    $this->info('Marked overdue tasks: '.(int) ($overdueResult['count'] ?? 0));
    $this->info("Sent overdue notifications: {$notified}");
})->purpose('Generate recurring waiter tasks and mark overdue tasks');

Artisan::command('waiter:send-task-reminders {date?}', function (?string $date = null) {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);
    $date = $date ?: date('Y-m-d');
    $sentByType = ['general' => 0, 'rack_check' => 0];

    foreach ($firebase->getActiveWaiters() as $waiter) {
        $waiterId = (string) ($waiter['id'] ?? '');
        if ($waiterId === '') {
            continue;
        }

        $tasks = $firebase->getWaiterTasksByWaiterId($waiterId);
        $visiblePendingTasks = array_values(array_filter($tasks, function ($task) use ($date, $firebase, $waiterId) {
            if (($task['status'] ?? 'pending') !== 'pending') {
                return false;
            }

            $scheduledDate = (string) ($task['scheduled_for_date'] ?? '');
            if ($scheduledDate !== '' && $scheduledDate > $date) {
                return false;
            }

            if ($scheduledDate !== '' && $scheduledDate < $date) {
                return true;
            }

            $shift = $firebase->getWaiterShiftForDate($waiterId, $date);
            $clockInTime = trim((string) ($shift['clock_in_time'] ?? ''));
            if ($clockInTime === '') {
                return true;
            }

            $shiftStartTs = strtotime($date.' '.$clockInTime.':00');

            return $shiftStartTs !== false && time() >= $shiftStartTs;
        }));

        if (empty($visiblePendingTasks)) {
            continue;
        }

        $attendance = $firebase->getAttendanceByDate($waiterId, $date);
        $result = $fonnte->sendTaskReminders($waiterId, $visiblePendingTasks, $attendance, $date);
        foreach (($result['sent'] ?? []) as $type) {
            if (isset($sentByType[$type])) {
                $sentByType[$type]++;
            }
        }
    }

    $this->info('General reminders sent: '.$sentByType['general']);
    $this->info('Rack-check reminders sent: '.$sentByType['rack_check']);
})->purpose('Send durable scheduled waiter task reminders');

Artisan::command('waiter:audit-attendance {date?}', function (?string $date = null) {
    $firebase = app(FirebaseService::class);
    $bonus = app(BonusService::class);
    $date = $date ?: date('Y-m-d');
    $month = substr($date, 0, 7);
    $now = time();
    $markedAbsent = 0;
    $penaltiesApplied = 0;
    $allPenalties = $bonus->getPenaltiesByMonth($month);
    $penaltyKeys = [];

    foreach ($allPenalties as $penalty) {
        if (($penalty['date'] ?? '') === $date && ($penalty['penalty_type'] ?? '') === 'absent') {
            $penaltyKeys[(string) ($penalty['waiter_id'] ?? '')] = true;
        }
    }

    foreach ($firebase->getActiveWaiters() as $waiter) {
        $waiterId = (string) ($waiter['id'] ?? '');
        if ($waiterId === '' || ! $firebase->isWorkingDay($waiterId, $date)) {
            continue;
        }

        // Skip waiters exempt from attendance
        if (!empty($waiter['attendance_exempt'])) {
            continue;
        }

        $shift = $firebase->getWaiterShiftForDate($waiterId, $date);
        $clockOutTime = trim((string) ($shift['clock_out_time'] ?? ''));
        if ($clockOutTime !== '') {
            $cutoff = strtotime($date.' '.$clockOutTime.':00');
            if ($cutoff !== false && $now < ($cutoff + 3600)) {
                continue;
            }
        }

        $attendance = $firebase->getAttendanceByDate($waiterId, $date);
        if (! empty($attendance['clock_in'])) {
            continue;
        }

        $status = (string) ($attendance['status'] ?? '');
        if (in_array($status, ['sick', 'day_off'], true)) {
            continue;
        }

        if ($status !== 'absent') {
            $note = trim((string) ($attendance['note'] ?? ''));
            $firebase->updateAttendance($waiterId, $date, [
                'status' => 'absent',
                'late_minutes' => 0,
                'note' => $note !== '' ? $note : 'Auto-marked absent by scheduler',
            ]);
            $markedAbsent++;
        }

        if (! isset($penaltyKeys[$waiterId])) {
            $result = $bonus->applyPenalty([
                'waiter_id' => $waiterId,
                'waiter_name' => (string) ($waiter['name'] ?? $waiter['email'] ?? 'Waiter'),
                'penalty_type' => 'absent',
                'date' => $date,
                'reason' => 'Tidak hadir pada hari kerja (otomatis dari audit scheduler)',
                'related_task_id' => '',
            ]);

            if ($result['success'] ?? false) {
                $penaltiesApplied++;
                $penaltyKeys[$waiterId] = true;
            }
        }
    }

    $this->info("Marked absent records: {$markedAbsent}");
    $this->info("Applied absent penalties: {$penaltiesApplied}");
})->purpose('Audit waiter no-shows and apply absent penalties');

Artisan::command('waiter:send-weekly-report', function () {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);

    $endDate = now()->subDay()->format('Y-m-d'); // Yesterday (Sunday)
    $startDate = now()->subDays(7)->format('Y-m-d'); // Last Monday

    $waiters = $firebase->getActiveWaiters();
    $totalTasks = 0;
    $doneTasks = 0;
    $overdueTasks = 0;
    $waiterStats = [];

    foreach ($waiters as $waiter) {
        $waiterId = (string) ($waiter['id'] ?? '');
        if ($waiterId === '') {
            continue;
        }

        $tasks = $firebase->getWaiterTasksByWaiterId($waiterId);
        $waiterTotal = 0;
        $waiterDone = 0;

        foreach ($tasks as $task) {
            $scheduledDate = (string) ($task['scheduled_for_date'] ?? '');
            $createdAt = (int) ($task['created_at'] ?? 0);
            $taskDate = $scheduledDate ?: ($createdAt > 0 ? date('Y-m-d', $createdAt) : '');

            if ($taskDate < $startDate || $taskDate > $endDate) {
                continue;
            }

            $totalTasks++;
            $waiterTotal++;
            $status = (string) ($task['status'] ?? 'pending');

            if ($status === 'done') {
                $doneTasks++;
                $waiterDone++;
            } elseif ($status === 'overdue') {
                $overdueTasks++;
            }
        }

        if ($waiterTotal > 0) {
            $waiterStats[] = [
                'name' => $waiter['name'] ?? 'Waiter',
                'done' => $waiterDone,
                'total' => $waiterTotal,
                'rate' => $waiterTotal > 0 ? ($waiterDone / $waiterTotal) : 0,
            ];
        }
    }

    usort($waiterStats, fn ($a, $b) => $b['rate'] <=> $a['rate']);

    $topPerformers = array_slice($waiterStats, 0, 3);
    $needsAttention = array_filter($waiterStats, fn ($w) => $w['rate'] < 0.5);
    usort($needsAttention, fn ($a, $b) => $a['rate'] <=> $b['rate']);

    $sent = $fonnte->sendWeeklyReport([
        'period' => $startDate.' s/d '.$endDate,
        'total' => $totalTasks,
        'done' => $doneTasks,
        'overdue' => $overdueTasks,
        'top_performers' => $topPerformers,
        'needs_attention' => array_values(array_slice($needsAttention, 0, 3)),
    ]);

    $this->info('Weekly report sent: '.($sent ? 'YES' : 'NO'));
})->purpose('Send weekly task summary report to supervisor via WhatsApp');

Artisan::command('waiter:send-monthly-report', function () {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);

    $lastMonth = now()->subMonth();
    $startDate = $lastMonth->startOfMonth()->format('Y-m-d');
    $endDate = $lastMonth->endOfMonth()->format('Y-m-d');
    $monthLabel = $lastMonth->translatedFormat('F Y');

    $waiters = $firebase->getActiveWaiters();
    $totalTasks = 0;
    $doneTasks = 0;
    $overdueTasks = 0;
    $waiterStats = [];
    $categoryStats = [];

    foreach ($waiters as $waiter) {
        $waiterId = (string) ($waiter['id'] ?? '');
        if ($waiterId === '') {
            continue;
        }

        $tasks = $firebase->getWaiterTasksByWaiterId($waiterId);
        $waiterTotal = 0;
        $waiterDone = 0;

        foreach ($tasks as $task) {
            $scheduledDate = (string) ($task['scheduled_for_date'] ?? '');
            $createdAt = (int) ($task['created_at'] ?? 0);
            $taskDate = $scheduledDate ?: ($createdAt > 0 ? date('Y-m-d', $createdAt) : '');

            if ($taskDate < $startDate || $taskDate > $endDate) {
                continue;
            }

            $totalTasks++;
            $waiterTotal++;
            $status = (string) ($task['status'] ?? 'pending');

            if ($status === 'done') {
                $doneTasks++;
                $waiterDone++;
            } elseif ($status === 'overdue') {
                $overdueTasks++;
            }

            // Category breakdown
            $catName = (string) ($task['category_name'] ?? 'Lainnya');
            if (! isset($categoryStats[$catName])) {
                $categoryStats[$catName] = ['name' => $catName, 'done' => 0, 'total' => 0];
            }
            $categoryStats[$catName]['total']++;
            if ($status === 'done') {
                $categoryStats[$catName]['done']++;
            }
        }

        if ($waiterTotal > 0) {
            $waiterStats[] = [
                'name' => $waiter['name'] ?? 'Waiter',
                'done' => $waiterDone,
                'total' => $waiterTotal,
                'rate' => $waiterTotal > 0 ? ($waiterDone / $waiterTotal) : 0,
            ];
        }
    }

    usort($waiterStats, fn ($a, $b) => $b['rate'] <=> $a['rate']);
    $categoryList = array_values($categoryStats);
    usort($categoryList, fn ($a, $b) => $b['total'] <=> $a['total']);

    $sent = $fonnte->sendMonthlyReport([
        'period' => $monthLabel,
        'total' => $totalTasks,
        'done' => $doneTasks,
        'overdue' => $overdueTasks,
        'total_waiters' => count($waiters),
        'top_performers' => array_slice($waiterStats, 0, 3),
        'by_category' => array_slice($categoryList, 0, 5),
    ]);

    $this->info('Monthly report sent: '.($sent ? 'YES' : 'NO'));
})->purpose('Send monthly task summary report to supervisor via WhatsApp');

Artisan::command('waiter:check-stale-po', function () {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);

    if (!$fonnte->isAutoReportEnabled()) {
        $this->info('Auto report disabled, skipping stale PO check.');
        return;
    }

    $staleOrders = $firebase->getStalePurchaseOrders(3);
    if (empty($staleOrders)) {
        $this->info('No stale POs found.');
        return;
    }

    $reportPhone = $fonnte->getReportPhone();
    if (!$reportPhone) {
        $this->info('No report phone configured.');
        return;
    }

    $lines = ["⚠️ *PO Belum Diterima (>3 hari)*\n"];
    foreach ($staleOrders as $po) {
        $daysAgo = round((time() - ($po['created_at'] ?? time())) / 86400);
        $lines[] = "📦 {$po['po_number']} — {$po['items_count']} item, {$daysAgo} hari lalu";
        if (!empty($po['supplier'])) {
            $lines[] = "   Supplier: {$po['supplier']}";
        }
    }
    $lines[] = "\nSegera follow up ke supplier.";

    $fonnte->sendMessage($reportPhone, implode("\n", $lines));
    $this->info('Stale PO reminder sent for ' . count($staleOrders) . ' PO(s).');
})->purpose('Send WhatsApp reminder for POs not received after 3 days');

Schedule::command('waiter:process-tasks')->everyMinute()->withoutOverlapping();
Schedule::command('waiter:send-task-reminders')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('waiter:audit-attendance')->hourly()->withoutOverlapping();
Schedule::command('waiter:send-weekly-report')->weeklyOn(1, '07:00')->withoutOverlapping();
Schedule::command('waiter:send-monthly-report')->monthlyOn(1, '07:00')->withoutOverlapping();
Schedule::command('waiter:check-stale-po')->dailyAt('08:00')->withoutOverlapping();
