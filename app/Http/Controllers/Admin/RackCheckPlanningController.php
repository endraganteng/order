<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Services\PlanningFirebaseService;
use App\Services\RackCheckTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk fitur Planning Cek Rak (manual assignment oleh supervisor).
 * Supervisor dapat menetapkan rak ke petugas secara manual untuk 7 hari ke depan,
 * menyimpan draft, dan mempublikasikan planning agar muncul di portal petugas.
 */
class RackCheckPlanningController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase,
        protected PlanningFirebaseService $planning,
        protected RackCheckTemplateService $templateService,
    ) {
    }

    /**
     * GET /admin/rack-check/planning
     * Weekly planner: 7 hari mulai besok.
     * Setiap hari menampilkan jumlah rak due dan jumlah yang sudah di-assign.
     */
    public function index(): \Illuminate\View\View
    {
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();

        $days = [];
        for ($i = 1; $i <= 7; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));

            $dueTemplates = array_values(array_filter($allTemplates, function (array $tpl) use ($date): bool {
                return ($tpl['task_type'] ?? '') === 'rack_check'
                    && ! empty($tpl['is_active'])
                    && $this->firebase->isTemplateDueForDate($tpl, $date);
            }));

            $dueCount = 0;
            foreach ($dueTemplates as $tpl) {
                $racks = $this->firebase->normalizeTemplateRacks($tpl);
                $dueCount += count($racks);
            }

            $planningTasks = $this->planning->getPlanningTasksForDate($date);
            $assignedCount = count(array_filter($planningTasks, fn (array $task): bool => ! empty($task['assigned_to'])));

            $days[] = [
                'date'           => $date,
                'label'          => date('l, d M', strtotime($date)),
                'due_count'      => $dueCount,
                'assigned_count' => $assignedCount,
                'is_complete'    => $dueCount > 0 && $assignedCount >= $dueCount,
            ];
        }

        return view('admin.rack_check.planning.index', compact('days'));
    }

    /**
     * GET /admin/rack-check/planning/daily-detail
     * AJAX: detail planning untuk satu tanggal.
     * Query param: date (Y-m-d)
     */
    public function dailyDetail(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = (string) $request->query('date');

        try {
            $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();

            // Rak yang due pada tanggal ini
            $dueRacks = [];
            foreach ($allTemplates as $tpl) {
                if (($tpl['task_type'] ?? '') !== 'rack_check') {
                    continue;
                }
                if (empty($tpl['is_active'])) {
                    continue;
                }
                if (! $this->firebase->isTemplateDueForDate($tpl, $date)) {
                    continue;
                }
                $racks = $this->firebase->normalizeTemplateRacks($tpl);
                foreach ($racks as $rack) {
                    $dueRacks[] = [
                        'template_id' => (string) ($tpl['id'] ?? ''),
                        'template_name' => (string) ($tpl['name'] ?? $tpl['title'] ?? ''),
                        'rack_id'     => (string) ($rack['id'] ?? ''),
                        'rack_code'   => (string) ($rack['barcode_value'] ?? ''),
                        'rack_name'   => (string) ($rack['name'] ?? ''),
                        'rack_location' => (string) ($rack['location'] ?? ''),
                    ];
                }
            }

            // Existing planning tasks untuk tanggal ini
            $planningTasks = $this->planning->getPlanningTasksForDate($date);

            // Pre-compute task count per waiter dari planning tasks yang sudah ada (skip N+1 query)
            $taskCountByWaiter = [];
            foreach ($planningTasks as $task) {
                $assignedTo = (string) ($task['assigned_to'] ?? '');
                $status = (string) ($task['status'] ?? '');
                if ($assignedTo !== '' && in_array($status, ['planned', 'pending'], true)) {
                    $taskCountByWaiter[$assignedTo] = ($taskCountByWaiter[$assignedTo] ?? 0) + 1;
                }
            }

            // Ketersediaan petugas
            $waiters = $this->firebase->getActiveWaiters();
            $employeeAvailability = [];
            foreach ($waiters as $waiter) {
                $waiterId = (string) ($waiter['id'] ?? '');
                if ($waiterId === '') {
                    continue;
                }

                $isWorking = $this->firebase->isWorkingDay($waiterId, $date);
                $shiftRaw = $this->firebase->getWaiterShiftForDate($waiterId, $date);
                $shiftInfo = $shiftRaw ? ($shiftRaw['name'] ?? '') . ' (' . ($shiftRaw['clock_in_time'] ?? '') . '-' . ($shiftRaw['clock_out_time'] ?? '') . ')' : '';
                $taskCount = $taskCountByWaiter[$waiterId] ?? 0;

                // Ambil daily cap dari template pertama yang due (referensi cap)
                $dailyCap = null;
                if (! empty($dueRacks)) {
                    $firstTemplate = null;
                    foreach ($allTemplates as $tpl) {
                        if ((string) ($tpl['id'] ?? '') === ($dueRacks[0]['template_id'] ?? '')) {
                            $firstTemplate = $tpl;
                            break;
                        }
                    }
                    if ($firstTemplate !== null) {
                        $dailyCap = $this->firebase->getRackCheckDailyCap($waiterId, $date, $firstTemplate);
                    }
                }

                $employeeAvailability[] = [
                    'waiter_id'    => $waiterId,
                    'waiter_name'  => (string) ($waiter['name'] ?? $waiterId),
                    'is_working'   => $isWorking,
                    'shift_info'   => $shiftInfo,
                    'task_count'   => (int) $taskCount,
                    'daily_cap'    => $dailyCap,
                    'can_take_more' => $isWorking && ($dailyCap === null || (int) $taskCount < (int) $dailyCap),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'              => true,
            'date'                 => $date,
            'due_racks'            => $dueRacks,
            'planning_tasks'       => array_values($planningTasks),
            'employee_availability' => $employeeAvailability,
        ]);
    }

    /**
     * POST /admin/rack-check/planning/assign
     * AJAX: assign rak ke petugas untuk tanggal tertentu.
     */
    public function assignRack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id'    => ['required', 'string'],
            'rack_id'        => ['required', 'string'],
            'rack_code'      => ['nullable', 'string'],
            'rack_name'      => ['nullable', 'string'],
            'waiter_id'      => ['required', 'string'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'override'       => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $templateId    = (string) $data['template_id'];
        $rackId        = (string) $data['rack_id'];
        $waiterId      = (string) $data['waiter_id'];
        $scheduledDate = (string) $data['scheduled_date'];
        $isOverride    = (bool) ($data['override'] ?? false);
        $overrideReason = isset($data['override_reason']) ? (string) $data['override_reason'] : null;

        try {
            // Cek petugas bekerja hari itu
            $isWorking = $this->firebase->isWorkingDay($waiterId, $scheduledDate);
            if (! $isWorking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Petugas tidak bekerja pada tanggal tersebut.',
                    'error_code' => 'not_working',
                ], 422);
            }

            // Cek daily cap
            $template = null;
            $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
            foreach ($allTemplates as $tpl) {
                if ((string) ($tpl['id'] ?? '') === $templateId) {
                    $template = $tpl;
                    break;
                }
            }

            if ($template !== null) {
                $dailyCap = $this->firebase->getRackCheckDailyCap($waiterId, $scheduledDate, $template);
                $taskCount = $this->firebase->getWaiterTaskCountForDate($waiterId, $scheduledDate);

                if ($dailyCap !== null && (int) $taskCount >= (int) $dailyCap && ! $isOverride) {
                    return response()->json([
                        'success'    => false,
                        'message'    => "Petugas sudah mencapai batas harian ({$taskCount}/{$dailyCap}). Aktifkan override jika ingin tetap assign.",
                        'error_code' => 'cap_exceeded',
                        'task_count' => (int) $taskCount,
                        'daily_cap'  => (int) $dailyCap,
                    ], 422);
                }
            }

            // Cek duplikat planning task
            $existingTasks = $this->planning->getPlanningTasksForDate($scheduledDate);
            foreach ($existingTasks as $task) {
                if (
                    (string) ($task['template_id'] ?? '') === $templateId
                    && (string) ($task['rack_id'] ?? '') === $rackId
                    && (string) ($task['scheduled_for_date'] ?? '') === $scheduledDate
                ) {
                    // Task sudah ada — update assignment-nya
                    $taskId = (string) ($task['id'] ?? '');
                    $waiter = $this->getWaiterData($waiterId);
                    $updateData = [
                        'assigned_to'           => $waiterId,
                        'assigned_waiter_name'  => (string) ($waiter['name'] ?? $waiterId),
                        'status'                => 'planned',
                        'is_published'          => false,
                        'updated_at'            => time(),
                        'override_reason'       => $isOverride ? $overrideReason : null,
                    ];
                    $this->planning->updatePlanningTask($taskId, $updateData);
                    $this->planning->logPlanningAction('assign_rack', [
                        'task_id'         => $taskId,
                        'template_id'     => $templateId,
                        'rack_id'         => $rackId,
                        'waiter_id'       => $waiterId,
                        'scheduled_date'  => $scheduledDate,
                        'override'        => $isOverride,
                        'override_reason' => $isOverride ? $overrideReason : null,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Assignment berhasil diperbarui.',
                        'task_id' => $taskId,
                        'action'  => 'updated',
                    ]);
                }
            }

            // Buat planning task baru
            $waiter = $this->getWaiterData($waiterId);
            $supervisor = auth()->user();
            $taskData = [
                'template_id'          => $templateId,
                'rack_id'              => $rackId,
                'rack_code'            => (string) ($data['rack_code'] ?? ''),
                'rack_name'            => (string) ($data['rack_name'] ?? ''),
                'scheduled_for_date'   => $scheduledDate,
                'assigned_to'          => $waiterId,
                'assigned_waiter_name' => (string) ($waiter['name'] ?? $waiterId),
                'status'               => 'planned',
                'is_published'         => false,
                'created_at'           => time(),
                'updated_at'           => time(),
                'created_by'           => $supervisor ? ($supervisor->email ?? $supervisor->name ?? 'supervisor') : 'supervisor',
                'override_reason'      => $isOverride ? $overrideReason : null,
            ];

            $savedTask = $this->planning->savePlanningTask($taskData);
            $newTaskId = (string) ($savedTask['id'] ?? '');

            $this->planning->logPlanningAction('assign_rack', [
                'task_id'         => $newTaskId,
                'template_id'     => $templateId,
                'rack_id'         => $rackId,
                'waiter_id'       => $waiterId,
                'scheduled_date'  => $scheduledDate,
                'override'        => $isOverride,
                'override_reason' => $isOverride ? $overrideReason : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign rak: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rak berhasil di-assign.',
            'task_id' => $newTaskId ?? null,
            'action'  => 'created',
        ]);
    }

    /**
     * POST /admin/rack-check/planning/save-draft
     * Simpan planning sebagai draft (status=planned, published=false).
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'  => ['required', 'date_format:Y-m-d'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_id'    => ['required', 'string'],
            'tasks.*.assigned_to' => ['nullable', 'string'],
        ]);

        $date = (string) $data['date'];
        $tasks = (array) $data['tasks'];
        $savedCount = 0;
        $errors = [];

        try {
            foreach ($tasks as $taskInput) {
                $taskId = (string) ($taskInput['task_id'] ?? '');
                if ($taskId === '') {
                    continue;
                }
                try {
                    $this->planning->updatePlanningTask($taskId, [
                        'assigned_to'  => $taskInput['assigned_to'] ?? null,
                        'status'       => 'planned',
                        'is_published' => false,
                        'updated_at'   => time(),
                    ]);
                    $savedCount++;
                } catch (\Throwable $e) {
                    $errors[] = "task {$taskId}: " . $e->getMessage();
                }
            }

            $this->planning->logPlanningAction('save_draft', [
                'date'        => $date,
                'saved_count' => $savedCount,
                'errors'      => $errors,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan draft: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'     => true,
            'message'     => "Draft planning berhasil disimpan ({$savedCount} task).",
            'saved_count' => $savedCount,
            'errors'      => $errors,
        ]);
    }

    /**
     * POST /admin/rack-check/planning/publish
     * Publikasikan planning untuk suatu tanggal.
     * Mengubah status dari 'planned' → 'pending' agar muncul di portal petugas.
     */
    public function publishPlanning(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = (string) $data['date'];

        try {
            $result = $this->planning->publishPlanningForDate($date);

            $publishedCount = (int) ($result['published_count'] ?? 0);
            $skippedCount   = (int) ($result['skipped_count'] ?? 0);

            $this->planning->logPlanningAction('publish_planning', [
                'date'            => $date,
                'published_count' => $publishedCount,
                'skipped_count'   => $skippedCount,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mempublikasikan planning: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'         => true,
            'message'         => "Planning tanggal {$date} berhasil dipublikasikan ({$publishedCount} task aktif).",
            'date'            => $date,
            'published_count' => $publishedCount,
            'skipped_count'   => $skippedCount,
            'summary'         => $result['summary'] ?? [],
        ]);
    }

    /**
     * POST /admin/rack-check/planning/unassign
     * AJAX: hapus assignment dari task planning (set assigned_to=null).
     * Hanya boleh jika task belum done.
     */
    public function unassignRack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'string'],
        ]);

        $taskId = (string) $data['task_id'];

        try {
            // Validasi: task dengan status terminal tidak boleh di-unassign
            $existingTask = $this->planning->getPlanningTaskById($taskId);
            if ($existingTask !== null) {
                $status = (string) ($existingTask['status'] ?? '');
                if (in_array($status, ['done', 'recheck_pending', 'reviewed'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Task sudah dikerjakan/direview. Tidak bisa di-unassign.',
                        'error_code' => 'terminal_status',
                    ], 422);
                }
            }

            $this->planning->updatePlanningTask($taskId, [
                'assigned_to'          => null,
                'assigned_waiter_name' => null,
                'status'               => 'planning_pending',
                'is_published'         => false,
                'updated_at'           => time(),
            ]);

            $this->planning->logPlanningAction('unassign_rack', [
                'task_id' => $taskId,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus assignment: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment berhasil dihapus.',
            'task_id' => $taskId,
        ]);
    }

    /**
     * POST /admin/rack-check/planning/reassign
     * AJAX: pindah assignment ke petugas lain, dengan validasi cap dan hari kerja.
     */
    public function reassignRack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id'         => ['required', 'string'],
            'new_waiter_id'   => ['required', 'string'],
            'override'        => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $taskId         = (string) $data['task_id'];
        $newWaiterId    = (string) $data['new_waiter_id'];
        $isOverride     = (bool) ($data['override'] ?? false);
        $overrideReason = isset($data['override_reason']) ? (string) $data['override_reason'] : null;

        try {
            $task = $this->planning->getPlanningTaskById($taskId);
            if ($task === null) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Task tidak ditemukan.',
                    'error_code' => 'not_found',
                ], 404);
            }

            $status = (string) ($task['status'] ?? '');
            if (in_array($status, ['done', 'recheck_pending', 'reviewed'], true)) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Task sudah dikerjakan/direview. Tidak bisa di-reassign.',
                    'error_code' => 'terminal_status',
                ], 422);
            }

            $scheduledDate = (string) ($task['scheduled_for_date'] ?? '');
            $oldWaiterId   = (string) ($task['assigned_to'] ?? '');

            // Validasi petugas baru bekerja pada tanggal tersebut
            $isWorking = $this->firebase->isWorkingDay($newWaiterId, $scheduledDate);
            if (! $isWorking) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Petugas baru tidak bekerja pada tanggal tersebut.',
                    'error_code' => 'not_working',
                ], 422);
            }

            // Validasi daily cap (kecuali override)
            if (! $isOverride) {
                $templateId   = (string) ($task['template_id'] ?? '');
                $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
                $template     = null;
                foreach ($allTemplates as $tpl) {
                    if ((string) ($tpl['id'] ?? '') === $templateId) {
                        $template = $tpl;
                        break;
                    }
                }

                if ($template !== null) {
                    $dailyCap  = $this->firebase->getRackCheckDailyCap($newWaiterId, $scheduledDate, $template);
                    $taskCount = $this->firebase->getWaiterTaskCountForDate($newWaiterId, $scheduledDate);

                    if ($dailyCap !== null && (int) $taskCount >= (int) $dailyCap) {
                        return response()->json([
                            'success'    => false,
                            'message'    => "Petugas baru sudah mencapai batas harian ({$taskCount}/{$dailyCap}). Aktifkan override jika ingin tetap reassign.",
                            'error_code' => 'cap_exceeded',
                            'task_count' => (int) $taskCount,
                            'daily_cap'  => (int) $dailyCap,
                        ], 422);
                    }
                }
            }

            $newWaiter     = $this->getWaiterData($newWaiterId);
            $newWaiterName = (string) ($newWaiter['name'] ?? $newWaiterId);

            $this->planning->updatePlanningTask($taskId, [
                'assigned_to'          => $newWaiterId,
                'assigned_waiter_name' => $newWaiterName,
                'updated_at'           => time(),
                'override_reason'      => $isOverride ? $overrideReason : null,
            ]);

            // Jika task sudah published, update waiter_task di Firebase
            $isPublished  = ! empty($task['is_published']);
            $waiterTaskId = (string) ($task['waiter_task_id'] ?? '');
            if ($isPublished && $waiterTaskId !== '') {
                $this->firebase->updateWaiterTask($waiterTaskId, [
                    'assigned_waiter_id'   => $newWaiterId,
                    'assigned_waiter_name' => $newWaiterName,
                ]);
            }

            $this->planning->logPlanningAction('reassign_rack', [
                'task_id'       => $taskId,
                'old_waiter_id' => $oldWaiterId,
                'new_waiter_id' => $newWaiterId,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal reassign rak: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment berhasil dipindah ke petugas baru.',
            'task_id' => $taskId,
        ]);
    }

    /**
     * POST /admin/rack-check/planning/reschedule
     * AJAX: pindah task ke tanggal lain (reset ke planning_pending, unpublish).
     */
    public function rescheduleRack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id'  => ['required', 'string'],
            'new_date' => ['required', 'date_format:Y-m-d'],
            'reason'   => ['nullable', 'string', 'max:500'],
        ]);

        $taskId  = (string) $data['task_id'];
        $newDate = (string) $data['new_date'];
        $reason  = isset($data['reason']) ? (string) $data['reason'] : null;

        try {
            $task = $this->planning->getPlanningTaskById($taskId);
            if ($task === null) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Task tidak ditemukan.',
                    'error_code' => 'not_found',
                ], 404);
            }

            $status = (string) ($task['status'] ?? '');
            if (in_array($status, ['done', 'recheck_pending', 'reviewed'], true)) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Task sudah dikerjakan/direview. Tidak bisa dijadwal ulang.',
                    'error_code' => 'terminal_status',
                ], 422);
            }

            $oldDate      = (string) ($task['scheduled_for_date'] ?? '');
            $isPublished  = ! empty($task['is_published']);
            $waiterTaskId = (string) ($task['waiter_task_id'] ?? '');
            $templateId   = (string) ($task['template_id'] ?? '');
            $rackId       = (string) ($task['rack_id'] ?? '');

            $this->planning->updatePlanningTask($taskId, [
                'scheduled_for_date' => $newDate,
                'status'             => 'planning_pending',
                'assigned_to'        => null,
                'is_published'       => false,
                'updated_at'         => time(),
            ]);

            // Hapus waiter_task lama jika sudah published
            if ($isPublished && $waiterTaskId !== '') {
                $this->firebase->removeWaiterTask($waiterTaskId);
            }

            // Hapus lock lama
            if ($templateId !== '' && $rackId !== '' && $oldDate !== '') {
                $this->planning->removePlanningLock($templateId, $rackId, $oldDate);
            }

            $this->planning->logPlanningAction('reschedule_rack', [
                'task_id'  => $taskId,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'reason'   => $reason,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjadwal ulang rak: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'message'  => "Task berhasil dipindah ke tanggal {$newDate}.",
            'task_id'  => $taskId,
            'new_date' => $newDate,
        ]);
    }

    /**
     * POST /admin/rack-check/planning/ignore
     * AJAX: abaikan task dengan alasan wajib.
     */
    public function ignoreRack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'string'],
            'reason'  => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $taskId = (string) $data['task_id'];
        $reason = (string) $data['reason'];

        try {
            $task = $this->planning->getPlanningTaskById($taskId);
            if ($task === null) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Task tidak ditemukan.',
                    'error_code' => 'not_found',
                ], 404);
            }

            $status = (string) ($task['status'] ?? '');
            if (in_array($status, ['done', 'recheck_pending', 'reviewed'], true)) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Task sudah dikerjakan/direview. Tidak bisa diabaikan.',
                    'error_code' => 'terminal_status',
                ], 422);
            }

            $isPublished  = ! empty($task['is_published']);
            $waiterTaskId = (string) ($task['waiter_task_id'] ?? '');

            $this->planning->updatePlanningTask($taskId, [
                'status'        => 'ignored_with_reason',
                'ignore_reason' => $reason,
                'assigned_to'   => null,
                'is_published'  => false,
                'updated_at'    => time(),
            ]);

            // Hapus waiter_task lama jika sudah published
            if ($isPublished && $waiterTaskId !== '') {
                $this->firebase->removeWaiterTask($waiterTaskId);
            }

            $this->planning->logPlanningAction('ignore_rack', [
                'task_id' => $taskId,
                'reason'  => $reason,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengabaikan task: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil diabaikan.',
            'task_id' => $taskId,
        ]);
    }

    /**
     * POST /admin/rack-check/planning/auto-suggest
     * AJAX: distribusikan rak yang belum di-assign ke petugas yang tersedia secara merata.
     * Hanya membuat draft (status=planned, is_published=false), tidak mempublikasikan.
     */
    public function autoSuggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = (string) $data['date'];

        try {
            // Ambil semua rak yang due untuk tanggal ini
            $allDueRacks = $this->firebase->getRacksDueForDateFromTemplates($date);

            if (empty($allDueRacks)) {
                return response()->json([
                    'success'             => true,
                    'assigned'            => 0,
                    'remaining_unassigned' => 0,
                    'message'             => 'Tidak ada rak yang perlu dicek pada tanggal ini.',
                ]);
            }

            // Ambil planning tasks yang sudah ada untuk tanggal ini
            $existingTasks = $this->planning->getPlanningTasksForDate($date);

            // Buat lookup: template_id + rack_id → task (untuk cek duplikat)
            $assignedKeys = [];
            foreach ($existingTasks as $task) {
                $taskTemplateId = (string) ($task['template_id'] ?? '');
                $taskRackId     = (string) ($task['rack_id'] ?? '');
                $taskStatus     = (string) ($task['status'] ?? '');
                $taskAssignedTo = (string) ($task['assigned_to'] ?? '');

                // Rak dianggap sudah di-assign jika ada task dengan assigned_to != null
                // dan status bukan 'ignored_with_reason'
                if (
                    $taskTemplateId !== ''
                    && $taskRackId !== ''
                    && $taskAssignedTo !== ''
                    && $taskStatus !== 'ignored_with_reason'
                ) {
                    $assignedKeys["{$taskTemplateId}|{$taskRackId}"] = true;
                }
            }

            // Filter rak yang belum di-assign
            $unassignedRacks = array_values(array_filter(
                $allDueRacks,
                function (array $rack) use ($assignedKeys): bool {
                    $key = ((string) ($rack['template_id'] ?? '')) . '|' . ((string) ($rack['rack_id'] ?? ''));
                    return ! isset($assignedKeys[$key]);
                }
            ));

            if (empty($unassignedRacks)) {
                return response()->json([
                    'success'             => true,
                    'assigned'            => 0,
                    'remaining_unassigned' => 0,
                    'message'             => 'Semua rak sudah di-assign. Tidak ada yang perlu didistribusikan.',
                ]);
            }

            // Ambil petugas aktif yang bekerja pada tanggal ini
            $allWaiters    = $this->firebase->getActiveWaiters();
            $workingWaiters = [];

            foreach ($allWaiters as $waiter) {
                $waiterId = (string) ($waiter['id'] ?? '');
                if ($waiterId === '') {
                    continue;
                }

                $isWorking = $this->firebase->isWorkingDay($waiterId, $date);
                if (! $isWorking) {
                    continue;
                }

                // Hitung daily cap menggunakan template pertama sebagai referensi
                $dailyCap  = null;
                $firstRack = $unassignedRacks[0] ?? null;
                if ($firstRack !== null) {
                    $dailyCap = $this->firebase->getRackCheckDailyCap($waiterId, $date, $firstRack);
                }

                $taskCount       = (int) $this->firebase->getWaiterTaskCountForDate($waiterId, $date);
                $remainingCapacity = $dailyCap !== null ? max(0, (int) $dailyCap - $taskCount) : PHP_INT_MAX;

                $workingWaiters[] = [
                    'waiter_id'          => $waiterId,
                    'waiter_name'        => (string) ($waiter['name'] ?? $waiterId),
                    'remaining_capacity' => $remainingCapacity,
                ];
            }

            if (empty($workingWaiters)) {
                return response()->json([
                    'success'             => false,
                    'assigned'            => 0,
                    'remaining_unassigned' => count($unassignedRacks),
                    'message'             => 'Tidak ada petugas yang bekerja pada tanggal ini.',
                ], 422);
            }

            // Urutkan berdasarkan sisa kapasitas terbesar (DESC)
            usort($workingWaiters, fn (array $a, array $b): int => $b['remaining_capacity'] <=> $a['remaining_capacity']);

            // Distribusi rak menggunakan greedy: assign ke petugas dengan kapasitas terbesar
            $assignedCount = 0;

            foreach ($unassignedRacks as $rack) {
                // Petugas dengan kapasitas paling besar ada di index 0 setelah sort
                if (empty($workingWaiters) || $workingWaiters[0]['remaining_capacity'] <= 0) {
                    break; // Semua petugas penuh
                }

                $targetWaiter   = $workingWaiters[0];
                $targetWaiterId = $targetWaiter['waiter_id'];
                $targetWaiterName = $targetWaiter['waiter_name'];

                $taskData = [
                    'template_id'          => (string) ($rack['template_id'] ?? ''),
                    'rack_id'              => (string) ($rack['rack_id'] ?? ''),
                    'rack_name'            => (string) ($rack['rack_name'] ?? $rack['rack_id'] ?? ''),
                    'rack_code'            => (string) ($rack['rack_code'] ?? ''),
                    'assigned_to'          => $targetWaiterId,
                    'assigned_waiter_name' => $targetWaiterName,
                    'scheduled_for_date'   => $date,
                    'status'               => 'planned',
                    'is_published'         => false,
                    'created_by'           => 'auto_suggest',
                ];

                $this->planning->savePlanningTask($taskData);
                $assignedCount++;

                // Kurangi kapasitas petugas ini, lalu re-sort
                $workingWaiters[0]['remaining_capacity']--;
                usort($workingWaiters, fn (array $a, array $b): int => $b['remaining_capacity'] <=> $a['remaining_capacity']);
            }

            $remaining = count($unassignedRacks) - $assignedCount;

            $this->planning->logPlanningAction('auto_suggest', [
                'date'                 => $date,
                'assigned_count'       => $assignedCount,
                'remaining_unassigned' => $remaining,
            ]);

            $message = $assignedCount > 0
                ? "Auto Suggest berhasil: {$assignedCount} rak didistribusikan ke petugas."
                : 'Semua petugas sudah mencapai batas harian. Tidak ada rak yang bisa didistribusikan.';

            if ($remaining > 0 && $assignedCount > 0) {
                $message .= " {$remaining} rak belum dapat di-assign karena semua petugas penuh.";
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan Auto Suggest: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'             => true,
            'assigned'            => $assignedCount,
            'remaining_unassigned' => $remaining,
            'message'             => $message,
        ]);
    }

    /**
     * Helper: ambil data waiter by ID dari daftar aktif.
     *
     * @return array<string, mixed>
     */
    private function getWaiterData(string $waiterId): array
    {
        $waiters = $this->firebase->getActiveWaiters();
        foreach ($waiters as $waiter) {
            if ((string) ($waiter['id'] ?? '') === $waiterId) {
                return (array) $waiter;
            }
        }
        return ['id' => $waiterId, 'name' => $waiterId];
    }
}
