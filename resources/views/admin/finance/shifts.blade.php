@extends('admin.layout')

@section('title', 'Detail Shift Kasir')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">🕐 Detail Shift Kasir</h1>
            <p class="fm-page-subtitle">Data shift dari API yang sudah tersinkronisasi</p>
        </div>
    </div>

    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Dari</label>
            <input type="date" class="fm-input" id="filterFrom" value="{{ request('from', date('Y-m-01')) }}" style="width:160px;">
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Sampai</label>
            <input type="date" class="fm-input" id="filterTo" value="{{ request('to', date('Y-m-d')) }}" style="width:160px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="applyFilter()">🔍 Filter</button>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Tanggal</th><th>Shift</th><th>Loket</th><th>Kasir</th><th style="text-align:right">Tunai</th><th style="text-align:right">QRIS</th><th style="text-align:right">Pengeluaran</th><th style="text-align:right">Selisih</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($shifts as $s)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($s['tanggal'])->format('d/m/Y') }}</td>
                    <td>Shift {{ $s['shift_number'] }}</td>
                    <td>{{ $s['loket'] ?? '—' }}</td>
                    <td>{{ $s['kasir'] ?? '—' }}</td>
                    <td style="text-align:right">Rp {{ number_format($s['penjualan_tunai'], 0, ',', '.') }}</td>
                    <td style="text-align:right">Rp {{ number_format($s['penjualan_qris'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money expense">Rp {{ number_format($s['total_pengeluaran'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money {{ $s['selisih'] < 0 ? 'expense' : 'income' }}">Rp {{ number_format($s['selisih'], 0, ',', '.') }}</td>
                    <td><span class="fm-badge fm-badge-{{ $s['status'] === 'submitted' ? 'synced' : 'draft' }}">{{ $s['status'] ?? '—' }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($shifts) === 0)
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada data shift. Lakukan sync terlebih dahulu.</div></div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function applyFilter(){
    const from=document.getElementById('filterFrom').value;
    const to=document.getElementById('filterTo').value;
    window.location.href='{{ route("admin.finance.shifts") }}?from='+from+'&to='+to;
}
</script>
@endpush
