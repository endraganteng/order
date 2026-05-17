<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>💰 Gaji Saya - {{ $waiterName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; color: #333; min-height: 100vh; padding-bottom: 2rem; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; padding: 1rem 1.25rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3); }
        .header-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .back-link { color: #fff; text-decoration: none; font-size: 14px; opacity: 0.9; }
        .back-link:hover { opacity: 1; }
        .header h1 { font-size: 22px; font-weight: 700; }
        .container { max-width: 720px; margin: 0 auto; padding: 16px; }
        .balance-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 14px; padding: 24px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25); }
        .balance-label { font-size: 13px; opacity: 0.9; text-transform: uppercase; font-weight: 600; }
        .balance-value { font-size: 36px; font-weight: 700; margin-top: 6px; line-height: 1.2; }
        .balance-info { font-size: 13px; opacity: 0.85; margin-top: 8px; }
        .card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); }
        .section-title { font-size: 15px; font-weight: 600; color: #1f2937; margin: 0 0 10px; }
        .input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        .btn { width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; }
        .btn--primary { background: #f59e0b; color: #fff; }
        .btn--primary:disabled { background: #94a3b8; cursor: not-allowed; }
        .bank-info { background: #f8fafc; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #475569; margin-bottom: 12px; }
        .bank-info--empty { background: #fef2f2; color: #991b1b; }
        .tx-list { display: flex; flex-direction: column; gap: 8px; }
        .tx-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border-radius: 8px; gap: 8px; }
        .tx-info { flex: 1; min-width: 0; }
        .tx-type { font-size: 13px; font-weight: 600; color: #1f2937; }
        .tx-date { font-size: 11px; color: #64748b; }
        .tx-note { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        .tx-amount { font-size: 14px; font-weight: 700; white-space: nowrap; }
        .tx-amount--in { color: #059669; }
        .tx-amount--out { color: #dc2626; }
        .tx-status { font-size: 10px; padding: 2px 6px; border-radius: 999px; margin-left: 4px; font-weight: 600; }
        .tx-status--pending { background: #fef3c7; color: #92400e; }
        .tx-status--rejected { background: #fee2e2; color: #991b1b; }
        .empty { padding: 24px; text-align: center; color: #94a3b8; }
        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .flash--success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
        .flash--error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .disabled-banner { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; padding: 16px; border-radius: 12px; text-align: center; margin-bottom: 16px; }
        .label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #475569; }
        .hint { font-size: 11px; color: #64748b; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <a href="{{ url('/waiter/tasks') }}" class="back-link">← Kembali</a>
        </div>
        <h1>💰 Gaji Saya</h1>
    </div>

    <div class="container">
        @if(session('success'))
            <div class="flash flash--success">✓ {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="flash flash--error">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        @if(! $settings['payroll_enabled'])
            <div class="disabled-banner">
                <strong>⚠️ Fitur Belum Aktif</strong><br>
                <span style="font-size: 13px;">Akun payroll Anda belum diaktifkan oleh supervisor. Hubungi supervisor untuk mengaktifkan fitur ini.</span>
            </div>
        @else
            <div class="balance-card">
                <div class="balance-label">Saldo Anda</div>
                <div class="balance-value">Rp {{ number_format($balance, 0, ',', '.') }}</div>
                <div class="balance-info">
                    @if($nextPayday)
                        📅 Gajian berikutnya: {{ \Carbon\Carbon::parse($nextPayday)->translatedFormat('d M Y') }}
                    @else
                        📅 Tanggal gajian belum diatur. Hubungi supervisor.
                    @endif
                </div>
                @if($settings['monthly_salary'] > 0)
                    <div class="balance-info">💼 Gaji pokok: Rp {{ number_format($settings['monthly_salary'], 0, ',', '.') }}/bulan</div>
                @endif
            </div>

            <div class="card">
                <h3 class="section-title">🏦 Rekening Saya</h3>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 10px;">Atur sendiri rekening tujuan transfer. Pastikan data benar — supervisor akan transfer ke rekening ini saat penarikan disetujui.</p>
                <form method="POST" action="{{ route('waiter.payroll.bank_update') }}">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <label class="label">Bank</label>
                        <input type="text" name="bank_name" required maxlength="60" class="input" value="{{ old('bank_name', $settings['bank_name']) }}" placeholder="BCA / Mandiri / BRI / dst">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label class="label">No Rekening</label>
                        <input type="text" name="bank_account_number" required maxlength="30" class="input" value="{{ old('bank_account_number', $settings['bank_account_number']) }}" placeholder="1234567890" inputmode="numeric">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label class="label">Atas Nama</label>
                        <input type="text" name="bank_account_holder" required maxlength="60" class="input" value="{{ old('bank_account_holder', $settings['bank_account_holder']) }}" placeholder="Nama sesuai buku tabungan">
                    </div>
                    <button type="submit" class="btn" style="background: #3b82f6; color: #fff;">💾 Simpan Rekening</button>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">💸 Tarik Saldo</h3>
                <div class="bank-info {{ ($settings['bank_name'] && $settings['bank_account_number']) ? '' : 'bank-info--empty' }}">
                    @if($settings['bank_name'] && $settings['bank_account_number'])
                        <div><strong>🏦 {{ $settings['bank_name'] }}</strong></div>
                        <div>{{ $settings['bank_account_number'] }} a.n. {{ $settings['bank_account_holder'] }}</div>
                        <div style="font-size: 11px; margin-top: 4px;">Dana akan ditransfer ke rekening ini setelah supervisor approve.</div>
                    @else
                        ⚠️ Lengkapi data rekening di atas dulu sebelum bisa tarik saldo.
                    @endif
                </div>

                @if($balance > 0 && $settings['bank_name'] && $settings['bank_account_number'])
                <form method="POST" action="{{ route('waiter.payroll.withdraw') }}">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <label class="label">Nominal</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #475569; font-weight: 600; pointer-events: none;">Rp</span>
                            <input type="text" id="amountDisplay" inputmode="numeric" autocomplete="off" required class="input rupiah-input" placeholder="0" style="padding-left: 36px;">
                        </div>
                        <input type="hidden" name="amount" id="amountRaw">
                        <p class="hint">Max: Rp {{ number_format($balance, 0, ',', '.') }}</p>
                    </div>
                    <button type="submit" class="btn btn--primary" onclick="return confirm('Yakin ajukan penarikan? Supervisor akan dapat notifikasi WhatsApp.');">
                        💸 Ajukan Penarikan
                    </button>
                </form>
                @endif
            </div>
        @endif

        <div class="card">
            <h3 class="section-title">📋 Riwayat Transaksi</h3>
            @if(empty($transactions))
                <div class="empty">Belum ada transaksi.</div>
            @else
            <div class="tx-list">
                @foreach($transactions as $tx)
                    @php
                        $type = $tx['type'] ?? '';
                        $typeLabel = match($type) {
                            'salary_credit' => '💰 Gaji Pokok',
                            'bonus_credit' => '🎉 Bonus Bulanan',
                            'manual_credit' => '✋ Tambahan Saldo',
                            'withdrawal' => '💸 Penarikan',
                            default => $type,
                        };
                        $isOut = $type === 'withdrawal';
                        $status = $tx['status'] ?? '';
                    @endphp
                    <div class="tx-item">
                        <div class="tx-info">
                            <div class="tx-type">{{ $typeLabel }}
                                @if($status === 'pending')
                                    <span class="tx-status tx-status--pending">Pending</span>
                                @elseif($status === 'rejected')
                                    <span class="tx-status tx-status--rejected">Ditolak</span>
                                @endif
                            </div>
                            <div class="tx-date">{{ \Carbon\Carbon::createFromTimestamp((int)($tx['created_at'] ?? time()))->translatedFormat('d M Y H:i') }}</div>
                            @if(!empty($tx['note']))
                                <div class="tx-note">{{ $tx['note'] }}</div>
                            @endif
                        </div>
                        <div class="tx-amount {{ $isOut ? 'tx-amount--out' : 'tx-amount--in' }}">
                            {{ $isOut ? '-' : '+' }} Rp {{ number_format((int)($tx['amount'] ?? 0), 0, ',', '.') }}
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    <script>
    (function() {
        function formatRupiah(value) {
            var digits = String(value || '').replace(/\D/g, '');
            if (digits === '') return '';
            return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        function attachRupiahInput(displayEl, hiddenEl, maxValue) {
            if (! displayEl || ! hiddenEl) return;
            displayEl.addEventListener('input', function () {
                var raw = displayEl.value.replace(/\D/g, '');
                if (typeof maxValue === 'number' && maxValue > 0 && raw !== '' && parseInt(raw, 10) > maxValue) {
                    raw = String(maxValue);
                }
                displayEl.value = formatRupiah(raw);
                hiddenEl.value = raw;
            });
            displayEl.addEventListener('blur', function () {
                if (displayEl.value === '') hiddenEl.value = '';
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            var amountDisplay = document.getElementById('amountDisplay');
            var amountRaw = document.getElementById('amountRaw');
            if (amountDisplay && amountRaw) {
                var maxValue = parseInt(amountDisplay.getAttribute('data-max') || '{{ $balance ?? 0 }}', 10);
                attachRupiahInput(amountDisplay, amountRaw, maxValue);
                // Block submit kalau hidden raw kosong / 0.
                var form = amountDisplay.closest('form');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        var v = parseInt(amountRaw.value || '0', 10);
                        if (! v || v <= 0) {
                            e.preventDefault();
                            alert('Masukkan nominal penarikan yang valid.');
                            amountDisplay.focus();
                        }
                    });
                }
            }
        });
    })();
    </script>
</body>
</html>
