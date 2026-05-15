@extends('admin.layout')

@section('title', 'Dashboard - Admin')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">

    <h2 style="margin-bottom: 20px; color: #1e293b; font-size: clamp(24px, 5vw, 32px); font-weight: 800;">Dashboard</h2>

    {{-- ============================================================ --}}
    {{-- KPI SUMMARY CARDS — Paling atas agar langsung terlihat --}}
    {{-- ============================================================ --}}
    @php
        $totalWaiters = count($waiters);
        $activeWaiters = collect($waiters)->where('is_active', true)->count();
        $totalOrders = $orderStatsSummary['total_orders'] ?? 0;
        $timeoutMinutes = $settings['order_timeout_minutes'] ?? 3;
    @endphp

    <div class="kpi-grid">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon">👥</div>
            <div class="kpi-value">{{ $totalWaiters }}</div>
            <div class="kpi-label">Total Waiters</div>
        </div>

        <div class="kpi-card kpi-green">
            <div class="kpi-icon">✅</div>
            <div class="kpi-value">{{ $activeWaiters }}</div>
            <div class="kpi-label">Waiter Aktif</div>
        </div>

        <div class="kpi-card kpi-amber">
            <div class="kpi-icon">📦</div>
            <div class="kpi-value">{{ $totalOrders }}</div>
            <div class="kpi-label">Total Order</div>
            <div class="kpi-trend neutral">Periode: {{ $orderPeriodLabel }}</div>
        </div>

        <div class="kpi-card kpi-red">
            <div class="kpi-icon">⏱️</div>
            <div class="kpi-value">{{ $timeoutMinutes }}<span style="font-size: 16px; font-weight: 500;">m</span></div>
            <div class="kpi-label">Timeout Order</div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- QUICK ACTIONS --}}
    {{-- ============================================================ --}}
    <div class="card" style="padding: 18px 16px; margin-bottom: 24px;">
        <h3 style="margin-bottom: 12px; font-size: 16px; color: #475569; font-weight: 700;">Quick Actions</h3>
        <div class="quick-actions">
            <a href="{{ route('admin.waiters.create') }}" class="btn btn-primary">
                + Tambah Waiter
            </a>
            <a href="{{ route('admin.settings') }}" class="btn btn-warning">
                Pengaturan
            </a>
            <a href="{{ route('admin.cleanup') }}" class="btn btn-danger">
                Cleanup Orders
            </a>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- STATISTIK SUPERVISOR — Filter + Chart + Tabel --}}
    {{-- ============================================================ --}}
    <div class="card" style="margin-bottom: 24px; padding: 20px 16px;">
        <div class="section-header">
            <h3 class="section-title" style="color: #1e293b;">Statistik Supervisor</h3>
            <span class="section-subtitle">Periode aktif: <strong>{{ $orderPeriodLabel }}</strong></span>
        </div>

        {{-- Filter Periode --}}
        <form method="GET" action="{{ route('admin.dashboard') }}" id="dashboard-order-period-form">
            <div class="filter-area">
                <label for="dashboard-date-range">Filter Periode</label>
                <input id="dashboard-date-range" name="date_range" type="text" class="input" style="width: 100%; max-width: 320px;"
                    value="{{ $dateRangeInput }}" autocomplete="off">
                <input type="hidden" id="dashboard-start-date" name="start_date" value="{{ $startDate }}">
                <input type="hidden" id="dashboard-end-date" name="end_date" value="{{ $endDate }}">
                <div class="filter-hint">Filter diterapkan otomatis saat pilihan berubah.</div>
            </div>
        </form>

        {{-- Summary Pills --}}
        <div class="summary-pills">
            <span class="status pending">
                {{ date('d M Y', $periodStartTs) }} - {{ date('d M Y', $periodEndTs) }}
            </span>
            <span class="status done">
                Total Order: {{ $totalOrders }}
            </span>
            <span class="status overdue">
                Waiter dengan Order: {{ $orderStatsSummary['waiter_with_orders'] ?? 0 }}
            </span>
        </div>

        {{-- ============================================================ --}}
        {{-- TOP WAITER BAR CHART + TABEL GABUNGAN --}}
        {{-- ============================================================ --}}
        <div class="dashboard-grid-2">
            {{-- Bar Chart: Top 10 Waiter --}}
            <div class="data-panel">
                <div class="data-panel-header blue">Top 10 Waiter — Order Terbanyak</div>
                @if(count($userStats) > 0)
                    @php
                        $topStats = array_slice($userStats, 0, 10);
                        $maxOrders = max(array_column($topStats, 'order_count'));
                    @endphp
                    <div class="bar-chart">
                        @foreach($topStats as $rank => $stat)
                            @php
                                $pct = $maxOrders > 0 ? round(($stat['order_count'] / $maxOrders) * 100) : 0;
                                $barClass = match($rank) {
                                    0 => 'gold',
                                    1 => 'silver',
                                    2 => 'bronze',
                                    default => '',
                                };
                                $medal = match($rank) {
                                    0 => '🥇',
                                    1 => '🥈',
                                    2 => '🥉',
                                    default => ($rank + 1),
                                };
                            @endphp
                            <div class="bar-row">
                                <span class="bar-rank">{{ $medal }}</span>
                                <span class="bar-label" title="{{ $stat['waiter_name'] }}">{{ $stat['waiter_name'] }}</span>
                                <div class="bar-track">
                                    <div class="bar-fill {{ $barClass }}" style="width: {{ max($pct, 8) }}%;">
                                        <span class="bar-count">{{ $stat['order_count'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty" style="margin: 14px;">Belum ada order pada periode {{ strtolower($orderPeriodLabel) }}.</div>
                @endif
            </div>

            {{-- Tabel: Statistik Order per Waiter (lengkap) --}}
            <div class="data-panel">
                <div class="data-panel-header gray">Statistik Order per Waiter</div>
                @if(count($userStats) > 0)
                    <div style="overflow-x: auto; max-height: 420px;">
                        <table class="table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Waiter</th>
                                    <th>Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($userStats, 0, 20) as $index => $stat)
                                    <tr style="{{ $index < 3 ? 'background: #fefce8;' : '' }}">
                                        <td>
                                            @if($index === 0)
                                                <span style="font-size: 16px;">🥇</span>
                                            @elseif($index === 1)
                                                <span style="font-size: 16px;">🥈</span>
                                            @elseif($index === 2)
                                                <span style="font-size: 16px;">🥉</span>
                                            @else
                                                {{ $index + 1 }}
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $stat['waiter_name'] }}</strong>
                                            @if(($stat['waiter_email'] ?? '') !== '')
                                                <div style="font-size: 12px; color: #64748b;">{{ $stat['waiter_email'] }}</div>
                                            @endif
                                        </td>
                                        <td><span class="status done">{{ $stat['order_count'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty" style="margin: 14px;">Belum ada order pada periode {{ strtolower($orderPeriodLabel) }}.</div>
                @endif
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- RANKING TUGAS (Cek Rak + Umum) --}}
        {{-- ============================================================ --}}
        <div style="margin-top: 16px;">
            <div class="data-panel">
                <div class="data-panel-header teal">Paling Rajin Mengerjakan Tugas (Termasuk Cek Rak)</div>
                @if(count($waiterTaskRanking) > 0)
                    <div style="overflow-x: auto; max-height: 300px;">
                        <table class="table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Waiter</th>
                                    <th>Total</th>
                                    <th>Umum</th>
                                    <th>Cek Rak</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($waiterTaskRanking, 0, 10) as $rank => $stat)
                                    <tr style="{{ $rank < 3 ? 'background: #ecfeff;' : '' }}">
                                        <td>
                                            @if($rank === 0)
                                                <span style="font-size: 16px;">🥇</span>
                                            @elseif($rank === 1)
                                                <span style="font-size: 16px;">🥈</span>
                                            @elseif($rank === 2)
                                                <span style="font-size: 16px;">🥉</span>
                                            @else
                                                {{ $rank + 1 }}
                                            @endif
                                        </td>
                                        <td><strong>{{ $stat['waiter_name'] }}</strong></td>
                                        <td><span class="status done">{{ $stat['completed_count'] }}</span></td>
                                        <td>{{ $stat['general_done_count'] }}</td>
                                        <td>{{ $stat['rack_done_count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty" style="margin: 14px;">Belum ada data penyelesaian tugas pada periode ini.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- FOLLOW-UP OPERASIONAL WAITER --}}
    {{-- ============================================================ --}}
    <div class="card" style="margin-bottom: 24px; padding: 20px 16px;">
        <div class="section-header">
            <h3 class="section-title" style="color: #7c2d12;">Follow-up Operasional Waiter</h3>
            <span class="section-subtitle">Periode evaluasi: <strong>{{ $waiterFollowUpBoard['period_label'] ?? $orderPeriodLabel }}</strong></span>
        </div>

        <div class="summary-pills">
            <span class="status overdue">
                Perlu Follow-up: {{ $waiterFollowUpBoard['active_waiter_attention_count'] ?? 0 }} waiter
            </span>
            <span class="status pending">
                Total Waiter Aktif: {{ $waiterFollowUpBoard['active_waiter_count'] ?? 0 }}
            </span>
        </div>

        @if(($waiterFollowUpBoard['has_attention'] ?? false) === true)
            {{-- Desktop: Table View --}}
            <div class="followup-table-wrap">
                <div style="overflow-x: auto; max-height: 420px; border: 1px solid #fed7aa; border-radius: 10px;">
                    <table class="table" style="margin: 0; min-width: 860px;">
                        <thead>
                            <tr>
                                <th>Waiter</th>
                                <th>Role</th>
                                <th>Tugas Umum</th>
                                <th>Cek Rak</th>
                                <th>Belum Selesai</th>
                                <th>Laporan</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($waiterFollowUpBoard['rows'] ?? [], 0, 30) as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['waiter_name'] ?? 'Waiter Tidak Diketahui' }}</strong>
                                        @if(($row['waiter_email'] ?? '') !== '')
                                            <div style="font-size: 12px; color: #64748b;">{{ $row['waiter_email'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $role = strtolower((string) ($row['waiter_role'] ?? 'pelayan'));
                                            if ($role === 'kasir') {
                                                $roleLabel = 'Kasir';
                                                $roleBg = '#fff7ed';
                                                $roleColor = '#9a3412';
                                            } elseif ($role === 'backup') {
                                                $roleLabel = 'Backup / Flexible';
                                                $roleBg = '#f3e8ff';
                                                $roleColor = '#6b21a8';
                                            } else {
                                                $roleLabel = 'Pelayan';
                                                $roleBg = '#ecfeff';
                                                $roleColor = '#0f766e';
                                            }
                                        @endphp
                                        <span style="display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 700; background: {{ $roleBg }}; color: {{ $roleColor }}; border: 1px solid rgba(15,23,42,0.08);">
                                            {{ $roleLabel }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status done">Selesai: {{ $row['general_done_count'] ?? 0 }}</span>
                                        @if(((int) ($row['general_total_count'] ?? 0)) === 0)
                                            <div style="margin-top: 4px;"><span class="status pending">Tidak ada tugas</span></div>
                                        @elseif(($row['missing_general_done'] ?? false) === true)
                                            <div style="margin-top: 4px;"><span class="status overdue">Belum ada penyelesaian</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="status done">Selesai: {{ $row['rack_done_count'] ?? 0 }}</span>
                                        @if(((int) ($row['rack_total_count'] ?? 0)) === 0)
                                            <div style="margin-top: 4px;"><span class="status pending">Tidak ada tugas</span></div>
                                        @elseif(($row['missing_rack_done'] ?? false) === true)
                                            <div style="margin-top: 4px;"><span class="status overdue">Belum ada penyelesaian</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        @php $openCount = (int) ($row['total_open_count'] ?? 0); @endphp
                                        @if($openCount > 0)
                                            <span class="status overdue">{{ $openCount }} task</span>
                                            <div style="font-size: 12px; color: #7f1d1d; margin-top: 4px;">
                                                Umum: {{ $row['general_open_count'] ?? 0 }} &bull; Rak: {{ $row['rack_open_count'] ?? 0 }}
                                            </div>
                                        @else
                                            <span class="status done">Semua selesai</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if((int) ($row['report_count'] ?? 0) > 0)
                                            <span class="status done">{{ $row['report_count'] }} laporan</span>
                                        @else
                                            <span class="status overdue">Belum isi</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            @foreach(($row['attention_tags'] ?? []) as $tag)
                                                <span class="attention-tag" style="display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 700; background: #fff7ed; color: #9a3412; border: 1px solid #fdba74;">
                                                    {{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile: Card View --}}
            <div class="followup-cards">
                @foreach(array_slice($waiterFollowUpBoard['rows'] ?? [], 0, 30) as $row)
                    <div class="followup-card">
                        <div class="followup-card-name">{{ $row['waiter_name'] ?? 'Waiter Tidak Diketahui' }}</div>
                        @if(($row['waiter_email'] ?? '') !== '')
                            <div class="followup-card-email">{{ $row['waiter_email'] }}</div>
                        @endif
                        <div class="followup-card-grid">
                            <div class="followup-card-item">
                                <span class="followup-card-item-label">Tugas Umum</span>
                                <span class="status done">Selesai: {{ $row['general_done_count'] ?? 0 }}</span>
                                @if(($row['missing_general_done'] ?? false) === true)
                                    <span class="status overdue">Belum ada</span>
                                @endif
                            </div>
                            <div class="followup-card-item">
                                <span class="followup-card-item-label">Cek Rak</span>
                                <span class="status done">Selesai: {{ $row['rack_done_count'] ?? 0 }}</span>
                                @if(($row['missing_rack_done'] ?? false) === true)
                                    <span class="status overdue">Belum ada</span>
                                @endif
                            </div>
                            <div class="followup-card-item">
                                <span class="followup-card-item-label">Belum Selesai</span>
                                @php $openCount = (int) ($row['total_open_count'] ?? 0); @endphp
                                @if($openCount > 0)
                                    <span class="status overdue">{{ $openCount }} task</span>
                                @else
                                    <span class="status done">Semua selesai</span>
                                @endif
                            </div>
                            <div class="followup-card-item">
                                <span class="followup-card-item-label">Laporan</span>
                                @if((int) ($row['report_count'] ?? 0) > 0)
                                    <span class="status done">{{ $row['report_count'] }} laporan</span>
                                @else
                                    <span class="status overdue">Belum isi</span>
                                @endif
                            </div>
                        </div>
                        @if(count($row['attention_tags'] ?? []) > 0)
                            <div class="followup-card-tags">
                                @foreach($row['attention_tags'] as $tag)
                                    <span class="attention-tag">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty success">
                Semua waiter aktif sudah on-track pada periode ini: tugas umum, cek rak, dan laporan sudah terpenuhi.
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var periodForm = document.getElementById('dashboard-order-period-form');
            var startDateInput = document.getElementById('dashboard-start-date');
            var endDateInput = document.getElementById('dashboard-end-date');
            var rangeInput = document.getElementById('dashboard-date-range');

            if (!periodForm || !startDateInput || !endDateInput || !rangeInput || typeof $ === 'undefined' || typeof moment ===
                'undefined') {
                return;
            }

            var submitWithLock = function () {
                if (periodForm.dataset.submitting === '1') {
                    return;
                }

                periodForm.dataset.submitting = '1';
                periodForm.submit();
            };

            var $range = $('#dashboard-date-range');
            var initialStart = moment(startDateInput.value, 'YYYY-MM-DD', true);
            var initialEnd = moment(endDateInput.value, 'YYYY-MM-DD', true);

            if (!initialStart.isValid()) {
                initialStart = moment().startOf('day');
            }

            if (!initialEnd.isValid()) {
                initialEnd = moment(initialStart).endOf('day');
            }

            if (initialEnd.isBefore(initialStart)) {
                initialEnd = moment(initialStart);
            }

            $range.daterangepicker({
                startDate: initialStart,
                endDate: initialEnd,
                autoUpdateInput: true,
                autoApply: true,
                opens: 'left',
                alwaysShowCalendars: true,
                locale: {
                    format: 'DD MMM YYYY',
                    separator: ' - ',
                    applyLabel: 'Apply',
                    cancelLabel: 'Batal',
                    customRangeLabel: 'Custom Range'
                },
                ranges: {
                    'Hari Ini': [moment(), moment()],
                    'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
                    '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                    'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                    'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            });

            $range.on('apply.daterangepicker', function (ev, picker) {
                startDateInput.value = picker.startDate.format('YYYY-MM-DD');
                endDateInput.value = picker.endDate.format('YYYY-MM-DD');
                rangeInput.value = picker.startDate.format('DD MMM YYYY') + ' - ' + picker.endDate.format('DD MMM YYYY');
                submitWithLock();
            });

            $range.on('cancel.daterangepicker', function (ev, picker) {
                startDateInput.value = picker.startDate.format('YYYY-MM-DD');
                endDateInput.value = picker.endDate.format('YYYY-MM-DD');
                rangeInput.value = picker.startDate.format('DD MMM YYYY') + ' - ' + picker.endDate.format('DD MMM YYYY');
            });
        });
    </script>
@endsection
