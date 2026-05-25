@extends('admin.layout')

@section('title', 'Detail Kasbon #' . $kasbon['id'])

@section('content')
<div class="container">
    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.kasbon.index') }}" style="color: #667eea; text-decoration: none; font-size: 13px;">← Kembali ke daftar kasbon</a>
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

    {{-- Info Kasbon --}}
    <div class="card" style="padding: 20px; margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 style="margin: 0 0 4px 0;">{{ $kasbon['waiter_name'] ?: $kasbon['waiter_id'] }}</h3>
                <p style="color: #64748b; font-size: 13px; margin: 0;">Kasbon #{{ $kasbon['id'] }} • Dibuat {{ \Carbon\Carbon::parse($kasbon['created_at'])->format('d M Y H:i') }}</p>
            </div>
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
            <span style="border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 600; {{ $statusStyles[$kasbon['status']] ?? '' }}">
                {{ $statusLabels[$kasbon['status']] ?? $kasbon['status'] }}
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 16px;">
            <div>
                <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase;">Jumlah Kasbon</div>
                <div style="font-size: 20px; font-weight: 700; margin-top: 2px;">Rp {{ number_format($kasbon['amount'], 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase;">Sisa Hutang</div>
                <div style="font-size: 20px; font-weight: 700; margin-top: 2px; color: {{ $kasbon['remaining'] > 0 ? '#dc2626' : '#16a34a' }};">Rp {{ number_format($kasbon['remaining'], 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase;">Terbayar</div>
                @php $paid = $kasbon['amount'] - $kasbon['remaining']; $pct = $kasbon['amount'] > 0 ? round($paid / $kasbon['amount'] * 100) : 0; @endphp
                <div style="font-size: 20px; font-weight: 700; margin-top: 2px;">{{ $pct }}%</div>
                <div style="background: #e2e8f0; border-radius: 4px; height: 6px; margin-top: 4px; overflow: hidden;">
                    <div style="background: #10b981; height: 100%; width: {{ $pct }}%; border-radius: 4px;"></div>
                </div>
            </div>
        </div>

        @if($kasbon['reason'])
        <div style="margin-top: 16px; padding: 10px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <strong>Alasan:</strong> {{ $kasbon['reason'] }}
        </div>
        @endif

        <div style="margin-top: 12px; font-size: 13px; color: #64748b;">
            Dibuat oleh: {{ $kasbon['created_by'] ?? '-' }}
            @if($kasbon['paid_off_at'])
                • Lunas: {{ \Carbon\Carbon::parse($kasbon['paid_off_at'])->format('d M Y H:i') }}
            @endif
            @if($kasbon['written_off_at'])
                • Write-off: {{ \Carbon\Carbon::parse($kasbon['written_off_at'])->format('d M Y H:i') }} oleh {{ $kasbon['written_off_by'] }}
                <br>Alasan: {{ $kasbon['written_off_reason'] }}
            @endif
            @if($kasbon['cancelled_at'])
                • Dibatalkan: {{ \Carbon\Carbon::parse($kasbon['cancelled_at'])->format('d M Y H:i') }} oleh {{ $kasbon['cancelled_by'] }}
            @endif
        </div>
    </div>

    {{-- Actions --}}
    @if($kasbon['status'] === 'active')
    <div class="card" style="padding: 16px; margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
        @if($kasbon['remaining'] == $kasbon['amount'])
        <form method="POST" action="{{ route('admin.kasbon.cancel', $kasbon['id']) }}" onsubmit="return confirm('Batalkan kasbon ini? Saldo akan dikembalikan.')">
            @csrf
            <button type="submit" style="padding: 6px 14px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-size: 12px; color: #475569;">Batalkan Kasbon</button>
        </form>
        @endif
        @if(session('admin_role') === 'supervisor')
        <button type="button" onclick="document.getElementById('modalWriteOff').style.display='flex'" style="padding: 6px 14px; border-radius: 6px; border: none; background: #dc2626; color: #fff; cursor: pointer; font-size: 12px; font-weight: 600;">Write-off</button>
        @endif
    </div>
    @endif

    {{-- Timeline Pembayaran --}}
    <div class="card" style="padding: 20px;">
        <h4 style="margin: 0 0 12px 0;">Riwayat Potongan</h4>
        @if(count($payments) > 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="padding: 8px 10px; text-align: left; font-weight: 600; color: #475569;">Tanggal</th>
                    <th style="padding: 8px 10px; text-align: right; font-weight: 600; color: #475569;">Potongan</th>
                    <th style="padding: 8px 10px; text-align: right; font-weight: 600; color: #475569;">Sisa Setelah</th>
                    <th style="padding: 8px 10px; text-align: left; font-weight: 600; color: #475569;">Sumber</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $p)
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 10px;">{{ \Carbon\Carbon::parse($p['created_at'])->format('d M Y H:i') }}</td>
                    <td style="padding: 8px 10px; text-align: right; color: #dc2626;">-Rp {{ number_format($p['amount'], 0, ',', '.') }}</td>
                    <td style="padding: 8px 10px; text-align: right;">Rp {{ number_format($p['remaining_after'], 0, ',', '.') }}</td>
                    <td style="padding: 8px 10px;">
                        @if($p['source'] === 'auto_deduct')
                            <span style="color: #667eea; font-size: 11px;">⚡ Auto-deduct</span>
                        @else
                            <span style="color: #64748b; font-size: 11px;">✋ Manual</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="color: #94a3b8; text-align: center; padding: 16px;">Belum ada potongan.</p>
        @endif
    </div>
</div>

{{-- Modal Write-off --}}
@if($kasbon['status'] === 'active')
<div id="modalWriteOff" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:420px; width:90%;">
        <h3 style="margin-top:0; margin-bottom:12px; color: #dc2626;">⚠️ Write-off Kasbon</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">Sisa hutang Rp {{ number_format($kasbon['remaining'], 0, ',', '.') }} akan dihapuskan. Tindakan ini tidak bisa dibatalkan.</p>
        <form method="POST" action="{{ route('admin.kasbon.write_off', $kasbon['id']) }}">
            @csrf
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Alasan write-off</label>
                <textarea name="reason" required rows="2" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; resize:vertical;" placeholder="Karyawan resign, tidak tertagih..."></textarea>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modalWriteOff').style.display='none'" style="padding:8px 16px; border-radius:6px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; font-size:13px;">Batal</button>
                <button type="submit" style="padding:8px 16px; border-radius:6px; border:none; background:#dc2626; color:#fff; cursor:pointer; font-size:13px; font-weight:600;">Konfirmasi Write-off</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
