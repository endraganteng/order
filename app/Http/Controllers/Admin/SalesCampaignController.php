<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesCampaignService;
use App\Services\FirebaseService;
use App\Services\ProductFirebaseService;
use Illuminate\Http\Request;

class SalesCampaignController extends Controller
{
    protected SalesCampaignService $campaign;
    protected FirebaseService $firebase;
    protected ProductFirebaseService $product;

    public function __construct(SalesCampaignService $campaign, FirebaseService $firebase, ProductFirebaseService $product)
    {
        $this->campaign = $campaign;
        $this->firebase = $firebase;
        $this->product = $product;
    }

    public function index()
    {
        $campaigns = $this->campaign->getAllCampaigns();
        $waiters = $this->firebase->getAllowedEmails();
        $masterProducts = $this->product->getActiveProducts();

        return view('admin.bonus.campaigns', compact('campaigns', 'waiters', 'masterProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'status' => 'required|string|in:active,draft',
            'eligible_type' => 'required|string|in:all,role,specific',
            'eligible_roles' => 'nullable|array',
            'eligible_roles.*' => 'string',
            'eligible_user_ids' => 'nullable|array',
            'eligible_user_ids.*' => 'string',
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:200',
            'products.*.points_per_unit' => 'required|integer|min:1',
            'products.*.quota' => 'nullable|integer|min:1',
        ]);

        $eligibleUsers = ['type' => $data['eligible_type']];
        if ($data['eligible_type'] === 'role') {
            $eligibleUsers['roles'] = $data['eligible_roles'] ?? [];
        } elseif ($data['eligible_type'] === 'specific') {
            $eligibleUsers['user_ids'] = $data['eligible_user_ids'] ?? [];
        }

        // Index products by sanitized key
        $products = [];
        foreach ($data['products'] as $i => $product) {
            $key = 'product_' . $i;
            $entry = [
                'name' => $product['name'],
                'points_per_unit' => (int) $product['points_per_unit'],
            ];
            if (isset($product['quota']) && $product['quota'] !== null && $product['quota'] !== '') {
                $entry['quota'] = (int) $product['quota'];
                $entry['quota_claimed'] = 0;
            }
            $products[$key] = $entry;
        }

        $id = $this->campaign->createCampaign([
            'title' => $data['title'],
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'status' => $data['status'],
            'products' => $products,
            'eligible_users' => $eligibleUsers,
            'created_by' => session('admin_email') ?? 'admin',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'id' => $id, 'message' => 'Campaign berhasil dibuat.']);
        }

        return redirect()->route('admin.bonus.campaigns')->with('success', 'Campaign berhasil dibuat.');
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'status' => 'required|string|in:active,draft,ended',
            'eligible_type' => 'required|string|in:all,role,specific',
            'eligible_roles' => 'nullable|array',
            'eligible_roles.*' => 'string',
            'eligible_user_ids' => 'nullable|array',
            'eligible_user_ids.*' => 'string',
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:200',
            'products.*.points_per_unit' => 'required|integer|min:1',
            'products.*.quota' => 'nullable|integer|min:1',
        ]);

        $eligibleUsers = ['type' => $data['eligible_type']];
        if ($data['eligible_type'] === 'role') {
            $eligibleUsers['roles'] = $data['eligible_roles'] ?? [];
        } elseif ($data['eligible_type'] === 'specific') {
            $eligibleUsers['user_ids'] = $data['eligible_user_ids'] ?? [];
        }

        // BUG FIX (#5): Preserve existing quota_claimed counter when updating
        // products. Match by name+points_per_unit since UI doesn't send keys.
        $existingCampaign = $this->campaign->getCampaignById($id);
        $existingProducts = (array) ($existingCampaign['products'] ?? []);
        $existingByName = [];
        foreach ($existingProducts as $oldProduct) {
            $name = (string) ($oldProduct['name'] ?? '');
            if ($name !== '') {
                $existingByName[mb_strtolower($name)] = $oldProduct;
            }
        }

        $products = [];
        foreach ($data['products'] as $i => $product) {
            $key = 'product_' . $i;
            $entry = [
                'name' => $product['name'],
                'points_per_unit' => (int) $product['points_per_unit'],
            ];
            if (isset($product['quota']) && $product['quota'] !== null && $product['quota'] !== '') {
                $entry['quota'] = (int) $product['quota'];
                // Preserve quota_claimed if same product name exists
                $matched = $existingByName[mb_strtolower($product['name'])] ?? null;
                $entry['quota_claimed'] = (int) ($matched['quota_claimed'] ?? 0);
            }
            $products[$key] = $entry;
        }

        $this->campaign->updateCampaign($id, [
            'title' => $data['title'],
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'status' => $data['status'],
            'products' => $products,
            'eligible_users' => $eligibleUsers,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Campaign berhasil diupdate.']);
        }

        return redirect()->route('admin.bonus.campaigns')->with('success', 'Campaign berhasil diupdate.');
    }

    public function destroy(string $id)
    {
        $this->campaign->deleteCampaign($id);

        return response()->json(['success' => true, 'message' => 'Campaign berhasil dihapus.']);
    }

    /**
     * Get campaign detail with claims summary (AJAX).
     */
    public function show(string $id)
    {
        $campaign = $this->campaign->getCampaignById($id);

        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign tidak ditemukan.'], 404);
        }

        $pendingClaims = $this->campaign->getClaimsByStatus('pending', $id);
        $approvedClaims = $this->campaign->getClaimsByStatus('approved', $id);
        $rejectedClaims = $this->campaign->getClaimsByStatus('rejected', $id);

        // Fitur #8: Stats per produk - aggregate dari approved claims
        $productStats = [];
        $products = (array) ($campaign['products'] ?? []);
        foreach ($products as $key => $p) {
            $productStats[$key] = [
                'name' => $p['name'] ?? '-',
                'points_per_unit' => (int) ($p['points_per_unit'] ?? 0),
                'quota' => $p['quota'] ?? null,
                'quota_claimed' => (int) ($p['quota_claimed'] ?? 0),
                'approved_qty' => 0,
                'approved_points' => 0,
                'pending_qty' => 0,
                'rejected_qty' => 0,
                'unique_waiters' => 0,
            ];
        }
        $waiterTracker = []; // [productKey => set of waiterIds]
        foreach ($approvedClaims as $cl) {
            $pk = (string) ($cl['product_key'] ?? '');
            if (! isset($productStats[$pk])) continue;
            $productStats[$pk]['approved_qty'] += (int) ($cl['quantity'] ?? 0);
            $productStats[$pk]['approved_points'] += (int) ($cl['points_claimed'] ?? 0);
            $waiterTracker[$pk][(string) ($cl['waiter_id'] ?? '')] = true;
        }
        foreach ($pendingClaims as $cl) {
            $pk = (string) ($cl['product_key'] ?? '');
            if (! isset($productStats[$pk])) continue;
            $productStats[$pk]['pending_qty'] += (int) ($cl['quantity'] ?? 0);
        }
        foreach ($rejectedClaims as $cl) {
            $pk = (string) ($cl['product_key'] ?? '');
            if (! isset($productStats[$pk])) continue;
            $productStats[$pk]['rejected_qty'] += (int) ($cl['quantity'] ?? 0);
        }
        foreach ($waiterTracker as $pk => $waiters) {
            $productStats[$pk]['unique_waiters'] = count($waiters);
        }

        // Fitur #9: Top performer leaderboard - aggregate approved claims per waiter
        $leaderboardMap = []; // [waiterId => {name, total_qty, total_points, claim_count}]
        foreach ($approvedClaims as $cl) {
            $wid = (string) ($cl['waiter_id'] ?? '');
            if ($wid === '') continue;
            if (! isset($leaderboardMap[$wid])) {
                $leaderboardMap[$wid] = [
                    'waiter_id' => $wid,
                    'waiter_name' => $cl['waiter_name'] ?? '-',
                    'total_qty' => 0,
                    'total_points' => 0,
                    'claim_count' => 0,
                ];
            }
            $leaderboardMap[$wid]['total_qty'] += (int) ($cl['quantity'] ?? 0);
            $leaderboardMap[$wid]['total_points'] += (int) ($cl['points_claimed'] ?? 0);
            $leaderboardMap[$wid]['claim_count']++;
        }
        $leaderboard = array_values($leaderboardMap);
        usort($leaderboard, fn($a, $b) => $b['total_points'] <=> $a['total_points']);

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
            'claims' => [
                'pending' => $pendingClaims,
                'approved' => $approvedClaims,
                'rejected' => $rejectedClaims,
            ],
            'stats' => [
                'total_pending' => count($pendingClaims),
                'total_approved' => count($approvedClaims),
                'total_rejected' => count($rejectedClaims),
                'total_points_approved' => array_sum(array_column($approvedClaims, 'points_claimed')),
            ],
            'product_stats' => array_values($productStats),
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Verify (approve/reject) a campaign claim from admin panel.
     * Mirror dari WaiterBonusController::processClaimVerification, dipanggil oleh admin.
     */
    public function verifyClaim(Request $request, string $id, string $claimId)
    {
        $data = $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $verifiedBy = (string) (session('admin_name') ?? session('admin_email') ?? 'Admin');

        $result = $this->campaign->verifyClaim(
            $claimId,
            $data['status'],
            $verifiedBy,
            $data['reason'] ?? null
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
