<?php

namespace App\Http\Controllers;

use App\Services\BonusService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class WaiterBonusController extends Controller
{
    protected BonusService $bonus;
    protected FirebaseService $firebase;

    public function __construct(BonusService $bonus, FirebaseService $firebase)
    {
        $this->bonus = $bonus;
        $this->firebase = $firebase;
    }

    /**
     * Show waiter bonus dashboard
     */
    public function index(Request $request)
    {
        $waiterId = session('waiter_id');
        $waiterName = session('waiter_name', 'Waiter');
        $month = $request->get('month', date('Y-m'));
        $progress = $this->bonus->getWaiterMonthlyProgress((string) $waiterId, $month);
        
        $config = $progress['config'];
        $monthlyPoints = $progress['monthly_points'];
        $penalties = $progress['penalties'];
        $salesTarget = $progress['sales_target'];
        $bonusSummary = $progress['bonus_summary'];
        $leaderboard = $progress['leaderboard'];
        $totalEarned = (int) $progress['total_earned'];
        $totalPenalties = (int) $progress['total_penalties'];
        $netPoints = (int) $progress['net_points'];
        $daysScored = (int) $progress['days_scored'];
        $perfectDays = (int) $progress['perfect_days'];
        $percentage = (float) $progress['percentage'];
        $theoreticalMax = (int) $progress['theoretical_max'];
        $workingDays = (int) $progress['working_days'];
        $dailyMaxWithPerfect = (int) $progress['daily_max_with_perfect'];
        $monthlyServiceMax = (int) $progress['monthly_service_max'];
        $monthlySalesMax = (int) $progress['monthly_sales_max'];
        
        // Find waiter's rank in leaderboard
        $myRank = null;
        if ($leaderboard && !empty($leaderboard['rankings'])) {
            foreach ($leaderboard['rankings'] as $entry) {
                if (($entry['waiter_id'] ?? '') === $waiterId) {
                    $myRank = $entry;
                    break;
                }
            }
        }
        
        return view('waiter.bonus_dashboard', compact(
            'waiterId', 'waiterName', 'month', 'config',
            'monthlyPoints', 'penalties', 'salesTarget', 'bonusSummary',
            'leaderboard', 'myRank',
            'totalEarned', 'totalPenalties', 'netPoints', 'daysScored', 'perfectDays',
            'percentage', 'theoreticalMax', 'workingDays', 'dailyMaxWithPerfect', 'monthlyServiceMax', 'monthlySalesMax'
        ));
    }

    /**
     * API endpoint for AJAX data refresh
     */
    public function apiData(Request $request)
    {
        $waiterId = session('waiter_id');
        $month = $request->get('month', date('Y-m'));
        $progress = $this->bonus->getWaiterMonthlyProgress((string) $waiterId, $month);
        
        return response()->json([
            'total_earned' => $progress['total_earned'],
            'total_penalties' => $progress['total_penalties'],
            'net_points' => $progress['net_points'],
            'percentage' => $progress['percentage'],
            'perfect_days' => $progress['perfect_days'],
            'monthly_points' => $progress['monthly_points'],
            'penalties' => $progress['penalties'],
            'sales_target' => $progress['sales_target'],
            'leaderboard' => $progress['leaderboard'],
            'theoretical_max' => $progress['theoretical_max'],
            'working_days' => $progress['working_days'],
            'daily_max_with_perfect' => $progress['daily_max_with_perfect'],
            'monthly_service_max' => $progress['monthly_service_max'],
            'monthly_sales_max' => $progress['monthly_sales_max'],
        ]);
    }
}
