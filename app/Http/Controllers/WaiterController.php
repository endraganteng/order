<?php

namespace App\Http\Controllers;

use App\Services\BonusService;
use App\Services\FirebaseService;
use App\Services\FonnteService;
use Illuminate\Http\Request;

class WaiterController extends Controller
{
    protected $firebase;
    protected $bonus;
    protected $fonnte;

    public function __construct(FirebaseService $firebase, BonusService $bonus, FonnteService $fonnte)
    {
        $this->firebase = $firebase;
        $this->bonus = $bonus;
        $this->fonnte = $fonnte;
    }

    /**
     * Show waiter login form.
     */
    public function showLogin()
    {
        if (session()->has('waiter_authenticated') && session()->has('waiter_id')) {
            return redirect()->to(route('waiter.tasks', [], false));
        }

        return view('waiter.login');
    }

    /**
     * Process waiter login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $waiter = $this->firebase->verifyWaiterCredentials($request->email, $request->password);
        if (! $waiter) {
            return back()->withErrors([
                'email' => 'Email/password tidak valid atau akun waiter belum aktif.',
            ])->withInput($request->only('email'));
        }

        $this->authenticateWaiterSession($request, $waiter);

        return redirect()->to(route('waiter.tasks', [], false));
    }

    /**
     * Process waiter Google login.
     */
    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $waiter = $this->firebase->verifyWaiterGoogleToken($request->id_token);
        if (! $waiter) {
            return response()->json([
                'success' => false,
                'message' => 'Google login gagal. Pastikan email Google terdaftar sebagai waiter aktif.',
            ], 422);
        }

        $this->authenticateWaiterSession($request, $waiter);

        return response()->json([
            'success' => true,
            'redirect' => route('waiter.tasks', [], false),
        ]);
    }

    /**
     * Store authenticated waiter identity in session.
     */
    protected function authenticateWaiterSession(Request $request, array $waiter): void
    {
        $request->session()->regenerate();
        session()->put('waiter_authenticated', true);
        session()->put('waiter_id', $waiter['id']);
        session()->put('waiter_name', $waiter['name'] ?? 'Waiter');
        session()->put('waiter_email', $waiter['email'] ?? '');
    }

    /**
     * Show task page for authenticated waiter.
     */
    public function tasksIndex()
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $reportDate = date('Y-m-d');

        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);
        $rackProductsMap = $this->firebase->getAllRackProductsMap();
        $rackTypesMap = $this->firebase->getRackTypesMap();
        $todayAttendance = $this->firebase->getAttendanceByDate($waiterId, date('Y-m-d'));
        $waiterShift = $this->firebase->getWaiterShift($waiterId);
        $settings = $this->firebase->getSettings();
        $clockOutEnabled = !empty($settings['clock_out_enabled']);

        // Bonus dashboard data
        $bonusMonth = date('Y-m');
        $bonusProgress = $this->bonus->getWaiterMonthlyProgress($waiterId, $bonusMonth);
        $bonusConfig = $bonusProgress['config'];
        $monthlyPoints = $bonusProgress['monthly_points'];
        $penalties = $bonusProgress['penalties'];
        $salesTarget = $bonusProgress['sales_target'];
        $bonusSummary = $bonusProgress['bonus_summary'];
        $leaderboard = $bonusProgress['leaderboard'];
        $totalEarned = (int) $bonusProgress['total_earned'];
        $totalPenalties = (int) $bonusProgress['total_penalties'];
        $netPoints = (int) $bonusProgress['net_points'];
        $daysScored = (int) $bonusProgress['days_scored'];
        $perfectDays = (int) $bonusProgress['perfect_days'];
        $percentage = (float) $bonusProgress['percentage'];
        $theoreticalMax = (int) $bonusProgress['theoretical_max'];
        $workingDays = (int) $bonusProgress['working_days'];
        $dailyMaxWithPerfect = (int) $bonusProgress['daily_max_with_perfect'];
        $monthlyServiceMax = (int) $bonusProgress['monthly_service_max'];
        $monthlySalesMax = (int) $bonusProgress['monthly_sales_max'];

        $myRank = null;
        if ($leaderboard && ! empty($leaderboard['rankings'])) {
            foreach ($leaderboard['rankings'] as $entry) {
                if (($entry['waiter_id'] ?? '') === $waiterId) {
                    $myRank = $entry;
                    break;
                }
            }
        }

        return view('waiter.tasks', [
            'waiterId' => $waiterId,
            'waiterName' => $waiterName,
            'waiterEmail' => (string) session('waiter_email', ''),
            'reportDate' => $reportDate,
            'pendingTasks' => $taskBuckets['pending_tasks'],
            'taskHistory' => $taskBuckets['task_history'],
            'activityReports' => $activityReports,
            'rackProductsMap' => $rackProductsMap,
            'rackTypesMap' => $rackTypesMap,
            'todayAttendance' => $todayAttendance,
            'waiterShift' => $waiterShift,
            'shiftStartTime' => $waiterShift ? ($waiterShift['clock_in_time'] ?? null) : null,
            // Bonus data
            'bonusMonth' => $bonusMonth,
            'bonusConfig' => $bonusConfig,
            'monthlyPoints' => $monthlyPoints,
            'penalties' => $penalties,
            'salesTarget' => $salesTarget,
            'bonusSummary' => $bonusSummary,
            'leaderboard' => $leaderboard,
            'myRank' => $myRank,
            'totalEarned' => $totalEarned,
            'totalPenalties' => $totalPenalties,
            'netPoints' => $netPoints,
            'daysScored' => $daysScored,
            'perfectDays' => $perfectDays,
            'percentage' => $percentage,
            'theoreticalMax' => $theoreticalMax,
            'workingDays' => $workingDays,
            'dailyMaxWithPerfect' => $dailyMaxWithPerfect,
            'monthlyServiceMax' => $monthlyServiceMax,
            'monthlySalesMax' => $monthlySalesMax,
            'clockOutEnabled' => $clockOutEnabled,
        ]);
    }

    /**
     * Waiter polling endpoint for no-reload task updates.
     */
    public function pollTasks()
    {
        $waiterId = (string) session('waiter_id');
        $reportDate = date('Y-m-d');
        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);

        return response()->json([
            'success' => true,
            'report_date' => $reportDate,
            'pending_tasks' => $taskBuckets['pending_tasks'],
            'task_history' => $taskBuckets['task_history'],
            'activity_reports' => $activityReports,
        ]);
    }

    /**
     * Refresh waiter portal data for polling.
     */
    public function syncDueTasks()
    {
        $waiterId = (string) session('waiter_id');
        $reportDate = date('Y-m-d');
        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);

        return response()->json([
            'success' => true,
            'report_date' => $reportDate,
            'pending_tasks' => $taskBuckets['pending_tasks'],
            'task_history' => $taskBuckets['task_history'],
            'activity_reports' => $activityReports,
        ]);
    }

    /**
     * Store optional waiter daily activity report.
     */
    public function storeActivityReport(Request $request)
    {
        $request->validate([
            'activity_text' => 'required|string|max:2000',
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $waiterEmail = (string) session('waiter_email', '');
        $reportDate = date('Y-m-d');

        $result = $this->firebase->createWaiterActivityReport([
            'waiter_id' => $waiterId,
            'waiter_name' => $waiterName,
            'waiter_email' => $waiterEmail,
            'report_date' => $reportDate,
            'activity_text' => (string) $request->input('activity_text', ''),
        ]);

        if (! ($result['success'] ?? false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Gagal menyimpan laporan kegiatan.',
                ], 422);
            }

            return back()->with('error', $result['message'] ?? 'Gagal menyimpan laporan kegiatan.');
        }

        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);

        // After successful activity report, auto-update daily points (attitude becomes 5)
        try {
            $bonusService = app(\App\Services\BonusService::class);
            $today = date('Y-m-d');

            $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
            $todayTasks = $this->firebase->getWaiterTasksForDate($waiterId, $today);
            $reports = $this->firebase->getWaiterActivityReportsByWaiterIdForDate($waiterId, $today);

            $autoScores = $bonusService->autoScoreDailyPoints($waiterId, $today, $attendance, $todayTasks, $reports);

            $categoryScores = [
                'discipline' => $autoScores['discipline'],
                'operational' => $autoScores['operational'],
                'attitude' => $autoScores['attitude'],
            ];

            $bonusService->saveAutoDailyScore($waiterId, $today, $categoryScores, 'Auto-scored on activity report', $autoScores['auto_details'] ?? []);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Laporan kegiatan berhasil disimpan.',
                'report_date' => $reportDate,
                'activity_reports' => $activityReports,
            ]);
        }

        return back()->with('success', 'Laporan kegiatan berhasil disimpan.');
    }

    /**
     * Waiter verifies task completion.
     */
    public function completeTask($id, Request $request)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
            'scanned_barcode' => 'nullable|string|max:120',
            'stock_report_items' => 'nullable|string|max:2000',
            'no_out_of_stock' => 'nullable|boolean',
            'photo_proof_data_url' => 'nullable|string|max:5000000',
            'photo_before_data_url' => 'nullable|string|max:5000000',
            'product_checklist' => 'nullable|string|max:50000',
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $waiterEmail = (string) session('waiter_email', '');

        $productChecklist = null;
        $rawChecklist = $request->input('product_checklist');
        if ($rawChecklist) {
            $productChecklist = json_decode($rawChecklist, true);
            if (! is_array($productChecklist)) {
                $productChecklist = null;
            }
        }

        $result = $this->firebase->updateWaiterTaskStatus(
            $id,
            'done',
            $waiterId,
            $waiterName,
            $waiterEmail,
            $request->input('note'),
            $request->input('scanned_barcode'),
            $request->input('stock_report_items'),
            $request->boolean('no_out_of_stock'),
            $request->input('photo_proof_data_url'),
            $productChecklist,
            $request->input('photo_before_data_url')
        );

        if (! ($result['success'] ?? false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Gagal memverifikasi tugas.',
                ], 422);
            }

            return back()->with('error', $result['message'] ?? 'Gagal memverifikasi tugas.');
        }

        // After successful task completion, auto-update daily points
        try {
            $bonusService = app(\App\Services\BonusService::class);
            $today = date('Y-m-d');

            $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
            $todayTasks = $this->firebase->getWaiterTasksForDate($waiterId, $today);
            $reports = $this->firebase->getWaiterActivityReportsByWaiterIdForDate($waiterId, $today);

            $autoScores = $bonusService->autoScoreDailyPoints($waiterId, $today, $attendance, $todayTasks, $reports);

            $categoryScores = [
                'discipline' => $autoScores['discipline'],
                'operational' => $autoScores['operational'],
                'attitude' => $autoScores['attitude'],
            ];

            $bonusService->saveAutoDailyScore($waiterId, $today, $categoryScores, 'Auto-scored on task completion', $autoScores['auto_details'] ?? []);
        } catch (\Throwable $e) {
            report($e);
        }

        // Auto-collect restock requests from product checklist (ONLY for storage racks)
        try {
            if ($productChecklist && is_array($productChecklist)) {
                $task = $this->firebase->getWaiterTaskById($id);
                $rackId = $task['rack_id'] ?? '';
                $rackName = $task['rack_name'] ?? ($task['title'] ?? '');

                // Only trigger restock for storage racks (not display racks)
                $rack = $rackId ? $this->firebase->getRackById($rackId) : null;
                $rackType = $rack['rack_type'] ?? 'storage';

                if ($rackType === 'storage') {
                    // STORAGE rack: shortage → masuk daftar restock (beli ke supplier)
                    $productCategories = $this->firebase->getProductCategoriesMap();

                    foreach ($productChecklist as $item) {
                        $actualQty = (int) ($item['actual_qty'] ?? 0);
                        $standardQty = (int) ($item['standard_qty'] ?? 0);
                        $minQty = (int) ($item['min_qty'] ?? 0);

                        $needsRestock = $standardQty > 0 && $actualQty < $standardQty;

                        if ($needsRestock && $rackId) {
                            $qtyNeeded = $standardQty - $actualQty;
                            if ($qtyNeeded <= 0) $qtyNeeded = $standardQty;

                            $productId = $item['product_id'] ?? '';
                            $productMaster = $productId ? $this->firebase->getProductById($productId) : null;
                            $catId = $productMaster['category_id'] ?? null;
                            $catName = ($catId && isset($productCategories[$catId])) ? $productCategories[$catId]['name'] : 'Tanpa Kategori';

                            $this->firebase->createOrUpdateRestockRequest([
                                'product_id' => $productId,
                                'product_name' => $item['product_name'] ?? ($item['name'] ?? ''),
                                'product_category_id' => $catId,
                                'product_category_name' => $catName,
                                'rack_id' => $rackId,
                                'rack_name' => $rackName,
                                'reported_qty' => $actualQty,
                                'standard_qty' => $standardQty,
                                'min_qty' => $minQty,
                                'qty_needed' => $qtyNeeded,
                                'reported_by' => $waiterId,
                                'reported_by_name' => $waiterName,
                                'date' => date('Y-m-d'),
                            ]);
                        }
                    }
                } elseif ($rackType === 'display') {
                    // DISPLAY rack: after refill step, if qty final still < standard → masuk restock
                    // (waiter already did inline refill from gudang, qty submitted is the FINAL qty)
                    $productCategories = $this->firebase->getProductCategoriesMap();

                    foreach ($productChecklist as $item) {
                        $actualQty = (int) ($item['actual_qty'] ?? 0); // qty FINAL setelah refill
                        $standardQty = (int) ($item['standard_qty'] ?? 0);
                        $minQty = (int) ($item['min_qty'] ?? 0);
                        $wasRefilled = (bool) ($item['was_refilled'] ?? false);

                        // Only trigger restock if item was refilled but still below standard
                        // (meaning gudang juga tidak cukup)
                        $needsRestock = $wasRefilled && $standardQty > 0 && $actualQty < $standardQty;

                        if ($needsRestock && $rackId) {
                            $qtyNeeded = $standardQty - $actualQty;

                            $productId = $item['product_id'] ?? '';
                            $productMaster = $productId ? $this->firebase->getProductById($productId) : null;
                            $catId = $productMaster['category_id'] ?? null;
                            $catName = ($catId && isset($productCategories[$catId])) ? $productCategories[$catId]['name'] : 'Tanpa Kategori';

                            $this->firebase->createOrUpdateRestockRequest([
                                'product_id' => $productId,
                                'product_name' => $item['product_name'] ?? ($item['name'] ?? ''),
                                'product_category_id' => $catId,
                                'product_category_name' => $catName,
                                'rack_id' => $rackId,
                                'rack_name' => $rackName,
                                'reported_qty' => $actualQty,
                                'standard_qty' => $standardQty,
                                'min_qty' => $minQty,
                                'qty_needed' => $qtyNeeded,
                                'reported_by' => $waiterId,
                                'reported_by_name' => $waiterName,
                                'date' => date('Y-m-d'),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $isPartial = (bool) ($result['partial'] ?? false);
        $responseMessage = $result['message'] ?? 'Tugas berhasil diverifikasi sebagai selesai.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'partial' => $isPartial,
                'completed_count' => $result['completed_count'] ?? 1,
                'repeat_count' => $result['repeat_count'] ?? 1,
                'message' => $responseMessage,
            ]);
        }

        return back()->with('success', $responseMessage);
    }

    /**
     * Get rack products map for waiter polling.
     */
    public function getRackProducts()
    {
        $rackProductsMap = $this->firebase->getAllRackProductsMap();
        $rackTypesMap = $this->firebase->getRackTypesMap();

        return response()->json([
            'success' => true,
            'rack_products_map' => $rackProductsMap,
            'rack_types_map' => $rackTypesMap,
        ]);
    }

    /**
     * Clock in via QR scan.
     */
    public function clockIn(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $scannedValue = $request->input('scanned_value');

        if (! $this->firebase->verifyAttendanceQrCode($scannedValue)) {
            return response()->json(['success' => false, 'message' => 'QR code absensi tidak valid'], 400);
        }

        $result = $this->firebase->clockIn($waiterId, 'qr_scan');

        if ($result['success'] ?? false) {
            // Check if late and auto-apply penalty
            $attendance = $this->firebase->getAttendanceByDate($waiterId, date('Y-m-d'));
            if ($attendance && ($attendance['status'] ?? '') === 'late' && ((int)($attendance['late_minutes'] ?? 0)) > 0) {
                try {
                    $bonusService = app(\App\Services\BonusService::class);
                    $today = date('Y-m-d');
                    $month = substr($today, 0, 7);

                    // Check if penalty already exists for today's late
                    $existingPenalties = $bonusService->getPenaltiesByMonth($month, $waiterId);
                    $alreadyHasLatePenalty = false;
                    foreach ($existingPenalties as $p) {
                        if (($p['penalty_type'] ?? '') === 'late_arrival' && ($p['date'] ?? '') === $today) {
                            $alreadyHasLatePenalty = true;
                            break;
                        }
                    }

                    if (!$alreadyHasLatePenalty) {
                        $waiterName = (string) session('waiter_name', 'Waiter');
                        $lateMin = (int) $attendance['late_minutes'];
                        $bonusService->applyPenalty([
                            'waiter_id' => $waiterId,
                            'waiter_name' => $waiterName,
                            'penalty_type' => 'late_arrival',
                            'date' => $today,
                            'reason' => 'Terlambat ' . $lateMin . ' menit (otomatis dari absensi)',
                            'related_task_id' => '',
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // After successful clock-in, auto-update discipline score
            try {
                $bonusService = app(\App\Services\BonusService::class);
                $today = date('Y-m-d');

                $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
                $todayTasks = $this->firebase->getWaiterTasksForDate($waiterId, $today);
                $reports = $this->firebase->getWaiterActivityReportsByWaiterIdForDate($waiterId, $today);

                $autoScores = $bonusService->autoScoreDailyPoints($waiterId, $today, $attendance, $todayTasks, $reports);

                $categoryScores = [
                    'discipline' => $autoScores['discipline'],
                    'operational' => $autoScores['operational'],
                    'attitude' => $autoScores['attitude'],
                ];

                $bonusService->saveAutoDailyScore($waiterId, $today, $categoryScores, 'Auto-scored on clock-in', $autoScores['auto_details'] ?? []);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json($result);
    }

    /**
     * Clock out via QR scan.
     */
    public function clockOut(Request $request)
    {
        $settings = $this->firebase->getSettings();
        if (empty($settings['clock_out_enabled'])) {
            return response()->json(['success' => false, 'message' => 'Fitur absen pulang tidak aktif'], 403);
        }

        $waiterId = (string) session('waiter_id');
        $scannedValue = $request->input('scanned_value');

        if (! $this->firebase->verifyAttendanceQrCode($scannedValue)) {
            return response()->json(['success' => false, 'message' => 'QR code absensi tidak valid'], 400);
        }

        $result = $this->firebase->clockOut($waiterId, 'qr_scan');

        return response()->json($result);
    }

    /**
     * Get today's attendance status for current waiter.
     */
    public function getAttendanceStatus()
    {
        $waiterId = (string) session('waiter_id');
        $today = date('Y-m-d');
        $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
        $shift = $this->firebase->getWaiterShift($waiterId);

        return response()->json([
            'attendance' => $attendance,
            'shift' => $shift,
            'today' => $today,
        ]);
    }

    /**
     * Logout waiter session.
     */
    public function logout()
    {
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->to(route('waiter.login', [], false));
    }

    /**
     * Build pending/history buckets for current waiter.
     */
    protected function buildWaiterTaskBuckets(string $waiterId): array
    {
        $tasks = $this->firebase->getWaiterTasksByWaiterId($waiterId);

        $pendingTasks = array_values(array_filter($tasks, function ($task) {
            $status = $task['status'] ?? 'pending';
            return $status === 'pending' || $status === 'in_progress';
        }));
        $pendingTasks = $this->deduplicatePendingTasks($pendingTasks);

        // Filter by shift time: only show tasks if waiter's shift has started
        $pendingTasks = $this->filterByShiftTime($pendingTasks, $waiterId);

        $taskHistory = array_values(array_filter($tasks, function ($task) {
            $status = $task['status'] ?? 'pending';
            return $status !== 'pending' && $status !== 'in_progress';
        }));

        return [
            'pending_tasks' => $pendingTasks,
            'task_history' => $taskHistory,
        ];
    }

    /**
     * Filter pending tasks based on shift schedule.
     * - Day off: return empty (no tasks on day off)
     * - Before shift start: hide today's tasks, show overdue only
     * - After shift start: show today's + overdue tasks
     */
    protected function filterByShiftTime(array $tasks, string $waiterId): array
    {
        $today = date('Y-m-d');
        $now = time();

        // Check if today is a working day
        $isWorking = $this->firebase->isWorkingDay($waiterId, $today);
        if (!$isWorking) {
            // Day off: no tasks shown at all
            return [];
        }

        $shift = $this->firebase->getWaiterShift($waiterId);

        // Has schedule but shift data missing (edge case): show all
        if (!$shift) {
            return $tasks;
        }

        $clockInTime = $shift['clock_in_time'] ?? null; // Format: "HH:MM" e.g. "08:00"
        if (!$clockInTime) {
            return $tasks;
        }

        // Calculate shift start timestamp for today
        $shiftStartTs = strtotime($today . ' ' . $clockInTime . ':00');

        return array_values(array_filter($tasks, function ($task) use ($today, $now, $shiftStartTs) {
            $scheduledDate = $task['scheduled_for_date'] ?? '';

            // Tasks for past dates: always show (overdue)
            if ($scheduledDate && $scheduledDate < $today) {
                return true;
            }

            // Tasks for future dates: never show
            if ($scheduledDate && $scheduledDate > $today) {
                return false;
            }

            // Tasks for today (or no date): show only if shift has started
            return $now >= $shiftStartTs;
        }));
    }

    /**
     * Remove accidental duplicate pending recurring tasks for waiter portal.
     */
    protected function deduplicatePendingTasks(array $pendingTasks): array
    {
        $sorted = array_values($pendingTasks);
        usort($sorted, function ($a, $b) {
            return (int) ($b['created_at'] ?? 0) <=> (int) ($a['created_at'] ?? 0);
        });

        $seenRecurring = [];
        $result = [];

        foreach ($sorted as $task) {
            $instanceKey = trim((string) ($task['recurring_instance_key'] ?? ''));
            if ($instanceKey === '') {
                $sourceTemplateId = trim((string) ($task['source_template_id'] ?? ''));
                $scheduledDate = trim((string) ($task['scheduled_for_date'] ?? ''));
                $assignedWaiterId = trim((string) ($task['assigned_waiter_id'] ?? ''));

                if ($sourceTemplateId !== '' && $scheduledDate !== '' && $assignedWaiterId !== '') {
                    $instanceKey = $sourceTemplateId.'::'.$assignedWaiterId.'::'.$scheduledDate;
                }
            }

            if ($instanceKey !== '') {
                if (isset($seenRecurring[$instanceKey])) {
                    continue;
                }
                $seenRecurring[$instanceKey] = true;
            }

            $result[] = $task;
        }

        return $result;
    }

    /**
     * Build activity reports for current waiter and date.
     */
    protected function buildWaiterActivityReports(string $waiterId, ?string $reportDate = null): array
    {
        return $this->firebase->getWaiterActivityReportsByWaiterIdForDate($waiterId, $reportDate);
    }

    /**
     * Waiter restock list — active POs with receivable items
     */
    public function restockList()
    {
        $orders = $this->firebase->getPurchaseOrders();
        // Filter to only active POs (ordered or partial)
        $activeOrders = array_filter($orders, fn($o) => in_array($o['status'] ?? '', ['ordered', 'partial']));

        return view('waiter.restock', compact('activeOrders'));
    }

    /**
     * Waiter confirms receiving a PO item (partial qty support)
     */
    public function receiveRestockItem(string $poId, Request $request)
    {
        $request->validate([
            'restock_id' => 'required|string',
            'received_qty' => 'required|integer|min:1',
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        $result = $this->firebase->receivePoItem(
            $poId,
            $request->input('restock_id'),
            (int) $request->input('received_qty'),
            $waiterId,
            $waiterName
        );

        return response()->json($result);
    }

    /**
     * Report issue with a PO item (not received, wrong qty, damaged)
     * If issue is "Barang tidak datang", auto-close item with received_qty = 0
     */
    public function reportRestockIssue(string $poId, Request $request)
    {
        $request->validate([
            'restock_id' => 'required|string',
            'issue_note' => 'required|string|max:500',
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $restockId = $request->input('restock_id');
        $issueNote = $request->input('issue_note');

        $result = $this->firebase->reportPoItemIssue($poId, $restockId, $issueNote, $waiterId, $waiterName);

        return response()->json($result);
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
}
