@extends('admin.layout')

@section('title', 'Laporan Keuangan Bulanan')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📈 Laporan Keuangan Bulanan</h1>
            <p class="fm-page-subtitle">Rekap pendapatan dan pengeluaran per bulan</p>
        </div>
        <a href="{{ route('admin.finance.report.export', ['month' => $month]) }}" class="fm-btn fm-btn-success">📥 Export CSV</a>
    </div>

    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Bulan</label>
            <input type="month" class="fm-input" id="filterMonth" value="{{ $month }}" style="width:180px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="window.location.href='{{ route('admin.finance.report.monthly') }}?month='+document.getElementById('filterMonth').value">🔍 Tampilkan</button>
    </div>

    {{-- Summary --}}
    <div class="fm-cards">
        <div class="fm-card green">
            <div class="fm-card-value fm-money income">Rp {{ number_format($report['summary']['total_pendapatan'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pendapatan</div>
        </div>
        <div class="fm-card red">
            <div class="fm-card-value fm-money expense">Rp {{ number_format($report['summary']['total_pengeluaran'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pengeluaran</div>
        </div>
        <div class="fm-card">
            <div class="fm-card-value fm-money income">Rp {{ number_format($report['summary']['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Pendapatan Bersih</div>
        </div>
        <div class="fm-card amber">
            <div class="fm-card-value">{{ $report['summary']['jumlah_shift'] ?? 0 }}</div>
            <div class="fm-card-label">Total Shift</div>
        </div>
    </div>

    {{-- Daily Table --}}
    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Tanggal</th><th style="text-align:right">Tunai</th><th style="text-align:right">QRIS</th><th style="text-align:right">Pendapatan</th><th style="text-align:right">Pengeluaran</th><th style="text-align:right">Bersih</th><th>Shift</th></tr></thead>
            <tbody>
                @foreach($report['daily'] as $d)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($d['tanggal'])->format('d/m/Y') }}</td>
                    <td style="text-align:right">Rp {{ number_format($d['penjualan_tunai'], 0, ',', '.') }}</td>
                    <td style="text-align:right">Rp {{ number_format($d['penjualan_qris'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money income">Rp {{ number_format($d['total_pendapatan'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money expense">Rp {{ number_format($d['total_pengeluaran'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money income">Rp {{ number_format($d['pendapatan_bersih'], 0, ',', '.') }}</td>
                    <td>{{ $d['jumlah_shift'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($report['daily']) === 0)
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada data untuk bulan ini. Lakukan sync terlebih dahulu.</div></div>
    @endif
</div>
@endsection
