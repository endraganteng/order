# Wire Controllers ke Repository/Service Layer

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Menghapus dependency langsung controller → FirebaseService untuk fitur yang sudah punya Repository/Service layer + feature flag MySQL.

**Architecture:** Setiap controller yang masih panggil `$this->firebase->xxx()` diganti dengan inject Repository/Service yang sudah ada. Repository sudah handle feature flag (baca MySQL atau RTDB). Ini menghilangkan 200+ direct calls ke FirebaseService tanpa mengubah behavior.

**Prinsip:**
- Tidak mengubah business logic
- Repository/Service sudah handle dual-path (feature flag)
- Controller hanya perlu ganti injection + method call
- Test existing tetap pass

---

## Phase 1: Controller dengan Repository SIAP (low-risk, direct swap)

### Task 1.1: ShiftController → WorkShift Model

**Objective:** Ganti 11x `firebase->` calls dengan Eloquent WorkShift model.

**Files:**
- Modify: `app/Http/Controllers/Admin/ShiftController.php`

**Mapping:**
| FirebaseService method | Replacement |
|---|---|
| `getActiveShifts()` | `WorkShift::where('is_active', true)->get()` |
| `getShiftById($id)` | `WorkShift::where('firebase_legacy_key', $id)->first()` |
| `createShift($data)` | `WorkShift::create($data)` |
| `updateShift($id, $data)` | `WorkShift::where('firebase_legacy_key', $id)->update($data)` |
| `deleteShift($id)` | `WorkShift::where('firebase_legacy_key', $id)->delete()` |

**Guard:** Wrap with `if (config('features.mysql_work_shifts'))` fallback ke firebase.

---

### Task 1.2: SupplierController → SupplierRepository

**Objective:** Ganti 12x `firebase->` calls dengan SupplierRepository.

**Files:**
- Modify: `app/Http/Controllers/Admin/SupplierController.php`

**Mapping:**
| FirebaseService method | Repository method |
|---|---|
| `getSuppliers()` | `$this->suppliers->all()` |
| `getSupplierById($id)` | `$this->suppliers->find($id)` |
| `createSupplier($data)` | `$this->suppliers->create($data)` |
| `updateSupplier($id, $data)` | `$this->suppliers->update($id, $data)` |
| `deleteSupplier($id)` | `$this->suppliers->delete($id)` |

**Inject:** `SupplierRepository $suppliers` di constructor.

---

### Task 1.3: AttendanceController → AttendanceRepository

**Objective:** Ganti 13x `firebase->` calls dengan AttendanceRepository.

**Files:**
- Modify: `app/Http/Controllers/Admin/AttendanceController.php`

**Mapping:**
| FirebaseService method | Repository method |
|---|---|
| `getAttendanceByDate($date)` | `$this->attendance->allOnDate($date)` |
| `getAttendanceByWaiterAndDate($wId, $date)` | `$this->attendance->forWaiterOnDate($wId, $date)` |
| `getAttendanceByWaiterAndMonth($wId, $month)` | `$this->attendance->forWaiterInMonth($wId, $month)` |

**Inject:** `AttendanceRepository $attendance` di constructor.

---

### Task 1.4: ProductCategoryController → ProductCategory Model

**Objective:** Ganti 6x `firebase->` calls dengan Eloquent ProductCategory model.

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductCategoryController.php`

**Mapping:**
| FirebaseService method | Replacement |
|---|---|
| `getProductCategories()` | `ProductCategory::orderBy('sort_order')->get()` |
| `getProductCategoryById($id)` | `ProductCategory::where('firebase_legacy_key', $id)->first()` |
| `createProductCategory($data)` | `ProductCategory::create($data)` |
| `updateProductCategory($id, $data)` | `ProductCategory::where('firebase_legacy_key', $id)->update($data)` |
| `deleteProductCategory($id)` | `ProductCategory::where('firebase_legacy_key', $id)->delete()` |

**Guard:** `if (config('features.mysql_product_categories'))`

---

### Task 1.5: RackProductController → RackProductRepository

**Objective:** Ganti 26x `firebase->` calls dengan RackProductRepository.

**Files:**
- Modify: `app/Http/Controllers/Admin/RackProductController.php`

**Mapping:**
| FirebaseService method | Repository method |
|---|---|
| `getActiveProducts()` | `$this->rackProducts->allActive()` |
| `getProductById($id)` | `$this->rackProducts->find($id)` |
| `createProduct($data)` | `$this->rackProducts->create($data)` |
| `updateProduct($id, $data)` | `$this->rackProducts->update($id, $data)` |
| `deleteProduct($id)` | `$this->rackProducts->delete($id)` |

**Inject:** `RackProductRepository $rackProducts` di constructor.

---

### Task 1.6: RackController → RackRepository

**Objective:** Ganti 19x `firebase->` calls dengan RackRepository.

**Files:**
- Modify: `app/Http/Controllers/Admin/RackController.php`

**Mapping:**
| FirebaseService method | Repository method |
|---|---|
| `getRacks()` | `$this->racks->all()` |
| `getActiveRacks()` | `$this->racks->allActive()` |
| `getRackById($id)` | `$this->racks->find($id)` |
| `createRack($data)` | `$this->racks->create($data)` |
| `updateRack($id, $data)` | `$this->racks->update($id, $data)` |
| `deleteRack($id)` | `$this->racks->delete($id)` |

**Inject:** `RackRepository $racks` di constructor.

---

### Task 1.7: CashierController → CashierTaskRepository

**Objective:** Ganti 18x `firebase->` calls dengan CashierTaskRepository.

**Files:**
- Modify: `app/Http/Controllers/CashierController.php`

**Mapping:**
| FirebaseService method | Repository method |
|---|---|
| `getCashierTasks()` | `$this->cashierTasks->all()` |
| `getCashierActiveTasks()` | `$this->cashierTasks->allActive()` |
| `createCashierTask($data)` | `$this->cashierTasks->create($data)` |
| `updateCashierTaskStatus($id, $status)` | `$this->cashierTasks->updateStatus($id, $status)` |
| `deleteCashierTask($id)` | `$this->cashierTasks->delete($id)` |

**Inject:** `CashierTaskRepository $cashierTasks` di constructor.

---

## Phase 2: TaskController (terbesar, 78 calls)

### Task 2.1: Audit semua firebase-> methods di TaskController

**Objective:** List semua unique firebase methods yang dipanggil, map ke WaiterTaskService/Repository.

**Action:** Read full TaskController, extract unique `firebase->xxx()` calls, classify:
- A) Sudah ada di WaiterTaskRepository → direct swap
- B) Sudah ada di WaiterTaskService → direct swap
- C) Belum ada equivalent → perlu tambah method di service/repo

### Task 2.2: Wire WaiterTaskService + WaiterTaskRepository ke TaskController

**Objective:** Inject services, replace category A+B calls.

### Task 2.3: Tambah missing methods di WaiterTaskService

**Objective:** Methods category C yang belum ada — extract dari FirebaseService ke WaiterTaskService.

---

## Phase 3: Complex Controllers (butuh service baru)

### Task 3.1: RackCheckPlanningController (54 calls)

**Status:** Belum ada dedicated service.
**Action:** Buat `RackCheckPlanningService` yang wrap logic dari FirebaseService.
**Scope:** Extract ~15 method terkait rack check planning.

### Task 3.2: RestockController (33 calls)

**Status:** Belum ada dedicated service.
**Action:** Buat `RestockService` yang wrap restock/PO logic.
**Scope:** Extract ~10 method terkait purchase orders & restock.

### Task 3.3: RackCheckTemplateController (25 calls)

**Status:** Belum ada dedicated service.
**Action:** Bisa digabung dengan `RackCheckPlanningService` atau buat terpisah.

---

## Phase 4: Remaining Controllers (ringan)

### Task 4.1: AuditLogController (1 call)
- Ganti ke `AuditLog::query()` langsung.

### Task 4.2: DashboardController (5 calls)
- Mix data dari berbagai repo — wire satu-satu.

### Task 4.3: OrderController (6 calls)
- Belum ada `mysql_orders` flag. Skip until Phase 2 migration.

### Task 4.4: ReconciliationController (3 calls)
- Niche, low priority.

---

## Priority & Estimation

| Phase | Tasks | firebase-> removed | Effort | Risk |
|---|---|---|---|---|
| Phase 1 | 7 tasks | ~105 calls | 2-3 jam | Low (direct swap) |
| Phase 2 | 3 tasks | ~78 calls | 2-3 jam | Medium (perlu audit) |
| Phase 3 | 3 tasks | ~112 calls | 4-6 jam | Medium (new services) |
| Phase 4 | 4 tasks | ~15 calls | 1 jam | Low |

**Total:** ~310 firebase-> calls removed, 10-13 jam kerja.

**Recommended order:** Phase 1 → Phase 2 → Phase 4 → Phase 3

Phase 1 paling aman karena hanya swap ke repo yang sudah tested. Phase 3 paling berat karena butuh extract logic dari FirebaseService ke service baru.

---

## Verification

Setelah setiap task:
1. `php artisan test` — semua test pass
2. Spot-check halaman terkait di browser
3. Pastikan data tampil dari MySQL (cek query log jika perlu)
4. Commit: `refactor: wire {Controller} to {Repository/Service}`
