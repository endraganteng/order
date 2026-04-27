@extends('admin.layout')

@section('title', 'Dashboard - Admin')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">

    <h2 style="margin-bottom: 20px; color: #333; font-size: clamp(24px, 5vw, 32px);">Dashboard</h2>

    {{-- Quick Actions --}}
    <div class="card" style="padding: 20px 15px; margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px; font-size: clamp(18px, 4vw, 20px);">⚡ Quick Actions</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="{{ route('admin.waiters.create') }}" class="btn btn-primary"
                style="flex: 1 1 auto; min-width: 150px; text-align: center;">
                ➕ Tambah Waiter
            </a>
            <a href="{{ route('admin.settings') }}" class="btn btn-warning"
                style="flex: 1 1 auto; min-width: 150px; text-align: center;">
                ⚙️ Settings
            </a>
            <a href="{{ route('admin.cleanup') }}" class="btn btn-danger"
                style="flex: 1 1 auto; min-width: 150px; text-align: center;">
                🗑️ Cleanup Orders
            </a>
        </div>
    </div>

    {{-- Statistik Supervisor (Filter + Statistik + Peringkat) --}}
    <div class="card" style="margin-bottom: 22px; padding: 20px 15px;">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:10px; margin-bottom:12px;">
            <h3 style="margin:0; color:#333; font-size: clamp(18px, 4vw, 24px);">📈 Statistik Supervisor</h3>
            <span style="font-size:12px; color:#64748b; align-self:center;">Periode aktif: <strong>{{ $orderPeriodLabel }}</strong></span>
        </div>

        <form method="GET" action="{{ route('admin.dashboard') }}" id="dashboard-order-period-form"
            style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin-bottom:12px;">
            <div style="min-width: 210px; flex: 1 1 250px;">
                <label for="dashboard-date-range" style="display:block; font-size:13px; color:#475569; margin-bottom:6px;">Filter Periode (Date Range Picker)</label>
                <input id="dashboard-date-range" name="date_range" type="text" class="input" style="width:100%;"
                    value="{{ $dateRangeInput }}" autocomplete="off">
                <input type="hidden" id="dashboard-start-date" name="start_date" value="{{ $startDate }}">
                <input type="hidden" id="dashboard-end-date" name="end_date" value="{{ $endDate }}">
            </div>
            <div style="font-size:12px; color:#94a3b8; padding-bottom:8px;">Filter diterapkan otomatis saat pilihan berubah.</div>
        </form>

        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
            <span class="status pending" style="display:inline-flex; align-items:center; gap:6px;">Rentang:
                {{ date('d M Y', $periodStartTs) }} - {{ date('d M Y', $periodEndTs) }}</span>
            <span class="status done" style="display:inline-flex; align-items:center; gap:6px;">Total Order:
                {{ $orderStatsSummary['total_orders'] ?? 0 }}</span>
            <span class="status overdue" style="display:inline-flex; align-items:center; gap:6px;">Waiter dengan Order:
                {{ $orderStatsSummary['waiter_with_orders'] ?? 0 }}</span>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 14px;">
            <div style="border:1px solid #e2e8f0; border-radius: 10px; overflow:hidden;">
                <div style="padding:10px 12px; background:#f8fafc; font-weight:700; color:#0f172a;">📊 Statistik Order per Waiter</div>
                @if(count($userStats) > 0)
                    <div style="overflow-x:auto; max-height:360px;">
                        <table class="table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Waiter</th>
                                    <th>Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($userStats, 0, 20) as $index => $stat)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $stat['waiter_name'] }}</strong>
                                            @if(($stat['waiter_email'] ?? '') !== '')
                                                <div style="font-size:12px; color:#64748b;">{{ $stat['waiter_email'] }}</div>
                                            @endif
                                        </td>
                                        <td><span class="status done">{{ $stat['order_count'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty" style="margin:10px;">Belum ada order pada periode {{ strtolower($orderPeriodLabel) }}.</div>
                @endif
            </div>

            <div style="display:grid; gap: 14px;">
                <div style="border:1px solid #e2e8f0; border-radius: 10px; overflow:hidden;">
                    <div style="padding:10px 12px; background:#eef2ff; font-weight:700; color:#1e3a8a;">🏆 Peringkat Order Terbanyak</div>
                    @if(count($userStats) > 0)
                        <div style="overflow-x:auto; max-height:180px;">
                            <table class="table" style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Waiter</th>
                                        <th>Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($userStats, 0, 10) as $rank => $stat)
                                        <tr>
                                            <td>{{ $rank + 1 }}</td>
                                            <td>{{ $stat['waiter_name'] }}</td>
                                            <td><span class="status done">{{ $stat['order_count'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty" style="margin:10px;">Belum ada data ranking order.</div>
                    @endif
                </div>

                <div style="border:1px solid #e2e8f0; border-radius: 10px; overflow:hidden;">
                    <div style="padding:10px 12px; background:#ecfeff; font-weight:700; color:#0f766e;">💪 Paling Rajin Mengerjakan Tugas (Termasuk Cek Rak)</div>
                    @if(count($waiterTaskRanking) > 0)
                        <div style="overflow-x:auto; max-height:220px;">
                            <table class="table" style="margin:0;">
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
                                        <tr>
                                            <td>{{ $rank + 1 }}</td>
                                            <td>{{ $stat['waiter_name'] }}</td>
                                            <td><span class="status done">{{ $stat['completed_count'] }}</span></td>
                                            <td>{{ $stat['general_done_count'] }}</td>
                                            <td>{{ $stat['rack_done_count'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty" style="margin:10px;">Belum ada data penyelesaian tugas pada periode ini.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Follow-up Operasional Waiter --}}
    <div class="card" style="margin-bottom: 22px; padding: 20px 15px;">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:10px; margin-bottom:10px;">
            <h3 style="margin:0; color:#7c2d12; font-size: clamp(18px, 4vw, 22px);">🚨 Follow-up Operasional Waiter</h3>
            <span style="font-size:12px; color:#64748b; align-self:center;">Periode evaluasi: <strong>{{ $waiterFollowUpBoard['period_label'] ?? $orderPeriodLabel }}</strong></span>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
            <span class="status overdue" style="display:inline-flex; align-items:center; gap:6px;">
                Perlu Follow-up: {{ $waiterFollowUpBoard['active_waiter_attention_count'] ?? 0 }} waiter
            </span>
            <span class="status pending" style="display:inline-flex; align-items:center; gap:6px;">
                Total Waiter Aktif: {{ $waiterFollowUpBoard['active_waiter_count'] ?? 0 }}
            </span>
        </div>

        @if(($waiterFollowUpBoard['has_attention'] ?? false) === true)
            <div style="overflow-x:auto; max-height: 420px; border:1px solid #fed7aa; border-radius:10px;">
                <table class="table" style="margin:0; min-width: 900px;">
                    <thead>
                        <tr>
                            <th>Waiter</th>
                            <th>Role</th>
                            <th>Tugas Umum</th>
                            <th>Cek Rak</th>
                            <th>Belum Selesai</th>
                            <th>Laporan</th>
                            <th>Catatan Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($waiterFollowUpBoard['rows'] ?? [], 0, 30) as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['waiter_name'] ?? 'Waiter Tidak Diketahui' }}</strong>
                                    @if(($row['waiter_email'] ?? '') !== '')
                                        <div style="font-size:12px; color:#64748b;">{{ $row['waiter_email'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $role = strtolower((string) ($row['waiter_role'] ?? 'pelayan'));
                                        $roleLabel = $role === 'kasir' ? 'Kasir' : 'Pelayan';
                                    @endphp
                                    @if($role === 'kasir')
                                        <span style="display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#fff7ed; color:#9a3412; border:1px solid rgba(15,23,42,0.08);">
                                            {{ $roleLabel }}
                                        </span>
                                    @else
                                        <span style="display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#ecfeff; color:#0f766e; border:1px solid rgba(15,23,42,0.08);">
                                            {{ $roleLabel }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <span class="status done">Selesai: {{ $row['general_done_count'] ?? 0 }}</span>
                                        @if(((int) ($row['general_total_count'] ?? 0)) === 0)
                                            <span class="status pending">Tidak ada tugas dijadwalkan</span>
                                        @elseif(($row['missing_general_done'] ?? false) === true)
                                            <span class="status overdue">Belum ada penyelesaian</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <span class="status done">Selesai: {{ $row['rack_done_count'] ?? 0 }}</span>
                                        @if(((int) ($row['rack_total_count'] ?? 0)) === 0)
                                            <span class="status pending">Tidak ada tugas dijadwalkan</span>
                                        @elseif(($row['missing_rack_done'] ?? false) === true)
                                            <span class="status overdue">Belum ada penyelesaian</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @php $openCount = (int) ($row['total_open_count'] ?? 0); @endphp
                                    @if($openCount > 0)
                                        <span class="status overdue">{{ $openCount }} task</span>
                                        <div style="font-size:12px; color:#7f1d1d; margin-top:4px;">
                                            Umum: {{ $row['general_open_count'] ?? 0 }} • Cek Rak: {{ $row['rack_open_count'] ?? 0 }}
                                        </div>
                                    @else
                                        <span class="status done">Tidak ada task terbuka</span>
                                    @endif
                                </td>
                                <td>
                                    @if((int) ($row['report_count'] ?? 0) > 0)
                                        <span class="status done">{{ $row['report_count'] }} laporan</span>
                                    @else
                                        <span class="status overdue">Belum isi laporan</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                        @foreach(($row['attention_tags'] ?? []) as $tag)
                                            <span style="display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#fff7ed; color:#9a3412; border:1px solid #fdba74;">
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
        @else
            <div class="empty" style="margin: 0; background:#f0fdf4; border-color:#86efac; color:#166534;">
                Semua waiter aktif sudah on-track pada periode ini: tugas umum, cek rak, dan laporan sudah terpenuhi.
            </div>
        @endif
    </div>

    {{-- Statistics Cards --}}
    <div style="
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
                gap: 15px;
                margin-bottom: 30px;
            ">
        <div class="card" style="text-align: center; padding: 20px 15px;">
            <h3 style="color: #667eea; font-size: clamp(28px, 6vw, 36px); margin-bottom: 10px;">{{ count($waiters) }}</h3>
            <p style="color: #666; font-size: 14px;">Total Waiters</p>
        </div>

        <div class="card" style="text-align: center; padding: 20px 15px;">
            <h3 style="color: #28a745; font-size: clamp(28px, 6vw, 36px); margin-bottom: 10px;">
                {{ collect($waiters)->where('is_active', true)->count() }}
            </h3>
            <p style="color: #666; font-size: 14px;">Active Waiters</p>
        </div>

        <div class="card" style="text-align: center; padding: 20px 15px;">
            <h3 style="color: #ffc107; font-size: clamp(28px, 6vw, 36px); margin-bottom: 10px;">
                {{ $settings['order_timeout_minutes'] ?? 3 }}
            </h3>
            <p style="color: #666; font-size: 14px;">Timeout (menit)</p>
        </div>
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
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
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
