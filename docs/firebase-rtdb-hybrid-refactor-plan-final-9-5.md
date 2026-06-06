# Plan Final Refactor Firebase RTDB ke Hybrid MySQL + RTDB

> Tanggal: 2026-06-06  
> Status: Final plan revisi  
> Target bandwidth: dari ±10GB/hari menjadi ±100–300MB/hari  
> Prinsip utama: **MySQL = source of truth**, **Firebase RTDB = realtime active state kecil dan bounded**

---

## 1. Executive Summary

Bandwidth Firebase RTDB membengkak karena aplikasi terlalu banyak memakai RTDB sebagai database utama, bukan hanya realtime layer. Banyak data historis, report, audit log, attendance, task history, order history, bonus, dan master data disimpan di RTDB lalu dibaca dengan pola full-node read atau listener terlalu luas.

Akar masalah utama:

1. `getWaiterTasksByWaiterId()` membaca seluruh history task waiter tanpa batas tanggal.
2. Waiter portal JS listener subscribe ke `/waiter_tasks.limitToLast(50)` tanpa filter waiter, sehingga semua waiter menerima event semua task.
3. `pollTasks()` dan `syncDueTasks()` redundan dan memicu backend call berkali-kali.
4. `cashier_tasks` dibaca full node setiap sync.
5. Banyak query RTDB belum dipastikan punya `.indexOn`.
6. Data yang tumbuh tanpa batas masih disimpan di RTDB.

Target refactor:

```text
Sebelum       : ±10GB/hari
Phase 0      : <3GB/hari
Phase 1      : <1GB/hari
Phase 2      : ±500MB/hari
Phase 3      : ±100–300MB/hari
Target ideal : ±50–150MB/hari jika cleanup dan active node disiplin
```

Prinsip final:

```text
MySQL       = source of truth / data utama / history / report / audit / master data
Firebase    = realtime active state hari ini saja
Redis/File  = cache kecil / idempotency TTL
```

---

## 2. Revisi Penting dari Plan Sebelumnya

Dokumen ini memasukkan 5 revisi wajib agar plan layak dieksekusi:

### Revisi 1 — Jangan lagi pakai `/waiter_tasks` sebagai target final realtime

Node lama:

```text
/waiter_tasks
```

dianggap legacy dan berisiko terus membesar.

Target baru:

```text
/waiter_tasks_active/{date}/{waiterId}/{taskId}
```

Contoh:

```text
/waiter_tasks_active/2026-06-06/waiter_123/wt_abcd1234
```

Manfaat:

- Listener waiter hanya membaca task miliknya.
- Data dibatasi per tanggal.
- Cleanup mudah.
- Tidak perlu query global.
- Tidak ada stampede antar waiter.

---

### Revisi 2 — Jangan hanya date-limit di PHP setelah membaca data besar

Solusi sementara boleh memakai query `scheduled_for_date`, tetapi solusi final harus memakai active node.

Salah:

```php
// Query masih membaca semua task waiter lalu tanggal difilter di PHP
orderByChild('assigned_waiter_id')->equalTo($waiterId);
```

Lebih baik untuk emergency:

```php
orderByChild('scheduled_for_date')
    ->startAt($sevenDaysAgo)
    ->endAt($today);
```

Final yang benar:

```text
/waiter_tasks_active/{today}/{waiterId}
```

Dengan final ini, waiter portal tidak perlu membaca `/waiter_tasks` lagi.

---

### Revisi 3 — Status enum harus konsisten

Jangan pakai status `planned` jika enum MySQL tidak menyediakannya.

Gunakan status final berikut:

```text
pending
in_progress
done
cancelled
rescheduled
ignored
failed
```

Jika sistem memang butuh membedakan planning dan publish, gunakan field terpisah:

```text
publish_status: draft | published | failed
status: pending | in_progress | done | cancelled | rescheduled | ignored
```

Rekomendasi final:

```text
status = status pekerjaan
publish_status = status publish ke Firebase
sync_status = status sinkronisasi Firebase
```

---

### Revisi 4 — Jangan ubah `.read` / `.write` Firebase Rules sembarangan

Emergency fix pertama adalah menambah `.indexOn`, bukan mengubah keamanan rules secara agresif.

Jika aplikasi frontend belum memakai Firebase Auth, rules seperti ini bisa merusak fitur:

```json
".read": "auth != null",
".write": "auth != null"
```

Untuk tahap awal, cukup tambahkan `.indexOn` pada rules yang sudah ada.

Contoh aman secara konsep:

```json
{
  "rules": {
    "waiter_tasks": {
      ".indexOn": ["assigned_waiter_id", "scheduled_for_date", "status"]
    },
    "cashier_tasks": {
      ".indexOn": ["scheduled_date", "status"]
    },
    "orders": {
      ".indexOn": ["created_at", "status", "expires_at"]
    }
  }
}
```

Security rules harus mengikuti sistem auth yang sudah berjalan.

---

### Revisi 5 — Tambahkan `firebase_sync_logs` dan reconcile command

Selain `sync_status` di tabel utama, wajib ada log sinkronisasi khusus agar kegagalan sync bisa diaudit.

Tabel wajib:

```text
firebase_sync_logs
```

Fungsinya:

- Mencatat path Firebase yang ditulis.
- Mencatat entity yang gagal sync.
- Mencatat error.
- Mencatat jumlah retry.
- Memudahkan reconcile dan debugging.

---

## 3. Current Problem Analysis

### Problem 1 — `getWaiterTasksByWaiterId()` tanpa date limit

```text
File    : app/Services/FirebaseService.php
Method  : getWaiterTasksByWaiterId($waiterId)
Node    : /waiter_tasks
Prioritas: P0
```

Masalah:

```php
orderByChild('assigned_waiter_id')->equalTo($waiterId)
```

Query ini mengambil seluruh riwayat task waiter tanpa batas tanggal. Jika task sudah berjalan berbulan-bulan, satu call bisa mengambil data besar.

Caller berisiko:

```text
WaiterController@tasksIndex
WaiterController@pollTasks
WaiterController@syncDueTasks
waiter:process-tasks
waiter:send-weekly-report
waiter:send-monthly-report
```

Solusi emergency:

```text
Batasi query berdasarkan scheduled_for_date.
```

Solusi final:

```text
Read live task dari /waiter_tasks_active/{date}/{waiterId}
History task dari MySQL.
```

---

### Problem 2 — Waiter JS listener global

```text
File    : resources/views/waiter/tasks.blade.php
Node    : /waiter_tasks
Prioritas: P0
```

Sebelum:

```javascript
firebaseDB.ref('waiter_tasks').limitToLast(50)
```

Masalah:

- Semua waiter menerima event semua task.
- Satu task berubah bisa memicu banyak browser melakukan polling.
- Terjadi stampede effect.

Emergency fix:

```javascript
firebaseDB.ref('waiter_tasks')
  .orderByChild('assigned_waiter_id')
  .equalTo(currentWaiterId)
```

Final fix:

```javascript
firebaseDB
  .ref(`waiter_tasks_active/${today}/${currentWaiterId}`)
  .on('child_added', handleTaskAdded);

firebaseDB
  .ref(`waiter_tasks_active/${today}/${currentWaiterId}`)
  .on('child_changed', handleTaskChanged);

firebaseDB
  .ref(`waiter_tasks_active/${today}/${currentWaiterId}`)
  .on('child_removed', handleTaskRemoved);
```

---

### Problem 3 — `pollTasks()` dan `syncDueTasks()` redundan

```text
File    : resources/views/waiter/tasks.blade.php
Prioritas: P0/P1
```

Masalah:

Ada beberapa mekanisme refresh bersamaan:

```text
Firebase listener
pollTasks interval
syncDueTasks interval
safety poll
```

Akibat:

- Banyak request backend.
- Backend kembali membaca RTDB besar.
- Bandwidth membengkak.

Solusi:

```text
Jika Firebase listener aktif, polling rutin dimatikan.
Polling hanya fallback jika listener gagal.
```

Contoh:

```javascript
let realtimeConnected = false;

function startRealtimeListener() {
  const ref = firebaseDB.ref(`waiter_tasks_active/${today}/${currentWaiterId}`);

  ref.on('value', () => {
    realtimeConnected = true;
  }, () => {
    realtimeConnected = false;
  });
}

setInterval(() => {
  if (realtimeConnected) return;
  pollTasksFallback();
}, 5 * 60 * 1000);
```

---

### Problem 4 — Cashier sync baca full `/cashier_tasks`

```text
File    : app/Services/FirebaseService.php
Method  : getExistingRecurringMapForDate($date)
Node    : /cashier_tasks
Prioritas: P0
```

Masalah:

```php
$this->database->getReference('cashier_tasks')->getSnapshot();
```

Ini membaca semua cashier task sejak awal.

Emergency fix:

```php
$this->database->getReference('cashier_tasks')
    ->orderByChild('scheduled_date')
    ->equalTo($today)
    ->getSnapshot();
```

Final fix:

```text
cashier_tasks pindah ke MySQL.
Firebase hanya dipakai jika ada cashier task aktif yang benar-benar butuh realtime.
```

---

### Problem 5 — Query RTDB tanpa `.indexOn`

```text
File    : Firebase Console / database.rules.json
Prioritas: P0
```

Masalah:

Tanpa `.indexOn`, query yang terlihat filtered bisa tetap mahal karena Firebase harus scan node besar.

Wajib tambahkan index untuk node yang masih dipakai selama transisi:

```json
{
  "rules": {
    "waiter_tasks": {
      ".indexOn": ["assigned_waiter_id", "scheduled_for_date", "status", "rack_id"]
    },
    "waiter_tasks_active": {
      "$date": {
        "$waiterId": {
          ".indexOn": ["status", "priority", "created_at"]
        }
      }
    },
    "cashier_tasks": {
      ".indexOn": ["scheduled_date", "status", "source_template_id"]
    },
    "orders": {
      ".indexOn": ["created_at", "status", "expires_at", "date"]
    },
    "orders_active": {
      "$date": {
        ".indexOn": ["status", "created_at", "expires_at"]
      }
    },
    "waiter_activity_reports": {
      ".indexOn": ["waiter_id", "date", "report_date"]
    },
    "waiter_attendance": {
      ".indexOn": ["waiter_id", "date"]
    },
    "waiter_daily_points": {
      ".indexOn": ["waiter_id", "date"]
    },
    "audit_logs": {
      ".indexOn": ["timestamp", "entity_type", "created_at"]
    }
  }
}
```

Catatan:

```text
Jangan ubah .read/.write tanpa menyesuaikan auth sistem yang sudah berjalan.
```

---

## 4. Firebase Node Classification Final

| Node Lama / Baru | Fitur | Keputusan | Alasan |
|---|---|---|---|
| `/waiter_tasks` | Legacy task waiter | Legacy / cleanup bertahap | Jangan jadi target final karena tumbuh tanpa batas |
| `/waiter_tasks_active/{date}/{waiterId}/{taskId}` | Live task waiter | Tetap RTDB | Kecil, bounded, realtime |
| `/orders` | Legacy order POS | Hybrid sementara / cleanup | Jangan simpan history panjang di RTDB |
| `/orders_active/{date}/{orderId}` | Live order kasir | Tetap RTDB | Kecil, realtime, bounded |
| `/cashier_tasks` | Task kasir | Pindah MySQL | Tidak wajib realtime, saat ini full-read |
| `/waiter_task_templates` | Template recurring task | Pindah MySQL | Config, jarang berubah |
| `/waiter_activity_reports` | Laporan kegiatan | Pindah MySQL | Historical, report |
| `/waiter_attendance` | Absensi | Pindah MySQL | Historical, query bulanan |
| `/waiter_daily_points` | Poin harian | Pindah MySQL | Aggregate, bonus |
| `/waiter_penalties` | Penalti | Pindah MySQL | Transactional |
| `/waiter_manual_bonuses` | Bonus manual | Pindah MySQL | Sering dihitung dan di-loop |
| `/waiter_bonus_summary` | Ringkasan bonus | Pindah MySQL | Computed data |
| `/waiter_leaderboard` | Leaderboard | Pindah MySQL / cache | Computed |
| `/bonus_config` | Config bonus | MySQL / cache | Settings |
| `/audit_logs` | Audit log | Pindah MySQL | Historical dan butuh pagination |
| `/rack_products` | Master produk | Pindah MySQL | Master data |
| `/waiter_racks` | Rak + produk | Hybrid | MySQL master, RTDB hanya live qty |
| `/rack_stock_movements` | History stok | Pindah MySQL | Historical |
| `/scan_attempts` | Log scan | Pindah MySQL | Log tumbuh terus |
| `/purchase_orders` | PO | Pindah MySQL | Transactional |
| `/restock_requests` | Request restock | Pindah MySQL | Transactional |
| `/dana_payments` | Notifikasi DANA | Tetap RTDB terbatas | Realtime notif |
| `/active_sessions` | Presence stock take | Tetap RTDB | Realtime presence |
| `/payroll_flags` | Trigger refresh payroll | Tetap RTDB | Tiny trigger |
| `/settings/queue_counter` | Counter antrian | Tetap RTDB | Atomic increment |
| `/waiter_task_generation_locks` | Lock task generator | Tetap RTDB | Distributed lock kecil |
| `/stock_movement_idempotency` | Idempotency stok | Redis | TTL cache |
| `/waiter_task_idempotency` | Idempotency task | Redis | TTL cache |
| `/allowed_waiters` | Profil waiter | Pindah MySQL | Relasi user, payroll, bonus |
| `/product_categories` | Kategori produk | Pindah MySQL | Master data |
| `/work_shifts` | Shift kerja | Pindah MySQL | Config |

---

## 5. Target Arsitektur Final

```text
+----------------------+
| Admin / Supervisor   |
| Blade + AJAX         |
+----------+-----------+
           |
           v
+----------------------+
| Laravel API          |
| Service Layer        |
+----------+-----------+
           |
           v
+----------------------+
| MySQL                |
| Source of Truth      |
+----------+-----------+
           |
           | publish active data
           v
+----------------------+
| Firebase RTDB        |
| Active State Only    |
+----------+-----------+
           |
           v
+----------------------+
| Waiter / Cashier UI  |
| Realtime Listener    |
+----------------------+
```

Prinsip:

```text
Write persistent data -> MySQL
Publish realtime view -> Firebase active node
Read report/history -> MySQL
Read live update -> Firebase active node
Sync failure -> firebase_sync_logs
Reconcile -> MySQL wins
Cleanup -> delete expired active node
```

---

## 6. Active Node Design

### 6.1 Waiter Task Active Node

Final path:

```text
/waiter_tasks_active/{date}/{waiterId}/{taskId}
```

Contoh:

```json
{
  "waiter_tasks_active": {
    "2026-06-06": {
      "waiter_123": {
        "wt_7a9c2d": {
          "mysql_id": 9182,
          "title": "Cek Rak Makanan Kucing",
          "task_type": "rack_check",
          "assigned_waiter_id": "waiter_123",
          "scheduled_for_date": "2026-06-06",
          "status": "pending",
          "priority": "normal",
          "rack_id": "rack_12",
          "rack_name": "Rak Makanan Kucing",
          "created_at": "2026-06-06T08:00:00+07:00",
          "updated_at": "2026-06-06T08:00:00+07:00"
        }
      }
    }
  }
}
```

Listener final waiter:

```javascript
const today = window.APP_TODAY;
const currentWaiterId = window.CURRENT_WAITER_ID;

const tasksRef = firebaseDB.ref(`waiter_tasks_active/${today}/${currentWaiterId}`);

tasksRef.on('child_added', handleTaskAdded);
tasksRef.on('child_changed', handleTaskChanged);
tasksRef.on('child_removed', handleTaskRemoved);

window.addEventListener('beforeunload', () => {
  tasksRef.off();
});
```

Manfaat:

- Waiter hanya mendapat task miliknya.
- Tidak ada event dari waiter lain.
- Data hari sebelumnya mudah dihapus.
- Portal tidak perlu membaca history.

---

### 6.2 Orders Active Node

Final path:

```text
/orders_active/{date}/{orderId}
```

Contoh:

```json
{
  "orders_active": {
    "2026-06-06": {
      "ord_10092": {
        "mysql_id": 10092,
        "order_number": "A102",
        "status": "pending",
        "total_amount": 125000,
        "created_at": "2026-06-06T12:05:00+07:00",
        "expires_at": "2026-06-06T12:20:00+07:00"
      }
    }
  }
}
```

Cashier listener:

```javascript
const ordersRef = firebaseDB.ref(`orders_active/${today}`);
ordersRef.orderByChild('status').equalTo('pending').on('child_added', handleNewOrder);
```

Cleanup:

```text
Order completed/cancelled -> update MySQL -> remove from /orders_active
End of day -> remove /orders_active/{yesterday}
```

---

### 6.3 Rack Live Stock

Master data:

```text
MySQL:
racks
rack_products
rack_product_stocks
rack_stock_movements
```

Realtime saat stock take:

```text
/rack_live_stock/{sessionId}/{rackId}/{productId}
```

Atau jika ingin tetap kompatibel sementara:

```text
/waiter_racks/{rackId}/products/{productId}/current_qty
```

Rekomendasi final:

```text
/rack_stocktake_sessions/{sessionId}/items/{productId}
```

Karena live stock saat stock take sebaiknya berbasis session agar bounded.

---

## 7. Emergency Fix Plan — Phase 0

Target:

```text
10GB/hari -> <3GB/hari
```

Waktu:

```text
Hari 1–3
```

Tanpa migrasi besar.

### 7.1 Tambahkan `.indexOn`

Tindakan:

```text
Firebase Console -> Realtime Database -> Rules -> tambahkan index
```

Jangan ubah `.read` / `.write` secara drastis.

Target index:

```json
{
  "rules": {
    "waiter_tasks": {
      ".indexOn": ["assigned_waiter_id", "scheduled_for_date", "status", "rack_id"]
    },
    "cashier_tasks": {
      ".indexOn": ["scheduled_date", "status", "source_template_id"]
    },
    "orders": {
      ".indexOn": ["created_at", "status", "expires_at", "date"]
    },
    "waiter_activity_reports": {
      ".indexOn": ["waiter_id", "date", "report_date"]
    },
    "waiter_attendance": {
      ".indexOn": ["waiter_id", "date"]
    },
    "waiter_daily_points": {
      ".indexOn": ["waiter_id", "date"]
    },
    "audit_logs": {
      ".indexOn": ["timestamp", "entity_type", "created_at"]
    }
  }
}
```

Test:

```text
Pastikan Firebase listener dan backend query tetap berjalan.
Monitor Usage 24 jam.
```

---

### 7.2 Scope waiter listener

Sementara sebelum active node:

```javascript
firebaseDB.ref('waiter_tasks')
  .orderByChild('assigned_waiter_id')
  .equalTo(currentWaiterId)
  .on('child_changed', trigger);
```

Final setelah active node:

```javascript
firebaseDB.ref(`waiter_tasks_active/${today}/${currentWaiterId}`)
  .on('child_changed', trigger);
```

Test:

```text
Login sebagai 2 waiter berbeda.
Update task waiter A.
Pastikan waiter B tidak trigger polling.
```

---

### 7.3 Matikan polling redundan

Ubah logic:

```text
Jika realtime listener aktif, jangan jalankan pollTasks rutin.
Jika realtime listener gagal, gunakan fallback polling maksimal 5 menit sekali.
```

Jangan ada:

```text
pollTasks interval aktif
syncDueTasks interval aktif
Firebase listener aktif
```

secara bersamaan tanpa kontrol.

---

### 7.4 Fix `getWaiterTasksByWaiterId()`

Emergency:

```php
public function getWaiterTasksByWaiterId($waiterId, $dateFrom = null, $dateTo = null)
{
    $dateFrom = $dateFrom ?: now()->subDays(7)->format('Y-m-d');
    $dateTo = $dateTo ?: now()->format('Y-m-d');

    $snapshot = $this->database->getReference('waiter_tasks')
        ->orderByChild('scheduled_for_date')
        ->startAt($dateFrom)
        ->endAt($dateTo)
        ->getSnapshot();

    $items = $snapshot->getValue() ?: [];

    return array_filter($items, function ($task) use ($waiterId) {
        return (string)($task['assigned_waiter_id'] ?? '') === (string)$waiterId;
    });
}
```

Catatan:

```text
Ini emergency saja.
Final read waiter task harus dari MySQL atau /waiter_tasks_active.
```

---

### 7.5 Fix `cashier_tasks`

```php
public function getExistingRecurringMapForDate($date)
{
    $snapshot = $this->database->getReference('cashier_tasks')
        ->orderByChild('scheduled_date')
        ->equalTo($date)
        ->getSnapshot();

    return $snapshot->getValue() ?: [];
}
```

---

### 7.6 Cleanup command sementara

Buat command:

```text
php artisan make:command CleanupFirebaseLegacyNodes
```

Target:

```text
/waiter_tasks > 30 hari selesai/cancelled
/cashier_tasks > 7 hari
/orders completed/cancelled > 7 hari
/dana_payments > 7 hari atau limit tertentu
```

Jangan hapus sebelum data penting di-backup atau di-seed ke MySQL.

---

## 8. MySQL Schema Final

### 8.1 `waiter_tasks`

```sql
CREATE TABLE waiter_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_legacy_key VARCHAR(150) NULL UNIQUE,
    firebase_active_path VARCHAR(500) NULL,
    deterministic_key VARCHAR(150) NOT NULL UNIQUE,

    template_id BIGINT UNSIGNED NULL,
    template_legacy_key VARCHAR(150) NULL,

    task_type ENUM('general', 'rack_check') NOT NULL DEFAULT 'general',
    title VARCHAR(300) NOT NULL,
    description TEXT NULL,

    assigned_waiter_id VARCHAR(100) NOT NULL,
    assigned_waiter_name VARCHAR(200) NULL,

    scheduled_for_date DATE NOT NULL,
    scheduled_time TIME NULL,

    status ENUM('pending', 'in_progress', 'done', 'cancelled', 'rescheduled', 'ignored', 'failed') NOT NULL DEFAULT 'pending',
    publish_status ENUM('draft', 'published', 'failed') NOT NULL DEFAULT 'draft',
    sync_status ENUM('pending', 'synced', 'failed') NOT NULL DEFAULT 'pending',

    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',

    rack_id VARCHAR(100) NULL,
    rack_code VARCHAR(100) NULL,
    rack_name VARCHAR(200) NULL,

    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    completed_by VARCHAR(100) NULL,

    cancelled_at TIMESTAMP NULL,
    cancelled_by VARCHAR(100) NULL,
    cancel_reason VARCHAR(500) NULL,

    ignored_at TIMESTAMP NULL,
    ignored_by VARCHAR(100) NULL,
    ignore_reason VARCHAR(500) NULL,

    photo_url VARCHAR(500) NULL,
    notes TEXT NULL,
    metadata JSON NULL,

    sync_error TEXT NULL,
    synced_at TIMESTAMP NULL,

    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_waiter_date (assigned_waiter_id, scheduled_for_date),
    INDEX idx_date_status (scheduled_for_date, status),
    INDEX idx_sync_status (sync_status),
    INDEX idx_publish_status (publish_status),
    INDEX idx_task_type_date (task_type, scheduled_for_date),
    INDEX idx_rack_date (rack_id, scheduled_for_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 8.2 `cashier_tasks`

```sql
CREATE TABLE cashier_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_legacy_key VARCHAR(150) NULL UNIQUE,
    deterministic_key VARCHAR(150) NOT NULL UNIQUE,

    template_id BIGINT UNSIGNED NULL,
    title VARCHAR(300) NOT NULL,
    description TEXT NULL,

    assigned_cashier_id VARCHAR(100) NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NULL,

    status ENUM('pending', 'in_progress', 'done', 'cancelled', 'failed') NOT NULL DEFAULT 'pending',

    is_recurring BOOLEAN NOT NULL DEFAULT FALSE,
    recurrence_pattern VARCHAR(100) NULL,
    metadata JSON NULL,

    completed_at TIMESTAMP NULL,
    notes TEXT NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cashier_date (assigned_cashier_id, scheduled_date),
    INDEX idx_date_status (scheduled_date, status),
    INDEX idx_template_date (template_id, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 8.3 `orders`

```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_legacy_key VARCHAR(150) NULL UNIQUE,
    firebase_active_path VARCHAR(500) NULL,

    order_number VARCHAR(100) NOT NULL,
    customer_name VARCHAR(200) NULL,
    customer_phone VARCHAR(100) NULL,

    status ENUM('pending', 'processing', 'ready', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',

    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(100) NULL,
    payment_status ENUM('unpaid', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',

    items JSON NOT NULL,
    metadata JSON NULL,

    order_date DATE NOT NULL,
    expires_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_order_date (order_date),
    INDEX idx_status_date (status, order_date),
    INDEX idx_payment_status (payment_status),
    INDEX idx_order_number (order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 8.4 `firebase_sync_logs`

```sql
CREATE TABLE firebase_sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(100) NOT NULL,

    firebase_path VARCHAR(500) NOT NULL,
    action ENUM('set', 'update', 'remove', 'reconcile') NOT NULL,

    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',

    payload JSON NULL,
    error_message TEXT NULL,

    attempt_count INT NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMP NULL,
    next_retry_at TIMESTAMP NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status_retry (status, next_retry_at),
    INDEX idx_firebase_path (firebase_path(191)),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 8.5 Tabel lain yang harus dibuat bertahap

```text
waiter_task_templates
waiter_activity_reports
waiter_attendance
waiter_daily_points
waiter_penalties
waiter_manual_bonuses
waiter_bonus_summaries
waiter_leaderboards
bonus_configs
audit_logs
rack_products
racks
rack_product_stocks
rack_stock_movements
scan_attempts
purchase_orders
restock_requests
waiters
work_shifts
product_categories
```

---

## 9. Firebase Sync Strategy Final

### 9.1 Prinsip

```text
MySQL = benar
Firebase = mirror realtime
Jika berbeda, MySQL menang
```

Flow create task:

```text
Admin/Supervisor create task
-> Laravel simpan ke MySQL
-> generate deterministic_key
-> publish ke /waiter_tasks_active/{date}/{waiterId}/{taskId}
-> catat firebase_sync_logs
-> update sync_status
```

Flow complete task:

```text
Waiter complete task
-> Laravel API update MySQL
-> update/remove node active Firebase
-> catat firebase_sync_logs
```

Rekomendasi:

```text
Jangan jadikan browser menulis status akhir langsung ke Firebase tanpa API,
kecuali sudah ada validasi security rules yang kuat.
Lebih aman: browser -> Laravel API -> MySQL -> Sync Firebase.
```

---

### 9.2 Deterministic Key

Untuk task recurring:

```php
$key = 'wt_' . substr(hash('sha256', implode('::', [
    $templateId,
    $waiterId,
    $scheduledDate,
    $taskType,
    $rackId ?? 'none'
])), 0, 32);
```

Untuk task manual:

```php
$key = 'wt_manual_' . substr(hash('sha256', implode('::', [
    $createdBy,
    $waiterId,
    $scheduledDate,
    $title,
    microtime(true)
])), 0, 32);
```

Path active:

```php
$path = "waiter_tasks_active/{$scheduledDate}/{$waiterId}/{$key}";
```

---

### 9.3 FirebaseSyncService Final

```php
class FirebaseSyncService
{
    public function publishWaiterTask(WaiterTask $task): void
    {
        $date = $task->scheduled_for_date->format('Y-m-d');
        $waiterId = $task->assigned_waiter_id;
        $key = $task->deterministic_key;

        $path = "waiter_tasks_active/{$date}/{$waiterId}/{$key}";

        $payload = [
            'mysql_id' => $task->id,
            'deterministic_key' => $key,
            'title' => $task->title,
            'description' => $task->description,
            'task_type' => $task->task_type,
            'assigned_waiter_id' => $task->assigned_waiter_id,
            'assigned_waiter_name' => $task->assigned_waiter_name,
            'scheduled_for_date' => $date,
            'scheduled_time' => optional($task->scheduled_time)->format('H:i'),
            'status' => $task->status,
            'priority' => $task->priority,
            'rack_id' => $task->rack_id,
            'rack_code' => $task->rack_code,
            'rack_name' => $task->rack_name,
            'updated_at' => now()->toIso8601String(),
        ];

        $log = FirebaseSyncLog::create([
            'entity_type' => 'waiter_task',
            'entity_id' => (string) $task->id,
            'firebase_path' => $path,
            'action' => 'set',
            'status' => 'pending',
            'payload' => $payload,
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);

        try {
            app('firebase.database')->getReference($path)->set($payload);

            $task->update([
                'firebase_active_path' => $path,
                'publish_status' => 'published',
                'sync_status' => 'synced',
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $log->update([
                'status' => 'success',
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $task->update([
                'publish_status' => 'failed',
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => now()->addMinutes(5),
            ]);

            report($e);
        }
    }

    public function removeWaiterTask(WaiterTask $task): void
    {
        if (!$task->firebase_active_path) {
            return;
        }

        $path = $task->firebase_active_path;

        $log = FirebaseSyncLog::create([
            'entity_type' => 'waiter_task',
            'entity_id' => (string) $task->id,
            'firebase_path' => $path,
            'action' => 'remove',
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);

        try {
            app('firebase.database')->getReference($path)->remove();

            $task->update([
                'sync_status' => 'synced',
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $log->update([
                'status' => 'success',
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $task->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => now()->addMinutes(5),
            ]);

            report($e);
        }
    }
}
```

---

## 10. Reconcile Strategy

### 10.1 Tujuan

Reconcile memastikan:

```text
Task aktif di MySQL ada di Firebase.
Task selesai/cancelled tidak tertinggal di Firebase.
Task Firebase yang tidak ada di MySQL dihapus.
```

### 10.2 Command

```text
php artisan make:command ReconcileFirebaseActiveTasks
```

Schedule:

```php
Schedule::command('firebase:reconcile-active-tasks')->everyFifteenMinutes();
Schedule::command('firebase:cleanup-active-nodes')->dailyAt('01:00');
Schedule::command('firebase:retry-sync')->everyFiveMinutes();
```

### 10.3 Logic reconcile

```text
1. Ambil task MySQL hari ini status pending/in_progress.
2. Ambil /waiter_tasks_active/{today}.
3. Jika task MySQL belum ada di Firebase -> publish ulang.
4. Jika task Firebase tidak ada di MySQL -> remove.
5. Jika status beda -> MySQL overwrite Firebase.
6. Catat semua perubahan ke firebase_sync_logs.
```

---

## 11. Migration Strategy

### 11.1 Feature Flag

```php
return [
    'mysql_waiter_tasks' => env('FEATURE_MYSQL_WAITER_TASKS', false),
    'active_waiter_task_node' => env('FEATURE_ACTIVE_WAITER_TASK_NODE', false),
    'mysql_cashier_tasks' => env('FEATURE_MYSQL_CASHIER_TASKS', false),
    'mysql_orders' => env('FEATURE_MYSQL_ORDERS', false),
    'active_orders_node' => env('FEATURE_ACTIVE_ORDERS_NODE', false),
    'mysql_attendance' => env('FEATURE_MYSQL_ATTENDANCE', false),
    'mysql_bonus' => env('FEATURE_MYSQL_BONUS', false),
    'mysql_master_data' => env('FEATURE_MYSQL_MASTER_DATA', false),
];
```

### 11.2 Tahapan aman

```text
1. Seed Firebase -> MySQL.
2. Dual-write sementara.
3. Read masih dari Firebase.
4. Bandingkan data.
5. Switch read ke MySQL.
6. Publish active data ke Firebase active node.
7. Update listener frontend ke active node.
8. Cleanup legacy node bertahap.
```

---

## 12. Roadmap Final

### Phase 0 — Stop Bleeding

Target:

```text
10GB/hari -> <3GB/hari
```

Aksi:

```text
1. Tambah .indexOn tanpa mengubah auth rules secara agresif.
2. Scope listener waiter.
3. Matikan polling redundan.
4. Date-limit getWaiterTasksByWaiterId sebagai emergency.
5. Filter cashier_tasks by scheduled_date.
6. Tambahkan cleanup command sementara.
```

---

### Phase 1 — MySQL untuk task dan cashier task

Target:

```text
<1GB/hari
```

Aksi:

```text
1. Buat waiter_tasks.
2. Buat cashier_tasks.
3. Buat firebase_sync_logs.
4. Seed data lama.
5. Dual-write.
6. Read waiter task dari MySQL.
7. Publish task aktif ke /waiter_tasks_active.
8. Update waiter listener ke active node.
```

---

### Phase 2 — Active orders dan report besar

Target:

```text
±500MB/hari
```

Aksi:

```text
1. Buat orders table.
2. Buat /orders_active/{date}/{orderId}.
3. Update cashier listener.
4. Pindahkan activity_reports ke MySQL.
5. Pindahkan attendance ke MySQL.
6. Pindahkan audit_logs ke MySQL.
```

---

### Phase 3 — Bonus, leaderboard, master data

Target:

```text
±100–300MB/hari
```

Aksi:

```text
1. Pindahkan waiter_daily_points.
2. Pindahkan waiter_penalties.
3. Pindahkan waiter_manual_bonuses.
4. Pre-compute leaderboard di MySQL/cache.
5. Pindahkan rack_products.
6. Pindahkan waiter_task_templates.
7. Pindahkan allowed_waiters ke waiters/users MySQL.
```

---

### Phase 4 — Cleanup total

Target:

```text
±50–150MB/hari jika semua active node bounded
```

Aksi:

```text
1. Hapus legacy Firebase nodes yang sudah dimigrasi.
2. Matikan method FirebaseService legacy.
3. Hapus listener lama.
4. Pastikan hanya active node yang tersisa.
5. Final monitoring 7 hari.
```

---

## 13. Controller Refactor Pattern

Jangan biarkan controller memanggil `FirebaseService` langsung untuk data bisnis utama.

Sebelum:

```php
$tasks = $this->firebaseService->getWaiterTasksByWaiterId($waiterId);
```

Sesudah:

```php
$tasks = $this->waiterTaskService->getTodayTasksForWaiter($waiterId);
```

Service layer:

```php
class WaiterTaskService
{
    public function getTodayTasksForWaiter(string $waiterId)
    {
        if (config('features.mysql_waiter_tasks')) {
            return WaiterTask::query()
                ->where('assigned_waiter_id', $waiterId)
                ->where('scheduled_for_date', today())
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('scheduled_time')
                ->get();
        }

        return collect(
            $this->firebase->getWaiterTasksByWaiterId(
                $waiterId,
                now()->subDays(7)->format('Y-m-d'),
                now()->format('Y-m-d')
            )
        );
    }

    public function completeTask(int|string $taskId, array $payload): void
    {
        if (config('features.mysql_waiter_tasks')) {
            DB::transaction(function () use ($taskId, $payload) {
                $task = WaiterTask::findOrFail($taskId);

                $task->update([
                    'status' => 'done',
                    'completed_at' => now(),
                    'completed_by' => auth()->id(),
                    'notes' => $payload['notes'] ?? null,
                    'photo_url' => $payload['photo_url'] ?? null,
                ]);

                app(FirebaseSyncService::class)->removeWaiterTask($task);
            });

            return;
        }

        $this->firebase->updateWaiterTaskStatus($taskId, 'done', $payload);
    }
}
```

---

## 14. Testing Plan

### 14.1 Functional test

Checklist:

```text
[ ] Waiter hanya melihat task miliknya.
[ ] Waiter tidak menerima event task waiter lain.
[ ] Task baru muncul realtime.
[ ] Task selesai hilang/update realtime.
[ ] Admin live monitor tetap update.
[ ] Cashier order tetap realtime.
[ ] Cashier task tidak lagi membaca full node.
[ ] Report membaca dari MySQL.
[ ] Audit log bisa pagination.
[ ] Bonus leaderboard tetap akurat.
```

### 14.2 Migration test

Checklist:

```text
[ ] Seed Firebase -> MySQL bisa dry-run.
[ ] Seed bisa dijalankan ulang tanpa duplikasi.
[ ] Deterministic key mencegah task dobel.
[ ] Data sample MySQL sama dengan Firebase.
[ ] Feature flag bisa rollback.
[ ] Sync failure tercatat di firebase_sync_logs.
[ ] Reconcile bisa memperbaiki data hilang.
```

### 14.3 Bandwidth test

Pantau harian:

```text
Firebase Console -> Realtime Database -> Usage
Laravel log -> Firebase sync failed
MySQL slow query log
Jumlah row firebase_sync_logs failed
```

Target monitoring:

```text
Setelah Phase 0: <3GB/hari
Setelah Phase 1: <1GB/hari
Setelah Phase 2: <500MB/hari
Setelah Phase 3: 100–300MB/hari
Setelah Phase 4: 50–150MB/hari
```

---

## 15. Rollback Plan

### 15.1 Instant rollback

Gunakan feature flag:

```env
FEATURE_MYSQL_WAITER_TASKS=false
FEATURE_ACTIVE_WAITER_TASK_NODE=false
```

Lalu:

```bash
php artisan config:clear
php artisan cache:clear
```

Efek:

```text
Read/write kembali ke Firebase legacy.
MySQL tetap menyimpan data, tapi tidak dipakai sementara.
```

---

### 15.2 Rollback per fitur

```text
waiter task error -> matikan FEATURE_MYSQL_WAITER_TASKS
active node error -> matikan FEATURE_ACTIVE_WAITER_TASK_NODE
orders error -> matikan FEATURE_MYSQL_ORDERS
attendance error -> matikan FEATURE_MYSQL_ATTENDANCE
bonus error -> matikan FEATURE_MYSQL_BONUS
```

---

### 15.3 Backup

Sebelum migrasi:

```text
1. Export Firebase RTDB JSON.
2. Backup MySQL.
3. Jalankan seed dengan --dry-run.
4. Deploy saat traffic rendah.
5. Aktifkan feature flag bertahap.
```

---

## 16. Execution Checklist Final

### Phase 0

```text
[ ] Backup Firebase rules lama.
[ ] Tambah .indexOn.
[ ] Jangan ubah .read/.write sebelum validasi auth.
[ ] Scope listener waiter.
[ ] Matikan polling redundan.
[ ] Fix getWaiterTasksByWaiterId emergency date limit.
[ ] Fix cashier_tasks by scheduled_date.
[ ] Deploy.
[ ] Monitor 24 jam.
```

### Phase 1

```text
[ ] Buat migration waiter_tasks.
[ ] Buat migration cashier_tasks.
[ ] Buat migration firebase_sync_logs.
[ ] Buat model.
[ ] Buat FirebaseSyncService.
[ ] Buat WaiterTaskService.
[ ] Buat seed Firebase -> MySQL.
[ ] Buat retry sync command.
[ ] Buat reconcile command.
[ ] Aktifkan dual-write.
[ ] Aktifkan active node.
[ ] Update waiter listener ke /waiter_tasks_active.
[ ] Monitor 7 hari.
```

### Phase 2

```text
[ ] Buat orders table.
[ ] Buat /orders_active publisher.
[ ] Update cashier listener.
[ ] Migrasi activity reports.
[ ] Migrasi attendance.
[ ] Migrasi audit logs.
[ ] Monitor bandwidth.
```

### Phase 3

```text
[ ] Migrasi bonus data.
[ ] Migrasi leaderboard.
[ ] Migrasi master products.
[ ] Migrasi racks.
[ ] Migrasi task templates.
[ ] Migrasi allowed_waiters ke MySQL.
```

### Phase 4

```text
[ ] Cleanup legacy /waiter_tasks.
[ ] Cleanup legacy /orders.
[ ] Cleanup /cashier_tasks.
[ ] Hapus listener lama.
[ ] Deprecate method FirebaseService lama.
[ ] Final monitoring 7 hari.
```

---

## 17. Final Recommendation

Plan ini layak dieksekusi dengan prinsip:

```text
Jangan migrasi semua sekaligus.
Stop bleeding dulu.
Pindahkan task dan cashier task dulu.
Gunakan active node baru.
Baru pindahkan report/history/master data.
```

Kunci keberhasilan:

```text
1. /waiter_tasks lama harus dianggap legacy.
2. /waiter_tasks_active/{date}/{waiterId}/{taskId} harus jadi target realtime final.
3. MySQL harus menjadi source of truth.
4. Firebase tidak boleh menyimpan history panjang.
5. Semua sync harus punya log dan reconcile.
6. Semua legacy listener harus dimatikan setelah active node stabil.
```

Jika semua phase dijalankan dengan benar, target realistis:

```text
10GB/hari -> 100–300MB/hari
```

Jika setelah refactor masih di atas:

```text
>1GB/hari
```

berarti masih ada salah satu masalah ini:

```text
1. Listener lama masih aktif.
2. Node legacy masih dibaca.
3. Cleanup tidak jalan.
4. Query belum pakai active node.
5. Report masih baca dari RTDB.
6. Cashier/order masih full-read.
```

---

## 18. Data Safety Guards (Tambahan Wajib)

### 18.1 Seed Validation Gate

Setelah seed Firebase -> MySQL selesai, WAJIB validasi count sebelum feature flag boleh diaktifkan.

```php
// app/Console/Commands/ValidateSeedCount.php

public function handle()
{
    $firebaseCount = count(
        app('firebase.database')->getReference('waiter_tasks')->getSnapshot()->getValue() ?? []
    );

    $mysqlCount = \App\Models\WaiterTask::count();

    $diff = abs($firebaseCount - $mysqlCount);
    $diffPercent = $firebaseCount > 0 ? ($diff / $firebaseCount) * 100 : 0;

    $this->info("Firebase: {$firebaseCount} | MySQL: {$mysqlCount} | Diff: {$diff} ({$diffPercent}%)");

    if ($diffPercent > 1) {
        $this->error("BLOCKED: Selisih > 1%. Jangan aktifkan feature flag.");
        $this->error("Investigasi record yang hilang sebelum lanjut.");
        return Command::FAILURE;
    }

    $this->info("PASSED: Data konsisten. Feature flag boleh diaktifkan.");
    return Command::SUCCESS;
}
```

Jalankan:

```bash
php artisan seed:validate-count --model=WaiterTask
php artisan seed:validate-count --model=CashierTask
```

Aturan:

```text
Jika selisih > 1% -> BLOCK activation feature flag.
Jika selisih 0-1% -> investigasi manual record yang miss, lalu approve.
Jika 0% -> langsung approve.
```

---

### 18.2 Cleanup Hard Guard

Command `CleanupFirebaseLegacyNodes` WAJIB punya pre-check sebelum menghapus apapun:

```php
// Di dalam handle() command cleanup

// Guard 1: Feature flag harus ON
if (!config('features.mysql_waiter_tasks')) {
    $this->error("ABORT: FEATURE_MYSQL_WAITER_TASKS belum ON. Cleanup ditolak.");
    return Command::FAILURE;
}

// Guard 2: MySQL tidak boleh kosong
if (\App\Models\WaiterTask::count() === 0) {
    $this->error("ABORT: MySQL waiter_tasks kosong. Cleanup ditolak.");
    return Command::FAILURE;
}

// Guard 3: Seed validation harus pernah pass
$lastValidation = \App\Models\FirebaseSyncLog::where('action', 'reconcile')
    ->where('status', 'success')
    ->where('created_at', '>=', now()->subDays(7))
    ->exists();

if (!$lastValidation) {
    $this->error("ABORT: Tidak ada reconcile sukses dalam 7 hari terakhir. Cleanup ditolak.");
    return Command::FAILURE;
}

$this->info("Guards passed. Memulai cleanup...");
```

Aturan:

```text
Cleanup HANYA jalan jika:
1. Feature flag ON (data sudah dibaca dari MySQL)
2. MySQL table TIDAK kosong (seed sudah jalan)
3. Reconcile pernah sukses dalam 7 hari terakhir (data terverifikasi)
```

---

### 18.3 Point-in-Time Backup Protocol

Sebelum SETIAP phase deployment:

```text
1. Export Firebase RTDB JSON via Console:
   Firebase Console -> Realtime Database -> ... (menu) -> Export JSON
   Simpan sebagai: backup-rtdb-YYYY-MM-DD-phaseN.json

2. Backup MySQL:
   mysqldump -u root -p order_db > backup-mysql-YYYY-MM-DD-phaseN.sql

3. Simpan kedua file di lokasi TERPISAH dari server production:
   - Google Drive / cloud storage
   - Atau server backup terpisah

4. Verifikasi backup bisa di-restore:
   - Import JSON ke Firebase project test (opsional)
   - Import SQL ke database test lokal
```

Aturan:

```text
JANGAN deploy phase baru tanpa backup.
JANGAN hapus backup sampai phase stable 30 hari.
Backup = jaring pengaman terakhir jika seed + MySQL + Firebase semua bermasalah.
```

---

## 19. Koreksi Kritikal Hasil Verifikasi Kode (Wajib Sebelum Phase 1)

> Tanggal: 2026-06-06
> Status: 4 lubang dikonfirmasi terhadap kode nyata. Phase 0 aman. Phase 1+ HARUS tutup lubang ini dulu.
> Sumber verifikasi: `app/Services/FirebaseService.php`, `resources/views/waiter/tasks.blade.php`.

### 19.1 Lubang 1 — Key Mapping: Firebase key vs MySQL id

Bukti kode:

```text
FirebaseService::updateWaiterTaskStatus($taskId, ...)   // baris ~3031
  -> getReference('waiter_tasks/'.$taskId)              // baris ~3057  = FIREBASE KEY

tasks.blade.php:
  route('waiter.task.complete', ['id' => '__TASK_ID__']) // baris ~1963
  data-task-id="${task.id}"                              // baris ~2856 = FIREBASE KEY
```

Masalah:

```text
Frontend kirim FIREBASE KEY.
Plan section 13 completeTask() pakai WaiterTask::findOrFail($taskId) = MYSQL ID.
=> Lookup gagal total saat FEATURE_MYSQL_WAITER_TASKS=ON.
```

Solusi wajib — resolve task by key, bukan id:

```php
// WaiterTaskService::completeTask($firebaseOrDeterministicKey, array $payload)
public function completeTask(string $key, array $payload): array
{
    if (config('features.mysql_waiter_tasks')) {
        return DB::transaction(function () use ($key, $payload) {
            $task = WaiterTask::query()
                ->where('deterministic_key', $key)
                ->orWhere('firebase_legacy_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: jika sudah done, return sukses tanpa re-proses
            if ($task->status === 'done') {
                return ['success' => true, 'message' => 'Sudah selesai (idempotent).'];
            }

            $task->update([
                'status' => 'done',
                'completed_at' => now(),
                'completed_by' => $payload['waiter_id'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'photo_url' => $payload['photo_url'] ?? null,
            ]);

            app(FirebaseSyncService::class)->removeWaiterTask($task);

            return ['success' => true, 'message' => 'Task selesai.'];
        });
    }

    return $this->firebase->updateWaiterTaskStatus($key, 'done', /* ...args lama... */);
}
```

Aturan:

```text
Frontend TIDAK boleh diubah kirim mysql_id.
Backend HARUS bisa resolve dari key (deterministic_key ATAU firebase_legacy_key).
Index UNIQUE pada kedua kolom sudah ada di schema 8.1 -> lookup cepat.
```

---

### 19.2 Lubang 2 — Signature `getWaiterTasksByWaiterId` lama hanya 1 param

Bukti kode:

```text
FirebaseService::getWaiterTasksByWaiterId($waiterId)   // baris ~2841, SATU param
  -> orderByChild('assigned_waiter_id')->equalTo($waiterId)  // tanpa date limit
  -> ada requestCache internal
```

Plan section 7.4 ubah jadi 3 param (`$dateFrom`, `$dateTo`). Itu breaking change.

Caller wajib audit dulu (plan section 3 sebut 6 caller):

```text
WaiterController@tasksIndex
WaiterController@pollTasks
WaiterController@syncDueTasks
waiter:process-tasks
waiter:send-weekly-report
waiter:send-monthly-report
```

Aturan:

```text
1. Param baru HARUS punya default (nullable) -> caller lama tidak pecah.
2. Weekly/monthly report BUTUH rentang lebih luas dari 7 hari default ->
   WAJIB pass dateFrom/dateTo eksplisit, jangan andalkan default 7 hari.
   Kalau tidak, laporan bulanan jadi salah (cuma 7 hari terakhir).
3. requestCache key sekarang HARUS termasuk dateFrom+dateTo,
   bukan cuma waiterId. Kalau tidak, call kedua dengan rentang beda
   kembalikan hasil rentang pertama (cache poisoning).
```

Fix cache key:

```php
$cacheKey = 'waiter_tasks_by_id_' . $waiterId . '_' . $dateFrom . '_' . $dateTo;
```

---

### 19.3 Lubang 3 — Timezone: JS pakai UTC, bukan WIB

Bukti kode:

```text
tasks.blade.php baris ~5326:
  const todayStr = new Date().toISOString().slice(0, 10);  // UTC!
  const taskDate = t.scheduled_for_date || '';
  const isOldTask = taskDate && taskDate !== todayStr;

baris ~2083:
  String(context.reportDate || new Date().toISOString().slice(0,10));  // UTC!
```

Masalah:

```text
toISOString() = UTC. Toko zona WIB (UTC+7).
Jam 17:00-23:59 WIB = 10:00-16:59 UTC (masih tanggal sama, aman).
TAPI jam 00:00-06:59 WIB = 17:00-23:59 UTC HARI SEBELUMNYA.

Efek pada active node /waiter_tasks_active/{date}/{waiterId}:
- Subuh WIB, JS hitung {date} = kemarin (UTC).
- Listener subscribe ke node tanggal SALAH.
- Task hari ini TIDAK muncul. Waiter lihat kosong.
```

Solusi wajib — server kirim tanggal WIB, JS jangan hitung sendiri:

```php
// Controller / Blade: inject tanggal lokal dari server (server pakai config app.timezone)
window.APP_TODAY = "{{ now()->timezone('Asia/Jakarta')->format('Y-m-d') }}";
```

```javascript
// JS: PAKAI APP_TODAY, jangan new Date().toISOString()
const todayStr = window.APP_TODAY;
```

Aturan:

```text
1. SEMUA penentuan {date} untuk active node HARUS dari server WIB.
2. scheduled_for_date di MySQL HARUS disimpan sebagai tanggal WIB.
3. Verifikasi config('app.timezone') = 'Asia/Jakarta' ATAU
   selalu eksplisit ->timezone('Asia/Jakarta') saat format tanggal node.
4. Cek juga config/app.php sebelum deploy.
```

---

### 19.4 Lubang 4 — Seed Validation Gate cacat (count global)

Masalah section 18.1:

```text
Bandingkan count(Firebase /waiter_tasks TOTAL) vs WaiterTask::count() TOTAL.
Firebase simpan history berbulan-bulan.
Seed awal mungkin hanya migrasi rentang tertentu.
=> Selisih hampir pasti > 1% => gate SELALU BLOCK. Tidak berguna.
```

Fix — bandingkan scope yang SAMA (per rentang tanggal), bukan total:

```php
public function handle()
{
    $from = $this->option('from') ?: now()->subDays(30)->format('Y-m-d');
    $to   = $this->option('to')   ?: now()->format('Y-m-d');

    // Firebase: hitung HANYA dalam rentang sama (butuh .indexOn scheduled_for_date)
    $fbSnap = app('firebase.database')->getReference('waiter_tasks')
        ->orderByChild('scheduled_for_date')
        ->startAt($from)->endAt($to)
        ->getSnapshot();
    $firebaseCount = $fbSnap->numChildren();

    // MySQL: rentang sama
    $mysqlCount = \App\Models\WaiterTask::query()
        ->whereBetween('scheduled_for_date', [$from, $to])
        ->count();

    $diff = abs($firebaseCount - $mysqlCount);
    $diffPercent = $firebaseCount > 0 ? ($diff / $firebaseCount) * 100 : 0;

    $this->info("[{$from}..{$to}] Firebase: {$firebaseCount} | MySQL: {$mysqlCount} | Diff: {$diff} ({$diffPercent}%)");

    if ($diffPercent > 1) {
        $this->error("BLOCKED: Selisih > 1% pada rentang sama. Investigasi sebelum aktifkan flag.");
        return Command::FAILURE;
    }
    $this->info("PASSED.");
    return Command::SUCCESS;
}
```

Aturan:

```text
Bandingkan rentang tanggal IDENTIK di kedua sisi.
Default rentang = 30 hari terakhir (data aktif relevan).
History lama di luar rentang TIDAK wajib di-seed -> bukan alasan block.
```

---

### 19.5 Reconcile Contract — Guard 3 cleanup butuh row eksplisit

Masalah section 18.2 Guard 3:

```text
Cek FirebaseSyncLog WHERE action='reconcile' AND status='success' dalam 7 hari.
TAPI publishWaiterTask() hanya tulis action='set'.
reconcile command BELUM didefinisi tulis row action='reconcile'.
=> Guard 3 SELALU gagal -> cleanup tidak pernah jalan.
```

Fix — reconcile command WAJIB tulis log run-level di akhir tiap eksekusi:

```php
// Di akhir ReconcileFirebaseActiveTasks::handle()
FirebaseSyncLog::create([
    'entity_type' => 'reconcile_run',
    'entity_id'   => 'waiter_tasks',
    'firebase_path' => 'waiter_tasks_active',
    'action'      => 'reconcile',
    'status'      => $hadFatalError ? 'failed' : 'success',
    'payload'     => [
        'republished' => $republishedCount,
        'removed'     => $removedCount,
        'overwritten' => $overwrittenCount,
        'range'       => [$from, $to],
    ],
    'attempt_count' => 1,
    'last_attempt_at' => now(),
]);
```

Aturan kontrak:

```text
1. Reconcile WAJIB tulis 1 row entity_type='reconcile_run' action='reconcile' tiap run.
2. status='success' HANYA jika tidak ada fatal error.
3. Guard 3 cleanup baca row ini -> kontrak nyambung.
4. Jangan pakai action='reconcile' untuk per-entity (nanti rancu dgn run-level).
   Per-entity perubahan pakai action set/update/remove seperti biasa.
```

---

### 19.6 Dual-Write Conflict — siapa menang saat transisi

Masalah belum dibahas plan:

```text
Saat dual-write aktif (Phase 1 langkah 5), ada 2 penulis status:
- Browser lama -> route waiter.task.complete -> updateWaiterTaskStatus() -> tulis /waiter_tasks (legacy)
- Backend baru -> WaiterTaskService -> MySQL + /waiter_tasks_active
Jika listener sudah pindah tapi sebagian browser belum reload -> status bentrok.
```

Aturan urutan migrasi (HARUS berurutan, jangan loncat):

```text
1. Deploy backend dual-write. Read MASIH dari Firebase legacy. Listener MASIH legacy.
2. Verifikasi MySQL terisi konsisten (gate 19.4 PASS).
3. Switch READ ke MySQL (flag mysql_waiter_tasks=ON). Listener MASIH legacy.
4. Route complete diarahkan ke WaiterTaskService::completeTask (resolve by key, 19.1).
   Pada titik ini MySQL = penulis status tunggal. Firebase di-mirror oleh sync.
5. BARU switch listener ke /waiter_tasks_active (flag active_waiter_task_node=ON).
6. Monitor 7 hari. Baru cleanup.
```

Prinsip resolusi konflik:

```text
MySQL menang. Reconcile (section 10) overwrite Firebase dari MySQL tiap 15 menit.
Browser TIDAK boleh tulis status final langsung ke Firebase setelah langkah 4.
```

---

### 19.7 Listener tidak re-subscribe saat ganti hari

Masalah:

```text
Waiter portal sering dibuka seharian penuh.
window.APP_TODAY di-set saat page load.
Lewat tengah malam WIB, {date} berubah tapi listener masih dengar node KEMARIN.
Task hari baru tidak muncul sampai waiter manual reload.
```

Solusi — cek pergantian hari, re-subscribe:

```javascript
let activeDate = window.APP_TODAY;

function currentJakartaDate() {
  // konsisten dgn server: minta dari endpoint ringan ATAU hitung offset +7
  return new Date(Date.now() + 7 * 3600 * 1000).toISOString().slice(0, 10);
}

setInterval(() => {
  const nowDate = currentJakartaDate();
  if (nowDate !== activeDate) {
    tasksRef.off();                 // lepas listener node lama
    activeDate = nowDate;
    subscribeActiveTasks(activeDate); // subscribe node baru
  }
}, 60 * 1000); // cek tiap menit
```

Aturan:

```text
1. Deteksi pergantian hari WIB tiap menit.
2. Saat ganti: off() node lama, on() node baru.
3. Offset +7 jam dipakai HANYA untuk deteksi tanggal, bukan source of truth.
   Source {date} tetap APP_TODAY awal + hasil re-compute ini.
```

---

### 19.8 Update Execution Checklist Phase 1

Tambahan item WAJIB sebelum Phase 1 dianggap selesai:

```text
[ ] completeTask resolve by deterministic_key/firebase_legacy_key (19.1), BUKAN findOrFail(id).
[ ] getWaiterTasksByWaiterId param baru nullable + cache key sertakan rentang (19.2).
[ ] Weekly/monthly report pass dateFrom/dateTo eksplisit (19.2).
[ ] APP_TODAY dari server WIB; JS stop pakai toISOString() untuk {date} (19.3).
[ ] Verifikasi config('app.timezone') sebelum deploy (19.3).
[ ] Seed gate bandingkan rentang identik, bukan total global (19.4).
[ ] Reconcile tulis row action='reconcile' tiap run (19.5).
[ ] Urutan dual-write 6 langkah dipatuhi, tidak loncat (19.6).
[ ] Listener re-subscribe saat ganti hari (19.7).
```

---

### 19.9 Verdict

```text
Phase 0  : AMAN dieksekusi sekarang. Low-risk, reversible.
Phase 1+ : Tutup lubang 19.1-19.7 dulu. Tanpa itu, complete task gagal (key),
           report bulanan salah (cache+default), task subuh hilang (timezone),
           gate macet (count), cleanup tak jalan (reconcile contract).
```

---

## 20. Temuan Eksekusi (Realita Kode vs Plan) — 2026-06-06

> Catatan hasil eksekusi nyata. Plan asli SEBAGIAN tidak cocok kode. Bagian ini
> wajib dibaca sebelum lanjut phase berikutnya.

### 20.1 TEMUAN UTAMA — Foto base64 di RTDB (TIDAK ADA di plan)

```text
Akar bandwidth TERBESAR: foto task disimpan base64 di /waiter_tasks node,
cap 3MB/foto, 2 foto/task (proof + before). Ditarik tiap read/listener.
Plan section 1-19 fokus jumlah read/scope listener -> LEWATKAN ini sepenuhnya.

FIX (commit 5fce23b, 132777b):
- uploadTaskPhoto() -> Firebase Storage, simpan URL (~100 byte) bukan base64
- 3 titik tulis di-wire: completions[], completed_photo_proof_url, _before_url
- command tasks:migrate-photos untuk foto lama (idempotent, --dry-run)
- .env FIREBASE_STORAGE_BUCKET dikoreksi -> imowebdev-project.firebasestorage.app
  (sebelumnya nunjuk project lain mataramorder, tak terakses kredensial)
- read aman: img src handle base64 lama + URL baru transparan
```

### 20.2 Klaim plan yang BASI (read sudah bounded sebelum sesi)

```text
orders -> MySQL (Phase 2)      : getOrders() bare NOL caller; read harian
                                 (getOrdersByDate/Range/AndWaiter) SUDAH filtered.
cashier_tasks full-read (7.5)  : getExistingRecurringMapForDate TIDAK ADA di kode.
polling redundan (7.3)         : sudah adaptif (getPollInterval).
waiter_penalties (Phase 3)     : sudah filtered.
```

### 20.3 Bug full-read ditemukan + fixed (kelas sama: terima rentang, query unbounded)

```text
getWaiterTasksByWaiterId    Phase 0  FIXED (commit 38f733d)
getAuditLogs                Phase 2  FIXED (45a62f1) - full-read tiap audit page
getWaiterTaskPerformance    Phase 2  FIXED (45a62f1) - bug baru
getManualBonusesByPeriod    Phase 3  FIXED (951e3a4) - bug baru
getWaiterActivityReports    Phase 2  FIXED (45a62f1)
```

### 20.4 Lubang fondasi Phase 1 (BELUM ditutup — wajib sebelum flag ON)

```text
LUBANG 1  Read path tak gated: buildWaiterTaskBuckets (WaiterController L1001)
          panggil firebase->getWaiterTasksByWaiterId langsung.
          WaiterTaskService::getTodayTasksForWaiter (gated, benar) NOL caller.
          -> flag ON, read tetap Firebase. MySQL read mati.

LUBANG 2  Create tak dual-write: createWaiterTasksFromAssignment (L2431) +
          createDisplayRefillTask (L2478) cuma push Firebase. Nol tulis MySQL.
          -> task baru tak masuk MySQL -> completeTask firstOrFail GAGAL.

LUBANG 3  Schema MySQL (8.1) TAK LENGKAP untuk portal: ~14 field hilang
          (requires_*, category_*, completed_stock_report, completed_product_checklist,
          completions[], completed_no_out_of_stock, dll). Portal baca field penuh.
          -> active-node section 6.1 sengaja ringan, TAPI plan tak jelaskan
          dari mana portal baca detail kalau pindah MySQL. Gap arsitektur.
```

### 20.5 attendance — tak bisa cheap-filter

```text
getAllAttendanceByDate (L8197): node /waiter_attendance/{waiterId}/{date}
-> date adalah KEY bukan child value. orderByChild murah mustahil.
Perlu restructure node (plan larang) atau mirror node atau MySQL. DEFER.
```

### 20.6 Rekomendasi urutan revisi

```text
1. [DONE] Photo offload (dampak terbesar)
2. [DONE] Bound semua full-read read (audit/reports/perf/bonus)
3. Tutup lubang fondasi Phase 1 (20.4) SEBELUM flag ON
4. Master data migrasi (rack_products, categories, work_shifts) - low risk
5. attendance + audit + reports -> MySQL (Phase 2 penuh) bila perlu history
6. Task -> MySQL: schema harus superset (tutup lubang 3) ATAU hybrid list/detail
```

---

## 21. Status Terkini (Update) — 2026-06-06

> Menggantikan status "OPEN" di section 20.4. Lubang fondasi SUDAH ditutup +
> diverifikasi runtime (bukan sekadar lint).

### 21.1 Photo offload — SELESAI (temuan #1, di luar plan asli)

```text
Foto base64 di RTDB (cap 3MB, 2/task) = akar bandwidth terbesar.
- uploadTaskPhoto() -> Firebase Storage, simpan URL ~100 byte
- 3 titik tulis wired (completions[], proof, before)
- command tasks:migrate-photos (idempotent) -> 32 task, 34 foto, B64:0 URL:17
- .env FIREBASE_STORAGE_BUCKET dikoreksi -> imowebdev-project.firebasestorage.app
- VERIFIED runtime: upload return URL valid
```

### 21.2 Fondasi Phase 1 — 3 lubang TERTUTUP + runtime-verified

```text
LUBANG 1 read   getWaiterTasksByWaiterId flag-gated MySQL, return firebase_payload
                verbatim + fallback kolom struktural. VERIFIED: 8 task, title+status OK.
LUBANG 2 create dualWriteWaiterTaskToMysql() di 4 titik (assignment, refill,
                recurring x2). VERIFIED: create -> id=35, priority OK, payload set.
LUBANG 3 schema migration firebase_payload JSON + model cast + seed isi.
                VERIFIED: 32/32 row payload terisi.
completeTask    VERIFIED: resolve by key -> pending->done, completed_at set.
```

### 21.3 Bug ditemukan + fixed sesi ini (5, kelas sama)

```text
getAuditLogs, getWaiterTaskPerformance, getManualBonusesByPeriod  (full-read unbounded)
seed mapper priority/task_type null            (commit 448e281)
dual-write helper priority/task_type null      (commit 69bd018, runtime-caught)
```

### 21.4 database.rules.json

```text
File rules final di repo root, sudah diaplikasikan ke Console.
Fix vs draft: waiter_manual_bonuses .indexOn tambah "date" (query pakai 'date').
Index cocok semua query kode yang diverifikasi sesi ini.
```

### 21.5 Sisa produksi (di luar sesi kode)

```text
[x] apply database.rules.json ke Console
[ ] seed full range (di luar window test 6 hari)
[ ] FEATURE_MYSQL_WAITER_TASKS=true di staging -> uji portal browser
[ ] monitor bandwidth (target Phase 1 <1GB/hari; photo offload dorong lebih turun)
[ ] Phase 2/3 migrasi MySQL penuh (attendance/audit/reports/bonus) - bila perlu history
[ ] Phase 4 cleanup legacy
```

### 21.6 Catatan flag

```text
FEATURE_MYSQL_WAITER_TASKS default FALSE -> produksi belum berubah.
Read+create+complete+schema sudah nyambung -> flag ON tak lagi langsung rusak.
Foto BARU sudah ke Storage tanpa bergantung flag (independen).
```


