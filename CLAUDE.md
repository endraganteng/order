# CLAUDE.md — Project Rules for AI Coding

## Project Overview

Laravel app for Mataram Petshop — manages tasks, attendance, rack stock, bonuses, shifts, and orders.
Backend: PHP/Laravel + Firebase Realtime Database (RTDB) as data layer.
Frontend: Blade templates. Docker-based deployment (order-app, order-nginx, order-scheduler).

## Architecture: Domain Services

FirebaseService (13K lines) telah dipecah menjadi 9 domain services.
**Sebelum edit, identifikasi domain yang tepat — jangan edit service yang salah.**

### Service Map

| Service | Domain | Lines | Key Methods |
|---------|--------|-------|-------------|
| `FirebaseService.php` | Core/shared (auth, settings, orders) | 842 | `getWaiterById`, `getActiveWaiters`, `getSettings`, `logAuditAction` |
| `WaiterTaskFirebaseService.php` | Task assignment, recurring, completion | 4,387 | `updateWaiterTaskStatus`, `createRecurringWaiterTaskTemplate`, `generateDueRecurringWaiterTasks` |
| `RackStockFirebaseService.php` | Rack, stock, barcode, stock-take | 1,973 | `getRacks`, `submitStandaloneStockTake`, `getRackCheckDailyCap` |
| `ProductFirebaseService.php` | Products, categories, rack-products | 1,210 | `getActiveProducts`, `assignProductsToRack`, `getProductStockSummary` |
| `PurchaseOrderFirebaseService.php` | PO, restock requests | 1,083 | `getPurchaseOrders`, `receivePoItem`, `createOrUpdateRestockRequest` |
| `ShiftScheduleFirebaseService.php` | Shifts, schedules, rotation | 877 | `getWaiterShiftForDate`, `isWorkingDay`, `getScheduleTemplate` |
| `BonusFirebaseService.php` | Bonus config, points, penalties | 361 | `getBonusConfig`, `getDailyPoints`, `flagTaskBonusPending` |
| `AttendanceFirebaseService.php` | Attendance, QR scan | ~786 | `processAttendanceQrScan`, `getAttendanceByDate`, `getAttendanceSummary` |
| `PlanningFirebaseService.php` | Rack check planning | 315 | Planning-specific methods |
| `CashierFirebaseService.php` | Cashier workers | 182 | `getCashierWorkers`, `addCashierWorker` |

### Dependency Graph

```
FirebaseService (core — injected into most domain services)
├── WaiterTaskFirebaseService (depends on: Firebase, RackStock, ShiftSchedule)
├── RackStockFirebaseService (depends on: Firebase, Product, ShiftSchedule)
├── ProductFirebaseService (depends on: Firebase)
├── ShiftScheduleFirebaseService (depends on: Firebase)
├── AttendanceFirebaseService (depends on: Firebase)
├── PurchaseOrderFirebaseService (standalone)
├── PlanningFirebaseService (standalone)
├── BonusFirebaseService (standalone)
└── CashierFirebaseService (standalone)
```

### Decision Guide: Where to Edit

| Task | Target Service |
|------|---------------|
| Fix task assignment/completion | `WaiterTaskFirebaseService` |
| Fix rack check, stock take, barcode | `RackStockFirebaseService` |
| Fix product CRUD, categories, rack-product mapping | `ProductFirebaseService` |
| Fix PO, restock, receiving | `PurchaseOrderFirebaseService` |
| Fix shift, schedule, working day | `ShiftScheduleFirebaseService` |
| Fix bonus, points, penalties, leaderboard | `BonusFirebaseService` (RTDB) or `BonusService` (MySQL) |
| Fix attendance, QR, clock in/out | `AttendanceFirebaseService` |
| Fix rack check planning/overflow | `PlanningFirebaseService` |
| Fix cashier workers | `CashierFirebaseService` |
| Fix waiter auth, settings, orders, audit log | `FirebaseService` (core) |

### MySQL Services (already migrated)

| Service | Domain |
|---------|--------|
| `BonusService.php` | Bonus calculation, payroll integration (MySQL source of truth) |
| `PayrollService.php` | Payroll, kasbon, salary |
| `OrderService.php` | Orders (MySQL) |
| `RestockService.php` | Restock workflows (MySQL) |
| `WaiterTaskService.php` | Task queries (MySQL) |

## Controller → Service Mapping

Controllers inject multiple domain services. Pattern:
```php
public function __construct(
    protected FirebaseService $firebase,        // core
    private WaiterTaskFirebaseService $waiterTask,  // tasks
    private ShiftScheduleFirebaseService $shift,    // shifts
    private RackStockFirebaseService $rack,          // racks
    // etc.
)
```

Key controllers:
- `TaskController` → waiterTask, shift, rack, cashierFb
- `WaiterController` → waiterTask, attendance, shift, product, rack, po, bonus
- `CashierController` → attendance, shift, cashierFb
- `RackCheckPlanningController` → rack, shift, planning
- `BonusController` → bonus (BonusService), attendance, waiterTask

## Infrastructure

- **Server**: GCP VM, Docker Compose
- **Containers**: order-app, order-scheduler, order-nginx, order-phpmyadmin
- **Deploy**: Files live on host at `~/projects/order`, sync to container via `docker cp`
- **PHP lint**: `sudo docker exec order-app php -l <path>`
- **Route test**: `curl -s -o /dev/null -w "%{http_code}" https://order.imoweb.dev/<route>`
- **Logs**: `sudo docker exec order-app tail storage/logs/laravel.log`
- **MySQL**: `sudo docker exec mysql mysql -uorder -p'[see .env]' order`
- **No bash in container**: Use `sh` not `bash` for exec commands

## Workflow Rules

- Work in small visible steps
- Before editing, identify controller → service → method chain
- After editing, `docker cp` to container then verify with `php -l` and route test
- Never modify `.env`, `vendor`, `node_modules`, logs, cache, or build output
- For Firebase/RTDB logic, avoid changing data structure unless explicitly requested
- For finance/bonus/shift logic, preserve existing business rules unless asked
- When "lanjut", continue from the previous checkpoint
- When "status", summarize current progress and next step
- Prefer small safe fixes over large refactors
- Always verify after changes: `php -l` → route test → check error log

## Naming Conventions

- Firebase services: `{Domain}FirebaseService.php` (still uses RTDB)
- MySQL services: `{Domain}Service.php` (migrated to MySQL)
- Repositories: `app/Repositories/` with interface contracts
- Controllers: `app/Http/Controllers/Admin/` (admin) or `app/Http/Controllers/` (waiter/cashier)

## Data Layer Strategy

- **Dual-write active**: Some operations write to both MySQL and Firebase RTDB
- **MySQL = source of truth** for: tasks, attendance, penalties, bonuses, orders, rack_products
- **Firebase RTDB = source of truth** for: realtime features (QR, live monitor), waiter auth, settings
- **Migration direction**: Firebase → MySQL (ongoing)
