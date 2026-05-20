# Inventory Management Overview

## 1. Fitur Inventory yang Sudah Ada

- **Master Data:** Produk, Kategori Produk, Rak (storage & display), Supplier
- **Stok per Rak:** Setiap produk di-assign ke rak dengan `standard_qty` dan `min_qty`
- **QR Code Rak:** Setiap rak punya barcode unik untuk scan oleh waiter
- **Purchase Order (PO):** Pembuatan PO (single, batch, manual), receiving partial/full
- **Restock Otomatis:** Deteksi shortage saat rack check → auto-create restock request
- **Stock Take:** Waiter lapor qty aktual saat cek rak
- **Storage Out:** Waiter ambil stok dari gudang via scan QR
- **Rekonsiliasi Mingguan:** Deteksi drift stok otomatis
- **Audit Trail:** Riwayat pergerakan stok per produk dan per rak
- **Anomaly Detection:** Stok minus terdeteksi dan di-log sebagai alert

---

## 2. File Terkait

### Controller
| File | Fungsi |
|------|--------|
| `app/Http/Controllers/Admin/RackController.php` | CRUD rak, QR, label print, history |
| `app/Http/Controllers/Admin/RackProductController.php` | CRUD produk, assign ke rak, bulk assign, import Excel |
| `app/Http/Controllers/Admin/RestockController.php` | Dashboard restock, PO CRUD, receiving |
| `app/Http/Controllers/Admin/SupplierController.php` | CRUD supplier |
| `app/Http/Controllers/Admin/ProductCategoryController.php` | CRUD kategori produk |
| `app/Http/Controllers/Admin/ReconciliationController.php` | Laporan rekonsiliasi mingguan |

### Service
| File | Fungsi |
|------|--------|
| `app/Services/FirebaseService.php` | Semua operasi inventory (monolithic, ~8900 baris) |

### Model
Belum ditemukan — data langsung via Firebase SDK, tidak pakai Eloquent model.

### Migration
Belum ditemukan — semua data inventory disimpan di Firebase Realtime Database, bukan MySQL.

### View
| File | Fungsi |
|------|--------|
| `resources/views/admin/products/index.blade.php` | Daftar produk |
| `resources/views/admin/products/bulk_assign.blade.php` | Bulk assign produk ke rak |
| `resources/views/admin/products/rack_products.blade.php` | Produk per rak + live stock |
| `resources/views/admin/products/audit_trail.blade.php` | Timeline riwayat produk |
| `resources/views/admin/products/categories.blade.php` | Manajemen kategori |
| `resources/views/admin/racks/index.blade.php` | Daftar rak |
| `resources/views/admin/racks/create.blade.php` / `edit.blade.php` | Form rak |
| `resources/views/admin/racks/history.blade.php` | Riwayat cek rak + pergerakan stok |
| `resources/views/admin/racks/print_labels.blade.php` | Print label QR |
| `resources/views/admin/restock/index.blade.php` | Board restock pending |
| `resources/views/admin/restock/orders.blade.php` | Daftar PO |
| `resources/views/admin/restock/order_detail.blade.php` | Detail PO + receiving |
| `resources/views/admin/suppliers/index.blade.php` | Daftar supplier |
| `resources/views/admin/suppliers/create.blade.php` / `edit.blade.php` | Form supplier |

---

## 3. Struktur Tabel Database (Firebase RTDB Nodes)

| Node | Fungsi |
|------|--------|
| `waiter_racks` | Master rak (name, location, barcode_value, rack_type, is_active) |
| `waiter_racks/{rackId}/products/{productId}` | Assignment produk per rak (current_qty, standard_qty, min_qty) |
| `products` | Master produk (name, category_id, standard_qty, unit, is_active) |
| `product_categories` | Kategori produk (name, description, sort_order) |
| `rack_stock_movements` | Ledger pergerakan stok (setiap perubahan qty tercatat) |
| `restock_requests` | Permintaan restock (pending → ordered → received) |
| `purchase_orders` | Purchase order ke supplier |
| `suppliers` | Master supplier |
| `stock_movement_idempotency` | Cegah duplikasi movement |
| `po_receive_idempotency` | Cegah duplikasi receiving |
| `audit_logs/stock_anomalies` | Alert stok minus/anomali |
| `reconciliation_reports` | Laporan rekonsiliasi mingguan |

---

## 4. Alur Stok Masuk

```
Shortage terdeteksi (rack check / threshold)
        ↓
Restock Request (status: pending)
        ↓
Purchase Order dibuat (single/batch/manual)
        ↓
PO dikirim ke supplier
        ↓
Barang diterima → receivePoItem()
        ↓
recordRackStockMovement(movement_type: 'po_receive', delta: +qty)
        ↓
current_qty bertambah di rak tujuan
```

**Trigger otomatis restock:**
1. Saat waiter selesai cek rak → `writeRestockRequestsForCompletion()` deteksi shortage
2. Setiap stock movement → `maybeAutoCreateRestockOnLowStock()` cek threshold `min_qty`

---

## 5. Alur Stok Keluar

### Storage Out (Waiter ambil dari gudang)
```
Waiter scan QR rak storage
        ↓
Pilih produk + qty yang diambil
        ↓
submitStandaloneStockTake()
        ↓
Validasi: takeQty <= currentQty
        ↓
recordRackStockMovement(movement_type: 'storage_out', delta: -qty)
        ↓
current_qty berkurang
```

### Stock Take (Cek rak → overwrite qty)
```
Waiter cek rak → lapor qty aktual
        ↓
recordRackStockMovement(movement_type: 'stock_take')
        ↓
current_qty = actual_qty (overwrite)
```

---

## 6. Sistem Mutasi / Riwayat Stok

**Node:** `rack_stock_movements`

Setiap perubahan qty tercatat dengan field:
- `rack_id`, `product_id`
- `movement_type`: `stock_take` | `po_receive` | `storage_out`
- `source`: `waiter_task` | `purchase_order` | `waiter_stock_take` | `manual`
- `previous_qty`, `current_qty`, `delta_qty`
- `waiter_id`, `po_id`, `restock_id`
- `idempotency_key`, `created_at`

**Fitur terkait:**
- Product Audit Trail: timeline gabungan (movements + restock + PO + anomalies) per produk
- Rack History: riwayat cek + movements per rak, filterable by produk/tanggal/status

---

## 7. Validasi Stok & Pencegahan Stok Minus

| Mekanisme | Penjelasan |
|-----------|------------|
| **Atomic CAS (Compare-And-Swap)** | `recordRackStockMovement()` pakai Firebase transaction + retry 3x untuk cegah race condition |
| **Pre-validation (Storage Out)** | Reject jika `takeQty > currentQty` atau `currentQty <= 0` |
| **Idempotency** | Stock movements dan PO receives pakai idempotency key → cegah duplikasi |
| **Anomaly Logging** | Jika `current_qty < 0` setelah movement → log ke `audit_logs/stock_anomalies` (severity: critical) |

> **Catatan:** Stok minus **tidak di-block** secara hard — operasi tetap jalan tapi di-flag sebagai anomali. Ini design decision untuk prioritaskan kelancaran operasional.

---

## 8. Laporan Inventory yang Tersedia

| Laporan | Lokasi | Penjelasan |
|---------|--------|------------|
| Rekonsiliasi Mingguan | `/admin/reconciliation` | Deteksi drift >5% antara expected vs actual qty |
| Product Stats | Per-produk di audit trail | Total in/out 30 hari, restock aktif, PO terbuka |
| Restock Summary | `/admin/restock` | Pending items, qty needed, open PO count |
| Stale PO | Dashboard restock | PO yang terlalu lama terbuka |
| Stock Report Export | `GET /admin/tasks/export-stock` | Export data stok |
| Rack History | `/admin/racks/{id}/history` | Per-rak: movements + filter |
| Product Audit Trail | `/admin/products/{id}/audit-trail` | Per-produk: timeline lengkap |

---

## 9. Kelebihan Sistem Saat Ini

1. **Real-time:** Firebase RTDB memberikan update instan ke semua client
2. **Otomasi restock:** Dua trigger independen (task completion + threshold) memastikan shortage terdeteksi
3. **Idempotent operations:** Tidak ada duplikasi stok movement meski ada retry/network issue
4. **Atomic writes:** Firebase transaction mencegah race condition antar waiter
5. **Audit trail lengkap:** Setiap perubahan qty tercatat dengan siapa, kapan, dari mana
6. **PO workflow lengkap:** Dari deteksi shortage → PO → receiving → stok masuk
7. **Rekonsiliasi otomatis:** Deteksi drift mingguan tanpa manual count
8. **QR-based:** Operasional cepat — waiter scan QR, tidak perlu cari manual
9. **Deduplication:** Restock request tidak duplikat untuk produk+rak yang sama

---

## 10. Kekurangan / Risiko Sistem Saat Ini

| # | Kekurangan | Risiko |
|---|-----------|--------|
| 1 | **Semua di Firebase** — tidak ada backup relasional | Data loss jika Firebase bermasalah, tidak bisa SQL query untuk analisis |
| 2 | **Monolithic service** — FirebaseService 8900 baris | Sulit maintain, test, dan debug |
| 3 | **Stok minus tidak di-block** | Bisa terjadi overselling/overuse tanpa hard stop |
| 4 | **Tidak ada COGS tracking** | Tidak tahu harga pokok per item → tidak bisa hitung margin per produk |
| 5 | **Tidak ada expiry/batch tracking** | Risiko barang kadaluarsa tidak terdeteksi |
| 6 | **Tidak ada stock opname formal** | Rekonsiliasi otomatis tapi tidak ada proses manual count terjadwal |
| 7 | **Tidak terintegrasi dengan finance** | Pembelian stok (PO) tidak otomatis tercatat sebagai pengeluaran di modul keuangan |
| 8 | **Tidak ada minimum order quantity** | PO bisa dibuat untuk qty sangat kecil (tidak efisien) |
| 9 | **Tidak ada forecast/prediksi** | Tidak bisa prediksi kapan stok habis berdasarkan consumption rate |
| 10 | **Firebase cost** | Banyak read/write per operasi → biaya bisa membengkak seiring scale |

---

## 11. Rekomendasi Perbaikan & Prioritas

### Prioritas Tinggi (Dampak langsung ke bisnis)

| # | Rekomendasi | Alasan |
|---|-------------|--------|
| 1 | **Integrasi PO → Finance** | PO yang di-receive harus otomatis tercatat sebagai pengeluaran di modul keuangan (kategori "Restok") |
| 2 | **Hard block stok minus** (opsional toggle) | Cegah waiter ambil lebih dari yang tersedia — bisa di-toggle per rak |
| 3 | **COGS / Harga Beli per produk** | Tambah field harga beli di produk/PO → bisa hitung margin dan valuasi stok |

### Prioritas Sedang (Efisiensi operasional)

| # | Rekomendasi | Alasan |
|---|-------------|--------|
| 4 | **Stock opname terjadwal** | Fitur formal: pilih rak → count fisik → bandingkan → adjust |
| 5 | **Consumption rate & forecast** | Hitung rata-rata pemakaian harian → prediksi kapan stok habis → auto-restock lebih awal |
| 6 | **Minimum Order Quantity (MOQ)** | Per supplier/produk, cegah PO terlalu kecil |
| 7 | **Migrasi inventory ke MySQL (hybrid)** | Seperti payroll — data di MySQL, Firebase untuk real-time flag saja |

### Prioritas Rendah (Nice to have)

| # | Rekomendasi | Alasan |
|---|-------------|--------|
| 8 | **Batch/expiry tracking** | Untuk produk yang punya masa kadaluarsa |
| 9 | **Barcode produk** (selain QR rak) | Scan barcode produk untuk input lebih cepat |
| 10 | **Dashboard inventory** | Ringkasan: total SKU, stok value, shortage count, PO pending — di satu halaman |
| 11 | **Refactor FirebaseService** | Pecah ke service terpisah: InventoryService, RackService, RestockService |
