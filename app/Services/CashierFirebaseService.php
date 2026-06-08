<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class CashierFirebaseService
{
    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Dual-write a freshly created Firebase cashier task into MySQL when the
     * flag is on. Idempotent via firebase_legacy_key. Mirrors the full Firebase
     * payload into firebase_payload so the cashier portal keeps every field.
     * Failure is logged, never fatal during rollout.
     */
    protected function dualWriteCashierTaskToMysql(string $firebaseKey, array $payload): void
    {
        if (! config('features.mysql_cashier_tasks')) {
            return;
        }

        try {
            $createdAt = $payload['created_at'] ?? null;
            $completedAt = $payload['completed_at'] ?? null;
            $rawStatus = $payload['status'] ?? 'pending';
            $validStatus = ['pending', 'in_progress', 'done', 'overdue', 'cancelled', 'failed'];
            \App\Models\CashierTask::updateOrCreate(
                ['firebase_legacy_key' => $firebaseKey],
                [
                    'deterministic_key' => 'ct_legacy_'.substr(hash('sha256', $firebaseKey), 0, 32),
                    'source_template_key' => isset($payload['source_template_id']) ? (string) $payload['source_template_id'] : null,
                    'title' => (string) ($payload['title'] ?? 'Untitled'),
                    'description' => $payload['description'] ?? null,
                    'scheduled_date' => $payload['scheduled_for_date'] ?? now()->format('Y-m-d'),
                    'scheduled_time' => $payload['scheduled_time'] ?? null,
                    'status' => in_array($rawStatus, $validStatus, true) ? $rawStatus : 'pending',
                    'is_recurring' => (bool) ($payload['is_recurring_instance'] ?? false),
                    'recurrence_pattern' => $payload['recurrence_type'] ?? null,
                    'notes' => $payload['completed_note'] ?? null,
                    'firebase_payload' => $payload,
                    'completed_at' => is_numeric($completedAt) ? date('Y-m-d H:i:s', (int) $completedAt) : null,
                    'created_at' => is_numeric($createdAt) ? date('Y-m-d H:i:s', (int) $createdAt) : null,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Reconstruct the RTDB cashier-task shape from a MySQL row. Prefers the
     * verbatim firebase_payload; falls back to structured columns for rows
     * seeded before the payload column existed. 'id' is the legacy key the
     * portal already expects.
     */
    protected function cashierRowToPayload(\App\Models\CashierTask $row): array
    {
        $id = $row->firebase_legacy_key ?: (string) $row->id;
        $payload = is_array($row->firebase_payload) ? $row->firebase_payload : [];

        if (empty($payload)) {
            $payload = [
                'title' => $row->title,
                'description' => $row->description,
                'status' => $row->status,
                'scheduled_for_date' => optional($row->scheduled_date)->format('Y-m-d'),
                'scheduled_time' => $row->scheduled_time,
                'source_template_id' => $row->source_template_key,
                'is_recurring_instance' => (bool) $row->is_recurring,
                'created_at' => optional($row->created_at)->timestamp,
            ];
        }

        return array_merge(['id' => $id], $payload);
    }

    /**
     * Get cashier worker list
     */
    public function getCashierWorkers()
    {
        $reference = $this->database->getReference('cashier_workers');
        $snapshot = $reference->getSnapshot();

        $workers = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $id => $worker) {
                $workers[] = array_merge(['id' => $id], $worker);
            }
        }

        usort($workers, function ($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $workers;
    }

    /**
     * Get active cashier workers only
     */
    public function getActiveCashierWorkers()
    {
        return array_values(array_filter($this->getCashierWorkers(), function ($worker) {
            return ($worker['is_active'] ?? true) !== false;
        }));
    }

    /**
     * Get cashier worker by id
     */
    public function getCashierWorkerById($id)
    {
        $reference = $this->database->getReference('cashier_workers/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    /**
     * Add cashier worker
     */
    public function addCashierWorker($name)
    {
        $this->database->getReference('cashier_workers')
            ->push([
                'name' => trim($name),
                'is_active' => true,
                'created_at' => time(),
            ]);
    }

    /**
     * Delete cashier worker
     */
    public function deleteCashierWorker($id)
    {
        if ($this->isCashierWorkerReferenced($id)) {
            $this->database->getReference('cashier_workers/'.$id)
                ->update([
                    'is_active' => false,
                    'deactivated_at' => time(),
                ]);

            return;
        }

        $this->database->getReference('cashier_workers/'.$id)
            ->remove();
    }

    /**
     * Check whether cashier worker already referenced in completed tasks
     */
    protected function isCashierWorkerReferenced($id)
    {
        $tasksRef = $this->database->getReference('cashier_tasks');
        $snapshot = $tasksRef->getSnapshot();

        if (! $snapshot->exists()) {
            return false;
        }

        foreach ($snapshot->getValue() as $task) {
            if (($task['completed_by_worker_id'] ?? null) === $id) {
                return true;
            }
        }

        return false;
    }
}
