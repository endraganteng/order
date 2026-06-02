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

    $waitersJson = collect($waiters)->map(fn ($w) => [
        'id' => (string) ($w['id'] ?? ''),
        'name' => (string) ($w['name'] ?? ''),
        'role' => strtolower((string) ($w['waiter_role'] ?? 'pelayan')),
        'is_active' => (bool) ($w['is_active'] ?? true),
    ])->filter(fn ($w) => $w['is_active'])->values();

    // Role styling: warna badge + label + emoji
    $roleStyles = [
        'pelayan'    => ['label' => 'Pelayan',    'emoji' => '👤', 'bg' => '#eef2ff', 'text' => '#3730a3', 'border' => '#c7d2fe'],
        'kasir'      => ['label' => 'Kasir',      'emoji' => '💰', 'bg' => '#f0fdf4', 'text' => '#166534', 'border' => '#bbf7d0'],
        'finance'    => ['label' => 'Finance',    'emoji' => '📊', 'bg' => '#fff7ed', 'text' => '#9a3412', 'border' => '#fed7aa'],
        'backup'     => ['label' => 'Backup',     'emoji' => '🔄', 'bg' => '#f0f9ff', 'text' => '#075985', 'border' => '#bae6fd'],
        'supervisor' => ['label' => 'Supervisor', 'emoji' => '⭐', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fde68a'],
    ];
    $defaultRoleStyle = ['label' => 'Lainnya', 'emoji' => '👤', 'bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1'];

    // Group waiters by role
    $waitersByRole = $waitersJson->groupBy('role');
    // Order group display: pelayan dulu (paling sering jadi rotasi cek rak), lalu kasir, backup, finance, supervisor, lainnya
    $roleOrder = ['pelayan', 'kasir', 'backup', 'finance', 'supervisor'];
    $sortedRoleKeys = collect($roleOrder)->filter(fn ($r) => $waitersByRole->has($r))
        ->merge($waitersByRole->keys()->diff($roleOrder))
        ->values();

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
                2 => ['label' => 'Petugas', 'icon' => '👥'],
                3 => ['label' => 'Jadwal', 'icon' => '📅'],
                4 => ['label' => 'Simpan', 'icon' => '✓'],
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

        {{-- ============ STEP 2: Pilih Petugas ============ --}}
        <div class="wiz-step" data-step="2" style="display: none; padding: 22px;">
            <h3 style="margin: 0 0 4px; color: var(--color-text); font-size: 18px;">Pilih petugas rotasi</h3>
            <p style="margin: 0 0 14px; color: var(--color-text-muted); font-size: 13px;">
                Sistem hanya akan memilih petugas yang sedang masuk kerja. Petugas yang libur otomatis dilewati.
            </p>

            @if($waitersJson->count() === 0)
                <div style="padding: 30px; text-align: center; color: var(--color-text-muted); font-size: 14px;">Tidak ada karyawan aktif. Tambahkan dulu di menu Waiters.</div>
            @else
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    @foreach($sortedRoleKeys as $roleKey)
                        @php
                            $style = $roleStyles[$roleKey] ?? $defaultRoleStyle;
                            $roleWaiters = $waitersByRole[$roleKey] ?? collect();
                        @endphp
                        <div class="role-group" data-role="{{ $roleKey }}"
                             style="border: 1px solid {{ $style['border'] }}; border-radius: 10px; overflow: hidden; background: white;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 14px; background: {{ $style['bg'] }}; color: {{ $style['text'] }}; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px;">
                                    <span style="font-size: 16px;">{{ $style['emoji'] }}</span>
                                    <span>{{ $style['label'] }}</span>
                                    <span style="background: rgba(255,255,255,0.7); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">
                                        {{ $roleWaiters->count() }} orang
                                    </span>
                                </div>
                                <button type="button" class="role-toggle-all"
                                        data-role="{{ $roleKey }}"
                                        style="background: rgba(255,255,255,0.85); border: 1px solid {{ $style['border'] }}; color: {{ $style['text'] }}; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;">
                                    Pilih semua
                                </button>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px; padding: 10px 12px;">
                                @foreach($roleWaiters as $w)
                                    <label style="display: flex; gap: 10px; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 7px; cursor: pointer; align-items: center; transition: background 0.1s;">
                                        <input type="checkbox" name="selected_waiter_ids[]" value="{{ $w['id'] }}" class="waiter-cb"{{ (isset($template) && in_array($w['id'], (array)($template['selected_waiter_ids'] ?? []), true)) ? ' checked' : '' }}
                                               data-role="{{ $roleKey }}"
                                               style="width: 16px; height: 16px; cursor: inherit;">
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-weight: 600; color: var(--color-text); font-size: 14px;">{{ $w['name'] ?: '—' }}</div>
                                        </div>
                                        <span style="background: {{ $style['bg'] }}; color: {{ $style['text'] }}; border: 1px solid {{ $style['border'] }}; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; white-space: nowrap;">
                                            {{ $style['emoji'] }} {{ $style['label'] }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="margin-top: 14px; padding: 10px 14px; background: var(--color-bg); border-radius: 8px; font-size: 13px; color: var(--color-text-secondary);">
                Petugas dipilih: <strong id="waiterCount" style="color: var(--color-text);">0</strong> orang
                <span id="waiterRoleSummary" style="color: var(--color-text-muted); margin-left: 8px;"></span>
            </div>
        </div>

        {{-- ============ STEP 3: Jadwal & Bukti ============ --}}
        <div class="wiz-step" data-step="3" style="display: none; padding: 22px;">
            <h3 style="margin: 0 0 4px; color: var(--color-text); font-size: 18px;">Atur jadwal dan bukti</h3>
            <p style="margin: 0 0 14px; color: var(--color-text-muted); font-size: 13px;">Sistem akan membuat task otomatis pas waktu shift petugas terpilih.</p>

            {{-- Mode pembagian --}}
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 600; color: var(--color-text); font-size: 13px; margin-bottom: 8px;">🎯 Mode pembagian</label>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="mode-option" data-mode="simple_lowest_load"
                           style="display: flex; gap: 10px; padding: 12px; border: 2px solid var(--color-primary); border-radius: 8px; cursor: pointer; align-items: flex-start; background: var(--color-primary-bg);">
                        <input type="radio" name="assignment_strategy" value="simple_lowest_load"{{ isset($template) && ($template['assignment_strategy'] ?? '') === 'simple_lowest_load' ? ' checked' : '' }}{{ !isset($template) ? ' checked' : '' }}
                               style="margin-top: 3px; width: 16px; height: 16px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 700; color: var(--color-primary); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                Beban Paling Ringan
                                <span style="background: var(--color-primary); color: white; padding: 1px 6px; border-radius: 8px; font-size: 10px; font-weight: 600;">DEFAULT</span>
                            </div>
                            <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 3px; line-height: 1.5;">
                                Sistem otomatis pilih petugas yang paling sedikit task cek rak hari ini. Cocok untuk operasional adil.
                            </div>
                        </div>
                    </label>

                    <label class="mode-option" data-mode="round_robin_simple"
                           style="display: flex; gap: 10px; padding: 12px; border: 2px solid var(--color-border); border-radius: 8px; cursor: pointer; align-items: flex-start;">
                        <input type="radio" name="assignment_strategy" value="round_robin_simple"{{ isset($template) && ($template['assignment_strategy'] ?? '') === 'round_robin_simple' ? ' checked' : '' }} style="margin-top: 3px; width: 16px; height: 16px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 700; color: var(--color-text); font-size: 14px;">Giliran Tetap</div>
                            <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 3px; line-height: 1.5;">
                                Bergantian urut sesuai daftar petugas: hari ini Anjar → besok Rendy → lusa Bagas, lalu ulang dari atas. Petugas libur otomatis dilewati ke giliran berikut.
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 16px; background: var(--color-info-bg); border: 1px solid var(--color-info-border); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #075985; line-height: 1.6;">
                <div style="font-weight: 700; margin-bottom: 4px;">⏱ Jam dan deadline mengikuti shift waiter</div>
                Task dibuat saat shift mulai. Deadline = jam selesai shift waiter terpilih. Tidak perlu setting jam manual.
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

            <div id="modeInfoLowestLoad" style="background: var(--color-info-bg); border: 1px solid var(--color-info-border); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #075985;">
                <div style="font-weight: 700; margin-bottom: 6px;">Aturan mode Beban Paling Ringan</div>
                <ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
                    <li>Libur = 0 task</li>
                    <li>Shift pendek = maksimal <strong id="capPartialDisplay">1</strong> task</li>
                    <li>Full shift (≥12j) = maksimal <strong id="capFullDisplay">2</strong> task</li>
                    <li>Jika tidak ada petugas tersedia, task tidak dibuat dan ditandai skipped</li>
                </ul>
            </div>

            {{-- Custom daily cap inputs (muncul hanya saat Beban Paling Ringan) --}}
            <div id="capInputsBox" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 8px; padding: 14px; margin-top: 14px;">
                <div style="font-weight: 700; color: var(--color-text); font-size: 13px; margin-bottom: 10px;">⚙️ Batas Maksimal Task per Hari (opsional)</div>
                <p style="margin: 0 0 12px; font-size: 12px; color: var(--color-text-muted);">Kosongkan untuk pakai default (full=2, partial=1). Isi 0 untuk meniadakan task pada shift tersebut.</p>
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

            <div id="modeInfoRoundRobin" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #166534;">
                <div style="font-weight: 700; margin-bottom: 6px;">Aturan mode Giliran Tetap</div>
                <ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
                    <li>Petugas dapat task bergiliran sesuai urutan daftar</li>
                    <li>Petugas libur otomatis dilewati ke giliran berikutnya</li>
                    <li>Batas task mengikuti pengaturan di atas (full shift / partial shift)</li>
                    <li>Jika seluruh giliran libur, task tidak dibuat (skipped)</li>
                </ul>
            </div>
        </div>

        {{-- ============ STEP 4: Ringkasan & Simpan ============ --}}
        <div class="wiz-step" data-step="4" style="display: none; padding: 22px;">
            <h3 style="margin: 0 0 4px; color: var(--color-text); font-size: 18px;">Ringkasan template</h3>
            <p style="margin: 0 0 14px; color: var(--color-text-muted); font-size: 13px;">Periksa kembali sebelum menyimpan.</p>

            <div id="summaryBox" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.7; color: var(--color-text-secondary);">
                <div style="text-align: center; color: var(--color-text-muted); padding: 20px 0;">Memuat ringkasan…</div>
            </div>

            <div style="margin-top: 14px; background: var(--color-warning-bg); border: 1px solid var(--color-warning-border); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #92400e;">
                <strong>Aturan otomatis:</strong>
                <ul style="margin: 6px 0 0; padding-left: 18px; line-height: 1.6;">
                    <li>Petugas libur otomatis dilewati</li>
                    <li>Full shift maksimal <strong id="summaryFullCap">2</strong> task cek rak per hari</li>
                    <li>Shift pendek maksimal <strong id="summaryPartialCap">1</strong> task cek rak per hari</li>
                    <li>Jika tidak ada petugas tersedia, task tidak dibuat</li>
                    <li>Task yang dicancel admin tidak dibuat ulang otomatis</li>
                </ul>
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
    const waitersData = @json($waitersJson);
    const dayLabels = { '1': 'Senin', '2': 'Selasa', '3': 'Rabu', '4': 'Kamis', '5': 'Jumat', '6': 'Sabtu', '7': 'Minggu' };

    const STORAGE_KEY = 'rack_check_wizard_draft_v1';
    const STORAGE_TTL_MS = 24 * 3600 * 1000; // 1 hari

    let currentStep = 1;
    const totalSteps = 4;

    const form = document.getElementById('wizardForm');
    const stepperItems = document.querySelectorAll('.step-pill');
    const wizSteps = document.querySelectorAll('.wiz-step');
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');

    // ─── Autosave to localStorage ─────────────────────────────────────────
    function snapshotForm() {
        const rackIds = Array.from(form.querySelectorAll('.rack-cb:checked')).map(cb => cb.value);
        const waiterIds = Array.from(form.querySelectorAll('.waiter-cb:checked')).map(cb => cb.value);
        const recurEl = form.querySelector('[name="recurrence_type"]:checked');
        const modeEl = form.querySelector('[name="assignment_strategy"]:checked');
        return {
            ts: Date.now(),
            step: currentStep,
            rack_ids: rackIds,
            selected_waiter_ids: waiterIds,
            recurrence_type: recurEl ? recurEl.value : 'daily',
            assignment_strategy: modeEl ? modeEl.value : 'simple_lowest_load',
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

        // Restore racks (only those still available + not locked)
        if (Array.isArray(draft.rack_ids)) {
            draft.rack_ids.forEach(id => {
                const cb = form.querySelector(`.rack-cb[value="${CSS.escape(id)}"]`);
                if (cb && !cb.disabled) cb.checked = true;
            });
        }

        // Restore waiters
        if (Array.isArray(draft.selected_waiter_ids)) {
            draft.selected_waiter_ids.forEach(id => {
                const cb = form.querySelector(`.waiter-cb[value="${CSS.escape(id)}"]`);
                if (cb) cb.checked = true;
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

        // Restore checkboxes (only if explicitly false — defaults are checked)
        const proofKeys = ['requires_barcode_scan', 'requires_photo_before', 'requires_photo_proof', 'allow_note', 'enable_empty_product_report'];
        proofKeys.forEach(k => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el && typeof draft[k] === 'boolean') el.checked = draft[k];
        });

        // Restore custom caps
        if (draft.full_shift_daily_cap !== undefined) {
            const fullCapEl = form.querySelector('[name="full_shift_daily_cap"]');
            if (fullCapEl) fullCapEl.value = draft.full_shift_daily_cap;
        }
        if (draft.partial_shift_daily_cap !== undefined) {
            const partialCapEl = form.querySelector('[name="partial_shift_daily_cap"]');
            if (partialCapEl) partialCapEl.value = draft.partial_shift_daily_cap;
        }

        // Restore mode pembagian
        const validModes = ['simple_lowest_load', 'round_robin_simple'];
        if (validModes.includes(draft.assignment_strategy)) {
            const modeRadio = form.querySelector(`[name="assignment_strategy"][value="${draft.assignment_strategy}"]`);
            if (modeRadio) modeRadio.checked = true;
        }

        // Restore step (clamp to valid range)
        if (Number.isInteger(draft.step) && draft.step >= 1 && draft.step <= totalSteps) {
            currentStep = draft.step;
        }

        // Show resume notification
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
    // Hook autosave on any form change (skip in edit mode)
    form.addEventListener('change', saveDraft);
    form.addEventListener('input', saveDraft);

    // Clear draft when form successfully submitted
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
            const checked = form.querySelectorAll('.waiter-cb:checked').length;
            if (checked === 0) {
                alert('Pilih minimal satu petugas.');
                return false;
            }
        } else if (step === 3) {
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

    // Sort: checked racks float to top
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
    // Initial sort on page load (for edit mode pre-checked)
    sortRacksCheckedFirst();

    // Step 2: waiter count + role summary + per-role toggle
    const waiterCount = document.getElementById('waiterCount');
    const waiterRoleSummary = document.getElementById('waiterRoleSummary');
    const roleLabels = {
        pelayan: 'Pelayan',
        kasir: 'Kasir',
        finance: 'Finance',
        backup: 'Backup',
        supervisor: 'Supervisor',
    };

    function refreshWaiterStats() {
        const checked = form.querySelectorAll('.waiter-cb:checked');
        waiterCount.textContent = checked.length;

        // Aggregate by role
        const byRole = {};
        checked.forEach(cb => {
            const r = cb.dataset.role || 'lainnya';
            byRole[r] = (byRole[r] || 0) + 1;
        });
        if (waiterRoleSummary) {
            const parts = Object.entries(byRole).map(([r, n]) => {
                const lbl = roleLabels[r] || r;
                return `${n} ${lbl}`;
            });
            waiterRoleSummary.textContent = parts.length > 0 ? '(' + parts.join(' · ') + ')' : '';
        }

        // Sync per-role "Pilih semua" button label
        document.querySelectorAll('.role-toggle-all').forEach(btn => {
            const role = btn.dataset.role;
            const groupBoxes = form.querySelectorAll(`.waiter-cb[data-role="${role}"]`);
            const groupChecked = form.querySelectorAll(`.waiter-cb[data-role="${role}"]:checked`);
            btn.textContent = groupBoxes.length > 0 && groupBoxes.length === groupChecked.length
                ? 'Lepas semua'
                : 'Pilih semua';
        });
    }

    form.querySelectorAll('.waiter-cb').forEach(cb => cb.addEventListener('change', refreshWaiterStats));

    document.querySelectorAll('.role-toggle-all').forEach(btn => {
        btn.addEventListener('click', function () {
            const role = btn.dataset.role;
            const groupBoxes = form.querySelectorAll(`.waiter-cb[data-role="${role}"]`);
            const allChecked = groupBoxes.length > 0 &&
                Array.from(groupBoxes).every(cb => cb.checked);
            groupBoxes.forEach(cb => { cb.checked = !allChecked; });
            refreshWaiterStats();
        });
    });

    refreshWaiterStats();

    // Step 3: recurrence pill toggle
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
    }));

    // Step 3: mode option toggle (Beban Paling Ringan vs Giliran Tetap)
    const modeOptions = document.querySelectorAll('.mode-option');
    const modeInfoLowestLoad = document.getElementById('modeInfoLowestLoad');
    const modeInfoRoundRobin = document.getElementById('modeInfoRoundRobin');

    function syncModeUI() {
        const checked = form.querySelector('[name="assignment_strategy"]:checked');
        const val = checked ? checked.value : 'simple_lowest_load';
        modeOptions.forEach(opt => {
            const optVal = opt.dataset.mode;
            if (optVal === val) {
                opt.style.borderColor = 'var(--color-primary)';
                opt.style.background = 'var(--color-primary-bg)';
            } else {
                opt.style.borderColor = 'var(--color-border)';
                opt.style.background = 'transparent';
            }
        });
        if (modeInfoLowestLoad) modeInfoLowestLoad.style.display = val === 'simple_lowest_load' ? 'block' : 'none';
        if (modeInfoRoundRobin) modeInfoRoundRobin.style.display = val === 'round_robin_simple' ? 'block' : 'none';
        // Show/hide custom cap inputs
        const capInputsBox = document.getElementById('capInputsBox');
        if (capInputsBox) capInputsBox.style.display = (val === 'simple_lowest_load' || val === 'round_robin_simple') ? 'block' : 'none';
        // Update dynamic cap displays
        updateCapDisplays();
    }

    modeOptions.forEach(opt => {
        opt.addEventListener('click', function () {
            const radio = opt.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            syncModeUI();
            saveDraft();
        });
    });
    syncModeUI();

    // Hook cap input changes → update displays + summary
    const fullShiftCap = document.getElementById('fullShiftCap');
    const partialShiftCap = document.getElementById('partialShiftCap');
    if (fullShiftCap) fullShiftCap.addEventListener('input', updateCapDisplays);
    if (partialShiftCap) partialShiftCap.addEventListener('input', updateCapDisplays);

    function updateCapDisplays() {
        const fullVal = fullShiftCap?.value || '2';
        const partialVal = partialShiftCap?.value || '1';
        const capFullDisp = document.getElementById('capFullDisplay');
        const capPartialDisp = document.getElementById('capPartialDisplay');
        const summaryFull = document.getElementById('summaryFullCap');
        const summaryPartial = document.getElementById('summaryPartialCap');
        if (capFullDisp) capFullDisp.textContent = fullVal;
        if (capPartialDisp) capPartialDisp.textContent = partialVal;
        if (summaryFull) summaryFull.textContent = fullVal;
        if (summaryPartial) summaryPartial.textContent = partialVal;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function buildSummary() {
        const box = document.getElementById('summaryBox');
        const rackIds = Array.from(form.querySelectorAll('.rack-cb:checked')).map(cb => cb.value);
        const waiterIds = Array.from(form.querySelectorAll('.waiter-cb:checked')).map(cb => cb.value);
        const rackNames = rackIds.map(id => {
            const r = racksData.find(x => x.id === id);
            return r ? r.name : id;
        });
        const waiterNames = waiterIds.map(id => {
            const w = waitersData.find(x => x.id === id);
            return w ? w.name : id;
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

        const time = '— mengikuti jam shift petugas —';
        const lim = '— sampai akhir shift —';
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
        html += `<div style="margin-bottom:10px;"><strong style="color:var(--color-text);">Petugas (${waiterNames.length}):</strong></div>`;
        html += `<ul style="margin:0 0 12px 20px;">`;
        waiterNames.forEach(n => html += `<li>${escapeHtml(n)}</li>`);
        html += `</ul>`;
        html += `<div><strong style="color:var(--color-text);">Jadwal:</strong> ${escapeHtml(recurLabel)}, ${escapeHtml(time)}</div>`;
        html += `<div><strong style="color:var(--color-text);">Tanggal mulai:</strong> ${escapeHtml(anchor)}</div>`;
        html += `<div><strong style="color:var(--color-text);">Deadline:</strong> ${escapeHtml(lim)}</div>`;
        html += `<div style="margin-top:8px;"><strong style="color:var(--color-text);">Bukti:</strong></div>`;
        html += `<ul style="margin:0 0 6px 20px;">`;
        proofs.forEach(p => html += `<li>${escapeHtml(p)}</li>`);
        html += `</ul>`;

        const modeChecked = form.querySelector('[name="assignment_strategy"]:checked');
        const modeVal = modeChecked ? modeChecked.value : 'simple_lowest_load';
        const modeLabels = {
            'simple_lowest_load': 'Beban Paling Ringan (otomatis pilih yang task-nya paling sedikit)',
            'round_robin_simple': 'Giliran Tetap (bergiliran sesuai urutan daftar petugas)',
        };
        html += `<div style="margin-top:10px;"><strong style="color:var(--color-text);">Mode pembagian:</strong> ${escapeHtml(modeLabels[modeVal] || modeVal)}</div>`;

        // Custom cap summary
        const fullCapVal = form.querySelector('[name="full_shift_daily_cap"]')?.value || '';
        const partialCapVal = form.querySelector('[name="partial_shift_daily_cap"]')?.value || '';
        const capParts = [];
        if (fullCapVal !== '') capParts.push(`Full shift: ${fullCapVal} task/hari`);
        if (partialCapVal !== '') capParts.push(`Partial shift: ${partialCapVal} task/hari`);
        const capSummary = capParts.length > 0 ? capParts.join(', ') : 'Default (full=2, partial=1)';
        html += `<div style="margin-top:6px;"><strong style="color:var(--color-text);">Batas task/hari:</strong> ${escapeHtml(capSummary)}</div>`;

        box.innerHTML = html;
    }

    // Restore draft sebelum render UI awal (skip jika edit mode — data dari server)
    @if(!isset($template))
    restoreDraft();

    // Sync UI counters setelah restore
    if (typeof refreshWaiterStats === 'function') refreshWaiterStats();
    const rackCountEl2 = document.getElementById('rackCount');
    if (rackCountEl2) rackCountEl2.textContent = form.querySelectorAll('.rack-cb:checked').length;

    // Kalau restore membawa user ke step 4, build summary langsung
    if (currentStep === totalSteps) buildSummary();
    @else
    // Edit mode: sync counters from server-provided data
    if (typeof refreshWaiterStats === 'function') refreshWaiterStats();
    const rackCountEdit = document.getElementById('rackCount');
    if (rackCountEdit) rackCountEdit.textContent = form.querySelectorAll('.rack-cb:checked').length;
    @endif

    renderStepper();
})();
</script>
@endsection
