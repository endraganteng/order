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

    /* Accordion Styles */
    .pt-accordion-item { border: 1px solid #e2e8f0; border-radius: var(--radius-sm); margin-bottom: 8px; overflow: hidden; }
    .pt-accordion-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: #f8fafc; cursor: pointer; transition: background 0.2s; user-select: none; }
    .pt-accordion-header:hover { background: #f1f5f9; }
    .pt-accordion-left { display: flex; align-items: center; gap: 10px; }
    .pt-accordion-icon { font-size: 11px; color: var(--color-text-secondary); transition: transform 0.2s; }
    .pt-accordion-header.active .pt-accordion-icon { transform: rotate(90deg); }
    .pt-accordion-date { font-size: 14px; font-weight: 600; color: #1e293b; }
    .pt-accordion-right { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 700; }
    .pt-accordion-body { padding: 0 16px 16px; background: white; }
    .pt-badge-warning { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .pt-badge-done { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .pt-alert { padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 13px; }
    .pt-alert-success { background: var(--color-success-bg); border: 1px solid var(--color-success-border); color: var(--color-success); }
    .pt-alert-info { background: var(--color-info-bg); border: 1px solid var(--color-info-border); color: var(--color-info); }



    /* Toast notification */
    .pt-toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
    .pt-toast { background: white; border-radius: var(--radius-sm, 6px); box-shadow: 0 4px 16px rgba(0,0,0,0.15); padding: 12px 18px; font-size: 13px; font-weight: 600; min-width: 260px; max-width: 360px; display: flex; align-items: center; gap: 10px; pointer-events: auto; animation: pt-toast-in 0.25s ease; border-left: 4px solid transparent; }
    .pt-toast-success { border-left-color: #16a34a; color: #166534; }
    .pt-toast-error { border-left-color: #dc2626; color: #991b1b; }
    .pt-toast-fade { animation: pt-toast-out 0.3s ease forwards; }
    @keyframes pt-toast-in { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes pt-toast-out { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(40px); } }

    /* Button loading state */
    .pt-btn:disabled { opacity: 0.65; cursor: not-allowed; }

    @media (max-width: 768px) {
        .pt-filter-bar { flex-direction: column; align-items: stretch; }
        .pt-table { font-size: 12px; }
        .pt-input-nominal { width: 100px; }
        .pt-chart-wrap { height: 200px; }
    }
</style>

{{-- Toast Container --}}
<div class="pt-toast-container" id="ptToastContainer"></div>

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

{{-- Link to Report --}}
<div style="margin-top: 16px; margin-bottom: 8px; display: flex; gap: 10px; align-items: center;">
    <a href="{{ route('admin.profit-tracking.report', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="pt-btn pt-btn-primary">📊 Lihat Grafik & Laporan</a>
    <button type="button" class="pt-btn pt-btn-outline" onclick="document.getElementById('ptSettingsSection').style.display = document.getElementById('ptSettingsSection').style.display === 'none' ? 'block' : 'none'">⚙️ Pengaturan</button>
</div>

{{-- Settings Section (Tanggal Mulai + Saldo Awal) --}}
<div id="ptSettingsSection" class="pt-section" style="display: none;">
    <div class="pt-date-header">⚙️ Pengaturan Profit Tracking</div>

    {{-- Tanggal Mulai --}}
    <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
        <h6 style="font-size: 14px; font-weight: 700; margin-bottom: 8px;">📅 Tanggal Mulai Tracking</h6>
        <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 10px;">
            Data hanya dihitung dan ditampilkan mulai dari tanggal ini. Set 1x saat pertama kali mulai pakai fitur.
        </p>
        <form method="POST" action="{{ route('admin.profit-tracking.settings') }}" class="pt-settings-form">
            @csrf
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="date" name="profit_tracking_start_date" value="{{ $trackingStartDate ?? date('Y-m-d') }}" style="padding: 6px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 13px;">
                <button type="submit" class="pt-btn pt-btn-primary pt-settings-btn">💾 Simpan</button>
            </div>
            @if($trackingStartDate)
            <p style="font-size: 12px; color: var(--color-success); margin-top: 6px;">✓ Tracking dimulai sejak: <strong>{{ \Carbon\Carbon::parse($trackingStartDate)->translatedFormat('d F Y') }}</strong></p>
            @endif
        </form>
    </div>

    {{-- Saldo Awal --}}
    <div>
        <h6 style="font-size: 14px; font-weight: 700; margin-bottom: 8px;">📦 Set Saldo Awal (Opsional)</h6>
        <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 10px;">
            Jika ada stok existing di rak saat mulai tracking, input di sini. Jika stok kosong, tidak perlu diisi.
        </p>
        <form method="POST" action="{{ route('admin.profit-tracking.opening-stock') }}" class="pt-opening-form">
            @csrf
            <div style="margin-bottom: 12px;">
                <label style="font-size: 13px; font-weight: 600;">Tanggal Saldo Awal:</label>
                <input type="date" name="opening_date" value="{{ $trackingStartDate ?? \Carbon\Carbon::yesterday()->format('Y-m-d') }}" style="padding: 6px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 13px;">
            </div>
            <div style="overflow-x: auto;">
                <table class="pt-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Sisa Stok (Qty)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['Jangkrik', 'Ulat Kandang', 'Ulat Hongkong', 'Ulat Jerman', 'Kroto'] as $pidx => $productName)
                        <tr>
                            <td><strong>{{ $productName }}</strong></td>
                            <td>
                                <input type="hidden" name="items[{{ $pidx }}][product_name]" value="{{ $productName }}">
                                <input type="number" step="0.01" min="0" name="items[{{ $pidx }}][sisa_stok_qty]" value="" placeholder="0 (kosong)" class="pt-input-nominal" style="width: 120px;">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 12px; text-align: right;">
                <button type="submit" class="pt-btn pt-btn-success pt-opening-btn">💾 Simpan Saldo Awal</button>
            </div>
        </form>
    </div>
</div>

{{-- Data Table (Accordion) --}}
<div class="pt-section">
    <div class="pt-date-header" style="border-bottom: none; margin-bottom: 0;">📅 Detail Harian</div>
    @forelse($grouped as $date => $items)
    @php
        $dayProfit = $summaries[$date]['total_profit'] ?? 0;
        $dayProfitClass = $dayProfit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative';
        $collapseId = 'collapse-' . str_replace(['-', ' '], '', $date);
        $needsInput = $items->contains(fn($item) => $item->penjualan_nominal <= 0);
    @endphp
    <div class="pt-accordion-item">
        <div class="pt-accordion-header" data-target="#{{ $collapseId }}" onclick="toggleAccordion(this)">
            <div class="pt-accordion-left">
                <span class="pt-accordion-icon">▶</span>
                <span class="pt-accordion-date">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</span>
            </div>
            <div class="pt-accordion-right">
                @if($needsInput)
                <span class="pt-badge-warning">⚠️ Perlu isi penjualan</span>
                @else
                <span class="pt-badge-done">✓ Lengkap</span>
                @endif
                <span class="{{ $dayProfitClass }}">Rp {{ number_format($dayProfit, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="pt-accordion-body" id="{{ $collapseId }}" style="display: none;">
            <form method="POST" action="{{ route('admin.profit-tracking.update-penjualan') }}" class="pt-penjualan-form">
                @csrf
                <input type="hidden" name="tracking_date" value="{{ $date }}">

                <div style="overflow-x: auto;">
                    <table class="pt-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Modal (Sisa Kemarin)</th>
                                <th>Stok Masuk (Qty)</th>
                                <th>Stok Masuk (Rp)</th>
                                <th>Sisa Stok</th>
                                <th>Terjual</th>
                                <th>HPP (Rp)</th>
                                <th>Penjualan (Rp)</th>
                                <th>Profit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $item)
                            @php
                                $sisaKemarin = $carryOver[$date][$item->product_name] ?? null;
                            @endphp
                            <tr>
                                <td><strong>{{ $item->product_name }}</strong></td>
                                <td>{{ $sisaKemarin !== null ? number_format($sisaKemarin, 2, ',', '.') : '-' }}</td>
                                <td>{{ number_format($item->stok_masuk_qty, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format($item->stok_masuk_total, 0, ',', '.') }}</td>
                                <td>{{ number_format($item->sisa_stok_qty, 2, ',', '.') }}</td>
                                <td>{{ $item->terjual_qty > 0 ? number_format($item->terjual_qty, 2, ',', '.') : '-' }}</td>
                                <td>{{ $item->hpp > 0 ? 'Rp ' . number_format($item->hpp, 0, ',', '.') : '-' }}</td>
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
                            <tr class="pt-summary-row">
                                <td><strong>TOTAL</strong></td>
                                <td>-</td>
                                <td>-</td>
                                <td>Rp {{ number_format($summaries[$date]['total_stok_masuk'], 0, ',', '.') }}</td>
                                <td>-</td>
                                <td>-</td>
                                <td>Rp {{ number_format($items->sum('hpp'), 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($summaries[$date]['total_penjualan'], 0, ',', '.') }}</td>
                                <td class="{{ $summaries[$date]['total_profit'] >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                                    Rp {{ number_format($summaries[$date]['total_profit'], 0, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 12px; text-align: right;">
                    <button type="submit" class="pt-btn pt-btn-success pt-submit-btn">💾 Simpan Penjualan</button>
                </div>
            </form>
        </div>
    </div>
    @empty
    <div style="text-align: center; color: var(--color-text-muted); padding: 40px;">
        <p style="font-size: 16px;">📭 Tidak ada data untuk rentang tanggal yang dipilih.</p>
        <p style="font-size: 13px; margin-top: 8px;">Coba ubah filter atau klik "Sync Sekarang" untuk mengambil data terbaru.</p>
    </div>
    @endforelse
</div>

@endsection

@push('scripts')
<script>
// ── Accordion Toggle ─────────────────────────────────────────────────────────
function toggleAccordion(header) {
    var targetId = header.getAttribute('data-target');
    var body = document.querySelector(targetId);
    var isOpen = body.style.display !== 'none';

    body.style.display = isOpen ? 'none' : 'block';
    header.classList.toggle('active', !isOpen);
}

// ── Toast ────────────────────────────────────────────────────────────────────
function ptShowToast(message, type) {
    var container = document.getElementById('ptToastContainer');
    var toast = document.createElement('div');
    var icon = type === 'success' ? '✅' : '❌';
    toast.className = 'pt-toast pt-toast-' + type;
    toast.innerHTML = '<span>' + icon + '</span><span>' + message + '</span>';
    container.appendChild(toast);

    setTimeout(function() {
        toast.classList.add('pt-toast-fade');
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
}

// ── Rupiah helpers ────────────────────────────────────────────────────────────
function formatRupiah(value) {
    var num = value.replace(/[^\d]/g, '');
    if (!num) return '';
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseRupiah(value) {
    return parseInt(value.replace(/[^\d]/g, '')) || 0;
}

// ── Realtime profit calc (col indices: 0=produk,1=modal,2=qty,3=stokRp,4=sisa,5=terjual,6=hpp,7=penjualan,8=profit) ──
document.querySelectorAll('.pt-input-rupiah').forEach(function(input) {
    input.addEventListener('input', function() {
        var raw = parseRupiah(this.value);
        this.value = formatRupiah(this.value);

        // Update hidden raw input
        var idx = this.dataset.idx;
        var form = this.closest('form');
        var hiddenInput = form.querySelector('.pt-input-raw[name="items[' + idx + '][penjualan_nominal]"]');
        if (hiddenInput) hiddenInput.value = raw;

        // Update profit realtime (profit = penjualan - hpp)
        var row = this.closest('tr');
        var hppText = row.querySelectorAll('td')[6].textContent;
        var hpp = parseInt(hppText.replace(/[^\d]/g, '')) || 0;
        var profit = raw - hpp;
        var profitCell = row.querySelectorAll('td')[8];
        profitCell.textContent = 'Rp ' + (profit >= 0 ? '' : '-') + Math.abs(profit).toLocaleString('id-ID');
        profitCell.className = profit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative';

        // Update summary
        updateSummary(this.closest('.pt-section'));
    });
});

function updateSummary(section) {
    var rows = section.querySelectorAll('tbody tr:not(.pt-summary-row)');
    var totalPenjualan = 0;
    var totalHpp = 0;
    rows.forEach(function(row) {
        var cells = row.querySelectorAll('td');
        totalHpp += parseInt(cells[6].textContent.replace(/[^\d]/g, '')) || 0;
        var input = row.querySelector('.pt-input-rupiah');
        totalPenjualan += parseRupiah(input ? input.value : '0');
    });
    var totalProfit = totalPenjualan - totalHpp;
    var summaryRow = section.querySelector('.pt-summary-row');
    if (summaryRow) {
        var cells = summaryRow.querySelectorAll('td');
        cells[7].textContent = 'Rp ' + totalPenjualan.toLocaleString('id-ID');
        cells[8].textContent = 'Rp ' + (totalProfit >= 0 ? '' : '-') + Math.abs(totalProfit).toLocaleString('id-ID');
        cells[8].className = totalProfit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative';
    }
}

// ── AJAX form submit ──────────────────────────────────────────────────────────
document.querySelectorAll('.pt-penjualan-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var btn = form.querySelector('.pt-submit-btn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';

        var formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                ptShowToast(data.message, 'success');
            } else {
                ptShowToast(data.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .catch(function(err) {
            ptShowToast('Gagal menyimpan data. Coba lagi.', 'error');
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
});

// ── AJAX Opening Stock form ───────────────────────────────────────────────────
var openingForm = document.querySelector('.pt-opening-form');
if (openingForm) {
    openingForm.addEventListener('submit', function(e) {
        e.preventDefault();

        var btn = openingForm.querySelector('.pt-opening-btn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';

        var formData = new FormData(openingForm);

        fetch(openingForm.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                ptShowToast(data.message, 'success');
            } else {
                ptShowToast(data.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .catch(function(err) {
            ptShowToast('Gagal menyimpan saldo awal. Coba lagi.', 'error');
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
}

// ── AJAX Settings form (Tanggal Mulai) ────────────────────────────────────────
var settingsForm = document.querySelector('.pt-settings-form');
if (settingsForm) {
    settingsForm.addEventListener('submit', function(e) {
        e.preventDefault();

        var btn = settingsForm.querySelector('.pt-settings-btn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';

        var formData = new FormData(settingsForm);

        fetch(settingsForm.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                ptShowToast(data.message, 'success');
            } else {
                ptShowToast(data.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .catch(function(err) {
            ptShowToast('Gagal menyimpan pengaturan. Coba lagi.', 'error');
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
}
</script>
@endpush
