<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\VerifiesSupervisorPin;
use App\Services\FirebaseService;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    use VerifiesSupervisorPin;
    protected PayrollService $payroll;
    protected FirebaseService $firebase;

    public function __construct(PayrollService $payroll, FirebaseService $firebase)
    {
        $this->payroll = $payroll;
        $this->firebase = $firebase;
    }

    /**
     * Dashboard: list semua waiter, status payroll, saldo current.
     */
    public function index()
    {
        $config = $this->payroll->getConfig();
        $waiters = $this->firebase->getAllowedEmails();

        $rows = [];
        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '') continue;

            $rows[] = [
                'id'                  => $waiterId,
                'name'                => (string) ($waiter['name'] ?? ''),
                'email'               => (string) ($waiter['email'] ?? ''),
                'role'                => (string) ($waiter['waiter_role'] ?? 'pelayan'),
                'is_active'           => (bool) ($waiter['is_active'] ?? true),
                'payroll_enabled'     => (bool) ($waiter['payroll_enabled'] ?? false),
                'monthly_salary'      => (int) ($waiter['monthly_salary'] ?? 0),
                'payday'              => (int) ($waiter['payday'] ?? 0),
                'bank_name'           => (string) ($waiter['bank_name'] ?? ''),
                'bank_account_number' => (string) ($waiter['bank_account_number'] ?? ''),
                'bank_account_holder' => (string) ($waiter['bank_account_holder'] ?? ''),
                'balance'             => $this->payroll->getBalance($waiterId),
            ];
        }
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        $totalBalance = array_sum(array_column($rows, 'balance'));
        $enabledCount = count(array_filter($rows, fn ($r) => $r['payroll_enabled']));

        return view('admin.payroll.index', compact('rows', 'config', 'totalBalance', 'enabledCount'));
    }

    public function show(string $waiterId)
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter) {
            return redirect()->route('admin.payroll.index')->withErrors(['waiter' => 'Karyawan tidak ditemukan']);
        }

        $settings = $this->payroll->getWaiterSettings($waiterId);
        $balance = $this->payroll->getBalance($waiterId);
        $transactions = $this->payroll->listTransactionsByWaiter($waiterId, 200);

        return view('admin.payroll.show', compact('waiter', 'settings', 'balance', 'transactions'));
    }

    public function updateConfig(Request $request)
    {
        $data = $request->validate([
            'supervisor_phone' => 'nullable|string|max:30',
            'public_base_url'  => 'nullable|url|max:200',
            'is_active'        => 'nullable|boolean',
        ]);

        $this->payroll->updateConfig([
            'supervisor_phone' => trim((string) ($data['supervisor_phone'] ?? '')),
            'public_base_url'  => rtrim(trim((string) ($data['public_base_url'] ?? '')), '/'),
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('admin.payroll.index')->with('success', 'Konfigurasi payroll berhasil disimpan.');
    }

    public function updateSettings(string $waiterId, Request $request)
    {
        $data = $request->validate([
            'payroll_enabled'     => 'nullable|boolean',
            'monthly_salary'      => 'nullable|integer|min:0|max:999999999',
            'payday'              => 'nullable|integer|min:0|max:28',
        ]);

        // Bank account fields are managed by waiter themselves via /waiter/payroll.
        $this->payroll->updateWaiterSettings($waiterId, [
            'payroll_enabled'     => (bool) ($data['payroll_enabled'] ?? false),
            'monthly_salary'      => (int) ($data['monthly_salary'] ?? 0),
            'payday'              => (int) ($data['payday'] ?? 0),
        ]);

        $this->firebase->logAuditAction('payroll_settings_update', 'waiter', $waiterId, [
            'patch' => $data,
        ]);

        return back()->with('success', 'Pengaturan payroll karyawan diperbarui.');
    }

    public function manualCredit(string $waiterId, Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:1|max:999999999',
            'note'   => 'nullable|string|max:200',
        ]);

        $admin = (string) (session('admin_name') ?? 'Supervisor');
        $result = $this->payroll->manualCredit(
            $waiterId,
            (int) $data['amount'],
            trim((string) ($data['note'] ?? '')),
            $admin
        );

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['amount' => $result['message'] ?? 'Gagal credit'])->withInput();
        }

        $this->firebase->logAuditAction('payroll_manual_credit', 'waiter', $waiterId, [
            'amount' => (int) $data['amount'],
            'note' => $data['note'] ?? '',
            'tx_id' => $result['tx_id'] ?? '',
        ]);

        return back()->with('success', 'Saldo berhasil ditambahkan: Rp ' . number_format((int) $data['amount'], 0, ',', '.'));
    }

    public function withdrawalsIndex()
    {
        $pending = $this->payroll->listPendingWithdrawals();
        return view('admin.payroll.withdrawals', compact('pending'));
    }

    public function approveWithdrawal(string $txId, Request $request)
    {
        if (! $this->verifySupervisorPin($request->input('supervisor_pin'))) {
            return back()->withErrors(['withdrawal' => 'PIN supervisor salah.']);
        }

        $admin = (string) (session('admin_name') ?? 'Supervisor');
        $result = $this->payroll->approveWithdrawal((int) $txId, $admin);

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['withdrawal' => $result['message'] ?? 'Gagal approve']);
        }

        $this->firebase->logAuditAction('payroll_withdrawal_approve', 'transaction', $txId, [
            'balance_after' => $result['balance_after'] ?? null,
        ]);

        return back()->with('success', 'Penarikan disetujui. Saldo karyawan dipotong.');
    }

    public function rejectWithdrawal(string $txId, Request $request)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:200',
        ]);

        $admin = (string) (session('admin_name') ?? 'Supervisor');
        $result = $this->payroll->rejectWithdrawal((int) $txId, trim((string) ($data['reason'] ?? '')), $admin);

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['withdrawal' => $result['message'] ?? 'Gagal reject']);
        }

        $this->firebase->logAuditAction('payroll_withdrawal_reject', 'transaction', $txId, [
            'reason' => $data['reason'] ?? '',
        ]);

        return back()->with('success', 'Penarikan ditolak. Saldo karyawan tidak berubah.');
    }

    /**
     * Manual trigger: jalankan auto-credit gaji bulanan sekarang juga.
     * Idempotent — kalau sudah di-credit bulan ini, tidak duplikat.
     */
    public function runSalaryCreditNow(Request $request)
    {
        $catchup = (int) $request->input('catchup', 7);
        if ($catchup < 0 || $catchup > 30) {
            $catchup = 7;
        }
        $result = $this->payroll->runDailySalaryCredit($catchup);

        $this->firebase->logAuditAction('payroll_manual_run_salary', 'system', null, [
            'catchup_days'   => $catchup,
            'credited'       => (int) ($result['credited'] ?? 0),
            'skipped'        => (int) ($result['skipped'] ?? 0),
            'errors_count'   => count($result['errors'] ?? []),
        ]);

        $msg = sprintf(
            'Trigger gajian selesai. %d karyawan di-credit, %d skip%s.',
            (int) ($result['credited'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            ! empty($result['errors']) ? ', ' . count($result['errors']) . ' error' : ''
        );

        return back()->with('success', $msg);
    }

    /**
     * Trigger gajian untuk user tertentu yang dipilih.
     */
    public function runSalaryCreditSelected(Request $request)
    {
        $data = $request->validate([
            'waiter_ids'   => 'required|array|min:1',
            'waiter_ids.*' => 'required|string',
            'catchup'      => 'nullable|integer|min:0|max:30',
        ]);

        $catchup = (int) ($data['catchup'] ?? 7);
        $result = $this->payroll->creditSalaryForWaiters($data['waiter_ids'], $catchup);

        $this->firebase->logAuditAction('payroll_manual_run_salary_selected', 'system', null, [
            'waiter_ids'   => $data['waiter_ids'],
            'catchup_days' => $catchup,
            'credited'     => (int) ($result['credited'] ?? 0),
            'skipped'      => (int) ($result['skipped'] ?? 0),
            'errors_count' => count($result['errors'] ?? []),
        ]);

        $msg = sprintf(
            'Trigger gajian selesai. %d karyawan di-credit, %d skip%s.',
            (int) ($result['credited'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            ! empty($result['errors']) ? ', ' . count($result['errors']) . ' error' : ''
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg] + $result);
        }

        return back()->with('success', $msg);
    }
}
