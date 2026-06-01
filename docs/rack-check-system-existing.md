# Sistem Tugas Cek Rak (Rack Check) — Dokumentasi Lengkap

**Project**: Laravel order app (S:\wamp64\www\endra\order)
**Tanggal dokumentasi**: 2026-06-01
**Audience**: AI assistant + developer untuk understanding cepat

Dokumen ini menjelaskan SELURUH lifecycle sistem cek rak yang sekarang aktif: dari pembuatan template, generator harian, AI Balancing scoring, eksekusi waiter, review Finance, hingga poin masuk bonus bulanan + penalty kalau missed.

---

## 1. Big Picture (Flow End-to-End)

```
┌─────────────────────────────────────────────────────────────────┐
│  PHASE 1: TEMPLATE CREATION (Admin / Supervisor)                │
├─────────────────────────────────────────────────────────────────┤
│  Admin buka /admin/tasks/studio                                 │
│  Pilih scope=rack_check, pilih rak target, set recurrence       │
│  Pilih assignment (single / role / role+selected_waiter_ids)    │
│  Set jadwal (fixed time atau shift_relative)                    │
│  Submit → POST /admin/tasks                                     │
│       ↓                                                         │
│  TaskController@store (line 240) → storeRecurring (line 2119)   │
│       ↓                                                         │
│  FirebaseService@createRecurringWaiterTaskTemplate (line 3515)  │
│       ↓                                                         │
│  RTDB: /waiter_task_templates/{push_id} = template payload      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  PHASE 2: GENERATOR HARIAN (Cron every 5 min)                   │
├─────────────────────────────────────────────────────────────────┤
│  Schedule::command('waiter:process-tasks')->everyFiveMinutes() │
│       ↓                                                         │
│  generateDueRecurringWaiterTasks() — list templates due         │
│       ↓                                                         │
│  Per template: generateRecurringTasksForDate() (line 3951)      │
│       ↓ ↓ ↓                                                     │
│  resolveTargetWaiters() (line 5018) — pilih kandidat waiter    │
│       ↓                                                         │
│  Filter isWorkingDay() (line 4143) — skip LIBUR                 │
│       ↓                                                         │
│  Filter daily cap (line 4304) — FULL=2, PAGI/SORE/SHIFT_*=1     │
│       ↓                                                         │
│  AI Balancing score (line 10532) — pilih waiter terbaik         │
│       ↓                                                         │
│  Defensive guard (line 4511) — re-check LIBUR sebelum persist   │
│       ↓                                                         │
│  Build node_key SHA256 → write /waiter_tasks/{node_key}         │
│       ↓                                                         │
│  markOverdueWaiterTasks() — task overdue auto penalty           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  PHASE 3: WAITER EXECUTION (Portal /waiter/tasks)               │
├─────────────────────────────────────────────────────────────────┤
│  Waiter login → buka /waiter/tasks                              │
│  Lihat task pending hari ini                                    │
│  Klik task → barcode scan rak (jika rack_check)                 │
│  Foto sebelum (kalau requires_photo_before)                     │
│  Eksekusi → foto sesudah → submit                               │
│       ↓                                                         │
│  POST /waiter/tasks/{id}/complete                               │
│       ↓                                                         │
│  WaiterController@completeTask (line 344)                       │
│       ↓                                                         │
│  FirebaseService@updateWaiterTaskStatus → status=done           │
│  + recheck_pending=true (kalau rack_check)                      │
│       ↓                                                         │
│  BonusService@autoScoreDailyPoints — categories/auto poin       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  PHASE 4: FINANCE REVIEW (Portal /waiter/tasks tab Verifikasi)  │
├─────────────────────────────────────────────────────────────────┤
│  Finance/Supervisor login → tab "Verifikasi Cek Rak" muncul     │
│  Lihat list task done + recheck_pending=true                    │
│  Buka task → lihat foto bukti → input poin 0-10 + notes         │
│       ↓                                                         │
│  POST /waiter/tasks/{id}/recheck                                │
│       ↓                                                         │
│  WaiterController@submitRackCheckReview (line 575)              │
│       ↓                                                         │
│  FirebaseService@submitRackCheckReview (line 2677)              │
│       ↓                                                         │
│  Update task: recheck_pending=false, recheck_points=N           │
│       ↓                                                         │
│  BonusService@saveAutoDailyScoreRackOnly (line 358)             │
│       ↓                                                         │
│  /bonus_daily_points/{waiterId}/{date}/categories/rack_recheck  │
│  → poin masuk bonus bulanan (max 10/hari)                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Phase 1 — Template Creation

### 2.1 UI: `/admin/tasks/studio`

- **File**: [resources/views/admin/tasks/studio.blade.php](file:///S:/wamp64/www/endra/order/resources/views/admin/tasks/studio.blade.php)
- **Framework**: AlpineJS
- **Legacy creator**: [resources/views/admin/tasks/create.blade.php](file:///S:/wamp64/www/endra/order/resources/views/admin/tasks/create.blade.php) (Board Builder drag-and-drop)
- **JS validation**: `validateForm()` pada line ~2370

### 2.2 Form Fields

| Field | Tipe | Keterangan |
|---|---|---|
| `task_scope` / `task_type` | hidden | value: `rack_check` |
| `rack_id` / `rack_ids[]` | required | Firebase push key dari rack |
| `assignment_mode` / `assignment_type` | enum | `single`, `role`, `all` |
| `assigned_waiter_id` | optional | kalau single |
| `assigned_waiter_role` | enum | `kasir`, `pelayan`, `backup`, `finance`, `supervisor` |
| `role_assignment_mode` | enum | `all`, `rolling`, `selected` (legacy) |
| `selected_waiter_ids[]` | array | rotation waiter list |
| `requires_barcode_scan` | boolean | hardcoded `true` untuk rack_check |
| `photo_mode` | enum | `none`, `after`, `both` |
| `requires_photo_before` | boolean | foto kondisi sebelum |
| `requires_photo_proof` | boolean | foto sesudah selesai |
| `schedule_mode` | enum | `fixed`, `shift_relative` |
| `schedule_time` | time | misal `09:00` (kalau fixed) |
| `time_limit_minutes` | int | deadline duration |
| `shift_offset_minutes` | int | trigger setelah shift mulai |
| `deadline_mode` | enum | `fixed`, `before_shift_end` |
| `deadline_before_end_minutes` | int | deadline sebelum shift end |
| `recurrence_type` | enum | `daily`, `weekly`, `every_n_days` |
| `weekly_day` | int | 1-7 (Mon-Sun) kalau weekly |
| `interval_days` | int | N hari untuk every_n_days |
| `recurrence_anchor_date` | date | start anchor Y-m-d |
| `rolling_enabled` | boolean | aktivasi rolling rotation |
| `rolling_period` | enum | `daily`, `weekly`, `monthly` |
| `rolling_waiter_ids[]` | array | queue order |
| `rolling_anchor_date` | date | anchor rotation |

### 2.3 Controller: `Admin\TaskController`

- **File**: [app/Http/Controllers/Admin/TaskController.php](file:///S:/wamp64/www/endra/order/app/Http/Controllers/Admin/TaskController.php)

| Method | Line | Fungsi |
|---|---|---|
| `store(StoreTaskRequest $request)` | 240 | Entry point, delegate ke `storeRecurring()` |
| `storeRecurring(...)` | 2119 | Map field → Firebase payload, panggil service |
| `rackMassAssign(Request $request)` | 835 | AJAX endpoint untuk mass role rolling |
| `validateConflictingConfig()` | 1301 | Backend conflict guard (cegah duplicate active rack template) |

### 2.4 Validation: `StoreTaskRequest`

- **File**: [app/Http/Requests/StoreTaskRequest.php](file:///S:/wamp64/www/endra/order/app/Http/Requests/StoreTaskRequest.php)
- Standard Laravel rules: `title nullable|string|max:255`, `description nullable|string|max:1000`, dll

### 2.5 Service: `FirebaseService::createRecurringWaiterTaskTemplate`

- **File**: [app/Services/FirebaseService.php#L3515](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L3515)
- **Signature**:
  ```php
  public function createRecurringWaiterTaskTemplate(array $data)
  ```
- **Action**: Push ke `/waiter_task_templates` (line 3596)

### 2.6 Schema `/waiter_task_templates/{id}`

```json
{
  "title": "Nama Rak (untuk rack_check) atau Judul (untuk general)",
  "description": "",
  "priority": "normal",
  "assigned_by": "Supervisor",
  "task_type": "rack_check | general",
  "category_id": null,
  "category_name": null,

  "requires_barcode_scan": true,
  "requires_photo_proof": true,
  "requires_photo_before": false,

  "rack_target_scope": "single | all",
  "rack_id": "-Oi...",
  "rack_name": "RAK SENAR",
  "rack_location": "Lokasi Rak",
  "rack_barcode_value": "RAK-XXXX-YYYY",
  "rack_type": "storage | display",

  "assignment_type": "single | role | all",
  "assignment_strategy": "role_round_robin | role_all | role_selected | null",
  "rolling_slot_index": 0,
  "assigned_waiter_id": null,
  "assigned_waiter_name": null,
  "assigned_waiter_email": null,
  "assigned_waiter_role": "pelayan | kasir | finance | backup | supervisor | null",
  "selected_waiter_ids": ["waiter_id_1", "waiter_id_2"],

  "schedule_time": "09:00",
  "time_limit_minutes": 60,
  "schedule_mode": "fixed | shift_relative",
  "shift_offset_minutes": 30,
  "deadline_mode": "fixed | before_shift_end",
  "deadline_before_end_minutes": 60,

  "recurrence_type": "daily | weekly | every_n_days",
  "weekly_day": null,
  "interval_days": null,
  "recurrence_anchor_date": "2026-06-01",

  "rolling_enabled": false,
  "rolling_period": "daily | weekly | monthly",
  "rolling_waiter_ids": [],
  "rolling_anchor_date": "",

  "target_shift_id": null,
  "is_active": true,
  "created_at": 1748784000,
  "last_generated_date": "2026-06-01"
}
```

### 2.7 Recurrence Types Reference

| Type | Logic |
|---|---|
| `daily` | Generate setiap hari |
| `weekly` | Generate kalau ISO weekday === `weekly_day` |
| `every_n_days` | `(targetDate - recurrence_anchor_date).days % interval_days == 0` |

### 2.8 Assignment Type & Strategy

| `assignment_type` | `assignment_strategy` | Behavior |
|---|---|---|
| `single` | — | Static ke 1 waiter (`assigned_waiter_id`) |
| `all` | — | Parallel ke semua active waiters |
| `role` | `role_all` | Parallel ke semua waiter dengan role tsb |
| `role` | `role_selected` | Parallel ke `selected_waiter_ids[]` |
| `role` | `role_round_robin` | **Rolling rack**: 1 task per template per hari, AI balancing pick winner dari `selected_waiter_ids[]` |

---

## 3. Phase 2 — Generator Harian (Cron)

### 3.1 Cron Entry

- **File**: [routes/console.php](file:///S:/wamp64/www/endra/order/routes/console.php)
- **Command**: `waiter:process-tasks` (line 16)
- **Schedule**: line 572
  ```php
  Schedule::command('waiter:process-tasks')
      ->everyFiveMinutes()
      ->withoutOverlapping();
  ```
- **Action chain**:
  1. `generateDueRecurringWaiterTasks()` — generate tasks
  2. `markOverdueWaiterTasks()` — mark + auto-penalty overdue
  3. `FonnteService::notifyTaskOverdue()` — kirim WA notif

### 3.2 Generator Pipeline: `generateRecurringTasksForDate()`

- **File**: [app/Services/FirebaseService.php#L3951](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L3951)
- **Signature**:
  ```php
  private function generateRecurringTasksForDate(
      string $targetDate,
      bool $isCatchUp,
      bool $force = false,
      ?string $templateIdFilter = null
  ): int
  ```

#### Step-by-step Logic

```
1. Load active templates via getRecurringWaiterTaskTemplates()
2. Per template:
   a. Check isTemplateDueForDate() → skip kalau bukan hari ini
   b. resolveTargetWaiters() → list kandidat waiter
   c. Branch general rolling: resolveRollingWaiterIdByCounter()
      → kalau target off-duty, fallback ke peer dengan load terendah
   d. Filter shift target_shift_id (kalau template define)
3. Filter isWorkingDay() (line 4143):
   $targetWaiters = filter (waiter where isWorkingDay = true)
4. Workday fallback (kalau semua libur):
   - assignment_type=single → cari peer same role lowest workload
   - else → reschedule task ke hari berikutnya (max 7 hari)
5. KALAU rack_check + role_round_robin (Rolling Rack):
   a. Filter daily cap (line 4304):
      $cappedWaiters = filter (assigned_today < getRackCheckDailyCap)
   b. Kalau semua hit cap → skip template + log [RACK_DAILY_CAP]
   c. AI Balancing scoring per waiter:
      $score = calculateRackBalancingScore(waiterId, targetDate, todayCount)
   d. Sort descending → pick highest scorer
6. Defensive guards:
   a. Stale deadline check
   b. Re-check isWorkingDay (line 4511) → skip + log [RACK_LIBUR_GUARD]
7. Build identity (line 5630):
   - General: {templateId}::{waiterId}::{scheduledDate}
   - Rolling rack: {templateId}::*::{scheduledDate}  (wildcard)
8. Build node_key SHA256 (line 5638):
   $nodeKey = "waiter_rec_" + substr(sha256(identity), 0, 32)
9. Write payload → /waiter_tasks/{node_key}
```

### 3.3 `resolveTargetWaiters()`

- **File**: [app/Services/FirebaseService.php#L5018](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L5018)
- **Branches**:

| `assignment_type` | Logic |
|---|---|
| `single` | Resolve 1 waiter, verify `is_active=true` |
| `role` | Filter active waiters by `assigned_waiter_role` |
| `role` + rack_check + selected_waiter_ids | **Bypass role filter**: match `selected_waiter_ids[]` langsung dari semua active waiters. Log cross-role warning. |
| `all` | Return semua active waiters |

```php
// Line 5047 - Special case rack_check + selected_ids
if ($taskType === 'rack_check' && count($selectedWaiterIds) > 0) {
    $allActive = $this->getActiveWaiters();
    $selectedWaiterMap = array_fill_keys($selectedWaiterIds, true);
    $resolved = array_values(array_filter($allActive, function ($waiter) use ($selectedWaiterMap) {
        $waiterId = trim((string) ($waiter['id'] ?? ''));
        return $waiterId !== '' && isset($selectedWaiterMap[$waiterId]);
    }));
    // logs role mismatch jika selected waiter beda role dari assigned_role
}
```

### 3.4 AI Balancing: `calculateRackBalancingScore()`

- **File**: [app/Services/FirebaseService.php#L10532](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L10532)
- **Signature**:
  ```php
  private function calculateRackBalancingScore(
      string $waiterId,
      string $targetDate,
      array $todayAssignmentCount
  ): float
  ```

#### Formula 4-Faktor

```
FinalScore = max(0, balanceScore + qualityScore + speedScore + recentScore - todayPenalty)
```

##### Factor 1: Balance (50%)

```
balanceScore = max(0, min(50, (avgWeekly - waiterWeekly) * 12))
```

- `avgWeekly` = rata-rata weekly count seluruh waiter
- `waiterWeekly` = count waiter ini 7 hari terakhir
- **New waiter damping** (Bug Fix #16):
  ```
  if daysSinceCreated < 7:
      dampingFactor = max(0.14, daysSinceCreated / 7)
      balanceScore *= dampingFactor
  ```

##### Factor 2: Quality / Recheck (30%)

```
qualityScore = (avgRecheckPoints / 10) * 30
```

- `avgRecheckPoints` = rata-rata `recheck_points` 7 hari (default 5.0 kalau tidak ada history)

##### Factor 3: Speed (10%)

```
speedScore = max(0, min(10, (480 - avgCompletionMinutes) / 48))
```

- `avgCompletionMinutes` = rata-rata waktu selesai task done (default 300)

##### Factor 4: Recent / Yesterday (10%)

```
recentScore = max(0, min(10, (3 - yesterdayCount) * 5))
```

- Penalize waiter yang kerja banyak kemarin

##### Today Penalty (Override)

```
todayPenalty = todayCount * 35.0
```

Penalty besar per task hari ini supaya rotasi same-day fair.

### 3.5 `getRackBalancingHistoricalData()`

- **File**: [app/Services/FirebaseService.php#L10405](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L10405)
- **Action**:
  1. Fetch `/waiter_tasks` (full)
  2. Aggregate per waiter:
     - `weekly_rack_count` = task count 6 hari terakhir + hari ini
     - `yesterday_count` = exact targetDate - 1
     - `completion_times[]` = duration tasks status=done
     - `recheck_scores[]` = points dari done+approved tasks
  3. **Skip cancelled** (Bug Fix #8) supaya history fair
  4. Cache di `$this->rackBalancingCache`

### 3.6 `isWorkingDay()` Multi-Source

- **File**: [app/Services/FirebaseService.php#L7321](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L7321)
- **Cache key**: `$this->requestCache["isWorkingDay:{waiterId}|{date}"]`

```
1. Short-circuit: kalau attendance_exempt=true → return FALSE (admin/exempt)
2. Source 1 (Retail): RetailScheduleService->isRetailEmployee()
   → buildShiftFromRetailTool() → kalau shift !== LIBUR return TRUE
3. Source 2 (Kasir): KasirScheduleService->isKasirOrBackup()
   → buildShiftFromKasirTool() → kalau shift !== LIBUR return TRUE
4. Source 3 (Fallback): /waiter_schedule_template/{wid}/{day}
   → kalau != 'off' return TRUE
5. Default: FALSE
```

### 3.7 Shift Adapter: `buildShiftFromRetailTool` & `buildShiftFromKasirTool`

- **File**: [app/Services/FirebaseService.php#L7226](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L7226) (retail) + [#L7271](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L7271) (kasir)
- **Output schema**:
  ```php
  [
      'id' => 'retail:FULL' | 'kasir:SHIFT_1',
      'name' => 'Retail Full Shift' | 'Kasir Shift 1',
      'clock_in_time' => '06:30',
      'clock_out_time' => '21:00',
      'late_tolerance_minutes' => 0,  // Strict mode (Bug Fix sebelumnya 15→0)
      'source' => 'retail_tool' | 'kasir_tool',
  ]
  ```

### 3.8 Daily Cap Shift-Aware: `getRackCheckDailyCap()`

```
LIBUR (shift null):  0 task
FULL (>= 12 jam):    2 task
PAGI/SORE/SHIFT_1/SHIFT_2 (< 12 jam): 1 task
Default fallback:    1 task
```

### 3.9 Identity & Node Key

#### `buildWaiterRecurringInstanceIdentity()` (line 5630)

| Tipe | Format Key |
|---|---|
| General task per-waiter | `{templateId}::{waiterId}::{scheduledDate}` |
| Rolling rack (wildcard) | `{templateId}::*::{scheduledDate}` |

Wildcard memastikan re-run tidak duplicate meski AI swap waiter.

#### `buildWaiterRecurringTaskNodeKey()` (line 5638)

```
nodeKey = "waiter_rec_" + substr(sha256(identityKey), 0, 32)
```

Output: deterministic key untuk `/waiter_tasks/{nodeKey}`.

### 3.10 Defensive Guards

#### Guard 1: Filter isWorkingDay (line 4143)

```php
$targetWaiters = array_values(array_filter($targetWaiters, function ($waiter) use ($effectiveTargetDate) {
    return $this->isWorkingDay($waiter['id'], $effectiveTargetDate);
}));
```

#### Guard 2: Daily Cap (line 4304)

```php
$cappedWaiters = array_values(array_filter($targetWaiters, function ($waiter) use ($rackCheckAssignmentCount, $effectiveTargetDate) {
    $assigned = (int) ($rackCheckAssignmentCount[$waiter['id']] ?? 0);
    $cap = $this->getRackCheckDailyCap($waiter['id'], $effectiveTargetDate);
    return $assigned < $cap;
}));
```

#### Guard 3: Persist-time LIBUR (line 4511)

```php
if ($taskTypeForGuard === 'rack_check'
    && $waiterIdForGuard !== ''
    && ! $this->isWorkingDay($waiterIdForGuard, $effectiveTargetDate)) {
    \Log::warning('[RACK_LIBUR_GUARD] Skip persist rack_check ke waiter LIBUR');
    $existingRecurringMap[$mapKey] = true;
    continue;
}
```

---

## 4. Phase 3 — Waiter Execution

### 4.1 Schema `/waiter_tasks/{node_key}`

```json
{
  "id": "waiter_rec_<32hex>",
  "template_id": "-Oi...",
  "title": "RAK SENAR",
  "description": "",
  "priority": "normal",

  "task_type": "rack_check",
  "category_id": null,
  "category_name": null,

  "rack_id": "-Oi...",
  "rack_name": "RAK SENAR",
  "rack_barcode_value": "RAK-XXXX",
  "rack_target_scope": "single",

  "requires_barcode_scan": true,
  "requires_photo_proof": true,
  "requires_photo_before": false,

  "assigned_waiter_id": "-OqrpfOF7hpqDo16eGDb",
  "assigned_waiter_name": "Bagas",
  "assigned_waiter_email": "bagas@example.com",
  "assigned_waiter_role": "pelayan",
  "assigned_by": "Supervisor",
  "assigned_at": 1748784000,

  "scheduled_for_date": "2026-06-01",
  "deadline_at": 1748800800,
  "target_shift_id": null,

  "status": "pending | in_progress | done | overdue | cancelled",
  "started_at": null,
  "completed_at": null,
  "completion_note": null,

  "scanned_barcode": null,
  "scanned_at": null,
  "scan_match": null,

  "photo_proof_url": null,
  "photo_before_url": null,

  "stock_report_items": null,
  "no_out_of_stock": null,
  "product_checklist": null,

  "recheck_pending": null,
  "recheck_points": null,
  "recheck_notes": null,
  "recheck_by": null,
  "recheck_by_name": null,
  "recheck_at": null,

  "is_off_day_assignment": false,
  "is_role_mismatch": false,
  "is_rescheduled": false,
  "is_emergency_assignment": false,
  "cancel_reason": null,
  "cancelled_at": null,

  "created_at": 1748784000,
  "updated_at": 1748784000
}
```

### 4.2 Status Lifecycle

```
                ┌────────┐
                │pending │  (created via generator)
                └───┬────┘
                    │ start (klik "Mulai")
                    ▼
                ┌────────────┐
                │in_progress │
                └─────┬──────┘
                      │ submit (foto + barcode)
                      ▼
                ┌────────┐
                │  done  │  (rack_check: + recheck_pending=true)
                └───┬────┘
                    │ Finance review
                    ├──────────────────┐
                    ▼                  ▼
              ┌──────────┐       ┌──────────┐
              │recheck_  │       │recheck_  │
              │points=N  │       │points=0  │
              │(approved)│       │(rejected)│
              └──────────┘       └──────────┘

Side states:
  pending → overdue (auto via cron kalau deadline lewat)
  pending/in_progress → cancelled (manual via admin)
```

### 4.3 Method: `WaiterController::completeTask()`

- **File**: [app/Http/Controllers/WaiterController.php#L344](file:///S:/wamp64/www/endra/order/app/Http/Controllers/WaiterController.php#L344)

```php
public function completeTask($id, Request $request)
{
    $request->validate([
        'note' => 'nullable|string|max:500',
        'scanned_barcode' => 'nullable|string|max:120',
        'stock_report_items' => 'nullable|string|max:2000',
        'no_out_of_stock' => 'nullable|boolean',
        'photo_proof_data_url' => 'nullable|string|max:5000000',
        'photo_before_data_url' => 'nullable|string|max:5000000',
        'product_checklist' => 'nullable|string|max:50000',
        'idempotency_key' => 'nullable|string|max:120',
    ]);

    // ... session lookup
    
    $result = $this->firebase->updateWaiterTaskStatus(
        $id, 'done', $waiterId, $waiterName, $waiterEmail,
        $request->input('note'),
        $request->input('scanned_barcode'),
        // ... etc
    );
    
    // Auto-score daily points via BonusService
    $bonusService->autoScoreDailyPoints($waiterId, $today, $attendance, $todayTasks, $reports);
}
```

### 4.4 Auto-Score Daily Points (Tanpa Recheck)

Setelah `completeTask`, BonusService auto-score categories:
- `discipline` — kehadiran tepat waktu
- `operational` — kebersihan, rapi
- `attitude` — sikap kerja
- `rack_recheck` — **belum diisi**, masih null. Diisi saat Finance review.

---

## 5. Phase 4 — Finance Review

### 5.1 Method: `WaiterController::submitRackCheckReview()`

- **File**: [app/Http/Controllers/WaiterController.php#L575](file:///S:/wamp64/www/endra/order/app/Http/Controllers/WaiterController.php#L575)
- **Route**: `POST /waiter/tasks/{id}/recheck` (name: `task.recheck`)
- **Permission**: role `finance` atau `supervisor`

### 5.2 Service: `FirebaseService::submitRackCheckReview()`

- **File**: [app/Services/FirebaseService.php#L2677](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L2677)

```php
public function submitRackCheckReview(
    string $taskId,
    string $financeId,
    string $financeName,
    int $points,             // 0 - maxPoints (default 10)
    string $notes,
    int $maxPoints = 10
): array
```

#### Validation

```
1. Task exists
2. task_type === 'rack_check'
3. status === 'done'
4. recheck_pending === true (belum direview)
5. points clamp: max(0, min(maxPoints, points))
```

#### Update Fields

```php
$updates = [
    'recheck_pending' => false,
    'recheck_points' => $points,
    'recheck_notes' => trim($notes),
    'recheck_by' => $financeId,
    'recheck_by_name' => $financeName,
    'recheck_at' => time(),
];
```

### 5.3 Query Pending: `getRackCheckPendingReview()`

- **File**: [app/Services/FirebaseService.php#L2733](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L2733)

```php
public function getRackCheckPendingReview(?string $date = null, int $lookbackDays = 0): array
```

- Filter: `task_type=rack_check AND status=done AND recheck_pending=true`
- Sort: `completed_at DESC`
- `lookbackDays`: 0 = hari ini saja, 7 = mundur 7 hari

### 5.4 View: Tab Verifikasi di Portal Waiter

- **File**: [resources/views/waiter/tasks.blade.php](file:///S:/wamp64/www/endra/order/resources/views/waiter/tasks.blade.php)
- **Section**: `panel-recheck` sekitar line 1692
- **Visibility**: tab muncul kalau `session('waiter_role') in ['finance', 'supervisor']`
- **Card per task**: foto bukti, info waiter, slider 0-10 poin, textarea notes, tombol Submit

### 5.5 Migration Helper: `markRackCheckPendingReview()`

- **File**: [app/Services/FirebaseService.php#L2617](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php#L2617)
- **Usage**: command `BonusRackRecheckMarkLegacy` untuk migrasi task lama yang `done` tapi belum punya `recheck_pending` field
- **Idempotent**: skip kalau task sudah punya field

---

## 6. Bonus Integration

### 6.1 Categories Config

- **File**: [app/Services/BonusService.php#L66-L78](file:///S:/wamp64/www/endra/order/app/Services/BonusService.php#L66-L78)

```php
'point_categories' => [
    'discipline'   => ['name' => 'Disiplin',     'max_daily_points' => 5],
    'operational'  => ['name' => 'Operasional',  'max_daily_points' => 5],
    'service'      => ['name' => 'Pelayanan',    'max_daily_points' => 5],
    'sales'        => ['name' => 'Penjualan',    'max_daily_points' => 5],
    'attitude'     => ['name' => 'Sikap',        'max_daily_points' => 5],
    'rack_recheck' => ['name' => 'Recheck Rak',  'max_daily_points' => 10],
],
```

### 6.2 Method: `saveAutoDailyScoreRackOnly()`

- **File**: [app/Services/BonusService.php#L358](file:///S:/wamp64/www/endra/order/app/Services/BonusService.php#L358)
- **Purpose**: Targeted merge `rack_recheck` category ke daily record tanpa overwrite kategori lain
- **Yang di-overwrite**:
  - `categories[rack_recheck]`
  - `raw_total`
  - `perfect_day_bonus`
  - `daily_total`
  - `auto_details[rack_recheck_*]`
  - `updated_at`, `scored_at`, `notes`

### 6.3 Storage: `/bonus_daily_points/{waiterId}/{date}`

```json
{
  "categories": {
    "discipline": 5,
    "operational": 5,
    "attitude": 5,
    "rack_recheck": 8
  },
  "raw_total": 23,
  "perfect_day_bonus": 5,
  "daily_total": 28,
  "auto_details": { ... },
  "scored_at": 1748800000,
  "notes": "Auto-scored on task completion"
}
```

### 6.4 Monthly Aggregation

`BonusService::calculateMonthlyBonus()` accumulates daily totals selama 1 bulan + service/sales points + manual_bonus + campaign_points → final bonus payout.

---

## 7. Penalty Engine

### 7.1 Penalty Types

- **File**: [app/Services/BonusService.php#L75-L80](file:///S:/wamp64/www/endra/order/app/Services/BonusService.php#L75-L80)

| Type | Points | Trigger |
|---|---|---|
| `late_arrival` | -5 | Clock-in lewat tolerance |
| `absent` | -15 | Tidak clock-in di working day |
| `mandatory_task_missed` | -10 | Task overdue auto |
| `careless_work` | -10 | Manual via admin |

### 7.2 Auto-Apply: `markOverdueWaiterTasks()` + `autoApplyPenaltiesForMissed()`

```
markOverdueWaiterTasks() (cron 5 min):
  1. Loop /waiter_tasks status=pending
  2. Kalau deadline_at < now() → status=overdue
  3. Push ke list overdue_tasks

autoApplyPenaltiesForMissed():
  1. Loop overdue_tasks
  2. Skip kalau:
     - cancelled, sick, day_off
     - attendance_exempt waiter (line 4723)
     - dedup by taskId (Bug Fix #1)
  3. Apply mandatory_task_missed -10 ke /penalties/{wid}/{date}/{push_id}
```

### 7.3 Skip Conditions (line 4720)

```php
$attendance = $attendanceLookup[$taskDate.'::'.$waiterId] ?? null;
$attendanceStatus = strtolower((string) ($attendance['status'] ?? ''));
$attendanceExempt = ! empty($attendance['attendance_exempt']);

if (in_array($attendanceStatus, ['absent', 'sick', 'day_off'], true) || $attendanceExempt) {
    continue; // skip penalty
}
```

---

## 8. Bug Fixes Riwayat

### 8.1 Bug Fix Log (Phase 1-4 dari audit fairness)

| Fix # | Issue | Solution |
|---|---|---|
| #1 | mandatory_task_missed double penalty | Dedup by taskId |
| #2 | deletePenalty orphan-kan index | Multi-path update hapus index |
| #3 | applyPenalty CAS race | Pre-generate push key + atomic CAS |
| #5 | Campaign quota race | Atomic transaction + refund saat reject |
| #6 | Peer fallback alphabetical bias | Sort by load |
| #7 | Reschedule fail silent | Supervisor emergency fallback |
| #8 | Cancelled count di balancing | Skip cancelled |
| #9 | late+absent double | Skip late kalau absent |
| #10 | mandatory ignore sick/day_off | Early return |
| #11 | Server time false late | Log clock_skew |
| #12 | Backup coverage cascade | Validate isWorkingDay backup |
| #13 | Manual penalty backdate | Date max 60 hari + cap 5/hari |
| #14 | Calendar offset | Persisted counter `/rotation_counters/{templateId}` |
| #15 | Rack_check role mismatch | Soft warning `is_role_mismatch=true` + log |
| #16 | New waiter overload | Damping factor `daysSinceCreated/7` |

### 8.2 Recent Patches (Sesi Sekarang)

| Date | Commit | Change |
|---|---|---|
| 2026-06-01 | `a14ab99` | Toleransi telat 15→0 menit (`buildShiftFromRetail/Kasir`) |
| 2026-06-01 | `b18b65b` | Daily cap shift-aware (FULL=2, partial=1, LIBUR=0) + libur skip |
| 2026-06-01 | `41960c0` | Defensive guard re-check isWorkingDay sebelum persist |
| 2026-06-01 | `ac9c20c` | PHP `memory_limit=512M` di public/index.php |

---

## 9. Pain Points Sistem Sekarang

### 9.1 Generator Bias

Meski sudah ada 3 layer filter (line 4143, 4304, 4511), kasus nyata 2026-06-01:
- Bagas LIBUR mendapat 7 task → harus manual cancel + re-run
- Cron jalan 5 menit → kalau code fix belum loaded, regenerasi terjadi
- AI Balancing 4-faktor scoring complex tapi tetap bisa salah

### 9.2 Cron Race Condition

- Cron `every 5 min` + `withoutOverlapping()` tetap bisa generate sebelum manual fix di-deploy
- Cancel manual + cron re-generate dalam 5 menit → loop

### 9.3 UI Kompleks untuk Supervisor

- 23 template aktif dengan banyak field (rolling, recurrence, schedule_mode, dll)
- Hard untuk supervisor pahami "kenapa Bagas dapat ini"
- Tidak ada visualisasi mingguan (cuma per-template)

### 9.4 Inkonsistensi Page

- `/admin/tasks/rack-check` (PHP server-side, filter `tracking_date` dengan fallback ke `created_at`)
- `/admin/tasks/live` (JS Firebase listener, filter `scheduled_for_date` saja)
- Beda data kalau task lama belum punya `scheduled_for_date`

---

## 10. File Reference Index

### Controllers
- [Admin/TaskController.php](file:///S:/wamp64/www/endra/order/app/Http/Controllers/Admin/TaskController.php) — template CRUD
- [WaiterController.php](file:///S:/wamp64/www/endra/order/app/Http/Controllers/WaiterController.php) — task execution + Finance review

### Services
- [FirebaseService.php](file:///S:/wamp64/www/endra/order/app/Services/FirebaseService.php) — semua RTDB operations
- [BonusService.php](file:///S:/wamp64/www/endra/order/app/Services/BonusService.php) — points + penalty engine
- [RetailScheduleService.php](file:///S:/wamp64/www/endra/order/app/Services/RetailScheduleService.php) — jadwal retail
- [KasirScheduleService.php](file:///S:/wamp64/www/endra/order/app/Services/KasirScheduleService.php) — jadwal kasir
- [ScheduleGeneratorService.php](file:///S:/wamp64/www/endra/order/app/Services/ScheduleGeneratorService.php) — schedule pure-PHP generator
- [FonnteService.php](file:///S:/wamp64/www/endra/order/app/Services/FonnteService.php) — WA notif

### Views
- [admin/tasks/studio.blade.php](file:///S:/wamp64/www/endra/order/resources/views/admin/tasks/studio.blade.php) — template creator
- [admin/tasks/create.blade.php](file:///S:/wamp64/www/endra/order/resources/views/admin/tasks/create.blade.php) — legacy creator
- [admin/tasks/live.blade.php](file:///S:/wamp64/www/endra/order/resources/views/admin/tasks/live.blade.php) — real-time monitor
- [waiter/tasks.blade.php](file:///S:/wamp64/www/endra/order/resources/views/waiter/tasks.blade.php) — portal waiter (line 1692 panel-recheck)

### Routes
- [routes/web.php](file:///S:/wamp64/www/endra/order/routes/web.php) — HTTP routes
- [routes/console.php](file:///S:/wamp64/www/endra/order/routes/console.php) — cron + commands

### Firebase RTDB Paths
- `/waiter_task_templates/{id}` — template definitions
- `/waiter_tasks/{node_key}` — generated task instances
- `/bonus_daily_points/{waiterId}/{date}` — daily score
- `/penalties/{waiterId}/{date}/{push_id}` — penalty records
- `/rotation_counters/{templateId}` — persisted rotation counter (Bug Fix #14)
- `/waiter_attendance/{waiterId}/{date}` — clock-in/out log
- `/work_shifts/{shift_id}` — shift definitions (legacy + retail/kasir tool)
- `/waiter_schedule_template/{waiterId}/{day}` — fallback schedule

---

## 11. Glossary

| Term | Meaning |
|---|---|
| **Rolling rack** | rack_check + role_round_robin: 1 task per template per hari, rotate winner |
| **Wildcard key** | `{templateId}::*::{date}` — node identity untuk rolling rack supaya re-run tidak duplicate |
| **AI Balancing** | Scoring 4-faktor (50% balance, 30% quality, 10% speed, 10% recent) untuk pick winner rolling rack |
| **Daily cap** | Max task per waiter per hari: FULL=2, partial=1, LIBUR=0 |
| **Damping factor** | Multiplier untuk new waiter (≤7 hari) supaya tidak overload (Bug Fix #16) |
| **Recheck pending** | rack_check task done yang belum direview Finance |
| **Persistent rotation counter** | `/rotation_counters/{templateId}` dengan `last_assigned + period_assignees` (Bug Fix #14) |
| **attendance_exempt** | Flag waiter (admin/supervisor) yang skip semua attendance/penalty/task |
| **isWorkingDay multi-source** | Cek 3 sumber: retail tool → kasir tool → /waiter_schedule_template/ fallback |
| **Defensive guard** | Re-check isWorkingDay tepat sebelum persist (line 4511) |
