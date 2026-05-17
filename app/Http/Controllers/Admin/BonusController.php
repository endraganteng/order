<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BonusService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    protected BonusService $bonus;
    protected FirebaseService $firebase;

    public function __construct(BonusService $bonus, FirebaseService $firebase)
    {
        $this->bonus = $bonus;
        $this->firebase = $firebase;
    }

    // ===== CONFIG =====
    public function config()
    {
        $config = $this->bonus->getBonusConfig();
        return view('admin.bonus.config', compact('config'));
    }

    public function updateConfig(Request $request)
    {
        // Validate and save config
        $data = $request->validate([
            'working_days_per_month' => 'required|integer|min:1|max:31',
            'total_bonus_pool' => 'required|integer|min:0',
            'perfect_day_bonus' => 'required|integer|min:0|max:20',
            'daily_max_points' => 'required|integer|min:1',
            // SOP launch date — kosong = tidak ada threshold (semua data masuk hitungan).
            'effective_from' => 'nullable|date_format:Y-m-d',
            // Category max points
            'cat_discipline_max' => 'required|integer|min:0',
            'cat_operational_max' => 'required|integer|min:0',
            'cat_service_max' => 'required|integer|min:0',
            'cat_sales_max' => 'required|integer|min:0',
            'cat_attitude_max' => 'required|integer|min:0',
            // Point bonus tiers (percentage-based)
            'pt_tier1_min_pct' => 'required|integer|min:0|max:100',
            'pt_tier1_bonus' => 'required|integer|min:0',
            'pt_tier2_min_pct' => 'required|integer|min:0|max:100',
            'pt_tier2_bonus' => 'required|integer|min:0',
            'pt_tier3_min_pct' => 'required|integer|min:0|max:100',
            'pt_tier3_bonus' => 'required|integer|min:0',
            // Sales bonus tiers
            'st_tier1_min_pct' => 'required|integer|min:0',
            'st_tier1_bonus' => 'required|integer|min:0',
            'st_tier2_min_pct' => 'required|integer|min:0',
            'st_tier2_bonus' => 'required|integer|min:0',
            'st_tier3_min_pct' => 'required|integer|min:0',
            'st_tier3_bonus' => 'required|integer|min:0',
        ]);

        // Build config structure
        $config = [
            'is_active' => true,
            'working_days_per_month' => (int)$data['working_days_per_month'],
            'total_bonus_pool' => (int)$data['total_bonus_pool'],
            'perfect_day_bonus' => (int)$data['perfect_day_bonus'],
            'daily_max_points' => (int)$data['daily_max_points'],
            'effective_from' => trim((string) ($data['effective_from'] ?? '')),
            'point_categories' => [
                'discipline' => ['name' => 'Disiplin', 'max_daily_points' => (int)$data['cat_discipline_max'], 'sort_order' => 1],
                'operational' => ['name' => 'Operasional', 'max_daily_points' => (int)$data['cat_operational_max'], 'sort_order' => 2],
                'service' => ['name' => 'Pelayanan', 'max_daily_points' => (int)$data['cat_service_max'], 'sort_order' => 3],
                'sales' => ['name' => 'Penjualan', 'max_daily_points' => (int)$data['cat_sales_max'], 'sort_order' => 4],
                'attitude' => ['name' => 'Sikap', 'max_daily_points' => (int)$data['cat_attitude_max'], 'sort_order' => 5],
            ],
            'penalty_types' => [
                'late_arrival' => ['label' => 'Terlambat masuk', 'points' => -5],
                'absent' => ['label' => 'Tidak hadir / no-show', 'points' => -15],
                'mandatory_task_missed' => ['label' => 'Tugas wajib tidak dikerjakan', 'points' => -10],
                'careless_work' => ['label' => 'Tugas dikerjakan asal-asalan', 'points' => -10],
                'missing_photo_proof' => ['label' => 'Bukti foto tidak ada', 'points' => -5],
                'valid_complaint' => ['label' => 'Komplain pelanggan valid', 'points' => -10],
            ],
            'point_bonus_tiers' => [
                'tier_1' => ['min_percentage' => (int)$data['pt_tier1_min_pct'], 'bonus_amount' => (int)$data['pt_tier1_bonus']],
                'tier_2' => ['min_percentage' => (int)$data['pt_tier2_min_pct'], 'bonus_amount' => (int)$data['pt_tier2_bonus']],
                'tier_3' => ['min_percentage' => (int)$data['pt_tier3_min_pct'], 'bonus_amount' => (int)$data['pt_tier3_bonus']],
                'tier_4' => ['min_percentage' => 0, 'bonus_amount' => 0],
            ],
            'sales_bonus_tiers' => [
                'tier_1' => ['min_percentage' => (int)$data['st_tier1_min_pct'], 'bonus_amount' => (int)$data['st_tier1_bonus']],
                'tier_2' => ['min_percentage' => (int)$data['st_tier2_min_pct'], 'bonus_amount' => (int)$data['st_tier2_bonus']],
                'tier_3' => ['min_percentage' => (int)$data['st_tier3_min_pct'], 'bonus_amount' => (int)$data['st_tier3_bonus']],
                'tier_4' => ['min_percentage' => 0, 'bonus_amount' => 0],
            ],
        ];

        $this->bonus->updateBonusConfig($config);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Konfigurasi bonus berhasil disimpan']);
        }
        return redirect()->route('admin.bonus.config')->with('success', 'Konfigurasi bonus berhasil disimpan');
    }

    /**
     * Reset all bonus historical data: daily points, penalties, monthly summaries, leaderboards, sales targets.
     * Requires explicit confirmation phrase to prevent accidental deletion.
     */
    public function resetBonusData(Request $request)
    {
        $data = $request->validate([
            'confirmation' => 'required|string',
            'effective_from' => 'nullable|date_format:Y-m-d',
        ]);

        if (trim((string) $data['confirmation']) !== 'RESET BONUS DATA') {
            return back()->withErrors(['confirmation' => 'Konfirmasi tidak cocok. Ketik tepat: RESET BONUS DATA'])->withInput();
        }

        $result = $this->bonus->resetBonusData();

        // Optionally update effective_from in the same step.
        $newEffectiveFrom = trim((string) ($data['effective_from'] ?? ''));
        if ($newEffectiveFrom !== '') {
            $existingConfig = $this->bonus->getBonusConfig();
            $existingConfig['effective_from'] = $newEffectiveFrom;
            $this->bonus->updateBonusConfig($existingConfig);
        }

        $this->firebase->logAuditAction('reset_bonus', 'bonus_data', null, [
            'pre_counts' => $result['counts'],
            'total_removed' => $result['total'],
            'effective_from' => $newEffectiveFrom !== '' ? $newEffectiveFrom : null,
        ]);

        $msg = sprintf(
            'Reset bonus berhasil. %d entri terhapus%s.',
            $result['total'],
            $newEffectiveFrom !== '' ? ' • SOP launch: ' . $newEffectiveFrom : ''
        );

        return redirect()->route('admin.bonus.config')->with('success', $msg);
    }

    // ===== DAILY SCORING =====
    public function dailyScoring(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $month = substr($date, 0, 7);
        $config = $this->bonus->getBonusConfig();
        $waiters = $this->firebase->getAllowedEmails(); // returns all waiters
        $existingScores = $this->firebase->getAllDailyPointsByDate($date);

        // ── BATCH FETCH: read each node ONCE for all waiters ──
        $allAttendance = $this->firebase->getAllAttendanceByDate($date);       // 1 read
        $allTasks      = $this->firebase->getWaiterTasks();                    // 1 read
        $allReports    = $this->firebase->getWaiterActivityReportsByDate($date); // 1 read (via getWaiterActivityReports internally)
        $allPenalties  = $this->bonus->getPenaltiesByMonth($month);            // 1 read

        // Group tasks by waiter+date
        $tasksByWaiter = [];
        foreach ($allTasks as $task) {
            $wid = $task['assigned_waiter_id'] ?? '';
            $taskDate = $task['scheduled_for_date'] ?? '';
            if ($wid && $taskDate === $date) {
                $tasksByWaiter[$wid][$task['id'] ?? ''] = $task;
            }
        }

        // Group reports by waiter
        $reportsByWaiter = [];
        foreach ($allReports as $report) {
            $wid = $report['waiter_id'] ?? '';
            if ($wid) {
                $reportsByWaiter[$wid][] = $report;
            }
        }

        // Group penalties by waiter
        $penaltiesByWaiter = [];
        foreach ($allPenalties as $penalty) {
            $wid = $penalty['waiter_id'] ?? '';
            if ($wid) {
                $penaltiesByWaiter[$wid][] = $penalty;
            }
        }

        // Auto-calculate scores and auto-apply penalties using pre-fetched data
        $autoScores = [];
        $autoPenalties = [];
        foreach ($waiters as $waiter) {
            $wid = $waiter['id'] ?? '';
            $wname = $waiter['name'] ?? $waiter['email'] ?? '';
            if ($wid) {
                $waiterAttendance = $allAttendance[$wid] ?? null;
                $waiterTasks      = $tasksByWaiter[$wid] ?? [];
                $waiterReports    = $reportsByWaiter[$wid] ?? [];
                $waiterPenalties  = $penaltiesByWaiter[$wid] ?? [];

                $autoScores[$wid]    = $this->bonus->autoScoreDailyPoints($wid, $date, $waiterAttendance, $waiterTasks, $waiterReports);
                $autoPenalties[$wid] = $this->bonus->autoApplyPenalties($wid, $wname, $date, $waiterAttendance, $waiterTasks, $waiterPenalties);
            }
        }

        return view('admin.bonus.daily_scoring', compact('date', 'config', 'waiters', 'existingScores', 'autoScores', 'autoPenalties'));
    }

    public function storeDailyScore(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'discipline' => 'required|integer|min:0',
            'operational' => 'required|integer|min:0',
            'attitude' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $this->bonus->saveAdminDailyScore(
            $request->waiter_id,
            $request->date,
            [
                'discipline' => (int)$request->discipline,
                'operational' => (int)$request->operational,
                'attitude' => (int)$request->attitude,
            ],
            $request->notes ?? ''
        );

        return response()->json($result);
    }

    // ===== PENALTIES =====
    public function penalties(Request $request)
    {
        $month = $request->get('month', date('Y-m'));
        $waiterId = $request->get('waiter_id');
        $config = $this->bonus->getBonusConfig();
        $penalties = $this->bonus->getPenaltiesByMonth($month, $waiterId);
        $waiters = $this->firebase->getAllowedEmails();
        
        return view('admin.bonus.penalties', compact('month', 'penalties', 'waiters', 'config', 'waiterId'));
    }

    public function storePenalty(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'waiter_name' => 'required|string',
            'penalty_type' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'reason' => 'required|string|max:500',
            'evidence_photo_url' => 'nullable|string',
            'related_task_id' => 'nullable|string',
        ]);

        $result = $this->bonus->applyPenalty($request->only([
            'waiter_id', 'waiter_name', 'penalty_type', 'date', 'reason', 'evidence_photo_url', 'related_task_id'
        ]));

        return response()->json($result);
    }

    public function destroyPenalty(string $id)
    {
        $this->bonus->deletePenalty($id);
        return response()->json(['success' => true, 'message' => 'Penalti berhasil dihapus']);
    }

    // ===== SALES TARGETS =====
    public function salesTargets(Request $request)
    {
        $month = $request->get('month', date('Y-m'));
        $waiters = $this->firebase->getAllowedEmails();
        $targets = $this->bonus->getAllSalesTargets($month);
        $config = $this->bonus->getBonusConfig();
        
        return view('admin.bonus.sales_targets', compact('month', 'waiters', 'targets', 'config'));
    }

    public function storeSalesTarget(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'month' => 'required|string',
            'target_amount' => 'required|integer|min:0',
            'role' => 'required|string|in:bird_specialist,fishing_specialist',
        ]);

        $this->bonus->setSalesTarget(
            $request->waiter_id,
            $request->month,
            (int)$request->target_amount,
            $request->role
        );

        return response()->json(['success' => true, 'message' => 'Target penjualan berhasil disimpan']);
    }

    public function recordSales(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'amount' => 'required|integer|min:0',
            'items_sold' => 'nullable|integer|min:0',
        ]);

        $month = substr($request->date, 0, 7);
        $this->bonus->recordDailySales(
            $request->waiter_id,
            $request->date,
            (int)$request->amount,
            (int)($request->items_sold ?? 0)
        );

        return response()->json(['success' => true, 'message' => 'Penjualan berhasil dicatat']);
    }

    // ===== MONTHLY SUMMARY =====
    public function monthlySummary(Request $request)
    {
        $month = $request->get('month', date('Y-m'));
        $waiters = $this->firebase->getAllowedEmails();
        $summaries = $this->bonus->getAllMonthlyBonusSummaries($month);
        $config = $this->bonus->getBonusConfig();
        
        return view('admin.bonus.monthly_summary', compact('month', 'waiters', 'summaries', 'config'));
    }

    public function calculateSummary(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'month' => 'required|string',
            'service_percentage' => 'nullable|integer|min:0|max:100',
            'sales_percentage' => 'nullable|integer|min:0|max:100',
        ]);

        $servicePercentage = $request->has('service_percentage') ? (int)$request->service_percentage : null;
        $salesPercentage = $request->has('sales_percentage') ? (int)$request->sales_percentage : null;

        $result = $this->bonus->calculateMonthlyBonus($request->waiter_id, $request->month, $servicePercentage, $salesPercentage);
        return response()->json($result);
    }

    public function finalizeSummary(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'month' => 'required|string',
            'service_percentage' => 'required|integer|min:0|max:100',
            'sales_percentage' => 'required|integer|min:0|max:100',
        ]);

        $result = $this->bonus->finalizeMonthlyBonus(
            $request->waiter_id,
            $request->month,
            (int)$request->service_percentage,
            (int)$request->sales_percentage
        );

        if (($result['already_finalized'] ?? false) === true) {
            return response()->json($result, 409);
        }

        return response()->json($result);
    }

    public function overrideBonus(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|string',
            'month' => 'required|string',
            'amount' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $this->bonus->overrideBonus(
            $request->waiter_id,
            $request->month,
            (int)$request->amount,
            $request->reason
        );

        return response()->json(['success' => true, 'message' => 'Override bonus berhasil']);
    }

    // ===== LEADERBOARD =====
    public function leaderboard(Request $request)
    {
        $month = $request->get('month', date('Y-m'));
        $leaderboard = $this->bonus->getLeaderboard($month);
        $config = $this->bonus->getBonusConfig();
        
        return view('admin.bonus.leaderboard', compact('month', 'leaderboard', 'config'));
    }

    public function generateLeaderboard(Request $request)
    {
        $request->validate(['month' => 'required|string']);
        $result = $this->bonus->generateLeaderboard($request->month);
        return response()->json(['success' => true, 'leaderboard' => $result]);
    }
}
