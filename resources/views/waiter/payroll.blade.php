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
        .header-meta { font-size: 11px; opacity: 0.85; }
        .container { max-width: 720px; margin: 0 auto; padding: 16px; }
        .balance-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 14px; padding: 24px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25); transition: opacity 0.2s; }
        .balance-card.is-updating { opacity: 0.8; }
        .balance-label { font-size: 13px; opacity: 0.9; text-transform: uppercase; font-weight: 600; }
        .balance-value { font-size: 36px; font-weight: 700; margin-top: 6px; line-height: 1.2; transition: color 0.4s; }
        .balance-value.flash-up { color: #fef3c7; }
        .balance-value.flash-down { color: #fee2e2; }
        .balance-info { font-size: 13px; opacity: 0.85; margin-top: 8px; }
        .card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); }
        .section-title { font-size: 15px; font-weight: 600; color: #1f2937; margin: 0 0 10px; display: flex; align-items: center; gap: 8px; }
        .live-dot { display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 0.4; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }
        .input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        .btn { width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; transition: opacity 0.2s; }
        .btn:disabled { opacity: 0.6; cursor: wait; }
        .btn--primary { background: #f59e0b; color: #fff; }
        .btn--primary:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn--blue { background: #3b82f6; color: #fff; }
        .bank-info { background: #f8fafc; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #475569; margin-bottom: 12px; }
        .bank-info--empty { background: #fef2f2; color: #991b1b; }
        .tx-list { display: flex; flex-direction: column; gap: 8px; }
        .tx-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border-radius: 8px; gap: 8px; }
        .tx-item.is-new { animation: slideIn 0.4s ease-out; background: #ecfdf5; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
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
        .tx-status--approved { background: #d1fae5; color: #065f46; }
        .empty { padding: 24px; text-align: center; color: #94a3b8; }
        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; transition: opacity 0.3s; }
        .flash--success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
        .flash--error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .disabled-banner { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; padding: 16px; border-radius: 12px; text-align: center; margin-bottom: 16px; }
        .label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #475569; }
        .hint { font-size: 11px; color: #64748b; margin-top: 4px; }
        .toast { position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); background: #1f2937; color: #fff; padding: 10px 16px; border-radius: 8px; font-size: 13px; z-index: 200; box-shadow: 0 8px 24px rgba(0,0,0,0.2); opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .toast.show { opacity: 1; }
        .toast--error { background: #dc2626; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <a href="{{ url('/waiter/tasks') }}" class="back-link">← Kembali</a>
            <span class="header-meta" id="lastSyncLabel">⟳ Live</span>
        </div>
        <h1>💰 Gaji Saya</h1>
    </div>

    <div class="container" id="payrollRoot" data-payroll-enabled="{{ $settings['payroll_enabled'] ? '1' : '0' }}" data-bank-ready="{{ ($settings['bank_name'] && $settings['bank_account_number']) ? '1' : '0' }}">
        <div id="flashSlot"></div>

        @if(! $settings['payroll_enabled'])
            <div class="disabled-banner">
                <strong>⚠️ Fitur Belum Aktif</strong><br>
                <span style="font-size: 13px;">Akun payroll Anda belum diaktifkan oleh supervisor. Hubungi supervisor untuk mengaktifkan fitur ini.</span>
            </div>
        @else
            <div class="balance-card" id="balanceCard">
                <div class="balance-label"><span class="live-dot"></span> Saldo Anda</div>
                <div class="balance-value" id="balanceValue">Rp {{ number_format($balance, 0, ',', '.') }}</div>
                <div class="balance-info" id="paydayInfo">
                    @if($nextPayday)
                        📅 Gajian berikutnya: {{ \Carbon\Carbon::parse($nextPayday)->translatedFormat('d M Y') }}
                    @else
                        📅 Tanggal gajian belum diatur. Hubungi supervisor.
                    @endif
                </div>
                @if($settings['monthly_salary'] > 0)
                    <div class="balance-info" id="salaryInfo">💼 Gaji pokok: Rp {{ number_format($settings['monthly_salary'], 0, ',', '.') }}/bulan</div>
                @endif
            </div>

            <div class="card">
                <h3 class="section-title">🏦 Rekening Saya</h3>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 10px;">Atur sendiri rekening tujuan transfer. Pastikan data benar — supervisor akan transfer ke rekening ini saat penarikan disetujui.</p>
                <form id="bankForm" data-action="{{ route('waiter.payroll.bank_update') }}">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <label class="label">Bank</label>
                        <input type="text" name="bank_name" id="bankNameInput" required maxlength="60" class="input" value="{{ $settings['bank_name'] }}" placeholder="BCA / Mandiri / BRI / dst">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label class="label">No Rekening</label>
                        <input type="text" name="bank_account_number" id="bankAccInput" required maxlength="30" class="input" value="{{ $settings['bank_account_number'] }}" placeholder="1234567890" inputmode="numeric">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label class="label">Atas Nama</label>
                        <input type="text" name="bank_account_holder" id="bankHolderInput" required maxlength="60" class="input" value="{{ $settings['bank_account_holder'] }}" placeholder="Nama sesuai buku tabungan">
                    </div>
                    <button type="submit" class="btn btn--blue" id="bankSubmitBtn">💾 Simpan Rekening</button>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">💸 Tarik Saldo</h3>
                <div class="bank-info {{ ($settings['bank_name'] && $settings['bank_account_number']) ? '' : 'bank-info--empty' }}" id="bankInfoSummary">
                    @if($settings['bank_name'] && $settings['bank_account_number'])
                        <div><strong>🏦 {{ $settings['bank_name'] }}</strong></div>
                        <div>{{ $settings['bank_account_number'] }} a.n. {{ $settings['bank_account_holder'] }}</div>
                        <div style="font-size: 11px; margin-top: 4px;">Dana akan ditransfer ke rekening ini setelah supervisor approve.</div>
                    @else
                        ⚠️ Lengkapi data rekening di atas dulu sebelum bisa tarik saldo.
                    @endif
                </div>

                <div id="withdrawFormSlot">
                @if($balance > 0 && $settings['bank_name'] && $settings['bank_account_number'])
                <form id="withdrawForm" data-action="{{ route('waiter.payroll.withdraw') }}">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <label class="label">Nominal</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #475569; font-weight: 600; pointer-events: none;">Rp</span>
                            <input type="text" id="amountDisplay" inputmode="numeric" autocomplete="off" required class="input rupiah-input" placeholder="0" style="padding-left: 36px;" data-max="{{ $balance }}">
                        </div>
                        <input type="hidden" name="amount" id="amountRaw">
                        <p class="hint" id="balanceMaxHint">Max: Rp {{ number_format($balance, 0, ',', '.') }}</p>
                    </div>
                    <button type="submit" class="btn btn--primary" id="withdrawSubmitBtn">💸 Ajukan Penarikan</button>
                </form>
                @endif
                </div>
            </div>
        @endif

        {{-- Section Kasbon (read-only) --}}
        @if($kasbonData)
        <div class="card">
            <h3 class="section-title">💰 Kasbon</h3>
            @php
                $activeKasbons = array_filter($kasbonData['items'], fn($k) => $k['status'] === 'active');
                $totalRemaining = array_sum(array_column($activeKasbons, 'remaining'));
            @endphp
            @if(count($activeKasbons) > 0)
                <div style="background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                    <div style="font-size: 12px; color: #854d0e; font-weight: 600; text-transform: uppercase;">Sisa Hutang Kasbon</div>
                    <div style="font-size: 22px; font-weight: 700; color: #92400e; margin-top: 2px;">Rp {{ number_format($totalRemaining, 0, ',', '.') }}</div>
                    @php
                        $totalAmount = array_sum(array_column($activeKasbons, 'amount'));
                        $paid = $totalAmount - $totalRemaining;
                        $pct = $totalAmount > 0 ? round($paid / $totalAmount * 100) : 0;
                    @endphp
                    <div style="background: #fde68a; border-radius: 4px; height: 6px; margin-top: 8px; overflow: hidden;">
                        <div style="background: #16a34a; height: 100%; width: {{ $pct }}%; border-radius: 4px;"></div>
                    </div>
                    <div style="font-size: 11px; color: #854d0e; margin-top: 4px;">Terbayar {{ $pct }}% — otomatis dipotong dari gaji/bonus</div>
                </div>
            @else
                <div style="color: #64748b; font-size: 13px; padding: 8px 0;">Tidak ada kasbon aktif.</div>
            @endif

            @if(count($kasbonData['items']) > 0)
                <div style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">Riwayat Kasbon</div>
                @foreach($kasbonData['items'] as $k)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <div style="font-size: 14px; font-weight: 600;">Rp {{ number_format($k['amount'], 0, ',', '.') }}</div>
                        <div style="font-size: 11px; color: #64748b;">{{ \Carbon\Carbon::parse($k['created_at'])->format('d M Y') }}{{ $k['reason'] ? ' • ' . $k['reason'] : '' }}</div>
                    </div>
                    <div>
                        @php
                            $wStatusStyles = [
                                'active' => 'background:#fef9c3;color:#854d0e;',
                                'paid_off' => 'background:#d1fae5;color:#065f46;',
                                'cancelled' => 'background:#f1f5f9;color:#475569;',
                                'written_off' => 'background:#fee2e2;color:#991b1b;',
                            ];
                            $wStatusLabels = ['active' => 'Aktif', 'paid_off' => 'Lunas', 'cancelled' => 'Batal', 'written_off' => 'Dihapus'];
                        @endphp
                        <span style="border-radius: 999px; padding: 2px 8px; font-size: 10px; font-weight: 600; {{ $wStatusStyles[$k['status']] ?? '' }}">
                            {{ $wStatusLabels[$k['status']] ?? $k['status'] }}
                        </span>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
        @endif

        <div class="card">
            <h3 class="section-title"><span class="live-dot"></span> 📋 Riwayat Transaksi</h3>
            <div id="txContainer">
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
                        <div class="tx-item" data-tx-id="{{ $tx['id'] ?? '' }}">
                            <div class="tx-info">
                                <div class="tx-type">{{ $typeLabel }}
                                    @if($status === 'pending')
                                        <span class="tx-status tx-status--pending">⏳ Menunggu Approval</span>
                                    @elseif($status === 'rejected')
                                        <span class="tx-status tx-status--rejected">✗ Ditolak</span>
                                    @elseif($status === 'approved' && $isOut)
                                        <span class="tx-status tx-status--approved">✓ Berhasil Ditransfer</span>
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
    </div>

    <div class="toast" id="toast"></div>

    <script>
    (function () {
        'use strict';
        var POLL_INTERVAL_MS = 8000;
        var apiUrl = "{{ route('waiter.payroll.api', [], false) }}";
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var lastBalance = {{ (int) ($balance ?? 0) }};
        var pollTimer = null;
        var inflight = false;

        // ── Helpers ──
        function formatRupiah(value) {
            var digits = String(value || '').replace(/\D/g, '');
            if (digits === '') return '';
            return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
            });
        }
        function showToast(msg, isError) {
            var el = document.getElementById('toast');
            if (!el) return;
            el.textContent = msg;
            el.className = 'toast show' + (isError ? ' toast--error' : '');
            clearTimeout(el._t);
            el._t = setTimeout(function () { el.className = 'toast'; }, 3500);
        }
        function showFlash(msg, type) {
            var slot = document.getElementById('flashSlot');
            if (!slot) return;
            var div = document.createElement('div');
            div.className = 'flash flash--' + (type === 'error' ? 'error' : 'success');
            div.textContent = (type === 'error' ? '✗ ' : '✓ ') + msg;
            slot.innerHTML = '';
            slot.appendChild(div);
            setTimeout(function () {
                div.style.opacity = '0';
                setTimeout(function () { div.remove(); }, 350);
            }, 4000);
        }
        function fmtDate(ts) {
            var d = new Date(ts * 1000);
            var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            var pad = function (n) { return n < 10 ? '0' + n : n; };
            return pad(d.getDate()) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }
        function txTypeLabel(type) {
            return ({
                'salary_credit': '💰 Gaji Pokok',
                'bonus_credit': '🎉 Bonus Bulanan',
                'manual_credit': '✋ Tambahan Saldo',
                'withdrawal': '💸 Penarikan'
            })[type] || type;
        }
        function statusBadge(status, isWithdrawal) {
            if (status === 'pending') return '<span class="tx-status tx-status--pending">⏳ Menunggu Approval</span>';
            if (status === 'rejected') return '<span class="tx-status tx-status--rejected">✗ Ditolak</span>';
            if (status === 'approved' && isWithdrawal) return '<span class="tx-status tx-status--approved">✓ Berhasil Ditransfer</span>';
            return '';
        }

        // ── Rendering ──
        function renderBalance(newBalance) {
            var el = document.getElementById('balanceValue');
            if (!el) return;
            el.textContent = 'Rp ' + formatRupiah(newBalance);
            if (newBalance > lastBalance) {
                el.classList.add('flash-up');
                setTimeout(function () { el.classList.remove('flash-up'); }, 800);
            } else if (newBalance < lastBalance) {
                el.classList.add('flash-down');
                setTimeout(function () { el.classList.remove('flash-down'); }, 800);
            }
            // Update max hint + max attribute on amount input.
            var maxHint = document.getElementById('balanceMaxHint');
            if (maxHint) maxHint.textContent = 'Max: Rp ' + formatRupiah(newBalance);
            var amountDisplay = document.getElementById('amountDisplay');
            if (amountDisplay) amountDisplay.setAttribute('data-max', String(newBalance));
            lastBalance = newBalance;
        }

        function renderTransactions(transactions) {
            var container = document.getElementById('txContainer');
            if (!container) return;
            if (!transactions || transactions.length === 0) {
                container.innerHTML = '<div class="empty">Belum ada transaksi.</div>';
                return;
            }
            // Build map of existing tx ids before re-render.
            var existing = {};
            container.querySelectorAll('[data-tx-id]').forEach(function (el) {
                existing[el.getAttribute('data-tx-id')] = true;
            });

            var html = '<div class="tx-list">';
            transactions.forEach(function (tx) {
                var type = tx.type || '';
                var isOut = type === 'withdrawal';
                var status = tx.status || '';
                var amount = parseInt(tx.amount || 0, 10);
                var isNew = !existing[tx.id];
                html += '<div class="tx-item' + (isNew ? ' is-new' : '') + '" data-tx-id="' + escapeHtml(tx.id) + '">';
                html += '<div class="tx-info">';
                html += '<div class="tx-type">' + escapeHtml(txTypeLabel(type)) + ' ' + statusBadge(status, isOut) + '</div>';
                html += '<div class="tx-date">' + fmtDate(parseInt(tx.created_at || 0, 10)) + '</div>';
                if (tx.note) html += '<div class="tx-note">' + escapeHtml(tx.note) + '</div>';
                html += '</div>';
                html += '<div class="tx-amount ' + (isOut ? 'tx-amount--out' : 'tx-amount--in') + '">';
                html += (isOut ? '-' : '+') + ' Rp ' + formatRupiah(amount);
                html += '</div></div>';
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function syncWithdrawFormVisibility(balance, settings) {
            var slot = document.getElementById('withdrawFormSlot');
            if (!slot) return;
            var hasBank = !!(settings && settings.bank_name && settings.bank_account_number);
            var hasFundsAndBank = (balance > 0) && hasBank;
            var existingForm = document.getElementById('withdrawForm');
            if (hasFundsAndBank && !existingForm) {
                // Form not present — render it so karyawan bisa langsung pakai.
                var holder = settings.bank_account_holder || '';
                slot.innerHTML = ''
                    + '<form id="withdrawForm" data-action="{{ route('waiter.payroll.withdraw') }}">'
                    + '<input type="hidden" name="_token" value="' + csrfToken + '">'
                    + '<div style="margin-bottom: 10px;">'
                    + '<label class="label">Nominal</label>'
                    + '<div style="position: relative;">'
                    + '<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #475569; font-weight: 600; pointer-events: none;">Rp</span>'
                    + '<input type="text" id="amountDisplay" inputmode="numeric" autocomplete="off" required class="input rupiah-input" placeholder="0" style="padding-left: 36px;" data-max="' + balance + '">'
                    + '</div>'
                    + '<input type="hidden" name="amount" id="amountRaw">'
                    + '<p class="hint" id="balanceMaxHint">Max: Rp ' + formatRupiah(balance) + '</p>'
                    + '</div>'
                    + '<button type="submit" class="btn btn--primary" id="withdrawSubmitBtn">💸 Ajukan Penarikan</button>'
                    + '</form>';
                attachWithdrawForm();
                attachRupiahHandler();
            } else if (!hasFundsAndBank && existingForm) {
                slot.innerHTML = '';
            }
        }

        function syncBankInfoSummary(settings) {
            var el = document.getElementById('bankInfoSummary');
            if (!el) return;
            if (settings.bank_name && settings.bank_account_number) {
                el.classList.remove('bank-info--empty');
                el.innerHTML = ''
                    + '<div><strong>🏦 ' + escapeHtml(settings.bank_name) + '</strong></div>'
                    + '<div>' + escapeHtml(settings.bank_account_number) + ' a.n. ' + escapeHtml(settings.bank_account_holder || '') + '</div>'
                    + '<div style="font-size: 11px; margin-top: 4px;">Dana akan ditransfer ke rekening ini setelah supervisor approve.</div>';
            } else {
                el.classList.add('bank-info--empty');
                el.textContent = '⚠️ Lengkapi data rekening di atas dulu sebelum bisa tarik saldo.';
            }
        }

        // ── Rupiah input handler ──
        function attachRupiahHandler() {
            var displayEl = document.getElementById('amountDisplay');
            var hiddenEl = document.getElementById('amountRaw');
            if (!displayEl || !hiddenEl || displayEl._rupiahAttached) return;
            displayEl._rupiahAttached = true;

            displayEl.addEventListener('input', function () {
                var max = parseInt(displayEl.getAttribute('data-max') || '0', 10);
                var raw = displayEl.value.replace(/\D/g, '');
                if (max > 0 && raw !== '' && parseInt(raw, 10) > max) raw = String(max);
                displayEl.value = formatRupiah(raw);
                hiddenEl.value = raw;
            });
            displayEl.addEventListener('blur', function () {
                if (displayEl.value === '') hiddenEl.value = '';
            });
        }

        // ── AJAX form handlers ──
        function attachBankForm() {
            var form = document.getElementById('bankForm');
            if (!form || form._attached) return;
            form._attached = true;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = document.getElementById('bankSubmitBtn');
                var origLabel = btn.textContent;
                btn.disabled = true;
                btn.textContent = '⏳ Menyimpan...';

                var fd = new FormData(form);
                fetch(form.getAttribute('data-action'), {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
                .then(function (resp) {
                    if (resp.status >= 200 && resp.status < 300 && resp.body && resp.body.success) {
                        showFlash(resp.body.message || 'Tersimpan', 'success');
                        showToast('Rekening tersimpan', false);
                        var settings = resp.body.settings || {};
                        syncBankInfoSummary(settings);
                        // Update payroll-root attr for current bank readiness.
                        var root = document.getElementById('payrollRoot');
                        if (root) root.setAttribute('data-bank-ready', settings.bank_name && settings.bank_account_number ? '1' : '0');
                        syncWithdrawFormVisibility(lastBalance, settings);
                    } else {
                        var msg = (resp.body && resp.body.message) || 'Gagal simpan';
                        if (resp.body && resp.body.errors) {
                            msg = Object.values(resp.body.errors).flat().join(', ');
                        }
                        showFlash(msg, 'error');
                    }
                })
                .catch(function (err) {
                    showFlash('Network error: ' + (err && err.message ? err.message : 'unknown'), 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = origLabel;
                });
            });
        }

        function attachWithdrawForm() {
            var form = document.getElementById('withdrawForm');
            if (!form || form._attached) return;
            form._attached = true;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var amountRaw = document.getElementById('amountRaw');
                var v = parseInt(amountRaw && amountRaw.value || '0', 10);
                if (!v || v <= 0) {
                    showToast('Masukkan nominal yang valid', true);
                    return;
                }
                if (v > lastBalance) {
                    showToast('Saldo tidak cukup', true);
                    return;
                }
                if (!confirm('Yakin ajukan penarikan Rp ' + formatRupiah(v) + '? Supervisor akan dapat notifikasi WhatsApp.')) return;

                var btn = document.getElementById('withdrawSubmitBtn');
                var origLabel = btn.textContent;
                btn.disabled = true;
                btn.textContent = '⏳ Mengirim...';

                var fd = new FormData(form);
                fetch(form.getAttribute('data-action'), {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
                .then(function (resp) {
                    if (resp.status >= 200 && resp.status < 300 && resp.body && resp.body.success) {
                        showFlash(resp.body.message || 'Permintaan dikirim', 'success');
                        showToast('Permintaan terkirim ke supervisor', false);
                        // Reset form input.
                        var amountDisplay = document.getElementById('amountDisplay');
                        if (amountDisplay) amountDisplay.value = '';
                        if (amountRaw) amountRaw.value = '';
                        // Trigger immediate snapshot to refresh tx list.
                        fetchSnapshot();
                    } else {
                        var msg = (resp.body && resp.body.message) || 'Gagal';
                        showFlash(msg, 'error');
                    }
                })
                .catch(function (err) {
                    showFlash('Network error: ' + (err && err.message ? err.message : 'unknown'), 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = origLabel;
                });
            });
        }

        // ── Polling ──
        function fetchSnapshot() {
            if (inflight) return;
            inflight = true;
            var label = document.getElementById('lastSyncLabel');
            if (label) label.textContent = '⟳ Sync...';

            fetch(apiUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) {
                if (r.status === 401) {
                    // Session expired — stop polling and redirect to login
                    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                    window.location.href = '{{ route("waiter.login", [], false) }}';
                    return null;
                }
                return r.json();
            })
            .then(function (body) {
                if (!body) return;
                if (body && body.success) {
                    var nextBalance = parseInt(body.balance || 0, 10);
                    renderBalance(nextBalance);
                    renderTransactions(body.transactions || []);
                    syncBankInfoSummary(body.settings || {});
                    syncWithdrawFormVisibility(nextBalance, body.settings || {});
                    if (label) label.textContent = '⟳ Live';
                } else {
                    if (label) label.textContent = '⚠ Sync error';
                }
            })
            .catch(function () {
                if (label) label.textContent = '⚠ Offline';
            })
            .finally(function () { inflight = false; });
        }

        function startPolling() {
            stopPolling();
            pollTimer = setInterval(fetchSnapshot, POLL_INTERVAL_MS);
        }
        function stopPolling() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        // Pause polling when tab hidden, resume when visible (save bandwidth).
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else {
                fetchSnapshot();
                startPolling();
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            attachBankForm();
            attachWithdrawForm();
            attachRupiahHandler();
            startPolling();
        });
    })();
    </script>
</body>
</html>
