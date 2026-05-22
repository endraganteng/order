@extends('admin.layout')

@section('title', 'Studio Tugas Umum - Admin')

@push('styles')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    .ts-shell {
        display: grid;
        grid-template-columns: 240px 1fr;
        gap: 14px;
        align-items: start;
    }
    @media (max-width: 1023px) {
        .ts-shell { grid-template-columns: 1fr; }
        .ts-palette { position: static !important; max-height: none !important; }
    }

    .ts-palette {
        position: sticky;
        top: 16px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px;
        max-height: calc(100vh - 32px);
        overflow-y: auto;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    .ts-palette-section { margin-bottom: 18px; }
    .ts-palette-section:last-child { margin-bottom: 0; }
    .ts-palette-title {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 8px;
    }
    .ts-palette-search {
        width: 100%;
        padding: 7px 10px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        margin-bottom: 8px;
        outline: none;
        transition: border-color 0.15s;
    }
    .ts-palette-search:focus { border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.2); }

    .ts-waiter-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 10px;
        margin-bottom: 5px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        cursor: grab;
        user-select: none;
        transition: box-shadow 0.15s, border-color 0.15s, transform 0.1s;
    }
    .ts-waiter-pill:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.08); border-color: #94a3b8; }
    .ts-waiter-pill:active, .ts-waiter-pill.is-dragging { cursor: grabbing; opacity: 0.5; transform: scale(0.97); }
    .ts-avatar {
        width: 26px; height: 26px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #fff;
        flex-shrink: 0;
    }
    .ts-avatar--pelayan { background: #6366f1; }
    .ts-avatar--kasir { background: #f59e0b; }
    .ts-avatar--finance { background: #10b981; }
    .ts-avatar--backup { background: #9333ea; }
    .ts-waiter-pill-name { font-weight: 600; color: #0f172a; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ts-waiter-pill-role { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }

    /* Rack pill: 2-row vertical layout for long names + locations */
    .ts-rack-pill {
        display: grid;
        grid-template-columns: 32px 1fr auto;
        column-gap: 8px;
        row-gap: 1px;
        align-items: center;
        padding: 7px 10px;
        margin-bottom: 5px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        cursor: grab;
        user-select: none;
        transition: box-shadow 0.15s, border-color 0.15s, transform 0.1s;
    }
    .ts-rack-pill:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.08); border-color: #94a3b8; }
    .ts-rack-pill:active, .ts-rack-pill.is-dragging { cursor: grabbing; opacity: 0.5; transform: scale(0.97); }
    .ts-rack-pill .ts-avatar { grid-row: span 2; }
    .ts-rack-pill-name {
        grid-column: 2;
        grid-row: 1;
        font-weight: 600;
        color: #0f172a;
        font-size: 12.5px;
        line-height: 1.3;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .ts-rack-pill-loc {
        grid-column: 2;
        grid-row: 2;
        font-size: 10px;
        color: #94a3b8;
        line-height: 1.3;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .ts-rack-pill-lock {
        grid-column: 3;
        grid-row: 1 / span 2;
        align-self: center;
        font-size: 12px;
        color: #dc2626;
    }

    .ts-cat-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        background: #f1f5f9;
        border-radius: 999px;
        font-size: 12px;
        margin: 0 4px 4px 0;
    }
    .ts-cat-dot { width: 8px; height: 8px; border-radius: 50%; }

    .ts-preset-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 7px 10px;
        margin-bottom: 5px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
    }
    .ts-preset-btn:hover { background: #eef2ff; border-color: #818cf8; color: #4338ca; }

    /* ── Board (middle panel) ────────────────────────── */
    .ts-board {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
    }
    .ts-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    .ts-toolbar-group { display: flex; align-items: center; gap: 6px; }
    .ts-toolbar-label { font-size: 12px; font-weight: 600; color: #64748b; }
    .ts-tab {
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 600;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s;
    }
    .ts-tab:hover { background: #e2e8f0; }
    .ts-tab.is-active { background: #6366f1; border-color: #6366f1; color: #fff; }
    .ts-toolbar-input {
        padding: 6px 10px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        outline: none;
        min-width: 180px;
    }
    .ts-toolbar-input:focus { border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.2); }
    .ts-toolbar-spacer { flex: 1; }
    .ts-btn {
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 600;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s;
    }
    .ts-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
    .ts-btn--primary { background: #6366f1; border-color: #6366f1; color: #fff; }
    .ts-btn--primary:hover { background: #4f46e5; border-color: #4f46e5; }
    .ts-btn--danger { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
    .ts-btn--danger:hover { background: #fee2e2; }

    .ts-columns {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 8px;
        align-items: flex-start;
        min-height: 60vh;
    }
    .ts-col {
        flex: 0 0 260px;
        min-width: 240px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        max-height: 75vh;
        transition: border-color 0.15s, background 0.15s;
    }
    .ts-col.is-drag-over { border-color: #818cf8; background: #eef2ff; }
    .ts-col-header {
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }
    .ts-col-title { font-size: 13px; font-weight: 700; color: #0f172a; }
    .ts-col-count { font-size: 11px; font-weight: 700; color: #94a3b8; background: #fff; padding: 2px 8px; border-radius: 999px; border: 1px solid #e2e8f0; }
    .ts-col-add {
        background: #6366f1;
        color: #fff;
        border: none;
        border-radius: 6px;
        width: 22px; height: 22px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        line-height: 1;
        display: flex; align-items: center; justify-content: center;
    }
    .ts-col-add:hover { background: #4f46e5; }
    .ts-col-body {
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        overflow-y: auto;
        flex: 1;
        min-height: 80px;
    }
    .ts-col-empty {
        padding: 24px 12px;
        text-align: center;
        color: #cbd5e1;
        font-size: 12px;
        border: 1.5px dashed #e2e8f0;
        border-radius: 8px;
    }

    /* Card (template) */
    .ts-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px;
        cursor: grab;
        transition: box-shadow 0.15s, border-color 0.15s, opacity 0.15s;
        position: relative;
    }
    .ts-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-color: #94a3b8; }
    .ts-card.is-inactive { opacity: 0.55; background: #fafafa; border-style: dashed; }
    .ts-card.is-dragging { opacity: 0.4; cursor: grabbing; }
    .ts-card.is-selected { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
    .ts-card-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 5px;
        line-height: 1.3;
        word-break: break-word;
    }
    .ts-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 6px;
    }
    .ts-badge {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }
    .ts-badge--freq { background: #dbeafe; color: #1e40af; }
    .ts-badge--time { background: #ecfdf5; color: #065f46; }
    .ts-badge--assign { background: #f3e8ff; color: #6b21a8; }
    .ts-badge--photo { background: #fef3c7; color: #92400e; }
    .ts-badge--cat { background: #f1f5f9; color: #475569; display: inline-flex; align-items: center; gap: 3px; }
    .ts-card-actions {
        display: flex;
        gap: 4px;
        margin-top: 4px;
    }
    .ts-card-btn {
        flex: 1;
        padding: 4px;
        background: none;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 11px;
        cursor: pointer;
        transition: background 0.15s;
        line-height: 1;
    }
    .ts-card-btn:hover { background: #f1f5f9; }
    .ts-card-btn--trigger:hover { background: #ecfdf5; border-color: #10b981; }
    .ts-card-btn--toggle:hover { background: #fef3c7; border-color: #f59e0b; }
    .ts-card-btn--del:hover { background: #fef2f2; border-color: #f87171; color: #b91c1c; }

    /* ── Drawer (right slide-in) ────────────────────── */
    .ts-drawer-overlay {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.5);
        z-index: 998;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
    }
    .ts-drawer-overlay.is-open { opacity: 1; pointer-events: auto; }
    .ts-drawer {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: min(440px, 100%);
        background: #fff;
        z-index: 999;
        box-shadow: -10px 0 30px rgba(0, 0, 0, 0.15);
        transform: translateX(100%);
        transition: transform 0.25s cubic-bezier(.4,0,.2,1);
        display: flex;
        flex-direction: column;
    }
    .ts-drawer.is-open { transform: translateX(0); }
    .ts-drawer-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .ts-drawer-title { font-size: 16px; font-weight: 700; color: #0f172a; }
    .ts-drawer-close {
        background: none;
        border: none;
        font-size: 22px;
        color: #94a3b8;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
    }
    .ts-drawer-close:hover { color: #475569; }
    .ts-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 18px 20px;
    }
    .ts-drawer-footer {
        padding: 12px 20px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 8px;
        background: #f8fafc;
    }
    .ts-field { margin-bottom: 14px; }
    .ts-field label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 5px;
    }
    .ts-field input[type=text], .ts-field input[type=number], .ts-field input[type=time], .ts-field select, .ts-field textarea {
        width: 100%;
        padding: 8px 10px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        outline: none;
        font-family: inherit;
        transition: border-color 0.15s;
        box-sizing: border-box;
    }
    .ts-field input:focus, .ts-field select:focus, .ts-field textarea:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 2px rgba(129,140,248,0.2);
    }
    .ts-field textarea { resize: vertical; min-height: 60px; }
    .ts-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .ts-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #334155;
        cursor: pointer;
        margin-bottom: 6px;
    }
    .ts-hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }

    /* Today drawer */
    .ts-today-fab {
        position: fixed;
        bottom: 20px; right: 20px;
        background: #0f172a;
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 12px 18px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(15,23,42,0.25);
        z-index: 50;
    }
    .ts-today-fab:hover { background: #1e293b; }
    .ts-today-badge {
        background: #f59e0b;
        color: #fff;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 11px;
        margin-left: 6px;
    }

    /* Toast */
    .ts-toast {
        position: fixed;
        top: 20px; right: 20px;
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        max-width: 360px;
        transition: opacity 0.2s, transform 0.2s;
    }
    .ts-toast--success { background: #d1fae5; color: #065f46; }
    .ts-toast--error { background: #fef2f2; color: #991b1b; }

    /* Quick stats */
    .ts-stats { display: flex; gap: 8px; flex-wrap: wrap; }
    .ts-stat {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        padding: 5px 10px;
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }
    .ts-stat strong { color: #0f172a; font-weight: 800; margin-right: 4px; }
</style>
@endpush

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div x-data="taskStudio()" x-init="init()" x-cloak>
    {{-- Toast --}}
    <template x-if="toast.show">
        <div class="ts-toast" :class="'ts-toast--' + toast.type" x-text="toast.msg"></div>
    </template>

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
        <div style="min-width:280px; flex:1;">
            <h2 style="margin:0; font-size:clamp(20px,3.5vw,26px); color:#0f172a;">
                <span x-text="scopeIsRack() ? '📦 Studio Cek Rak' : '🎛️ Studio Tugas Umum'"></span>
            </h2>
            <div style="font-size:12px; color:#64748b; margin-top:3px;">
                <span x-show="!scopeIsRack()">Buat, atur jadwal, dan pantau tugas dalam satu halaman. Drag card untuk pindah role atau hari, klik untuk edit.</span>
                <span x-show="scopeIsRack()">Atur tugas pengecekan rak: pilih rak, jadwalkan, tugaskan ke waiter atau rolling. Setiap rak hanya boleh punya 1 template aktif.</span>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; flex-shrink:0;">
            {{-- Scope tab toggle --}}
            <div style="display:inline-flex; background:#f1f5f9; border-radius:10px; padding:4px;">
                <button class="ts-btn"
                        :class="!scopeIsRack() && 'ts-btn--primary'"
                        style="border:none; padding:6px 14px; font-weight:600;"
                        @click="switchScope('general')">🎛️ Tugas Umum</button>
                <button class="ts-btn"
                        :class="scopeIsRack() && 'ts-btn--primary'"
                        style="border:none; padding:6px 14px; font-weight:600;"
                        @click="switchScope('rack_check')">📦 Cek Rak</button>
            </div>
            {{-- Side action: link audit (fixed width supaya tidak shift) --}}
            <a :href="scopeIsRack() ? '{{ route('admin.tasks.rack.index') }}' : '{{ route('admin.tasks.index') }}'"
               class="ts-btn"
               style="min-width:170px; text-align:center; white-space:nowrap;"
               x-text="scopeIsRack() ? '📊 Riwayat Cek Rak' : '📊 Daftar Tugas'"></a>
            {{-- Primary CTA (fixed width) --}}
            <button class="ts-btn ts-btn--primary"
                    style="min-width:170px; white-space:nowrap;"
                    @click="openDrawer(null)"
                    x-text="scopeIsRack() ? '+ Cek Rak Baru' : '+ Tugas Baru'"></button>
        </div>
    </div>

    {{-- Quick stats --}}
    <div class="ts-stats" style="margin-bottom:14px;">
        <span class="ts-stat"><strong x-text="templatesInScope().length"></strong> template total</span>
        <span class="ts-stat"><strong x-text="templatesInScope().filter(t => t.is_active).length"></strong> aktif</span>
        <span class="ts-stat"><strong x-text="templatesInScope().filter(t => !t.is_active).length"></strong> tidak aktif</span>
        <span class="ts-stat"><strong x-text="(todayTasks||[]).length"></strong> tugas hari ini</span>
    </div>

    <div class="ts-shell">
        {{-- LEFT: palette --}}
        <aside class="ts-palette">
            {{-- Section: Waiter (only general scope) --}}
            <div class="ts-palette-section" x-show="!scopeIsRack()">
                <div class="ts-palette-title">👥 Waiter Aktif</div>
                <input type="text" class="ts-palette-search" placeholder="🔍 Cari waiter…" x-model="waiterSearch">
                <div>
                    <template x-for="w in filteredWaiters()" :key="w.id">
                        <div class="ts-waiter-pill"
                             draggable="true"
                             @dragstart="dragWaiterStart($event, w)"
                             @dragend="dragWaiterEnd($event)"
                             @click="openDrawer(null, { assigned_waiter_id: w.id, assignment_type: 'single', assigned_waiter_role: w.role })"
                             :title="'Drag ke card untuk assign ke ' + w.name + ', atau klik untuk buat tugas baru'">
                            <div class="ts-avatar" :class="'ts-avatar--' + w.role" x-text="w.name.charAt(0).toUpperCase()"></div>
                            <span class="ts-waiter-pill-name" x-text="w.name"></span>
                            <span class="ts-waiter-pill-role" x-text="w.role"></span>
                        </div>
                    </template>
                    <div x-show="filteredWaiters().length === 0" style="text-align:center; padding:14px; color:#cbd5e1; font-size:12px;">
                        <span x-text="waiters.length === 0 ? 'Belum ada waiter aktif' : 'Tidak ada hasil'"></span>
                    </div>
                </div>
            </div>

            {{-- Section: Rak (only rack_check scope) --}}
            <div class="ts-palette-section" x-show="scopeIsRack()">
                <div class="ts-palette-title">📦 Rak Aktif</div>
                <input type="text" class="ts-palette-search" placeholder="🔍 Cari rak…" x-model="rackSearch">
                <div style="display:flex; gap:4px; margin-bottom:8px; flex-wrap:wrap;">
                    <button class="ts-btn" style="padding:4px 10px; font-size:11px;"
                            :class="rackTypeFilter === 'all' && 'ts-btn--primary'"
                            @click="rackTypeFilter = 'all'">Semua</button>
                    <button class="ts-btn" style="padding:4px 10px; font-size:11px;"
                            :class="rackTypeFilter === 'storage' && 'ts-btn--primary'"
                            @click="rackTypeFilter = 'storage'">📦 Gudang</button>
                    <button class="ts-btn" style="padding:4px 10px; font-size:11px;"
                            :class="rackTypeFilter === 'display' && 'ts-btn--primary'"
                            @click="rackTypeFilter = 'display'">🛍️ Display</button>
                </div>
                <div>
                    <template x-for="r in filteredRacks()" :key="r.id">
                        <div class="ts-rack-pill"
                             draggable="true"
                             @dragstart="dragRackStart($event, r)"
                             @dragend="dragWaiterEnd($event)"
                             @click="openDrawer(null, { rack_id: r.id, rack_name: r.name, rack_location: r.location, rack_barcode_value: r.barcode_value, rack_type: r.rack_type, task_type: 'rack_check', requires_barcode_scan: true, requires_photo_proof: true })"
                             :title="'Klik untuk buat template Cek Rak untuk ' + r.name + (r.location ? ' • ' + r.location : '')">
                            <div class="ts-avatar" :class="r.rack_type === 'display' ? 'ts-avatar--kasir' : 'ts-avatar--pelayan'" style="font-size:14px;" x-text="r.rack_type === 'display' ? '🛍️' : '📦'"></div>
                            <div class="ts-rack-pill-name" x-text="r.name"></div>
                            <div class="ts-rack-pill-loc" x-text="r.location || '—'"></div>
                            <template x-if="rackInUseByTemplate(r.id, null)">
                                <span class="ts-rack-pill-lock" title="Sudah ada template aktif">🔒</span>
                            </template>
                        </div>
                    </template>
                    <div x-show="filteredRacks().length === 0" style="text-align:center; padding:14px; color:#cbd5e1; font-size:12px;">
                        <span x-text="racks.length === 0 ? 'Belum ada rak aktif' : 'Tidak ada hasil'"></span>
                    </div>
                </div>
                {{-- Inline add rack --}}
                <div style="margin-top:10px;">
                    <button class="ts-preset-btn" @click="addRackOpen = !addRackOpen" x-show="!addRackOpen">
                        ➕ Tambah Rak Baru
                    </button>
                    <div x-show="addRackOpen" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; display:flex; flex-direction:column; gap:6px;">
                        <input type="text" class="ts-palette-search" placeholder="Nama rak…" x-model="addRackForm.name" style="margin-bottom:0;">
                        <input type="text" class="ts-palette-search" placeholder="Lokasi (opsional)…" x-model="addRackForm.location" style="margin-bottom:0;">
                        <select x-model="addRackForm.rack_type" class="ts-palette-search" style="margin-bottom:0;">
                            <option value="storage">📦 Gudang</option>
                            <option value="display">🛍️ Display</option>
                        </select>
                        <div style="display:flex; gap:6px;">
                            <button class="ts-btn ts-btn--primary" style="flex:1; padding:6px;"
                                    @click="addRackInline()" :disabled="addRackSaving">
                                <span x-text="addRackSaving ? 'Menyimpan…' : '✓ Simpan'"></span>
                            </button>
                            <button class="ts-btn" style="padding:6px;"
                                    @click="addRackOpen = false; addRackForm = { name: '', location: '', rack_type: 'storage' };">Batal</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ts-palette-section">
                <div class="ts-palette-title">🗂️ Kategori</div>
                <div>
                    <template x-for="c in categories" :key="c.id">
                        <span class="ts-cat-chip" :title="c.name">
                            <span class="ts-cat-dot" :style="'background:' + c.color"></span>
                            <span x-text="c.name"></span>
                        </span>
                    </template>
                    <div x-show="categories.length === 0" style="font-size:12px; color:#cbd5e1;">Belum ada kategori</div>
                </div>
            </div>

            <div class="ts-palette-section" x-show="!scopeIsRack()">
                <div class="ts-palette-title">⚡ Preset Cepat</div>
                <button class="ts-preset-btn" @click="openDrawer(null, { recurrence_type: 'daily', schedule_mode: 'shift_relative' })">
                    🔄 Harian (ikut shift)
                </button>
                <button class="ts-preset-btn" @click="openDrawer(null, { recurrence_type: 'daily', schedule_mode: 'fixed', schedule_time: '09:00' })">
                    ⏰ Harian jam 09:00
                </button>
                <button class="ts-preset-btn" @click="openDrawer(null, { recurrence_type: 'weekly', weekly_day: '1', schedule_mode: 'fixed', schedule_time: '09:00' })">
                    📅 Mingguan (Senin)
                </button>
            </div>
        </aside>

        {{-- MIDDLE: board --}}
        <main class="ts-board">
            <div class="ts-toolbar">
                <div class="ts-toolbar-group">
                    <span class="ts-toolbar-label">Tampilan:</span>
                    <button class="ts-tab" :class="viewMode === 'role' && 'is-active'" @click="viewMode = 'role'">👥 Per Role</button>
                    <button class="ts-tab" :class="viewMode === 'schedule' && 'is-active'" @click="viewMode = 'schedule'">📅 Per Jadwal</button>
                </div>
                <div class="ts-toolbar-group">
                    <input type="text" class="ts-toolbar-input" placeholder="🔍 Cari template…" x-model="search">
                </div>
                <div class="ts-toolbar-spacer"></div>
                <div class="ts-toolbar-group">
                    <span class="ts-toolbar-label">Filter:</span>
                    <select x-model="freqFilter" class="ts-toolbar-input" style="min-width:auto;">
                        <option value="all">Semua frekuensi</option>
                        <option value="daily">Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="every_n_days">Tiap N hari</option>
                    </select>
                </div>
            </div>

            {{-- ROLE VIEW --}}
            <div class="ts-columns" x-show="viewMode === 'role'">
                <template x-for="col in roleColumns" :key="col.key">
                    <div class="ts-col"
                         :class="dragOverCol === col.key && 'is-drag-over'"
                         @dragover.prevent="dragOverCol = col.key"
                         @dragleave.self="dragOverCol = null"
                         @drop.prevent="dropOnRole(col.key, $event)">
                        <div class="ts-col-header">
                            <span class="ts-col-title" x-text="col.label"></span>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span class="ts-col-count" x-text="cardsForRole(col.key).length"></span>
                                <button class="ts-col-add" x-show="col.key !== 'inactive'"
                                        @click="openDrawer(null, { assigned_waiter_role: col.key })"
                                        title="Tambah tugas untuk role ini">+</button>
                            </div>
                        </div>
                        <div class="ts-col-body">
                            <template x-for="t in cardsForRole(col.key)" :key="t.id">
                                <div class="ts-card"
                                     :class="[!t.is_active && 'is-inactive', selected === t.id && 'is-selected']"
                                     draggable="true"
                                     @dragstart="dragCardStart($event, t)"
                                     @dragend="dragCardEnd($event)"
                                     @click="openDrawer(t)">
                                    <div class="ts-card-title">
                                        <template x-if="t.task_type === 'rack_check'">
                                            <span x-text="(t.rack_type === 'display' ? '🛍️ ' : '📦 ') + (t.rack_name || t.title || '(Tanpa rak)')"></span>
                                        </template>
                                        <template x-if="t.task_type !== 'rack_check'">
                                            <span x-text="t.title || '(Tanpa judul)'"></span>
                                        </template>
                                    </div>
                                    <div class="ts-card-meta">
                                        <span class="ts-badge ts-badge--freq" x-text="freqLabel(t)"></span>
                                        <span class="ts-badge ts-badge--time" x-text="timeLabel(t)"></span>
                                        <span class="ts-badge ts-badge--assign" x-text="assignLabel(t)"></span>
                                        <template x-if="t.task_type === 'rack_check' && t.rack_location">
                                            <span class="ts-badge" style="background:#e0e7ff; color:#3730a3;" :title="'Lokasi rak: ' + t.rack_location">
                                                📍 <span x-text="t.rack_location"></span>
                                            </span>
                                        </template>
                                        <template x-if="t.task_type === 'rack_check'">
                                            <span class="ts-badge" style="background:#fce7f3; color:#9d174d;" title="Wajib scan QR rak saat eksekusi">
                                                📷 QR
                                            </span>
                                        </template>
                                        <template x-if="t.rolling_enabled && (t.rolling_waiter_ids || []).length >= 2">
                                            <span class="ts-badge" style="background:#fef3c7; color:#92400e;" :title="rollingTooltip(t)">
                                                🔄 <span x-text="rollingShortLabel(t)"></span>
                                            </span>
                                        </template>
                                        <template x-if="t.target_shift_id">
                                            <span class="ts-badge" style="background:#dbeafe; color:#1e40af;" :title="'Khusus shift: ' + (shiftName(t.target_shift_id) || '?')">
                                                🕒 <span x-text="shiftName(t.target_shift_id) || 'shift'"></span>
                                            </span>
                                        </template>
                                        <template x-if="t.requires_photo_proof">
                                            <span class="ts-badge ts-badge--photo">📸 Foto</span>
                                        </template>
                                        <template x-if="t.category_name">
                                            <span class="ts-badge ts-badge--cat">
                                                <span class="ts-cat-dot" :style="'background:' + (categoryColor(t.category_id) || '#94a3b8')"></span>
                                                <span x-text="t.category_name"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <div class="ts-card-actions">
                                        <button class="ts-card-btn ts-card-btn--trigger"
                                                @click.stop="forceGenerate(t)"
                                                title="Generate tugas sekarang">🚀</button>
                                        <button class="ts-card-btn ts-card-btn--toggle"
                                                @click.stop="toggleActive(t)"
                                                :title="t.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                                x-text="t.is_active ? '⏸️' : '▶️'"></button>
                                        <button class="ts-card-btn ts-card-btn--del"
                                                @click.stop="deleteTemplate(t)"
                                                title="Hapus">🗑️</button>
                                    </div>
                                </div>
                            </template>
                            <div class="ts-col-empty" x-show="cardsForRole(col.key).length === 0">
                                <span x-show="!scopeIsRack()">Drag waiter atau klik <strong>+</strong> untuk menambah tugas</span>
                                <span x-show="scopeIsRack()">Drag rak ke kolom ini, atau klik <strong>+</strong> untuk Cek Rak baru</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- SCHEDULE VIEW --}}
            <div class="ts-columns" x-show="viewMode === 'schedule'">
                <template x-for="col in scheduleColumns" :key="col.key">
                    <div class="ts-col"
                         :class="dragOverCol === col.key && 'is-drag-over'"
                         @dragover.prevent="dragOverCol = col.key"
                         @dragleave.self="dragOverCol = null"
                         @drop.prevent="dropOnSchedule(col, $event)">
                        <div class="ts-col-header">
                            <span class="ts-col-title" x-text="col.label"></span>
                            <span class="ts-col-count" x-text="cardsForSchedule(col).length"></span>
                        </div>
                        <div class="ts-col-body">
                            <template x-for="t in cardsForSchedule(col)" :key="t.id">
                                <div class="ts-card"
                                     :class="[!t.is_active && 'is-inactive', selected === t.id && 'is-selected']"
                                     draggable="true"
                                     @dragstart="dragCardStart($event, t)"
                                     @dragend="dragCardEnd($event)"
                                     @click="openDrawer(t)">
                                    <div class="ts-card-title">
                                        <template x-if="t.task_type === 'rack_check'">
                                            <span x-text="(t.rack_type === 'display' ? '🛍️ ' : '📦 ') + (t.rack_name || t.title || '(Tanpa rak)')"></span>
                                        </template>
                                        <template x-if="t.task_type !== 'rack_check'">
                                            <span x-text="t.title || '(Tanpa judul)'"></span>
                                        </template>
                                    </div>
                                    <div class="ts-card-meta">
                                        <span class="ts-badge ts-badge--time" x-text="timeLabel(t)"></span>
                                        <span class="ts-badge ts-badge--assign" x-text="assignLabel(t)"></span>
                                        <template x-if="t.task_type === 'rack_check'">
                                            <span class="ts-badge" style="background:#fce7f3; color:#9d174d;" title="Wajib scan QR rak">📷 QR</span>
                                        </template>
                                        <template x-if="t.category_name">
                                            <span class="ts-badge ts-badge--cat">
                                                <span class="ts-cat-dot" :style="'background:' + (categoryColor(t.category_id) || '#94a3b8')"></span>
                                                <span x-text="t.category_name"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <div class="ts-col-empty" x-show="cardsForSchedule(col).length === 0">Kosong</div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>

    {{-- Floating today FAB --}}
    <button class="ts-today-fab" @click="todayDrawer = true">
        📋 Tugas Hari Ini
        <span class="ts-today-badge" x-text="(todayTasks||[]).length"></span>
    </button>

    {{-- Drawer overlay --}}
    <div class="ts-drawer-overlay" :class="(drawer || todayDrawer) && 'is-open'" @click="closeAllDrawers()"></div>

    {{-- Drawer: edit / create --}}
    <aside class="ts-drawer" :class="drawer && 'is-open'" @click.stop>
        <header class="ts-drawer-header">
            <span class="ts-drawer-title">
                <template x-if="form.task_type === 'rack_check'">
                    <span x-text="form.id ? '✏️ Edit Cek Rak' : '➕ Cek Rak Baru'"></span>
                </template>
                <template x-if="form.task_type !== 'rack_check'">
                    <span x-text="form.id ? '✏️ Edit Tugas' : '➕ Tugas Baru'"></span>
                </template>
            </span>
            <button class="ts-drawer-close" @click="closeDrawer()">×</button>
        </header>
        <div class="ts-drawer-body">
            {{-- ── RACK SELECTOR (rack_check only) ─────────────────── --}}
            <template x-if="form.task_type === 'rack_check'">
                <div>
                    <div class="ts-field">
                        <label>Rak Target <span style="color:#dc2626">*</span></label>
                        <select x-model="form.rack_id" @change="(() => {
                                const r = rackById(form.rack_id);
                                if (r) {
                                    form.rack_name = r.name || '';
                                    form.rack_location = r.location || '';
                                    form.rack_barcode_value = r.barcode_value || '';
                                    form.rack_type = r.rack_type || 'storage';
                                    form.title = r.name || '';
                                }
                            })()" :disabled="form.id">
                            <option value="">- Pilih rak -</option>
                            <template x-for="r in racks.filter(x => x.is_active)" :key="r.id">
                                <option :value="r.id" :disabled="rackInUseByTemplate(r.id, form.id) ? true : false"
                                        x-text="(r.rack_type === 'display' ? '🛍️ ' : '📦 ') + r.name + (r.location ? ' • ' + r.location : '') + (rackInUseByTemplate(r.id, form.id) ? ' (sudah ada template)' : '')"></option>
                            </template>
                        </select>
                        <template x-if="form.id">
                            <div class="ts-hint">Rak template ini tidak bisa diganti. Hapus template dulu kalau mau pakai rak lain.</div>
                        </template>
                        <template x-if="!form.id">
                            <div class="ts-hint">Setiap rak hanya boleh punya 1 template aktif. Pilih rak yang belum dikunci.</div>
                        </template>
                    </div>
                    <template x-if="form.rack_id">
                        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 12px; margin-bottom:14px;">
                            <div style="font-weight:700; color:#0369a1; font-size:13px;">
                                <span x-text="form.rack_type === 'display' ? '🛍️' : '📦'"></span>
                                <span x-text="form.rack_name || '?'"></span>
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:3px;">
                                <span x-show="form.rack_location">📍 <span x-text="form.rack_location"></span></span>
                                <span x-show="form.rack_barcode_value" style="margin-left:8px;">🔖 <span x-text="form.rack_barcode_value"></span></span>
                            </div>
                            <div style="font-size:11px; color:#9d174d; margin-top:3px; font-weight:600;">
                                📷 Wajib scan QR rak saat eksekusi (otomatis)
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Title field — HIDDEN untuk rack_check (auto-set dari rack_name) --}}
            <div class="ts-field" x-show="form.task_type !== 'rack_check'">
                <label>Judul Tugas <span style="color:#dc2626">*</span></label>
                <input type="text" x-model="form.title" placeholder="Contoh: Bersihkan area bar" maxlength="255">
            </div>

            <div class="ts-field" x-show="form.task_type !== 'rack_check'">
                <label>Deskripsi</label>
                <textarea x-model="form.description" rows="2" placeholder="Detail (opsional)" maxlength="1000"></textarea>
            </div>

            <div class="ts-field-row" x-show="form.task_type !== 'rack_check'">
                <div class="ts-field">
                    <label>Kategori</label>
                    <select x-model="form.category_id" @change="syncCategoryName()">
                        <option value="">- Tanpa kategori -</option>
                        <template x-for="c in categories" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div class="ts-field">
                    <label>Prioritas</label>
                    <select x-model="form.priority">
                        <option value="urgent">🔴 Urgent</option>
                        <option value="normal">🔵 Normal</option>
                        <option value="low">⚪ Low</option>
                    </select>
                </div>
            </div>

            {{-- Kategori only untuk rack_check (tanpa prioritas, tanpa deskripsi) --}}
            <div class="ts-field" x-show="form.task_type === 'rack_check'">
                <label>Kategori</label>
                <select x-model="form.category_id" @change="syncCategoryName()">
                    <option value="">- Tanpa kategori -</option>
                    <template x-for="c in categories" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>

            <hr style="border:none; border-top:1px solid #f1f5f9; margin:14px 0;">
            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:8px;">⏰ Pengulangan & Jadwal</div>

            <div class="ts-field">
                <label>Frekuensi</label>
                <select x-model="form.recurrence_type" @change="onRecurrenceChange()">
                    <option value="daily">Harian</option>
                    <option value="weekly">Mingguan (hari tertentu)</option>
                    <option value="every_n_days">Setiap N hari</option>
                </select>
            </div>

            <div class="ts-field" x-show="form.recurrence_type === 'weekly'">
                <label>Hari</label>
                <select x-model="form.weekly_day" @change="snapAnchorToRecurrence()">
                    <option value="1">Senin</option>
                    <option value="2">Selasa</option>
                    <option value="3">Rabu</option>
                    <option value="4">Kamis</option>
                    <option value="5">Jumat</option>
                    <option value="6">Sabtu</option>
                    <option value="7">Minggu</option>
                </select>
            </div>

            <div class="ts-field" x-show="form.recurrence_type === 'every_n_days'">
                <label>Interval (hari)</label>
                <input type="number" x-model="form.interval_days" @blur="onIntervalChange()" min="2" max="365">
                <div class="ts-hint">Minimal 2. Untuk interval 1, gunakan frekuensi "Harian".</div>
            </div>

            <div class="ts-field">
                <label>Mode Jadwal</label>
                <select x-model="form.schedule_mode" @change="onScheduleModeChange()">
                    <option value="fixed">⏰ Jam tetap</option>
                    <option value="shift_relative">🔄 Ikuti shift waiter</option>
                </select>
                <div class="ts-hint">Mode "Ikuti Shift" = jam tugas mengikuti shift masing-masing waiter</div>
            </div>

            <div class="ts-field-row" x-show="form.schedule_mode === 'fixed'">
                <div class="ts-field">
                    <label>Jam mulai</label>
                    <input type="time" x-model="form.schedule_time">
                </div>
                <div class="ts-field">
                    <label>Batas (menit)</label>
                    <input type="number" x-model="form.time_limit_minutes" min="0" max="1440">
                </div>
            </div>

            <div class="ts-field-row" x-show="form.schedule_mode === 'shift_relative'">
                <div class="ts-field">
                    <label>Offset shift (menit)</label>
                    <input type="number" x-model="form.shift_offset_minutes" min="0" max="480">
                    <div class="ts-hint">Mis. 30 = muncul 30 menit setelah shift mulai</div>
                </div>
                <div class="ts-field">
                    <label>Deadline sebelum akhir (menit)</label>
                    <input type="number" x-model="form.deadline_before_end_minutes" min="0" max="480">
                </div>
            </div>

            <hr style="border:none; border-top:1px solid #f1f5f9; margin:14px 0;">
            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:8px;">👤 Penugasan</div>

            {{-- Unified mode selector: 4 mutually-exclusive paths --}}
            <div class="ts-field">
                <label>Mode penugasan</label>
                <select x-model="form.assignment_mode" @change="onAssignmentModeChange()">
                    <option value="role_all">Semua waiter dalam 1 role</option>
                    <option value="single">1 waiter spesifik (tetap)</option>
                    <option value="rolling">Rotasi giliran antar waiter (1 role)</option>
                    <option value="everyone">Semua waiter aktif (lintas role)</option>
                </select>
                <small style="color:#64748b; font-size:11px; display:block; margin-top:4px;" x-text="assignmentModeHint()"></small>
            </div>

            {{-- Role selector: shown unless mode=everyone --}}
            <div class="ts-field" x-show="form.assignment_mode !== 'everyone'">
                <label>Role waiter</label>
                <select x-model="form.assigned_waiter_role" @change="onRoleChange()">
                    <option value="pelayan">Pelayan</option>
                    <option value="kasir">Kasir</option>
                    <option value="finance">Finance</option>
                    <option value="backup">Backup</option>
                </select>
            </div>

            {{-- Single waiter selector --}}
            <div class="ts-field" x-show="form.assignment_mode === 'single'">
                <label>Pilih waiter</label>
                <select x-model="form.assigned_waiter_id">
                    <option value="">- Pilih waiter -</option>
                    <template x-for="w in waitersForRole(form.assigned_waiter_role)" :key="w.id">
                        <option :value="w.id" x-text="w.name"></option>
                    </template>
                </select>
            </div>

            {{-- Rolling rotation panel --}}
            <div x-show="form.assignment_mode === 'rolling'" style="background:#f8fafc; padding:12px; border-radius:8px; margin-top:8px; border:1px solid #e2e8f0;">
                <div style="font-size:11px; font-weight:700; color:#4338ca; margin-bottom:8px;">🔄 PENGATURAN ROTASI</div>

                <div class="ts-field">
                    <label>Periode rotasi</label>
                    <select x-model="form.rolling_period" @change="onRollingPeriodChange()">
                        <option value="daily" :disabled="form.recurrence_type !== 'daily'" x-bind:title="form.recurrence_type !== 'daily' ? 'Tidak masuk akal: tugas tidak harian' : ''">Harian (ganti tiap hari)</option>
                        <option value="weekly">Mingguan (ganti tiap minggu)</option>
                        <option value="monthly">Bulanan (ganti tiap bulan)</option>
                    </select>
                    <small style="color:#dc2626; font-size:11px; display:block; margin-top:4px;" x-show="form.recurrence_type === 'weekly' && form.rolling_period === 'daily'">
                        ⚠️ Tugas hanya generate sekali seminggu. Rotasi harian setara dengan rotasi mingguan tapi membingungkan.
                    </small>
                </div>

                <div class="ts-field">
                    <label>Mulai rotasi sejak</label>
                    <input type="date" x-model="form.rolling_anchor_date" @change="snapAnchorToRecurrence()">
                    <small style="color:#64748b; font-size:11px;">Hari ini = waiter pertama mendapat giliran. Default tanggal hari ini.</small>
                </div>

                <div class="ts-field">
                    <label>Urutan waiter giliran (role <strong x-text="form.assigned_waiter_role"></strong>)</label>
                    <small style="color:#64748b; font-size:11px; display:block; margin:-2px 0 6px;">
                        Hanya waiter dari role yang dipilih di atas yang muncul. Ubah role untuk pilih waiter dari role lain.
                    </small>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <template x-for="(wid, idx) in form.rolling_waiter_ids" :key="idx">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;" x-text="idx + 1"></span>
                                <select :value="wid" @change="form.rolling_waiter_ids[idx] = $event.target.value" style="flex:1;">
                                    <option value="">- Pilih waiter -</option>
                                    <template x-for="w in rollingWaiterOptions(idx)" :key="w.id">
                                        <option :value="w.id" :selected="w.id === wid" x-text="w.name"></option>
                                    </template>
                                </select>
                                <button type="button" @click="form.rolling_waiter_ids.splice(idx, 1)" class="ts-btn ts-btn--danger" style="padding:4px 8px; flex-shrink:0;" title="Hapus dari urutan">×</button>
                            </div>
                        </template>
                        <button type="button" class="ts-btn" @click="form.rolling_waiter_ids.push('')" style="margin-top:4px;" :disabled="rollingWaiterOptions(-1).length === 0">
                            + Tambah waiter ke giliran
                        </button>
                    </div>
                    <small style="color:#64748b; font-size:11px; display:block; margin-top:6px;" x-show="form.rolling_waiter_ids.filter(id => id).length >= 2">
                        🗓️ <span x-text="rotationPreview()"></span>
                    </small>
                    <small style="color:#dc2626; font-size:11px; display:block; margin-top:6px;" x-show="form.rolling_waiter_ids.filter(id => id).length < 2">
                        ⚠️ Rotasi butuh minimal 2 waiter berbeda.
                    </small>
                </div>
            </div>

            <hr style="border:none; border-top:1px solid #f1f5f9; margin:14px 0;">

            {{-- Shift filter (independent: works with all modes except hidden when rolling because rolling already narrows) --}}
            <div class="ts-field" x-show="form.assignment_mode !== 'rolling' && form.assignment_mode !== 'single'">
                <label>🕒 Khusus shift (opsional)</label>
                <select x-model="form.target_shift_id">
                    <option value="">— Tanpa filter shift —</option>
                    <template x-for="s in shifts" :key="s.id">
                        <option :value="s.id" x-text="s.name + ' (' + s.clock_in_time + ' - ' + s.clock_out_time + ')'"></option>
                    </template>
                </select>
                <small style="color:#64748b; font-size:11px; display:block; margin-top:4px;">
                    Hanya waiter yang dijadwalkan shift ini hari itu yang dapat tugas. Mismatch ditandai ⚠️ saja, tidak diblokir.
                </small>
            </div>

            <hr style="border:none; border-top:1px solid #f1f5f9; margin:14px 0;" x-show="form.assignment_mode !== 'rolling' && form.assignment_mode !== 'single'">

            <label class="ts-checkbox">
                <input type="checkbox" x-model="form.requires_photo_proof">
                📸 Wajib upload foto bukti
            </label>
            <label class="ts-checkbox">
                <input type="checkbox" x-model="form.requires_photo_before">
                📷 Wajib foto sebelum mulai
            </label>
            <label class="ts-checkbox" x-show="form.id">
                <input type="checkbox" x-model="form.is_active">
                ✅ Template aktif (auto-generate task)
            </label>
        </div>
        <footer class="ts-drawer-footer">
            <template x-if="form.id">
                <button class="ts-btn ts-btn--danger" @click="deleteTemplate(form, true)" style="margin-right:auto;">🗑️ Hapus</button>
            </template>
            <button class="ts-btn" @click="closeDrawer()">Batal</button>
            <button class="ts-btn ts-btn--primary" @click="saveTemplate()" :disabled="saving" x-text="saving ? 'Menyimpan…' : (form.id ? '💾 Simpan Perubahan' : '+ Buat Tugas')"></button>
        </footer>
    </aside>

    {{-- Drawer: today's tasks --}}
    <aside class="ts-drawer" :class="todayDrawer && 'is-open'" @click.stop>
        <header class="ts-drawer-header">
            <span class="ts-drawer-title">📋 Tugas Hari Ini</span>
            <button class="ts-drawer-close" @click="todayDrawer = false">×</button>
        </header>
        <div class="ts-drawer-body">
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button class="ts-btn" @click="loadTodayTasks()">🔄 Refresh</button>
                <button class="ts-btn" @click="selectAllToday()">☑️ Pilih semua pending</button>
                <button class="ts-btn ts-btn--danger" @click="cancelSelected()" :disabled="selectedToday.length === 0">
                    ❌ Cancel (<span x-text="selectedToday.length"></span>)
                </button>
            </div>
            <div x-show="todayTasks === null" style="text-align:center; padding:30px; color:#94a3b8;">Memuat…</div>
            <div x-show="todayTasks && todayTasks.length === 0" style="text-align:center; padding:30px; color:#94a3b8;">Belum ada tugas hari ini.</div>
            <template x-for="t in (todayTasks || [])" :key="t.id">
                <label style="display:flex; align-items:center; gap:10px; padding:10px; border-bottom:1px solid #f1f5f9; cursor:pointer;"
                       :style="t.status !== 'pending' && 'opacity:0.55; cursor:default'">
                    <input type="checkbox" :value="t.id" x-model="selectedToday" :disabled="t.status !== 'pending'">
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:600; font-size:13px; color:#0f172a;" x-text="t.title"></div>
                        <div style="font-size:11px; color:#64748b; margin-top:2px;">
                            👤 <span x-text="t.assigned_waiter_name || '-'"></span>
                            <template x-if="waiterLoadCount(t.assigned_waiter_id) >= 3">
                                <span style="background:#fee2e2; color:#991b1b; padding:1px 6px; border-radius:8px; font-weight:700; margin-left:6px;" :title="'Waiter ini dapat ' + waiterLoadCount(t.assigned_waiter_id) + ' tugas hari ini'">
                                    🔴 BEBAN BERAT (<span x-text="waiterLoadCount(t.assigned_waiter_id)"></span>)
                                </span>
                            </template>
                            <template x-if="waiterLoadCount(t.assigned_waiter_id) === 2">
                                <span style="background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:8px; font-weight:700; margin-left:6px;" :title="'Waiter ini dapat 2 tugas hari ini'">
                                    🟡 2 tugas
                                </span>
                            </template>
                            <template x-if="t.is_rolling_assignment">
                                <span style="color:#92400e; margin-left:6px;">🔄 rotasi</span>
                            </template>
                            <template x-if="t.is_off_day_assignment">
                                <span style="color:#dc2626; margin-left:6px;" title="Waiter ini sedang libur hari ini">⚠️ libur</span>
                            </template>
                            <template x-if="t.is_shift_mismatch">
                                <span style="color:#dc2626; margin-left:6px;" :title="'Tidak sesuai shift target: ' + (shiftName(t.target_shift_id) || '?')">⚠️ beda shift</span>
                            </template>
                        </div>
                    </div>
                    <span style="font-size:14px;" x-text="t.status === 'pending' ? '🟡' : (t.status === 'done' ? '✅' : (t.status === 'cancelled' ? '🚫' : '🔴'))"></span>
                </label>
            </template>
        </div>
    </aside>
</div>

<script>
function taskStudio() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const initialWaiters = @json($jsWaiters);
    const initialCategories = @json($jsCategories);
    const initialTemplates = @json($jsTemplates);
    const initialShifts = @json($jsShifts);
    const initialRacks = @json($jsRacks);
    const initialScope = @json($initialScope);

    return {
        // ─── State ────────────────────────────────
        scope: initialScope,         // 'general' | 'rack_check'
        waiters: initialWaiters,
        categories: initialCategories,
        templates: initialTemplates,
        shifts: initialShifts,
        racks: initialRacks,
        viewMode: 'role',
        search: '',
        freqFilter: 'all',
        waiterSearch: '',
        rackSearch: '',
        rackTypeFilter: 'all',
        selected: null,
        drawer: false,
        todayDrawer: false,
        addRackOpen: false,
        addRackForm: { name: '', location: '', rack_type: 'storage' },
        addRackSaving: false,
        saving: false,
        form: this.emptyForm ? this.emptyForm() : {},
        toast: { show: false, msg: '', type: 'success' },
        todayTasks: null,
        selectedToday: [],
        dragging: null,         // {kind: 'card'|'waiter'|'rack', payload}
        dragOverCol: null,

        // ─── Constants ────────────────────────────
        roleColumns: [
            { key: 'pelayan', label: '👤 Pelayan' },
            { key: 'kasir', label: '💰 Kasir' },
            { key: 'finance', label: '📊 Finance' },
            { key: 'backup', label: '🔄 Backup' },
            { key: 'inactive', label: '🚫 Tidak Aktif' },
        ],
        scheduleColumns: [
            { key: 'inactive', label: '🚫 Tidak Aktif', match: t => !t.is_active },
            { key: 'daily', label: '🔄 Harian', match: t => t.is_active && t.recurrence_type === 'daily' },
            { key: 'w1', label: '📅 Senin', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '1', recurrence: 'weekly', day: 1 },
            { key: 'w2', label: '📅 Selasa', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '2', recurrence: 'weekly', day: 2 },
            { key: 'w3', label: '📅 Rabu', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '3', recurrence: 'weekly', day: 3 },
            { key: 'w4', label: '📅 Kamis', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '4', recurrence: 'weekly', day: 4 },
            { key: 'w5', label: '📅 Jumat', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '5', recurrence: 'weekly', day: 5 },
            { key: 'w6', label: '📅 Sabtu', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '6', recurrence: 'weekly', day: 6 },
            { key: 'w7', label: '📅 Minggu', match: t => t.is_active && t.recurrence_type === 'weekly' && String(t.weekly_day) === '7', recurrence: 'weekly', day: 7 },
            { key: 'every_n', label: '🔢 Setiap N hari', match: t => t.is_active && t.recurrence_type === 'every_n_days' },
        ],

        // ─── Init ─────────────────────────────────
        init() {
            this.form = this.emptyForm();
            this.loadTodayTasks();
        },

        emptyForm() {
            const today = new Date().toISOString().slice(0, 10);
            const isRack = this.scope === 'rack_check';
            return {
                id: null,
                title: '',
                description: '',
                priority: 'normal',
                category_id: '',
                category_name: '',
                recurrence_type: 'daily',
                weekly_day: '1',
                interval_days: 3,
                schedule_mode: 'fixed',
                schedule_time: '09:00',
                shift_offset_minutes: 30,
                time_limit_minutes: 30,
                deadline_mode: 'fixed',
                deadline_before_end_minutes: 60,
                assignment_type: 'role',
                assignment_mode: 'role_all', // UI-level: role_all | single | rolling | everyone
                assigned_waiter_id: '',
                assigned_waiter_role: 'pelayan',
                requires_photo_proof: isRack ? true : false,
                requires_photo_before: false,
                is_active: true,
                rolling_enabled: false,
                rolling_period: 'weekly',
                rolling_waiter_ids: [],
                rolling_anchor_date: today,
                target_shift_id: '',
                // Rack-specific (only used when scope === 'rack_check')
                task_type: isRack ? 'rack_check' : 'general',
                rack_id: '',
                rack_name: '',
                rack_location: '',
                rack_barcode_value: '',
                rack_type: '',
                requires_barcode_scan: isRack,
            };
        },

        // ─── Helpers ──────────────────────────────
        showToast(msg, type = 'success') {
            this.toast = { show: true, msg, type };
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => this.toast.show = false, 3200);
        },

        // Filter templates by current scope (general vs rack_check).
        templatesInScope() {
            if (this.scope === 'rack_check') {
                return this.templates.filter(t => (t.task_type || 'general') === 'rack_check');
            }
            return this.templates.filter(t => (t.task_type || 'general') === 'general');
        },

        switchScope(newScope) {
            if (newScope !== 'general' && newScope !== 'rack_check') return;
            if (this.scope === newScope) return;
            this.scope = newScope;
            this.search = '';
            this.freqFilter = 'all';
            this.selectedToday = [];
            // Drawer mungkin terbuka dgn form scope lama, tutup biar tidak ambigu
            if (this.drawer) this.closeDrawer();
            // Update URL tanpa reload supaya bookmarkable
            try {
                const url = new URL(window.location);
                url.searchParams.set('scope', newScope);
                window.history.replaceState({}, '', url);
            } catch (e) {}
            this.loadTodayTasks();
        },

        scopeIsRack() {
            return this.scope === 'rack_check';
        },

        filteredRacks() {
            const q = this.rackSearch.trim().toLowerCase();
            return this.racks.filter(r => {
                if (!r.is_active) return false;
                if (this.rackTypeFilter !== 'all' && r.rack_type !== this.rackTypeFilter) return false;
                if (!q) return true;
                return (r.name || '').toLowerCase().includes(q)
                    || (r.location || '').toLowerCase().includes(q)
                    || (r.barcode_value || '').toLowerCase().includes(q);
            });
        },

        rackById(id) {
            return this.racks.find(r => r.id === id) || null;
        },

        rackInUseByTemplate(rackId, excludeId) {
            return this.templates.find(t =>
                (t.task_type || 'general') === 'rack_check'
                && t.is_active
                && t.rack_id === rackId
                && t.id !== excludeId
            ) || null;
        },

        rackTypeLabel(type) {
            if (type === 'storage') return '📦 Gudang';
            if (type === 'display') return '🛍️ Display';
            return type || '-';
        },

        async addRackInline() {
            const name = (this.addRackForm.name || '').trim();
            const location = (this.addRackForm.location || '').trim();
            if (name === '') {
                this.showToast('Nama rak wajib diisi', 'error');
                return;
            }
            this.addRackSaving = true;
            try {
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('name', name);
                fd.append('location', location);
                fd.append('rack_type', this.addRackForm.rack_type || 'storage');
                const res = await fetch('{{ route('admin.racks.ajax_store') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success && data.rack) {
                    this.racks.push({
                        id: data.rack.id,
                        name: data.rack.name || name,
                        location: data.rack.location || location,
                        barcode_value: data.rack.barcode_value || '',
                        rack_type: data.rack.rack_type || this.addRackForm.rack_type,
                        check_order: data.rack.check_order || 0,
                        is_active: true,
                    });
                    this.addRackForm = { name: '', location: '', rack_type: 'storage' };
                    this.addRackOpen = false;
                    this.showToast('✓ Rak baru ditambahkan', 'success');
                } else {
                    this.showToast((data && (data.message || data.error)) || 'Gagal tambah rak', 'error');
                }
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.addRackSaving = false;
            }
        },

        filteredWaiters() {
            const q = this.waiterSearch.trim().toLowerCase();
            if (!q) return this.waiters;
            return this.waiters.filter(w => w.name.toLowerCase().includes(q) || w.role.includes(q));
        },

        waitersForRole(role) {
            return this.waiters.filter(w => w.role === role);
        },

        categoryColor(id) {
            const cat = this.categories.find(c => c.id === id);
            return cat ? cat.color : null;
        },

        passesSearch(t) {
            if (this.freqFilter !== 'all' && t.recurrence_type !== this.freqFilter) return false;
            const q = this.search.trim().toLowerCase();
            if (!q) return true;
            return (t.title || '').toLowerCase().includes(q)
                || (t.description || '').toLowerCase().includes(q)
                || (t.category_name || '').toLowerCase().includes(q);
        },

        cardsForRole(roleKey) {
            const inScope = this.templatesInScope();
            if (roleKey === 'inactive') {
                return inScope.filter(t => !t.is_active && this.passesSearch(t));
            }
            return inScope.filter(t => t.is_active && (t.assigned_waiter_role || 'pelayan') === roleKey && this.passesSearch(t));
        },

        cardsForSchedule(col) {
            return this.templatesInScope().filter(t => col.match(t) && this.passesSearch(t));
        },

        freqLabel(t) {
            if (t.recurrence_type === 'daily') return '🔄 Harian';
            if (t.recurrence_type === 'weekly') {
                const days = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                return '📅 ' + (days[parseInt(t.weekly_day) || 1] || '-');
            }
            if (t.recurrence_type === 'every_n_days') return '🔢 Tiap ' + (t.interval_days || '?') + ' hari';
            return '?';
        },

        timeLabel(t) {
            if (t.schedule_mode === 'shift_relative') {
                return '🔄 +' + (t.shift_offset_minutes || 0) + 'm shift';
            }
            return '⏰ ' + (t.schedule_time || '--:--');
        },

        assignLabel(t) {
            if (t.assignment_type === 'single' && t.assigned_waiter_id) {
                const w = this.waiters.find(x => x.id === t.assigned_waiter_id);
                return '👤 ' + (w ? w.name : 'Orang');
            }
            if (t.assignment_type === 'role') return '👥 Role ' + (t.assigned_waiter_role || '?');
            return '🌐 Semua';
        },

        shiftName(id) {
            if (!id) return '';
            const s = this.shifts.find(x => x.id === id);
            return s ? s.name : '';
        },

        rollingShortLabel(t) {
            const period = ({ daily: 'harian', weekly: 'mingguan', monthly: 'bulanan' })[t.rolling_period] || 'rotasi';
            const ids = Array.isArray(t.rolling_waiter_ids) ? t.rolling_waiter_ids : [];
            return `${period} (${ids.length} orang)`;
        },

        rollingTooltip(t) {
            const ids = Array.isArray(t.rolling_waiter_ids) ? t.rolling_waiter_ids : [];
            const names = ids.map(id => {
                const w = this.waiters.find(x => x.id === id);
                return w ? w.name : '?';
            });
            const period = ({ daily: 'tiap hari', weekly: 'tiap minggu', monthly: 'tiap bulan' })[t.rolling_period] || '';
            return `Rotasi ${period}: ${names.join(' → ')}` + (t.rolling_anchor_date ? ` (mulai ${t.rolling_anchor_date})` : '');
        },

        // ─── Drawer ───────────────────────────────
        openDrawer(template, overrides) {
            if (template) {
                this.form = Object.assign(this.emptyForm(), template, {
                    weekly_day: String(template.weekly_day || '1'),
                    interval_days: template.interval_days || 3,
                    rolling_waiter_ids: Array.isArray(template.rolling_waiter_ids)
                        ? [...template.rolling_waiter_ids]
                        : (template.rolling_waiter_ids ? Object.values(template.rolling_waiter_ids) : []),
                    rolling_anchor_date: template.rolling_anchor_date || new Date().toISOString().slice(0, 10),
                });
                // Derive UI-level assignment_mode from raw fields
                this.form.assignment_mode = this.deriveAssignmentMode(this.form);
                this.selected = template.id;
                // Saat edit rack_check, set task_type & requires_barcode_scan
                if ((this.form.task_type || 'general') === 'rack_check') {
                    this.form.requires_barcode_scan = true;
                }
            } else {
                this.form = this.emptyForm();
                this.form.assignment_mode = 'role_all';
                this.selected = null;
            }
            if (overrides) Object.assign(this.form, overrides);
            this.drawer = true;
        },

        // Derive assignment_mode from raw fields
        deriveAssignmentMode(f) {
            if (f.rolling_enabled) return 'rolling';
            if (f.assignment_type === 'all') return 'everyone';
            if (f.assignment_type === 'single') return 'single';
            return 'role_all';
        },

        // Apply assignment_mode → underlying fields (called before save)
        applyAssignmentMode() {
            const mode = this.form.assignment_mode;
            // Auto-sync deadline_mode with schedule_mode at save time too (defense-in-depth)
            if (this.form.schedule_mode === 'fixed') {
                this.form.deadline_mode = 'fixed';
            } else {
                this.form.deadline_mode = 'before_shift_end';
            }
            // Clear stale target_shift_id when mode hides it
            if (mode === 'rolling' || mode === 'single') {
                this.form.target_shift_id = '';
            }
            if (mode === 'rolling') {
                this.form.assignment_type = 'role';
                this.form.rolling_enabled = true;
                this.form.assigned_waiter_id = '';
            } else if (mode === 'single') {
                this.form.assignment_type = 'single';
                this.form.rolling_enabled = false;
                this.form.rolling_waiter_ids = [];
            } else if (mode === 'everyone') {
                this.form.assignment_type = 'all';
                this.form.rolling_enabled = false;
                this.form.rolling_waiter_ids = [];
                this.form.assigned_waiter_id = '';
            } else { // role_all
                this.form.assignment_type = 'role';
                this.form.rolling_enabled = false;
                this.form.rolling_waiter_ids = [];
                this.form.assigned_waiter_id = '';
            }
        },

        onAssignmentModeChange() {
            const mode = this.form.assignment_mode;
            // Clear stale state when switching modes
            if (mode !== 'rolling') {
                this.form.rolling_waiter_ids = [];
            }
            if (mode !== 'single') {
                this.form.assigned_waiter_id = '';
            }
            // Rolling: bootstrap an empty slot if none and a default anchor
            if (mode === 'rolling' && this.form.rolling_waiter_ids.length === 0) {
                this.form.rolling_waiter_ids = ['', ''];
            }
            if (mode === 'rolling' && !this.form.rolling_anchor_date) {
                this.form.rolling_anchor_date = new Date().toISOString().slice(0, 10);
            }
            // Everyone: clear shift/role state to neutralize
            if (mode === 'everyone') {
                this.form.target_shift_id = '';
            }
        },

        onRoleChange() {
            // When user changes role and rolling is active, prune cross-role waiters
            if (this.form.assignment_mode === 'rolling') {
                const role = this.form.assigned_waiter_role;
                const before = (this.form.rolling_waiter_ids || []).filter(id => id);
                const after = before.filter(id => {
                    const w = this.waiters.find(x => x.id === id);
                    return !w || !w.role || w.role === role;
                });
                if (before.length !== after.length) {
                    this.form.rolling_waiter_ids = after.length > 0 ? after : ['', ''];
                    const removed = before.length - after.length;
                    this.showToast(`${removed} waiter dihapus dari rotasi karena role berubah`, 'success');
                }
            }
            // When user changes role and single is active, clear stale waiter selection
            if (this.form.assignment_mode === 'single' && this.form.assigned_waiter_id) {
                const w = this.waiters.find(x => x.id === this.form.assigned_waiter_id);
                if (!w || w.role !== this.form.assigned_waiter_role) {
                    this.form.assigned_waiter_id = '';
                }
            }
        },

        assignmentModeHint() {
            const mode = this.form.assignment_mode;
            if (mode === 'role_all') return 'Semua waiter dengan role ini yang ngeshift hari itu mendapat tugas. Cocok untuk tugas paralel.';
            if (mode === 'single') return 'Hanya 1 waiter spesifik yang dipilih. Tetap sama tiap hari.';
            if (mode === 'rolling') return 'Tugas dilempar bergiliran ke daftar waiter yang dipilih. Hanya 1 waiter aktif per periode.';
            if (mode === 'everyone') return 'Semua waiter aktif di sistem (lintas role) mendapat tugas paralel. Jarang dipakai.';
            return '';
        },

        closeDrawer() {
            this.drawer = false;
            this.selected = null;
        },

        closeAllDrawers() {
            this.drawer = false;
            this.todayDrawer = false;
            this.selected = null;
        },

        syncCategoryName() {
            const cat = this.categories.find(c => c.id === this.form.category_id);
            this.form.category_name = cat ? cat.name : '';
        },

        // ─── Save / Delete / Toggle ───────────────
        async saveTemplate() {
            // Translate UI mode → underlying fields BEFORE validation
            this.applyAssignmentMode();

            // Pre-flight conflict validation
            const errs = this.validateForm();
            if (errs.length > 0) {
                this.showToast(errs[0], 'error');
                return;
            }

            // Soft warnings (allow save with confirmation)
            const warns = this.collectWarnings();
            if (warns.length > 0) {
                const msg = '⚠️ Peringatan:\n\n' + warns.map((w, i) => `${i + 1}. ${w}`).join('\n\n') + '\n\nLanjutkan simpan?';
                if (!confirm(msg)) return;
            }

            this.syncCategoryName();
            this.saving = true;

            try {
                if (this.form.id) {
                    await this.updateTemplate();
                } else {
                    await this.createTemplate();
                }
            } catch (e) {
                this.showToast(e.message || 'Gagal menyimpan', 'error');
            } finally {
                this.saving = false;
            }
        },

        // ── HARD ERRORS: blocking save ──────────────────────────────
        validateForm() {
            const e = [];
            const f = this.form;
            const isRack = (f.task_type === 'rack_check') || this.scope === 'rack_check';

            if (isRack) {
                // Rack-specific hard errors
                if (!f.rack_id) e.push('Pilih rak target untuk tugas cek rak');
                if (f.rack_id) {
                    const r = this.rackById(f.rack_id);
                    if (!r || !r.is_active) e.push('Rak yang dipilih tidak valid atau sudah dinonaktifkan');
                    const inUse = this.rackInUseByTemplate(f.rack_id, f.id);
                    if (inUse) {
                        e.push(`Rak ini sudah punya template aktif lain ("${inUse.title || inUse.rack_name || '?'}"). Hapus/nonaktifkan dulu sebelum buat baru.`);
                    }
                }
                // Title bukan field user-input untuk rack_check, tapi tetap di-set dari rack_name di backend
            } else {
                if (!f.title.trim()) e.push('Judul wajib diisi');
            }
            if (f.assignment_mode === 'single' && !f.assigned_waiter_id) {
                e.push('Pilih satu waiter spesifik untuk mode tetap');
            }
            if (f.recurrence_type === 'weekly' && !f.weekly_day) e.push('Pilih hari mingguan');
            if (f.recurrence_type === 'every_n_days' && (!f.interval_days || f.interval_days < 1)) {
                e.push('Interval hari minimal 1');
            }
            if (f.schedule_mode === 'fixed' && !f.schedule_time) {
                e.push('Jam mulai wajib diisi pada mode jadwal tetap');
            }
            if (f.deadline_mode === 'fixed' && (!f.time_limit_minutes || f.time_limit_minutes < 1)) {
                e.push('Batas waktu pengerjaan minimal 1 menit pada mode deadline tetap. Untuk deadline mengikuti shift, ubah ke mode "before_shift_end".');
            }

            // Rolling validation (mode is guaranteed 'rolling' here = applyAssignmentMode set assignment_type=role)
            if (f.assignment_mode === 'rolling') {
                const cleanIds = (f.rolling_waiter_ids || []).filter(id => id);
                if (cleanIds.length < 2) e.push('Rotasi butuh minimal 2 waiter berbeda pada urutan giliran');
                if (cleanIds.length !== new Set(cleanIds).size) e.push('Waiter rotasi tidak boleh duplikat');
                if (!f.rolling_anchor_date) e.push('Tanggal mulai rotasi wajib diisi');
                if (!['daily', 'weekly', 'monthly'].includes(f.rolling_period)) {
                    e.push('Periode rotasi harus harian/mingguan/bulanan');
                }

                // Period coherence check
                if (f.recurrence_type === 'weekly' && f.rolling_period === 'daily') {
                    e.push('Periode rotasi "harian" tidak masuk akal untuk tugas mingguan. Pilih "mingguan" atau "bulanan".');
                }
                if (f.recurrence_type === 'every_n_days' && f.rolling_period === 'daily' && (parseInt(f.interval_days) || 1) > 1) {
                    e.push(`Periode rotasi "harian" tidak masuk akal untuk tugas tiap ${f.interval_days} hari.`);
                }

                // Anchor day-of-week must match weekly_day for weekly recurrence
                if (f.recurrence_type === 'weekly' && f.rolling_period === 'weekly' && f.rolling_anchor_date) {
                    const d = new Date(f.rolling_anchor_date + 'T00:00:00');
                    const targetDow = parseInt(f.weekly_day) || 1;
                    const jsDow = d.getDay() === 0 ? 7 : d.getDay();
                    if (jsDow !== targetDow) {
                        const dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                        e.push(`Tanggal mulai rotasi (${dayNames[jsDow]}) harus jatuh pada hari ${dayNames[targetDow]}. Tugas mingguan hanya generate di hari itu.`);
                    }
                }
            }

            return e;
        },

        // ── SOFT WARNINGS: allow with confirm ───────────────────────
        collectWarnings() {
            const w = [];
            const f = this.form;

            // Duplicate title detection
            const titleNorm = f.title.trim().toLowerCase().replace(/\s+/g, ' ');
            const dups = this.templates.filter(t =>
                t.id !== f.id
                && (t.title || '').trim().toLowerCase().replace(/\s+/g, ' ') === titleNorm
                && t.is_active
            );
            if (dups.length > 0) {
                w.push(`Sudah ada ${dups.length} template aktif dengan judul "${f.title}". Pertimbangkan rename agar tidak duplikat.`);
            }

            // Same time + same role overlap
            if (f.schedule_mode === 'fixed' && (f.assignment_mode === 'role_all' || f.assignment_mode === 'rolling')) {
                const overlapping = this.templates.filter(t =>
                    t.id !== f.id
                    && t.is_active
                    && t.schedule_mode === 'fixed'
                    && t.schedule_time === f.schedule_time
                    && (t.assignment_type === 'role')
                    && t.assigned_waiter_role === f.assigned_waiter_role
                );
                if (overlapping.length > 0) {
                    w.push(`Ada ${overlapping.length} template lain pada role ${f.assigned_waiter_role} di jam ${f.schedule_time}. Risiko tabrakan jadwal.`);
                }
            }

            // Time limit kosong with deadline_mode=fixed -- already hard error, skip
            // shift_relative + shift_offset_minutes very small
            if (f.schedule_mode === 'shift_relative' && f.shift_offset_minutes < 5 && f.shift_offset_minutes > 0) {
                w.push(`Offset hanya ${f.shift_offset_minutes} menit setelah shift mulai. Waiter mungkin belum siap. Disarankan minimal 15 menit.`);
            }

            // Anchor di masa depan (rolling)
            if (f.assignment_mode === 'rolling' && f.rolling_anchor_date) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const anchor = new Date(f.rolling_anchor_date + 'T00:00:00');
                const diffDays = Math.round((anchor - today) / 86400000);
                if (diffDays > 14) {
                    w.push(`Tanggal mulai rotasi (${f.rolling_anchor_date}) ${diffDays} hari ke depan. Tugas tidak akan generate sampai tanggal itu.`);
                }
            }

            // Role tanpa waiter aktif
            if ((f.assignment_mode === 'role_all' || f.assignment_mode === 'rolling') && f.assigned_waiter_role) {
                const count = this.waiters.filter(x => x.role === f.assigned_waiter_role).length;
                if (count === 0) {
                    w.push(`Tidak ada waiter aktif dengan role "${f.assigned_waiter_role}". Tugas tidak akan ter-generate sampai ada waiter yang ditambahkan ke role ini.`);
                }
            }

            return w;
        },

        // Count how many pending tasks a waiter has in today's task list.
        // Used to flag heavy-load waiters in today drawer.
        waiterLoadCount(waiterId) {
            if (!waiterId || !this.todayTasks) return 0;
            return this.todayTasks.filter(t =>
                t.assigned_waiter_id === waiterId && t.status === 'pending'
            ).length;
        },

        rollingWaiterOptions(currentIndex) {
            // Show waiters of selected role, EXCLUDING those already picked in
            // other slots (so each select dropdown can't pick a duplicate).
            // currentIndex = -1 means "checking if any waiter still available" (for + button)
            const role = this.form.assigned_waiter_role;
            const pool = this.waiters.filter(w => w.role === role);
            const picked = new Set(
                (this.form.rolling_waiter_ids || [])
                    .map((id, i) => i !== currentIndex ? id : null)
                    .filter(Boolean)
            );
            return pool.filter(w => !picked.has(w.id));
        },

        cleanRollingMismatch() {
            // Legacy alias: kept for any leftover @change handlers; delegates to onRoleChange.
            this.onRoleChange();
        },

        onRollingToggle() {
            // Legacy: rolling is now controlled via assignment_mode select; this is a no-op.
        },

        rotationPreview() {
            const ids = (this.form.rolling_waiter_ids || []).filter(id => id);
            if (ids.length < 2) return '';
            const names = ids.map(id => {
                const w = this.waiters.find(x => x.id === id);
                return w ? w.name : '?';
            });
            // Compute first 3 generation dates from anchor
            const dates = this.firstThreeDates();
            if (dates.length === 0) {
                const period = ({ daily: 'tiap hari', weekly: 'tiap minggu', monthly: 'tiap bulan' })[this.form.rolling_period] || '';
                return 'Rotasi ' + period + ': ' + names.join(' → ') + ' → ' + names[0] + ' (lanjut)';
            }
            // Show concrete: date → waiter
            const lines = dates.map((d, i) => {
                const waiterName = names[i % names.length];
                const dayName = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][d.getDay()];
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][d.getMonth()];
                return `${dayName} ${dd} ${mm} → ${waiterName}`;
            });
            return lines.join(' • ') + ' • ...';
        },

        // Compute the next 3 task generation dates from anchor
        firstThreeDates() {
            const anchorStr = this.form.rolling_anchor_date;
            if (!anchorStr) return [];
            const anchor = new Date(anchorStr + 'T00:00:00');
            if (isNaN(anchor.getTime())) return [];
            const dates = [];
            const period = this.form.rolling_period;
            const recur = this.form.recurrence_type;

            if (recur === 'weekly') {
                // Weekly task: snap anchor forward to next occurrence of weekly_day
                const targetDow = parseInt(this.form.weekly_day) || 1; // 1=Mon..7=Sun
                let d = new Date(anchor);
                // JavaScript getDay: 0=Sun, 1=Mon..6=Sat → convert to 1..7
                const jsDow = d.getDay() === 0 ? 7 : d.getDay();
                const diff = (targetDow - jsDow + 7) % 7;
                d.setDate(d.getDate() + diff);
                for (let i = 0; i < 3; i++) {
                    dates.push(new Date(d));
                    d.setDate(d.getDate() + 7);
                }
            } else if (recur === 'every_n_days') {
                const n = parseInt(this.form.interval_days) || 1;
                let d = new Date(anchor);
                for (let i = 0; i < 3; i++) {
                    dates.push(new Date(d));
                    d.setDate(d.getDate() + n);
                }
            } else { // daily
                let d = new Date(anchor);
                for (let i = 0; i < 3; i++) {
                    dates.push(new Date(d));
                    d.setDate(d.getDate() + 1);
                }
            }
            return dates;
        },

        // Auto-snap anchor to next valid weekly_day if recurrence=weekly
        snapAnchorToRecurrence() {
            if (this.form.recurrence_type !== 'weekly') return;
            if (!this.form.rolling_anchor_date) return;
            const anchor = new Date(this.form.rolling_anchor_date + 'T00:00:00');
            if (isNaN(anchor.getTime())) return;
            const targetDow = parseInt(this.form.weekly_day) || 1; // 1=Mon..7=Sun
            const jsDow = anchor.getDay() === 0 ? 7 : anchor.getDay();
            if (jsDow === targetDow) return; // already aligned
            const diff = (targetDow - jsDow + 7) % 7;
            const newAnchor = new Date(anchor);
            newAnchor.setDate(newAnchor.getDate() + diff);
            // Format manually to avoid timezone shift from toISOString
            const y = newAnchor.getFullYear();
            const m = String(newAnchor.getMonth() + 1).padStart(2, '0');
            const d = String(newAnchor.getDate()).padStart(2, '0');
            const newStr = `${y}-${m}-${d}`;
            this.form.rolling_anchor_date = newStr;
            const dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
            this.showToast(`Tanggal mulai disesuaikan ke ${dayNames[targetDow]} terdekat (${newStr})`, 'success');
        },

        onRecurrenceChange() {
            // When recurrence changes, narrow rolling_period defaults & snap anchor
            if (this.form.recurrence_type === 'weekly') {
                if (this.form.rolling_period === 'daily') {
                    this.form.rolling_period = 'weekly';
                }
                this.snapAnchorToRecurrence();
            } else if (this.form.recurrence_type === 'every_n_days') {
                if (this.form.rolling_period === 'daily' && (parseInt(this.form.interval_days) || 1) > 1) {
                    this.form.rolling_period = 'weekly';
                }
            }
        },

        onScheduleModeChange() {
            // Auto-sync deadline_mode with schedule_mode (they're 1:1 coupled).
            // fixed schedule → fixed deadline (uses time_limit_minutes)
            // shift_relative schedule → before_shift_end deadline (uses deadline_before_end_minutes)
            if (this.form.schedule_mode === 'fixed') {
                this.form.deadline_mode = 'fixed';
                if (!this.form.time_limit_minutes || this.form.time_limit_minutes < 1) {
                    this.form.time_limit_minutes = 30;
                }
            } else {
                this.form.deadline_mode = 'before_shift_end';
                if (!this.form.deadline_before_end_minutes || this.form.deadline_before_end_minutes < 1) {
                    this.form.deadline_before_end_minutes = 60;
                }
            }
        },

        onIntervalChange() {
            // every_n_days + interval=1 = daily, suggest user switch
            if (this.form.recurrence_type === 'every_n_days' && parseInt(this.form.interval_days) === 1) {
                this.form.recurrence_type = 'daily';
                this.form.interval_days = 3; // reset to default for next time
                this.showToast('Interval 1 hari = harian. Frekuensi otomatis diubah ke "Harian".', 'success');
            }
        },

        onRollingPeriodChange() {
            // If user picks daily but recurrence isn't daily, gently flip back
            if (this.form.rolling_period === 'daily' && this.form.recurrence_type !== 'daily') {
                this.showToast('Periode rotasi "harian" tidak tersedia untuk tugas non-harian. Diubah ke mingguan.', 'error');
                this.form.rolling_period = 'weekly';
            }
        },

        appendRollingFields(fd) {
            if (this.form.rolling_enabled) {
                fd.append('rolling_enabled', '1');
                fd.append('rolling_period', this.form.rolling_period || 'weekly');
                fd.append('rolling_anchor_date', this.form.rolling_anchor_date || '');
                const cleanIds = (this.form.rolling_waiter_ids || []).filter(id => id);
                cleanIds.forEach(id => fd.append('rolling_waiter_ids[]', id));
            } else {
                fd.append('rolling_enabled', '0');
            }
            if (this.form.target_shift_id) {
                fd.append('target_shift_id', this.form.target_shift_id);
            } else {
                fd.append('target_shift_id', '');
            }
        },

        async createTemplate() {
            // POST admin.tasks.store via FormData (mirip submit form classic)
            const f = this.form;
            const isRack = (f.task_type === 'rack_check') || this.scope === 'rack_check';
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('task_scope', isRack ? 'rack_check' : 'general');
            fd.append('task_type', isRack ? 'rack_check' : 'general');
            if (isRack) {
                // Title backend builds dari rack_name; kirim placeholder untuk debug.
                const rack = this.rackById(f.rack_id);
                if (rack) {
                    fd.append('rack_id', rack.id);
                    fd.append('rack_ids[]', rack.id);
                    // Title fallback (backend akan override pakai buildRackCheckTaskTitle)
                    fd.append('title', rack.name || '');
                } else {
                    fd.append('title', '');
                }
                fd.append('description', '');
                fd.append('priority', 'normal');
                fd.append('rack_target_scope', 'single');
                if (f.requires_photo_proof) fd.append('requires_photo_proof', '1');
                if (f.requires_photo_before) fd.append('requires_photo_before', '1');
            } else {
                fd.append('title', f.title);
                fd.append('description', f.description || '');
                fd.append('priority', f.priority);
                if (f.requires_photo_proof) fd.append('requires_photo_proof', '1');
                if (f.requires_photo_before) fd.append('requires_photo_before', '1');
            }
            fd.append('category_id', f.category_id || '');
            fd.append('category_name', f.category_name || '');
            fd.append('is_recurring', '1');
            fd.append('recurrence_type', f.recurrence_type);
            if (f.recurrence_type === 'weekly') fd.append('weekly_day', f.weekly_day);
            if (f.recurrence_type === 'every_n_days') fd.append('interval_days', String(f.interval_days));
            fd.append('schedule_mode', f.schedule_mode);
            if (f.schedule_mode === 'fixed') {
                fd.append('schedule_time', f.schedule_time);
                fd.append('time_limit_minutes', String(f.time_limit_minutes));
            } else {
                fd.append('shift_offset_minutes', String(f.shift_offset_minutes));
                fd.append('deadline_mode', 'before_shift_end');
                fd.append('deadline_before_end_minutes', String(f.deadline_before_end_minutes));
                fd.append('schedule_time', f.schedule_time || '00:00');
                fd.append('time_limit_minutes', String(f.time_limit_minutes));
            }
            fd.append('assignment_type', f.assignment_type);
            fd.append('assigned_waiter_role', f.assigned_waiter_role || '');
            // Untuk rack_check + rolling: backend mode lama pakai role_assignment_mode='rolling'.
            // Studio kirim rolling_enabled=1 (general-style) yang sudah dihandle backend.
            if (f.assignment_type === 'single') {
                fd.append('assigned_waiter_id', f.assigned_waiter_id);
            }
            this.appendRollingFields(fd);

            const res = await fetch('{{ route('admin.tasks.store') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json, text/html', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
                redirect: 'manual',
            });

            // Endpoint lama balikin redirect; treat opaqueredirect / 302 / 200 sebagai sukses, lalu reload page (paling reliable, krn POST tidak balikin id template baru).
            if (res.type === 'opaqueredirect' || res.status === 302 || res.status === 200 || res.status === 201) {
                this.showToast('✓ Template berhasil dibuat, memuat ulang…', 'success');
                setTimeout(() => window.location.reload(), 600);
                return;
            }
            if (res.status === 422) {
                let msg = 'Validasi gagal';
                try {
                    const data = await res.json();
                    if (data.errors) msg = Object.values(data.errors).flat().join('; ');
                    else if (data.message) msg = data.message;
                } catch (e) { /* HTML response */ }
                throw new Error(msg);
            }
            throw new Error('Server error: ' + res.status);
        },

        async updateTemplate() {
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('_method', 'PUT');
            fd.append('title', this.form.title);
            fd.append('description', this.form.description || '');
            fd.append('priority', this.form.priority);
            fd.append('category_id', this.form.category_id || '');
            fd.append('category_name', this.form.category_name || '');
            fd.append('recurrence_type', this.form.recurrence_type);
            if (this.form.recurrence_type === 'weekly') fd.append('weekly_day', this.form.weekly_day);
            if (this.form.recurrence_type === 'every_n_days') fd.append('interval_days', String(this.form.interval_days));
            fd.append('schedule_mode', this.form.schedule_mode);
            if (this.form.schedule_mode === 'fixed') {
                fd.append('schedule_time', this.form.schedule_time);
                fd.append('time_limit_minutes', String(this.form.time_limit_minutes));
                fd.append('deadline_mode', 'fixed');
            } else {
                fd.append('shift_offset_minutes', String(this.form.shift_offset_minutes));
                fd.append('deadline_mode', 'before_shift_end');
                fd.append('deadline_before_end_minutes', String(this.form.deadline_before_end_minutes));
            }
            if (this.form.is_active) fd.append('is_active', '1');
            this.appendRollingFields(fd);

            const url = '/admin/tasks/recurring/' + this.form.id;
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json, text/html', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
                redirect: 'manual',
            });

            if (res.type === 'opaqueredirect' || res.status === 302 || res.status === 200) {
                // Update fields in-place (optimistic). Field penugasan dihandle terpisah via PATCH schedule.
                const t = this.templates.find(x => x.id === this.form.id);
                if (t) {
                    Object.assign(t, {
                        title: this.form.title,
                        description: this.form.description,
                        priority: this.form.priority,
                        category_id: this.form.category_id,
                        category_name: this.form.category_name,
                        recurrence_type: this.form.recurrence_type,
                        weekly_day: this.form.recurrence_type === 'weekly' ? parseInt(this.form.weekly_day) : null,
                        interval_days: this.form.recurrence_type === 'every_n_days' ? parseInt(this.form.interval_days) : null,
                        schedule_mode: this.form.schedule_mode,
                        schedule_time: this.form.schedule_time,
                        shift_offset_minutes: parseInt(this.form.shift_offset_minutes) || 0,
                        time_limit_minutes: parseInt(this.form.time_limit_minutes) || 0,
                        deadline_before_end_minutes: parseInt(this.form.deadline_before_end_minutes) || 0,
                        is_active: !!this.form.is_active,
                        rolling_enabled: !!this.form.rolling_enabled,
                        rolling_period: this.form.rolling_period,
                        rolling_waiter_ids: (this.form.rolling_waiter_ids || []).filter(id => id),
                        rolling_anchor_date: this.form.rolling_anchor_date || '',
                        target_shift_id: this.form.target_shift_id || '',
                    });
                }
                await this.patchAssignment();
                if (t) {
                    t.assignment_type = this.form.assignment_type;
                    t.assigned_waiter_role = this.form.assigned_waiter_role;
                    t.assigned_waiter_id = this.form.assignment_type === 'single' ? this.form.assigned_waiter_id : '';
                }
                this.showToast('✓ Tersimpan', 'success');
                this.closeDrawer();
                return;
            }
            if (res.status === 422) {
                let msg = 'Validasi gagal';
                try {
                    const data = await res.json();
                    if (data.errors) msg = Object.values(data.errors).flat().join('; ');
                } catch (e) {}
                throw new Error(msg);
            }
            throw new Error('Server error: ' + res.status);
        },

        async patchAssignment() {
            const patch = {
                assignment_type: this.form.assignment_type,
                assigned_waiter_role: this.form.assigned_waiter_role || '',
            };
            if (this.form.assignment_type === 'single') {
                patch.assigned_waiter_id = this.form.assigned_waiter_id;
            }
            patch.target_shift_id = this.form.target_shift_id || '';
            patch.rolling_enabled = !!this.form.rolling_enabled;
            if (this.form.rolling_enabled) {
                patch.rolling_period = this.form.rolling_period || 'weekly';
                patch.rolling_waiter_ids = (this.form.rolling_waiter_ids || []).filter(id => id);
                patch.rolling_anchor_date = this.form.rolling_anchor_date || '';
            }
            try {
                await fetch('/admin/tasks/recurring/' + this.form.id + '/schedule', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(patch),
                });
            } catch (e) { /* non-fatal */ }
        },

        async deleteTemplate(t, fromDrawer = false) {
            if (!confirm('Hapus template "' + (t.title || 'Tanpa judul') + '"? Task pending akan di-cancel otomatis.')) return;
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (data.success) {
                    this.templates = this.templates.filter(x => x.id !== t.id);
                    this.showToast('Template dihapus' + (data.cancelled_tasks ? ' (' + data.cancelled_tasks + ' task pending di-cancel)' : ''));
                    if (fromDrawer) this.closeDrawer();
                } else {
                    this.showToast(data.message || 'Gagal menghapus', 'error');
                }
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        async toggleActive(t) {
            const newState = !t.is_active;
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id + '/schedule', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ is_active: newState }),
                });
                const data = await res.json();
                if (data.success) {
                    t.is_active = newState;
                    this.showToast(newState ? '▶ Aktif' : '⏸ Nonaktif');
                } else {
                    this.showToast(data.message || 'Gagal', 'error');
                }
            } catch (e) { this.showToast(e.message, 'error'); }
        },

        async forceGenerate(t) {
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id + '/force-generate', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                const generated = data.generated || 0;
                this.showToast('🚀 Generated ' + generated + ' task' + (generated === 0 ? ' (cek log: shift / day-off / sudah ada)' : ''), generated > 0 ? 'success' : 'error');
                this.loadTodayTasks();
            } catch (e) { this.showToast(e.message, 'error'); }
        },

        // ─── Drag & Drop ──────────────────────────
        dragWaiterStart(e, w) {
            this.dragging = { kind: 'waiter', payload: w };
            e.dataTransfer.effectAllowed = 'link';
            e.target.classList.add('is-dragging');
        },
        dragRackStart(e, r) {
            this.dragging = { kind: 'rack', payload: r };
            e.dataTransfer.effectAllowed = 'link';
            e.target.classList.add('is-dragging');
        },
        dragWaiterEnd(e) {
            this.dragging = null;
            this.dragOverCol = null;
            e.target.classList.remove('is-dragging');
        },
        dragCardStart(e, t) {
            this.dragging = { kind: 'card', payload: t };
            e.dataTransfer.effectAllowed = 'move';
            e.target.classList.add('is-dragging');
        },
        dragCardEnd(e) {
            this.dragging = null;
            this.dragOverCol = null;
            e.target.classList.remove('is-dragging');
        },

        async dropOnRole(roleKey, e) {
            this.dragOverCol = null;
            if (!this.dragging) return;

            // Drop rack from palette → buka drawer create rack_check pre-filled dgn role
            if (this.dragging.kind === 'rack' && roleKey !== 'inactive') {
                const r = this.dragging.payload;
                this.openDrawer(null, {
                    rack_id: r.id,
                    rack_name: r.name,
                    rack_location: r.location,
                    rack_barcode_value: r.barcode_value,
                    rack_type: r.rack_type,
                    task_type: 'rack_check',
                    requires_barcode_scan: true,
                    requires_photo_proof: true,
                    assigned_waiter_role: roleKey,
                    assignment_type: 'role',
                });
                this.dragging = null;
                return;
            }

            // Drop card → ubah role / activeness
            if (this.dragging.kind === 'card') {
                const t = this.dragging.payload;
                const patch = {};
                if (roleKey === 'inactive') {
                    patch.is_active = false;
                } else {
                    patch.is_active = true;
                    patch.assigned_waiter_role = roleKey;
                    if (t.assignment_type === 'single') {
                        // role ganti tapi waiter spesifik mungkin di role lain → reset ke role-mode
                        patch.assignment_type = 'role';
                        patch.assigned_waiter_id = '';
                    }
                }
                await this.applyPatch(t, patch);
            }
        },

        async dropOnSchedule(col, e) {
            this.dragOverCol = null;
            if (!this.dragging || this.dragging.kind !== 'card') return;
            const t = this.dragging.payload;
            const patch = {};
            if (col.key === 'inactive') {
                patch.is_active = false;
            } else if (col.key === 'daily') {
                patch.is_active = true;
                patch.recurrence_type = 'daily';
            } else if (col.recurrence === 'weekly') {
                patch.is_active = true;
                patch.recurrence_type = 'weekly';
                patch.weekly_day = col.day;
            } else if (col.key === 'every_n') {
                patch.is_active = true;
                patch.recurrence_type = 'every_n_days';
                if (!t.interval_days || t.interval_days < 1) patch.interval_days = 3;
            }
            await this.applyPatch(t, patch);
        },

        async applyPatch(t, patch) {
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id + '/schedule', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(patch),
                });
                const data = await res.json();
                if (data.success) {
                    Object.assign(t, patch);
                    this.showToast('✓ Diperbarui');
                } else {
                    this.showToast(data.message || 'Gagal', 'error');
                }
            } catch (e) { this.showToast(e.message, 'error'); }
        },

        // ─── Today tasks ──────────────────────────
        async loadTodayTasks() {
            try {
                const url = '/admin/tasks/today-generated?scope=' + encodeURIComponent(this.scope);
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.todayTasks = await res.json();
            } catch (e) {
                this.todayTasks = [];
            }
        },

        selectAllToday() {
            const pendingIds = (this.todayTasks || [])
                .filter(t => t.status === 'pending')
                .map(t => t.id);
            if (this.selectedToday.length === pendingIds.length) {
                this.selectedToday = [];
            } else {
                this.selectedToday = pendingIds;
            }
        },

        async cancelSelected() {
            if (this.selectedToday.length === 0) return;
            if (!confirm('Cancel ' + this.selectedToday.length + ' tugas hari ini?')) return;
            const ids = [...this.selectedToday];
            for (const id of ids) {
                try {
                    await fetch('/admin/tasks/' + id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                } catch (e) { /* continue */ }
            }
            this.selectedToday = [];
            this.showToast(ids.length + ' tugas di-cancel');
            this.loadTodayTasks();
        },
    };
}
</script>
@endsection
