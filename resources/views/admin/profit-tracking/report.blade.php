@extends('admin.layout')

@section('title', 'Laporan Profit Tracking')

@section('content')
<style>
    .pt-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .pt-page-title { margin: 0; color: #1e293b; font-size: clamp(24px, 5vw, 32px); font-weight: 800; }
    .pt-filter-bar { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .pt-filter-bar label { font-size: 13px; font-weight: 600; color: var(--color-text-secondary); margin-right: 4px; }
    .pt-filter-bar input[type="date"], .pt-filter-bar select { padding: 6px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 13px; }
    .pt-btn { padding: 8px 16px; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .pt-btn-primary { background: var(--color-primary); color: white; }
    .pt-btn-primary:hover { background: var(--color-primary-dark); }
    .pt-btn-outline { background: white; border: 1px solid var(--color-border); color: var(--color-text-secondary); }
    .pt-btn-outline:hover { border-color: var(--color-primary); color: var(--color-primary); }
    .pt-section { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 24px; }
    .pt-section-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .pt-date-header { font-size: 15px; font-weight: 700; color: #1e293b; padding: 10px 0 8px; border-bottom: 2px solid var(--color-primary-bg); margin-bottom: 12px; }
    .pt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pt-table th { background: #f8fafc; padding: 10px 12px; text-align: left; font-weight: 600; color: var(--color-text-secondary); border-bottom: 1px solid var(--color-border); }
    .pt-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: var(--color-text); }
    .pt-table tr:hover td { background: #f8fafc; }
    .pt-profit-positive { color: var(--color-success); font-weight: 700; }
    .pt-profit-negative { color: var(--color-danger); font-weight: 700; }
    .pt-summary-row td { background: #f8fafc; font-weight: 700; border-top: 2px solid var(--color-border); }
    
    /* Comparison Cards Grid */
    .pt-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .pt-card { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px; display: flex; flex-direction: column; position: relative; overflow: hidden; }
    .pt-card-label { font-size: 13px; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 8px; }
    .pt-card-value { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
    .pt-card-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 700; width: fit-content; }
    .pt-badge-up { background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success-border); }
    .pt-badge-down { background: var(--color-danger-bg); color: var(--color-danger); border: 1px solid var(--color-danger-border); }
    .pt-badge-neutral { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .pt-card-subtext { font-size: 11px; color: var(--color-text-muted); margin-top: 8px; }
    
    .pt-chart-container { position: relative; height: 350px; width: 100%; }

    /* Accordion Styles */
    .pt-accordion-item { border: 1px solid #e2e8f0; border-radius: var(--radius-sm); margin-bottom: 8px; overflow: hidden; }
    .pt-accordion-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: #f8fafc; cursor: pointer; transition: background 0.2s; user-select: none; }
    .pt-accordion-header:hover { background: #f1f5f9; }
    .pt-accordion-left { display: flex; align-items: center; gap: 10px; }
    .pt-accordion-icon { font-size: 11px; color: var(--color-text-secondary); transition: transform 0.2s; }
    .pt-accordion-header.active .pt-accordion-icon { transform: rotate(90deg); }
    .pt-accordion-date { font-size: 14px; font-weight: 600; color: #1e293b; }
    .pt-accordion-right { font-size: 14px; font-weight: 700; }
    .pt-accordion-body { padding: 0 16px 16px; background: white; }

    @media (max-width: 768px) {
        .pt-filter-bar { flex-direction: column; align-items: stretch; }
        .pt-table { font-size: 12px; }
        .pt-chart-container { height: 260px; }
    }
</style>

<div class="pt-page-header">
    <h1 class="pt-page-title">📊 Laporan Profit Tracking</h1>
    <a href="{{ route('admin.profit-tracking.index', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="pt-btn pt-btn-outline">
        ⬅️ Kembali ke Input
    </a>
</div>

{{-- Filter Bar --}}
<form method="GET" action="{{ route('admin.profit-tracking.report') }}" class="pt-filter-bar">
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

{{-- Comparison Card --}}
<div class="pt-cards-grid">
    <div class="pt-card">
        <div class="pt-card-label">Perbandingan Profit Periode Ini vs Sebelumnya</div>
        <div class="pt-card-value">
            Rp {{ number_format($comparison['current_total'], 0, ',', '.') }}
        </div>
        
        @if ($comparison['change_percent'] > 0)
            <div class="pt-card-badge pt-badge-up">
                ⬆️ +{{ number_format($comparison['change_percent'], 1, ',', '.') }}%
            </div>
        @elseif ($comparison['change_percent'] < 0)
            <div class="pt-card-badge pt-badge-down">
                ⬇️ {{ number_format($comparison['change_percent'], 1, ',', '.') }}%
            </div>
        @else
            <div class="pt-card-badge pt-badge-neutral">
                ➡️ 0%
            </div>
        @endif
        
        <div class="pt-card-subtext">
            Periode Sebelumnya: <strong>Rp {{ number_format($comparison['previous_total'], 0, ',', '.') }}</strong> 
            ({{ \Carbon\Carbon::parse($comparison['prev_start'])->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($comparison['prev_end'])->format('d/m/Y') }})
        </div>
    </div>
</div>

{{-- Chart Section --}}
<div class="pt-section">
    <div class="pt-section-title">📈 Tren Profit per Produk</div>
    <div class="pt-chart-container">
        <canvas id="profitTrendChart"></canvas>
    </div>
</div>

{{-- Product Summary Table --}}
<div class="pt-section">
    <div class="pt-section-title">📦 Ringkasan per Produk</div>
    <div style="overflow-x: auto;">
        <table class="pt-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Hari Tertrack</th>
                    <th>Total Stok Masuk (Qty)</th>
                    <th>Total Stok Masuk (Rp)</th>
                    <th>Total Penjualan</th>
                    <th>Total Profit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $sumQty = 0;
                    $sumStokRp = 0;
                    $sumPenjualan = 0;
                    $sumProfit = 0;
                @endphp
                @forelse($productSummary as $summary)
                    @php
                        $sumQty += $summary['total_stok_masuk_qty'];
                        $sumStokRp += $summary['total_stok_masuk_rp'];
                        $sumPenjualan += $summary['total_penjualan'];
                        $sumProfit += $summary['total_profit'];
                    @endphp
                    <tr>
                        <td><strong>{{ $summary['product_name'] }}</strong></td>
                        <td>{{ $summary['days_tracked'] }} Hari</td>
                        <td>{{ number_format($summary['total_stok_masuk_qty'], 2, ',', '.') }}</td>
                        <td>Rp {{ number_format($summary['total_stok_masuk_rp'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($summary['total_penjualan'], 0, ',', '.') }}</td>
                        <td class="{{ $summary['total_profit'] >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                            Rp {{ number_format($summary['total_profit'], 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--color-text-muted);">Tidak ada data ringkasan produk.</td>
                    </tr>
                @endforelse
                @if (count($productSummary) > 0)
                    <tr style="background: #f8fafc; font-weight: 700; border-top: 2px solid var(--color-border);">
                        <td>TOTAL</td>
                        <td>-</td>
                        <td>{{ number_format($sumQty, 2, ',', '.') }}</td>
                        <td>Rp {{ number_format($sumStokRp, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($sumPenjualan, 0, ',', '.') }}</td>
                        <td class="{{ $sumProfit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                            Rp {{ number_format($sumProfit, 0, ',', '.') }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- Daily Detail Section (Accordion per Hari) --}}
<div class="pt-section">
    <div class="pt-section-title">📅 Detail Harian</div>
    @forelse($grouped as $date => $items)
    @php
        $dayProfit = $dailySummaries[$date]['total_profit'] ?? 0;
        $dayProfitClass = $dayProfit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative';
        $collapseId = 'collapse-' . str_replace(['-', ' '], '', $date);
    @endphp
    <div class="pt-accordion-item">
        <div class="pt-accordion-header" data-target="#{{ $collapseId }}" onclick="toggleAccordion(this)">
            <div class="pt-accordion-left">
                <span class="pt-accordion-icon">▶</span>
                <span class="pt-accordion-date">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</span>
            </div>
            <div class="pt-accordion-right">
                <span class="{{ $dayProfitClass }}">Rp {{ number_format($dayProfit, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="pt-accordion-body" id="{{ $collapseId }}" style="display: none;">
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
                        @foreach($items as $item)
                        @php
                            $sisaKemarin = $carryOver[$date][$item->product_name] ?? null;
                        @endphp
                        <tr>
                            <td><strong>{{ $item->product_name }}</strong></td>
                            <td>{{ $sisaKemarin !== null ? number_format($sisaKemarin, 2, ',', '.') : '-' }}</td>
                            <td>{{ number_format($item->stok_masuk_qty, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($item->stok_masuk_total, 0, ',', '.') }}</td>
                            <td>{{ $item->sisa_stok_qty !== null ? number_format($item->sisa_stok_qty, 2, ',', '.') : '-' }}</td>
                            <td>{{ $item->terjual_qty > 0 ? number_format($item->terjual_qty, 2, ',', '.') : '-' }}</td>
                            <td>{{ $item->hpp > 0 ? 'Rp ' . number_format($item->hpp, 0, ',', '.') : '-' }}</td>
                            <td>Rp {{ number_format($item->penjualan_nominal, 0, ',', '.') }}</td>
                            <td class="{{ $item->profit >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                                Rp {{ number_format($item->profit, 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                        <tr class="pt-summary-row">
                            <td><strong>TOTAL</strong></td>
                            <td>-</td>
                            <td>-</td>
                            <td>Rp {{ number_format($dailySummaries[$date]['total_stok_masuk'] ?? 0, 0, ',', '.') }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>Rp {{ number_format($items->sum('hpp'), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($dailySummaries[$date]['total_penjualan'] ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ ($dailySummaries[$date]['total_profit'] ?? 0) >= 0 ? 'pt-profit-positive' : 'pt-profit-negative' }}">
                                Rp {{ number_format($dailySummaries[$date]['total_profit'] ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @empty
    <div style="text-align: center; color: var(--color-text-muted); padding: 40px;">
        <p style="font-size: 16px;">📭 Tidak ada data detail harian untuk rentang tanggal yang dipilih.</p>
    </div>
    @endforelse
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function toggleAccordion(header) {
        var targetId = header.getAttribute('data-target');
        var body = document.querySelector(targetId);
        var isOpen = body.style.display !== 'none';
        
        body.style.display = isOpen ? 'none' : 'block';
        header.classList.toggle('active', !isOpen);
    }

    document.addEventListener("DOMContentLoaded", function () {
        var rawChartData = @json($chartData);
        
        // Product color map
        var colors = {
            'Jangkrik': '#6366f1',
            'Ulat Kandang': '#f59e0b',
            'Ulat Hongkong': '#10b981',
            'Ulat Jerman': '#ef4444',
            'Kroto': '#8b5cf6'
        };
        var defaultColor = '#94a3b8';

        // Extract labels (unique sorted dates) and datasets
        var dates = [...new Set(rawChartData.map(item => item.date))].sort();
        
        // Group data by product
        var productData = {};
        rawChartData.forEach(function (item) {
            if (!productData[item.product_name]) {
                productData[item.product_name] = {};
            }
            productData[item.product_name][item.date] = parseFloat(item.profit) || 0;
        });

        var datasets = Object.keys(productData).map(function (product) {
            var dataPoints = dates.map(function (date) {
                return productData[product][date] !== undefined ? productData[product][date] : 0;
            });
            var color = colors[product] || defaultColor;
            return {
                label: product,
                data: dataPoints,
                borderColor: color,
                backgroundColor: color + '1a', // 10% opacity
                borderWidth: 2,
                tension: 0.15,
                fill: false
            };
        });

        // Format label date to localized format
        var formattedLabels = dates.map(function (dateStr) {
            try {
                var d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
            } catch (e) {
                return dateStr;
            }
        });

        var ctx = document.getElementById('profitTrendChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { size: 10 },
                            callback: function (value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    });
</script>
@endpush
