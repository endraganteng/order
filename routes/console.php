<?php

use App\Services\BonusService;
use App\Services\FirebaseService;
use App\Services\FonnteService;
use App\Services\PayrollService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('waiter:process-tasks', function () {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);
    $generateResult = $firebase->generateDueRecurringWaiterTasks();
    $generatedCount = is_array($generateResult)
        ? (int) ($generateResult['generated'] ?? 0)
        : (int) $generateResult;
    $generatedDates = is_array($generateResult) ? ($generateResult['dates'] ?? []) : [];

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

    $this->info("Generated recurring tasks: {$generatedCount}");
    if (! empty($generatedDates)) {
        $this->info('Dates processed: '.implode(', ', $generatedDates));
    }
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

Artisan::command('firebase:cleanup-idempotency {--days=7 : Hapus cache yang lebih lama dari N hari}', function () {
    $firebase = app(FirebaseService::class);
    $days = max(1, (int) $this->option('days'));
    $cutoff = time() - ($days * 86400);

    $stats = $firebase->cleanupIdempotencyCaches($cutoff);
    $this->info('Cutoff: '.date('Y-m-d H:i:s', $cutoff)." ({$days} hari lalu)");
    $this->info('stock_movement_idempotency dihapus: '.$stats['stock_movement']);
    $this->info('waiter_task_idempotency dihapus: '.$stats['waiter_task']);
})->purpose('Bersihkan idempotency cache lama untuk hemat bandwidth Firebase');

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

Artisan::command('firebase:reconcile-stock {--days=7 : Window dalam hari}', function () {
    $firebase = app(FirebaseService::class);
    $fonnte = app(FonnteService::class);
    $days = max(1, (int) $this->option('days'));

    $result = $firebase->runWeeklyReconciliation($days);
    $anomalies = $result['anomalies'] ?? [];

    $this->info('Total racks: '.(int) ($result['total_racks_checked'] ?? 0));
    $this->info('Total products: '.(int) ($result['total_products_checked'] ?? 0));
    $this->info('Anomalies: '.count($anomalies));

    foreach (array_slice($anomalies, 0, 5) as $idx => $anomaly) {
        $this->line(sprintf(
            '%d) %s / %s | expected=%d actual=%d drift=%+.2f%%',
            $idx + 1,
            (string) ($anomaly['rack_name'] ?? $anomaly['rack_id'] ?? '-'),
            (string) ($anomaly['product_name'] ?? $anomaly['product_id'] ?? '-'),
            (int) ($anomaly['expected'] ?? 0),
            (int) ($anomaly['actual'] ?? 0),
            (float) ($anomaly['drift_pct'] ?? 0)
        ));
    }

    if (count($anomalies) > 0 && $fonnte->isAutoReportEnabled()) {
        $reportPhone = $fonnte->getReportPhone();
        if ($reportPhone) {
            $lines = [];
            foreach (array_slice($anomalies, 0, 5) as $anomaly) {
                $lines[] = sprintf(
                    '- %s/%s: %.2f%% (%+d)',
                    (string) ($anomaly['rack_name'] ?? $anomaly['rack_id'] ?? '-'),
                    (string) ($anomaly['product_name'] ?? $anomaly['product_id'] ?? '-'),
                    (float) ($anomaly['drift_pct'] ?? 0),
                    (int) ($anomaly['drift_qty'] ?? 0)
                );
            }

            $body = "🚨 *Reconciliation Mingguan*\n"
                .'Minggu: '.(string) ($result['iso_year_week'] ?? date('o_W'))."\n"
                .'Drift terdeteksi: '.count($anomalies)."\n\n"
                ."Top 5:\n".implode("\n", $lines)."\n\n"
                .'Lihat detail: /admin/reconciliation';

            $fonnte->sendMessage($reportPhone, $body);
        }
    }
})->purpose('Rekonsiliasi stok mingguan dari ledger vs snapshot rak');

Artisan::command('bonus:generate-leaderboard {month? : Format Y-m, default bulan ini}', function (?string $month = null) {
    $bonus = app(BonusService::class);
    $month = $month ?: date('Y-m');

    try {
        $leaderboard = $bonus->generateLeaderboard($month);
        $count = is_array($leaderboard['rankings'] ?? null) ? count($leaderboard['rankings']) : 0;
        $this->info("Leaderboard generated for {$month}: {$count} entries.");
    } catch (\Throwable $e) {
        $this->error('Failed: '.$e->getMessage());
        report($e);
        return 1;
    }
    return 0;
})->purpose('Auto-regenerate bonus leaderboard for current month');

Artisan::command('bonus:reconcile-pending', function () {
    $firebase = app(FirebaseService::class);
    $bonus = app(BonusService::class);

    $items = $firebase->getBonusPendingRecomputes(100);
    if (empty($items)) {
        $this->info('No pending bonus recompute items.');
        return 0;
    }

    $this->info('Processing '.count($items).' pending bonus recompute item(s)...');
    $success = 0;
    $failed = 0;

    foreach ($items as $item) {
        $waiterId = (string) ($item['waiter_id'] ?? '');
        $date = (string) ($item['date'] ?? '');
        if ($waiterId === '' || $date === '') {
            $firebase->clearBonusPendingFlag($item);
            continue;
        }

        try {
            $attendance = $firebase->getAttendanceByDate($waiterId, $date);
            $todayTasks = $firebase->getWaiterTasksForDate($waiterId, $date);
            $reports = $firebase->getWaiterActivityReportsByWaiterIdForDate($waiterId, $date);

            $autoScores = $bonus->autoScoreDailyPoints($waiterId, $date, $attendance, $todayTasks, $reports);
            $categoryScores = [
                'discipline' => $autoScores['discipline'] ?? 0,
                'operational' => $autoScores['operational'] ?? 0,
                'attitude' => $autoScores['attitude'] ?? 0,
            ];
            $bonus->saveAutoDailyScore($waiterId, $date, $categoryScores, 'Auto-scored on reconcile worker', $autoScores['auto_details'] ?? []);

            $firebase->clearBonusPendingFlag($item);
            $success++;
        } catch (\Throwable $e) {
            report($e);
            $failed++;
            // Don't clear; will retry next run. But cap retry count to avoid infinite loop.
        }
    }

    $this->info("Reconciled: {$success}, Failed: {$failed}");
    return $failed > 0 ? 1 : 0;
})->purpose('Retry auto-score for tasks/events that failed bonus computation');

Schedule::command('waiter:process-tasks')->everyMinute()->withoutOverlapping();
Schedule::command('waiter:send-task-reminders')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('waiter:audit-attendance')->hourly()->withoutOverlapping();
Schedule::command('waiter:send-weekly-report')->weeklyOn(1, '07:00')->withoutOverlapping();
Schedule::command('firebase:reconcile-stock')->weeklyOn(1, '07:30')->withoutOverlapping();
Schedule::command('bonus:generate-leaderboard')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('bonus:reconcile-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('waiter:send-monthly-report')->monthlyOn(1, '07:00')->withoutOverlapping();
Schedule::command('waiter:check-stale-po')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('firebase:cleanup-idempotency')->dailyAt('03:00')->withoutOverlapping();

Artisan::command('payroll:auto-credit-salary {--catchup=7 : Catchup window dalam hari}', function () {
    $payroll = app(PayrollService::class);
    $catchup = max(0, (int) $this->option('catchup'));
    $result = $payroll->runDailySalaryCredit($catchup);
    $this->info('Salary credits applied: ' . (int) ($result['credited'] ?? 0));
    $this->info('Skipped (not eligible / no payday match): ' . (int) ($result['skipped'] ?? 0));
    if (! empty($result['errors'])) {
        $this->warn('Errors: ' . count($result['errors']));
        foreach ($result['errors'] as $err) {
            $this->warn(' - ' . json_encode($err));
        }
    }
})->purpose('Auto-credit gaji pokok bulanan ke saldo payroll karyawan eligible');

Schedule::command('payroll:auto-credit-salary')->dailyAt('05:00')->withoutOverlapping();

// ─── Finance Auto Sync ──────────────────────────────────────────
Artisan::command('finance:auto-sync', function () {
    $finance = app(\App\Services\FinanceService::class);
    $enabled = $finance->getSetting('auto_sync_enabled', '0');

    if ($enabled !== '1') {
        $this->info('Finance auto sync is disabled.');
        return 0;
    }

    $syncMode = $finance->getSetting('sync_mode', 'daily');
    $syncTime = $finance->getSetting('auto_sync_time', '00:00');
    $syncTarget = $finance->getSetting('sync_data_target', 'yesterday');
    $currentHour = date('H:i');

    // Untuk mode daily, hanya jalan di jam yang diset
    if ($syncMode === 'daily' && substr($currentHour, 0, 2) !== substr($syncTime, 0, 2)) {
        return 0;
    }

    // Tentukan tanggal berdasarkan sync_data_target
    if ($syncTarget === 'today') {
        $from = $to = date('Y-m-d');
    } else {
        $from = $to = date('Y-m-d', strtotime('-1 day'));
    }

    $result = $finance->syncDaily($from, $to, 'auto_sync');

    $this->info("Finance auto sync: status={$result['status']}, synced={$result['synced']}, failed={$result['failed']}");

    // Retry failed dari 3 hari terakhir
    $failedLogs = \Illuminate\Support\Facades\DB::table('finance_sync_logs')
        ->where('status', 'failed')
        ->where('created_at', '>=', now()->subDays(3))
        ->limit(3)
        ->get();

    foreach ($failedLogs as $log) {
        $retryResult = $finance->syncDaily($log->sync_date_from, $log->sync_date_to, 'auto_retry');
        $this->info("Retry {$log->sync_date_from}: {$retryResult['status']}");
    }

    return $result['status'] === 'failed' ? 1 : 0;
})->purpose('Auto sync finance data dari API shift kasir');

// Schedule finance auto sync setiap jam (command sendiri yang cek apakah enabled + jam yang tepat)
Schedule::command('finance:auto-sync')->hourly()->withoutOverlapping();
