@extends('admin.layout')

@section('title', '{{ $scope === "rack_check" ? "Board Cek Rak" : "Board Tugas Umum" }}')

@push('styles')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
.role-col { flex:0 0 240px; min-width:220px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0; }
.role-col-body { padding:8px; display:flex; flex-direction:column; gap:8px; max-height:70vh; overflow-y:auto; }
.task-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; transition:box-shadow 0.15s; }
.task-card:hover { box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.badge { font-size:11px; padding:2px 6px; border-radius:4px; font-weight:600; display:inline-block; }
.badge-freq { background:#dbeafe; color:#1e40af; }
.badge-assign { background:#f3e8ff; color:#6b21a8; }
.badge-time { background:#ecfdf5; color:#065f46; }
.card-btn { background:none; border:1px solid #e2e8f0; border-radius:4px; padding:4px 8px; cursor:pointer; font-size:12px; transition:background 0.15s; }
.card-btn:hover { background:#f1f5f9; }
.filter-btn { background:#f1f5f9; border:1px solid #e2e8f0; padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer; font-weight:500; }
.modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; display:flex; align-items:center; justify-content:center; }
.modal-box { background:#fff; border-radius:12px; padding:24px; width:100%; max-width:420px; max-height:85vh; overflow-y:auto; margin:16px; }
.modal-box label { display:block; font-size:13px; font-weight:600; margin-bottom:4px; color:#334155; }
.modal-box input, .modal-box select, .modal-box textarea { width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:6px; font-size:14px; margin-bottom:12px; }
</style>
@endpush

@section('content')
@php
    $jsWaiters = collect($waiters ?? [])->filter(fn($w) => !empty($w['is_active']))->map(fn($w) => ['id' => $w['id'], 'name' => $w['name'] ?? '-', 'role' => strtolower($w['waiter_role'] ?? 'pelayan')])->values();
    $jsRacks = collect($racks ?? [])->map(fn($r) => ['id' => $r['id'], 'name' => $r['name'] ?? '-', 'location' => $r['location'] ?? '', 'barcode_value' => $r['barcode_value'] ?? ''])->values();
    $jsUsedRackIds = collect($grouped)->flatten(1)->pluck('rack_id')->filter()->values();
    $jsTemplates = collect($grouped)->flatten(1)->values();
    $roles = ['pelayan' => '👤 Pelayan', 'kasir' => '💰 Kasir', 'finance' => '📊 Finance', 'backup' => '🔄 Backup', 'inactive' => '🚫 Tidak Aktif'];
@endphp

<div x-data="taskBoard()" x-init="loadTodayTasks()">
    {{-- Toast --}}
    <div x-show="toast.show" x-transition x-text="toast.msg"
         :style="'background:' + (toast.type==='success' ? '#d1fae5' : '#fef2f2') + ';color:' + (toast.type==='success' ? '#065f46' : '#991b1b')"
         style="position:fixed; top:16px; right:16px; z-index:10000; padding:12px 18px; border-radius:8px; font-size:14px; font-weight:600; box-shadow:0 4px 12px rgba(0,0,0,0.15); max-width:340px;"></div>

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <div>
            <h2 style="margin:0; font-size:clamp(20px,4vw,26px);">{{ $scope === 'rack_check' ? '📦 Board Cek Rak' : '📋 Board Tugas Umum' }} — Per Role</h2>
            <p style="font-size:13px; color:#64748b; margin-top:4px;">Kelola {{ $scope === 'rack_check' ? 'jadwal cek rak' : 'tugas berulang' }}. Semua aksi tanpa reload.</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            @if($scope === 'rack_check')
            <a href="{{ route('admin.tasks.templates.board', ['scope' => 'general', 'view' => 'role']) }}" style="background:#e2e8f0; color:#334155; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:13px;">📋 Tugas Umum</a>
            @else
            <a href="{{ route('admin.tasks.templates.board', ['scope' => 'rack_check', 'view' => 'role']) }}" style="background:#e2e8f0; color:#334155; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:13px;">📦 Cek Rak</a>
            @endif
        </div>
    </div>

    {{-- Filter --}}
    <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
        <button class="filter-btn" :class="filter==='all' && 'active'" @click="filter='all'" style="cursor:pointer;">Semua</button>
        <button class="filter-btn" :class="filter==='daily' && 'active'" @click="filter='daily'" style="cursor:pointer;">Harian</button>
        <button class="filter-btn" :class="filter==='weekly' && 'active'" @click="filter='weekly'" style="cursor:pointer;">Mingguan</button>
        <button class="filter-btn" :class="filter==='every_n_days' && 'active'" @click="filter='every_n_days'" style="cursor:pointer;">Tiap N Hari</button>
    </div>
    <style>.filter-btn.active{background:#667eea;color:#fff;border-color:#667eea;}</style>

    {{-- Board --}}
    <div style="display:flex; gap:14px; overflow-x:auto; padding-bottom:16px; align-items:flex-start; min-height:50vh;">
        @foreach($roles as $roleKey => $roleLabel)
        <div class="role-col">
            <div style="padding:10px 14px; border-top:3px solid {{ $roleKey === 'inactive' ? '#94a3b8' : '#667eea' }}; border-radius:10px 10px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:700; font-size:14px;">{{ $roleLabel }}</span>
                <div style="display:flex; gap:4px; align-items:center;">
                    <span style="background:#e2e8f0; padding:2px 8px; border-radius:99px; font-size:12px; font-weight:600;" x-text="cardsForRole('{{ $roleKey }}').length"></span>
                    @if($roleKey !== 'inactive')
                    <button @click="openModal('{{ $roleKey }}')" style="background:#667eea; color:#fff; border:none; border-radius:4px; width:22px; height:22px; font-size:14px; cursor:pointer; line-height:1;">+</button>
                    @endif
                </div>
            </div>
            <div class="role-col-body">
                <template x-for="t in cardsForRole('{{ $roleKey }}')" :key="t.id">
                    <div class="task-card" x-show="filter==='all' || t.recurrence_type===filter">
                        <div style="font-weight:600; font-size:13px; margin-bottom:6px;" x-text="t.title"></div>
                        <div style="display:flex; gap:4px; flex-wrap:wrap; margin-bottom:8px;">
                            <span class="badge badge-freq" x-text="'🔄 ' + freqLabel(t)"></span>
                            <span class="badge badge-assign" x-text="'👤 ' + assignLabel(t)"></span>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button class="card-btn" @click="openModal('{{ $roleKey }}', t)">✏️</button>
                            <button class="card-btn" @click="deleteItem(t)">🗑️</button>
                            @if($roleKey !== 'inactive')
                            <button class="card-btn" style="border-color:#10b981;" @click="triggerItem(t)">🚀</button>
                            @endif
                            <button class="card-btn" @click="toggleItem(t)" x-text="t.is_active ? '⏸️' : '▶️'"></button>
                        </div>
                    </div>
                </template>
                <div x-show="cardsForRole('{{ $roleKey }}').length === 0" style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">Kosong</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Today Tasks --}}
    <div style="margin-top:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; font-size:16px;">📋 Tugas Hari Ini</h3>
            <div style="display:flex; gap:8px;">
                <button class="filter-btn" @click="selectAllToday()" style="font-size:12px;">☑️ Pilih Semua</button>
                <button class="filter-btn" @click="cancelSelected()" style="font-size:12px; background:#fef2f2; border-color:#fca5a5; color:#dc2626;">🗑️ Cancel</button>
            </div>
        </div>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; min-height:60px;">
            <div x-show="todayTasks===null" style="color:#94a3b8; text-align:center; padding:16px;">Memuat...</div>
            <div x-show="todayTasks && todayTasks.length===0" style="color:#94a3b8; text-align:center; padding:16px;">Belum ada tugas hari ini.</div>
            <template x-for="t in (todayTasks||[])" :key="t.id">
                <label style="display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer;" :style="t.status!=='pending' && 'opacity:0.6;cursor:default'">
                    <input type="checkbox" x-model="selectedToday" :value="t.id" :disabled="t.status!=='pending'">
                    <div style="flex:1;">
                        <span style="font-weight:600; font-size:13px;" x-text="t.title"></span>
                        <span style="font-size:11px; color:#64748b; margin-left:8px;" x-text="'👤 ' + (t.assigned_waiter_name||'-')"></span>
                    </div>
                    <span style="font-size:12px;" x-text="t.status==='pending' ? '🟡' : t.status==='done' ? '✅' : '❌'"></span>
                </label>
            </template>
        </div>
    </div>

    {{-- Modal --}}
    <div class="modal-bg" x-show="modal" x-transition @click.self="modal=false" style="display:none;" :style="modal && 'display:flex'">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;" x-text="editId ? '✏️ Edit' : '➕ Tambah'"></h3>

            <label>Role</label>
            <select x-model="form.assigned_waiter_role" disabled style="opacity:0.7;">
                @foreach($roles as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>

            @if($scope === 'rack_check')
            <label>Pilih Rak</label>
            <input type="text" placeholder="🔍 Cari rak..." x-model="rackSearch" style="margin-bottom:4px;">
            <select x-model="form.rack_id" :disabled="!!editId" size="5" style="height:auto;">
                <template x-for="r in filteredRacks" :key="r.id">
                    <option :value="r.id" x-text="r.name + (r.location ? ' ('+r.location+')' : '')"></option>
                </template>
            </select>
            @else
            <label>Judul</label>
            <input type="text" x-model="form.title">
            <label>Deskripsi</label>
            <textarea rows="2" x-model="form.description" style="font-family:inherit;resize:vertical;"></textarea>
            @endif

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div><label>Frekuensi</label><select x-model="form.recurrence_type"><option value="daily">Harian</option><option value="weekly">Mingguan</option><option value="every_n_days">Tiap N Hari</option></select></div>
                <div x-show="form.recurrence_type==='weekly'"><label>Hari</label><select x-model="form.weekly_day"><option value="1">Senin</option><option value="2">Selasa</option><option value="3">Rabu</option><option value="4">Kamis</option><option value="5">Jumat</option><option value="6">Sabtu</option><option value="7">Minggu</option></select></div>
                <div x-show="form.recurrence_type==='every_n_days'"><label>Interval (hari)</label><input type="number" x-model="form.interval_days" min="1" max="365"></div>
            </div>
            <label>Assign ke</label>
            <select x-model="form.assignment_type">
                <option value="role">Semua role ini</option>
                <option value="single">Orang tertentu</option>
            </select>
            <div x-show="form.assignment_type==='single'">
                <label>Pilih orang</label>
                <select x-model="form.assigned_waiter_id">
                    <template x-for="w in filteredWaiters" :key="w.id"><option :value="w.id" x-text="w.name"></option></template>
                </select>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                <button @click="modal=false" style="padding:8px 16px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; cursor:pointer;">Batal</button>
                <button @click="save()" :style="'padding:8px 16px; border:none; border-radius:6px; color:#fff; font-weight:600; cursor:pointer; background:' + (editId ? '#667eea' : '#10b981')" x-text="editId ? 'Simpan' : 'Tambah'"></button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function taskBoard() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const scope = @json($scope);
    const allRacks = @json($jsRacks);
    const usedRackIds = @json($jsUsedRackIds);
    const waitersByRole = @json($jsWaiters);

    return {
        templates: @json($jsTemplates),
        filter: 'all',
        modal: false,
        editId: null,
        form: {},
        rackSearch: '',
        toast: {show:false, msg:'', type:'success'},
        todayTasks: null,
        selectedToday: [],

        get availableRacks() {
            return allRacks.filter(r => !usedRackIds.includes(r.id) || r.id === this.form.rack_id);
        },
        get filteredRacks() {
            const q = this.rackSearch.toLowerCase();
            return this.availableRacks.filter(r => !q || r.name.toLowerCase().includes(q) || (r.location||'').toLowerCase().includes(q));
        },
        get filteredWaiters() {
            return waitersByRole.filter(w => w.role === this.form.assigned_waiter_role);
        },

        cardsForRole(role) {
            if (role === 'inactive') return this.templates.filter(t => !t.is_active);
            return this.templates.filter(t => t.is_active && (t.assigned_waiter_role || 'pelayan') === role);
        },

        freqLabel(t) {
            if (t.recurrence_type === 'daily') return 'Harian';
            if (t.recurrence_type === 'weekly') return ['','Sen','Sel','Rab','Kam','Jum','Sab','Min'][t.weekly_day||1];
            return 'Tiap ' + (t.interval_days||'?') + 'h';
        },
        assignLabel(t) {
            if (t.assignment_type === 'single' && t.assigned_waiter_id) {
                const w = waitersByRole.find(x => x.id === t.assigned_waiter_id);
                return w ? w.name : 'Orang';
            }
            return 'Semua';
        },

        showToast(msg, type='success') {
            this.toast = {show:true, msg, type};
            setTimeout(() => this.toast.show = false, 3000);
        },

        openModal(role, t=null) {
            this.editId = t ? t.id : null;
            this.form = {
                title: t?.title || '',
                description: t?.description || '',
                assigned_waiter_role: t?.assigned_waiter_role || role,
                recurrence_type: t?.recurrence_type || 'daily',
                weekly_day: String(t?.weekly_day || '1'),
                interval_days: t?.interval_days || 3,
                shift_offset_minutes: t?.shift_offset_minutes || 0,
                time_limit_minutes: t?.time_limit_minutes || 30,
                assignment_type: t?.assignment_type || 'role',
                assigned_waiter_id: t?.assigned_waiter_id || '',
                rack_id: t?.rack_id || '',
            };
            this.rackSearch = '';
            this.modal = true;
        },

        async save() {
            let title = this.form.title;
            if (scope === 'rack_check' && !this.editId) {
                if (!this.form.rack_id) { this.showToast('Pilih rak', 'error'); return; }
                const rack = allRacks.find(r => r.id === this.form.rack_id);
                title = rack ? rack.name : '';
            } else if (scope !== 'rack_check' && !title.trim()) {
                this.showToast('Judul wajib diisi', 'error'); return;
            }

            const body = {
                title, description: this.form.description || '',
                priority: 'normal',
                assigned_waiter_role: this.form.assigned_waiter_role,
                recurrence_type: this.form.recurrence_type,
                schedule_mode: 'shift_relative', schedule_time: '',
                shift_offset_minutes: parseInt(this.form.shift_offset_minutes) || 0,
                time_limit_minutes: parseInt(this.form.time_limit_minutes) || 30,
                assignment_type: this.form.assignment_type,
                task_type: scope, task_scope: scope, is_recurring: 1,
            };
            if (body.assignment_type === 'single') body.assigned_waiter_id = this.form.assigned_waiter_id;
            if (body.recurrence_type === 'weekly') body.weekly_day = parseInt(this.form.weekly_day);
            if (body.recurrence_type === 'every_n_days') body.interval_days = parseInt(this.form.interval_days);
            if (scope === 'rack_check' && !this.editId) { body.rack_ids = [this.form.rack_id]; body.rack_target_scope = 'single'; }

            const url = this.editId ? '/admin/tasks/recurring/' + this.editId + '/schedule' : '/admin/tasks';
            const method = this.editId ? 'PATCH' : 'POST';

            try {
                const res = await fetch(url, {method, headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify(body)});
                const data = await res.json();
                if (data.success !== false) {
                    if (this.editId) {
                        const idx = this.templates.findIndex(x => x.id === this.editId);
                        if (idx >= 0) Object.assign(this.templates[idx], body, {id: this.editId});
                    } else {
                        this.templates.push({...body, id: data.id || Date.now(), is_active: true, rack_id: this.form.rack_id});
                        if (scope === 'rack_check' && this.form.rack_id) usedRackIds.push(this.form.rack_id);
                    }
                    this.modal = false;
                    this.showToast(this.editId ? 'Diupdate!' : 'Ditambahkan!');
                } else this.showToast(data.message || 'Gagal', 'error');
            } catch(e) { this.showToast(e.message, 'error'); }
        },

        async deleteItem(t) {
            if (!confirm('Hapus "' + t.title + '"?')) return;
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id, {method:'DELETE', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
                const data = await res.json();
                if (data.success) {
                    this.templates = this.templates.filter(x => x.id !== t.id);
                    this.showToast('Dihapus!');
                } else this.showToast(data.message || 'Gagal', 'error');
            } catch(e) { this.showToast(e.message, 'error'); }
        },

        async triggerItem(t) {
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id + '/force-generate', {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
                const data = await res.json();
                this.showToast('🚀 "' + t.title + '" → ' + (data.generated||0) + ' task');
                this.loadTodayTasks();
            } catch(e) { this.showToast(e.message, 'error'); }
        },

        async toggleItem(t) {
            const newState = !t.is_active;
            try {
                const res = await fetch('/admin/tasks/recurring/' + t.id + '/schedule', {method:'PATCH', headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({is_active:newState})});
                const data = await res.json();
                if (data.success) {
                    t.is_active = newState;
                    this.showToast(newState ? 'Diaktifkan!' : 'Dinonaktifkan!');
                }
            } catch(e) { this.showToast(e.message, 'error'); }
        },

        async loadTodayTasks() {
            try {
                const res = await fetch('/admin/tasks/today-generated?scope=' + scope, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                this.todayTasks = await res.json();
            } catch(e) { this.todayTasks = []; }
        },

        selectAllToday() {
            const pending = (this.todayTasks||[]).filter(t => t.status === 'pending').map(t => t.id);
            this.selectedToday = this.selectedToday.length === pending.length ? [] : pending;
        },

        async cancelSelected() {
            if (!this.selectedToday.length) { this.showToast('Pilih tugas', 'error'); return; }
            if (!confirm('Cancel ' + this.selectedToday.length + ' tugas?')) return;
            for (const id of this.selectedToday) {
                await fetch('/admin/tasks/' + id, {method:'DELETE', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
            }
            this.showToast(this.selectedToday.length + ' tugas di-cancel!');
            this.selectedToday = [];
            this.loadTodayTasks();
        },
    };
}
</script>
@endpush
