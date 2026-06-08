@extends('admin.layout')

@push('styles')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
:root { --ts-palette-w: 260px; --ts-drawer-w: 400px; }
.ts-layout { display: grid; grid-template-columns: var(--ts-palette-w) 1fr; gap: 0; min-height: calc(100vh - 60px); position: relative; }
.ts-palette { position: sticky; top: 60px; height: calc(100vh - 60px); overflow-y: auto; background: #f8fafc; border-right: 1px solid var(--color-border); padding: 16px 12px; }
.ts-palette-section { margin-bottom: 20px; }
.ts-palette-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-muted); margin-bottom: 8px; padding: 0 4px; }
.ts-palette-search { width: 100%; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; background: white; margin-bottom: 10px; }
.ts-palette-search:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
.ts-rack-pill { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: white; border: 1px solid var(--color-border); border-radius: 8px; margin-bottom: 6px; cursor: grab; transition: all 0.15s; font-size: 13px; }
.ts-rack-pill:hover { border-color: var(--color-primary); box-shadow: 0 2px 8px rgba(102,126,234,0.12); transform: translateY(-1px); }
.ts-rack-pill.dragging { opacity: 0.5; transform: scale(0.95); }
.ts-rack-pill-icon { width: 28px; height: 28px; border-radius: 6px; background: linear-gradient(135deg, var(--color-primary), #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 700; flex-shrink: 0; }
.ts-rack-pill-info { flex: 1; min-width: 0; }
.ts-rack-pill-name { font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ts-rack-pill-loc { font-size: 11px; color: var(--color-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ts-main { padding: 16px 20px; overflow-x: auto; }
.ts-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.ts-toolbar-title { font-size: 18px; font-weight: 700; color: var(--color-text); }
.ts-toolbar-subtitle { font-size: 13px; color: var(--color-text-muted); }
.ts-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--color-border); background: white; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px; }
.ts-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
.ts-btn--primary { background: var(--color-primary); color: white; border-color: var(--color-primary); }
.ts-btn--primary:hover { background: #5a6fd6; }
.ts-btn--success { background: var(--color-success); color: white; border-color: var(--color-success); }
.ts-btn--success:hover { opacity: 0.9; }
.ts-btn--warning { background: var(--color-warning); color: white; border-color: var(--color-warning); }
.ts-btn--sm { padding: 5px 10px; font-size: 12px; }
.ts-btn[disabled] { opacity: 0.5; cursor: not-allowed; }
.ts-date-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
.ts-date-tab { padding: 8px 14px; border-radius: 8px; border: 1px solid var(--color-border); background: white; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.15s; text-align: center; min-width: 80px; }
.ts-date-tab:hover { border-color: var(--color-primary); }
.ts-date-tab.active { background: var(--color-primary); color: white; border-color: var(--color-primary); }
.ts-date-tab .day { font-weight: 700; display: block; }
.ts-date-tab .date { font-size: 11px; opacity: 0.8; }
.ts-date-tab .badge { font-size: 10px; margin-top: 2px; display: block; }
.ts-summary { display: flex; gap: 16px; flex-wrap: wrap; padding: 12px 16px; background: white; border: 1px solid var(--color-border); border-radius: 10px; margin-bottom: 16px; font-size: 13px; align-items: center; }
.ts-summary-item { display: flex; align-items: center; gap: 4px; }
.ts-summary-item strong { font-weight: 700; }
.ts-columns { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 16px; min-height: 300px; }
.ts-col { min-width: 220px; max-width: 280px; flex: 1; background: #f8fafc; border: 1px solid var(--color-border); border-radius: 10px; display: flex; flex-direction: column; }
.ts-col.is-off { opacity: 0.5; pointer-events: none; }
.ts-col-header { padding: 12px; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; gap: 8px; }
.ts-col-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.ts-col-name { font-weight: 600; font-size: 13px; color: var(--color-text); }
.ts-col-meta { font-size: 11px; color: var(--color-text-muted); }
.ts-col-cap { font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 600; }
.ts-col-cap.ok { background: #dcfce7; color: #166534; }
.ts-col-cap.full { background: #fee2e2; color: #991b1b; }
.ts-col-body { padding: 8px; flex: 1; min-height: 80px; transition: background 0.15s; }
.ts-col-body.drag-over { background: rgba(102,126,234,0.08); border: 2px dashed var(--color-primary); border-radius: 8px; }
.ts-col-empty { padding: 20px; text-align: center; color: var(--color-text-muted); font-size: 12px; border: 2px dashed var(--color-border); border-radius: 8px; margin: 4px; }
.ts-card { background: white; border: 1px solid var(--color-border); border-radius: 8px; padding: 10px; margin-bottom: 6px; cursor: grab; transition: all 0.15s; }
.ts-card:hover { border-color: var(--color-primary); box-shadow: 0 2px 8px rgba(102,126,234,0.12); }
.ts-card.dragging { opacity: 0.4; transform: rotate(2deg); }
.ts-card.saving { opacity: 0.6; pointer-events: none; }
.ts-card-title { font-weight: 600; font-size: 13px; color: var(--color-text); margin-bottom: 4px; }
.ts-card-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.ts-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; }
.ts-badge--loc { background: #e0e7ff; color: #3730a3; }
.ts-badge--template { background: #fef3c7; color: #92400e; }
.ts-badge--status { background: #dcfce7; color: #166534; }
.ts-card-actions { display: flex; gap: 4px; margin-top: 6px; }
.ts-drawer-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 999; }
.ts-drawer-overlay.active { display: block; }
.ts-drawer { position: fixed; top: 0; right: -440px; width: 400px; height: 100vh; background: white; box-shadow: -4px 0 20px rgba(0,0,0,0.1); z-index: 1000; transition: right 0.3s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; }
.ts-drawer.open { right: 0; }
.ts-drawer-header { padding: 16px 20px; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; }
.ts-drawer-title { font-size: 16px; font-weight: 700; color: var(--color-text); }
.ts-drawer-close { width: 32px; height: 32px; border-radius: 6px; border: none; background: #f1f5f9; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
.ts-drawer-body { flex: 1; overflow-y: auto; padding: 20px; }
.ts-drawer-section { margin-bottom: 20px; }
.ts-drawer-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 6px; }
.ts-form-group { margin-bottom: 14px; }
.ts-form-label { font-size: 12px; font-weight: 600; color: var(--color-text); margin-bottom: 4px; display: block; }
.ts-form-input, .ts-form-select, .ts-form-textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; }
.ts-form-textarea { min-height: 80px; resize: vertical; }
.ts-unassign-zone { border: 2px dashed var(--color-border); border-radius: 10px; padding: 16px; text-align: center; color: var(--color-text-muted); font-size: 12px; margin-top: 12px; transition: all 0.15s; }
.ts-unassign-zone.drag-over { border-color: #ef4444; background: #fef2f2; color: #dc2626; }
.ts-toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-size: 13px; font-weight: 600; z-index: 2000; transform: translateY(100px); opacity: 0; transition: all 0.3s; }
.ts-toast.show { transform: translateY(0); opacity: 1; }
.ts-toast.success { background: var(--color-success); }
.ts-toast.error { background: #ef4444; }

@media (max-width: 1023px) {
    .ts-layout { grid-template-columns: 1fr; }
    .ts-palette { position: static; height: auto; border-right: none; border-bottom: 1px solid var(--color-border); }
    .ts-drawer { width: 100%; right: -100%; }
    .ts-columns { flex-direction: column; }
    .ts-col { min-width: 100%; max-width: 100%; }
}
</style>
@endpush

@section('content')
<div x-data="planningBoard()" x-init="init()" class="ts-layout">
    <!-- LEFT PALETTE -->
    <div class="ts-palette">
        <div class="ts-palette-section">
            <div class="ts-palette-title">Rak Belum Diassign</div>
            <input type="text" class="ts-palette-search" placeholder="Cari rak..." x-model="rackSearch" />
            <template x-for="rack in filteredUnassignedRacks" :key="rack.rack_id">
                <div class="ts-rack-pill"
                     draggable="true"
                     :data-rack-id="rack.rack_id"
                     :data-template-id="rack.template_id"
                     :data-rack-code="rack.rack_code"
                     :data-rack-name="rack.rack_name"
                     @dragstart="onPillDragStart($event, rack)"
                     @dragend="onDragEnd($event)">
                    <div class="ts-rack-pill-icon" x-text="rack.rack_name.charAt(0)"></div>
                    <div class="ts-rack-pill-info">
                        <div class="ts-rack-pill-name" x-text="rack.rack_name"></div>
                        <div class="ts-rack-pill-loc" x-text="rack.rack_location || rack.template_name"></div>
                    </div>
                </div>
            </template>
            <div x-show="filteredUnassignedRacks.length === 0" style="text-align:center; padding:16px; color:var(--color-text-muted); font-size:12px;">
                <template x-if="rackSearch">
                    <span>Tidak ditemukan</span>
                </template>
                <template x-if="!rackSearch">
                    <span>Semua rak sudah diassign</span>
                </template>
            </div>
        </div>

        <!-- Unassign Drop Zone -->
        <div class="ts-unassign-zone"
             @dragover.prevent="$el.classList.add('drag-over')"
             @dragleave="$el.classList.remove('drag-over')"
             @drop.prevent="onDropUnassign($event); $el.classList.remove('drag-over')">
            Drop di sini untuk unassign
        </div>

        <!-- Summary -->
        <div class="ts-palette-section" style="margin-top:16px;">
            <div class="ts-palette-title">Ringkasan</div>
            <div style="font-size:12px; color:var(--color-text-secondary); line-height:1.8;">
                <div>Total Rak: <strong x-text="dueRacks.length"></strong></div>
                <div>Assigned: <strong x-text="assignedCount" style="color:var(--color-success)"></strong></div>
                <div>Belum: <strong x-text="unassignedCount" style="color:var(--color-warning)"></strong></div>
            </div>
        </div>
    </div>

    <!-- MAIN BOARD -->
    <div class="ts-main">
        <!-- Toolbar -->
        <div class="ts-toolbar">
            <div style="flex:1;">
                <div class="ts-toolbar-title">Planning Cek Rak</div>
                <div class="ts-toolbar-subtitle">Kelola pembagian tugas cek rak manual</div>
            </div>
            <a href="{{ route('admin.rack_check.templates.index') }}" class="ts-btn ts-btn--sm">Template</a>
        </div>

        <!-- Date Tabs -->
        <div class="ts-date-tabs">
            @foreach($days as $day)
                <button type="button"
                        class="ts-date-tab"
                        :class="{ 'active': selectedDate === '{{ $day['date'] }}' }"
                        @click="loadDate('{{ $day['date'] }}')">
                    <span class="day">{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('D') }}</span>
                    <span class="date">{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}</span>
                    @if($day['is_complete'])
                        <span class="badge" style="color:var(--color-success);">&#10003;</span>
                    @elseif($day['due_count'] > 0)
                        <span class="badge" style="color:var(--color-warning);">{{ $day['due_count'] - $day['assigned_count'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <!-- Summary Bar -->
        <div class="ts-summary" x-show="!loading">
            <span class="ts-summary-item">Rak Due: <strong x-text="dueRacks.length"></strong></span>
            <span class="ts-summary-item" style="color:var(--color-success);">Assigned: <strong x-text="assignedCount"></strong></span>
            <span class="ts-summary-item" style="color:var(--color-warning);" x-show="unassignedCount > 0">Belum: <strong x-text="unassignedCount"></strong></span>
            <div style="flex:1;"></div>
            <button class="ts-btn ts-btn--sm" @click="refreshDate()" :disabled="loading" title="Refresh data">&#8635;</button>
            <button class="ts-btn ts-btn--sm" @click="autoSuggest()" :disabled="saving || unassignedCount === 0">Auto Suggest</button>
            <button class="ts-btn ts-btn--sm ts-btn--primary" @click="saveDraft()" :disabled="saving">Simpan Draft</button>
            <button class="ts-btn ts-btn--sm ts-btn--success" @click="confirmPublish()" :disabled="saving || unassignedCount > 0">Publish</button>
        </div>

        <!-- Loading -->
        <div x-show="loading" style="text-align:center; padding:60px; color:var(--color-text-muted);">
            <div style="font-size:14px;">Memuat data...</div>
        </div>

        <!-- Employee Columns -->
        <div class="ts-columns" x-show="!loading">
            <template x-for="emp in employees.filter(e => e.is_working)" :key="emp.waiter_id">
                <div class="ts-col">
                    <div class="ts-col-header">
                        <div class="ts-col-avatar" x-text="emp.waiter_name.charAt(0)"></div>
                        <div style="flex:1; min-width:0;">
                            <div class="ts-col-name" x-text="emp.waiter_name"></div>
                            <div class="ts-col-meta">
                                <span x-text="emp.shift_info"></span>
                            </div>
                        </div>
                        <span class="ts-col-cap" :class="emp.can_take_more ? 'ok' : 'full'" x-text="emp.task_count + '/' + (emp.daily_cap ?? '∞')"></span>
                    </div>
                    <div class="ts-col-body"
                         :data-waiter-id="emp.waiter_id"
                         @dragover.prevent="onColDragOver($event)"
                         @dragleave="onColDragLeave($event)"
                         @drop.prevent="onColDrop($event, emp)">
                        <template x-for="task in getTasksForEmployee(emp.waiter_id)" :key="task.id">
                            <div class="ts-card"
                                 :class="{ 'saving': savingCards.has(task.id) }"
                                 draggable="true"
                                 :data-task-id="task.id"
                                 :data-rack-id="task.rack_id"
                                 :data-template-id="task.template_id"
                                 :data-assigned-to="task.assigned_to"
                                 @dragstart="onCardDragStart($event, task)"
                                 @dragend="onDragEnd($event)"
                                 @click="openDrawer(task)">
                                <div class="ts-card-title" x-text="task.rack_name"></div>
                                <div class="ts-card-meta">
                                    <span class="ts-badge ts-badge--loc" x-text="task.rack_code"></span>
                                    <span class="ts-badge ts-badge--template" x-text="task.template_name || ''"></span>
                                </div>
                            </div>
                        </template>
                        <div class="ts-col-empty" x-show="getTasksForEmployee(emp.waiter_id).length === 0">
                            Drop rak di sini
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Info Karyawan Libur -->
        <div x-show="!loading && employees.filter(e => !e.is_working).length > 0" style="margin-top:12px; padding:10px 14px; background:#fef3c7; border:1px solid #fde68a; border-radius:8px; font-size:12px; color:#92400e;">
            <strong>Libur:</strong>
            <template x-for="emp in employees.filter(e => !e.is_working)" :key="emp.waiter_id">
                <span x-text="emp.waiter_name" style="margin-left:8px;"></span>
            </template>
        </div>
    </div>
    <!-- RIGHT DRAWER -->
    <div class="ts-drawer-overlay" :class="{ 'active': drawerOpen }" @click="closeDrawer()"></div>
    <div class="ts-drawer" :class="{ 'open': drawerOpen }">
        <div class="ts-drawer-header">
            <div class="ts-drawer-title" x-text="drawerTask ? drawerTask.rack_name : ''"></div>
            <button class="ts-drawer-close" @click="closeDrawer()">&times;</button>
        </div>
        <div class="ts-drawer-body">
            <!-- Task Info -->
            <div class="ts-drawer-section">
                <div class="ts-drawer-label">Detail Task</div>
                <div style="font-size:13px; line-height:1.8;">
                    <div>Rak: <strong x-text="drawerTask?.rack_name"></strong></div>
                    <div>Kode: <span x-text="drawerTask?.rack_code"></span></div>
                    <div>Template: <span x-text="drawerTask?.template_name || '-'"></span></div>
                    <div>Assigned ke: <strong x-text="drawerTask?.assigned_waiter_name || '-'"></strong></div>
                    <div>Status: <span x-text="drawerTask?.status || 'draft'"></span></div>
                </div>
            </div>

            <!-- Reassign -->
            <div class="ts-drawer-section">
                <div class="ts-drawer-label">Pindah ke Karyawan Lain</div>
                <div class="ts-form-group">
                    <select class="ts-form-select" x-model="drawerReassignTo">
                        <option value="">-- Pilih Karyawan --</option>
                        <template x-for="emp in employees.filter(e => e.is_working && e.waiter_id !== drawerTask?.assigned_to)" :key="emp.waiter_id">
                            <option :value="emp.waiter_id" x-text="emp.waiter_name + ' (' + emp.task_count + '/' + emp.daily_cap + ')'"></option>
                        </template>
                    </select>
                </div>
                <button class="ts-btn ts-btn--sm ts-btn--primary" @click="doReassign()" :disabled="!drawerReassignTo || saving">Reassign</button>
            </div>

            <!-- Reschedule -->
            <div class="ts-drawer-section">
                <div class="ts-drawer-label">Pindah Tanggal</div>
                <div class="ts-form-group">
                    <input type="date" class="ts-form-input" x-model="drawerRescheduleDate" :min="minRescheduleDate" />
                </div>
                <button class="ts-btn ts-btn--sm ts-btn--warning" @click="doReschedule()" :disabled="!drawerRescheduleDate || saving">Reschedule</button>
            </div>

            <!-- Ignore -->
            <div class="ts-drawer-section">
                <div class="ts-drawer-label">Abaikan Task</div>
                <div class="ts-form-group">
                    <label class="ts-form-label">Alasan (wajib)</label>
                    <textarea class="ts-form-textarea" x-model="drawerIgnoreReason" placeholder="Tulis alasan mengabaikan rak ini..."></textarea>
                </div>
                <button class="ts-btn ts-btn--sm" style="background:#ef4444; color:white; border-color:#ef4444;" @click="doIgnore()" :disabled="!drawerIgnoreReason.trim() || saving">Abaikan</button>
            </div>

            <!-- Unassign -->
            <div class="ts-drawer-section">
                <div class="ts-drawer-label">Unassign</div>
                <button class="ts-btn ts-btn--sm" @click="doUnassign()" :disabled="saving">Kembalikan ke pool</button>
            </div>
        </div>
    </div>

    <!-- Publish Confirm Modal -->
    <div x-show="showPublishModal" style="position:fixed; inset:0; z-index:2000; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4);" x-cloak>
        <div style="background:white; border-radius:12px; padding:24px; max-width:400px; width:90%;">
            <h3 style="margin:0 0 12px; font-size:16px;">Publish Planning?</h3>
            <p style="font-size:13px; color:var(--color-text-secondary); margin-bottom:16px;">
                Task akan dikirim ke portal waiter. Pastikan semua assignment sudah benar.
            </p>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button class="ts-btn ts-btn--sm" @click="showPublishModal = false">Batal</button>
                <button class="ts-btn ts-btn--sm ts-btn--success" @click="doPublish()" :disabled="saving">Publish</button>
            </div>
        </div>
    </div>

    <!-- Override Cap Modal -->
    <div x-show="showOverrideModal" style="position:fixed; inset:0; z-index:2000; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4);" x-cloak>
        <div style="background:white; border-radius:12px; padding:24px; max-width:400px; width:90%;">
            <h3 style="margin:0 0 12px; font-size:16px; color:var(--color-warning);">Melebihi Kapasitas Harian</h3>
            <p style="font-size:13px; color:var(--color-text-secondary); margin-bottom:12px;" x-text="overrideMessage"></p>
            <div class="ts-form-group">
                <label class="ts-form-label">Alasan Override</label>
                <textarea class="ts-form-textarea" x-model="overrideReason" placeholder="Alasan assign melebihi cap..."></textarea>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button class="ts-btn ts-btn--sm" @click="showOverrideModal = false; pendingAssign = null;">Batal</button>
                <button class="ts-btn ts-btn--sm ts-btn--warning" @click="confirmOverride()" :disabled="!overrideReason.trim() || saving">Override & Assign</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="ts-toast" :class="{ 'show': toast.show, [toast.type]: true }" x-text="toast.message"></div>
</div>
@endsection

@push('scripts')
<script>
function planningBoard() {
    return {
        selectedDate: '{{ $days[0]["date"] ?? now()->format("Y-m-d") }}',
        loading: false,
        saving: false,
        dueRacks: [],
        planningTasks: [],
        employees: [],
        rackSearch: '',
        savingCards: new Set(),
        cardOps: new Map(), // task_id/rack_key -> opId (for stale response detection)
        dateCache: new Map(), // date -> { dueRacks, planningTasks, employees, ts }

        // Drawer
        drawerOpen: false,
        drawerTask: null,
        drawerReassignTo: '',
        drawerRescheduleDate: '',
        drawerIgnoreReason: '',

        // Modals
        showPublishModal: false,
        showOverrideModal: false,
        overrideMessage: '',
        overrideReason: '',
        pendingAssign: null,

        // Drag state
        draggedItem: null,
        dragType: null, // 'pill' or 'card'

        // Toast
        toast: { show: false, message: '', type: 'success' },

        // Computed
        get filteredUnassignedRacks() {
            const assigned = new Set(this.planningTasks.filter(t => t.assigned_to).map(t => t.rack_id + '::' + t.template_id));
            let racks = this.dueRacks.filter(r => !assigned.has(r.rack_id + '::' + r.template_id));
            if (this.rackSearch.trim()) {
                const q = this.rackSearch.toLowerCase();
                racks = racks.filter(r => r.rack_name.toLowerCase().includes(q) || (r.rack_code||'').toLowerCase().includes(q));
            }
            return racks;
        },
        get assignedCount() { return this.planningTasks.filter(t => t.assigned_to).length; },
        get unassignedCount() { return this.dueRacks.length - this.assignedCount; },
        get minRescheduleDate() {
            const d = new Date(); d.setDate(d.getDate() + 1);
            return d.toISOString().split('T')[0];
        },

        init() {
            this.loadDate(this.selectedDate);
        },

        async loadDate(date, forceRefresh = false) {
            this.selectedDate = date;
            this.closeDrawer();

            // Use cache if available and not forced refresh
            if (!forceRefresh && this.dateCache.has(date)) {
                const cached = this.dateCache.get(date);
                this.dueRacks = cached.dueRacks;
                this.planningTasks = cached.planningTasks;
                this.employees = cached.employees;
                return;
            }

            this.loading = true;
            try {
                const resp = await fetch(`{{ route('admin.rack_check.planning.daily') }}?date=${date}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                if (data.success) {
                    this.dueRacks = data.due_racks || [];
                    this.planningTasks = data.planning_tasks || [];
                    this.employees = data.employee_availability || [];
                    // Store in cache
                    this.dateCache.set(date, {
                        dueRacks: this.dueRacks,
                        planningTasks: this.planningTasks,
                        employees: this.employees,
                        ts: Date.now()
                    });
                }
            } catch(e) {
                this.showToast('Gagal memuat data', 'error');
            }
            this.loading = false;
        },

        // Invalidate cache for current date and force reload
        async refreshDate() {
            this.dateCache.delete(this.selectedDate);
            await this.loadDate(this.selectedDate, true);
        },

        // Invalidate cache (called after publish/auto-suggest)
        invalidateCache(date) {
            if (date) {
                this.dateCache.delete(date);
            } else {
                this.dateCache.clear();
            }
        },

        // Update cache after local state mutation (assign/unassign/reassign)
        syncCache() {
            if (this.dateCache.has(this.selectedDate)) {
                this.dateCache.set(this.selectedDate, {
                    dueRacks: this.dueRacks,
                    planningTasks: this.planningTasks,
                    employees: this.employees,
                    ts: Date.now()
                });
            }
        },

        getTasksForEmployee(waiterId) {
            return this.planningTasks.filter(t => t.assigned_to === waiterId);
        },

        // DRAG: Pill from palette
        onPillDragStart(ev, rack) {
            // Block drag if this rack is currently being assigned
            const cardKey = rack.rack_id + '::' + rack.template_id;
            if (this.savingCards.has(cardKey)) {
                ev.preventDefault();
                return;
            }
            this.dragType = 'pill';
            this.draggedItem = rack;
            ev.target.classList.add('dragging');
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', rack.rack_id);
        },

        // DRAG: Card from column
        onCardDragStart(ev, task) {
            // Block drag if card is currently saving
            if (this.savingCards.has(task.id)) {
                ev.preventDefault();
                return;
            }
            this.dragType = 'card';
            this.draggedItem = task;
            ev.target.classList.add('dragging');
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', task.id);
        },

        onDragEnd(ev) {
            ev.target.classList.remove('dragging');
            this.draggedItem = null;
            this.dragType = null;
        },

        onColDragOver(ev) {
            ev.currentTarget.classList.add('drag-over');
        },
        onColDragLeave(ev) {
            ev.currentTarget.classList.remove('drag-over');
        },
        // DROP on employee column
        async onColDrop(ev, emp) {
            ev.currentTarget.classList.remove('drag-over');
            if (!this.draggedItem) return;
            // Capture and clear immediately to prevent stale ref
            const item = this.draggedItem;
            const type = this.dragType;
            this.draggedItem = null;
            this.dragType = null;

            if (type === 'pill') {
                await this.doAssign(item, emp);
            } else if (type === 'card') {
                if (item.assigned_to === emp.waiter_id) return;
                await this.doReassignDrop(item, emp);
            }
        },

        // DROP on unassign zone
        async onDropUnassign(ev) {
            if (!this.draggedItem || this.dragType !== 'card') return;
            const item = this.draggedItem;
            this.draggedItem = null;
            this.dragType = null;
            await this.doUnassignTask(item);
        },

        // ASSIGN (pill -> employee)
        async doAssign(rack, emp) {
            if (!emp.can_take_more) {
                this.pendingAssign = { rack, emp, type: 'assign' };
                this.overrideMessage = `${emp.waiter_name} sudah ${emp.task_count}/${emp.daily_cap} tugas. Tetap assign?`;
                this.overrideReason = '';
                this.showOverrideModal = true;
                return;
            }
            await this.executeAssign(rack, emp, '');
        },

        async executeAssign(rack, emp, overrideReason) {
            const cardKey = rack.rack_id + '::' + rack.template_id;
            // Guard: skip if already saving this rack
            if (this.savingCards.has(cardKey)) return;
            this.savingCards.add(cardKey);

            const opId = Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            this.cardOps.set(cardKey, opId);

            // Optimistic UI
            const tempTask = {
                id: 'temp_' + opId,
                rack_id: rack.rack_id,
                rack_code: rack.rack_code,
                rack_name: rack.rack_name,
                template_id: rack.template_id,
                template_name: rack.template_name,
                assigned_to: emp.waiter_id,
                assigned_waiter_name: emp.waiter_name,
                status: 'draft'
            };
            this.planningTasks.push(tempTask);
            emp.task_count++;
            emp.can_take_more = emp.daily_cap === null || emp.task_count < emp.daily_cap;

            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.assign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        scheduled_date: this.selectedDate,
                        rack_id: rack.rack_id,
                        rack_code: rack.rack_code || '',
                        rack_name: rack.rack_name || '',
                        template_id: rack.template_id,
                        waiter_id: emp.waiter_id,
                        override: overrideReason ? true : false,
                        override_reason: overrideReason || undefined
                    })
                });
                const data = await resp.json();
                // Stale check: if another op happened on this card, ignore this response
                if (this.cardOps.get(cardKey) !== opId) return;

                if (data.success) {
                    const idx = this.planningTasks.findIndex(t => t.id === tempTask.id);
                    if (idx !== -1) {
                        const realTask = data.task || { ...tempTask, id: data.task_id || tempTask.id };
                        this.planningTasks.splice(idx, 1, realTask);
                    }
                    this.showToast('Rak berhasil diassign', 'success');
                    this.syncCache();
                } else {
                    throw new Error(data.message || 'Gagal assign');
                }
            } catch(e) {
                // Safe rollback: only if this op is still the latest for this card
                if (this.cardOps.get(cardKey) === opId) {
                    this.planningTasks = this.planningTasks.filter(t => t.id !== tempTask.id);
                    emp.task_count--;
                    emp.can_take_more = emp.daily_cap === null || emp.task_count < emp.daily_cap;
                    this.showToast(e.message || 'Gagal assign', 'error');
                }
            } finally {
                this.savingCards.delete(cardKey);
                if (this.cardOps.get(cardKey) === opId) this.cardOps.delete(cardKey);
            }
        },

        // REASSIGN via drag (card -> different employee)
        async doReassignDrop(task, newEmp) {
            if (!newEmp.can_take_more) {
                this.pendingAssign = { task, emp: newEmp, type: 'reassign' };
                this.overrideMessage = `${newEmp.waiter_name} sudah ${newEmp.task_count}/${newEmp.daily_cap} tugas. Tetap reassign?`;
                this.overrideReason = '';
                this.showOverrideModal = true;
                return;
            }
            await this.executeReassign(task, newEmp, '');
        },

        async executeReassign(task, newEmp, overrideReason) {
            // Guard: skip if already saving this task
            if (this.savingCards.has(task.id)) return;
            this.savingCards.add(task.id);

            const opId = Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            this.cardOps.set(task.id, opId);

            const oldEmp = this.employees.find(e => e.waiter_id === task.assigned_to);
            const oldWaiterId = task.assigned_to;
            const oldWaiterName = task.assigned_waiter_name;

            // Optimistic
            task.assigned_to = newEmp.waiter_id;
            task.assigned_waiter_name = newEmp.waiter_name;
            if (oldEmp) { oldEmp.task_count--; oldEmp.can_take_more = oldEmp.daily_cap === null || oldEmp.task_count < oldEmp.daily_cap; }
            newEmp.task_count++;
            newEmp.can_take_more = newEmp.daily_cap === null || newEmp.task_count < newEmp.daily_cap;

            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.reassign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        task_id: task.id,
                        new_waiter_id: newEmp.waiter_id,
                        override: overrideReason ? true : false,
                        override_reason: overrideReason || undefined
                    })
                });
                const data = await resp.json();
                // Stale check
                if (this.cardOps.get(task.id) !== opId) return;

                if (!data.success) throw new Error(data.message || 'Gagal reassign');
                this.showToast('Berhasil dipindah ke ' + newEmp.waiter_name, 'success');
                this.syncCache();
            } catch(e) {
                // Safe rollback: only if this is still the latest op
                if (this.cardOps.get(task.id) === opId) {
                    task.assigned_to = oldWaiterId;
                    task.assigned_waiter_name = oldWaiterName;
                    if (oldEmp) { oldEmp.task_count++; oldEmp.can_take_more = oldEmp.daily_cap === null || oldEmp.task_count < oldEmp.daily_cap; }
                    newEmp.task_count--;
                    newEmp.can_take_more = newEmp.daily_cap === null || newEmp.task_count < newEmp.daily_cap;
                    this.showToast(e.message || 'Gagal reassign', 'error');
                }
            } finally {
                this.savingCards.delete(task.id);
                if (this.cardOps.get(task.id) === opId) this.cardOps.delete(task.id);
            }
        },
        // UNASSIGN (card -> palette)
        async doUnassignTask(task) {
            // Guard: skip if already saving this task
            if (this.savingCards.has(task.id)) return;
            this.savingCards.add(task.id);

            const opId = Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            this.cardOps.set(task.id, opId);

            const emp = this.employees.find(e => e.waiter_id === task.assigned_to);
            const taskSnapshot = { ...task };

            // Optimistic
            this.planningTasks = this.planningTasks.filter(t => t.id !== task.id);
            if (emp) { emp.task_count--; emp.can_take_more = emp.daily_cap === null || emp.task_count < emp.daily_cap; }

            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.unassign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ task_id: task.id })
                });
                const data = await resp.json();
                // Stale check
                if (this.cardOps.get(task.id) !== opId) return;

                if (!data.success) throw new Error(data.message || 'Gagal unassign');
                this.showToast('Rak dikembalikan ke pool', 'success');
                this.syncCache();
            } catch(e) {
                // Safe rollback: only if this is still the latest op
                if (this.cardOps.get(task.id) === opId) {
                    // Only push back if not already re-added by another action
                    if (!this.planningTasks.find(t => t.id === taskSnapshot.id)) {
                        this.planningTasks.push(taskSnapshot);
                    }
                    if (emp) { emp.task_count++; emp.can_take_more = emp.daily_cap === null || emp.task_count < emp.daily_cap; }
                    this.showToast(e.message || 'Gagal unassign', 'error');
                }
            } finally {
                this.savingCards.delete(task.id);
                if (this.cardOps.get(task.id) === opId) this.cardOps.delete(task.id);
            }
        },

        // OVERRIDE CONFIRM
        async confirmOverride() {
            if (!this.pendingAssign) return;
            const p = this.pendingAssign;
            this.showOverrideModal = false;

            if (p.type === 'assign') {
                await this.executeAssign(p.rack, p.emp, this.overrideReason);
            } else if (p.type === 'reassign') {
                await this.executeReassign(p.task, p.emp, this.overrideReason);
            }
            this.pendingAssign = null;
            this.overrideReason = '';
        },

        // DRAWER ACTIONS
        openDrawer(task) {
            this.drawerTask = task;
            this.drawerReassignTo = '';
            this.drawerRescheduleDate = '';
            this.drawerIgnoreReason = '';
            this.drawerOpen = true;
        },
        closeDrawer() {
            this.drawerOpen = false;
            this.drawerTask = null;
        },

        async doReassign() {
            if (!this.drawerTask || !this.drawerReassignTo) return;
            const newEmp = this.employees.find(e => e.waiter_id === this.drawerReassignTo);
            if (!newEmp) return;
            await this.executeReassign(this.drawerTask, newEmp, '');
            this.closeDrawer();
        },

        async doReschedule() {
            if (!this.drawerTask || !this.drawerRescheduleDate) return;
            this.saving = true;
            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.reschedule") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        task_id: this.drawerTask.id,
                        new_date: this.drawerRescheduleDate
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.planningTasks = this.planningTasks.filter(t => t.id !== this.drawerTask.id);
                    const emp = this.employees.find(e => e.waiter_id === this.drawerTask.assigned_to);
                    if (emp) { emp.task_count--; emp.can_take_more = emp.daily_cap === null || emp.task_count < emp.daily_cap; }
                    this.showToast('Task dipindah ke ' + this.drawerRescheduleDate, 'success');
                    this.closeDrawer();
                } else {
                    throw new Error(data.message || 'Gagal reschedule');
                }
            } catch(e) {
                this.showToast(e.message, 'error');
            }
            this.saving = false;
        },

        async doIgnore() {
            if (!this.drawerTask || !this.drawerIgnoreReason.trim()) return;
            this.saving = true;
            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.ignore") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        task_id: this.drawerTask.id,
                        reason: this.drawerIgnoreReason
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.planningTasks = this.planningTasks.filter(t => t.id !== this.drawerTask.id);
                    const emp = this.employees.find(e => e.waiter_id === this.drawerTask.assigned_to);
                    if (emp) { emp.task_count--; emp.can_take_more = emp.daily_cap === null || emp.task_count < emp.daily_cap; }
                    this.showToast('Task diabaikan', 'success');
                    this.closeDrawer();
                } else {
                    throw new Error(data.message || 'Gagal ignore');
                }
            } catch(e) {
                this.showToast(e.message, 'error');
            }
            this.saving = false;
        },

        async doUnassign() {
            if (!this.drawerTask) return;
            await this.doUnassignTask(this.drawerTask);
            this.closeDrawer();
        },
        // AUTO SUGGEST
        async autoSuggest() {
            this.saving = true;
            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.auto_suggest") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ date: this.selectedDate })
                });
                const data = await resp.json();
                if (data.success) {
                    this.invalidateCache(this.selectedDate);
                    await this.loadDate(this.selectedDate, true);
                    this.showToast('Auto suggest berhasil', 'success');
                } else {
                    throw new Error(data.message || 'Gagal auto suggest');
                }
            } catch(e) {
                this.showToast(e.message, 'error');
            }
            this.saving = false;
        },

        // SAVE DRAFT
        async saveDraft() {
            this.saving = true;
            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.save_draft") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        date: this.selectedDate,
                        tasks: this.planningTasks.map(t => ({ task_id: t.id, assigned_to: t.assigned_to }))
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.showToast('Draft tersimpan', 'success');
                } else {
                    throw new Error(data.message || 'Gagal simpan draft');
                }
            } catch(e) {
                this.showToast(e.message, 'error');
            }
            this.saving = false;
        },

        // PUBLISH
        confirmPublish() {
            this.showPublishModal = true;
        },
        async doPublish() {
            this.showPublishModal = false;
            this.saving = true;
            try {
                const resp = await fetch('{{ route("admin.rack_check.planning.publish") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ date: this.selectedDate })
                });
                const data = await resp.json();
                if (data.success) {
                    this.invalidateCache(this.selectedDate);
                    await this.loadDate(this.selectedDate, true);
                    this.showToast('Planning berhasil dipublish!', 'success');
                } else {
                    throw new Error(data.message || 'Gagal publish');
                }
            } catch(e) {
                this.showToast(e.message, 'error');
            }
            this.saving = false;
        },

        // TOAST
        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3000);
        }
    };
}
</script>
@endpush