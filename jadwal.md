# Buat Fitur Jadwal Shift Karyawan Retail

Saya ingin membuat fitur jadwal shift karyawan untuk toko retail.

## Data Karyawan

Ada 3 karyawan:

1. Anjar
2. Rendy
3. Bagas

## Jam Operasional Toko

Toko buka setiap hari dari:

- 06.30 sampai 21.00

## Aturan Utama Jadwal

1. Setiap hari dari jam buka sampai jam tutup harus ada minimal 2 orang yang menjaga toko.
2. Setiap karyawan wajib libur 1 kali dalam seminggu.
3. Libur tidak boleh pada hari Sabtu dan Minggu.
4. Karena hanya ada 3 karyawan, pada hari ketika 1 orang libur, maka 2 orang lainnya harus masuk full shift.
5. Full shift adalah 06.30–21.00.
6. Shift pendek terdiri dari:
   - Shift pagi: 06.30–15.30
   - Shift sore: 12.00–21.00
7. Pada hari yang semua karyawan masuk, harus ada 1 orang full shift, 1 orang shift pagi, dan 1 orang shift sore.
8. Jadwal harus memastikan toko selalu dijaga minimal 2 orang sepanjang jam operasional.

## Jenis Shift

| Kode | Nama Shift | Jam |
|---|---|---|
| FULL | Full Shift | 06.30–21.00 |
| PAGI | Shift Pagi | 06.30–15.30 |
| SORE | Shift Sore | 12.00–21.00 |
| LIBUR | Libur | Tidak masuk |

## Contoh Jadwal Minggu Pertama

| Hari | Anjar | Rendy | Bagas |
|---|---|---|---|
| Senin | LIBUR | FULL | FULL |
| Selasa | FULL | LIBUR | FULL |
| Rabu | FULL | FULL | LIBUR |
| Kamis | PAGI | SORE | FULL |
| Jumat | SORE | FULL | PAGI |
| Sabtu | FULL | PAGI | SORE |
| Minggu | PAGI | FULL | SORE |

## Hitungan Full Shift Minggu Pertama

| Karyawan | Jumlah Full Shift |
|---|---:|
| Anjar | 3x |
| Rendy | 4x |
| Bagas | 3x |

## Rotasi Full Shift 4x

Karena dengan 3 karyawan jumlah full shift tidak bisa dibagi rata sempurna, maka setiap minggu harus ada 1 orang yang mendapat 4x full shift.

Orang yang mendapat 4x full shift harus dirotasi setiap minggu agar adil.

Contoh rotasi:

| Minggu | Karyawan yang mendapat 4x Full Shift |
|---|---|
| Minggu 1 | Rendy |
| Minggu 2 | Bagas |
| Minggu 3 | Anjar |
| Minggu 4 | Rendy |
| Minggu 5 | Bagas |
| Minggu 6 | Anjar |

Pola rotasi terus berulang.

## Jam Istirahat

Jam istirahat hanya bisa dibuat normal pada hari ketika 3 orang masuk, yaitu hari Kamis sampai Minggu.

Karena pada hari tersebut ada 3 orang masuk dan ada overlap shift pagi dan sore dari jam 12.00 sampai 15.30.

Pola istirahat:

| Urutan | Jam Istirahat |
|---|---|
| Orang 1 | 12.00–13.00 |
| Orang 2 | 13.00–14.00 |
| Orang 3 | 14.00–15.00 |

Saat 1 orang istirahat, toko tetap dijaga oleh 2 orang.

## Aturan Istirahat Senin–Rabu

Pada Senin, Selasa, dan Rabu hanya ada 2 orang masuk karena 1 orang libur.

Karena toko harus selalu dijaga minimal 2 orang, maka tidak boleh ada istirahat keluar toko pada hari tersebut.

Untuk Senin–Rabu, sistem harus memberikan catatan:

- Istirahat fleksibel di toko
- Makan bergantian tapi tetap standby
- Tidak boleh meninggalkan area toko kecuali ada pengganti

## Output yang Dibutuhkan

Buat sistem jadwal shift yang bisa:

1. Menampilkan jadwal mingguan.
2. Menampilkan nama karyawan.
3. Menampilkan shift setiap hari.
4. Menghitung jumlah full shift tiap karyawan.
5. Menghitung total jam kerja tiap karyawan.
6. Menentukan siapa yang mendapat 4x full shift berdasarkan rotasi mingguan.
7. Menampilkan jadwal istirahat.
8. Memberi peringatan jika jadwal melanggar aturan minimal 2 orang.
9. Tidak mengizinkan libur Sabtu dan Minggu.
10. Tidak mengizinkan lebih dari 1 orang libur dalam 1 hari.

## Perhitungan Durasi Shift

Gunakan durasi berikut:

| Shift | Durasi |
|---|---:|
| FULL | 14,5 jam |
| PAGI | 9 jam |
| SORE | 9 jam |
| LIBUR | 0 jam |

Contoh total jam pada minggu pertama:

| Karyawan | Full Shift | Shift Pendek | Total Jam |
|---|---:|---:|---:|
| Anjar | 3x = 43,5 jam | 3x = 27 jam | 70,5 jam |
| Rendy | 4x = 58 jam | 2x = 18 jam | 76 jam |
| Bagas | 3x = 43,5 jam | 3x = 27 jam | 70,5 jam |

## Validasi Sistem

Sistem harus memvalidasi:

1. Setiap hari minimal ada 2 orang yang masuk.
2. Sabtu dan Minggu semua karyawan masuk.
3. Setiap karyawan hanya libur 1x per minggu.
4. Tidak ada 2 karyawan libur di hari yang sama.
5. Jadwal dari 06.30 sampai 21.00 selalu ter-cover minimal 2 orang.
6. Yang mendapat 4x full shift hanya 1 orang per minggu.
7. Karyawan yang mendapat 4x full shift harus mengikuti rotasi mingguan.

## Catatan UI

Buat tampilan yang mudah dibaca dengan tabel mingguan.

Gunakan badge warna untuk shift:

- FULL = merah/oranye
- PAGI = biru
- SORE = hijau
- LIBUR = abu-abu

Tampilkan juga ringkasan di bawah jadwal:

- Total full shift per karyawan
- Total jam kerja per karyawan
- Hari libur masing-masing karyawan
- Siapa yang mendapat 4x full shift minggu ini
- Jadwal istirahat