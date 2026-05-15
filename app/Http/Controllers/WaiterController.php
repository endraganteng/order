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
            // Flag waiter untuk worker retry; jangan biarkan poin hilang silent.
            try {
                $this->firebase->flagWaiterBonusPending($waiterId, $reportDate, [
                    'source' => 'activity_report',
                    'reason' => 'auto_score_failed',
                    'error_class' => get_class($e),
                    'error_message' => substr($e->getMessage(), 0, 500),
                    'report_id' => (string) ($result['report_id'] ?? ''),
                ]);
            } catch (\Throwable $flagErr) {
                report($flagErr);
            }
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
            'idempotency_key' => 'nullable|string|max:120',
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
            $request->input('photo_before_data_url'),
            $request->input('idempotency_key')
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
            // Flag task untuk worker retry; jangan biarkan poin hilang silent.
            try {
                $this->firebase->flagTaskBonusPending((string) $id, $waiterId, [
                    'source' => 'complete_task',
                    'date' => date('Y-m-d'),
                    'reason' => 'auto_score_failed',
                    'error_class' => get_class($e),
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
            } catch (\Throwable $flagErr) {
                report($flagErr);
            }
        }

        // Auto-collect restock requests sudah dipindah ke FirebaseService::updateWaiterTaskStatus
        // (P0-3 atomicity: stock_movements + restock_requests ditulis SEBELUM task status='done',
        // sehingga shortage signal tidak hilang kalau ada partial failure).

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
     * Claim waiter task for 15 minutes.
     */
    public function claimTask($id, Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        $result = $this->firebase->claimWaiterTask((string) $id, $waiterId, $waiterName);
        $ok = (bool) ($result['success'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => (string) ($result['message'] ?? ($ok ? 'Tugas berhasil di-klaim.' : 'Gagal klaim tugas.')),
            'claim_expires_at' => $result['expires_at'] ?? null,
            'claimed_by_name' => $result['claimed_by_name'] ?? null,
        ], $ok ? 200 : 422);
    }

    /**
     * Release task claim by current claimer.
     */
    public function releaseTask($id, Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $result = $this->firebase->releaseWaiterTask((string) $id, $waiterId);
        $ok = (bool) ($result['success'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => (string) ($result['message'] ?? ($ok ? 'Klaim tugas dilepas.' : 'Gagal melepas klaim tugas.')),
        ], $ok ? 200 : 422);
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
     * Standalone stock-take page for waiter.
     */
    public function stockTakeIndex()
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        return view('waiter.stock_take', [
            'waiterId' => $waiterId,
            'waiterName' => $waiterName,
        ]);
    }

    /**
     * Resolve storage rack by scanned barcode for standalone stock take.
     */
    public function resolveStockTakeRack(Request $request)
    {
        $request->validate([
            'rack_barcode_value' => 'required|string|max:120',
        ]);

        $result = $this->firebase->getStorageRackProductsByBarcode((string) $request->input('rack_barcode_value'));
        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Rak tidak valid.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'rack' => $result['rack'] ?? null,
            'products' => $result['products'] ?? [],
        ]);
    }

    /**
     * Submit standalone storage stock-take movements.
     */
    public function submitStockTake(Request $request)
    {
        $request->validate([
            'rack_barcode_value' => 'required|string|max:120',
            'items' => 'required|string|max:120000',
            'note' => 'nullable|string|max:500',
            'idempotency_key' => 'nullable|string|max:120',
        ]);

        $items = json_decode((string) $request->input('items'), true);
        if (! is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Format item pengambilan tidak valid.',
            ], 422);
        }

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $waiterEmail = (string) session('waiter_email', '');

        $result = $this->firebase->submitStandaloneStockTake([
            'waiter_id' => $waiterId,
            'waiter_name' => $waiterName,
            'waiter_email' => $waiterEmail,
            'rack_barcode_value' => (string) $request->input('rack_barcode_value'),
            'items' => $items,
            'note' => (string) $request->input('note', ''),
            'idempotency_key' => (string) $request->input('idempotency_key', ''),
        ]);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Gagal menyimpan pengambilan stok.',
                'invalid_items' => $result['invalid_items'] ?? [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Pengambilan stok berhasil disimpan.',
            'rack_id' => $result['rack_id'] ?? null,
            'rack_name' => $result['rack_name'] ?? null,
            'rack_barcode_value' => $result['rack_barcode_value'] ?? null,
            'processed_items' => $result['processed_items'] ?? [],
            'invalid_items' => $result['invalid_items'] ?? [],
        ]);
    }

    /**
     * Clock in via QR scan.
     */
    public function clockIn(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $scannedValue = $request->input('scanned_value');
        
        // Check if using global QR mode
        $settings = $this->firebase->getSettings();
        $useGlobalQr = !empty($settings['attendance_use_global_qr']);
        
        if ($useGlobalQr) {
            // Global QR mode (scan-triggered rotating)
            $result = $this->firebase->processGlobalQrScanWithRegeneration($waiterId, 'clock_in', (string) $scannedValue);
        } else {
            // Per-waiter QR mode (original)
            $result = $this->firebase->processAttendanceQrScan($waiterId, 'clock_in', (string) $scannedValue, 'qr_scan');
        }

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
                // Flag waiter untuk worker retry; jangan biarkan poin hilang silent.
                try {
                    $this->firebase->flagWaiterBonusPending($waiterId, date('Y-m-d'), [
                        'source' => 'clock_in',
                        'reason' => 'auto_score_failed',
                        'error_class' => get_class($e),
                        'error_message' => substr($e->getMessage(), 0, 500),
                    ]);
                } catch (\Throwable $flagErr) {
                    report($flagErr);
                }
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
        
        // Check if using global QR mode
        $useGlobalQr = !empty($settings['attendance_use_global_qr']);
        
        if ($useGlobalQr) {
            // Global QR mode (scan-triggered rotating)
            $result = $this->firebase->processGlobalQrScanWithRegeneration($waiterId, 'clock_out', (string) $scannedValue);
        } else {
            // Per-waiter QR mode (original)
            $result = $this->firebase->processAttendanceQrScan($waiterId, 'clock_out', (string) $scannedValue, 'qr_scan');
        }

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
            'idempotency_key' => 'nullable|string|max:120',
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        $result = $this->firebase->receivePoItem(
            $poId,
            $request->input('restock_id'),
            (int) $request->input('received_qty'),
            $waiterId,
            $waiterName,
            $request->input('idempotency_key')
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
            'idempotency_key' => 'nullable|string|max:120',
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $restockId = $request->input('restock_id');
        $issueNote = $request->input('issue_note');

        $result = $this->firebase->reportPoItemIssue($poId, $restockId, $issueNote, $waiterId, $waiterName, $request->input('idempotency_key'));

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
