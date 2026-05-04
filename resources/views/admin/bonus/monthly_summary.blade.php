@extends('admin.layout')

@section('title', '📋 Rekap Bonus Bulanan')

@section('content')
@php
    $month = $month ?? date('Y-m');
    $waiters = $waiters ?? [];
    $summaries = $summaries ?? [];
    $config = $config ?? [];

    $workingDays = (int) ($config['working_days_per_month'] ?? 26);
    $dailyMaxPoints = (int) ($config['daily_max_points'] ?? 20);
    $perfectDayBonus = (int) ($config['perfect_day_bonus'] ?? 5);
    $dailyMaxWithPerfect = $dailyMaxPoints + $perfectDayBonus; // 25
    $monthlyServiceMax = 5 * $workingDays; // 130
    $monthlySalesMax = 5 * $workingDays; // 130
    $theoreticalMax = ($dailyMaxWithPerfect * $workingDays) + $monthlyServiceMax + $monthlySalesMax; // 910

    $toArray = function ($value) {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        return [];
    };

    $getValue = function ($item, $key, $default = null) {
        if (is_array($item)) {
            return $item[$key] ?? $default;
        }
        if (is_object($item)) {
            return $item->{$key} ?? $default;
        }
        return $default;
    };

    $summariesByWaiter = [];
    foreach ($summaries as $wid => $summary) {
        $summariesByWaiter[$wid] = is_array($summary) ? $summary : (array) $summary;
    }

    $rows = [];
    $totalBonus = 0;
    $statusCounts = ['draft' => 0, 'finalized' => 0, 'overridden' => 0];

    foreach ($waiters as $waiter) {
        $waiterId = (string) $getValue($waiter, 'id', '');
        $waiterName = (string) $getValue($waiter, 'name', '-');
        $waiterRole = (string) $getValue($waiter, 'role', '-');

        $summary = $summariesByWaiter[$waiterId] ?? [];

        $netPoints = (float) ($summary['net_points'] ?? 0);
        $pointPercentage = (float) ($summary['points_percentage'] ?? 0);
        if ($pointPercentage == 0 && $netPoints > 0 && $theoreticalMax > 0) {
            $pointPercentage = max(0, min(100, ($netPoints / $theoreticalMax) * 100));
        }

        $status = (string) ($summary['status'] ?? 'draft');
        if (!array_key_exists($status, $statusCounts)) {
            $status = 'draft';
        }

        $row = [
            'waiter_id' => $waiterId,
            'waiter_name' => $waiterName,
            'waiter_role' => $waiterRole,
            'net_points' => (float) $netPoints,
            'point_percentage' => (float) $pointPercentage,
            'points_bonus' => (float) ($summary['points_bonus'] ?? 0),
            'sales_percentage' => (float) ($summary['sales_percentage'] ?? 0),
            'sales_bonus' => (float) ($summary['sales_bonus'] ?? 0),
            'total_bonus' => (float) ($summary['total_bonus'] ?? 0),
            'status' => $status,
            'monthly_service_percentage' => (int) ($summary['monthly_service_percentage'] ?? 0),
            'monthly_sales_percentage' => (int) ($summary['monthly_sales_percentage'] ?? 0),
            'service_points' => (int) ($summary['service_points'] ?? 0),
            'sales_points' => (int) ($summary['sales_points'] ?? 0),
        ];

        $rows[] = $row;
        $totalBonus += $row['total_bonus'];
        $statusCounts[$status]++;
    }

    $totalKaryawan = count($rows);
    $avgBonus = $totalKaryawan > 0 ? ($totalBonus / $totalKaryawan) : 0;

    $formatRp = function ($number) {
        return 'Rp ' . number_format((float) $number, 0, ',', '.');
    };

    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
    $currentMonth = date('Y-m');
@endphp

    <div class="monthly-summary-page">
        <div class="page-head">
            <div>
                <h2>📋 Rekap Bonus Bulanan</h2>
                <p>Periode: <strong>{{ date('F Y', strtotime($month . '-01')) }}</strong></p>
            </div>
            <div class="month-nav">
                <a href="?month={{ $prevMonth }}" class="nav-btn btn-secondary" aria-label="Bulan sebelumnya">&larr;</a>
                <input type="month" id="monthPicker" value="{{ $month }}">
                <a href="?month={{ $nextMonth }}" class="nav-btn btn-secondary" aria-label="Bulan berikutnya">&rarr;</a>
                <a href="?month={{ $currentMonth }}" class="btn btn-primary" style="margin-left: 8px;">Bulan Ini</a>
            </div>
        </div>

    <div class="kpi-grid">
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Total Karyawan</div>
            <div class="kpi-value" id="kpiTotalKaryawan">{{ $totalKaryawan }}</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-label">Total Bonus Dikeluarkan (Rp)</div>
            <div class="kpi-value" id="kpiTotalBonus">{{ $formatRp($totalBonus) }}</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-label">Rata-rata Bonus</div>
            <div class="kpi-value" id="kpiAvgBonus">{{ $formatRp($avgBonus) }}</div>
        </div>
        <div class="kpi-card kpi-amber">
            <div class="kpi-label">Status</div>
            <div class="kpi-value" id="kpiStatusCount">
                {{ ($totalKaryawan > 0 && $statusCounts['finalized'] === $totalKaryawan) ? 'Finalized' : 'Draft' }}
            </div>
        </div>
    </div>

    {{-- MONTHLY SCORING SECTION --}}
    <div class="monthly-scoring-section">
        <div class="scoring-section-header">
            <h3>📝 Penilaian Bulanan — Pelayanan &amp; Penjualan</h3>
            <p class="scoring-section-desc">Input persentase (0-100%) untuk setiap karyawan. Poin dihitung: persentase × 5 × {{ $workingDays }} hari kerja. Max per kategori: {{ $monthlyServiceMax }} poin.</p>
        </div>
        <div class="scoring-table-wrap">
            <table class="scoring-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th style="width: 160px;">Pelayanan %</th>
                        <th style="width: 100px;">→ Poin</th>
                        <th style="width: 160px;">Penjualan %</th>
                        <th style="width: 100px;">→ Poin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $idx => $row)
                        <tr data-waiter-id="{{ $row['waiter_id'] }}">
                            <td>
                                <strong>{{ $row['waiter_name'] }}</strong>
                                <div class="sub">{{ $row['waiter_role'] }}</div>
                            </td>
                            <td>
                                <div class="pct-input-wrap">
                                    <input type="number" class="pct-input service-pct-input" data-waiter-id="{{ $row['waiter_id'] }}" min="0" max="100" value="{{ $row['monthly_service_percentage'] }}" placeholder="0">
                                    <span class="pct-suffix">%</span>
                                </div>
                            </td>
                            <td>
                                <span class="points-preview service-points-preview" data-waiter-id="{{ $row['waiter_id'] }}">{{ $row['service_points'] }}</span> <span class="sub">/{{ $monthlyServiceMax }}</span>
                            </td>
                            <td>
                                <div class="pct-input-wrap">
                                    <input type="number" class="pct-input sales-pct-input" data-waiter-id="{{ $row['waiter_id'] }}" min="0" max="100" value="{{ $row['monthly_sales_percentage'] }}" placeholder="0">
                                    <span class="pct-suffix">%</span>
                                </div>
                            </td>
                            <td>
                                <span class="points-preview sales-points-preview" data-waiter-id="{{ $row['waiter_id'] }}">{{ $row['sales_points'] }}</span> <span class="sub">/{{ $monthlySalesMax }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="action-row">
        <button type="button" class="btn btn-primary" id="btnCalculateAll">Hitung Semua</button>
        <button type="button" class="btn btn-success" id="btnFinalizeAll">Finalisasi Semua</button>
    </div>

    <div class="table-wrap desktop-only">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Total Poin</th>
                    <th>Persen Poin</th>
                    <th>Bonus Poin Rp</th>
                    <th>Sales Target %</th>
                    <th>Bonus Sales Rp</th>
                    <th>Total Bonus Rp</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="summaryTableBody">
                @foreach($rows as $row)
                    @php
                        $rowClass = 'row-lt-red';
                        if ($row['point_percentage'] >= 80) {
                            $rowClass = 'row-lt-green';
                        } elseif ($row['point_percentage'] >= 60) {
                            $rowClass = 'row-lt-yellow';
                        }
                    @endphp
                    <tr class="summary-row {{ $rowClass }}" data-waiter-id="{{ $row['waiter_id'] }}">
                        <td>
                            <strong>{{ $row['waiter_name'] }}</strong>
                            <div class="sub">{{ $row['waiter_role'] }}</div>
                        </td>
                        <td class="cell-net-points">{{ number_format($row['net_points'], 0, ',', '.') }}</td>
                        <td class="cell-point-percentage"><span class="{{ $row['point_percentage'] >= 80 ? 'pct-high' : ($row['point_percentage'] >= 70 ? 'pct-med' : ($row['point_percentage'] >= 60 ? 'pct-low' : 'pct-fail')) }}">{{ number_format($row['point_percentage'], 1, ',', '.') }}%</span></td>
                        <td class="cell-points-bonus">{{ $formatRp($row['points_bonus']) }}</td>
                        <td class="cell-sales-percentage"><span class="{{ $row['sales_percentage'] >= 80 ? 'pct-high' : ($row['sales_percentage'] >= 70 ? 'pct-med' : ($row['sales_percentage'] >= 60 ? 'pct-low' : 'pct-fail')) }}">{{ number_format($row['sales_percentage'], 1, ',', '.') }}%</span></td>
                        <td class="cell-sales-bonus">{{ $formatRp($row['sales_bonus']) }}</td>
                        <td class="cell-total-bonus"><strong>{{ $formatRp($row['total_bonus']) }}</strong></td>
                        <td class="cell-status">{!! $row['status'] === 'finalized' ? '<span class="badge badge-finalized">finalized</span>' : ($row['status'] === 'overridden' ? '<span class="badge badge-overridden">overridden</span>' : '<span class="badge badge-draft">draft</span>') !!}</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-info btn-sm btn-calculate" data-id="{{ $row['waiter_id'] }}">Hitung</button>
                                <button type="button" class="btn btn-warning btn-sm btn-override" data-id="{{ $row['waiter_id'] }}" data-name="{{ $row['waiter_name'] }}">Override</button>
                                <button type="button" class="btn btn-success btn-sm btn-finalize" data-id="{{ $row['waiter_id'] }}" {{ $row['status'] === 'finalized' ? 'disabled' : '' }}>Finalisasi</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mobile-list mobile-only" id="mobileSummaryList">
        @foreach($rows as $row)
            @php
                $rowClass = 'row-lt-red';
                if ($row['point_percentage'] >= 80) {
                    $rowClass = 'row-lt-green';
                } elseif ($row['point_percentage'] >= 60) {
                    $rowClass = 'row-lt-yellow';
                }
            @endphp
            <div class="mobile-card {{ $rowClass }}" data-waiter-id="{{ $row['waiter_id'] }}">
                <div class="mobile-head">
                    <div>
                        <strong>{{ $row['waiter_name'] }}</strong>
                        <div class="sub">{{ $row['waiter_role'] }}</div>
                    </div>
                    <div class="cell-status">{!! $row['status'] === 'finalized' ? '<span class="badge badge-finalized">finalized</span>' : ($row['status'] === 'overridden' ? '<span class="badge badge-overridden">overridden</span>' : '<span class="badge badge-draft">draft</span>') !!}</div>
                </div>
                <div class="mobile-grid">
                    <div><span>Total Poin</span><b class="cell-net-points">{{ number_format($row['net_points'], 0, ',', '.') }}</b></div>
                    <div><span>Persen Poin</span><b class="cell-point-percentage"><span class="{{ $row['point_percentage'] >= 80 ? 'pct-high' : ($row['point_percentage'] >= 70 ? 'pct-med' : ($row['point_percentage'] >= 60 ? 'pct-low' : 'pct-fail')) }}">{{ number_format($row['point_percentage'], 1, ',', '.') }}%</span></b></div>
                    <div><span>Bonus Poin</span><b class="cell-points-bonus">{{ $formatRp($row['points_bonus']) }}</b></div>
                    <div><span>Sales Target %</span><b class="cell-sales-percentage"><span class="{{ $row['sales_percentage'] >= 80 ? 'pct-high' : ($row['sales_percentage'] >= 70 ? 'pct-med' : ($row['sales_percentage'] >= 60 ? 'pct-low' : 'pct-fail')) }}">{{ number_format($row['sales_percentage'], 1, ',', '.') }}%</span></b></div>
                    <div><span>Bonus Sales</span><b class="cell-sales-bonus">{{ $formatRp($row['sales_bonus']) }}</b></div>
                    <div><span>Total Bonus</span><b class="cell-total-bonus">{{ $formatRp($row['total_bonus']) }}</b></div>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn-info btn-sm btn-calculate" data-id="{{ $row['waiter_id'] }}">Hitung</button>
                    <button type="button" class="btn btn-warning btn-sm btn-override" data-id="{{ $row['waiter_id'] }}" data-name="{{ $row['waiter_name'] }}">Override</button>
                    <button type="button" class="btn btn-success btn-sm btn-finalize" data-id="{{ $row['waiter_id'] }}" {{ $row['status'] === 'finalized' ? 'disabled' : '' }}>Finalisasi</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="modal-backdrop" id="overrideModal">
    <div class="modal-box">
        <h3>Override Bonus</h3>
        <p id="overrideTargetName">Karyawan: -</p>
        <label for="overrideAmount">Amount (Rp)</label>
        <input type="number" id="overrideAmount" min="0" placeholder="Contoh: 350000">
        <label for="overrideReason">Reason</label>
        <textarea id="overrideReason" rows="3" placeholder="Alasan override bonus"></textarea>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="btnCancelOverride">Batal</button>
            <button type="button" class="btn btn-primary" id="btnSubmitOverride">Submit Override</button>
        </div>
    </div>
</div>

<style>
    .monthly-summary-page { display: grid; gap: 16px; }
    .page-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-head h2 { margin: 0; }
    .page-head p { margin: 4px 0 0; color: #64748b; }

    .month-nav { display: inline-flex; align-items: center; gap: 8px; }
    .month-nav input {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 7px 10px;
        font: inherit;
    }
    .nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        background: #fff;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .kpi-card {
        background: #fff;
        border-radius: 12px;
        padding: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .kpi-card.kpi-blue { border-left: 4px solid #3b82f6; }
    .kpi-card.kpi-green { border-left: 4px solid #16a34a; }
    .kpi-card.kpi-purple { border-left: 4px solid #7c3aed; }
    .kpi-card.kpi-amber { border-left: 4px solid #d97706; }
    .kpi-label { font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 700; }
    .kpi-value { margin-top: 8px; font-weight: 800; color: #0f172a; font-size: 20px; }

    /* Monthly scoring section */
    .monthly-scoring-section {
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        border: 2px solid #c7d2fe;
    }
    .scoring-section-header h3 { margin: 0 0 4px; font-size: 16px; color: #1e40af; }
    .scoring-section-desc { margin: 0 0 12px; font-size: 13px; color: #64748b; }
    .scoring-table-wrap { overflow-x: auto; }
    .scoring-table { width: 100%; border-collapse: collapse; }
    .scoring-table th, .scoring-table td { padding: 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    .scoring-table th { background: #f8fafc; font-size: 13px; white-space: nowrap; }
    .pct-input-wrap { display: flex; align-items: center; gap: 4px; }
    .pct-input {
        width: 70px;
        padding: 7px 8px;
        border: 2px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 700;
        text-align: center;
        transition: border-color 0.2s;
    }
    .pct-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
    .pct-suffix { font-size: 13px; color: #64748b; font-weight: 700; }
    .points-preview { font-weight: 700; color: #1e40af; font-size: 14px; }

    .action-row { display: flex; gap: 8px; flex-wrap: wrap; }
    .table-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; min-width: 980px; }
    th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    th { background: #f8fafc; font-size: 13px; white-space: nowrap; }
    .sub { font-size: 12px; color: #64748b; }
    .actions { display: flex; gap: 6px; flex-wrap: wrap; }

    .badge {
        display: inline-block;
        border-radius: 999px;
        padding: 4px 9px;
        font-size: 12px;
        font-weight: 700;
        text-transform: lowercase;
    }
    .badge-draft { background: #fef3c7; color: #92400e; }
    .badge-finalized { background: #dcfce7; color: #166534; }
    .badge-overridden { background: #dbeafe; color: #1e40af; }

    .row-lt-green { background: #f0fdf4; border-left: 4px solid #16a34a; }
    .row-lt-yellow { background: #fffbeb; border-left: 4px solid #ca8a04; }
    .row-lt-red { background: #fef2f2; border-left: 4px solid #dc2626; }

    .pct-high { color: #16a34a; font-weight: bold; }
    .pct-med { color: #ca8a04; font-weight: bold; }
    .pct-low { color: #ea580c; font-weight: bold; }
    .pct-fail { color: #dc2626; font-weight: bold; }

    .mobile-only { display: none; }
    .mobile-list { display: grid; gap: 10px; }
    .mobile-card {
        border-radius: 12px;
        padding: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    .mobile-head { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .mobile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 10px;
    }
    .mobile-grid span { display: block; font-size: 11px; text-transform: uppercase; color: #64748b; }
    .mobile-grid b { font-size: 14px; color: #0f172a; }

    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 14px;
        z-index: 2100;
    }
    .modal-backdrop.open { display: flex; }
    .modal-box {
        width: min(520px, 100%);
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 12px 28px rgba(0,0,0,.2);
        display: grid;
        gap: 10px;
    }
    .modal-box h3 { margin: 0; }
    .modal-box p { margin: 0; color: #64748b; }
    .modal-box input,
    .modal-box textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 9px 10px;
        font: inherit;
    }
    .modal-actions { display: flex; justify-content: flex-end; gap: 8px; }

    @media (max-width: 1024px) {
        .desktop-only { display: none; }
        .mobile-only { display: block; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const monthPicker = document.getElementById('monthPicker');
    const monthValue = monthPicker ? monthPicker.value : '{{ $month }}';
    const workingDays = {{ $workingDays }};
    const monthlyServiceMax = {{ $monthlyServiceMax }};
    const monthlySalesMax = {{ $monthlySalesMax }};

    const formatter = new Intl.NumberFormat('id-ID');
    const summaries = @json($rows);
    const summaryMap = {};
    summaries.forEach(function (item) { summaryMap[String(item.waiter_id)] = item; });

    let overrideWaiterId = null;

    function formatRp(value) {
        return 'Rp ' + formatter.format(Number(value || 0));
    }

    function formatNumber(value, digits) {
        return Number(value || 0).toLocaleString('id-ID', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    function statusBadge(status) {
        if (status === 'finalized') return '<span class="badge badge-finalized">finalized</span>';
        if (status === 'overridden') return '<span class="badge badge-overridden">overridden</span>';
        return '<span class="badge badge-draft">draft</span>';
    }

    function rowClassByPercentage(percent) {
        if (percent >= 80) return 'row-lt-green';
        if (percent >= 60) return 'row-lt-yellow';
        return 'row-lt-red';
    }

    function pctClass(percent) {
        if (percent >= 80) return 'pct-high';
        if (percent >= 70) return 'pct-med';
        if (percent >= 60) return 'pct-low';
        return 'pct-fail';
    }

    // Real-time points preview for percentage inputs
    function updatePointsPreview(waiterId) {
        const serviceInput = document.querySelector('.service-pct-input[data-waiter-id="' + waiterId + '"]');
        const salesInput = document.querySelector('.sales-pct-input[data-waiter-id="' + waiterId + '"]');
        const servicePreview = document.querySelector('.service-points-preview[data-waiter-id="' + waiterId + '"]');
        const salesPreview = document.querySelector('.sales-points-preview[data-waiter-id="' + waiterId + '"]');

        if (serviceInput && servicePreview) {
            const pct = Math.max(0, Math.min(100, parseInt(serviceInput.value) || 0));
            const pts = Math.round((pct / 100) * monthlyServiceMax);
            servicePreview.textContent = pts;
        }
        if (salesInput && salesPreview) {
            const pct = Math.max(0, Math.min(100, parseInt(salesInput.value) || 0));
            const pts = Math.round((pct / 100) * monthlySalesMax);
            salesPreview.textContent = pts;
        }
    }

    function syncFinalizeButtons(waiterId, rowData) {
        const finalized = String(rowData.status || '') === 'finalized';
        document.querySelectorAll('.btn-finalize[data-id="' + waiterId + '"]').forEach(function (btn) {
            btn.disabled = finalized;
        });
    }

    // Attach listeners to percentage inputs
    document.querySelectorAll('.service-pct-input, .sales-pct-input').forEach(function (input) {
        input.addEventListener('input', function () {
            updatePointsPreview(this.getAttribute('data-waiter-id'));
        });
    });

    function getMonthlyPercentages(waiterId) {
        const serviceInput = document.querySelector('.service-pct-input[data-waiter-id="' + waiterId + '"]');
        const salesInput = document.querySelector('.sales-pct-input[data-waiter-id="' + waiterId + '"]');
        return {
            service_percentage: Math.max(0, Math.min(100, parseInt(serviceInput?.value) || 0)),
            sales_percentage: Math.max(0, Math.min(100, parseInt(salesInput?.value) || 0))
        };
    }

    function parseSummaryFromResponse(data, waiterId) {
        const source = data || {};
        return {
            waiter_id: String(source.waiter_id || waiterId || ''),
            waiter_name: source.waiter_name || summaryMap[String(waiterId)]?.waiter_name || '-',
            waiter_role: source.waiter_role || summaryMap[String(waiterId)]?.waiter_role || '-',
            net_points: Number(source.net_points || 0),
            point_percentage: Number(source.points_percentage || 0),
            points_bonus: Number(source.points_bonus || 0),
            sales_percentage: Number(source.sales_percentage || 0),
            sales_bonus: Number(source.sales_bonus || 0),
            total_bonus: Number(source.total_bonus || 0),
            status: String(source.status || 'draft'),
            monthly_service_percentage: Number(source.monthly_service_percentage || 0),
            monthly_sales_percentage: Number(source.monthly_sales_percentage || 0),
            service_points: Number(source.service_points || 0),
            sales_points: Number(source.sales_points || 0),
        };
    }

    function applyRowData(waiterId, rowData) {
        summaryMap[String(waiterId)] = rowData;

        const desktopRow = document.querySelector('tr.summary-row[data-waiter-id="' + waiterId + '"]');
        const mobileCard = document.querySelector('.mobile-card[data-waiter-id="' + waiterId + '"]');
        const rowClass = rowClassByPercentage(rowData.point_percentage);

        [desktopRow, mobileCard].forEach(function (root) {
            if (!root) return;
            root.classList.remove('row-lt-green', 'row-lt-yellow', 'row-lt-red');
            root.classList.add(rowClass);

            const netPointsEl = root.querySelector('.cell-net-points');
            const pointPercentEl = root.querySelector('.cell-point-percentage');
            const pointsBonusEl = root.querySelector('.cell-points-bonus');
            const salesPercentEl = root.querySelector('.cell-sales-percentage');
            const salesBonusEl = root.querySelector('.cell-sales-bonus');
            const totalBonusEl = root.querySelector('.cell-total-bonus');
            const statusEl = root.querySelector('.cell-status');

            if (netPointsEl) netPointsEl.textContent = formatNumber(rowData.net_points, 0);
            if (pointPercentEl) pointPercentEl.innerHTML = '<span class="' + pctClass(rowData.point_percentage) + '">' + formatNumber(rowData.point_percentage, 1) + '%</span>';
            if (pointsBonusEl) pointsBonusEl.textContent = formatRp(rowData.points_bonus);
            if (salesPercentEl) salesPercentEl.innerHTML = '<span class="' + pctClass(rowData.sales_percentage) + '">' + formatNumber(rowData.sales_percentage, 1) + '%</span>';
            if (salesBonusEl) salesBonusEl.textContent = formatRp(rowData.sales_bonus);
            if (totalBonusEl) totalBonusEl.textContent = formatRp(rowData.total_bonus);
            if (statusEl) statusEl.innerHTML = statusBadge(rowData.status);
        });

        // Update percentage inputs if returned from server
        const serviceInput = document.querySelector('.service-pct-input[data-waiter-id="' + waiterId + '"]');
        const salesInput = document.querySelector('.sales-pct-input[data-waiter-id="' + waiterId + '"]');
        if (serviceInput && rowData.monthly_service_percentage !== undefined) {
            serviceInput.value = rowData.monthly_service_percentage;
        }
        if (salesInput && rowData.monthly_sales_percentage !== undefined) {
            salesInput.value = rowData.monthly_sales_percentage;
        }
        updatePointsPreview(waiterId);
        syncFinalizeButtons(waiterId, rowData);

        refreshKpi();
    }

    function refreshKpi() {
        const all = Object.values(summaryMap);
        const totalKaryawan = all.length;
        const totalBonus = all.reduce(function (acc, item) { return acc + Number(item.total_bonus || 0); }, 0);
        const avgBonus = totalKaryawan > 0 ? (totalBonus / totalKaryawan) : 0;
        const counts = { draft: 0, finalized: 0, overridden: 0 };
        all.forEach(function (item) {
            const status = counts[item.status] !== undefined ? item.status : 'draft';
            counts[status] += 1;
        });

        const kpiTotalKaryawan = document.getElementById('kpiTotalKaryawan');
        const kpiTotalBonus = document.getElementById('kpiTotalBonus');
        const kpiAvgBonus = document.getElementById('kpiAvgBonus');
        const kpiStatusCount = document.getElementById('kpiStatusCount');

        if (kpiTotalKaryawan) kpiTotalKaryawan.textContent = totalKaryawan;
        if (kpiTotalBonus) kpiTotalBonus.textContent = formatRp(totalBonus);
        if (kpiAvgBonus) kpiAvgBonus.textContent = formatRp(avgBonus);
        if (kpiStatusCount) kpiStatusCount.textContent = (totalKaryawan > 0 && counts.finalized === totalKaryawan) ? 'Finalized' : 'Draft';
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(payload)
        });

        let data = {};
        try { data = await res.json(); } catch (e) { data = {}; }
        if (!res.ok) throw new Error(data?.message || 'Request gagal');
        return data;
    }

    async function calculateOne(waiterId) {
        const pcts = getMonthlyPercentages(waiterId);
        const data = await postJson('{{ route('admin.bonus.monthly_summary.calculate') }}', {
            waiter_id: waiterId,
            month: monthValue,
            service_percentage: pcts.service_percentage,
            sales_percentage: pcts.sales_percentage
        });

        const rowData = parseSummaryFromResponse(data, waiterId);
        applyRowData(waiterId, rowData);
    }

    async function finalizeOne(waiterId) {
        const pcts = getMonthlyPercentages(waiterId);
        const data = await postJson('{{ route('admin.bonus.monthly_summary.finalize') }}', {
            waiter_id: waiterId,
            month: monthValue,
            service_percentage: pcts.service_percentage,
            sales_percentage: pcts.sales_percentage
        });

        const rowData = parseSummaryFromResponse(data, waiterId);
        applyRowData(waiterId, rowData);
    }

    function setButtonsState(selector, disabled) {
        document.querySelectorAll(selector).forEach(function (btn) { btn.disabled = disabled; });
    }

    document.getElementById('monthPicker')?.addEventListener('change', function (e) {
        if (e.target.value) {
            window.location.href = '?month=' + encodeURIComponent(e.target.value);
        }
    });

    document.querySelectorAll('.btn-calculate').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const waiterId = String(btn.getAttribute('data-id'));
            const original = btn.textContent;
            btn.disabled = true;
            btn.textContent = '...';
            try {
                await calculateOne(waiterId);
            } catch (err) {
                alert(err.message || 'Gagal hitung bonus.');
            } finally {
                btn.disabled = false;
                btn.textContent = original;
            }
        });
    });

    document.querySelectorAll('.btn-finalize').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const waiterId = String(btn.getAttribute('data-id'));
            const original = btn.textContent;
            btn.disabled = true;
            btn.textContent = '...';
            try {
                await finalizeOne(waiterId);
            } catch (err) {
                alert(err.message || 'Gagal finalisasi bonus.');
            } finally {
                btn.disabled = false;
                btn.textContent = original;
            }
        });
    });

    document.getElementById('btnCalculateAll')?.addEventListener('click', async function () {
        const btn = this;
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Menghitung...';
        setButtonsState('.btn-calculate', true);
        try {
            const ids = Object.keys(summaryMap);
            for (const id of ids) {
                await calculateOne(id);
            }
        } catch (err) {
            alert(err.message || 'Gagal hitung semua.');
        } finally {
            btn.disabled = false;
            btn.textContent = original;
            setButtonsState('.btn-calculate', false);
        }
    });

    document.getElementById('btnFinalizeAll')?.addEventListener('click', async function () {
        if (!confirm('Finalisasi semua karyawan? Pastikan persentase Pelayanan & Penjualan sudah diisi.')) return;
        const btn = this;
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Memfinalisasi...';
        setButtonsState('.btn-finalize', true);
        try {
            const ids = Object.keys(summaryMap);
            for (const id of ids) {
                await finalizeOne(id);
            }
        } catch (err) {
            alert(err.message || 'Gagal finalisasi semua.');
        } finally {
            btn.disabled = false;
            btn.textContent = original;
            setButtonsState('.btn-finalize', false);
        }
    });

    const modal = document.getElementById('overrideModal');
    const overrideTargetName = document.getElementById('overrideTargetName');
    const overrideAmount = document.getElementById('overrideAmount');
    const overrideReason = document.getElementById('overrideReason');

    document.querySelectorAll('.btn-override').forEach(function (btn) {
        btn.addEventListener('click', function () {
            overrideWaiterId = String(btn.getAttribute('data-id'));
            const name = btn.getAttribute('data-name') || '-';
            overrideTargetName.textContent = 'Karyawan: ' + name;
            overrideAmount.value = '';
            overrideReason.value = '';
            modal.classList.add('open');
        });
    });

    document.getElementById('btnCancelOverride')?.addEventListener('click', function () {
        modal.classList.remove('open');
        overrideWaiterId = null;
    });

    document.getElementById('btnSubmitOverride')?.addEventListener('click', async function () {
        if (!overrideWaiterId) {
            alert('Karyawan tidak valid.');
            return;
        }

        const amount = Number(overrideAmount.value || 0);
        const reason = String(overrideReason.value || '').trim();

        if (amount < 0) {
            alert('Amount tidak valid.');
            return;
        }
        if (!reason) {
            alert('Reason wajib diisi.');
            return;
        }

        const btn = this;
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const data = await postJson('{{ route('admin.bonus.monthly_summary.override') }}', {
                waiter_id: overrideWaiterId,
                month: monthValue,
                amount: amount,
                reason: reason
            });

            const rowData = parseSummaryFromResponse(data, overrideWaiterId);
            rowData.status = 'overridden';
            rowData.total_bonus = amount;
            applyRowData(overrideWaiterId, rowData);

            modal.classList.remove('open');
            overrideWaiterId = null;
        } catch (err) {
            alert(err.message || 'Gagal override bonus.');
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    });
});
</script>
@endsection
