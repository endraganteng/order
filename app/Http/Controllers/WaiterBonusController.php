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
        
        $config = $this->bonus->getBonusConfig();
        $monthlyPoints = $this->firebase->getMonthlyDailyPoints($waiterId, $month);
        $penalties = $this->bonus->getPenaltiesByMonth($month, $waiterId);
        $salesTarget = $this->bonus->getSalesTarget($waiterId, $month);
        $bonusSummary = $this->bonus->getMonthlyBonusSummary($waiterId, $month);
        $leaderboard = $this->bonus->getLeaderboard($month);
        
        // Calculate current stats
        $totalEarned = 0;
        $totalPenalties = 0;
        $daysScored = 0;
        $perfectDays = 0;
        
        foreach ($monthlyPoints as $date => $record) {
            $totalEarned += (int)($record['daily_total'] ?? 0);
            $daysScored++;
            if (!empty($record['perfect_day_bonus']) && $record['perfect_day_bonus'] > 0) {
                $perfectDays++;
            }
        }
        
        foreach ($penalties as $penalty) {
            $totalPenalties += abs((int)($penalty['points_deducted'] ?? 0));
        }
        
        $netPoints = $totalEarned - $totalPenalties;
        $workingDays = (int)($config['working_days_per_month'] ?? 26);
        $perfectDayBonus = (int)($config['perfect_day_bonus'] ?? 5);
        $dailyMax = (int)($config['daily_max_points'] ?? 30);
        $theoreticalMax = ($dailyMax + $perfectDayBonus) * $workingDays;
        $percentage = $theoreticalMax > 0 ? round(($netPoints / $theoreticalMax) * 100, 1) : 0;
        
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
            'percentage', 'theoreticalMax', 'workingDays'
        ));
    }

    /**
     * API endpoint for AJAX data refresh
     */
    public function apiData(Request $request)
    {
        $waiterId = session('waiter_id');
        $month = $request->get('month', date('Y-m'));
        
        $config = $this->bonus->getBonusConfig();
        $monthlyPoints = $this->firebase->getMonthlyDailyPoints($waiterId, $month);
        $penalties = $this->bonus->getPenaltiesByMonth($month, $waiterId);
        $salesTarget = $this->bonus->getSalesTarget($waiterId, $month);
        $leaderboard = $this->bonus->getLeaderboard($month);
        
        $totalEarned = 0;
        $totalPenalties = 0;
        $perfectDays = 0;
        
        foreach ($monthlyPoints as $record) {
            $totalEarned += (int)($record['daily_total'] ?? 0);
            if (!empty($record['perfect_day_bonus']) && $record['perfect_day_bonus'] > 0) {
                $perfectDays++;
            }
        }
        foreach ($penalties as $penalty) {
            $totalPenalties += abs((int)($penalty['points_deducted'] ?? 0));
        }
        
        $netPoints = $totalEarned - $totalPenalties;
        $workingDays = (int)($config['working_days_per_month'] ?? 26);
        $theoreticalMax = ((int)($config['daily_max_points'] ?? 30) + (int)($config['perfect_day_bonus'] ?? 5)) * $workingDays;
        $percentage = $theoreticalMax > 0 ? round(($netPoints / $theoreticalMax) * 100, 1) : 0;
        
        return response()->json([
            'total_earned' => $totalEarned,
            'total_penalties' => $totalPenalties,
            'net_points' => $netPoints,
            'percentage' => $percentage,
            'perfect_days' => $perfectDays,
            'monthly_points' => $monthlyPoints,
            'penalties' => $penalties,
            'sales_target' => $salesTarget,
            'leaderboard' => $leaderboard,
        ]);
    }
}
