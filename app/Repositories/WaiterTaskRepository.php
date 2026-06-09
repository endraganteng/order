<?php

namespace App\Repositories;

use App\Models\WaiterTask;
use App\Repositories\Contracts\WaiterTaskRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * WaiterTaskRepository
 *
 * Hybrid impl: flag mysql_waiter_tasks pilih read MySQL atau RTDB. Read + delete
 * data-access dipindah dari FirebaseService (god object). Business logic
 * (assignment, status update) TETAP di service. requestCache di-pass dari
 * service via closure resolver supaya konsistensi per-request terjaga.
 */
class WaiterTaskRepository implements WaiterTaskRepositoryInterface
{
    private array $cache = [];

    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        $snapshot = $this->database->getReference('waiter_tasks')->getSnapshot();
        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }
        $this->sortByCreatedDesc($tasks);

        return $tasks;
    }

    public function find(string $taskId): ?array
    {
        $snapshot = $this->database->getReference('waiter_tasks/'.$taskId)->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $taskId], $snapshot->getValue());
    }

    public function delete(string $id): void
    {
        // Remove from RTDB
        $this->database->getReference('waiter_tasks/'.$id)->remove();

        // Also cancel in MySQL (waiter portal reads from MySQL)
        if (config('features.mysql_waiter_tasks')) {
            WaiterTask::where('firebase_legacy_key', $id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }
    }

    public function forDate(string $date): array
    {
        $snapshot = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')->equalTo($date)->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        return $tasks;
    }

    public function forDateRange(string $startDate, string $endDate): array
    {
        $cacheKey = 'range_'.$startDate.'_'.$endDate;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $snapshot = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->startAt($startDate)->endAt($endDate)->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }
        $this->sortByCreatedDesc($tasks);
        $this->cache[$cacheKey] = $tasks;

        return $tasks;
    }

    public function forWaiter(string $waiterId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $cacheKey = 'by_id_'.$waiterId.'_'.($dateFrom ?? 'null').'_'.($dateTo ?? 'null');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (config('features.mysql_waiter_tasks')) {
            $tasks = $this->forWaiterFromMysql($waiterId, $dateFrom, $dateTo);
        } else {
            $tasks = $this->forWaiterFromRtdb($waiterId, $dateFrom, $dateTo);
        }
        $this->sortByCreatedDesc($tasks);
        $this->cache[$cacheKey] = $tasks;

        return $tasks;
    }

    public function forWaiterOnDate(string $waiterId, string $date): array
    {
        $cacheKey = 'by_date_'.$date;
        if (! isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->forDate($date);
        }

        return array_values(array_filter(
            $this->cache[$cacheKey],
            fn ($task) => ($task['assigned_waiter_id'] ?? '') === $waiterId
        ));
    }

    private function forWaiterFromMysql(string $waiterId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = WaiterTask::query()->forWaiter($waiterId);
        if ($dateFrom !== null) {
            $query->where('scheduled_for_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('scheduled_for_date', '<=', $dateTo);
        }

        return $query->get()->map(function ($row) {
            $payload = is_array($row->firebase_payload) ? $row->firebase_payload : [];
            if (empty($payload)) {
                $payload = [
                    'title' => $row->title,
                    'description' => $row->description,
                    'task_type' => $row->task_type,
                    'assigned_waiter_id' => $row->assigned_waiter_id,
                    'assigned_waiter_name' => $row->assigned_waiter_name,
                    'scheduled_for_date' => optional($row->scheduled_for_date)->format('Y-m-d'),
                    'priority' => $row->priority,
                    'rack_id' => $row->rack_id,
                    'rack_name' => $row->rack_name,
                    'created_at' => optional($row->created_at)->timestamp,
                ];
            }
            $payload['id'] = $row->firebase_legacy_key ?: $row->deterministic_key;
            $payload['status'] = $row->status; // MySQL is source of truth for status
            return $payload;
        })->all();
    }

    private function forWaiterFromRtdb(string $waiterId, ?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom !== null || $dateTo !== null) {
            $from = $dateFrom ?: '0000-00-00';
            $to = $dateTo ?: '9999-12-31';

            $snapshot = $this->database->getReference('waiter_tasks')
                ->orderByChild('scheduled_for_date')
                ->startAt($from)->endAt($to)->getSnapshot();

            $tasks = [];
            if ($snapshot->exists()) {
                foreach ($snapshot->getValue() as $key => $task) {
                    if ((string) ($task['assigned_waiter_id'] ?? '') === $waiterId) {
                        $tasks[] = array_merge(['id' => $key], $task);
                    }
                }
            }
            return $tasks;
        }

        $snapshot = $this->database->getReference('waiter_tasks')
            ->orderByChild('assigned_waiter_id')->equalTo($waiterId)->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        return $tasks;
    }

    private function sortByCreatedDesc(array &$tasks): void
    {
        usort($tasks, function ($a, $b) {
            $aTime = is_numeric($a['created_at'] ?? 0) ? (int) ($a['created_at'] ?? 0) : strtotime($a['created_at'] ?? '0');
            $bTime = is_numeric($b['created_at'] ?? 0) ? (int) ($b['created_at'] ?? 0) : strtotime($b['created_at'] ?? '0');
            return $bTime - $aTime;
        });
    }
}
