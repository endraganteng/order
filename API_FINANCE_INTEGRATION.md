# API Finance Integration

API untuk integrasi data penjualan dan pengeluaran dengan aplikasi finance eksternal.

## Autentikasi

Semua endpoint memerlukan header:

```
X-Internal-Token: <FINANCE_API_TOKEN>
```

Token dikonfigurasi oleh supervisor melalui dashboard:

```
Menu Admin → Settings → Finance API Token
URL: /admin/settings/finance-api-token
```

Jika belum diset di dashboard, sistem akan fallback ke env variable `INTERNAL_API_TOKEN`.

## Base URL

```
https://<domain>/api/finance
```

## Parameter Filter Tanggal

Semua endpoint mendukung filter tanggal yang sama:

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `tanggal` | date (Y-m-d) | Filter untuk satu tanggal tertentu |
| `dari` | date (Y-m-d) | Tanggal awal range (wajib bersama `sampai`) |
| `sampai` | date (Y-m-d) | Tanggal akhir range (wajib bersama `dari`) |

Jika tidak ada parameter tanggal, default = hari ini.

---

## Endpoints

### 1. GET `/api/finance/summary`

Ringkasan total pendapatan dan pengeluaran.

**Contoh Request:**

```bash
curl -H "X-Internal-Token: your-token" \
  "https://domain.com/api/finance/summary?dari=2026-05-01&sampai=2026-05-18"
```

**Response:**

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

| Field | Tipe | Keterangan |
|-------|------|------------|
| `penjualan_tunai` | integer | Total penjualan tunai (Rp) |
| `penjualan_qris` | integer | Total penjualan QRIS (Rp) |
| `total_pendapatan` | integer | tunai + qris |
| `total_pengeluaran` | integer | Total pengeluaran dari semua shift |
| `pendapatan_bersih` | integer | total_pendapatan - total_pengeluaran |
| `jumlah_shift` | integer | Jumlah shift dalam periode |

---

### 2. GET `/api/finance/daily`

Pendapatan & pengeluaran per hari (breakdown harian). Cocok untuk sinkronisasi data harian ke sistem finance.

**Parameter (wajib):**

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `dari` | date (Y-m-d) | Tanggal awal |
| `sampai` | date (Y-m-d) | Tanggal akhir |

**Contoh Request:**

```bash
curl -H "X-Internal-Token: your-token" \
  "https://domain.com/api/finance/daily?dari=2026-05-01&sampai=2026-05-31"
```

**Response:**

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
    },
    {
      "tanggal": "2026-05-02",
      "penjualan_tunai": 4200000,
      "penjualan_qris": 1850000,
      "total_pendapatan": 6050000,
      "total_pengeluaran": 980000,
      "pendapatan_bersih": 5070000,
      "jumlah_shift": 2
    }
  ]
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `tanggal` | string | Tanggal (Y-m-d) |
| `penjualan_tunai` | integer | Total penjualan tunai hari itu (Rp) |
| `penjualan_qris` | integer | Total penjualan QRIS hari itu (Rp) |
| `total_pendapatan` | integer | tunai + qris |
| `total_pengeluaran` | integer | Total pengeluaran hari itu (Rp) |
| `pendapatan_bersih` | integer | total_pendapatan - total_pengeluaran |
| `jumlah_shift` | integer | Jumlah shift hari itu |

> Catatan: Hanya tanggal yang memiliki shift yang akan muncul di response.

---

### 3. GET `/api/finance/pengeluaran/daily`

Pengeluaran per hari dengan breakdown detail per item.

**Parameter (wajib):**

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `dari` | date (Y-m-d) | Tanggal awal |
| `sampai` | date (Y-m-d) | Tanggal akhir |

**Contoh Request:**

```bash
curl -H "X-Internal-Token: your-token" \
  "https://domain.com/api/finance/pengeluaran/daily?dari=2026-05-01&sampai=2026-05-31"
```

**Response:**

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

| Field | Tipe | Keterangan |
|-------|------|------------|
| `tanggal` | string | Tanggal (Y-m-d) |
| `total_pengeluaran` | integer | Total pengeluaran hari itu (Rp) |
| `jumlah_item` | integer | Jumlah item pengeluaran hari itu |
| `items` | array | Detail item (sama seperti endpoint pengeluaran) |

---

### 4. GET `/api/finance/pengeluaran`

Detail pengeluaran per item.

**Contoh Request:**

```bash
curl -H "X-Internal-Token: your-token" \
  "https://domain.com/api/finance/pengeluaran?tanggal=2026-05-03"
```

**Response:**

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

| Field | Tipe | Keterangan |
|-------|------|------------|
| `tanggal` | string | Tanggal pengeluaran (Y-m-d) |
| `line_type` | string | `product`, `kasbon`, atau `custom` |
| `deskripsi` | string | Nama produk / nama karyawan kasbon / deskripsi custom |
| `kategori` | string\|null | Nama tipe pengeluaran (jika product) |
| `supplier` | string\|null | Nama supplier (jika ada) |
| `qty` | float | Jumlah |
| `harga_satuan` | integer | Harga per unit (Rp) |
| `total` | integer | Total = qty × harga_satuan (Rp) |

---

### 5. GET `/api/finance/shifts`

Detail pendapatan dan pengeluaran per shift.

**Contoh Request:**

```bash
curl -H "X-Internal-Token: your-token" \
  "https://domain.com/api/finance/shifts?dari=2026-05-01&sampai=2026-05-03"
```

**Response:**

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

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | integer | ID shift |
| `tanggal` | string | Tanggal shift (Y-m-d) |
| `shift_number` | integer | Nomor shift (1, 2, dst) |
| `loket` | string\|null | Nama loket/terminal kasir |
| `kasir` | string\|null | Nama kasir penyerah |
| `penjualan_tunai` | integer | Penjualan tunai shift ini (Rp) |
| `penjualan_qris` | integer | Penjualan QRIS shift ini (Rp) |
| `total_pengeluaran` | integer | Total pengeluaran shift ini (Rp) |
| `selisih` | integer | Selisih kas (bisa negatif) |
| `status` | string | `submitted`, `approved`, `rejected` |

---

## Error Response

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Unauthorized internal request."
}
```

### 422 Validation Error

```json
{
  "message": "The sampai field must be a date after or equal to dari.",
  "errors": {
    "sampai": ["The sampai field must be a date after or equal to dari."]
  }
}
```

---

## Catatan

- Semua nilai uang dalam satuan **Rupiah (integer)**, tanpa desimal.
- `selisih` pada shift bisa bernilai negatif (uang fisik kurang dari yang seharusnya).
- `line_type` pada pengeluaran:
  - `product` — pembelian produk/barang dari supplier
  - `kasbon` — pinjaman karyawan
  - `custom` — pengeluaran lain-lain
