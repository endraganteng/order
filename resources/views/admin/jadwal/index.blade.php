@extends('admin.layout')

@section('title', 'Jadwal Shift Retail - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">🗓️ Jadwal Shift Retail</h2>
            <div class="page-subtitle">Generator + customization untuk 3 karyawan retail. Bisa di-apply ke attendance system.</div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.jadwal.index', ['week_start' => $prevWeek]) }}" class="btn" style="background: var(--color-border);">← Minggu Lalu</a>
            <a href="{{ route('admin.jadwal.index', ['week_start' => $currentWeek]) }}" class="btn" style="background: var(--color-border);">📅 Minggu Ini</a>
            <a href="{{ route('admin.jadwal.index', ['week_start' => $nextWeek]) }}" class="btn btn-primary">Minggu Depan →</a>
        </div>
    </div>

    {{-- Week info banner --}}
    <div class="info-banner">
        <div class="info-banner__main">
            <strong>Minggu {{ $schedule['week_iso'] }}</strong>
            <span class="text-muted">({{ \Carbon\Carbon::parse($schedule['week_start'])->isoFormat('D MMM YYYY') }} – {{ \Carbon\Carbon::parse($schedule['week_start'])->addDays(6)->isoFormat('D MMM YYYY') }})</span>
            @if($hasWeekOverride)
                <span class="badge badge-warning" style="margin-left: 8px;">📌 Custom Saved</span>
            @endif
        </div>
        <div class="info-banner__rotation">
            <span class="text-muted">4× Full Shift:</span>
            <strong class="rotation-holder">{{ $schedule['holder_name'] }}</strong>
        </div>
    </div>

    {{-- Validation panel --}}
    @if(! $schedule['validation']['valid'] || count($schedule['validation']['warnings']) > 0)
        <div class="validation-panel {{ $schedule['validation']['valid'] ? 'is-warning' : 'is-error' }}">
            @if(! $schedule['validation']['valid'])
                <strong>⚠️ Jadwal melanggar aturan:</strong>
                <ul>@foreach($schedule['validation']['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul>
            @endif
            @if(count($schedule['validation']['warnings']) > 0)
                <strong>ℹ️ Catatan:</strong>
                <ul>@foreach($schedule['validation']['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
            @endif
        </div>
    @else
        <div class="validation-panel is-success">✅ Jadwal valid — semua aturan terpenuhi.</div>
    @endif

    {{-- Preferences Panel --}}
    <details class="prefs-panel" {{ empty($preferences) ? 'open' : '' }}>
        <summary>⚙️ Preferensi Global (berlaku untuk semua minggu ke depan)</summary>
        <form id="prefsForm" class="prefs-form">
            @csrf
            <div class="prefs-grid">
                @foreach($schedule['employees'] as $emp)
                    @php $currentLibur = $schedule['libur_days'][$emp['name']] ?? 'monday'; @endphp
                    <div class="pref-item">
                        <label class="form-label"><strong>{{ $emp['name'] }}</strong> — Hari Libur</label>
                        <select name="libur_days[{{ $emp['name'] }}]" class="form-control">
                            @foreach($weekdayKeys as $i => $key)
                                <option value="{{ $key }}" @if($currentLibur === $key) selected @endif>{{ $dayLabels[$i] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="prefs-holder">
                <label class="form-label"><strong>Holder 4× Full Shift</strong></label>
                @php $holderMode = $preferences['holder_mode'] ?? 'auto'; @endphp
                <div style="display: flex; gap: 14px; flex-wrap: wrap; align-items: center; margin-top: 6px;">
                    <label class="radio-line">
                        <input type="radio" name="holder_mode" value="auto" @if($holderMode === 'auto') checked @endif onchange="document.getElementById('lockedHolderSelect').disabled=true">
                        Auto rotate (Rendy → Bagas → Anjar)
                    </label>
                    <label class="radio-line">
                        <input type="radio" name="holder_mode" value="locked" @if($holderMode === 'locked') checked @endif onchange="document.getElementById('lockedHolderSelect').disabled=false">
                        Lock ke:
                    </label>
                    <select id="lockedHolderSelect" name="holder_name" class="form-control" style="max-width: 180px;" @if($holderMode !== 'locked') disabled @endif>
                        @foreach($schedule['employees'] as $emp)
                            <option value="{{ $emp['name'] }}" @if(($preferences['holder_name'] ?? '') === $emp['name']) selected @endif>{{ $emp['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="prefs-actions">
                <button type="button" class="btn btn-primary" onclick="savePrefs()">💾 Simpan Preferensi</button>
                <small class="text-muted">Preferensi berlaku untuk semua minggu ke depan, kecuali minggu yang sudah di-save manual.</small>
            </div>
        </form>
    </details>

    {{-- Schedule matrix dengan inline edit --}}
    <div class="card-block">
        <div class="card-block__header">
            <h3 class="card-block__title">📊 Jadwal Mingguan</h3>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <button type="button" id="btnEditMode" class="btn btn-sm" onclick="toggleEditMode()" style="background: var(--color-border);">✏️ Edit Manual</button>
                <button type="button" class="btn btn-sm btn-primary hidden" id="btnSaveWeek" onclick="saveWeek()">💾 Save Minggu Ini</button>
                @if($hasWeekOverride)
                    <button type="button" class="btn btn-sm" onclick="resetWeek()" style="background: #fee2e2; color: #991b1b; border-color: #fca5a5;">🔄 Reset ke Default</button>
                @endif
                <button type="button" class="btn btn-sm" onclick="applyAttendance()" style="background: #d1fae5; color: #065f46; border-color: #6ee7b7;">⚡ Apply ke Attendance</button>
            </div>
        </div>

        <div class="table-scroll desktop-only">
            <table class="table schedule-matrix" id="scheduleTable">
                <thead>
                    <tr>
                        <th>Hari</th>
                        @foreach($schedule['employees'] as $emp)
                            <th class="text-center">{{ $emp['name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule['matrix'] as $day)
                        <tr class="{{ $day['is_weekend'] ? 'is-weekend' : '' }}">
                            <td>
                                <strong>{{ $day['day_label'] }}</strong>
                                <div class="text-muted small">{{ $day['date_label'] }}</div>
                            </td>
                            @foreach($day['assignments'] as $a)
                                @php
                                    $shift = $a['shift'];
                                    $meta = $a['shift_meta'];
                                    $badgeClass = match($shift) {
                                        'FULL' => 'shift-badge shift-full',
                                        'PAGI' => 'shift-badge shift-pagi',
                                        'SORE' => 'shift-badge shift-sore',
                                        default => 'shift-badge shift-libur',
                                    };
                                @endphp
                                <td class="text-center cell-shift" data-day="{{ $day['day_key'] }}" data-employee="{{ $a['employee']['name'] }}" data-shift="{{ $shift }}">
                                    <div class="{{ $badgeClass }} cell-display">
                                        <strong>{{ $shift }}</strong>
                                        @if($meta && $meta['start'])
                                            <div class="shift-time">{{ $meta['start'] }}–{{ $meta['end'] }}</div>
                                        @endif
                                    </div>
                                    <select class="form-control cell-edit hidden" onchange="onCellChange(this)">
                                        @foreach($shiftCodes as $code)
                                            <option value="{{ $code }}" @if($code === $shift) selected @endif>{{ $code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mobile-only">
            @foreach($schedule['matrix'] as $day)
                <div class="mobile-day-card {{ $day['is_weekend'] ? 'is-weekend' : '' }}">
                    <div class="mobile-day-card__header">
                        <strong>{{ $day['day_label'] }}</strong>
                        <span class="text-muted small">{{ $day['date_label'] }}</span>
                    </div>
                    <div class="mobile-day-card__body">
                        @foreach($day['assignments'] as $a)
                            @php
                                $badgeClass = match($a['shift']) {
                                    'FULL' => 'shift-badge shift-full',
                                    'PAGI' => 'shift-badge shift-pagi',
                                    'SORE' => 'shift-badge shift-sore',
                                    default => 'shift-badge shift-libur',
                                };
                            @endphp
                            <div class="mobile-day-card__row">
                                <span class="text-muted">{{ $a['employee']['name'] }}</span>
                                <span class="{{ $badgeClass }}">
                                    <strong>{{ $a['shift'] }}</strong>
                                    @if($a['shift_meta'] && $a['shift_meta']['start'])
                                        <span class="shift-time">{{ $a['shift_meta']['start'] }}–{{ $a['shift_meta']['end'] }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Summary --}}
    <div class="card-block">
        <h3 class="card-block__title">📈 Ringkasan Jam Kerja</h3>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th class="text-center">Full Shift</th>
                        <th class="text-center">Shift Pagi</th>
                        <th class="text-center">Shift Sore</th>
                        <th class="text-center">Libur</th>
                        <th>Hari Libur</th>
                        <th class="text-right">Total Jam</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule['summary'] as $s)
                        <tr>
                            <td><strong>{{ $s['name'] }}</strong></td>
                            <td class="text-center">{{ $s['full_count'] }}× @if($s['full_count'] === 4)<span class="badge badge-warning" style="margin-left: 4px;">4×</span>@endif</td>
                            <td class="text-center">{{ $s['pagi_count'] }}×</td>
                            <td class="text-center">{{ $s['sore_count'] }}×</td>
                            <td class="text-center">{{ $s['libur_count'] }}×</td>
                            <td>{{ $s['libur_day'] ?? '-' }}</td>
                            <td class="text-right"><strong>{{ number_format($s['total_hours'], 1, ',', '.') }} jam</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Breaks --}}
    <div class="card-block">
        <h3 class="card-block__title">☕ Jadwal Istirahat</h3>
        <div class="break-grid">
            @foreach($schedule['breaks'] as $b)
                <div class="break-card {{ $b['mode'] === 'flex' ? 'is-flex' : '' }}">
                    <div class="break-card__header">
                        <strong>{{ $b['day_label'] }}</strong>
                        <span class="text-muted small">{{ $b['date_label'] }}</span>
                    </div>
                    @if($b['mode'] === 'rotation')
                        <div class="break-card__slots">
                            @foreach($b['slots'] as $slot)
                                <div class="break-slot"><span class="break-slot__time">{{ $slot['time'] }}</span><strong>{{ $slot['employee'] }}</strong></div>
                            @endforeach
                        </div>
                    @else
                        <div class="break-card__note">
                            <p style="margin: 0 0 6px 0;"><strong>Mode: Fleksibel</strong></p>
                            <p style="margin: 0; font-size: 0.85rem;">{{ $b['note'] }}</p>
                            @if(!empty($b['workers']))
                                <div style="margin-top: 8px; font-size: 0.85rem;">
                                    <span class="text-muted">Yang masuk:</span> <strong>{{ implode(' & ', $b['workers']) }}</strong>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @push('styles')
    <style>
        .info-banner { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border: 1px solid #c4b5fd; border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 14px; flex-wrap: wrap; gap: 12px; }
        .info-banner__main { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
        .rotation-holder { color: #4338ca; background: #fff; padding: 3px 10px; border-radius: 999px; border: 1px solid #c4b5fd; margin-left: 6px; }

        .validation-panel { border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 14px; font-size: 0.9rem; }
        .validation-panel ul { margin: 6px 0 0 0; padding-left: 22px; }
        .validation-panel.is-success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .validation-panel.is-warning { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
        .validation-panel.is-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        .prefs-panel { background: #f8fafc; border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 14px; }
        .prefs-panel summary { padding: 12px 16px; cursor: pointer; font-weight: 600; user-select: none; list-style: none; display: flex; align-items: center; justify-content: space-between; }
        .prefs-panel summary::after { content: '▾'; transition: transform 0.2s; }
        .prefs-panel[open] summary::after { transform: rotate(180deg); }
        .prefs-form { padding: 0 16px 16px 16px; }
        .prefs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .pref-item { background: #fff; padding: 10px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); }
        .prefs-holder { background: #fff; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); margin-bottom: 14px; }
        .radio-line { display: flex; gap: 6px; align-items: center; cursor: pointer; }
        .prefs-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        .card-block { background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px 16px 16px 16px; margin-bottom: 14px; }
        .card-block__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
        .card-block__title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--color-text, #1e293b); }

        .schedule-matrix th { background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; color: var(--color-text-muted); }
        .schedule-matrix tr.is-weekend td { background: #fffbeb; }
        .schedule-matrix td { padding: 10px 8px; vertical-align: middle; }
        .cell-shift { position: relative; }
        .cell-edit { font-size: 0.85rem; padding: 6px 8px; min-width: 88px; }

        .shift-badge { display: inline-block; padding: 6px 10px; border-radius: var(--radius-sm); font-size: 0.78rem; min-width: 88px; text-align: center; line-height: 1.2; }
        .shift-badge .shift-time { font-size: 0.7rem; opacity: 0.85; margin-top: 2px; display: block; }
        .shift-full { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .shift-pagi { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
        .shift-sore { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .shift-libur { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .break-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .break-card { background: #f8fafc; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 10px 12px; }
        .break-card.is-flex { background: #fffbeb; border-color: #fde68a; }
        .break-card__header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--color-border); }
        .break-card.is-flex .break-card__header { border-bottom-color: #fde68a; }
        .break-card__slots { display: flex; flex-direction: column; gap: 5px; }
        .break-slot { display: flex; justify-content: space-between; font-size: 0.85rem; padding: 3px 0; }
        .break-slot__time { color: var(--color-text-muted); }

        .hidden { display: none !important; }
        .desktop-only { display: block; }
        .mobile-only { display: none; }
        .mobile-day-card { background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-bottom: 8px; overflow: hidden; }
        .mobile-day-card.is-weekend { border-color: #fcd34d; background: #fffbeb; }
        .mobile-day-card__header { display: flex; justify-content: space-between; padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid var(--color-border); }
        .mobile-day-card.is-weekend .mobile-day-card__header { background: #fef3c7; border-bottom-color: #fcd34d; }
        .mobile-day-card__body { padding: 8px 12px; }
        .mobile-day-card__row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 0.85rem; }
        .mobile-day-card__row + .mobile-day-card__row { border-top: 1px dashed var(--color-border); }

        @media (max-width: 768px) {
            .desktop-only { display: none; }
            .mobile-only { display: block; }
            .info-banner { flex-direction: column; align-items: flex-start; }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
        // Build base URL relative to current page (handle WAMP subpath like /endra/order/public)
        const PATH = window.location.pathname; // e.g. /endra/order/public/admin/jadwal
        const BASE = PATH.replace(/\/admin\/jadwal.*$/, ''); // e.g. /endra/order/public
        const URLS = {
            savePrefs: BASE + '/admin/jadwal/save-preferences',
            saveWeek: BASE + '/admin/jadwal/save-week',
            resetWeek: BASE + '/admin/jadwal/reset-week',
            applyAttendance: BASE + '/admin/jadwal/apply-attendance',
        };
        const WEEK_ISO = @json($schedule['week_iso']);
        const WEEK_START = @json($schedule['week_start']);
        const HOLDER_NAME = @json($schedule['holder_name']);

        let editMode = false;

        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(body),
            }).then(async (res) => {
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, status: res.status, data };
            });
        }

        async function savePrefs() {
            const form = document.getElementById('prefsForm');
            const data = new FormData(form);
            const liburDays = {};
            for (const [k, v] of data.entries()) {
                if (k.startsWith('libur_days[')) {
                    const m = k.match(/libur_days\[(.+)\]/);
                    if (m) liburDays[m[1]] = v;
                }
            }
            const payload = {
                libur_days: liburDays,
                holder_mode: data.get('holder_mode') || 'auto',
                holder_name: data.get('holder_name') || null,
            };
            const { ok, data: resp } = await postJson(URLS.savePrefs, payload);
            if (ok && resp.success) {
                alert(resp.message);
                location.reload();
            } else {
                alert((resp.message || 'Gagal') + (resp.errors ? '\n- ' + resp.errors.join('\n- ') : ''));
            }
        }

        function toggleEditMode() {
            editMode = !editMode;
            document.querySelectorAll('.cell-display').forEach(el => el.classList.toggle('hidden', editMode));
            document.querySelectorAll('.cell-edit').forEach(el => el.classList.toggle('hidden', !editMode));
            document.getElementById('btnEditMode').textContent = editMode ? '👁️ View Mode' : '✏️ Edit Manual';
            document.getElementById('btnSaveWeek').classList.toggle('hidden', !editMode);
        }

        function onCellChange(select) {
            const td = select.closest('.cell-shift');
            td.dataset.shift = select.value;
            // Update display badge color preview
            const display = td.querySelector('.cell-display');
            const cls = { FULL: 'shift-full', PAGI: 'shift-pagi', SORE: 'shift-sore', LIBUR: 'shift-libur' }[select.value];
            display.className = 'shift-badge ' + cls + ' cell-display hidden';
            display.querySelector('strong').textContent = select.value;
        }

        async function saveWeek() {
            const cells = {};
            document.querySelectorAll('.cell-shift').forEach(td => {
                const day = td.dataset.day;
                const emp = td.dataset.employee;
                const select = td.querySelector('.cell-edit');
                const shift = select ? select.value : td.dataset.shift;
                if (!cells[day]) cells[day] = {};
                cells[day][emp] = shift;
            });

            const { ok, data } = await postJson(URLS.saveWeek, {
                week_iso: WEEK_ISO,
                week_start: WEEK_START,
                cells,
                holder_name: HOLDER_NAME,
            });
            if (ok && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Gagal save.');
            }
        }

        async function resetWeek() {
            if (!confirm('Reset jadwal minggu ini ke default (hapus customisasi)?')) return;
            const { ok, data } = await postJson(URLS.resetWeek, { week_iso: WEEK_ISO });
            if (ok && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Gagal reset.');
            }
        }

        async function applyAttendance() {
            if (!confirm('Apply jadwal ini ke attendance system existing? Akan overwrite jadwal mingguan untuk 3 karyawan.')) return;
            const { ok, data } = await postJson(URLS.applyAttendance, { week_start: WEEK_START });
            if (ok && data.success) {
                alert(data.message);
            } else {
                alert((data.message || 'Gagal apply') + (data.errors && data.errors.length ? '\n- ' + data.errors.join('\n- ') : ''));
            }
        }
    </script>
    @endpush
@endsection
