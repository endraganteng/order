<?php

namespace App\Http\Controllers;

use App\Services\PayrollService;
use Illuminate\Http\Request;

class PublicPayrollApprovalController extends Controller
{
    protected PayrollService $payroll;

    public function __construct(PayrollService $payroll)
    {
        $this->payroll = $payroll;
    }

    /**
     * Public review page (mobile-friendly). Token-based, no login.
     */
    public function review(string $txId, string $token)
    {
        $verify = $this->payroll->verifyApprovalToken($txId, $token);
        $tx = $verify['tx'] ?? null;

        return view('public.payroll_approve', [
            'verifyOk' => (bool) $verify['ok'],
            'message'  => (string) $verify['message'],
            'tx'       => $tx,
            'txId'     => $txId,
            'token'    => $token,
        ]);
    }

    public function approve(string $txId, string $token, Request $request)
    {
        $result = $this->payroll->approveByToken($txId, $token);
        if (! ($result['success'] ?? false)) {
            return redirect()->route('public.payroll.review', ['txId' => $txId, 'token' => $token])
                ->withErrors(['action' => $result['message'] ?? 'Gagal approve']);
        }
        return redirect()->route('public.payroll.review', ['txId' => $txId, 'token' => $token])
            ->with('success', 'Penarikan disetujui. Saldo karyawan dipotong dan notifikasi WA dikirim.');
    }

    public function reject(string $txId, string $token, Request $request)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:200',
        ]);
        $result = $this->payroll->rejectByToken($txId, $token, trim((string) ($data['reason'] ?? '')));
        if (! ($result['success'] ?? false)) {
            return redirect()->route('public.payroll.review', ['txId' => $txId, 'token' => $token])
                ->withErrors(['action' => $result['message'] ?? 'Gagal reject']);
        }
        return redirect()->route('public.payroll.review', ['txId' => $txId, 'token' => $token])
            ->with('success', 'Penarikan ditolak. Saldo karyawan tidak berubah dan notifikasi WA dikirim.');
    }
}
