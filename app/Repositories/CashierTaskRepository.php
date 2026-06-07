<?php

namespace App\Repositories;

use App\Models\CashierTask;
use App\Repositories\Contracts\CashierTaskRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * CashierTaskRepository
 *
 * Hybrid impl: flag mysql_cashier_tasks pilih read MySQL atau RTDB;
 * legacy_write_cashier_tasks kontrol mirror RTDB. Logika data-access dipindah
 * dari FirebaseService (god object). FirebaseService delegasi ke sini.
 */
class CashierTaskRepository implements CashierTaskRepositoryInterface
{
    private const VALID_STATUS = ['pending', 'in_progress', 'done', 'overdue', 'cancelled', 'failed'];

    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        if (config('features.mysql_cashier_tasks')) {
            return CashierTask::orderByDesc('created_at')->get()
                ->map(fn ($row) => $this->rowToPayload($row))
                ->all();
        }

        $snapshot = $this->database->getReference('cashier_tasks')->getSnapshot();
        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }
        usort($tasks, fn ($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));

        return $tasks;
    }

    public function allActive(): array
    {
        return array_filter($this->all(), fn ($t) => ($t['status'] ?? 'pending') === 'pending');
    }

    public function create(array $taskData): string
    {
        $legacyKey = null;
        if (config('features.legacy_write_cashier_tasks')) {
            $legacyKey = (string) $this->database->getReference('cashier_tasks')->push($taskData)->getKey();
        } else {
            $legacyKey = 'ct_local_'.substr(hash('sha256', json_encode($taskData).microtime()), 0, 24);
        }

        $this->mirrorToMysql($legacyKey, $taskData);

        return $legacyKey;
    }

    public function delete(string $id): void
    {
        if (config('features.legacy_write_cashier_tasks')) {
            $this->database->getReference('cashier_tasks/'.$id)->remove();
        }

        if (config('features.mysql_cashier_tasks')) {
            CashierTask::where('firebase_legacy_key', $id)->delete();
        }
    }

    public function updateStatus(string $id, string $status, ?string $note = null, ?string $workerId = null, ?string $workerName = null): array
    {
        $useMysql = config('features.mysql_cashier_tasks');
        $mysqlRow = $useMysql ? CashierTask::where('firebase_legacy_key', $id)->first() : null;

        $taskReference = $this->database->getReference('cashier_tasks/'.$id);
        $snapshot = $taskReference->getSnapshot();

        if (! $snapshot->exists() && ! $mysqlRow) {
            return ['success' => false, 'message' => 'Tugas tidak ditemukan'];
        }

        $task = $snapshot->exists()
            ? $snapshot->getValue()
            : (is_array($mysqlRow->firebase_payload) ? $mysqlRow->firebase_payload : []);
        $currentStatus = $task['status'] ?? ($mysqlRow->status ?? 'pending');

        if ($currentStatus !== 'pending') {
            return ['success' => false, 'message' => 'Tugas ini sudah tidak aktif'];
        }

        $now = time();
        $deadlineAt = (int) ($task['deadline_at'] ?? 0);
        if ($deadlineAt > 0 && $now > $deadlineAt) {
            $this->applyStatusToRtdb($taskReference, 'overdue', $now, 'Auto: batas waktu habis');
            $this->applyStatusToMysql($id, 'overdue', $now, 'Auto: batas waktu habis');

            return ['success' => false, 'message' => 'Tugas sudah melewati batas waktu dan dihitung tidak selesai'];
        }

        $updates = ['status' => $status, 'completed_at' => $now];
        if (! empty($note)) {
            $updates['completed_note'] = $note;
        }
        if ($status === 'done') {
            $updates['completed_by_worker_id'] = $workerId;
            $updates['completed_by_worker_name'] = $workerName;
        }
        $taskReference->update($updates);

        $this->applyStatusToMysql($id, $status, $now, $note);

        return ['success' => true, 'message' => 'Status tugas berhasil diupdate'];
    }

    public function markOverdue(): int
    {
        $reference = $this->database->getReference('cashier_tasks')
            ->orderByChild('status')->equalTo('pending');
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return 0;
        }

        $now = time();
        $updates = [];
        $overdueCount = 0;
        $baseRef = $this->database->getReference('cashier_tasks');

        foreach ($snapshot->getValue() as $taskId => $task) {
            $deadlineAt = (int) ($task['deadline_at'] ?? 0);
            if ($deadlineAt <= 0 || $now <= $deadlineAt) {
                continue;
            }
            $updates[$taskId.'/status'] = 'overdue';
            $updates[$taskId.'/completed_at'] = $now;
            if (empty($task['completed_note'])) {
                $updates[$taskId.'/completed_note'] = 'Auto: batas waktu habis';
            }
            $this->applyStatusToMysql((string) $taskId, 'overdue', $now, 'Auto: batas waktu habis');
            $overdueCount++;
        }

        if (! empty($updates)) {
            $baseRef->update($updates);
        }

        return $overdueCount;
    }

    public function existingRecurringMap(string $date): array
    {
        $map = [];

        if (config('features.mysql_cashier_tasks')) {
            $rows = CashierTask::where('scheduled_date', $date)
                ->where('status', 'pending')
                ->whereNotNull('source_template_key')
                ->pluck('source_template_key');
            foreach ($rows as $key) {
                $map[$key] = true;
            }
            return $map;
        }

        $snapshot = $this->database->getReference('cashier_tasks')->getSnapshot();
        if (! $snapshot->exists()) {
            return $map;
        }
        foreach ($snapshot->getValue() as $task) {
            $tpl = $task['source_template_id'] ?? null;
            if ($tpl && ($task['scheduled_for_date'] ?? null) === $date && ($task['status'] ?? 'pending') === 'pending') {
                $map[$tpl] = true;
            }
        }

        return $map;
    }

    public function hasPendingRecurringInstance(string $templateId, string $date): bool
    {
        return $this->hasRecurringInstanceWithStatus($templateId, $date, 'pending');
    }

    public function hasDoneRecurringInstance(string $templateId, string $date): bool
    {
        return $this->hasRecurringInstanceWithStatus($templateId, $date, 'done');
    }

    private function hasRecurringInstanceWithStatus(string $templateId, string $date, string $status): bool
    {
        if (config('features.mysql_cashier_tasks')) {
            return CashierTask::where('source_template_key', $templateId)
                ->where('scheduled_date', $date)
                ->where('status', $status)
                ->exists();
        }

        $snapshot = $this->database->getReference('cashier_tasks')
            ->orderByChild('source_template_id')->equalTo($templateId)->getSnapshot();
        if (! $snapshot->exists()) {
            return false;
        }
        foreach ($snapshot->getValue() as $task) {
            if (($task['scheduled_for_date'] ?? null) === $date && ($task['status'] ?? 'pending') === $status) {
                return true;
            }
        }

        return false;
    }

    private function applyStatusToRtdb($reference, string $status, int $now, string $note): void
    {
        $reference->update(['status' => $status, 'completed_at' => $now, 'completed_note' => $note]);
    }

    private function applyStatusToMysql(string $id, string $status, int $now, ?string $note): void
    {
        if (! config('features.mysql_cashier_tasks')) {
            return;
        }
        $updates = ['status' => $status, 'completed_at' => date('Y-m-d H:i:s', $now)];
        if (! empty($note)) {
            $updates['notes'] = $note;
        }
        CashierTask::where('firebase_legacy_key', $id)->update($updates);
    }

    private function mirrorToMysql(string $firebaseKey, array $payload): void
    {
        if (! config('features.mysql_cashier_tasks')) {
            return;
        }

        try {
            $completedAt = $payload['completed_at'] ?? null;
            $createdAt = $payload['created_at'] ?? null;
            $rawStatus = $payload['status'] ?? 'pending';
            CashierTask::updateOrCreate(
                ['firebase_legacy_key' => $firebaseKey],
                [
                    'deterministic_key' => 'ct_legacy_'.substr(hash('sha256', $firebaseKey), 0, 32),
                    'source_template_key' => isset($payload['source_template_id']) ? (string) $payload['source_template_id'] : null,
                    'title' => (string) ($payload['title'] ?? 'Untitled'),
                    'description' => $payload['description'] ?? null,
                    'scheduled_date' => $payload['scheduled_for_date'] ?? now()->format('Y-m-d'),
                    'scheduled_time' => $payload['scheduled_time'] ?? null,
                    'status' => in_array($rawStatus, self::VALID_STATUS, true) ? $rawStatus : 'pending',
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

    private function rowToPayload(CashierTask $row): array
    {
        $id = $row->firebase_legacy_key ?: (string) $row->id;
        $payload = is_array($row->firebase_payload) ? $row->firebase_payload : [];

        if (empty($payload)) {
            $payload = [
                'title' => $row->title,
                'description' => $row->description,
                'scheduled_for_date' => optional($row->scheduled_date)->format('Y-m-d'),
                'scheduled_time' => $row->scheduled_time,
                'source_template_id' => $row->source_template_key,
                'is_recurring_instance' => (bool) $row->is_recurring,
                'created_at' => optional($row->created_at)->timestamp,
            ];
        }

        // MySQL columns are source of truth for mutable state — payload snapshot
        // can be stale (status updated via column, not re-serialized into payload).
        $payload['status'] = $row->status;
        if ($row->completed_at) {
            $payload['completed_at'] = $row->completed_at->timestamp;
        }
        if ($row->notes) {
            $payload['completed_note'] = $row->notes;
        }

        return array_merge(['id' => $id], $payload);
    }
}
