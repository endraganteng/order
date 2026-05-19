<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\BonusController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\RackController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\RackProductController;
use App\Http\Controllers\Admin\RestockController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\WaiterController as AdminWaiterController;
use App\Http\Controllers\Admin\WaiterPerformanceController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\WaiterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Public payroll approval (token-based, no login). Used in WA magic-link.
Route::get('payroll/proses/{txId}/{token}', [\App\Http\Controllers\PublicPayrollApprovalController::class, 'review'])
    ->where('token', '[a-f0-9]{16,128}')
    ->name('public.payroll.review');
Route::post('payroll/proses/{txId}/{token}/approve', [\App\Http\Controllers\PublicPayrollApprovalController::class, 'approve'])
    ->where('token', '[a-f0-9]{16,128}')
    ->name('public.payroll.approve');
Route::post('payroll/proses/{txId}/{token}/reject', [\App\Http\Controllers\PublicPayrollApprovalController::class, 'reject'])
    ->where('token', '[a-f0-9]{16,128}')
    ->name('public.payroll.reject');

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.post');
    Route::post('login/google', [AuthController::class, 'loginWithGoogle'])
        ->middleware('throttle:20,1')
        ->name('login.google');

    Route::middleware('admin.auth')->group(function () {
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Waiters management
        Route::get('waiters', [AdminWaiterController::class, 'index'])->name('waiters.index');
        Route::get('waiters/create', [AdminWaiterController::class, 'create'])->name('waiters.create');
        Route::post('waiters', [AdminWaiterController::class, 'store'])->name('waiters.store');
        Route::get('waiters/{id}/edit', [AdminWaiterController::class, 'edit'])->name('waiters.edit');
        Route::put('waiters/{id}', [AdminWaiterController::class, 'update'])->name('waiters.update');
        Route::delete('waiters/{id}', [AdminWaiterController::class, 'destroy'])->name('waiters.destroy');

        // Rack management
        Route::get('racks', [RackController::class, 'index'])->name('racks.index');
        Route::get('racks/create', [RackController::class, 'create'])->name('racks.create');
        Route::post('racks', [RackController::class, 'store'])->name('racks.store');
        Route::post('racks/bulk-update-type', [RackController::class, 'bulkUpdateType'])->name('racks.bulk_update_type');
        Route::get('racks/print-labels', [RackController::class, 'printLabels'])->name('racks.print_labels');
        Route::get('racks/export-barcodes', [RackController::class, 'exportBarcodes'])->name('racks.export_barcodes');
        Route::get('racks/{id}/edit', [RackController::class, 'edit'])->name('racks.edit');
        Route::put('racks/{id}', [RackController::class, 'update'])->name('racks.update');
        Route::post('racks/{id}/regenerate-barcode', [RackController::class, 'regenerateBarcode'])->name('racks.regenerate_barcode');
        Route::delete('racks/{id}', [RackController::class, 'destroy'])->name('racks.destroy');
        Route::get('racks/{id}/history', [RackController::class, 'history'])->name('racks.history');
        Route::post('racks/ajax-store', [RackController::class, 'storeAjax'])->name('racks.ajax_store');

        // Product categories
        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product_categories.index');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product_categories.store');
        Route::put('product-categories/{id}', [ProductCategoryController::class, 'update'])->name('product_categories.update');
        Route::delete('product-categories/{id}', [ProductCategoryController::class, 'destroy'])->name('product_categories.destroy');

        // Product management
        Route::get('products', [RackProductController::class, 'index'])->name('products.index');
        Route::get('products/search', [RackProductController::class, 'searchJson'])->name('products.search');
        Route::post('products', [RackProductController::class, 'store'])->name('products.store');
        Route::get('products/bulk-assign', [RackProductController::class, 'bulkAssign'])->name('products.bulk_assign');
        Route::post('products/bulk-assign', [RackProductController::class, 'saveBulkAssign'])->name('products.bulk_assign.save');
        Route::post('products/bulk-destroy', [RackProductController::class, 'bulkDestroy'])->name('products.bulk_destroy');
        Route::post('products/import', [RackProductController::class, 'importProducts'])->name('products.import');
        Route::post('products/reset', [RackProductController::class, 'resetProducts'])->name('products.reset');
        Route::get('products/{id}/audit-trail', [RackProductController::class, 'auditTrail'])->name('products.audit_trail');
        Route::put('products/{id}', [RackProductController::class, 'update'])->name('products.update');
        Route::delete('products/{id}', [RackProductController::class, 'destroy'])->name('products.destroy');

        // Rack product assignment
        Route::get('racks/{id}/products', [RackProductController::class, 'rackProducts'])->name('racks.products');
        Route::post('racks/{id}/products', [RackProductController::class, 'saveRackProducts'])->name('racks.products.save');

        // Settings
        Route::get('settings', [SettingsController::class, 'show'])->name('settings');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test-fonnte', [SettingsController::class, 'testFonnte'])->name('settings.test_fonnte');

        // Test Order
        Route::get('test-order', [OrderController::class, 'showTestOrder'])->name('test_order');
        Route::post('test-order', [OrderController::class, 'createTestOrder'])->name('test_order.create');
        Route::get('current-order', [OrderController::class, 'currentOrdersIndex'])->name('current_order.index');

        // Cleanup
        Route::get('cleanup', [OrderController::class, 'showCleanup'])->name('cleanup');
        Route::post('cleanup', [OrderController::class, 'processCleanup'])->name('cleanup.process');

        // Task Management (Supervisor)
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/rack-check', [TaskController::class, 'rackIndex'])->name('tasks.rack.index');
        Route::post('tasks/rack-check/reset', [TaskController::class, 'rackReset'])->name('tasks.rack.reset');
        Route::post('tasks/reset-all', [TaskController::class, 'resetAll'])->name('tasks.reset_all');
        Route::post('tasks/force-generate', [TaskController::class, 'forceGenerate'])->name('tasks.force_generate');
        Route::post('tasks/bulk-cancel-today', [TaskController::class, 'bulkCancelToday'])->name('tasks.bulk_cancel_today');
        Route::get('tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::post('tasks/cashiers', [TaskController::class, 'cashierStore'])->name('tasks.cashiers.store');
        Route::delete('tasks/cashiers/{id}', [TaskController::class, 'cashierDestroy'])->name('tasks.cashiers.destroy');
        Route::get('tasks/recurring/{id}/edit', [TaskController::class, 'recurringEdit'])->name('tasks.recurring.edit');
        Route::put('tasks/recurring/{id}', [TaskController::class, 'recurringUpdate'])->name('tasks.recurring.update');
        Route::patch('tasks/recurring/{id}/schedule', [TaskController::class, 'recurringScheduleUpdate'])->name('tasks.recurring.schedule_update');
        Route::post('tasks/recurring/{id}/force-generate', [TaskController::class, 'recurringForceGenerate'])->name('tasks.recurring.force_generate');
        Route::get('tasks/templates/board', [TaskController::class, 'templatesBoard'])->name('tasks.templates.board');
        Route::delete('tasks/{id}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::delete('tasks/recurring/{id}', [TaskController::class, 'recurringDestroy'])->name('tasks.recurring.destroy');
        Route::post('tasks/recurring/batch-destroy', [TaskController::class, 'recurringBatchDestroy'])->name('tasks.recurring.batch_destroy');

        // Live Dashboard
        Route::get('tasks/live', [TaskController::class, 'live'])->name('tasks.live');
        Route::get('tasks/live/active-sessions', [TaskController::class, 'liveActiveSessions'])->name('tasks.live.active_sessions');

        Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::get('reconciliation/{isoYearWeek}/{reportId}', [ReconciliationController::class, 'show'])->name('reconciliation.show');
        Route::post('reconciliation/run', [ReconciliationController::class, 'runNow'])->name('reconciliation.run');

        // Task Categories (AJAX)
        Route::get('tasks/categories', [TaskController::class, 'categoryIndex'])->name('tasks.categories.index');
        Route::post('tasks/categories', [TaskController::class, 'categoryStore'])->name('tasks.categories.store');
        Route::delete('tasks/categories/{id}', [TaskController::class, 'categoryDestroy'])->name('tasks.categories.destroy');

        // Bulk Reassign
        Route::post('tasks/bulk-reassign', [TaskController::class, 'bulkReassign'])->name('tasks.bulk_reassign');

        // Stock Report Export
        Route::get('tasks/export-stock', [TaskController::class, 'exportStockReport'])->name('tasks.export_stock');

        // Restock & Purchase Orders
        Route::get('restock', [RestockController::class, 'index'])->name('restock.index');
        Route::post('restock/create-po', [RestockController::class, 'createPO'])->name('restock.create_po');
        Route::post('restock/create-batch-po', [RestockController::class, 'createBatchPO'])->name('restock.create_batch_po');
        Route::post('restock/create-manual-po', [RestockController::class, 'createManualPO'])->name('restock.create_manual_po');
        Route::get('restock/orders', [RestockController::class, 'orders'])->name('restock.orders');
        Route::get('restock/orders/{id}', [RestockController::class, 'orderDetail'])->name('restock.order_detail');
        Route::delete('restock/orders/{id}', [RestockController::class, 'cancelOrder'])->name('restock.cancel_order');
        Route::post('restock/orders/{poId}/accept-as-is/{restockId}', [RestockController::class, 'acceptAsIs'])->name('restock.accept_as_is');

        // Suppliers
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::post('suppliers/ajax-store', [SupplierController::class, 'storeAjax'])->name('suppliers.ajax_store');
        Route::get('suppliers/{id}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{id}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        // Shifts & Schedules
        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::put('shifts/{id}', [ShiftController::class, 'update'])->name('shifts.update');
        Route::delete('shifts/{id}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
        Route::get('schedules', [ShiftController::class, 'schedules'])->name('schedules.index');
        Route::post('schedules/save', [ShiftController::class, 'saveScheduleTemplate'])->name('schedules.save');

        // Attendance
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
        Route::get('attendance/qr', [AttendanceController::class, 'qrConfig'])->name('attendance.qr');
        Route::post('attendance/qr/regenerate', [AttendanceController::class, 'regenerateQr'])->name('attendance.qr.regenerate');
        Route::get('attendance/qr/print', [AttendanceController::class, 'printQr'])->name('attendance.qr.print');
        Route::post('attendance/{waiterId}/{date}/override', [AttendanceController::class, 'override'])->name('attendance.override');
        Route::delete('attendance/{waiterId}/{date}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

        // Bonus Management
        Route::get('bonus/config', [BonusController::class, 'config'])->name('bonus.config');
        Route::post('bonus/config', [BonusController::class, 'updateConfig'])->name('bonus.config.update');
        Route::post('bonus/reset-data', [BonusController::class, 'resetBonusData'])->name('bonus.reset_data');
        Route::get('bonus/daily-scoring', [BonusController::class, 'dailyScoring'])->name('bonus.daily_scoring');
        Route::post('bonus/daily-scoring', [BonusController::class, 'storeDailyScore'])->name('bonus.daily_scoring.store');
        Route::get('bonus/penalties', [BonusController::class, 'penalties'])->name('bonus.penalties');
        Route::post('bonus/penalties', [BonusController::class, 'storePenalty'])->name('bonus.penalties.store');
        Route::delete('bonus/penalties/{id}', [BonusController::class, 'destroyPenalty'])->name('bonus.penalties.destroy');
        Route::get('bonus/sales-targets', [BonusController::class, 'salesTargets'])->name('bonus.sales_targets');
        Route::post('bonus/sales-targets', [BonusController::class, 'storeSalesTarget'])->name('bonus.sales_targets.store');
        Route::post('bonus/sales-record', [BonusController::class, 'recordSales'])->name('bonus.sales_record');
        Route::get('bonus/monthly-summary', [BonusController::class, 'monthlySummary'])->name('bonus.monthly_summary');
        Route::post('bonus/monthly-summary/calculate', [BonusController::class, 'calculateSummary'])->name('bonus.monthly_summary.calculate');
        Route::post('bonus/monthly-summary/finalize', [BonusController::class, 'finalizeSummary'])->name('bonus.monthly_summary.finalize');
        Route::post('bonus/monthly-summary/override', [BonusController::class, 'overrideBonus'])->name('bonus.monthly_summary.override');
        Route::get('bonus/leaderboard', [BonusController::class, 'leaderboard'])->name('bonus.leaderboard');
        Route::post('bonus/leaderboard/generate', [BonusController::class, 'generateLeaderboard'])->name('bonus.leaderboard.generate');

        // Payroll Management
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll/config', [PayrollController::class, 'updateConfig'])->name('payroll.config_update');
        Route::post('payroll/run-salary-credit', [PayrollController::class, 'runSalaryCreditNow'])->name('payroll.run_salary_credit');
        Route::get('payroll/withdrawals', [PayrollController::class, 'withdrawalsIndex'])->name('payroll.withdrawals');
        Route::get('payroll/penarikan', [PayrollController::class, 'withdrawalsIndex'])->name('payroll.penarikan');
        Route::post('payroll/withdrawals/{txId}/approve', [PayrollController::class, 'approveWithdrawal'])->name('payroll.withdrawals.approve');
        Route::post('payroll/withdrawals/{txId}/reject', [PayrollController::class, 'rejectWithdrawal'])->name('payroll.withdrawals.reject');
        Route::get('payroll/{waiterId}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{waiterId}/settings', [PayrollController::class, 'updateSettings'])->name('payroll.settings_update');
        Route::post('payroll/{waiterId}/credit', [PayrollController::class, 'manualCredit'])->name('payroll.manual_credit');

        // Audit Log
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit_log.index');

        // Waiter Performance
        Route::get('waiters/{id}/performance', [WaiterPerformanceController::class, 'show'])->name('waiters.performance');

        // Finance Module
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\FinanceController::class, 'dashboard'])->name('dashboard');
            Route::get('settings', [\App\Http\Controllers\Admin\FinanceController::class, 'settings'])->name('settings');
            Route::post('settings', [\App\Http\Controllers\Admin\FinanceController::class, 'saveSettings'])->name('settings.save');
            Route::post('test-connection', [\App\Http\Controllers\Admin\FinanceController::class, 'testConnection'])->name('test_connection');

            // Sync
            Route::get('sync', [\App\Http\Controllers\Admin\FinanceController::class, 'sync'])->name('sync');
            Route::post('sync', [\App\Http\Controllers\Admin\FinanceController::class, 'doSync'])->name('sync.run');
            Route::post('sync-today', [\App\Http\Controllers\Admin\FinanceController::class, 'syncToday'])->name('sync.today');
            Route::get('sync-logs', [\App\Http\Controllers\Admin\FinanceController::class, 'syncLogs'])->name('sync_logs');

            // Categories
            Route::get('categories', [\App\Http\Controllers\Admin\FinanceController::class, 'categories'])->name('categories');
            Route::post('categories', [\App\Http\Controllers\Admin\FinanceController::class, 'storeCategory'])->name('categories.store');
            Route::put('categories/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateCategory'])->name('categories.update');
            Route::post('categories/{id}/toggle', [\App\Http\Controllers\Admin\FinanceController::class, 'toggleCategory'])->name('categories.toggle');

            // Allocations
            Route::get('allocations', [\App\Http\Controllers\Admin\FinanceController::class, 'allocations'])->name('allocations');
            Route::post('allocations', [\App\Http\Controllers\Admin\FinanceController::class, 'storeAllocation'])->name('allocations.store');
            Route::put('allocations/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateAllocation'])->name('allocations.update');
            Route::delete('allocations/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'deleteAllocation'])->name('allocations.delete');
            Route::post('allocations/simulate', [\App\Http\Controllers\Admin\FinanceController::class, 'simulateAllocation'])->name('allocations.simulate');

            // Cash Accounts
            Route::get('cash-accounts', [\App\Http\Controllers\Admin\FinanceController::class, 'cashAccounts'])->name('cash_accounts');
            Route::post('cash-accounts', [\App\Http\Controllers\Admin\FinanceController::class, 'storeCashAccount'])->name('cash_accounts.store');
            Route::put('cash-accounts/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateCashAccount'])->name('cash_accounts.update');
            Route::post('cash-accounts/{id}/toggle', [\App\Http\Controllers\Admin\FinanceController::class, 'toggleCashAccount'])->name('cash_accounts.toggle');
            Route::post('cash-accounts/{id}/reset', [\App\Http\Controllers\Admin\FinanceController::class, 'resetCashAccount'])->name('cash_accounts.reset');

            // Transfers
            Route::get('transfers', [\App\Http\Controllers\Admin\FinanceController::class, 'transfers'])->name('transfers');
            Route::post('transfers', [\App\Http\Controllers\Admin\FinanceController::class, 'storeTransfer'])->name('transfers.store');
            Route::post('transfers/{id}/approve', [\App\Http\Controllers\Admin\FinanceController::class, 'approveTransfer'])->name('transfers.approve');
            Route::post('transfers/{id}/reject', [\App\Http\Controllers\Admin\FinanceController::class, 'rejectTransfer'])->name('transfers.reject');

            // Hutang Supplier
            Route::get('debts', [\App\Http\Controllers\Admin\FinanceController::class, 'debts'])->name('debts');
            Route::post('debts', [\App\Http\Controllers\Admin\FinanceController::class, 'storeDebt'])->name('debts.store');
            Route::post('debts/{id}/pay', [\App\Http\Controllers\Admin\FinanceController::class, 'payDebt'])->name('debts.pay');
            Route::get('debts/{id}/payments', [\App\Http\Controllers\Admin\FinanceController::class, 'debtPayments'])->name('debts.payments');

            // Mutations
            Route::get('mutations', [\App\Http\Controllers\Admin\FinanceController::class, 'mutations'])->name('mutations');

            // Pengeluaran Manual
            Route::get('expenses', [\App\Http\Controllers\Admin\FinanceController::class, 'expenses'])->name('expenses');
            Route::post('expenses', [\App\Http\Controllers\Admin\FinanceController::class, 'storeExpense'])->name('expenses.store');

            // Budget vs Realisasi
            Route::get('budget', [\App\Http\Controllers\Admin\FinanceController::class, 'budgetRealization'])->name('budget');

            // API Mappings
            Route::get('mappings/category', [\App\Http\Controllers\Admin\FinanceController::class, 'categoryMappings'])->name('mappings.category');
            Route::get('mappings/account', [\App\Http\Controllers\Admin\FinanceController::class, 'accountMappings'])->name('mappings.account');
            Route::post('mappings', [\App\Http\Controllers\Admin\FinanceController::class, 'storeMapping'])->name('mappings.store');
            Route::put('mappings/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateMapping'])->name('mappings.update');
            Route::delete('mappings/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'deleteMapping'])->name('mappings.delete');

            // Shifts
            Route::get('shifts', [\App\Http\Controllers\Admin\FinanceController::class, 'shifts'])->name('shifts');

            // Need Review
            Route::get('need-review', [\App\Http\Controllers\Admin\FinanceController::class, 'needReview'])->name('need_review');
            Route::post('need-review/{id}', [\App\Http\Controllers\Admin\FinanceController::class, 'resolveReview'])->name('need_review.resolve');

            // Audit Log
            Route::get('audit-log', [\App\Http\Controllers\Admin\FinanceController::class, 'auditLog'])->name('audit_log');

            // Reports
            Route::get('report/monthly', [\App\Http\Controllers\Admin\FinanceController::class, 'reportMonthly'])->name('report.monthly');
            Route::get('report/balance', [\App\Http\Controllers\Admin\FinanceController::class, 'reportBalance'])->name('report.balance');
            Route::get('report/export', [\App\Http\Controllers\Admin\FinanceController::class, 'exportReport'])->name('report.export');
        });
    });
});

// Cashier route (no auth required)
Route::get('cashier', [CashierController::class, 'index'])->name('cashier.index');
Route::get('cashier/workers', [CashierController::class, 'getCashierWorkers'])->name('cashier.workers');
Route::get('cashier/attendance-qr', [CashierController::class, 'getAttendanceQr'])->name('cashier.attendance_qr');
Route::get('cashier/attendance-qr/global', [CashierController::class, 'getGlobalAttendanceQr'])->name('cashier.attendance_qr_global');

// === Finance Sync Webhook (dipanggil shift kasir setelah closing) ===
// CSRF dikecualikan via bootstrap/app.php (webhooks/*).
Route::post('webhooks/finance-sync', function (\Illuminate\Http\Request $request) {
    $token = $request->header('X-Finance-Token');
    $expectedToken = app(\App\Services\FinanceService::class)->getSetting('api_token');

    if (!$token || $token !== $expectedToken) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $tanggal = $request->input('tanggal', date('Y-m-d'));
    $result = app(\App\Services\FinanceService::class)->syncDaily($tanggal, $tanggal, 'webhook');

    return response()->json(['success' => true, 'status' => $result['status'], 'synced' => $result['synced']]);
})->name('webhooks.finance_sync');

// === DANA Listener Webhook (TESTING ONLY, NO AUTH) ===
// Endpoint testing untuk menerima POST dari app notification listener.
// CSRF dikecualikan via bootstrap/app.php → middleware->validateCsrfTokens(except: ['webhooks/*']).
Route::prefix('webhooks/dana-listener')->name('webhooks.dana_listener.')->group(function () {
    Route::post('/', [\App\Http\Controllers\DanaWebhookController::class, 'receive'])->name('receive');
    Route::get('/inspect', [\App\Http\Controllers\DanaWebhookController::class, 'inspect'])->name('inspect');
    Route::get('/feed', [\App\Http\Controllers\DanaWebhookController::class, 'feed'])->name('feed');
    Route::post('/reset', [\App\Http\Controllers\DanaWebhookController::class, 'reset'])->name('reset');
});

// Cashier task actions (no auth, accessible from cashier page)
Route::post('cashier/tasks/sync-due', [CashierController::class, 'syncDueTasks'])
    ->middleware('throttle:20,1')
    ->name('cashier.task.sync_due');
Route::post('cashier/tasks/{id}/status', [CashierController::class, 'updateTaskStatus'])->name('cashier.task.status');

// Waiter routes
Route::prefix('waiter')->name('waiter.')->group(function () {
    Route::get('login', [WaiterController::class, 'showLogin'])->name('login');
    Route::post('login', [WaiterController::class, 'login'])->name('login.post');
    Route::post('login/google', [WaiterController::class, 'loginWithGoogle'])
        ->middleware('throttle:20,1')
        ->name('login.google');

    Route::middleware('waiter.auth')->group(function () {
        Route::get('tasks', [WaiterController::class, 'tasksIndex'])->name('tasks');
        Route::get('tasks/poll', [WaiterController::class, 'pollTasks'])
            ->middleware('throttle:waiter-poll')
            ->name('task.poll');
        Route::post('tasks/sync-due', [WaiterController::class, 'syncDueTasks'])
            ->middleware('throttle:waiter-sync-due')
            ->name('task.sync_due');
        Route::post('tasks/{id}/claim', [WaiterController::class, 'claimTask'])->name('task.claim');
        Route::post('tasks/{id}/release', [WaiterController::class, 'releaseTask'])->name('task.release');
        Route::post('tasks/{id}/complete', [WaiterController::class, 'completeTask'])->name('task.complete');
        Route::get('stock-take', [WaiterController::class, 'stockTakeIndex'])->name('stock_take');
        Route::post('stock-take/resolve-rack', [WaiterController::class, 'resolveStockTakeRack'])->name('stock_take.resolve_rack');
        Route::post('stock-take/submit', [WaiterController::class, 'submitStockTake'])->name('stock_take.submit');
        Route::post('activity-reports', [WaiterController::class, 'storeActivityReport'])
            ->middleware('throttle:waiter-activity-store')
            ->name('activity.store');
        Route::get('rack-products', [WaiterController::class, 'getRackProducts'])->name('rack_products');
        Route::get('rack-products/search', [WaiterController::class, 'searchMasterProducts'])->name('rack_products.search');
        Route::post('rack-products/assign', [WaiterController::class, 'assignProductToRack'])->name('rack_products.assign');
        Route::post('rack-products/storage-info', [WaiterController::class, 'storageInfoForProducts'])->name('rack_products.storage_info');

        // Attendance
        Route::post('attendance/clock-in', [WaiterController::class, 'clockIn'])->name('attendance.clock_in');
        Route::post('attendance/clock-out', [WaiterController::class, 'clockOut'])->name('attendance.clock_out');
        Route::get('attendance/status', [WaiterController::class, 'getAttendanceStatus'])->name('attendance.status');

        // Bonus Dashboard
        Route::get('bonus', [\App\Http\Controllers\WaiterBonusController::class, 'index'])->name('bonus');
        Route::get('bonus/api', [\App\Http\Controllers\WaiterBonusController::class, 'apiData'])->name('bonus.api');

        // Payroll Portal
        Route::get('payroll', [\App\Http\Controllers\WaiterPayrollController::class, 'index'])->name('payroll');
        Route::get('payroll/api', [\App\Http\Controllers\WaiterPayrollController::class, 'apiSnapshot'])->name('payroll.api');
        Route::post('payroll/withdraw', [\App\Http\Controllers\WaiterPayrollController::class, 'requestWithdrawal'])->name('payroll.withdraw');
        Route::post('payroll/bank', [\App\Http\Controllers\WaiterPayrollController::class, 'updateBankAccount'])->name('payroll.bank_update');

        // Restock / Penerimaan Barang
        Route::get('restock', [WaiterController::class, 'restockList'])->name('restock');
        Route::post('restock/{poId}/receive', [WaiterController::class, 'receiveRestockItem'])->name('restock.receive');
        Route::post('restock/{poId}/report-issue', [WaiterController::class, 'reportRestockIssue'])->name('restock.report_issue');

        Route::get('logout', [WaiterController::class, 'logout'])->name('logout');
    });
});
