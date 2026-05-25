# Desain Fitur Kasbon — Order App

## Ringkasan

Kasbon (pinjaman karyawan) dipindahkan dari ShifKasir ke Order app. Alasan: kasbon adalah urusan finance/HR, bukan urusan kasir. Di Order sudah ada payroll system yang bisa otomatis potong kasbon dari gaji/bonus.

## Flow Utama

```
Finance buat kasbon untuk karyawan (admin panel)
        ↓
Validasi: kasbon_enabled? limit tidak terlampaui? max aktif?
        ↓
Langsung cair — balance payroll berkurang (boleh negatif)
Status → active
        ↓
Notifikasi WA ke Supervisor (informasi)
        ↓
Setiap credit masuk (gaji/bonus) → potong FULL untuk kasbon dulu (FIFO)
Sisa credit baru masuk ke saldo waiter
        ↓
Remaining = 0 → kasbon status → paid_off (lunas otomatis)
```

## Aturan Bisnis

1. **Eligibility**: Supervisor menentukan siapa yang boleh kasbon (flag `kasbon_enabled` per waiter)
2. **Limit**: Berdasarkan gaji berjalan (prorated). Rumus: `(monthly_salary / 30) * hari_sudah_kerja_bulan_ini * limit_percent / 100`. Contoh: gaji 1jt, limit 30%, sudah kerja 15 hari = (1.000.000/30) * 15 * 30% = Rp 150.000 max kasbon. Bisa ditambah `kasbon_limit_fixed` sebagai tambahan/fallback jika gaji belum di-set.
3. **Approval flow**: Finance buat kasbon → langsung cair (auto-disburse). Notifikasi WA ke Supervisor sebagai informasi.
4. **Pencairan**: Langsung saat dibuat. Balance payroll boleh negatif (karena ini "ambil gaji di awal"). Saat gaji/bonus masuk, otomatis potong untuk kembalikan.
5. **Pelunasan**: TIDAK ada cicilan. Setiap credit masuk (gaji/bonus), otomatis potong FULL sampai kasbon lunas. Sisa credit baru masuk ke saldo. Jika multiple kasbon aktif → FIFO (oldest first).
6. **Resign/nonaktif**: Kasbon tetap tercatat. Supervisor bisa write-off jika tidak tertagih.
7. **Multiple kasbon**: Configurable — default max 1 kasbon aktif per karyawan. Deduct FIFO.
8. **Yang bisa buat kasbon**: Hanya role Finance
9. **Yang bisa cancel**: Finance atau Supervisor (hanya status pending)
10. **Visibility**: Waiter hanya lihat info kasbon di portal payroll (read-only, jika fitur diaktifkan)

## Notifikasi WhatsApp

| Event | Kirim ke | Isi |
|-------|----------|-----|
| Kasbon dibuat + dicairkan | Supervisor (info) | "[Nama] kasbon Rp X telah dicairkan oleh Finance" |
| Kasbon lunas (auto) | Supervisor | "Kasbon [Nama] Rp X telah lunas" |
| Kasbon di-write-off | Supervisor | "Kasbon [Nama] Rp X di-write-off: [alasan]" |

Nomor supervisor: dari `payroll_configs.supervisor_phone` yang sudah ada.

## Database Schema

### Tabel: `kasbon_configs`
| Column | Type | Keterangan |
|--------|------|------------|
| id | bigint PK | |
| key | varchar unique | Config key |
| value | text nullable | Config value |
| created_at | timestamp | |
| updated_at | timestamp | |

Config keys:
- `default_limit_percent` — default 30 (% dari gaji berjalan prorated)
- `kasbon_limit_fixed` — fallback nominal tetap jika gaji belum di-set (default 0)
- `min_kasbon_amount` — minimum pengajuan (misal 50000)
- `max_active_kasbon` — max kasbon aktif per karyawan (default 1)
- `auto_deduct_enabled` — true (otomatis potong dari credit)

### Tabel: `kasbons`
| Column | Type | Keterangan |
|--------|------|------------|
| id | bigint PK | |
| waiter_id | varchar | FK ke Firebase waiter |
| waiter_name | varchar nullable | Denormalized |
| amount | decimal(15,0) | Jumlah kasbon |
| remaining | decimal(15,0) | Sisa yang belum lunas |
| reason | text nullable | Alasan/keterangan kasbon |
| status | enum | active, paid_off, cancelled, written_off |
| created_by | varchar nullable | Finance user yang buat |
| paid_off_at | timestamp nullable | Tanggal lunas |
| cancelled_by | varchar nullable | Yang cancel |
| cancelled_at | timestamp nullable | |
| written_off_by | varchar nullable | Supervisor yang write-off |
| written_off_at | timestamp nullable | |
| written_off_reason | text nullable | Alasan write-off |
| created_at | timestamp | |
| updated_at | timestamp | |

Status flow:
```
active → paid_off (otomatis saat remaining = 0)
active → written_off (supervisor write-off, misal karyawan resign)
active → cancelled (Finance/Supervisor batalkan)
```

- `active`: sudah dicairkan, menunggu pelunasan dari gaji
- `paid_off`: lunas otomatis
- `cancelled`: dibatalkan oleh Finance/Supervisor
- `written_off`: dihapuskan (karyawan resign, tidak tertagih)

### Tabel: `kasbon_payments`
| Column | Type | Keterangan |
|--------|------|------------|
| id | bigint PK | |
| kasbon_id | bigint FK | |
| waiter_id | varchar | |
| amount | decimal(15,0) | Jumlah yang dipotong |
| remaining_after | decimal(15,0) | Sisa setelah potong |
| source | enum | auto_deduct, manual_payment |
| payroll_tx_id | bigint nullable | FK ke payroll_transactions |
| note | text nullable | |
| created_at | timestamp | |

### Perubahan di Firebase (waiter data)
Tambah field di `allowed_waiters/{id}`:
- `kasbon_enabled`: boolean — apakah boleh ajukan kasbon
- `kasbon_limit_percent`: int — override limit (null = pakai default)

## Integrasi dengan Payroll

### Saat Kasbon Dibuat (langsung cair, status → active)
1. Validasi: `kasbon_enabled`? Limit tidak terlampaui? Max aktif tidak terlampaui?
2. Hitung limit tersedia: `(monthly_salary / 30) * hari_kerja_bulan_ini * limit_percent / 100 + kasbon_limit_fixed - total_kasbon_aktif_remaining`
3. Jika amount > limit tersedia → tolak
4. Potong saldo payroll: `adjustBalance(waiterId, -amount)` (boleh negatif)
5. Catat di `payroll_transactions` dengan type `kasbon_disbursement`
6. Insert `kasbons` record (status: `active`, remaining: amount)
7. Notifikasi WA ke Supervisor (informasi)

### Saat Credit Masuk (gaji/bonus) — Auto-Deduct FULL, FIFO
Di `PayrollService::creditIfAbsent()`, setelah credit berhasil:
1. Cek apakah waiter punya kasbon `active` (ORDER BY created_at ASC — FIFO)
2. Loop setiap kasbon aktif (oldest first):
   a. Hitung potongan: `min(sisa_credit, kasbon.remaining)`
   b. Potong dari balance: `adjustBalance(waiterId, -potongan)`
   c. Catat di `kasbon_payments` (source: `auto_deduct`)
   d. Catat di `payroll_transactions` dengan type `kasbon_deduct`
   e. Update `kasbons.remaining -= potongan`
   f. Jika remaining = 0 → status `paid_off`, set `paid_off_at`
   g. `sisa_credit -= potongan`
   h. Jika sisa_credit = 0 → break
3. Notifikasi WA jika ada kasbon yang lunas

**Contoh:**
- Kasbon Rp 1.000.000, gaji masuk Rp 1.500.000
- Potong full Rp 1.000.000 untuk kasbon → lunas
- Sisa Rp 500.000 masuk ke saldo waiter

- Kasbon Rp 1.000.000, gaji masuk Rp 600.000
- Potong full Rp 600.000 untuk kasbon → remaining Rp 400.000
- Saldo waiter = Rp 0 (semua gaji ke kasbon)

- 2 kasbon aktif: #1 remaining 200rb, #2 remaining 500rb. Gaji masuk 400rb.
- Potong #1: 200rb → lunas. Sisa credit: 200rb.
- Potong #2: 200rb → remaining 300rb. Sisa credit: 0.

### Transaction Types (tambahan di payroll_transactions.type)
- `kasbon_disbursement` — pencairan kasbon (balance berkurang)
- `kasbon_deduct` — potongan otomatis dari credit (balance berkurang)

### Race Condition Prevention
Auto-deduct HARUS di dalam DB transaction yang sama dengan `adjustBalance`. Pakai `lockForUpdate()` pada `kasbons` row untuk prevent double-deduct.

## API Endpoints

### Admin Panel — Finance (role: finance/supervisor)
- `GET /admin/kasbon` — list semua kasbon (filter: status, waiter, tanggal)
- `GET /admin/kasbon/{id}` — detail + histori pembayaran
- `POST /admin/kasbon` — buat kasbon baru `{waiter_id, amount, reason}` (hanya Finance, langsung cair)
- `POST /admin/kasbon/{id}/cancel` — batalkan (Finance/Supervisor, hanya status active + remaining = amount, belum ada potongan)
- `POST /admin/kasbon/{id}/write-off` — write-off `{reason}` (hanya Supervisor)
- `GET /admin/kasbon/settings` — get config
- `POST /admin/kasbon/settings` — update config
- `PATCH /admin/waiters/{id}/kasbon-settings` — enable/disable + set limit per waiter

### Waiter Portal (authenticated waiter, read-only)
- `GET /waiter/kasbon` — list kasbon saya + sisa + histori pembayaran (hanya jika kasbon_enabled)

### Permission Groups
- `kasbon` — akses halaman kasbon di admin panel
- Finance: bisa buat, cancel
- Supervisor: bisa cancel, write-off

## UI

### Portal Waiter (tab Payroll) — READ ONLY
Tambah section "Kasbon" di bawah saldo (hanya muncul jika `kasbon_enabled`):
- Saldo kasbon aktif (hutang): Rp XXX
- Progress bar (terbayar vs total)
- List kasbon (status, jumlah, sisa, tanggal)
- Detail per kasbon: histori potongan otomatis

### Admin Panel — Halaman Kasbon
Halaman baru `/admin/kasbon`:
- **KPI cards**: Total kasbon aktif (Rp), Jumlah karyawan punya kasbon, Total sudah lunas bulan ini
- **Tombol "Buat Kasbon"** → modal form (pilih waiter, nominal, alasan) — langsung cair
- **Tabel** semua kasbon (filter status, waiter, tanggal)
- **Detail page**: info kasbon + timeline (dibuat/dicairkan → potongan-potongan → lunas/write-off)
- **Action buttons**: Cancel (jika belum ada potongan), Write-off (Supervisor)
- **Settings page**: default limit %, limit fixed, max kasbon aktif, min amount

## Migrasi Data dari ShifKasir

### Data yang perlu dipindahkan:
1. Kasbon yang masih `open` (belum lunas) → import sebagai kasbon `active`
2. Histori pembayaran → import ke `kasbon_payments`
3. Mapping employee ShifKasir → waiter_id Order (by name/phone)

### Script migrasi:
1. Hit ShifKasir API atau direct MySQL query untuk ambil data kasbon
2. Match employee → waiter berdasarkan nama
3. Insert ke `kasbons` (status: active, remaining: sisa hutang)
4. Insert histori ke `kasbon_payments` (source: manual_payment, note: "Migrasi dari ShifKasir")
5. Setelah migrasi selesai, disable fitur kasbon di ShifKasir

## Fase Implementasi

### Fase 1: Database + Backend
- Migration tabel kasbon
- KasbonService (CRUD, approval, disbursement, auto-deduct)
- Integrasi ke PayrollService (hook di creditIfAbsent)
- API endpoints

### Fase 2: UI
- Admin panel halaman kasbon
- Portal waiter section kasbon
- Notifikasi WA saat approved/rejected/disbursed

### Fase 3: Migrasi
- Script migrasi data dari ShifKasir
- Testing parallel (kedua sistem jalan bersamaan)
- Cutover: disable kasbon di ShifKasir

## UI Styling Guide

### Prinsip
- **Tidak pakai CSS framework** — custom CSS inline + CSS variables
- **Responsive**: single breakpoint `@media (max-width: 768px)`
- **Admin**: extends `admin.layout`, container max-width 1200px
- **Waiter portal**: standalone, mobile-first, container max-width 720px

### Color Palette (dari CSS variables existing)
```css
:root {
    --color-primary: #667eea;
    --color-primary-dark: #5568d3;
    --color-success: #16a34a;
    --color-success-bg: #f0fdf4;
    --color-warning: #d97706;
    --color-warning-bg: #fffbeb;
    --color-danger: #dc2626;
    --color-danger-bg: #fef2f2;
    --color-text: #0f172a;
    --color-text-secondary: #475569;
    --color-text-muted: #64748b;
    --color-border: #e2e8f0;
    --color-bg: #f8fafc;
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
}
```

### Mapping warna ke status kasbon
- `pending` → amber: `background: #fef9c3; color: #854d0e; border: 1px solid #fde68a;`
- `approved` → blue: `background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd;`
- `active` → green: `background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7;`
- `paid_off` → gray-green: `background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;`
- `rejected` → red: `background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;`
- `cancelled` → gray: `background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;`

### Admin Panel — Halaman Kasbon
```
┌─────────────────────────────────────────────────────────┐
│ [Navbar gradient #667eea → #764ba2]                     │
├─────────────────────────────────────────────────────────┤
│ .container (max-width: 1200px)                          │
│                                                         │
│ ┌─ KPI Row (grid: repeat(auto-fit, minmax(220px,1fr))) │
│ │ ┌────────────┐ ┌────────────┐ ┌────────────┐        │
│ │ │ border-left│ │ border-left│ │ border-left│        │
│ │ │ 4px green  │ │ 4px amber  │ │ 4px blue   │        │
│ │ │ Total Aktif│ │ Pending    │ │ Total Cair │        │
│ │ │ Rp 2.5jt  │ │ 3 pengajuan│ │ Rp 15jt    │        │
│ │ └────────────┘ └────────────┘ └────────────┘        │
│ └──────────────────────────────────────────────────────│
│                                                         │
│ ┌─ Filter Row ─────────────────────────────────────────│
│ │ [Status ▼] [Karyawan ▼] [Tanggal dari-sampai]       │
│ └──────────────────────────────────────────────────────│
│                                                         │
│ ┌─ Table (overflow-x: auto) ──────────────────────────│
│ │ thead: bg #f1f5f9                                    │
│ │ Karyawan | Jumlah | Sisa | Status | Tanggal | Aksi  │
│ │ ─────────────────────────────────────────────────    │
│ │ Randy    | 500rb  | 250rb| [pill] | 20 Mei  | [btn] │
│ │ border-bottom: 1px solid #e2e8f0                     │
│ └──────────────────────────────────────────────────────│
└─────────────────────────────────────────────────────────┘
```

### Waiter Portal — Section Kasbon (di tab Payroll)
```
┌─────────────────────────────────┐
│ [Header gradient #10b981→#059669]│
├─────────────────────────────────┤
│ container (max-width: 720px)     │
│                                  │
│ ┌─ Card (radius 12px) ─────────│
│ │ 💰 Kasbon Aktif               │
│ │ Rp 500.000 / Rp 1.000.000    │
│ │ [progress bar green]          │
│ │ Cicilan: 25% per gaji        │
│ │                               │
│ │ [btn--primary full-width]     │
│ │ "Ajukan Kasbon"               │
│ └───────────────────────────────│
│                                  │
│ ┌─ Card ────────────────────────│
│ │ Riwayat Kasbon                │
│ │ ┌─ item ─────────────────────│
│ │ │ Rp 1.000.000 [pill active] │
│ │ │ 15 Mei 2026 • Sisa: 500rb  │
│ │ └────────────────────────────│
│ │ ┌─ item ─────────────────────│
│ │ │ Rp 750.000 [pill paid_off] │
│ │ │ 1 Apr 2026 • Lunas         │
│ │ └────────────────────────────│
│ └───────────────────────────────│
└─────────────────────────────────┘
```

### Komponen yang dipakai
- **KPI cards**: `border-left: 4px solid [color]`, padding 16px, label uppercase 12px muted
- **Table**: `border-collapse`, thead `#f1f5f9`, row border `#e2e8f0`, font-size 14px
- **Status pill**: `border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 600;`
- **Button admin**: `padding: 6px 10px; border-radius: 6px; font-size: 12px;`
- **Button waiter**: `width: 100%; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 15px;`
- **Modal**: fixed inset, backdrop rgba(0,0,0,.5), content radius 12px, max-width 480px
- **Form input**: `padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;`
- **Flash success**: `background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;`
- **Flash error**: `background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;`

### Responsive Rules
- Admin: `@media (max-width: 768px)` → KPI stack 1 kolom, table font smaller, padding reduced
- Waiter: sudah mobile-first (720px container), tidak perlu breakpoint tambahan
- Table: selalu wrap dalam `overflow-x: auto`
- Flex layouts: `flex-wrap: wrap` dengan min-width constraints

## Estimasi LOC
- Migration: ~80 LOC
- KasbonService: ~280 LOC
- PayrollService patch (auto-deduct hook): ~50 LOC
- Controller admin: ~180 LOC
- Controller waiter: ~100 LOC
- Blade admin (kasbon index + detail + settings): ~350 LOC
- Blade waiter (section kasbon di payroll): ~200 LOC
- **Total: ~1240 LOC** (split 3 fase)

## Plan Implementasi

### Fase 1: Database + Backend (~350 LOC)
1. Migration: tabel `kasbons`, `kasbon_payments`, `kasbon_configs` + alter `payroll_transactions.type` enum
2. `KasbonService.php`: create (+ auto-disburse), cancel, write-off, auto-deduct logic, limit calculation
3. Patch `PayrollService::creditIfAbsent()`: hook auto-deduct FIFO setelah credit
4. Firebase: tambah field `kasbon_enabled`, `kasbon_limit_percent` di waiter data
5. Routes: register endpoint admin + waiter kasbon

### Fase 2: UI Admin Panel (~480 LOC)
1. `admin/kasbon/index.blade.php`: KPI cards + filter + tabel + modal buat kasbon
2. `admin/kasbon/show.blade.php`: detail kasbon + timeline pembayaran + action buttons (cancel/write-off)
3. `admin/kasbon/settings.blade.php`: config default limit, limit fixed, max aktif, min amount
4. `Admin/KasbonController.php`: handle semua admin endpoints
5. Tambah menu "Kasbon" di navbar admin (permission group `kasbon`)
6. Tambah permission group `kasbon` di role_permissions
7. Responsive: test di 768px breakpoint

### Fase 3: UI Waiter Portal + Migrasi (~250 LOC)
1. Section kasbon di `waiter/payroll.blade.php`: saldo hutang, progress bar, riwayat (read-only)
2. `WaiterKasbonController.php`: list kasbon + histori pembayaran
3. Notifikasi WA: saat dibuat (ke supervisor), lunas (ke supervisor)
4. Migrasi data dari ShifKasir (manual input dipandu user)
5. Testing + cutover

### Urutan Kerja (chunk ≤300 LOC per batch)
```
Batch 1: Migration + KasbonService (core logic)           ~300 LOC
Batch 2: Admin Controller + Routes + PayrollService patch  ~250 LOC  
Batch 3: Admin blade views (index + show + modal)          ~280 LOC
Batch 4: Admin settings + waiter read-only section         ~200 LOC
Batch 5: Notifikasi WA + migrasi data ShifKasir            ~100 LOC
```

**Total: ~1130 LOC** (lebih simpel tanpa approval flow)
