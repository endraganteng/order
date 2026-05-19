@extends('admin.layout')

@section('title', '📊 Finance Dashboard')

@section('content')
<div class="container">
    <div class="page-header" style="margin-bottom: 20px;">
        <h2>📊 Finance Dashboard</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Ringkasan keuangan hari ini ({{ $today }}) dan bulan {{ $month }}.</p>
    </div>

    {{-- KPI Hari Ini --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div class="card" style="padding: 16px; border-left: 4px solid #10b981;">
            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Pendapatan Hari Ini</div>
            <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">Rp {{ number_format($dailySummary['total_pendapatan'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #3b82f6;">
            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Pengeluaran Hari Ini</div>
            <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">Rp {{ number_format($dailySummary['total_pengeluaran'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b;">
            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Bersih Hari Ini</div>
            <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">Rp {{ number_format($dailySummary['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="card" style="padding: 16px; border-left: 4px solid #8b5cf6;">
            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600;">Hutang Aktif</div>
            <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">Rp {{ number_format($debtSummary['total_hutang'] ?? 0, 0, ',', '.') }}</div>
            <div style="font-size: 11px; color: #64748b;">{{ $debtSummary['jumlah_hutang'] ?? 0 }} hutang · {{ $debtSummary['jatuh_tempo_minggu_ini'] ?? 0 }} jatuh tempo</div>
        </div>
    </div>

    {{-- Ringkasan Bulan --}}
    <div class="card" style="padding: 16px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 12px;">📅 Ringkasan Bulan {{ $month }}</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;">
            <div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Penjualan Tunai</div>
                <div style="font-size: 18px; font-weight: 700;">Rp {{ number_format($monthSummary['penjualan_tunai'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Penjualan QRIS</div>
                <div style="font-size: 18px; font-weight: 700;">Rp {{ number_format($monthSummary['penjualan_qris'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Total Pendapatan</div>
                <div style="font-size: 18px; font-weight: 700; color: #059669;">Rp {{ number_format($monthSummary['total_pendapatan'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Total Pengeluaran</div>
                <div style="font-size: 18px; font-weight: 700; color: #dc2626;">Rp {{ number_format($monthSummary['total_pengeluaran'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Pendapatan Bersih</div>
                <div style="font-size: 18px; font-weight: 700;">Rp {{ number_format($monthSummary['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Hari Sync</div>
                <div style="font-size: 18px; font-weight: 700;">{{ $monthSummary['days_synced'] ?? 0 }} hari</div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        {{-- Saldo Akun Kas --}}
        <div class="card" style="padding: 16px;">
            <h3 style="margin: 0 0 12px;">🏦 Saldo Akun Kas</h3>
            @forelse($accounts as $acc)
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="font-size: 13px;">{{ $acc['name'] }}</span>
                    <span style="font-size: 13px; font-weight: 600;">Rp {{ number_format($acc['balance'] ?? 0, 0, ',', '.') }}</span>
                </div>
            @empty
                <p style="color: #94a3b8; font-size: 13px;">Belum ada akun kas.</p>
            @endforelse
        </div>

        {{-- Penarikan Gaji Pending --}}
        <div class="card" style="padding: 16px;">
            <h3 style="margin: 0 0 12px;">💰 Penarikan Gaji Pending</h3>
            @forelse($pendingWithdrawals as $wd)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <div style="font-size: 13px; font-weight: 600;">{{ $wd['waiter_name'] ?? $wd['waiter_id'] }}</div>
                        <div style="font-size: 11px; color: #64748b;">{{ \Carbon\Carbon::parse($wd['created_at'])->format('d/m H:i') }}</div>
                    </div>
                    <span style="font-size: 13px; font-weight: 600; color: #f59e0b;">Rp {{ number_format($wd['amount'] ?? 0, 0, ',', '.') }}</span>
                </div>
            @empty
                <p style="color: #94a3b8; font-size: 13px;">Tidak ada penarikan pending.</p>
            @endforelse
            @if(count($pendingWithdrawals) > 0)
                <a href="{{ route('admin.payroll.withdrawals') }}" style="display: block; text-align: center; margin-top: 12px; font-size: 13px; color: #3b82f6;">Lihat semua →</a>
            @endif
        </div>
    </div>

    {{-- Last Sync Info --}}
    @if($lastSync)
    <div style="margin-top: 16px; padding: 10px 16px; background: #f8fafc; border-radius: 8px; font-size: 12px; color: #64748b;">
        Sync terakhir: {{ \Carbon\Carbon::parse($lastSync['created_at'])->format('d/m/Y H:i') }} · Status: {{ $lastSync['status'] }} · {{ $lastSync['records_synced'] ?? 0 }} records
    </div>
    @endif
</div>
@endsection
