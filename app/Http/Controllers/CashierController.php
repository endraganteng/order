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
        $settings = $this->firebase->getSettings();

        // Get waiters who have shift today but haven't clocked in yet
        $today = date('Y-m-d');
        $waitersNotYetClocked = [];
        
        foreach ($attendanceWaiters as $waiter) {
            $waiterId = $waiter['id'] ?? '';
            $shift = $this->firebase->getWaiterShiftForDate($waiterId, $today);
            
            // Only include if waiter has shift today (not off)
            if ($shift) {
                $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
                
                // Check if not clocked in yet
                if (!$attendance || empty($attendance['clock_in'])) {
                    $waitersNotYetClocked[] = [
                        'id' => $waiterId,
                        'name' => $waiter['name'] ?? 'Unknown',
                        'shift_name' => $shift['name'] ?? 'Shift',
                        'clock_in_time' => $shift['clock_in_time'] ?? '-',
                    ];
                }
            }
        }

        return view('cashier.index', compact('cashierWorkers', 'attendanceWaiters', 'settings', 'waitersNotYetClocked'));
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
     * Get global attendance QR (scan-triggered rotating mode).
     */
    public function getGlobalAttendanceQr()
    {
        $qrData = $this->firebase->getCurrentGlobalAttendanceQr();
        $today = date('Y-m-d');
        
        // Calculate statistics and build waiters list
        $eligibleWaiters = $this->firebase->getAttendanceEligibleWaiters();
        $notYet = 0;
        $clockedIn = 0;
        $clockedOut = 0;
        $waitersNotYetClocked = [];
        
        foreach ($eligibleWaiters as $waiter) {
            $waiterId = $waiter['id'] ?? '';
            $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
            
            // Check if waiter has shift today (not day off)
            $shift = $this->firebase->getWaiterShiftForDate($waiterId, $today);
            
            // Skip if waiter is off today (no shift)
            if (!$shift) {
                continue;
            }
            
            if (empty($attendance['clock_in'])) {
                $notYet++;
                
                // Add to not-yet-clocked list with shift info
                $waitersNotYetClocked[] = [
                    'id' => $waiterId,
                    'name' => $waiter['name'] ?? 'Unknown',
                    'shift_name' => $shift['name'] ?? 'Shift',
                    'clock_in_time' => $shift['clock_in_time'] ?? '-',
                ];
            } elseif (empty($attendance['clock_out'])) {
                $clockedIn++;
            } else {
                $clockedOut++;
            }
        }
        
        // Get last scanned waiter name
        $lastScannedWaiterName = null;
        if (!empty($qrData['last_scanned_by'])) {
            $lastWaiter = $this->firebase->getWaiterById($qrData['last_scanned_by']);
            $lastScannedWaiterName = $lastWaiter['name'] ?? null;
        }
        
        return response()->json([
            'success' => true,
            'qr_value' => $qrData['qr_value'],
            'generated_at' => $qrData['generated_at'],
            'scan_count' => $qrData['scan_count'],
            'last_scanned_by' => $qrData['last_scanned_by'] ?? null,
            'last_scanned_waiter_name' => $lastScannedWaiterName,
            'date' => $today,
            'message' => 'Scan QR ini untuk absen masuk/pulang',
            'stats' => [
                'total_waiters' => count($eligibleWaiters),
                'not_yet' => $notYet,
                'clocked_in' => $clockedIn,
                'clocked_out' => $clockedOut,
            ],
            'waiters_not_yet_clocked' => $waitersNotYetClocked,
        ]);
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
