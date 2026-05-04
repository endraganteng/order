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

        // Cooldown guard: run heavy ops max once per 60s per session
        $lastGenerated = (int) session('waiter_last_generated_at', 0);
        if (time() - $lastGenerated >= 60) {
            $this->firebase->generateDueRecurringWaiterTasks();
            $overdueResult = $this->firebase->markOverdueWaiterTasks();
            $this->sendOverdueNotifications($overdueResult['overdue_tasks'] ?? []);
            session()->put('waiter_last_generated_at', time());
        }

        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);
        $rackProductsMap = $this->firebase->getAllRackProductsMap();
        $todayAttendance = $this->firebase->getAttendanceByDate($waiterId, date('Y-m-d'));
        $waiterShift = $this->firebase->getWaiterShift($waiterId);

        // Bonus dashboard data
        $bonusMonth = date('Y-m');
        $bonusConfig = $this->bonus->getBonusConfig();
        $monthlyPoints = $this->firebase->getMonthlyDailyPoints($waiterId, $bonusMonth);
        $penalties = $this->bonus->getPenaltiesByMonth($bonusMonth, $waiterId);
        $salesTarget = $this->bonus->getSalesTarget($waiterId, $bonusMonth);
        $bonusSummary = $this->bonus->getMonthlyBonusSummary($waiterId, $bonusMonth);
        $leaderboard = $this->bonus->getLeaderboard($bonusMonth);

        $totalEarned = 0;
        $totalPenalties = 0;
        $daysScored = 0;
        $perfectDays = 0;

        foreach ($monthlyPoints as $date => $record) {
            $totalEarned += (int) ($record['daily_total'] ?? 0);
            $daysScored++;
            if (! empty($record['perfect_day_bonus']) && $record['perfect_day_bonus'] > 0) {
                $perfectDays++;
            }
        }

        foreach ($penalties as $penalty) {
            $totalPenalties += abs((int) ($penalty['points_deducted'] ?? 0));
        }

        $netPoints = $totalEarned - $totalPenalties;
        $workingDays = (int) ($bonusConfig['working_days_per_month'] ?? 26);
        $perfectDayBonus = (int) ($bonusConfig['perfect_day_bonus'] ?? 5);
        $dailyMax = (int) ($bonusConfig['daily_max_points'] ?? 30);
        $theoreticalMax = ($dailyMax + $perfectDayBonus) * $workingDays;
        $percentage = $theoreticalMax > 0 ? round(($netPoints / $theoreticalMax) * 100, 1) : 0;

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
     * Generate due recurring tasks for waiter portal polling.
     */
    public function syncDueTasks()
    {
        $waiterId = (string) session('waiter_id');
        $reportDate = date('Y-m-d');
        $generated = $this->firebase->generateDueRecurringWaiterTasks();
        $overdueResult = $this->firebase->markOverdueWaiterTasks();
        $this->sendOverdueNotifications($overdueResult['overdue_tasks'] ?? []);
        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);

        // Send periodic task reminders via WhatsApp
        try {
            $attendance = $this->firebase->getAttendanceByDate($waiterId, $reportDate);
            $this->fonnte->sendTaskReminders($waiterId, $taskBuckets['pending_tasks'], $attendance);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'report_date' => $reportDate,
            'generated' => $generated,
            'overdue' => $overdueResult['count'],
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

            $bonusService->scoreDailyPoints($waiterId, $today, $categoryScores, 'Auto-scored on activity report');
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
            $productChecklist
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

            $bonusService->scoreDailyPoints($waiterId, $today, $categoryScores, 'Auto-scored on task completion');
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tugas berhasil diverifikasi sebagai selesai.',
            ]);
        }

        return back()->with('success', 'Tugas berhasil diverifikasi sebagai selesai.');
    }

    /**
     * Get rack products map for waiter polling.
     */
    public function getRackProducts()
    {
        $rackProductsMap = $this->firebase->getAllRackProductsMap();

        return response()->json([
            'success' => true,
            'rack_products_map' => $rackProductsMap,
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

                $bonusService->scoreDailyPoints($waiterId, $today, $categoryScores, 'Auto-scored on clock-in');
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
            return ($task['status'] ?? 'pending') === 'pending';
        }));
        $pendingTasks = $this->deduplicatePendingTasks($pendingTasks);

        // Filter by shift time: only show tasks if waiter's shift has started
        $pendingTasks = $this->filterByShiftTime($pendingTasks, $waiterId);

        $taskHistory = array_values(array_filter($tasks, function ($task) {
            return ($task['status'] ?? 'pending') !== 'pending';
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
