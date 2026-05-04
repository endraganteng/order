@extends('admin.layout')

@section('title', 'Buat Tugas Baru - Admin')

@section('content')
    @php
        $taskScopeMode = ($taskScope ?? 'general') === 'rack_check' ? 'rack_check' : 'general';
        $taskScopeLabel = $taskScopeMode === 'rack_check' ? 'Cek Rak' : 'Tugas Umum';
    @endphp

    <div class="page-header">
        <h2 class="page-title">{{ $taskScopeMode === 'rack_check' ? 'Buat Tugas Cek Rak' : 'Buat Tugas Umum' }}</h2>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @push('styles')
    <style>
        .task-create-card {
            max-width: 1100px !important;
        }

        #role-waiter-checkbox-list .role-waiter-item {
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            padding: 10px;
            background: #fff;
        }

        #role-waiter-checkbox-list .role-waiter-item.is-hidden {
            display: none;
        }

        @media (min-width: 1024px) {
            #rack-checkbox-list {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                max-height: 360px !important;
            }

            #role-waiter-checkbox-list {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                max-height: 300px !important;
            }

            #assignment_type,
            #assigned_waiter_id,
            #assigned_waiter_role,
            #role_assignment_mode,
            #recurrence_type {
                width: 100% !important;
                max-width: 460px !important;
            }

            #schedule_time,
            #time_limit_minutes,
            #weekly_day,
            #interval_days {
                width: 100% !important;
                max-width: 320px !important;
            }

            .task-create-actions {
                max-width: 700px;
            }
        }

        /* Board Builder styles */
        .bb-wrapper { margin-bottom: 24px; }
        .bb-info-banner { padding: 14px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; margin-bottom: 20px; }
        .bb-info-banner-title { font-weight: 700; margin-bottom: 6px; }
        .bb-info-banner-text { font-size: 13px; line-height: 1.5; }
        .bb-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
        .bb-toolbar-label { display: flex; align-items: center; font-weight: 700; font-size: 13px; color: #475569; margin-right: 4px; white-space: nowrap; }
        .bb-toolbar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .bb-toolbar-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .bb-toolbar-btn.is-active { background: #eef2ff; border-color: #818cf8; color: #4338ca; }
        .bb-toolbar-btn--reset { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
        .bb-toolbar-btn--reset:hover { background: #fee2e2; border-color: #f87171; }
        .bb-toolbar-select { padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; color: #334155; background: #fff; cursor: pointer; }
        .bb-board { display: grid; grid-template-columns: 280px 1fr; gap: 16px; min-height: 320px; }
        .bb-rack-pool { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 12px; min-height: 280px; transition: border-color 0.2s, background 0.2s; }
        .bb-rack-pool.is-drag-over { border-color: #818cf8; background: #eef2ff; }
        .bb-rack-pool-header { font-weight: 700; font-size: 14px; color: #475569; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        .bb-rack-pool-count { font-size: 12px; font-weight: 600; color: #94a3b8; }
        .bb-rack-pool-list { display: flex; flex-direction: column; gap: 6px; }
        .bb-rack-pool-empty { text-align: center; padding: 30px 10px; color: #94a3b8; font-size: 13px; }
        .bb-rack-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; cursor: grab; transition: box-shadow 0.2s, border-color 0.2s, opacity 0.2s; user-select: none; -webkit-user-select: none; }
        .bb-rack-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-color: #94a3b8; }
        .bb-rack-card:active, .bb-rack-card.is-dragging { cursor: grabbing; opacity: 0.5; }
        .bb-rack-card-icon { width: 36px; height: 36px; border-radius: 8px; background: #fff7ed; border: 1px solid #fdba74; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .bb-rack-card-info { min-width: 0; flex: 1; }
        .bb-rack-card-name { font-weight: 700; font-size: 13px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bb-rack-card-loc { font-size: 12px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bb-waiter-lanes { display: flex; flex-direction: column; gap: 10px; max-height: 520px; overflow-y: auto; padding-right: 4px; }
        .bb-waiter-lane { background: #fff; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; }
        .bb-waiter-lane.is-drag-over { border-color: #818cf8; background: #f5f3ff; box-shadow: 0 0 0 3px rgba(129,140,248,0.15); }
        .bb-waiter-lane-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .bb-waiter-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0; }
        .bb-waiter-avatar--pelayan { background: #6366f1; }
        .bb-waiter-avatar--kasir { background: #f59e0b; }
        .bb-waiter-lane-name { font-weight: 700; font-size: 14px; color: #0f172a; }
        .bb-waiter-lane-role { font-size: 12px; color: #64748b; text-transform: capitalize; }
        .bb-waiter-lane-count { margin-left: auto; font-size: 12px; font-weight: 700; color: #6366f1; background: #eef2ff; padding: 2px 10px; border-radius: 20px; }
        .bb-waiter-lane-drop { min-height: 40px; border: 2px dashed #e2e8f0; border-radius: 8px; padding: 6px; display: flex; flex-wrap: wrap; gap: 6px; transition: border-color 0.2s; }
        .bb-waiter-lane.is-drag-over .bb-waiter-lane-drop { border-color: #a5b4fc; }
        .bb-waiter-lane-placeholder { width: 100%; text-align: center; padding: 8px; color: #cbd5e1; font-size: 12px; pointer-events: none; }
        .bb-rack-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; background: #fff7ed; border: 1px solid #fdba74; border-radius: 6px; font-size: 12px; font-weight: 600; color: #9a3412; cursor: grab; }
        .bb-rack-chip-remove { width: 16px; height: 16px; border-radius: 50%; border: none; background: #fed7aa; color: #c2410c; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; transition: background 0.2s; }
        .bb-rack-chip-remove:hover { background: #fdba74; }
        .bb-summary { margin-top: 14px; padding: 12px 16px; border-radius: 10px; background: #f0fdf4; border: 1px solid #86efac; font-size: 13px; color: #166534; display: none; }
        .bb-summary.is-visible { display: block; }
        .bb-summary-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .bb-waiter-lane-mode-btn { padding: 3px 10px; border-radius: 20px; border: 1px solid #cbd5e1; background: #f1f5f9; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-left: 6px; white-space: nowrap; }
        .bb-waiter-lane-mode-btn:hover { border-color: #94a3b8; }
        .bb-waiter-lane-mode-btn.is-fixed { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
        .bb-waiter-lane-mode-btn.is-rolling { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
        .bb-rack-chip.is-fixed { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
        .bb-rack-chip.is-rolling { background: #dbeafe; border-color: #60a5fa; color: #1e40af; }
        .bb-summary-detail { margin-top: 6px; font-size: 12px; opacity: 0.85; }
        .bb-add-rack-toggle { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border: 2px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; width: 100%; text-align: left; }
        .bb-add-rack-toggle:hover { border-color: #818cf8; color: #4338ca; background: #eef2ff; }
        .bb-add-rack-form { display: none; padding: 12px; border: 1px solid #c7d2fe; border-radius: 10px; background: #eef2ff; }
        .bb-add-rack-form.is-open { display: block; }
        .bb-add-rack-form label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px; }
        .bb-add-rack-form input[type="text"] { width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; margin-bottom: 8px; box-sizing: border-box; }
        .bb-add-rack-form input[type="text"]:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.2); }
        .bb-add-rack-actions { display: flex; gap: 6px; margin-top: 4px; }
        .bb-add-rack-submit { padding: 6px 14px; border: none; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .bb-add-rack-submit:hover { background: #4338ca; }
        .bb-add-rack-submit:disabled { background: #94a3b8; cursor: not-allowed; }
        .bb-add-rack-cancel { padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #64748b; font-size: 12px; font-weight: 600; cursor: pointer; }
        .bb-add-rack-cancel:hover { background: #f1f5f9; }
        .bb-add-rack-msg { font-size: 12px; margin-top: 6px; padding: 6px 10px; border-radius: 6px; }
        .bb-add-rack-msg--ok { background: #dcfce7; color: #166534; }
        .bb-add-rack-msg--err { background: #fef2f2; color: #991b1b; }
        @media (max-width: 768px) {
            .bb-board { grid-template-columns: 1fr; }
            .bb-rack-pool { min-height: 120px; }
            .bb-waiter-lanes { max-height: none; }
            .bb-toolbar { flex-direction: column; }
        }
    </style>
    @endpush

    <div class="card task-create-card" style="padding: 30px;">
        <form id="taskCreateForm" action="{{ route('admin.tasks.store') }}" method="POST">
            @csrf
            <input type="hidden" name="task_scope" value="{{ $taskScope ?? 'general' }}">
            <input type="hidden" id="task_type" name="task_type" value="{{ $taskScopeMode === 'rack_check' ? 'rack_check' : 'general' }}">

            @if($taskScopeMode === 'rack_check')
                {{-- ============================================================ --}}
                {{-- RACK CHECK MODE: Board Builder UI                             --}}
                {{-- ============================================================ --}}

                @php
                    $bbRacks = $racks ?? [];
                    $bbWaiters = $waiters ?? [];
                    $bbPelayanCount = collect($bbWaiters)->filter(fn($w) => strtolower($w['waiter_role'] ?? 'pelayan') === 'pelayan')->count();
                    $bbKasirCount = collect($bbWaiters)->filter(fn($w) => strtolower($w['waiter_role'] ?? 'pelayan') === 'kasir')->count();
                @endphp

                @php
                    $taskType = $taskScopeMode === 'rack_check' ? 'rack_check' : 'general';
                @endphp
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jenis Halaman
                    </label>
                    <div style="display: inline-block; padding: 10px 14px; border-radius: 8px; background: #fff7ed; border: 1px solid #fdba74; color: #1f2937; font-weight: 700; font-size: 14px;">📦 Cek Rak</div>
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Anda sedang membuat <b>{{ $taskScopeLabel }}</b>. Jenis tugas dikunci sesuai halaman agar tidak tercampur.
                    </div>
                </div>

                <div class="bb-wrapper">
                    {{-- Info Banner --}}
                    <div class="bb-info-banner">
                        <div class="bb-info-banner-title">🧭 Board Builder — Seret & Lepas Rak ke Waiter</div>
                        <div class="bb-info-banner-text">
                            Seret kartu rak dari kolom kiri ke lane waiter di kanan untuk menugaskan cek rak.
                            Gunakan tombol <b>Quick Assign</b> untuk pembagian otomatis, atau atur manual satu per satu.
                            <b>Judul tugas otomatis menggunakan nama rak</b> agar waiter langsung fokus ke rak tujuan.
                            <br><br>
                            💡 <b>Hybrid Fixed + Rolling:</b> Klik tombol <b>🔒 Fixed</b> pada waiter untuk mengunci rak-nya (tetap setiap hari).
                            Waiter yang bertanda <b>🔄 Rolling</b> akan bertukar rak otomatis setiap hari.
                        </div>
                    </div>

                    {{-- Toolbar --}}
                    <div class="bb-toolbar">
                        <span class="bb-toolbar-label">Quick Assign:</span>
                        <button type="button" id="bbQuickAll" class="bb-toolbar-btn">📦 Semua Rak → Semua Waiter</button>
                        <button type="button" id="bbQuickRolling" class="bb-toolbar-btn">🔄 Rolling Merata</button>
                        <select id="bbRoleFilter" class="bb-toolbar-select">
                            <option value="all">Semua Role</option>
                            <option value="pelayan">Pelayan ({{ $bbPelayanCount }})</option>
                            <option value="kasir">Kasir ({{ $bbKasirCount }})</option>
                        </select>
                        <button type="button" id="bbQuickReset" class="bb-toolbar-btn bb-toolbar-btn--reset">🗑️ Reset</button>
                    </div>

                    {{-- Board Grid --}}
                    <div class="bb-board">
                        {{-- Left: Rack Pool --}}
                        <div class="bb-rack-pool" id="bbRackPool">
                            <div class="bb-rack-pool-header">
                                <span>📦 Rak Tersedia</span>
                                <span class="bb-rack-pool-count" id="bbRackPoolCount">{{ count($bbRacks) }}</span>
                            </div>
                            <div class="bb-rack-pool-list" id="bbRackPoolList">
                                @forelse($bbRacks as $rack)
                                    <div class="bb-rack-card"
                                         draggable="true"
                                         data-rack-id="{{ $rack['id'] ?? '' }}"
                                         data-rack-name="{{ $rack['name'] ?? '-' }}"
                                         data-rack-location="{{ $rack['location'] ?? '-' }}"
                                         data-rack-barcode="{{ $rack['barcode_value'] ?? '-' }}">
                                        <div class="bb-rack-card-icon">📦</div>
                                        <div class="bb-rack-card-info">
                                            <div class="bb-rack-card-name">{{ $rack['name'] ?? '-' }}</div>
                                            <div class="bb-rack-card-loc">📍 {{ $rack['location'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="bb-rack-pool-empty">Belum ada rak aktif.</div>
                                @endforelse
                            </div>

                            {{-- Inline Add Rack --}}
                            <div style="margin-top: 10px;">
                                <button type="button" id="bbAddRackToggle" class="bb-add-rack-toggle">
                                    ➕ Tambah Rak Baru
                                </button>
                                <div id="bbAddRackForm" class="bb-add-rack-form">
                                    <label for="bbNewRackName">Nama Rak <span style="color:#dc2626;">*</span></label>
                                    <input type="text" id="bbNewRackName" placeholder="Contoh: Rak Minuman A" maxlength="120" autocomplete="off">
                                    <label for="bbNewRackLocation">Lokasi <span style="color:#dc2626;">*</span></label>
                                    <input type="text" id="bbNewRackLocation" placeholder="Contoh: Lantai 1, Dekat Kasir" maxlength="120" autocomplete="off">
                                    <div class="bb-add-rack-actions">
                                        <button type="button" id="bbAddRackSubmit" class="bb-add-rack-submit">💾 Simpan Rak</button>
                                        <button type="button" id="bbAddRackCancel" class="bb-add-rack-cancel">Batal</button>
                                    </div>
                                    <div id="bbAddRackMsg" class="bb-add-rack-msg" style="display:none;"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Waiter Lanes --}}
                        <div class="bb-waiter-lanes" id="bbWaiterLanes">
                            @forelse($bbWaiters as $waiter)
                                @php
                                    $wRole = strtolower($waiter['waiter_role'] ?? 'pelayan');
                                    $wInitial = strtoupper(mb_substr($waiter['name'] ?? '?', 0, 1));
                                @endphp
                                <div class="bb-waiter-lane"
                                     data-waiter-id="{{ $waiter['id'] ?? '' }}"
                                     data-waiter-name="{{ $waiter['name'] ?? '-' }}"
                                     data-waiter-role="{{ $wRole }}">
                                    <div class="bb-waiter-lane-header">
                                        <div class="bb-waiter-avatar bb-waiter-avatar--{{ $wRole }}">{{ $wInitial }}</div>
                                        <div>
                                            <div class="bb-waiter-lane-name">{{ $waiter['name'] ?? '-' }}</div>
                                            <div class="bb-waiter-lane-role">{{ $wRole }}</div>
                                        </div>
                                        <button type="button" class="bb-waiter-lane-mode-btn is-rolling js-bb-mode-toggle" title="Klik untuk toggle Fixed/Rolling">🔄 Rolling</button>
                                        <span class="bb-waiter-lane-count" data-count="0">0 rak</span>
                                    </div>
                                    <div class="bb-waiter-lane-drop">
                                        <div class="bb-waiter-lane-placeholder">Seret rak ke sini</div>
                                    </div>
                                </div>
                            @empty
                                <div style="padding: 20px; text-align: center; color: #94a3b8;">Belum ada waiter aktif.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Summary Bar --}}
                    <div class="bb-summary" id="bbSummary"></div>
                </div>

                {{-- Hidden form fields for Board Builder --}}
                <input type="hidden" id="rack_target_scope" name="rack_target_scope" value="single">
                <div id="bbHiddenRackIds"></div>
                <input type="hidden" id="assignment_type" name="assignment_type" value="role">
                <input type="hidden" id="assigned_waiter_id" name="assigned_waiter_id" value="">
                <input type="hidden" id="assigned_waiter_role" name="assigned_waiter_role" value="pelayan">
                <input type="hidden" id="role_assignment_mode" name="role_assignment_mode" value="rolling">
                <div id="bbHiddenSelectedWaiterIds"></div>
                <input type="hidden" id="fixed_rack_assignments" name="fixed_rack_assignments" value="">

                {{-- Photo Proof --}}
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                        <input
                            type="checkbox"
                            id="requires_photo_proof"
                            name="requires_photo_proof"
                            value="1"
                            {{ old('requires_photo_proof') ? 'checked' : '' }}
                            style="margin-top: 3px;"
                        >
                        <span>📷 Wajib bukti foto saat waiter menyelesaikan tugas</span>
                    </label>
                    <div style="font-size: 13px; color: #666; margin-top: 8px; margin-left: 26px;">
                        Jika aktif, waiter harus ambil/upload foto bukti (kamera HP) sebelum tombol verifikasi bisa diproses.
                    </div>
                </div>

                {{-- Schedule Section (rack_check) --}}
                <div style="margin-bottom: 20px; padding: 14px; border: 1px solid #fdba74; border-radius: 10px; background: #fff7ed;">
                    <div style="font-weight: 700; color: #9a3412; margin-bottom: 6px;">⏱️ Jadwal Cek Rak</div>
                    <div style="font-size: 13px; color: #7c2d12; line-height: 1.5;">
                        Supervisor dapat mengubah pola jadwal, jam mulai, dan batas waktu penyelesaian sesuai kebutuhan operasional.
                    </div>
                </div>
                <input type="hidden" id="is_recurring" name="is_recurring" value="1">

                <div id="recurring-time-wrapper" style="margin-bottom: 25px; display: block;">
                    <div style="margin-bottom: 14px;">
                        <label for="recurrence_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Pola Perulangan <span style="color: #dc3545;">*</span>
                        </label>
                        @php $recurrenceType = old('recurrence_type', 'daily'); @endphp
                        <select
                            id="recurrence_type"
                            name="recurrence_type"
                            onchange="toggleRecurrenceDetailFields()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                            required
                        >
                            <option value="daily" {{ $recurrenceType === 'daily' ? 'selected' : '' }}>Setiap Hari</option>
                            <option value="weekly" {{ $recurrenceType === 'weekly' ? 'selected' : '' }}>Mingguan (hari tertentu)</option>
                            <option value="every_n_days" {{ $recurrenceType === 'every_n_days' ? 'selected' : '' }}>Setiap N Hari</option>
                        </select>
                    </div>

                    <div id="weekly-day-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="weekly_day" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Hari (Mode Mingguan) <span style="color: #dc3545;">*</span>
                        </label>
                        @php $weeklyDay = old('weekly_day', date('N')); @endphp
                        <select
                            id="weekly_day"
                            name="weekly_day"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                            <option value="1" {{ (string) $weeklyDay === '1' ? 'selected' : '' }}>Senin</option>
                            <option value="2" {{ (string) $weeklyDay === '2' ? 'selected' : '' }}>Selasa</option>
                            <option value="3" {{ (string) $weeklyDay === '3' ? 'selected' : '' }}>Rabu</option>
                            <option value="4" {{ (string) $weeklyDay === '4' ? 'selected' : '' }}>Kamis</option>
                            <option value="5" {{ (string) $weeklyDay === '5' ? 'selected' : '' }}>Jumat</option>
                            <option value="6" {{ (string) $weeklyDay === '6' ? 'selected' : '' }}>Sabtu</option>
                            <option value="7" {{ (string) $weeklyDay === '7' ? 'selected' : '' }}>Minggu</option>
                        </select>
                    </div>

                    <div id="interval-days-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="interval_days" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Interval Hari (Mode Setiap N Hari) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="interval_days"
                            name="interval_days"
                            min="1"
                            max="365"
                            value="{{ old('interval_days', 2) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 2 berarti task muncul setiap 2 hari sekali.
                        </div>
                    </div>

                    <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jam Jadwal <span style="color: #dc3545;">*</span>
                    </label>
                    <input
                        type="time"
                        id="schedule_time"
                        name="schedule_time"
                        value="{{ old('schedule_time', '06:00') }}"
                        style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"                        required
                    >
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Format 24 jam, contoh: 06:00 atau 07:30.
                    </div>

                    <div style="margin-top: 14px;">
                        <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Batas Waktu Penyelesaian (menit) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="time_limit_minutes"
                            name="time_limit_minutes"
                            min="1"
                            max="1440"
                            value="{{ old('time_limit_minutes', 900) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"                            required
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 900 berarti task harus selesai maksimal 15 jam setelah jam jadwal.
                        </div>
                    </div>
                </div>
            @else
                {{-- ============================================================ --}}
                {{-- GENERAL MODE: Keep ALL existing content exactly as-is         --}}
                {{-- ============================================================ --}}

                <div style="margin-bottom: 20px;">
                    <label for="title" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Judul Tugas <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}"
                        placeholder="Contoh: Bersihkan area meja 5"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"                        required>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="description" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Deskripsi <span style="color: #999; font-weight: normal;">(opsional)</span>
                    </label>
                    <textarea id="description" name="description" rows="3"
                        placeholder="Detail tambahan tentang tugas..."
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; resize: vertical; font-family: inherit; transition: border-color 0.3s;">{{ old('description') }}</textarea>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                        Prioritas <span style="color: #dc3545;">*</span>
                    </label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label style="flex: 1; min-width: 120px; cursor: pointer;">
                            <input type="radio" name="priority" value="urgent" {{ old('priority') === 'urgent' ? 'checked' : '' }}
                                style="display: none;" onchange="updatePriorityStyle(this)">
                            <div class="priority-option" data-priority="urgent"
                                style="padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; text-align: center; transition: all 0.3s;">
                                <div style="font-size: 24px; margin-bottom: 6px;">🔴</div>
                                <div style="font-weight: 600; font-size: 14px;">Urgent</div>
                            </div>
                        </label>
                        <label style="flex: 1; min-width: 120px; cursor: pointer;">
                            <input type="radio" name="priority" value="normal" {{ old('priority', 'normal') === 'normal' ? 'checked' : '' }}
                                style="display: none;" onchange="updatePriorityStyle(this)">
                            <div class="priority-option" data-priority="normal"
                                style="padding: 14px; border: 2px solid #667eea; border-radius: 8px; text-align: center; background: #f0f3ff; transition: all 0.3s;">
                                <div style="font-size: 24px; margin-bottom: 6px;">🔵</div>
                                <div style="font-weight: 600; font-size: 14px;">Normal</div>
                            </div>
                        </label>
                        <label style="flex: 1; min-width: 120px; cursor: pointer;">
                            <input type="radio" name="priority" value="low" {{ old('priority') === 'low' ? 'checked' : '' }}
                                style="display: none;" onchange="updatePriorityStyle(this)">
                            <div class="priority-option" data-priority="low"
                                style="padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; text-align: center; transition: all 0.3s;">
                                <div style="font-size: 24px; margin-bottom: 6px;">⚪</div>
                                <div style="font-weight: 600; font-size: 14px;">Low</div>
                            </div>
                        </label>
                    </div>
                </div>

                @php
                    $taskType = $taskScopeMode === 'rack_check' ? 'rack_check' : 'general';
                    $oldRackIdsInput = old('rack_ids', []);
                    if (!is_array($oldRackIdsInput)) {
                        $oldRackIdsInput = explode(',', (string) $oldRackIdsInput);
                    }
                    $oldRackIdsInput = array_values(array_filter(array_map(function ($rackId) {
                        return trim((string) $rackId);
                    }, $oldRackIdsInput), function ($rackId) {
                        return $rackId !== '';
                    }));

                    $legacyRackId = trim((string) old('rack_id', ''));
                    if ($legacyRackId !== '') {
                        $oldRackIdsInput[] = $legacyRackId;
                    }

                    $selectedRackIds = array_values(array_unique($oldRackIdsInput));
                    $rackTotalCount = count($racks ?? []);
                    $selectedRackCount = count($selectedRackIds);
                    $rackTargetScope = old('rack_target_scope', ($rackTotalCount > 0 && $selectedRackCount === $rackTotalCount) ? 'all' : 'single');
                @endphp
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jenis Halaman
                    </label>
                    <div style="display: inline-block; padding: 10px 14px; border-radius: 8px; background: #eef2ff; border: 1px solid #c7d2fe; color: #1f2937; font-weight: 700; font-size: 14px;">📝 Tugas Umum</div>
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Anda sedang membuat <b>{{ $taskScopeLabel }}</b>. Jenis tugas dikunci sesuai halaman agar tidak tercampur.
                    </div>
                </div>

                <div id="rack-selector-wrapper" style="margin-bottom: 20px; display: none;">
                    <input type="hidden" id="rack_target_scope" name="rack_target_scope" value="{{ $rackTargetScope }}">

                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Pilih Rak Target (Bisa Multi-Pilih) <span style="color: #dc3545;">*</span>
                    </label>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; cursor: pointer;">
                            <input type="checkbox" id="rack_select_all" {{ ($rackTotalCount > 0 && $selectedRackCount === $rackTotalCount) ? 'checked' : '' }}>
                            Pilih semua rak aktif
                        </label>
                        <span id="rack-selection-count-hint" style="font-size: 12px; color: #64748b;">
                            {{ $selectedRackCount }} dari {{ $rackTotalCount }} rak dipilih
                        </span>
                    </div>

                    <div id="rack-checkbox-list" style="display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); max-height: 280px; overflow: auto; padding: 10px; border: 1px solid #dbe2ea; border-radius: 10px; background: #f8fafc;">
                        @forelse(($racks ?? []) as $rack)
                            @php
                                $rackId = (string) ($rack['id'] ?? '');
                                $isChecked = in_array($rackId, $selectedRackIds, true);
                            @endphp
                            <label class="rack-checkbox-item" style="display: block; border: 1px solid #dbe2ea; border-radius: 8px; padding: 10px; background: #fff; cursor: pointer;">
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    <input
                                        type="checkbox"
                                        class="js-rack-checkbox"
                                        name="rack_ids[]"
                                        value="{{ $rackId }}"
                                        {{ $isChecked ? 'checked' : '' }}
                                        style="margin-top: 3px;"
                                    >
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 700; color: #0f172a; word-break: break-word;">{{ $rack['name'] ?? '-' }}</div>
                                        <div style="font-size: 12px; color: #64748b; word-break: break-word;">📍 {{ $rack['location'] ?? '-' }}</div>
                                        <div style="font-size: 12px; color: #94a3b8; word-break: break-all;">QR: {{ $rack['barcode_value'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div style="font-size: 13px; color: #b91c1c;">Belum ada rak aktif untuk dipilih.</div>
                        @endforelse
                    </div>

                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Supervisor bisa memilih satu atau beberapa rak sekaligus. Sistem akan membuat task cek-rak per rak terpilih.
                        <br>
                        Rak belum ada? <a href="{{ route('admin.racks.create') }}" target="_blank" rel="noopener">Tambah rak dulu</a>.
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                        <input
                            type="checkbox"
                            id="requires_photo_proof"
                            name="requires_photo_proof"
                            value="1"
                            {{ old('requires_photo_proof') ? 'checked' : '' }}
                            style="margin-top: 3px;"
                        >
                        <span>📷 Wajib bukti foto saat waiter menyelesaikan tugas</span>
                    </label>
                    <div style="font-size: 13px; color: #666; margin-top: 8px; margin-left: 26px;">
                        Jika aktif, waiter harus ambil/upload foto bukti (kamera HP) sebelum tombol verifikasi bisa diproses.
                    </div>
                </div>

                @php
                    $assignmentType = old('assignment_type', 'all');
                    $assignedWaiterRole = old('assigned_waiter_role', 'pelayan');
                    $roleAssignmentMode = old('role_assignment_mode', $taskScopeMode === 'rack_check' ? 'rolling' : 'all');
                    $oldSelectedWaiterIdsInput = old('selected_waiter_ids', []);
                    if (!is_array($oldSelectedWaiterIdsInput)) {
                        $oldSelectedWaiterIdsInput = explode(',', (string) $oldSelectedWaiterIdsInput);
                    }
                    $selectedWaiterIds = array_values(array_unique(array_filter(array_map(function ($waiterId) {
                        return trim((string) $waiterId);
                    }, $oldSelectedWaiterIdsInput), function ($waiterId) {
                        return $waiterId !== '';
                    })));
                    $activePelayanCount = collect($waiters ?? [])->filter(function ($waiter) {
                        return strtolower((string) ($waiter['waiter_role'] ?? 'pelayan')) === 'pelayan';
                    })->count();
                    $activeKasirCount = collect($waiters ?? [])->filter(function ($waiter) {
                        return strtolower((string) ($waiter['waiter_role'] ?? 'pelayan')) === 'kasir';
                    })->count();
                @endphp
                <div style="margin-bottom: 20px;">
                    <label for="assignment_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Delegasi Ke <span style="color: #dc3545;">*</span>
                    </label>
                    <select
                        id="assignment_type"
                        name="assignment_type"
                        onchange="toggleAssignmentFields()"
                        style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                    >
                        <option value="all" {{ $assignmentType === 'all' ? 'selected' : '' }}>Semua Waiter Aktif</option>
                        <option value="single" {{ $assignmentType === 'single' ? 'selected' : '' }}>Waiter Tertentu</option>
                        <option value="role" {{ $assignmentType === 'role' ? 'selected' : '' }}>Berdasarkan Role</option>
                    </select>
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Supervisor bisa kirim ke 1 waiter, semua waiter aktif, atau waiter berdasarkan role.
                    </div>
                </div>

                <div id="single-waiter-wrapper" style="margin-bottom: 20px; display: none;">
                    <label for="assigned_waiter_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Pilih Nama Waiter <span style="color: #dc3545;">*</span>
                    </label>
                    <select
                        id="assigned_waiter_id"
                        name="assigned_waiter_id"
                        style="width: 100%; max-width: 420px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                    >
                        <option value="">-- Pilih waiter --</option>
                        @foreach(($waiters ?? []) as $waiter)
                            @php $isDayOff = ($waiterDayOffMap[$waiter['id'] ?? ''] ?? false); @endphp
                            <option value="{{ $waiter['id'] }}" {{ old('assigned_waiter_id') === ($waiter['id'] ?? '') ? 'selected' : '' }}>
                                {{ $waiter['name'] ?? '-' }} ({{ $waiter['email'] ?? '-' }}){{ $isDayOff ? ' — LIBUR HARI INI' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="role-waiter-wrapper" style="margin-bottom: 20px; display: none;">
                    <label for="assigned_waiter_role" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Pilih Role Waiter <span style="color: #dc3545;">*</span>
                    </label>
                    <select
                        id="assigned_waiter_role"
                        name="assigned_waiter_role"
                        style="width: 100%; max-width: 320px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                    >
                        <option value="pelayan" {{ $assignedWaiterRole === 'pelayan' ? 'selected' : '' }}>Pelayan ({{ $activePelayanCount }} aktif)</option>
                        <option value="kasir" {{ $assignedWaiterRole === 'kasir' ? 'selected' : '' }}>Kasir ({{ $activeKasirCount }} aktif)</option>
                    </select>
                </div>

                <div id="role-assignment-mode-wrapper" style="margin-bottom: 20px; display: none;">
                    <label for="role_assignment_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Mode Delegasi Role <span style="color: #dc3545;">*</span>
                    </label>
                    <select
                        id="role_assignment_mode"
                        name="role_assignment_mode"
                        style="width: 100%; max-width: 360px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                    >
                        <option value="all" {{ $roleAssignmentMode === 'all' ? 'selected' : '' }}>Semua Waiter dalam Role</option>
                        <option value="selected" {{ $roleAssignmentMode === 'selected' ? 'selected' : '' }}>Pilih Waiter Tertentu (Checklist)</option>
                        <option value="rolling" data-rack-only="1" {{ $roleAssignmentMode === 'rolling' ? 'selected' : '' }}>Rolling per Rak (disarankan untuk Cek Rak)</option>
                    </select>
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Pilih <b>Semua</b> untuk broadcast satu role, <b>Pilih Waiter Tertentu</b> untuk subset via checkbox,
                        atau <b>Rolling per Rak</b> untuk pembagian merata yang berotasi otomatis setiap hari.
                        Anda juga bisa memilih waiter tertentu lalu tetap memakai rolling per rak.
                    </div>
                </div>

                <div id="role-selected-waiters-wrapper" style="margin-bottom: 20px; display: none;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Pilih Nama Waiter dalam Role <span style="color: #dc3545;">*</span>
                    </label>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; cursor: pointer;">
                            <input type="checkbox" id="role_waiter_select_all">
                            Pilih semua waiter yang tampil
                        </label>
                        <span id="role-selection-count-hint" style="font-size: 12px; color: #64748b;">0 waiter dipilih</span>
                    </div>

                    <div id="role-waiter-checkbox-list" style="display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); max-height: 240px; overflow: auto; padding: 10px; border: 1px solid #dbe2ea; border-radius: 10px; background: #f8fafc;">
                        @foreach(($waiters ?? []) as $waiter)
                            @php
                                $waiterId = (string) ($waiter['id'] ?? '');
                                $waiterRole = strtolower((string) ($waiter['waiter_role'] ?? 'pelayan'));
                                $isSelectedWaiter = in_array($waiterId, $selectedWaiterIds, true);
                                $isWaiterOff = ($waiterDayOffMap[$waiterId] ?? false);
                            @endphp
                            <label class="role-waiter-item" data-waiter-role="{{ $waiterRole }}">
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    <input
                                        type="checkbox"
                                        class="js-role-waiter-checkbox"
                                        name="selected_waiter_ids[]"
                                        value="{{ $waiterId }}"
                                        data-waiter-role="{{ $waiterRole }}"
                                        {{ $isSelectedWaiter ? 'checked' : '' }}
                                        style="margin-top: 3px;"
                                    >
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 700; color: #0f172a; word-break: break-word;">{{ $waiter['name'] ?? '-' }}@if($isWaiterOff) <span style="color: #dc2626; font-size: 11px; font-weight: 600;">LIBUR</span>@endif</div>
                                        <div style="font-size: 12px; color: #64748b; word-break: break-all;">{{ $waiter['email'] ?? '-' }}</div>
                                        <div style="font-size: 12px; color: #94a3b8; text-transform: capitalize;">Role: {{ $waiterRole }}</div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Centang waiter yang dipilih untuk menerima tugas pada role ini.
                        Jika mode <b>Rolling per Rak</b> aktif dan tidak ada checklist waiter, sistem akan memakai semua waiter pada role.
                    </div>
                </div>

                {{-- General mode: recurring section --}}
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                        <input
                            type="checkbox"
                            id="is_recurring"
                            name="is_recurring"
                            value="1"
                            {{ old('is_recurring') ? 'checked' : '' }}
                            onchange="toggleRecurringFields()"
                        >
                        🔁 Jadwalkan sebagai task berulang
                    </label>
                    <div style="font-size: 13px; color: #666; margin-top: 8px; margin-left: 26px;">
                        Pilih pola: harian, mingguan, atau setiap N hari.
                    </div>
                </div>

                <div id="recurring-time-wrapper" style="margin-bottom: 25px; display: none;">
                    <div style="margin-bottom: 14px;">
                        <label for="recurrence_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Pola Perulangan <span style="color: #dc3545;">*</span>
                        </label>
                        @php $recurrenceType = old('recurrence_type', 'daily'); @endphp
                        <select
                            id="recurrence_type"
                            name="recurrence_type"
                            onchange="toggleRecurrenceDetailFields()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                            <option value="daily" {{ $recurrenceType === 'daily' ? 'selected' : '' }}>Setiap Hari</option>
                            <option value="weekly" {{ $recurrenceType === 'weekly' ? 'selected' : '' }}>Mingguan (hari tertentu)</option>
                            <option value="every_n_days" {{ $recurrenceType === 'every_n_days' ? 'selected' : '' }}>Setiap N Hari</option>
                        </select>
                    </div>

                    <div id="weekly-day-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="weekly_day" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Hari (Mode Mingguan) <span style="color: #dc3545;">*</span>
                        </label>
                        @php $weeklyDay = old('weekly_day', date('N')); @endphp
                        <select
                            id="weekly_day"
                            name="weekly_day"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                            <option value="1" {{ (string) $weeklyDay === '1' ? 'selected' : '' }}>Senin</option>
                            <option value="2" {{ (string) $weeklyDay === '2' ? 'selected' : '' }}>Selasa</option>
                            <option value="3" {{ (string) $weeklyDay === '3' ? 'selected' : '' }}>Rabu</option>
                            <option value="4" {{ (string) $weeklyDay === '4' ? 'selected' : '' }}>Kamis</option>
                            <option value="5" {{ (string) $weeklyDay === '5' ? 'selected' : '' }}>Jumat</option>
                            <option value="6" {{ (string) $weeklyDay === '6' ? 'selected' : '' }}>Sabtu</option>
                            <option value="7" {{ (string) $weeklyDay === '7' ? 'selected' : '' }}>Minggu</option>
                        </select>
                    </div>

                    <div id="interval-days-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="interval_days" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Interval Hari (Mode Setiap N Hari) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="interval_days"
                            name="interval_days"
                            min="1"
                            max="365"
                            value="{{ old('interval_days', 2) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 2 berarti task muncul setiap 2 hari sekali.
                        </div>
                    </div>

                    <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jam Jadwal <span style="color: #dc3545;">*</span>
                    </label>
                    <input
                        type="time"
                        id="schedule_time"
                        name="schedule_time"
                        value="{{ old('schedule_time') }}"
                        style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"                    >
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Format 24 jam, contoh: 10:30 atau 16:45.
                    </div>

                    <div style="margin-top: 14px;">
                        <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Batas Waktu Penyelesaian (menit) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="time_limit_minutes"
                            name="time_limit_minutes"
                            min="1"
                            max="1440"
                            value="{{ old('time_limit_minutes', 30) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 30 berarti task harus selesai maksimal 30 menit setelah jam jadwal.
                        </div>
                    </div>
                </div>
            @endif

            <div class="task-create-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px; flex: 1;">
                    📤 Delegasikan Tugas ke Waiter
                </button>
                <a href="{{ route($backRouteName ?? 'admin.tasks.index') }}" class="btn" 
                    style="padding: 12px 20px; font-size: 16px; background: #e0e0e0; color: #333; text-align: center;">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <script>
        function updatePriorityStyle(radio) {
            // Reset all options
            document.querySelectorAll('.priority-option').forEach(el => {
                el.style.borderColor = '#e0e0e0';
                el.style.background = 'white';
            });

            // Highlight selected
            const selected = radio.closest('label').querySelector('.priority-option');
            const priority = selected.dataset.priority;
            
            if (priority === 'urgent') {
                selected.style.borderColor = '#dc3545';
                selected.style.background = '#fff5f5';
            } else if (priority === 'normal') {
                selected.style.borderColor = '#667eea';
                selected.style.background = '#f0f3ff';
            } else {
                selected.style.borderColor = '#6c757d';
                selected.style.background = '#f8f9fa';
            }
        }

        function toggleRecurringFields() {
            const recurringCheckbox = document.getElementById('is_recurring');
            const recurringWrapper = document.getElementById('recurring-time-wrapper');
            const scheduleTimeInput = document.getElementById('schedule_time');
            const timeLimitInput = document.getElementById('time_limit_minutes');
            const recurrenceTypeInput = document.getElementById('recurrence_type');

            if (!recurringCheckbox || !recurringWrapper || !scheduleTimeInput || !timeLimitInput || !recurrenceTypeInput) {
                return;
            }

            const forceRecurring = recurringCheckbox.type === 'hidden';
            if (forceRecurring || recurringCheckbox.checked) {
                recurringWrapper.style.display = 'block';
                scheduleTimeInput.required = true;
                timeLimitInput.required = true;
                recurrenceTypeInput.required = true;
                toggleRecurrenceDetailFields();
            } else {
                recurringWrapper.style.display = 'none';
                scheduleTimeInput.required = false;
                timeLimitInput.required = false;
                recurrenceTypeInput.required = false;

                document.getElementById('weekly_day').required = false;
                document.getElementById('interval_days').required = false;
            }
        }

        function syncRackSelectionState() {
            const rackTargetScopeInput = document.getElementById('rack_target_scope');
            const rackCheckboxes = Array.from(document.querySelectorAll('.js-rack-checkbox'));
            const selectAllCheckbox = document.getElementById('rack_select_all');
            const countHintEl = document.getElementById('rack-selection-count-hint');

            if (!rackTargetScopeInput || rackCheckboxes.length === 0) {
                if (countHintEl) {
                    countHintEl.textContent = '0 rak tersedia';
                }
                return;
            }

            const totalCount = rackCheckboxes.length;
            const selectedCount = rackCheckboxes.filter((checkbox) => checkbox.checked).length;

            rackTargetScopeInput.value = selectedCount > 0 && selectedCount === totalCount ? 'all' : 'single';

            if (countHintEl) {
                countHintEl.textContent = `${selectedCount} dari ${totalCount} rak dipilih`;
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCount;
                selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCount;
            }

            rackCheckboxes.forEach((checkbox, index) => {
                checkbox.required = index === 0 && selectedCount === 0;
            });
        }

        function toggleTaskTypeFields() {
            const taskTypeInput = document.getElementById('task_type');
            const taskType = taskTypeInput ? taskTypeInput.value : 'general';
            const rackWrapper = document.getElementById('rack-selector-wrapper');
            const rackTargetScopeInput = document.getElementById('rack_target_scope');
            const rackCheckboxes = Array.from(document.querySelectorAll('.js-rack-checkbox'));
            const selectAllCheckbox = document.getElementById('rack_select_all');

            if (!rackWrapper || !rackTargetScopeInput) {
                toggleAssignmentFields();
                return;
            }

            if (taskType === 'rack_check') {
                rackWrapper.style.display = 'block';
                rackTargetScopeInput.required = true;

                rackCheckboxes.forEach((checkbox) => {
                    checkbox.disabled = false;
                });

                if (selectAllCheckbox) {
                    selectAllCheckbox.disabled = false;
                }

                syncRackSelectionState();
            } else {
                rackWrapper.style.display = 'none';
                rackTargetScopeInput.required = false;

                rackCheckboxes.forEach((checkbox) => {
                    checkbox.required = false;
                    checkbox.disabled = true;
                });

                if (selectAllCheckbox) {
                    selectAllCheckbox.disabled = true;
                }
            }

            toggleAssignmentFields();
        }

        function toggleAssignmentFields() {
            var assignmentTypeEl = document.getElementById('assignment_type');
            if (!assignmentTypeEl || assignmentTypeEl.type === 'hidden') return;

            const assignmentType = assignmentTypeEl.value;
            const singleWaiterWrapper = document.getElementById('single-waiter-wrapper');
            const roleWaiterWrapper = document.getElementById('role-waiter-wrapper');
            const roleAssignmentModeWrapper = document.getElementById('role-assignment-mode-wrapper');
            const roleSelectedWaitersWrapper = document.getElementById('role-selected-waiters-wrapper');
            const assignedWaiterInput = document.getElementById('assigned_waiter_id');
            const assignedWaiterRoleInput = document.getElementById('assigned_waiter_role');
            const roleAssignmentModeInput = document.getElementById('role_assignment_mode');
            const taskTypeInput = document.getElementById('task_type');
            const taskType = taskTypeInput ? taskTypeInput.value : 'general';
            const rollingOption = roleAssignmentModeInput
                ? roleAssignmentModeInput.querySelector('option[value="rolling"]')
                : null;

            if (assignmentType === 'single') {
                singleWaiterWrapper.style.display = 'block';
                roleWaiterWrapper.style.display = 'none';
                roleAssignmentModeWrapper.style.display = 'none';
                roleSelectedWaitersWrapper.style.display = 'none';
                assignedWaiterInput.required = true;
                assignedWaiterRoleInput.required = false;
                roleAssignmentModeInput.required = false;
            } else if (assignmentType === 'role') {
                singleWaiterWrapper.style.display = 'none';
                roleWaiterWrapper.style.display = 'block';
                roleAssignmentModeWrapper.style.display = 'block';
                assignedWaiterInput.required = false;
                assignedWaiterRoleInput.required = true;
                roleAssignmentModeInput.required = true;

                if (rollingOption) {
                    rollingOption.disabled = taskType !== 'rack_check';
                }

                if (taskType !== 'rack_check' && roleAssignmentModeInput.value === 'rolling') {
                    roleAssignmentModeInput.value = 'all';
                }

                roleSelectedWaitersWrapper.style.display = roleAssignmentModeInput.value === 'selected' ? 'block' : 'none';
            } else {
                singleWaiterWrapper.style.display = 'none';
                roleWaiterWrapper.style.display = 'none';
                roleAssignmentModeWrapper.style.display = 'none';
                roleSelectedWaitersWrapper.style.display = 'none';
                assignedWaiterInput.required = false;
                assignedWaiterRoleInput.required = false;
                roleAssignmentModeInput.required = false;

                if (rollingOption) {
                    rollingOption.disabled = false;
                }
            }

            syncRoleWaiterSelectionState();
        }

        function syncRoleWaiterSelectionState() {
            var assignmentTypeEl = document.getElementById('assignment_type');
            if (!assignmentTypeEl || assignmentTypeEl.type === 'hidden') return;

            const assignmentTypeInput = assignmentTypeEl;
            const roleInput = document.getElementById('assigned_waiter_role');
            const roleModeInput = document.getElementById('role_assignment_mode');
            const selectedWrapper = document.getElementById('role-selected-waiters-wrapper');
            const selectAllCheckbox = document.getElementById('role_waiter_select_all');
            const countHint = document.getElementById('role-selection-count-hint');
            const taskTypeInput = document.getElementById('task_type');
            const waiterItems = Array.from(document.querySelectorAll('.role-waiter-item'));
            const waiterCheckboxes = Array.from(document.querySelectorAll('.js-role-waiter-checkbox'));

            if (!assignmentTypeInput || !roleInput || !roleModeInput || !selectedWrapper || waiterCheckboxes.length === 0) {
                return;
            }

            const selectedRole = (roleInput.value || '').toLowerCase();
            const taskType = taskTypeInput ? taskTypeInput.value : 'general';
            const modeValue = roleModeInput.value;
            const usingRoleChecklistMode = assignmentTypeInput.value === 'role' && (modeValue === 'selected' || (taskType === 'rack_check' && modeValue === 'rolling'));
            const requireAtLeastOne = assignmentTypeInput.value === 'role' && modeValue === 'selected';

            selectedWrapper.style.display = usingRoleChecklistMode ? 'block' : 'none';

            let visibleCount = 0;
            let selectedVisibleCount = 0;
            let firstVisibleCheckbox = null;

            waiterItems.forEach((item) => {
                const itemRole = (item.getAttribute('data-waiter-role') || '').toLowerCase();
                const checkbox = item.querySelector('.js-role-waiter-checkbox');
                const isVisible = usingRoleChecklistMode && itemRole === selectedRole;

                item.classList.toggle('is-hidden', !isVisible);

                if (!checkbox) {
                    return;
                }

                checkbox.disabled = !isVisible;
                checkbox.required = false;

                if (!isVisible) {
                    checkbox.checked = false;
                    return;
                }

                visibleCount++;
                if (!firstVisibleCheckbox) {
                    firstVisibleCheckbox = checkbox;
                }
                if (checkbox.checked) {
                    selectedVisibleCount++;
                }
            });

            if (firstVisibleCheckbox && requireAtLeastOne && selectedVisibleCount === 0) {
                firstVisibleCheckbox.required = true;
            }

            if (countHint) {
                if (!usingRoleChecklistMode) {
                    countHint.textContent = 'Mode role terpilih tidak aktif';
                } else {
                    countHint.textContent = `${selectedVisibleCount} dari ${visibleCount} waiter dipilih`;
                }
            }

            if (selectAllCheckbox) {
                if (!usingRoleChecklistMode || visibleCount === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.disabled = true;
                } else {
                    selectAllCheckbox.disabled = false;
                    selectAllCheckbox.checked = selectedVisibleCount > 0 && selectedVisibleCount === visibleCount;
                    selectAllCheckbox.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleCount;
                }
            }
        }

        function toggleRecurrenceDetailFields() {
            const recurrenceTypeInput = document.getElementById('recurrence_type');
            const weeklyWrapper = document.getElementById('weekly-day-wrapper');
            const intervalWrapper = document.getElementById('interval-days-wrapper');
            const weeklyDayInput = document.getElementById('weekly_day');
            const intervalDaysInput = document.getElementById('interval_days');

            if (!recurrenceTypeInput || !weeklyWrapper || !intervalWrapper || !weeklyDayInput || !intervalDaysInput) {
                return;
            }

            const recurrenceType = recurrenceTypeInput.value;

            if (recurrenceType === 'weekly') {
                weeklyWrapper.style.display = 'block';
                intervalWrapper.style.display = 'none';
                weeklyDayInput.required = true;
                intervalDaysInput.required = false;
            } else if (recurrenceType === 'every_n_days') {
                weeklyWrapper.style.display = 'none';
                intervalWrapper.style.display = 'block';
                weeklyDayInput.required = false;
                intervalDaysInput.required = true;
            } else {
                weeklyWrapper.style.display = 'none';
                intervalWrapper.style.display = 'none';
                weeklyDayInput.required = false;
                intervalDaysInput.required = false;
            }
        }

        const rackSelectAllEl = document.getElementById('rack_select_all');
        if (rackSelectAllEl) {
            rackSelectAllEl.addEventListener('change', () => {
                const checked = rackSelectAllEl.checked;
                document.querySelectorAll('.js-rack-checkbox').forEach((checkbox) => {
                    if (!checkbox.disabled) {
                        checkbox.checked = checked;
                    }
                });
                syncRackSelectionState();
            });
        }

        document.querySelectorAll('.js-rack-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', syncRackSelectionState);
        });

        const roleSelectAllEl = document.getElementById('role_waiter_select_all');
        if (roleSelectAllEl) {
            roleSelectAllEl.addEventListener('change', () => {
                const selectedRole = (document.getElementById('assigned_waiter_role')?.value || '').toLowerCase();
                const shouldCheck = roleSelectAllEl.checked;

                document.querySelectorAll('.js-role-waiter-checkbox').forEach((checkbox) => {
                    const checkboxRole = (checkbox.getAttribute('data-waiter-role') || '').toLowerCase();
                    if (!checkbox.disabled && checkboxRole === selectedRole) {
                        checkbox.checked = shouldCheck;
                    }
                });

                syncRoleWaiterSelectionState();
            });
        }

        const roleInputEl = document.getElementById('assigned_waiter_role');
        if (roleInputEl) {
            roleInputEl.addEventListener('change', syncRoleWaiterSelectionState);
        }

        const roleModeEl = document.getElementById('role_assignment_mode');
        if (roleModeEl) {
            roleModeEl.addEventListener('change', toggleAssignmentFields);
        }

        document.querySelectorAll('.js-role-waiter-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', syncRoleWaiterSelectionState);
        });

        toggleAssignmentFields();
        toggleRecurringFields();
        toggleTaskTypeFields();

        /* ================================================================
         * Board Builder IIFE — rack_check mode only
         * ================================================================ */
        (function() {
            var taskTypeInput = document.getElementById('task_type');
            if (!taskTypeInput || taskTypeInput.value !== 'rack_check') return;

            // ── State ──
            var assignments = {}; // waiterId → [rackId, ...]
            var waiterModes = {}; // waiterId → 'fixed' | 'rolling'

            // ── DOM refs ──
            var poolList = document.getElementById('bbRackPoolList');
            var poolCountEl = document.getElementById('bbRackPoolCount');
            var pool = document.getElementById('bbRackPool');
            var lanesContainer = document.getElementById('bbWaiterLanes');
            var summaryEl = document.getElementById('bbSummary');
            var roleFilter = document.getElementById('bbRoleFilter');
            var form = document.getElementById('taskCreateForm');

            if (!poolList || !lanesContainer) return;

            var allRackCards = Array.from(poolList.querySelectorAll('.bb-rack-card'));
            var allLanes = Array.from(lanesContainer.querySelectorAll('.bb-waiter-lane'));

            // Initialize assignments map + waiter modes
            allLanes.forEach(function(lane) {
                var wid = lane.getAttribute('data-waiter-id');
                if (wid) {
                    assignments[wid] = [];
                    waiterModes[wid] = 'rolling'; // default: rolling
                }
            });

            // ── Mode Toggle Handler ──
            lanesContainer.addEventListener('click', function(e) {
                var btn = e.target.closest('.js-bb-mode-toggle');
                if (!btn) return;
                var lane = btn.closest('.bb-waiter-lane');
                if (!lane) return;
                var wid = lane.getAttribute('data-waiter-id');
                if (!wid) return;

                // Toggle mode
                if (waiterModes[wid] === 'rolling') {
                    waiterModes[wid] = 'fixed';
                    btn.className = 'bb-waiter-lane-mode-btn is-fixed js-bb-mode-toggle';
                    btn.innerHTML = '🔒 Fixed';
                    btn.title = 'Waiter ini TETAP di rak yang sama setiap hari. Klik untuk ubah ke Rolling.';
                } else {
                    waiterModes[wid] = 'rolling';
                    btn.className = 'bb-waiter-lane-mode-btn is-rolling js-bb-mode-toggle';
                    btn.innerHTML = '🔄 Rolling';
                    btn.title = 'Waiter ini ikut ROTASI rak otomatis setiap hari. Klik untuk ubah ke Fixed.';
                }
                renderBoard();
                syncHiddenFields();
            });

            // ── Inline Add Rack ──
            (function initAddRack() {
                var toggleBtn = document.getElementById('bbAddRackToggle');
                var formWrap = document.getElementById('bbAddRackForm');
                var nameInput = document.getElementById('bbNewRackName');
                var locInput = document.getElementById('bbNewRackLocation');
                var submitBtn = document.getElementById('bbAddRackSubmit');
                var cancelBtn = document.getElementById('bbAddRackCancel');
                var msgEl = document.getElementById('bbAddRackMsg');

                if (!toggleBtn || !formWrap) return;

                toggleBtn.addEventListener('click', function() {
                    formWrap.classList.toggle('is-open');
                    if (formWrap.classList.contains('is-open')) {
                        toggleBtn.style.display = 'none';
                        nameInput.focus();
                    } else {
                        toggleBtn.style.display = 'flex';
                    }
                });

                cancelBtn.addEventListener('click', function() {
                    formWrap.classList.remove('is-open');
                    toggleBtn.style.display = 'flex';
                    nameInput.value = '';
                    locInput.value = '';
                    msgEl.style.display = 'none';
                });

                submitBtn.addEventListener('click', function() {
                    var name = nameInput.value.trim();
                    var location = locInput.value.trim();

                    if (!name) { nameInput.focus(); showMsg('Nama rak wajib diisi.', false); return; }
                    if (!location) { locInput.focus(); showMsg('Lokasi rak wajib diisi.', false); return; }

                    submitBtn.disabled = true;
                    submitBtn.textContent = '⏳ Menyimpan...';
                    msgEl.style.display = 'none';

                    var csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken) {
                        var csrfInput = document.querySelector('input[name="_token"]');
                        var token = csrfInput ? csrfInput.value : '';
                    } else {
                        var token = csrfToken.getAttribute('content');
                    }

                    fetch('{{ route("admin.racks.ajax_store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ name: name, location: location }),
                    })
                    .then(function(resp) {
                        if (!resp.ok) {
                            return resp.json().then(function(data) { throw data; });
                        }
                        return resp.json();
                    })
                    .then(function(data) {
                        if (data.success && data.rack) {
                            var rack = data.rack;
                            // Create new rack card DOM element
                            var card = document.createElement('div');
                            card.className = 'bb-rack-card';
                            card.setAttribute('draggable', 'true');
                            card.setAttribute('data-rack-id', rack.id);
                            card.setAttribute('data-rack-name', rack.name);
                            card.setAttribute('data-rack-location', rack.location);
                            card.setAttribute('data-rack-barcode', rack.barcode_value || '');
                            card.innerHTML =
                                '<div class="bb-rack-card-icon">📦</div>' +
                                '<div class="bb-rack-card-info">' +
                                    '<div class="bb-rack-card-name">' + escapeHtml(rack.name) + '</div>' +
                                    '<div class="bb-rack-card-loc">📍 ' + escapeHtml(rack.location) + '</div>' +
                                '</div>';

                            // Remove empty state if present
                            var emptyEl = poolList.querySelector('.bb-rack-pool-empty');
                            if (emptyEl) emptyEl.remove();

                            // Add to pool list and track
                            poolList.appendChild(card);
                            allRackCards.push(card);

                            // Attach drag events to new card
                            attachDragToCard(card);

                            // Update pool count
                            renderBoard();
                            syncHiddenFields();

                            // Reset form
                            nameInput.value = '';
                            locInput.value = '';
                            showMsg('✅ Rak "' + rack.name + '" berhasil ditambahkan!', true);

                            // Auto-close form after 1.5s
                            setTimeout(function() {
                                formWrap.classList.remove('is-open');
                                toggleBtn.style.display = 'flex';
                                msgEl.style.display = 'none';
                            }, 1500);
                        } else {
                            showMsg('Gagal menambahkan rak.', false);
                        }
                    })
                    .catch(function(err) {
                        var errMsg = 'Gagal menambahkan rak.';
                        if (err && err.errors) {
                            var msgs = [];
                            Object.keys(err.errors).forEach(function(k) {
                                msgs.push(err.errors[k].join(', '));
                            });
                            errMsg = msgs.join(' ');
                        } else if (err && err.message) {
                            errMsg = err.message;
                        }
                        showMsg(errMsg, false);
                    })
                    .finally(function() {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '💾 Simpan Rak';
                    });
                });

                // Enter key submits
                [nameInput, locInput].forEach(function(input) {
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') { e.preventDefault(); submitBtn.click(); }
                    });
                });

                function showMsg(text, isOk) {
                    msgEl.style.display = 'block';
                    msgEl.className = 'bb-add-rack-msg ' + (isOk ? 'bb-add-rack-msg--ok' : 'bb-add-rack-msg--err');
                    msgEl.textContent = text;
                }
            })();

            // ── Helpers ──
            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function getAssignedRackIds() {
                var ids = {};
                Object.keys(assignments).forEach(function(wid) {
                    assignments[wid].forEach(function(rid) { ids[rid] = true; });
                });
                return Object.keys(ids);
            }

            function getWaitersWithAssignments() {
                var result = [];
                Object.keys(assignments).forEach(function(wid) {
                    if (assignments[wid].length > 0) result.push(wid);
                });
                return result;
            }

            function findRackCardByRackId(rackId) {
                for (var i = 0; i < allRackCards.length; i++) {
                    if (allRackCards[i].getAttribute('data-rack-id') === rackId) return allRackCards[i];
                }
                return null;
            }

            function findLaneByWaiterId(waiterId) {
                for (var i = 0; i < allLanes.length; i++) {
                    if (allLanes[i].getAttribute('data-waiter-id') === waiterId) return allLanes[i];
                }
                return null;
            }

            function getVisibleLanes() {
                return allLanes.filter(function(lane) {
                    return lane.style.display !== 'none';
                });
            }

            function assignRackToWaiter(rackId, waiterId) {
                // Remove from any other waiter first
                removeRackFromAllWaiters(rackId);
                if (!assignments[waiterId]) assignments[waiterId] = [];
                if (assignments[waiterId].indexOf(rackId) === -1) {
                    assignments[waiterId].push(rackId);
                }
            }

            function removeRackFromAllWaiters(rackId) {
                Object.keys(assignments).forEach(function(wid) {
                    var idx = assignments[wid].indexOf(rackId);
                    if (idx !== -1) assignments[wid].splice(idx, 1);
                });
            }

            function removeRackFromWaiter(rackId, waiterId) {
                if (!assignments[waiterId]) return;
                var idx = assignments[waiterId].indexOf(rackId);
                if (idx !== -1) assignments[waiterId].splice(idx, 1);
            }

            // ── Render ──
            function renderBoard() {
                var assignedIds = getAssignedRackIds();

                // Show/hide pool cards
                var poolVisible = 0;
                allRackCards.forEach(function(card) {
                    var rid = card.getAttribute('data-rack-id');
                    var isAssigned = assignedIds.indexOf(rid) !== -1;
                    card.style.display = isAssigned ? 'none' : 'flex';
                    if (!isAssigned) poolVisible++;
                });

                // Pool empty state
                var emptyEl = poolList.querySelector('.bb-rack-pool-empty');
                if (poolVisible === 0 && allRackCards.length > 0) {
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.className = 'bb-rack-pool-empty';
                        emptyEl.textContent = 'Semua rak sudah ditugaskan ✓';
                        poolList.appendChild(emptyEl);
                    }
                    emptyEl.style.display = 'block';
                } else if (emptyEl) {
                    emptyEl.style.display = 'none';
                }

                if (poolCountEl) poolCountEl.textContent = poolVisible;

                // Render chips in lanes
                allLanes.forEach(function(lane) {
                    var wid = lane.getAttribute('data-waiter-id');
                    var dropZone = lane.querySelector('.bb-waiter-lane-drop');
                    var countBadge = lane.querySelector('.bb-waiter-lane-count');
                    var rackIds = assignments[wid] || [];

                    // Clear drop zone
                    dropZone.innerHTML = '';

                    var mode = waiterModes[wid] || 'rolling';
                    if (rackIds.length === 0) {
                        var ph = document.createElement('div');
                        ph.className = 'bb-waiter-lane-placeholder';
                        ph.textContent = 'Seret rak ke sini';
                        dropZone.appendChild(ph);
                    } else {
                        rackIds.forEach(function(rid) {
                            var card = findRackCardByRackId(rid);
                            var rackName = card ? card.getAttribute('data-rack-name') : rid;
                            var chip = document.createElement('div');
                            var chipModeClass = mode === 'fixed' ? ' is-fixed' : ' is-rolling';
                            chip.className = 'bb-rack-chip' + chipModeClass;
                            chip.setAttribute('draggable', 'true');
                            chip.setAttribute('data-rack-id', rid);
                            var modeIcon = mode === 'fixed' ? '🔒' : '🔄';
                            chip.innerHTML = '📦 ' + escapeHtml(rackName) + ' <span style="font-size:12px;opacity:0.7;">' + modeIcon + '</span>' +
                                '<button type="button" class="bb-rack-chip-remove" data-rack-id="' + escapeHtml(rid) + '" data-waiter-id="' + escapeHtml(wid) + '">×</button>';
                            dropZone.appendChild(chip);

                            // Chip drag
                            chip.addEventListener('dragstart', function(e) {
                                e.dataTransfer.setData('text/plain', rid);
                                e.dataTransfer.setData('application/x-bb-source', 'lane:' + wid);
                                chip.classList.add('is-dragging');
                            });
                            chip.addEventListener('dragend', function() {
                                chip.classList.remove('is-dragging');
                            });

                            // Remove button
                            var removeBtn = chip.querySelector('.bb-rack-chip-remove');
                            removeBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                removeRackFromWaiter(rid, wid);
                                renderBoard();
                                syncHiddenFields();
                            });
                        });
                    }

                    if (countBadge) {
                        countBadge.textContent = rackIds.length + ' rak';
                        countBadge.setAttribute('data-count', rackIds.length);
                    }
                });

                // Update summary
                updateSummary();
            }

            function updateSummary() {
                var assignedIds = getAssignedRackIds();
                var waitersWithRacks = getWaitersWithAssignments();
                var totalRacks = allRackCards.length;

                if (assignedIds.length === 0) {
                    summaryEl.className = 'bb-summary';
                    summaryEl.innerHTML = '';
                    return;
                }

                // Count fixed vs rolling racks
                var fixedRackCount = 0;
                var rollingRackCount = 0;
                var fixedWaiterCount = 0;
                var rollingWaiterCount = 0;
                waitersWithRacks.forEach(function(wid) {
                    var mode = waiterModes[wid] || 'rolling';
                    var rackCount = (assignments[wid] || []).length;
                    if (mode === 'fixed') {
                        fixedRackCount += rackCount;
                        fixedWaiterCount++;
                    } else {
                        rollingRackCount += rackCount;
                        rollingWaiterCount++;
                    }
                });

                var unassignedCount = totalRacks - assignedIds.length;
                var isWarn = unassignedCount > 0;
                var isHybrid = fixedRackCount > 0 && rollingRackCount > 0;

                summaryEl.className = 'bb-summary is-visible' + (isWarn ? ' bb-summary-warn' : '');

                var mainLine = '✅ <b>' + assignedIds.length + '</b> rak ditugaskan ke <b>' + waitersWithRacks.length + '</b> waiter.';
                if (unassignedCount > 0) {
                    mainLine += ' ⚠️ <b>' + unassignedCount + '</b> rak belum ditugaskan.';
                } else {
                    mainLine += ' Semua rak sudah ditugaskan.';
                }

                var detailLine = '';
                if (isHybrid) {
                    detailLine = '<div class="bb-summary-detail">🔒 <b>' + fixedRackCount + '</b> rak tetap (' + fixedWaiterCount + ' waiter) · 🔄 <b>' + rollingRackCount + '</b> rak rolling (' + rollingWaiterCount + ' waiter) — rotasi harian otomatis</div>';
                } else if (fixedRackCount > 0) {
                    detailLine = '<div class="bb-summary-detail">🔒 Semua rak ditugaskan <b>tetap</b> (tidak ada rotasi)</div>';
                } else if (rollingRackCount > 0) {
                    detailLine = '<div class="bb-summary-detail">🔄 Semua rak ikut <b>rotasi harian</b> otomatis</div>';
                }

                summaryEl.innerHTML = mainLine + detailLine;
            }

            // ── Sync Hidden Fields ──
            function syncHiddenFields() {
                var assignedRackIds = getAssignedRackIds();
                var waitersWithRacks = getWaitersWithAssignments();

                // rack_ids[]
                var hiddenRackContainer = document.getElementById('bbHiddenRackIds');
                hiddenRackContainer.innerHTML = '';
                assignedRackIds.forEach(function(rid) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'rack_ids[]';
                    inp.value = rid;
                    hiddenRackContainer.appendChild(inp);
                });

                // rack_target_scope
                var scopeEl = document.getElementById('rack_target_scope');
                scopeEl.value = (assignedRackIds.length > 0 && assignedRackIds.length === allRackCards.length) ? 'all' : 'single';

                var assignTypeEl = document.getElementById('assignment_type');
                var assignWaiterIdEl = document.getElementById('assigned_waiter_id');
                var assignWaiterRoleEl = document.getElementById('assigned_waiter_role');
                var roleModeEl = document.getElementById('role_assignment_mode');
                var hiddenWaiterContainer = document.getElementById('bbHiddenSelectedWaiterIds');
                var fixedRackEl = document.getElementById('fixed_rack_assignments');

                hiddenWaiterContainer.innerHTML = '';

                // Build fixed rack assignments map: { rackId: waiterId }
                var fixedMap = {};
                var hasFixed = false;
                var hasRolling = false;
                var rollingWaiterIds = [];

                waitersWithRacks.forEach(function(wid) {
                    var mode = waiterModes[wid] || 'rolling';
                    var rackIds = assignments[wid] || [];
                    if (mode === 'fixed' && rackIds.length > 0) {
                        hasFixed = true;
                        rackIds.forEach(function(rid) {
                            fixedMap[rid] = wid;
                        });
                    } else if (mode === 'rolling' && rackIds.length > 0) {
                        hasRolling = true;
                        rollingWaiterIds.push(wid);
                    }
                });

                // Set fixed_rack_assignments JSON
                fixedRackEl.value = hasFixed ? JSON.stringify(fixedMap) : '';

                if (waitersWithRacks.length === 0) {
                    // No waiters assigned — default to role/rolling
                    assignTypeEl.value = 'role';
                    roleModeEl.value = 'rolling';
                    assignWaiterIdEl.value = '';
                } else if (hasFixed && !hasRolling) {
                    // All fixed, no rolling — still use role mode so controller processes per-rack
                    assignTypeEl.value = 'role';
                    roleModeEl.value = 'rolling';
                    assignWaiterIdEl.value = '';
                    // Include all assigned waiters as selected
                    waitersWithRacks.forEach(function(wid) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'selected_waiter_ids[]';
                        inp.value = wid;
                        hiddenWaiterContainer.appendChild(inp);
                    });
                    var firstLane = findLaneByWaiterId(waitersWithRacks[0]);
                    if (firstLane) {
                        assignWaiterRoleEl.value = firstLane.getAttribute('data-waiter-role') || 'pelayan';
                    }
                } else if (hasFixed && hasRolling) {
                    // Hybrid mode: fixed + rolling
                    assignTypeEl.value = 'role';
                    roleModeEl.value = 'rolling';
                    assignWaiterIdEl.value = '';
                    // Include rolling waiters as selected (fixed handled via fixed_rack_assignments)
                    rollingWaiterIds.forEach(function(wid) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'selected_waiter_ids[]';
                        inp.value = wid;
                        hiddenWaiterContainer.appendChild(inp);
                    });
                    var firstRollingLane = findLaneByWaiterId(rollingWaiterIds[0]);
                    if (firstRollingLane) {
                        assignWaiterRoleEl.value = firstRollingLane.getAttribute('data-waiter-role') || 'pelayan';
                    }
                } else if (waitersWithRacks.length === 1) {
                    // Single waiter (rolling)
                    assignTypeEl.value = 'single';
                    assignWaiterIdEl.value = waitersWithRacks[0];
                    var singleLane = findLaneByWaiterId(waitersWithRacks[0]);
                    if (singleLane) {
                        assignWaiterRoleEl.value = singleLane.getAttribute('data-waiter-role') || 'pelayan';
                    }
                } else {
                    // Multiple waiters, all rolling
                    assignTypeEl.value = 'role';
                    roleModeEl.value = 'rolling';
                    waitersWithRacks.forEach(function(wid) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'selected_waiter_ids[]';
                        inp.value = wid;
                        hiddenWaiterContainer.appendChild(inp);
                    });
                    var firstLane = findLaneByWaiterId(waitersWithRacks[0]);
                    if (firstLane) {
                        assignWaiterRoleEl.value = firstLane.getAttribute('data-waiter-role') || 'pelayan';
                    }
                }
            }

            // ── HTML5 Drag & Drop: Rack Cards ──
            function attachDragToCard(card) {
                card.addEventListener('dragstart', function(e) {
                    var rid = card.getAttribute('data-rack-id');
                    e.dataTransfer.setData('text/plain', rid);
                    e.dataTransfer.setData('application/x-bb-source', 'pool');
                    card.classList.add('is-dragging');
                });
                card.addEventListener('dragend', function() {
                    card.classList.remove('is-dragging');
                });
            }
            allRackCards.forEach(function(card) {
                attachDragToCard(card);
            });

            // ── Drop on Waiter Lanes ──
            allLanes.forEach(function(lane) {
                lane.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    lane.classList.add('is-drag-over');
                });
                lane.addEventListener('dragleave', function(e) {
                    if (!lane.contains(e.relatedTarget)) {
                        lane.classList.remove('is-drag-over');
                    }
                });
                lane.addEventListener('drop', function(e) {
                    e.preventDefault();
                    lane.classList.remove('is-drag-over');
                    var rackId = e.dataTransfer.getData('text/plain');
                    var waiterId = lane.getAttribute('data-waiter-id');
                    if (rackId && waiterId) {
                        assignRackToWaiter(rackId, waiterId);
                        renderBoard();
                        syncHiddenFields();
                    }
                });
            });

            // ── Drop back to Pool ──
            pool.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                pool.classList.add('is-drag-over');
            });
            pool.addEventListener('dragleave', function(e) {
                if (!pool.contains(e.relatedTarget)) {
                    pool.classList.remove('is-drag-over');
                }
            });
            pool.addEventListener('drop', function(e) {
                e.preventDefault();
                pool.classList.remove('is-drag-over');
                var rackId = e.dataTransfer.getData('text/plain');
                if (rackId) {
                    removeRackFromAllWaiters(rackId);
                    renderBoard();
                    syncHiddenFields();
                }
            });

            // ── Touch Support ──
            var touchState = { active: false, clone: null, rackId: null, sourceType: null, sourceWaiterId: null, startX: 0, startY: 0 };

            function handleTouchStart(e, rackId, sourceType, sourceWaiterId) {
                var touch = e.touches[0];
                touchState.startX = touch.clientX;
                touchState.startY = touch.clientY;
                touchState.rackId = rackId;
                touchState.sourceType = sourceType;
                touchState.sourceWaiterId = sourceWaiterId || null;
                touchState.active = false;
            }

            function handleTouchMove(e) {
                if (!touchState.rackId) return;
                var touch = e.touches[0];
                var dx = Math.abs(touch.clientX - touchState.startX);
                var dy = Math.abs(touch.clientY - touchState.startY);

                if (!touchState.active && (dx > 8 || dy > 8)) {
                    touchState.active = true;
                    // Create clone
                    var card = findRackCardByRackId(touchState.rackId);
                    var rackName = card ? card.getAttribute('data-rack-name') : touchState.rackId;
                    var clone = document.createElement('div');
                    clone.className = 'bb-rack-card';
                    clone.style.position = 'fixed';
                    clone.style.zIndex = '9999';
                    clone.style.pointerEvents = 'none';
                    clone.style.opacity = '0.85';
                    clone.style.width = '200px';
                    clone.style.boxShadow = '0 4px 16px rgba(0,0,0,0.2)';
                    clone.innerHTML = '<div class="bb-rack-card-icon">📦</div><div class="bb-rack-card-info"><div class="bb-rack-card-name">' + escapeHtml(rackName) + '</div></div>';
                    document.body.appendChild(clone);
                    touchState.clone = clone;
                }

                if (touchState.active && touchState.clone) {
                    e.preventDefault();
                    touchState.clone.style.left = (touch.clientX - 100) + 'px';
                    touchState.clone.style.top = (touch.clientY - 20) + 'px';

                    // Highlight drop targets
                    var el = document.elementFromPoint(touch.clientX, touch.clientY);
                    allLanes.forEach(function(l) { l.classList.remove('is-drag-over'); });
                    pool.classList.remove('is-drag-over');
                    if (el) {
                        var targetLane = el.closest('.bb-waiter-lane');
                        if (targetLane) targetLane.classList.add('is-drag-over');
                        var targetPool = el.closest('.bb-rack-pool');
                        if (targetPool) targetPool.classList.add('is-drag-over');
                    }
                }
            }

            function handleTouchEnd(e) {
                if (!touchState.rackId) return;

                allLanes.forEach(function(l) { l.classList.remove('is-drag-over'); });
                pool.classList.remove('is-drag-over');

                if (touchState.clone) {
                    document.body.removeChild(touchState.clone);
                    touchState.clone = null;
                }

                if (touchState.active) {
                    var touch = e.changedTouches[0];
                    var el = document.elementFromPoint(touch.clientX, touch.clientY);
                    if (el) {
                        var targetLane = el.closest('.bb-waiter-lane');
                        var targetPool = el.closest('.bb-rack-pool');
                        if (targetLane) {
                            var waiterId = targetLane.getAttribute('data-waiter-id');
                            assignRackToWaiter(touchState.rackId, waiterId);
                            renderBoard();
                            syncHiddenFields();
                        } else if (targetPool) {
                            removeRackFromAllWaiters(touchState.rackId);
                            renderBoard();
                            syncHiddenFields();
                        }
                    }
                }

                touchState.active = false;
                touchState.rackId = null;
                touchState.sourceType = null;
                touchState.sourceWaiterId = null;
            }

            // Attach touch to pool cards
            allRackCards.forEach(function(card) {
                card.addEventListener('touchstart', function(e) {
                    handleTouchStart(e, card.getAttribute('data-rack-id'), 'pool', null);
                }, { passive: true });
            });

            document.addEventListener('touchmove', handleTouchMove, { passive: false });
            document.addEventListener('touchend', handleTouchEnd);

            // Re-attach touch to chips after render (delegated via lanesContainer)
            lanesContainer.addEventListener('touchstart', function(e) {
                var chip = e.target.closest('.bb-rack-chip');
                if (!chip) return;
                var rid = chip.getAttribute('data-rack-id');
                var lane = chip.closest('.bb-waiter-lane');
                var wid = lane ? lane.getAttribute('data-waiter-id') : null;
                handleTouchStart(e, rid, 'lane', wid);
            }, { passive: true });

            // ── Quick Assign: All ──
            var btnAll = document.getElementById('bbQuickAll');
            if (btnAll) {
                btnAll.addEventListener('click', function() {
                    var visibleLanes = getVisibleLanes();
                    if (visibleLanes.length === 0) return;
                    // Every rack to every visible waiter
                    allRackCards.forEach(function(card) {
                        var rid = card.getAttribute('data-rack-id');
                        visibleLanes.forEach(function(lane) {
                            var wid = lane.getAttribute('data-waiter-id');
                            if (!assignments[wid]) assignments[wid] = [];
                            if (assignments[wid].indexOf(rid) === -1) {
                                assignments[wid].push(rid);
                            }
                        });
                    });
                    // For "all" quick assign, set assignment_type to 'all'
                    var assignTypeEl = document.getElementById('assignment_type');
                    if (assignTypeEl) assignTypeEl.value = 'all';
                    renderBoard();
                    syncHiddenFields();
                });
            }

            // ── Quick Assign: Rolling ──
            var btnRolling = document.getElementById('bbQuickRolling');
            if (btnRolling) {
                btnRolling.addEventListener('click', function() {
                    var visibleLanes = getVisibleLanes();
                    if (visibleLanes.length === 0) return;

                    // Collect fixed racks (preserve them)
                    var fixedRacks = {}; // rackId → waiterId
                    Object.keys(assignments).forEach(function(wid) {
                        if (waiterModes[wid] === 'fixed') {
                            (assignments[wid] || []).forEach(function(rid) {
                                fixedRacks[rid] = wid;
                            });
                        }
                    });

                    // Reset only rolling waiter assignments
                    Object.keys(assignments).forEach(function(wid) {
                        if (waiterModes[wid] !== 'fixed') {
                            assignments[wid] = [];
                        }
                    });

                    // Get rolling-eligible lanes (visible + rolling mode)
                    var rollingLanes = visibleLanes.filter(function(lane) {
                        var wid = lane.getAttribute('data-waiter-id');
                        return waiterModes[wid] !== 'fixed';
                    });

                    if (rollingLanes.length === 0) {
                        alert('Tidak ada waiter dengan mode Rolling. Ubah minimal 1 waiter ke mode Rolling.');
                        renderBoard();
                        syncHiddenFields();
                        return;
                    }

                    // Round-robin only non-fixed racks
                    var laneIndex = 0;
                    allRackCards.forEach(function(card) {
                        var rid = card.getAttribute('data-rack-id');
                        // Skip racks already fixed
                        if (fixedRacks[rid]) return;
                        var wid = rollingLanes[laneIndex % rollingLanes.length].getAttribute('data-waiter-id');
                        if (!assignments[wid]) assignments[wid] = [];
                        assignments[wid].push(rid);
                        laneIndex++;
                    });
                    renderBoard();
                    syncHiddenFields();
                });
            }

            // ── Quick Reset ──
            var btnReset = document.getElementById('bbQuickReset');
            if (btnReset) {
                btnReset.addEventListener('click', function() {
                    Object.keys(assignments).forEach(function(wid) { assignments[wid] = []; });
                    // Reset all waiter modes to rolling
                    Object.keys(waiterModes).forEach(function(wid) { waiterModes[wid] = 'rolling'; });
                    // Reset mode toggle buttons
                    allLanes.forEach(function(lane) {
                        var btn = lane.querySelector('.js-bb-mode-toggle');
                        if (btn) {
                            btn.className = 'bb-waiter-lane-mode-btn is-rolling js-bb-mode-toggle';
                            btn.innerHTML = '🔄 Rolling';
                        }
                    });
                    renderBoard();
                    syncHiddenFields();
                });
            }

            // ── Role Filter ──
            if (roleFilter) {
                roleFilter.addEventListener('change', function() {
                    var filterVal = roleFilter.value;
                    allLanes.forEach(function(lane) {
                        if (filterVal === 'all') {
                            lane.style.display = '';
                        } else {
                            var role = lane.getAttribute('data-waiter-role');
                            lane.style.display = (role === filterVal) ? '' : 'none';
                        }
                    });
                });
            }

            // ── Form Submit Guard ──
            if (form) {
                form.addEventListener('submit', function(e) {
                    var assignedRackIds = getAssignedRackIds();
                    if (assignedRackIds.length === 0) {
                        e.preventDefault();
                        alert('Harap tugaskan minimal 1 rak ke waiter sebelum submit.');
                        return false;
                    }
                });
            }

            // Initial render
            renderBoard();
            syncHiddenFields();
        })();
    </script>
@endsection
