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

Schedule::command('waiter:process-tasks')->everyMinute()->withoutOverlapping();
Schedule::command('waiter:send-task-reminders')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('waiter:audit-attendance')->hourly()->withoutOverlapping();
