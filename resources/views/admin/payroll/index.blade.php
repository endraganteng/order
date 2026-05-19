@extends('admin.layout')

@section('title', '💰 Payroll Karyawan')

@section('content')
<div class="container">
    <div class="page-header" style="margin-bottom: 20px;">
        <h2>💰 Payroll Karyawan</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Kelola gaji, saldo, dan penarikan untuk karyawan eligible.</p>
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

    {{-- KPI cards --}}
    <div class="kpi-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div class="card" style="padding: 16px; border-left: 4px solid #10b981;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Total Saldo Akumulasi</div>
            <div style="font-size: 24px; font-weight: 700; color: #1f2937; margin-top: 4px;">Rp {{ number_format($totalBalance, 0, ',', '.') }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #3b82f6;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Karyawan Eligible</div>
            <div style="font-size: 24px; font-weight: 700; color: #1f2937; margin-top: 4px;">{{ $enabledCount }} / {{ count($rows) }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Tindakan Cepat</div>
            <div style="margin-top: 8px; display: flex; flex-direction: column; gap: 6px;">
                <a href="{{ route('admin.payroll.withdrawals') }}" class="btn" style="background: #f59e0b; color: #fff; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; display: inline-block; text-align: center;">📋 Lihat Penarikan Pending</a>
                <button type="button" onclick="document.getElementById('modalTriggerGaji').style.display='flex'" class="btn" style="background: #10b981; color: #fff; padding: 8px 12px; border-radius: 6px; border: none; font-size: 13px; cursor: pointer; width: 100%; font-weight: 600;">🚀 Trigger Gajian Sekarang</button>
            </div>
        </div>
    </div>

    {{-- Config --}}
    <div class="card" style="padding: 16px; margin-bottom: 20px;">
        <h3 style="margin-top: 0;">⚙️ Konfigurasi Payroll</h3>
        <form method="POST" action="{{ route('admin.payroll.config_update') }}">
            @csrf
            <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
                <div style="flex: 1; min-width: 280px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">📱 Nomor WA Supervisor (notif penarikan)</label>
                    <input type="text" name="supervisor_phone" value="{{ $config['supervisor_phone'] ?? '' }}" placeholder="6281234567890" class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Format: 62xxxx tanpa tanda + atau spasi. Format 08xxxx akan auto-converted ke 62xxxx.</p>
                </div>
                <div style="flex: 1; min-width: 280px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">🌐 Public URL Aplikasi (link di WA)</label>
                    <input type="url" name="public_base_url" value="{{ $config['public_base_url'] ?? '' }}" placeholder="https://order.tokoanda.com" class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Domain publik aplikasi. Kalau diisi, link akan masuk di WA. Catatan: Fonnte free tier mungkin reject pesan dengan link — kosongkan kalau pakai free tier.</p>
                </div>
                <input type="hidden" name="is_active" value="1">
                <button type="submit" class="btn btn-primary" style="background: #3b82f6; color: #fff; padding: 10px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer;">💾 Simpan</button>
            </div>
        </form>
    </div>

    {{-- Karyawan list --}}
    <div class="card" style="padding: 16px;">
        <h3 style="margin-top: 0;">👥 Daftar Karyawan</h3>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left;">
                        <th style="padding: 10px 12px;">Nama</th>
                        <th style="padding: 10px 12px;">Status</th>
                        <th style="padding: 10px 12px;">Gaji Pokok</th>
                        <th style="padding: 10px 12px;">Tgl Gajian</th>
                        <th style="padding: 10px 12px;">Saldo</th>
                        <th style="padding: 10px 12px;">Rekening</th>
                        <th style="padding: 10px 12px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 10px 12px;">
                            <div style="font-weight: 600;">{{ $r['name'] }}</div>
                            <div style="font-size: 11px; color: #64748b;">{{ $r['email'] }}</div>
                        </td>
                        <td style="padding: 10px 12px;">
                            @if($r['payroll_enabled'])
                                <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;">✓ Aktif</span>
                            @else
                                <span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;">Non-aktif</span>
                            @endif
                        </td>
                        <td style="padding: 10px 12px;">
                            @if($r['monthly_salary'] > 0)
                                Rp {{ number_format($r['monthly_salary'], 0, ',', '.') }}
                            @else
                                <span style="color: #94a3b8;">-</span>
                            @endif
                        </td>
                        <td style="padding: 10px 12px;">
                            @if($r['payday'] >= 1 && $r['payday'] <= 28)
                                Tgl {{ $r['payday'] }}
                            @else
                                <span style="color: #94a3b8;">-</span>
                            @endif
                        </td>
                        <td style="padding: 10px 12px; font-weight: 600; color: #059669;">
                            Rp {{ number_format($r['balance'], 0, ',', '.') }}
                        </td>
                        <td style="padding: 10px 12px; font-size: 12px;">
                            @if($r['bank_name'] && $r['bank_account_number'])
                                <div>{{ $r['bank_name'] }}</div>
                                <div style="color: #64748b;">{{ $r['bank_account_number'] }}</div>
                                <div style="color: #64748b; font-style: italic;">{{ $r['bank_account_holder'] }}</div>
                            @else
                                <span style="color: #94a3b8;">Belum diisi</span>
                            @endif
                        </td>
                        <td style="padding: 10px 12px;">
                            <a href="{{ route('admin.payroll.show', $r['id']) }}" class="btn" style="background: #3b82f6; color: #fff; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; display: inline-block;">⚙️ Kelola</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding: 24px; text-align: center; color: #64748b;">Belum ada karyawan terdaftar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Trigger Gajian --}}
<div id="modalTriggerGaji" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff; border-radius:12px; padding:24px; width:100%; max-width:480px; max-height:80vh; overflow-y:auto; margin:16px;">
        <h3 style="margin:0 0 4px;">🚀 Trigger Gajian</h3>
        <p style="color:#64748b; font-size:13px; margin:0 0 16px;">Pilih karyawan yang ingin di-trigger gajiannya. Idempotent — tidak akan duplikat.</p>
        <form method="POST" action="{{ route('admin.payroll.run_salary_credit_selected') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="font-size:13px; cursor:pointer;">
                    <input type="checkbox" id="checkAllWaiters" onchange="document.querySelectorAll('.waiter-check').forEach(c=>c.checked=this.checked)"> <strong>Pilih Semua</strong>
                </label>
            </div>
            <div style="border:1px solid #e2e8f0; border-radius:8px; max-height:300px; overflow-y:auto;">
                @foreach($rows as $r)
                    @if($r['payroll_enabled'] && $r['monthly_salary'] > 0 && $r['payday'] >= 1)
                    <label style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer;">
                        <input type="checkbox" name="waiter_ids[]" value="{{ $r['id'] }}" class="waiter-check" checked>
                        <div style="flex:1;">
                            <div style="font-weight:600; font-size:14px;">{{ $r['name'] }}</div>
                            <div style="font-size:12px; color:#64748b;">Rp {{ number_format($r['monthly_salary'], 0, ',', '.') }} · Tgl {{ $r['payday'] }}</div>
                        </div>
                    </label>
                    @endif
                @endforeach
            </div>
            <input type="hidden" name="catchup" value="7">
            <div style="display:flex; gap:8px; margin-top:16px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modalTriggerGaji').style.display='none'" style="padding:10px 16px; border-radius:6px; border:1px solid #cbd5e1; background:#fff; cursor:pointer; font-size:13px;">Batal</button>
                <button type="submit" style="padding:10px 16px; border-radius:6px; border:none; background:#10b981; color:#fff; font-weight:600; cursor:pointer; font-size:13px;">✅ Trigger Gajian</button>
            </div>
        </form>
    </div>
</div>
@endsection
