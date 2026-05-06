@extends('admin.layout')

@section('title', 'Template Jadwal - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Template Jadwal</h2>
            <div class="page-subtitle">Atur jadwal permanen. Perubahan berlaku untuk seterusnya.</div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.shifts.index') }}" class="btn" style="background: var(--color-border);">Kelola Shift</a>
            <button type="button" class="btn btn-primary" id="btnSaveAll" onclick="saveScheduleTemplate()">💾 Simpan Template</button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div style="padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-sm); font-size: 13px; color: #1e40af; margin-bottom: 16px;">
        ℹ️ Jadwal ini berlaku permanen. Setiap perubahan yang disimpan akan langsung berlaku untuk minggu ini dan seterusnya.
    </div>

    @push('styles')
    <style>
        .schedule-table-desktop { display: block; }
        .schedule-cards-mobile { display: none; }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        .schedule-table th,
        .schedule-table td {
            padding: 8px 4px;
            text-align: center;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
        }
        .schedule-table th {
            background: #f8f9fa;
            font-weight: 700;
            color: var(--color-text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 10px 4px;
        }
        .schedule-table td:first-child,
        .schedule-table th:first-child {
            text-align: left;
            min-width: 130px;
            padding-left: 12px;
        }

        .schedule-cell { position: relative; }
        .schedule-cell.off { background: #fef2f2; }
        .schedule-cell.working { background: #f0fdf4; }

        .shift-select {
            width: 100%;
            min-width: 90px;
            padding: 5px 4px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 12px;
            background: #fff;
            cursor: pointer;
        }
        .shift-select:focus { border-color: var(--color-primary); outline: none; }

        .waiter-name-cell { font-weight: 600; color: var(--color-text); font-size: 13px; }
        .waiter-role-tag {
            display: inline-block;
            font-size: 10px;
            padding: 1px 5px;
            border-radius: 4px;
            font-weight: 600;
            margin-left: 4px;
        }
        .waiter-role-tag.kasir { background: #fff7ed; color: #9a3412; }
        .waiter-role-tag.pelayan { background: #ecfeff; color: #0f766e; }

        .day-header-sun { color: var(--color-danger); }

        @media (max-width: 900px) {
            .schedule-table-desktop { display: none; }
            .schedule-cards-mobile {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .schedule-mobile-card {
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 14px;
                background: #fff;
                box-shadow: var(--shadow-sm);
            }
            .schedule-mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }
            .schedule-mobile-name {
                font-weight: 700;
                color: var(--color-text);
                font-size: 15px;
            }
            .schedule-mobile-days {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .schedule-day-row {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 6px 8px;
                border-radius: var(--radius-sm);
            }
            .schedule-day-row.off { background: #fef2f2; }
            .schedule-day-row.working { background: #f0fdf4; }
            .schedule-day-label {
                font-size: 13px;
                font-weight: 600;
                color: var(--color-text-secondary);
                min-width: 60px;
            }
            .schedule-day-label.sunday { color: var(--color-danger); }
            .schedule-day-row select {
                flex: 1;
                padding: 7px 8px;
                border: 1px solid var(--color-border);
                border-radius: var(--radius-sm);
                font-size: 13px;
            }
        }
    </style>
    @endpush

    @php
        $days = ['monday' => 'Sen', 'tuesday' => 'Sel', 'wednesday' => 'Rab', 'thursday' => 'Kam', 'friday' => 'Jum', 'saturday' => 'Sab', 'sunday' => 'Min'];
    @endphp

    {{-- Desktop Table --}}
    <div class="card schedule-table-desktop" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto; padding: 12px;">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Karyawan</th>
                        @foreach($days as $dayKey => $dayLabel)
                            <th class="{{ $dayKey === 'sunday' ? 'day-header-sun' : '' }}">
                                {{ $dayLabel }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($waiters as $waiter)
                        @php
                            $wId = $waiter['id'];
                            $wName = $waiter['name'] ?? '-';
                            $wRole = strtolower($waiter['waiter_role'] ?? 'pelayan');
                            $wSchedule = $scheduleMap[$wId] ?? [];
                        @endphp
                        <tr>
                            <td>
                                <span class="waiter-name-cell">{{ $wName }}</span>
                                <span class="waiter-role-tag {{ $wRole }}">{{ ucfirst($wRole) }}</span>
                            </td>
                            @foreach($days as $dayKey => $dayLabel)
                                @php $cellValue = $wSchedule[$dayKey] ?? 'off'; @endphp
                                <td class="schedule-cell {{ $cellValue === 'off' ? 'off' : 'working' }}">
                                    <select class="shift-select js-shift-select"
                                            data-waiter-id="{{ $wId }}"
                                            data-day="{{ $dayKey }}"
                                            onchange="updateCellColor(this)">
                                        <option value="off" {{ $cellValue === 'off' ? 'selected' : '' }}>Libur</option>
                                        @foreach($shifts as $shift)
                                            <option value="{{ $shift['id'] }}" {{ $cellValue === $shift['id'] ? 'selected' : '' }}>
                                                {{ $shift['name'] }} ({{ $shift['clock_in_time'] }}-{{ $shift['clock_out_time'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 24px;">Belum ada karyawan terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Card Layout --}}
    <div class="schedule-cards-mobile">
        @forelse($waiters as $waiter)
            @php
                $wId = $waiter['id'];
                $wName = $waiter['name'] ?? '-';
                $wRole = strtolower($waiter['waiter_role'] ?? 'pelayan');
                $wSchedule = $scheduleMap[$wId] ?? [];
            @endphp
            <div class="schedule-mobile-card">
                <div class="schedule-mobile-header">
                    <div>
                        <div class="schedule-mobile-name">{{ $wName }}</div>
                        <span class="waiter-role-tag {{ $wRole }}">{{ ucfirst($wRole) }}</span>
                    </div>
                </div>
                <div class="schedule-mobile-days">
                    @foreach($days as $dayKey => $dayLabel)
                        @php $cellValue = $wSchedule[$dayKey] ?? 'off'; @endphp
                        <div class="schedule-day-row {{ $cellValue === 'off' ? 'off' : 'working' }}">
                            <span class="schedule-day-label {{ $dayKey === 'sunday' ? 'sunday' : '' }}">
                                {{ $dayLabel }}
                            </span>
                            <select class="js-shift-select"
                                    data-waiter-id="{{ $wId }}"
                                    data-day="{{ $dayKey }}"
                                    onchange="updateCellColor(this)">
                                <option value="off" {{ $cellValue === 'off' ? 'selected' : '' }}>Libur</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift['id'] }}" {{ $cellValue === $shift['id'] ? 'selected' : '' }}>
                                        {{ $shift['name'] }} ({{ $shift['clock_in_time'] }}-{{ $shift['clock_out_time'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div style="text-align: center; color: var(--color-text-muted); padding: 24px;">Belum ada karyawan terdaftar.</div>
        @endforelse
    </div>

    <script>
        // Sync desktop ↔ mobile selects on change
        document.querySelectorAll('.js-shift-select').forEach(function(select) {
            select.addEventListener('change', function() {
                var wId = this.dataset.waiterId;
                var day = this.dataset.day;
                var val = this.value;
                // Sync all selects with same waiter+day
                document.querySelectorAll('.js-shift-select[data-waiter-id="' + wId + '"][data-day="' + day + '"]').forEach(function(s) {
                    if (s !== select) s.value = val;
                    updateCellColor(s);
                });
            });
        });

        function saveScheduleTemplate() {
            var btn = document.getElementById('btnSaveAll');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            var schedule = {};
            // Only collect from visible selects to avoid duplicates
            var selects = document.querySelectorAll('.schedule-table-desktop .js-shift-select');
            if (selects.length === 0) {
                // Mobile view active
                selects = document.querySelectorAll('.schedule-cards-mobile .js-shift-select');
            }
            selects.forEach(function(select) {
                var wId = select.dataset.waiterId;
                var day = select.dataset.day;
                if (!schedule[wId]) schedule[wId] = {};
                schedule[wId][day] = select.value;
            });

            fetch('{{ route("admin.schedules.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ schedule: schedule })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.textContent = '✅ Tersimpan!';
                    btn.style.background = '#16a34a';
                    btn.style.color = '#fff';
                    setTimeout(function() {
                        btn.textContent = '💾 Simpan Template';
                        btn.style.background = '';
                        btn.style.color = '';
                        btn.disabled = false;
                    }, 2000);
                } else {
                    alert('Gagal: ' + (data.message || 'Error'));
                    btn.disabled = false;
                    btn.textContent = '💾 Simpan Template';
                }
            })
            .catch(function() {
                alert('Koneksi error');
                btn.disabled = false;
                btn.textContent = '💾 Simpan Template';
            });
        }

        function updateCellColor(select) {
            var cell = select.closest('.schedule-cell') || select.closest('.schedule-day-row');
            if (!cell) return;
            if (select.value === 'off') {
                cell.classList.add('off');
                cell.classList.remove('working');
            } else {
                cell.classList.remove('off');
                cell.classList.add('working');
            }
        }
    </script>
@endsection
