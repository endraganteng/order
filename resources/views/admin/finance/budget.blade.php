@extends('admin.layout')

@section('title', 'Budget vs Realisasi')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📊 Budget vs Realisasi</h1>
            <p class="fm-page-subtitle">Monitoring penggunaan dana vs alokasi budget</p>
        </div>
    </div>

    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Bulan</label>
            <input type="month" class="fm-input" id="filterMonth" value="{{ $month }}" style="width:180px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="window.location.href='{{ route('admin.finance.budget') }}?month='+document.getElementById('filterMonth').value">🔍 Tampilkan</button>
    </div>

    @if($budget['total_pendapatan'] > 0)
    <div class="fm-cards" style="margin-bottom:20px;">
        <div class="fm-card green">
            <div class="fm-card-icon">📈</div>
            <div class="fm-card-value fm-money income">Rp {{ number_format($budget['total_pendapatan'], 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pendapatan Bulan Ini</div>
        </div>
    </div>

    <div class="fm-table-wrap" style="padding:20px;">
        @foreach($budget['allocations'] as $alloc)
        <div style="margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e2e8f0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <div>
                    <strong style="font-size:15px;">{{ $alloc['category_name'] }}</strong>
                    <span style="font-size:12px;color:#64748b;margin-left:8px;">{{ $alloc['percentage'] }}% dari pendapatan</span>
                </div>
                <span class="fm-badge {{ $alloc['pct_used'] > 100 ? 'fm-badge-failed' : ($alloc['pct_used'] > 80 ? 'fm-badge-need_review' : 'fm-badge-synced') }}">
                    {{ $alloc['pct_used'] }}% terpakai
                </span>
            </div>
            <div class="fm-progress" style="height:12px;">
                <div class="fm-progress-bar {{ $alloc['pct_used'] > 100 ? 'red' : ($alloc['pct_used'] > 80 ? 'amber' : 'green') }}" style="width:{{ min($alloc['pct_used'], 100) }}%"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:13px;">
                <span>Budget: <strong class="fm-money">Rp {{ number_format($alloc['budget'], 0, ',', '.') }}</strong></span>
                <span>Terpakai: <strong class="fm-money expense">Rp {{ number_format($alloc['realisasi'], 0, ',', '.') }}</strong></span>
                <span>Sisa: <strong class="fm-money {{ $alloc['sisa'] >= 0 ? 'income' : 'expense' }}">Rp {{ number_format($alloc['sisa'], 0, ',', '.') }}</strong></span>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada data pendapatan untuk bulan ini. Lakukan sync terlebih dahulu.</div></div>
    @endif
</div>
@endsection
