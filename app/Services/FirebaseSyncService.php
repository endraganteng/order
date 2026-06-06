<?php

namespace App\Services;

use App\Models\FirebaseSyncLog;
use App\Models\WaiterTask;
use Kreait\Firebase\Contract\Database;
use Throwable;

class FirebaseSyncService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Publish a waiter task to the bounded active node:
     * waiter_tasks_active/{date}/{waiterId}/{deterministic_key}
     */
    public function publishWaiterTask(WaiterTask $task): void
    {
        $date = $task->scheduled_for_date->format('Y-m-d');
        $waiterId = $task->assigned_waiter_id;
        $key = $task->deterministic_key;

        $path = "waiter_tasks_active/{$date}/{$waiterId}/{$key}";

        $payload = [
            'mysql_id' => $task->id,
            'deterministic_key' => $key,
            'title' => $task->title,
            'description' => $task->description,
            'task_type' => $task->task_type,
            'assigned_waiter_id' => $task->assigned_waiter_id,
            'assigned_waiter_name' => $task->assigned_waiter_name,
            'scheduled_for_date' => $date,
            'scheduled_time' => optional($task->scheduled_time)->format('H:i'),
            'status' => $task->status,
            'priority' => $task->priority,
            'rack_id' => $task->rack_id,
            'rack_code' => $task->rack_code,
            'rack_name' => $task->rack_name,
            'updated_at' => now()->toIso8601String(),
        ];

        $log = FirebaseSyncLog::create([
            'entity_type' => 'waiter_task',
            'entity_id' => (string) $task->id,
            'firebase_path' => $path,
            'action' => 'set',
            'status' => 'pending',
            'payload' => $payload,
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);

        try {
            $this->database->getReference($path)->set($payload);

            $task->update([
                'firebase_active_path' => $path,
                'publish_status' => 'published',
                'sync_status' => 'synced',
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $log->update([
                'status' => 'success',
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
            ]);
        } catch (Throwable $e) {
            $task->update([
                'publish_status' => 'failed',
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => now()->addMinutes(5),
            ]);

            report($e);
        }
    }

    /**
     * Remove a waiter task from its active node (on complete/cancel).
     */
    public function removeWaiterTask(WaiterTask $task): void
    {
        if (! $task->firebase_active_path) {
            return;
        }

        $path = $task->firebase_active_path;

        $log = FirebaseSyncLog::create([
            'entity_type' => 'waiter_task',
            'entity_id' => (string) $task->id,
            'firebase_path' => $path,
            'action' => 'remove',
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);

        try {
            $this->database->getReference($path)->remove();

            $task->update([
                'sync_status' => 'synced',
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $log->update([
                'status' => 'success',
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
            ]);
        } catch (Throwable $e) {
            $task->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => now()->addMinutes(5),
            ]);

            report($e);
        }
    }
}
