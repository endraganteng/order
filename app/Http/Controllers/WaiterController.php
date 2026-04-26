<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;

class WaiterController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
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
        $reportDate = date('Y-m-d');

        $this->firebase->generateDueRecurringWaiterTasks();
        $this->firebase->markOverdueWaiterTasks();

        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);

        return view('waiter.tasks', [
            'waiterId' => $waiterId,
            'waiterName' => (string) session('waiter_name', 'Waiter'),
            'waiterEmail' => (string) session('waiter_email', ''),
            'reportDate' => $reportDate,
            'pendingTasks' => $taskBuckets['pending_tasks'],
            'taskHistory' => $taskBuckets['task_history'],
            'activityReports' => $activityReports,
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
        $overdue = $this->firebase->markOverdueWaiterTasks();
        $taskBuckets = $this->buildWaiterTaskBuckets($waiterId);
        $activityReports = $this->buildWaiterActivityReports($waiterId, $reportDate);

        return response()->json([
            'success' => true,
            'report_date' => $reportDate,
            'generated' => $generated,
            'overdue' => $overdue,
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
        ]);

        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $waiterEmail = (string) session('waiter_email', '');

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
            $request->input('photo_proof_data_url')
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

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tugas berhasil diverifikasi sebagai selesai.',
            ]);
        }

        return back()->with('success', 'Tugas berhasil diverifikasi sebagai selesai.');
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

        $taskHistory = array_values(array_filter($tasks, function ($task) {
            return ($task['status'] ?? 'pending') !== 'pending';
        }));

        return [
            'pending_tasks' => $pendingTasks,
            'task_history' => $taskHistory,
        ];
    }

    /**
     * Build activity reports for current waiter and date.
     */
    protected function buildWaiterActivityReports(string $waiterId, ?string $reportDate = null): array
    {
        return $this->firebase->getWaiterActivityReportsByWaiterIdForDate($waiterId, $reportDate);
    }
}
