@extends('admin.layout')

@section('title', 'Profit Tracking Produk')

@section('content')
<style>
    .pt-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .pt-page-title { margin: 0; color: #1e293b; font-size: clamp(24px, 5vw, 32px); font-weight: 800; }
    .pt-filter-bar { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .pt-filter-bar label { font-size: 13px; font-weight: 600; color: var(--color-text-secondary); margin-right: 4px; }
    .pt-filter-bar input[type="date"], .pt-filter-bar select { padding: 6px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 13px; }
    .pt-btn { padding: 8px 16px; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .pt-btn-primary { background: var(--color-primary); color: white; }
    .pt-btn-primary:hover { background: var(--color-primary-dark); }
    .pt-btn-success { background: var(--color-success); color: white; }
    .pt-btn-success:hover { background: #15803d; }
    .pt-btn-outline { background: white; border: 1px solid var(--color-border); color: var(--color-text-secondary); }
    .pt-btn-outline:hover { border-color: var(--color-primary); color: var(--color-primary); }
    .pt-section { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 24px; }
    .pt-date-header { font-size: 15px; font-weight: 700; color: #1e293b; padding: 10px 0 8px; border-bottom: 2px solid var(--color-primary-bg); margin-bottom: 12px; }
    .pt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pt-table th { background: #f8fafc; padding: 10px 12px; text-align: left; font-weight: 600; color: var(--color-text-secondary); border-bottom: 1px solid var(--color-border); }
    .pt-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: var(--color-text); }
    .pt-table tr:hover td { background: #f8fafc; }
    .pt-input-nominal { width: 130px; padding: 5px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 13px; text-align: right; }
    .pt-input-nominal:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 2px rgba(102,126,234,0.15); }
    .pt-profit-positive { color: var(--color-success); font-weight: 700; }
    .pt-profit-negative { color: var(--color-danger); font-weight: 700; }
    .pt-summary-row td { background: #f8fafc; font-weight: 700; border-top: 2px solid var(--color-border); }
    .pt-alert { padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 13px; }
    .pt-alert-success { background: var(--color-success-bg); border: 1px solid var(--color-success-border); color: var(--color-success); }
    .pt-alert-info { background: var(--color-info-bg); border: 1px solid var(--color-info-border); color: var(--color-info); }
    @media (max-width: 768px) {
        .pt-filter-bar { flex-direction: column; align-items: stretch; }
        .pt-table { font-size: 12px; }
        .pt-input-nominal { width: 100px; }
    }
</style>

<div class="pt-page-header">
    <h1 class="pt-page-title">📈 Profit Tracking Produk</h1>
</div>

@if(session('success'))
<div class="pt-alert pt-alert-success">✅ {{ session('success') }}</div>
@endif
@if(session('info'))
<div class="pt-alert pt-alert-info">ℹ️ {{ session('info') }}</div>
@endif
@if($errors->any())
<div class="pt-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;">
    ⚠️ <strong>Error:</strong>
    <ul style="margin:4px 0 0 16px;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Filter Bar --}}
<form method="GET" action="{{ route('admin.profit-tracking.index') }}" class="pt-filter-bar">
    <div>
        <label>Dari:</label>
        <input type="date" name="start_date" value="{{ $startDate }}">
    </div>
    <div>
        <label>Sampai:</label>
        <input type="date" name="end_date" value="{{ $endDate }}">
    </div>
    <div>
        <label>Mode:</label>
        <select name="mode">
            <option value="harian" {{ $mode === 'harian' ? 'selected' : '' }}>Harian</option>
            <option value="mingguan" {{ $mode === 'mingguan' ? 'selected' : '' }}>Mingguan</option>
            <option value="bulanan" {{ $mode === 'bulanan' ? 'selected' : '' }}>Bulanan</option>
        </select>
    </div>
    <button type="submit" class="pt-btn pt-btn-primary">🔍 Filter</button>
</form>

<form method="POST" action="{{ route('admin.profit-tracking.sync') }}" style="display:inline;">
    @csrf
    <input type="hidden" name="start_date" value="{{ $startDate }}">
    <input type="hidden" name="end_date" value="{{ $endDate }}">
    <button type="submit" class="pt-btn pt-btn-outline">🔄 Sync Sekarang</button>
</form>

{{-- Data Table --}}
@forelse($grouped as $date => $items)
<div class="pt-section">
    <div class="pt-date-header">📅 {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</div>

    <form method="POST" action="{{ route('admin.profit-tracking.update-penjualan') }}">
        @csrf
        <input type="hidden" name="tracking_date" value="{{ $date }}">

        <div style="overflow-x: auto;">
            <table class="pt-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Stok Masuk (Qty)</th>
                        <th>Stok Masuk (Rp)</th>
                        <th>Sisa Stok</th>
                        <th>Penjualan (Rp)</th>
                        <th>Profit (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $idx => $item)
                    <tr>
                        <td><strong>{{ $item->product_name }}</strong></td>
                        <td>{{ number_format($item->stok_masuk_qty, 2, ',', '.') }}</td>
                        <td>Rp {{ number_format($item->stok_masuk_total, 0, ',', '.') }}</td>
                        <td>{{ number_format($item->sisa_stok_qty, 2, ',', '.') }}</td>
                        <td>
                            <input type="hidden" name="items[{{ $idx }}][product_name]" value="{{ $item->product_name }}">
                            <input type="hidden" name="items[{{ $idx }}][penjualan_nominal]" value="{{ $item->penjualan_nominal }}" class="pt-input-raw">
                            <div style="position:relative;">
                                <span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);font-size:12px;color:#64748b;">Rp</span>
                                <input type="text" inputmode="numeric"
                                    value="{{ $item->penjualan_nominal > 0 ? number_format($item->penjualan_nominal, 0, ',', '.') : '' }}"
                                    class="pt-input-nominal pt-input-rupiah"
                                    placeholder="0"
                                    style="padding-left:28px;"
                                    data-idx="{{ $idx }}">
                            </div>
                        </td>
                        <td class="{{ $item->profit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                            Rp {{ number_format($item->profit, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                    {{-- Summary Row --}}
                    <tr class="pt-summary-row">
                        <td><strong>TOTAL</strong></td>
                        <td>-</td>
                        <td>Rp {{ number_format($summaries[$date]['total_stok_masuk'], 0, ',', '.') }}</td>
                        <td>-</td>
                        <td>Rp {{ number_format($summaries[$date]['total_penjualan'], 0, ',', '.') }}</td>
                        <td class="{{ $summaries[$date]['total_profit'] >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                            Rp {{ number_format($summaries[$date]['total_profit'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 12px; text-align: right;">
            <button type="submit" class="pt-btn pt-btn-success">💾 Simpan Penjualan</button>
        </div>
    </form>
</div>
@empty
<div class="pt-section" style="text-align: center; color: var(--color-text-muted); padding: 40px;">
    <p style="font-size: 16px;">📭 Tidak ada data untuk rentang tanggal yang dipilih.</p>
    <p style="font-size: 13px; margin-top: 8px;">Coba ubah filter atau klik "Sync Sekarang" untuk mengambil data terbaru.</p>
</div>
@endforelse

@endsection

@push('scripts')
<script>
function formatRupiah(value) {
    var num = value.replace(/[^\d]/g, '');
    if (!num) return '';
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseRupiah(value) {
    return parseInt(value.replace(/[^\d]/g, '')) || 0;
}

document.querySelectorAll('.pt-input-rupiah').forEach(function(input) {
    input.addEventListener('input', function() {
        var raw = parseRupiah(this.value);
        this.value = formatRupiah(this.value);

        // Update hidden raw input
        var idx = this.dataset.idx;
        var form = this.closest('form');
        var hiddenInput = form.querySelector('.pt-input-raw[name="items[' + idx + '][penjualan_nominal]"]');
        if (hiddenInput) hiddenInput.value = raw;

        // Update profit realtime
        var row = this.closest('tr');
        var stokMasukText = row.querySelectorAll('td')[2].textContent;
        var stokMasuk = parseInt(stokMasukText.replace(/[^\d]/g, '')) || 0;
        var profit = raw - stokMasuk;
        var profitCell = row.querySelectorAll('td')[5];
        profitCell.textContent = 'Rp ' + (profit >= 0 ? '' : '-') + Math.abs(profit).toLocaleString('id-ID');
        profitCell.className = profit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative';

        // Update summary
        updateSummary(this.closest('.pt-section'));
    });
});

function updateSummary(section) {
    var rows = section.querySelectorAll('tbody tr:not(.pt-summary-row)');
    var totalPenjualan = 0;
    var totalStokMasuk = 0;
    rows.forEach(function(row) {
        var cells = row.querySelectorAll('td');
        totalStokMasuk += parseInt(cells[2].textContent.replace(/[^\d]/g, '')) || 0;
        var input = row.querySelector('.pt-input-rupiah');
        totalPenjualan += parseRupiah(input ? input.value : '0');
    });
    var totalProfit = totalPenjualan - totalStokMasuk;
    var summaryRow = section.querySelector('.pt-summary-row');
    if (summaryRow) {
        var cells = summaryRow.querySelectorAll('td');
        cells[4].textContent = 'Rp ' + totalPenjualan.toLocaleString('id-ID');
        cells[5].textContent = 'Rp ' + (totalProfit >= 0 ? '' : '-') + Math.abs(totalProfit).toLocaleString('id-ID');
        cells[5].className = totalProfit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative';
    }
}
</script>
@endpush
