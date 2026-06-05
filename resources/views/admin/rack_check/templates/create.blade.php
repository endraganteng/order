@extends('admin.layout')

@section('title', (isset($template) ? 'Edit Template Cek Rak Otomatis' : 'Buat Template Cek Rak Otomatis').' - Admin')

@section('content')
@php
    $templateRackIds = $templateRackIds ?? [];

    $racksJson = collect($racks)->map(fn ($r) => [
        'id' => (string) ($r['id'] ?? ''),
        'name' => (string) ($r['name'] ?? ''),
        'location' => (string) ($r['location'] ?? ''),
        'barcode' => (string) ($r['barcode_value'] ?? ''),
        'locked' => isset($lockedRackMap[$r['id'] ?? '']),
        'locked_reason' => $lockedRackMap[$r['id'] ?? '']['strategy'] ?? '',
    ])->values();

    $defaultAnchor = date('Y-m-d');
@endphp

<div style="max-width: 760px; margin: 0 auto; padding: 0 12px;">
    <div style="margin-bottom: 14px;">
        <a href="{{ route('admin.rack_check.templates.index') }}"
           style="color: var(--color-text-muted); text-decoration: none; font-size: 13px;">← Kembali ke daftar template</a>
    </div>

    <h2 style="margin: 0 0 6px; color: var(--color-text); font-size: clamp(22px, 5vw, 28px);">{{ isset($template) ? "Edit Template Cek Rak Otomatis" : "Buat Template Cek Rak Otomatis" }}</h2>
    <p style="margin: 0 0 16px; color: var(--color-text-muted); font-size: 14px; line-height: 1.6;">
        Template ini akan membuat tugas cek rak otomatis sesuai jadwal. Semua rak yang dipilih digabung dalam satu template.
    </p>

    @if($errors->any())
        <div style="background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); color: #7f1d1d; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            <div style="font-weight: 700; margin-bottom: 4px;">⚠ Tidak bisa menyimpan template:</div>
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Stepper --}}
    <div id="stepperBar" style="display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap;">
        @php
            $steps = [
                1 => ['label' => 'Rak', 'icon' => '📦'],
                2 => ['label' => 'Jadwal', 'icon' => '📅'],
                3 => ['label' => 'Pengaturan', 'icon' => '⚙'],
            ];
        @endphp
        @foreach($steps as $idx => $step)
            <div class="step-pill" data-step="{{ $idx }}"
                 style="flex: 1; min-width: 130px; padding: 10px 14px; border-radius: 8px; border: 2px solid var(--color-border); background: white; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--color-text-muted); transition: all 0.15s;">
                <span style="font-size: 16px;">{{ $step['icon'] }}</span>
                <span>{{ $idx }}. {{ $step['label'] }}</span>
            </div>
        @endforeach
    </div>

    <form id="wizardForm" method="POST" action="{{ isset($template) ? route('admin.rack_check.templates.update', $template['id']) : route('admin.rack_check.templates.store') }}"
          style="background: white; border: 1px solid var(--color-border); border-radius: 12px; box-shadow: var(--shadow-sm);">
        @csrf
        @if(isset($template))
            @method('PUT')
        @endif

        {{-- ============ STEP 1: Pilih Rak ============ --}}
        <div class="wiz-step" data-step="1" style="padding: 22px;">
            <h3 style="margin: 0 0 4px; color: var(--color-text); font-size: 18px;">Pilih rak yang akan dicek rutin</h3>
            <p style="margin: 0 0 14px; color: var(--color-text-muted); font-size: 13px;">Pilih satu atau banyak rak. Semua rak digabung dalam satu template.</p>

            {{-- Template name field --}}
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 6px;">Nama template (opsional)</label>
                <input type="text" name="template_name" value="{{ isset($template) ? ($template['name'] ?? '') : '' }}" placeholder="Otomatis dari pilihan rak"
                       style="width: 100%; padding: 10px 14px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--color-text-muted);">Kosongkan untuk auto-generate dari nama rak yang dipilih.</p>
            </div>

            <div style="margin-bottom: 12px;">
                <input type="text" id="rackSearch" placeholder="Cari nama rak atau barcode…"
                       style="width: 100%; padding: 10px 14px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
            </div>

            <div id="rackList" style="max-height: 360px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding-right: 4px;">
                @forelse($racksJson as $r)
                    @php
                        $isCheckedRack = isset($templateRackIds) && in_array($r['id'], $templateRackIds, true);
                    @endphp
                    <label class="rack-item"
                           data-name="{{ strtolower($r['name']) }}"
                           data-location="{{ strtolower($r['location']) }}"
                           data-barcode="{{ strtolower($r['barcode']) }}"
                           style="display: flex; gap: 10px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; cursor: pointer; align-items: flex-start; {{ $r['locked'] ? 'opacity: 0.5; cursor: not-allowed; background: #f8fafc;' : '' }}">
                        <input type="checkbox" name="rack_ids[]" value="{{ $r['id'] }}" class="rack-cb"{{ $isCheckedRack ? ' checked' : '' }}
                               style="margin-top: 3px; width: 16px; height: 16px; cursor: inherit;"
                               {{ $r['locked'] ? 'disabled' : '' }}>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; color: var(--color-text); font-size: 14px;">{{ $r['name'] ?: '—' }}</div>
                            <div style="font-size: 12px; color: var(--color-text-muted); line-height: 1.5;">
                                @if($r['location']) Lokasi: {{ $r['location'] }} · @endif
                                @if($r['barcode']) Barcode: <code style="font-size: 11px;">{{ $r['barcode'] }}</code> @endif
                            </div>
                            @if($r['locked'])
                                <div style="font-size: 11px; color: var(--color-warning); margin-top: 4px;">⚠ Sudah punya template aktif lain</div>
                            @endif
                        </div>
                    </label>
                @empty
                    <div style="padding: 30px; text-align: center; color: var(--color-text-muted); font-size: 14px;">Tidak ada rak aktif. Tambahkan rak dulu di menu Tim &amp; Area.</div>
                @endforelse
            </div>

            <div style="margin-top: 14px; padding: 10px 14px; background: var(--color-bg); border-radius: 8px; font-size: 13px; color: var(--color-text-secondary);">
                Rak dipilih: <strong id="rackCount" style="color: var(--color-text);">0</strong> rak
            </div>
        </div>

        {{-- ============ STEP 2: Jadwal ============ --}}
        <div class="wiz-step" data-step="2" style="display: none; padding: 22px;">
            <h3 style="margin: 0 0 4px; color: var(--color-text); font-size: 18px;">Atur jadwal cek</h3>
            <p style="margin: 0 0 14px; color: var(--color-text-muted); font-size: 13px;">Sistem akan membuat task otomatis di hari yang ditentukan.</p>

            <div style="margin-bottom: 16px; background: var(--color-info-bg); border: 1px solid var(--color-info-border); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #075985; line-height: 1.6;">
                Template ini menentukan rak mana yang perlu dicek dan kapan. Pembagian ke karyawan dilakukan di halaman Planning.
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 6px;">Tanggal mulai</label>
                    <input type="date" name="recurrence_anchor_date" value="{{ isset($template) ? ($template['recurrence_anchor_date'] ?? $defaultAnchor) : $defaultAnchor }}" required
                           style="width: 100%; padding: 10px 12px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 8px;">Pengulangan</label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <label class="recurrence-pill" data-recur="daily" style="padding: 8px 14px; border: 2px solid var(--color-primary); border-radius: 8px; cursor: pointer; background: var(--color-primary-bg); color: var(--color-primary); font-weight: 600; font-size: 13px;">
                        <input type="radio" name="recurrence_type" value="daily"{{ isset($template) && ($template['recurrence_type'] ?? '') === 'daily' ? ' checked' : '' }}{{ !isset($template) ? ' checked' : '' }} style="display: none;"> Setiap hari
                    </label>
                    <label class="recurrence-pill" data-recur="weekly" style="padding: 8px 14px; border: 2px solid var(--color-border); border-radius: 8px; cursor: pointer; color: var(--color-text-secondary); font-weight: 600; font-size: 13px;">
                        <input type="radio" name="recurrence_type" value="weekly"{{ isset($template) && ($template['recurrence_type'] ?? '') === 'weekly' ? ' checked' : '' }} style="display: none;"> Setiap minggu
                    </label>
                    <label class="recurrence-pill" data-recur="every_n_days" style="padding: 8px 14px; border: 2px solid var(--color-border); border-radius: 8px; cursor: pointer; color: var(--color-text-secondary); font-weight: 600; font-size: 13px;">
                        <input type="radio" name="recurrence_type" value="every_n_days"{{ isset($template) && ($template['recurrence_type'] ?? '') === 'every_n_days' ? ' checked' : '' }} style="display: none;"> Setiap beberapa hari
                    </label>
                </div>
            </div>

            <div id="weeklyDayWrapper" style="display: none; margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 6px;">Hari (mode mingguan)</label>
                <select name="weekly_day" style="width: 240px; max-width: 100%; padding: 10px 12px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
                    <option value="1"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 1 ? ' selected' : '' }}>Senin</option>
                    <option value="2"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 2 ? ' selected' : '' }}>Selasa</option>
                    <option value="3"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 3 ? ' selected' : '' }}>Rabu</option>
                    <option value="4"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 4 ? ' selected' : '' }}>Kamis</option>
                    <option value="5"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 5 ? ' selected' : '' }}>Jumat</option>
                    <option value="6"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 6 ? ' selected' : '' }}>Sabtu</option>
                    <option value="7"{{ isset($template) && (int)($template['weekly_day'] ?? 0) === 7 ? ' selected' : '' }}>Minggu</option>
                </select>
            </div>

            <div id="intervalDaysWrapper" style="display: none; margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 6px;">Interval hari</label>
                <input type="number" name="interval_days" value="{{ isset($template) ? ($template['interval_days'] ?? '2') : '2' }}" min="1" max="365"
                       style="width: 240px; max-width: 100%; padding: 10px 12px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
            </div>
        </div>

        {{-- ============ STEP 3: Pengaturan & Simpan ============ --}}
        <div class="wiz-step" data-step="3" style="display: none; padding: 22px;">
            <h3 style="margin: 0 0 4px; color: var(--color-text); font-size: 18px;">Pengaturan & Ringkasan</h3>
            <p style="margin: 0 0 14px; color: var(--color-text-muted); font-size: 13px;">Atur bukti yang dibutuhkan dan periksa template.</p>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 8px;">Bukti yang diminta dari petugas</label>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; gap: 8px; align-items: center; font-size: 14px; color: var(--color-text-secondary);">
                        <input type="checkbox" name="requires_barcode_scan" value="1"{{ isset($template) ? ($template['requires_barcode_scan'] ?? true ? ' checked' : '') : ' checked' }} style="width: 16px; height: 16px;"> Wajib scan barcode rak
                    </label>
                    <label style="display: flex; gap: 8px; align-items: center; font-size: 14px; color: var(--color-text-secondary);">
                        <input type="checkbox" name="requires_photo_before" value="1"{{ isset($template) ? ($template['requires_photo_before'] ?? true ? ' checked' : '') : ' checked' }} style="width: 16px; height: 16px;"> Foto sebelum
                    </label>
                    <label style="display: flex; gap: 8px; align-items: center; font-size: 14px; color: var(--color-text-secondary);">
                        <input type="checkbox" name="requires_photo_proof" value="1"{{ isset($template) ? ($template['requires_photo_proof'] ?? true ? ' checked' : '') : ' checked' }} style="width: 16px; height: 16px;"> Foto sesudah
                    </label>
                    <label style="display: flex; gap: 8px; align-items: center; font-size: 14px; color: var(--color-text-secondary);">
                        <input type="checkbox" name="allow_note" value="1"{{ isset($template) ? ($template['allow_note'] ?? true ? ' checked' : '') : ' checked' }} style="width: 16px; height: 16px;"> Petugas boleh menulis catatan
                    </label>
                    <label style="display: flex; gap: 8px; align-items: center; font-size: 14px; color: var(--color-text-secondary);">
                        <input type="checkbox" name="enable_empty_product_report" value="1"{{ isset($template) ? ($template['enable_empty_product_report'] ?? true ? ' checked' : '') : ' checked' }} style="width: 16px; height: 16px;"> Aktifkan laporan produk kosong
                    </label>
                </div>
            </div>

            <div id="capInputsBox" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                <div style="font-weight: 700; color: var(--color-text); font-size: 13px; margin-bottom: 10px;">⚙️ Batas Maksimal Task per Hari di Planning (opsional)</div>
                <p style="margin: 0 0 12px; font-size: 12px; color: var(--color-text-muted);">Nilai ini dipakai saat membagikan tugas di halaman planning.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 4px;">Full Shift (≥12 jam)</label>
                        <input type="number" name="full_shift_daily_cap" id="fullShiftCap" value="{{ isset($template) ? ($template['full_shift_daily_cap'] ?? '') : '' }}"
                               placeholder="2 (default)" min="0" max="99"
                               style="width: 100%; padding: 10px 12px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 4px;">Partial Shift (&lt;12 jam)</label>
                        <input type="number" name="partial_shift_daily_cap" id="partialShiftCap" value="{{ isset($template) ? ($template['partial_shift_daily_cap'] ?? '') : '' }}"
                               placeholder="1 (default)" min="0" max="99"
                               style="width: 100%; padding: 10px 12px; border: 2px solid var(--color-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
            </div>

            <div id="summaryBox" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.7; color: var(--color-text-secondary);">
                <div style="text-align: center; color: var(--color-text-muted); padding: 20px 0;">Memuat ringkasan…</div>
            </div>
        </div>

        {{-- Action bar --}}
        <div style="display: flex; gap: 12px; justify-content: space-between; align-items: center; padding: 16px 22px; border-top: 1px solid var(--color-border); background: var(--color-bg); border-radius: 0 0 12px 12px; flex-wrap: wrap;">
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button type="button" id="btnPrev" style="background: white; color: var(--color-text-secondary); border: 1px solid var(--color-border); padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">← Kembali</button>
                <a href="{{ route('admin.rack_check.templates.index') }}"
                   onclick="return confirm('Yakin keluar dari wizard? Progress akan disimpan otomatis dan bisa dilanjutkan nanti.');"
                   style="color: var(--color-text-muted); font-size: 13px; text-decoration: none; border-bottom: 1px dashed var(--color-border); padding-bottom: 1px;">
                    Keluar wizard
                </a>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" id="btnNext" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">Lanjut →</button>
                <button type="submit" id="btnSubmit" style="display: none; background: var(--color-success); color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">{{ isset($template) ? '💾 Perbarui Template' : '💾 Simpan Template' }}</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const racksData = @json($racksJson);
    const dayLabels = { '1': 'Senin', '2': 'Selasa', '3': 'Rabu', '4': 'Kamis', '5': 'Jumat', '6': 'Sabtu', '7': 'Minggu' };

    const STORAGE_KEY = 'rack_check_wizard_draft_v2';
    const STORAGE_TTL_MS = 24 * 3600 * 1000; // 1 hari

    let currentStep = 1;
    const totalSteps = 3;

    const form = document.getElementById('wizardForm');
    const stepperItems = document.querySelectorAll('.step-pill');
    const wizSteps = document.querySelectorAll('.wiz-step');
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');

    // ─── Autosave to localStorage ─────────────────────────────────────────
    function snapshotForm() {
        const rackIds = Array.from(form.querySelectorAll('.rack-cb:checked')).map(cb => cb.value);
        const recurEl = form.querySelector('[name="recurrence_type"]:checked');
        return {
            ts: Date.now(),
            step: currentStep,
            rack_ids: rackIds,
            recurrence_type: recurEl ? recurEl.value : 'daily',
            weekly_day: form.querySelector('[name="weekly_day"]')?.value || '1',
            interval_days: form.querySelector('[name="interval_days"]')?.value || '2',
            recurrence_anchor_date: form.querySelector('[name="recurrence_anchor_date"]')?.value || '',
            requires_barcode_scan: !!form.querySelector('[name="requires_barcode_scan"]')?.checked,
            requires_photo_before: !!form.querySelector('[name="requires_photo_before"]')?.checked,
            requires_photo_proof: !!form.querySelector('[name="requires_photo_proof"]')?.checked,
            allow_note: !!form.querySelector('[name="allow_note"]')?.checked,
            enable_empty_product_report: !!form.querySelector('[name="enable_empty_product_report"]')?.checked,
            full_shift_daily_cap: form.querySelector('[name="full_shift_daily_cap"]')?.value || '',
            partial_shift_daily_cap: form.querySelector('[name="partial_shift_daily_cap"]')?.value || '',
        };
    }

    function saveDraft() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(snapshotForm()));
        } catch (e) {
            // localStorage full / disabled — silent fail
        }
    }

    function clearDraft() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
    }

    function restoreDraft() {
        let raw;
        try { raw = localStorage.getItem(STORAGE_KEY); } catch (e) { return; }
        if (!raw) return;
        let draft;
        try { draft = JSON.parse(raw); } catch (e) { clearDraft(); return; }
        if (!draft || typeof draft !== 'object') { clearDraft(); return; }

        // TTL check
        if (!draft.ts || Date.now() - draft.ts > STORAGE_TTL_MS) {
            clearDraft();
            return;
        }

        // Restore racks
        if (Array.isArray(draft.rack_ids)) {
            draft.rack_ids.forEach(id => {
                const cb = form.querySelector(`.rack-cb[value="${CSS.escape(id)}"]`);
                if (cb && !cb.disabled) cb.checked = true;
            });
        }

        // Restore recurrence
        const validRecur = ['daily', 'weekly', 'every_n_days'];
        if (validRecur.includes(draft.recurrence_type)) {
            const radio = form.querySelector(`[name="recurrence_type"][value="${draft.recurrence_type}"]`);
            if (radio) {
                radio.checked = true;
                // Trigger pill UI sync
                document.querySelectorAll('.recurrence-pill').forEach(pp => {
                    pp.style.borderColor = 'var(--color-border)';
                    pp.style.background = 'transparent';
                    pp.style.color = 'var(--color-text-secondary)';
                });
                const activePill = document.querySelector(`.recurrence-pill[data-recur="${draft.recurrence_type}"]`);
                if (activePill) {
                    activePill.style.borderColor = 'var(--color-primary)';
                    activePill.style.background = 'var(--color-primary-bg)';
                    activePill.style.color = 'var(--color-primary)';
                }
                const weeklyWrap = document.getElementById('weeklyDayWrapper');
                const intervalWrap = document.getElementById('intervalDaysWrapper');
                if (weeklyWrap) weeklyWrap.style.display = draft.recurrence_type === 'weekly' ? 'block' : 'none';
                if (intervalWrap) intervalWrap.style.display = draft.recurrence_type === 'every_n_days' ? 'block' : 'none';
            }
        }
        const weeklyDayEl = form.querySelector('[name="weekly_day"]');
        if (weeklyDayEl && draft.weekly_day) weeklyDayEl.value = draft.weekly_day;
        const intervalEl = form.querySelector('[name="interval_days"]');
        if (intervalEl && draft.interval_days) intervalEl.value = draft.interval_days;
        const anchorEl = form.querySelector('[name="recurrence_anchor_date"]');
        if (anchorEl && draft.recurrence_anchor_date) anchorEl.value = draft.recurrence_anchor_date;

        // Restore checkboxes
        const proofKeys = ['requires_barcode_scan', 'requires_photo_before', 'requires_photo_proof', 'allow_note', 'enable_empty_product_report'];
        proofKeys.forEach(k => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el && typeof draft[k] === 'boolean') el.checked = draft[k];
        });

        // Restore caps
        if (draft.full_shift_daily_cap !== undefined) {
            const fullCapEl = form.querySelector('[name="full_shift_daily_cap"]');
            if (fullCapEl) fullCapEl.value = draft.full_shift_daily_cap;
        }
        if (draft.partial_shift_daily_cap !== undefined) {
            const partialCapEl = form.querySelector('[name="partial_shift_daily_cap"]');
            if (partialCapEl) partialCapEl.value = draft.partial_shift_daily_cap;
        }

        // Restore step
        if (Number.isInteger(draft.step) && draft.step >= 1 && draft.step <= totalSteps) {
            currentStep = draft.step;
        }

        showResumeBanner();
    }

    function showResumeBanner() {
        const banner = document.createElement('div');
        banner.id = 'draftResumeBanner';
        banner.style.cssText = 'background:var(--color-info-bg);border:1px solid var(--color-info-border);color:#075985;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;';
        banner.innerHTML = `
            <span>📝 Draft sebelumnya dipulihkan. Lanjutkan dari step ${currentStep} atau <button type="button" id="discardDraftBtn" style="background:none;border:none;color:var(--color-danger);text-decoration:underline;cursor:pointer;font-size:13px;padding:0;font-weight:600;">mulai dari awal</button>.</span>
            <button type="button" id="dismissBannerBtn" style="background:none;border:none;color:var(--color-text-muted);cursor:pointer;font-size:18px;padding:0;line-height:1;">×</button>
        `;
        const target = form.parentElement;
        target.insertBefore(banner, form);

        document.getElementById('discardDraftBtn').addEventListener('click', function () {
            clearDraft();
            location.reload();
        });
        document.getElementById('dismissBannerBtn').addEventListener('click', function () {
            banner.remove();
        });
    }

    @if(!isset($template))
    form.addEventListener('change', saveDraft);
    form.addEventListener('input', saveDraft);
    form.addEventListener('submit', clearDraft);
    @endif

    function renderStepper() {
        stepperItems.forEach(el => {
            const idx = parseInt(el.dataset.step, 10);
            if (idx === currentStep) {
                el.style.borderColor = 'var(--color-primary)';
                el.style.background = 'var(--color-primary-bg)';
                el.style.color = 'var(--color-primary)';
            } else if (idx < currentStep) {
                el.style.borderColor = 'var(--color-success-border)';
                el.style.background = 'var(--color-success-bg)';
                el.style.color = 'var(--color-success)';
            } else {
                el.style.borderColor = 'var(--color-border)';
                el.style.background = 'white';
                el.style.color = 'var(--color-text-muted)';
            }
        });

        wizSteps.forEach(el => {
            el.style.display = parseInt(el.dataset.step, 10) === currentStep ? 'block' : 'none';
        });

        btnPrev.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        if (currentStep === totalSteps) {
            btnNext.style.display = 'none';
            btnSubmit.style.display = 'inline-block';
        } else {
            btnNext.style.display = 'inline-block';
            btnSubmit.style.display = 'none';
        }
    }

    function validateStep(step) {
        if (step === 1) {
            const checked = form.querySelectorAll('.rack-cb:checked').length;
            if (checked === 0) {
                alert('Pilih minimal satu rak.');
                return false;
            }
        } else if (step === 2) {
            const anchor = form.querySelector('[name="recurrence_anchor_date"]').value;
            if (!anchor) { alert('Tanggal mulai wajib diisi.'); return false; }
            const recur = form.querySelector('[name="recurrence_type"]:checked').value;
            if (recur === 'weekly' && !form.querySelector('[name="weekly_day"]').value) {
                alert('Pilih hari untuk mode mingguan.'); return false;
            }
            if (recur === 'every_n_days') {
                const n = parseInt(form.querySelector('[name="interval_days"]').value, 10);
                if (!Number.isFinite(n) || n < 1) { alert('Interval hari minimal 1.'); return false; }
            }
        }
        return true;
    }

    btnNext.addEventListener('click', function () {
        if (!validateStep(currentStep)) return;
        if (currentStep < totalSteps) {
            currentStep++;
            if (currentStep === totalSteps) buildSummary();
            renderStepper();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    btnPrev.addEventListener('click', function () {
        if (currentStep > 1) {
            currentStep--;
            renderStepper();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    // Step 1: rack search + count
    const rackSearch = document.getElementById('rackSearch');
    const rackCount = document.getElementById('rackCount');
    if (rackSearch) {
        rackSearch.addEventListener('input', function () {
            const q = rackSearch.value.trim().toLowerCase();
            document.querySelectorAll('.rack-item').forEach(el => {
                if (!q) { el.style.display = 'flex'; return; }
                const match = (el.dataset.name || '').includes(q)
                    || (el.dataset.location || '').includes(q)
                    || (el.dataset.barcode || '').includes(q);
                el.style.display = match ? 'flex' : 'none';
            });
        });
    }
    form.querySelectorAll('.rack-cb').forEach(cb => cb.addEventListener('change', () => {
        rackCount.textContent = form.querySelectorAll('.rack-cb:checked').length;
        sortRacksCheckedFirst();
    }));

    function sortRacksCheckedFirst() {
        const list = document.getElementById('rackList');
        if (!list) return;
        const items = Array.from(list.querySelectorAll('.rack-item'));
        items.sort((a, b) => {
            const aChecked = a.querySelector('.rack-cb')?.checked ? 0 : 1;
            const bChecked = b.querySelector('.rack-cb')?.checked ? 0 : 1;
            return aChecked - bChecked;
        });
        items.forEach(el => list.appendChild(el));
    }
    sortRacksCheckedFirst();

    // Step 2: recurrence pill toggle
    const pills = document.querySelectorAll('.recurrence-pill');
    const weeklyWrap = document.getElementById('weeklyDayWrapper');
    const intervalWrap = document.getElementById('intervalDaysWrapper');
    pills.forEach(p => p.addEventListener('click', function () {
        const val = p.dataset.recur;
        pills.forEach(pp => {
            pp.style.borderColor = 'var(--color-border)';
            pp.style.background = 'transparent';
            pp.style.color = 'var(--color-text-secondary)';
        });
        p.style.borderColor = 'var(--color-primary)';
        p.style.background = 'var(--color-primary-bg)';
        p.style.color = 'var(--color-primary)';
        const radio = p.querySelector('input[type="radio"]');
        radio.checked = true;
        weeklyWrap.style.display = val === 'weekly' ? 'block' : 'none';
        intervalWrap.style.display = val === 'every_n_days' ? 'block' : 'none';
        saveDraft();
    }));

    // Re-init recurrence pills state if edited/restored
    const initRecur = form.querySelector('[name="recurrence_type"]:checked');
    if (initRecur) {
        const activePill = document.querySelector(`.recurrence-pill[data-recur="${initRecur.value}"]`);
        if (activePill) {
            activePill.style.borderColor = 'var(--color-primary)';
            activePill.style.background = 'var(--color-primary-bg)';
            activePill.style.color = 'var(--color-primary)';
            weeklyWrap.style.display = initRecur.value === 'weekly' ? 'block' : 'none';
            intervalWrap.style.display = initRecur.value === 'every_n_days' ? 'block' : 'none';
        }
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function buildSummary() {
        const box = document.getElementById('summaryBox');
        const rackIds = Array.from(form.querySelectorAll('.rack-cb:checked')).map(cb => cb.value);
        const rackNames = rackIds.map(id => {
            const r = racksData.find(x => x.id === id);
            return r ? r.name : id;
        });

        const templateNameInput = form.querySelector('[name="template_name"]');
        const templateName = templateNameInput && templateNameInput.value.trim()
            ? templateNameInput.value.trim()
            : (rackNames.length === 1 ? rackNames[0] : `Cek ${rackNames.length} Rak`);

        const recur = form.querySelector('[name="recurrence_type"]:checked').value;
        let recurLabel = 'Setiap hari';
        if (recur === 'weekly') {
            const day = form.querySelector('[name="weekly_day"]').value;
            recurLabel = 'Setiap ' + (dayLabels[day] || '-');
        } else if (recur === 'every_n_days') {
            recurLabel = 'Setiap ' + (form.querySelector('[name="interval_days"]').value || '?') + ' hari sekali';
        }

        const anchor = form.querySelector('[name="recurrence_anchor_date"]').value;

        const proofs = [];
        if (form.querySelector('[name="requires_barcode_scan"]').checked) proofs.push('Scan barcode rak');
        if (form.querySelector('[name="requires_photo_before"]').checked) proofs.push('Foto sebelum');
        if (form.querySelector('[name="requires_photo_proof"]').checked) proofs.push('Foto sesudah');
        if (form.querySelector('[name="allow_note"]').checked) proofs.push('Catatan pengerjaan');
        if (form.querySelector('[name="enable_empty_product_report"]').checked) proofs.push('Laporan produk kosong');

        let html = '';
        html += `<div style="margin-bottom:12px;"><strong style="color:var(--color-text);font-size:15px;">📋 ${escapeHtml(templateName)}</strong></div>`;
        html += `<div style="margin-bottom:10px;"><strong style="color:var(--color-text);">Rak (${rackNames.length}):</strong></div>`;
        html += `<ul style="margin:0 0 12px 20px;">`;
        rackNames.forEach(n => html += `<li>${escapeHtml(n)}</li>`);
        html += `</ul>`;
        html += `<div><strong style="color:var(--color-text);">Jadwal:</strong> ${escapeHtml(recurLabel)}</div>`;
        html += `<div><strong style="color:var(--color-text);">Tanggal mulai:</strong> ${escapeHtml(anchor)}</div>`;
        html += `<div style="margin-top:8px;"><strong style="color:var(--color-text);">Bukti:</strong></div>`;
        html += `<ul style="margin:0 0 6px 20px;">`;
        proofs.forEach(p => html += `<li>${escapeHtml(p)}</li>`);
        html += `</ul>`;

        // Custom cap summary
        const fullCapVal = form.querySelector('[name="full_shift_daily_cap"]')?.value || '';
        const partialCapVal = form.querySelector('[name="partial_shift_daily_cap"]')?.value || '';
        const capParts = [];
        if (fullCapVal !== '') capParts.push(`Full shift: ${fullCapVal} task/hari`);
        if (partialCapVal !== '') capParts.push(`Partial shift: ${partialCapVal} task/hari`);
        const capSummary = capParts.length > 0 ? capParts.join(', ') : 'Default (full=2, partial=1)';
        html += `<div style="margin-top:6px;"><strong style="color:var(--color-text);">Batas task/hari di planning:</strong> ${escapeHtml(capSummary)}</div>`;

        box.innerHTML = html;
    }

    @if(!isset($template))
    restoreDraft();
    const rackCountEl2 = document.getElementById('rackCount');
    if (rackCountEl2) rackCountEl2.textContent = form.querySelectorAll('.rack-cb:checked').length;
    if (currentStep === totalSteps) buildSummary();
    @else
    const rackCountEdit = document.getElementById('rackCount');
    if (rackCountEdit) rackCountEdit.textContent = form.querySelectorAll('.rack-cb:checked').length;
    @endif

    renderStepper();
})();
</script>
@endsection