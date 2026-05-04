@extends('admin.layout')

@section('title', 'Absensi Harian - Admin')

@push('styles')
<style>
    .att-date-nav {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .att-date-nav input[type="date"] {
        padding: 8px 12px;
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 15px;
    }
    .att-date-nav input[type="date"]:focus {
        border-color: var(--color-primary);
        outline: none;
    }
    .att-date-btn {
        padding: 8px 14px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: #fff;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }
    .att-date-btn:hover { background: #f1f5f9; }

    .att-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .att-kpi {
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 14px;
        text-align: center;
        border-left: 4px solid #cbd5e1;
    }
    .att-kpi-value { font-size: 28px; font-weight: 800; line-height: 1.1; }
    .att-kpi-label { font-size: 12px; color: var(--color-text-muted); margin-top: 4px; }
    .att-kpi.green { border-left-color: #22c55e; }
    .att-kpi.green .att-kpi-value { color: #16a34a; }
    .att-kpi.blue { border-left-color: #3b82f6; }
    .att-kpi.blue .att-kpi-value { color: #2563eb; }
    .att-kpi.orange { border-left-color: #f59e0b; }
    .att-kpi.orange .att-kpi-value { color: #d97706; }
    .att-kpi.gray { border-left-color: #94a3b8; }
    .att-kpi.gray .att-kpi-value { color: #64748b; }
    .att-kpi.purple { border-left-color: #a855f7; }
    .att-kpi.purple .att-kpi-value { color: #9333ea; }

    .att-badge {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }
    .att-badge.present { background: #dcfce7; color: #166534; }
    .att-badge.late { background: #fff7ed; color: #9a3412; }
    .att-badge.absent { background: #fef2f2; color: #991b1b; }
    .att-badge.day_off { background: #eff6ff; color: #1e40af; }
    .att-badge.sick { background: #faf5ff; color: #7e22ce; }
    .att-badge.not_yet { background: #f1f5f9; color: #475569; }

    .att-table { width: 100%; border-collapse: collapse; }
    .att-table th, .att-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
    .att-table th { background: #f8fafc; font-weight: 600; color: #334155; font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; }

    .att-actions { display: flex; gap: 6px; }

    .att-modal-overlay {
        position: fixed; inset: 0; background: rgba(15,23,42,0.6);
        display: none; align-items: center; justify-content: center;
        padding: 16px; z-index: 2000;
    }
    .att-modal-overlay.is-open { display: flex; }
    .att-modal {
        background: #fff; border-radius: var(--radius-lg); padding: 24px;
        max-width: 480px; width: 100%; box-shadow: 0 14px 34px rgba(0,0,0,0.25);
    }
    .att-modal h3 { margin: 0 0 16px; font-size: 18px; color: var(--color-text); }
    .att-modal .form-group { margin-bottom: 14px; }
    .att-modal label { display: block; font-size: 13px; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 4px; }
    .att-modal input, .att-modal select, .att-modal textarea {
        width: 100%; padding: 8px 12px; border: 2px solid var(--color-border);
        border-radius: var(--radius-md); font-size: 14px;
    }
    .att-modal textarea { resize: vertical; min-height: 60px; }
    .att-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }

    @media (max-width: 768px) {
        .att-table-wrap { display: none; }
        .att-mobile-cards { display: flex; flex-direction: column; gap: 12px; }
        .att-m-card {
            border: 1px solid var(--color-border); border-radius: var(--radius-lg);
            padding: 14px; background: #fff; box-shadow: var(--shadow-sm);
        }
        .att-m-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .att-m-card-name { font-weight: 700; font-size: 15px; }
        .att-m-card-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        .att-m-card-field-label { font-size: 11px; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; }
        .att-m-card-actions { display: flex; gap: 6px; padding-top: 10px; border-top: 1px solid var(--color-border); }
    }
    @media (min-width: 769px) {
        .att-mobile-cards { display: none; }
    }
</style>
@endpush

@section('content')
@php
    $dayMap = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];
    $dayOfWeek = (int) date('N', strtotime($date));
    $dayName = $dayMap[$dayOfWeek] ?? '';

    $totalPresent = 0; $totalOnTime = 0; $totalLate = 0; $totalNotYet = 0; $totalDayOff = 0;

    foreach ($waiters as $w) {
        $wId = $w['id'] ?? '';
        $att = $attendanceByDate[$wId] ?? null;
        $sched = $schedules[$wId] ?? null;
        $isWorkDay = true;
        if ($sched && $dayName !== '') {
            $isWorkDay = !empty($sched[$dayName]);
        }

        if (!$isWorkDay) { $totalDayOff++; continue; }
        if (!$att || empty($att['clock_in'])) {
            $status = $att['status'] ?? null;
            if ($status === 'day_off') { $totalDayOff++; }
            else { $totalNotYet++; }
            continue;
        }
        $status = $att['status'] ?? 'present';
        if ($status === 'late') { $totalLate++; $totalPresent++; }
        elseif ($status === 'day_off') { $totalDayOff++; }
        else { $totalOnTime++; $totalPresent++; }
    }
@endphp

<div class="page-header">
    <div>
        <h2 class="page-title">Absensi Harian</h2>
        <p class="page-subtitle">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</p>
    </div>
    <a href="{{ route('admin.attendance.qr') }}" class="btn btn-primary">🔲 Kelola QR</a>
</div>

<div class="att-date-nav">
    <button class="att-date-btn" onclick="navDate(-1)">◀</button>
    <input type="date" id="attDatePicker" value="{{ $date }}" onchange="goToDate(this.value)">
    <button class="att-date-btn" onclick="navDate(1)">▶</button>
    <button class="att-date-btn" onclick="goToDate('{{ date('Y-m-d') }}')">Hari Ini</button>
</div>

<div class="att-kpi-grid">
    <div class="att-kpi green">
        <div class="att-kpi-value">{{ $totalPresent }}</div>
        <div class="att-kpi-label">Total Hadir</div>
    </div>
    <div class="att-kpi blue">
        <div class="att-kpi-value">{{ $totalOnTime }}</div>
        <div class="att-kpi-label">Tepat Waktu</div>
    </div>
    <div class="att-kpi orange">
        <div class="att-kpi-value">{{ $totalLate }}</div>
        <div class="att-kpi-label">Terlambat</div>
    </div>
    <div class="att-kpi gray">
        <div class="att-kpi-value">{{ $totalNotYet }}</div>
        <div class="att-kpi-label">Belum Absen</div>
    </div>
    <div class="att-kpi purple">
        <div class="att-kpi-value">{{ $totalDayOff }}</div>
        <div class="att-kpi-label">Libur</div>
    </div>
</div>

<div class="card">
    <div class="att-table-wrap" style="overflow-x:auto;">
        <table class="att-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status</th>
                    <th>Telat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($waiters as $w)
                @php
                    $wId = $w['id'] ?? '';
                    $att = $attendanceByDate[$wId] ?? null;
                    $todayShift = $todayShifts[$wId] ?? null;
                    $clockIn = $att['clock_in'] ?? '-';
                    $clockOut = $att['clock_out'] ?? '-';
                    $lateMin = (int) ($att['late_minutes'] ?? 0);

                    $sched = $schedules[$wId] ?? null;
                    $isWorkDay = true;
                    if ($sched && $dayName !== '') {
                        $isWorkDay = !empty($sched[$dayName]);
                    }

                    if (!$isWorkDay) {
                        $status = 'day_off';
                    } elseif (!$att || empty($att['clock_in'])) {
                        $status = $att['status'] ?? 'not_yet';
                    } else {
                        $status = $att['status'] ?? 'present';
                    }

                    $statusLabels = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Absen', 'day_off' => 'Libur', 'sick' => 'Sakit', 'not_yet' => 'Belum Absen'];
                    $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                @endphp
                <tr>
                    <td style="font-weight:600;">{{ $w['name'] ?? '-' }}</td>
                    <td>
                        @if($todayShift)
                            <span class="time-badge">{{ $todayShift['name'] ?? '-' }} ({{ $todayShift['clock_in_time'] ?? '' }})</span>
                        @else
                            <span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:13px;font-weight:600;background:#fef2f2;color:#991b1b;">Libur</span>
                        @endif
                    </td>
                    <td>{{ $clockIn }}</td>
                    <td>{{ $clockOut }}</td>
                    <td><span class="att-badge {{ $status }}">{{ $statusLabel }}</span></td>
                    <td>{{ $lateMin > 0 ? $lateMin.' mnt' : '-' }}</td>
                    <td>
                        <div class="att-actions">
                            <button class="btn btn-warning btn-sm" onclick="openOverride('{{ $wId }}', '{{ $w['name'] ?? '' }}', '{{ $date }}', '{{ $clockIn }}', '{{ $clockOut }}', '{{ $status }}', '{{ $att['note'] ?? '' }}')">Edit</button>
                            @if($att)
                            <button class="btn btn-danger btn-sm" onclick="deleteAttendance('{{ $wId }}', '{{ $date }}')">Hapus</button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="att-mobile-cards">
        @foreach($waiters as $w)
        @php
            $wId = $w['id'] ?? '';
            $att = $attendanceByDate[$wId] ?? null;
            $todayShift = $todayShifts[$wId] ?? null;
            $clockIn = $att['clock_in'] ?? '-';
            $clockOut = $att['clock_out'] ?? '-';
            $lateMin = (int) ($att['late_minutes'] ?? 0);

            $sched = $schedules[$wId] ?? null;
            $isWorkDay = true;
            if ($sched && $dayName !== '') {
                $isWorkDay = !empty($sched[$dayName]);
            }

            if (!$isWorkDay) { $status = 'day_off'; }
            elseif (!$att || empty($att['clock_in'])) { $status = $att['status'] ?? 'not_yet'; }
            else { $status = $att['status'] ?? 'present'; }

            $statusLabels = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Absen', 'day_off' => 'Libur', 'sick' => 'Sakit', 'not_yet' => 'Belum Absen'];
            $statusLabel = $statusLabels[$status] ?? ucfirst($status);
        @endphp
        <div class="att-m-card">
            <div class="att-m-card-header">
                <span class="att-m-card-name">{{ $w['name'] ?? '-' }}</span>
                <span class="att-badge {{ $status }}">{{ $statusLabel }}</span>
            </div>
            <div class="att-m-card-grid">
                <div>
                    <div class="att-m-card-field-label">Shift</div>
                    <div>
                        @if($todayShift)
                            <span class="time-badge">{{ $todayShift['name'] ?? '-' }} ({{ $todayShift['clock_in_time'] ?? '' }})</span>
                        @else
                            <span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:13px;font-weight:600;background:#fef2f2;color:#991b1b;">Libur</span>
                        @endif
                    </div>
                </div>
                <div><div class="att-m-card-field-label">Telat</div><div>{{ $lateMin > 0 ? $lateMin.' mnt' : '-' }}</div></div>
                <div><div class="att-m-card-field-label">Masuk</div><div>{{ $clockIn }}</div></div>
                <div><div class="att-m-card-field-label">Keluar</div><div>{{ $clockOut }}</div></div>
            </div>
            <div class="att-m-card-actions">
                <button class="btn btn-warning btn-sm" onclick="openOverride('{{ $wId }}', '{{ $w['name'] ?? '' }}', '{{ $date }}', '{{ $clockIn }}', '{{ $clockOut }}', '{{ $status }}', '{{ $att['note'] ?? '' }}')">Edit</button>
                @if($att)
                <button class="btn btn-danger btn-sm" onclick="deleteAttendance('{{ $wId }}', '{{ $date }}')">Hapus</button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

<div class="att-modal-overlay" id="overrideModal">
    <div class="att-modal">
        <h3>Override Absensi — <span id="omWaiterName"></span></h3>
        <input type="hidden" id="omWaiterId">
        <input type="hidden" id="omDate">
        <div class="form-group">
            <label>Jam Masuk</label>
            <input type="time" id="omClockIn">
        </div>
        <div class="form-group">
            <label>Jam Keluar</label>
            <input type="time" id="omClockOut">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="omStatus">
                <option value="present">Hadir</option>
                <option value="late">Terlambat</option>
                <option value="absent">Absen</option>
                <option value="day_off">Libur</option>
                <option value="sick">Sakit</option>
            </select>
        </div>
        <div class="form-group">
            <label>Catatan</label>
            <textarea id="omNote" placeholder="Opsional..."></textarea>
        </div>
        <div class="att-modal-actions">
            <button class="btn btn-secondary" onclick="closeOverride()">Batal</button>
            <button class="btn btn-primary" onclick="submitOverride()">Simpan</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function navDate(offset) {
    var current = document.getElementById('attDatePicker').value;
    var d = new Date(current);
    d.setDate(d.getDate() + offset);
    goToDate(d.toISOString().split('T')[0]);
}

function goToDate(dateStr) {
    window.location.href = '{{ route("admin.attendance.index") }}?date=' + dateStr;
}

function openOverride(waiterId, name, date, clockIn, clockOut, status, note) {
    document.getElementById('omWaiterId').value = waiterId;
    document.getElementById('omDate').value = date;
    document.getElementById('omWaiterName').textContent = name;
    document.getElementById('omClockIn').value = (clockIn !== '-') ? clockIn : '';
    document.getElementById('omClockOut').value = (clockOut !== '-') ? clockOut : '';
    document.getElementById('omStatus').value = status || 'present';
    document.getElementById('omNote').value = note || '';
    document.getElementById('overrideModal').classList.add('is-open');
}

function closeOverride() {
    document.getElementById('overrideModal').classList.remove('is-open');
}

function submitOverride() {
    var waiterId = document.getElementById('omWaiterId').value;
    var date = document.getElementById('omDate').value;
    var payload = {
        clock_in: document.getElementById('omClockIn').value,
        clock_out: document.getElementById('omClockOut').value,
        status: document.getElementById('omStatus').value,
        note: document.getElementById('omNote').value
    };

    fetch('/admin/attendance/' + waiterId + '/' + date + '/override', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Gagal menyimpan');
        }
    })
    .catch(function() { alert('Terjadi kesalahan jaringan'); });
}

function deleteAttendance(waiterId, date) {
    if (!confirm('Hapus data absensi ini?')) return;

    fetch('/admin/attendance/' + waiterId + '/' + date, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Gagal menghapus');
        }
    })
    .catch(function() { alert('Terjadi kesalahan jaringan'); });
}

document.getElementById('overrideModal').addEventListener('click', function(e) {
    if (e.target === this) closeOverride();
});
</script>
@endpush
