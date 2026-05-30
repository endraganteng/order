<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesCampaignService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class SalesCampaignController extends Controller
{
    protected SalesCampaignService $campaign;
    protected FirebaseService $firebase;

    public function __construct(SalesCampaignService $campaign, FirebaseService $firebase)
    {
        $this->campaign = $campaign;
        $this->firebase = $firebase;
    }

    public function index()
    {
        $campaigns = $this->campaign->getAllCampaigns();
        $waiters = $this->firebase->getAllowedEmails();
        $masterProducts = $this->firebase->getActiveProducts();

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
            $products[$key] = [
                'name' => $product['name'],
                'points_per_unit' => (int) $product['points_per_unit'],
            ];
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
        ]);

        $eligibleUsers = ['type' => $data['eligible_type']];
        if ($data['eligible_type'] === 'role') {
            $eligibleUsers['roles'] = $data['eligible_roles'] ?? [];
        } elseif ($data['eligible_type'] === 'specific') {
            $eligibleUsers['user_ids'] = $data['eligible_user_ids'] ?? [];
        }

        $products = [];
        foreach ($data['products'] as $i => $product) {
            $key = 'product_' . $i;
            $products[$key] = [
                'name' => $product['name'],
                'points_per_unit' => (int) $product['points_per_unit'],
            ];
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
        ]);
    }
}
