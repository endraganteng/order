<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchDestroyRecurringTaskRequest;
use App\Http\Requests\StoreCashierWorkerRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateRecurringTaskRequest;
use App\Services\FirebaseService;
use App\Services\FonnteService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    protected FirebaseService $firebase;

    protected FonnteService $fonnte;

    public function __construct(FirebaseService $firebase, FonnteService $fonnte)
    {
        $this->firebase = $firebase;
        $this->fonnte = $fonnte;
    }

    /**
     * Show general tasks management list.
     */
    public function index(Request $request)
    {
        return $this->indexByScope($request, 'general');
    }

    /**
     * Show dedicated rack-check management list.
     */
    public function rackIndex(Request $request)
    {
        return $this->indexByScope($request, 'rack_check');
    }

    /**
     * Reset all rack-check waiter data (tasks + recurring templates).
     */
    public function rackReset()
    {
        try {
            $result = $this->firebase->resetRackCheckWaiterData();
            $deletedTasks = (int) ($result['deleted_tasks'] ?? 0);
            $deletedTemplates = (int) ($result['deleted_templates'] ?? 0);

            if ($deletedTasks === 0 && $deletedTemplates === 0) {
                return redirect()->route('admin.tasks.rack.index')
                    ->with('success', 'Data cek rak waiter sudah kosong. Tidak ada data yang direset.');
            }

            return redirect()->route('admin.tasks.rack.index')
                ->with('success', "Reset data cek rak berhasil. {$deletedTasks} task dan {$deletedTemplates} template berulang telah dihapus.");
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.tasks.rack.index')
                ->with('error', 'Reset data cek rak gagal diproses. Silakan coba lagi.');
        }
    }

    /**
     * Show create task form.
     */
    public function create(Request $request)
    {
        $waiters = $this->firebase->getActiveWaiters();
        $racks = $this->firebase->getActiveRacks();
        $requestedScope = (string) $request->input('task_scope', 'general');
        $taskScope = $requestedScope === 'rack_check' ? 'rack_check' : 'general';
        $requestedTaskType = $taskScope === 'rack_check' ? 'rack_check' : 'general';
        $backRouteName = $taskScope === 'rack_check'
            ? 'admin.tasks.rack.index'
            : 'admin.tasks.index';

        // Build day-off map for today so admin can see who's off
        $today = date('Y-m-d');
        $waiterDayOffMap = [];
        foreach ($waiters as $waiter) {
            $wId = $waiter['id'] ?? '';
            if ($wId !== '') {
                $waiterDayOffMap[$wId] = !$this->firebase->isWorkingDay($wId, $today);
            }
        }

        return view('admin.tasks.create', compact('waiters', 'racks', 'taskScope', 'backRouteName', 'requestedTaskType', 'waiterDayOffMap'));
    }

    /**
     * Store new task.
     */
    public function store(StoreTaskRequest $request)
    {
        $isRecurring = (bool) $request->boolean('is_recurring');
        $assignmentType = $request->input('assignment_type', 'all');
        $assignedWaiterId = $request->input('assigned_waiter_id');
        $assignedWaiterRole = strtolower(trim((string) $request->input('assigned_waiter_role', '')));
        $roleAssignmentMode = strtolower(trim((string) $request->input('role_assignment_mode', 'all')));
        if (! in_array($roleAssignmentMode, ['all', 'rolling', 'selected'], true)) {
            $roleAssignmentMode = 'all';
        }

        $selectedWaiterIdsInput = $request->input('selected_waiter_ids', []);
        if (! is_array($selectedWaiterIdsInput)) {
            $selectedWaiterIdsInput = explode(',', (string) $selectedWaiterIdsInput);
        }

        $selectedWaiterIds = array_values(array_unique(array_filter(array_map(function ($waiterId) {
            return trim((string) $waiterId);
        }, $selectedWaiterIdsInput), function ($waiterId) {
            return $waiterId !== '';
        })));

        $rackTargetScope = (string) $request->input('rack_target_scope', 'single');
        $requiresPhotoProof = (bool) $request->boolean('requires_photo_proof');
        $requestedScope = (string) $request->input('task_scope', 'general');
        $taskType = $requestedScope === 'rack_check' ? 'rack_check' : 'general';
        $taskTitle = trim((string) $request->input('title', ''));
        $taskDescription = trim((string) $request->input('description', ''));
        $taskPriority = (string) $request->input('priority', 'normal');

        // Parse hybrid fixed rack assignments: { "rackId": "waiterId", ... }
        $fixedRackAssignments = [];
        $fixedRackAssignmentsRaw = trim((string) $request->input('fixed_rack_assignments', ''));
        if ($fixedRackAssignmentsRaw !== '' && $taskType === 'rack_check') {
            $decoded = json_decode($fixedRackAssignmentsRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $rackId => $waiterId) {
                    $rackId = trim((string) $rackId);
                    $waiterId = trim((string) $waiterId);
                    if ($rackId !== '' && $waiterId !== '') {
                        $fixedRackAssignments[$rackId] = $waiterId;
                    }
                }
            }
        }

        if ($assignmentType === 'role' && $taskType !== 'rack_check' && $roleAssignmentMode === 'rolling') {
            $roleAssignmentMode = 'all';
        }

        if ($taskType !== 'rack_check' && $taskTitle === '') {
            return back()
                ->withErrors(['title' => 'Judul tugas wajib diisi untuk tugas umum.'])
                ->withInput();
        }

        if (! in_array($taskPriority, ['urgent', 'normal', 'low'], true)) {
            $taskPriority = 'normal';
        }

        if ($taskType === 'rack_check') {
            $isRecurring = true;
            $taskDescription = '';
            $taskPriority = 'normal';
            $request->merge([
                'is_recurring' => 1,
            ]);
        }

        $redirectRouteName = $this->resolveRouteNameByTaskType($taskType, $requestedScope);

        $taskRackPayloads = [[
            'task_type' => 'general',
            'requires_barcode_scan' => false,
            'requires_photo_proof' => $requiresPhotoProof,
            'rack_target_scope' => null,
            'rack_id' => null,
            'rack_name' => null,
            'rack_location' => null,
            'rack_barcode_value' => null,
        ]];

        if ($taskType === 'rack_check') {
            $activeRacks = $this->firebase->getActiveRacks();
            if (count($activeRacks) === 0) {
                return back()
                    ->withErrors(['rack_ids' => 'Tidak ada rak aktif. Tambahkan/aktifkan rak dulu sebelum membuat tugas cek rak.'])
                    ->withInput();
            }

            $activeRackMap = [];
            foreach ($activeRacks as $activeRack) {
                $activeRackId = trim((string) ($activeRack['id'] ?? ''));
                if ($activeRackId === '') {
                    continue;
                }

                $activeRackMap[$activeRackId] = $activeRack;
            }

            $selectedRackIdsInput = $request->input('rack_ids', []);
            if (! is_array($selectedRackIdsInput)) {
                $selectedRackIdsInput = explode(',', (string) $selectedRackIdsInput);
            }

            $selectedRackIds = array_values(array_unique(array_filter(array_map(function ($rackId) {
                return trim((string) $rackId);
            }, $selectedRackIdsInput), function ($rackId) {
                return $rackId !== '';
            })));

            if (count($selectedRackIds) === 0) {
                $legacyRackId = trim((string) $request->input('rack_id', ''));
                if ($legacyRackId !== '') {
                    $selectedRackIds[] = $legacyRackId;
                }
            }

            if (count($selectedRackIds) === 0 && $request->input('rack_target_scope') === 'all') {
                $selectedRackIds = array_keys($activeRackMap);
            }

            if (count($selectedRackIds) === 0) {
                return back()
                    ->withErrors(['rack_ids' => 'Untuk tugas cek rak, pilih minimal satu rak target.'])
                    ->withInput();
            }

            $invalidRackIds = array_values(array_filter($selectedRackIds, function ($rackId) use ($activeRackMap) {
                return ! isset($activeRackMap[$rackId]);
            }));

            if (count($invalidRackIds) > 0) {
                return back()
                    ->withErrors(['rack_ids' => 'Ada rak yang tidak valid atau nonaktif. Silakan pilih ulang rak target.'])
                    ->withInput();
            }

            $selectedRacks = [];
            foreach ($selectedRackIds as $selectedRackId) {
                $selectedRacks[] = $activeRackMap[$selectedRackId];
            }

            $rackTargetScope = count($selectedRacks) === count($activeRacks) ? 'all' : 'single';

            $taskRackPayloads = array_map(function ($rack) use ($requiresPhotoProof, $rackTargetScope) {
                return [
                    'task_type' => 'rack_check',
                    'requires_barcode_scan' => true,
                    'requires_photo_proof' => $requiresPhotoProof,
                    'rack_target_scope' => $rackTargetScope,
                    'rack_id' => $rack['id'] ?? null,
                    'rack_name' => $rack['name'] ?? null,
                    'rack_location' => $rack['location'] ?? null,
                    'rack_barcode_value' => $rack['barcode_value'] ?? null,
                ];
            }, $selectedRacks);
        }

        if ($assignmentType === 'single' && ! $assignedWaiterId) {
            return back()
                ->withErrors(['assigned_waiter_id' => 'Pilih waiter tujuan untuk delegasi spesifik.'])
                ->withInput();
        }

        $roleWaiters = [];
        $selectedRoleWaiters = [];
        if ($assignmentType === 'single') {
            $targetWaiter = $this->firebase->getWaiterById($assignedWaiterId);
            if (! $targetWaiter || (($targetWaiter['is_active'] ?? true) === false)) {
                return back()
                    ->withErrors(['assigned_waiter_id' => 'Waiter tujuan tidak valid atau nonaktif.'])
                    ->withInput();
            }
        }

        if ($assignmentType === 'role') {
            if (! in_array($assignedWaiterRole, ['kasir', 'pelayan'], true)) {
                return back()
                    ->withErrors(['assigned_waiter_role' => 'Pilih role waiter untuk delegasi berbasis role.'])
                    ->withInput();
            }

            $roleWaiters = $this->firebase->getActiveWaitersByRole($assignedWaiterRole);
            if (count($roleWaiters) === 0) {
                return back()
                    ->withErrors(['assigned_waiter_role' => 'Tidak ada waiter aktif untuk role yang dipilih.'])
                    ->withInput();
            }

            usort($roleWaiters, function ($a, $b) {
                $nameCompare = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                if ($nameCompare !== 0) {
                    return $nameCompare;
                }

                return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
            });

            $modeNeedsSubsetValidation = in_array($roleAssignmentMode, ['selected', 'rolling'], true);
            if ($modeNeedsSubsetValidation) {
                if ($roleAssignmentMode === 'selected' && count($selectedWaiterIds) === 0) {
                    return back()
                        ->withErrors(['selected_waiter_ids' => 'Pilih minimal satu waiter dari role yang dipilih.'])
                        ->withInput();
                }

                $roleWaiterMap = [];
                foreach ($roleWaiters as $roleWaiter) {
                    $roleWaiterId = trim((string) ($roleWaiter['id'] ?? ''));
                    if ($roleWaiterId === '') {
                        continue;
                    }

                    $roleWaiterMap[$roleWaiterId] = $roleWaiter;
                }

                foreach ($selectedWaiterIds as $selectedWaiterId) {
                    if (! isset($roleWaiterMap[$selectedWaiterId])) {
                        return back()
                            ->withErrors(['selected_waiter_ids' => 'Daftar waiter terpilih tidak valid untuk role yang dipilih.'])
                            ->withInput();
                    }

                    $selectedRoleWaiters[] = $roleWaiterMap[$selectedWaiterId];
                }

                usort($selectedRoleWaiters, function ($a, $b) {
                    $nameCompare = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                    if ($nameCompare !== 0) {
                        return $nameCompare;
                    }

                    return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
                });

                if (count($selectedRoleWaiters) > 0) {
                    $roleWaiters = $selectedRoleWaiters;
                }
            }
        }

        $assignmentStrategy = null;
        if ($assignmentType === 'role') {
            if ($roleAssignmentMode === 'rolling') {
                $assignmentStrategy = 'role_round_robin';
            } elseif ($roleAssignmentMode === 'selected') {
                $assignmentStrategy = 'role_selected';
            } else {
                $assignmentStrategy = 'role_all';
            }
        }

        if ($isRecurring && ! $request->filled('schedule_time')) {
            return back()
                ->withErrors(['schedule_time' => 'Jam jadwal wajib diisi untuk task berulang'])
                ->withInput();
        }

        if ($isRecurring && ! $request->filled('time_limit_minutes')) {
            return back()
                ->withErrors(['time_limit_minutes' => 'Batas waktu (menit) wajib diisi untuk task berulang'])
                ->withInput();
        }

        $recurrenceType = $request->input('recurrence_type', 'daily');

        if ($isRecurring && $recurrenceType === 'weekly' && ! $request->filled('weekly_day')) {
            return back()
                ->withErrors(['weekly_day' => 'Hari mingguan wajib dipilih untuk mode mingguan'])
                ->withInput();
        }

        if ($isRecurring && $recurrenceType === 'every_n_days' && ! $request->filled('interval_days')) {
            return back()
                ->withErrors(['interval_days' => 'Jumlah hari wajib diisi untuk mode setiap N hari'])
                ->withInput();
        }

        if ($isRecurring) {
            return $this->storeRecurring(
                $taskRackPayloads,
                $taskType,
                $taskTitle,
                $taskDescription,
                $taskPriority,
                $requiresPhotoProof,
                $rackTargetScope,
                $assignmentType,
                $assignedWaiterId,
                $assignedWaiterRole,
                $roleAssignmentMode,
                $assignmentStrategy,
                $selectedRoleWaiters,
                $fixedRackAssignments,
                $recurrenceType,
                $request,
                $redirectRouteName
            );
        }

        return $this->storeImmediate(
            $taskRackPayloads,
            $taskType,
            $taskTitle,
            $taskDescription,
            $taskPriority,
            $rackTargetScope,
            $assignmentType,
            $assignedWaiterId,
            $assignedWaiterRole,
            $roleAssignmentMode,
            $assignmentStrategy,
            $roleWaiters,
            $selectedRoleWaiters,
            $fixedRackAssignments,
            $redirectRouteName
        );
    }

    /**
     * Delete task.
     */
    public function destroy($id)
    {
        $task = collect($this->firebase->getWaiterTasks())->first(function ($candidate) use ($id) {
            return (string) ($candidate['id'] ?? '') === (string) $id;
        });

        $redirectRouteName = $this->resolveRouteNameByTaskType((string) ($task['task_type'] ?? 'general'));

        $this->firebase->deleteWaiterTask($id);

        return redirect()->route($redirectRouteName)
            ->with('success', 'Tugas berhasil dihapus');
    }

    /**
     * Delete recurring task template.
     */
    public function recurringDestroy($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        $redirectRouteName = $this->resolveRouteNameByTaskType((string) ($template['task_type'] ?? 'general'));

        $this->firebase->deleteRecurringWaiterTaskTemplate($id);

        return redirect()->route($redirectRouteName)
            ->with('success', 'Template task berulang berhasil dihapus');
    }

    /**
     * Batch delete recurring templates by schedule group.
     */
    public function recurringBatchDestroy(BatchDestroyRecurringTaskRequest $request)
    {
        $templateIds = $request->input('template_ids', []);
        $deletedCount = 0;

        foreach ($templateIds as $templateId) {
            $template = $this->firebase->getRecurringWaiterTaskTemplateById($templateId);
            if ($template) {
                $this->firebase->deleteRecurringWaiterTaskTemplate($templateId);
                $deletedCount++;
            }
        }

        $redirectScope = $request->input('redirect_scope', 'rack_check');
        $redirectRouteName = $redirectScope === 'rack_check' ? 'admin.tasks.rack.index' : 'admin.tasks.index';

        return redirect()->route($redirectRouteName)
            ->with('success', "{$deletedCount} template jadwal berulang berhasil dihapus.");
    }

    /**
     * Show edit recurring task template form.
     */
    public function recurringEdit($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);

        if (! $template) {
            abort(404);
        }

        return view('admin.tasks.edit_recurring', compact('template'));
    }

    /**
     * Update recurring task template.
     */
    public function recurringUpdate($id, UpdateRecurringTaskRequest $request)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            abort(404);
        }

        $recurrenceType = (string) $request->input('recurrence_type', 'daily');
        $scheduleTime = (string) $request->input('schedule_time', '');
        $timeLimitMinutes = (int) $request->input('time_limit_minutes', 0);

        if ($recurrenceType === 'weekly' && ! $request->filled('weekly_day')) {
            return back()
                ->withErrors(['weekly_day' => 'Hari mingguan wajib dipilih untuk mode mingguan'])
                ->withInput();
        }

        if ($recurrenceType === 'every_n_days' && ! $request->filled('interval_days')) {
            return back()
                ->withErrors(['interval_days' => 'Jumlah hari wajib diisi untuk mode setiap N hari'])
                ->withInput();
        }

        $this->firebase->updateRecurringWaiterTaskTemplate($id, [
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => $request->priority,
            'schedule_time' => $scheduleTime,
            'time_limit_minutes' => $timeLimitMinutes,
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) $request->weekly_day : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) $request->interval_days : null,
            'reset_anchor_date' => $request->has('reset_anchor_date'),
            'is_active' => $request->has('is_active'),
        ]);

        $redirectRouteName = $this->resolveRouteNameByTaskType((string) ($template['task_type'] ?? 'general'));

        return redirect()->route($redirectRouteName)
            ->with('success', 'Template task berulang berhasil diupdate');
    }

    /**
     * Add cashier worker master data.
     */
    public function cashierStore(StoreCashierWorkerRequest $request)
    {
        $this->firebase->addCashierWorker($request->cashier_name);

        return redirect()->route('admin.tasks.index')
            ->with('success', 'Nama kasir berhasil ditambahkan');
    }

    /**
     * Delete cashier worker master data.
     */
    public function cashierDestroy($id)
    {
        $this->firebase->deleteCashierWorker($id);

        return redirect()->route('admin.tasks.index')
            ->with('success', 'Nama kasir berhasil dihapus');
    }

    // ─── Protected Helpers ───────────────────────────────────────────────

    /**
     * Render tasks index by task scope.
     */
    protected function indexByScope(Request $request, string $taskScope)
    {
        $taskScope = $taskScope === 'rack_check' ? 'rack_check' : 'general';

        $this->firebase->generateDueRecurringWaiterTasks();
        $overdueResult = $this->firebase->markOverdueWaiterTasks();
        $this->sendOverdueNotifications($overdueResult['overdue_tasks'] ?? []);
        $tasks = $this->firebase->getWaiterTasks();
        $recurringTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $waiters = $this->firebase->getActiveWaiters();
        $racks = $this->firebase->getRacks();

        $tasks = array_map(function ($task) {
            $task['tracking_date'] = $this->resolveTrackingDate($task);

            return $task;
        }, $tasks);

        $tasks = array_values(array_filter($tasks, function ($task) use ($taskScope) {
            return $this->matchesTaskScope($task, $taskScope);
        }));

        $recurringTemplates = array_values(array_filter($recurringTemplates, function ($template) use ($taskScope) {
            return $this->matchesTaskScope($template, $taskScope);
        }));

        $taskHistory = $tasks;

        $selectedDate = $request->input('track_date', date('Y-m-d'));
        $dateTasks = array_values(array_filter($tasks, function ($task) use ($selectedDate) {
            return ($task['tracking_date'] ?? '') === $selectedDate;
        }));
        $waiterActivityReports = $this->firebase->getWaiterActivityReportsByDate($selectedDate);
        $waiterActivityBoard = $this->buildWaiterActivityBoard($waiterActivityReports);

        $dateDoneTasks = array_values(array_filter($dateTasks, function ($task) {
            return ($task['status'] ?? '') === 'done';
        }));

        $dateNotDoneTasks = array_values(array_filter($dateTasks, function ($task) {
            return ($task['status'] ?? '') !== 'done';
        }));

        $dateWaiterTrackingBoard = $this->buildWaiterTrackingBoard($dateDoneTasks, $dateNotDoneTasks);

        $rackExecutionBoard = $this->buildRackExecutionBoard($dateTasks);
        $collectedStockBoard = $this->buildCollectedStockBoard($dateTasks);

        $waiterPerformance = $this->buildWaiterPerformance($tasks);

        $taskScopeRouteName = $taskScope === 'rack_check'
            ? 'admin.tasks.rack.index'
            : 'admin.tasks.index';

        $otherTaskScopeRouteName = $taskScope === 'rack_check'
            ? 'admin.tasks.index'
            : 'admin.tasks.rack.index';

        $taskScopeLabel = $taskScope === 'rack_check'
            ? 'Cek Rak'
            : 'Tugas Umum';

        $otherTaskScopeLabel = $taskScope === 'rack_check'
            ? 'Tugas Umum'
            : 'Cek Rak';

        // ── Pre-computed KPI & aggregation data ──
        $isRackScope = $taskScope === 'rack_check';
        $tasksCollection = collect($tasks);
        $kpi = [
            'pending' => $tasksCollection->where('status', 'pending')->count(),
            'done' => $tasksCollection->where('status', 'done')->count(),
            'overdue' => $tasksCollection->where('status', 'overdue')->count(),
            'activeRackCount' => collect($racks)->filter(fn ($rack) => ($rack['is_active'] ?? true) === true)->count(),
        ];

        $activityTotalReports = (int) ($waiterActivityBoard['total_reports'] ?? 0);
        $activityWaiterCount = (int) ($waiterActivityBoard['waiter_count'] ?? 0);
        $activityWaiters = $waiterActivityBoard['waiters'] ?? [];
        $collectedTotalReports = (int) ($collectedStockBoard['total_reports'] ?? 0);
        $collectedTotalMentions = (int) ($collectedStockBoard['total_item_mentions'] ?? 0);
        $collectedRacks = $collectedStockBoard['racks'] ?? [];
        $collectedTopItems = $collectedStockBoard['top_items'] ?? [];
        $dateNotDoneCount = count($dateNotDoneTasks);
        $rackNotDoneTotal = collect($rackExecutionBoard)->sum(fn ($board) => (int) ($board['not_done_count'] ?? 0));
        $rackDoneTotal = collect($rackExecutionBoard)->sum(fn ($board) => (int) ($board['done_count'] ?? 0));
        $recurringDailyCount = collect($recurringTemplates)->filter(fn ($t) => ($t['recurrence_type'] ?? 'daily') === 'daily')->count();
        $recurringSingleDelegateCount = collect($recurringTemplates)->filter(fn ($t) => ($t['assignment_type'] ?? 'all') === 'single')->count();
        $recurringPhotoRequiredCount = collect($recurringTemplates)->filter(fn ($t) => !empty($t['requires_photo_proof']))->count();

        $weeklyNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

        // ── Grouped recurring templates for rack_check scope ──
        $recurringGroupedRackTemplates = collect();
        if ($isRackScope) {
            $recurringGroupedRackTemplates = collect($recurringTemplates)
                ->groupBy(function ($template) {
                    return implode('|', [
                        (string) ($template['recurrence_type'] ?? 'daily'),
                        (string) ($template['weekly_day'] ?? ''),
                        (string) ($template['interval_days'] ?? ''),
                        (string) ($template['schedule_time'] ?? ''),
                        (string) ($template['time_limit_minutes'] ?? ''),
                        (string) ($template['assignment_type'] ?? 'all'),
                        (string) ($template['assigned_waiter_role'] ?? ''),
                        (string) ($template['assignment_strategy'] ?? ''),
                        !empty($template['requires_photo_proof']) ? '1' : '0',
                    ]);
                })
                ->map(function ($group, $groupKey) use ($weeklyNames) {
                    $first = $group->first();
                    $recurrenceType = (string) ($first['recurrence_type'] ?? 'daily');
                    $typeLabel = $recurrenceType === 'weekly'
                        ? ('Mingguan ' . ($weeklyNames[(int) ($first['weekly_day'] ?? 0)] ?? '-'))
                        : ($recurrenceType === 'every_n_days'
                            ? ('Setiap ' . ($first['interval_days'] ?? '-') . ' hari')
                            : 'Harian');

                    $rackNames = $group->pluck('rack_name')->filter()->unique()->values()->all();
                    $assignedRole = ucfirst((string) ($first['assigned_waiter_role'] ?? 'pelayan'));
                    $delegateLabel = (string) ($first['assignment_type'] ?? 'all') === 'single'
                        ? ('Single: ' . ((string) ($first['assigned_waiter_name'] ?? '-') !== '' ? (string) ($first['assigned_waiter_name'] ?? '-') : '-'))
                        : ((string) ($first['assignment_type'] ?? 'all') === 'role'
                            ? ('Role: ' . $assignedRole)
                            : 'Semua Waiter');

                    $searchText = strtolower(trim(implode(' ', [
                        (string) $typeLabel,
                        (string) ($first['schedule_time'] ?? ''),
                        (string) ($first['time_limit_minutes'] ?? ''),
                        (string) $delegateLabel,
                        implode(' ', $rackNames),
                    ])));

                    return [
                        'group_key' => $groupKey,
                        'first' => $first,
                        'type_label' => $typeLabel,
                        'delegate_label' => $delegateLabel,
                        'template_count' => $group->count(),
                        'rack_count' => count($rackNames),
                        'rack_names' => $rackNames,
                        'templates' => $group->values()->all(),
                        'search_text' => $searchText,
                    ];
                })
                ->sortBy(function ($group) {
                    $first = $group['first'] ?? [];
                    return sprintf('%s|%s|%s',
                        (string) ($first['schedule_time'] ?? ''),
                        (string) ($first['recurrence_type'] ?? ''),
                        (string) ($group['delegate_label'] ?? '')
                    );
                })
                ->values();
        }
        $recurringDisplayCount = $isRackScope ? $recurringGroupedRackTemplates->count() : count($recurringTemplates);

        // ── History data pre-grouped by waiter ──
        $historyWaiterNames = collect($taskHistory)->pluck('assigned_waiter_name')->filter()->unique()->sort()->values();
        $historyRackNames = collect($taskHistory)->pluck('rack_name')->filter()->unique()->sort()->values();
        $historyByWaiter = collect($taskHistory)->groupBy(function ($t) {
            return $t['assigned_waiter_name'] ?? 'Tidak Diketahui';
        })->sortKeys();

        return view('admin.tasks.index', compact(
            'tasks',
            'recurringTemplates',
            'waiters',
            'taskHistory',
            'selectedDate',
            'dateTasks',
            'dateDoneTasks',
            'dateNotDoneTasks',
            'dateWaiterTrackingBoard',
            'waiterActivityReports',
            'waiterActivityBoard',
            'racks',
            'rackExecutionBoard',
            'collectedStockBoard',
            'waiterPerformance',
            'taskScope',
            'taskScopeRouteName',
            'otherTaskScopeRouteName',
            'taskScopeLabel',
            'otherTaskScopeLabel',
            'isRackScope',
            'kpi',
            'activityTotalReports',
            'activityWaiterCount',
            'activityWaiters',
            'collectedTotalReports',
            'collectedTotalMentions',
            'collectedRacks',
            'collectedTopItems',
            'dateNotDoneCount',
            'rackNotDoneTotal',
            'rackDoneTotal',
            'recurringDailyCount',
            'recurringSingleDelegateCount',
            'recurringPhotoRequiredCount',
            'weeklyNames',
            'recurringGroupedRackTemplates',
            'recurringDisplayCount',
            'historyWaiterNames',
            'historyRackNames',
            'historyByWaiter'
        ));
    }

    /**
     * Store recurring task templates.
     */
    protected function storeRecurring(
        array $taskRackPayloads,
        string $taskType,
        string $taskTitle,
        string $taskDescription,
        string $taskPriority,
        bool $requiresPhotoProof,
        string $rackTargetScope,
        string $assignmentType,
        ?string $assignedWaiterId,
        string $assignedWaiterRole,
        string $roleAssignmentMode,
        ?string $assignmentStrategy,
        array $selectedRoleWaiters,
        array $fixedRackAssignments,
        string $recurrenceType,
        Request $request,
        string $redirectRouteName
    ) {
        $templateCount = 0;
        $fixedCount = 0;
        $rollingCount = 0;
        $isRoleRollingRack = $taskType === 'rack_check'
            && $assignmentType === 'role'
            && $roleAssignmentMode === 'rolling';
        $hasHybridFixedAssignments = $taskType === 'rack_check' && count($fixedRackAssignments) > 0;

        $rollingSlotCounter = 0;

        foreach ($taskRackPayloads as $rackIndex => $taskRackPayload) {
            $resolvedTaskTitle = $taskType === 'rack_check'
                ? $this->buildRackCheckTaskTitle($taskRackPayload)
                : $taskTitle;

            $currentRackId = (string) ($taskRackPayload['rack_id'] ?? '');
            $isFixedRack = $hasHybridFixedAssignments && isset($fixedRackAssignments[$currentRackId]);

            if ($isFixedRack) {
                $templateAssignmentType = 'single';
                $templateAssignedWaiterId = $fixedRackAssignments[$currentRackId];
                $rollingSlotIndex = null;
                $templateAssignmentStrategy = null;
                $templateAssignedWaiterRole = null;
                $templateSelectedWaiterIds = [];
                $fixedCount++;
            } elseif ($isRoleRollingRack || ($hasHybridFixedAssignments && $assignmentType === 'role')) {
                $templateAssignmentType = 'role';
                $templateAssignedWaiterId = null;
                $rollingSlotIndex = $rollingSlotCounter;
                $rollingSlotCounter++;
                $templateAssignmentStrategy = 'role_round_robin';
                $templateAssignedWaiterRole = $assignedWaiterRole ?: 'pelayan';
                $templateSelectedWaiterIds = count($selectedRoleWaiters) > 0
                    ? array_values(array_map(function ($waiter) {
                        return (string) ($waiter['id'] ?? '');
                    }, $selectedRoleWaiters))
                    : [];
                $rollingCount++;
            } else {
                $templateAssignmentType = $assignmentType;
                $templateAssignedWaiterId = $assignmentType === 'single' ? $assignedWaiterId : null;
                $rollingSlotIndex = null;
                $templateAssignmentStrategy = $assignmentStrategy;
                $templateAssignedWaiterRole = $assignmentType === 'role' ? $assignedWaiterRole : null;
                $templateSelectedWaiterIds = $assignmentType === 'role' && count($selectedRoleWaiters) > 0
                    ? array_values(array_map(function ($waiter) {
                        return (string) ($waiter['id'] ?? '');
                    }, $selectedRoleWaiters))
                    : [];
            }

            $this->firebase->createRecurringWaiterTaskTemplate([
                'title' => $resolvedTaskTitle,
                'description' => $taskDescription,
                'priority' => $taskPriority,
                'assigned_by' => 'Supervisor',
                'task_type' => $taskRackPayload['task_type'],
                'requires_barcode_scan' => $taskRackPayload['requires_barcode_scan'],
                'requires_photo_proof' => $taskRackPayload['requires_photo_proof'],
                'rack_target_scope' => $taskRackPayload['rack_target_scope'],
                'rack_id' => $taskRackPayload['rack_id'],
                'rack_name' => $taskRackPayload['rack_name'],
                'rack_location' => $taskRackPayload['rack_location'],
                'rack_barcode_value' => $taskRackPayload['rack_barcode_value'],
                'assignment_type' => $templateAssignmentType,
                'assignment_strategy' => $templateAssignmentStrategy,
                'assigned_waiter_id' => $templateAssignedWaiterId,
                'assigned_waiter_role' => $templateAssignedWaiterRole,
                'selected_waiter_ids' => $templateSelectedWaiterIds,
                'rolling_slot_index' => $rollingSlotIndex,
                'schedule_time' => $request->schedule_time,
                'time_limit_minutes' => (int) $request->time_limit_minutes,
                'recurrence_type' => $recurrenceType,
                'weekly_day' => $recurrenceType === 'weekly' ? (int) $request->weekly_day : null,
                'interval_days' => $recurrenceType === 'every_n_days' ? (int) $request->interval_days : null,
            ]);
            $templateCount++;
        }

        // Hybrid fixed+rolling success message
        if ($taskType === 'rack_check' && $hasHybridFixedAssignments && $rollingCount > 0) {
            return redirect()->route($redirectRouteName)
                ->with('success', "Template cek rak berulang berhasil dibuat: {$fixedCount} rak tetap (fixed) + {$rollingCount} rak rotasi harian (rolling) dari total {$templateCount} rak.");
        }

        if ($taskType === 'rack_check' && $hasHybridFixedAssignments && $fixedCount > 0 && $rollingCount === 0) {
            return redirect()->route($redirectRouteName)
                ->with('success', "Template cek rak berulang berhasil dibuat: {$fixedCount} rak ditugaskan tetap ke waiter tertentu.");
        }

        if ($taskType === 'rack_check' && $rackTargetScope === 'all') {
            return redirect()->route($redirectRouteName)
                ->with('success', "Template task cek rak berulang berhasil dibuat untuk semua rak aktif ({$templateCount} rak). Waiter akan wajib scan QR code tiap rak saat eksekusi.");
        }

        if ($taskType === 'rack_check') {
            if ($assignmentType === 'role' && $roleAssignmentMode === 'rolling') {
                $selectedCount = count($selectedRoleWaiters);
                if ($selectedCount > 0) {
                    return redirect()->route($redirectRouteName)
                        ->with('success', "Template task cek rak berulang berhasil dibuat dengan rotasi harian otomatis untuk {$selectedCount} waiter terpilih (role {$assignedWaiterRole}) pada {$templateCount} rak.");
                }

                return redirect()->route($redirectRouteName)
                    ->with('success', "Template task cek rak berulang berhasil dibuat dengan rotasi harian otomatis berdasarkan role {$assignedWaiterRole} untuk {$templateCount} rak.");
            }

            if ($assignmentType === 'role' && $roleAssignmentMode === 'selected') {
                $selectedCount = count($selectedRoleWaiters);

                return redirect()->route($redirectRouteName)
                    ->with('success', "Template task cek rak berulang berhasil dibuat untuk {$selectedCount} waiter terpilih (role {$assignedWaiterRole}) pada {$templateCount} rak.");
            }

            if ($assignmentType === 'role') {
                return redirect()->route($redirectRouteName)
                    ->with('success', "Template task cek rak berulang berhasil dibuat untuk role {$assignedWaiterRole} pada {$templateCount} rak.");
            }

            return redirect()->route($redirectRouteName)
                ->with('success', "Template task cek rak berulang berhasil dibuat untuk {$templateCount} rak terpilih. Waiter akan wajib scan QR code tiap rak saat eksekusi.");
        }

        if ($assignmentType === 'role') {
            if ($roleAssignmentMode === 'selected') {
                $selectedCount = count($selectedRoleWaiters);

                return redirect()->route($redirectRouteName)
                    ->with('success', "Task berulang waiter berhasil dibuat untuk {$selectedCount} waiter terpilih (role {$assignedWaiterRole}).");
            }

            return redirect()->route($redirectRouteName)
                ->with('success', "Task berulang waiter berhasil dibuat untuk role {$assignedWaiterRole}.");
        }

        return redirect()->route($redirectRouteName)
            ->with('success', 'Task berulang waiter berhasil dibuat dengan pola jadwal yang dipilih.');
    }

    /**
     * Store immediate (non-recurring) tasks.
     */
    protected function storeImmediate(
        array $taskRackPayloads,
        string $taskType,
        string $taskTitle,
        string $taskDescription,
        string $taskPriority,
        string $rackTargetScope,
        string $assignmentType,
        ?string $assignedWaiterId,
        string $assignedWaiterRole,
        string $roleAssignmentMode,
        ?string $assignmentStrategy,
        array $roleWaiters,
        array $selectedRoleWaiters,
        array $fixedRackAssignments,
        string $redirectRouteName
    ) {
        $createdCount = 0;
        $allCreatedEntries = [];
        $isRoleRollingRack = $taskType === 'rack_check'
            && $assignmentType === 'role'
            && $roleAssignmentMode === 'rolling';
        $hasHybridFixedImmediate = $taskType === 'rack_check' && count($fixedRackAssignments) > 0;
        $immediateRollingSlotCounter = 0;

        foreach ($taskRackPayloads as $rackIndex => $taskRackPayload) {
            $resolvedTaskTitle = $taskType === 'rack_check'
                ? $this->buildRackCheckTaskTitle($taskRackPayload)
                : $taskTitle;

            $currentRackId = (string) ($taskRackPayload['rack_id'] ?? '');
            $isFixedRack = $hasHybridFixedImmediate && isset($fixedRackAssignments[$currentRackId]);

            if ($isFixedRack) {
                $taskAssignmentType = 'single';
                $taskAssignedWaiterId = $fixedRackAssignments[$currentRackId];
            } elseif ($isRoleRollingRack || ($hasHybridFixedImmediate && $assignmentType === 'role')) {
                $rollingWaiters = count($selectedRoleWaiters) > 0 ? $selectedRoleWaiters : $roleWaiters;
                if (count($rollingWaiters) > 0) {
                    $targetWaiter = $rollingWaiters[$immediateRollingSlotCounter % count($rollingWaiters)];
                    $taskAssignmentType = 'single';
                    $taskAssignedWaiterId = $targetWaiter['id'] ?? null;
                    $immediateRollingSlotCounter++;
                } else {
                    $taskAssignmentType = $assignmentType;
                    $taskAssignedWaiterId = $assignmentType === 'single' ? $assignedWaiterId : null;
                }
            } else {
                $taskAssignmentType = $assignmentType;
                $taskAssignedWaiterId = $assignmentType === 'single' ? $assignedWaiterId : null;
            }

            $result = $this->firebase->createWaiterTasksFromAssignment([
                'title' => $resolvedTaskTitle,
                'description' => $taskDescription,
                'priority' => $taskPriority,
                'assigned_by' => 'Supervisor',
                'task_type' => $taskRackPayload['task_type'],
                'requires_barcode_scan' => $taskRackPayload['requires_barcode_scan'],
                'requires_photo_proof' => $taskRackPayload['requires_photo_proof'],
                'rack_target_scope' => $taskRackPayload['rack_target_scope'],
                'rack_id' => $taskRackPayload['rack_id'],
                'rack_name' => $taskRackPayload['rack_name'],
                'rack_location' => $taskRackPayload['rack_location'],
                'rack_barcode_value' => $taskRackPayload['rack_barcode_value'],
                'assignment_type' => $taskAssignmentType,
                'assignment_strategy' => $assignmentStrategy,
                'assigned_waiter_id' => $taskAssignedWaiterId,
                'assigned_waiter_role' => $assignmentType === 'role' ? $assignedWaiterRole : null,
                'selected_waiter_ids' => $assignmentType === 'role' && count($selectedRoleWaiters) > 0
                    ? array_values(array_map(function ($waiter) {
                        return (string) ($waiter['id'] ?? '');
                    }, $selectedRoleWaiters))
                    : [],
            ]);
            $createdCount += $result['count'];
            $allCreatedEntries = array_merge($allCreatedEntries, $result['entries']);
        }

        // Send WhatsApp notifications for newly created tasks (fire-and-forget)
        try {
            foreach ($allCreatedEntries as $entry) {
                $this->fonnte->notifyTaskAssigned($entry['waiter'], $entry['task']);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($createdCount <= 0) {
            return redirect()->route($redirectRouteName)
                ->with('error', 'Tidak ada waiter aktif yang bisa menerima tugas ini.');
        }

        if ($taskType === 'rack_check' && $rackTargetScope === 'all') {
            $rackCount = count($taskRackPayloads);

            return redirect()->route($redirectRouteName)
                ->with('success', "Tugas cek rak berhasil dibuat untuk semua rak aktif ({$rackCount} rak) dengan total {$createdCount} delegasi waiter. Waiter harus scan QR code setiap rak melalui task masing-masing.");
        }

        if ($taskType === 'rack_check') {
            $rackCount = count($taskRackPayloads);

            if ($assignmentType === 'role' && $roleAssignmentMode === 'rolling') {
                $selectedCount = count($selectedRoleWaiters);
                if ($selectedCount > 0) {
                    return redirect()->route($redirectRouteName)
                        ->with('success', "Tugas cek rak berhasil di-rolling untuk {$selectedCount} waiter terpilih (role {$assignedWaiterRole}) pada {$rackCount} rak (total {$createdCount} delegasi).");
                }

                return redirect()->route($redirectRouteName)
                    ->with('success', "Tugas cek rak berhasil di-rolling berdasarkan role {$assignedWaiterRole} untuk {$rackCount} rak (total {$createdCount} delegasi).");
            }

            if ($assignmentType === 'role') {
                if ($roleAssignmentMode === 'selected') {
                    $selectedCount = count($selectedRoleWaiters);

                    return redirect()->route($redirectRouteName)
                        ->with('success', "Tugas cek rak berhasil dibuat untuk {$selectedCount} waiter terpilih (role {$assignedWaiterRole}) pada {$rackCount} rak (total {$createdCount} delegasi).");
                }

                return redirect()->route($redirectRouteName)
                    ->with('success', "Tugas cek rak berhasil dibuat untuk role {$assignedWaiterRole} pada {$rackCount} rak (total {$createdCount} delegasi).");
            }

            return redirect()->route($redirectRouteName)
                ->with('success', "Tugas cek rak berhasil dibuat untuk {$rackCount} rak terpilih dengan total {$createdCount} delegasi waiter.");
        }

        if ($assignmentType === 'role') {
            if ($roleAssignmentMode === 'selected') {
                $selectedCount = count($selectedRoleWaiters);

                return redirect()->route($redirectRouteName)
                    ->with('success', "Tugas berhasil dibuat dan didelegasikan ke {$selectedCount} waiter terpilih (role {$assignedWaiterRole}) dengan total {$createdCount} task.");
            }

            return redirect()->route($redirectRouteName)
                ->with('success', "Tugas berhasil dibuat dan didelegasikan ke role {$assignedWaiterRole} (total {$createdCount} task).");
        }

        return redirect()->route($redirectRouteName)
            ->with('success', "Tugas berhasil dibuat dan didelegasikan ke {$createdCount} waiter.");
    }

    /**
     * Send WhatsApp notifications for newly overdue tasks.
     */
    protected function sendOverdueNotifications(array $overdueTasks): void
    {
        if (empty($overdueTasks)) {
            return;
        }

        try {
            foreach ($overdueTasks as $task) {
                $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
                if ($waiterId === '') {
                    continue;
                }

                $waiter = $this->firebase->getWaiterById($waiterId);
                if (! $waiter) {
                    continue;
                }

                $this->fonnte->notifyTaskOverdue($waiter, $task);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Check whether task/template belongs to selected management scope.
     */
    protected function matchesTaskScope(array $item, string $taskScope): bool
    {
        $type = (string) ($item['task_type'] ?? 'general');
        if ($taskScope === 'rack_check') {
            return $type === 'rack_check';
        }

        return $type !== 'rack_check';
    }

    /**
     * Resolve destination route based on task scope.
     */
    protected function resolveRouteNameByTaskType(string $taskType, string $fallbackScope = ''): string
    {
        if ($taskType === 'rack_check') {
            return 'admin.tasks.rack.index';
        }

        if ($fallbackScope === 'rack_check') {
            return 'admin.tasks.rack.index';
        }

        return 'admin.tasks.index';
    }

    /**
     * Rack-check tasks always use rack name as title.
     */
    protected function buildRackCheckTaskTitle(array $taskRackPayload): string
    {
        $rackName = trim((string) ($taskRackPayload['rack_name'] ?? ''));
        if ($rackName !== '') {
            return $rackName;
        }

        $rackLocation = trim((string) ($taskRackPayload['rack_location'] ?? ''));
        if ($rackLocation !== '') {
            return 'Rak '.$rackLocation;
        }

        return 'Rak Tanpa Nama';
    }

    /**
     * Resolve task tracking date (for reporting).
     */
    protected function resolveTrackingDate($task)
    {
        if (! empty($task['scheduled_for_date'])) {
            return $task['scheduled_for_date'];
        }

        if (! empty($task['created_at'])) {
            return date('Y-m-d', (int) $task['created_at']);
        }

        return date('Y-m-d');
    }

    /**
     * Build waiter performance ranking from completed tasks.
     */
    protected function buildWaiterPerformance(array $tasks): array
    {
        $stats = [];

        foreach ($tasks as $task) {
            if (($task['status'] ?? '') !== 'done') {
                continue;
            }

            $waiterName = trim((string) ($task['completed_by_waiter_name'] ?? ''));
            if ($waiterName === '') {
                continue;
            }

            if (! isset($stats[$waiterName])) {
                $stats[$waiterName] = [
                    'name' => $waiterName,
                    'done_count' => 0,
                    'last_done_at' => 0,
                ];
            }

            $stats[$waiterName]['done_count']++;

            $completedAt = (int) ($task['completed_at'] ?? 0);
            if ($completedAt > $stats[$waiterName]['last_done_at']) {
                $stats[$waiterName]['last_done_at'] = $completedAt;
            }
        }

        $result = array_values($stats);
        usort($result, function ($a, $b) {
            if ($b['done_count'] === $a['done_count']) {
                return $b['last_done_at'] <=> $a['last_done_at'];
            }

            return $b['done_count'] <=> $a['done_count'];
        });

        return $result;
    }

    /**
     * Build waiter-centric tracking board for selected date.
     */
    protected function buildWaiterTrackingBoard(array $doneTasks, array $notDoneTasks): array
    {
        $board = [];

        $upsertWaiter = function (string $waiterName, string $waiterId) use (&$board): string {
            $normalizedName = trim($waiterName);
            $normalizedId = trim($waiterId);

            $key = '';
            if ($normalizedId !== '') {
                $key = 'id:'.$normalizedId;
            } elseif ($normalizedName !== '') {
                $key = 'name:'.strtolower($normalizedName);
            } else {
                $key = 'unknown';
            }

            if (! isset($board[$key])) {
                $board[$key] = [
                    'waiter_key' => $key,
                    'waiter_id' => $normalizedId,
                    'waiter_name' => $normalizedName !== '' ? $normalizedName : 'Waiter Tidak Diketahui',
                    'done_tasks' => [],
                    'not_done_tasks' => [],
                    'done_count' => 0,
                    'not_done_count' => 0,
                    'total_count' => 0,
                ];
            }

            if ($board[$key]['waiter_name'] === 'Waiter Tidak Diketahui' && $normalizedName !== '') {
                $board[$key]['waiter_name'] = $normalizedName;
            }

            if ($board[$key]['waiter_id'] === '' && $normalizedId !== '') {
                $board[$key]['waiter_id'] = $normalizedId;
            }

            return $key;
        };

        foreach ($doneTasks as $task) {
            $key = $upsertWaiter(
                (string) ($task['completed_by_waiter_name'] ?? ''),
                (string) ($task['completed_by_waiter_id'] ?? '')
            );

            $board[$key]['done_tasks'][] = $task;
            $board[$key]['done_count']++;
            $board[$key]['total_count']++;
        }

        foreach ($notDoneTasks as $task) {
            $key = $upsertWaiter(
                (string) ($task['assigned_waiter_name'] ?? ''),
                (string) ($task['assigned_waiter_id'] ?? '')
            );

            $board[$key]['not_done_tasks'][] = $task;
            $board[$key]['not_done_count']++;
            $board[$key]['total_count']++;
        }

        $result = array_values($board);
        usort($result, function ($a, $b) {
            $totalCompare = (int) ($b['total_count'] ?? 0) <=> (int) ($a['total_count'] ?? 0);
            if ($totalCompare !== 0) {
                return $totalCompare;
            }

            return strcmp(
                strtolower((string) ($a['waiter_name'] ?? '')),
                strtolower((string) ($b['waiter_name'] ?? ''))
            );
        });

        return $result;
    }

    /**
     * Build supervisor board for waiter daily activity reports.
     */
    protected function buildWaiterActivityBoard(array $reports): array
    {
        $grouped = [];

        foreach ($reports as $report) {
            $waiterId = (string) ($report['waiter_id'] ?? '');
            $key = $waiterId !== '' ? $waiterId : 'unknown::'.md5((string) ($report['waiter_name'] ?? ''));

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'waiter_id' => $waiterId,
                    'waiter_name' => (string) ($report['waiter_name'] ?? 'Waiter'),
                    'waiter_email' => (string) ($report['waiter_email'] ?? ''),
                    'report_count' => 0,
                    'latest_created_at' => 0,
                    'reports' => [],
                ];
            }

            $createdAt = (int) ($report['created_at'] ?? 0);
            $grouped[$key]['report_count']++;
            if ($createdAt > $grouped[$key]['latest_created_at']) {
                $grouped[$key]['latest_created_at'] = $createdAt;
            }

            $grouped[$key]['reports'][] = [
                'id' => (string) ($report['id'] ?? ''),
                'activity_text' => (string) ($report['activity_text'] ?? ''),
                'activity_items' => is_array($report['activity_items'] ?? null) ? $report['activity_items'] : [],
                'created_at' => $createdAt,
                'report_date' => (string) ($report['report_date'] ?? ''),
            ];
        }

        $waiters = array_values($grouped);
        foreach ($waiters as &$waiter) {
            usort($waiter['reports'], function ($a, $b) {
                return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
            });
        }
        unset($waiter);

        usort($waiters, function ($a, $b) {
            if (($b['report_count'] ?? 0) === ($a['report_count'] ?? 0)) {
                return ((int) ($b['latest_created_at'] ?? 0)) <=> ((int) ($a['latest_created_at'] ?? 0));
            }

            return ($b['report_count'] ?? 0) <=> ($a['report_count'] ?? 0);
        });

        return [
            'total_reports' => count($reports),
            'waiter_count' => count($waiters),
            'waiters' => $waiters,
        ];
    }

    /**
     * Build dedicated board for collected depleted-stock reports.
     */
    protected function buildCollectedStockBoard(array $dateTasks): array
    {
        $rackGroups = [];
        $globalItems = [];
        $totalReports = 0;
        $totalItemMentions = 0;

        foreach ($dateTasks as $task) {
            if ((string) ($task['task_type'] ?? 'general') !== 'rack_check') {
                continue;
            }

            if ((string) ($task['status'] ?? '') !== 'done') {
                continue;
            }

            if ((bool) ($task['completed_no_out_of_stock'] ?? false) === true) {
                continue;
            }

            $items = $this->normalizeStockReportItems($task);
            if (count($items) === 0) {
                continue;
            }

            $totalReports++;
            $totalItemMentions += count($items);

            $rackId = (string) ($task['rack_id'] ?? '');
            $rackCode = (string) ($task['rack_barcode_value'] ?? '');
            $rackKey = $rackId !== '' ? $rackId : 'barcode::'.$rackCode;

            if (! isset($rackGroups[$rackKey])) {
                $rackGroups[$rackKey] = [
                    'rack_id' => $rackId,
                    'rack_name' => (string) ($task['rack_name'] ?? 'Rak Tidak Diketahui'),
                    'rack_location' => (string) ($task['rack_location'] ?? '-'),
                    'rack_barcode_value' => $rackCode,
                    'reports_count' => 0,
                    'item_mentions_count' => 0,
                    'latest_reported_at' => 0,
                    'items' => [],
                    'reports' => [],
                ];
            }

            $reportedAt = (int) ($task['stock_reported_at'] ?? $task['completed_at'] ?? 0);
            $waiterName = trim((string) ($task['completed_by_waiter_name'] ?? $task['assigned_waiter_name'] ?? '-'));
            if ($waiterName === '') {
                $waiterName = '-';
            }

            $rackGroups[$rackKey]['reports_count']++;
            $rackGroups[$rackKey]['item_mentions_count'] += count($items);
            $rackGroups[$rackKey]['latest_reported_at'] = max($rackGroups[$rackKey]['latest_reported_at'], $reportedAt);
            $rackGroups[$rackKey]['reports'][] = [
                'task_id' => (string) ($task['id'] ?? ''),
                'task_title' => (string) ($task['title'] ?? '-'),
                'waiter_name' => $waiterName,
                'items' => $items,
                'reported_at' => $reportedAt,
                'raw_report' => (string) ($task['completed_stock_report'] ?? ''),
            ];

            foreach ($items as $item) {
                $itemKey = strtolower($item);

                if (! isset($rackGroups[$rackKey]['items'][$itemKey])) {
                    $rackGroups[$rackKey]['items'][$itemKey] = [
                        'item' => $item,
                        'count' => 0,
                    ];
                }
                $rackGroups[$rackKey]['items'][$itemKey]['count']++;

                if (! isset($globalItems[$itemKey])) {
                    $globalItems[$itemKey] = [
                        'item' => $item,
                        'count' => 0,
                        'racks' => [],
                    ];
                }
                $globalItems[$itemKey]['count']++;
                $globalItems[$itemKey]['racks'][$rackKey] = $rackGroups[$rackKey]['rack_name'];
            }
        }

        $racks = array_values(array_map(function ($rack) {
            $rack['items'] = array_values($rack['items']);

            usort($rack['items'], function ($a, $b) {
                if (($b['count'] ?? 0) === ($a['count'] ?? 0)) {
                    return strcmp((string) ($a['item'] ?? ''), (string) ($b['item'] ?? ''));
                }

                return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            });

            usort($rack['reports'], function ($a, $b) {
                return ((int) ($b['reported_at'] ?? 0)) <=> ((int) ($a['reported_at'] ?? 0));
            });

            return $rack;
        }, $rackGroups));

        usort($racks, function ($a, $b) {
            if (($b['item_mentions_count'] ?? 0) === ($a['item_mentions_count'] ?? 0)) {
                return ($b['reports_count'] ?? 0) <=> ($a['reports_count'] ?? 0);
            }

            return ($b['item_mentions_count'] ?? 0) <=> ($a['item_mentions_count'] ?? 0);
        });

        $topItems = array_values(array_map(function ($item) {
            return [
                'item' => (string) ($item['item'] ?? '-'),
                'count' => (int) ($item['count'] ?? 0),
                'racks' => array_values($item['racks'] ?? []),
                'rack_count' => count($item['racks'] ?? []),
            ];
        }, $globalItems));

        usort($topItems, function ($a, $b) {
            if (($b['count'] ?? 0) === ($a['count'] ?? 0)) {
                return strcmp((string) ($a['item'] ?? ''), (string) ($b['item'] ?? ''));
            }

            return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        });

        return [
            'total_reports' => $totalReports,
            'total_item_mentions' => $totalItemMentions,
            'racks' => $racks,
            'top_items' => $topItems,
        ];
    }

    /**
     * Normalize item list from task report payload.
     */
    protected function normalizeStockReportItems(array $task): array
    {
        $source = $task['completed_stock_report_items'] ?? [];

        if (! is_array($source) || count($source) === 0) {
            $raw = (string) ($task['completed_stock_report'] ?? '');
            $source = preg_split('/[\r\n,;]+/', $raw) ?: [];
        }

        $items = [];
        $seen = [];
        foreach ($source as $rawItem) {
            $item = trim(preg_replace('/\s+/', ' ', (string) $rawItem) ?? '');
            if ($item === '') {
                continue;
            }

            $key = strtolower($item);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Build rack tracking board (who did and who did not).
     */
    protected function buildRackExecutionBoard(array $dateTasks): array
    {
        $grouped = [];

        foreach ($dateTasks as $task) {
            if ((string) ($task['task_type'] ?? 'general') !== 'rack_check') {
                continue;
            }

            $rackId = (string) ($task['rack_id'] ?? '');
            $rackCode = (string) ($task['rack_barcode_value'] ?? '');
            $key = $rackId !== '' ? $rackId : 'barcode::'.$rackCode;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'rack_id' => $rackId,
                    'rack_name' => (string) ($task['rack_name'] ?? 'Rak Tidak Diketahui'),
                    'rack_location' => (string) ($task['rack_location'] ?? '-'),
                    'rack_barcode_value' => $rackCode,
                    'done_waiters' => [],
                    'not_done_waiters' => [],
                    'done_count' => 0,
                    'not_done_count' => 0,
                    'is_role_round_robin' => false,
                    'assigned_waiter_role' => '',
                    'today_assignee_map' => [],
                    'today_assignee_label' => '-',
                ];
            }

            $waiterLabel = trim((string) ($task['assigned_waiter_name'] ?? '-'));
            if ($waiterLabel === '') {
                $waiterLabel = '-';
            }

            $assignmentStrategy = (string) ($task['assignment_strategy'] ?? '');
            if ($assignmentStrategy === 'role_round_robin') {
                $grouped[$key]['is_role_round_robin'] = true;

                $assignedWaiterRole = strtolower(trim((string) ($task['assigned_waiter_role'] ?? '')));
                if ($grouped[$key]['assigned_waiter_role'] === '' && in_array($assignedWaiterRole, ['kasir', 'pelayan'], true)) {
                    $grouped[$key]['assigned_waiter_role'] = $assignedWaiterRole;
                }

                if ($waiterLabel !== '-' && $waiterLabel !== '') {
                    $assigneeKey = strtolower($waiterLabel);
                    $grouped[$key]['today_assignee_map'][$assigneeKey] = $waiterLabel;
                }
            }

            if (($task['status'] ?? '') === 'done') {
                $grouped[$key]['done_count']++;
                $grouped[$key]['done_waiters'][] = [
                    'name' => $waiterLabel,
                    'completed_scanned_barcode' => (string) ($task['completed_scanned_barcode'] ?? ''),
                    'completed_stock_report' => (string) ($task['completed_stock_report'] ?? ''),
                    'completed_stock_report_items' => $this->normalizeStockReportItems($task),
                    'completed_no_out_of_stock' => (bool) ($task['completed_no_out_of_stock'] ?? false),
                ];
            } else {
                $grouped[$key]['not_done_count']++;
                $grouped[$key]['not_done_waiters'][] = [
                    'name' => $waiterLabel,
                    'status' => (string) ($task['status'] ?? 'pending'),
                ];
            }
        }

        $result = array_values($grouped);
        foreach ($result as &$rackBoard) {
            $assignees = array_values($rackBoard['today_assignee_map'] ?? []);
            sort($assignees, SORT_NATURAL | SORT_FLAG_CASE);

            if (count($assignees) === 1) {
                $rackBoard['today_assignee_label'] = $assignees[0];
            } elseif (count($assignees) > 1) {
                $rackBoard['today_assignee_label'] = implode(', ', $assignees);
            } else {
                $rackBoard['today_assignee_label'] = '-';
            }

            unset($rackBoard['today_assignee_map']);
        }
        unset($rackBoard);

        usort($result, function ($a, $b) {
            if ($b['not_done_count'] === $a['not_done_count']) {
                return $b['done_count'] <=> $a['done_count'];
            }

            return $b['not_done_count'] <=> $a['not_done_count'];
        });

        return $result;
    }
}
