<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BonusService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class WaiterPerformanceController extends Controller
{
    public function __construct(
        private FirebaseService $firebase,
        private BonusService $bonus
    ) {
    }

    public function show(Request $request, string $id)
    {
        $waiters = $this->firebase->getAllowedEmails();
        $waiter = collect($waiters)->firstWhere('id', $id);

        if (!$waiter) {
            return redirect()->route('admin.waiters.index')->with('error', 'Waiter tidak ditemukan.');
        }

        // Date range: default last 30 days
        $toDate = $request->query('to', now()->format('Y-m-d'));
        $fromDate = $request->query('from', now()->subDays(29)->format('Y-m-d'));

        // Task performance (may fail if Firebase index not set)
        try {
            $taskPerformance = $this->firebase->getWaiterTaskPerformance($id, $fromDate, $toDate);
        } catch (\Exception $e) {
            $taskPerformance = ['total_tasks' => 0, 'total_done' => 0, 'total_overdue' => 0, 'completion_rate' => 0, 'daily_stats' => []];
        }

        // Attendance summary (current month)
        $currentMonth = now()->format('Y-m');
        try {
            $attendanceSummary = $this->firebase->getAttendanceSummary($id, $currentMonth);
        } catch (\Exception $e) {
            $attendanceSummary = [];
        }

        // Bonus history (last 6 months)
        try {
            $bonusHistory = $this->firebase->getWaiterBonusHistory($id, 6);
        } catch (\Exception $e) {
            $bonusHistory = [];
        }

        // Current month bonus progress
        try {
            $bonusProgress = $this->bonus->getWaiterMonthlyProgress($id, $currentMonth);
        } catch (\Exception $e) {
            $bonusProgress = [];
        }

        // Penalties this month
        try {
            $penalties = $this->firebase->getPenalties($currentMonth, $id);
        } catch (\Exception $e) {
            $penalties = [];
        }

        return view('admin.waiters.performance', compact(
            'waiter',
            'fromDate',
            'toDate',
            'taskPerformance',
            'attendanceSummary',
            'bonusHistory',
            'bonusProgress',
            'penalties',
            'currentMonth'
        ));
    }
}
