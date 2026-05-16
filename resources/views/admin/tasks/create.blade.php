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
        .bb-board { display: grid; grid-template-columns: 280px 1fr; gap: 16px; min-height: 320px; align-items: start; }
        .bb-rack-pool { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 12px; min-height: 280px; max-height: 520px; overflow-y: auto; position: sticky; top: 16px; transition: border-color 0.2s, background 0.2s; }
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
        .bb-waiter-avatar--backup { background: #9333ea; }
        .bb-waiter-avatar--supervisor { background: #2563eb; }
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

        /* General Task Board Builder styles (GT) */
        .gt-wrapper { margin-bottom: 24px; }
        .gt-info-banner { padding: 14px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; margin-bottom: 20px; }
        .gt-info-banner-title { font-weight: 700; margin-bottom: 6px; }
        .gt-info-banner-text { font-size: 13px; line-height: 1.5; }
        .gt-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
        .gt-toolbar-label { display: flex; align-items: center; font-weight: 700; font-size: 13px; color: #475569; margin-right: 4px; white-space: nowrap; }
        .gt-toolbar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .gt-toolbar-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .gt-toolbar-btn.is-active { background: #eef2ff; border-color: #818cf8; color: #4338ca; }
        .gt-toolbar-btn--reset { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
        .gt-toolbar-btn--reset:hover { background: #fee2e2; border-color: #f87171; }
        .gt-toolbar-select { padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; color: #334155; background: #fff; cursor: pointer; }
        .gt-board { display: grid; grid-template-columns: 320px 1fr; gap: 16px; min-height: 320px; }
        @media (max-width: 767px) {
            .gt-board { grid-template-columns: 1fr; }
        }
        .gt-task-pool { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 12px; min-height: 280px; }
        .gt-task-pool-header { font-weight: 700; font-size: 14px; color: #475569; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        .gt-task-pool-count { font-size: 12px; font-weight: 600; color: #94a3b8; }
        .gt-task-pool-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
        .gt-task-pool-empty { text-align: center; padding: 20px 10px; color: #94a3b8; font-size: 13px; }
        .gt-task-card { display: flex; flex-direction: column; gap: 6px; padding: 10px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; cursor: grab; transition: box-shadow 0.2s, border-color 0.2s, opacity 0.2s; user-select: none; -webkit-user-select: none; position: relative; }
        .gt-task-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-color: #94a3b8; }
        .gt-task-card:active, .gt-task-card.is-dragging { cursor: grabbing; opacity: 0.5; }
        .gt-task-card-header { display: flex; align-items: center; gap: 8px; }
        .gt-task-card-icon { font-size: 16px; flex-shrink: 0; }
        .gt-task-card-title { font-weight: 700; font-size: 13px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
        .gt-task-card-remove { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border-radius: 50%; border: none; background: #fee2e2; color: #ef4444; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.1); opacity: 0; transition: opacity 0.2s; }
        .gt-task-card:hover .gt-task-card-remove { opacity: 1; }
        .gt-task-card-remove:hover { background: #fca5a5; color: #b91c1c; }
        .gt-task-card-meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #64748b; }
        .gt-task-card-cat { display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; background: #f1f5f9; border-radius: 4px; }
        .gt-task-card-cat-dot { width: 8px; height: 8px; border-radius: 50%; }
        .gt-task-card-proof { display: inline-flex; align-items: center; gap: 4px; color: #f59e0b; font-weight: 600; }
        .gt-task-add-form { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .gt-task-add-form-group { margin-bottom: 8px; }
        .gt-task-add-form input[type="text"], .gt-task-add-form select, .gt-task-add-form textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
        .gt-task-add-form input[type="text"]:focus, .gt-task-add-form select:focus, .gt-task-add-form textarea:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.2); }
        .gt-task-add-form textarea { resize: vertical; min-height: 40px; }
        .gt-task-add-form-checkbox { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; cursor: pointer; }
        .gt-task-add-btn { width: 100%; padding: 8px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: background 0.2s; margin-top: 4px; }
        .gt-task-add-btn:hover { background: #4338ca; }
        .gt-waiter-lanes { display: flex; flex-direction: column; gap: 10px; max-height: 560px; overflow-y: auto; padding-right: 4px; }
        .gt-waiter-lane { background: #fff; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; }
        .gt-waiter-lane.is-drag-over { border-color: #818cf8; background: #f5f3ff; box-shadow: 0 0 0 3px rgba(129,140,248,0.15); }
        .gt-waiter-lane-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .gt-waiter-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0; }
        .gt-waiter-avatar--pelayan { background: #6366f1; }
        .gt-waiter-avatar--kasir { background: #f59e0b; }
        .gt-waiter-avatar--backup { background: #9333ea; }
        .gt-waiter-avatar--supervisor { background: #2563eb; }
        .gt-waiter-lane-name { font-weight: 700; font-size: 14px; color: #0f172a; }
        .gt-waiter-lane-role { font-size: 12px; color: #64748b; text-transform: capitalize; }
        .gt-waiter-lane-count { margin-left: auto; font-size: 12px; font-weight: 700; color: #6366f1; background: #eef2ff; padding: 2px 10px; border-radius: 20px; }
        .gt-waiter-lane-drop { min-height: 50px; border: 2px dashed #e2e8f0; border-radius: 8px; padding: 8px; display: flex; flex-direction: column; gap: 6px; transition: border-color 0.2s; }
        .gt-waiter-lane.is-drag-over .gt-waiter-lane-drop { border-color: #a5b4fc; }
        .gt-waiter-lane-placeholder { width: 100%; text-align: center; padding: 12px 8px; color: #cbd5e1; font-size: 12px; pointer-events: none; }
        .gt-summary { margin-top: 14px; padding: 12px 16px; border-radius: 10px; background: #f0fdf4; border: 1px solid #86efac; font-size: 13px; color: #166534; display: none; }
        .gt-summary.is-visible { display: block; }
        .gt-summary-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .gt-summary-detail { margin-top: 6px; font-size: 12px; opacity: 0.85; }
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
                    $bbBackupCount = collect($bbWaiters)->filter(fn($w) => strtolower($w['waiter_role'] ?? 'pelayan') === 'backup')->count();
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
                            <option value="backup">Backup / Flexible ({{ $bbBackupCount }})</option>
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
                                    @php
                                        $rId = $rack['id'] ?? '';
                                        $rStatus = ($rackCheckStatus[$rId] ?? null);
                                        $rDone = $rStatus['done'] ?? 0;
                                        $rTotal = $rStatus['total'] ?? 0;
                                    @endphp
                                    <div class="bb-rack-card"
                                         draggable="true"
                                         data-rack-id="{{ $rId }}"
                                         data-rack-name="{{ $rack['name'] ?? '-' }}"
                                         data-rack-location="{{ $rack['location'] ?? '-' }}"
                                         data-rack-barcode="{{ $rack['barcode_value'] ?? '-' }}">
                                        <div class="bb-rack-card-icon">📦</div>
                                        <div class="bb-rack-card-info">
                                            <div class="bb-rack-card-name">{{ $rack['name'] ?? '-' }}</div>
                                            <div class="bb-rack-card-loc">📍 {{ $rack['location'] ?? '-' }}</div>
                                        </div>
                                        @if($rTotal > 0)
                                            <div style="font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 10px; white-space: nowrap; {{ $rDone >= $rTotal ? 'background: #d1fae5; color: #065f46;' : 'background: #fef3c7; color: #92400e;' }}">
                                                {{ $rDone }}/{{ $rTotal }} ✓
                                            </div>
                                        @endif
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
                <input type="hidden" id="rack_recurrence_map" name="rack_recurrence_map" value="">
                <div id="bbHiddenSelectedWaiterIds"></div>
                <input type="hidden" id="fixed_rack_assignments" name="fixed_rack_assignments" value="">

                {{-- Photo Proof --}}
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 600; color: #333; margin-bottom: 8px;">📷 Bukti Foto</div>
                    <select id="requires_photo_proof" name="photo_mode" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; width: 100%; max-width: 300px;">
                        <option value="none" {{ old('photo_mode', 'none') === 'none' ? 'selected' : '' }}>Tidak wajib</option>
                        <option value="after" {{ old('photo_mode') === 'after' ? 'selected' : '' }}>Wajib foto sesudah</option>
                        <option value="both" {{ old('photo_mode') === 'both' ? 'selected' : '' }}>Wajib foto sebelum & sesudah</option>
                    </select>
                    <input type="hidden" name="requires_photo_proof" id="requires_photo_proof_hidden" value="{{ old('requires_photo_proof', '0') }}">
                    <input type="hidden" name="requires_photo_before" id="requires_photo_before_hidden" value="{{ old('requires_photo_before', '0') }}">
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        "Sebelum & sesudah" = waiter harus foto kondisi rak sebelum mulai cek, lalu foto lagi setelah selesai.
                    </div>
                </div>
                <script>
                    (function() {
                        var sel = document.getElementById('requires_photo_proof');
                        var hiddenProof = document.getElementById('requires_photo_proof_hidden');
                        var hiddenBefore = document.getElementById('requires_photo_before_hidden');
                        function syncPhotoMode() {
                            hiddenProof.value = sel.value !== 'none' ? '1' : '0';
                            hiddenBefore.value = sel.value === 'both' ? '1' : '0';
                        }
                        sel.addEventListener('change', syncPhotoMode);
                        syncPhotoMode();
                    })();
                </script>

                {{-- Schedule Section (rack_check) --}}
                <div style="margin-bottom: 20px; padding: 14px; border: 1px solid #fdba74; border-radius: 10px; background: #fff7ed;">
                    <div style="font-weight: 700; color: #9a3412; margin-bottom: 6px;">⏱️ Jadwal Cek Rak</div>
                    <div style="font-size: 13px; color: #7c2d12; line-height: 1.5;">
                        Supervisor dapat mengubah pola jadwal, jam mulai, dan batas waktu penyelesaian sesuai kebutuhan operasional.
                    </div>
                </div>
                <input type="hidden" id="is_recurring" name="is_recurring" value="1">

                <div id="recurring-time-wrapper" style="margin-bottom: 25px; display: block;">
                    <div id="recurrence-pattern-wrapper" data-rack-check-hide="1">
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
                    </div>{{-- /#recurrence-pattern-wrapper --}}

                    <div id="rack-check-recurrence-note" data-rack-check-show="1" style="display:none; margin-bottom: 14px; padding: 12px 14px; border: 1px dashed #818cf8; border-radius: 10px; background: #eef2ff; color: #3730a3; font-size: 13px; line-height: 1.5;">
                        <strong>📅 Pola Perulangan per Rak</strong><br>
                        Pola perulangan untuk tugas cek rak diatur per rak via dropdown di kartu rak (📅 Harian / 🗓️ Mingguan / 🔁 Setiap N hari). Default = Harian. Anda hanya perlu mengatur jam mulai &amp; deadline di bawah.
                    </div>

                    {{-- Schedule Mode --}}
                    <div style="margin-bottom: 14px;">
                        <label for="schedule_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Mode Jadwal
                        </label>
                        <select id="schedule_mode" name="schedule_mode" onchange="toggleScheduleMode()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                            <option value="fixed" {{ old('schedule_mode', 'fixed') === 'fixed' ? 'selected' : '' }}>⏰ Jam Tetap (Fixed)</option>
                            <option value="shift_relative" {{ old('schedule_mode') === 'shift_relative' ? 'selected' : '' }}>🔄 Ikuti Shift Waiter</option>
                        </select>
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            "Ikuti Shift" = jam tugas & deadline otomatis menyesuaikan shift masing-masing waiter.
                        </div>
                    </div>

                    {{-- Fixed mode --}}
                    <div id="fixed-schedule-wrapper">
                        <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Jam Jadwal <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="time"
                            id="schedule_time"
                            name="schedule_time"
                            value="{{ old('schedule_time', '06:00') }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Format 24 jam, contoh: 06:00 atau 07:30.
                        </div>
                    </div>

                    <div id="fixed-deadline-wrapper" style="margin-top: 14px;">
                        <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Batas Waktu Penyelesaian (menit) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="time_limit_minutes"
                            name="time_limit_minutes"
                            min="0"
                            max="1440"
                            value="{{ old('time_limit_minutes', 900) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 900 berarti task harus selesai maksimal 15 jam setelah jam jadwal.
                        </div>
                    </div>

                    {{-- Shift-relative mode --}}
                    <div id="shift-offset-wrapper" style="margin-top: 14px; display: none;">
                        <label for="shift_offset_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Muncul Setelah Shift Mulai (menit)
                        </label>
                        <input type="number" id="shift_offset_minutes" name="shift_offset_minutes" min="0" max="480"
                            value="{{ old('shift_offset_minutes', 30) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            Contoh: 30 = tugas muncul 30 menit setelah shift dimulai.
                        </div>
                    </div>

                    <div id="shift-deadline-wrapper" style="margin-top: 14px; display: none;">
                        <label for="deadline_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Mode Deadline
                        </label>
                        <select id="deadline_mode" name="deadline_mode" onchange="toggleDeadlineMode()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                            <option value="fixed" {{ old('deadline_mode', 'fixed') === 'fixed' ? 'selected' : '' }}>Batas waktu tetap (menit dari muncul)</option>
                            <option value="before_shift_end" {{ old('deadline_mode') === 'before_shift_end' ? 'selected' : '' }}>Sebelum shift selesai</option>
                        </select>
                    </div>

                    <div id="deadline-before-end-wrapper" style="margin-top: 14px; display: none;">
                        <label for="deadline_before_end_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Deadline Sebelum Shift Selesai (menit)
                        </label>
                        <input type="number" id="deadline_before_end_minutes" name="deadline_before_end_minutes" min="0" max="480"
                            value="{{ old('deadline_before_end_minutes', 60) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            Contoh: 60 = deadline 1 jam sebelum shift berakhir.
                        </div>
                    </div>
                </div>
            @else
                {{-- GENERAL MODE: Board Builder UI --}}
                <div class="gt-wrapper">
                    <input type="hidden" name="task_type" id="task_type" value="general">
                    <input type="hidden" name="task_scope" value="general">
                    <input type="hidden" id="batch_tasks_json" name="batch_tasks_json" value="">

                    <div class="gt-info-banner">
                        <div class="gt-info-banner-title">🛠️ Mode Tugas Umum</div>
                        <div class="gt-info-banner-text">
                            Buat tugas pada kolom <b>Tugas</b> di sebelah kiri, lalu <b>seret (drag)</b> tugas tersebut ke nama waiter di sebelah kanan.<br>
                            Satu tugas bisa diseret ke lebih dari satu waiter. Gunakan tombol Quick Assign untuk membagi tugas dengan cepat.
                        </div>
                    </div>

                    <div class="gt-toolbar">
                        <div class="gt-toolbar-label">Quick Assign:</div>
                        <button type="button" class="gt-toolbar-btn js-gt-btn-assign-all">
                            ✨ Assign Semua
                        </button>
                        <button type="button" class="gt-toolbar-btn js-gt-btn-merata">
                            🎲 Rolling Merata
                        </button>
                        <div style="width: 1px; background: #cbd5e1; margin: 0 4px;"></div>
                        <div class="gt-toolbar-label">Filter:</div>
                        <select class="gt-toolbar-select js-gt-role-filter">
                            <option value="all">Semua Role</option>
                            <option value="pelayan">Pelayan Saja</option>
                            <option value="kasir">Kasir Saja</option>
                            <option value="backup">Backup / Flexible Saja</option>
                        </select>
                        <div style="margin-left: auto;">
                            <button type="button" class="gt-toolbar-btn gt-toolbar-btn--reset js-gt-btn-reset">
                                🗑️ Reset
                            </button>
                        </div>
                    </div>

                    <div class="gt-board">
                        {{-- LEFT COLUMN: Task Pool --}}
                        <div class="gt-task-pool js-gt-task-pool">
                            <div class="gt-task-pool-header">
                                <span>📝 Tugas</span>
                                <span class="gt-task-pool-count js-gt-pool-count">0 dibuat</span>
                            </div>
                            
                            <div class="gt-task-pool-list js-gt-pool-list">
                                <div class="gt-task-pool-empty js-gt-pool-empty">
                                    Belum ada tugas. Buat tugas baru di bawah.
                                </div>
                            </div>

                            <div class="gt-task-add-form">
                                <div class="gt-task-add-form-group">
                                    <input type="text" id="gt_new_title" placeholder="Judul tugas (wajib)...">
                                </div>
                                <div class="gt-task-add-form-group">
                                    <textarea id="gt_new_desc" rows="2" placeholder="Deskripsi tugas (opsional)..."></textarea>
                                </div>
                                <div class="gt-task-add-form-group">
                                    <select id="gt_new_category">
                                        <option value="">Pilih Kategori (opsional)</option>
                                        @foreach(($categories ?? []) as $cat)
                                            <option value="{{ $cat['id'] }}" data-color="{{ $cat['color'] }}">{{ $cat['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="gt-task-add-form-group" style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; color: #475569; font-weight: 600; white-space: nowrap;">📷 Foto:</label>
                                    <select id="gt_new_photo" style="padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px;">
                                        <option value="none">Tidak wajib</option>
                                        <option value="after">Wajib foto sesudah</option>
                                        <option value="both">Wajib sebelum & sesudah</option>
                                    </select>
                                </div>
                                <div class="gt-task-add-form-group" style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; color: #475569; font-weight: 600; white-space: nowrap;">🔄 Ulangi:</label>
                                    <input type="number" id="gt_new_repeat" min="1" max="10" value="1" style="width: 60px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; text-align: center;">
                                    <span style="font-size: 11px; color: #94a3b8;">kali per waiter</span>
                                </div>
                                <div class="gt-task-add-form-group" style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; color: #475569; font-weight: 600; white-space: nowrap;">⏰ Batas waktu:</label>
                                    <input type="time" id="gt_new_deadline" value="" style="width: 110px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                                    <span style="font-size: 11px; color: #94a3b8;">opsional</span>
                                </div>
                                <button type="button" class="gt-task-add-btn js-gt-btn-add">
                                    + Tambah Tugas
                                </button>
                            </div>
                        </div>

                        {{-- RIGHT COLUMN: Waiter Lanes --}}
                        <div class="gt-waiter-lanes">
                            @forelse(($waiters ?? []) as $waiter)
                                @php
                                    $waiterId = (string) ($waiter['id'] ?? '');
                                    $waiterRole = strtolower((string) ($waiter['waiter_role'] ?? 'pelayan'));
                                    $isWaiterOff = ($waiterDayOffMap[$waiterId] ?? false);
                                @endphp
                                <div class="gt-waiter-lane js-gt-waiter-lane" data-waiter-id="{{ $waiterId }}" data-waiter-role="{{ $waiterRole }}">
                                    <div class="gt-waiter-lane-header">
                                        <div class="gt-waiter-avatar gt-waiter-avatar--{{ $waiterRole }}">
                                            {{ strtoupper(substr($waiter['name'] ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="gt-waiter-lane-name">
                                                {{ $waiter['name'] ?? '-' }}
                                                @if($isWaiterOff)
                                                    <span style="color: #dc2626; font-size: 11px; font-weight: 600; margin-left: 4px;">LIBUR</span>
                                                @endif
                                            </div>
                                            <div class="gt-waiter-lane-role">{{ $waiterRole }}</div>
                                        </div>
                                        <div class="gt-waiter-lane-count js-gt-lane-count">0</div>
                                    </div>
                                    <div class="gt-waiter-lane-drop js-gt-lane-drop">
                                        <div class="gt-waiter-lane-placeholder js-gt-lane-placeholder">Seret tugas ke sini</div>
                                    </div>
                                </div>
                            @empty
                                <div style="text-align: center; padding: 20px; color: #64748b; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    Tidak ada data waiter aktif.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="gt-summary js-gt-summary">
                        <div style="font-weight: 700; margin-bottom: 4px;" class="js-gt-summary-title">Status Tugas</div>
                        <div class="js-gt-summary-text">0 tugas dibuat, 0 tugas di-assign.</div>
                        <div class="gt-summary-detail js-gt-summary-detail"></div>
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

                    {{-- Schedule Mode --}}
                    <div style="margin-bottom: 14px;">
                        <label for="schedule_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Mode Jadwal
                        </label>
                        <select id="schedule_mode" name="schedule_mode" onchange="toggleScheduleMode()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                            <option value="fixed" {{ old('schedule_mode', 'fixed') === 'fixed' ? 'selected' : '' }}>⏰ Jam Tetap (Fixed)</option>
                            <option value="shift_relative" {{ old('schedule_mode') === 'shift_relative' ? 'selected' : '' }}>🔄 Ikuti Shift Waiter</option>
                        </select>
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            "Ikuti Shift" = jam tugas & deadline otomatis menyesuaikan shift masing-masing waiter.
                        </div>
                    </div>

                    {{-- Fixed mode --}}
                    <div id="fixed-schedule-wrapper">
                        <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Jam Jadwal <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="time"
                            id="schedule_time"
                            name="schedule_time"
                            value="{{ old('schedule_time') }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Format 24 jam, contoh: 10:30 atau 16:45.
                        </div>
                    </div>

                    <div id="fixed-deadline-wrapper" style="margin-top: 14px;">
                        <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Batas Waktu Penyelesaian (menit) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="time_limit_minutes"
                            name="time_limit_minutes"
                            min="0"
                            max="1440"
                            value="{{ old('time_limit_minutes', 30) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 30 berarti task harus selesai maksimal 30 menit setelah jam jadwal.
                        </div>
                    </div>

                    {{-- Shift-relative mode --}}
                    <div id="shift-offset-wrapper" style="margin-top: 14px; display: none;">
                        <label for="shift_offset_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Muncul Setelah Shift Mulai (menit)
                        </label>
                        <input type="number" id="shift_offset_minutes" name="shift_offset_minutes" min="0" max="480"
                            value="{{ old('shift_offset_minutes', 30) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            Contoh: 30 = tugas muncul 30 menit setelah shift dimulai.
                        </div>
                    </div>

                    <div id="shift-deadline-wrapper" style="margin-top: 14px; display: none;">
                        <label for="deadline_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Mode Deadline
                        </label>
                        <select id="deadline_mode" name="deadline_mode" onchange="toggleDeadlineMode()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                            <option value="fixed" {{ old('deadline_mode', 'fixed') === 'fixed' ? 'selected' : '' }}>Batas waktu tetap (menit dari muncul)</option>
                            <option value="before_shift_end" {{ old('deadline_mode') === 'before_shift_end' ? 'selected' : '' }}>Sebelum shift selesai</option>
                        </select>
                    </div>

                    <div id="deadline-before-end-wrapper" style="margin-top: 14px; display: none;">
                        <label for="deadline_before_end_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Deadline Sebelum Shift Selesai (menit)
                        </label>
                        <input type="number" id="deadline_before_end_minutes" name="deadline_before_end_minutes" min="0" max="480"
                            value="{{ old('deadline_before_end_minutes', 60) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            Contoh: 60 = deadline 1 jam sebelum shift berakhir.
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
        {{-- General Mode Board Builder Logic --}}
        (function() {
            var taskTypeInput = document.getElementById('task_type');
            if (!taskTypeInput || taskTypeInput.value !== 'general') return;

            var taskPool = [];
            var assignments = {}; 
            var taskIdCounter = 0;

            var DOM = {
                title: document.getElementById('gt_new_title'),
                desc: document.getElementById('gt_new_desc'),
                cat: document.getElementById('gt_new_category'),
                photo: document.getElementById('gt_new_photo'),
                btnAdd: document.querySelector('.js-gt-btn-add'),
                poolList: document.querySelector('.js-gt-pool-list'),
                poolCount: document.querySelector('.js-gt-pool-count'),
                emptyState: document.querySelector('.js-gt-pool-empty'),
                lanes: document.querySelectorAll('.js-gt-waiter-lane'),
                roleFilter: document.querySelector('.js-gt-role-filter'),
                btnAssignAll: document.querySelector('.js-gt-btn-assign-all'),
                btnMerata: document.querySelector('.js-gt-btn-merata'),
                btnReset: document.querySelector('.js-gt-btn-reset'),
                summary: document.querySelector('.js-gt-summary'),
                summaryTitle: document.querySelector('.js-gt-summary-title'),
                summaryText: document.querySelector('.js-gt-summary-text'),
                summaryDetail: document.querySelector('.js-gt-summary-detail'),
                jsonField: document.getElementById('batch_tasks_json'),
                form: document.querySelector('form')
            };

            function updateSummary() {
                var totalTasks = taskPool.length;
                var assignedTasks = new Set();
                
                Object.keys(assignments).forEach(function(wid) {
                    assignments[wid].forEach(function(tid) {
                        assignedTasks.add(tid);
                    });
                });

                var assignedCount = assignedTasks.size;
                var isWarning = totalTasks > 0 && assignedCount < totalTasks;

                if (totalTasks === 0) {
                    DOM.summary.classList.remove('is-visible');
                    return;
                }

                DOM.summary.classList.add('is-visible');
                DOM.summaryText.textContent = totalTasks + " tugas dibuat, " + assignedCount + " sudah di-assign ke waiter.";
                
                if (isWarning) {
                    DOM.summary.classList.add('gt-summary-warn');
                    DOM.summaryTitle.textContent = "⚠️ Perhatian";
                    DOM.summaryDetail.innerHTML = "Ada <b>" + (totalTasks - assignedCount) + "</b> tugas yang belum diberikan ke siapapun.";
                } else {
                    DOM.summary.classList.remove('gt-summary-warn');
                    DOM.summaryTitle.textContent = "✅ Status Tugas";
                    DOM.summaryDetail.innerHTML = "Semua tugas sudah diberikan minimal ke 1 waiter.";
                }

                // Update lane counters
                DOM.lanes.forEach(function(lane) {
                    var wid = lane.getAttribute('data-waiter-id');
                    var count = (assignments[wid] || []).length;
                    lane.querySelector('.js-gt-lane-count').textContent = count;
                });
            }

            function syncJSON() {
                var payload = {
                    tasks: taskPool.map(function(t) {
                        return {
                            title: t.title,
                            description: t.description,
                            category_id: t.category_id,
                            category_name: t.category_name,
                            requires_photo_proof: t.requires_photo_proof,
                            requires_photo_before: t.requires_photo_before || false,
                            repeat_count: t.repeat_count || 1,
                            deadline_time: t.deadline_time || null
                        };
                    }),
                    assignments: {}
                };

                // Map assignments from task.id to task index in payload.tasks array
                Object.keys(assignments).forEach(function(wid) {
                    var assignedIndices = [];
                    assignments[wid].forEach(function(tid) {
                        var index = taskPool.findIndex(function(t) { return t.id === tid; });
                        if (index !== -1) assignedIndices.push(index);
                    });
                    if (assignedIndices.length > 0) {
                        payload.assignments[wid] = assignedIndices;
                    }
                });

                DOM.jsonField.value = JSON.stringify(payload);
            }

            function renderPool() {
                DOM.poolList.innerHTML = '';
                DOM.poolCount.textContent = taskPool.length + " dibuat";

                if (taskPool.length === 0) {
                    DOM.poolList.appendChild(DOM.emptyState);
                    return;
                }

                taskPool.forEach(function(task) {
                    var card = document.createElement('div');
                    card.className = 'gt-task-card';
                    card.setAttribute('draggable', 'true');
                    card.setAttribute('data-task-id', task.id);

                    var catHtml = '';
                    if (task.category_name) {
                        catHtml = '<div class="gt-task-card-cat">' +
                                  '<div class="gt-task-card-cat-dot" style="background: ' + (task.category_color || '#cbd5e1') + '"></div>' +
                                  task.category_name + '</div>';
                    }

                    var proofHtml = task.requires_photo_before ? '<div class="gt-task-card-proof" title="Wajib Foto Sebelum & Sesudah">📷📷</div>' : (task.requires_photo_proof ? '<div class="gt-task-card-proof" title="Wajib Foto Bukti">📷</div>' : '');
                    var repeatHtml = task.repeat_count > 1 ? '<div class="gt-task-card-proof" title="Ulangi ' + task.repeat_count + 'x" style="color: #6366f1;">🔄 ' + task.repeat_count + 'x</div>' : '';
                    var deadlineHtml = task.deadline_time ? '<div class="gt-task-card-proof" title="Batas waktu ' + task.deadline_time + '" style="color: #d97706;">⏰ ' + task.deadline_time + '</div>' : '';

                    card.innerHTML = 
                        '<button type="button" class="gt-task-card-remove" title="Hapus tugas">×</button>' +
                        '<div class="gt-task-card-header">' +
                            '<div class="gt-task-card-icon">📝</div>' +
                            '<div class="gt-task-card-title" title="' + task.title + '">' + task.title + '</div>' +
                        '</div>' +
                        '<div class="gt-task-card-meta">' + catHtml + proofHtml + repeatHtml + deadlineHtml + '</div>';

                    // Delete task
                    card.querySelector('.gt-task-card-remove').addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (confirm('Hapus tugas ini?')) {
                            taskPool = taskPool.filter(function(t) { return t.id !== task.id; });
                            Object.keys(assignments).forEach(function(wid) {
                                assignments[wid] = assignments[wid].filter(function(tid) { return tid !== task.id; });
                            });
                            renderPool();
                            renderLanes();
                            updateSummary();
                            syncJSON();
                        }
                    });

                    // Drag Start
                    card.addEventListener('dragstart', function(e) {
                        e.dataTransfer.setData('text/plain', task.id);
                        e.dataTransfer.setData('application/x-gt-source', 'pool');
                        card.classList.add('is-dragging');
                    });
                    card.addEventListener('dragend', function() {
                        card.classList.remove('is-dragging');
                    });

                    // Touch support
                    card.addEventListener('touchstart', function(e) {
                        handleTouchStart(e, task.id, 'pool', null);
                    }, { passive: true });

                    DOM.poolList.appendChild(card);
                });
            }

            function renderLanes() {
                DOM.lanes.forEach(function(lane) {
                    var wid = lane.getAttribute('data-waiter-id');
                    var dropZone = lane.querySelector('.js-gt-lane-drop');
                    var placeholder = dropZone.querySelector('.js-gt-lane-placeholder');
                    
                    // Clear existing chips
                    Array.from(dropZone.querySelectorAll('.gt-task-chip')).forEach(function(el) { el.remove(); });

                    var myTasks = assignments[wid] || [];
                    if (myTasks.length === 0) {
                        if (placeholder) placeholder.style.display = 'block';
                    } else {
                        if (placeholder) placeholder.style.display = 'none';
                        
                        myTasks.forEach(function(tid) {
                            var task = taskPool.find(function(t) { return t.id === tid; });
                            if (!task) return;

                            var chip = document.createElement('div');
                            chip.className = 'gt-task-chip';
                            chip.setAttribute('draggable', 'true');
                            chip.setAttribute('data-task-id', task.id);
                            chip.style.cssText = 'display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: grab;';

                            var chipRepeat = task.repeat_count > 1 ? ' <span style="color:#6366f1; font-size:10px;">🔄' + task.repeat_count + 'x</span>' : '';
                            chip.innerHTML = 
                                '<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">' + task.title + chipRepeat + '</span>' +
                                '<button type="button" class="gt-task-chip-remove" style="border: none; background: #e2e8f0; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; font-size: 10px;">×</button>';

                            // Remove from lane
                            chip.querySelector('.gt-task-chip-remove').addEventListener('click', function(e) {
                                e.stopPropagation();
                                assignments[wid] = assignments[wid].filter(function(id) { return id !== task.id; });
                                renderLanes();
                                updateSummary();
                                syncJSON();
                            });

                            // Drag from lane back to pool (unassign)
                            chip.addEventListener('dragstart', function(e) {
                                e.dataTransfer.setData('text/plain', task.id);
                                e.dataTransfer.setData('application/x-gt-source', 'lane');
                                e.dataTransfer.setData('application/x-gt-waiter', wid);
                                chip.style.opacity = '0.5';
                            });
                            chip.addEventListener('dragend', function() {
                                chip.style.opacity = '1';
                            });

                            // Touch from lane
                            chip.addEventListener('touchstart', function(e) {
                                handleTouchStart(e, task.id, 'lane', wid);
                            }, { passive: true });

                            dropZone.appendChild(chip);
                        });
                    }
                });
            }

            function assignTask(tid, wid) {
                if (!assignments[wid]) assignments[wid] = [];
                if (assignments[wid].indexOf(tid) === -1) {
                    assignments[wid].push(tid);
                }
            }

            // --- Add Task ---
            DOM.btnAdd.addEventListener('click', function() {
                var title = DOM.title.value.trim();
                if (!title) {
                    alert('Judul tugas wajib diisi.');
                    DOM.title.focus();
                    return;
                }

                var catOpt = DOM.cat.options[DOM.cat.selectedIndex];
                
                var repeatInput = document.getElementById('gt_new_repeat');
                var repeatCount = Math.max(1, Math.min(10, parseInt(repeatInput.value) || 1));
                var deadlineInput = document.getElementById('gt_new_deadline');
                var deadlineTime = deadlineInput.value || '';

                var newTask = {
                    id: 't_' + (++taskIdCounter) + '_' + Date.now(),
                    title: title,
                    description: DOM.desc.value.trim(),
                    category_id: catOpt.value,
                    category_name: catOpt.value ? catOpt.text : '',
                    category_color: catOpt.value ? catOpt.getAttribute('data-color') : '',
                    requires_photo_proof: DOM.photo.value !== 'none',
                    requires_photo_before: DOM.photo.value === 'both',
                    repeat_count: repeatCount,
                    deadline_time: deadlineTime
                };

                taskPool.push(newTask);
                
                // Reset form
                DOM.title.value = '';
                DOM.desc.value = '';
                DOM.cat.selectedIndex = 0;
                DOM.photo.value = 'none';
                repeatInput.value = '1';
                deadlineInput.value = '';
                DOM.title.focus();

                renderPool();
                updateSummary();
                syncJSON();
            });

            // --- Drag and Drop ---
            DOM.lanes.forEach(function(lane) {
                lane.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    lane.classList.add('is-drag-over');
                });
                lane.addEventListener('dragleave', function() {
                    lane.classList.remove('is-drag-over');
                });
                lane.addEventListener('drop', function(e) {
                    e.preventDefault();
                    lane.classList.remove('is-drag-over');
                    
                    var tid = e.dataTransfer.getData('text/plain');
                    var wid = lane.getAttribute('data-waiter-id');
                    
                    if (tid && wid) {
                        assignTask(tid, wid);
                        renderLanes();
                        updateSummary();
                        syncJSON();
                    }
                });
            });

            // Pool Drop Zone (unassign)
            var poolZone = document.querySelector('.js-gt-task-pool');
            poolZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                poolZone.style.borderColor = '#818cf8';
                poolZone.style.background = '#eef2ff';
            });
            poolZone.addEventListener('dragleave', function() {
                poolZone.style.borderColor = '';
                poolZone.style.background = '';
            });
            poolZone.addEventListener('drop', function(e) {
                e.preventDefault();
                poolZone.style.borderColor = '';
                poolZone.style.background = '';

                var tid = e.dataTransfer.getData('text/plain');
                var src = e.dataTransfer.getData('application/x-gt-source');
                var srcWid = e.dataTransfer.getData('application/x-gt-waiter');

                if (tid && src === 'lane' && srcWid) {
                    assignments[srcWid] = assignments[srcWid].filter(function(id) { return id !== tid; });
                    renderLanes();
                    updateSummary();
                    syncJSON();
                }
            });

            // --- Touch Dragging ---
            var touchState = { active: false, tid: null, clone: null, srcWid: null, srcType: null };
            
            function handleTouchStart(e, tid, srcType, srcWid) {
                touchState.active = true;
                touchState.tid = tid;
                touchState.srcType = srcType;
                touchState.srcWid = srcWid;

                var clone = e.target.closest(srcType === 'pool' ? '.gt-task-card' : '.gt-task-chip').cloneNode(true);
                clone.style.position = 'fixed';
                clone.style.opacity = '0.8';
                clone.style.zIndex = '9999';
                clone.style.pointerEvents = 'none';
                clone.style.width = '200px';
                document.body.appendChild(clone);
                touchState.clone = clone;
                
                var touch = e.touches[0];
                moveClone(touch.clientX, touch.clientY);
            }

            function moveClone(x, y) {
                if (touchState.clone) {
                    touchState.clone.style.left = (x - 20) + 'px';
                    touchState.clone.style.top = (y - 20) + 'px';
                }
            }

            document.addEventListener('touchmove', function(e) {
                if (!touchState.active) return;
                e.preventDefault(); // prevent scroll
                var touch = e.touches[0];
                moveClone(touch.clientX, touch.clientY);
            }, { passive: false });

            document.addEventListener('touchend', function(e) {
                if (!touchState.active) return;
                
                if (touchState.clone) {
                    touchState.clone.remove();
                    touchState.clone = null;
                }

                var touch = e.changedTouches[0];
                var el = document.elementFromPoint(touch.clientX, touch.clientY);
                
                if (el) {
                    var targetLane = el.closest('.gt-waiter-lane');
                    var targetPool = el.closest('.gt-task-pool');

                    if (targetLane && touchState.tid) {
                        var wid = targetLane.getAttribute('data-waiter-id');
                        assignTask(touchState.tid, wid);
                        renderLanes();
                        updateSummary();
                        syncJSON();
                    } else if (targetPool && touchState.srcType === 'lane' && touchState.srcWid) {
                        assignments[touchState.srcWid] = assignments[touchState.srcWid].filter(function(id) { return id !== touchState.tid; });
                        renderLanes();
                        updateSummary();
                        syncJSON();
                    }
                }

                touchState.active = false;
            });

            // --- Toolbar Actions ---
            function getVisibleLanes() {
                return Array.from(DOM.lanes).filter(function(lane) { return lane.style.display !== 'none'; });
            }

            DOM.roleFilter.addEventListener('change', function() {
                var val = this.value;
                DOM.lanes.forEach(function(lane) {
                    var role = lane.getAttribute('data-waiter-role');
                    lane.style.display = (val === 'all' || role === val) ? 'flex' : 'none';
                });
            });

            DOM.btnAssignAll.addEventListener('click', function() {
                if (taskPool.length === 0) return alert('Buat tugas terlebih dahulu.');
                var visibleLanes = getVisibleLanes();
                if (visibleLanes.length === 0) return;

                taskPool.forEach(function(task) {
                    visibleLanes.forEach(function(lane) {
                        assignTask(task.id, lane.getAttribute('data-waiter-id'));
                    });
                });
                renderLanes();
                updateSummary();
                syncJSON();
            });

            DOM.btnMerata.addEventListener('click', function() {
                if (taskPool.length === 0) return alert('Buat tugas terlebih dahulu.');
                var visibleLanes = getVisibleLanes();
                if (visibleLanes.length === 0) return;

                var idx = 0;
                taskPool.forEach(function(task) {
                    var lane = visibleLanes[idx % visibleLanes.length];
                    assignTask(task.id, lane.getAttribute('data-waiter-id'));
                    idx++;
                });
                renderLanes();
                updateSummary();
                syncJSON();
            });

            DOM.btnReset.addEventListener('click', function() {
                if (confirm('Hapus semua penugasan (kembalikan tugas ke pool)?')) {
                    Object.keys(assignments).forEach(function(wid) { assignments[wid] = []; });
                    renderLanes();
                    updateSummary();
                    syncJSON();
                }
            });

            // --- Form Submit Validation ---
            DOM.form.addEventListener('submit', function(e) {
                // Only validate if general mode is active
                if (document.getElementById('task_type').value !== 'general') return;

                if (taskPool.length === 0) {
                    e.preventDefault();
                    alert('Anda belum membuat satupun tugas.');
                    return;
                }

                var unassignedCount = 0;
                var assignedTasks = new Set();
                Object.keys(assignments).forEach(function(wid) {
                    assignments[wid].forEach(function(tid) { assignedTasks.add(tid); });
                });

                unassignedCount = taskPool.length - assignedTasks.size;

                if (unassignedCount > 0) {
                    if (!confirm('Ada ' + unassignedCount + ' tugas yang belum di-assign. Tetap lanjutkan? Tugas yang tidak di-assign tidak akan dikirim.')) {
                        e.preventDefault();
                    }
                }
                
                // Ensure JSON is synced
                syncJSON();
            });

        })();

        function updateCategoryName(selectEl) {
            const selected = selectEl.options[selectEl.selectedIndex];
            document.getElementById('category_name').value = selected.dataset.name || '';
        }

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
                recurrenceTypeInput.required = true;
                toggleRecurrenceDetailFields();
                // Let toggleScheduleMode handle required state for schedule_time/time_limit
                toggleScheduleMode();
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

        function toggleScheduleMode() {
            const modeEl = document.getElementById('schedule_mode');
            if (!modeEl) return;
            const mode = modeEl.value;
            const fixedSchedule = document.getElementById('fixed-schedule-wrapper');
            const fixedDeadline = document.getElementById('fixed-deadline-wrapper');
            const shiftOffset = document.getElementById('shift-offset-wrapper');
            const shiftDeadline = document.getElementById('shift-deadline-wrapper');
            const deadlineBeforeEnd = document.getElementById('deadline-before-end-wrapper');
            const scheduleTimeInput = document.getElementById('schedule_time');
            const timeLimitInput = document.getElementById('time_limit_minutes');

            if (mode === 'shift_relative') {
                if (fixedSchedule) fixedSchedule.style.display = 'none';
                if (fixedDeadline) fixedDeadline.style.display = 'none';
                if (shiftOffset) shiftOffset.style.display = 'block';
                if (shiftDeadline) shiftDeadline.style.display = 'block';
                if (scheduleTimeInput) scheduleTimeInput.required = false;
                if (timeLimitInput) timeLimitInput.required = false;
                toggleDeadlineMode();
            } else {
                if (fixedSchedule) fixedSchedule.style.display = 'block';
                if (fixedDeadline) fixedDeadline.style.display = 'block';
                if (shiftOffset) shiftOffset.style.display = 'none';
                if (shiftDeadline) shiftDeadline.style.display = 'none';
                if (deadlineBeforeEnd) deadlineBeforeEnd.style.display = 'none';
                if (scheduleTimeInput) scheduleTimeInput.required = true;
                if (timeLimitInput) timeLimitInput.required = true;
            }
        }

        function toggleDeadlineMode() {
            const deadlineModeEl = document.getElementById('deadline_mode');
            if (!deadlineModeEl) return;
            const deadlineMode = deadlineModeEl.value;
            const beforeEndWrapper = document.getElementById('deadline-before-end-wrapper');
            const fixedDeadline = document.getElementById('fixed-deadline-wrapper');

            if (deadlineMode === 'before_shift_end') {
                if (beforeEndWrapper) beforeEndWrapper.style.display = 'block';
                if (fixedDeadline) fixedDeadline.style.display = 'none';
            } else {
                if (beforeEndWrapper) beforeEndWrapper.style.display = 'none';
                if (fixedDeadline) fixedDeadline.style.display = 'block';
            }
        }

        // Initialize schedule mode on page load
        toggleScheduleMode();

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

        // Hide global recurrence pattern selector di rack-check (per-rak dropdown jadi single source).
        // Tampilkan note penjelasan + biarkan schedule mode + jam mulai + deadline tetap visible.
        (function applyRackCheckRecurrenceVisibility() {
            var taskTypeEl = document.getElementById('task_type');
            if (!taskTypeEl) return;
            var isRackCheck = taskTypeEl.value === 'rack_check';

            document.querySelectorAll('[data-rack-check-hide="1"]').forEach(function(el) {
                el.style.display = isRackCheck ? 'none' : '';
            });
            document.querySelectorAll('[data-rack-check-show="1"]').forEach(function(el) {
                el.style.display = isRackCheck ? 'block' : 'none';
            });

            // Disable required pada select recurrence_type kalau hidden, supaya validation lulus.
            var recurrenceSelect = document.getElementById('recurrence_type');
            if (recurrenceSelect && isRackCheck) {
                recurrenceSelect.removeAttribute('required');
                // Set ke 'daily' sebagai fallback. Per-rak map override real.
                recurrenceSelect.value = 'daily';
            }
        })();

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
                            ensureRackRecurrenceControls(card);

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

            function getWeeklyDayNameShort(day) {
                var dayNames = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                var d = parseInt(day, 10) || 1;
                if (d < 1 || d > 7) d = 1;
                return dayNames[d];
            }

            function updateRackRecurrenceBadge(rackId) {
                var card = findRackCardByRackId(rackId);
                if (!card) return;
                var sel = card.querySelector('.bb-rack-recurrence');
                var weeklySel = card.querySelector('.bb-rack-weekly-day');
                var badge = card.querySelector('.bb-rack-recurrence-badge');
                if (!sel || !weeklySel || !badge) return;

                var val = sel.value || 'daily';
                weeklySel.style.display = val === 'weekly' ? 'inline-block' : 'none';

                var label = '📅';
                if (val === 'weekly') {
                    label = '🗓️ ' + getWeeklyDayNameShort(weeklySel.value);
                } else if (val.indexOf('every_') === 0) {
                    var n = parseInt(val.replace('every_', ''), 10) || 1;
                    label = '🔁 ' + n + 'd';
                }
                badge.textContent = label;
            }

            function buildRackCardRecurrenceControls(rackId) {
                var rid = String(rackId || '');
                var escRid = escapeHtml(rid);
                return '' +
                    '<div style="margin-top:4px; display:flex; align-items:center; gap:4px; flex-wrap:wrap;">' +
                        '<select class="bb-rack-recurrence" data-rack-id="' + escRid + '" style="font-size:11px; padding:2px 4px;">' +
                            '<option value="daily">📅 Harian</option>' +
                            '<option value="weekly">🗓️ Mingguan</option>' +
                            '<option value="every_3">🔁 Setiap 3 hari</option>' +
                            '<option value="every_7">🔁 Setiap 7 hari</option>' +
                        '</select>' +
                        '<select class="bb-rack-weekly-day" data-rack-id="' + escRid + '" style="display:none; font-size:11px; padding:2px 4px;">' +
                            '<option value="1">Senin</option>' +
                            '<option value="2">Selasa</option>' +
                            '<option value="3">Rabu</option>' +
                            '<option value="4">Kamis</option>' +
                            '<option value="5">Jumat</option>' +
                            '<option value="6">Sabtu</option>' +
                            '<option value="7">Minggu</option>' +
                        '</select>' +
                        '<span class="bb-rack-recurrence-badge" data-rack-id="' + escRid + '" style="font-size:11px; font-weight:700; color:#334155;">📅</span>' +
                    '</div>';
            }

            function ensureRackRecurrenceControls(card) {
                if (!card) return;
                var rid = card.getAttribute('data-rack-id');
                if (!rid) return;
                if (card.querySelector('.bb-rack-recurrence')) {
                    updateRackRecurrenceBadge(rid);
                    return;
                }

                var info = card.querySelector('.bb-rack-card-info');
                if (!info) return;
                info.insertAdjacentHTML('beforeend', buildRackCardRecurrenceControls(rid));

                var recSel = card.querySelector('.bb-rack-recurrence');
                var daySel = card.querySelector('.bb-rack-weekly-day');
                if (recSel) {
                    recSel.addEventListener('change', function() {
                        updateRackRecurrenceBadge(rid);
                        syncHiddenFields();
                    });
                }
                if (daySel) {
                    daySel.addEventListener('change', function() {
                        updateRackRecurrenceBadge(rid);
                        syncHiddenFields();
                    });
                }
                updateRackRecurrenceBadge(rid);
            }

            // ── Render ──
            function renderBoard() {
                var assignedIds = getAssignedRackIds();

                // Show/hide pool cards
                var poolVisible = 0;
                allRackCards.forEach(function(card) {
                    ensureRackRecurrenceControls(card);
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
                var rackRecurrenceEl = document.getElementById('rack_recurrence_map');

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

                // Build per-rack recurrence map
                var rackRecurrenceMap = {};
                assignedRackIds.forEach(function(rid) {
                    var card = findRackCardByRackId(rid);
                    if (!card) return;
                    var recSel = card.querySelector('.bb-rack-recurrence');
                    var daySel = card.querySelector('.bb-rack-weekly-day');
                    var val = recSel ? recSel.value : 'daily';
                    var entry = { type: 'daily' };

                    if (val === 'weekly') {
                        var day = daySel ? (parseInt(daySel.value, 10) || 1) : 1;
                        if (day < 1 || day > 7) day = 1;
                        entry = { type: 'weekly', weekly_day: day };
                    } else if (typeof val === 'string' && val.indexOf('every_') === 0) {
                        var n = parseInt(val.replace('every_', ''), 10) || 1;
                        if (n < 1) n = 1;
                        entry = { type: 'every_n_days', interval_days: n };
                    }

                    rackRecurrenceMap[rid] = entry;
                });

                if (rackRecurrenceEl) {
                    rackRecurrenceEl.value = Object.keys(rackRecurrenceMap).length > 0
                        ? JSON.stringify(rackRecurrenceMap)
                        : '';
                }

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

            // ── Auto-scroll saat drag (UX untuk rak banyak) ──
            // Browser native HTML5 drag-drop tidak auto-scroll containers.
            // Kalau cursor saat drag dekat top/bottom edge dari pool atau lanes,
            // scroll smoothly supaya user bisa drop ke lane atas saat sedang drag dari rak bawah.
            (function setupAutoScrollDuringDrag() {
                var rackPoolEl = document.getElementById('bbRackPool');
                var lanesEl = document.getElementById('bbWaiterLanes');
                var scrollables = [rackPoolEl, lanesEl, document.scrollingElement || document.documentElement].filter(Boolean);
                var dragActive = false;
                var scrollTimer = null;
                var EDGE_PX = 70;
                var SCROLL_PX = 14;

                function isDragOverElement(el, x, y) {
                    if (!el) return false;
                    var rect = el.getBoundingClientRect();
                    return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
                }

                function scrollHotEdge(el, x, y) {
                    if (!el || el === document.scrollingElement || el === document.documentElement) {
                        // Window-level scroll: pakai window.scrollY
                        var topEdge = y < EDGE_PX;
                        var bottomEdge = y > window.innerHeight - EDGE_PX;
                        if (topEdge) window.scrollBy(0, -SCROLL_PX);
                        else if (bottomEdge) window.scrollBy(0, SCROLL_PX);
                        return;
                    }
                    var rect = el.getBoundingClientRect();
                    var topEdge = y - rect.top < EDGE_PX;
                    var bottomEdge = rect.bottom - y < EDGE_PX;
                    if (topEdge) el.scrollTop = Math.max(0, el.scrollTop - SCROLL_PX);
                    else if (bottomEdge) el.scrollTop = el.scrollTop + SCROLL_PX;
                }

                document.addEventListener('dragstart', function() {
                    dragActive = true;
                });
                document.addEventListener('dragend', function() {
                    dragActive = false;
                    if (scrollTimer) { clearInterval(scrollTimer); scrollTimer = null; }
                });
                document.addEventListener('drop', function() {
                    dragActive = false;
                    if (scrollTimer) { clearInterval(scrollTimer); scrollTimer = null; }
                });

                document.addEventListener('dragover', function(e) {
                    if (!dragActive) return;
                    var x = e.clientX, y = e.clientY;
                    // Stop existing timer; we'll start fresh based on current position
                    if (scrollTimer) { clearInterval(scrollTimer); scrollTimer = null; }
                    // Detect which scrollable cursor is over (priority: lanes > pool > window)
                    var target = null;
                    if (isDragOverElement(lanesEl, x, y)) target = lanesEl;
                    else if (isDragOverElement(rackPoolEl, x, y)) target = rackPoolEl;
                    else target = document.scrollingElement || document.documentElement;

                    // Continuous scroll while cursor stays in hot edge
                    scrollTimer = setInterval(function() {
                        scrollHotEdge(target, x, y);
                    }, 16);
                });
            })();

            // Initial render
            renderBoard();
            syncHiddenFields();
        })();
    </script>
@endsection
