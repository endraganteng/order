@extends('admin.layout')

@section('title', 'Cek Rak Otomatis - Admin')

@section('content')
@php
    $dayLabels = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    $describeRecurrence = function ($tpl) use ($dayLabels) {
        $type = (string) ($tpl['recurrence_type'] ?? 'daily');
        if ($type === 'weekly') {
            $day = (int) ($tpl['weekly_day'] ?? 1);
            return 'Setiap '.($dayLabels[$day] ?? '-');
        }
        if ($type === 'every_n_days') {
            $n = (int) ($tpl['interval_days'] ?? 2);
            return "Setiap {$n} hari";
        }
        return 'Setiap hari';
    };

    $todayStr = date('Y-m-d');
@endphp

<div style="max-width: 1100px; margin: 0 auto; padding: 0 12px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
        <div>
            <h2 style="margin: 0 0 6px; color: var(--color-text); font-size: clamp(22px, 5vw, 30px); display: flex; align-items: center; gap: 10px;">
                📦 Cek Rak Otomatis
                <span id="liveIndicator" style="display: none; align-items: center; gap: 6px; background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success-border); padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--color-success); animation: pulse 1.5s ease-in-out infinite;"></span>
                    LIVE
                </span>
            </h2>
            <p style="margin: 0; color: var(--color-text-muted); font-size: 14px;">
                Template membuat tugas cek rak setiap hari sesuai jadwal. Petugas dipilih otomatis dari yang masuk kerja dan beban tugasnya paling ringan.
            </p>
        </div>
        <a href="{{ route('admin.rack_check.templates.create') }}"
           style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 11px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: var(--shadow-sm); white-space: nowrap;">
            ➕ Buat Template Baru
        </a>
    </div>

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.7); }
        }
        .status-row {
            padding: 8px 12px;
            background: var(--color-bg);
            border-radius: 7px;
            font-size: 12px;
            color: var(--color-text-secondary);
            line-height: 1.5;
        }
        .status-row .status-label {
            font-weight: 600;
            color: var(--color-text);
        }
    </style>

    <div style="margin-bottom: 16px; padding: 10px 14px; background: #f8fafc; border: 1px dashed var(--color-border); border-radius: 8px; font-size: 12px; color: var(--color-text-muted); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <span>📚 Butuh lihat task lama / mode AI Balancing?</span>
        <a href="{{ route('admin.tasks.rack.index') }}" style="color: var(--color-text-secondary); text-decoration: underline;">Buka arsip Cek Rak lama →</a>
    </div>

    @if(session('success'))
        <div style="background: var(--color-success-bg); border: 1px solid var(--color-success-border); color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); color: #7f1d1d; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            ⚠ {{ session('error') }}
        </div>
    @endif

    @if(count($templates) === 0)
        <div style="background: white; border: 2px dashed var(--color-border); border-radius: 12px; padding: 60px 20px; text-align: center; color: var(--color-text-muted);">
            <div style="font-size: 48px; margin-bottom: 12px;">📭</div>
            <h3 style="margin: 0 0 6px; color: var(--color-text); font-size: 18px;">Belum ada template cek rak otomatis</h3>
            <p style="margin: 0 0 16px; font-size: 14px;">Mulai buat template untuk mengotomasi cek rak harian dengan beban paling ringan.</p>
            <a href="{{ route('admin.rack_check.templates.create') }}"
               style="display: inline-block; background: var(--color-primary); color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
                ➕ Buat Template Pertama
            </a>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px;">
            @foreach($templates as $tpl)
                @php
                    $isActive = (bool) ($tpl['is_active'] ?? true);
                    $selectedIds = is_array($tpl['selected_waiter_ids'] ?? null) ? $tpl['selected_waiter_ids'] : [];
                    $waiterNames = array_map(fn ($id) => $waiterMap[$id] ?? '—', $selectedIds);
                    $proofParts = [];
                    if ($tpl['requires_barcode_scan'] ?? false) $proofParts[] = 'Scan QR rak';
                    if ($tpl['requires_photo_before'] ?? false) $proofParts[] = 'Foto sebelum';
                    if ($tpl['requires_photo_proof'] ?? false) $proofParts[] = 'Foto sesudah';
                @endphp
                <div class="rc-template-card" data-template-id="{{ $tpl['id'] }}"
                     style="background: white; border: 1px solid var(--color-border); border-radius: 12px; padding: 16px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 10px; {{ $isActive ? '' : 'opacity: 0.65;' }}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                        <div style="font-weight: 700; color: var(--color-text); font-size: 16px; word-break: break-word;">
                            {{ $tpl['rack_name'] ?? $tpl['title'] ?? '—' }}
                        </div>
                        @if($isActive)
                            <span style="background: var(--color-success-bg); color: var(--color-success); padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap;">● Aktif</span>
                        @else
                            <span style="background: #f1f5f9; color: var(--color-text-muted); padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap;">○ Nonaktif</span>
                        @endif
                    </div>

                    <div style="font-size: 13px; color: var(--color-text-secondary); line-height: 1.6;">
                        <div><strong style="color: var(--color-text);">📅 Jadwal:</strong> {{ $describeRecurrence($tpl) }}</div>
                        <div><strong style="color: var(--color-text);">⏱ Jam &amp; deadline:</strong> mengikuti shift petugas</div>
                        <div><strong style="color: var(--color-text);">👥 Petugas rotasi:</strong>
                            @if(count($waiterNames) > 0)
                                {{ implode(', ', $waiterNames) }}
                            @else
                                <span style="color: var(--color-warning);">Belum ada petugas</span>
                            @endif
                        </div>
                        @if(count($proofParts) > 0)
                            <div><strong style="color: var(--color-text);">📋 Bukti:</strong> {{ implode(' + ', $proofParts) }}</div>
                        @endif
                        <div><strong style="color: var(--color-text);">🎯 Mode:</strong>
                            @php $strat = (string) ($tpl['assignment_strategy'] ?? 'simple_lowest_load'); @endphp
                            @if($strat === 'round_robin_simple')
                                <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 1px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">🔁 Giliran Tetap</span>
                            @else
                                <span style="background: var(--color-info-bg); color: var(--color-info); border: 1px solid var(--color-info-border); padding: 1px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">⚖ Beban Paling Ringan</span>
                            @endif
                        </div>
                    </div>

                    <div class="status-row" data-status-slot
                         style="background: #f8fafc; border: 1px dashed var(--color-border); color: var(--color-text-muted);">
                        <span class="status-label">Hari ini:</span>
                        <span data-status-content>memuat status…</span>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; padding-top: 10px; border-top: 1px solid var(--color-border);">
                        <button type="button"
                                onclick="previewTemplate('{{ $tpl['id'] }}', @js($tpl['rack_name'] ?? ''))"
                                style="background: var(--color-info-bg); color: var(--color-info); border: 1px solid var(--color-info-border); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                            🔍 Preview Besok
                        </button>

                        @if($isActive)
                            <form method="POST" action="{{ route('admin.rack_check.templates.generate', $tpl['id']) }}" style="display: inline;"
                                  onsubmit="return confirm('Generate task hari ini untuk {{ addslashes($tpl['rack_name'] ?? 'rak ini') }} sekarang?\n\nSistem akan langsung memilih petugas dan membuat task tanpa menunggu cron.');">
                                @csrf
                                <button type="submit"
                                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                    ⚡ Generate Sekarang
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.rack_check.templates.toggle', $tpl['id']) }}" style="display: inline;">
                            @csrf
                            <button type="submit"
                                    style="background: {{ $isActive ? '#fef3c7' : 'var(--color-success-bg)' }}; color: {{ $isActive ? '#92400e' : 'var(--color-success)' }}; border: 1px solid {{ $isActive ? '#fde68a' : 'var(--color-success-border)' }}; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                {{ $isActive ? '⏸ Nonaktifkan' : '▶ Aktifkan' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.rack_check.templates.destroy', $tpl['id']) }}" style="display: inline; margin-left: auto;"
                              onsubmit="return confirm('Hapus template cek rak otomatis untuk {{ addslashes($tpl['rack_name'] ?? 'rak ini') }}? Task pending hari ini akan dibatalkan.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    style="background: var(--color-danger-bg); color: var(--color-danger); border: 1px solid var(--color-danger-border); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                🗑 Hapus
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Preview Modal --}}
<div id="previewModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: white; border-radius: 12px; max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-md);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
            <div style="font-weight: 700; color: var(--color-text); font-size: 16px;">🔍 Preview Pembagian</div>
            <button type="button" onclick="closePreview()" style="background: none; border: none; font-size: 22px; color: var(--color-text-muted); cursor: pointer; padding: 0; line-height: 1;">×</button>
        </div>
        <div id="previewBody" style="padding: 18px 20px; font-size: 14px; line-height: 1.6;">
            <div style="text-align: center; color: var(--color-text-muted); padding: 30px 0;">Memuat preview…</div>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end;">
            <button type="button" onclick="closePreview()" style="background: var(--color-bg); color: var(--color-text-secondary); border: 1px solid var(--color-border); padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Tutup</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('previewModal');
    const body = document.getElementById('previewBody');

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function buildPreviewHtml(rackName, preview) {
        const parts = [];
        const dateStr = escapeHtml(preview.date || '');
        parts.push(`<div style="margin-bottom:10px;color:var(--color-text-muted);font-size:13px;">Tanggal preview: <strong style="color:var(--color-text);">${dateStr}</strong></div>`);
        parts.push(`<div style="margin-bottom:14px;font-weight:700;color:var(--color-text);font-size:15px;">${escapeHtml(rackName || preview.rack_name || 'Rak')}</div>`);

        if (preview.status === 'eligible' && preview.selected) {
            const sel = preview.selected;
            parts.push(`<div style="background:var(--color-success-bg);border:1px solid var(--color-success-border);border-radius:8px;padding:12px;margin-bottom:12px;">`);
            parts.push(`<div style="font-weight:700;color:#065f46;margin-bottom:4px;">Petugas terpilih: ${escapeHtml(sel.name)}</div>`);
            parts.push(`<div style="color:#065f46;font-size:13px;">Beban hari ini: <strong>${sel.today_count}/${sel.daily_cap}</strong> · Beban minggu ini: <strong>${sel.weekly_count}</strong></div>`);
            parts.push(`</div>`);

            parts.push(`<div style="font-weight:600;color:var(--color-text);margin-bottom:6px;">Alasan:</div>`);
            parts.push(`<ul style="margin:0 0 14px 20px;color:var(--color-text-secondary);font-size:13px;">`);
            parts.push(`<li>Masuk kerja hari ${dateStr}</li>`);
            parts.push(`<li>Task cek rak hari ini paling sedikit (${sel.today_count})</li>`);
            parts.push(`<li>Belum mencapai batas tugas harian (${sel.daily_cap})</li>`);
            parts.push(`</ul>`);
        } else {
            parts.push(`<div style="background:var(--color-warning-bg);border:1px solid var(--color-warning-border);border-radius:8px;padding:12px;margin-bottom:12px;color:#92400e;font-weight:600;">`);
            parts.push(`⚠ Tidak ada petugas yang eligible. Task tidak akan dibuat dan ditandai skipped.`);
            parts.push(`</div>`);
        }

        const others = Array.isArray(preview.rejected_candidates) ? preview.rejected_candidates : [];
        if (others.length > 0) {
            parts.push(`<div style="font-weight:600;color:var(--color-text);margin-bottom:6px;">Kandidat lain:</div>`);
            parts.push(`<ul style="margin:0;padding-left:20px;color:var(--color-text-secondary);font-size:13px;">`);
            others.forEach(o => {
                parts.push(`<li><strong>${escapeHtml(o.name)}:</strong> ${escapeHtml(o.reason || '-')}</li>`);
            });
            parts.push(`</ul>`);
        }

        return parts.join('');
    }

    window.previewTemplate = function (templateId, rackName) {
        modal.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center;color:var(--color-text-muted);padding:30px 0;">Memuat preview…</div>';

        const tomorrow = new Date(Date.now() + 86400000);
        const yyyy = tomorrow.getFullYear();
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        const url = `{{ url('admin/rack-check/templates') }}/${encodeURIComponent(templateId)}/preview?date=${dateStr}`;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(json => {
                if (!json.success) {
                    body.innerHTML = `<div style="color:var(--color-danger);">Gagal memuat preview: ${escapeHtml(json.message || 'Unknown')}</div>`;
                    return;
                }
                body.innerHTML = buildPreviewHtml(rackName, json.preview || {});
            })
            .catch(err => {
                body.innerHTML = `<div style="color:var(--color-danger);">Gagal memuat preview: ${escapeHtml(err.message || err)}</div>`;
            });
    };

    window.closePreview = function () {
        modal.style.display = 'none';
    };

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closePreview();
    });
})();
</script>

{{-- ─────── LIVE STATUS via Firebase Realtime DB ─────── --}}
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
    import { getDatabase, ref, query, orderByChild, equalTo, onValue } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js';
    import { getAuth, signInWithCredential, GoogleAuthProvider } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

    const firebaseConfig = {
        apiKey: "{{ env('FIREBASE_API_KEY') }}",
        authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
        projectId: "{{ env('FIREBASE_PROJECT_ID') }}",
        storageBucket: "{{ env('FIREBASE_STORAGE_BUCKET') }}",
        messagingSenderId: "{{ env('FIREBASE_MESSAGING_SENDER_ID') }}",
        appId: "{{ env('FIREBASE_APP_ID') }}",
        databaseURL: "{{ env('FIREBASE_DATABASE_URL') }}"
    };

    const app = initializeApp(firebaseConfig);
    const database = getDatabase(app);
    const auth = getAuth(app);
    const today = "{{ $todayStr }}";

    // Auth — pakai stored token kalau ada (sama dengan live monitor)
    const storedToken = {!! json_encode(session('admin_firebase_token')) !!};
    if (storedToken) {
        try {
            const credential = GoogleAuthProvider.credential(storedToken);
            await signInWithCredential(auth, credential);
        } catch (e) {
            console.warn('[rack-check live] Firebase auth failed:', e.message);
        }
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function statusBadge(status, isOverdue) {
        if (status === 'done')        return ['✓ Selesai',         '#dcfce7', '#166534', '#bbf7d0'];
        if (status === 'in_progress') return ['🔄 Dikerjakan',     '#dbeafe', '#1e40af', '#bfdbfe'];
        if (status === 'cancelled')   return ['❌ Dibatalkan',     '#fee2e2', '#7f1d1d', '#fecaca'];
        if (status === 'overdue' || isOverdue) return ['⚠ Overdue', '#fef3c7', '#92400e', '#fde68a'];
        return ['⏳ Pending',                                       '#f1f5f9', '#475569', '#cbd5e1'];
    }

    function renderTaskRow(slot, task) {
        const [label, bg, text, border] = statusBadge(task.status || 'pending', task.is_overdue);
        const waiterName = task.assigned_waiter_name || 'Tanpa nama';
        const recheckPending = task.status === 'done' && task.recheck_pending === true;
        const deadlineStr = task.deadline_at
            ? new Date(task.deadline_at * 1000).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            : '';

        let extra = '';
        if (recheckPending) {
            extra = ' <span style="color:#9a3412;">· menunggu Finance review</span>';
        } else if (task.status === 'done' && typeof task.recheck_points === 'number') {
            extra = ` <span style="color:#166534;">· direview ${task.recheck_points}/10</span>`;
        } else if (task.status !== 'done' && task.status !== 'cancelled' && deadlineStr) {
            extra = ` <span>· deadline ${escapeHtml(deadlineStr)}</span>`;
        }

        const html = `
            <span class="status-label">Hari ini:</span>
            <span style="display:inline-flex;align-items:center;gap:6px;background:${bg};color:${text};border:1px solid ${border};padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;margin:0 6px;">${label}</span>
            <strong style="color:var(--color-text);">${escapeHtml(waiterName)}</strong>${extra}
        `;
        slot.innerHTML = html;
        slot.style.background = bg;
        slot.style.borderColor = border;
        slot.style.borderStyle = 'solid';
        slot.style.color = text;
    }

    function renderEmpty(slot) {
        slot.innerHTML = `
            <span class="status-label">Hari ini:</span>
            <span style="color:var(--color-text-muted);">Belum tergenerate. Cron akan menjalankan dalam 5 menit.</span>
        `;
        slot.style.background = '#f8fafc';
        slot.style.borderStyle = 'dashed';
        slot.style.borderColor = 'var(--color-border)';
        slot.style.color = 'var(--color-text-muted)';
    }

    function renderSkipped(slot) {
        slot.innerHTML = `
            <span class="status-label">Hari ini:</span>
            <span style="color:#92400e;">💤 Skipped — tidak ada petugas eligible</span>
        `;
        slot.style.background = 'var(--color-warning-bg)';
        slot.style.borderStyle = 'solid';
        slot.style.borderColor = 'var(--color-warning-border)';
        slot.style.color = '#92400e';
    }

    // Listen tasks for today
    const tasksRef = query(ref(database, 'waiter_tasks'), orderByChild('scheduled_for_date'), equalTo(today));
    const indicator = document.getElementById('liveIndicator');

    onValue(tasksRef, (snapshot) => {
        const allTasks = snapshot.val() || {};
        // Group by source_template_id (only assignment_mode=simple_lowest_load)
        const byTemplate = {};
        Object.values(allTasks).forEach(t => {
            const mode = t.assignment_mode || '';
            if (mode !== 'simple_lowest_load' && mode !== 'round_robin_simple') return;
            const tplId = t.source_template_id;
            if (!tplId) return;
            // Pick latest task per template (by created_at)
            if (!byTemplate[tplId] || (t.created_at || 0) > (byTemplate[tplId].created_at || 0)) {
                byTemplate[tplId] = t;
            }
        });

        // Render each card
        document.querySelectorAll('.rc-template-card').forEach(card => {
            const tplId = card.dataset.templateId;
            const slot = card.querySelector('[data-status-slot]');
            if (!slot) return;
            const task = byTemplate[tplId];
            if (task) {
                renderTaskRow(slot, task);
            } else {
                // Cek lock untuk skipped status
                checkLockStatus(tplId, slot);
            }
        });

        if (indicator) indicator.style.display = 'inline-flex';
    }, (error) => {
        console.error('[rack-check live] listener error:', error);
        document.querySelectorAll('[data-status-slot]').forEach(slot => {
            slot.innerHTML = `<span style="color:var(--color-danger);">Gagal memuat status realtime.</span>`;
        });
    });

    // Lookup lock untuk template tanpa task (apakah skipped atau belum tergenerate)
    async function checkLockStatus(tplId, slot) {
        try {
            const todayCompact = today.replace(/-/g, '');
            const lockRef = ref(database, `waiter_task_generation_locks/${tplId}/${todayCompact}`);
            onValue(lockRef, (snap) => {
                const lock = snap.val();
                if (lock && lock.status === 'skipped_no_eligible_waiter') {
                    renderSkipped(slot);
                } else if (lock && lock.status === 'cancelled_by_admin') {
                    slot.innerHTML = `
                        <span class="status-label">Hari ini:</span>
                        <span style="color:#7f1d1d;">❌ Dibatalkan admin · tidak akan diregenerasi</span>
                    `;
                    slot.style.background = 'var(--color-danger-bg)';
                    slot.style.borderStyle = 'solid';
                    slot.style.borderColor = 'var(--color-danger-border)';
                    slot.style.color = 'var(--color-danger)';
                } else {
                    renderEmpty(slot);
                }
            }, { onlyOnce: true });
        } catch (e) {
            renderEmpty(slot);
        }
    }
</script>

@endsection
