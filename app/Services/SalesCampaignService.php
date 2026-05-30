<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class SalesCampaignService
{
    protected Database $database;
    protected FirebaseService $firebase;

    public function __construct(Database $database, FirebaseService $firebase)
    {
        $this->database = $database;
        $this->firebase = $firebase;
    }

    // =========================================================================
    //  CAMPAIGN CRUD
    // =========================================================================

    public function createCampaign(array $data): string
    {
        $ref = $this->database->getReference('sales_campaigns')->push([
            'title' => $data['title'],
            'status' => $data['status'] ?? 'active',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null, // null = selamanya
            'products' => $data['products'] ?? [],
            'eligible_users' => $data['eligible_users'] ?? ['type' => 'all'],
            'created_by' => $data['created_by'] ?? 'admin',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return $ref->getKey();
    }

    public function updateCampaign(string $id, array $data): void
    {
        $data['updated_at'] = time();
        $this->database->getReference('sales_campaigns/' . $id)->update($data);
    }

    public function getCampaignById(string $id): ?array
    {
        $snapshot = $this->database->getReference('sales_campaigns/' . $id)->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        $campaign = (array) $snapshot->getValue();
        $campaign['id'] = $id;

        return $campaign;
    }

    public function getAllCampaigns(): array
    {
        $snapshot = $this->database->getReference('sales_campaigns')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $campaigns = [];
        foreach ((array) $snapshot->getValue() as $id => $data) {
            if (! is_array($data)) {
                continue;
            }
            $data['id'] = $id;
            $campaigns[] = $data;
        }

        // Sort by created_at desc
        usort($campaigns, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));

        return $campaigns;
    }

    public function getActiveCampaigns(): array
    {
        $all = $this->getAllCampaigns();
        $today = date('Y-m-d');

        return array_values(array_filter($all, function ($c) use ($today) {
            if (($c['status'] ?? '') !== 'active') {
                return false;
            }
            // Check date range
            $start = $c['start_date'] ?? null;
            $end = $c['end_date'] ?? null;

            if ($start && $today < $start) {
                return false;
            }
            if ($end && $today > $end) {
                return false;
            }

            return true;
        }));
    }

    public function deleteCampaign(string $id): void
    {
        $this->database->getReference('sales_campaigns/' . $id)->remove();
    }

    // =========================================================================
    //  ELIGIBLE CAMPAIGNS FOR USER
    // =========================================================================

    public function getEligibleCampaignsForUser(string $userId): array
    {
        $active = $this->getActiveCampaigns();
        $waiter = $this->firebase->getWaiterById($userId);
        $waiterRole = (string) ($waiter['waiter_role'] ?? $waiter['role'] ?? '');

        return array_values(array_filter($active, function ($campaign) use ($userId, $waiterRole) {
            $eligible = $campaign['eligible_users'] ?? ['type' => 'all'];
            $type = $eligible['type'] ?? 'all';

            if ($type === 'all') {
                return true;
            }

            if ($type === 'role') {
                $roles = (array) ($eligible['roles'] ?? []);
                return in_array($waiterRole, $roles, true);
            }

            if ($type === 'specific') {
                $userIds = (array) ($eligible['user_ids'] ?? []);
                return in_array($userId, $userIds, true);
            }

            return false;
        }));
    }

    // =========================================================================
    //  CLAIMS
    // =========================================================================

    public function submitClaim(array $data): array
    {
        $campaignId = $data['campaign_id'];
        $campaign = $this->getCampaignById($campaignId);

        if (! $campaign) {
            return ['success' => false, 'message' => 'Campaign tidak ditemukan.'];
        }

        // Find product in campaign
        $products = (array) ($campaign['products'] ?? []);
        $productKey = $data['product_key'];
        $product = null;

        foreach ($products as $key => $p) {
            if ((string) $key === $productKey || ($p['name'] ?? '') === $productKey) {
                $product = (array) $p;
                $product['key'] = $key;
                break;
            }
        }

        if (! $product) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan dalam campaign.'];
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $pointsPerUnit = (int) ($product['points_per_unit'] ?? 0);
        $pointsClaimed = $quantity * $pointsPerUnit;

        $claimData = [
            'campaign_id' => $campaignId,
            'campaign_title' => $campaign['title'] ?? '',
            'waiter_id' => $data['waiter_id'],
            'waiter_name' => $data['waiter_name'],
            'date' => $data['date'] ?? date('Y-m-d'),
            'product_key' => $product['key'],
            'product_name' => $product['name'] ?? '',
            'quantity' => $quantity,
            'points_per_unit' => $pointsPerUnit,
            'points_claimed' => $pointsClaimed,
            'photo_url' => $data['photo_url'] ?? null,
            'status' => 'pending',
            'submitted_at' => time(),
            'verified_by' => null,
            'verified_at' => null,
            'reject_reason' => null,
        ];

        $ref = $this->database->getReference('sales_campaign_claims')->push($claimData);

        return [
            'success' => true,
            'claim_id' => $ref->getKey(),
            'points_claimed' => $pointsClaimed,
            'message' => "Klaim berhasil disubmit ({$pointsClaimed} poin). Menunggu verifikasi finance.",
        ];
    }

    public function getClaimsByStatus(string $status, ?string $campaignId = null): array
    {
        $snapshot = $this->database->getReference('sales_campaign_claims')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $claims = [];
        foreach ((array) $snapshot->getValue() as $id => $claim) {
            if (! is_array($claim)) {
                continue;
            }
            if (($claim['status'] ?? '') !== $status) {
                continue;
            }
            if ($campaignId && ($claim['campaign_id'] ?? '') !== $campaignId) {
                continue;
            }
            $claim['id'] = $id;
            $claims[] = $claim;
        }

        // Sort by submitted_at desc
        usort($claims, fn($a, $b) => ($b['submitted_at'] ?? 0) <=> ($a['submitted_at'] ?? 0));

        return $claims;
    }

    public function getClaimsByUser(string $userId, ?string $month = null): array
    {
        $snapshot = $this->database->getReference('sales_campaign_claims')->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $claims = [];
        foreach ((array) $snapshot->getValue() as $id => $claim) {
            if (! is_array($claim)) {
                continue;
            }
            if (($claim['waiter_id'] ?? '') !== $userId) {
                continue;
            }
            if ($month && substr($claim['date'] ?? '', 0, 7) !== $month) {
                continue;
            }
            $claim['id'] = $id;
            $claims[] = $claim;
        }

        usort($claims, fn($a, $b) => ($b['submitted_at'] ?? 0) <=> ($a['submitted_at'] ?? 0));

        return $claims;
    }

    public function verifyClaim(string $claimId, string $status, string $verifiedBy, ?string $reason = null): array
    {
        $path = 'sales_campaign_claims/' . $claimId;
        $snapshot = $this->database->getReference($path)->getSnapshot();

        if (! $snapshot->exists()) {
            return ['success' => false, 'message' => 'Klaim tidak ditemukan.'];
        }

        $claim = (array) $snapshot->getValue();

        if (($claim['status'] ?? '') !== 'pending') {
            return ['success' => false, 'message' => 'Klaim sudah diverifikasi sebelumnya.'];
        }

        $updates = [
            'status' => $status, // 'approved' or 'rejected'
            'verified_by' => $verifiedBy,
            'verified_at' => time(),
        ];

        if ($status === 'rejected' && $reason) {
            $updates['reject_reason'] = $reason;
        }

        $this->database->getReference($path)->update($updates);

        return [
            'success' => true,
            'message' => $status === 'approved'
                ? 'Klaim disetujui. Poin ' . ($claim['points_claimed'] ?? 0) . ' ditambahkan.'
                : 'Klaim ditolak.',
            'points' => $status === 'approved' ? (int) ($claim['points_claimed'] ?? 0) : 0,
        ];
    }

    // =========================================================================
    //  POINTS AGGREGATION (for monthly bonus integration)
    // =========================================================================

    /**
     * Get total approved campaign points for a user in a given month.
     */
    public function getUserCampaignPoints(string $userId, string $month): int
    {
        $claims = $this->getClaimsByUser($userId, $month);
        $total = 0;

        foreach ($claims as $claim) {
            if (($claim['status'] ?? '') === 'approved') {
                $total += (int) ($claim['points_claimed'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Get campaign points breakdown for a user in a month (for dashboard display).
     */
    public function getUserCampaignBreakdown(string $userId, string $month): array
    {
        $claims = $this->getClaimsByUser($userId, $month);

        $approved = 0;
        $pending = 0;
        $rejected = 0;
        $approvedClaims = [];
        $pendingClaims = [];

        foreach ($claims as $claim) {
            $status = $claim['status'] ?? '';
            $points = (int) ($claim['points_claimed'] ?? 0);

            if ($status === 'approved') {
                $approved += $points;
                $approvedClaims[] = $claim;
            } elseif ($status === 'pending') {
                $pending += $points;
                $pendingClaims[] = $claim;
            } else {
                $rejected += $points;
            }
        }

        return [
            'total_approved' => $approved,
            'total_pending' => $pending,
            'total_rejected' => $rejected,
            'approved_claims' => $approvedClaims,
            'pending_claims' => $pendingClaims,
            'all_claims' => $claims,
        ];
    }
}
