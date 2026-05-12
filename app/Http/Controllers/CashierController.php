<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Show cashier view
     */
    public function index()
    {
        $lastSync = (int) session('cashier_last_sync', 0);
        $now = time();

        if ($now - $lastSync >= 30) {
            $this->firebase->generateDueRecurringTasks();
            $this->firebase->markOverdueTasks();
            session(['cashier_last_sync' => $now]);
        }

        $cashierWorkers = $this->firebase->getActiveCashierWorkers();
        $attendanceWaiters = $this->firebase->getAttendanceEligibleWaiters();

        return view('cashier.index', compact('cashierWorkers', 'attendanceWaiters'));
    }

    /**
     * Get current attendance QR data for selected waiter.
     */
    public function getAttendanceQr(Request $request)
    {
        $waiterId = trim((string) $request->query('waiter_id', ''));
        if ($waiterId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Waiter harus dipilih terlebih dahulu.',
            ], 422);
        }

        $payload = $this->firebase->getCashierAttendanceQrData($waiterId);
        if (empty($payload['found'])) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'] ?? 'Waiter tidak ditemukan.',
            ], 404);
        }

        return response()->json(array_merge(['success' => true], $payload));
    }

    /**
     * Get active cashier workers for cashier client
     */
    public function getCashierWorkers()
    {
        return response()->json([
            'success' => true,
            'workers' => $this->firebase->getActiveCashierWorkers(),
        ]);
    }

    /**
     * Generate due recurring tasks (polling endpoint)
     */
    public function syncDueTasks()
    {
        $lastSync = (int) session('cashier_last_sync', 0);
        $now = time();

        if ($now - $lastSync < 30) {
            return response()->json([
                'success' => true,
                'generated' => 0,
                'overdue' => 0,
                'skipped' => true,
            ]);
        }

        session(['cashier_last_sync' => $now]);

        $generated = $this->firebase->generateDueRecurringTasks();
        $overdue = $this->firebase->markOverdueTasks();

        return response()->json([
            'success' => true,
            'generated' => $generated,
            'overdue' => $overdue,
        ]);
    }

    /**
     * Update task status from cashier page
     */
    public function updateTaskStatus($id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:done',
            'note' => 'nullable|string|max:500',
            'cashier_worker_id' => 'required|string|max:100',
        ]);

        $worker = $this->firebase->getCashierWorkerById($request->cashier_worker_id);
        if (! $worker || empty($worker['is_active'])) {
            return response()->json([
                'success' => false,
                'message' => 'Nama kasir tidak valid atau sudah nonaktif',
            ], 422);
        }

        $result = $this->firebase->updateTaskStatus(
            $id,
            $request->status,
            $request->note,
            $worker['id'],
            $worker['name'] ?? null
        );

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}
