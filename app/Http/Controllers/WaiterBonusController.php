<?php

namespace App\Http\Controllers;

use App\Services\BonusService;
use App\Services\FirebaseService;
use App\Services\SalesCampaignService;
use Illuminate\Http\Request;

class WaiterBonusController extends Controller
{
    protected BonusService $bonus;
    protected FirebaseService $firebase;
    protected SalesCampaignService $campaign;

    public function __construct(BonusService $bonus, FirebaseService $firebase, SalesCampaignService $campaign)
    {
        $this->bonus = $bonus;
        $this->firebase = $firebase;
        $this->campaign = $campaign;
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
        $pointEvents = $this->bonus->getWaiterPointEvents((string) $waiterId, $month);

        // Fetch waiter role for sales eligibility check in dashboard.
        $waiterRole = '';
        try {
            $waiter = $this->firebase->getWaiterById((string) $waiterId);
            $waiterRole = (string) ($waiter['waiter_role'] ?? $waiter['role'] ?? '');
        } catch (\Throwable $e) {
            // Tidak fatal; treat as unknown role (akan dianggap eligible default).
            $waiterRole = '';
        }
        
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
        // BUG FIX (#4): Campaign points & breakdown for dashboard display
        $campaignPoints = (int) ($progress['campaign_points'] ?? 0);
        $campaignBreakdown = (array) ($progress['campaign_breakdown'] ?? []);
        
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
            'waiterId', 'waiterName', 'waiterRole', 'month', 'config',
            'monthlyPoints', 'penalties', 'salesTarget', 'bonusSummary',
            'leaderboard', 'myRank', 'pointEvents',
            'totalEarned', 'totalPenalties', 'netPoints', 'daysScored', 'perfectDays',
            'percentage', 'theoreticalMax', 'workingDays', 'dailyMaxWithPerfect', 'monthlyServiceMax', 'monthlySalesMax',
            'campaignPoints', 'campaignBreakdown'
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
        $pointEvents = $this->bonus->getWaiterPointEvents((string) $waiterId, $month);
        
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
            'point_events' => $pointEvents,
            // BUG FIX (#4): expose campaign points in API too
            'campaign_points' => $progress['campaign_points'] ?? 0,
            'campaign_breakdown' => $progress['campaign_breakdown'] ?? [],
        ]);
    }

    // =========================================================================
    //  BONUS PRODUK (Sales Campaign Claims)
    // =========================================================================

    /**
     * Show bonus produk page — list eligible campaigns + claim form.
     */
    public function bonusProduk()
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        $month = date('Y-m');

        $campaigns = $this->campaign->getEligibleCampaignsForUser($waiterId);
        $breakdown = $this->campaign->getUserCampaignBreakdown($waiterId, $month);

        // Flatten products from all eligible campaigns + sort by points DESC.
        // This gives waiter a single sorted list focused on highest-value products.
        $sortedProducts = [];
        foreach ($campaigns as $campaign) {
            $cId = (string) ($campaign['id'] ?? '');
            $cTitle = (string) ($campaign['title'] ?? '');
            $cEnd = $campaign['end_date'] ?? null;
            foreach ((array) ($campaign['products'] ?? []) as $key => $product) {
                if (! is_array($product)) {
                    continue;
                }
                $points = (int) ($product['points_per_unit'] ?? 0);
                if ($points <= 0) {
                    continue;
                }
                $hasQuota = isset($product['quota']) && $product['quota'] !== null && $product['quota'] !== '';
                $quotaCap = $hasQuota ? (int) $product['quota'] : null;
                $quotaClaimed = (int) ($product['quota_claimed'] ?? 0);
                $quotaRemaining = $hasQuota ? max(0, $quotaCap - $quotaClaimed) : null;

                $sortedProducts[] = [
                    'campaign_id' => $cId,
                    'campaign_title' => $cTitle,
                    'campaign_end_date' => $cEnd,
                    'product_key' => (string) $key,
                    'name' => (string) ($product['name'] ?? '-'),
                    'points_per_unit' => $points,
                    'has_quota' => $hasQuota,
                    'quota' => $quotaCap,
                    'quota_remaining' => $quotaRemaining,
                ];
            }
        }

        usort($sortedProducts, function ($a, $b) {
            // Highest points first; tie-break alphabetical
            if ($a['points_per_unit'] !== $b['points_per_unit']) {
                return $b['points_per_unit'] <=> $a['points_per_unit'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return view('waiter.bonus_produk', compact(
            'waiterId', 'waiterName', 'month', 'campaigns', 'breakdown', 'sortedProducts'
        ));
    }

    /**
     * Submit a claim for bonus produk.
     * Supports both single-product (legacy) and multi-product (new) payloads.
     */
    public function submitClaim(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        // Multi-item payload detection
        if ($request->has('items') && is_array($request->input('items'))) {
            $request->validate([
                'items' => 'required|array|min:1|max:20',
                'items.*.campaign_id' => 'required|string',
                'items.*.product_key' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'photo_proof' => 'required|string|max:5000000',
            ]);

            $items = $request->input('items', []);
            $photoProof = (string) $request->input('photo_proof');
            $today = date('Y-m-d');

            $results = [];
            $successCount = 0;
            $totalPoints = 0;
            $errors = [];

            foreach ($items as $idx => $item) {
                $r = $this->campaign->submitClaim([
                    'campaign_id' => (string) ($item['campaign_id'] ?? ''),
                    'waiter_id' => $waiterId,
                    'waiter_name' => $waiterName,
                    'product_key' => (string) ($item['product_key'] ?? ''),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'photo_url' => $photoProof,
                    'date' => $today,
                ]);
                $results[] = $r;
                if ($r['success'] ?? false) {
                    $successCount++;
                    $totalPoints += (int) ($r['points_claimed'] ?? 0);
                } else {
                    $errors[] = 'Item #'.($idx + 1).': '.($r['message'] ?? 'gagal');
                }
            }

            return response()->json([
                'success' => $successCount > 0,
                'submitted' => $successCount,
                'total_items' => count($items),
                'total_points' => $totalPoints,
                'errors' => $errors,
                'results' => $results,
                'message' => $successCount === count($items)
                    ? "Berhasil submit {$successCount} klaim ({$totalPoints} poin total). Menunggu verifikasi finance."
                    : "Submit {$successCount}/".count($items)." berhasil. Cek detail di bawah.",
            ]);
        }

        // Legacy single-item payload
        $request->validate([
            'campaign_id' => 'required|string',
            'product_key' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'photo_proof' => 'required|string|max:5000000',
        ]);

        $result = $this->campaign->submitClaim([
            'campaign_id' => $request->campaign_id,
            'waiter_id' => $waiterId,
            'waiter_name' => $waiterName,
            'product_key' => $request->product_key,
            'quantity' => (int) $request->quantity,
            'photo_url' => $request->photo_proof,
            'date' => date('Y-m-d'),
        ]);

        return response()->json($result);
    }

    /**
     * Get claim history for current waiter (AJAX).
     */
    public function claimHistory(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $month = $request->get('month', date('Y-m'));
        $claims = $this->campaign->getClaimsByUser($waiterId, $month);

        return response()->json(['success' => true, 'claims' => $claims]);
    }

    // =========================================================================
    //  FINANCE: VERIFY CAMPAIGN CLAIMS
    // =========================================================================

    /**
     * Show pending claims for finance verification.
     */
    public function verifyClaims()
    {
        $waiterRole = (string) session('waiter_role', '');

        if (! in_array($waiterRole, ['finance', 'supervisor'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Finance/Supervisor yang dapat verifikasi.'], 403);
        }

        $pendingClaims = $this->campaign->getClaimsByStatus('pending');
        $recentApproved = $this->campaign->getClaimsByStatus('approved');
        $recentRejected = $this->campaign->getClaimsByStatus('rejected');

        // Limit recent to last 20
        $recentApproved = array_slice($recentApproved, 0, 20);
        $recentRejected = array_slice($recentRejected, 0, 20);

        return response()->json([
            'success' => true,
            'pending' => $pendingClaims,
            'recent_approved' => $recentApproved,
            'recent_rejected' => $recentRejected,
        ]);
    }

    /**
     * Process claim verification (approve/reject).
     */
    public function processClaimVerification(Request $request, string $id)
    {
        $waiterRole = (string) session('waiter_role', '');

        if (! in_array($waiterRole, ['finance', 'supervisor'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Finance/Supervisor yang dapat verifikasi.'], 403);
        }

        $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $verifiedBy = (string) session('waiter_name', 'Finance');

        $result = $this->campaign->verifyClaim(
            $id,
            $request->status,
            $verifiedBy,
            $request->reason
        );

        return response()->json($result);
    }
}
