<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Approve Penarikan Gaji</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1f2937; min-height: 100vh; padding: 16px; }
        .wrap { max-width: 480px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); margin-bottom: 16px; }
        h1 { font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .sub { font-size: 13px; color: #64748b; margin-bottom: 16px; }
        .amount-card { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 14px; padding: 20px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(245,158,11,0.3); }
        .amount-label { font-size: 13px; opacity: 0.9; text-transform: uppercase; font-weight: 600; }
        .amount-value { font-size: 30px; font-weight: 700; margin-top: 4px; }
        .amount-meta { font-size: 13px; opacity: 0.85; margin-top: 6px; }
        .info-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; font-weight: 500; }
        .info-value { color: #1f2937; font-weight: 600; text-align: right; }
        .btn { width: 100%; padding: 14px; border-radius: 10px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; transition: transform 0.1s, box-shadow 0.1s; }
        .btn:active { transform: translateY(1px); }
        .btn--approve { background: #10b981; color: #fff; margin-bottom: 10px; }
        .btn--approve:hover { box-shadow: 0 4px 12px rgba(16,185,129,0.4); }
        .btn--reject { background: #ef4444; color: #fff; }
        .btn--reject:hover { box-shadow: 0 4px 12px rgba(239,68,68,0.4); }
        .reject-form { display: none; margin-top: 12px; padding: 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; }
        .reject-form.open { display: block; }
        .reason-input { width: 100%; padding: 10px 12px; border: 1px solid #fca5a5; border-radius: 8px; font-size: 14px; margin-bottom: 10px; }
        .alert { padding: 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
        .alert--success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
        .alert--error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert--warn { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
        .footer { font-size: 11px; color: #94a3b8; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        @if(session('success'))
            <div class="alert alert--success">✓ {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert--error">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <div class="card">
            <h1>💸 Approval Penarikan Gaji</h1>
            <p class="sub">Akses langsung tanpa login. Token sekali pakai.</p>

            @if(! $verifyOk)
                <div class="alert alert--warn" style="margin-bottom: 0;">
                    ⚠️ {{ $message }}
                </div>
                @if($tx && in_array(($tx['status'] ?? ''), ['approved','rejected']))
                    <div style="margin-top: 14px; padding: 14px; background: #f8fafc; border-radius: 10px;">
                        <div class="info-row"><span class="info-label">Karyawan</span><span class="info-value">{{ $tx['waiter_name'] ?? '-' }}</span></div>
                        <div class="info-row"><span class="info-label">Nominal</span><span class="info-value">Rp {{ number_format((int)($tx['amount'] ?? 0), 0, ',', '.') }}</span></div>
                        <div class="info-row"><span class="info-label">Status</span><span class="info-value">{{ ucfirst($tx['status'] ?? '-') }}</span></div>
                        @if(! empty($tx['processed_at']))
                            <div class="info-row"><span class="info-label">Diproses</span><span class="info-value">{{ \Carbon\Carbon::createFromTimestamp((int)$tx['processed_at'])->translatedFormat('d M Y H:i') }}</span></div>
                        @endif
                        @if(! empty($tx['reject_reason']))
                            <div class="info-row"><span class="info-label">Alasan reject</span><span class="info-value">{{ $tx['reject_reason'] }}</span></div>
                        @endif
                    </div>
                @endif
            @else
                <div class="amount-card">
                    <div class="amount-label">Nominal Penarikan</div>
                    <div class="amount-value">Rp {{ number_format((int)($tx['amount'] ?? 0), 0, ',', '.') }}</div>
                    <div class="amount-meta">{{ $tx['waiter_name'] ?? '-' }}</div>
                </div>

                <div style="margin-bottom: 16px;">
                    <div class="info-row"><span class="info-label">Bank</span><span class="info-value">{{ $tx['bank_name'] ?? '-' }}</span></div>
                    <div class="info-row"><span class="info-label">No Rekening</span><span class="info-value">{{ $tx['bank_account_number'] ?? '-' }}</span></div>
                    <div class="info-row"><span class="info-label">Atas Nama</span><span class="info-value">{{ $tx['bank_account_holder'] ?? '-' }}</span></div>
                    @if(! empty($tx['note']))
                        <div class="info-row"><span class="info-label">Catatan</span><span class="info-value">{{ $tx['note'] }}</span></div>
                    @endif
                    <div class="info-row"><span class="info-label">Diajukan</span><span class="info-value">{{ \Carbon\Carbon::createFromTimestamp((int)($tx['created_at'] ?? time()))->translatedFormat('d M Y H:i') }}</span></div>
                </div>

                <form method="POST" action="{{ route('public.payroll.approve', ['txId' => $txId, 'token' => $token]) }}" onsubmit="return confirm('Yakin approve penarikan ini? Saldo karyawan akan dipotong.');">
                    @csrf
                    <button type="submit" class="btn btn--approve">✓ Approve Penarikan</button>
                </form>

                <button type="button" class="btn btn--reject" id="toggleRejectBtn">✗ Reject</button>

                <div class="reject-form" id="rejectForm">
                    <form method="POST" action="{{ route('public.payroll.reject', ['txId' => $txId, 'token' => $token]) }}">
                        @csrf
                        <input type="text" name="reason" maxlength="200" class="reason-input" placeholder="Alasan reject (opsional)">
                        <button type="submit" class="btn btn--reject" onclick="return confirm('Yakin reject?');">Konfirmasi Reject</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="footer">Token sekali pakai. Setelah diproses, link ini tidak bisa dipakai lagi.</div>
    </div>

    <script>
        document.getElementById('toggleRejectBtn')?.addEventListener('click', function () {
            document.getElementById('rejectForm').classList.toggle('open');
        });
    </script>
</body>
</html>
