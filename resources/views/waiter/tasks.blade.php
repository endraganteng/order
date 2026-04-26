<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Tugas Waiter</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f6fb;
            color: #273444;
            padding: 20px;
        }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .top {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .top h1 { margin: 0; font-size: clamp(20px, 4vw, 28px); }
        .muted { color: #6b7280; font-size: 14px; }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-logout { background: #ef4444; color: #fff; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            border-left: 5px solid #3b82f6;
        }
        .card.urgent { border-left-color: #ef4444; }
        .card.low { border-left-color: #9ca3af; }
        .title { font-weight: 700; margin-bottom: 6px; font-size: 18px; }
        .desc { font-size: 14px; color: #4b5563; margin-bottom: 10px; line-height: 1.45; }
        .meta { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        .input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 9px 10px;
            margin-bottom: 10px;
        }
        textarea.input {
            min-height: 86px;
            resize: vertical;
        }
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #374151;
            margin-bottom: 8px;
        }
        .btn-done {
            width: 100%;
            background: #10b981;
            color: #fff;
        }
        .btn-scan {
            width: 100%;
            background: #2563eb;
            color: #fff;
            margin-bottom: 8px;
        }
        .tag-rack {
            display: inline-block;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #fff7ed;
            color: #9a3412;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .task-group {
            margin-bottom: 16px;
        }
        .task-group-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .task-group-title {
            margin: 0;
            font-size: 18px;
            color: #0f172a;
        }
        .task-group-subtitle {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }
        .task-group-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 10px;
            background: #e2e8f0;
            color: #1e293b;
        }
        .task-group-empty {
            font-size: 13px;
            color: #64748b;
            background: #fff;
            border-radius: 10px;
            border: 1px dashed #dbe2ea;
            padding: 12px;
        }
        .empty {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
        }
        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .alert.hidden { display: none; }
        .ok { background: #e7f8f1; color: #065f46; border: 1px solid #b7ebd4; }
        .err { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
        .portal-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .tab-btn {
            border: 1px solid #dbe2ea;
            background: #fff;
            color: #334155;
            border-radius: 999px;
            padding: 8px 14px;
            cursor: pointer;
            font-weight: 700;
        }
        .tab-btn.active {
            background: #1d4ed8;
            color: #fff;
            border-color: #1d4ed8;
        }
        .portal-panel { display: none; }
        .portal-panel.active { display: block; }
        .activity-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .activity-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }
        .activity-item-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .activity-item-text {
            font-size: 14px;
            color: #1f2937;
            line-height: 1.5;
            white-space: normal;
            word-break: break-word;
        }
        .activity-item-tags {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .activity-tag {
            font-size: 11px;
            border-radius: 999px;
            padding: 4px 8px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            font-size: 14px;
            vertical-align: top;
        }
        th { background: #f8fafc; }
        .scanner-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 999;
        }
        .scanner-box {
            width: min(100%, 560px);
            background: #fff;
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
        }
        .scanner-reader {
            width: 100%;
            min-height: 240px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .photo-proof-wrap {
            margin-top: 10px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 10px;
            background: #f8fafc;
        }
        .photo-proof-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
            color: #334155;
            font-weight: 700;
        }
        .photo-proof-preview {
            display: block;
            width: 100%;
            max-height: 220px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            background: #fff;
            margin-top: 8px;
        }
        .photo-proof-meta {
            font-size: 12px;
            color: #475569;
            margin-top: 6px;
            line-height: 1.4;
        }
        .btn-photo-clear {
            background: #e2e8f0;
            color: #1e293b;
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 700;
        }
        .btn-photo-view {
            background: #e0ecff;
            color: #1d4ed8;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 8px;
            border: 1px solid #bfdbfe;
            cursor: pointer;
            font-weight: 700;
        }
        .photo-view-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.72);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1001;
        }
        .photo-view-box {
            width: min(100%, 760px);
            max-height: calc(100vh - 32px);
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.35);
        }
        .photo-view-image {
            width: 100%;
            max-height: 72vh;
            object-fit: contain;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #f8fafc;
        }
        .photo-view-meta {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
        }
        .mobile-nav {
            display: none;
        }
        .mobile-nav-btn {
            border: none;
            background: transparent;
            padding: 10px 8px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
        }
        .mobile-nav-btn.active {
            color: #1d4ed8;
            border-top: 3px solid #1d4ed8;
            background: #f8fbff;
        }
        @media (max-width: 768px) {
            body {
                padding: 14px 14px 86px;
            }
            .portal-tabs {
                display: none;
            }
            .mobile-nav {
                display: grid;
                grid-template-columns: 1fr 1fr;
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1000;
                background: #ffffff;
                border-top: 1px solid #dbe2ea;
                box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.08);
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>🧑‍🍳 Portal Tugas Waiter</h1>
                <div class="muted">Login sebagai: <strong>{{ $waiterName }}</strong> ({{ $waiterEmail }})</div>
            </div>
            <a href="{{ route('waiter.logout', [], false) }}" class="btn btn-logout">Logout</a>
        </div>

        <div id="flash-success" class="alert ok{{ session('success') ? '' : ' hidden' }}">
            ✅ {{ session('success') ?? '' }}
        </div>
        <div id="flash-error" class="alert err{{ session('error') ? '' : ' hidden' }}">
            ❌ {{ session('error') ?? '' }}
        </div>

        <div class="portal-tabs">
            <button type="button" class="tab-btn js-tab-btn active" data-tab="tasks">📝 Tugas</button>
            <button type="button" class="tab-btn js-tab-btn" data-tab="reports">📔 Laporan Kegiatan</button>
        </div>

        <section id="panel-tasks" class="portal-panel active">
            <h2 style="margin: 0 0 10px 0;">Tugas Saya (<span id="pending-count">{{ count($pendingTasks) }}</span>)</h2>
            <div id="pending-container"></div>

            <h2 style="margin: 10px 0;">Riwayat Tugas Saya</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tugas</th>
                            <th>Status</th>
                            <th>Catatan</th>
                            <th>Verifikasi Rak</th>
                            <th>Laporan Stok Rak</th>
                            <th>Bukti Foto</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
        </section>

        <section id="panel-reports" class="portal-panel">
            <div class="card" style="border-left-color:#7c3aed;">
                <h2 style="margin: 0 0 6px 0;">📔 Laporan Kegiatan Waiter</h2>
                <div class="muted" style="margin-bottom: 10px;">
                    Tanggal: <strong id="report-date-label">{{ $reportDate ?? date('Y-m-d') }}</strong> • Opsional, tapi membantu supervisor memonitor aktivitas harian.
                </div>

                <form id="activity-report-form">
                    <label for="activity-text" class="meta" style="display:block; margin-bottom:6px; color:#111827; font-weight:600;">Aktivitas hari ini</label>
                    <textarea id="activity-text" class="input" name="activity_text" maxlength="2000" placeholder="Contoh: Cek kebersihan area lantai 1, refill saus, bantu closing shift sore"></textarea>
                    <button type="submit" id="activity-submit-btn" class="btn" style="background:#7c3aed; color:#fff; width:100%;">💾 Simpan Laporan Kegiatan</button>
                </form>

                <div id="activity-empty" class="empty" style="margin-top: 12px;">Belum ada laporan kegiatan untuk hari ini.</div>
                <div id="activity-report-list" class="activity-list"></div>
            </div>
        </section>
    </div>

    <nav class="mobile-nav" id="waiter-mobile-nav" aria-label="Menu Portal Waiter Mobile">
        <button type="button" class="mobile-nav-btn js-tab-btn active" data-tab="tasks">📝 Tugas</button>
        <button type="button" class="mobile-nav-btn js-tab-btn" data-tab="reports">📔 Laporan</button>
    </nav>

    <div id="scanner-modal" class="scanner-modal" aria-hidden="true">
        <div class="scanner-box">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <strong>📷 Scan Barcode Rak</strong>
                <button type="button" id="scanner-close-btn" class="btn" style="background:#ef4444;color:#fff;padding:6px 10px;">Tutup</button>
            </div>
            <div id="scanner-task-meta" class="muted" style="margin-top: 6px;"></div>
            <div id="scanner-reader" class="scanner-reader"></div>
            <div id="scanner-feedback" class="muted">Arahkan kamera ke barcode rak sampai terbaca.</div>
        </div>
    </div>

    <div id="photo-preview-modal" class="photo-view-modal" aria-hidden="true">
        <div class="photo-view-box">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
                <strong>📷 Preview Bukti Foto</strong>
                <button type="button" id="photo-preview-close-btn" class="btn" style="background:#ef4444;color:#fff;padding:6px 10px;">Tutup</button>
            </div>
            <img id="photo-preview-image" class="photo-view-image" src="" alt="Preview bukti foto">
            <div id="photo-preview-meta" class="photo-view-meta"></div>
        </div>
    </div>

    <script id="waiter-context" type="application/json">{!! json_encode([
        'waiterId' => $waiterId,
        'reportDate' => $reportDate ?? date('Y-m-d'),
        'pendingTasks' => $pendingTasks,
        'taskHistory' => $taskHistory,
        'activityReports' => $activityReports ?? [],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>

        const contextEl = document.getElementById('waiter-context');
        const context = contextEl ? JSON.parse(contextEl.textContent || '{}') : {};

        const waiterId = String(context.waiterId || '');
        const pendingCountEl = document.getElementById('pending-count');
        const pendingContainer = document.getElementById('pending-container');
        const historyBody = document.getElementById('history-body');
        const successEl = document.getElementById('flash-success');
        const errorEl = document.getElementById('flash-error');
        const scannerModalEl = document.getElementById('scanner-modal');
        const scannerCloseBtn = document.getElementById('scanner-close-btn');
        const scannerReaderElId = 'scanner-reader';
        const scannerFeedbackEl = document.getElementById('scanner-feedback');
        const scannerTaskMetaEl = document.getElementById('scanner-task-meta');
        const photoPreviewModalEl = document.getElementById('photo-preview-modal');
        const photoPreviewImageEl = document.getElementById('photo-preview-image');
        const photoPreviewMetaEl = document.getElementById('photo-preview-meta');
        const photoPreviewCloseBtn = document.getElementById('photo-preview-close-btn');
        const csrfToken = '{{ csrf_token() }}';
        const completeUrlTemplate = "{{ route('waiter.task.complete', ['id' => '__TASK_ID__'], false) }}";
        const pollUrl = "{{ route('waiter.task.poll', [], false) }}";
        const syncDueUrl = "{{ route('waiter.task.sync_due', [], false) }}";
        const activityStoreUrl = "{{ route('waiter.activity.store', [], false) }}";

        const tabButtons = Array.from(document.querySelectorAll('.js-tab-btn'));
        const panelTasks = document.getElementById('panel-tasks');
        const panelReports = document.getElementById('panel-reports');
        const reportDateLabelEl = document.getElementById('report-date-label');
        const activityFormEl = document.getElementById('activity-report-form');
        const activityTextEl = document.getElementById('activity-text');
        const activitySubmitBtn = document.getElementById('activity-submit-btn');
        const activityEmptyEl = document.getElementById('activity-empty');
        const activityReportListEl = document.getElementById('activity-report-list');

        const scannedBarcodeByTask = new Map();
        const stockReportItemsByTask = new Map();
        const noteDraftByTask = new Map();
        const photoProofByTask = new Map();
        let activeScannerTaskId = '';
        let activeScannerTaskLabel = '';
        let activeScannerExpectedBarcode = '';
        let scannerInstance = null;
        let scannerRunning = false;
        let pendingRenderDeferred = false;

        let waiterTasks = [
            ...(Array.isArray(context.pendingTasks) ? context.pendingTasks : []),
            ...(Array.isArray(context.taskHistory) ? context.taskHistory : []),
        ];
        let reportDate = String(context.reportDate || new Date().toISOString().slice(0, 10));
        let activityReports = Array.isArray(context.activityReports) ? context.activityReports : [];
        let syncDueInFlight = false;
        let syncDueCooldownUntil = 0;
        let syncDueBackoffMs = 0;
        let lastSyncDueAttemptAt = 0;
        let pollInFlight = false;
        let pollCooldownUntil = 0;
        let pollBackoffMs = 0;

        const MIN_SYNC_DUE_INTERVAL_MS = 5000;

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');
        }

        function parseTimestamp(value) {
            const num = Number(value || 0);
            return Number.isFinite(num) ? num : 0;
        }

        function getRetryAfterMs(response, fallbackMs = 30000) {
            if (!response?.headers) {
                return fallbackMs;
            }

            const raw = String(response.headers.get('Retry-After') || '').trim();
            if (raw === '') {
                return fallbackMs;
            }

            const seconds = Number(raw);
            if (Number.isFinite(seconds) && seconds > 0) {
                return Math.max(1000, Math.round(seconds * 1000));
            }

            const unixMs = Date.parse(raw);
            if (Number.isFinite(unixMs)) {
                return Math.max(1000, unixMs - Date.now());
            }

            return fallbackMs;
        }

        function formatDateTime(ts) {
            const unix = parseTimestamp(ts);
            if (!unix) return '-';

            return new Date(unix * 1000).toLocaleString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        function normalizeBarcodeValue(value) {
            return String(value || '').trim().toUpperCase();
        }

        function estimateDataUrlBytes(dataUrl) {
            const raw = String(dataUrl || '');
            const commaIndex = raw.indexOf(',');
            if (commaIndex < 0) {
                return 0;
            }

            const base64 = raw.slice(commaIndex + 1);
            if (!base64) {
                return 0;
            }

            const paddingMatch = base64.match(/=+$/);
            const padding = paddingMatch ? paddingMatch[0].length : 0;

            return Math.max(0, Math.floor((base64.length * 3) / 4) - padding);
        }

        function formatBytes(bytes) {
            const value = Number(bytes || 0);
            if (!Number.isFinite(value) || value <= 0) {
                return '0 B';
            }
            if (value < 1024) {
                return `${value} B`;
            }
            if (value < 1024 * 1024) {
                return `${(value / 1024).toFixed(1)} KB`;
            }

            return `${(value / (1024 * 1024)).toFixed(2)} MB`;
        }

        function readFileAsDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result || ''));
                reader.onerror = () => reject(new Error('Gagal membaca file foto.'));
                reader.readAsDataURL(file);
            });
        }

        function loadImageFromDataUrl(dataUrl) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = () => reject(new Error('Format foto tidak valid.'));
                img.src = dataUrl;
            });
        }

        async function compressPhotoProofFile(file) {
            if (!file || typeof file !== 'object') {
                throw new Error('File foto tidak ditemukan.');
            }

            const mime = String(file.type || '').toLowerCase();
            if (!mime.startsWith('image/')) {
                throw new Error('File harus berupa gambar.');
            }

            const maxRawFileSize = 10 * 1024 * 1024;
            if ((file.size || 0) > maxRawFileSize) {
                throw new Error('Ukuran file terlalu besar. Maksimal 10MB sebelum kompresi.');
            }

            const sourceDataUrl = await readFileAsDataUrl(file);
            const image = await loadImageFromDataUrl(sourceDataUrl);

            const maxWidth = 1280;
            const maxHeight = 1280;
            const widthScale = maxWidth / Math.max(1, image.width);
            const heightScale = maxHeight / Math.max(1, image.height);
            const scale = Math.min(1, widthScale, heightScale);
            const targetWidth = Math.max(1, Math.round(image.width * scale));
            const targetHeight = Math.max(1, Math.round(image.height * scale));

            const canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('Gagal memproses foto pada perangkat ini.');
            }

            ctx.drawImage(image, 0, 0, targetWidth, targetHeight);

            const outputDataUrl = canvas.toDataURL('image/jpeg', 0.82);
            const sizeBytes = estimateDataUrlBytes(outputDataUrl);
            const maxOutputSize = 3 * 1024 * 1024;
            if (sizeBytes > maxOutputSize) {
                throw new Error('Ukuran foto setelah kompresi masih terlalu besar (maks 3MB).');
            }

            return {
                dataUrl: outputDataUrl,
                sizeBytes,
                mimeType: 'image/jpeg',
                fileName: String(file.name || 'bukti-foto.jpg'),
            };
        }

        function setActiveTab(tab) {
            const targetTab = tab === 'reports' ? 'reports' : 'tasks';

            panelTasks.classList.toggle('active', targetTab === 'tasks');
            panelReports.classList.toggle('active', targetTab === 'reports');

            tabButtons.forEach((button) => {
                const isMatch = String(button.getAttribute('data-tab') || '') === targetTab;
                button.classList.toggle('active', isMatch);
            });
        }

        function normalizeActivityReport(report) {
            const normalized = report && typeof report === 'object' ? { ...report } : {};
            normalized.id = String(normalized.id || '');
            normalized.activity_text = String(normalized.activity_text || '').trim();
            normalized.report_date = String(normalized.report_date || reportDate);
            normalized.created_at = parseTimestamp(normalized.created_at);
            normalized.activity_items = Array.isArray(normalized.activity_items)
                ? normalized.activity_items
                    .map((item) => String(item || '').trim())
                    .filter((item) => item !== '')
                : [];

            return normalized;
        }

        function renderActivityReports() {
            const sortedReports = (Array.isArray(activityReports) ? activityReports : [])
                .map(normalizeActivityReport)
                .filter((report) => report.activity_text !== '')
                .sort((a, b) => b.created_at - a.created_at);

            if (reportDateLabelEl) {
                reportDateLabelEl.textContent = reportDate || '-';
            }

            if (!activityReportListEl || !activityEmptyEl) {
                return;
            }

            if (!sortedReports.length) {
                activityEmptyEl.classList.remove('hidden');
                activityReportListEl.innerHTML = '';
                return;
            }

            activityEmptyEl.classList.add('hidden');
            activityReportListEl.innerHTML = sortedReports.map((report, index) => {
                const tagsHtml = report.activity_items.length
                    ? `<div class="activity-item-tags">${report.activity_items.map((item) => `<span class="activity-tag">${escapeHtml(item)}</span>`).join('')}</div>`
                    : '';

                return `<article class="activity-item">
                    <div class="activity-item-head">
                        <span>#${index + 1}</span>
                        <span>${escapeHtml(formatDateTime(report.created_at))}</span>
                    </div>
                    <div class="activity-item-text">${escapeHtml(report.activity_text)}</div>
                    ${tagsHtml}
                </article>`;
            }).join('');
        }

        function hydrateActivityFromPayload(payload) {
            if (payload && typeof payload === 'object') {
                if (typeof payload.report_date === 'string' && payload.report_date.trim() !== '') {
                    reportDate = payload.report_date.trim();
                }

                if (Array.isArray(payload.activity_reports)) {
                    activityReports = payload.activity_reports;
                }
            }

            renderActivityReports();
        }

        function normalizeTask(task) {
            const normalized = task && typeof task === 'object' ? { ...task } : {};
            normalized.id = String(normalized.id || '');
            normalized.assigned_waiter_id = String(normalized.assigned_waiter_id || '');
            normalized.status = String(normalized.status || 'pending');
            normalized.priority = String(normalized.priority || 'normal');
            normalized.created_at = parseTimestamp(normalized.created_at);
            normalized.completed_at = parseTimestamp(normalized.completed_at);
            normalized.deadline_at = parseTimestamp(normalized.deadline_at);
            return normalized;
        }

        function showFlash(type, message) {
            const isSuccess = type === 'success';
            const target = isSuccess ? successEl : errorEl;
            const other = isSuccess ? errorEl : successEl;

            other.textContent = '';
            other.classList.add('hidden');
            target.textContent = (isSuccess ? '✅ ' : '❌ ') + message;
            target.classList.remove('hidden');
        }

        function isRackScanTask(task) {
            return Boolean(task?.requires_barcode_scan) || String(task?.task_type || 'general') === 'rack_check';
        }

        function isPendingFormInputActive() {
            const active = document.activeElement;
            if (!active || !(active instanceof HTMLElement)) {
                return false;
            }

            if (!pendingContainer.contains(active)) {
                return false;
            }

            return active.matches('.js-stock-report, .js-complete-form input[name="note"], .js-photo-proof');
        }

        function flushDeferredPendingRender() {
            if (!pendingRenderDeferred) {
                return;
            }

            if (isPendingFormInputActive()) {
                return;
            }

            pendingRenderDeferred = false;
            renderAllTasks();
        }

        function closePhotoPreviewModal() {
            if (!photoPreviewModalEl) {
                return;
            }

            photoPreviewModalEl.style.display = 'none';
            photoPreviewModalEl.setAttribute('aria-hidden', 'true');
            if (photoPreviewImageEl) {
                photoPreviewImageEl.src = '';
            }
            if (photoPreviewMetaEl) {
                photoPreviewMetaEl.textContent = '';
            }
        }

        function openPhotoPreviewForTask(taskId) {
            const task = waiterTasks.find((item) => String(item?.id || '') === String(taskId || ''));
            const photoUrl = String(task?.completed_photo_proof_url || '').trim();
            if (photoUrl === '') {
                showFlash('error', 'Foto bukti tidak ditemukan untuk task ini.');
                return;
            }

            if (!photoPreviewModalEl || !photoPreviewImageEl) {
                showFlash('error', 'Preview foto belum tersedia pada halaman ini.');
                return;
            }

            photoPreviewImageEl.src = photoUrl;
            photoPreviewModalEl.style.display = 'flex';
            photoPreviewModalEl.setAttribute('aria-hidden', 'false');

            if (photoPreviewMetaEl) {
                const sizeBytes = Number(task?.completed_photo_proof_size_bytes || 0);
                const mimeType = String(task?.completed_photo_proof_mime_type || 'image/*');
                const extra = Number.isFinite(sizeBytes) && sizeBytes > 0
                    ? ` • ${formatBytes(sizeBytes)}`
                    : '';
                photoPreviewMetaEl.textContent = `Format: ${mimeType}${extra}`;
            }
        }

        function escapeAttr(value) {
            return escapeHtml(value).replaceAll('`', '&#96;');
        }

        function renderPendingTaskCard(task) {
            const priority = task.priority || 'normal';
            const cls = priority === 'urgent' ? 'urgent' : (priority === 'low' ? 'low' : '');
            const scheduleText = task.scheduled_time
                ? `<div class="meta">Jadwal: ${escapeHtml(task.scheduled_for_date || '-')} ${escapeHtml(task.scheduled_time)}</div>`
                : '';
            const deadlineText = task.deadline_at
                ? `<div class="meta">Batas waktu: ${escapeHtml(formatDateTime(task.deadline_at))}</div>`
                : '';
            const requiresScan = isRackScanTask(task);
            const requiresPhotoProof = Boolean(task?.requires_photo_proof);
            const existingScan = String(scannedBarcodeByTask.get(task.id) || '');
            const existingStockReport = String(stockReportItemsByTask.get(task.id) || '');
            const existingNoteDraft = String(noteDraftByTask.get(task.id) || '');
            const existingPhotoProof = photoProofByTask.get(task.id) || null;
            const existingPhotoDataUrl = String(existingPhotoProof?.dataUrl || '');
            const rackTargetScope = String(task.rack_target_scope || 'single');
            const stockReportBlock = existingScan
                ? `<label class="meta" style="display:block; margin-top: 8px; margin-bottom: 4px; color:#111827; font-weight:600;">Laporan Barang Menipis/Habis (Opsional)</label>
                    <textarea class="input js-stock-report" name="stock_report_items" data-task-id="${escapeAttr(task.id)}" maxlength="2000" placeholder="Jika ada barang menipis/habis, tulis di sini. Boleh dikosongkan jika tidak ada.">${escapeHtml(existingStockReport)}</textarea>
                    <div class="meta" style="font-size:12px; color:#6b7280;">Alur cek rak: scan barcode rak → (jika ada) isi barang menipis/habis → selesai.</div>`
                : `<div class="meta" style="font-size:12px; color:#9a3412; margin-top: 8px;">🔒 Form barang menipis/habis muncul setelah barcode rak berhasil di-scan.</div>`;
            const photoProofBlock = requiresPhotoProof
                ? `<div class="photo-proof-wrap">
                        <div class="photo-proof-head">
                            <span>📷 Bukti Foto Wajib</span>
                            ${existingPhotoDataUrl
                                ? `<button type="button" class="btn-photo-clear js-photo-proof-clear" data-task-id="${escapeAttr(task.id)}">Hapus Foto</button>`
                                : ''}
                        </div>
                        <input
                            class="input js-photo-proof"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            data-task-id="${escapeAttr(task.id)}"
                            style="margin-bottom: 6px;"
                        >
                        <div class="photo-proof-meta">Ambil/upload foto bukti. Sistem akan kompres otomatis sebelum kirim.</div>
                        ${existingPhotoDataUrl
                            ? `<img src="${escapeAttr(existingPhotoDataUrl)}" alt="Bukti foto task ${escapeAttr(task.title || '-')}" class="photo-proof-preview">
                               <div class="photo-proof-meta">Foto siap dikirim • ${escapeHtml(formatBytes(existingPhotoProof?.sizeBytes || estimateDataUrlBytes(existingPhotoDataUrl)))}.</div>`
                            : '<div class="photo-proof-meta" style="color:#9a3412;">⚠️ Belum ada foto bukti.</div>'}
                    </div>`
                : '';
            const rackBlock = requiresScan
                ? `<div style="margin: 8px 0 10px 0;">
                        <span class="tag-rack">📦 Cek Rak - Wajib Scan</span>
                        <div class="meta">Rak: <strong>${escapeHtml(task.rack_name || '-')}</strong> (${escapeHtml(task.rack_location || '-')})</div>
                        <div class="meta">Barcode Rak: <code>${escapeHtml(task.rack_barcode_value || '-')}</code></div>
                        ${rackTargetScope === 'all' ? '<div class="meta" style="font-size:12px;color:#334155;">🎯 Bagian dari assignment <b>Semua Rak Aktif</b> (wajib scan tiap rak melalui task masing-masing).</div>' : ''}
                        <input type="hidden" name="scanned_barcode" value="${escapeAttr(existingScan)}">
                        <button type="button" class="btn btn-scan js-open-scanner" data-task-id="${escapeAttr(task.id)}" data-task-label="${escapeAttr(task.title || 'Task')}" data-rack-name="${escapeAttr(task.rack_name || '-')}" data-rack-barcode="${escapeAttr(task.rack_barcode_value || '')}">📷 Scan Barcode Rak</button>
                        <div class="meta" style="font-size:12px;color:${existingScan ? '#166534' : '#9a3412'};" data-scan-status>
                            ${existingScan ? `✅ Barcode ter-scan: <code>${escapeHtml(existingScan)}</code>` : '⚠️ Belum scan barcode rak.'}
                        </div>
                        ${stockReportBlock}
                    </div>`
                : '';
            const noteInput = requiresScan
                ? ''
                : `<input class="input" type="text" name="note" maxlength="500" placeholder="Catatan verifikasi (opsional)" value="${escapeAttr(existingNoteDraft)}">`;
            const submitLabel = requiresScan ? '✅ Selesaikan Cek Rak' : '✅ Verifikasi Selesai';

            return `<div class="card ${cls}">
                <div class="title">${escapeHtml(task.title || '-')}</div>
                ${task.description ? `<div class="desc">${escapeHtml(task.description)}</div>` : ''}
                <div class="meta">Prioritas: ${escapeHtml(String(priority).toUpperCase())}</div>
                <div class="meta">Dibuat: ${escapeHtml(formatDateTime(task.created_at))}</div>
                ${scheduleText}
                ${deadlineText}
                ${rackBlock}
                ${photoProofBlock}
                <form class="js-complete-form" data-task-id="${escapeHtml(task.id)}" style="margin-top: 10px;">
                    ${noteInput}
                    <button type="submit" class="btn btn-done">${submitLabel}</button>
                </form>
            </div>`;
        }

        function renderTaskGroupSection(title, subtitle, tasks, emptyMessage) {
            return `<section class="task-group">
                <div class="task-group-head">
                    <div>
                        <h3 class="task-group-title">${title}</h3>
                        <div class="task-group-subtitle">${subtitle}</div>
                    </div>
                    <span class="task-group-badge">${tasks.length} tugas</span>
                </div>
                ${tasks.length
                    ? `<div class="grid">${tasks.map(renderPendingTaskCard).join('')}</div>`
                    : `<div class="task-group-empty">${emptyMessage}</div>`}
            </section>`;
        }

        function renderPending(pendingTasks) {
            pendingCountEl.textContent = String(pendingTasks.length);

            const pendingTaskIds = new Set(pendingTasks.map((task) => String(task.id || '')));
            for (const taskId of Array.from(scannedBarcodeByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    scannedBarcodeByTask.delete(taskId);
                }
            }
            for (const taskId of Array.from(stockReportItemsByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    stockReportItemsByTask.delete(taskId);
                }
            }
            for (const taskId of Array.from(noteDraftByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    noteDraftByTask.delete(taskId);
                }
            }
            for (const taskId of Array.from(photoProofByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    photoProofByTask.delete(taskId);
                }
            }

            if (!pendingTasks.length) {
                pendingContainer.innerHTML = '<div class="empty">Tidak ada tugas aktif saat ini.</div>';
                return;
            }

            const rackTasks = pendingTasks.filter((task) => isRackScanTask(task));
            const generalTasks = pendingTasks.filter((task) => !isRackScanTask(task));

            pendingContainer.innerHTML = [
                renderTaskGroupSection(
                    '📦 Tugas Cek Rak Rutin',
                    'Alur khusus: scan barcode rak, isi form barang menipis/habis jika ada, lalu selesai.',
                    rackTasks,
                    'Tidak ada tugas cek rak aktif saat ini.'
                ),
                renderTaskGroupSection(
                    '📝 Tugas Umum',
                    'Tugas operasional waiter di luar cek rak.',
                    generalTasks,
                    'Tidak ada tugas umum aktif saat ini.'
                ),
            ].join('');
        }

        function renderHistory(historyTasks) {
            if (!historyTasks.length) {
                historyBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #6b7280;">Belum ada riwayat.</td></tr>';
                return;
            }

            historyBody.innerHTML = historyTasks.map((task) => {
                let statusText = escapeHtml(String(task.status || '-').toUpperCase());
                if (task.status === 'done') {
                    statusText = '✅ Selesai';
                } else if (task.status === 'overdue') {
                    statusText = '❌ Tidak Selesai';
                }

                return `<tr>
                    <td>
                        <strong>${escapeHtml(task.title || '-')}</strong>
                        ${task.description ? `<div style="font-size: 12px; color: #6b7280;">${escapeHtml(task.description)}</div>` : ''}
                    </td>
                    <td>${statusText}</td>
                    <td>${escapeHtml(task.completed_note || '-')}</td>
                    <td>${isRackScanTask(task)
                        ? (task.completed_scanned_barcode
                            ? `<span style="color:#166534;">✅ ${escapeHtml(task.completed_scanned_barcode)}</span>`
                            : '<span style="color:#9a3412;">(wajib scan)</span>')
                        : '-'}</td>
                    <td>${isRackScanTask(task)
                        ? (task.completed_no_out_of_stock
                            ? '<span style="color:#166534;">✅ Tidak ada barang habis</span>'
                            : (task.completed_stock_report
                                ? `<span style="color:#9a3412;">⚠️ ${escapeHtml(task.completed_stock_report)}</span>`
                                : '<span style="color:#9ca3af;">-</span>'))
                        : '-'}</td>
                    <td>${task.completed_photo_proof_url
                        ? `<button type="button" class="btn-photo-view js-photo-view" data-task-id="${escapeAttr(task.id)}">📷 Lihat Foto</button>`
                        : (task.requires_photo_proof
                            ? '<span style="color:#9a3412; font-size:12px;">(wajib foto)</span>'
                            : '-')}</td>
                    <td>
                        Dibuat: ${escapeHtml(formatDateTime(task.created_at))}
                        <div style="font-size: 12px; color: #6b7280;">
                            Selesai: ${escapeHtml(formatDateTime(task.completed_at))}
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function renderAllTasks() {
            const normalized = waiterTasks
                .map(normalizeTask)
                .filter((task) => task.assigned_waiter_id === waiterId && task.id !== '');

            const pendingTasks = normalized
                .filter((task) => task.status === 'pending')
                .sort((a, b) => b.created_at - a.created_at);

            const historyTasks = normalized
                .filter((task) => task.status !== 'pending')
                .sort((a, b) => {
                    const bScore = b.completed_at || b.created_at;
                    const aScore = a.completed_at || a.created_at;
                    return bScore - aScore;
                });

            renderPending(pendingTasks);
            renderHistory(historyTasks);
        }

        function hydrateTasksFromPayload(payload) {
            waiterTasks = [
                ...(Array.isArray(payload?.pending_tasks) ? payload.pending_tasks : []),
                ...(Array.isArray(payload?.task_history) ? payload.task_history : []),
            ];

            if (isPendingFormInputActive()) {
                pendingRenderDeferred = true;
                hydrateActivityFromPayload(payload);
                return;
            }

            pendingRenderDeferred = false;
            renderAllTasks();
            hydrateActivityFromPayload(payload);
        }

        async function submitActivityReport(activityText) {
            if (activitySubmitBtn) {
                activitySubmitBtn.disabled = true;
            }

            try {
                const response = await fetch(activityStoreUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        activity_text: activityText,
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Gagal menyimpan laporan kegiatan.');
                }

                showFlash('success', payload?.message || 'Laporan kegiatan berhasil disimpan.');
                hydrateActivityFromPayload(payload);

                if (activityTextEl) {
                    activityTextEl.value = '';
                }
            } catch (error) {
                showFlash('error', error?.message || 'Gagal menyimpan laporan kegiatan.');
            } finally {
                if (activitySubmitBtn) {
                    activitySubmitBtn.disabled = false;
                }
            }
        }

        async function syncDueTasks() {
            const now = Date.now();

            if (syncDueInFlight) {
                return;
            }

            if (now < syncDueCooldownUntil) {
                return;
            }

            if ((now - lastSyncDueAttemptAt) < MIN_SYNC_DUE_INTERVAL_MS) {
                return;
            }

            syncDueInFlight = true;
            lastSyncDueAttemptAt = now;

            try {
                const response = await fetch(syncDueUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    if (response.status === 429) {
                        const retryAfterMs = getRetryAfterMs(response, 60000);
                        const boostedBackoff = syncDueBackoffMs > 0
                            ? Math.min(syncDueBackoffMs * 2, 300000)
                            : retryAfterMs;
                        const backoffMs = Math.max(retryAfterMs, boostedBackoff) + Math.floor(Math.random() * 1000);
                        syncDueBackoffMs = backoffMs;
                        syncDueCooldownUntil = Date.now() + backoffMs;
                        console.warn(`Sync due throttled (429). Retry in ${Math.ceil(backoffMs / 1000)}s.`);
                        return;
                    }

                    throw new Error('Sinkronisasi tugas gagal.');
                }

                const payload = await response.json();
                hydrateTasksFromPayload(payload);
                syncDueCooldownUntil = 0;
                syncDueBackoffMs = 0;
            } catch (error) {
                console.log('Sync due tasks failed', error);
            } finally {
                syncDueInFlight = false;
            }
        }

        async function pollTasks() {
            const now = Date.now();

            if (pollInFlight) {
                return;
            }

            if (now < pollCooldownUntil) {
                return;
            }

            pollInFlight = true;

            try {
                const response = await fetch(pollUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    if (response.status === 429) {
                        const retryAfterMs = getRetryAfterMs(response, 15000);
                        const boostedBackoff = pollBackoffMs > 0
                            ? Math.min(pollBackoffMs * 2, 120000)
                            : retryAfterMs;
                        const backoffMs = Math.max(retryAfterMs, boostedBackoff) + Math.floor(Math.random() * 500);
                        pollBackoffMs = backoffMs;
                        pollCooldownUntil = Date.now() + backoffMs;
                        console.warn(`Poll throttled (429). Retry in ${Math.ceil(backoffMs / 1000)}s.`);
                        return;
                    }

                    throw new Error('Polling tugas gagal.');
                }

                const payload = await response.json();
                hydrateTasksFromPayload(payload);
                pollCooldownUntil = 0;
                pollBackoffMs = 0;
            } catch (error) {
                console.log('Poll tasks failed', error);
            } finally {
                pollInFlight = false;
            }
        }

        async function completeTask(taskId, note, submitButton, stockReportItems, photoProofDataUrl) {
            submitButton.disabled = true;

            const scannedBarcode = String(scannedBarcodeByTask.get(taskId) || '');

            try {
                const completeUrl = completeUrlTemplate.replace('__TASK_ID__', encodeURIComponent(taskId));
                const response = await fetch(completeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        note,
                        scanned_barcode: scannedBarcode,
                        stock_report_items: stockReportItems,
                        photo_proof_data_url: photoProofDataUrl,
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Gagal memverifikasi tugas.');
                }

                scannedBarcodeByTask.delete(taskId);
                stockReportItemsByTask.delete(taskId);
                noteDraftByTask.delete(taskId);
                photoProofByTask.delete(taskId);
                showFlash('success', payload?.message || 'Tugas berhasil diverifikasi sebagai selesai.');
            } catch (error) {
                showFlash('error', error?.message || 'Gagal memverifikasi tugas.');
            } finally {
                submitButton.disabled = false;
            }
        }

        async function stopScannerIfRunning() {
            if (scannerInstance && scannerRunning) {
                try {
                    await scannerInstance.stop();
                } catch (error) {
                    console.log('stop scanner failed', error);
                }
                scannerRunning = false;
            }
        }

        async function closeScannerModal() {
            await stopScannerIfRunning();
            scannerModalEl.style.display = 'none';
            scannerModalEl.setAttribute('aria-hidden', 'true');
            activeScannerTaskId = '';
            activeScannerTaskLabel = '';
            activeScannerExpectedBarcode = '';
        }

        async function startScannerForTask(taskId, taskLabel, rackName, expectedBarcode) {
            activeScannerTaskId = taskId;
            activeScannerTaskLabel = taskLabel;
            activeScannerExpectedBarcode = normalizeBarcodeValue(expectedBarcode);

            if (activeScannerExpectedBarcode === '') {
                showFlash('error', 'Barcode rak target belum terkonfigurasi pada task ini. Hubungi supervisor.');
                activeScannerTaskId = '';
                activeScannerTaskLabel = '';
                activeScannerExpectedBarcode = '';
                return;
            }

            scannerTaskMetaEl.textContent = `Task: ${taskLabel} | Rak: ${rackName} | Target: ${activeScannerExpectedBarcode}`;
            scannerFeedbackEl.textContent = 'Arahkan kamera ke barcode rak sampai terbaca.';
            scannerModalEl.style.display = 'flex';
            scannerModalEl.setAttribute('aria-hidden', 'false');

            if (typeof Html5Qrcode === 'undefined') {
                scannerFeedbackEl.textContent = 'Library scanner belum termuat. Refresh halaman lalu coba lagi.';
                return;
            }

            if (!scannerInstance) {
                scannerInstance = new Html5Qrcode(scannerReaderElId);
            }

            await stopScannerIfRunning();

            try {
                const formats = typeof Html5QrcodeSupportedFormats !== 'undefined'
                    ? [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.QR_CODE,
                    ]
                    : undefined;

                await scannerInstance.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 320, height: 140 },
                        ...(formats ? { formatsToSupport: formats } : {}),
                    },
                    async (decodedText) => {
                        if (!activeScannerTaskId) return;

                        const cleanBarcode = normalizeBarcodeValue(decodedText);
                        if (cleanBarcode === '') {
                            return;
                        }

                        if (cleanBarcode !== activeScannerExpectedBarcode) {
                            scannerFeedbackEl.textContent = `❌ Barcode tidak cocok. Target ${activeScannerExpectedBarcode}, terbaca ${cleanBarcode}. Scan ulang rak yang benar.`;
                            return;
                        }

                        scannedBarcodeByTask.set(activeScannerTaskId, cleanBarcode);
                        scannerFeedbackEl.textContent = `✅ Barcode cocok: ${cleanBarcode}`;
                        renderAllTasks();
                        await closeScannerModal();
                    },
                    () => {
                        // ignore per-frame decode errors
                    }
                );

                scannerRunning = true;
            } catch (error) {
                scannerFeedbackEl.textContent = `Gagal menyalakan kamera: ${error?.message || 'Unknown error'}`;
            }
        }

        pendingContainer.addEventListener('submit', async (event) => {
            const form = event.target.closest('.js-complete-form');
            if (!form) {
                return;
            }

            event.preventDefault();

            const taskId = String(form.getAttribute('data-task-id') || '');
            if (!taskId) {
                showFlash('error', 'Task ID tidak valid.');
                return;
            }

            const noteInput = form.querySelector('input[name="note"]');
            const submitButton = form.querySelector('button[type="submit"]');
            const stockReportInput = form.closest('.card')?.querySelector(`textarea.js-stock-report[data-task-id="${taskId}"]`);
            const note = noteInput ? noteInput.value : '';
            const stockReportItems = stockReportInput ? String(stockReportInput.value || '').trim() : '';
            const currentTask = waiterTasks.find((task) => String(task?.id || '') === taskId);
            const requiresPhotoProof = Boolean(currentTask?.requires_photo_proof);
            const photoProofDataUrl = String(photoProofByTask.get(taskId)?.dataUrl || '');
            const expectedBarcode = normalizeBarcodeValue(currentTask?.rack_barcode_value || '');
            const scannedBarcode = normalizeBarcodeValue(scannedBarcodeByTask.get(taskId) || '');

            if (isRackScanTask(currentTask) && expectedBarcode === '') {
                showFlash('error', 'Barcode rak target pada task ini belum terdaftar. Hubungi supervisor.');
                return;
            }

            if (isRackScanTask(currentTask) && !String(scannedBarcodeByTask.get(taskId) || '').trim()) {
                showFlash('error', 'Task cek rak wajib scan barcode rak terlebih dahulu.');
                return;
            }

            if (isRackScanTask(currentTask) && scannedBarcode !== expectedBarcode) {
                showFlash('error', `Barcode tidak sesuai task. Target ${expectedBarcode}, yang ter-scan ${scannedBarcode || '-'}.`);
                return;
            }

            if (requiresPhotoProof && photoProofDataUrl === '') {
                showFlash('error', 'Task ini wajib foto bukti sebelum verifikasi selesai.');
                return;
            }

            await completeTask(taskId, note, submitButton, stockReportItems, photoProofDataUrl);
            await pollTasks();
        });

        pendingContainer.addEventListener('input', (event) => {
            const reportField = event.target.closest('.js-stock-report');
            if (reportField) {
                const taskId = String(reportField.getAttribute('data-task-id') || '');
                if (!taskId) {
                    return;
                }

                stockReportItemsByTask.set(taskId, String(reportField.value || ''));
                return;
            }

            const noteField = event.target.closest('.js-complete-form input[name="note"]');
            if (!noteField) {
                return;
            }

            const taskId = String(noteField.closest('.js-complete-form')?.getAttribute('data-task-id') || '');
            if (!taskId) {
                return;
            }

            noteDraftByTask.set(taskId, String(noteField.value || ''));
        });

        pendingContainer.addEventListener('change', async (event) => {
            const photoInput = event.target.closest('.js-photo-proof');
            if (!photoInput) {
                return;
            }

            const taskId = String(photoInput.getAttribute('data-task-id') || '');
            if (!taskId) {
                return;
            }

            const selectedFile = photoInput.files && photoInput.files.length > 0
                ? photoInput.files[0]
                : null;

            if (!selectedFile) {
                photoProofByTask.delete(taskId);
                renderAllTasks();
                return;
            }

            try {
                const compressed = await compressPhotoProofFile(selectedFile);
                photoProofByTask.set(taskId, compressed);
                showFlash('success', `Foto bukti siap dikirim (${formatBytes(compressed.sizeBytes)}).`);
            } catch (error) {
                photoProofByTask.delete(taskId);
                showFlash('error', error?.message || 'Gagal memproses foto bukti.');
            }

            renderAllTasks();
        });

        pendingContainer.addEventListener('focusout', () => {
            setTimeout(() => {
                flushDeferredPendingRender();
            }, 0);
        });

        pendingContainer.addEventListener('click', async (event) => {
            const photoClearBtn = event.target.closest('.js-photo-proof-clear');
            if (photoClearBtn) {
                const taskId = String(photoClearBtn.getAttribute('data-task-id') || '');
                if (!taskId) {
                    return;
                }

                photoProofByTask.delete(taskId);
                renderAllTasks();
                showFlash('success', 'Foto bukti dihapus dari draft task ini.');
                return;
            }

            const btn = event.target.closest('.js-open-scanner');
            if (!btn) return;

            const taskId = String(btn.getAttribute('data-task-id') || '');
            const taskLabel = String(btn.getAttribute('data-task-label') || 'Task');
            const rackName = String(btn.getAttribute('data-rack-name') || '-');
            const rackBarcode = String(btn.getAttribute('data-rack-barcode') || '');
            if (!taskId) return;

            await startScannerForTask(taskId, taskLabel, rackName, rackBarcode);
        });

        historyBody.addEventListener('click', (event) => {
            const photoViewBtn = event.target.closest('.js-photo-view');
            if (!photoViewBtn) {
                return;
            }

            const taskId = String(photoViewBtn.getAttribute('data-task-id') || '');
            if (!taskId) {
                return;
            }

            openPhotoPreviewForTask(taskId);
        });

        scannerCloseBtn.addEventListener('click', async () => {
            await closeScannerModal();
        });

        scannerModalEl.addEventListener('click', async (event) => {
            if (event.target === scannerModalEl) {
                await closeScannerModal();
            }
        });

        photoPreviewCloseBtn?.addEventListener('click', () => {
            closePhotoPreviewModal();
        });

        photoPreviewModalEl?.addEventListener('click', (event) => {
            if (event.target === photoPreviewModalEl) {
                closePhotoPreviewModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && photoPreviewModalEl?.style.display === 'flex') {
                closePhotoPreviewModal();
            }
        });

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetTab = String(button.getAttribute('data-tab') || 'tasks');
                setActiveTab(targetTab);
            });
        });

        activityFormEl.addEventListener('submit', async (event) => {
            event.preventDefault();

            const activityText = String(activityTextEl.value || '').trim();
            if (activityText === '') {
                showFlash('error', 'Isi laporan kegiatan dulu sebelum disimpan.');
                return;
            }

            await submitActivityReport(activityText);
        });

        window.addEventListener('beforeunload', () => {
            if (scannerInstance && scannerRunning) {
                scannerInstance.stop().catch(() => {});
            }
        });

        setActiveTab('tasks');
        renderAllTasks();
        renderActivityReports();
        syncDueTasks();
        setInterval(pollTasks, 5000);
        setInterval(syncDueTasks, 60000);
    </script>
</body>

</html>
