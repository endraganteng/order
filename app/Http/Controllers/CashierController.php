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
        $this->firebase->generateDueRecurringTasks();
        $this->firebase->markOverdueTasks();

        $cashierWorkers = $this->firebase->getActiveCashierWorkers();

        return view('cashier.index', compact('cashierWorkers'));
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
