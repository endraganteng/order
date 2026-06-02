# Prompt AI Coding — Refactor UI Template Cek Rak Otomatis ke Wizard + Simple Lowest Load

## Konteks Project

Project ini adalah aplikasi Laravel existing untuk order/operasional toko.

Dokumentasi sistem existing:
- Sistem sudah memiliki fitur **Tugas Cek Rak / Rack Check**
- Template task disimpan di Firebase RTDB path: `/waiter_task_templates/{id}`
- Task hasil generate harian disimpan di: `/waiter_tasks/{node_key}`
- Pembuatan template saat ini lewat `/admin/tasks/studio`
- Eksekusi waiter lewat `/waiter/tasks`
- Review finance/supervisor lewat task `rack_check` dengan `recheck_pending=true`
- Bonus masuk ke kategori `rack_recheck`
- Cron generator existing: `waiter:process-tasks` setiap 5 menit

File existing penting:
- `resources/views/admin/tasks/studio.blade.php`
- `app/Http/Controllers/Admin/TaskController.php`
- `app/Http/Requests/StoreTaskRequest.php`
- `app/Services/FirebaseService.php`
- `app/Http/Controllers/WaiterController.php`
- `app/Services/BonusService.php`
- `resources/views/waiter/tasks.blade.php`
- `routes/web.php`
- `routes/console.php`

## Masalah Saat Ini

UI pembuatan template cek rak terlalu teknis untuk supervisor. Banyak field seperti:

- `assignment_type`
- `assignment_strategy`
- `role_assignment_mode`
- `schedule_mode`
- `shift_relative`
- `deadline_mode`
- `rolling_enabled`
- `rolling_period`
- `target_shift_id`
- `recurrence_anchor_date`

Field tersebut bagus untuk backend, tetapi membuat supervisor sulit membuat template cek rak otomatis secara low effort.

Selain itu, sistem generator saat ini terlalu kompleks karena menggunakan AI balancing, fallback, daily cap, multi-source jadwal, dan banyak guard. Target refactor ini **bukan menghapus fitur lama**, tetapi membuat mode baru yang lebih sederhana dan stabil untuk operasional harian.

## Tujuan Utama

Buat fitur baru:

```text
/admin/rack-check/templates/create
```

atau sesuaikan dengan routing existing, untuk membuat template cek rak otomatis dengan UI **wizard sederhana**.

Supervisor cukup melakukan:

```text
Pilih rak
↓
Pilih petugas rotasi
↓
Atur jadwal dan bukti
↓
Lihat ringkasan
↓
Simpan template
```

Template yang dibuat harus tetap kompatibel dengan sistem existing `/waiter_task_templates`, tetapi menggunakan mode assignment baru:

```text
simple_lowest_load
```

## Prinsip Penting

1. Jangan merusak fitur existing `/admin/tasks/studio`.
2. Jangan menghapus AI balancing lama.
3. Tambahkan mode baru yang lebih aman: `simple_lowest_load`.
4. Jadikan mode baru ini default untuk UI wizard cek rak.
5. Semua data tetap masuk ke struktur Firebase existing.
6. UI harus mengikuti style existing admin panel.
7. Gunakan Blade + inline CSS/fetch/AJAX jika pola existing seperti itu.
8. Jangan membuat SPA penuh.
9. Jangan membuat migration database SQL jika tidak perlu.
10. Firebase RTDB tetap menjadi sumber data utama task/template.

---

# Fitur yang Dibuat

## 1. Halaman Wizard Buat Template Cek Rak

Buat halaman baru:

```text
/admin/rack-check/templates/create
```

Nama route bebas, tapi gunakan pola route existing Laravel.

Contoh route name:

```php
admin.rack-check.templates.create
admin.rack-check.templates.store
admin.rack-check.templates.index
admin.rack-check.templates.preview
```

Jika struktur route existing berbeda, sesuaikan dengan pola project.

## 2. Bentuk Wizard

Wizard cukup 4 step utama:

```text
1. Pilih Rak
2. Pilih Petugas
3. Jadwal & Bukti
4. Ringkasan & Simpan
```

Jangan tampilkan field teknis backend di UI utama.

---

# UI Detail

## Header Wizard

```text
+================================================+
| Buat Template Cek Rak Otomatis                 |
+================================================+
| Template ini akan membuat tugas cek rak         |
| otomatis sesuai jadwal. Petugas dipilih dari    |
| karyawan yang masuk kerja dan beban tugasnya    |
| paling ringan.                                  |
+================================================+
| [1 Rak] -> [2 Petugas] -> [3 Jadwal] -> [4 Simpan]
+================================================+
```

---

## Step 1 — Pilih Rak

Supervisor bisa memilih satu atau banyak rak.

UI:

```text
Pilih rak yang akan dicek rutin:

[ Search rak / barcode ]

[x] RAK SENAR
    Lokasi  : Area depan
    Barcode : RAK-SENAR-001

[x] RAK OBAT
    Lokasi  : Area tengah
    Barcode : RAK-OBAT-002

[ ] RAK MAKANAN KUCING
    Lokasi  : Area belakang
    Barcode : RAK-MKN-KCG-003

Rak dipilih: 2 rak

[ Batal ] [ Lanjut ]
```

Catatan teknis:
- Ambil data rak dari service existing yang digunakan task studio sekarang.
- Jika belum ada method khusus, gunakan method FirebaseService existing yang mengambil list rack.
- Support multi-select rack.
- Jika user memilih banyak rack, backend boleh membuat banyak template, satu template per rack.

Validation:
- Minimal 1 rak wajib dipilih.
- Rak harus valid dan ada di data Firebase.

---

## Step 2 — Pilih Petugas Rotasi

Supervisor memilih karyawan yang boleh mendapat tugas cek rak.

UI:

```text
Pilih petugas yang ikut rotasi:

[x] Anjar
    Role   : Pelayan
    Status : Aktif

[x] Rendy
    Role   : Pelayan
    Status : Aktif

[x] Bagas
    Role   : Pelayan
    Status : Aktif

Catatan:
Sistem hanya akan memilih petugas yang sedang masuk kerja.
Petugas yang libur otomatis dilewati.

Petugas dipilih: 3 orang

[ Kembali ] [ Lanjut ]
```

Catatan teknis:
- Ambil waiter aktif dari method existing, misalnya `getActiveWaiters()`.
- Jangan batasi hanya satu role jika sistem existing mendukung selected waiter lintas role untuk rack check.
- Simpan sebagai `selected_waiter_ids`.

Validation:
- Minimal 1 petugas wajib dipilih.
- Hanya waiter aktif yang boleh dipilih.

---

## Step 3 — Jadwal & Bukti

UI:

```text
Jam tugas dibuat:
[ 09:00 ]

Batas pengerjaan:
[ 60 ] menit setelah tugas dibuat

Pengulangan:
(x) Setiap hari
( ) Setiap minggu
( ) Setiap beberapa hari

Jika setiap minggu:
[ Senin v ]

Jika setiap beberapa hari:
Setiap [ 2 ] hari sekali

Tanggal mulai:
[ 2026-06-01 ]

Bukti:
[x] Wajib scan barcode rak
[x] Foto sebelum
[x] Foto sesudah
[x] Petugas boleh menulis catatan
[x] Aktifkan laporan produk kosong

Mode pembagian:
[ Otomatis - Beban Paling Ringan ]

Aturan:
- Libur = 0 task
- Shift pendek = maksimal 1 task
- Full shift = maksimal 2 task
- Jika tidak ada petugas tersedia, task tidak dibuat dan ditandai skipped

[ Kembali ] [ Lanjut ]
```

Default:
- `schedule_time`: `09:00`
- `time_limit_minutes`: `60`
- `recurrence_type`: `daily`
- `requires_barcode_scan`: `true`
- `requires_photo_before`: `true`
- `requires_photo_proof`: `true`
- `assignment_strategy`: `simple_lowest_load`
- `schedule_mode`: `fixed`
- `deadline_mode`: `fixed`

Jangan tampilkan:
- `shift_relative`
- `shift_offset_minutes`
- `deadline_before_end_minutes`
- `rolling_enabled`
- `rolling_period`
- `rolling_slot_index`

Boleh disimpan default di backend jika schema existing butuh.

---

## Step 4 — Ringkasan & Simpan

UI:

```text
Ringkasan Template

Rak:
- RAK SENAR
- RAK OBAT

Petugas:
- Anjar
- Rendy
- Bagas

Jadwal:
Setiap hari pukul 09:00
Deadline 60 menit

Bukti:
[x] Scan barcode rak
[x] Foto sebelum
[x] Foto sesudah
[x] Catatan pengerjaan
[x] Laporan produk kosong

Mode pembagian:
Otomatis - Beban Paling Ringan

Aturan otomatis:
- Petugas libur otomatis dilewati
- Full shift maksimal 2 task cek rak per hari
- Shift pendek maksimal 1 task cek rak per hari
- Jika tidak ada petugas tersedia, task tidak dibuat
- Task skipped tampil di dashboard/generation log

[ Kembali ] [ Simpan Template ]
```

Setelah berhasil:

```text
Template berhasil dibuat.

Sistem akan membuat tugas cek rak otomatis sesuai jadwal.
Petugas dipilih otomatis berdasarkan jadwal kerja dan beban tugas paling ringan.

[ Lihat Template Aktif ] [ Buat Template Lain ]
```

---

# Mapping Data ke Firebase Template

Ketika disimpan, payload ke `/waiter_task_templates/{id}` harus tetap kompatibel dengan schema existing.

Contoh payload untuk setiap rack:

```json
{
  "title": "RAK SENAR",
  "description": "",
  "priority": "normal",
  "assigned_by": "Supervisor",

  "task_type": "rack_check",

  "requires_barcode_scan": true,
  "requires_photo_proof": true,
  "requires_photo_before": true,

  "rack_target_scope": "single",
  "rack_id": "rack_push_id",
  "rack_name": "RAK SENAR",
  "rack_location": "Area depan",
  "rack_barcode_value": "RAK-SENAR-001",
  "rack_type": "display",

  "assignment_type": "role",
  "assignment_strategy": "simple_lowest_load",
  "assigned_waiter_id": null,
  "assigned_waiter_name": null,
  "assigned_waiter_email": null,
  "assigned_waiter_role": null,
  "selected_waiter_ids": ["waiter_id_1", "waiter_id_2", "waiter_id_3"],

  "schedule_mode": "fixed",
  "schedule_time": "09:00",
  "time_limit_minutes": 60,
  "deadline_mode": "fixed",

  "recurrence_type": "daily",
  "weekly_day": null,
  "interval_days": null,
  "recurrence_anchor_date": "2026-06-01",

  "rolling_enabled": false,
  "rolling_period": null,
  "rolling_waiter_ids": [],
  "rolling_anchor_date": null,

  "target_shift_id": null,
  "is_active": true,

  "simple_lowest_load_enabled": true,
  "skip_when_no_eligible_waiter": true,
  "daily_cap_mode": "shift_aware",

  "created_at": 1748784000,
  "updated_at": 1748784000
}
```

Catatan:
- Jangan wajib sama persis jika schema existing butuh nama field tertentu.
- Gunakan field existing sebisa mungkin.
- Tambahkan field baru hanya jika tidak mengganggu sistem lama.
- `assignment_strategy = simple_lowest_load` adalah indikator utama untuk generator baru.

---

# Konsep Generator Simple Lowest Load

Tambahkan support generator untuk template:

```text
task_type = rack_check
assignment_strategy = simple_lowest_load
```

## Aturan Utama

Untuk setiap template due hari ini:

```text
1. Ambil selected_waiter_ids dari template
2. Resolve waiter aktif
3. Cek isWorkingDay(waiterId, date)
4. Buang waiter yang libur
5. Hitung daily cap:
   - LIBUR = 0 task
   - shift pendek = 1 task
   - FULL = 2 task
6. Hitung jumlah rack_check task hari ini per waiter
7. Buang waiter yang sudah mencapai cap
8. Sort kandidat:
   - today_rack_task_count ASC
   - weekly_rack_task_count ASC
   - last_rack_assigned_at ASC
   - waiter_id ASC
9. Pilih kandidat pertama
10. Generate task
11. Simpan assignment_reason
```

## Pseudo-code

```php
$candidates = $this->resolveSelectedActiveWaiters($template['selected_waiter_ids'] ?? []);

$evaluated = [];

foreach ($candidates as $waiter) {
    $waiterId = (string) $waiter['id'];

    $isWorking = $this->isWorkingDay($waiterId, $targetDate);
    $dailyCap = $this->getRackCheckDailyCap($waiterId, $targetDate);
    $todayCount = $this->countRackCheckTasksForWaiterOnDate($waiterId, $targetDate);
    $weeklyCount = $this->countRackCheckTasksForWaiterBetweenDates($waiterId, $weekStart, $targetDate);
    $lastAssignedAt = $this->getLastRackCheckAssignedAt($waiterId);

    $eligible = $isWorking && $todayCount < $dailyCap;

    $evaluated[] = [
        'waiter' => $waiter,
        'waiter_id' => $waiterId,
        'is_working_day' => $isWorking,
        'daily_cap' => $dailyCap,
        'today_count' => $todayCount,
        'weekly_count' => $weeklyCount,
        'last_assigned_at' => $lastAssignedAt,
        'eligible' => $eligible,
        'reject_reason' => $this->buildRejectReason($isWorking, $todayCount, $dailyCap),
    ];
}

$eligible = array_values(array_filter($evaluated, fn ($row) => $row['eligible']));

if (count($eligible) === 0) {
    $this->writeRackCheckGenerationLog($templateId, $targetDate, [
        'status' => 'skipped',
        'reason' => 'no_eligible_waiter',
        'evaluated_candidates' => $evaluated,
    ]);

    return null;
}

usort($eligible, function ($a, $b) {
    return [$a['today_count'], $a['weekly_count'], $a['last_assigned_at'], $a['waiter_id']]
        <=> [$b['today_count'], $b['weekly_count'], $b['last_assigned_at'], $b['waiter_id']];
});

$selected = $eligible[0];

$assignmentReason = [
    'mode' => 'simple_lowest_load',
    'selected_waiter_id' => $selected['waiter_id'],
    'selected_waiter_name' => $selected['waiter']['name'] ?? '',
    'reason' => 'Dipilih karena sedang kerja dan memiliki beban cek rak paling ringan.',
    'today_rack_task_count_before' => $selected['today_count'],
    'weekly_rack_task_count' => $selected['weekly_count'],
    'daily_cap' => $selected['daily_cap'],
    'candidate_count' => count($evaluated),
    'eligible_candidate_count' => count($eligible),
    'evaluated_candidates' => $evaluated,
];
```

---

# Assignment Reason Wajib Disimpan

Saat membuat `/waiter_tasks/{node_key}`, tambahkan field:

```json
{
  "assignment_mode": "simple_lowest_load",
  "assignment_reason": {
    "mode": "simple_lowest_load",
    "selected_waiter_id": "waiter_id",
    "selected_waiter_name": "Rendy",
    "reason": "Rendy dipilih karena sedang kerja dan memiliki beban cek rak paling ringan.",
    "today_rack_task_count_before": 0,
    "weekly_rack_task_count": 3,
    "daily_cap": 2,
    "candidate_count": 3,
    "eligible_candidate_count": 2,
    "rejected_candidates": [
      {
        "waiter_id": "bagas_id",
        "name": "Bagas",
        "reason": "LIBUR"
      },
      {
        "waiter_id": "anjar_id",
        "name": "Anjar",
        "reason": "Beban hari ini lebih tinggi"
      }
    ]
  }
}
```

Tujuan:
- Supervisor bisa melihat kenapa sistem memilih petugas tertentu.
- Debugging bug assignment jadi mudah.
- Jika ada orang libur dapat tugas, bisa langsung diketahui sumber masalahnya.

---

# Generation Lock

Tambahkan mekanisme lock untuk mencegah cron generate ulang setelah cancel.

Path rekomendasi:

```text
/waiter_task_generation_locks/{templateId}/{date}
```

Isi:

```json
{
  "template_id": "template_id",
  "date": "2026-06-01",
  "status": "generated",
  "task_id": "waiter_rec_xxx",
  "assigned_waiter_id": "waiter_id",
  "generated_at": 1748784000,
  "cancelled_by_admin": false,
  "force_regenerate_count": 0
}
```

Aturan:
1. Sebelum generate, cek lock.
2. Jika lock sudah ada dan bukan force regenerate, jangan generate ulang.
3. Jika task dicancel admin, lock tetap ada.
4. Jika ingin generate ulang, harus lewat action `Force Regenerate`.
5. Untuk skipped juga boleh tulis lock/log agar tidak dicoba berulang setiap 5 menit.

Status lock:
- `generated`
- `skipped_no_eligible_waiter`
- `cancelled_by_admin`
- `force_regenerated`

---

# Preview Pembagian

Tambahkan tombol di template aktif:

```text
[ Preview Besok ]
```

Endpoint:

```text
GET /admin/rack-check/templates/{id}/preview?date=YYYY-MM-DD
```

Preview tidak boleh membuat task sungguhan.

Output UI:

```text
Preview Pembagian Besok - 2026-06-02

RAK SENAR
Petugas terpilih: Rendy

Alasan:
[x] Rendy masuk kerja
[x] Task cek rak hari ini paling sedikit
[x] Belum mencapai batas tugas harian

Kandidat lain:
- Anjar: masuk, tapi beban minggu ini lebih tinggi
- Bagas: libur
```

Gunakan logic yang sama dengan simple lowest load, tetapi mode dry-run.

---

# Template Aktif UI

Buat halaman daftar template aktif:

```text
/admin/rack-check/templates
```

UI card:

```text
RAK SENAR
Jadwal    : Setiap hari, 09:00
Petugas   : Anjar, Rendy, Bagas
Mode      : Beban Paling Ringan
Bukti     : Scan barcode + foto sebelum/sesudah
Status    : Aktif

[ Edit ] [ Nonaktifkan ] [ Preview Besok ]
```

---

# Validasi Backend

Pastikan request store template memvalidasi:

```php
'rack_ids' => 'required|array|min:1',
'rack_ids.*' => 'required|string',
'selected_waiter_ids' => 'required|array|min:1',
'selected_waiter_ids.*' => 'required|string',
'schedule_time' => 'required|date_format:H:i',
'time_limit_minutes' => 'required|integer|min:5|max:1440',
'recurrence_type' => 'required|in:daily,weekly,every_n_days',
'weekly_day' => 'nullable|integer|min:1|max:7',
'interval_days' => 'nullable|integer|min:1|max:365',
'recurrence_anchor_date' => 'required|date',
'requires_photo_before' => 'nullable|boolean',
'requires_photo_proof' => 'nullable|boolean'
```

Jika `recurrence_type=weekly`, `weekly_day` wajib.
Jika `recurrence_type=every_n_days`, `interval_days` wajib.

---

# Compatibility dengan Sistem Existing

Pastikan:
1. Template yang dibuat wizard tetap bisa dibaca `generateDueRecurringWaiterTasks()`.
2. Task yang dihasilkan tetap bisa dikerjakan di `/waiter/tasks`.
3. Barcode scan tetap jalan.
4. Foto sebelum/sesudah tetap jalan.
5. `recheck_pending=true` tetap dibuat setelah task selesai.
6. Review finance/supervisor tetap menggunakan route existing `POST /waiter/tasks/{id}/recheck`.
7. Bonus `rack_recheck` tetap masuk seperti existing.
8. Penalty overdue existing tetap tidak rusak.

---

# Jangan Dilakukan

Jangan:
- Menghapus `/admin/tasks/studio`
- Menghapus AI balancing lama
- Mengubah struktur besar Firebase tanpa backward compatibility
- Membuat supervisor harus setup task setiap hari
- Membuat task ke orang libur
- Generate ulang otomatis task yang sudah dicancel admin
- Membuat UI terlalu teknis
- Menampilkan JSON payload ke supervisor
- Membuat SPA penuh jika project existing berbasis Blade

---

# Acceptance Criteria

Fitur dianggap selesai jika:

## UI Wizard
- Supervisor bisa buka halaman buat template cek rak.
- Supervisor bisa pilih satu/banyak rak.
- Supervisor bisa pilih petugas rotasi.
- Supervisor bisa atur jam, deadline, recurrence, dan bukti.
- Supervisor bisa lihat ringkasan sebelum simpan.
- Template berhasil masuk ke `/waiter_task_templates`.

## Generator
- Template `simple_lowest_load` bisa menghasilkan task harian.
- Orang libur tidak pernah mendapat task.
- Orang yang sudah mencapai daily cap tidak mendapat task tambahan.
- Jika tidak ada petugas eligible, task tidak dibuat dan dicatat skipped.
- Cron tidak membuat duplicate.
- Task yang dicancel admin tidak langsung dibuat ulang oleh cron.

## Assignment Reason
- Setiap task simple lowest load punya `assignment_reason`.
- Dashboard/detail bisa menampilkan alasan pemilihan petugas.

## Preview
- Supervisor bisa preview pembagian besok.
- Preview tidak membuat task sungguhan.
- Preview menampilkan kandidat, yang dipilih, dan alasan.

## Compatibility
- Waiter tetap bisa mengerjakan task.
- Finance/supervisor tetap bisa review.
- Bonus rack_recheck tetap masuk.
- Penalty overdue existing tetap aman.

---

# Target UX

Supervisor harus merasa:

```text
Saya cukup pilih rak, pilih orang, tentukan jam, lalu sistem otomatis membagi ke orang yang masuk dan beban tugasnya paling ringan.
```

Tujuan akhir:
- Low effort untuk supervisor
- Lebih mudah dipahami
- Lebih mudah debug
- Tidak salah assign ke orang libur
- Tidak duplicate karena cron
- Tetap kompatibel dengan sistem existing
