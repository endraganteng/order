<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

/**
 * Rack Check Planning operations (Firebase RTDB).
 * Extracted from FirebaseService to reduce god-class size.
 */
class PlanningFirebaseService
{
    protected Database $database;
    protected array $requestCache = [];

    public function __construct(Database $database)
    {
        $this->database = $database;
    }


    /**
     * Get all planning tasks for a specific date from /rack_check_planning.
     *
     * @param  string  $date  Format: YYYY-MM-DD
     * @return array
     */
    public function getPlanningTasksForDate(string $date): array
    {
        $cacheKey = 'planningTasksForDate:' . $date;
        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

        try {
            $snapshot = $this->database->getReference('rack_check_planning')->getSnapshot();
            if (! $snapshot->exists()) {
                $this->requestCache[$cacheKey] = [];
                return [];
            }

            $allTasks = (array) ($snapshot->getValue() ?? []);
            $result = [];

            foreach ($allTasks as $taskId => $task) {
                if (! is_array($task)) {
                    continue;
                }
                if (($task['scheduled_for_date'] ?? '') === $date) {
                    $result[] = array_merge($task, ['id' => $taskId]);
                }
            }

            $this->requestCache[$cacheKey] = $result;
            return $result;
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] getPlanningTasksForDate error', [
                'date'  => $date,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }


    /**
     * Create a new planning task at /rack_check_planning/{auto_id}.
     *
     * @param  array  $data
     * @return string  The generated Firebase key
     */
    public function savePlanningTask(array $data): string
    {
        try {
            $newRef = $this->database->getReference('rack_check_planning')->push($data);

            return (string) $newRef->getKey();
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] savePlanningTask error', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    /**
     * Update specific fields of a planning task at /rack_check_planning/{taskId}.
     * Automatically adds updated_at timestamp.
     *
     * @param  string  $taskId
     * @param  array   $data
     * @return void
     */
    public function updatePlanningTask(string $taskId, array $data): void
    {
        try {
            $data['updated_at'] = time();
            $this->database->getReference('rack_check_planning/' . $taskId)->update($data);
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] updatePlanningTask error', [
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    /**
     * Batch publish all planned+unpublished planning tasks for a date.
     * Creates real waiter tasks with deterministic keys and locks to prevent duplicates.
     *
     * @param  string  $date  Format: YYYY-MM-DD
     * @return array  ['published' => int, 'skipped' => int, 'errors' => array]
     */
    public function publishPlanningForDate(string $date): array
    {
        $published = 0;
        $skipped   = 0;
        $errors    = [];

        try {
            $tasks = $this->getPlanningTasksForDate($date);
        } catch (\Throwable $e) {
            return ['published' => 0, 'skipped' => 0, 'errors' => [$e->getMessage()]];
        }

        $dateCompact = str_replace('-', '', $date);

        foreach ($tasks as $task) {
            if (($task['status'] ?? '') !== 'planned' || ! empty($task['is_published'])) {
                $skipped++;
                continue;
            }

            $taskId     = (string) ($task['id'] ?? '');
            $templateId = (string) ($task['template_id'] ?? '');
            $waiterId   = (string) ($task['assigned_to'] ?? '');
            $rackId     = (string) ($task['rack_id'] ?? '');

            if ($taskId === '' || $templateId === '' || $waiterId === '' || $rackId === '') {
                $errors[] = "Task {$taskId}: missing required fields";
                $skipped++;
                continue;
            }

            // Check lock to prevent duplicates
            $lockPath = 'waiter_task_generation_locks/' . $templateId . '/' . $rackId . '/' . $dateCompact;
            try {
                $lockExists = $this->database->getReference($lockPath)->getSnapshot()->exists();
            } catch (\Throwable $e) {
                $errors[] = "Task {$taskId}: lock check failed — " . $e->getMessage();
                $skipped++;
                continue;
            }

            if ($lockExists) {
                $skipped++;
                continue;
            }

            // Get rack barcode value and rack code
            $rackBarcodeValue = '';
            $rackCode = '';
            $rackName = '';
            try {
                $rackSnapshot = $this->database->getReference('racks/' . $rackId)->getSnapshot();
                if ($rackSnapshot->exists()) {
                    $rackData         = (array) ($rackSnapshot->getValue() ?? []);
                    $rackBarcodeValue = (string) ($rackData['barcode_value'] ?? '');
                    $rackCode         = (string) ($rackData['code'] ?? $rackData['rack_code'] ?? '');
                    $rackName         = (string) ($rackData['name'] ?? $rackData['rack_name'] ?? '');
                }
            } catch (\Throwable $e) {
                \Log::warning('[FirebaseService] publishPlanningForDate: failed to get rack barcode', [
                    'rack_id' => $rackId,
                    'error'   => $e->getMessage(),
                ]);
            }

            // Build deterministic waiter task key
            $nodeKey   = 'waiter_rec_' . substr(hash('sha256', $templateId . '::' . $waiterId . '::' . $date), 0, 32);
            $createdAt = time();

            // Determine deadline_at: end of shift (23:59:59 of the scheduled date)
            $deadlineAt = strtotime($date . ' 23:59:59');
            if ($deadlineAt === false) {
                $deadlineAt = $createdAt;
            }

            $waiterTask = [
                'id'                   => $nodeKey,
                'template_id'          => $templateId,
                'title'                => (string) ($task['title'] ?? ''),
                'description'          => (string) ($task['description'] ?? ''),
                'task_type'            => 'rack_check',
                'assigned_waiter_id'   => $waiterId,
                'assigned_waiter_name' => (string) ($task['assigned_waiter_name'] ?? ''),
                'status'               => 'pending',
                'scheduled_for_date'   => $date,
                'deadline_at'          => $deadlineAt,
                'requires_barcode_scan'=> true,
                'rack_id'              => $rackId,
                'rack_code'            => $rackCode,
                'rack_name'            => $rackName,
                'rack_barcode_value'   => $rackBarcodeValue,
                'priority'             => 'normal',
                'created_at'           => $createdAt,
                'created_by'           => (string) ($task['created_by'] ?? 'system'),
            ];

            // Multi-path atomic write: create waiter task + lock + update planning task
            try {
                $this->database->getReference()->update([
                    '/waiter_tasks/' . $nodeKey => $waiterTask,
                    '/rack_check_planning/' . $taskId . '/status'       => 'pending',
                    '/rack_check_planning/' . $taskId . '/is_published' => true,
                    '/rack_check_planning/' . $taskId . '/updated_at'   => $createdAt,
                    '/rack_check_planning/' . $taskId . '/waiter_task_id' => $nodeKey,
                    '/' . $lockPath => [
                        'created_at'  => $createdAt,
                        'waiter_id'   => $waiterId,
                        'template_id' => $templateId,
                        'rack_id'     => $rackId,
                        'date'        => $date,
                    ],
                ]);
                $published++;
            } catch (\Throwable $e) {
                $errors[] = "Task {$taskId}: write failed — " . $e->getMessage();
            }
        }

        return [
            'published' => $published,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ];
    }


    /**
     * Get a single planning task by ID.
     *
     * @param  string  $taskId
     * @return array|null
     */
    public function getPlanningTaskById(string $taskId): ?array
    {
        try {
            $snapshot = $this->database->getReference('rack_check_planning/' . $taskId)->getSnapshot();
            if (! $snapshot->exists()) {
                return null;
            }

            return array_merge((array) $snapshot->getValue(), ['id' => $taskId]);
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] getPlanningTaskById error', [
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }


    /**
     * Write an audit log entry to /rack_check_planning_logs/{date}/{push_id}.
     *
     * @param  string  $action  Action name, e.g. 'create', 'publish', 'update'
     * @param  array   $data    Additional context data to include in the log
     * @return void
     */
    public function logPlanningAction(string $action, array $data): void
    {
        try {
            $date    = date('Y-m-d');
            $logPath = 'rack_check_planning_logs/' . $date;

            $logEntry = array_merge($data, [
                'action'       => $action,
                'performed_at' => time(),
            ]);

            // performed_by may be passed via $data; keep it if present
            if (! isset($logEntry['performed_by'])) {
                $logEntry['performed_by'] = 'system';
            }

            $this->database->getReference($logPath)->push($logEntry);
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] logPlanningAction error', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }


    /**
     * Remove a generation lock entry.
     */
    public function removePlanningLock(string $templateId, string $rackId, string $date): void
    {
        try {
            $dateCompact = str_replace('-', '', $date);
            $this->database->getReference('waiter_task_generation_locks/' . $templateId . '/' . $rackId . '/' . $dateCompact)->remove();
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] removePlanningLock error', ['error' => $e->getMessage()]);
        }
    }
}
