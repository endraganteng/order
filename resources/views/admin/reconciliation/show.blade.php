@extends('admin.layout')

@section('content')
    <div style="margin-bottom:12px;">
        <a href="{{ route('admin.reconciliation.index', ['iso_year_week' => $isoYearWeek]) }}">Reconciliation</a>
        &gt; {{ $isoYearWeek }} &gt; {{ substr($reportId, 0, 8) }}
    </div>

    <h1>Detail Reconciliation Stok</h1>

    @php
        $maxSeverity = 'warning';
        foreach ($anomalies as $a) {
            if (($a['severity'] ?? '') === 'severe') { $maxSeverity = 'severe'; break; }
            if (($a['severity'] ?? '') === 'critical') { $maxSeverity = 'critical'; }
        }
        $severityColor = $maxSeverity === 'severe' ? '#6f42c1' : ($maxSeverity === 'critical' ? '#dc3545' : '#ffc107');
        $filtered = array_values(array_filter($anomalies, function ($a) use ($severityFilter) {
            return $severityFilter === 'all' || (($a['severity'] ?? '') === $severityFilter);
        }));
    @endphp

    <div style="display:grid; grid-template-columns: repeat(3,minmax(160px,1fr)); gap:10px; margin:12px 0 16px;">
        <div class="card" style="padding:12px;">Racks Checked<br><strong>{{ (int) ($report['total_racks_checked'] ?? 0) }}</strong></div>
        <div class="card" style="padding:12px;">Products Checked<br><strong>{{ (int) ($report['total_products_checked'] ?? 0) }}</strong></div>
        <div class="card" style="padding:12px; border-left:5px solid {{ $severityColor }};">Anomalies<br><strong>{{ count($anomalies) }}</strong></div>
    </div>

    <div style="display:flex; gap:10px; align-items:center; margin-bottom:12px;">
        <form method="GET" action="{{ route('admin.reconciliation.show', ['isoYearWeek' => $isoYearWeek, 'reportId' => $reportId]) }}">
            <label for="severity">Filter Severity:</label>
            <select id="severity" name="severity" onchange="this.form.submit()">
                <option value="all" {{ $severityFilter === 'all' ? 'selected' : '' }}>Semua</option>
                <option value="warning" {{ $severityFilter === 'warning' ? 'selected' : '' }}>Warning</option>
                <option value="critical" {{ $severityFilter === 'critical' ? 'selected' : '' }}>Critical</option>
                <option value="severe" {{ $severityFilter === 'severe' ? 'selected' : '' }}>Severe</option>
            </select>
        </form>
        <button type="button" class="btn btn-secondary" disabled title="TODO: export">Export CSV</button>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Rack</th>
                <th>Produk</th>
                <th>Expected</th>
                <th>Actual</th>
                <th>Drift Qty</th>
                <th>Drift %</th>
                <th>Severity</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filtered as $a)
                <tr>
                    <td>{{ $a['rack_name'] ?? $a['rack_id'] ?? '-' }}</td>
                    <td>{{ $a['product_name'] ?? $a['product_id'] ?? '-' }}</td>
                    <td>{{ (int) ($a['expected'] ?? 0) }}</td>
                    <td>{{ (int) ($a['actual'] ?? 0) }}</td>
                    <td>{{ (int) ($a['drift_qty'] ?? 0) }}</td>
                    <td>{{ number_format((float) ($a['drift_pct'] ?? 0), 2) }}%</td>
                    <td><span class="badge">{{ ucfirst((string) ($a['severity'] ?? 'warning')) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7">Tidak ada anomali untuk filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
