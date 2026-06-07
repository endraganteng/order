# Refactor Audit Report

> Tanggal audit: 2026-06-07
> Project: Laravel 12 + Firebase RTDB + MySQL (hybrid, migrasi berjalan)
> Sifat: Read-only audit. Tidak ada kode diubah. Dokumentasi + roadmap.

---

## 1. Ringkasan Kondisi Project

Project = aplikasi operasional retail (task waiter/kasir, absensi, bonus, payroll,
cek rak, order POS, purchasing). Laravel 12, Blade server-rendered, fetch/AJAX
untuk aksi non-reload. Sedang transisi Firebase RTDB → MySQL (hybrid).

**Skala kode:**
- Total ~47.776 LOC PHP di `app/`
- 310 method dalam 1 file `FirebaseService.php` (13.750 LOC)
- 21 model, 44 migration, 16 FormRequest
- 109 file Blade, 81 punya `<script>` inline, 57 punya `<style>` inline
- 0 Repository, 0 Action class, 0 Blade component (`resources/views/components/` kosong)

**Temuan tingkat tinggi:**
1. **God object** — `FirebaseService` 13.750 LOC / 310 method = pusat segala domain.
2. **God controller** — `TaskController` 3.502 LOC.
3. **God view** — `waiter/tasks.blade.php` 5.466 LOC (247KB), campur HTML+CSS+JS.
4. **Tidak ada lapisan abstraksi data** — controller panggil FirebaseService langsung.
5. **Tidak ada komponen UI** — markup berulang di-copy antar view, inline style/script.
6. **Hybrid setengah jalan** — sebagian fitur sudah MySQL, sebagian masih RTDB.

Status keseluruhan: **fungsional tapi sulit dimaintain.** Bukan rusak — tapi setiap
perubahan berisiko karena logic terpusat di file raksasa dan tercampur antar lapisan.

---

## 2. File Paling Bermasalah

### 2.1 Backend (PHP) — Top offenders

| File | LOC | Masalah |
|------|-----|---------|
| `app/Services/FirebaseService.php` | **13.750** | God object. 310 method. Semua domain campur. |
| `app/Http/Controllers/Admin/TaskController.php` | **3.502** | God controller. Logic bisnis di controller. |
| `app/Services/BonusService.php` | 2.173 | Besar, tapi domain fokus (acceptable, perlu split). |
| `app/Services/FinanceService.php` | 1.759 | Besar, multi-concern (settings + debt + closing). |
| `app/Http/Controllers/WaiterController.php` | 1.291 | Controller gemuk. |
| `app/Services/PayrollService.php` | 1.076 | Batas wajar atas. |

Aturan proyek (CLAUDE.md global): warn >500 LOC, block >1000 LOC per file.
**6 file lewat 1000 LOC. FirebaseService 13x lipat batas block.**

### 2.2 View (Blade) — Top offenders

| File | LOC | Byte | Masalah |
|------|-----|------|---------|
| `waiter/tasks.blade.php` | **5.466** | 247KB | HTML+CSS+JS satu file. Listener RTDB + poll. |
| `admin/tasks/studio.blade.php` | 3.085 | 148KB | Inline berat. |
| `admin/tasks/create.blade.php` | 3.018 | 172KB | Inline berat. |
| `cashier/index.blade.php` | 2.901 | 110KB | POS realtime, listener orders. |
| `admin/tasks/index.blade.php` | 2.797 | 144KB | Inline berat. |
| `admin/layout.blade.php` | 1.603 | 60KB | Layout + CSS global inline. |

### 2.3 Routes

| File | LOC | Catatan |
|------|-----|---------|
| `routes/web.php` | 596 | Banyak route, perlu grouping by domain. |
| `routes/console.php` | 951 | 39 command/schedule entry + ~188 baris comment/blank. Bukan bloat murni — tapi sebagian closure command panjang sebaiknya pindah ke class Command. |

`routes/console.php` 951 LOC: setelah dicek, mayoritas = definisi scheduled task
wajar + comment. Tetap ada ruang perbaikan: closure command yang panjang sebaiknya
diekstrak ke class `app/Console/Commands/` (testable + reusable).

### 2.4 File nyangkut / junk (kebersihan repo)

| File | Masalah | Aksi |
|------|---------|------|
| `app/Http/Controllers/AdminController.php.bak` | Backup file di folder controller | Hapus setelah konfirmasi tak dipakai |
| `count()` (root, 0 byte) | Artifact shell-redirect kebablasan | Hapus |
| `toArray())` (root, 0 byte) | Artifact shell-redirect | Hapus |
| `$line)` (root, 0 byte) | Artifact shell-redirect | Hapus |

Junk file root muncul dari command shell yang salah escape (PowerShell). Tak masuk
git (untracked) tapi bikin `git status` kotor. Bersihkan saat housekeeping.

---

## 3. Breakdown FirebaseService (13.750 LOC, 310 method)

FirebaseService = god object. Satu kelas menangani SEMUA domain RTDB + sebagian
logic MySQL (read-path flag-gated). Mapping berdasarkan node yang disentuh:

### 3.1 Distribusi akses node (jumlah getReference per node)

| Domain | Node | Akses | Saran lapisan |
|--------|------|-------|---------------|
| Waiter Task | waiter_tasks, waiter_task_templates, task_categories, *_idempotency | 43+12+4+3 | `WaiterTaskRepository` |
| Cashier Task | cashier_tasks, cashier_task_templates, cashier_workers | 13+6+5 | `CashierTaskRepository` |
| Rack/Stock | waiter_racks, rack_products, rack_check_overflows, rack_stock_movements, rack_check_planning | 12+8+10+4+4 | `RackRepository` |
| Attendance | waiter_attendance, attendance_config, waiter_schedule_template | 8+4+2 | `AttendanceRepository` |
| Purchasing | restock_requests, purchase_orders, purchase_order_drafts, suppliers, po_*_idempotency | 8+6+6+2+3 | `PurchasingRepository` |
| Order/POS | orders | 6 | `OrderRepository` |
| Bonus | waiter_penalties, bonus_pending_*_index | 3+3+3 | `BonusRepository` |
| Master | allowed_waiters, product_categories, work_shifts | 6+7+5 | `MasterDataRepository` |
| System | settings, audit_logs, waiter_activity_reports | 3+6+3 | `SystemRepository` |

### 3.2 Masalah struktural FirebaseService

1. **Single Responsibility violated total** — 9+ domain dalam 1 kelas.
2. **310 method** — navigasi sulit, merge conflict tinggi, test mustahil granular.
3. **Read-path MySQL flag-gated bercampur** — method seperti `getProducts()`,
   `getTasks()`, `getActiveWaiters()` punya cabang `if config('features.mysql_*')`.
   Logic dua-database dalam satu method = sulit dibaca.
4. **Helper dual-write tersebar** — `dualWriteWaiterTaskToMysql`,
   `dualWriteCashierTaskToMysql`, `dualWriteRackProductToMysql` di kelas yang sama
   dengan read RTDB. Concern mirroring + read tercampur.

### 3.3 Rekomendasi pemecahan (BERTAHAP, jangan sekaligus)

Target arsitektur:
```
app/
  Repositories/
    Contracts/          # interface per domain (WaiterTaskRepositoryInterface)
    Firebase/           # implementasi RTDB
    Mysql/              # implementasi MySQL
    Hybrid/             # dual-write + flag routing
  Services/
    FirebaseService.php # SHRINK -> cuma koneksi/low-level RTDB helper
```

Strategi aman: **extract per domain satu per satu**, mulai domain yang sudah
MySQL-stable (rack_products, cashier_tasks). Facade lama `FirebaseService`
delegasi ke repository baru selama transisi → call site tidak perlu diubah serentak.


---

## 4. Controller / View / Route / JS yang Perlu Dirapikan

### 4.1 Controller

| Controller | LOC | Masalah | Saran |
|-----------|-----|---------|-------|
| `TaskController` | 3.502 | God controller. Business logic + orchestration di controller. | Extract ke Action/Service per use-case (CreateTask, AssignTask, dll). |
| `WaiterController` | 1.291 | Gemuk. Task bucket building + order + attendance campur. | Split per concern. |
| `RackCheckPlanningController` | 931 | Planning logic di controller. | Extract `RackCheckPlanningService`. |
| `DashboardController` | 729 | Agregasi data multi-domain di controller. | Extract query ke service/repo. |

**Pola masalah:** logic bisnis (hitung, agregasi, orchestration) ada di controller.
Standar Laravel: controller tipis (validasi + delegasi + response). Logic → Service/Action.

### 4.2 View (Blade)

- **5 view >2.700 LOC** dengan HTML+CSS+JS satu file.
- `waiter/tasks.blade.php` 5.466 LOC = file paling parah. Listener RTDB, poll, render,
  modal, semua inline.
- **81/109 view** punya `<script>` inline; **57/109** punya `<style>` inline.
- **0 Blade component** — `resources/views/components/` kosong. Tombol, modal, tabel,
  alert, badge di-copy-paste antar view.

### 4.3 Routes

- `routes/console.php` **951 LOC** — closure command panjang inline. Pindah ke
  class `app/Console/Commands/`.
- `routes/web.php` 596 LOC — perlu grouping prefix/middleware by domain lebih rapi.

### 4.4 JavaScript

- Tidak ada build step JS untuk logic halaman (inline `<script>` per Blade).
- Firebase client SDK di-import per view (`firebasejs/10.7.1` CDN) berulang.
- Listener RTDB scoped (sudah `equalTo`/`limitToLast` — bagus), tapi duplikat
  pola koneksi di banyak view. Extract ke shared JS module.

---

## 5. Masalah UI/UX

### 5.1 Konsistensi

- **Inline CSS masif** — 57 view punya `<style>`. Warna, spacing, radius di-hardcode
  per view. Tidak ada design token terpusat.
- **Tidak ada komponen UI** — tombol/modal/tabel/alert/badge duplikat. Perubahan
  style = edit puluhan file.
- **Layout global** (`admin/layout.blade.php` 1.603 LOC) campur struktur + CSS inline.

### 5.2 Pola berulang (kandidat komponen)

Dari scan: tombol aksi, badge status, modal konfirmasi, tabel data, form filter,
alert sukses/error, empty state, loading spinner — semua di-copy antar view.

### 5.3 State UI

- **Loading state** — tidak konsisten (sebagian ada spinner, sebagian tidak).
- **Empty state** — sebagian view tampil tabel kosong tanpa pesan.
- **Alert** — pola `session('success')`/`session('error')` di-inline beda gaya per view.
- **Mobile responsive** — perlu audit per halaman (inline style menyulitkan konsistensi
  breakpoint).


---

## 6. Rekomendasi Struktur Folder Baru

```
app/
  Http/
    Controllers/        # TIPIS: validasi + delegasi + response
    Requests/           # FormRequest (sudah ada 16, perbanyak)
  Actions/              # single use-case (CreateWaiterTask, FinalizeBonus)
  Repositories/
    Contracts/          # interface per domain
    Firebase/           # impl RTDB
    Mysql/              # impl MySQL (Eloquent)
    Hybrid/             # dual-write + flag routing
  Services/             # orchestration lintas-repo + domain logic
    Firebase/
      FirebaseConnection.php   # low-level SDK wrapper (shrink dari god object)
  Models/               # Eloquent (sudah ada 21)
  Support/              # helper, value objects

resources/views/
  components/           # Blade components (button, modal, table, alert, badge)
  layouts/              # layout dipisah dari CSS
  <domain>/             # view per domain

resources/
  css/                  # design token + komponen CSS (pindah dari inline)
  js/
    firebase/           # shared RTDB client module
    pages/              # logic per halaman (pindah dari inline <script>)
```

---

## 7. Rekomendasi Standardisasi UI Component

Buat Blade component (Laravel `<x-...>`) untuk pola berulang:

| Component | Ganti |
|-----------|-------|
| `<x-button variant=... >` | semua tombol inline-styled |
| `<x-modal>` | modal konfirmasi duplikat |
| `<x-data-table>` | tabel data + empty state built-in |
| `<x-alert type=...>` | session success/error |
| `<x-badge status=...>` | badge status task/order |
| `<x-filter-bar>` | form filter tanggal/scope |
| `<x-loading>` | spinner konsisten |
| `<x-empty-state>` | tampilan data kosong |

**Design token** — pindah warna/spacing/radius ke CSS variable terpusat
(`resources/css/tokens.css`), buang hardcode inline.

---

## 8. Klasifikasi Fitur: Firebase RTDB vs MySQL

Berdasarkan kebutuhan realtime (listener `onValue`/`.on()` di browser):

### 8.1 TETAP Firebase RTDB (butuh realtime — listener live)

| Node | Alasan |
|------|--------|
| `orders` | POS kasir, listener live (`cashier/index.blade.php`) |
| `waiter_tasks` | listener live (tasks.blade.php, live.blade.php) — write tetap RTDB |
| `waiter_penalties` | listener live (tasks.blade.php) |
| `purchase_orders` | listener live (restock.blade.php) |
| `waiter_racks` | listener live stock_take (sub-node products) |
| `active_sessions` | presence realtime |

### 8.2 PINDAH MySQL (tidak butuh realtime)

| Node | Status |
|------|--------|
| waiter_tasks (READ) | ✅ sudah MySQL |
| cashier_tasks | ✅ sudah MySQL (read+write+flag aktif) |
| attendance | ✅ read MySQL |
| audit_logs, activity_reports | ✅ MySQL |
| product_categories, work_shifts | ✅ MySQL |
| bonus_summary, penalties, manual_bonuses | ✅ read MySQL |
| rack_products | ✅ sudah MySQL (read+write, flag belum di-flip) |
| allowed_waiters (master) | ⏳ belum (ada requestCache, beban kecil) |
| suppliers | ⏳ belum (jarang dibaca) |

### 8.3 Catatan penting

- `waiter_racks` = **hybrid wajib** — data master bisa MySQL, tapi sub-node `products`
  realtime untuk stock take. Pisahkan: master rack → MySQL, state live → RTDB.
- `getAllRackProductsMap()` masih baca `waiter_racks` full-node → beban #8 belum tuntas
  walau rack_products sudah MySQL.


---

## 9. Risiko Refactor

| Area | Risiko | Mitigasi |
|------|--------|----------|
| Pecah FirebaseService | TINGGI — 310 method, ratusan call site | Facade delegasi, extract per domain, test tiap langkah |
| waiter_racks → MySQL | TINGGI — core business (stock take, rack check), listener live | Pisah master vs realtime; jangan sentuh listener |
| allowed_waiters → MySQL | TINGGI — auth/login baca node ini | Test login menyeluruh sebelum flip |
| Pindah inline CSS → token | SEDANG — visual regression | Screenshot before/after per halaman |
| Blade component extraction | SEDANG — markup berubah | Migrasi 1 component, verify, baru lanjut |
| TaskController split | SEDANG — banyak route | Extract Action satu per satu, route tetap |
| routes/console.php → Command | RENDAH — cron terpisah | Test tiap command manual |

**Prinsip:** jangan rename/hapus sebelum dependency 100% jelas. Facade lama tetap
ada selama transisi. Tiap langkah punya rollback (flag / git).

---

## 10. Prioritas Refactor

> Prinsip urutan: **risiko-rendah + nilai-tinggi dulu**, **risiko-tinggi terakhir.**
> High-value tapi high-risk (waiter_racks) TIDAK boleh di awal — taruh akhir setelah
> fondasi stabil. Urutan ini selaras dengan Roadmap (Section 11).

### CRITICAL (lakukan dulu — dampak besar, risiko rendah)
- C1. Stabilkan hybrid yang sudah jalan: flip flag rack_products, monitor bandwidth.
- C2. Housekeeping: hapus `AdminController.php.bak` + 3 junk file root (setelah konfirmasi).

### HIGH (maintainability inti — fondasi)
- H1. Repository pattern per domain + interface (fondasi semua refactor lain).
- H2. Pecah `FirebaseService` mulai domain MySQL-stable (rack_products, cashier).
- H3. Extract business logic dari `TaskController` → Action/Service.
- H4. Buat Blade component dasar (button, modal, table, alert, badge).
- H5. Pindah `routes/console.php` closure panjang → class Command.

### MEDIUM (kualitas + konsistensi)
- M1. Design token CSS terpusat, buang inline `<style>` bertahap.
- M2. Extract inline `<script>` → JS module per halaman.
- M3. Shared Firebase client JS module (hapus duplikat import).
- M4. Standardisasi loading/empty/alert state.
- M5. **waiter_racks master vs realtime split** (beban #8). Risiko TINGGI — butuh
  fondasi repository (H1/H2) selesai dulu. Bukan CRITICAL karena risiko ke core business.

### LOW (polish)
- L1. allowed_waiters/suppliers → MySQL (beban kecil, ROI rendah).
- L2. Audit mobile responsive per halaman.
- L3. Grouping routes/web.php by domain.

---

## 11. Roadmap Refactor Bertahap

### Fase 0 — Stabilisasi + housekeeping (1-2 hari)
- Flip flag rack_products, monitor Firebase Console bandwidth 1-2 hari.
- Konfirmasi 2 gajah (waiter_tasks, cashier) turun nyata.
- Hapus `AdminController.php.bak` + 3 junk file root (setelah konfirmasi tak dipakai).
- Tidak ada perubahan struktur — verifikasi dulu.

### Fase 1 — Repository foundation (per domain, bertahap)
- Buat `Repositories/Contracts/` + impl untuk 1 domain stabil (rack_products).
- `FirebaseService` delegasi ke repo baru (facade). Call site tidak berubah.
- Test + commit per domain. Ulangi: cashier, waiter_task, attendance.

### Fase 2 — Controller slimming
- Extract logic `TaskController` → Action class satu use-case per waktu.
- Route + signature tetap. Test tiap extraction.

### Fase 3 — UI component system
- Buat Blade component dasar + design token CSS.
- Migrasi 1 view percontohan (mis. admin/products) full ke component.
- Verify visual, baru rollout ke view lain bertahap.

### Fase 4 — JS modularization
- Shared Firebase client module.
- Pindah inline `<script>` view terbesar (waiter/tasks) → module.

### Fase 5 — waiter_racks hybrid split
- Master rack → MySQL. Sub-node products realtime → tetap RTDB.
- Selesaikan beban #8. Risiko tinggi — paling akhir, test berat.
- **Prasyarat:** Fase 1 (repository) selesai. Jangan kerjakan ini sebelum fondasi
  data-layer stabil. Selaras dengan prioritas M5 (bukan CRITICAL).

### Fase 6 — Polish
- allowed_waiters/suppliers → MySQL (opsional, ROI rendah).
- Mobile responsive audit. Routes grouping.

---

## 12. Catatan Penutup

- Project **fungsional**, bukan rusak. Masalah = maintainability + god object + UI tak konsisten.
- Migrasi RTDB→MySQL **sudah jalan separuh** dan benar arahnya (read gajah sudah pindah).
- Refactor **harus bertahap** — facade delegasi + flag + test tiap langkah. Jangan big-bang.
- Urutan aman: stabilkan → repository → controller → UI → JS → waiter_racks → polish.
- Angka bandwidth pasti hanya dari Firebase Console > Usage. Estimasi code-side: ~10GB → ~300-450MB/hari.

