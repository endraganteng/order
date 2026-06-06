<?php

namespace App\Console\Commands;

use App\Models\FirebaseSyncLog;
use App\Models\WaiterTask;
use App\Services\FirebaseSyncService;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Database;
use Throwable;

/**
 * ReconcileFirebaseActiveTasks (plan 10.3 + koreksi 19.5)
 *
 * Pastikan /waiter_tasks_active/{today} cermin MySQL:
 *  1. Task MySQL aktif belum ada di Firebase -> publish ulang.
 *  2. Task Firebase tak ada di MySQL -> remove (orphan).
 *  3. Status beda -> MySQL overwrite Firebase.
 *
 * WAJIB tulis 1 row firebase_sync_logs action='reconcile' tiap run.
 * Guard 3 cleanup command baca row ini (kontrak 19.5).
 *
 * Schedule: firebase:reconcile-active-tasks everyFifteenMinutes.
 */
class ReconcileFirebaseActiveTasks extends Command
{
    protected $signature = 'firebase:reconcile-active-tasks
                            {--date= : Tanggal target YYYY-MM-DD (default hari ini WIB)}';

    protected $description = 'Reconcile MySQL waiter_tasks aktif dengan node Firebase active';

    public function handle(Database $database, FirebaseSyncService $sync): int
    {
        $date = $this->option('date') ?: now()->format('Y-m-d');

        $republished = 0;
        $removed = 0;
        $overwritten = 0;
        $hadFatalError = false;

        try {
            // MySQL: task aktif hari ini, indexed by deterministic_key
            $mysqlTasks = WaiterTask::query()
                ->forDate($date)
                ->active()
                ->get()
                ->keyBy('deterministic_key');

            // Firebase: snapshot node active hari ini (semua waiter)
            $fbSnapshot = $database->getReference("waiter_tasks_active/{$date}")->getSnapshot();
            $fbValue = $fbSnapshot->getValue() ?: [];

            // Flatten Firebase: {waiterId: {key: task}} -> {key: task}
            $fbKeys = [];
            foreach ($fbValue as $waiterId => $tasksByKey) {
                if (! is_array($tasksByKey)) {
                    continue;
                }
                foreach ($tasksByKey as $key => $task) {
                    $fbKeys[$key] = $task;
                }
            }

            // 1 & 3: MySQL aktif -> pastikan ada + status sinkron di Firebase
            foreach ($mysqlTasks as $key => $task) {
                $fbTask = $fbKeys[$key] ?? null;
                if ($fbTask === null) {
                    $sync->publishWaiterTask($task);
                    $republished++;
                } elseif (($fbTask['status'] ?? null) !== $task->status) {
                    $sync->publishWaiterTask($task); // overwrite, MySQL menang
                    $overwritten++;
                }
            }

            // 2: Firebase orphan (tak ada di MySQL aktif) -> remove
            foreach ($fbKeys as $key => $fbTask) {
                if (! $mysqlTasks->has($key)) {
                    $waiterId = $fbTask['assigned_waiter_id'] ?? null;
                    if ($waiterId) {
                        $database->getReference("waiter_tasks_active/{$date}/{$waiterId}/{$key}")->remove();
                        $removed++;
                    }
                }
            }
        } catch (Throwable $e) {
            $hadFatalError = true;
            report($e);
            $this->error('Reconcile error: '.$e->getMessage());
        }

        // Kontrak 19.5: row run-level action='reconcile' tiap run
        FirebaseSyncLog::create([
            'entity_type' => 'reconcile_run',
            'entity_id' => 'waiter_tasks',
            'firebase_path' => "waiter_tasks_active/{$date}",
            'action' => 'reconcile',
            'status' => $hadFatalError ? 'failed' : 'success',
            'payload' => compact('republished', 'removed', 'overwritten', 'date'),
            'attempt_count' => 1,
            'last_attempt_at' => now(),
        ]);

        $this->info("Reconcile [{$date}] republished={$republished} overwritten={$overwritten} removed={$removed}");
        return $hadFatalError ? Command::FAILURE : Command::SUCCESS;
    }
}
