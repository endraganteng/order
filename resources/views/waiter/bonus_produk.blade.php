<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Produk - {{ $waiterName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #1e293b;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        .header-top { display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 1.1rem; font-weight: 700; }
        .back-btn {
            color: white; text-decoration: none; font-size: 1.1rem;
            padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.15);
            transition: background 0.2s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.25); }
        .header-name { font-size: 0.8rem; opacity: 0.85; }

        /* Container */
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }

        /* Summary Card */
        .summary-card {
            background: white;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
        .summary-item .label { font-size: 11px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px; }
        .summary-item .value { font-size: 1.3rem; font-weight: 800; margin-top: 2px; }
        .summary-sub { font-size: 0.65rem; color: #94a3b8; margin-top: 2px; }
        .value-green { color: #059669; }
        .value-orange { color: #d97706; }
        .value-muted { color: #475569; }

        /* Hero summary - total poin */
        .summary-hero {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; margin: -16px -16px 14px -16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border-radius: 14px 14px 0 0;
        }
        .summary-hero-icon {
            font-size: 2.5rem; line-height: 1; flex-shrink: 0;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15));
        }
        .summary-hero-content { flex: 1; min-width: 0; }
        .summary-hero-label {
            font-size: 0.7rem; opacity: 0.9; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        .summary-hero-value {
            font-size: 1.8rem; font-weight: 800; line-height: 1.1;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .summary-hero-unit { font-size: 0.85rem; font-weight: 500; opacity: 0.85; }
        .summary-hero-pending {
            font-size: 0.72rem; opacity: 0.92; margin-top: 4px;
            background: rgba(255,255,255,0.18);
            padding: 2px 8px; border-radius: 10px;
            display: inline-block;
        }

        /* Tabs */
        .tab-bar {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .tab-btn {
            flex: 1; padding: 10px 8px; text-align: center;
            font-size: 0.78rem; font-weight: 600; color: #64748b;
            background: none; border: none; cursor: pointer;
            border-radius: 8px; transition: all 0.2s;
        }
        .tab-btn.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; box-shadow: 0 2px 8px rgba(102,126,234,0.3); }

        /* Section */
        .section-title { font-size: 0.82rem; font-weight: 700; color: #475569; margin-bottom: 10px; padding-left: 2px; }

        /* Product Card */
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #667eea;
            transition: transform 0.15s;
        }
        .product-card:active { transform: scale(0.98); }
        .product-card .info h4 { font-size: 0.88rem; color: #1e293b; font-weight: 600; margin-bottom: 3px; }
        .product-card .info .meta { font-size: 0.72rem; color: #94a3b8; }
        .points-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 5px 12px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; white-space: nowrap;
            box-shadow: 0 2px 6px rgba(102,126,234,0.3);
        }

        /* History Item */
        .history-item {
            background: white;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        }
        .history-item .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .history-item .product-name { font-weight: 600; font-size: 0.85rem; color: #1e293b; }
        .history-item .detail { font-size: 0.75rem; color: #64748b; line-height: 1.5; }

        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        /* Verify Card */
        .verify-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
            border-left: 4px solid #d97706;
        }
        .verify-card .waiter-name { font-weight: 700; font-size: 0.85rem; color: #1e293b; }
        .verify-card .claim-detail { font-size: 0.78rem; color: #475569; margin: 6px 0; }
        .verify-card .claim-points { font-weight: 700; color: #667eea; }
        .verify-card img { max-width: 100%; max-height: 180px; border-radius: 8px; margin: 8px 0; cursor: pointer; border: 1px solid #e2e8f0; }
        .verify-actions { display: flex; gap: 8px; margin-top: 10px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; border-radius: 10px; font-size: 0.85rem;
            font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; width: 100%; box-shadow: 0 3px 12px rgba(102,126,234,0.3); }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(102,126,234,0.4); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-sm { padding: 8px 14px; font-size: 0.78rem; border-radius: 8px; }
        .btn-approve { background: #059669; color: white; flex: 1; }
        .btn-approve:hover { background: #047857; }
        .btn-reject { background: #dc2626; color: white; flex: 1; }
        .btn-reject:hover { background: #b91c1c; }
        .btn-light { background: #f1f5f9; color: #475569; }

        /* Fixed Bottom Button */
        .claim-btn-container {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 12px 16px; background: white;
            box-shadow: 0 -4px 16px rgba(0,0,0,0.08); z-index: 50;
        }
        .claim-btn-container .btn { max-width: 600px; margin: 0 auto; }

        /* Modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.6); display:none; align-items:flex-end; justify-content:center; z-index:9999; backdrop-filter: blur(2px); }
        .modal-content { background:#fff; border-radius:20px 20px 0 0; padding:24px 20px; width:100%; max-width:600px; max-height:85vh; overflow-y:auto; box-shadow: 0 -8px 30px rgba(0,0,0,0.15); }
        .modal-handle { width: 40px; height: 4px; background: #cbd5e1; border-radius: 4px; margin: 0 auto 16px; }
        .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 16px; color: #1e293b; }

        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; transition: border-color 0.2s; background: #fafbfc; }
        .form-control:focus { outline: none; border-color: #667eea; background: white; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        select.form-control { appearance: auto; }

        /* Photo Upload */
        .photo-upload {
            border: 2px dashed #cbd5e1; border-radius: 12px;
            padding: 24px; text-align: center; cursor: pointer; transition: all 0.2s;
            background: #fafbfc;
        }
        .photo-upload:hover { border-color: #667eea; background: #f8faff; }
        .photo-upload.has-photo { border-color: #059669; background: #f0fdf4; border-style: solid; }
        .photo-upload .icon { font-size: 2rem; margin-bottom: 6px; }
        .photo-upload .text { font-size: 0.8rem; color: #64748b; }
        .photo-upload img { max-width: 100%; max-height: 150px; border-radius: 8px; margin-top: 10px; }

        /* Points Preview */
        .points-preview {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0; padding: 12px; border-radius: 10px;
            text-align: center; margin-bottom: 16px;
        }
        .points-preview .label { font-size: 0.75rem; color: #065f46; }
        .points-preview .value { font-size: 1.3rem; font-weight: 800; color: #059669; }

        /* Empty State */
        .empty-state { text-align: center; padding: 2.5rem 1rem; color: #94a3b8; }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 8px; }
        .empty-state p { font-size: 0.85rem; }

        /* Search Box */
        .search-box { margin-bottom: 14px; }
        .search-box input {
            width: 100%; padding: 11px 14px;
            background: white; border: 1px solid #e2e8f0;
            border-radius: 10px; font-size: 0.85rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .search-box input:focus {
            outline: none; border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }

        /* Campaign Group */
        .campaign-group {
            background: white; border-radius: 12px;
            margin-bottom: 12px; overflow: hidden;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        }
        .campaign-group summary {
            list-style: none; cursor: pointer;
            user-select: none;
        }
        .campaign-group summary::-webkit-details-marker { display: none; }
        .campaign-group-header {
            background: linear-gradient(135deg, #eff6ff 0%, #ede9fe 100%);
            padding: 12px 14px;
            border-bottom: 1px solid #e0e7ff;
            display: flex; justify-content: space-between; align-items: center;
            gap: 8px;
            transition: background 0.15s;
        }
        .campaign-group summary:hover .campaign-group-header {
            background: linear-gradient(135deg, #dbeafe 0%, #ddd6fe 100%);
        }
        .summary-main { flex: 1; min-width: 0; }
        .summary-toggle {
            font-size: 1.1rem; color: #6366f1; font-weight: 700;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }
        .campaign-group[open] .summary-toggle { transform: rotate(180deg); }
        .campaign-title {
            font-size: 0.86rem; font-weight: 700; color: #4338ca;
        }
        .campaign-end {
            font-size: 0.7rem; color: #64748b; margin-top: 2px;
        }
        .product-count {
            display: inline-block;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca; font-weight: 600;
            padding: 1px 6px; border-radius: 8px;
            font-size: 0.65rem;
        }
        .campaign-group-body { padding: 8px 10px; }
        .campaign-group-body .product-card {
            margin-bottom: 6px; box-shadow: none;
            border-left: 3px solid #667eea;
            background: #fafbff;
        }
        .campaign-group-body .product-card:last-child { margin-bottom: 0; }
        .campaign-group.is-hidden { display: none; }
        .product-card.is-hidden { display: none !important; }

        /* Multi-row Claim Items */
        .claim-item-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .claim-item-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px;
        }
        .claim-item-num { font-size: 0.78rem; font-weight: 700; color: #475569; }
        .btn-remove-item {
            width: 24px; height: 24px; border-radius: 50%;
            background: #fee2e2; color: #dc2626; border: none;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            line-height: 1;
        }
        .btn-remove-item:hover { background: #fecaca; }
        .claim-search-wrap { position: relative; margin-bottom: 6px; }
        .claim-item-meta {
            display: flex; gap: 6px; font-size: 11px; margin-bottom: 6px;
            color: #475569;
        }
        .claim-qty-row {
            display: flex; align-items: center; gap: 8px; margin-top: 6px;
        }
        .claim-qty-row label { font-size: 0.75rem; color: #475569; font-weight: 600; }
        .claim-qty-row input[type="number"] {
            width: 70px; padding: 6px 8px; font-size: 0.85rem;
        }
        .btn-add-item {
            width: 100%; padding: 10px; margin-bottom: 12px;
            background: #fff; border: 1.5px dashed #94a3b8; color: #475569;
            border-radius: 10px; cursor: pointer; font-size: 0.82rem; font-weight: 600;
            transition: all 0.15s;
        }
        .btn-add-item:hover {
            background: #eff6ff; border-color: #667eea; color: #667eea;
        }

        /* Per-row photo upload */
        .claim-photo-section {
            margin-top: 8px; padding-top: 8px;
            border-top: 1px dashed #cbd5e1;
        }
        .claim-photo-section label {
            display: block; font-size: 0.72rem; font-weight: 600;
            color: #475569; margin-bottom: 6px;
        }
        .row-photo-upload {
            padding: 14px; border: 1.5px dashed #cbd5e1; border-radius: 8px;
            text-align: center; cursor: pointer; background: white;
            transition: all 0.15s; position: relative;
        }
        .row-photo-upload:hover { border-color: #667eea; background: #f8faff; }
        .row-photo-upload.has-photo { border-color: #059669; padding: 6px; }
        .row-photo-upload.is-loading { border-color: #667eea; background: #f0f4ff; pointer-events: none; }
        .row-photo-upload .icon { font-size: 1.6rem; margin-bottom: 4px; }
        .row-photo-upload .text { font-size: 0.74rem; color: #64748b; }
        .row-photo-preview { max-width: 100%; max-height: 140px; border-radius: 6px; }
        .row-photo-size {
            font-size: 0.7rem; color: #475569; padding: 4px 0 0;
            text-align: right;
        }

        /* Loading spinner */
        .loading-spinner {
            width: 28px; height: 28px;
            border: 3px solid #e2e8f0; border-top-color: #667eea;
            border-radius: 50%; animation: spin 0.7s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Submit overlay */
        .submit-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            z-index: 10000;
            display: none;
            align-items: center; justify-content: center;
        }
        .submit-overlay.is-active { display: flex; }
        .submit-box {
            background: white; padding: 22px 24px;
            border-radius: 12px; text-align: center;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
            min-width: 200px;
        }
        .submit-box .loading-spinner { width: 36px; height: 36px; margin-bottom: 10px; }
        .submit-box .label { font-size: 0.85rem; color: #1e293b; font-weight: 600; }
        .submit-box .progress { font-size: 0.75rem; color: #64748b; margin-top: 4px; }

        /* Result modal */
        .result-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            z-index: 10001;
            display: none;
            align-items: center; justify-content: center;
            padding: 16px;
            animation: fadeIn 0.2s ease;
        }
        .result-modal-overlay.is-active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .result-modal-content {
            background: white;
            border-radius: 16px;
            padding: 28px 22px 22px;
            max-width: 380px; width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .result-icon {
            font-size: 3.2rem; line-height: 1;
            margin-bottom: 8px;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.1));
            animation: bounce 0.6s ease;
        }
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            40% { transform: scale(1.15); }
            70% { transform: scale(0.95); }
        }
        .result-title {
            font-size: 1.25rem; font-weight: 800; color: #1e293b;
            margin-bottom: 4px;
        }
        .result-subtitle {
            font-size: 0.82rem; color: #64748b;
            margin-bottom: 18px;
        }
        .result-stats {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
            margin-bottom: 16px;
        }
        .result-stats:has(#resultFailedStat:not([style*="display:none"])) {
            grid-template-columns: repeat(3, 1fr);
        }
        .result-stat {
            background: #f8fafc; border-radius: 10px;
            padding: 10px 8px;
        }
        .rs-label {
            font-size: 0.65rem; color: #64748b; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .rs-value {
            font-size: 1.6rem; font-weight: 800; line-height: 1.1;
            margin-top: 4px;
        }
        .rs-success { color: #059669; }
        .rs-fail { color: #dc2626; }
        .rs-points { color: #6366f1; }
        .result-breakdown {
            background: #f8fafc; border-radius: 10px;
            padding: 12px; margin-bottom: 12px; text-align: left;
            max-height: 180px; overflow-y: auto;
        }
        .rb-title { font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 8px; }
        .rb-list { display: flex; flex-direction: column; gap: 6px; }
        .rb-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 6px 8px; background: white; border-radius: 6px;
            font-size: 0.78rem;
        }
        .rb-item-name { color: #1e293b; font-weight: 500; flex: 1; min-width: 0; }
        .rb-item-points {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 2px 8px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 700; white-space: nowrap;
        }
        .rb-item.is-failed .rb-item-points {
            background: #fee2e2; color: #dc2626;
        }
        .result-errors {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: 10px 12px; margin-bottom: 12px; text-align: left;
        }
        .re-title { font-size: 0.78rem; font-weight: 700; color: #b91c1c; margin-bottom: 6px; }
        .re-list { margin: 0; padding-left: 18px; font-size: 0.74rem; color: #991b1b; }
        .re-list li { margin-bottom: 2px; }
        .result-actions {
            display: flex; gap: 8px;
        }
        .result-actions .btn { flex: 1; }

        /* Autocomplete dropdown */
        .autocomplete-list {
            position: absolute; top: 100%; left: 0; right: 0;
            background: white; border: 1px solid #e2e8f0;
            border-radius: 8px; max-height: 280px; overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            z-index: 1100; margin-top: 4px;
        }
        .autocomplete-item {
            padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s;
        }
        .autocomplete-item:hover { background: #f0f4ff; }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item.is-disabled { opacity: 0.45; cursor: not-allowed; }
        .autocomplete-item.is-disabled:hover { background: white; }
        .ac-row1 { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .ac-name { font-size: 0.84rem; font-weight: 600; color: #1e293b; }
        .ac-points {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 2px 8px; border-radius: 12px;
            font-size: 0.7rem; font-weight: 700;
        }
        .ac-row2 { display: flex; justify-content: space-between; margin-top: 2px; }
        .ac-campaign { font-size: 0.7rem; color: #94a3b8; }
        .autocomplete-empty {
            padding: 12px; text-align: center; color: #94a3b8; font-size: 0.78rem;
        }
        .autocomplete-footer {
            padding: 8px; text-align: center; color: #94a3b8;
            font-size: 0.7rem; background: #f8fafc; font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <a href="{{ route('waiter.bonus') }}" class="back-btn">←</a>
            <h1>🎯 Bonus Produk</h1>
            <span class="header-name">{{ $waiterName }}</span>
        </div>
    </div>

    <div class="container">
        {{-- Summary --}}
        <div class="summary-card">
            <div class="summary-hero">
                <div class="summary-hero-icon">🏆</div>
                <div class="summary-hero-content">
                    <div class="summary-hero-label">Total Poin Bonus Penjualan Bulan {{ date('M Y', strtotime($month . '-01')) }}</div>
                    <div class="summary-hero-value">+{{ $breakdown['total_approved'] ?? 0 }} <span class="summary-hero-unit">poin</span></div>
                    @if(($breakdown['total_pending'] ?? 0) > 0)
                        <div class="summary-hero-pending">+{{ $breakdown['total_pending'] }} pending menunggu verifikasi</div>
                    @endif
                </div>
            </div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Disetujui</div>
                    <div class="value value-green">{{ count($breakdown['approved_claims'] ?? []) }}</div>
                    <div class="summary-sub">klaim</div>
                </div>
                <div class="summary-item">
                    <div class="label">Pending</div>
                    <div class="value value-orange">{{ count($breakdown['pending_claims'] ?? []) }}</div>
                    <div class="summary-sub">klaim</div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Klaim</div>
                    <div class="value value-muted">{{ count($breakdown['all_claims'] ?? []) }}</div>
                    <div class="summary-sub">bulan ini</div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('products', this)">📦 Produk</button>
            <button class="tab-btn" onclick="switchTab('history', this)">📋 Riwayat</button>
            @if(in_array(session('waiter_role'), ['finance', 'supervisor']))
            <button class="tab-btn" onclick="switchTab('verify', this)">🔍 Verifikasi</button>
            @endif
        </div>

        {{-- Products Tab --}}
        <div id="tab-products">
            @if(count($groupedProducts ?? []) === 0)
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <p>Tidak ada campaign bonus produk aktif saat ini.</p>
                </div>
            @else
                <div class="search-box">
                    <input type="search" id="productSearch" placeholder="🔍 Cari produk (nama / campaign)..." oninput="filterProducts(this.value)">
                </div>
                <div id="productGroupsContainer">
                    @foreach($groupedProducts as $gIdx => $group)
                        <details class="campaign-group" data-group-idx="{{ $gIdx }}" data-campaign-title="{{ strtolower($group['campaign_title']) }}" {{ $gIdx === 0 ? 'open' : '' }}>
                            <summary class="campaign-group-header">
                                <div class="summary-main">
                                    <div class="campaign-title">🎯 {{ $group['campaign_title'] }}</div>
                                    <div class="campaign-end">
                                        <span class="product-count">{{ count($group['products']) }} produk</span>
                                        @if($group['campaign_end_date'])
                                            &bull; s/d {{ $group['campaign_end_date'] }}
                                        @endif
                                    </div>
                                </div>
                                <span class="summary-toggle">▾</span>
                            </summary>
                            <div class="campaign-group-body">
                                @foreach($group['products'] as $sp)
                                    <div class="product-card" data-product-name="{{ strtolower($sp['name']) }}">
                                        <div class="info">
                                            <h4>{{ $sp['name'] }}</h4>
                                            <div class="meta">
                                                @if($sp['has_quota'])
                                                    <span style="color:{{ $sp['quota_remaining'] === 0 ? '#dc2626' : '#d97706' }};">sisa {{ $sp['quota_remaining'] }}/{{ $sp['quota'] }}</span>
                                                @else
                                                    Jual 1 unit = dapat poin
                                                @endif
                                            </div>
                                        </div>
                                        <div class="points-badge">+{{ $sp['points_per_unit'] }} poin</div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
                <div id="searchEmpty" style="display:none;" class="empty-state">
                    <div class="icon">🔍</div>
                    <p>Tidak ada produk cocok dengan pencarian.</p>
                </div>
            @endif
        </div>

        {{-- History Tab --}}
        <div id="tab-history" style="display:none;">
            @php $allClaims = $breakdown['all_claims'] ?? []; @endphp
            @if(count($allClaims) === 0)
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <p>Belum ada klaim bulan ini.</p>
                </div>
            @else
                @foreach($allClaims as $claim)
                <div class="history-item">
                    <div class="top">
                        <span class="product-name">{{ $claim['product_name'] ?? '-' }}</span>
                        <span class="status-badge status-{{ $claim['status'] ?? 'pending' }}">{{ ucfirst($claim['status'] ?? 'pending') }}</span>
                    </div>
                    <div class="detail">
                        {{ $claim['quantity'] ?? 0 }} unit × {{ $claim['points_per_unit'] ?? 0 }} poin = <strong>{{ $claim['points_claimed'] ?? 0 }} poin</strong>
                        &bull; {{ $claim['date'] ?? '-' }}
                        @if(($claim['status'] ?? '') === 'rejected' && ($claim['reject_reason'] ?? ''))
                            <br><span style="color:#dc2626;">❌ {{ $claim['reject_reason'] }}</span>
                        @endif
                        @if(($claim['status'] ?? '') === 'approved')
                            <br><span style="color:#059669;">✅ Diverifikasi oleh {{ $claim['verified_by'] ?? '-' }}</span>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        {{-- Verification Tab (Finance/Supervisor only) --}}
        @if(in_array(session('waiter_role'), ['finance', 'supervisor']))
        <div id="tab-verify" style="display:none;">
            <div id="verifyLoading" class="empty-state">
                <div class="icon">⏳</div>
                <p>Memuat klaim pending...</p>
            </div>
            <div id="verifyContent" style="display:none;"></div>
        </div>
        @endif
    </div>

    {{-- Fixed Claim Button --}}
    @if(count($campaigns) > 0)
    <div class="claim-btn-container">
        <button class="btn btn-primary" onclick="openClaimModal()">📝 Klaim Bonus Penjualan</button>
    </div>
    @endif

    {{-- Claim Modal --}}
    <div id="claimModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-handle"></div>
            <div class="modal-title">📝 Klaim Bonus Penjualan</div>
            <form id="claimForm">
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:0.78rem; color:#1e40af;">
                    💡 Tap "+ Tambah Produk" untuk klaim banyak produk. Setiap produk butuh foto bukti masing-masing.
                </div>

                <div id="claimItemsContainer">
                    {{-- First row added by JS on modal open --}}
                </div>

                <button type="button" class="btn-add-item" onclick="addClaimItemRow()">+ Tambah Produk</button>

                <div id="pointsPreview" class="points-preview" style="display:none;">
                    <div class="label">Total poin yang akan diklaim</div>
                    <div class="value" id="previewPoints">0</div>
                </div>

                <button type="submit" class="btn btn-primary" id="btnClaim" disabled>Kirim Klaim</button>
                <button type="button" class="btn btn-light" style="width:100%; margin-top:8px;" onclick="closeClaimModal()">Batal</button>
            </form>
        </div>
    </div>

    {{-- All eligible products data for client-side autocomplete --}}
    <script id="allProductsData" type="application/json">@json($sortedProducts ?? [])</script>

    {{-- Submit progress overlay --}}
    <div id="submitOverlay" class="submit-overlay">
        <div class="submit-box">
            <div class="loading-spinner"></div>
            <div class="label" id="submitOverlayLabel">Mengirim klaim...</div>
            <div class="progress" id="submitOverlayProgress">Mohon tunggu</div>
        </div>
    </div>

    {{-- Result modal --}}
    <div id="resultModal" class="result-modal-overlay">
        <div class="result-modal-content">
            <div class="result-icon" id="resultIcon">🎉</div>
            <div class="result-title" id="resultTitle">Klaim Berhasil!</div>
            <div class="result-subtitle" id="resultSubtitle">Menunggu verifikasi finance</div>

            <div class="result-stats">
                <div class="result-stat">
                    <div class="rs-label">Berhasil</div>
                    <div class="rs-value rs-success" id="resultSuccessCount">0</div>
                </div>
                <div class="result-stat">
                    <div class="rs-label">Total Poin</div>
                    <div class="rs-value rs-points" id="resultTotalPoints">0</div>
                </div>
                <div class="result-stat" id="resultFailedStat" style="display:none;">
                    <div class="rs-label">Gagal</div>
                    <div class="rs-value rs-fail" id="resultFailedCount">0</div>
                </div>
            </div>

            <div class="result-breakdown" id="resultBreakdown" style="display:none;">
                <div class="rb-title">📋 Detail Klaim</div>
                <div class="rb-list" id="resultBreakdownList"></div>
            </div>

            <div class="result-errors" id="resultErrors" style="display:none;">
                <div class="re-title">⚠️ Beberapa klaim gagal</div>
                <ul class="re-list" id="resultErrorsList"></ul>
            </div>

            <div class="result-actions">
                <button type="button" class="btn btn-light" onclick="closeResultModal()">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="goToHistory()">📋 Lihat Riwayat</button>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tab, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-products').style.display = tab === 'products' ? 'block' : 'none';
        document.getElementById('tab-history').style.display = tab === 'history' ? 'block' : 'none';
        const verifyTab = document.getElementById('tab-verify');
        if (verifyTab) verifyTab.style.display = tab === 'verify' ? 'block' : 'none';
        if (tab === 'verify') loadVerifyClaims();
    }

    // ===== VERIFY =====
    async function loadVerifyClaims() {
        const loading = document.getElementById('verifyLoading');
        const content = document.getElementById('verifyContent');
        if (!loading || !content) return;

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const res = await fetch('{{ route("waiter.bonus_produk.verify") }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!data.success) { loading.innerHTML = `<div class="icon">⚠️</div><p>${data.message || 'Gagal memuat.'}</p>`; return; }

            loading.style.display = 'none';
            content.style.display = 'block';

            const pending = data.pending || [];
            const approved = data.recent_approved || [];

            if (pending.length === 0 && approved.length === 0) {
                content.innerHTML = '<div class="empty-state"><div class="icon">✅</div><p>Tidak ada klaim yang perlu diverifikasi.</p></div>';
                return;
            }

            let html = '';
            if (pending.length > 0) {
                html += `<div class="section-title" style="color:#d97706;">⏳ Menunggu Verifikasi (${pending.length})</div>`;
                pending.forEach(c => { html += renderVerifyCard(c); });
            }
            if (approved.length > 0) {
                html += `<div class="section-title" style="margin-top:20px; color:#059669;">✅ Baru Disetujui</div>`;
                approved.forEach(c => {
                    html += `<div class="history-item">
                        <div class="top"><span class="product-name">${c.waiter_name || '-'} — ${c.product_name || '-'}</span><span class="status-badge status-approved">Approved</span></div>
                        <div class="detail">${c.quantity || 0} unit = <strong>${c.points_claimed || 0} poin</strong> &bull; ${c.date || '-'}</div>
                    </div>`;
                });
            }
            content.innerHTML = html;
        } catch (err) { loading.innerHTML = `<div class="icon">⚠️</div><p>Error: ${err.message}</p>`; }
    }

    function renderVerifyCard(claim) {
        const photoHtml = claim.photo_url
            ? `<img src="${claim.photo_url}" onclick="window.open(this.src)" alt="Bukti struk">`
            : '<div style="padding:8px; background:#fef2f2; border-radius:8px; font-size:0.75rem; color:#dc2626; text-align:center;">⚠️ Tidak ada foto bukti</div>';

        return `<div class="verify-card" id="claim-${claim.id}">
            <div class="waiter-name">${claim.waiter_name || '-'}</div>
            <div class="claim-detail">
                <strong>${claim.product_name || '-'}</strong> — ${claim.quantity || 0} unit × ${claim.points_per_unit || 0} = <span class="claim-points">${claim.points_claimed || 0} poin</span>
                <br><span style="color:#94a3b8; font-size:0.72rem;">${claim.date || '-'} • ${claim.campaign_title || ''}</span>
            </div>
            ${photoHtml}
            <div class="verify-actions">
                <button class="btn btn-sm btn-approve" onclick="verifyClaim('${claim.id}', 'approved')">✅ Approve</button>
                <button class="btn btn-sm btn-reject" onclick="rejectClaim('${claim.id}')">❌ Reject</button>
            </div>
        </div>`;
    }

    async function verifyClaim(id, status, reason = null) {
        const body = { status };
        if (reason) body.reason = reason;
        try {
            const res = await fetch('{{ url("waiter/bonus-produk/verify") }}/' + id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (data.success) { document.getElementById('claim-' + id)?.remove(); loadVerifyClaims(); }
            else alert(data.message || 'Gagal verifikasi.');
        } catch (err) { alert('Error: ' + err.message); }
    }

    function rejectClaim(id) {
        const reason = prompt('Alasan penolakan (opsional):');
        if (reason === null) return;
        verifyClaim(id, 'rejected', reason || '');
    }

    // ===== PRODUCT SEARCH FILTER (products tab) =====
    function filterProducts(query) {
        const q = (query || '').trim().toLowerCase();
        const groups = document.querySelectorAll('.campaign-group');
        let totalVisible = 0;

        groups.forEach((group, gIdx) => {
            const campaignTitle = (group.dataset.campaignTitle || '');
            const cards = group.querySelectorAll('.product-card');
            let visibleInGroup = 0;

            cards.forEach(card => {
                const name = card.dataset.productName || '';
                const matches = q === '' || name.includes(q) || campaignTitle.includes(q);
                if (matches) {
                    card.classList.remove('is-hidden');
                    visibleInGroup++;
                } else {
                    card.classList.add('is-hidden');
                }
            });

            if (visibleInGroup === 0) {
                group.classList.add('is-hidden');
            } else {
                group.classList.remove('is-hidden');
                totalVisible += visibleInGroup;
                // Auto-open group when searching with any query
                // When search is cleared, only first group stays open (default)
                if (q !== '') {
                    group.open = true;
                } else {
                    group.open = (gIdx === 0);
                }
            }
        });

        const empty = document.getElementById('searchEmpty');
        if (empty) empty.style.display = totalVisible === 0 ? 'block' : 'none';
    }

    // ===== CLAIM =====
    const ALL_PRODUCTS = (function() {
        try { return JSON.parse(document.getElementById('allProductsData').textContent || '[]'); }
        catch (e) { return []; }
    })();
    const rowPhotos = {}; // { rowId: dataUrl }
    let claimRowSeq = 0;

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function openClaimModal() {
        document.getElementById('claimModal').style.display = 'flex';
        const container = document.getElementById('claimItemsContainer');
        if (container.children.length === 0) addClaimItemRow();
    }
    function closeClaimModal() {
        document.getElementById('claimModal').style.display = 'none';
    }

    function addClaimItemRow(presetProduct) {
        const container = document.getElementById('claimItemsContainer');
        const idx = ++claimRowSeq;
        const row = document.createElement('div');
        row.className = 'claim-item-row';
        row.dataset.rowId = idx;
        row.innerHTML = `
            <div class="claim-item-header">
                <span class="claim-item-num">Produk #${container.children.length + 1}</span>
                <button type="button" class="btn-remove-item" onclick="removeClaimItemRow(this)" title="Hapus produk">×</button>
            </div>
            <div class="claim-search-wrap">
                <input type="text" class="form-control js-product-search" placeholder="Ketik nama produk..." autocomplete="off" data-row-id="${idx}">
                <input type="hidden" class="js-campaign-id">
                <input type="hidden" class="js-product-key">
                <input type="hidden" class="js-points" value="0">
                <div class="autocomplete-list" data-row-id="${idx}" style="display:none;"></div>
            </div>
            <div class="claim-item-meta js-row-meta" style="display:none;">
                <span class="js-row-points-info"></span>
                <span class="js-row-quota-info"></span>
            </div>
            <div class="claim-qty-row">
                <label>Qty:</label>
                <input type="number" class="form-control js-qty" min="1" value="1" oninput="onQtyOrProductChange(this)">
                <span class="js-row-subtotal" style="font-weight:700; color:#667eea; font-size:0.9rem;"></span>
            </div>
            <div class="claim-photo-section">
                <label>Foto Struk/Bukti Produk #${container.children.length + 1}</label>
                <div class="photo-upload row-photo-upload" data-row-id="${idx}" onclick="document.getElementById('photoInput-${idx}').click()">
                    <div class="row-photo-placeholder">
                        <div class="icon">📷</div>
                        <div class="text">Tap untuk ambil/pilih foto bukti</div>
                    </div>
                    <img class="row-photo-preview" style="display:none;">
                </div>
                <input type="file" id="photoInput-${idx}" accept="image/*" style="display:none;" onchange="handleRowPhoto(this, ${idx})">
            </div>
        `;
        container.appendChild(row);

        const search = row.querySelector('.js-product-search');
        search.addEventListener('input', () => onSearchInput(search));
        search.addEventListener('focus', () => onSearchInput(search));
        search.addEventListener('blur', () => setTimeout(() => hideAutocomplete(idx), 200));

        if (presetProduct) {
            search.value = presetProduct.name;
            applyProductPick(row, presetProduct);
        }
        renumberRows();
        updateTotalPreview();
    }

    function removeClaimItemRow(btn) {
        const row = btn.closest('.claim-item-row');
        const rowId = row.dataset.rowId;
        const container = document.getElementById('claimItemsContainer');
        delete rowPhotos[rowId];
        if (container.children.length === 1) {
            // Don't allow removing last row, just clear it
            row.querySelector('.js-product-search').value = '';
            row.querySelector('.js-campaign-id').value = '';
            row.querySelector('.js-product-key').value = '';
            row.querySelector('.js-points').value = '0';
            row.querySelector('.js-row-meta').style.display = 'none';
            row.querySelector('.js-row-subtotal').textContent = '';
            const preview = row.querySelector('.row-photo-preview');
            const placeholder = row.querySelector('.row-photo-placeholder');
            if (preview) { preview.src = ''; preview.style.display = 'none'; }
            if (placeholder) placeholder.style.display = 'block';
            row.querySelector('.row-photo-upload').classList.remove('has-photo');
            updateTotalPreview();
            return;
        }
        row.remove();
        renumberRows();
        updateTotalPreview();
    }

    function renumberRows() {
        const rows = document.querySelectorAll('.claim-item-row');
        rows.forEach((r, i) => {
            const numEl = r.querySelector('.claim-item-num');
            if (numEl) numEl.textContent = `Produk #${i + 1}`;
            const photoLabel = r.querySelector('.claim-photo-section label');
            if (photoLabel) photoLabel.textContent = `Foto Struk/Bukti Produk #${i + 1}`;
        });
    }

    function handleRowPhoto(input, rowId) {
        const file = input.files[0];
        if (!file) return;

        // Validation: file type
        if (!file.type.startsWith('image/')) {
            alert('File harus berupa gambar (JPG/PNG). File yang dipilih: ' + (file.type || 'unknown'));
            input.value = '';
            return;
        }

        // Validation: max raw file size 20MB (sebelum compress)
        const MAX_RAW_SIZE = 20 * 1024 * 1024;
        if (file.size > MAX_RAW_SIZE) {
            alert('Foto terlalu besar (' + (file.size / 1024 / 1024).toFixed(1) + ' MB). Max 20 MB sebelum kompresi.');
            input.value = '';
            return;
        }

        const row = document.querySelector(`.claim-item-row[data-row-id="${rowId}"]`);
        const upload = row.querySelector('.row-photo-upload');
        const placeholder = row.querySelector('.row-photo-placeholder');
        const preview = row.querySelector('.row-photo-preview');

        // Show loading state immediately
        upload.classList.add('is-loading');
        placeholder.style.display = 'block';
        placeholder.innerHTML = '<div class="loading-spinner"></div><div class="text" style="margin-top:6px;">Memproses foto...</div>';
        preview.style.display = 'none';

        compressImage(file)
            .then(({ dataUrl, originalSize, compressedSize }) => {
                rowPhotos[rowId] = dataUrl;
                preview.src = dataUrl;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                upload.classList.remove('is-loading');
                upload.classList.add('has-photo');

                // Show compression info ke waiter
                const sizeBadge = row.querySelector('.row-photo-size') || (() => {
                    const el = document.createElement('div');
                    el.className = 'row-photo-size';
                    upload.parentElement.appendChild(el);
                    return el;
                })();
                const ratio = Math.round((1 - compressedSize / originalSize) * 100);
                sizeBadge.innerHTML = `✅ ${(compressedSize/1024).toFixed(0)} KB ${ratio > 5 ? `<span style="color:#059669;">(hemat ${ratio}%)</span>` : ''}`;

                validateForm();
            })
            .catch(err => {
                console.error('[bonus produk] photo compress error:', err);
                alert('Gagal memproses foto: ' + (err.message || 'tidak dikenal') + '. Coba pilih foto lain.');
                upload.classList.remove('is-loading');
                placeholder.innerHTML = '<div class="icon">📷</div><div class="text">Tap untuk ambil foto bukti</div>';
                input.value = '';
                delete rowPhotos[rowId];
                validateForm();
            });
    }

    /**
     * Compress image client-side: resize to max 1280px (longest edge) + JPEG quality 0.78.
     * Returns { dataUrl, originalSize, compressedSize }.
     * Handles EXIF orientation via createImageBitmap when supported.
     */
    function compressImage(file) {
        return new Promise((resolve, reject) => {
            const MAX_DIMENSION = 1280;
            const JPEG_QUALITY = 0.78;
            const originalSize = file.size;

            const reader = new FileReader();
            reader.onerror = () => reject(new Error('Gagal baca file (FileReader error)'));
            reader.onload = (e) => {
                const img = new Image();
                img.onerror = () => reject(new Error('File bukan gambar valid'));
                img.onload = () => {
                    try {
                        let { width, height } = img;
                        if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
                            if (width > height) {
                                height = Math.round(height * MAX_DIMENSION / width);
                                width = MAX_DIMENSION;
                            } else {
                                width = Math.round(width * MAX_DIMENSION / height);
                                height = MAX_DIMENSION;
                            }
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        const dataUrl = canvas.toDataURL('image/jpeg', JPEG_QUALITY);
                        // base64 size estimation: (length * 3/4) - padding
                        const compressedSize = Math.round((dataUrl.length - 'data:image/jpeg;base64,'.length) * 3 / 4);

                        if (compressedSize > 5 * 1024 * 1024) {
                            // Still too big, retry with lower quality
                            const retryUrl = canvas.toDataURL('image/jpeg', 0.55);
                            const retrySize = Math.round((retryUrl.length - 'data:image/jpeg;base64,'.length) * 3 / 4);
                            if (retrySize > 5 * 1024 * 1024) {
                                return reject(new Error('Foto masih terlalu besar setelah kompresi. Pakai foto resolusi lebih rendah.'));
                            }
                            return resolve({ dataUrl: retryUrl, originalSize, compressedSize: retrySize });
                        }

                        resolve({ dataUrl, originalSize, compressedSize });
                    } catch (err) {
                        reject(err);
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function onSearchInput(input) {
        const q = input.value.trim().toLowerCase();
        const idx = input.dataset.rowId;
        const list = document.querySelector(`.autocomplete-list[data-row-id="${idx}"]`);
        if (!list) return;

        let matches = ALL_PRODUCTS;
        if (q) {
            matches = ALL_PRODUCTS.filter(p =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.campaign_title || '').toLowerCase().includes(q)
            );
        }
        const limited = matches.slice(0, 10);

        if (limited.length === 0) {
            list.innerHTML = `<div class="autocomplete-empty">Tidak ada produk cocok dengan "${escapeHtml(q)}"</div>`;
        } else {
            list.innerHTML = limited.map((p, i) => {
                const quotaInfo = p.has_quota
                    ? (p.quota_remaining === 0
                        ? '<span style="color:#dc2626; font-size:11px; margin-left:6px;">HABIS</span>'
                        : `<span style="color:#d97706; font-size:11px; margin-left:6px;">sisa ${p.quota_remaining}</span>`)
                    : '';
                return `<div class="autocomplete-item ${p.has_quota && p.quota_remaining === 0 ? 'is-disabled' : ''}" data-idx="${i}">
                    <div class="ac-row1">
                        <span class="ac-name">${escapeHtml(p.name)}</span>
                        <span class="ac-points">+${p.points_per_unit}</span>
                    </div>
                    <div class="ac-row2">
                        <span class="ac-campaign">${escapeHtml(p.campaign_title)}</span>
                        ${quotaInfo}
                    </div>
                </div>`;
            }).join('') + (matches.length > 10 ? `<div class="autocomplete-footer">+${matches.length - 10} produk lain — perjelas pencarian</div>` : '');

            list.querySelectorAll('.autocomplete-item').forEach(item => {
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    if (item.classList.contains('is-disabled')) {
                        alert('Produk ini sudah habis quota. Pilih produk lain.');
                        return;
                    }
                    const i = parseInt(item.dataset.idx, 10);
                    const picked = limited[i];
                    const row = input.closest('.claim-item-row');
                    input.value = picked.name;
                    applyProductPick(row, picked);
                    hideAutocomplete(idx);
                });
            });
        }
        list.style.display = 'block';
    }

    function applyProductPick(row, product) {
        row.querySelector('.js-campaign-id').value = product.campaign_id;
        row.querySelector('.js-product-key').value = product.product_key;
        row.querySelector('.js-points').value = product.points_per_unit;

        const meta = row.querySelector('.js-row-meta');
        const ptsInfo = row.querySelector('.js-row-points-info');
        const qInfo = row.querySelector('.js-row-quota-info');
        ptsInfo.textContent = `+${product.points_per_unit} poin/unit`;
        if (product.has_quota) {
            qInfo.textContent = ` • sisa quota: ${product.quota_remaining}/${product.quota}`;
            qInfo.style.color = product.quota_remaining === 0 ? '#dc2626' : '#d97706';
        } else {
            qInfo.textContent = '';
        }
        meta.style.display = 'flex';

        onQtyOrProductChange(row.querySelector('.js-qty'));
    }

    function onQtyOrProductChange(qtyInput) {
        const row = qtyInput.closest('.claim-item-row');
        const points = parseInt(row.querySelector('.js-points').value, 10) || 0;
        const qty = parseInt(qtyInput.value, 10) || 0;
        const subtotal = points * qty;
        const subtotalEl = row.querySelector('.js-row-subtotal');
        subtotalEl.textContent = subtotal > 0 ? `= ${subtotal} poin` : '';
        updateTotalPreview();
    }

    function updateTotalPreview() {
        let total = 0;
        let validRows = 0;
        document.querySelectorAll('.claim-item-row').forEach(row => {
            const points = parseInt(row.querySelector('.js-points').value, 10) || 0;
            const qty = parseInt(row.querySelector('.js-qty').value, 10) || 0;
            const productKey = row.querySelector('.js-product-key').value;
            if (points > 0 && qty > 0 && productKey) {
                total += points * qty;
                validRows++;
            }
        });
        const preview = document.getElementById('pointsPreview');
        if (total > 0) {
            preview.style.display = 'block';
            document.getElementById('previewPoints').textContent = `${total} poin (${validRows} produk)`;
        } else {
            preview.style.display = 'none';
        }
        validateForm();
    }

    function hideAutocomplete(idx) {
        const list = document.querySelector(`.autocomplete-list[data-row-id="${idx}"]`);
        if (list) list.style.display = 'none';
    }

    function validateForm() {
        let validRows = 0;
        document.querySelectorAll('.claim-item-row').forEach(row => {
            const productKey = row.querySelector('.js-product-key').value;
            const qty = parseInt(row.querySelector('.js-qty').value, 10) || 0;
            const rowId = row.dataset.rowId;
            const hasPhoto = !!rowPhotos[rowId];
            if (productKey && qty > 0 && hasPhoto) validRows++;
        });
        document.getElementById('btnClaim').disabled = validRows === 0;
    }

    document.getElementById('claimForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const itemsForUI = []; // includes product name + points for breakdown display
        const itemsPayload = []; // sent to backend
        const issues = [];
        document.querySelectorAll('.claim-item-row').forEach((row, idx) => {
            const cId = row.querySelector('.js-campaign-id').value;
            const pKey = row.querySelector('.js-product-key').value;
            const qty = parseInt(row.querySelector('.js-qty').value, 10) || 0;
            const points = parseInt(row.querySelector('.js-points').value, 10) || 0;
            const name = row.querySelector('.js-product-search').value.trim();
            const rowId = row.dataset.rowId;
            const photo = rowPhotos[rowId];
            if (!cId || !pKey) {
                issues.push(`Produk #${idx + 1}: belum pilih produk`);
                return;
            }
            if (qty <= 0) {
                issues.push(`Produk #${idx + 1}: qty harus > 0`);
                return;
            }
            if (!photo) {
                issues.push(`Produk #${idx + 1}: foto bukti wajib diupload`);
                return;
            }
            itemsForUI.push({ name, qty, points, subtotal: points * qty });
            itemsPayload.push({ campaign_id: cId, product_key: pKey, quantity: qty, photo_proof: photo });
        });

        if (issues.length > 0) {
            alert('Lengkapi data berikut:\n- ' + issues.join('\n- '));
            return;
        }
        if (itemsPayload.length === 0) {
            alert('Minimal pilih 1 produk dengan qty > 0 dan foto bukti.');
            return;
        }

        const totalKb = Math.round(itemsPayload.reduce((acc, it) => acc + (it.photo_proof.length * 3 / 4), 0) / 1024);

        const overlay = document.getElementById('submitOverlay');
        const overlayLabel = document.getElementById('submitOverlayLabel');
        const overlayProgress = document.getElementById('submitOverlayProgress');
        overlay.classList.add('is-active');
        overlayLabel.textContent = `Mengirim ${itemsPayload.length} klaim...`;
        overlayProgress.textContent = `Upload ${totalKb} KB · jangan tutup halaman`;

        const btn = document.getElementById('btnClaim');
        btn.disabled = true; btn.textContent = 'Mengirim...';

        try {
            const res = await fetch('{{ route("waiter.bonus_produk.claim") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ items: itemsPayload }),
            });

            overlayProgress.textContent = 'Memproses respons...';
            const data = await res.json();
            overlay.classList.remove('is-active');

            // Always show result modal (success or partial fail)
            showResultModal(data, itemsForUI);
        } catch (err) {
            overlay.classList.remove('is-active');
            showResultModal({
                success: false,
                submitted: 0,
                total_items: itemsPayload.length,
                total_points: 0,
                errors: ['Network error: ' + err.message + '. Cek koneksi internet & coba lagi.'],
                results: [],
            }, itemsForUI);
        }
        finally { btn.disabled = false; btn.textContent = 'Kirim Klaim'; }
    });

    /**
     * Show result modal after submit. Handles all states:
     * - All success: 🎉 with breakdown
     * - Partial success: 🟡 with success+failed counts + errors
     * - All fail: ❌ with errors
     */
    function showResultModal(data, itemsForUI) {
        // Close claim modal first so result modal sits clean on top
        closeClaimModal();

        const modal = document.getElementById('resultModal');
        const icon = document.getElementById('resultIcon');
        const title = document.getElementById('resultTitle');
        const subtitle = document.getElementById('resultSubtitle');
        const successCount = document.getElementById('resultSuccessCount');
        const failedCount = document.getElementById('resultFailedCount');
        const failedStat = document.getElementById('resultFailedStat');
        const totalPoints = document.getElementById('resultTotalPoints');
        const breakdown = document.getElementById('resultBreakdown');
        const breakdownList = document.getElementById('resultBreakdownList');
        const errorsBox = document.getElementById('resultErrors');
        const errorsList = document.getElementById('resultErrorsList');

        const total = data.total_items || itemsForUI.length;
        const submitted = data.submitted || 0;
        const failed = total - submitted;

        // State
        if (submitted === total && submitted > 0) {
            icon.textContent = '🎉';
            title.textContent = 'Klaim Berhasil!';
            subtitle.textContent = 'Menunggu verifikasi finance';
        } else if (submitted > 0) {
            icon.textContent = '⚠️';
            title.textContent = 'Sebagian Berhasil';
            subtitle.textContent = `${submitted} sukses, ${failed} gagal`;
        } else {
            icon.textContent = '❌';
            title.textContent = 'Klaim Gagal';
            subtitle.textContent = 'Tidak ada klaim yang berhasil disubmit';
        }

        successCount.textContent = submitted;
        totalPoints.textContent = '+' + (data.total_points || 0);

        if (failed > 0) {
            failedStat.style.display = 'block';
            failedCount.textContent = failed;
        } else {
            failedStat.style.display = 'none';
        }

        // Breakdown - show successful items first
        if (submitted > 0 && Array.isArray(data.results) && data.results.length > 0) {
            breakdownList.innerHTML = '';
            data.results.forEach((r, idx) => {
                const ui = itemsForUI[idx] || {};
                const success = r.success ?? false;
                const item = document.createElement('div');
                item.className = 'rb-item' + (!success ? ' is-failed' : '');
                const name = escapeHtml(ui.name || 'Produk #' + (idx + 1));
                const qtyTxt = ui.qty ? `× ${ui.qty}` : '';
                const ptsTxt = success ? `+${r.points_claimed || ui.subtotal || 0} poin` : 'Gagal';
                item.innerHTML = `
                    <div class="rb-item-name">${name} ${qtyTxt}</div>
                    <div class="rb-item-points">${ptsTxt}</div>
                `;
                breakdownList.appendChild(item);
            });
            breakdown.style.display = 'block';
        } else {
            breakdown.style.display = 'none';
        }

        // Errors
        if (Array.isArray(data.errors) && data.errors.length > 0) {
            errorsBox.style.display = 'block';
            errorsList.innerHTML = data.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('');
        } else {
            errorsBox.style.display = 'none';
        }

        modal.classList.add('is-active');
    }

    function closeResultModal() {
        document.getElementById('resultModal').classList.remove('is-active');
        // Reload to refresh breakdown + product quota
        location.reload();
    }

    function goToHistory() {
        // Close result modal first
        document.getElementById('resultModal').classList.remove('is-active');
        // Set hash, then force reload supaya DOMContentLoaded handler trigger
        // (browser TIDAK reload otomatis kalau cuma ganti hash)
        const url = window.location.pathname + '#history';
        window.location.replace(url);
        // Force reload because hash-only change doesn't trigger reload
        setTimeout(() => window.location.reload(), 50);
    }

    // Auto-switch to verify tab if URL has ?tab=verify (deep-link from quick-action tile)
    function initDeepLinkHandler() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('tab') === 'verify') {
            const verifyBtn = document.querySelector('.tab-bar .tab-btn:nth-child(3)');
            if (verifyBtn) verifyBtn.click();
        }
        // Auto-switch to history tab if URL hash is #history (from result modal "Lihat Riwayat")
        if (window.location.hash === '#history') {
            const historyBtn = document.querySelectorAll('.tab-bar .tab-btn')[1];
            if (historyBtn) historyBtn.click();
        }
    }
    // Run immediately if DOM already loaded (script tag at end of body),
    // otherwise wait for DOMContentLoaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDeepLinkHandler);
    } else {
        initDeepLinkHandler();
    }
    </script>
</body>
</html>
