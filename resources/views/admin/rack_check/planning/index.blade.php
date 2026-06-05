@extends('admin.layout')

@section('title', 'Planning Cek Rak - Admin')

@section('content')
<div style="max-width: 1100px; margin: 0 auto; padding: 0 12px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
        <div>
            <h2 style="margin: 0 0 6px; color: var(--color-text); font-size: clamp(22px, 5vw, 30px);">
                Planning Cek Rak
            </h2>
            <p style="margin: 0; color: var(--color-text-muted); font-size: 14px;">
                Kelola pembagian tugas cek rak secara manual. Pilih tanggal untuk melihat dan mengatur assignment.
            </p>
        </div>
        <a href="{{ route('admin.rack_check.templates.index') }}"
           style="background: #f1f5f9; color: var(--color-text-secondary); padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px;">
            Template Cek Rak
        </a>
    </div>

    {{-- Weekly Summary Bar --}}
    <div style="margin-bottom: 16px; padding: 12px 16px; background: white; border: 1px solid var(--color-border); border-radius: 10px; box-shadow: var(--shadow-sm);">
        <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: center; font-size: 13px;">
            <span style="font-weight: 600; color: var(--color-text);">7 Hari ke Depan:</span>
            @php
                $totalDue = collect($days)->sum('due_count');
                $totalAssigned = collect($days)->sum('assigned_count');
                $totalUnassigned = $totalDue - $totalAssigned;
            @endphp
            <span style="color: var(--color-text-secondary);">Rak Due: <strong>{{ $totalDue }}</strong></span>
            <span style="color: var(--color-success);">Assigned: <strong>{{ $totalAssigned }}</strong></span>
            @if($totalUnassigned > 0)
                <span style="color: var(--color-warning);">Belum: <strong>{{ $totalUnassigned }}</strong></span>
            @endif
        </div>
    </div>

    {{-- Tab Tanggal --}}
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
        @foreach($days as $i => $day)
            <button type="button" onclick="loadDailyDetail('{{ $day['date'] }}')"
                    id="tab-{{ $day['date'] }}"
                    class="date-tab"
                    style="padding: 10px 14px; border-radius: 8px; border: 1px solid var(--color-border); background: white; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.15s; display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 90px;">
                <span style="font-weight: 600;">{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('D') }}</span>
                <span style="font-size: 12px; color: var(--color-text-muted);">{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}</span>
                @if($day['is_complete'])
                    <span style="font-size: 10px; color: var(--color-success); font-weight: 600;">Lengkap</span>
                @elseif($day['due_count'] > 0)
                    <span style="font-size: 10px; color: var(--color-warning); font-weight: 600;">{{ $day['assigned_count'] }}/{{ $day['due_count'] }}</span>
                @else
                    <span style="font-size: 10px; color: var(--color-text-muted);">Tidak ada</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Daily Detail Content Area --}}
    <div id="daily-content" style="background: white; border: 1px solid var(--color-border); border-radius: 12px; padding: 20px; box-shadow: var(--shadow-sm); min-height: 300px;">
        <div id="loading-state" style="display: none; text-align: center; padding: 40px; color: var(--color-text-muted);">
            <div style="font-size: 24px; margin-bottom: 8px;">Memuat data...</div>
        </div>
        <div id="empty-state" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
            <div style="font-size: 36px; margin-bottom: 8px;">📅</div>
            <p style="margin: 0; font-size: 14px;">Pilih tanggal di atas untuk melihat detail planning.</p>
        </div>
        <div id="detail-state" style="display: none;">
            {{-- Header with actions --}}
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 id="detail-title" style="margin: 0; font-size: 18px; color: var(--color-text);"></h3>
                    <p id="detail-summary" style="margin: 4px 0 0; font-size: 13px; color: var(--color-text-muted);"></p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" onclick="toggleStudioMode()" id="btn-studio"
                            style="padding: 8px 14px; border-radius: 6px; border: 1px solid var(--color-border); background: white; color: var(--color-text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <span>🎛</span> Studio Mode
                    </button>
                    <button type="button" onclick="saveDraft()" id="btn-save-draft"
                            style="padding: 8px 14px; border-radius: 6px; border: 1px solid var(--color-border); background: white; color: var(--color-text-secondary); font-size: 13px; font-weight: 600; cursor: pointer;">
                        Simpan Draft
                    </button>
                    <button type="button" onclick="showPublishConfirm()" id="btn-publish"
                            style="padding: 8px 14px; border-radius: 6px; border: none; background: var(--color-primary); color: white; font-size: 13px; font-weight: 600; cursor: pointer;">
                        Publish Planning
                    </button>
                </div>
            </div>

            {{-- Tabs: Belum Diassign / Per Karyawan / Semua --}}
            <div id="tabs-container" style="display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 0;">
                <button type="button" onclick="switchTab('unassigned')" id="tab-btn-unassigned" class="detail-tab active-tab"
                        style="padding: 8px 14px; border: none; background: none; font-size: 13px; font-weight: 600; cursor: pointer; border-bottom: 2px solid var(--color-primary); color: var(--color-primary);">
                    Belum Diassign
                </button>
                <button type="button" onclick="switchTab('per-employee')" id="tab-btn-per-employee" class="detail-tab"
                        style="padding: 8px 14px; border: none; background: none; font-size: 13px; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; color: var(--color-text-muted);">
                    Per Karyawan
                </button>
                <button type="button" onclick="switchTab('all')" id="tab-btn-all" class="detail-tab"
                        style="padding: 8px 14px; border: none; background: none; font-size: 13px; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; color: var(--color-text-muted);">
                    Semua Rak
                </button>
            </div>

            {{-- Tab Content --}}
            <div id="tab-contents-container">
                <div id="tab-content-unassigned"></div>
                <div id="tab-content-per-employee" style="display: none;"></div>
                <div id="tab-content-all" style="display: none;"></div>
            </div>

            {{-- Studio Board (hidden until toggled) --}}
            <div id="studio-board" style="display: none;"></div>
        </div>
    </div>

    {{-- Modal Assign Rak --}}
    <div id="modal-assign" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 500px; max-height: 80vh; overflow-y: auto; box-shadow: var(--shadow-md);">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 16px; color: var(--color-text);">Assign Rak</h3>
            </div>
            <div style="padding: 16px 20px;">
                <div id="modal-rack-info" style="margin-bottom: 14px; padding: 10px 12px; background: var(--color-bg); border-radius: 8px; font-size: 13px;">
                </div>
                <div id="modal-employee-list" style="display: flex; flex-direction: column; gap: 8px;">
                </div>
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid var(--color-border); text-align: right;">
                <button type="button" onclick="closeAssignModal()"
                        style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--color-border); background: white; color: var(--color-text-secondary); font-size: 13px; cursor: pointer;">
                    Batal
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Override Cap --}}
    <div id="modal-override" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 10000; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 420px; box-shadow: var(--shadow-md);">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 16px; color: var(--color-danger);">Override Max Task</h3>
            </div>
            <div style="padding: 16px 20px;">
                <p id="override-message" style="margin: 0 0 12px; font-size: 14px; color: var(--color-text);"></p>
                <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--color-text);">Alasan override (wajib):</label>
                <textarea id="override-reason" rows="3" style="width: 100%; border: 1px solid var(--color-border); border-radius: 6px; padding: 8px 10px; font-size: 13px; resize: vertical;" placeholder="Tulis alasan override..."></textarea>
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closeOverrideModal()"
                        style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--color-border); background: white; color: var(--color-text-secondary); font-size: 13px; cursor: pointer;">
                    Batal
                </button>
                <button type="button" onclick="confirmOverride()"
                        style="padding: 8px 16px; border-radius: 6px; border: none; background: var(--color-danger); color: white; font-size: 13px; font-weight: 600; cursor: pointer;">
                    Lanjut Assign Override
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Unassign Confirm --}}
    <div id="modal-unassign-confirm" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 10001; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 380px; box-shadow: var(--shadow-md);">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 16px; color: var(--color-warning);">Hapus Assignment</h3>
            </div>
            <div style="padding: 16px 20px;">
                <p id="unassign-confirm-message" style="margin: 0; font-size: 14px; color: var(--color-text);">Hapus assignment untuk rak ini?</p>
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closeUnassignConfirmModal()"
                        style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--color-border); background: white; color: var(--color-text-secondary); font-size: 13px; cursor: pointer;">
                    Batal
                </button>
                <button type="button" onclick="confirmUnassignAction()"
                        style="padding: 8px 16px; border-radius: 6px; border: none; background: var(--color-warning); color: white; font-size: 13px; font-weight: 600; cursor: pointer;">
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Publish Confirm --}}
    <div id="modal-publish" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 450px; box-shadow: var(--shadow-md);">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 16px; color: var(--color-text);">Publish Planning</h3>
            </div>
            <div style="padding: 16px 20px;">
                <div id="publish-summary" style="font-size: 14px; color: var(--color-text);"></div>
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closePublishModal()"
                        style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--color-border); background: white; color: var(--color-text-secondary); font-size: 13px; cursor: pointer;">
                    Batal
                </button>
                <button type="button" onclick="confirmPublish()"
                        style="padding: 8px 16px; border-radius: 6px; border: none; background: var(--color-primary); color: white; font-size: 13px; font-weight: 600; cursor: pointer;">
                    Publish Sekarang
                </button>
            </div>
        </div>
    </div>

<script>
let currentDate = null;
let currentData = null;
let pendingOverride = null;

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function loadDailyDetail(date) {
    currentDate = date;
    document.querySelectorAll('.date-tab').forEach(el => {
        el.style.background = 'white';
        el.style.borderColor = 'var(--color-border)';
    });
    const activeTab = document.getElementById('tab-' + date);
    if (activeTab) {
        activeTab.style.background = 'var(--color-primary-bg)';
        activeTab.style.borderColor = 'var(--color-primary)';
    }

    document.getElementById('empty-state').style.display = 'none';
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('detail-state').style.display = 'none';

    fetch(`{{ route('admin.rack_check.planning.daily') }}?date=${date}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('loading-state').style.display = 'none';
        if (!data.success) {
            alert(data.message || 'Gagal memuat data');
            return;
        }
        currentData = data;
        renderDailyDetail(data);
    })
    .catch(err => {
        document.getElementById('loading-state').style.display = 'none';
        alert('Error: ' + err.message);
    });
}

function renderDailyDetail(data) {
    document.getElementById('detail-state').style.display = 'block';

    const dateObj = new Date(data.date + 'T00:00:00');
    const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
    document.getElementById('detail-title').textContent = 'Planning - ' + dateObj.toLocaleDateString('id-ID', options);

    const totalDue = data.due_racks.length;
    const assigned = data.planning_tasks.filter(t => t.assigned_to).length;
    const unassigned = totalDue - assigned;
    document.getElementById('detail-summary').textContent = `Rak Due: ${totalDue} | Assigned: ${assigned} | Belum: ${unassigned}`;

    renderUnassignedTab(data);
    renderPerEmployeeTab(data);
    renderAllTab(data);
    switchTab('unassigned');
}
</script>

<script>
function renderUnassignedTab(data) {
    const container = document.getElementById('tab-content-unassigned');
    const assignedRackIds = new Set(data.planning_tasks.filter(t => t.assigned_to).map(t => t.template_id + '_' + t.rack_id));
    const unassigned = data.due_racks.filter(r => !assignedRackIds.has(r.template_id + '_' + r.rack_id));

    if (unassigned.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--color-success);font-size:14px;">Semua rak sudah diassign.</div>';
        return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:10px;">';
    unassigned.forEach(rack => {
        html += `<div style="border:1px solid var(--color-border);border-radius:8px;padding:12px 14px;background:var(--color-bg);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <strong style="font-size:14px;color:var(--color-text);">${rack.rack_name || 'Rak'}</strong>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;">
                        Kode: ${rack.rack_code || '-'} | Template: ${rack.template_name || '-'} | Jam: ${rack.schedule_time || '-'}
                    </div>
                </div>
                <button type="button" onclick="openAssignModal('${rack.template_id}', '${rack.rack_id}', '${rack.rack_code || ''}', '${rack.rack_name || ''}')"
                        style="padding:6px 12px;border-radius:6px;border:none;background:var(--color-primary);color:white;font-size:12px;font-weight:600;cursor:pointer;">
                    Assign
                </button>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderPerEmployeeTab(data) {
    const container = document.getElementById('tab-content-per-employee');
    const employees = data.employee_availability || [];

    if (employees.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--color-text-muted);font-size:14px;">Tidak ada data karyawan.</div>';
        return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:12px;">';
    employees.forEach(emp => {
        const statusColor = !emp.is_working ? 'var(--color-text-muted)' : emp.can_take_more ? 'var(--color-success)' : 'var(--color-warning)';
        const statusText = !emp.is_working ? 'Libur' : emp.can_take_more ? 'Bisa diassign' : 'Penuh';
        const assignedTasks = (data.planning_tasks || []).filter(t => t.assigned_to === emp.waiter_id);

        html += `<div style="border:1px solid var(--color-border);border-radius:8px;padding:14px;background:white;${!emp.is_working ? 'opacity:0.6;' : ''}">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <strong style="font-size:14px;color:var(--color-text);">${emp.waiter_name}</strong>
                    <span style="margin-left:8px;font-size:11px;padding:2px 8px;border-radius:10px;background:${statusColor}20;color:${statusColor};font-weight:600;">${statusText}</span>
                </div>
                <span style="font-size:12px;color:var(--color-text-secondary);">Task: ${emp.task_count}/${emp.daily_cap || '-'}</span>
            </div>`;

        if (assignedTasks.length > 0) {
            html += '<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">';
            assignedTasks.forEach(task => {
                html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:var(--color-bg);border-radius:6px;font-size:12px;">
                    <span>${task.rack_name || task.rack_id}</span>
                    <button type="button" onclick="unassignRack('${task.id}')" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:11px;font-weight:600;">Unassign</button>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div>';
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderAllTab(data) {
    const container = document.getElementById('tab-content-all');
    const allRacks = data.due_racks || [];

    if (allRacks.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--color-text-muted);font-size:14px;">Tidak ada rak jatuh tempo.</div>';
        return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
    allRacks.forEach(rack => {
        const task = (data.planning_tasks || []).find(t => t.template_id === rack.template_id && t.rack_id === rack.rack_id);
        const isAssigned = task && task.assigned_to;
        const statusBg = isAssigned ? 'var(--color-success-bg)' : 'var(--color-warning-bg)';
        const statusText = isAssigned ? ('Assigned: ' + (task.assigned_waiter_name || task.assigned_to)) : 'Belum diassign';

        html += `<div style="border:1px solid var(--color-border);border-radius:8px;padding:10px 14px;background:${statusBg};">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                <div>
                    <strong style="font-size:13px;">${rack.rack_name || '-'}</strong>
                    <span style="margin-left:8px;font-size:11px;color:var(--color-text-muted);">${rack.rack_code || '-'}</span>
                </div>
                <span style="font-size:12px;font-weight:500;">${statusText}</span>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}
</script>

<script>
function switchTab(tab) {
    ['unassigned', 'per-employee', 'all'].forEach(t => {
        document.getElementById('tab-content-' + t).style.display = (t === tab) ? 'block' : 'none';
        const btn = document.getElementById('tab-btn-' + t);
        if (btn) {
            btn.style.borderBottomColor = (t === tab) ? 'var(--color-primary)' : 'transparent';
            btn.style.color = (t === tab) ? 'var(--color-primary)' : 'var(--color-text-muted)';
        }
    });
}

function openAssignModal(templateId, rackId, rackCode, rackName) {
    const modal = document.getElementById('modal-assign');
    modal.style.display = 'flex';

    document.getElementById('modal-rack-info').innerHTML = `
        <strong>${rackName}</strong><br>
        <span style="font-size:12px;color:var(--color-text-muted);">Kode: ${rackCode} | Tanggal: ${currentDate}</span>
    `;

    const employees = currentData.employee_availability || [];
    let html = '';
    employees.forEach(emp => {
        const disabled = !emp.is_working;
        const full = emp.is_working && !emp.can_take_more;
        let btnHtml = '';

        if (disabled) {
            btnHtml = '<span style="font-size:12px;color:var(--color-text-muted);">Tidak bisa diassign</span>';
        } else if (full) {
            btnHtml = `<button type="button" onclick="attemptAssign('${templateId}', '${rackId}', '${rackCode}', '${rackName}', '${emp.waiter_id}', '${emp.waiter_name}', true)"
                        style="padding:5px 10px;border-radius:5px;border:1px solid var(--color-warning);background:var(--color-warning-bg);color:var(--color-warning);font-size:11px;font-weight:600;cursor:pointer;">
                Override
            </button>`;
        } else {
            btnHtml = `<button type="button" onclick="attemptAssign('${templateId}', '${rackId}', '${rackCode}', '${rackName}', '${emp.waiter_id}', '${emp.waiter_name}', false)"
                        style="padding:5px 10px;border-radius:5px;border:none;background:var(--color-primary);color:white;font-size:11px;font-weight:600;cursor:pointer;">
                Pilih
            </button>`;
        }

        html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border:1px solid var(--color-border);border-radius:8px;${disabled ? 'opacity:0.5;' : ''}">
            <div>
                <strong style="font-size:13px;">${emp.waiter_name}</strong>
                <div style="font-size:11px;color:var(--color-text-muted);">Task: ${emp.task_count}/${emp.daily_cap || '-'} | ${!emp.is_working ? 'Libur' : 'Masuk'}</div>
            </div>
            ${btnHtml}
        </div>`;
    });

    document.getElementById('modal-employee-list').innerHTML = html;
}

function closeAssignModal() {
    document.getElementById('modal-assign').style.display = 'none';
}

function attemptAssign(templateId, rackId, rackCode, rackName, waiterId, waiterName, needsOverride) {
    if (needsOverride) {
        pendingOverride = { templateId, rackId, rackCode, rackName, waiterId, waiterName };
        document.getElementById('override-message').textContent = `${waiterName} sudah mencapai batas harian. Lanjut assign dengan override?`;
        document.getElementById('override-reason').value = '';
        document.getElementById('modal-override').style.display = 'flex';
        return;
    }
    doAssign(templateId, rackId, rackCode, rackName, waiterId, false, null);
}

function closeOverrideModal() {
    document.getElementById('modal-override').style.display = 'none';
    pendingOverride = null;
}

function confirmOverride() {
    const reason = document.getElementById('override-reason').value.trim();
    if (!reason) {
        alert('Alasan override wajib diisi.');
        return;
    }
    if (!pendingOverride) return;
    const p = pendingOverride;
    closeOverrideModal();
    doAssign(p.templateId, p.rackId, p.rackCode, p.rackName, p.waiterId, true, reason);
}
</script>

<script>
function doAssign(templateId, rackId, rackCode, rackName, waiterId, override, overrideReason) {
    closeAssignModal();
    if (studioActive) {
        studioDoAssign(templateId, rackId, rackCode, rackName, waiterId, override, overrideReason);
        return;
    }
    fetch(`{{ route('admin.rack_check.planning.assign') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            template_id: templateId,
            rack_id: rackId,
            rack_code: rackCode,
            rack_name: rackName,
            waiter_id: waiterId,
            scheduled_date: currentDate,
            override: override,
            override_reason: overrideReason
        })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            alert(result.message || 'Gagal assign');
            return;
        }
        loadDailyDetail(currentDate);
    })
    .catch(err => alert('Error: ' + err.message));
}

function unassignRack(taskId) {
    if (studioActive) {
        studioUnassign(taskId);
        return;
    }
    // Show custom confirm modal for non-studio too
    pendingUnassignTaskId = taskId;
    const task = currentData ? (currentData.planning_tasks || []).find(t => t.id === taskId) : null;
    const rackLabel = task ? (task.rack_name || task.rack_code || task.rack_id) : '';
    document.getElementById('unassign-confirm-message').textContent =
        rackLabel ? `Hapus assignment untuk rak "${rackLabel}"?` : 'Hapus assignment untuk rak ini?';
    document.getElementById('modal-unassign-confirm').style.display = 'flex';
}

function unassignRackDirect(taskId) {
    fetch(`{{ route('admin.rack_check.planning.unassign') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ task_id: taskId })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            alert(result.message || 'Gagal unassign');
            return;
        }
        loadDailyDetail(currentDate);
    })
    .catch(err => alert('Error: ' + err.message));
}

var pendingUnassignTaskId = null;

function closeUnassignConfirmModal() {
    document.getElementById('modal-unassign-confirm').style.display = 'none';
    pendingUnassignTaskId = null;
}

function confirmUnassignAction() {
    const taskId = pendingUnassignTaskId;
    closeUnassignConfirmModal();
    if (!taskId) return;
    if (studioActive) {
        executeUnassign(taskId);
    } else {
        unassignRackDirect(taskId);
    }
}

function saveDraft() {
    if (!currentData || !currentDate) return;
    const tasks = (currentData.planning_tasks || []).map(t => ({
        task_id: t.id,
        assigned_to: t.assigned_to || null
    }));
    if (tasks.length === 0) {
        alert('Tidak ada task untuk disimpan.');
        return;
    }
    fetch(`{{ route('admin.rack_check.planning.save_draft') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ date: currentDate, tasks: tasks })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            alert(result.message || 'Gagal simpan draft');
            return;
        }
        alert(result.message || 'Draft tersimpan.');
    })
    .catch(err => alert('Error: ' + err.message));
}

function showPublishConfirm() {
    if (!currentData || !currentDate) return;
    const tasks = currentData.planning_tasks || [];
    const assigned = tasks.filter(t => t.assigned_to).length;
    const total = currentData.due_racks.length;
    const unassigned = total - assigned;

    let summaryHtml = `<p><strong>Tanggal:</strong> ${currentDate}</p>`;
    summaryHtml += `<p><strong>Rak jatuh tempo:</strong> ${total}</p>`;
    summaryHtml += `<p><strong>Sudah diassign:</strong> ${assigned}</p>`;
    if (unassigned > 0) {
        summaryHtml += `<p style="color:var(--color-warning);font-weight:600;">Belum diassign: ${unassigned}</p>`;
        summaryHtml += `<p style="font-size:12px;color:var(--color-text-muted);margin-top:8px;">Planning belum lengkap. Rak yang belum diassign tidak akan dipublish.</p>`;
    }
    document.getElementById('publish-summary').innerHTML = summaryHtml;
    document.getElementById('modal-publish').style.display = 'flex';
}

function closePublishModal() {
    document.getElementById('modal-publish').style.display = 'none';
}

function confirmPublish() {
    closePublishModal();
    fetch(`{{ route('admin.rack_check.planning.publish') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ date: currentDate })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            alert(result.message || 'Gagal publish');
            return;
        }
        alert(result.message || 'Planning berhasil dipublish.');
        loadDailyDetail(currentDate);
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>

{{-- Modal Reassign --}}
<div id="modal-reassign" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:white;border-radius:12px;width:100%;max-width:500px;max-height:80vh;overflow-y:auto;box-shadow:var(--shadow-md);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--color-border);">
            <h3 style="margin:0;font-size:16px;color:var(--color-text);">Ubah Karyawan</h3>
        </div>
        <div style="padding:16px 20px;">
            <div id="reassign-rack-info" style="margin-bottom:14px;padding:10px 12px;background:var(--color-bg);border-radius:8px;font-size:13px;"></div>
            <div id="reassign-employee-list" style="display:flex;flex-direction:column;gap:8px;"></div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--color-border);text-align:right;">
            <button type="button" onclick="closeReassignModal()" style="padding:8px 16px;border-radius:6px;border:1px solid var(--color-border);background:white;color:var(--color-text-secondary);font-size:13px;cursor:pointer;">Batal</button>
        </div>
    </div>
</div>

{{-- Modal Reschedule --}}
<div id="modal-reschedule" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:white;border-radius:12px;width:100%;max-width:400px;box-shadow:var(--shadow-md);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--color-border);">
            <h3 style="margin:0;font-size:16px;color:var(--color-text);">Pindah Tanggal</h3>
        </div>
        <div style="padding:16px 20px;">
            <div id="reschedule-rack-info" style="margin-bottom:14px;padding:10px 12px;background:var(--color-bg);border-radius:8px;font-size:13px;"></div>
            <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--color-text);">Tanggal Baru:</label>
            <input type="date" id="reschedule-date" style="width:100%;border:1px solid var(--color-border);border-radius:6px;padding:8px 10px;font-size:13px;margin-bottom:12px;">
            <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--color-text);">Alasan (opsional):</label>
            <textarea id="reschedule-reason" rows="2" style="width:100%;border:1px solid var(--color-border);border-radius:6px;padding:8px 10px;font-size:13px;resize:vertical;" placeholder="Alasan pindah tanggal..."></textarea>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--color-border);display:flex;justify-content:flex-end;gap:8px;">
            <button type="button" onclick="closeRescheduleModal()" style="padding:8px 16px;border-radius:6px;border:1px solid var(--color-border);background:white;color:var(--color-text-secondary);font-size:13px;cursor:pointer;">Batal</button>
            <button type="button" onclick="confirmReschedule()" style="padding:8px 16px;border-radius:6px;border:none;background:var(--color-primary);color:white;font-size:13px;font-weight:600;cursor:pointer;">Pindah Tanggal</button>
        </div>
    </div>
</div>

{{-- Modal Ignore --}}
<div id="modal-ignore" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:white;border-radius:12px;width:100%;max-width:400px;box-shadow:var(--shadow-md);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--color-border);">
            <h3 style="margin:0;font-size:16px;color:var(--color-warning);">Abaikan Rak</h3>
        </div>
        <div style="padding:16px 20px;">
            <div id="ignore-rack-info" style="margin-bottom:14px;padding:10px 12px;background:var(--color-bg);border-radius:8px;font-size:13px;"></div>
            <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--color-text);">Alasan (wajib):</label>
            <textarea id="ignore-reason" rows="3" style="width:100%;border:1px solid var(--color-border);border-radius:6px;padding:8px 10px;font-size:13px;resize:vertical;" placeholder="Contoh: Rak sedang kosong, area renovasi..."></textarea>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--color-border);display:flex;justify-content:flex-end;gap:8px;">
            <button type="button" onclick="closeIgnoreModal()" style="padding:8px 16px;border-radius:6px;border:1px solid var(--color-border);background:white;color:var(--color-text-secondary);font-size:13px;cursor:pointer;">Batal</button>
            <button type="button" onclick="confirmIgnore()" style="padding:8px 16px;border-radius:6px;border:none;background:var(--color-warning);color:white;font-size:13px;font-weight:600;cursor:pointer;">Abaikan dengan Alasan</button>
        </div>
    </div>
</div>

<script>
let pendingReassignTaskId = null;
let pendingRescheduleTaskId = null;
let pendingIgnoreTaskId = null;

function openReassignModal(taskId, rackName, rackCode, currentWaiter) {
    pendingReassignTaskId = taskId;
    document.getElementById('reassign-rack-info').innerHTML = `<strong>${rackName}</strong><br><span style="font-size:12px;color:var(--color-text-muted);">Kode: ${rackCode} | Saat ini: ${currentWaiter}</span>`;
    const employees = currentData.employee_availability || [];
    let html = '';
    employees.forEach(emp => {
        const disabled = !emp.is_working;
        const full = emp.is_working && !emp.can_take_more;
        let btnHtml = '';
        if (disabled) {
            btnHtml = '<span style="font-size:12px;color:var(--color-text-muted);">Libur</span>';
        } else if (full) {
            btnHtml = `<button type="button" onclick="doReassign('${emp.waiter_id}', true)" style="padding:5px 10px;border-radius:5px;border:1px solid var(--color-warning);background:var(--color-warning-bg);color:var(--color-warning);font-size:11px;font-weight:600;cursor:pointer;">Override</button>`;
        } else {
            btnHtml = `<button type="button" onclick="doReassign('${emp.waiter_id}', false)" style="padding:5px 10px;border-radius:5px;border:none;background:var(--color-primary);color:white;font-size:11px;font-weight:600;cursor:pointer;">Pilih</button>`;
        }
        html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border:1px solid var(--color-border);border-radius:8px;${disabled ? 'opacity:0.5;' : ''}">
            <div><strong style="font-size:13px;">${emp.waiter_name}</strong><div style="font-size:11px;color:var(--color-text-muted);">Task: ${emp.task_count}/${emp.daily_cap || '-'}</div></div>
            ${btnHtml}
        </div>`;
    });
    document.getElementById('reassign-employee-list').innerHTML = html;
    document.getElementById('modal-reassign').style.display = 'flex';
}

function closeReassignModal() { document.getElementById('modal-reassign').style.display = 'none'; pendingReassignTaskId = null; }

function doReassign(newWaiterId, needsOverride) {
    if (needsOverride) {
        pendingOverride = { action: 'reassign', taskId: pendingReassignTaskId, newWaiterId: newWaiterId };
        document.getElementById('override-message').textContent = 'Karyawan sudah penuh. Lanjut reassign dengan override?';
        document.getElementById('override-reason').value = '';
        document.getElementById('modal-override').style.display = 'flex';
        return;
    }
    submitReassign(newWaiterId, false, null);
}

function submitReassign(newWaiterId, override, reason) {
    closeReassignModal();
    fetch(`{{ route('admin.rack_check.planning.reassign') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ task_id: pendingReassignTaskId, new_waiter_id: newWaiterId, override: override, override_reason: reason })
    })
    .then(r => r.json())
    .then(result => { if (!result.success) { alert(result.message); return; } loadDailyDetail(currentDate); })
    .catch(err => alert('Error: ' + err.message));
}

function openRescheduleModal(taskId, rackName, rackCode) {
    pendingRescheduleTaskId = taskId;
    document.getElementById('reschedule-rack-info').innerHTML = `<strong>${rackName}</strong><br><span style="font-size:12px;color:var(--color-text-muted);">Kode: ${rackCode}</span>`;
    document.getElementById('reschedule-date').value = '';
    document.getElementById('reschedule-reason').value = '';
    document.getElementById('modal-reschedule').style.display = 'flex';
}

function closeRescheduleModal() { document.getElementById('modal-reschedule').style.display = 'none'; pendingRescheduleTaskId = null; }

function confirmReschedule() {
    const newDate = document.getElementById('reschedule-date').value;
    const reason = document.getElementById('reschedule-reason').value.trim();
    if (!newDate) { alert('Pilih tanggal baru.'); return; }
    closeRescheduleModal();
    fetch(`{{ route('admin.rack_check.planning.reschedule') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ task_id: pendingRescheduleTaskId, new_date: newDate, reason: reason })
    })
    .then(r => r.json())
    .then(result => { if (!result.success) { alert(result.message); return; } loadDailyDetail(currentDate); })
    .catch(err => alert('Error: ' + err.message));
}

function openIgnoreModal(taskId, rackName, rackCode) {
    pendingIgnoreTaskId = taskId;
    document.getElementById('ignore-rack-info').innerHTML = `<strong>${rackName}</strong><br><span style="font-size:12px;color:var(--color-text-muted);">Kode: ${rackCode}</span>`;
    document.getElementById('ignore-reason').value = '';
    document.getElementById('modal-ignore').style.display = 'flex';
}

function closeIgnoreModal() { document.getElementById('modal-ignore').style.display = 'none'; pendingIgnoreTaskId = null; }

function confirmIgnore() {
    const reason = document.getElementById('ignore-reason').value.trim();
    if (!reason || reason.length < 3) { alert('Alasan wajib diisi (min 3 karakter).'); return; }
    closeIgnoreModal();
    fetch(`{{ route('admin.rack_check.planning.ignore') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ task_id: pendingIgnoreTaskId, reason: reason })
    })
    .then(r => r.json())
    .then(result => { if (!result.success) { alert(result.message); return; } loadDailyDetail(currentDate); })
    .catch(err => alert('Error: ' + err.message));
}
</script>

<script>
// Override confirmOverride to support reassign too
var _origConfirmOverride = confirmOverride;
confirmOverride = function() {
    const reason = document.getElementById('override-reason').value.trim();
    if (!reason) { alert('Alasan override wajib diisi.'); return; }
    if (pendingOverride && pendingOverride.action === 'reassign') {
        const p = pendingOverride;
        closeOverrideModal();
        submitReassign(p.newWaiterId, true, reason);
        return;
    }
    _origConfirmOverride();
};

// Update renderPerEmployeeTab to include action menu
var _origRenderPerEmployee = renderPerEmployeeTab;
renderPerEmployeeTab = function(data) {
    const container = document.getElementById('tab-content-per-employee');
    const employees = data.employee_availability || [];
    if (employees.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--color-text-muted);font-size:14px;">Tidak ada data karyawan.</div>';
        return;
    }
    let html = '<div style="display:flex;flex-direction:column;gap:12px;">';
    employees.forEach(emp => {
        const statusColor = !emp.is_working ? 'var(--color-text-muted)' : emp.can_take_more ? 'var(--color-success)' : 'var(--color-warning)';
        const statusText = !emp.is_working ? 'Libur' : emp.can_take_more ? 'Bisa diassign' : 'Penuh';
        const assignedTasks = (data.planning_tasks || []).filter(t => t.assigned_to === emp.waiter_id);
        html += `<div style="border:1px solid var(--color-border);border-radius:8px;padding:14px;background:white;${!emp.is_working ? 'opacity:0.6;' : ''}">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div><strong style="font-size:14px;color:var(--color-text);">${emp.waiter_name}</strong>
                <span style="margin-left:8px;font-size:11px;padding:2px 8px;border-radius:10px;background:${statusColor}20;color:${statusColor};font-weight:600;">${statusText}</span></div>
                <span style="font-size:12px;color:var(--color-text-secondary);">Task: ${emp.task_count}/${emp.daily_cap || '-'}</span>
            </div>`;
        if (assignedTasks.length > 0) {
            html += '<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">';
            assignedTasks.forEach(task => {
                html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--color-bg);border-radius:6px;font-size:12px;">
                    <span><strong>${task.rack_name || task.rack_id}</strong> <span style="color:var(--color-text-muted);">${task.rack_code || ''}</span></span>
                    <div style="display:flex;gap:6px;">
                        <button onclick="openReassignModal('${task.id}','${task.rack_name || ''}','${task.rack_code || ''}','${emp.waiter_name}')" style="background:none;border:none;color:var(--color-primary);cursor:pointer;font-size:11px;font-weight:600;">Ubah</button>
                        <button onclick="unassignRack('${task.id}')" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:11px;font-weight:600;">Unassign</button>
                        <button onclick="openRescheduleModal('${task.id}','${task.rack_name || ''}','${task.rack_code || ''}')" style="background:none;border:none;color:var(--color-info);cursor:pointer;font-size:11px;font-weight:600;">Pindah</button>
                        <button onclick="openIgnoreModal('${task.id}','${task.rack_name || ''}','${task.rack_code || ''}')" style="background:none;border:none;color:var(--color-warning);cursor:pointer;font-size:11px;font-weight:600;">Abaikan</button>
                    </div>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div>';
    });
    html += '</div>';
    container.innerHTML = html;
};
</script>
<style>
.studio-drag-over {
    border-color: var(--color-primary) !important;
    background-color: rgba(102, 126, 234, 0.05) !important;
}
.studio-rack-card {
    background: white;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 10px 12px;
    cursor: grab;
    transition: box-shadow 0.2s;
    user-select: none;
}
.studio-rack-card:active {
    cursor: grabbing;
}
.studio-rack-card:hover {
    box-shadow: var(--shadow-sm);
}
.studio-dropzone {
    background: var(--color-bg);
    border: 2px dashed var(--color-border);
    border-radius: 8px;
    min-height: 100px;
    padding: 10px;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
@media (max-width: 767px) {
    #studio-board-desktop { display: none !important; }
    #studio-board-mobile { display: flex !important; flex-direction: column; gap: 16px; }
}
@media (min-width: 768px) {
    #studio-board-desktop { display: flex !important; gap: 20px; }
    #studio-board-mobile { display: none !important; }
}
</style>

<script>
let isStudioMode = false;

function toggleStudioMode() {
    isStudioMode = !isStudioMode;
    const btn = document.getElementById('btn-studio');
    const tabsContainer = document.getElementById('tabs-container');
    const tabContents = document.getElementById('tab-contents-container');
    const studioBoard = document.getElementById('studio-board');

    if (isStudioMode) {
        btn.style.background = 'var(--color-primary)';
        btn.style.color = 'white';
        tabsContainer.style.display = 'none';
        tabContents.style.display = 'none';
        studioBoard.style.display = 'block';
        if (currentData) renderStudioBoard(currentData);
    } else {
        btn.style.background = 'white';
        btn.style.color = 'var(--color-text-secondary)';
        tabsContainer.style.display = 'flex';
        tabContents.style.display = 'block';
        studioBoard.style.display = 'none';
    }
}

// Override renderDailyDetail to hook studio render
var _origRenderDailyDetail = renderDailyDetail;
renderDailyDetail = function(data) {
    _origRenderDailyDetail(data);
    if (isStudioMode) {
        renderStudioBoard(data);
    }
};

function renderStudioBoard(data) {
    const container = document.getElementById('studio-board');
    
    const assignedRackIds = new Set(data.planning_tasks.filter(t => t.assigned_to).map(t => t.template_id + '_' + t.rack_id));
    const unassigned = data.due_racks.filter(r => !assignedRackIds.has(r.template_id + '_' + r.rack_id));
    const employees = data.employee_availability || [];

    // Desktop Layout (Drag & Drop)
    let desktopHtml = `
        <div id="studio-board-desktop">
            <!-- Left Column: Unassigned Racks -->
            <div style="flex: 0 0 30%; max-width: 30%; border-right: 1px solid var(--color-border); padding-right: 16px;">
                <h4 style="margin: 0 0 12px; font-size: 14px; color: var(--color-text);">Belum Diassign (${unassigned.length})</h4>
                <div style="display: flex; flex-direction: column; gap: 10px; max-height: 600px; overflow-y: auto; padding-bottom: 20px;">
                    ${unassigned.map(rack => `
                        <div class="studio-rack-card" draggable="true" 
                             ondragstart="studioDragStart(event, '${rack.template_id}', '${rack.rack_id}', '${rack.rack_code || ''}', '${rack.rack_name || ''}')">
                            <strong style="font-size: 13px; display: block;">${rack.rack_name || 'Rak'}</strong>
                            <span style="font-size: 11px; color: var(--color-text-muted);">${rack.rack_code || '-'}</span>
                        </div>
                    `).join('')}
                    ${unassigned.length === 0 ? '<div style="font-size: 13px; color: var(--color-text-muted); text-align: center; padding: 20px;">Semua rak sudah diassign</div>' : ''}
                </div>
            </div>
            
            <!-- Right Column: Employees -->
            <div style="flex: 1; padding-left: 16px;">
                <h4 style="margin: 0 0 12px; font-size: 14px; color: var(--color-text);">Board Karyawan</h4>
                <div style="display: flex; flex-direction: column; gap: 16px; max-height: 600px; overflow-y: auto; padding-bottom: 20px;">
                    ${employees.filter(e => e.is_working).map(emp => {
                        const assignedTasks = (data.planning_tasks || []).filter(t => t.assigned_to === emp.waiter_id);
                        return `
                        <div style="border: 1px solid var(--color-border); border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="font-size: 14px;">${emp.waiter_name}</strong>
                                <span style="font-size: 12px; color: ${emp.can_take_more ? 'var(--color-text-secondary)' : 'var(--color-warning)'};">
                                    Task: ${emp.task_count}/${emp.daily_cap || '-'}
                                </span>
                            </div>
                            <div class="studio-dropzone" 
                                 ondragover="studioDragOver(event)" 
                                 ondragleave="studioDragLeave(event)" 
                                 ondrop="studioDrop(event, '${emp.waiter_id}', '${emp.waiter_name}', ${!emp.can_take_more})">
                                ${assignedTasks.map(task => `
                                    <div style="background: white; border: 1px solid var(--color-border); border-radius: 6px; padding: 8px 10px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong style="font-size: 12px;">${task.rack_name || task.rack_id}</strong>
                                            <span style="font-size: 11px; color: var(--color-text-muted); margin-left: 6px;">${task.rack_code || ''}</span>
                                        </div>
                                        <button type="button" onclick="unassignRack('${task.id}')" style="background: none; border: none; color: var(--color-danger); cursor: pointer; padding: 4px; border-radius: 4px;">✕</button>
                                    </div>
                                `).join('')}
                                ${assignedTasks.length === 0 ? '<div style="font-size: 12px; color: var(--color-text-muted); text-align: center; margin: auto;">Drop rak ke sini</div>' : ''}
                            </div>
                        </div>
                    `}).join('')}
                </div>
            </div>
        </div>
    `;

    // Mobile Layout (List & Buttons)
    let mobileHtml = `
        <div id="studio-board-mobile">
            <h4 style="margin: 0 0 4px; font-size: 14px; color: var(--color-text);">Belum Diassign (${unassigned.length})</h4>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                ${unassigned.map(rack => `
                    <div style="border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; background: white; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 13px;">${rack.rack_name || 'Rak'}</strong>
                            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">${rack.rack_code || '-'}</div>
                        </div>
                        <button type="button" onclick="openAssignModal('${rack.template_id}', '${rack.rack_id}', '${rack.rack_code || ''}', '${rack.rack_name || ''}')"
                                style="padding: 6px 12px; border-radius: 6px; border: none; background: var(--color-primary); color: white; font-size: 12px; font-weight: 600;">
                            Assign ke...
                        </button>
                    </div>
                `).join('')}
                ${unassigned.length === 0 ? '<div style="font-size: 13px; color: var(--color-text-muted); text-align: center; padding: 10px;">Semua rak sudah diassign</div>' : ''}
            </div>

            <h4 style="margin: 0 0 4px; font-size: 14px; color: var(--color-text);">Sudah Diassign</h4>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                ${employees.filter(e => e.is_working).map(emp => {
                    const assignedTasks = (data.planning_tasks || []).filter(t => t.assigned_to === emp.waiter_id);
                    if (assignedTasks.length === 0) return '';
                    return `
                    <div style="border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; background: var(--color-bg);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="font-size: 13px;">${emp.waiter_name}</strong>
                            <span style="font-size: 11px; color: var(--color-text-secondary);">Task: ${emp.task_count}/${emp.daily_cap || '-'}</span>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            ${assignedTasks.map(task => `
                                <div style="background: white; border: 1px solid var(--color-border); border-radius: 6px; padding: 8px 10px; display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 12px;">${task.rack_name || task.rack_id}</span>
                                    <button type="button" onclick="unassignRack('${task.id}')" style="background: none; border: none; color: var(--color-danger); font-size: 11px; font-weight: 600;">Unassign</button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `}).join('')}
            </div>
        </div>
    `;

    container.innerHTML = desktopHtml + mobileHtml;
}

// Drag and Drop Handlers
function studioDragStart(ev, templateId, rackId, rackCode, rackName) {
    ev.dataTransfer.setData("text/plain", JSON.stringify({ templateId, rackId, rackCode, rackName }));
    ev.dataTransfer.effectAllowed = "move";
}

function studioDragOver(ev) {
    ev.preventDefault();
    ev.dataTransfer.dropEffect = "move";
    ev.currentTarget.classList.add('studio-drag-over');
}

function studioDragLeave(ev) {
    ev.currentTarget.classList.remove('studio-drag-over');
}

function studioDrop(ev, waiterId, waiterName, needsOverride) {
    ev.preventDefault();
    ev.currentTarget.classList.remove('studio-drag-over');
    
    try {
        const dataStr = ev.dataTransfer.getData("text/plain");
        if (!dataStr) return;
        
        const data = JSON.parse(dataStr);
        attemptAssign(data.templateId, data.rackId, data.rackCode, data.rackName, waiterId, waiterName, needsOverride);
    } catch (e) {
        console.error("Drop error:", e);
    }
}
</script>

{{-- ===== STUDIO MODE ===== --}}
<style>
.studio-rack-card {
    background: white;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 10px 12px;
    cursor: grab;
    user-select: none;
    transition: box-shadow 0.15s, opacity 0.15s;
    position: relative;
}
.studio-rack-card:active { cursor: grabbing; }
.studio-rack-card.dragging { opacity: 0.45; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.studio-drop-zone {
    background: var(--color-bg);
    border: 2px dashed var(--color-border);
    border-radius: 8px;
    min-height: 100px;
    padding: 8px;
    transition: border-color 0.15s, background 0.15s;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.studio-drop-zone.drag-over {
    border-color: var(--color-primary);
    background: rgba(102,126,234,0.05);
}
.studio-assigned-card {
    background: white;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.studio-unassign-btn {
    background: none;
    border: none;
    color: var(--color-danger);
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    padding: 0 2px;
    flex-shrink: 0;
}
.studio-unassign-btn:hover { opacity: 0.7; }
@media (max-width: 767px) {
    .studio-board-desktop { display: none !important; }
    .studio-board-mobile { display: flex !important; }
}
@media (min-width: 768px) {
    .studio-board-desktop { display: flex !important; }
    .studio-board-mobile { display: none !important; }
}
</style>

<script>
// ─── Studio Mode State ───────────────────────────────────────────────────────
let studioActive = false;

function toggleStudioMode() {
    studioActive = !studioActive;
    const btn = document.getElementById('btn-studio');
    const tabNav = document.querySelector('#detail-state > div:nth-child(2)'); // tab buttons row
    const tabContents = document.querySelectorAll('#tab-content-unassigned, #tab-content-per-employee, #tab-content-all');
    const studioBoard = document.getElementById('studio-board');

    if (studioActive) {
        btn.style.background = 'var(--color-primary)';
        btn.style.color = 'white';
        btn.style.borderColor = 'var(--color-primary)';
        tabNav && (tabNav.style.display = 'none');
        tabContents.forEach(el => el.style.display = 'none');
        studioBoard.style.display = 'block';
        if (currentData) renderStudioBoard(currentData);
    } else {
        btn.style.background = 'white';
        btn.style.color = 'var(--color-text-secondary)';
        btn.style.borderColor = 'var(--color-border)';
        tabNav && (tabNav.style.display = '');
        studioBoard.style.display = 'none';
        if (currentData) {
            renderUnassignedTab(currentData);
            renderPerEmployeeTab(currentData);
            renderAllTab(currentData);
            switchTab('unassigned');
        }
    }
}

// ─── Detect tab nav correctly ─────────────────────────────────────────────────
function getTabNavEl() {
    // The tab buttons row is the div with border-bottom inside #detail-state
    const detailState = document.getElementById('detail-state');
    if (!detailState) return null;
    return Array.from(detailState.children).find(el =>
        el.querySelector && el.querySelector('#tab-btn-unassigned')
    ) || null;
}

// ─── Render Studio Board ──────────────────────────────────────────────────────
function renderStudioBoard(data) {
    const board = document.getElementById('studio-board');
    const isMobile = window.innerWidth < 768;

    const assignedRackIds = new Set(
        (data.planning_tasks || []).filter(t => t.assigned_to)
            .map(t => t.template_id + '_' + t.rack_id)
    );
    const unassigned = (data.due_racks || []).filter(
        r => !assignedRackIds.has(r.template_id + '_' + r.rack_id)
    );
    const workingEmployees = (data.employee_availability || []).filter(e => e.is_working);

    if (isMobile) {
        board.innerHTML = renderStudioMobile(data, unassigned, workingEmployees);
    } else {
        board.innerHTML = renderStudioDesktop(data, unassigned, workingEmployees);
        attachDropListeners(); // no-op if already attached (event delegation)
    }
}

// ─── Desktop Board ────────────────────────────────────────────────────────────
function renderStudioDesktop(data, unassigned, employees) {
    const leftCards = unassigned.map(rack => `
        <div class="studio-rack-card"
             draggable="true"
             data-rack-id="${rack.rack_id}"
             data-rack-name="${escHtml(rack.rack_name || '')}"
             data-rack-code="${escHtml(rack.rack_code || '')}"
             data-template-id="${rack.template_id}">
            <div style="font-size:13px;font-weight:600;color:var(--color-text);">${escHtml(rack.rack_name || 'Rak')}</div>
            <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">${escHtml(rack.rack_code || '-')}</div>
        </div>
    `).join('');

    const leftEmpty = unassigned.length === 0
        ? '<div style="text-align:center;padding:20px;color:var(--color-success);font-size:13px;">Semua rak sudah diassign ✓</div>'
        : '';

    const empCols = employees.map(emp => {
        const assignedTasks = (data.planning_tasks || []).filter(t => t.assigned_to === emp.waiter_id);
        const statusColor = emp.can_take_more ? 'var(--color-success)' : 'var(--color-warning)';
        const statusText = emp.can_take_more ? 'Bisa' : 'Penuh';
        const assignedCards = assignedTasks.map(task => `
            <div class="studio-assigned-card" draggable="true"
                 data-task-key="${task.template_id}_${task.rack_id}"
                 data-rack-id="${task.rack_id}"
                 data-rack-name="${escHtml(task.rack_name || '')}"
                 data-rack-code="${escHtml(task.rack_code || '')}"
                 data-template-id="${task.template_id}"
                 data-task-id="${task.id}"
                 data-assigned-to="${task.assigned_to || ''}">
                <div style="min-width:0;">
                    <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(task.rack_name || task.rack_id)}</div>
                    <div style="font-size:11px;color:var(--color-text-muted);">${escHtml(task.rack_code || '')}</div>
                </div>
                <button class="studio-unassign-btn" data-task-id="${task.id}" data-task-key="${task.template_id}_${task.rack_id}" title="Unassign">\u00d7</button>
            </div>
        `).join('');

        const dropHint = assignedTasks.length === 0
            ? '<div style="text-align:center;padding:16px 8px;color:var(--color-text-muted);font-size:12px;pointer-events:none;">Taruh rak di sini</div>'
            : '';

        return `
            <div style="flex:0 0 220px;display:flex;flex-direction:column;gap:8px;">
                <div style="text-align:center;">
                    <div style="font-size:13px;font-weight:600;color:var(--color-text);">${escHtml(emp.waiter_name)}</div>
                    <div style="font-size:11px;margin-top:2px;">
                        <span class="studio-emp-status" style="color:${statusColor};font-weight:600;">${statusText}</span>
                        <span class="studio-emp-count" style="color:var(--color-text-muted);"> · ${emp.task_count}/${emp.daily_cap || '∞'}</span>
                    </div>
                </div>
                <div class="studio-drop-zone"
                     data-waiter-id="${emp.waiter_id}"
                     data-waiter-name="${escHtml(emp.waiter_name)}"
                     data-can-take-more="${emp.can_take_more ? '1' : '0'}">
                    ${assignedCards}${dropHint}
                </div>
            </div>
        `;
    }).join('');

    const noEmployees = employees.length === 0
        ? '<div style="padding:30px;color:var(--color-text-muted);font-size:13px;">Tidak ada karyawan masuk hari ini.</div>'
        : '';

    return `
        <div class="studio-board-desktop" style="gap:16px;align-items:flex-start;">
            {{-- Left: unassigned racks --}}
            <div style="flex:0 0 28%;min-width:180px;max-width:260px;">
                <div style="font-size:13px;font-weight:700;color:var(--color-text);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--color-border);">
                    Rak Belum Diassign <span style="font-size:12px;font-weight:400;color:var(--color-text-muted);">(${unassigned.length})</span>
                </div>
                <div id="studio-unassigned-list" style="display:flex;flex-direction:column;gap:6px;">
                    ${leftCards}${leftEmpty}
                </div>
            </div>
            {{-- Right: employee columns --}}
            <div style="flex:1;overflow-x:auto;">
                <div style="font-size:13px;font-weight:700;color:var(--color-text);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--color-border);">
                    Per Karyawan
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start;min-width:max-content;">
                    ${empCols}${noEmployees}
                </div>
            </div>
        </div>
    `;
}

// ─── Mobile Board ─────────────────────────────────────────────────────────────
function renderStudioMobile(data, unassigned, employees) {
    const rackCards = unassigned.map(rack => `
        <div style="border:1px solid var(--color-border);border-radius:8px;padding:10px 12px;background:white;display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--color-text);">${escHtml(rack.rack_name || 'Rak')}</div>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">${escHtml(rack.rack_code || '-')}</div>
            </div>
            <button type="button"
                    onclick="openAssignModal('${rack.template_id}','${rack.rack_id}','${escAttr(rack.rack_code || '')}','${escAttr(rack.rack_name || '')}')"
                    style="flex-shrink:0;padding:6px 12px;border-radius:6px;border:none;background:var(--color-primary);color:white;font-size:12px;font-weight:600;cursor:pointer;">
                Assign ke...
            </button>
        </div>
    `).join('');

    const emptyMsg = unassigned.length === 0
        ? '<div style="text-align:center;padding:20px;color:var(--color-success);font-size:13px;">Semua rak sudah diassign ✓</div>'
        : '';

    return `
        <div class="studio-board-mobile" style="flex-direction:column;gap:6px;">
            <div style="font-size:13px;font-weight:700;color:var(--color-text);margin-bottom:4px;">
                Rak Belum Diassign <span style="font-size:12px;font-weight:400;color:var(--color-text-muted);">(${unassigned.length})</span>
            </div>
            ${rackCards}${emptyMsg}
        </div>
    `;
}

// ─── Event Delegation (single listener on #studio-board) ─────────────────────
let studioListenersAttached = false;

function attachDropListeners() {
    if (studioListenersAttached) return; // only attach once
    const board = document.getElementById('studio-board');
    if (!board) return;
    studioListenersAttached = true;

    // Dragstart delegation
    board.addEventListener('dragstart', function(e) {
        const card = e.target.closest('.studio-rack-card') || e.target.closest('.studio-assigned-card');
        if (!card) return;
        // Don't drag if clicking unassign button
        if (e.target.closest('.studio-unassign-btn')) { e.preventDefault(); return; }
        draggedRack = {
            rackId:     card.dataset.rackId,
            rackName:   card.dataset.rackName,
            rackCode:   card.dataset.rackCode,
            templateId: card.dataset.templateId,
            fromWaiterId: card.dataset.assignedTo || null, // track source waiter for reassign
            taskId:     card.dataset.taskId || null,
        };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify(draggedRack));
        card.classList.add('dragging');
    });

    // Dragend delegation
    board.addEventListener('dragend', function(e) {
        const card = e.target.closest('.studio-rack-card') || e.target.closest('.studio-assigned-card');
        if (card) card.classList.remove('dragging');
        draggedRack = null;
    });

    // Dragover delegation
    board.addEventListener('dragover', function(e) {
        const zone = e.target.closest('.studio-drop-zone');
        if (!zone) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        zone.classList.add('drag-over');
    });

    // Dragleave delegation
    board.addEventListener('dragleave', function(e) {
        const zone = e.target.closest('.studio-drop-zone');
        if (!zone) return;
        if (!zone.contains(e.relatedTarget)) {
            zone.classList.remove('drag-over');
        }
    });

    // Drop delegation
    board.addEventListener('drop', function(e) {
        const zone = e.target.closest('.studio-drop-zone');
        if (!zone) return;
        e.preventDefault();
        zone.classList.remove('drag-over');

        let rack = draggedRack;
        if (!rack) {
            try { rack = JSON.parse(e.dataTransfer.getData('text/plain')); } catch { return; }
        }
        if (!rack || !rack.rackId) return;

        const waiterId   = zone.dataset.waiterId;
        const waiterName = zone.dataset.waiterName;
        const canTakeMore = zone.dataset.canTakeMore === '1';

        // Skip if dropping on same waiter
        if (rack.fromWaiterId && rack.fromWaiterId === waiterId) return;

        if (!canTakeMore) {
            pendingOverride = {
                templateId: rack.templateId,
                rackId:     rack.rackId,
                rackCode:   rack.rackCode,
                rackName:   rack.rackName,
                waiterId:   waiterId,
                waiterName: waiterName,
                fromWaiterId: rack.fromWaiterId || null,
                taskId:     rack.taskId || null,
            };
            document.getElementById('override-message').textContent =
                `${waiterName} sudah mencapai batas harian. Lanjut assign dengan override?`;
            document.getElementById('override-reason').value = '';
            document.getElementById('modal-override').style.display = 'flex';
            return;
        }

        // If reassign (from another waiter), do unassign first then assign
        if (rack.fromWaiterId) {
            studioReassign(rack.templateId, rack.rackId, rack.rackCode, rack.rackName, rack.fromWaiterId, waiterId, rack.taskId, false, null);
        } else {
            studioDoAssign(rack.templateId, rack.rackId, rack.rackCode, rack.rackName, waiterId, false, null);
        }
    });

    // Click delegation for unassign buttons
    board.addEventListener('click', function(e) {
        const btn = e.target.closest('.studio-unassign-btn');
        if (!btn) return;
        const taskId = btn.dataset.taskId;
        const taskKey = btn.dataset.taskKey;
        // Find real task_id from currentData if we only have taskKey
        let resolvedId = taskId;
        if (!resolvedId && taskKey && currentData) {
            const parts = taskKey.split('_');
            const tId = parts[0];
            const rId = parts.slice(1).join('_');
            const task = (currentData.planning_tasks || []).find(t => t.template_id === tId && t.rack_id === rId);
            if (task) resolvedId = task.id;
        }
        if (resolvedId) studioUnassign(resolvedId);
    });
}

let draggedRack = null;

// ─── Studio assign / unassign (optimistic UI) ───────────────────────────────
const savingCards = new Set(); // guard against double-action

function updateSummaryLine() {
    if (!currentData) return;
    const totalDue = (currentData.due_racks || []).length;
    const assigned = (currentData.planning_tasks || []).filter(t => t.assigned_to).length;
    const el = document.getElementById('detail-summary');
    if (el) el.textContent = `Rak Due: ${totalDue} | Assigned: ${assigned} | Belum: ${totalDue - assigned}`;
}

function updateEmployeeCounter(waiterId) {
    const zone = document.querySelector(`.studio-drop-zone[data-waiter-id="${waiterId}"]`);
    if (!zone) return;
    const cards = zone.querySelectorAll('.studio-assigned-card');
    const taskCount = cards.length;
    const header = zone.closest('[style*="flex:0 0 220px"]');
    if (!header) return;

    // Determine daily cap from currentData
    let dailyCap = null;
    if (currentData && currentData.employee_availability) {
        const emp = currentData.employee_availability.find(e => e.waiter_id === waiterId);
        if (emp) dailyCap = emp.daily_cap;
    }

    // Update can_take_more attribute
    const canTakeMore = dailyCap === null || dailyCap === 0 || taskCount < dailyCap;
    zone.dataset.canTakeMore = canTakeMore ? '1' : '0';

    // Update counter text
    const capDisplay = dailyCap || '\u221e';
    const countEl = header.querySelector('.studio-emp-count');
    if (countEl) {
        countEl.textContent = ` \u00b7 ${taskCount}/${capDisplay}`;
    }

    // Update status badge (Bisa/Penuh)
    const statusEl = header.querySelector('.studio-emp-status');
    if (statusEl) {
        if (canTakeMore) {
            statusEl.style.color = 'var(--color-success)';
            statusEl.textContent = 'Bisa';
        } else {
            statusEl.style.color = 'var(--color-warning)';
            statusEl.textContent = 'Penuh';
        }
    }
}

function studioReassign(templateId, rackId, rackCode, rackName, fromWaiterId, toWaiterId, taskId, override, overrideReason) {
    const cardKey = templateId + '_' + rackId;
    if (savingCards.has(cardKey)) return;
    savingCards.add(cardKey);

    // --- Optimistic UI: move card from one waiter to another ---
    const fromZone = document.querySelector(`.studio-drop-zone[data-waiter-id="${fromWaiterId}"]`);
    const toZone = document.querySelector(`.studio-drop-zone[data-waiter-id="${toWaiterId}"]`);
    const card = fromZone ? fromZone.querySelector(`.studio-assigned-card[data-task-key="${cardKey}"]`) : null;

    // Snapshot for rollback
    const originalCard = card ? card.cloneNode(true) : null;

    if (card && toZone) {
        card.remove();
        // Remove drop hint in target zone
        const hint = toZone.querySelector('[style*="pointer-events:none"]');
        if (hint) hint.remove();
        // Update card's assigned-to
        card.dataset.assignedTo = toWaiterId;
        toZone.appendChild(card);

        // Show drop hint if source zone is now empty
        if (fromZone && fromZone.querySelectorAll('.studio-assigned-card').length === 0) {
            fromZone.innerHTML = '<div style="text-align:center;padding:16px 8px;color:var(--color-text-muted);font-size:12px;pointer-events:none;">Taruh rak di sini</div>';
        }

        // Update counters for both
        updateEmployeeCounter(fromWaiterId);
        updateEmployeeCounter(toWaiterId);
    }

    // Update local data
    if (currentData && currentData.planning_tasks) {
        const task = currentData.planning_tasks.find(t => t.template_id === templateId && t.rack_id === rackId);
        if (task) {
            task.assigned_to = toWaiterId;
            task.status = 'planned';
        }
    }
    updateSummaryLine();

    // --- Background: use reassign endpoint ---
    const resolvedTaskId = taskId || (currentData ? ((currentData.planning_tasks || []).find(t => t.template_id === templateId && t.rack_id === rackId) || {}).id : null);

    fetch(`{{ route('admin.rack_check.planning.reassign') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            task_id: resolvedTaskId,
            new_waiter_id: toWaiterId,
            override: override,
            override_reason: overrideReason,
        })
    })
    .then(r => r.json())
    .then(result => {
        savingCards.delete(cardKey);
        if (!result.success) {
            // --- Rollback: move card back ---
            if (card && card.parentElement) card.remove();
            if (originalCard && fromZone) {
                const hint = fromZone.querySelector('[style*="pointer-events:none"]');
                if (hint) hint.remove();
                fromZone.appendChild(originalCard);
            }
            // Show drop hint if target zone now empty
            if (toZone && toZone.querySelectorAll('.studio-assigned-card').length === 0) {
                toZone.innerHTML = '<div style="text-align:center;padding:16px 8px;color:var(--color-text-muted);font-size:12px;pointer-events:none;">Taruh rak di sini</div>';
            }
            if (currentData && currentData.planning_tasks) {
                const task = currentData.planning_tasks.find(t => t.template_id === templateId && t.rack_id === rackId);
                if (task) {
                    task.assigned_to = fromWaiterId;
                    task.status = 'planned';
                }
            }
            updateEmployeeCounter(fromWaiterId);
            updateEmployeeCounter(toWaiterId);
            updateSummaryLine();
            alert(result.message || 'Gagal reassign');
        }
    })
    .catch(err => {
        savingCards.delete(cardKey);
        // Rollback
        if (card && card.parentElement) card.remove();
        if (originalCard && fromZone) {
            const hint = fromZone.querySelector('[style*="pointer-events:none"]');
            if (hint) hint.remove();
            fromZone.appendChild(originalCard);
        }
        if (toZone && toZone.querySelectorAll('.studio-assigned-card').length === 0) {
            toZone.innerHTML = '<div style="text-align:center;padding:16px 8px;color:var(--color-text-muted);font-size:12px;pointer-events:none;">Taruh rak di sini</div>';
        }
        if (currentData && currentData.planning_tasks) {
            const task = currentData.planning_tasks.find(t => t.template_id === templateId && t.rack_id === rackId);
            if (task) {
                task.assigned_to = fromWaiterId;
                task.status = 'planned';
            }
        }
        updateEmployeeCounter(fromWaiterId);
        updateEmployeeCounter(toWaiterId);
        updateSummaryLine();
        alert('Error: ' + err.message);
    });
}

function studioDoAssign(templateId, rackId, rackCode, rackName, waiterId, override, overrideReason) {
    const cardKey = templateId + '_' + rackId;
    if (savingCards.has(cardKey)) return; // prevent double action
    savingCards.add(cardKey);

    // --- Optimistic UI: move card instantly ---
    const card = document.querySelector(`.studio-rack-card[data-rack-id="${rackId}"][data-template-id="${templateId}"]`);
    const dropZone = document.querySelector(`.studio-drop-zone[data-waiter-id="${waiterId}"]`);
    const unassignedList = document.getElementById('studio-unassigned-list');

    // Snapshot for rollback
    const originalParent = card ? card.parentElement : null;
    const originalNextSibling = card ? card.nextElementSibling : null;
    let assignedCard = null;

    if (card && dropZone) {
        // Remove from unassigned
        card.remove();
        // Remove drop hint if present
        const hint = dropZone.querySelector('[style*="pointer-events:none"]');
        if (hint) hint.remove();
        // Create assigned card in drop zone
        assignedCard = document.createElement('div');
        assignedCard.className = 'studio-assigned-card';
        assignedCard.dataset.taskKey = cardKey;
        assignedCard.innerHTML = `
            <div style="min-width:0;">
                <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(rackName || rackId)}</div>
                <div style="font-size:11px;color:var(--color-text-muted);">${escHtml(rackCode || '')}</div>
            </div>
            <button class="studio-unassign-btn" data-task-key="${cardKey}" title="Unassign">\u00d7</button>
        `;
        dropZone.appendChild(assignedCard);

        // Update unassigned count
        const countLabel = unassignedList ? unassignedList.closest('div').querySelector('[style*="font-weight:700"]') : null;
        if (countLabel) {
            const remaining = unassignedList.querySelectorAll('.studio-rack-card').length;
            const span = countLabel.querySelector('span');
            if (span) span.textContent = `(${remaining})`;
            if (remaining === 0 && !unassignedList.querySelector('[style*="color:var(--color-success)"]')) {
                unassignedList.innerHTML = '<div style="text-align:center;padding:20px;color:var(--color-success);font-size:13px;">Semua rak sudah diassign \u2713</div>';
            }
        }

        // Update employee counter
        updateEmployeeCounter(waiterId);
    }

    // Update local data optimistically
    if (currentData) {
        if (!currentData.planning_tasks) currentData.planning_tasks = [];
        const existingIdx = currentData.planning_tasks.findIndex(t => t.template_id === templateId && t.rack_id === rackId);
        if (existingIdx >= 0) {
            currentData.planning_tasks[existingIdx].assigned_to = waiterId;
            currentData.planning_tasks[existingIdx].status = 'planned';
        } else {
            currentData.planning_tasks.push({
                id: '__optimistic_' + cardKey,
                template_id: templateId,
                rack_id: rackId,
                rack_code: rackCode,
                rack_name: rackName,
                assigned_to: waiterId,
                status: 'planned',
                scheduled_for_date: currentDate,
            });
        }
        updateSummaryLine();
    }

    // --- Background POST ---
    fetch(`{{ route('admin.rack_check.planning.assign') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            template_id: templateId,
            rack_id: rackId,
            rack_code: rackCode,
            rack_name: rackName,
            waiter_id: waiterId,
            scheduled_date: currentDate,
            override: override,
            override_reason: overrideReason,
        })
    })
    .then(r => r.json())
    .then(result => {
        savingCards.delete(cardKey);
        if (!result.success) {
            // --- Rollback UI ---
            rollbackAssign(card, assignedCard, originalParent, originalNextSibling, dropZone, waiterId, templateId, rackId);
            alert(result.message || 'Gagal assign');
            return;
        }
        // Update optimistic task_id with real one
        if (result.task_id && currentData) {
            const task = currentData.planning_tasks.find(t => t.template_id === templateId && t.rack_id === rackId);
            if (task) task.id = result.task_id;
            // Update unassign button with real task_id
            const btn = dropZone ? dropZone.querySelector(`.studio-unassign-btn[data-task-key="${cardKey}"]`) : null;
            if (btn) btn.setAttribute('data-task-id', result.task_id);
        }
    })
    .catch(err => {
        savingCards.delete(cardKey);
        rollbackAssign(card, assignedCard, originalParent, originalNextSibling, dropZone, waiterId, templateId, rackId);
        alert('Error: ' + err.message);
    });
}

function rollbackAssign(origCard, assignedCard, originalParent, originalNextSibling, dropZone, waiterId, templateId, rackId) {
    // Remove optimistic assigned card
    if (assignedCard && assignedCard.parentElement) assignedCard.remove();
    // Restore draggable card to unassigned list
    if (origCard && originalParent) {
        if (originalNextSibling) {
            originalParent.insertBefore(origCard, originalNextSibling);
        } else {
            originalParent.appendChild(origCard);
        }
        // Remove "semua sudah diassign" message if present
        const successMsg = originalParent.querySelector('[style*="color:var(--color-success)"]');
        if (successMsg) successMsg.remove();
    }
    // Revert local data
    if (currentData && currentData.planning_tasks) {
        const idx = currentData.planning_tasks.findIndex(t => t.template_id === templateId && t.rack_id === rackId);
        if (idx >= 0) {
            currentData.planning_tasks[idx].assigned_to = null;
            currentData.planning_tasks[idx].status = 'planning_pending';
        }
    }
    updateEmployeeCounter(waiterId);
    updateSummaryLine();
    // Update unassigned count label
    const unassignedList = document.getElementById('studio-unassigned-list');
    if (unassignedList) {
        const countLabel = unassignedList.closest('div').querySelector('[style*="font-weight:700"] span');
        if (countLabel) countLabel.textContent = `(${unassignedList.querySelectorAll('.studio-rack-card').length})`;
    }
}

function studioUnassign(taskId) {
    if (savingCards.has('unassign_' + taskId)) return; // prevent double action
    pendingUnassignTaskId = taskId;
    // Show task name in confirmation message
    const task = currentData ? (currentData.planning_tasks || []).find(t => t.id === taskId) : null;
    const rackLabel = task ? (task.rack_name || task.rack_code || task.rack_id) : '';
    document.getElementById('unassign-confirm-message').textContent =
        rackLabel ? `Hapus assignment untuk rak "${rackLabel}"?` : 'Hapus assignment untuk rak ini?';
    document.getElementById('modal-unassign-confirm').style.display = 'flex';
}

function executeUnassign(taskId) {
    savingCards.add('unassign_' + taskId);

    // Find task in local data
    const task = currentData ? (currentData.planning_tasks || []).find(t => t.id === taskId) : null;
    const templateId = task ? task.template_id : '';
    const rackId = task ? task.rack_id : '';
    const rackCode = task ? (task.rack_code || '') : '';
    const rackName = task ? (task.rack_name || task.rack_id || '') : '';
    const waiterId = task ? task.assigned_to : '';

    // --- Optimistic UI: move card back to unassigned ---
    const cardKey = templateId + '_' + rackId;
    let assignedCard = document.querySelector(`.studio-assigned-card[data-task-key="${cardKey}"]`);
    if (!assignedCard && taskId) {
        // Fallback: find via button's data-task-id, then get parent card
        const btn = document.querySelector(`.studio-unassign-btn[data-task-id="${taskId}"]`);
        if (btn) assignedCard = btn.closest('.studio-assigned-card');
    }
    const assignedCardParent = assignedCard ? assignedCard.parentElement : null;
    const unassignedList = document.getElementById('studio-unassigned-list');

    // Remove "semua sudah" message if present
    if (unassignedList) {
        const successMsg = unassignedList.querySelector('[style*="color:var(--color-success)"]');
        if (successMsg) successMsg.remove();
    }

    // Snapshot for rollback
    let removedCard = null;
    if (assignedCard) {
        removedCard = assignedCard.cloneNode(true);
        assignedCard.remove();
    }

    // Add draggable card back to unassigned
    let restoredCard = null;
    if (unassignedList && templateId && rackId) {
        restoredCard = document.createElement('div');
        restoredCard.className = 'studio-rack-card';
        restoredCard.draggable = true;
        restoredCard.dataset.rackId = rackId;
        restoredCard.dataset.rackName = rackName;
        restoredCard.dataset.rackCode = rackCode;
        restoredCard.dataset.templateId = templateId;
        restoredCard.innerHTML = `
            <div style="font-size:13px;font-weight:600;color:var(--color-text);">${escHtml(rackName || 'Rak')}</div>
            <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">${escHtml(rackCode || '-')}</div>
        `;
        unassignedList.appendChild(restoredCard);
    }

    // Show drop hint if employee zone is now empty
    if (assignedCardParent && assignedCardParent.querySelectorAll('.studio-assigned-card').length === 0) {
        assignedCardParent.innerHTML = '<div style="text-align:center;padding:16px 8px;color:var(--color-text-muted);font-size:12px;pointer-events:none;">Taruh rak di sini</div>';
    }

    // Update counters
    if (waiterId) updateEmployeeCounter(waiterId);
    if (unassignedList) {
        const countLabel = unassignedList.closest('div').querySelector('[style*="font-weight:700"] span');
        if (countLabel) countLabel.textContent = `(${unassignedList.querySelectorAll('.studio-rack-card').length})`;
    }

    // Update local data
    if (task) {
        task.assigned_to = null;
        task.assigned_waiter_name = null;
        task.status = 'planning_pending';
    }
    updateSummaryLine();

    // --- Background POST ---
    fetch(`{{ route('admin.rack_check.planning.unassign') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ task_id: taskId })
    })
    .then(r => r.json())
    .then(result => {
        savingCards.delete('unassign_' + taskId);
        if (!result.success) {
            // --- Rollback ---
            if (restoredCard && restoredCard.parentElement) restoredCard.remove();
            if (removedCard && assignedCardParent) {
                // Remove drop hint
                const hint = assignedCardParent.querySelector('[style*="pointer-events:none"]');
                if (hint) hint.remove();
                assignedCardParent.appendChild(removedCard);
            }
            if (task) {
                task.assigned_to = waiterId;
                task.status = 'planned';
            }
            if (waiterId) updateEmployeeCounter(waiterId);
            updateSummaryLine();
            if (unassignedList) {
                const countLabel = unassignedList.closest('div').querySelector('[style*="font-weight:700"] span');
                if (countLabel) countLabel.textContent = `(${unassignedList.querySelectorAll('.studio-rack-card').length})`;
            }
            alert(result.message || 'Gagal unassign');
        }
    })
    .catch(err => {
        savingCards.delete('unassign_' + taskId);
        // Rollback
        if (restoredCard && restoredCard.parentElement) restoredCard.remove();
        if (removedCard && assignedCardParent) {
            const hint = assignedCardParent.querySelector('[style*="pointer-events:none"]');
            if (hint) hint.remove();
            assignedCardParent.appendChild(removedCard);
        }
        if (task) {
            task.assigned_to = waiterId;
            task.status = 'planned';
        }
        if (waiterId) updateEmployeeCounter(waiterId);
        updateSummaryLine();
        alert('Error: ' + err.message);
    });
}

// ─── Hook: override confirm must also support studio drop + reassign ──────────
// Wrap existing confirmOverride (already wrapped once above for reassign)
(function() {
    const _prev = confirmOverride;
    confirmOverride = function() {
        const reason = document.getElementById('override-reason').value.trim();
        if (!reason) { alert('Alasan override wajib diisi.'); return; }
        // Studio drop override (has templateId/rackId but no action:'reassign')
        if (pendingOverride && pendingOverride.templateId && !pendingOverride.action) {
            const p = pendingOverride;
            closeOverrideModal();
            if (studioActive && p.fromWaiterId) {
                // Reassign with override
                studioReassign(p.templateId, p.rackId, p.rackCode, p.rackName, p.fromWaiterId, p.waiterId, p.taskId, true, reason);
            } else if (studioActive) {
                studioDoAssign(p.templateId, p.rackId, p.rackCode, p.rackName, p.waiterId, true, reason);
            } else {
                doAssign(p.templateId, p.rackId, p.rackCode, p.rackName, p.waiterId, true, reason);
            }
            return;
        }
        _prev();
    };
})();

// ─── Hook: renderDailyDetail — render studio board if mode active ─────────────
(function() {
    const _origRender = renderDailyDetail;
    renderDailyDetail = function(data) {
        _origRender(data);
        if (studioActive) {
            // Hide tab UI, show studio
            const tabNav = getTabNavEl();
            if (tabNav) tabNav.style.display = 'none';
            ['unassigned','per-employee','all'].forEach(t => {
                const el = document.getElementById('tab-content-' + t);
                if (el) el.style.display = 'none';
            });
            document.getElementById('studio-board').style.display = 'block';
            renderStudioBoard(data);
        }
    };
})();

// ─── Escape helpers ───────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function escAttr(str) {
    return String(str).replace(/'/g,"\\'");
}
</script>
@endsection
