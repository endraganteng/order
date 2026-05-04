<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir - Order & Tasks Display</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a2e;
            color: white;
            padding: 20px;
        }

        /* ========== TAB NAVIGATION ========== */
        .tab-bar {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.6);
            padding: 12px 28px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.8);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .tab-btn.active-tasks {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        }

        .tab-badge {
            background: #ff4757;
            color: white;
            font-size: 13px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            min-width: 24px;
            text-align: center;
            animation: badgePulse 2s infinite;
        }

        .tab-badge.zero {
            background: rgba(255, 255, 255, 0.2);
            animation: none;
        }

        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        /* ========== TAB CONTENT ========== */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Waiter tasks moved to dedicated waiter portal */
        .tab-btn[data-tab="tasks"],
        #tab-tasks {
            display: none !important;
        }

        /* ========== HEADER ========== */
        .section-title {
            text-align: center;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .section-title.orders { color: #00d9ff; }
        .section-title.tasks { color: #f093fb; }

        /* ========== ORDER CARDS ========== */
        #orders-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        #expired-orders-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .order-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }

        .order-card.order-card-expired {
            background: linear-gradient(135deg, #475569 0%, #334155 100%);
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }

        .order-header {
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .order-waiter { font-size: 18px; font-weight: 600; }
        .order-time { font-size: 12px; opacity: 0.8; margin-top: 5px; }

        .product-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .product-badge {
            background: transparent;
            color: rgba(255, 255, 255, 0.4);
            width: auto;
            height: auto;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: normal;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 0;
            margin-right: 6px;
        }

        .product-details {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .product-name { font-weight: 600; font-size: 18px; flex-grow: 1; }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #ffd700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ========== TASK CARDS ========== */
        #tasks-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .task-card {
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
            position: relative;
            overflow: hidden;
        }

        .task-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .task-card.priority-urgent {
            background: linear-gradient(135deg, #f5576c 0%, #ff6b6b 100%);
        }
        .task-card.priority-urgent::before {
            background: #ff0000;
            height: 5px;
            animation: urgentGlow 1.5s ease-in-out infinite;
        }

        .task-card.priority-normal {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .task-card.priority-normal::before {
            background: #00d9ff;
        }

        .task-card.priority-low {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #333;
        }
        .task-card.priority-low::before {
            background: #a8edea;
        }

        @keyframes urgentGlow {
            0%, 100% { box-shadow: 0 0 5px rgba(255, 0, 0, 0.3); }
            50% { box-shadow: 0 0 20px rgba(255, 0, 0, 0.6); }
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .task-card.priority-low .task-header {
            border-bottom-color: rgba(0,0,0,0.1);
        }

        .task-priority-badge {
            font-size: 13px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(255, 255, 255, 0.25);
        }

        .task-card.priority-low .task-priority-badge {
            background: rgba(0, 0, 0, 0.1);
            color: #555;
        }

        .task-time {
            font-size: 13px;
            opacity: 0.85;
        }

        .task-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .task-description {
            font-size: 15px;
            opacity: 0.9;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .task-card.priority-low .task-description {
            color: #555;
        }

        .task-assigned {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 15px;
        }

        .task-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .cashier-worker-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.18);
            color: #fff;
            font-size: 14px;
            outline: none;
        }

        .cashier-worker-select option {
            color: #111;
        }

        .task-worker-help {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .task-btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .task-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .task-btn:active {
            transform: translateY(0);
        }

        .task-btn.done-btn {
            background: rgba(255, 255, 255, 0.95);
            color: #28a745;
        }

        .task-btn.skip-btn {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }

        .task-card.priority-low .task-btn.skip-btn {
            background: rgba(0, 0, 0, 0.1);
            color: #666;
        }

        .task-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ========== EMPTY STATE ========== */
        .no-orders, .no-tasks, .no-expired-orders {
            text-align: center;
            color: #888;
            font-size: 20px;
            margin-top: 80px;
        }

        .expired-history-note {
            text-align: center;
            margin: -10px 0 16px 0;
            color: rgba(255, 255, 255, 0.75);
            font-size: 14px;
        }

        .expired-pagination {
            max-width: 1400px;
            margin: 18px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .expired-pagination-info {
            color: rgba(255, 255, 255, 0.78);
            font-size: 13px;
        }

        .expired-pagination-controls {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .expired-pagination-btn {
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .expired-pagination-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .expired-pagination-page {
            font-size: 13px;
            color: #e2e8f0;
            font-weight: 600;
        }

        .no-tasks .empty-icon {
            font-size: 64px;
            margin-bottom: 15px;
            display: block;
        }

        /* ========== STATUS INDICATORS ========== */
        #connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ffc107;
            color: #333;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: block;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        #connection-status.offline {
            background: #dc3545;
            color: white;
            animation: pulse 2s infinite;
        }

        #connection-status.online {
            background: #28a745;
            color: white;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        #toast-container {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
            font-weight: 600;
            min-width: 300px;
            justify-content: center;
        }

        .toast.toast-order { background: #28a745; }
        .toast.toast-task { background: linear-gradient(135deg, #f093fb, #f5576c); }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 480px) {
            .tab-btn { padding: 10px 18px; font-size: 15px; }
            .section-title { font-size: 22px; }
            #tasks-container { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div id="toast-container"></div>
    <div id="connection-status">⌛ Connecting...</div>

    <div style="position: absolute; top: 20px; left: 20px;">
        <button id="btn-test-sound" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
            🔊 Test Suara & Aktifkan
        </button>
    </div>

    <!-- TAB NAVIGATION -->
    <div class="tab-bar">
        <button class="tab-btn active" data-tab="orders" onclick="switchTab('orders')">
            📋 Orderan Masuk
        </button>
        <button class="tab-btn" data-tab="expired-orders" onclick="switchTab('expired-orders')">
            🕓 Riwayat Expired
        </button>
        <button class="tab-btn" data-tab="tasks" onclick="switchTab('tasks')">
            📝 Tugas Supervisor
            <span id="task-badge" class="tab-badge zero">0</span>
        </button>
    </div>

    <!-- TAB: ORDERS -->
    <div id="tab-orders" class="tab-content active">
        <div id="orders-container">
            <div class="no-orders">Menunggu orderan...</div>
        </div>
    </div>

    <!-- TAB: EXPIRED ORDERS -->
    <div id="tab-expired-orders" class="tab-content">
        <h1 class="section-title orders">🕓 Riwayat Order Expired</h1>
        <div class="expired-history-note">Menampilkan order expired terbaru khusus hari ini.</div>
        <div id="expired-orders-container">
            <div class="no-expired-orders">Belum ada order expired hari ini.</div>
        </div>
        <div id="expired-orders-pagination" class="expired-pagination"></div>
    </div>

    <!-- TAB: TASKS -->
    <div id="tab-tasks" class="tab-content">
        <h1 class="section-title tasks">📝 Tugas dari Supervisor</h1>
        <div id="tasks-container">
            <div class="no-tasks">
                <span class="empty-icon">✅</span>
                Tidak ada tugas saat ini
            </div>
        </div>
    </div>

    <script id="cashier-workers-data" type="application/json">{!! json_encode($cashierWorkers ?? []) !!}</script>

    <script type="module">
        // Firebase Realtime Database configuration
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
        import { getDatabase, ref, onValue, get, query, orderByChild, startAt, endAt, limitToLast } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js';

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
        const ordersContainer = document.getElementById('orders-container');
        const expiredOrdersContainer = document.getElementById('expired-orders-container');
        const expiredOrdersPaginationEl = document.getElementById('expired-orders-pagination');
        const tasksContainer = document.getElementById('tasks-container');
        const taskBadge = document.getElementById('task-badge');
        const cashierWorkersDataEl = document.getElementById('cashier-workers-data');
        let cashierWorkers = [];
        try {
            cashierWorkers = JSON.parse(cashierWorkersDataEl?.textContent || '[]');
        } catch (error) {
            cashierWorkers = [];
        }

        let serverTimeOffsetMs = 0;
        const serverTimeOffsetRef = ref(database, '.info/serverTimeOffset');
        onValue(serverTimeOffsetRef, (snapshot) => {
            const offset = Number(snapshot.val());
            serverTimeOffsetMs = Number.isFinite(offset) ? offset : 0;
            refreshExpiredOrdersForToday();
        });

        function getServerNowSeconds() {
            return (Date.now() + serverTimeOffsetMs) / 1000;
        }

        const EXPIRED_ORDERS_PER_PAGE = 12;
        let expiredOrdersToday = [];
        let expiredOrdersPage = 1;
        let expiredHasOlderPages = false;
        let expiredIsLoading = false;
        let expiredUsingCacheFallback = false;
        let latestOrdersCache = [];
        let currentExpiredDayRangeKey = '';
        const expiredPageCache = new Map();
        const expiredOldestCursorByPage = new Map();

        function normalizeUnixSeconds(value) {
            const numericValue = Number(value || 0);
            if (!Number.isFinite(numericValue) || numericValue <= 0) {
                return 0;
            }

            if (numericValue > 9999999999) {
                return Math.floor(numericValue / 1000);
            }

            return Math.floor(numericValue);
        }

        function getServerTodayRangeSeconds() {
            const now = new Date(Date.now() + serverTimeOffsetMs);
            const start = new Date(now);
            start.setHours(0, 0, 0, 0);
            const end = new Date(now);
            end.setHours(23, 59, 59, 999);

            const startSeconds = Math.floor(start.getTime() / 1000);
            const endSeconds = Math.floor(end.getTime() / 1000);

            return {
                startSeconds,
                endSeconds,
                dayKey: `${startSeconds}:${endSeconds}`,
            };
        }

        function renderExpiredOrdersPagination(totalCount) {
            if (!expiredOrdersPaginationEl) {
                return;
            }

            if (!totalCount) {
                expiredOrdersPaginationEl.innerHTML = '';
                return;
            }

            expiredOrdersPaginationEl.innerHTML = `
                <div class="expired-pagination-info">
                    Halaman ${expiredOrdersPage} • Menampilkan ${totalCount} order expired terbaru hari ini
                </div>
                <div class="expired-pagination-controls">
                    <button type="button" class="expired-pagination-btn js-expired-prev" ${expiredOrdersPage <= 1 ? 'disabled' : ''}>← Sebelumnya</button>
                    <span class="expired-pagination-page">Halaman ${expiredOrdersPage}</span>
                    <button type="button" class="expired-pagination-btn js-expired-next" ${!expiredHasOlderPages ? 'disabled' : ''}>Berikutnya →</button>
                </div>
            `;

            const prevButton = expiredOrdersPaginationEl.querySelector('.js-expired-prev');
            const nextButton = expiredOrdersPaginationEl.querySelector('.js-expired-next');

            if (prevButton) {
                prevButton.addEventListener('click', async () => {
                    if (expiredOrdersPage > 1 && !expiredIsLoading) {
                        expiredOrdersPage -= 1;
                        await loadExpiredOrdersPage(expiredOrdersPage);
                    }
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', async () => {
                    if (expiredHasOlderPages && !expiredIsLoading) {
                        expiredOrdersPage += 1;
                        await loadExpiredOrdersPage(expiredOrdersPage);
                    }
                });
            }
        }

        function renderExpiredOrdersTab() {
            expiredOrdersContainer.innerHTML = '';

            if (!expiredOrdersToday.length) {
                expiredOrdersContainer.innerHTML = '<div class="no-expired-orders">Belum ada order expired hari ini.</div>';
                if (expiredOrdersPaginationEl) {
                    expiredOrdersPaginationEl.innerHTML = '';
                }
                return;
            }

            expiredOrdersToday.forEach((order) => {
                expiredOrdersContainer.appendChild(renderOrderCard(order, true));
            });

            renderExpiredOrdersPagination(expiredOrdersToday.length);
        }

        function getExpiredRowsFromCache(range, nowSeconds) {
            if (!latestOrdersCache.length) {
                return [];
            }

            return latestOrdersCache
                .map((order) => {
                    const normalizedOrder = { ...(order || {}) };
                    normalizedOrder.expires_at = normalizeUnixSeconds(order?.expires_at);
                    normalizedOrder.created_at = normalizeUnixSeconds(order?.created_at);
                    normalizedOrder.id = String(order?.id || '');
                    return normalizedOrder;
                })
                .filter((order) => order.id && order.expires_at > 0 && order.expires_at <= nowSeconds && order.expires_at >= range.startSeconds)
                .sort((a, b) => Number(b.expires_at || 0) - Number(a.expires_at || 0));
        }

        async function loadExpiredOrdersPage(targetPage, forceRefresh = false) {
            if (expiredIsLoading) {
                return;
            }

            const range = getServerTodayRangeSeconds();
            const dayChanged = currentExpiredDayRangeKey !== range.dayKey;
            if (dayChanged) {
                expiredPageCache.clear();
                expiredOldestCursorByPage.clear();
                expiredOrdersPage = 1;
                expiredUsingCacheFallback = false;
            }
            currentExpiredDayRangeKey = range.dayKey;

            if (!forceRefresh && expiredPageCache.has(targetPage)) {
                const cached = expiredPageCache.get(targetPage);
                expiredOrdersToday = cached.rows;
                expiredHasOlderPages = cached.hasOlderPages;
                renderExpiredOrdersTab();
                return;
            }

            expiredIsLoading = true;
            expiredOrdersContainer.innerHTML = '<div class="no-expired-orders">Memuat riwayat expired...</div>';

            const previousPageCursor = targetPage > 1 ? expiredOldestCursorByPage.get(targetPage - 1) : null;
            if (targetPage > 1 && !previousPageCursor) {
                expiredIsLoading = false;
                expiredOrdersToday = [];
                expiredHasOlderPages = false;
                renderExpiredOrdersTab();
                return;
            }

            const nowSeconds = Math.floor(getServerNowSeconds());
            const upperBoundValue = previousPageCursor ? previousPageCursor.value : Math.min(range.endSeconds, nowSeconds);
            const fetchLimit = EXPIRED_ORDERS_PER_PAGE + (previousPageCursor ? 2 : 1);

            if (targetPage === 1) {
                const cacheRows = getExpiredRowsFromCache(range, nowSeconds);
                if (cacheRows.length) {
                    const previewRows = cacheRows.slice(0, EXPIRED_ORDERS_PER_PAGE);
                    expiredOrdersToday = previewRows;
                    expiredHasOlderPages = cacheRows.length > EXPIRED_ORDERS_PER_PAGE;
                    renderExpiredOrdersTab();
                }
            }

            const queryConstraints = [
                ref(database, 'orders'),
                orderByChild('expires_at'),
                startAt(range.startSeconds),
            ];

            if (previousPageCursor) {
                queryConstraints.push(endAt(upperBoundValue, previousPageCursor.key));
            } else {
                queryConstraints.push(endAt(upperBoundValue));
            }

            queryConstraints.push(limitToLast(fetchLimit));
            const pageQuery = query(...queryConstraints);

            try {
                const snapshot = await get(pageQuery);
                const rows = [];

                if (snapshot.exists()) {
                    snapshot.forEach((childSnapshot) => {
                        const order = childSnapshot.val() || {};
                        const expiresAt = normalizeUnixSeconds(order.expires_at);
                        if (expiresAt > 0 && expiresAt <= nowSeconds && expiresAt >= range.startSeconds) {
                            order.id = childSnapshot.key;
                            order.expires_at = expiresAt;
                            rows.push(order);
                        }
                    });
                }

                rows.sort((a, b) => Number(b.expires_at || 0) - Number(a.expires_at || 0));

                let filteredRows = rows;
                if (previousPageCursor) {
                    let removedAnchor = false;
                    filteredRows = rows.filter((row) => {
                        if (!removedAnchor && String(row.id) === String(previousPageCursor.key) && Number(row.expires_at || 0) === Number(previousPageCursor.value || 0)) {
                            removedAnchor = true;
                            return false;
                        }

                        return true;
                    });
                }

                let hasOlderPages = filteredRows.length > EXPIRED_ORDERS_PER_PAGE;
                let pageRows = hasOlderPages ? filteredRows.slice(0, EXPIRED_ORDERS_PER_PAGE) : filteredRows;
                let oldestRow = pageRows[pageRows.length - 1] || null;

                if (!pageRows.length && latestOrdersCache.length) {
                    const fallbackRows = getExpiredRowsFromCache(range, nowSeconds);

                    if (fallbackRows.length) {
                        const startIndex = (targetPage - 1) * EXPIRED_ORDERS_PER_PAGE;
                        pageRows = fallbackRows.slice(startIndex, startIndex + EXPIRED_ORDERS_PER_PAGE);
                        hasOlderPages = startIndex + EXPIRED_ORDERS_PER_PAGE < fallbackRows.length;
                        oldestRow = pageRows[pageRows.length - 1] || null;
                        expiredUsingCacheFallback = true;
                    }
                } else {
                    expiredUsingCacheFallback = false;
                }

                expiredPageCache.set(targetPage, {
                    rows: pageRows,
                    hasOlderPages,
                });

                if (oldestRow) {
                    expiredOldestCursorByPage.set(targetPage, {
                        value: Number(oldestRow.expires_at || 0),
                        key: String(oldestRow.id || ''),
                    });
                }

                expiredOrdersToday = pageRows;
                expiredHasOlderPages = hasOlderPages;
                renderExpiredOrdersTab();
            } catch (error) {
                console.log('Failed loading expired orders:', error);
                expiredOrdersToday = [];
                expiredHasOlderPages = false;
                renderExpiredOrdersTab();
            } finally {
                expiredIsLoading = false;
            }
        }

        async function refreshExpiredOrdersForToday() {
            const range = getServerTodayRangeSeconds();
            if (currentExpiredDayRangeKey !== range.dayKey) {
                expiredOrdersPage = 1;
                expiredPageCache.clear();
                expiredOldestCursorByPage.clear();
                await loadExpiredOrdersPage(1, true);
                return;
            }

            if (expiredOrdersPage === 1) {
                await loadExpiredOrdersPage(1, true);
            }
        }

        // Sound notification setup
        const notificationSound = new Audio("{{ asset('ordermasuk.mp3') }}");
        let processedOrderIds = new Set();
        let processedTaskIds = new Set();
        let initialLoadComplete = false;
        let initialTaskLoadComplete = false;

        function sortCashierWorkers() {
            cashierWorkers.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'id-ID'));
        }

        sortCashierWorkers();

        function buildCashierWorkerOptions(selectedWorkerId = '') {
            if (!cashierWorkers.length) {
                return '<option value="">Belum ada kasir aktif</option>';
            }

            const options = ['<option value="">Pilih nama kasir...</option>'];
            cashierWorkers.forEach((worker) => {
                const selected = selectedWorkerId === worker.id ? 'selected' : '';
                options.push(`<option value="${escapeHtml(worker.id)}" ${selected}>${escapeHtml(worker.name || '-')}</option>`);
            });

            return options.join('');
        }

        function refreshCashierWorkerSelects() {
            document.querySelectorAll('.task-card').forEach((card) => {
                const select = card.querySelector('.cashier-worker-select');
                const doneButton = card.querySelector('.done-btn');
                if (!select || !doneButton) {
                    return;
                }

                const currentValue = select.value || '';
                select.innerHTML = buildCashierWorkerOptions(currentValue);
                if (currentValue) {
                    select.value = currentValue;
                }

                doneButton.disabled = !cashierWorkers.length;
            });
        }

        async function loadCashierWorkersFromBackend() {
            try {
                const response = await fetch('/cashier/workers', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (!payload?.success || !Array.isArray(payload.workers)) {
                    return;
                }

                cashierWorkers = payload.workers;
                sortCashierWorkers();
                refreshCashierWorkerSelects();
            } catch (error) {
                console.log('Failed to load cashier workers:', error);
            }
        }

        loadCashierWorkersFromBackend();

        // Tab switching
        window.switchTab = function(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active');
                el.classList.remove('active-tasks');
            });

            document.getElementById('tab-' + tab).classList.add('active');
            const btn = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
            btn.classList.add(tab === 'tasks' ? 'active-tasks' : 'active');

            if (tab === 'expired-orders') {
                loadExpiredOrdersPage(expiredOrdersPage || 1, true);
            }
        };

        // Button Test Sound
        document.getElementById('btn-test-sound').addEventListener('click', () => {
            notificationSound.play()
                .then(() => {
                    alert('Suara berhasil diputar! Autoplay sekarang aktif.');
                })
                .catch(error => {
                    alert('Gagal memutar suara: ' + error.message);
                    console.error('Audio Error:', error);
                });
        });

        // Show toast notification
        function showToast(message, type = 'order') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = message;
            document.getElementById('toast-container').appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s';
                setTimeout(() => toast.remove(), 500);
            }, 5000);
        }

        // ========================================
        // ORDERS LISTENER (existing logic preserved)
        // ========================================
        const todayStartSeconds = Math.floor(new Date().setHours(0, 0, 0, 0) / 1000);
        const ordersRef = query(ref(database, 'orders'), orderByChild('created_at'), startAt(todayStartSeconds));

        function formatOrderDateTime(unixSeconds) {
            if (!unixSeconds) {
                return '-';
            }

            const date = new Date(unixSeconds * 1000);
            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        function renderOrderCard(order, isExpired = false) {
            const now = getServerNowSeconds();
            const expiresAt = normalizeUnixSeconds(order.expires_at);
            const createdAt = normalizeUnixSeconds(order.created_at);
            const queueNumber = order.queue_number ? `#${escapeHtml(String(order.queue_number))}` : '';

            const orderCard = document.createElement('div');
            orderCard.className = isExpired ? 'order-card order-card-expired' : 'order-card';
            orderCard.id = `${isExpired ? 'expired-order' : 'order'}-${order.id}`;

            let productsHTML = '';
            if (order.products && Array.isArray(order.products)) {
                productsHTML = order.products.map((product, index) => {
                    const productName = escapeHtml(String(product?.name || '-'));
                    const productPrice = Number(product?.price || 0);

                    return `
                        <div class="product-item">
                            <div class="product-badge">${index + 1}</div>
                            <div class="product-details">
                                <div class="product-name">${productName}</div>
                                <div class="product-price">Rp ${productPrice.toLocaleString('id-ID')}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            const expiredInfo = isExpired
                ? `<div class="order-time" style="color:#fecaca;">⛔ Expired: ${escapeHtml(formatOrderDateTime(expiresAt))}</div>`
                : '';

            orderCard.innerHTML = `
                <div class="order-header" style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="order-waiter">👤 ${escapeHtml(String(order.waiter_name || 'Unknown'))}</div>
                        <div class="order-time">🕐 ${escapeHtml(formatOrderDateTime(createdAt))}</div>
                        ${expiredInfo}
                    </div>
                    <div style="font-size: 24px; font-weight: bold; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 8px;">
                        ${queueNumber}
                    </div>
                </div>
                ${productsHTML}
            `;

            if (!isExpired && expiresAt > 0) {
                const timeUntilExpiry = (expiresAt - now) * 1000;
                if (timeUntilExpiry > 0) {
                    setTimeout(() => {
                        const card = document.getElementById(`order-${order.id}`);
                        if (card) {
                            card.style.animation = 'slideOut 0.3s ease-out';
                            setTimeout(() => card.remove(), 300);
                        }
                    }, timeUntilExpiry);
                }
            }

            return orderCard;
        }

        function handleOrdersSnapshot(snapshot) {
            ordersContainer.innerHTML = '';
            latestOrdersCache = [];

            let hasNewOrders = false;
            if (snapshot.exists()) {
                snapshot.forEach((childSnapshot) => {
                    if (initialLoadComplete && !processedOrderIds.has(childSnapshot.key)) {
                        const orderData = childSnapshot.val();
                        const now = getServerNowSeconds();
                        const expiresAt = normalizeUnixSeconds(orderData?.expires_at);
                        if (!expiresAt || expiresAt > now) {
                            hasNewOrders = true;
                        }
                    }
                    processedOrderIds.add(childSnapshot.key);
                });
            }

            if (hasNewOrders) {
                notificationSound.play().catch(error => {
                    console.log('Audio playback blocked:', error);
                });
                showToast('🔔 Ada Order Baru!', 'order');
            }

            initialLoadComplete = true;

            if (!snapshot.exists()) {
                ordersContainer.innerHTML = '<div class="no-orders">Menunggu orderan...</div>';
                return;
            }

            const orders = [];
            snapshot.forEach((childSnapshot) => {
                const order = childSnapshot.val();
                order.id = childSnapshot.key;
                order.expires_at = normalizeUnixSeconds(order.expires_at);
                order.created_at = normalizeUnixSeconds(order.created_at);
                orders.push(order);
            });

            latestOrdersCache = orders;

            orders.sort((a, b) => (a.queue_number || 0) - (b.queue_number || 0));

            const now = getServerNowSeconds();
            const activeOrders = orders.filter((order) => {
                const expiresAt = normalizeUnixSeconds(order.expires_at);
                return !(expiresAt && expiresAt < now);
            });

            if (!activeOrders.length) {
                ordersContainer.innerHTML = '<div class="no-orders">Tidak ada order aktif.</div>';
            } else {
                activeOrders.forEach((order) => {
                    ordersContainer.appendChild(renderOrderCard(order, false));
                });
            }
        }

        // Try indexed query first, fallback to full node if index missing
        onValue(ordersRef, handleOrdersSnapshot, (error) => {
            console.warn('Orders query failed (missing .indexOn?), falling back to full node listener:', error.message);
            // Fallback: listen to entire orders node without query filter
            const fallbackRef = ref(database, 'orders');
            onValue(fallbackRef, (snapshot) => {
                // Wrap in a filtered snapshot-like handler
                const now = getServerNowSeconds();
                ordersContainer.innerHTML = '';
                latestOrdersCache = [];

                if (!snapshot.exists()) {
                    ordersContainer.innerHTML = '<div class="no-orders">Menunggu orderan...</div>';
                    initialLoadComplete = true;
                    return;
                }

                let hasNewOrders = false;
                const orders = [];
                snapshot.forEach((childSnapshot) => {
                    const order = childSnapshot.val();
                    order.id = childSnapshot.key;
                    order.created_at = normalizeUnixSeconds(order.created_at);
                    order.expires_at = normalizeUnixSeconds(order.expires_at);

                    // Client-side filter: only today's orders
                    if (order.created_at >= todayStartSeconds) {
                        if (initialLoadComplete && !processedOrderIds.has(childSnapshot.key)) {
                            if (!order.expires_at || order.expires_at > now) {
                                hasNewOrders = true;
                            }
                        }
                        processedOrderIds.add(childSnapshot.key);
                        orders.push(order);
                    }
                });

                if (hasNewOrders) {
                    notificationSound.play().catch(e => console.log('Audio blocked:', e));
                    showToast('🔔 Ada Order Baru!', 'order');
                }

                initialLoadComplete = true;
                latestOrdersCache = orders;

                orders.sort((a, b) => (a.queue_number || 0) - (b.queue_number || 0));

                const activeOrders = orders.filter((order) => {
                    return !(order.expires_at && order.expires_at < now);
                });

                if (!activeOrders.length) {
                    ordersContainer.innerHTML = '<div class="no-orders">Tidak ada order aktif.</div>';
                } else {
                    activeOrders.forEach((order) => {
                        ordersContainer.appendChild(renderOrderCard(order, false));
                    });
                }
            });
        });

        // Expired tab uses a scoped TODAY query with pagination to avoid loading all historical data.
        loadExpiredOrdersPage(1, true);

        // Tasks moved to dedicated waiter portal. Hide badge for cashier page.
        updateBadge(0);

        // Update badge counter
        function updateBadge(count) {
            taskBadge.textContent = count;
            if (count > 0) {
                taskBadge.classList.remove('zero');
            } else {
                taskBadge.classList.add('zero');
            }
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Complete task via API
        window.completeTask = async function(taskId, buttonEl) {
            // Disable buttons
            const card = buttonEl.closest('.task-card');
            const buttons = card.querySelectorAll('.task-btn');
            buttons.forEach(btn => btn.disabled = true);
            buttonEl.innerHTML = '⏳ Processing...';

            if (!cashierWorkers.length) {
                buttons.forEach(btn => btn.disabled = false);
                buttonEl.innerHTML = '✅ Selesai';
                alert('Belum ada nama kasir aktif. Silakan minta supervisor menambahkan nama kasir di halaman admin.');
                return;
            }

            const workerSelect = card.querySelector('.cashier-worker-select');
            const pickedWorkerId = workerSelect?.value || '';

            if (!pickedWorkerId) {
                buttons.forEach(btn => btn.disabled = false);
                buttonEl.innerHTML = '✅ Selesai';
                alert('Silakan pilih nama kasir terlebih dahulu.');
                return;
            }

            const pickedWorker = cashierWorkers.find((worker) => worker.id === pickedWorkerId);
            if (!pickedWorker) {
                buttons.forEach(btn => btn.disabled = false);
                buttonEl.innerHTML = '✅ Selesai';
                alert('Nama kasir tidak ditemukan. Silakan pilih ulang atau refresh halaman.');
                return;
            }

            try {
                const response = await fetch(`/cashier/tasks/${taskId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        status: 'done',
                        cashier_worker_id: pickedWorkerId
                    })
                });

                if (response.ok) {
                    // Card will be removed by Firebase real-time listener
                    card.style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => card.remove(), 300);

                    showToast('✅ Tugas selesai!', 'task');
                } else {
                    const payload = await response.json().catch(() => null);
                    throw new Error(payload?.message || 'Failed to update task');
                }
            } catch (error) {
                console.error('Error updating task:', error);
                buttons.forEach(btn => btn.disabled = false);
                buttonEl.innerHTML = '✅ Selesai';
                alert(error.message || 'Gagal mengupdate tugas. Coba lagi.');
            }
        };

        // ========================================
        // CONNECTION STATUS MONITORING
        // ========================================
        async function syncDueRecurringTasks() {
            try {
                await fetch('/cashier/tasks/sync-due', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
            } catch (error) {
                console.log('Recurring task sync error:', error);
            }
        }

        // Trigger sync on load
        syncDueRecurringTasks();

        let workerIntervalId = null;
        let syncIntervalId = null;
        let expiredIntervalId = null;

        function startCashierPollingIntervals() {
            workerIntervalId = setInterval(loadCashierWorkersFromBackend, 120000);
            syncIntervalId = setInterval(syncDueRecurringTasks, 120000);
            expiredIntervalId = setInterval(() => {
                refreshExpiredOrdersForToday();
            }, 60000);
        }

        function stopCashierPollingIntervals() {
            if (workerIntervalId) {
                clearInterval(workerIntervalId);
                workerIntervalId = null;
            }

            if (syncIntervalId) {
                clearInterval(syncIntervalId);
                syncIntervalId = null;
            }

            if (expiredIntervalId) {
                clearInterval(expiredIntervalId);
                expiredIntervalId = null;
            }
        }

        startCashierPollingIntervals();

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopCashierPollingIntervals();
                return;
            }

            loadCashierWorkersFromBackend();
            syncDueRecurringTasks();
            refreshExpiredOrdersForToday();
            stopCashierPollingIntervals();
            startCashierPollingIntervals();
        });

        const connectedRef = ref(database, '.info/connected');
        const statusDiv = document.getElementById('connection-status');

        onValue(connectedRef, (snapshot) => {
            if (snapshot.val() === true) {
                console.log('✅ Connected to Firebase');
                statusDiv.innerHTML = '✅ Online';
                statusDiv.classList.remove('offline');
                statusDiv.classList.add('online');
                statusDiv.style.opacity = '0.8';
            } else {
                console.log('❌ Disconnected from Firebase');
                statusDiv.innerHTML = '🔌 Offline - Reconnecting...';
                statusDiv.classList.remove('online');
                statusDiv.classList.add('offline');
                statusDiv.style.opacity = '1';
            }
        });
    </script>
</body>

</html>
