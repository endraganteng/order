@extends('admin.layout')

@section('title', 'Live Monitor - Tugas')

@section('content')
<div id="live-app" style="padding: 20px 0;">
    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--color-text);">📡 Live Monitor</h1>
            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-top: 4px;">
                Real-time progress tugas hari ini — <span id="lm-date">{{ $today }}</span>
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span id="lm-status" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 500; background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success-border);">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--color-success); animation: pulse 2s infinite;"></span>
                Terhubung
            </span>
            <button onclick="location.reload()" style="padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: #fff; cursor: pointer; font-size: 0.8rem;">🔄 Refresh</button>
        </div>
    </div>

    {{-- Active Sessions --}}
    <div style="margin-bottom: 24px;">
        <div id="lm-active-sessions" style="background: #fff; border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--color-text); margin:0;">📍 Sesi Aktif Sekarang</h3>
                <span id="lm-active-sessions-count" style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:var(--color-primary-bg);color:var(--color-primary);border:1px solid var(--color-primary-border);font-size:0.78rem;font-weight:600;">0 sesi</span>
            </div>
            <div id="lm-active-sessions-body" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                <div style="color: var(--color-text-muted); font-size: 0.85rem; text-align: center; padding: 20px; border:1px dashed var(--color-border); border-radius:var(--radius-md); grid-column:1 / -1;">Memuat sesi aktif...</div>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div id="lm-kpi" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <div class="lm-kpi-card" style="background: #fff; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Hari Ini</div>
            <div id="kpi-total" style="font-size: 1.8rem; font-weight: 700; color: var(--color-text); margin-top: 4px;">0</div>
        </div>
        <div class="lm-kpi-card" style="background: var(--color-success-bg); border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--color-success-border);">
            <div style="font-size: 0.75rem; color: var(--color-success); text-transform: uppercase; letter-spacing: 0.5px;">✅ Selesai</div>
            <div id="kpi-done" style="font-size: 1.8rem; font-weight: 700; color: var(--color-success); margin-top: 4px;">0</div>
        </div>
        <div class="lm-kpi-card" style="background: var(--color-info-bg); border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--color-info-border);">
            <div style="font-size: 0.75rem; color: var(--color-info); text-transform: uppercase; letter-spacing: 0.5px;">🔄 Proses</div>
            <div id="kpi-progress" style="font-size: 1.8rem; font-weight: 700; color: var(--color-info); margin-top: 4px;">0</div>
        </div>
        <div class="lm-kpi-card" style="background: var(--color-warning-bg); border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--color-warning-border);">
            <div style="font-size: 0.75rem; color: var(--color-warning); text-transform: uppercase; letter-spacing: 0.5px;">⏳ Pending</div>
            <div id="kpi-pending" style="font-size: 1.8rem; font-weight: 700; color: var(--color-warning); margin-top: 4px;">0</div>
        </div>
        <div class="lm-kpi-card" style="background: var(--color-danger-bg); border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--color-danger-border);">
            <div style="font-size: 0.75rem; color: var(--color-danger); text-transform: uppercase; letter-spacing: 0.5px;">🚨 Terlambat</div>
            <div id="kpi-overdue" style="font-size: 1.8rem; font-weight: 700; color: var(--color-danger); margin-top: 4px;">0</div>
        </div>
    </div>

    {{-- Main Grid: Waiter Progress + Activity Feed --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        {{-- Per-Waiter Progress --}}
        <div style="background: #fff; border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--color-text); margin-bottom: 16px;">👥 Progress Per Waiter</h3>
            <div id="lm-waiter-list" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="color: var(--color-text-muted); font-size: 0.85rem; text-align: center; padding: 20px;">Memuat data...</div>
            </div>
        </div>

        {{-- Live Activity Feed --}}
        <div style="background: #fff; border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--color-text); margin-bottom: 16px;">⚡ Aktivitas Terbaru</h3>
            <div id="lm-feed" style="display: flex; flex-direction: column; gap: 8px; max-height: 400px; overflow-y: auto;">
                <div style="color: var(--color-text-muted); font-size: 0.85rem; text-align: center; padding: 20px;">Menunggu aktivitas...</div>
            </div>
        </div>
    </div>

    {{-- Scope Breakdown --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div style="background: #fff; border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--color-text); margin-bottom: 12px;">📝 Tugas Umum</h3>
            <div id="lm-scope-general" style="display: flex; align-items: center; gap: 12px;">
                <div style="flex: 1; height: 8px; background: var(--color-border); border-radius: 4px; overflow: hidden;">
                    <div id="bar-general" style="height: 100%; background: var(--color-primary); border-radius: 4px; transition: width 0.5s ease; width: 0%;"></div>
                </div>
                <span id="label-general" style="font-size: 0.85rem; font-weight: 600; color: var(--color-text); min-width: 60px; text-align: right;">0/0</span>
            </div>
        </div>
        <div style="background: #fff; border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--color-text); margin-bottom: 12px;">📦 Cek Rak</h3>
            <div id="lm-scope-rack" style="display: flex; align-items: center; gap: 12px;">
                <div style="flex: 1; height: 8px; background: var(--color-border); border-radius: 4px; overflow: hidden;">
                    <div id="bar-rack" style="height: 100%; background: #f59e0b; border-radius: 4px; transition: width 0.5s ease; width: 0%;"></div>
                </div>
                <span id="label-rack" style="font-size: 0.85rem; font-weight: 600; color: var(--color-text); min-width: 60px; text-align: right;">0/0</span>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    @media (max-width: 768px) {
        #live-app > div:nth-child(3),
        #live-app > div:nth-child(4),
        #lm-active-sessions-body {
            grid-template-columns: 1fr !important;
        }
    }
</style>

{{-- Waiter data from server --}}
<script id="lm-waiters-data" type="application/json">{!! json_encode($waiters ?? []) !!}</script>

<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
    import { getDatabase, ref, onValue, query, orderByChild, equalTo, limitToLast } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js';
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
    const today = "{{ $today }}";

    // Authenticate with Firebase using stored token or onAuthStateChanged
    const storedToken = {!! json_encode(session('admin_firebase_token')) !!};
    if (storedToken) {
        try {
            const credential = GoogleAuthProvider.credential(storedToken);
            await signInWithCredential(auth, credential);
        } catch (e) {
            console.warn('Firebase auth with stored token failed, trying anonymous...', e.message);
        }
    }

    // Parse waiter data
    let waiters = [];
    try {
        const raw = JSON.parse(document.getElementById('lm-waiters-data')?.textContent || '[]');
        waiters = Array.isArray(raw) ? raw : Object.values(raw);
    } catch (e) { waiters = []; }

    const waiterMap = {};
    waiters.forEach(w => { waiterMap[w.id] = w; });

    // State
    let allTasks = {};
    let feedItems = [];
    const MAX_FEED = 20;
    const MAX_ACTIVE_SESSIONS = 20;
    const ACTIVE_IDLE_MS = 2 * 60 * 1000;
    const ACTIVE_STALE_MS = 5 * 60 * 1000;
    let activeSessions = {};
    let activeSessionRenderTimer = null;

    // Listen to today's tasks
    const tasksRef = query(ref(database, 'waiter_tasks'), orderByChild('scheduled_for_date'), equalTo(today));

    onValue(tasksRef, (snapshot) => {
        allTasks = snapshot.val() || {};
        updateDashboard();
    }, (error) => {
        document.getElementById('lm-status').innerHTML = `
            <span style="width:8px;height:8px;border-radius:50%;background:var(--color-danger);"></span> Terputus
        `;
        document.getElementById('lm-status').style.background = 'var(--color-danger-bg)';
        document.getElementById('lm-status').style.color = 'var(--color-danger)';
        document.getElementById('lm-status').style.borderColor = 'var(--color-danger-border)';
    });

    // Bandwidth: listener tasksRef di atas sudah filter scheduled_for_date=today via modular SDK.
    // Hapus duplikasi compat SDK listener — modular SDK sudah authenticated via Google credential.

    (function setupActiveSessionsListener() {
        const endpoint = "{{ route('admin.tasks.live.active_sessions') }}";

        async function fetchActiveSessions() {
            try {
                const res = await fetch(endpoint, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                if (json && json.success) {
                    activeSessions = json.sessions || {};
                    renderActiveSessions();
                } else {
                    renderActiveSessions('Gagal memuat sesi aktif.');
                }
            } catch (e) {
                console.warn('[active_sessions] fetch failed:', e);
                renderActiveSessions('Gagal memuat sesi aktif.');
            }
        }

        // Initial + polling
        fetchActiveSessions();
        activeSessionRenderTimer = setInterval(fetchActiveSessions, 5000);
    })();

    function updateDashboard() {
        const tasks = Object.entries(allTasks);
        let total = 0, done = 0, inProgress = 0, pending = 0, overdue = 0;
        let generalDone = 0, generalTotal = 0, rackDone = 0, rackTotal = 0;
        const waiterStats = {};

        // Init waiter stats
        waiters.forEach(w => {
            waiterStats[w.id] = { total: 0, done: 0, name: w.name || w.id };
        });

        tasks.forEach(([id, task]) => {
            total++;
            const wId = task.assigned_waiter_id;
            if (waiterStats[wId]) waiterStats[wId].total++;

            if (task.status === 'done') {
                done++;
                if (waiterStats[wId]) waiterStats[wId].done++;
            } else if (task.status === 'in_progress') {
                inProgress++;
            } else if (task.status === 'overdue') {
                overdue++;
            } else {
                pending++;
            }

            // Scope
            if (task.task_type === 'rack_check') {
                rackTotal++;
                if (task.status === 'done') rackDone++;
            } else {
                generalTotal++;
                if (task.status === 'done') generalDone++;
            }
        });

        // Update KPI
        document.getElementById('kpi-total').textContent = total;
        document.getElementById('kpi-done').textContent = done;
        document.getElementById('kpi-progress').textContent = inProgress;
        document.getElementById('kpi-pending').textContent = pending;
        document.getElementById('kpi-overdue').textContent = overdue;

        // Update scope bars
        const generalPct = generalTotal > 0 ? (generalDone / generalTotal * 100) : 0;
        const rackPct = rackTotal > 0 ? (rackDone / rackTotal * 100) : 0;
        document.getElementById('bar-general').style.width = generalPct + '%';
        document.getElementById('label-general').textContent = `${generalDone}/${generalTotal}`;
        document.getElementById('bar-rack').style.width = rackPct + '%';
        document.getElementById('label-rack').textContent = `${rackDone}/${rackTotal}`;

        // Update waiter progress
        renderWaiterProgress(waiterStats);

        // Update feed
        buildFeed(tasks);
    }

    function renderWaiterProgress(stats) {
        const container = document.getElementById('lm-waiter-list');
        const sorted = Object.entries(stats)
            .filter(([, s]) => s.total > 0)
            .sort((a, b) => {
                const pctA = a[1].total > 0 ? a[1].done / a[1].total : 0;
                const pctB = b[1].total > 0 ? b[1].done / b[1].total : 0;
                return pctB - pctA;
            });

        if (sorted.length === 0) {
            container.innerHTML = '<div style="color:var(--color-text-muted);font-size:0.85rem;text-align:center;padding:20px;">Belum ada tugas hari ini</div>';
            return;
        }

        container.innerHTML = sorted.map(([wId, s]) => {
            const pct = s.total > 0 ? Math.round(s.done / s.total * 100) : 0;
            const barColor = pct === 100 ? 'var(--color-success)' : pct >= 50 ? 'var(--color-primary)' : 'var(--color-warning)';
            const waiter = waiterMap[wId];
            const avatar = waiter?.photo_url
                ? `<img src="${waiter.photo_url}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">`
                : `<div style="width:28px;height:28px;border-radius:50%;background:var(--color-primary-bg);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:600;color:var(--color-primary);">${(s.name || '?').charAt(0).toUpperCase()}</div>`;

            return `
                <div style="display:flex;align-items:center;gap:10px;">
                    ${avatar}
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                            <span style="font-size:0.82rem;font-weight:500;color:var(--color-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${s.name}</span>
                            <span style="font-size:0.75rem;color:var(--color-text-muted);flex-shrink:0;">${s.done}/${s.total} (${pct}%)</span>
                        </div>
                        <div style="height:6px;background:var(--color-border);border-radius:3px;overflow:hidden;">
                            <div style="height:100%;width:${pct}%;background:${barColor};border-radius:3px;transition:width 0.5s ease;"></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function buildFeed(tasks) {
        // Get completed tasks sorted by completed_at desc
        const completed = tasks
            .filter(([, t]) => t.status === 'done' && t.completed_at)
            .sort((a, b) => (b[1].completed_at || 0) - (a[1].completed_at || 0))
            .slice(0, MAX_FEED);

        const container = document.getElementById('lm-feed');

        if (completed.length === 0) {
            container.innerHTML = '<div style="color:var(--color-text-muted);font-size:0.85rem;text-align:center;padding:20px;">Belum ada tugas selesai hari ini</div>';
            return;
        }

        container.innerHTML = completed.map(([id, task]) => {
            const waiter = waiterMap[task.assigned_waiter_id];
            const name = waiter?.name || task.assigned_waiter_id || '?';
            const time = task.completed_at ? formatTime(task.completed_at) : '-';
            const icon = task.task_type === 'rack_check' ? '📦' : '📝';
            const title = task.title || task.rack_name || 'Tugas';

            return `
                <div style="display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-radius:var(--radius-sm);background:var(--color-success-bg);border:1px solid var(--color-success-border);">
                    <span style="font-size:1rem;flex-shrink:0;">${icon}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.82rem;color:var(--color-text);"><strong>${name}</strong> menyelesaikan <em>${truncate(title, 30)}</em></div>
                        <div style="font-size:0.72rem;color:var(--color-text-muted);margin-top:2px;">${time}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function formatTime(timestamp) {
        if (!timestamp) return '-';
        const ts = typeof timestamp === 'number' && timestamp > 9999999999 ? timestamp : timestamp * 1000;
        const d = new Date(ts);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function truncate(str, max) {
        if (!str) return '';
        return str.length > max ? str.substring(0, max) + '...' : str;
    }

    function formatDuration(ms) {
        const minutes = Math.max(0, Math.floor(ms / 60000));
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours <= 0) return `${minutes} menit`;
        if (mins <= 0) return `${hours} jam`;
        return `${hours} jam ${mins} menit`;
    }

    function flattenActiveSessions(raw) {
        const list = [];
        Object.entries(raw || {}).forEach(([rackId, sessions]) => {
            Object.entries(sessions || {}).forEach(([sessionId, s]) => {
                if (!s || typeof s !== 'object') return;
                list.push({ rackId, sessionId, ...s });
            });
        });
        return list
            .sort((a, b) => (b.last_seen || 0) - (a.last_seen || 0))
            .slice(0, MAX_ACTIVE_SESSIONS);
    }

    function renderActiveSessions(errorMessage = '') {
        const body = document.getElementById('lm-active-sessions-body');
        const count = document.getElementById('lm-active-sessions-count');
        if (!body || !count) return;

        if (errorMessage) {
            count.textContent = '0 sesi';
            body.innerHTML = `<div style="color: var(--color-danger); font-size: 0.85rem; text-align: center; padding: 20px; border:1px dashed var(--color-danger-border); border-radius:var(--radius-md); grid-column:1 / -1;">${errorMessage}</div>`;
            return;
        }

        const sessions = flattenActiveSessions(activeSessions);
        count.textContent = `${sessions.length} sesi`;

        if (sessions.length === 0) {
            body.innerHTML = '<div style="color: var(--color-text-muted); font-size: 0.85rem; text-align: center; padding: 20px; border:1px dashed var(--color-border); border-radius:var(--radius-md); grid-column:1 / -1;">Tidak ada waiter sedang scan rak.</div>';
            return;
        }

        const now = Date.now();
        body.innerHTML = sessions.map((s) => {
            const startedAt = Number(s.started_at || 0);
            const lastSeen = Number(s.last_seen || 0);
            const sinceSeen = Math.max(0, now - lastSeen);
            const isIdle = sinceSeen > ACTIVE_IDLE_MS;
            const isStale = sinceSeen > ACTIVE_STALE_MS;
            const duration = startedAt > 0 ? formatDuration(now - startedAt) : '-';
            return `
                <div style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px;opacity:${isStale ? '0.5' : '1'};background:${isStale ? 'var(--color-bg-muted, #f8fafc)' : '#fff'};">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">
                        <div style="font-size:0.85rem;font-weight:600;color:var(--color-text);">${s.rack_name || 'Rak'}</div>
                        <span style="font-size:0.72rem;font-weight:600;padding:3px 8px;border-radius:999px;border:1px solid ${isIdle ? 'var(--color-warning-border)' : 'var(--color-success-border)'};background:${isIdle ? 'var(--color-warning-bg)' : 'var(--color-success-bg)'};color:${isIdle ? 'var(--color-warning)' : 'var(--color-success)'};">${isIdle ? '🟡 Idle' : '🟢 Online'}</span>
                    </div>
                    <div style="font-size:0.78rem;color:var(--color-text-muted);margin-bottom:4px;">${s.rack_code || '-'}</div>
                    <div style="font-size:0.82rem;color:var(--color-text);margin-bottom:4px;"><strong>${s.waiter_name || 'Waiter'}</strong></div>
                    <div style="font-size:0.76rem;color:var(--color-text-muted);">Durasi sesi: ${duration}${isIdle ? ' · Idle' : ''}</div>
                </div>
            `;
        }).join('');
    }

    // Visibility API: detach listener when tab hidden to save bandwidth
    let unsubscribe = null;
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Tab hidden — we keep the listener but could detach for extreme savings
            // For now, Firebase SDK handles this efficiently with keep-alive
        }
    });
</script>
@endsection
