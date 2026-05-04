@extends('admin.layout')

@section('title', 'Absensi Bulanan - Admin')

@push('styles')
<style>
    .att-month-nav {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .att-month-nav input[type="month"] {
        padding: 8px 12px;
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 15px;
    }
    .att-month-nav input[type="month"]:focus {
        border-color: var(--color-primary);
        outline: none;
    }
    .att-month-btn {
        padding: 8px 14px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: #fff;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }
    .att-month-btn:hover { background: #f1f5f9; }

    .att-monthly-table { width: 100%; border-collapse: collapse; }
    .att-monthly-table th, .att-monthly-table td {
        padding: 10px 12px; text-align: center; border-bottom: 1px solid #e2e8f0; font-size: 13px;
    }
    .att-monthly-table th {
        background: #f8fafc; font-weight: 600; color: #334155;
        font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em;
    }
    .att-monthly-table td:first-child { text-align: left; font-weight: 600; }

    .pct-badge {
        display: inline-block; padding: 3px 8px; border-radius: 999px;
        font-size: 12px; font-weight: 700;
    }
    .pct-badge.green { background: #dcfce7; color: #166534; }
    .pct-badge.orange { background: #fff7ed; color: #9a3412; }
    .pct-badge.red { background: #fef2f2; color: #991b1b; }

    @media (max-width: 768px) {
        .att-monthly-table-wrap { display: none; }
        .att-monthly-cards { display: flex; flex-direction: column; gap: 12px; }
        .att-mc {
            border: 1px solid var(--color-border); border-radius: var(--radius-lg);
            padding: 14px; background: #fff; box-shadow: var(--shadow-sm);
        }
        .att-mc-name { font-weight: 700; font-size: 15px; margin-bottom: 10px; }
        .att-mc-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .att-mc-item { text-align: center; }
        .att-mc-item-val { font-size: 18px; font-weight: 800; }
        .att-mc-item-lbl { font-size: 11px; color: var(--color-text-muted); }
    }
    @media (min-width: 769px) {
        .att-monthly-cards { display: none; }
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">Absensi Bulanan</h2>
        <p class="page-subtitle">Ringkasan kehadiran per waiter</p>
    </div>
    <a href="{{ route('admin.attendance.index') }}" class="btn btn-secondary">📋 Harian</a>
</div>

<div class="att-month-nav">
    <button class="att-month-btn" onclick="navMonth(-1)">◀</button>
    <input type="month" id="attMonthPicker" value="{{ $yearMonth }}" onchange="goToMonth(this.value)">
    <button class="att-month-btn" onclick="navMonth(1)">▶</button>
    <button class="att-month-btn" onclick="goToMonth('{{ date('Y-m') }}')">Bulan Ini</button>
</div>

<div class="card">
    <div class="att-monthly-table-wrap" style="overflow-x:auto;">
        <table class="att-monthly-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Hari Kerja</th>
                    <th>Hadir</th>
                    <th>Tepat Waktu</th>
                    <th>Terlambat</th>
                    <th>Absen</th>
                    <th>Sakit</th>
                    <th>Libur</th>
                    <th>% Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                @foreach($waiters as $w)
                @php
                    $wId = $w['id'] ?? '';
                    $s = $summaries[$wId] ?? [];
                    $worked = (int) ($s['total_days_worked'] ?? 0);
                    $onTime = (int) ($s['total_on_time'] ?? 0);
                    $late = (int) ($s['total_late'] ?? 0);
                    $absent = (int) ($s['total_absent'] ?? 0);
                    $sick = (int) ($s['total_sick'] ?? 0);
                    $dayOff = (int) ($s['total_day_off'] ?? 0);
                    $totalWorkDays = $worked + $absent + $sick;
                    $pct = $totalWorkDays > 0 ? round(($worked / $totalWorkDays) * 100) : 0;
                    $pctClass = $pct >= 90 ? 'green' : ($pct >= 70 ? 'orange' : 'red');
                @endphp
                <tr>
                    <td>{{ $w['name'] ?? '-' }}</td>
                    <td>{{ $totalWorkDays }}</td>
                    <td>{{ $worked }}</td>
                    <td>{{ $onTime }}</td>
                    <td>{{ $late }}</td>
                    <td>{{ $absent }}</td>
                    <td>{{ $sick }}</td>
                    <td>{{ $dayOff }}</td>
                    <td><span class="pct-badge {{ $pctClass }}">{{ $pct }}%</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="att-monthly-cards">
        @foreach($waiters as $w)
        @php
            $wId = $w['id'] ?? '';
            $s = $summaries[$wId] ?? [];
            $worked = (int) ($s['total_days_worked'] ?? 0);
            $onTime = (int) ($s['total_on_time'] ?? 0);
            $late = (int) ($s['total_late'] ?? 0);
            $absent = (int) ($s['total_absent'] ?? 0);
            $sick = (int) ($s['total_sick'] ?? 0);
            $dayOff = (int) ($s['total_day_off'] ?? 0);
            $totalWorkDays = $worked + $absent + $sick;
            $pct = $totalWorkDays > 0 ? round(($worked / $totalWorkDays) * 100) : 0;
            $pctClass = $pct >= 90 ? 'green' : ($pct >= 70 ? 'orange' : 'red');
        @endphp
        <div class="att-mc">
            <div class="att-mc-name">{{ $w['name'] ?? '-' }} <span class="pct-badge {{ $pctClass }}">{{ $pct }}%</span></div>
            <div class="att-mc-grid">
                <div class="att-mc-item"><div class="att-mc-item-val" style="color:#16a34a;">{{ $worked }}</div><div class="att-mc-item-lbl">Hadir</div></div>
                <div class="att-mc-item"><div class="att-mc-item-val" style="color:#2563eb;">{{ $onTime }}</div><div class="att-mc-item-lbl">Tepat Waktu</div></div>
                <div class="att-mc-item"><div class="att-mc-item-val" style="color:#d97706;">{{ $late }}</div><div class="att-mc-item-lbl">Terlambat</div></div>
                <div class="att-mc-item"><div class="att-mc-item-val" style="color:#dc2626;">{{ $absent }}</div><div class="att-mc-item-lbl">Absen</div></div>
                <div class="att-mc-item"><div class="att-mc-item-val" style="color:#7e22ce;">{{ $sick }}</div><div class="att-mc-item-lbl">Sakit</div></div>
                <div class="att-mc-item"><div class="att-mc-item-val" style="color:#64748b;">{{ $dayOff }}</div><div class="att-mc-item-lbl">Libur</div></div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
function navMonth(offset) {
    var current = document.getElementById('attMonthPicker').value;
    var parts = current.split('-');
    var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1 + offset, 1);
    var m = String(d.getMonth() + 1).padStart(2, '0');
    goToMonth(d.getFullYear() + '-' + m);
}

function goToMonth(val) {
    window.location.href = '{{ route("admin.attendance.monthly") }}?month=' + val;
}
</script>
@endpush
