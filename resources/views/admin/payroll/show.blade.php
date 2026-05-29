@extends('admin.layout')

@section('title', '💰 Detail Payroll - ' . ($waiter['name'] ?? ''))

@section('content')
<div class="container">
    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.payroll.index') }}" style="color: #3b82f6; text-decoration: none; font-size: 14px;">← Kembali ke daftar</a>
    </div>

    <div class="page-header" style="margin-bottom: 20px;">
        <h2>💰 {{ $waiter['name'] ?? 'Karyawan' }}</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">{{ $waiter['email'] ?? '' }}</p>
    </div>

    @if(session('success'))
        <div style="background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Saldo card --}}
    <div class="card" style="padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
        <div style="font-size: 13px; opacity: 0.85; text-transform: uppercase; font-weight: 600;">💰 Saldo Saat Ini</div>
        <div style="font-size: 36px; font-weight: 700; margin-top: 4px;">Rp {{ number_format($balance, 0, ',', '.') }}</div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">

        {{-- Settings form --}}
        <div class="card" style="padding: 16px;">
            <h3 style="margin-top: 0;">⚙️ Pengaturan Payroll</h3>
            <form method="POST" action="{{ route('admin.payroll.settings_update', $waiter['id'] ?? '') }}">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="payroll_enabled" value="1" {{ $settings['payroll_enabled'] ? 'checked' : '' }}>
                        <span style="font-weight: 600;">Aktifkan Payroll untuk karyawan ini</span>
                    </label>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Gaji Pokok Bulanan</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #475569; font-weight: 600; pointer-events: none;">Rp</span>
                        <input type="text" id="salaryDisplay" inputmode="numeric" autocomplete="off" value="{{ $settings['monthly_salary'] > 0 ? number_format($settings['monthly_salary'], 0, ',', '.') : '' }}" placeholder="0" class="form-control rupiah-input" style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <input type="hidden" name="monthly_salary" id="salaryRaw" value="{{ (int) $settings['monthly_salary'] }}">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Tanggal Gajian (1-28)</label>
                    <input type="number" name="payday" value="{{ $settings['payday'] }}" min="0" max="28" class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Isi 0 untuk tidak auto-credit. Auto-credit jalan tiap pagi tanggal ini.</p>
                </div>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 16px 0;">
                <h4 style="margin-top: 0; color: #475569;">🏦 Kasbon</h4>
                <div style="margin-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="kasbon_enabled" value="1" {{ ($kasbonSettings['kasbon_enabled'] ?? false) ? 'checked' : '' }}>
                        <span style="font-weight: 600;">Aktifkan fitur kasbon untuk karyawan ini</span>
                    </label>
                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Jika diaktifkan, Finance bisa membuat kasbon untuk karyawan ini dari halaman Kasbon.</p>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Limit Kasbon (%)</label>
                    <input type="number" name="kasbon_limit_percent" value="{{ $kasbonSettings['kasbon_limit_percent'] ?? '' }}" min="0" max="100" placeholder="Kosongkan = pakai default (30%)" class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Persentase dari gaji berjalan (prorated). Kosongkan untuk pakai default dari pengaturan kasbon.</p>
                </div>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 16px 0;">
                <h4 style="margin-top: 0; color: #475569;">🏦 Rekening Tujuan Penarikan</h4>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 8px;">Diatur oleh karyawan sendiri lewat portal /waiter/payroll. Anda hanya bisa lihat untuk verifikasi.</p>
                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 13px; color: #475569;">
                    <div><strong>Bank:</strong> {{ $settings['bank_name'] !== '' ? $settings['bank_name'] : '-' }}</div>
                    <div><strong>No Rekening:</strong> {{ $settings['bank_account_number'] !== '' ? $settings['bank_account_number'] : '-' }}</div>
                    <div><strong>Atas Nama:</strong> {{ $settings['bank_account_holder'] !== '' ? $settings['bank_account_holder'] : '-' }}</div>
                    @if($settings['bank_name'] === '' || $settings['bank_account_number'] === '')
                        <div style="margin-top: 6px; color: #b45309; font-size: 12px;">⚠️ Karyawan belum mengisi data rekening. Mereka harus lengkapi sendiri di portal sebelum bisa tarik saldo.</div>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary" style="background: #3b82f6; color: #fff; padding: 10px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; width: 100%; margin-top: 16px;">💾 Simpan Pengaturan</button>
            </form>
        </div>

        {{-- Manual credit --}}
        <div class="card" style="padding: 16px;">
            <h3 style="margin-top: 0;">💸 Manual Credit (THR / Bonus Extra)</h3>
            <form method="POST" action="{{ route('admin.payroll.manual_credit', $waiter['id'] ?? '') }}">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Nominal</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #475569; font-weight: 600; pointer-events: none;">Rp</span>
                        <input type="text" id="creditDisplay" inputmode="numeric" autocomplete="off" required class="form-control rupiah-input" placeholder="0" style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <input type="hidden" name="amount" id="creditRaw">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Catatan</label>
                    <input type="text" name="note" maxlength="200" class="form-control" placeholder="THR Lebaran 2026 / Bonus Khusus / dst" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <button type="submit" class="btn" style="background: #10b981; color: #fff; padding: 10px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; width: 100%;">+ Tambah Saldo</button>
            </form>
        </div>
    </div>

    {{-- Transaction history --}}
    <div class="card" style="padding: 16px; margin-top: 20px;">
        <h3 style="margin-top: 0;">📋 Riwayat Transaksi (200 terakhir)</h3>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left;">
                        <th style="padding: 8px 12px;">Tanggal</th>
                        <th style="padding: 8px 12px;">Tipe</th>
                        <th style="padding: 8px 12px;">Nominal</th>
                        <th style="padding: 8px 12px;">Status</th>
                        <th style="padding: 8px 12px;">Saldo Setelah</th>
                        <th style="padding: 8px 12px;">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                    @php
                        $type = $tx['type'] ?? '';
                        $typeLabel = match($type) {
                            'salary_credit' => '💰 Gaji Pokok',
                            'bonus_credit' => '🎉 Bonus',
                            'manual_credit' => '✋ Manual',
                            'withdrawal' => '💸 Penarikan',
                            default => $type,
                        };
                        $amount = (int) ($tx['amount'] ?? 0);
                        $isOut = $type === 'withdrawal';
                        $status = $tx['status'] ?? '';
                        $statusColor = match($status) {
                            'completed' => '#065f46',
                            'approved' => '#065f46',
                            'pending' => '#92400e',
                            'rejected' => '#991b1b',
                            default => '#64748b',
                        };
                        $statusBg = match($status) {
                            'completed' => '#d1fae5',
                            'approved' => '#d1fae5',
                            'pending' => '#fef3c7',
                            'rejected' => '#fee2e2',
                            default => '#f1f5f9',
                        };
                    @endphp
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 12px;">{{ \Carbon\Carbon::createFromTimestamp((int)($tx['created_at'] ?? time()))->format('d M Y H:i') }}</td>
                        <td style="padding: 8px 12px;">{{ $typeLabel }}</td>
                        <td style="padding: 8px 12px; font-weight: 600; color: {{ $isOut ? '#dc2626' : '#059669' }};">
                            {{ $isOut ? '-' : '+' }} Rp {{ number_format($amount, 0, ',', '.') }}
                        </td>
                        <td style="padding: 8px 12px;">
                            <span style="background: {{ $statusBg }}; color: {{ $statusColor }}; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;">{{ $status }}</span>
                        </td>
                        <td style="padding: 8px 12px;">
                            @if($tx['balance_after'] !== null)
                                Rp {{ number_format((int) $tx['balance_after'], 0, ',', '.') }}
                            @else
                                <span style="color: #94a3b8;">-</span>
                            @endif
                        </td>
                        <td style="padding: 8px 12px; color: #64748b;">{{ $tx['note'] ?? '' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding: 24px; text-align: center; color: #64748b;">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    function formatRupiah(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (digits === '') return '';
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function attachRupiahInput(displayId, hiddenId) {
        var displayEl = document.getElementById(displayId);
        var hiddenEl = document.getElementById(hiddenId);
        if (! displayEl || ! hiddenEl) return;
        // Sync raw on init kalau display value sudah ada (mis. monthly_salary preset).
        var initialRaw = String(displayEl.value || '').replace(/\D/g, '');
        hiddenEl.value = initialRaw;
        displayEl.addEventListener('input', function () {
            var raw = displayEl.value.replace(/\D/g, '');
            displayEl.value = formatRupiah(raw);
            hiddenEl.value = raw;
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        attachRupiahInput('salaryDisplay', 'salaryRaw');
        attachRupiahInput('creditDisplay', 'creditRaw');

        // Block manual credit submit kalau amount kosong/0.
        var creditDisplay = document.getElementById('creditDisplay');
        var creditRaw = document.getElementById('creditRaw');
        if (creditDisplay && creditRaw) {
            var creditForm = creditDisplay.closest('form');
            if (creditForm) {
                creditForm.addEventListener('submit', function (e) {
                    var v = parseInt(creditRaw.value || '0', 10);
                    if (! v || v <= 0) {
                        e.preventDefault();
                        alert('Masukkan nominal yang valid.');
                        creditDisplay.focus();
                    }
                });
            }
        }
    });
})();
</script>
@endpush
