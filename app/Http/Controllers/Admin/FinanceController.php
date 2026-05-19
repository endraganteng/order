<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(protected FinanceService $finance) {}

    // ─── Dashboard ──────────────────────────────────────────────

    public function dashboard()
    {
        $today = $this->finance->getDashboardSummary();
        $month = $this->finance->getMonthSummary(date('Y-m'));
        $accounts = $this->finance->getCashAccounts(true);
        $lastSync = $this->finance->getLastSync();
        $pendingTransfers = $this->finance->getTransfers('pending');
        $categories = $this->finance->getCategories('expense', true);

        return view('admin.finance.dashboard', compact('today', 'month', 'accounts', 'lastSync', 'pendingTransfers', 'categories'));
    }

    // ─── Settings / Sync Config ─────────────────────────────────

    public function settings()
    {
        $settings = $this->finance->getAllSettings();
        return view('admin.finance.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'api_domain' => 'nullable|url',
            'api_token' => 'nullable|string|max:255',
            'auto_sync_enabled' => 'nullable',
            'auto_sync_time' => 'nullable|string|max:5',
            'sync_mode' => 'nullable|string|in:manual,daily,hourly,daily_hourly',
            'sync_data_target' => 'nullable|string|in:yesterday,today,retry_failed',
        ]);

        $old = $this->finance->getAllSettings();
        $this->finance->saveSettings($request->only(['api_domain', 'api_token', 'auto_sync_enabled', 'auto_sync_time', 'sync_mode', 'sync_data_target']));
        $this->finance->logAudit('update', 'settings', null, $old, $request->only(['api_domain', 'auto_sync_enabled', 'auto_sync_time', 'sync_mode', 'sync_data_target']));

        return response()->json(['success' => true, 'message' => 'Pengaturan disimpan.']);
    }

    public function testConnection()
    {
        return response()->json($this->finance->testConnection());
    }

    // ─── Sync ───────────────────────────────────────────────────

    public function sync()
    {
        return view('admin.finance.sync');
    }

    public function doSync(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        $result = $this->finance->syncDaily($request->from, $request->to, session('admin_name', 'admin'));
        $this->finance->logAudit('sync', 'sync', $result['id'] ?? null, null, ['from' => $request->from, 'to' => $request->to, 'status' => $result['status']]);
        return response()->json($result);
    }

    public function syncToday()
    {
        $today = date('Y-m-d');
        $result = $this->finance->syncDaily($today, $today, session('admin_name', 'admin'));
        return response()->json($result);
    }

    // ─── Sync Logs ──────────────────────────────────────────────

    public function syncLogs(Request $request)
    {
        $logs = $this->finance->getSyncLogs($request->integer('limit', 20), $request->integer('offset', 0));
        if ($request->wantsJson()) return response()->json($logs);
        return view('admin.finance.sync_logs', compact('logs'));
    }

    // ─── Categories ─────────────────────────────────────────────

    public function categories(Request $request)
    {
        $categories = $this->finance->getCategories($request->type, $request->has('active') ? (bool) $request->active : null);
        if ($request->wantsJson()) return response()->json($categories);
        return view('admin.finance.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'type' => 'required|in:income,expense']);
        $id = $this->finance->createCategory($request->only(['name', 'type', 'parent_id', 'sort_order']));
        $this->finance->logAudit('create', 'category', $id, null, $request->only(['name', 'type']));
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateCategory(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $old = $this->finance->getCategory($id);
        $this->finance->updateCategory($id, $request->only(['name', 'type', 'parent_id', 'sort_order']));
        $this->finance->logAudit('update', 'category', $id, $old, $request->only(['name', 'type']));
        return response()->json(['success' => true]);
    }

    public function toggleCategory(int $id)
    {
        $this->finance->toggleCategory($id);
        $this->finance->logAudit('toggle', 'category', $id);
        return response()->json(['success' => true]);
    }

    // ─── Allocations ────────────────────────────────────────────

    public function allocations(Request $request)
    {
        $allocations = $this->finance->getAllocations($request->has('active') ? (bool) $request->active : null);
        $categories = $this->finance->getCategories('expense', true);
        if ($request->wantsJson()) return response()->json(['allocations' => $allocations, 'categories' => $categories]);
        return view('admin.finance.allocations', compact('allocations', 'categories'));
    }

    public function storeAllocation(Request $request)
    {
        $request->validate(['finance_category_id' => 'required|integer', 'percentage' => 'required|numeric|min:0|max:100', 'effective_date' => 'required|date']);
        $id = $this->finance->createAllocation($request->only(['finance_category_id', 'percentage', 'effective_date', 'end_date', 'notes']));
        $this->finance->logAudit('create', 'allocation', $id, null, $request->only(['finance_category_id', 'percentage', 'effective_date']));
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateAllocation(Request $request, int $id)
    {
        $request->validate(['percentage' => 'required|numeric|min:0|max:100']);
        $this->finance->updateAllocation($id, $request->only(['finance_category_id', 'percentage', 'effective_date', 'end_date', 'is_active', 'notes']));
        $this->finance->logAudit('update', 'allocation', $id);
        return response()->json(['success' => true]);
    }

    public function deleteAllocation(int $id)
    {
        $this->finance->deleteAllocation($id);
        $this->finance->logAudit('delete', 'allocation', $id);
        return response()->json(['success' => true]);
    }

    public function simulateAllocation(Request $request)
    {
        $request->validate(['total' => 'required|numeric|min:0']);
        return response()->json($this->finance->simulateAllocation((int) $request->total));
    }

    // ─── Cash Accounts ──────────────────────────────────────────

    public function cashAccounts(Request $request)
    {
        $accounts = $this->finance->getCashAccounts();
        if ($request->wantsJson()) return response()->json($accounts);
        return view('admin.finance.cash_accounts', compact('accounts'));
    }

    public function storeCashAccount(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'code' => 'required|string|max:50|unique:cash_accounts,code']);
        $id = $this->finance->createCashAccount($request->only(['name', 'code', 'balance', 'sort_order']));
        $this->finance->logAudit('create', 'cash_account', $id, null, $request->only(['name', 'code']));
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateCashAccount(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $old = $this->finance->getCashAccount($id);
        $this->finance->updateCashAccount($id, $request->only(['name', 'code', 'sort_order']));
        $this->finance->logAudit('update', 'cash_account', $id, $old, $request->only(['name', 'code']));
        return response()->json(['success' => true]);
    }

    public function toggleCashAccount(int $id)
    {
        $this->finance->toggleCashAccount($id);
        $this->finance->logAudit('toggle', 'cash_account', $id);
        return response()->json(['success' => true]);
    }

    public function resetCashAccount(int $id)
    {
        $old = $this->finance->getCashAccount($id);
        $this->finance->resetAccountBalance($id);
        $this->finance->logAudit('reset', 'cash_account', $id, $old, ['balance' => 0]);
        return response()->json(['success' => true]);
    }

    // ─── Transfers ──────────────────────────────────────────────

    public function transfers(Request $request)
    {
        $transfers = $this->finance->getTransfers($request->status);
        $accounts = $this->finance->getCashAccounts(true);
        if ($request->wantsJson()) return response()->json(['transfers' => $transfers, 'accounts' => $accounts]);
        return view('admin.finance.transfers', compact('transfers', 'accounts'));
    }

    public function storeTransfer(Request $request)
    {
        $request->validate(['from_account_id' => 'required|integer', 'to_account_id' => 'required|integer|different:from_account_id', 'amount' => 'required|numeric|min:1']);
        $id = $this->finance->createTransfer($request->only(['from_account_id', 'to_account_id', 'amount', 'fee', 'notes', 'status']));
        $this->finance->logAudit('create', 'transfer', $id, null, $request->only(['from_account_id', 'to_account_id', 'amount']));
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function approveTransfer(int $id)
    {
        $result = $this->finance->approveTransfer($id, session('admin_name', 'admin'));
        if (!$result) return response()->json(['success' => false, 'message' => 'Saldo tidak cukup atau status tidak valid.'], 422);
        $this->finance->logAudit('approve', 'transfer', $id);
        return response()->json(['success' => true]);
    }

    public function rejectTransfer(int $id)
    {
        $this->finance->rejectTransfer($id, session('admin_name', 'admin'));
        $this->finance->logAudit('reject', 'transfer', $id);
        return response()->json(['success' => true]);
    }

    // ─── Mutations ──────────────────────────────────────────────

    public function mutations(Request $request)
    {
        $mutations = $this->finance->getMutations($request->integer('account_id') ?: null, $request->from, $request->to, $request->integer('limit', 50), $request->integer('offset', 0));
        $accounts = $this->finance->getCashAccounts();
        if ($request->wantsJson()) return response()->json($mutations);
        return view('admin.finance.mutations', compact('mutations', 'accounts'));
    }

    // ─── API Mappings ───────────────────────────────────────────

    public function categoryMappings(Request $request)
    {
        $mappings = $this->finance->getMappings('category');
        $categories = $this->finance->getCategories(null, true);
        if ($request->wantsJson()) return response()->json(['mappings' => $mappings, 'categories' => $categories]);
        return view('admin.finance.mappings_category', compact('mappings', 'categories'));
    }

    public function accountMappings(Request $request)
    {
        $mappings = $this->finance->getMappings('cash_account');
        $accounts = $this->finance->getCashAccounts(true);
        if ($request->wantsJson()) return response()->json(['mappings' => $mappings, 'accounts' => $accounts]);
        return view('admin.finance.mappings_account', compact('mappings', 'accounts'));
    }

    public function storeMapping(Request $request)
    {
        $request->validate(['mapping_type' => 'required|in:category,cash_account', 'api_key' => 'required|string', 'api_value' => 'required|string', 'target_id' => 'required|integer']);
        $id = $this->finance->createMapping($request->only(['mapping_type', 'api_key', 'api_value', 'target_id']));
        $this->finance->logAudit('create', 'mapping', $id, null, $request->only(['mapping_type', 'api_key', 'api_value', 'target_id']));
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateMapping(Request $request, int $id)
    {
        $request->validate(['target_id' => 'required|integer']);
        $this->finance->updateMapping($id, $request->only(['api_key', 'api_value', 'target_id', 'is_active']));
        $this->finance->logAudit('update', 'mapping', $id);
        return response()->json(['success' => true]);
    }

    public function deleteMapping(int $id)
    {
        $this->finance->deleteMapping($id);
        $this->finance->logAudit('delete', 'mapping', $id);
        return response()->json(['success' => true]);
    }

    // ─── Shifts ─────────────────────────────────────────────────

    public function shifts(Request $request)
    {
        $shifts = $this->finance->getShifts($request->from, $request->to);
        if ($request->wantsJson()) return response()->json($shifts);
        return view('admin.finance.shifts', compact('shifts'));
    }

    // ─── Need Review ────────────────────────────────────────────

    public function needReview(Request $request)
    {
        $items = $this->finance->getNeedReviewItems($request->integer('limit', 50), $request->integer('offset', 0));
        $categories = $this->finance->getCategories(null, true);
        if ($request->wantsJson()) return response()->json($items);
        return view('admin.finance.need_review', compact('items', 'categories'));
    }

    public function resolveReview(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:resolve,ignore', 'finance_category_id' => 'nullable|integer']);
        $this->finance->resolveReviewItem($id, $request->integer('finance_category_id') ?: null, $request->action);
        $this->finance->logAudit($request->action, 'review', $id);
        return response()->json(['success' => true]);
    }

    // ─── Audit Log ──────────────────────────────────────────────

    public function auditLog(Request $request)
    {
        $logs = $this->finance->getAuditLogs($request->module, $request->integer('limit', 50), $request->integer('offset', 0));
        if ($request->wantsJson()) return response()->json($logs);
        return view('admin.finance.audit_log', compact('logs'));
    }

    // ─── Hutang Supplier ────────────────────────────────────────

    public function debts(Request $request)
    {
        $debts = $this->finance->getDebts($request->status);
        $summary = $this->finance->getDebtSummary();
        $accounts = $this->finance->getCashAccounts(true);
        if ($request->wantsJson()) return response()->json(['debts' => $debts, 'summary' => $summary]);
        return view('admin.finance.debts', compact('debts', 'summary', 'accounts'));
    }

    public function storeDebt(Request $request)
    {
        $request->validate(['supplier_name' => 'required|string|max:100', 'amount' => 'required|numeric|min:1', 'debt_date' => 'required|date']);
        $id = $this->finance->createDebt($request->only(['supplier_name', 'amount', 'description', 'debt_date', 'due_date']));
        $this->finance->logAudit('create', 'debt', $id, null, $request->only(['supplier_name', 'amount']));
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function payDebt(Request $request, int $id)
    {
        $request->validate(['cash_account_id' => 'required|integer', 'amount' => 'required|numeric|min:1', 'payment_date' => 'required|date']);
        $result = $this->finance->payDebt($id, $request->integer('cash_account_id'), (int) $request->amount, $request->payment_date, $request->notes);
        if (!$result) return response()->json(['success' => false, 'message' => 'Hutang sudah lunas atau tidak ditemukan.'], 422);
        $this->finance->logAudit('pay', 'debt', $id, null, ['amount' => $request->amount]);
        return response()->json(['success' => true]);
    }

    public function debtPayments(int $id)
    {
        return response()->json($this->finance->getDebtPayments($id));
    }

    // ─── Pengeluaran Manual ────────────────────────────────────

    public function expenses(Request $request)
    {
        $accounts = $this->finance->getCashAccounts(true);
        $categories = $this->finance->getCategories('expense', true);
        $budget = $this->finance->getBudgetRealization($request->month ?? date('Y-m'));
        return view('admin.finance.expenses', compact('accounts', 'categories', 'budget'));
    }

    public function storeExpense(Request $request)
    {
        $request->validate([
            'cash_account_id' => 'required|integer',
            'finance_category_id' => 'required|integer',
            'total_amount' => 'required|numeric|min:1',
            'cash_amount' => 'nullable|numeric|min:0',
            'description' => 'required|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        $totalAmount = (int) $request->total_amount;
        $cashAmount = (int) ($request->cash_amount ?? $totalAmount);
        $debtAmount = max(0, $totalAmount - $cashAmount);

        // Catat bagian cash sebagai expense
        $id = null;
        if ($cashAmount > 0) {
            try {
                $id = $this->finance->recordExpense([
                    'cash_account_id' => $request->cash_account_id,
                    'finance_category_id' => $request->finance_category_id,
                    'amount' => $cashAmount,
                    'description' => $request->description,
                    'transaction_date' => $request->transaction_date,
                ]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        // Catat bagian hutang
        if ($debtAmount > 0) {
            $supplierName = $request->supplier_name ?: $request->description;
            $this->finance->createDebt([
                'supplier_name' => $supplierName,
                'amount' => $debtAmount,
                'description' => $request->description,
                'debt_date' => $request->transaction_date,
                'due_date' => $request->due_date,
            ]);
        }

        $this->finance->logAudit('create', 'expense', $id, null, [
            'total' => $totalAmount, 'cash' => $cashAmount, 'debt' => $debtAmount, 'description' => $request->description,
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function budgetRealization(Request $request)
    {
        $month = $request->month ?? date('Y-m');
        $budget = $this->finance->getBudgetRealization($month);
        if ($request->wantsJson()) return response()->json($budget);
        return view('admin.finance.budget', compact('budget', 'month'));
    }

    // ─── Reports ────────────────────────────────────────────────

    public function reportMonthly(Request $request)
    {
        $month = $request->month ?? date('Y-m');
        $report = $this->finance->getMonthlyReport($month);
        if ($request->wantsJson()) return response()->json($report);
        return view('admin.finance.report_monthly', compact('report', 'month'));
    }

    public function reportBalance(Request $request)
    {
        $accounts = $this->finance->getAccountBalanceReport($request->month);
        return view('admin.finance.report_balance', compact('accounts'));
    }

    public function exportReport(Request $request)
    {
        $month = $request->month ?? date('Y-m');
        $report = $this->finance->getMonthlyReport($month);

        // CSV export
        $filename = "laporan-keuangan-{$month}.csv";
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($report) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Tanggal', 'Penjualan Tunai', 'Penjualan QRIS', 'Total Pendapatan', 'Total Pengeluaran', 'Pendapatan Bersih', 'Jumlah Shift']);
            foreach ($report['daily'] as $row) {
                fputcsv($file, [$row['tanggal'], $row['penjualan_tunai'], $row['penjualan_qris'], $row['total_pendapatan'], $row['total_pengeluaran'], $row['pendapatan_bersih'], $row['jumlah_shift']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
