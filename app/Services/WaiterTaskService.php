<?php

namespace App\Services;

use App\Services\WaiterTaskFirebaseService;

use App\Models\WaiterTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WaiterTaskService
{
    public function __construct(
        private FirebaseService $firebase,
        private FirebaseSyncService $sync,
        private WaiterTaskFirebaseService $waiterTask
    ) {
    }

    /**
     * Today's active tasks for a waiter. MySQL when flag on, else Firebase legacy
     * (date-bounded last 7 days — see getWaiterTasksByWaiterId).
     */
    public function getTodayTasksForWaiter(string $waiterId): Collection
    {
        if (config('features.mysql_waiter_tasks')) {
            return WaiterTask::query()
                ->forWaiter($waiterId)
                ->forDate(now()->format('Y-m-d'))
                ->active()
                ->orderBy('scheduled_time')
                ->get();
        }

        return collect(
            $this->waiterTask->getWaiterTasksByWaiterId(
                $waiterId,
                now()->subDays(7)->format('Y-m-d'),
                now()->format('Y-m-d')
            )
        );
    }

    /**
     * Complete a task. Frontend sends a Firebase/deterministic KEY, not the MySQL id
     * (koreksi 19.1) — resolve by key, never findOrFail($mysqlId).
     */
    public function completeTask(string $key, array $payload): array
    {
        if (! config('features.mysql_waiter_tasks')) {
            return $this->waiterTask->updateWaiterTaskStatus(
                $key,
                'done',
                $payload['waiter_id'] ?? '',
                $payload['waiter_name'] ?? '',
                $payload['waiter_email'] ?? '',
                $payload['notes'] ?? null,
            );
        }

        return DB::transaction(function () use ($key, $payload) {
            $task = WaiterTask::query()
                ->where('deterministic_key', $key)
                ->orWhere('firebase_legacy_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent: already done -> return success without reprocessing
            if ($task->status === 'done') {
                return ['success' => true, 'message' => 'Tugas sudah selesai.'];
            }

            $task->update([
                'status' => 'done',
                'completed_at' => now(),
                'completed_by' => $payload['waiter_id'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'photo_url' => $payload['photo_url'] ?? null,
            ]);

            $this->sync->removeWaiterTask($task);

            return ['success' => true, 'message' => 'Tugas selesai.'];
        });
    }
}
