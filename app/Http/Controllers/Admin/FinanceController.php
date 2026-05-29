<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\VerifiesSupervisorPin;
use App\Services\FinanceService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    use VerifiesSupervisorPin;

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
        $debtSummary = $this->finance->getDebtSummary();

        return view('admin.finance.dashboard', compact('today', 'month', 'accounts', 'lastSync', 'pendingTransfers', 'categories', 'debtSummary'));
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
        $this->finance->saveSettings($request->only([
            'api_domain', 'api_token', 'auto_sync_enabled', 'auto_sync_time', 'sync_mode', 'sync_data_target',
            'ai_provider', 'ai_model', 'ai_api_key', 'ai_gemini_key', 'ai_base_url',
            'ai_temperature', 'ai_max_tokens', 'ai_timeout',
        ]));
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

    public function toggleCashAccount(int $id, Request $request)
    {
        if (! $this->verifySupervisorPin($request->supervisor_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN supervisor salah.', 'pin_required' => true], 403);
        }
        $this->finance->toggleCashAccount($id);
        $this->finance->logAudit('toggle', 'cash_account', $id);
        return response()->json(['success' => true]);
    }

    public function resetCashAccount(int $id, Request $request)
    {
        if (! $this->verifySupervisorPin($request->supervisor_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN supervisor salah.', 'pin_required' => true], 403);
        }
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
        $transfer = DB::table('cash_transfers')->find($id);
        $result = $this->finance->approveTransfer($id, session('admin_name', 'admin'));
        if (!$result) return response()->json(['success' => false, 'message' => 'Saldo tidak cukup atau status tidak valid.'], 422);
        $this->finance->logAudit('approve', 'transfer', $id);

        // Notifikasi ke Telegram Finance
        if ($transfer) {
            $from = DB::table('cash_accounts')->where('id', $transfer->from_account_id)->value('name');
            $to = DB::table('cash_accounts')->where('id', $transfer->to_account_id)->value('name');
            $fmt = number_format($transfer->amount, 0, ',', '.');
            $msg = "💸 *TRANSFER DISETUJUI*\n━━━━━━━━━━━━━━━━━━━━━\n📤 Dari: {$from}\n📥 Ke: {$to}\n💰 Jumlah: Rp {$fmt}\n👤 Oleh: " . session('admin_name', 'admin') . "\n📅 " . now()->format('d/m/Y H:i');
            if ($transfer->notes) $msg .= "\n📝 {$transfer->notes}";
            try { app(TelegramService::class)->sendToFinance($msg); } catch (\Exception $e) {}
        }

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
        $accountId = $request->integer('account_id') ?: null;
        $from = $request->from;
        $to = $request->to;
        $mutations = $this->finance->getMutations($accountId, $from, $to, $request->integer('limit', 50), $request->integer('offset', 0));
        $accounts = $this->finance->getCashAccounts();

        if ($request->wantsJson()) {
            // Add server-side summary for the full period (not just current page)
            $q = \Illuminate\Support\Facades\DB::table('cash_mutations');
            if ($accountId) $q->where('cash_account_id', $accountId);
            if ($from) $q->where('transaction_date', '>=', $from);
            if ($to) $q->where('transaction_date', '<=', $to);

            $mutations['sum_income'] = (int) (clone $q)->whereIn('type', ['income', 'transfer_in'])->sum('amount');
            $mutations['sum_expense'] = (int) (clone $q)->whereIn('type', ['expense', 'transfer_out'])->sum('amount');

            return response()->json($mutations);
        }

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

    public function updateDebt(Request $request, int $id)
    {
        $request->validate([
            'supplier_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        DB::table('finance_debts')->where('id', $id)->update([
            'supplier_name' => $request->supplier_name,
            'amount' => (int) $request->amount,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'updated_at' => now(),
        ]);

        $this->finance->logAudit('update', 'debt', $id);
        return response()->json(['success' => true]);
    }

    public function deleteDebt(int $id)
    {
        $debt = $this->finance->getDebt($id);
        if ($debt && (int) $debt['paid'] > 0) {
            return response()->json(['success' => false, 'message' => 'Hutang yang sudah ada pembayaran tidak bisa dihapus.'], 422);
        }

        DB::table('finance_debts')->where('id', $id)->delete();
        $this->finance->logAudit('delete', 'debt', $id);
        return response()->json(['success' => true]);
    }

    // ─── Pengeluaran Manual ────────────────────────────────────

    public function deposit(Request $request)
    {
        $request->validate(['cash_account_id' => 'required|integer', 'amount' => 'required|numeric|min:1', 'description' => 'required|string|max:255']);

        $txDate = $request->transaction_date ?? date('Y-m-d');
        if ($this->finance->isMonthClosed(substr($txDate, 0, 7))) {
            return response()->json(['success' => false, 'message' => 'Bulan ini sudah ditutup. Buka kembali untuk melakukan perubahan.'], 422);
        }

        $accountId = $request->integer('cash_account_id');
        $amount = (int) $request->amount;

        DB::table('cash_accounts')->where('id', $accountId)->increment('balance', $amount);
        $newBalance = (int) DB::table('cash_accounts')->where('id', $accountId)->value('balance');

        DB::table('cash_mutations')->insert([
            'cash_account_id' => $accountId,
            'type' => 'income',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'description' => $request->description,
            'reference_type' => 'manual_deposit',
            'transaction_date' => $request->transaction_date ?? date('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->finance->logAudit('deposit', 'cash_account', $accountId, null, ['amount' => $amount, 'description' => $request->description]);

        // Notifikasi ke Telegram Finance
        $accName = DB::table('cash_accounts')->where('id', $accountId)->value('name');
        $fmt = number_format($amount, 0, ',', '.');
        $msg = "🟢 *SETORAN/DEPOSIT*\n━━━━━━━━━━━━━━━━━━━━━\n📝 {$request->description}\n💰 Rp {$fmt}\n🏦 Akun: {$accName}\n👤 Oleh: " . session('admin_name', 'admin') . "\n📅 " . now()->format('d/m/Y H:i');
        try { app(TelegramService::class)->sendToFinance($msg); } catch (\Exception $e) {}

        return response()->json(['success' => true]);
    }

    public function correctBalance(Request $request)
    {
        $request->validate([
            'cash_account_id' => 'required|integer',
            'actual_balance' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'supervisor_pin' => 'nullable|string',
        ]);

        if ($this->finance->isMonthClosed(date('Y-m'))) {
            return response()->json(['success' => false, 'message' => 'Bulan ini sudah ditutup. Buka kembali untuk melakukan perubahan.'], 422);
        }

        if (! $this->verifySupervisorPin($request->supervisor_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN supervisor salah.', 'pin_required' => true], 403);
        }

        $accountId = $request->integer('cash_account_id');
        $actualBalance = (int) $request->actual_balance;
        $currentBalance = (int) DB::table('cash_accounts')->where('id', $accountId)->value('balance');
        $diff = $actualBalance - $currentBalance;

        if ($diff === 0) {
            return response()->json(['success' => true, 'message' => 'Saldo sudah sesuai, tidak ada koreksi.']);
        }

        DB::table('cash_accounts')->where('id', $accountId)->update(['balance' => $actualBalance, 'updated_at' => now()]);

        $type = $diff > 0 ? 'income' : 'expense';
        $desc = $request->description ?: ('Koreksi saldo: ' . ($diff > 0 ? '+' : '') . 'Rp ' . number_format($diff, 0, ',', '.'));

        DB::table('cash_mutations')->insert([
            'cash_account_id' => $accountId,
            'type' => $type,
            'amount' => abs($diff),
            'balance_after' => $actualBalance,
            'description' => $desc,
            'reference_type' => 'correction',
            'transaction_date' => date('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->finance->logAudit('correction', 'cash_account', $accountId, ['balance' => $currentBalance], ['balance' => $actualBalance, 'diff' => $diff]);

        // Notifikasi ke Telegram Finance
        $accName = DB::table('cash_accounts')->where('id', $accountId)->value('name');
        $fmtOld = number_format($currentBalance, 0, ',', '.');
        $fmtNew = number_format($actualBalance, 0, ',', '.');
        $fmtDiff = ($diff > 0 ? '+' : '') . number_format($diff, 0, ',', '.');
        $msg = "⚖️ *KOREKSI SALDO*\n━━━━━━━━━━━━━━━━━━━━━\n🏦 Akun: {$accName}\n📊 Sebelum: Rp {$fmtOld}\n📊 Sesudah: Rp {$fmtNew}\n📈 Selisih: Rp {$fmtDiff}\n👤 Oleh: " . session('admin_name', 'admin') . "\n📅 " . now()->format('d/m/Y H:i');
        if ($request->description) $msg .= "\n📝 {$request->description}";
        try { app(TelegramService::class)->sendToFinance($msg); } catch (\Exception $e) {}

        return response()->json(['success' => true, 'old_balance' => $currentBalance, 'new_balance' => $actualBalance, 'diff' => $diff]);
    }

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

        if ($this->finance->isMonthClosed(substr($request->transaction_date, 0, 7))) {
            return response()->json(['success' => false, 'message' => 'Bulan ini sudah ditutup. Buka kembali untuk melakukan perubahan.'], 422);
        }

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
                'finance_category_id' => $request->finance_category_id,
            ]);
        }

        $this->finance->logAudit('create', 'expense', $id, null, [
            'total' => $totalAmount, 'cash' => $cashAmount, 'debt' => $debtAmount, 'description' => $request->description,
        ]);

        // Notifikasi ke Telegram Finance
        $accName = DB::table('cash_accounts')->where('id', $request->cash_account_id)->value('name');
        $catName = DB::table('finance_categories')->where('id', $request->finance_category_id)->value('name');
        $fmt = number_format($totalAmount, 0, ',', '.');
        $msg = "🔴 *PENGELUARAN*\n━━━━━━━━━━━━━━━━━━━━━\n📝 {$request->description}\n💰 Rp {$fmt}\n🏦 Akun: {$accName}\n📂 Kategori: {$catName}\n👤 Oleh: " . session('admin_name', 'admin') . "\n📅 {$request->transaction_date}";
        if ($debtAmount > 0) $msg .= "\n⚠️ Hutang: Rp " . number_format($debtAmount, 0, ',', '.');
        try { app(TelegramService::class)->sendToFinance($msg); } catch (\Exception $e) {}

        return response()->json(['success' => true, 'id' => $id]);
    }

    /**
     * Check budget status for a category in current month.
     */
    public function checkBudget(Request $request)
    {
        $categoryId = $request->integer('finance_category_id');
        $month = date('Y-m');
        $budget = $this->finance->getBudgetRealization($month);

        $match = collect($budget['allocations'] ?? [])->firstWhere('category_id', $categoryId);
        if (! $match) {
            return response()->json(['has_budget' => false]);
        }

        return response()->json([
            'has_budget' => true,
            'category_name' => $match['category_name'],
            'budget' => $match['budget'],
            'realisasi' => $match['realisasi'],
            'sisa' => $match['sisa'],
            'pct_used' => $match['pct_used'],
        ]);
    }

    public function budgetRealization(Request $request)
    {
        // Mode: 'month' (default) atau 'range' untuk onboarding mid-period.
        $mode = $request->mode === 'range' ? 'range' : 'month';
        $month = $request->month ?? date('Y-m');

        if ($mode === 'range') {
            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);
            $from = $request->from;
            $to = $request->to;
            $budget = $this->finance->getBudgetRealizationRange($from, $to);
        } else {
            $from = $month . '-01';
            $to = date('Y-m-t', strtotime($from));
            $budget = $this->finance->getBudgetRealization($month);
        }

        if ($request->wantsJson()) return response()->json($budget);
        return view('admin.finance.budget', compact('budget', 'month', 'mode', 'from', 'to'));
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

    // ─── Tutup Buku ────────────────────────────────────────────

    public function tutupBuku(Request $request)
    {
        $closings = $this->finance->getClosings();
        if ($request->wantsJson()) return response()->json($closings);
        return view('admin.finance.tutup_buku', compact('closings'));
    }

    public function closeMonth(Request $request)
    {
        $request->validate(['month' => 'required|string|size:7', 'notes' => 'nullable|string|max:255']);

        if (! $this->verifySupervisorPin($request->supervisor_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN supervisor salah.', 'pin_required' => true], 403);
        }

        $result = $this->finance->closeMonth($request->month, session('admin_name', 'Admin'), $request->notes);
        return response()->json($result);
    }

    public function reopenMonth(Request $request)
    {
        $request->validate(['month' => 'required|string|size:7']);

        if (! $this->verifySupervisorPin($request->supervisor_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN supervisor salah.', 'pin_required' => true], 403);
        }

        $result = $this->finance->reopenMonth($request->month, session('admin_name', 'Admin'));
        return response()->json($result);
    }

    // ─── Laporan Laba Rugi ─────────────────────────────────────

    public function labaRugi(Request $request)
    {
        $month = $request->month ?? date('Y-m');
        $report = $this->finance->getLabaRugi($month);
        if ($request->wantsJson()) return response()->json($report);
        return view('admin.finance.laba_rugi', compact('report', 'month'));
    }
}
