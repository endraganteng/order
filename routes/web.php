<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\WaiterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminController::class, 'showLogin'])->name('login');
    Route::post('login', [AdminController::class, 'login'])->name('login.post');

    Route::middleware('admin.auth')->group(function () {
        Route::get('logout', [AdminController::class, 'logout'])->name('logout');
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // Waiters management
        Route::get('waiters', [AdminController::class, 'waitersIndex'])->name('waiters.index');
        Route::get('waiters/create', [AdminController::class, 'waitersCreate'])->name('waiters.create');
        Route::post('waiters', [AdminController::class, 'waitersStore'])->name('waiters.store');
        Route::get('waiters/{id}/edit', [AdminController::class, 'waitersEdit'])->name('waiters.edit');
        Route::put('waiters/{id}', [AdminController::class, 'waitersUpdate'])->name('waiters.update');
        Route::delete('waiters/{id}', [AdminController::class, 'waitersDestroy'])->name('waiters.destroy');

        // Rack management
        Route::get('racks', [AdminController::class, 'racksIndex'])->name('racks.index');
        Route::get('racks/create', [AdminController::class, 'racksCreate'])->name('racks.create');
        Route::post('racks', [AdminController::class, 'racksStore'])->name('racks.store');
        Route::get('racks/print-labels', [AdminController::class, 'racksPrintLabels'])->name('racks.print_labels');
        Route::get('racks/export-barcodes', [AdminController::class, 'racksExportBarcodes'])->name('racks.export_barcodes');
        Route::get('racks/{id}/edit', [AdminController::class, 'racksEdit'])->name('racks.edit');
        Route::put('racks/{id}', [AdminController::class, 'racksUpdate'])->name('racks.update');
        Route::post('racks/{id}/regenerate-barcode', [AdminController::class, 'racksRegenerateBarcode'])->name('racks.regenerate_barcode');
        Route::delete('racks/{id}', [AdminController::class, 'racksDestroy'])->name('racks.destroy');

        // Settings
        Route::get('settings', [AdminController::class, 'showSettings'])->name('settings');
        Route::post('settings', [AdminController::class, 'updateSettings'])->name('settings.update');

        // Test Order
        Route::get('test-order', [AdminController::class, 'showTestOrder'])->name('test_order');
        Route::post('test-order', [AdminController::class, 'createTestOrder'])->name('test_order.create');
        Route::get('current-order', [AdminController::class, 'currentOrdersIndex'])->name('current_order.index');

        // Cleanup
        Route::get('cleanup', [AdminController::class, 'showCleanup'])->name('cleanup');
        Route::post('cleanup', [AdminController::class, 'processCleanup'])->name('cleanup.process');

        // Task Management (Supervisor)
        Route::get('tasks', [AdminController::class, 'tasksIndex'])->name('tasks.index');
        Route::get('tasks/rack-check', [AdminController::class, 'rackTasksIndex'])->name('tasks.rack.index');
        Route::post('tasks/rack-check/reset', [AdminController::class, 'rackTasksReset'])->name('tasks.rack.reset');
        Route::get('tasks/create', [AdminController::class, 'tasksCreate'])->name('tasks.create');
        Route::post('tasks', [AdminController::class, 'tasksStore'])->name('tasks.store');
        Route::post('tasks/cashiers', [AdminController::class, 'tasksCashierStore'])->name('tasks.cashiers.store');
        Route::delete('tasks/cashiers/{id}', [AdminController::class, 'tasksCashierDestroy'])->name('tasks.cashiers.destroy');
        Route::get('tasks/recurring/{id}/edit', [AdminController::class, 'tasksRecurringEdit'])->name('tasks.recurring.edit');
        Route::put('tasks/recurring/{id}', [AdminController::class, 'tasksRecurringUpdate'])->name('tasks.recurring.update');
        Route::delete('tasks/{id}', [AdminController::class, 'tasksDestroy'])->name('tasks.destroy');
        Route::delete('tasks/recurring/{id}', [AdminController::class, 'tasksRecurringDestroy'])->name('tasks.recurring.destroy');
    });
});

// Cashier route (no auth required)
Route::get('cashier', [CashierController::class, 'index'])->name('cashier.index');
Route::get('cashier/workers', [CashierController::class, 'getCashierWorkers'])->name('cashier.workers');

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
        Route::post('tasks/{id}/complete', [WaiterController::class, 'completeTask'])->name('task.complete');
        Route::post('activity-reports', [WaiterController::class, 'storeActivityReport'])
            ->middleware('throttle:waiter-activity-store')
            ->name('activity.store');
        Route::get('logout', [WaiterController::class, 'logout'])->name('logout');
    });
});
