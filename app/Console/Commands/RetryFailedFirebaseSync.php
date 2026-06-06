<?php

namespace App\Console\Commands;

use App\Models\FirebaseSyncLog;
use App\Models\WaiterTask;
use App\Services\FirebaseSyncService;
use Illuminate\Console\Command;

/**
 * RetryFailedFirebaseSync (plan 10.2)
 *
 * Ambil firebase_sync_logs status=failed yang sudah lewat next_retry_at,
 * ulang publish/remove via FirebaseSyncService berdasar action asli.
 *
 * Schedule: firebase:retry-sync everyFiveMinutes.
 *
 * Usage:
 *   php artisan firebase:retry-sync
 *   php artisan firebase:retry-sync --limit=100
 */
class RetryFailedFirebaseSync extends Command
{
    protected $signature = 'firebase:retry-sync
                            {--limit=50 : Maksimum log diproses per run}';

    protected $description = 'Retry firebase_sync_logs yang gagal dan sudah jatuh tempo';

    public function handle(FirebaseSyncService $sync): int
    {
        $limit = (int) $this->option('limit');

        $logs = FirebaseSyncLog::query()
            ->retryable()
            ->where('entity_type', 'waiter_task')
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Tidak ada sync gagal yang jatuh tempo.');
            return Command::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($logs as $log) {
            $task = WaiterTask::find($log->entity_id);
            if (! $task) {
                // Task hilang -> log usang, tandai untuk skip retry berikutnya
                $log->update(['status' => 'success', 'error_message' => 'task gone, skipped']);
                continue;
            }

            if ($log->action === 'remove') {
                $sync->removeWaiterTask($task);
            } else {
                $sync->publishWaiterTask($task);
            }

            $task->refresh()->sync_status === 'synced' ? $ok++ : $fail++;
        }

        $this->info("Retry selesai. Sukses: {$ok} | Masih gagal: {$fail}");
        return Command::SUCCESS;
    }
}
