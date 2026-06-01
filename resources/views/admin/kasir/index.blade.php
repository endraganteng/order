@extends('admin.layout')

@section('title', 'Jadwal Kasir - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">💰 Jadwal Kasir</h2>
            <div class="page-subtitle">2 kasir tetap + 1 backup finance. Libur Senin/Selasa, jam beda weekday vs weekend.</div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.kasir.index', ['week_start' => $prevWeek]) }}" class="btn" style="background: var(--color-border);">← Minggu Lalu</a>
            <a href="{{ route('admin.kasir.index', ['week_start' => $currentWeek]) }}" class="btn" style="background: var(--color-border);">📅 Minggu Ini</a>
            <a href="{{ route('admin.kasir.index', ['week_start' => $nextWeek]) }}" class="btn btn-primary">Minggu Depan →</a>
        </div>
    </div>

    @if(! empty($error))
        <div class="alert alert-danger">
            <strong>⚠️ Error:</strong> {{ $error }}<br>
            Pastikan ada minimal 2 karyawan dengan role 'kasir' di sistem, atau set preferensi kasir di bawah.
        </div>
    @endif

    @if($schedule)
        <div class="info-banner">
            <div class="info-banner__main">
                <strong>Minggu {{ $schedule['week_iso'] }}</strong>
                <span class="text-muted">({{ \Carbon\Carbon::parse($schedule['week_start'])->isoFormat('D MMM YYYY') }} – {{ \Carbon\Carbon::parse($schedule['week_start'])->addDays(6)->isoFormat('D MMM YYYY') }})</span>
                @if($hasWeekOverride)
                    <span class="badge badge-warning" style="margin-left: 8px;">📌 Custom Saved</span>
                @endif
            </div>
            <div class="info-banner__rotation">
                <span class="text-muted">Backup hari ini:</span>
                <strong class="rotation-holder">{{ $schedule['backup']['name'] ?? 'Tidak ada' }}</strong>
            </div>
        </div>

        @if(! $schedule['validation']['valid'])
            <div class="validation-panel is-error">
                <strong>⚠️ Jadwal melanggar aturan:</strong>
                <ul>@foreach($schedule['validation']['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @else
            <div class="validation-panel is-success">✅ Jadwal valid — semua aturan terpenuhi.</div>
        @endif
    @endif

    {{-- Preferences --}}
    <details class="prefs-panel" {{ empty($preferences) ? 'open' : '' }}>
        <summary>⚙️ Preferensi Global</summary>
        <form id="prefsForm" class="prefs-form">
            @csrf

            @if($schedule)
                <div class="mapping-status-bar">
                    <strong style="font-size: 0.85rem;">🔗 Karyawan Aktif:</strong>
                    <div class="mapping-grid">
                        @foreach($schedule['kasirs'] as $k)
                            <div class="mapping-item is-matched">
                                <strong>{{ $k['name'] }}</strong>
                                <span class="text-muted small">role: {{ $k['role'] ?? '-' }}</span>
                            </div>
                        @endforeach
                        @if($schedule['backup'])
                            <div class="mapping-item is-matched" style="background: #fef3c7; border-color: #fcd34d;">
                                <strong>{{ $schedule['backup']['name'] }}</strong>
                                <span class="text-muted small">backup ({{ $schedule['backup']['role'] }})</span>
                            </div>
                        @else
                            <div class="mapping-item is-missing">
                                <strong>Backup</strong>
                                <span class="small">❌ Belum dipilih</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="prefs-section">
                <label class="form-label"><strong>Pilih 2 Kasir + 1 Backup</strong></label>
                <div class="prefs-grid" style="margin-top: 6px;">
                    <div class="pref-item">
                        <label class="form-label small">Kasir 1</label>
                        <select name="kasir_ids[]" class="form-control">
                            <option value="">— Pilih kasir —</option>
                            @foreach($allWaiters as $w)
                                <option value="{{ $w['id'] }}" @if(($schedule['kasirs'][0]['id'] ?? null) === $w['id']) selected @endif>
                                    {{ $w['name'] }}@if($w['role']) ({{ $w['role'] }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pref-item">
                        <label class="form-label small">Kasir 2</label>
                        <select name="kasir_ids[]" class="form-control">
                            <option value="">— Pilih kasir —</option>
                            @foreach($allWaiters as $w)
                                <option value="{{ $w['id'] }}" @if(($schedule['kasirs'][1]['id'] ?? null) === $w['id']) selected @endif>
                                    {{ $w['name'] }}@if($w['role']) ({{ $w['role'] }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pref-item">
                        <label class="form-label small">Backup (Finance)</label>
                        <select name="backup_id" class="form-control">
                            <option value="">— Tidak ada backup —</option>
                            @foreach($allWaiters as $w)
                                <option value="{{ $w['id'] }}" @if(($schedule['backup']['id'] ?? null) === $w['id']) selected @endif>
                                    {{ $w['name'] }}@if($w['role']) ({{ $w['role'] }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if($schedule)
                <div class="prefs-section">
                    <label class="form-label"><strong>Hari Libur per Kasir</strong></label>
                    <div class="prefs-grid" style="margin-top: 6px;">
                        @foreach($schedule['kasirs'] as $k)
                            @php $currentLibur = $schedule['libur_days'][$k['name']] ?? 'monday'; @endphp
                            <div class="pref-item">
                                <label class="form-label small"><strong>{{ $k['name'] }}</strong></label>
                                <select name="libur_days[{{ $k['name'] }}]" class="form-control">
                                    <option value="monday" @if($currentLibur === 'monday') selected @endif>Senin</option>
                                    <option value="tuesday" @if($currentLibur === 'tuesday') selected @endif>Selasa</option>
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">Kedua kasir harus pilih hari libur yang berbeda (Senin atau Selasa).</small>
                </div>
            @endif

            <div class="prefs-actions">
                <button type="button" class="btn btn-primary" onclick="savePrefs()">💾 Simpan Preferensi</button>
            </div>
        </form>
    </details>

    @if($schedule)
        {{-- Schedule matrix --}}
        <div class="card-block">
            <div class="card-block__header">
                <h3 class="card-block__title">📊 Jadwal Mingguan</h3>
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm" id="toggleEditBtn" onclick="toggleEditMode()" style="background: #eef2ff; color: #4338ca; border-color: #c7d2fe;">✏️ Edit Manual</button>
                    <button type="button" class="btn btn-sm btn-primary" id="saveWeekBtn" onclick="saveWeek()" style="display: none;">💾 Simpan Minggu Ini</button>
                    <button type="button" class="btn btn-sm" id="cancelEditBtn" onclick="cancelEdit()" style="display: none; background: #f3f4f6;">✕ Batal</button>
                    @if($hasWeekOverride)
                        <button type="button" class="btn btn-sm" onclick="resetWeek()" style="background: #fee2e2; color: #991b1b; border-color: #fca5a5;">🔄 Reset ke Default</button>
                    @endif
                </div>
            </div>

            <div class="table-scroll">
                <table class="table schedule-matrix" id="scheduleMatrix">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            @foreach($schedule['kasirs'] as $k)
                                <th class="text-center">{{ $k['name'] }}</th>
                            @endforeach
                            @if($schedule['backup'])
                                <th class="text-center" style="background: #fef3c7;">{{ $schedule['backup']['name'] }} <span class="badge badge-warning" style="font-size: 0.65rem;">Backup</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedule['matrix'] as $day)
                            <tr class="{{ $day['is_weekend'] ? 'is-weekend' : '' }}" data-day-key="{{ $day['day_key'] }}">
                                <td>
                                    <strong>{{ $day['day_label'] }}</strong>
                                    <div class="text-muted small">{{ $day['date_label'] }}</div>
                                </td>
                                @foreach($day['assignments'] as $a)
                                    @php
                                        $shift = $a['shift'];
                                        $meta = $a['shift_meta'];
                                        $empName = $a['employee']['name'] ?? '';
                                        $badgeClass = match($shift) {
                                            'SHIFT_1' => 'shift-badge shift-pagi',
                                            'SHIFT_2' => 'shift-badge shift-sore',
                                            default => 'shift-badge shift-libur',
                                        };
                                        $shiftLabel = match($shift) {
                                            'SHIFT_1' => 'SHIFT 1',
                                            'SHIFT_2' => 'SHIFT 2',
                                            default => 'LIBUR',
                                        };
                                    @endphp
                                    <td class="text-center cell-shift" data-day="{{ $day['day_key'] }}" data-emp="{{ $empName }}" data-orig="{{ $shift }}">
                                        <div class="shift-display {{ $badgeClass }}">
                                            <strong>{{ $shiftLabel }}</strong>
                                            @if($meta['start'] ?? null)
                                                <div class="shift-time">{{ $meta['start'] }}–{{ $meta['end'] }}</div>
                                            @endif
                                        </div>
                                        <select class="shift-edit form-control form-control-sm" style="display: none;">
                                            <option value="SHIFT_1" @if($shift === 'SHIFT_1') selected @endif>SHIFT 1 (Pagi)</option>
                                            <option value="SHIFT_2" @if($shift === 'SHIFT_2') selected @endif>SHIFT 2 (Sore)</option>
                                            <option value="LIBUR" @if($shift === 'LIBUR') selected @endif>LIBUR</option>
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="livePreview" style="display: none; margin-top: 12px; padding: 12px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md); background: #f8fafc;">
                <div style="font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">🔍 Preview Validasi:</div>
                <div id="livePreviewContent" class="text-muted small">Klik "Validasi" untuk cek aturan setelah ubah cell.</div>
                <button type="button" class="btn btn-sm" onclick="validatePreview()" style="margin-top: 8px; background: #fff;">Validasi Perubahan</button>
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
                            <th>Role</th>
                            <th class="text-center">Shift 1</th>
                            <th class="text-center">Shift 2</th>
                            <th class="text-center">Libur</th>
                            <th>Hari Libur</th>
                            <th class="text-right">Total Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedule['summary'] as $s)
                            <tr>
                                <td><strong>{{ $s['name'] }}</strong></td>
                                <td><span class="badge {{ $s['role'] === 'finance' ? 'badge-warning' : 'badge-info' }}">{{ $s['role'] ?? '-' }}</span></td>
                                <td class="text-center">{{ $s['shift_1_count'] }}×</td>
                                <td class="text-center">{{ $s['shift_2_count'] }}×</td>
                                <td class="text-center">{{ $s['libur_count'] }}×</td>
                                <td>{{ $s['libur_day'] ?? '-' }}</td>
                                <td class="text-right"><strong>{{ number_format($s['total_hours'], 1, ',', '.') }} jam</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Info card --}}
        <div class="card-block">
            <h3 class="card-block__title">ℹ️ Aturan Jadwal Kasir</h3>
            <div class="rules-grid">
                <div class="rule-item">🏪 <strong>Toko buka:</strong> 06:30–21:00 setiap hari</div>
                <div class="rule-item">⏰ <strong>Weekday Shift 1:</strong> 06:30–15:30 (9 jam)</div>
                <div class="rule-item">⏰ <strong>Weekday Shift 2:</strong> 12:30–21:00 (8.5 jam)</div>
                <div class="rule-item">⏰ <strong>Weekend Shift 1:</strong> 06:30–17:00 (10.5 jam)</div>
                <div class="rule-item">⏰ <strong>Weekend Shift 2:</strong> 10:30–21:00 (10.5 jam)</div>
                <div class="rule-item">🏖️ <strong>Libur:</strong> Senin atau Selasa, kedua kasir harus beda hari</div>
                <div class="rule-item">🛟 <strong>Backup (finance):</strong> Cover saat kasir libur (Sen+Sel)</div>
            </div>
        </div>
    @endif

    @push('styles')
    <style>
        .info-banner { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 1px solid #fcd34d; border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 14px; flex-wrap: wrap; gap: 12px; }
        .info-banner__main { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
        .rotation-holder { color: #92400e; background: #fff; padding: 3px 10px; border-radius: 999px; border: 1px solid #fcd34d; margin-left: 6px; }
        .validation-panel { border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 14px; font-size: 0.9rem; }
        .validation-panel ul { margin: 6px 0 0 0; padding-left: 22px; }
        .validation-panel.is-success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .validation-panel.is-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .prefs-panel { background: #f8fafc; border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 14px; }
        .prefs-panel summary { padding: 12px 16px; cursor: pointer; font-weight: 600; user-select: none; list-style: none; display: flex; align-items: center; justify-content: space-between; }
        .prefs-panel summary::after { content: '▾'; transition: transform 0.2s; }
        .prefs-panel[open] summary::after { transform: rotate(180deg); }
        .prefs-form { padding: 0 16px 16px 16px; }
        .prefs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .pref-item { background: #fff; padding: 10px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); }
        .prefs-section { background: #fff; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); margin-bottom: 12px; }
        .prefs-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .mapping-status-bar { background: #fff; padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); margin-bottom: 12px; }
        .mapping-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-top: 6px; }
        .mapping-item { padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid; display: flex; flex-direction: column; gap: 2px; font-size: 0.85rem; }
        .mapping-item.is-matched { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
        .mapping-item.is-missing { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
        .form-label.small { font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 4px; display: block; }
        .card-block { background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px 16px 16px 16px; margin-bottom: 14px; }
        .card-block__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
        .card-block__title { margin: 0; font-size: 1rem; font-weight: 700; }
        .schedule-matrix th { background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; color: var(--color-text-muted); }
        .schedule-matrix tr.is-weekend td { background: #fffbeb; }
        .schedule-matrix td { padding: 10px 8px; vertical-align: middle; }
        .shift-badge { display: inline-block; padding: 6px 10px; border-radius: var(--radius-sm); font-size: 0.78rem; min-width: 88px; text-align: center; line-height: 1.2; }
        .shift-badge .shift-time { font-size: 0.7rem; opacity: 0.85; margin-top: 2px; display: block; }
        .shift-pagi { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
        .shift-sore { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .shift-libur { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .rules-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 8px; }
        .rule-item { font-size: 0.85rem; padding: 8px 10px; background: #f8fafc; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
        .form-control-sm { padding: 4px 6px; font-size: 0.8rem; width: 100%; min-width: 96px; }
        .cell-shift .shift-edit { background: #fff; }
        .schedule-matrix tbody tr.is-editing td { background: #fefce8; }
    </style>
    @endpush

    @push('scripts')
    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const PATH = window.location.pathname;
        const BASE = PATH.replace(/\/admin\/kasir.*$/, '');
        const URLS = {
            savePrefs: BASE + '/admin/kasir/save-preferences',
            saveWeek: BASE + '/admin/kasir/save-week',
            resetWeek: BASE + '/admin/kasir/reset-week',
        };
        const WEEK_ISO = @json($weekIso ?? '');
        const WEEK_START = @json($weekStart ?? '');

        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(body),
            }).then(async (res) => ({ ok: res.ok, data: await res.json().catch(() => ({})) }));
        }

        async function savePrefs() {
            const form = document.getElementById('prefsForm');
            const data = new FormData(form);
            const liburDays = {};
            const kasirIds = [];
            for (const [k, v] of data.entries()) {
                if (k.startsWith('libur_days[')) {
                    const m = k.match(/libur_days\[(.+)\]/);
                    if (m) liburDays[m[1]] = v;
                } else if (k === 'kasir_ids[]') {
                    kasirIds.push(v);
                }
            }
            const payload = {
                kasir_ids: kasirIds,
                backup_id: data.get('backup_id') || null,
                libur_days: liburDays,
            };
            const { ok, data: resp } = await postJson(URLS.savePrefs, payload);
            if (ok && resp.success) {
                alert(resp.message);
                location.reload();
            } else {
                alert((resp.message || 'Gagal') + (resp.errors ? '\n- ' + resp.errors.join('\n- ') : ''));
            }
        }

        async function resetWeek() {
            if (!confirm('Reset jadwal minggu ini ke default?')) return;
            const { ok, data } = await postJson(URLS.resetWeek, { week_iso: WEEK_ISO });
            if (ok && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Gagal reset.');
            }
        }

        // ===== Edit Manual Mode =====
        let isEditing = false;

        function toggleEditMode() {
            if (isEditing) {
                cancelEdit();
                return;
            }
            isEditing = true;
            document.querySelectorAll('.shift-display').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.shift-edit').forEach(el => el.style.display = '');
            document.getElementById('toggleEditBtn').style.display = 'none';
            document.getElementById('saveWeekBtn').style.display = '';
            document.getElementById('cancelEditBtn').style.display = '';
            document.getElementById('livePreview').style.display = '';
        }

        function cancelEdit() {
            isEditing = false;
            // Reset selects ke nilai original
            document.querySelectorAll('.cell-shift').forEach(td => {
                const sel = td.querySelector('.shift-edit');
                if (sel) sel.value = td.dataset.orig;
            });
            document.querySelectorAll('.shift-display').forEach(el => el.style.display = '');
            document.querySelectorAll('.shift-edit').forEach(el => el.style.display = 'none');
            document.getElementById('toggleEditBtn').style.display = '';
            document.getElementById('saveWeekBtn').style.display = 'none';
            document.getElementById('cancelEditBtn').style.display = 'none';
            document.getElementById('livePreview').style.display = 'none';
            document.getElementById('livePreviewContent').innerHTML = 'Klik "Validasi" untuk cek aturan setelah ubah cell.';
        }

        function collectCells() {
            const cells = {};
            document.querySelectorAll('.cell-shift').forEach(td => {
                const day = td.dataset.day;
                const emp = td.dataset.emp;
                const sel = td.querySelector('.shift-edit');
                if (!day || !emp || !sel) return;
                if (!cells[day]) cells[day] = {};
                cells[day][emp] = sel.value;
            });
            return cells;
        }

        async function validatePreview() {
            const previewEl = document.getElementById('livePreviewContent');
            previewEl.innerHTML = '⏳ Validating...';
            const cells = collectCells();
            const { ok, data } = await postJson(URLS.saveWeek, {
                week_iso: WEEK_ISO,
                week_start: WEEK_START,
                cells: cells,
                _dry_run: true, // backend ignore tapi kita pisahkan via flag tambahan
            });
            // Backend nge-save kalau valid. Untuk preview-only, kita parse hasil.
            if (ok && data.success) {
                previewEl.innerHTML = '<span style="color:#065f46;">✅ Valid — perubahan tersimpan otomatis.</span>';
                setTimeout(() => location.reload(), 1200);
            } else {
                const errors = (data.errors || [data.message || 'Tidak valid']).map(e => `<li>${e}</li>`).join('');
                previewEl.innerHTML = `<div style="color:#991b1b;"><strong>❌ Tidak valid:</strong><ul>${errors}</ul></div>`;
            }
        }

        async function saveWeek() {
            const cells = collectCells();
            const { ok, data } = await postJson(URLS.saveWeek, {
                week_iso: WEEK_ISO,
                week_start: WEEK_START,
                cells: cells,
            });
            if (ok && data.success) {
                alert(data.message);
                location.reload();
            } else {
                const errors = (data.errors || [data.message || 'Gagal']).join('\n- ');
                alert('Gagal: \n- ' + errors);
            }
        }
    </script>
    @endpush
@endsection
