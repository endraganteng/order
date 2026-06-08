# Plan: Sistem Bonus Penjualan Per Produk

## Goal
Membuat fitur bonus penjualan di mana admin bisa:
1. Memilih produk tertentu yang eligible untuk bonus
2. Set poin per produk (berbeda-beda tergantung produk)
3. Assign ke user tertentu (atau semua) dalam periode tertentu
4. Track penjualan produk tersebut per user → akumulasi poin

## Current State (Sistem Bonus yang Sudah Ada)

Sistem bonus saat ini sudah punya:

### Struktur Poin Harian (6 kategori)
- **Disiplin** (max 5/hari) — daily
- **Operasional** (max 10/hari) — daily, auto dari task completion
- **Pelayanan** (max 5/hari) — monthly percentage
- **Penjualan** (max 5/hari) — monthly percentage
- **Sikap** (max 5/hari) — daily
- **Recheck Rak** (max 10/hari) — daily

### Sales Target (Sudah Ada)
- Per waiter, per bulan
- Role: `bird_specialist` atau `fishing_specialist`
- Target dalam Rupiah (total omzet)
- Achievement di-record manual via `recordDailySales()`
- Bonus berdasarkan tier (100%→200k, 80%→150k, 60%→100k)

### Yang BELUM Ada (Gap)
- ❌ Tidak ada tracking per PRODUK (hanya total omzet)
- ❌ Tidak ada poin per produk yang bisa di-customize
- ❌ Tidak ada periode campaign (misal: "minggu ini jual X dapat 5 poin")
- ❌ Tidak ada integrasi otomatis dengan Olsera (sales data per produk per kasir)

---

## Proposed Approach: "Sales Campaign" System

### Konsep
Admin membuat **campaign** bonus penjualan:
- Pilih produk (dari Olsera product list)
- Set poin per unit terjual
- Set periode (tanggal mulai - selesai)
- Pilih user yang eligible (semua / role tertentu / user spesifik)
- Sistem otomatis track dari data Olsera → akumulasi poin

### Data Model (Firebase RTDB)

```
sales_campaigns/
  {campaign_id}/
    title: "Promo Royal Canin Juni"
    status: "active" | "ended" | "draft"
    start_date: "2026-06-01"
    end_date: "2026-06-30"
    created_by: "supervisor"
    created_at: timestamp
    
    products/
      {sku_or_product_id}/
        name: "Royal Canin Indoor 2kg"
        sku: "RC-IND-2KG"
        points_per_unit: 10
        max_points_per_day: 50  (optional cap)
    
    eligible_users/
      type: "all" | "role" | "specific"
      roles: ["kasir", "pelayan"]  (if type=role)
      user_ids: ["-Oipay...", "-Oqrpf..."]  (if type=specific)

sales_campaign_progress/
  {campaign_id}/
    {user_id}/
      total_points: 150
      total_units_sold: 23
      daily_log/
        "2026-06-01"/
          {product_id}/
            units: 3
            points: 30
            synced_from: "olsera"
            synced_at: timestamp
```

### Sync Strategy
- **Opsi A: Manual record** — Admin/kasir input penjualan produk campaign
- **Opsi B: Auto dari Olsera** — Cron job cek Olsera sales data, match SKU dengan campaign products, auto-credit poin
- **Recommended: Opsi B** (sudah ada OlseraService + hourly sync)

---

## Step-by-Step Plan

### Phase 1: Backend — Campaign CRUD (~150 LOC)
1. Tambah method di `BonusService.php`:
   - `createSalesCampaign(array $data): string`
   - `updateSalesCampaign(string $id, array $data): void`
   - `getSalesCampaign(string $id): ?array`
   - `getActiveCampaigns(): array`
   - `getAllCampaigns(): array`
   - `deleteSalesCampaign(string $id): void`

2. Tambah controller method di `BonusController.php`:
   - `campaigns()` — list view
   - `createCampaign()` / `storeCampaign()`
   - `editCampaign()` / `updateCampaign()`
   - `deleteCampaign()`

### Phase 2: Backend — Progress Tracking (~100 LOC)
3. Method di `BonusService.php`:
   - `recordCampaignSale(string $campaignId, string $userId, string $productId, int $units, string $date): array`
   - `getCampaignProgress(string $campaignId): array`
   - `getUserCampaignProgress(string $campaignId, string $userId): array`

### Phase 3: Olsera Auto-Sync (~80 LOC)
4. Extend `OlseraService.php` atau buat command baru:
   - Cron job (misal setiap 2 jam) cek sales data dari Olsera
   - Match SKU dengan active campaigns
   - Auto-credit poin ke user yang jual
   - **Catatan**: Perlu mapping kasir Olsera → waiter ID di Order app

### Phase 4: Admin UI — Campaign Management (~200 LOC)
5. View `admin/bonus/campaigns.blade.php`:
   - List campaigns (active/ended/draft)
   - Create/edit form: pilih produk, set poin, pilih user, set periode
   - Product picker (dari Olsera product list)
   
6. View `admin/bonus/campaign_detail.blade.php`:
   - Progress per user
   - Leaderboard campaign
   - Daily breakdown

### Phase 5: Waiter Portal (~80 LOC)
7. Tambah section di `waiter/bonus_dashboard.blade.php`:
   - Active campaigns yang user eligible
   - Progress poin per campaign
   - Produk apa saja dan berapa poin

### Phase 6: Integration with Monthly Bonus (~50 LOC)
8. Campaign points masuk ke monthly bonus calculation:
   - Bisa sebagai tambahan di kategori "Penjualan"
   - Atau sebagai bonus terpisah (campaign_bonus)
   - **Recommend**: Terpisah, supaya tidak ganggu existing tier system

---

## Files Likely to Change

| File | Change |
|------|--------|
| `app/Services/BonusService.php` | +Campaign CRUD, progress tracking |
| `app/Http/Controllers/Admin/BonusController.php` | +Campaign routes/methods |
| `app/Services/OlseraService.php` | +SKU-based sales query per kasir |
| `resources/views/admin/bonus/campaigns.blade.php` | NEW — campaign list |
| `resources/views/admin/bonus/campaign_detail.blade.php` | NEW — campaign detail |
| `resources/views/waiter/bonus_dashboard.blade.php` | +Campaign progress section |
| `routes/web.php` | +Campaign routes |
| `app/Console/Commands/SyncCampaignSales.php` | NEW — cron auto-sync |
| `app/Console/Kernel.php` or `routes/console.php` | +Schedule campaign sync |

## Estimated LOC
~660 LOC total (within chunking limit)

---

## Open Questions (Perlu Keputusan)

1. **Sumber data penjualan**: Otomatis dari Olsera (recommended) atau manual input?
2. **Mapping kasir**: Bagaimana mapping antara kasir Olsera dan user di Order app? (by name? by email? manual mapping?)
3. **Poin campaign masuk ke mana**: Terpisah dari monthly bonus, atau masuk ke kategori "Penjualan"?
4. **Cap/limit**: Ada batas maksimal poin per hari per campaign? Atau unlimited?
5. **Multiple campaigns**: Bisa ada >1 campaign aktif bersamaan untuk produk yang sama?
6. **Reward**: Poin campaign di-convert ke Rupiah bagaimana? Ikut tier existing atau punya conversion sendiri?

---

## Risks & Tradeoffs

- **Olsera API rate limit**: Sync terlalu sering bisa kena limit. Recommend: 2-3x/hari
- **Mapping kasir**: Kalau Olsera tidak track siapa yang jual (hanya per loket), maka auto-sync tidak bisa per-user → perlu manual input
- **BonusService.php sudah 1935 LOC**: Mendekati limit 2000. Mungkin perlu extract ke `SalesCampaignService.php` terpisah
- **Firebase RTDB cost**: Campaign progress per user per day per product bisa banyak node. Perlu cleanup strategy untuk campaign yang sudah ended
