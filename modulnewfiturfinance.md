# Prompt Ringkas AI Coding — Modul Akuntansi / Finance Petshop Laravel

Saya punya aplikasi Laravel petshop yang sudah berjalan. Tambahkan modul baru **Akuntansi / Finance**. Sebelum coding, analisis dulu struktur project yang sudah ada dan ikuti pola existing: route, controller, model, view/frontend, middleware, permission, user, role, style UI, upload file, dan cara request async.

Jangan merusak fitur lama. Gunakan user, role, permission, warna, layout, dan komponen yang sudah ada di sistem. Jika belum ada reusable CSS, buat reusable CSS baru khusus modul finance dengan namespace `.finance-module` agar tidak bentrok dengan halaman lain.

---

## 1. Role & Hak Akses

Gunakan sistem user dan role yang sudah ada.

Role utama:
- **Supervisor**: akses penuh modul finance, pengaturan, approval, token API, audit log.
- **Manager Keuangan**: akses melihat dan mengelola finance sesuai permission yang diberikan.

Permission minimal:
- view finance dashboard
- manage finance category
- manage fund allocation
- manage cash account
- manage cash transfer
- sync finance api
- manage api mapping
- view audit log
- manage sync setting

---

## 2. Menu Modul Finance

Tambahkan menu:

```text
Akuntansi
├── Dashboard Keuangan
├── Integrasi Shift Kasir
│   ├── Sinkronisasi Data
│   ├── Pengaturan Sinkronisasi
│   ├── Riwayat Sinkronisasi
│   ├── Mapping Kategori API
│   ├── Mapping Akun Kas API
│   ├── Data Perlu Review
│   └── Detail Shift Kasir
├── Akun Kas
├── Transfer Antar Akun Kas
├── Mutasi Kas
├── Pengaturan Akuntansi
│   ├── Manajemen Kategori Keuangan
│   ├── Manajemen Alokasi Dana
│   └── Audit Log
└── Laporan
    ├── Laporan Keuangan Bulanan
    ├── Laporan Saldo Kas
    └── Export Laporan
```

---

## 3. Dashboard Keuangan

Dashboard menampilkan:
- Total pendapatan hari ini / bulan ini
- Penjualan tunai
- Penjualan QRIS
- Total pengeluaran
- Pendapatan bersih
- Jumlah shift
- Selisih kas
- Saldo kas per akun
- Data need review
- Last sync dan status sync
- Tombol **Refresh Data Hari Ini**

Dashboard mengambil data dari API Shift Kasir, bukan input manual.

---

## 4. Integrasi API Shift Kasir

Gunakan API Finance Integration.

Base URL:

```text
https://<domain>/api/finance
```

Semua request wajib memakai header:

```text
X-Internal-Token: <FINANCE_API_TOKEN>
```

Token hanya boleh dikelola Supervisor. Manager Keuangan tidak boleh melihat token kecuali diberi permission khusus.

### Filter Tanggal

Semua endpoint mendukung:

```text
tanggal=YYYY-MM-DD
```

atau:

```text
dari=YYYY-MM-DD&sampai=YYYY-MM-DD
```

Jika tidak ada parameter tanggal, API default mengambil data hari ini.

### Endpoint yang digunakan

1. `GET /api/finance/summary`  
   Untuk ringkasan dashboard dan total periode.

2. `GET /api/finance/daily`  
   Untuk sinkronisasi data harian dan laporan bulanan.

3. `GET /api/finance/pengeluaran/daily`  
   Untuk rekap pengeluaran harian beserta item.

4. `GET /api/finance/pengeluaran`  
   Untuk detail pengeluaran per item pada tanggal/periode tertentu.

5. `GET /api/finance/shifts`  
   Untuk detail shift kasir, kasir, loket, status, dan selisih kas.

---

## 5. Contoh Response API

### Summary

```json
{
  "success": true,
  "data": {
    "penjualan_tunai": 5408500,
    "penjualan_qris": 2313000,
    "total_pendapatan": 7721500,
    "total_pengeluaran": 1203000,
    "pendapatan_bersih": 6518500,
    "jumlah_shift": 3
  }
}
```

### Daily

```json
{
  "success": true,
  "data": [
    {
      "tanggal": "2026-05-01",
      "penjualan_tunai": 5408500,
      "penjualan_qris": 2313000,
      "total_pendapatan": 7721500,
      "total_pengeluaran": 1203000,
      "pendapatan_bersih": 6518500,
      "jumlah_shift": 3
    }
  ]
}
```

### Pengeluaran Daily

```json
{
  "success": true,
  "data": [
    {
      "tanggal": "2026-05-01",
      "total_pengeluaran": 1203000,
      "jumlah_item": 8,
      "items": [
        {
          "line_type": "product",
          "deskripsi": "Aqua 600ml",
          "kategori": "Belanja Toko",
          "supplier": "PT Sumber Air",
          "qty": 10,
          "harga_satuan": 3500,
          "total": 35000
        }
      ]
    }
  ]
}
```

### Pengeluaran Detail

```json
{
  "success": true,
  "data": {
    "total_pengeluaran": 1203000,
    "jumlah_item": 8,
    "items": [
      {
        "tanggal": "2026-05-03",
        "line_type": "product",
        "deskripsi": "Aqua 600ml",
        "kategori": "Belanja Toko",
        "supplier": "PT Sumber Air",
        "qty": 10,
        "harga_satuan": 3500,
        "total": 35000
      },
      {
        "tanggal": "2026-05-03",
        "line_type": "kasbon",
        "deskripsi": "Kasbon: Budi",
        "kategori": null,
        "supplier": null,
        "qty": 1,
        "harga_satuan": 200000,
        "total": 200000
      },
      {
        "tanggal": "2026-05-03",
        "line_type": "custom",
        "deskripsi": "Bayar parkir bulanan",
        "kategori": null,
        "supplier": null,
        "qty": 1,
        "harga_satuan": 50000,
        "total": 50000
      }
    ]
  }
}
```

### Shifts

```json
{
  "success": true,
  "data": [
    {
      "id": 265,
      "tanggal": "2026-05-03",
      "shift_number": 1,
      "loket": "Loket 1",
      "kasir": "Andi",
      "penjualan_tunai": 5408500,
      "penjualan_qris": 2313000,
      "total_pengeluaran": 1203000,
      "selisih": -15000,
      "status": "submitted"
    }
  ]
}
```

### Error Response

```json
{
  "success": false,
  "message": "Unauthorized internal request."
}
```

```json
{
  "message": "The sampai field must be a date after or equal to dari.",
  "errors": {
    "sampai": ["The sampai field must be a date after or equal to dari."]
  }
}
```

---

## 6. Sinkronisasi Data

Buat 3 jenis sinkronisasi:

1. **Sync Data Hari Ini**  
   Untuk refresh data terbaru hari ini.

2. **Manual Sync**  
   User memilih tanggal atau range tanggal.

3. **Auto Sync Terjadwal**  
   Bisa diatur Supervisor.

Default auto sync:
- aktif/nonaktif bisa diatur
- default jam: 00:00
- default mengambil data hari sebelumnya
- timezone mengikuti aplikasi
- support retry jika gagal

Tambahkan pengaturan:
- jam sync
- mode sync: manual, daily, hourly, daily + hourly
- data yang diambil: hari ini, kemarin, range tertentu, retry failed
- test koneksi API
- riwayat sync
- status sync terakhir
- error log

Status sync:
- success
- failed
- partial_success
- need_review

Cegah data double saat sync ulang. Jika data sudah ada, update data lama, jangan insert duplikat. Untuk item pengeluaran yang tidak punya ID unik, buat hash internal dari kombinasi tanggal, line_type, deskripsi, supplier, qty, harga_satuan, total.

---

## 7. Mapping API

Buat mapping agar data dari API masuk ke kategori dan akun kas internal.

### Mapping Kategori API

Contoh default:
- `line_type product` → Restok / Modal Barang
- `line_type kasbon` → Gaji & Operasional
- `line_type custom` → Need Review

Jika tidak ada mapping, status data menjadi `need_review`.

### Mapping Akun Kas API

Contoh default:
- Penjualan Tunai → Kas Toko
- Penjualan QRIS → QRIS
- Pengeluaran Shift → Kas Toko

Jika QRIS dicairkan ke rekening bank, catat sebagai Transfer Antar Akun Kas, bukan pendapatan baru.

---

## 8. Kategori Keuangan

Buat CRUD Manajemen Kategori Keuangan.

Kategori default:
- Gaji & Operasional — expense
- Pemilik — expense
- Restok / Modal Barang — expense
- Penjualan Toko — income
- Pemasukan Lain — income

Fitur:
- tambah, edit, aktif/nonaktif
- search, filter type, filter status
- support subkategori jika memungkinkan
- kategori yang sudah dipakai transaksi tidak boleh dihapus permanen, cukup nonaktif
- kategori nonaktif tidak muncul di form baru, tetapi tetap muncul di laporan lama

---

## 9. Alokasi Dana

Buat CRUD Manajemen Alokasi Dana.

Default:
- Gaji & Operasional: 7.5%
- Pemilik: 18.6%
- Restok / Modal Barang: 73.9%

Aturan:
- hanya untuk kategori expense
- total alokasi aktif dalam periode harus 100%
- ada tanggal mulai berlaku
- simpan riwayat perubahan
- laporan periode lama tidak boleh berubah saat alokasi baru dibuat
- buat simulasi alokasi berdasarkan total pendapatan

Contoh simulasi: input total pendapatan Rp 268.092.625, output nominal pembagian per kategori.

---

## 10. Akun Kas & Transfer Antar Akun Kas

Buat Manajemen Akun Kas.

Akun default:
- Kas Toko
- Kas Kecil
- Rekening Bank
- QRIS
- Kas Operasional
- Kas Restok
- Dana Pemilik

Buat fitur Transfer Antar Akun Kas.

Aturan:
- transfer bukan pendapatan dan bukan pengeluaran
- hanya memindahkan saldo antar akun
- akun sumber dan tujuan tidak boleh sama
- saldo sumber berkurang, saldo tujuan bertambah
- jika ada biaya transfer, biaya tersebut boleh dicatat sebagai pengeluaran operasional
- transfer muncul di mutasi kas
- transfer tidak boleh menaikkan pendapatan/pengeluaran bisnis

Status transfer:
- draft
- pending
- approved
- rejected
- cancelled

Jika menggunakan approval, saldo hanya berubah setelah approved.

---

## 11. Mutasi Kas & Laporan

Buat mutasi kas dengan jenis:
- income
- expense
- transfer_in
- transfer_out

Laporan yang dibutuhkan:
- Laporan Keuangan Bulanan
- Laporan Saldo Kas
- Laporan Mutasi Kas
- Laporan Sync API
- Export PDF/Excel/CSV jika memungkinkan

Laporan saldo kas menampilkan:
- saldo awal
- pemasukan
- pengeluaran
- transfer masuk
- transfer keluar
- saldo akhir

Transfer tidak dihitung sebagai pendapatan/pengeluaran pada laporan bisnis.

---

## 12. Audit Log

Catat semua aksi penting:
- create/update/nonaktif kategori
- create/update alokasi
- sync manual/auto/retry
- sync gagal
- perubahan mapping API
- perubahan akun kas
- transfer kas
- approve/reject transfer
- perubahan jadwal auto sync
- perubahan token API
- data need_review diubah/diabaikan
- export laporan
- perubahan permission finance

Audit log minimal berisi:
- user
- role
- action
- module
- record_id
- old_values
- new_values
- ip_address
- user_agent
- waktu

Jika sistem sudah punya audit log, gunakan yang existing. Jika belum ada, buat reusable audit log service/helper.

---

## 13. CRUD No Reload / SPA-like

Semua CRUD finance harus berjalan tanpa full reload halaman.

Berlaku untuk:
- Kategori Keuangan
- Alokasi Dana
- Mapping Kategori API
- Mapping Akun Kas API
- Akun Kas
- Transfer Antar Akun Kas
- Pengaturan Sinkronisasi
- Data Perlu Review
- Catatan finance

Aturan:
- gunakan modal/drawer/form inline sesuai UI existing
- create/update/delete update table tanpa reload penuh
- validasi error tampil langsung di form
- loading state saat submit
- toast/alert sukses/gagal
- search, filter, sorting, pagination sebisa mungkin async

Jika project Blade biasa, gunakan AJAX/fetch. Jika Inertia/Vue/React, ikuti stack existing.

---

## 14. Reusable CSS Finance

Karena sistem belum punya reusable CSS, buat reusable CSS baru khusus modul finance.

Aturan:
- warna mengikuti sistem existing
- jangan membuat theme baru
- jangan mengubah CSS global lama
- gunakan namespace `.finance-module`
- struktur UI mengikuti standar industri
- responsive desktop/tablet/mobile

Komponen style yang perlu dibuat:
- layout finance
- summary card
- table
- button
- badge status
- form
- filter bar
- modal/drawer
- alert/toast
- loading state
- empty state
- money display
- budget progress bar
- pagination

Status badge minimal:
- synced
- need_review
- failed
- ignored
- pending
- approved
- rejected
- draft
- cancelled
- active
- inactive

---

## 15. Ketentuan Teknis Umum

- Ikuti struktur Laravel project existing.
- Gunakan migration, model, controller, request validation, middleware/policy sesuai standar project.
- Gunakan decimal/integer untuk uang, jangan float.
- Gunakan database transaction untuk proses penting seperti sync, approval, transfer.
- Gunakan pagination, search, filter.
- File upload harus aman.
- Jangan duplikasi fitur yang sudah ada.
- Jangan hardcode user, role, warna, atau struktur yang sudah tersedia.
- Semua fitur penting harus masuk audit log.

---

## Output yang Diminta dari AI Coding

Setelah implementasi, berikan:
1. Analisis singkat struktur project.
2. Daftar file yang dibuat/diubah.
3. Struktur tabel dan relasi.
4. Ringkasan fitur yang berhasil dibuat.
5. Cara penggunaan modul finance.
6. Cara menjalankan sync manual dan auto sync.
7. Konfirmasi CRUD no reload / SPA-like.
8. Konfirmasi CSS finance tidak mengganggu halaman lama.
