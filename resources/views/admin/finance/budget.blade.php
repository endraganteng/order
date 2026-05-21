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

    {{-- Toggle mode --}}
    <div class="fm-filter" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">
        <div class="fm-form-group" style="margin:0;">
            <label class="fm-label">Mode</label>
            <div style="display:flex;gap:6px;">
                <button type="button" class="fm-btn fm-btn-sm {{ $mode === 'month' ? 'fm-btn-primary' : 'fm-btn-outline' }}" onclick="switchMode('month')">📅 Per Bulan</button>
                <button type="button" class="fm-btn fm-btn-sm {{ $mode === 'range' ? 'fm-btn-primary' : 'fm-btn-outline' }}" onclick="switchMode('range')">📆 Range Tanggal</button>
            </div>
        </div>
    </div>

    {{-- Filter mode bulan --}}
    <div class="fm-filter" id="filterMonthBox" style="{{ $mode === 'month' ? '' : 'display:none;' }}">
        <div class="fm-form-group">
            <label class="fm-label">Bulan</label>
            <input type="month" class="fm-input" id="filterMonth" value="{{ $month }}" style="width:180px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="applyMonth()">🔍 Tampilkan</button>
    </div>

    {{-- Filter mode range --}}
    <div class="fm-filter" id="filterRangeBox" style="{{ $mode === 'range' ? '' : 'display:none;' }}">
        <div class="fm-form-group">
            <label class="fm-label">Dari Tanggal</label>
            <input type="date" class="fm-input" id="filterFrom" value="{{ $from }}" style="width:180px;">
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Sampai Tanggal</label>
            <input type="date" class="fm-input" id="filterTo" value="{{ $to }}" style="width:180px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="applyRange()">🔍 Tampilkan</button>
    </div>

    @if($mode === 'range')
    <div class="fm-alert fm-alert-info" style="margin-bottom:14px;">
        ℹ️ Mode <strong>Range Tanggal</strong>: pendapatan & realisasi dihitung hanya untuk periode <strong>{{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}</strong> ({{ \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1 }} hari). Cocok untuk onboarding mid-bulan atau evaluasi periode pendek.
    </div>
    @endif

    @if($budget['total_pendapatan'] > 0)
    <div class="fm-cards" style="margin-bottom:20px;">
        <div class="fm-card green">
            <div class="fm-card-icon">📈</div>
            <div class="fm-card-value fm-money income">Rp {{ number_format($budget['total_pendapatan'], 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pendapatan {{ $mode === 'range' ? 'Periode Ini' : 'Bulan Ini' }}</div>
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
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada data pendapatan untuk periode ini. Lakukan sync terlebih dahulu.</div></div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const baseUrl = '{{ route('admin.finance.budget') }}';

function switchMode(mode) {
    const monthBox = document.getElementById('filterMonthBox');
    const rangeBox = document.getElementById('filterRangeBox');
    if (mode === 'range') {
        monthBox.style.display = 'none';
        rangeBox.style.display = '';
    } else {
        monthBox.style.display = '';
        rangeBox.style.display = 'none';
    }
}

function applyMonth() {
    const m = document.getElementById('filterMonth').value;
    if (!m) return;
    window.location.href = baseUrl + '?mode=month&month=' + encodeURIComponent(m);
}

function applyRange() {
    const f = document.getElementById('filterFrom').value;
    const t = document.getElementById('filterTo').value;
    if (!f || !t) { alert('Isi tanggal dari dan sampai.'); return; }
    if (f > t) { alert('Tanggal dari harus lebih kecil atau sama dengan tanggal sampai.'); return; }
    window.location.href = baseUrl + '?mode=range&from=' + encodeURIComponent(f) + '&to=' + encodeURIComponent(t);
}
</script>
@endpush
