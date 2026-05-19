@extends('admin.layout')

@section('title', 'Laporan Laba Rugi')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📈 Laporan Laba Rugi</h1>
            <p class="fm-page-subtitle">Profit & Loss bulan {{ $month }}</p>
        </div>
    </div>

    {{-- Month Picker --}}
    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Periode</label>
            <input type="month" class="fm-input" value="{{ $month }}" onchange="location.href='{{ route('admin.finance.laba_rugi') }}?month='+this.value" style="width:180px;">
        </div>
    </div>

    {{-- Report --}}
    <div class="fm-card" style="padding:0;overflow:hidden;max-width:600px;">
        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;padding:16px 20px;">
            <div style="font-weight:700;font-size:16px;">Laporan Laba Rugi</div>
            <div style="font-size:13px;opacity:0.8;">Periode: {{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</div>
        </div>

        <div style="padding:20px;">
            {{-- Pendapatan --}}
            <div style="font-weight:700;font-size:13px;color:#064e3b;text-transform:uppercase;margin-bottom:8px;">Pendapatan</div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                @foreach($report['pendapatan'] as $item)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:8px 0;font-size:13px;color:#334155;">{{ $item['label'] }}</td>
                    <td style="padding:8px 0;text-align:right;font-size:13px;font-weight:600;">Rp {{ number_format($item['amount'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="border-top:2px solid #d1fae5;">
                    <td style="padding:8px 0;font-weight:700;color:#064e3b;">Total Pendapatan</td>
                    <td style="padding:8px 0;text-align:right;font-weight:700;color:#059669;">Rp {{ number_format($report['total_pendapatan'], 0, ',', '.') }}</td>
                </tr>
            </table>

            {{-- Retur --}}
            @if($report['retur'] > 0)
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:8px 0;font-size:13px;color:#dc2626;">Retur Penjualan</td>
                    <td style="padding:8px 0;text-align:right;font-size:13px;font-weight:600;color:#dc2626;">(Rp {{ number_format($report['retur'], 0, ',', '.') }})</td>
                </tr>
                <tr style="border-top:2px solid #e2e8f0;">
                    <td style="padding:8px 0;font-weight:700;">Pendapatan Bersih</td>
                    <td style="padding:8px 0;text-align:right;font-weight:700;">Rp {{ number_format($report['pendapatan_bersih'], 0, ',', '.') }}</td>
                </tr>
            </table>
            @endif

            {{-- Pengeluaran --}}
            <div style="font-weight:700;font-size:13px;color:#7f1d1d;text-transform:uppercase;margin-bottom:8px;">Pengeluaran</div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                @forelse($report['pengeluaran'] as $exp)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:8px 0;font-size:13px;color:#334155;">{{ $exp['category'] }}</td>
                    <td style="padding:8px 0;text-align:right;font-size:13px;font-weight:600;">Rp {{ number_format($exp['total'], 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="2" style="padding:8px 0;color:#94a3b8;font-size:13px;">Tidak ada pengeluaran.</td></tr>
                @endforelse
                <tr style="border-top:2px solid #fecaca;">
                    <td style="padding:8px 0;font-weight:700;color:#7f1d1d;">Total Pengeluaran</td>
                    <td style="padding:8px 0;text-align:right;font-weight:700;color:#dc2626;">Rp {{ number_format($report['total_pengeluaran'], 0, ',', '.') }}</td>
                </tr>
            </table>

            {{-- Laba Bersih --}}
            <div style="background:{{ $report['laba_bersih'] >= 0 ? '#d1fae5' : '#fef2f2' }};border-radius:8px;padding:16px;text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;font-weight:600;color:#64748b;margin-bottom:4px;">Laba Bersih</div>
                <div style="font-size:28px;font-weight:800;color:{{ $report['laba_bersih'] >= 0 ? '#059669' : '#dc2626' }};">
                    Rp {{ number_format($report['laba_bersih'], 0, ',', '.') }}
                </div>
                <div style="font-size:13px;color:#64748b;margin-top:4px;">
                    Margin: {{ $report['margin'] }}%
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
