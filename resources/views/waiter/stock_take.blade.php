<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ambil Stok Gudang</title>
    <style>
        /* ─── Design tokens ─── */
        :root {
            --c-bg: #f3f6fb;
            --c-card: #fff;
            --c-text: #1f2937;
            --c-muted: #64748b;
            --c-border: #d1d5db;
            --c-border-light: #e2e8f0;
            --c-primary: #2563eb;
            --c-primary-dark: #1d4ed8;
            --c-success: #059669;
            --c-success-bg: #ecfdf5;
            --c-success-border: #a7f3d0;
            --c-success-text: #065f46;
            --c-danger: #b91c1c;
            --c-danger-bg: #fef2f2;
            --c-danger-border: #fecaca;
            --c-danger-text: #991b1b;
            --c-filled-bg: #f0fdf4;
            --c-filled-border: #bbf7d0;
            --c-warn: #d97706;
            --c-warn-bg: #fffbeb;
            --c-warn-border: #fde68a;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-xs: 6px;
            --shadow: 0 8px 24px rgba(15,23,42,0.08);
            --shadow-lg: 0 12px 28px rgba(15,23,42,0.18);
            --bar-h: 68px;
        }

        /* ─── Reset / Base ─── */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: var(--c-bg);
            color: var(--c-text);
            font-size: 15px;
            line-height: 1.5;
        }
        .wrap { max-width: 980px; margin: 0 auto; padding: 14px 12px calc(var(--bar-h) + 16px); }

        /* ─── Card ─── */
        .card { background: var(--c-card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; margin-bottom: 12px; }

        /* ─── Header (desktop) ─── */
        .head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .title { margin: 0; font-size: 19px; font-weight: 700; }
        .muted { color: var(--c-muted); font-size: 13px; margin-top: 3px; }

        /* ─── Mobile sticky top bar ─── */
        .mobile-header {
            display: none;
            position: sticky; top: 0; z-index: 40;
            background: var(--c-card);
            border-bottom: 1px solid var(--c-border-light);
            box-shadow: 0 2px 8px rgba(15,23,42,.07);
            height: 52px; min-height: 52px;
            padding: 0 10px;
            align-items: center; gap: 8px;
        }
        .mobile-header .mh-back {
            flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px;
            border-radius: var(--radius-xs);
            border: 1.5px solid var(--c-border);
            background: var(--c-card); color: var(--c-text);
            text-decoration: none; font-size: 18px; line-height: 1;
            transition: background .15s;
            -webkit-tap-highlight-color: transparent;
        }
        .mobile-header .mh-back:active { background: #f1f5f9; }
        .mobile-header .mh-title {
            flex: 1; min-width: 0;
            font-size: 15px; font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .mobile-header .mh-waiter {
            flex-shrink: 0;
            font-size: 11px; color: var(--c-muted);
            background: #f1f5f9; border-radius: 20px;
            padding: 3px 8px; max-width: 90px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ─── Scan card collapsed chip bar ─── */
        .scan-chip-bar {
            display: none;
            align-items: center; gap: 10px;
            background: #eff6ff; border: 1.5px solid #bfdbfe;
            border-radius: var(--radius-sm);
            padding: 9px 12px;
        }
        .scan-chip-bar .scb-icon { font-size: 16px; flex-shrink: 0; }
        .scan-chip-bar .scb-info { flex: 1; min-width: 0; }
        .scan-chip-bar .scb-name { font-weight: 700; font-size: 13px; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .scan-chip-bar .scb-code { font-size: 11px; color: #3b5ab0; font-family: 'Courier New', monospace; }
        .scan-chip-bar .scb-change {
            flex-shrink: 0;
            font-size: 12px; font-weight: 600; color: var(--c-primary);
            background: none; border: 1.5px solid var(--c-primary);
            border-radius: var(--radius-xs); padding: 5px 10px;
            cursor: pointer; font-family: inherit;
            transition: background .15s, color .15s;
            -webkit-tap-highlight-color: transparent;
        }
        .scan-chip-bar .scb-change:active { background: var(--c-primary); color: #fff; }

        /* ─── Scan card hint (before rack loaded) ─── */
        .scan-hint {
            font-size: 12px; color: var(--c-muted);
            margin-bottom: 10px; padding-bottom: 10px;
            border-bottom: 1px solid var(--c-border-light);
        }

        /* ─── Buttons ─── */
        .btn {
            border: none; border-radius: var(--radius-sm);
            padding: 11px 15px; font-weight: 700; cursor: pointer;
            font-size: 14px; min-height: 44px; min-width: 44px;
            display: inline-flex; align-items: center; gap: 6px;
            transition: opacity .15s, background .15s, transform .1s;
            -webkit-tap-highlight-color: transparent;
        }
        .btn:active { transform: scale(.97); }
        .btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
        .btn-primary { background: var(--c-primary); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--c-primary-dark); }
        .btn-soft { background: var(--c-card); color: #334155; border: 1.5px solid #cbd5e1; text-decoration: none; }
        .btn-soft:hover:not(:disabled) { background: #f8fafc; }
        .btn-success { background: var(--c-success); color: #fff; }
        .btn-success:hover:not(:disabled) { background: #047857; }
        .btn-danger { background: var(--c-danger); color: #fff; }
        .btn-icon { padding: 0; width: 36px; height: 36px; border-radius: var(--radius-xs); border: 1.5px solid var(--c-border); background: var(--c-card); color: var(--c-text); cursor: pointer; font-size: 18px; display: inline-flex; align-items: center; justify-content: center; transition: background .15s, transform .1s; -webkit-tap-highlight-color: transparent; }
        .btn-icon:active { transform: scale(.92); background: #f1f5f9; }

        /* ─── Input ─── */
        .input, textarea {
            width: 100%; border: 1.5px solid var(--c-border);
            border-radius: var(--radius-sm); padding: 11px 12px;
            font-size: 15px; color: var(--c-text);
            background: var(--c-card); transition: border-color .15s;
            font-family: inherit;
        }
        .input:focus, textarea:focus { outline: none; border-color: var(--c-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }

        /* ─── Barcode row ─── */
        .barcode-row { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: end; }

        /* ─── Rack meta chip ─── */
        .rack-meta {
            background: #eff6ff; border: 1.5px solid #bfdbfe;
            color: #1e3a8a; border-radius: var(--radius-sm);
            padding: 10px 12px; font-size: 13px;
            display: none; margin-top: 10px;
        }
        .rack-meta .chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 6px; }
        .chip { display: inline-flex; align-items: center; gap: 4px; background: #dbeafe; border-radius: 20px; padding: 3px 10px; font-size: 12px; font-weight: 600; color: #1e40af; }
        .chip code { font-family: 'Courier New', monospace; font-size: 12px; }

        /* ─── Feedback ─── */
        .feedback { margin-top: 10px; padding: 11px 13px; border-radius: var(--radius-sm); font-size: 13px; display: none; }
        .feedback.ok  { display: block; background: var(--c-success-bg); color: var(--c-success-text); border: 1px solid var(--c-success-border); }
        .feedback.err { display: block; background: var(--c-danger-bg);  color: var(--c-danger-text);  border: 1px solid var(--c-danger-border); }

        /* ─── Loading spinner ─── */
        .spinner {
            display: none; width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        .spinner.dark { border-color: rgba(37,99,235,.3); border-top-color: var(--c-primary); }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── Search bar (sticky) ─── */
        .search-bar {
            position: sticky; top: 0; z-index: 10;
            background: var(--c-card);
            border-bottom: 1px solid var(--c-border-light);
            padding: 10px 14px;
            margin: -16px -16px 12px;
        }
        .search-wrap { position: relative; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--c-muted); pointer-events: none; }
        .search-input { padding-left: 34px; padding-right: 36px; }
        .search-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--c-muted); font-size: 18px; line-height: 1; padding: 4px; display: none; }
        .search-hint { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; font-size: 12px; color: var(--c-muted); }
        .search-hint a { color: var(--c-primary); cursor: pointer; text-decoration: none; }

        /* ─── Product list header ─── */
        .products-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .products-header .section-title { font-weight: 700; font-size: 14px; }

        /* ─── Desktop table ─── */
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px 8px; border-bottom: 1px solid var(--c-border-light); font-size: 13px; text-align: left; vertical-align: middle; }
        .table th { color: var(--c-muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
        .table tbody tr.filled { background: var(--c-filled-bg); }
        .table tbody tr.filled td { border-bottom-color: var(--c-filled-border); }
        .table-view { display: block; }

        /* ─── Qty stepper ─── */
        .stepper { display: flex; align-items: center; gap: 4px; }
        .stepper .input { max-width: 68px; text-align: center; padding: 8px 4px; margin: 0; font-weight: 700; }
        .filled-mark { color: var(--c-success); display: none; }
        tr.filled .filled-mark, .prod-card.filled .filled-mark { display: inline; }

        /* ─── Out-of-stock / low-stock state ─── */
        .stock-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 700; line-height: 1;
            text-transform: uppercase; letter-spacing: .03em;
        }
        .stock-badge.out { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .stock-badge.low { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .stepper.disabled { opacity: .5; pointer-events: none; }
        .stepper.disabled .input { background: #f1f5f9; cursor: not-allowed; }
        .table tbody tr.out-of-stock td:first-child strong,
        .prod-card.out-of-stock .prod-name { color: var(--c-muted); text-decoration: line-through; }
        .prod-card.out-of-stock { background: #fafafa; }

        /* ─── Mobile card layout ─── */
        .card-list { display: none; flex-direction: column; gap: 10px; }
        .prod-card { border: 1.5px solid var(--c-border-light); border-radius: var(--radius-sm); padding: 12px; transition: border-color .15s, background .15s; }
        .prod-card.filled { background: var(--c-filled-bg); border-color: var(--c-filled-border); }
        .prod-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
        .prod-name { font-weight: 700; font-size: 14px; line-height: 1.3; }
        .prod-unit { font-size: 12px; color: var(--c-muted); margin-top: 2px; }
        .prod-stats { display: flex; gap: 12px; font-size: 12px; color: var(--c-muted); margin-bottom: 10px; }
        .prod-stat { display: flex; flex-direction: column; }
        .prod-stat span:first-child { font-weight: 600; font-size: 13px; color: var(--c-text); }
        .prod-card .stepper .input { max-width: 80px; font-size: 17px; }
        .empty-state { text-align: center; padding: 28px 16px; color: var(--c-muted); font-size: 14px; }
        .empty-state svg { margin-bottom: 8px; opacity: .4; }

        /* ─── Collapsible note ─── */
        .collapsible-toggle {
            width: 100%; text-align: left; background: none; border: none;
            border-top: 1px solid var(--c-border-light); padding: 10px 0 0;
            cursor: pointer; font-size: 13px; color: var(--c-muted);
            display: flex; align-items: center; gap: 6px; margin-top: 14px;
            font-family: inherit;
        }
        .collapsible-toggle .arrow { transition: transform .2s; display: inline-block; }
        .collapsible-toggle.open .arrow { transform: rotate(90deg); }
        .collapsible-body { display: none; padding-top: 10px; }
        .collapsible-body.open { display: block; }

        /* ─── Sticky bottom action bar ─── */
        .action-bar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            background: var(--c-card);
            border-top: 1px solid var(--c-border-light);
            box-shadow: 0 -4px 16px rgba(15,23,42,.1);
            padding: 10px 14px;
            display: none;
            align-items: center;
            gap: 8px;
        }
        .action-bar.visible { display: flex; }
        .action-bar .counter { font-size: 13px; color: var(--c-muted); white-space: nowrap; flex-shrink: 0; }
        .action-bar .counter strong { color: var(--c-success); }
        .action-bar .spacer { flex: 1; }
        .action-bar #btnSubmit { flex-shrink: 0; }

        /* ─── Confirm dialog ─── */
        .confirm-modal { position: fixed; inset: 0; background: rgba(15,23,42,.6); display: none; align-items: center; justify-content: center; z-index: 9998; padding: 16px; }
        .confirm-modal.open { display: flex; }
        .confirm-box { background: var(--c-card); border-radius: var(--radius); padding: 20px; width: min(460px, 100%); box-shadow: var(--shadow-lg); }
        .confirm-box h3 { margin: 0 0 12px; font-size: 16px; }
        .confirm-items { list-style: none; margin: 0 0 16px; padding: 0; max-height: 220px; overflow-y: auto; }
        .confirm-items li { padding: 7px 0; border-bottom: 1px solid var(--c-border-light); font-size: 13px; display: flex; justify-content: space-between; gap: 8px; }
        .confirm-items li:last-child { border-bottom: none; }
        .confirm-actions { display: flex; gap: 8px; justify-content: flex-end; }

        /* ─── Toast ─── */
        .toast-wrap { position: fixed; top: 16px; right: 16px; z-index: 10000; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        .toast { padding: 11px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; box-shadow: var(--shadow-lg); pointer-events: auto; animation: toastIn .25s ease; max-width: 300px; white-space: pre-line; }
        .toast.ok  { background: var(--c-success-bg); color: var(--c-success-text); border: 1px solid var(--c-success-border); }
        .toast.err { background: var(--c-danger-bg);  color: var(--c-danger-text);  border: 1px solid var(--c-danger-border); }
        .toast.info { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        @keyframes toastIn { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }

        /* ─── Scanner modal ─── */
        .scanner-modal { position: fixed; inset: 0; background: rgba(15,23,42,.6); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 16px; }
        .scanner-box { width: min(560px, 100%); background: #fff; border-radius: var(--radius); padding: 14px; box-shadow: var(--shadow-lg); }
        .scanner-reader { width: 100%; min-height: 280px; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; background: #0f172a; }
        .scanner-actions { display: flex; justify-content: flex-end; margin-top: 8px; }

        /* ─── Responsive breakpoint: ≤ 767px → card layout ─── */
        @media (max-width: 767px) {
            .wrap { padding-left: 10px; padding-right: 10px; padding-top: 8px; }
            .barcode-row { grid-template-columns: 1fr; }
            .table-view { display: none; }
            .card-list { display: flex; }
            .action-bar.visible { display: flex; }
            /* Compact cards on mobile */
            .card { padding: 12px 10px; margin-bottom: 10px; }
            /* Hide desktop header card, show slim sticky bar */
            .card.head { display: none; }
            .mobile-header { display: flex; }
            /* Search bar sticky offset accounts for 52px mobile header */
            .search-bar { top: 52px; }
        }
        @media (min-width: 768px) {
            .action-bar.visible { display: flex; }
            .wrap { padding-bottom: calc(var(--bar-h) + 20px); }
            .mobile-header { display: none !important; }
            .scan-hint { display: none; }
        }
    </style>
</head>
<body>
@include('partials.firebase-rtdb-client')
<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- Mobile sticky top bar (≤767px only) -->
<header class="mobile-header" aria-label="Navigasi halaman">
    <a href="{{ route('waiter.tasks') }}" class="mh-back" aria-label="Kembali ke Portal">&#8592;</a>
    <span class="mh-title">Ambil Stok Gudang</span>
    <span class="mh-waiter">{{ $waiterName }}</span>
</header>

<div class="wrap">
    <!-- Header (desktop) -->
    <div class="card head">
        <div>
            <h1 class="title">Ambil Stok Gudang</h1>
            <div class="muted">Halo, {{ $waiterName }}. Scan rak gudang, pilih barang, lalu isi qty yang diambil.</div>
        </div>
        <a href="{{ route('waiter.tasks') }}" class="btn btn-soft">← Kembali ke Portal</a>
    </div>

    <!-- Scanner / barcode card -->
    <div class="card">
        <!-- Scan hint: visible on mobile before rack loads, moves greeting out of header -->
        <div class="scan-hint" id="scanHint">Scan rak gudang, pilih barang, lalu isi qty yang diambil.</div>
        <!-- Collapsed chip bar: shown after successful rack load -->
        <div class="scan-chip-bar" id="scanCardCollapsed">
            <span class="scb-icon">&#9783;</span>
            <div class="scb-info">
                <div class="scb-name" id="scbName">-</div>
                <div class="scb-code" id="scbCode">-</div>
            </div>
            <button class="scb-change" id="btnChangeRack" type="button">Ganti rak</button>
        </div>
        <!-- Full scan controls: shown before rack loads -->
        <div id="scanCardFull">
            <div class="barcode-row">
                <div>
                    <label for="rackBarcode" style="font-weight:600; font-size:13px; display:block; margin-bottom:5px;">QR Code Rak Storage</label>
                    <input id="rackBarcode" class="input" placeholder="Scan QR rak atau isi kode rak">
                </div>
                <button id="btnScanQr" class="btn btn-soft" type="button">&#128247; Scan QR</button>
                <button id="btnLoadRack" class="btn btn-primary" type="button">
                    <span id="btnLoadSpinner" class="spinner"></span>
                    Muat Rak
                </button>
            </div>
        </div>
        <div id="rackMeta" class="rack-meta"></div>
        <div id="feedback" class="feedback"></div>
    </div>

    <!-- Products card -->
    <div class="card" id="productsCard" style="display:none;">
        <!-- Sticky search bar -->
        <div class="search-bar">
            <div class="search-wrap">
                <svg class="search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="8.5" cy="8.5" r="5.5"/><line x1="13.5" y1="13.5" x2="18" y2="18"/>
                </svg>
                <input id="searchProduct" class="input search-input" type="search" placeholder="Cari produk atau satuan...">
                <button id="btnClearSearch" class="search-clear" type="button" title="Hapus pencarian">×</button>
            </div>
            <div class="search-hint">
                <span id="searchCounter"></span>
                <a id="btnBersihkanFilter" style="display:none;" tabindex="0">Bersihkan filter</a>
            </div>
        </div>

        <!-- Desktop: table -->
        <div class="table-view">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Stok Sekarang</th>
                        <th>Min</th>
                        <th>Qty Ambil</th>
                    </tr>
                </thead>
                <tbody id="productsBody"></tbody>
            </table>
            <div id="tableEmpty" class="empty-state" style="display:none;">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <div>Tidak ada produk yang cocok dengan pencarian.</div>
            </div>
        </div>

        <!-- Mobile: card list -->
        <div class="card-list" id="cardList"></div>
        <div id="cardEmpty" class="empty-state" style="display:none;">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <div>Tidak ada produk yang cocok dengan pencarian.</div>
        </div>

        <!-- Collapsible catatan -->
        <button class="collapsible-toggle" id="toggleNote" type="button">
            <span class="arrow">▶</span>
            Catatan (opsional)
        </button>
        <div class="collapsible-body" id="noteBody">
            <textarea id="stockTakeNote" rows="2" class="input" style="width:100%; margin-top:6px;" placeholder="Contoh: ambil untuk refill display area depan"></textarea>
        </div>
    </div>
</div>

<!-- Sticky bottom action bar -->
<div class="action-bar" id="actionBar">
    <div class="counter" id="filledCounter"><strong>0</strong> produk diambil</div>
    <div class="spacer"></div>
    <button id="btnReset" class="btn btn-soft" type="button" style="min-width:auto; padding:10px 12px; font-size:13px;">Reset Qty</button>
    <button id="btnSubmit" type="button" class="btn btn-success" disabled>Simpan Pengambilan</button>
</div>

<!-- Scanner modal -->
<div id="scannerModal" class="scanner-modal" aria-hidden="true">
    <div class="scanner-box">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:8px;">
            <strong>📷 Scan QR Code Rak</strong>
            <button id="btnCloseScanner" type="button" class="btn" style="background:#ef4444;color:#fff;padding:6px 10px;">Tutup</button>
        </div>
        <div id="scannerReader" class="scanner-reader"></div>
        <div id="scannerFeedback" class="muted" style="margin-top:8px;">Arahkan kamera ke QR code rak sampai terbaca.</div>
        <div class="scanner-actions">
            <button id="btnManualEntry" type="button" class="btn btn-soft">Input Manual</button>
        </div>
    </div>
</div>

<!-- Confirm submit modal -->
<div id="confirmModal" class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="confirm-box">
        <h3 id="confirmTitle">Konfirmasi Pengambilan</h3>
        <ul class="confirm-items" id="confirmItems"></ul>
        <div style="font-size:12px; color:var(--c-muted); margin-bottom:14px;" id="confirmNote"></div>
        <div class="confirm-actions">
            <button id="btnConfirmCancel" class="btn btn-soft" type="button">Batal</button>
            <button id="btnConfirmOk" class="btn btn-success" type="button">Ya, Simpan</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const rackBarcode    = document.getElementById('rackBarcode');
    const btnLoadRack    = document.getElementById('btnLoadRack');
    const btnLoadSpinner = document.getElementById('btnLoadSpinner');
    const btnScanQr      = document.getElementById('btnScanQr');
    const rackMeta       = document.getElementById('rackMeta');
    const productsCard   = document.getElementById('productsCard');
    const productsBody   = document.getElementById('productsBody');
    const btnSubmit      = document.getElementById('btnSubmit');
    const feedback       = document.getElementById('feedback');
    const stockTakeNote  = document.getElementById('stockTakeNote');
    const scannerModal   = document.getElementById('scannerModal');
    const btnCloseScanner= document.getElementById('btnCloseScanner');
    const btnManualEntry = document.getElementById('btnManualEntry');
    const scannerFeedback= document.getElementById('scannerFeedback');
    const scannerReaderId= 'scannerReader';

    const actionBar        = document.getElementById('actionBar');
    const filledCounter    = document.getElementById('filledCounter');
    const btnReset         = document.getElementById('btnReset');
    const searchProduct    = document.getElementById('searchProduct');
    const btnClearSearch   = document.getElementById('btnClearSearch');
    const searchCounter    = document.getElementById('searchCounter');
    const btnBersihkanFilter = document.getElementById('btnBersihkanFilter');
    const tableEmpty       = document.getElementById('tableEmpty');
    const cardList         = document.getElementById('cardList');
    const cardEmpty        = document.getElementById('cardEmpty');
    const toggleNote       = document.getElementById('toggleNote');
    const noteBody         = document.getElementById('noteBody');
    const confirmModal     = document.getElementById('confirmModal');
    const confirmItems     = document.getElementById('confirmItems');
    const confirmNote      = document.getElementById('confirmNote');
    const btnConfirmCancel = document.getElementById('btnConfirmCancel');
    const btnConfirmOk     = document.getElementById('btnConfirmOk');
    const toastWrap        = document.getElementById('toastWrap');

    // Scan card collapse elements
    const scanCardFull      = document.getElementById('scanCardFull');
    const scanCardCollapsed = document.getElementById('scanCardCollapsed');
    const scanHint          = document.getElementById('scanHint');
    const scbName           = document.getElementById('scbName');
    const scbCode           = document.getElementById('scbCode');
    const btnChangeRack     = document.getElementById('btnChangeRack');

    let currentRack = null;
    let currentProducts = [];
    let scanner = null;
    let scannerRunning = false;
    let filterText = '';
    let stockTakeFormInstanceId = '';
    let _stockTakeRtdbRef = null;
    let _activeSessionRef = null;
    let _activeSessionOnDisconnect = null;
    let _activeSessionHeartbeat = null;

    // ─── Draft autosave helpers ───
    const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;
    function saveDraftLocal(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify({ data: data, saved_at: Date.now() }));
        } catch (e) { console.warn('[draft] save failed:', e); }
    }
    function loadDraftLocal(key) {
        try {
            const stored = localStorage.getItem(key);
            if (!stored) return null;
            const parsed = JSON.parse(stored);
            if (!parsed || typeof parsed !== 'object') return null;
            const savedAt = parseInt(parsed.saved_at || 0, 10);
            if (Date.now() - savedAt > DRAFT_TTL_MS) { localStorage.removeItem(key); return null; }
            return parsed.data || null;
        } catch (e) { return null; }
    }
    function clearDraftLocal(key) {
        try { localStorage.removeItem(key); } catch (e) {}
    }
    let _draftSaveTimer = null;
    function debounceSaveDraft(key, getDataFn) {
        clearTimeout(_draftSaveTimer);
        _draftSaveTimer = setTimeout(() => {
            const data = getDataFn();
            if (data && Object.keys(data).length > 0) saveDraftLocal(key, data);
        }, 500);
    }
    function getDraftKey() {
        const rackId = currentRack?.id || '';
        return `waiter_draft:stocktake:${rackId}`;
    }
    function collectDraftData() {
        const qtyData = {};
        getQtyInputs().forEach(inp => {
            const idx = Number(inp.dataset.index);
            const p = currentProducts[idx];
            if (!p) return;
            const val = parseFloat(inp.value || 0);
            if (val > 0) qtyData[p.id] = val;
        });
        return {
            qty: qtyData,
            note: stockTakeNote.value || '',
        };
    }
    function showDraftBanner(savedAt) {
        const mins = Math.round((Date.now() - savedAt) / 60000);
        const msg = mins < 1 ? 'baru saja' : `${mins} menit lalu`;
        showToast('ok', `📋 Draft dipulihkan (dari ${msg})`);
    }

    const resolveWaiterName = () => {
        if (typeof window.WAITER_NAME === 'string' && window.WAITER_NAME.trim()) return window.WAITER_NAME.trim();
        const bladeWaiterName = @json($waiterName ?? '');
        if (typeof bladeWaiterName === 'string' && bladeWaiterName.trim()) return bladeWaiterName.trim();
        return 'Waiter';
    };

    const getActiveSessionId = () => {
        if (window.activeSessionId) return window.activeSessionId;
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            window.activeSessionId = crypto.randomUUID();
            return window.activeSessionId;
        }
        window.activeSessionId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        return window.activeSessionId;
    };

    function stopActiveSessionHeartbeat() {
        if (_activeSessionHeartbeat) {
            clearInterval(_activeSessionHeartbeat);
            _activeSessionHeartbeat = null;
        }
    }

    function cleanupActiveSessionPresence() {
        stopActiveSessionHeartbeat();
        if (_activeSessionOnDisconnect) {
            try { _activeSessionOnDisconnect.cancel(); } catch (e) {}
            _activeSessionOnDisconnect = null;
        }
        if (_activeSessionRef) {
            try { _activeSessionRef.remove(); } catch (e) {}
            _activeSessionRef = null;
        }
    }

    function startActiveSessionPresence(rack) {
        if (!window.RTDB_READY || !window.firebaseDB || !rack?.id) return;
        try {
            cleanupActiveSessionPresence();
            const sessionId = getActiveSessionId();
            _activeSessionRef = window.firebaseDB.ref(`active_sessions/${rack.id}/${sessionId}`);
            _activeSessionOnDisconnect = _activeSessionRef.onDisconnect();
            _activeSessionOnDisconnect.remove();
            _activeSessionRef.set({
                waiter_id: @json((string)($waiterId ?? '')),
                waiter_name: resolveWaiterName(),
                rack_name: rack.name || '-',
                rack_code: rack.barcode_value || '-',
                started_at: window.firebase.database.ServerValue.TIMESTAMP,
                last_seen: window.firebase.database.ServerValue.TIMESTAMP,
            });
            _activeSessionHeartbeat = setInterval(() => {
                if (!_activeSessionRef) return;
                try {
                    _activeSessionRef.update({
                        last_seen: window.firebase.database.ServerValue.TIMESTAMP,
                    });
                } catch (e) {}
            }, 30000);
        } catch (e) {
            console.warn('[RTDB] active session presence failed:', e);
        }
    }

    const newFormInstanceId = () => {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
        return Math.random().toString(36).slice(2) + Date.now().toString(36);
    };

    // ─── Scan card collapse/expand ───
    function collapseScanCard(rack) {
        scbName.textContent = rack.name || '-';
        scbCode.textContent = rack.barcode_value || '-';
        scanCardFull.style.display = 'none';
        scanCardCollapsed.style.display = 'flex';
        if (scanHint) scanHint.style.display = 'none';
    }
    function expandScanCard() {
        detachRackLiveListener();
        cleanupActiveSessionPresence();
        scanCardFull.style.display = '';
        scanCardCollapsed.style.display = 'none';
        if (scanHint) scanHint.style.display = '';
        productsCard.style.display = 'none';
        actionBar.classList.remove('visible');
        stockTakeFormInstanceId = '';
        // Clear draft when user explicitly changes rack
        if (currentRack?.id) clearDraftLocal(`waiter_draft:stocktake:${currentRack.id}`);
    }

    function attachRackLiveListener(rackId) {
        if (!window.RTDB_READY || !window.firebaseDB) return;
        detachRackLiveListener();
        try {
            _stockTakeRtdbRef = window.firebaseDB.ref(`waiter_racks/${rackId}/products`);
            _stockTakeRtdbRef.on('value', (snap) => {
                const liveProducts = snap.val() || {};
                if (currentRack && Array.isArray(currentProducts)) {
                    currentProducts.forEach((p) => {
                        if (liveProducts[p.id] && typeof liveProducts[p.id].current_qty !== 'undefined') {
                            p.current_qty = parseInt(liveProducts[p.id].current_qty, 10) || 0;
                        }
                    });
                    renderProducts(currentProducts);
                }
            });
        } catch (e) {
            console.warn('[RTDB] rack listener failed:', e);
        }
    }

    function detachRackLiveListener() {
        if (_stockTakeRtdbRef) {
            try { _stockTakeRtdbRef.off(); } catch (e) {}
            _stockTakeRtdbRef = null;
        }
    }

    // ─── Toast ───
    function showToast(type, message, duration = 3500) {
        const t = document.createElement('div');
        t.className = 'toast ' + type;
        t.textContent = message;
        toastWrap.appendChild(t);
        setTimeout(() => t.remove(), duration);
    }

    // ─── Feedback banner (inside scanner card) ───
    function showFeedback(type, message) {
        feedback.className = 'feedback ' + type;
        feedback.textContent = message;
        if (type === 'ok' || type === 'err') {
            feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
    function clearFeedback() {
        feedback.className = 'feedback';
        feedback.textContent = '';
    }

    // ─── normalizeBarcode (unchanged logic) ───
    function normalizeBarcode(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const pushCandidate = (list, v) => {
            const normalized = String(v || '').trim().toUpperCase();
            if (!normalized) return;
            if (!list.includes(normalized)) list.push(normalized);
        };
        const candidates = [];
        pushCandidate(candidates, raw);
        try {
            const parsedJson = JSON.parse(raw);
            if (parsedJson && typeof parsedJson === 'object') {
                ['rack_barcode_value', 'barcode_value', 'rack_barcode', 'barcode', 'rack_code', 'code']
                    .forEach((k) => pushCandidate(candidates, parsedJson[k]));
            }
        } catch (_) {}
        try {
            const parsedUrl = new URL(raw);
            ['rack_barcode_value', 'barcode_value', 'rack_barcode', 'barcode', 'rack_code', 'code']
                .forEach((k) => pushCandidate(candidates, parsedUrl.searchParams.get(k)));
            const segments = parsedUrl.pathname.split('/').filter(Boolean);
            if (segments.length > 0) {
                pushCandidate(candidates, segments[segments.length - 1]);
            }
        } catch (_) {}
        return candidates[0] || '';
    }

    // ─── Scanner ───
    async function stopScanner() {
        if (!scanner || !scannerRunning) return;
        try { await scanner.stop(); } catch (e) { console.log('stop scanner failed', e); }
        finally { scannerRunning = false; }
    }

    async function openScanner() {
        scannerModal.style.display = 'flex';
        scannerModal.setAttribute('aria-hidden', 'false');
        scannerFeedback.textContent = 'Arahkan kamera ke QR code rak sampai terbaca.';
        if (typeof Html5Qrcode === 'undefined') {
            scannerFeedback.textContent = 'Library scanner belum termuat. Refresh halaman lalu coba lagi.';
            return;
        }
        if (!scanner) scanner = new Html5Qrcode(scannerReaderId);
        try {
            const formats = typeof Html5QrcodeSupportedFormats !== 'undefined'
                ? [Html5QrcodeSupportedFormats.QR_CODE] : undefined;
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 280, height: 280 }, formatsToSupport: formats },
                async (decodedText) => {
                    const qrValue = normalizeBarcode(decodedText);
                    if (!qrValue) return;
                    rackBarcode.value = qrValue;
                    scannerFeedback.textContent = `✅ QR terbaca: ${qrValue}`;
                    await stopScanner();
                    scannerModal.style.display = 'none';
                    scannerModal.setAttribute('aria-hidden', 'true');
                    await resolveRack();
                }
            );
            scannerRunning = true;
        } catch (error) {
            scannerFeedback.textContent = `Gagal menyalakan kamera: ${error?.message || 'Unknown error'}`;
        }
    }

    // ─── Qty / filled state helpers ───
    function getQtyInputs() {
        return Array.from(document.querySelectorAll('.js-take-qty'));
    }

    function updateFilledState(input) {
        const qty = parseFloat(input.value || 0);
        const idx = input.dataset.index;
        const isFilled = qty > 0;

        // Table row
        const tr = document.getElementById('tr-' + idx);
        if (tr) tr.classList.toggle('filled', isFilled);

        // Card
        const card = document.getElementById('pc-' + idx);
        if (card) card.classList.toggle('filled', isFilled);

        refreshFilledCounter();
    }

    function refreshFilledCounter() {
        const inputs = getQtyInputs();
        const filled = inputs.filter(i => parseFloat(i.value || 0) > 0);
        const count = filled.length;
        filledCounter.innerHTML = `<strong>${count}</strong> produk diambil`;
        btnSubmit.disabled = count === 0;
    }

    // ─── Search / filter ───
    function applyFilter() {
        const q = filterText.trim().toLowerCase();
        const total = currentProducts.length;
        let visible = 0;

        currentProducts.forEach((p, i) => {
            const match = !q ||
                (p.name || '').toLowerCase().includes(q) ||
                (p.unit || '').toLowerCase().includes(q);

            const tr = document.getElementById('tr-' + i);
            const card = document.getElementById('pc-' + i);
            if (tr) tr.style.display = match ? '' : 'none';
            if (card) card.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        searchCounter.textContent = q ? `${visible} dari ${total} produk` : `${total} produk`;
        btnClearSearch.style.display = q ? 'block' : 'none';
        btnBersihkanFilter.style.display = q ? 'inline' : 'none';
        tableEmpty.style.display = (visible === 0 && total > 0) ? 'block' : 'none';
        cardEmpty.style.display = (visible === 0 && total > 0) ? 'block' : 'none';
    }

    function clearFilter() {
        filterText = '';
        searchProduct.value = '';
        applyFilter();
    }

    // ─── Event listeners (attached once) ───
    let _stockTakeListenersAttached = false;
    function attachStockTakeListenersOnce() {
        if (_stockTakeListenersAttached) return;
        _stockTakeListenersAttached = true;
        [productsBody, cardList].forEach(container => {
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('.js-plus, .js-minus');
                if (!btn || btn.disabled) return;
                const idx = btn.dataset.index;
                const inputs = document.querySelectorAll(`.js-take-qty[data-index="${idx}"]`);
                inputs.forEach(inp => {
                    if (inp.disabled) return;
                    const maxAttr = parseInt(inp.getAttribute('max') || '', 10);
                    const ceiling = Number.isFinite(maxAttr) && maxAttr > 0 ? maxAttr : Number.POSITIVE_INFINITY;
                    let val = parseInt(inp.value || 0, 10);
                    if (btn.classList.contains('js-plus')) val = Math.min(ceiling, Math.max(0, val + 1));
                    else val = Math.max(0, val - 1);
                    inp.value = val;
                    updateFilledState(inp);
                    syncTwinInputs(idx, val);
                });
                debounceSaveDraft(getDraftKey(), collectDraftData);
            });
            container.addEventListener('input', (e) => {
                const inp = e.target.closest('.js-take-qty');
                if (!inp || inp.disabled) return;
                const idx = inp.dataset.index;
                const maxAttr = parseInt(inp.getAttribute('max') || '', 10);
                const ceiling = Number.isFinite(maxAttr) && maxAttr > 0 ? maxAttr : Number.POSITIVE_INFINITY;
                let val = Math.max(0, parseInt(inp.value || 0, 10));
                if (val > ceiling) {
                    val = ceiling;
                    showFeedback('err', `Qty maksimal ${ceiling} sesuai stok tersedia.`);
                }
                inp.value = val;
                updateFilledState(inp);
                syncTwinInputs(idx, val, inp);
                debounceSaveDraft(getDraftKey(), collectDraftData);
            });
        });
    }

    // ─── Render products ───
    function renderProducts(products) {
        productsBody.innerHTML = '';
        cardList.innerHTML = '';

        if (!Array.isArray(products) || products.length === 0) {
            productsBody.innerHTML = '<tr><td colspan="4" style="color:var(--c-muted); text-align:center; padding:20px;">Belum ada produk terpasang di rak ini.</td></tr>';
            return;
        }

        products.forEach((p, i) => {
            const name = p.name || '-';
            const unit = p.unit || 'pcs';
            const currentQty = (p.current_qty === null || p.current_qty === undefined) ? null : Number(p.current_qty);
            const minQty = Number(p.min_qty || 0);
            const isOutOfStock = currentQty !== null && currentQty <= 0;
            const isLowStock = !isOutOfStock && currentQty !== null && currentQty <= minQty && minQty > 0;

            const stockBadge = isOutOfStock
                ? '<span class="stock-badge out" title="Stok kosong, tambah stok rak dulu">🚫 Stok habis</span>'
                : isLowStock
                    ? `<span class="stock-badge low" title="Stok di bawah minimum">⚠ Stok menipis</span>`
                    : '';

            const stepperClass = isOutOfStock ? 'stepper disabled' : 'stepper';
            const inputAttrs = isOutOfStock
                ? `disabled aria-disabled="true" title="Stok kosong"`
                : `max="${currentQty !== null ? currentQty : ''}"`;
            const btnDisabled = isOutOfStock ? 'disabled aria-disabled="true"' : '';
            const stockDisplay = currentQty === null ? '-' : currentQty;
            const trClass = isOutOfStock ? 'out-of-stock' : '';
            const cardClass = isOutOfStock ? 'prod-card out-of-stock' : 'prod-card';

            // ── Desktop table row ──
            const tr = document.createElement('tr');
            tr.id = 'tr-' + i;
            if (trClass) tr.className = trClass;
            tr.innerHTML = `
                <td>
                    <span class="filled-mark">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;color:var(--c-success)"><polyline points="4 10 8 14 16 6"/></svg>
                    </span>
                    <strong>${name}</strong>
                    <div style="color:var(--c-muted); font-size:12px;">${unit}</div>
                    ${stockBadge ? `<div style="margin-top:4px;">${stockBadge}</div>` : ''}
                </td>
                <td>${stockDisplay}</td>
                <td>${minQty}</td>
                <td>
                    <div class="${stepperClass}">
                        <button type="button" class="btn-icon js-minus" data-index="${i}" aria-label="Kurangi" ${btnDisabled}>−</button>
                        <input type="number" min="0" step="1" value="0"
                            class="input js-take-qty" data-index="${i}"
                            inputmode="numeric" pattern="[0-9]*" ${inputAttrs}>
                        <button type="button" class="btn-icon js-plus" data-index="${i}" aria-label="Tambah" ${btnDisabled}>+</button>
                    </div>
                </td>
            `;
            productsBody.appendChild(tr);

            // ── Mobile card ──
            const card = document.createElement('div');
            card.className = cardClass;
            card.id = 'pc-' + i;
            card.innerHTML = `
                <div class="prod-card-head">
                    <div>
                        <div class="prod-name">${name}</div>
                        <div class="prod-unit">${unit}</div>
                        ${stockBadge ? `<div style="margin-top:6px;">${stockBadge}</div>` : ''}
                    </div>
                    <span class="filled-mark">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--c-success)"><polyline points="4 10 8 14 16 6"/></svg>
                    </span>
                </div>
                <div class="prod-stats">
                    <div class="prod-stat">
                        <span>${stockDisplay}</span>
                        <span>Stok sekarang</span>
                    </div>
                    <div class="prod-stat">
                        <span>${minQty}</span>
                        <span>Minimum</span>
                    </div>
                </div>
                <div class="${stepperClass}">
                    <button type="button" class="btn-icon js-minus" data-index="${i}" aria-label="Kurangi" ${btnDisabled}>−</button>
                    <input type="number" min="0" step="1" value="0"
                        class="input js-take-qty" data-index="${i}"
                        inputmode="numeric" pattern="[0-9]*" ${inputAttrs}>
                    <button type="button" class="btn-icon js-plus" data-index="${i}" aria-label="Tambah" ${btnDisabled}>+</button>
                </div>
            `;
            cardList.appendChild(card);
        });

        // Delegate stepper clicks on productsBody and cardList (attached ONCE outside renderProducts)
        attachStockTakeListenersOnce();

        applyFilter();
        refreshFilledCounter();

        // Restore draft after products rendered
        const draftKey = getDraftKey();
        const draft = loadDraftLocal(draftKey);
        if (draft && draft.qty && Object.keys(draft.qty).length > 0) {
            let restored = false;
            currentProducts.forEach((p, i) => {
                const savedQty = draft.qty[p.id];
                if (savedQty == null) return;
                document.querySelectorAll(`.js-take-qty[data-index="${i}"]`).forEach(inp => {
                    if (inp.disabled) return;
                    const maxAttr = parseInt(inp.getAttribute('max') || '', 10);
                    const ceiling = Number.isFinite(maxAttr) && maxAttr > 0 ? maxAttr : Number.POSITIVE_INFINITY;
                    inp.value = Math.min(ceiling, Math.max(0, savedQty));
                    updateFilledState(inp);
                    restored = true;
                });
            });
            if (draft.note) {
                stockTakeNote.value = draft.note;
            }
            if (restored) {
                const savedAt = (() => { try { return JSON.parse(localStorage.getItem(draftKey))?.saved_at || 0; } catch(e){ return 0; } })();
                showDraftBanner(savedAt || Date.now());
                refreshFilledCounter();
            }
        }

        // Attach debounced save on qty inputs (delegate via container events registered below)
    }

    // Sync table + card qty inputs for same product
    function syncTwinInputs(idx, val, originEl) {
        document.querySelectorAll(`.js-take-qty[data-index="${idx}"]`).forEach(inp => {
            if (inp === originEl) return;
            inp.value = val;
        });
    }

    // ─── resolveRack ───
    async function resolveRack() {
        const code = normalizeBarcode(rackBarcode.value);
        if (!code) { showFeedback('err', 'QR code rak wajib di-scan / diisi.'); return; }

        clearFeedback();
        btnLoadRack.disabled = true;
        btnLoadSpinner.style.display = 'block';
        showFeedback('ok', 'Memuat data rak...');

        try {
            const res = await fetch('{{ route('waiter.stock_take.resolve_rack') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ rack_barcode_value: code }),
            });

            let data = null;
            try { data = await res.json(); }
            catch (parseErr) {
                showFeedback('err', `Server tidak merespons JSON (HTTP ${res.status}). Coba refresh halaman.`);
                return;
            }

            if (!res.ok || !data || !data.success) {
                currentRack = null;
                currentProducts = [];
                productsCard.style.display = 'none';
                actionBar.classList.remove('visible');
                rackMeta.style.display = 'none';
                showFeedback('err', (data && data.message) ? data.message : `Rak tidak valid (HTTP ${res.status}).`);
                return;
            }

            currentRack = data.rack;
            currentProducts = Array.isArray(data.products) ? data.products : [];
            stockTakeFormInstanceId = newFormInstanceId();

            rackMeta.style.display = 'block';
            rackMeta.innerHTML = `
                <strong>${currentRack.name}</strong>
                <div class="chips">
                    <span class="chip" style="background: #dbeafe; color: #1e40af; border-color: #93c5fd;" title="Stok di rak ini diambil ke display rack">
                        🟦 Gudang Storage
                    </span>
                    <span class="chip">
                        <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6c0 4 6 10 6 10s6-6 6-10a6 6 0 00-6-6zm0 8a2 2 0 110-4 2 2 0 010 4z"/></svg>
                        ${currentRack.location || '-'}
                    </span>
                    <span class="chip">
                        <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h12a1 1 0 011 1v2a1 1 0 01-.293.707L13 11v6l-6-2V11L3.293 6.707A1 1 0 013 6V4a1 1 0 011-1z"/></svg>
                        <code>${currentRack.barcode_value}</code>
                    </span>
                </div>
            `;

            productsCard.style.display = 'block';
            actionBar.classList.add('visible');
            clearFilter();
            renderProducts(currentProducts);
            if (currentRack?.id) {
                attachRackLiveListener(currentRack.id);
            }
            startActiveSessionPresence(currentRack);

            // Collapse scan card → show chip bar, scroll products into view
            collapseScanCard(currentRack);
            setTimeout(() => productsCard.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);

            if (currentProducts.length === 0) {
                showFeedback('err', 'QR rak valid, tapi belum ada produk terpasang di rak ini. Silakan assign produk rak dulu di menu admin.');
                return;
            }
            showFeedback('ok', 'Rak berhasil dimuat. Isi qty yang diambil lalu simpan.');
        } catch (e) {
            showFeedback('err', `Gagal memuat data rak: ${e?.message || 'koneksi terputus'}.`);
        } finally {
            btnLoadRack.disabled = false;
            btnLoadSpinner.style.display = 'none';
        }
    }

    // ─── Reset qty ───
    function resetAllQty() {
        getQtyInputs().forEach(inp => { inp.value = 0; updateFilledState(inp); });
    }

    // ─── Confirm + submit ───
    function openConfirm() {
        const inputs = getQtyInputs();
        const uniqueMap = new Map();
        inputs.forEach(inp => {
            const idx = Number(inp.dataset.index);
            const p = currentProducts[idx];
            const qty = Math.max(0, Number(inp.value || 0));
            if (!p || qty <= 0) return;
            if (!uniqueMap.has(p.id)) uniqueMap.set(p.id, { product: p, qty });
            else uniqueMap.get(p.id).qty = qty; // last write wins (table+card sync)
        });

        if (uniqueMap.size === 0) {
            showFeedback('err', 'Isi minimal satu qty ambil yang lebih dari 0.');
            return;
        }

        confirmItems.innerHTML = '';
        uniqueMap.forEach(({ product: p, qty }) => {
            const li = document.createElement('li');
            li.innerHTML = `<span>${p.name || '-'} <span style="color:var(--c-muted);font-size:12px;">(${p.unit || 'pcs'})</span></span><strong>${qty}</strong>`;
            confirmItems.appendChild(li);
        });

        const note = stockTakeNote.value.trim();
        confirmNote.textContent = note ? `Catatan: ${note}` : '';
        confirmModal.classList.add('open');
    }

    async function doSubmit() {
        confirmModal.classList.remove('open');
        if (!currentRack) { showFeedback('err', 'Silakan scan/muat rak terlebih dulu.'); return; }

        const inputs = getQtyInputs();
        const uniqueMap = new Map();
        inputs.forEach(inp => {
            const idx = Number(inp.dataset.index);
            const p = currentProducts[idx];
            const qty = Math.max(0, Number(inp.value || 0));
            if (!p || qty <= 0) return;
            uniqueMap.set(p.id, { product_id: p.id, qty });
        });

        const items = Array.from(uniqueMap.values());
        if (!items.length) { showFeedback('err', 'Isi minimal satu qty ambil yang lebih dari 0.'); return; }

        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Menyimpan...';

        try {
            const payload = {
                rack_barcode_value: normalizeBarcode(currentRack.barcode_value),
                items: JSON.stringify(items),
                note: stockTakeNote.value || '',
                idempotency_key: `standalone-stocktake:${currentRack.id}:${stockTakeFormInstanceId}`,
            };
            const res = await fetch('{{ route('waiter.stock_take.submit') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.success) {
                showFeedback('err', data.message || 'Gagal menyimpan pengambilan stok.');
                showToast('err', data.message || 'Gagal menyimpan pengambilan stok.');
                return;
            }
            showFeedback('ok', data.message || 'Pengambilan stok berhasil disimpan.');
            showToast('ok', data.message || 'Pengambilan stok berhasil disimpan!');

            // F3: Tampilkan summary restock_request yg ter-create otomatis (transparency
            // ke waiter supaya ngerti kenapa setelah cek rak ada task baru muncul).
            const createdRestocks = Array.isArray(data.created_restock_requests) ? data.created_restock_requests : [];
            if (createdRestocks.length > 0) {
                const supplierItems = createdRestocks.filter(r => r && (r.source === 'auto_threshold_storage' || r.source === 'storage_rack_shortage'));
                const refillItems = createdRestocks.filter(r => r && (r.source === 'auto_threshold_display_storage_low' || r.source === 'display_rack_post_refill_short' || r.source === 'display_rack_low_storage_low'));

                const lines = [];
                if (refillItems.length > 0) {
                    lines.push(`🟡 ${refillItems.length} produk akan di-refill dari gudang ke display`);
                }
                if (supplierItems.length > 0) {
                    lines.push(`🔴 ${supplierItems.length} produk perlu di-PO ke supplier`);
                }
                if (lines.length > 0) {
                    setTimeout(() => {
                        showToast('info', 'Auto-restock dibuat:\n' + lines.join('\n'), 6000);
                    }, 800);
                }
            }

            clearDraftLocal(getDraftKey());
            stockTakeNote.value = '';
            await resolveRack();
        } catch (e) {
            showFeedback('err', 'Terjadi kendala saat menyimpan.');
            showToast('err', 'Terjadi kendala saat menyimpan.');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Simpan Pengambilan';
            refreshFilledCounter();
        }
    }

    // ─── Event listeners ───
    btnLoadRack.addEventListener('click', resolveRack);
    btnScanQr.addEventListener('click', openScanner);
    btnSubmit.addEventListener('click', openConfirm);

    btnChangeRack.addEventListener('click', () => {
        currentRack = null;
        currentProducts = [];
        expandScanCard();
        clearFeedback();
        rackBarcode.focus();
    });

    btnReset.addEventListener('click', () => {
        if (!currentRack) return;
        if (!confirm('Reset semua qty ke 0?')) return;
        resetAllQty();
    });

    searchProduct.addEventListener('input', (e) => {
        filterText = e.target.value;
        applyFilter();
    });
    btnClearSearch.addEventListener('click', clearFilter);
    btnBersihkanFilter.addEventListener('click', clearFilter);

    toggleNote.addEventListener('click', () => {
        toggleNote.classList.toggle('open');
        noteBody.classList.toggle('open');
    });

    stockTakeNote.addEventListener('input', () => {
        debounceSaveDraft(getDraftKey(), collectDraftData);
    });

    btnConfirmOk.addEventListener('click', doSubmit);
    btnConfirmCancel.addEventListener('click', () => confirmModal.classList.remove('open'));
    confirmModal.addEventListener('click', (e) => { if (e.target === confirmModal) confirmModal.classList.remove('open'); });

    btnCloseScanner.addEventListener('click', async () => {
        await stopScanner();
        scannerModal.style.display = 'none';
        scannerModal.setAttribute('aria-hidden', 'true');
    });
    btnManualEntry.addEventListener('click', async () => {
        await stopScanner();
        scannerModal.style.display = 'none';
        scannerModal.setAttribute('aria-hidden', 'true');
        rackBarcode.focus();
    });
    scannerModal.addEventListener('click', async (e) => {
        if (e.target === scannerModal) {
            await stopScanner();
            scannerModal.style.display = 'none';
            scannerModal.setAttribute('aria-hidden', 'true');
        }
    });

    rackBarcode.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); resolveRack(); }
    });

    window.addEventListener('beforeunload', () => {
        cleanupActiveSessionPresence();
        detachRackLiveListener();
        if (scanner && scannerRunning) scanner.stop().catch(() => {});
    });
})();
</script>
</body>
</html>
