# Plan: Bonus Produk (Sales Campaign with Claim System)

## Goal
Waiter bisa klaim bonus penjualan produk tertentu via portal, dengan verifikasi dari finance (mirip flow verifikasi cek rak).

## Flow

```
Admin set campaign → Waiter lihat list produk bonus → Waiter klaim (pilih produk + qty + foto struk)
→ Finance verifikasi (approve/reject) → Poin masuk ke total bulanan (ikut tier existing)
```

## Keputusan Final

| Aspek | Keputusan |
|-------|-----------|
| Input | Waiter pilih produk + qty + upload foto struk |
| Approval | Finance verifikasi via portal (mirip verifikasi cek rak) |
| Periode | Custom range date ATAU selamanya (no end date) |
| Limit | Tidak ada limit klaim per hari |
| Conversion | Poin masuk ke total poin bulanan → ikut tier existing |
| Eligible users | Admin pilih (semua/role/spesifik) |

## Data Model (Firebase RTDB)

```
sales_campaigns/
  {campaign_id}/
    title: "Bonus Royal Canin"
    status: "active" | "ended" | "draft"
    start_date: "2026-06-01"
    end_date: "2026-06-30" | null (selamanya)
    created_by: "supervisor"
    created_at: timestamp
    eligible_users:
      type: "all" | "role" | "specific"
      roles: ["pelayan", "kasir"]
      user_ids: [...]
    products/
      {product_key}/
        name: "Royal Canin Indoor 2kg"
        points_per_unit: 10

sales_campaign_claims/
  {claim_id}/
    campaign_id: "..."
    waiter_id: "..."
    waiter_name: "Bagas"
    date: "2026-06-05"
    product_key: "..."
    product_name: "Royal Canin Indoor 2kg"
    quantity: 3
    points_claimed: 30  (qty × points_per_unit)
    photo_url: "https://..."  (foto struk)
    status: "pending" | "approved" | "rejected"
    submitted_at: timestamp
    verified_by: "annisa"
    verified_at: timestamp
    reject_reason: "..." (if rejected)
```

## Step-by-Step Implementation

### Phase 1: Backend Service (~120 LOC)
File: `app/Services/SalesCampaignService.php` (NEW — pisah dari BonusService yang sudah 1935 LOC)

Methods:
- `createCampaign(array $data): string`
- `updateCampaign(string $id, array $data): void`
- `getActiveCampaigns(): array`
- `getCampaignById(string $id): ?array`
- `deleteCampaign(string $id): void`
- `getEligibleCampaignsForUser(string $userId): array`
- `submitClaim(array $data): array`
- `getClaimsByStatus(string $status): array`
- `getClaimsByCampaign(string $campaignId): array`
- `getClaimsByUser(string $userId): array`
- `verifyClaim(string $claimId, string $status, string $verifiedBy, ?string $reason): array`
- `getUserCampaignPoints(string $userId, string $month): int` — total approved points for monthly bonus

### Phase 2: Admin Controller + Routes (~80 LOC)
File: `app/Http/Controllers/Admin/SalesCampaignController.php` (NEW)

Routes:
- `GET /admin/bonus/campaigns` — list campaigns
- `GET /admin/bonus/campaigns/create` — create form
- `POST /admin/bonus/campaigns` — store
- `GET /admin/bonus/campaigns/{id}/edit` — edit form
- `PUT /admin/bonus/campaigns/{id}` — update
- `DELETE /admin/bonus/campaigns/{id}` — delete

### Phase 3: Admin UI — Campaign Management (~200 LOC)
File: `resources/views/admin/bonus/campaigns.blade.php` (NEW)

- List campaigns (active/ended/draft)
- Create/edit: title, date range, products + poin, eligible users
- Product input: nama produk + poin (manual input, bukan dari Olsera)

### Phase 4: Waiter Portal — Bonus Produk (~180 LOC)
File: `resources/views/waiter/bonus_produk.blade.php` (NEW)

- List produk yang ada bonus (dari active campaigns yang user eligible)
- Button "Klaim Bonus Penjualan"
- Form: pilih produk, input qty, upload foto struk
- History klaim (pending/approved/rejected)

Controller: tambah method di `WaiterController.php` atau `WaiterBonusController.php`

### Phase 5: Finance Verification (~100 LOC)
File: extend existing finance verification view (mirip rack recheck verification)

- List klaim pending
- Lihat foto struk
- Approve / Reject (dengan alasan)
- Bisa filter by campaign, by waiter, by date

### Phase 6: Integration with Monthly Bonus (~30 LOC)
File: `app/Services/BonusService.php`

- Di `calculateMonthlyBonus()`: tambah campaign points ke net_points
- Campaign points = sum of approved claims dalam bulan tersebut
- Masuk ke theoretical_max juga? → TIDAK, karena campaign bonus = extra di atas daily max

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `app/Services/SalesCampaignService.php` | CREATE |
| `app/Http/Controllers/Admin/SalesCampaignController.php` | CREATE |
| `resources/views/admin/bonus/campaigns.blade.php` | CREATE |
| `resources/views/waiter/bonus_produk.blade.php` | CREATE |
| `app/Http/Controllers/WaiterBonusController.php` | MODIFY — add claim methods |
| `app/Services/BonusService.php` | MODIFY — integrate campaign points |
| `routes/web.php` | MODIFY — add routes |
| `resources/views/waiter/layout or nav` | MODIFY — add menu "Bonus Produk" |
| Finance verification view (existing) | MODIFY — add campaign claims tab |

## Estimated LOC: ~710 (split into 3 chunks)

## Chunk Plan
1. **Chunk 1** (~250 LOC): Service + Admin controller + routes
2. **Chunk 2** (~250 LOC): Admin UI + Waiter portal view
3. **Chunk 3** (~210 LOC): Finance verification + monthly bonus integration

---

## Risks
- BonusService.php sudah 1935 LOC → pakai service terpisah (SalesCampaignService)
- Photo upload: perlu storage (Firebase Storage atau local disk?) → ikut pattern existing (foto cek rak)
- Campaign points di monthly bonus: perlu hati-hati supaya tidak double count
