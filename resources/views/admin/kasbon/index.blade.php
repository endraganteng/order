@extends('admin.layout')

@section('title', '💰 Kasbon Karyawan')

@section('content')
<div class="container">
    <div class="page-header" style="margin-bottom: 20px;">
        <h2>💰 Kasbon Karyawan</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Kelola kasbon (pinjaman gaji di awal) untuk karyawan.</p>
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
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div class="card" style="padding: 16px; border-left: 4px solid #10b981;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Total Kasbon Aktif</div>
            <div style="font-size: 24px; font-weight: 700; color: #1f2937; margin-top: 4px;">Rp {{ number_format($stats['total_active_amount'], 0, ',', '.') }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Karyawan Punya Kasbon</div>
            <div style="font-size: 24px; font-weight: 700; color: #1f2937; margin-top: 4px;">{{ $stats['count_active_waiters'] }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #3b82f6;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Lunas Bulan Ini</div>
            <div style="font-size: 24px; font-weight: 700; color: #1f2937; margin-top: 4px;">Rp {{ number_format($stats['paid_off_this_month'], 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- Actions --}}
    <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
        @if(session('admin_role') === 'finance' || session('admin_role') === 'supervisor')
        <button type="button" onclick="document.getElementById('modalBuatKasbon').style.display='flex'" style="background: #667eea; color: #fff; padding: 8px 16px; border-radius: 6px; border: none; font-size: 13px; cursor: pointer; font-weight: 600;">+ Buat Kasbon</button>
        @endif
        <a href="{{ route('admin.kasbon.settings') }}" style="background: #f1f5f9; color: #475569; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600;">⚙️ Pengaturan</a>
    </div>

    {{-- Filter --}}
    <div class="card" style="padding: 12px 16px; margin-bottom: 16px;">
        <form method="GET" action="{{ route('admin.kasbon.index') }}" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: end;">
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #64748b; display: block; margin-bottom: 2px;">Status</label>
                <select name="status" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    <option value="">Semua</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="paid_off" {{ ($filters['status'] ?? '') === 'paid_off' ? 'selected' : '' }}>Lunas</option>
                    <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                    <option value="written_off" {{ ($filters['status'] ?? '') === 'written_off' ? 'selected' : '' }}>Write-off</option>
                </select>
            </div>
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #64748b; display: block; margin-bottom: 2px;">Karyawan</label>
                <select name="waiter_id" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    <option value="">Semua</option>
                    @foreach($waiters as $w)
                    <option value="{{ $w['id'] }}" {{ ($filters['waiter_id'] ?? '') === $w['id'] ? 'selected' : '' }}>{{ $w['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #64748b; display: block; margin-bottom: 2px;">Dari</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #64748b; display: block; margin-bottom: 2px;">Sampai</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
            </div>
            <button type="submit" style="background: #667eea; color: #fff; padding: 6px 14px; border-radius: 6px; border: none; font-size: 13px; cursor: pointer;">Filter</button>
            <a href="{{ route('admin.kasbon.index') }}" style="color: #64748b; font-size: 13px; text-decoration: none; padding: 6px 8px;">Reset</a>
        </form>
    </div>

    {{-- Table --}}
    <div class="card" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569;">Karyawan</th>
                    <th style="padding: 10px 12px; text-align: right; font-weight: 600; color: #475569;">Jumlah</th>
                    <th style="padding: 10px 12px; text-align: right; font-weight: 600; color: #475569;">Sisa</th>
                    <th style="padding: 10px 12px; text-align: center; font-weight: 600; color: #475569;">Status</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569;">Tanggal</th>
                    <th style="padding: 10px 12px; text-align: center; font-weight: 600; color: #475569;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($kasbons as $k)
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px 12px;">{{ $k['waiter_name'] ?: $k['waiter_id'] }}</td>
                    <td style="padding: 10px 12px; text-align: right;">Rp {{ number_format($k['amount'], 0, ',', '.') }}</td>
                    <td style="padding: 10px 12px; text-align: right;">Rp {{ number_format($k['remaining'], 0, ',', '.') }}</td>
                    <td style="padding: 10px 12px; text-align: center;">
                        @php
                            $statusStyles = [
                                'active' => 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;',
                                'paid_off' => 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;',
                                'cancelled' => 'background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;',
                                'written_off' => 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;',
                            ];
                            $statusLabels = [
                                'active' => 'Aktif',
                                'paid_off' => 'Lunas',
                                'cancelled' => 'Dibatalkan',
                                'written_off' => 'Write-off',
                            ];
                        @endphp
                        <span style="border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 600; {{ $statusStyles[$k['status']] ?? '' }}">
                            {{ $statusLabels[$k['status']] ?? $k['status'] }}
                        </span>
                    </td>
                    <td style="padding: 10px 12px; font-size: 13px; color: #64748b;">{{ \Carbon\Carbon::parse($k['created_at'])->format('d M Y') }}</td>
                    <td style="padding: 10px 12px; text-align: center;">
                        <a href="{{ route('admin.kasbon.show', $k['id']) }}" style="color: #667eea; text-decoration: none; font-size: 12px; font-weight: 600;">Detail</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Belum ada data kasbon.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top: 8px; font-size: 13px; color: #64748b;">Total: {{ $total }} kasbon</div>
</div>

{{-- Modal Buat Kasbon --}}
<div id="modalBuatKasbon" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:480px; width:90%; max-height:90vh; overflow-y:auto;">
        <h3 style="margin-top:0; margin-bottom:16px;">Buat Kasbon Baru</h3>
        <form method="POST" action="{{ route('admin.kasbon.store') }}">
            @csrf
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Karyawan</label>
                <select name="waiter_id" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;" id="selectWaiterKasbon" onchange="checkLimit(this.value)">
                    <option value="">-- Pilih Karyawan --</option>
                    @foreach($waiters as $w)
                        @if($w['kasbon_enabled'])
                        <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                        @endif
                    @endforeach
                </select>
                <div id="limitInfo" style="font-size:12px; color:#64748b; margin-top:4px;"></div>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Nominal (Rp)</label>
                <input type="number" name="amount" required min="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;" placeholder="500000">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Alasan (opsional)</label>
                <textarea name="reason" rows="2" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; resize:vertical;" placeholder="Keperluan darurat..."></textarea>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modalBuatKasbon').style.display='none'" style="padding:8px 16px; border-radius:6px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; font-size:13px;">Batal</button>
                <button type="submit" style="padding:8px 16px; border-radius:6px; border:none; background:#667eea; color:#fff; cursor:pointer; font-size:13px; font-weight:600;">Cairkan Kasbon</button>
            </div>
        </form>
    </div>
</div>

<script>
function checkLimit(waiterId) {
    const el = document.getElementById('limitInfo');
    if (!waiterId) { el.textContent = ''; return; }
    fetch('{{ url("admin/kasbon/waiter") }}/' + waiterId + '/limit')
        .then(r => r.json())
        .then(d => {
            el.textContent = 'Limit tersedia: Rp ' + new Intl.NumberFormat('id-ID').format(d.available) + ' (dari Rp ' + new Intl.NumberFormat('id-ID').format(d.limit) + ')';
            el.style.color = d.available > 0 ? '#065f46' : '#991b1b';
        })
        .catch(() => { el.textContent = 'Gagal cek limit'; el.style.color = '#991b1b'; });
}
</script>
@endsection
