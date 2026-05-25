<?php

namespace App\Http\Controllers;

use App\Services\PayrollService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class WaiterPayrollController extends Controller
{
    protected PayrollService $payroll;
    protected FirebaseService $firebase;

    public function __construct(PayrollService $payroll, FirebaseService $firebase)
    {
        $this->payroll = $payroll;
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');
        if ($waiterId === '') {
            return redirect()->route('waiter.login');
        }

        $waiter = $this->firebase->getWaiterById($waiterId) ?: [];
        $settings = $this->payroll->getWaiterSettings($waiterId);
        $balance = $this->payroll->getBalance($waiterId);
        $transactions = $this->payroll->listTransactionsByWaiter($waiterId, 50);

        // Compute next payday display.
        $nextPayday = null;
        if ($settings['payday'] >= 1 && $settings['payday'] <= 28) {
            $today = new \DateTimeImmutable(date('Y-m-d'));
            $thisMonthPayday = $today->setDate((int)$today->format('Y'), (int)$today->format('m'), $settings['payday']);
            if ($thisMonthPayday >= $today) {
                $nextPayday = $thisMonthPayday->format('Y-m-d');
            } else {
                $next = $thisMonthPayday->modify('+1 month');
                $nextPayday = $next->format('Y-m-d');
            }
        }

        // Kasbon data (jika fitur diaktifkan)
        $kasbonData = null;
        if (! empty($waiter['kasbon_enabled'])) {
            $kasbonService = app(\App\Services\KasbonService::class);
            $kasbonData = [
                'items' => $kasbonService->listByWaiter($waiterId),
                'limit_info' => $kasbonService->calculateAvailableLimit($waiterId),
            ];
        }

        return view('waiter.payroll', compact(
            'waiterId', 'waiterName', 'waiter', 'settings', 'balance', 'transactions', 'nextPayday', 'kasbonData'
        ));
    }

    public function requestWithdrawal(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        if ($waiterId === '') {
            return response()->json(['success' => false, 'message' => 'Sesi habis. Login ulang.'], 401);
        }

        $data = $request->validate([
            'amount' => 'required|integer|min:1|max:999999999',
            'note'   => 'nullable|string|max:200',
        ]);

        $result = $this->payroll->requestWithdrawal(
            $waiterId,
            (int) $data['amount'],
            trim((string) ($data['note'] ?? ''))
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        }

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['amount' => $result['message'] ?? 'Gagal'])->withInput();
        }

        return back()->with('success', $result['message'] ?? 'Permintaan dikirim');
    }

    /**
     * Karyawan update data rekening sendiri.
     * Tidak boleh ubah payroll_enabled / monthly_salary / payday — itu domain supervisor.
     */
    public function updateBankAccount(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        if ($waiterId === '') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Sesi habis. Login ulang.'], 401);
            }
            return redirect()->route('waiter.login');
        }

        $data = $request->validate([
            'bank_name'           => 'required|string|max:60',
            'bank_account_number' => 'required|string|max:30',
            'bank_account_holder' => 'required|string|max:60',
        ]);

        $this->payroll->updateWaiterSettings($waiterId, [
            'bank_name'           => trim((string) $data['bank_name']),
            'bank_account_number' => trim((string) $data['bank_account_number']),
            'bank_account_holder' => trim((string) $data['bank_account_holder']),
        ]);

        $this->firebase->logAuditAction('payroll_bank_self_update', 'waiter', $waiterId, [
            'bank_name' => $data['bank_name'],
            'bank_account_number' => $data['bank_account_number'],
            'bank_account_holder' => $data['bank_account_holder'],
            'self' => true,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            $settings = $this->payroll->getWaiterSettings($waiterId);
            return response()->json([
                'success' => true,
                'message' => 'Data rekening berhasil diperbarui.',
                'settings' => $settings,
            ]);
        }

        return back()->with('success', 'Data rekening berhasil diperbarui.');
    }

    /**
     * JSON snapshot untuk polling realtime di portal karyawan.
     * Return: balance, transactions (50 terakhir), settings.
     */
    public function apiSnapshot(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        if ($waiterId === '') {
            return response()->json(['success' => false, 'message' => 'Sesi habis.'], 401);
        }

        $settings = $this->payroll->getWaiterSettings($waiterId);
        $balance = $this->payroll->getBalance($waiterId);
        $transactions = $this->payroll->listTransactionsByWaiter($waiterId, 50);

        return response()->json([
            'success'      => true,
            'balance'      => $balance,
            'settings'     => $settings,
            'transactions' => $transactions,
            'server_time'  => time(),
        ]);
    }
}
