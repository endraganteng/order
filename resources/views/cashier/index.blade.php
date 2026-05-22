<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir - Order & Tasks Display</title>
    <link rel="stylesheet" href="{{ asset('css/cashier-utilities.css') }}">
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

        /* ========== TOP BAR (Test Sound & Status) ========== */
        #btn-test-sound {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            z-index: 1000;
        }

        #btn-test-sound:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
        }

        #connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        #connection-status.online {
            background: rgba(34, 197, 94, 0.2);
            border-color: rgba(34, 197, 94, 0.4);
            color: #22c55e;
        }

        #connection-status.offline {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #ef4444;
        }

        /* ========== SCAN NOTIFICATION TOAST ========== */
        #scan-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
            z-index: 1001;
            min-width: 320px;
            max-width: 400px;
            transform: translateX(450px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        #scan-notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        #scan-notification.hide {
            transform: translateX(450px);
            opacity: 0;
        }

        .scan-notification-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .scan-notification-icon {
            font-size: 28px;
            animation: bounce 0.6s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .scan-notification-title {
            font-size: 16px;
            font-weight: 700;
        }

        .scan-notification-body {
            font-size: 14px;
            line-height: 1.5;
            padding-left: 38px;
        }

        .scan-notification-waiter {
            font-weight: 700;
            font-size: 15px;
        }

        .scan-notification-time {
            opacity: 0.9;
            font-size: 13px;
        }

        /* QR Pulse Animation */
        @keyframes qr-pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .qr-changed {
            animation: qr-pulse 0.6s ease;
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
        .section-title.dana { color: #4ade80; }

        /* ========== DANA TAB ========== */
        .dana-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            max-width: 1200px;
            margin: 0 auto 24px;
        }

        .dana-summary-card {
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.12), rgba(34, 197, 94, 0.06));
            border: 1px solid rgba(74, 222, 128, 0.25);
            border-radius: 12px;
            padding: 16px 20px;
        }

        .dana-summary-label {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .dana-summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #4ade80;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dana-summary-value.status-listening { color: #4ade80; font-size: 18px; }
        .dana-summary-value.status-offline { color: #ef4444; font-size: 18px; }

        /* Voice toggle */
        .dana-voice-control {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dana-voice-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
            cursor: pointer;
        }

        .dana-voice-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .dana-voice-slider {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #475569;
            border-radius: 26px;
            transition: 0.25s;
        }

        .dana-voice-slider:before {
            content: '';
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            top: 3px;
            background: #cbd5e1;
            border-radius: 50%;
            transition: 0.25s;
        }

        .dana-voice-toggle input:checked + .dana-voice-slider {
            background: #4ade80;
        }

        .dana-voice-toggle input:checked + .dana-voice-slider:before {
            transform: translateX(24px);
            background: #fff;
        }

        .dana-voice-test-btn {
            background: rgba(74, 222, 128, 0.15);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #4ade80;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .dana-voice-test-btn:hover {
            background: rgba(74, 222, 128, 0.25);
        }

        .dana-voice-test-btn:active {
            transform: scale(0.96);
        }

        .dana-voice-select {
            margin-top: 10px;
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #e2e8f0;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }

        .dana-voice-select:focus {
            outline: none;
            border-color: rgba(74, 222, 128, 0.6);
        }

        .dana-voice-select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .dana-payments-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 900px;
            margin: 0 auto;
        }

        .no-dana-payments {
            text-align: center;
            color: #64748b;
            padding: 40px 20px;
            font-size: 16px;
            background: rgba(148, 163, 184, 0.05);
            border-radius: 12px;
            border: 1px dashed rgba(148, 163, 184, 0.2);
        }

        .dana-payment-card {
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.08), rgba(34, 197, 94, 0.04));
            border: 1px solid rgba(74, 222, 128, 0.2);
            border-left: 4px solid #4ade80;
            border-radius: 10px;
            padding: 16px 20px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            animation: slideIn 0.3s ease-out;
            transition: background 0.3s ease;
        }

        .dana-payment-card.fresh {
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.25), rgba(34, 197, 94, 0.12));
            box-shadow: 0 0 24px rgba(74, 222, 128, 0.4);
        }

        .dana-payment-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .dana-payment-info {
            min-width: 0;
        }

        .dana-payment-amount {
            font-size: 22px;
            font-weight: 700;
            color: #4ade80;
            margin-bottom: 4px;
        }

        .dana-payment-sender {
            font-size: 14px;
            color: #e2e8f0;
            font-weight: 500;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dana-payment-meta {
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .dana-payment-time {
            font-size: 13px;
            color: #cbd5e1;
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .dana-payment-time-relative {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .dana-source-badge {
            display: inline-block;
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        @media (max-width: 600px) {
            .dana-payment-card {
                grid-template-columns: auto 1fr;
            }
            .dana-payment-time {
                grid-column: 1 / -1;
                text-align: left;
                padding-left: 64px;
            }
        }
        /* ========== END DANA TAB ========== */

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

        /* ========== PRODUCT LIST ========== */
        .product-item {
            display: flex;
            align-items: flex-start;
            gap: 0;
            padding: 12px 16px;
            margin-top: 10px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.1);
        }

        .product-badge {
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
            font-size: 10px;
            line-height: 1;
            margin-right: 4px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .product-details {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: white;
            flex: 1;
            line-height: 1.2;
        }

        .product-price {
            font-size: 18px;
            color: #fbbf24;
            font-weight: 700;
            white-space: nowrap;
        }

        /* ========== EMPTY STATES ========== */
        .no-orders, .no-tasks, .no-expired-orders {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 18px;
            font-weight: 500;
        }

        .empty-icon {
            font-size: 64px;
            display: block;
            margin-bottom: 16px;
        }

        .expired-history-note {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .expired-pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .expired-pagination button {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .expired-pagination button:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .expired-pagination button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .expired-pagination button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* ========== TASKS CONTAINER ========== */
        #tasks-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .task-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }

        .task-header {
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .task-meta {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 4px;
        }

        .task-description {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.9);
        }

        .task-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .task-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .task-btn-done {
            background: rgba(34, 197, 94, 0.9);
            color: white;
        }

        .task-btn-done:hover {
            background: rgba(34, 197, 94, 1);
        }

        /* ========== TOAST NOTIFICATIONS ========== */
        #toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            font-size: 15px;
            font-weight: 600;
            min-width: 280px;
            max-width: 400px;
            pointer-events: auto;
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toast.order {
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
        }

        .toast.success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            #toast-container {
                top: auto;
                bottom: 20px;
                right: 10px;
                left: 10px;
            }
            .toast {
                min-width: auto;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .tab-btn { padding: 10px 18px; font-size: 15px; }
            .section-title { font-size: 22px; }
            #tasks-container { grid-template-columns: 1fr; }
            #orders-container, #expired-orders-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div id="toast-container"></div>
    
    <!-- Scan Notification Toast -->
    <div id="scan-notification">
        <div class="scan-notification-header">
            <div class="scan-notification-icon">🎉</div>
            <div class="scan-notification-title">Absensi Berhasil!</div>
        </div>
        <div class="scan-notification-body">
            <div class="scan-notification-waiter" id="scan-notification-waiter"></div>
            <div class="scan-notification-time" id="scan-notification-time"></div>
        </div>
    </div>
    
    <button id="btn-test-sound">
        🔊 Test Suara
    </button>
    
    <div id="connection-status">⌛ Connecting...</div>

    <!-- TAB NAVIGATION -->
    <div class="tab-bar">
        <button class="tab-btn active" data-tab="orders" onclick="switchTab('orders')">
            📋 Orderan Masuk
        </button>
        <button class="tab-btn" data-tab="expired-orders" onclick="switchTab('expired-orders')">
            🕓 Riwayat Expired
        </button>
        <button class="tab-btn" data-tab="attendance" onclick="switchTab('attendance')">
            👤 Absensi
            <span id="attendance-badge" class="tab-badge zero">0</span>
        </button>
        <button class="tab-btn" data-tab="tasks" onclick="switchTab('tasks')">
            ?? Tugas Supervisor
            <span id="task-badge" class="tab-badge zero">0</span>
        </button>
        <button class="tab-btn" data-tab="dana" onclick="switchTab('dana')">
            💰 DANA Masuk
            <span id="dana-badge" class="tab-badge zero">0</span>
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

    <!-- TAB: ATTENDANCE -->
    <div id="tab-attendance" class="tab-content">
        <h1 class="section-title">👤 Absensi Waiter</h1>
        
        <div class="cashier-attendance-grid">
            <!-- QR Absensi Section -->
            <div class="cashier-attendance-panel">
                <div class="cashier-attendance-panel-header">
                    <div>
                        <div class="cashier-attendance-title">QR Absensi</div>
                        <div id="attendance-qr-subtitle-tab" class="cashier-attendance-subtitle">Memuat...</div>
                    </div>
                    <div id="attendance-qr-badge-tab" class="badge badge-primary">1x Pakai</div>
                </div>

                <!-- Per-Waiter Mode (Original) -->
                <select id="attendance-qr-waiter-select" class="cashier-attendance-select"></select>

                <!-- Global QR Mode -->
                <div id="attendance-global-mode" class="cashier-attendance-mode">
                    <div class="cashier-attendance-mode-title">Mode: Scan Berurutan</div>
                    <div class="cashier-attendance-mode-meta">Total scan hari ini: <span id="global-scan-count">0</span></div>
                </div>

                <div class="cashier-attendance-purpose">
                    <div id="attendance-qr-purpose" class="cashier-attendance-purpose-title">Memuat...</div>
                    <div id="attendance-qr-date" class="cashier-attendance-date"></div>
                </div>

                <div class="cashier-attendance-qr-box">
                    <div id="attendance-qr-code" class="cashier-attendance-qr-code"></div>
                    <div id="attendance-qr-empty" class="cashier-attendance-empty"></div>
                </div>

                <div id="attendance-qr-message" class="cashier-attendance-message">Menyiapkan QR absensi...</div>
                <div id="attendance-qr-meta" class="cashier-attendance-meta"></div>
            </div>

            <!-- Waiters Not Yet Clocked Section -->
            <div class="cashier-attendance-panel cashier-attendance-panel-scroll">
                <div class="cashier-attendance-panel-header cashier-attendance-panel-header-bordered">
                    <div class="cashier-attendance-title cashier-attendance-section-title">
                        ⚠️ Belum Absen
                    </div>
                    <div id="waiters-not-clocked-count-tab" class="cashier-attendance-count">0</div>
                </div>
                <div id="waiters-not-clocked-list-tab">
                    @if(isset($waitersNotYetClocked) && count($waitersNotYetClocked) > 0)
                        @foreach($waitersNotYetClocked as $waiter)
                        <div class="cashier-waiter-card">
                            <div class="cashier-waiter-name">{{ $waiter['name'] }}</div>
                            <div class="cashier-waiter-meta-row">
                                <div class="cashier-waiter-shift">
                                    📅 {{ $waiter['shift_name'] }}
                                </div>
                                <div class="cashier-waiter-time">
                                    🕐 {{ $waiter['clock_in_time'] }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="cashier-attendance-empty-state">
                            <div class="cashier-attendance-empty-icon">✅</div>
                            <div>Semua waiter sudah absen</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: TASKS -->
    <div id="tab-tasks" class="tab-content">
        <h1 class="section-title tasks">?? Tugas dari Supervisor</h1>
        <div id="tasks-container">
            <div class="no-tasks">
                <span class="empty-icon">?</span>
                Tidak ada tugas saat ini
            </div>
        </div>
    </div>

    <!-- TAB: DANA MASUK -->
    <div id="tab-dana" class="tab-content">
        <h1 class="section-title dana">💰 Pembayaran DANA Masuk</h1>

        <div class="dana-summary">
            <div class="dana-summary-card">
                <div class="dana-summary-label">Total Hari Ini</div>
                <div id="dana-summary-total" class="dana-summary-value">Rp 0</div>
            </div>
            <div class="dana-summary-card">
                <div class="dana-summary-label">Jumlah Transaksi</div>
                <div id="dana-summary-count" class="dana-summary-value">0</div>
            </div>
            <div class="dana-summary-card">
                <div class="dana-summary-label">Status</div>
                <div id="dana-summary-status" class="dana-summary-value status-listening">
                    <span class="status-dot"></span> Mendengarkan
                </div>
            </div>
            <div class="dana-summary-card">
                <div class="dana-summary-label">Suara Nominal</div>
                <div class="dana-voice-control">
                    <label class="dana-voice-toggle">
                        <input type="checkbox" id="dana-voice-enabled" checked>
                        <span class="dana-voice-slider"></span>
                    </label>
                    <button type="button" id="dana-voice-test" class="dana-voice-test-btn" title="Test suara">🔊 Test</button>
                </div>
                <select id="dana-voice-select" class="dana-voice-select" title="Pilih suara">
                    <option value="">Memuat suara...</option>
                </select>
            </div>
        </div>

        <div id="dana-payments-container" class="dana-payments-container">
            <div class="no-dana-payments">Belum ada pembayaran DANA hari ini.</div>
        </div>
    </div>
        </div>
    </div>

    <script id="cashier-workers-data" type="application/json">{!! json_encode($cashierWorkers ?? []) !!}</script>
    <script id="attendance-waiters-data" type="application/json">{!! json_encode($attendanceWaiters ?? []) !!}</script>
    <script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>

    <!-- Global Functions (must be outside module scope for onclick handlers) -->
    <script>
        // Tab switching function
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.classList.remove('active-tasks');
            });

            // Show selected tab content
            const selectedTab = document.getElementById('tab-' + tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // Add active class to selected button
            const selectedButton = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
            if (selectedButton) {
                selectedButton.classList.add(tabName === 'tasks' ? 'active-tasks' : 'active');
            }
            
            // Load expired orders when switching to that tab
            if (tabName === 'expired-orders' && typeof loadExpiredOrdersPage === 'function') {
                loadExpiredOrdersPage(1, true);
            }
        }
    </script>

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
        const attendanceWaitersDataEl = document.getElementById('attendance-waiters-data');
        const attendanceWaiterSelectEl = document.getElementById('attendance-qr-waiter-select');
        const attendanceQrCodeEl = document.getElementById('attendance-qr-code');
        const attendanceQrEmptyEl = document.getElementById('attendance-qr-empty');
        const attendanceQrPurposeEl = document.getElementById('attendance-qr-purpose');
        const attendanceQrDateEl = document.getElementById('attendance-qr-date');
        const attendanceQrMessageEl = document.getElementById('attendance-qr-message');
        const attendanceQrMetaEl = document.getElementById('attendance-qr-meta');
        const attendanceQrEndpoint = @json(route('cashier.attendance_qr', [], false));
        const attendanceQrGlobalEndpoint = @json(route('cashier.attendance_qr_global', [], false));
        const attendanceQrStorageKey = 'cashier_attendance_waiter_id';
        const useGlobalQrMode = @json($settings['attendance_use_global_qr'] ?? false);
        
        // Debug: Log mode
        console.log('Attendance Mode:', useGlobalQrMode ? 'GLOBAL QR' : 'PER-WAITER');
        console.log('Settings:', @json($settings ?? []));
        
        let cashierWorkers = [];
        let attendanceWaiters = [];
        let attendanceQrIntervalId = null;
        let attendanceQrRequestId = 0;
        try {
            cashierWorkers = JSON.parse(cashierWorkersDataEl?.textContent || '[]');
        } catch (error) {
            cashierWorkers = [];
        }
        try {
            attendanceWaiters = JSON.parse(attendanceWaitersDataEl?.textContent || '[]');
        } catch (error) {
            attendanceWaiters = [];
        }

        function renderAttendanceWaiterOptions() {
            // Guard: Don't run in global QR mode
            if (useGlobalQrMode) {
                console.log('Skipping waiter options render (global mode active)');
                return;
            }
            
            if (!attendanceWaiterSelectEl) return;
            attendanceWaiterSelectEl.innerHTML = '';

            if (!attendanceWaiters.length) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Tidak ada waiter wajib absen aktif';
                attendanceWaiterSelectEl.appendChild(option);
                attendanceWaiterSelectEl.disabled = true;
                return;
            }

            attendanceWaiters.forEach((waiter) => {
                const option = document.createElement('option');
                option.value = waiter.id || '';
                option.textContent = waiter.name || 'Waiter';
                attendanceWaiterSelectEl.appendChild(option);
            });

            const savedWaiterId = window.localStorage.getItem(attendanceQrStorageKey) || '';
            const defaultWaiter = attendanceWaiters.find((waiter) => waiter.id === savedWaiterId) || attendanceWaiters[0];
            attendanceWaiterSelectEl.value = defaultWaiter?.id || '';
            attendanceWaiterSelectEl.disabled = false;
        }

        function renderAttendanceQr(payload) {
            if (!attendanceQrCodeEl || !attendanceQrEmptyEl || !attendanceQrPurposeEl || !attendanceQrDateEl || !attendanceQrMessageEl || !attendanceQrMetaEl) {
                return;
            }

            attendanceQrCodeEl.innerHTML = '';
            attendanceQrEmptyEl.style.display = 'none';

            attendanceQrPurposeEl.textContent = payload?.purpose_label || 'QR Absensi';
            attendanceQrDateEl.textContent = payload?.date || '';
            attendanceQrMessageEl.textContent = payload?.message || 'QR siap dipindai.';

            const attendance = payload?.attendance || {};
            const metaParts = [];
            if (attendance.clock_in) metaParts.push(`Masuk: ${attendance.clock_in}`);
            if (attendance.clock_out) metaParts.push(`Pulang: ${attendance.clock_out}`);
            if (attendance.status === 'late' && Number(attendance.late_minutes || 0) > 0) {
                metaParts.push(`Terlambat ${attendance.late_minutes} menit`);
            }
            attendanceQrMetaEl.textContent = metaParts.join(' • ');

            if (!payload?.available || !payload?.qr_value || typeof QRCode === 'undefined') {
                attendanceQrEmptyEl.style.display = 'block';
                attendanceQrEmptyEl.textContent = payload?.available
                    ? 'Generator QR belum siap dimuat di browser ini.'
                    : (payload?.message || 'QR saat ini tidak tersedia.');
                return;
            }

            new QRCode(attendanceQrCodeEl, {
                text: payload.qr_value,
                width: 180,
                height: 180,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });
        }

        // Global QR Mode Functions
        let lastQrValue = null;
        let lastScanCount = 0;
        
        async function loadGlobalAttendanceQr() {
            try {
                const response = await fetch(attendanceQrGlobalEndpoint, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                
                const data = await response.json();
                
                if (!response.ok || !data?.success) {
                    throw new Error(data?.message || 'Gagal memuat QR absensi.');
                }
                
                // Detect QR change (someone just scanned)
                const qrChanged = lastQrValue !== null && lastQrValue !== data.qr_value;
                const scanCountIncreased = data.scan_count > lastScanCount;
                
                if (qrChanged && scanCountIncreased && data.last_scanned_waiter_name) {
                    // Show notification
                    showScanNotification(data.last_scanned_waiter_name);
                    
                    // Play sound
                    playNotificationSound();
                    
                    // Add pulse animation to QR
                    if (attendanceQrCodeEl) {
                        attendanceQrCodeEl.classList.add('qr-changed');
                        setTimeout(() => {
                            attendanceQrCodeEl.classList.remove('qr-changed');
                        }, 600);
                    }
                }
                
                // Update tracking variables
                lastQrValue = data.qr_value;
                lastScanCount = data.scan_count;
                
                // Render QR
                if (attendanceQrCodeEl && data.qr_value) {
                    attendanceQrCodeEl.innerHTML = '';
                    new QRCode(attendanceQrCodeEl, {
                        text: data.qr_value,
                        width: 180,
                        height: 180,
                        colorDark: '#0f172a',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H,
                    });
                }
                
                // Hide empty state
                if (attendanceQrEmptyEl) {
                    attendanceQrEmptyEl.style.display = 'none';
                }
                
                // Update purpose
                if (attendanceQrPurposeEl) {
                    attendanceQrPurposeEl.textContent = 'QR Absensi Global';
                }
                
                // Update date
                if (attendanceQrDateEl) {
                    attendanceQrDateEl.textContent = data.date || '';
                }
                
                // Update scan count
                const scanCountEl = document.getElementById('global-scan-count');
                if (scanCountEl) {
                    scanCountEl.textContent = data.scan_count || 0;
                }
                
                // Update message
                if (attendanceQrMessageEl) {
                    attendanceQrMessageEl.textContent = data.message || 'Scan QR untuk absen';
                }
                
                // Update stats
                if (attendanceQrMetaEl && data.stats) {
                    const s = data.stats;
                    attendanceQrMetaEl.textContent = 
                        `Belum: ${s.not_yet} | Masuk: ${s.clocked_in} | Pulang: ${s.clocked_out}`;
                }
                
                // Update "Belum Absen" list with real-time data
                updateWaitersNotClockedList(data.stats, data.waiters_not_yet_clocked);
                
            } catch (error) {
                console.error('Failed to load global QR:', error);
                if (attendanceQrEmptyEl) {
                    attendanceQrEmptyEl.style.display = 'block';
                    attendanceQrEmptyEl.textContent = error?.message || 'Gagal memuat QR absensi.';
                }
            }
        }
        
        function showScanNotification(waiterName) {
            const notificationEl = document.getElementById('scan-notification');
            const waiterEl = document.getElementById('scan-notification-waiter');
            const timeEl = document.getElementById('scan-notification-time');
            
            if (!notificationEl || !waiterEl || !timeEl) return;
            
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            
            waiterEl.textContent = waiterName;
            timeEl.textContent = `⏰ ${timeStr}`;
            
            // Show notification
            notificationEl.classList.remove('hide');
            notificationEl.classList.add('show');
            
            // Auto-hide after 4 seconds
            setTimeout(() => {
                notificationEl.classList.remove('show');
                notificationEl.classList.add('hide');
            }, 4000);
        }
        
        function playNotificationSound() {
            try {
                if (notificationSound) {
                    notificationSound.currentTime = 0;
                    notificationSound.play().catch(err => {
                        console.warn('Could not play notification sound:', err);
                    });
                }
            } catch (error) {
                console.warn('Notification sound error:', error);
            }
        }
        
        function updateWaitersNotClockedList(stats, waitersNotYetClocked) {
            // Update counter
            const countEl = document.getElementById('waiters-not-clocked-count-tab');
            if (countEl && stats) {
                countEl.textContent = stats.not_yet || 0;
                countEl.classList.toggle('is-zero', Number(stats.not_yet || 0) === 0);
            }
            
            // Update badge
            const badgeEl = document.getElementById('attendance-badge');
            if (badgeEl && stats) {
                badgeEl.textContent = stats.not_yet || 0;
                if (stats.not_yet > 0) {
                    badgeEl.classList.remove('zero');
                } else {
                    badgeEl.classList.add('zero');
                }
            }
            
            // Update list content
            const listEl = document.getElementById('waiters-not-clocked-list-tab');
            if (!listEl) return;
            
            if (!waitersNotYetClocked || waitersNotYetClocked.length === 0) {
                listEl.innerHTML = `
                    <div class="cashier-attendance-empty-state">
                        <div class="cashier-attendance-empty-icon">✅</div>
                        <div>Semua waiter sudah absen</div>
                    </div>
                `;
            } else {
                const cardsHtml = waitersNotYetClocked.map(waiter => `
                    <div class="cashier-waiter-card">
                        <div class="cashier-waiter-name">${escapeHtml(waiter.name || 'Unknown')}</div>
                        <div class="cashier-waiter-meta-row">
                            <div class="cashier-waiter-shift">
                                📅 ${escapeHtml(waiter.shift_name || 'Shift')}
                            </div>
                            <div class="cashier-waiter-time">
                                🕐 ${escapeHtml(waiter.clock_in_time || '-')}
                            </div>
                        </div>
                    </div>
                `).join('');
                
                listEl.innerHTML = cardsHtml;
            }
        }

        async function loadAttendanceQrForSelectedWaiter() {
            // Guard: Don't run in global QR mode
            if (useGlobalQrMode) {
                console.log('Skipping per-waiter QR load (global mode active)');
                return;
            }
            
            const waiterId = attendanceWaiterSelectEl?.value || '';
            if (!waiterId) {
                renderAttendanceQr({
                    available: false,
                    purpose_label: 'QR Absensi',
                    message: 'Pilih waiter untuk menampilkan QR absensi.',
                    date: '',
                });
                return;
            }

            const requestId = ++attendanceQrRequestId;

            try {
                const response = await fetch(`${attendanceQrEndpoint}?waiter_id=${encodeURIComponent(waiterId)}`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                const payload = await response.json();
                if (requestId !== attendanceQrRequestId) {
                    return;
                }

                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Gagal memuat QR absensi.');
                }

                window.localStorage.setItem(attendanceQrStorageKey, waiterId);
                renderAttendanceQr(payload);
            } catch (error) {
                renderAttendanceQr({
                    available: false,
                    purpose_label: 'QR Absensi',
                    message: error?.message || 'Gagal memuat QR absensi.',
                    date: '',
                });
            }
        }

        function startAttendanceQrPolling() {
            // Guard: Don't run in global QR mode
            if (useGlobalQrMode) {
                console.log('Skipping per-waiter polling (global mode active)');
                return;
            }
            
            if (attendanceQrIntervalId) {
                clearInterval(attendanceQrIntervalId);
            }

            attendanceQrIntervalId = window.setInterval(() => {
                loadAttendanceQrForSelectedWaiter();
            }, 5000);
        }

        function stopAttendanceQrPolling() {
            if (!attendanceQrIntervalId) return;
            clearInterval(attendanceQrIntervalId);
            attendanceQrIntervalId = null;
        }

        function startGlobalAttendanceQrPolling() {
            if (!useGlobalQrMode) {
                return;
            }

            stopAttendanceQrPolling();
            loadGlobalAttendanceQr();
            attendanceQrIntervalId = window.setInterval(() => {
                loadGlobalAttendanceQr();
            }, 2000);
        }

        // Initialize attendance QR based on mode
        if (useGlobalQrMode) {
            // Global QR Mode
            const subtitleEl = document.getElementById('attendance-qr-subtitle-tab');
            const badgeEl = document.getElementById('attendance-qr-badge-tab');
            
            if (subtitleEl) {
                subtitleEl.textContent = 'Semua waiter scan QR yang sama. QR berubah otomatis setelah ada yang scan.';
            }
            if (badgeEl) {
                badgeEl.textContent = 'Scan Berurutan';
            }
            
            if (attendanceWaiterSelectEl) {
                attendanceWaiterSelectEl.style.display = 'none';
            }
            document.getElementById('attendance-global-mode').style.display = 'block';

            startGlobalAttendanceQrPolling();
            
        } else {
            // Per-Waiter Mode (original)
            const subtitleEl = document.getElementById('attendance-qr-subtitle-tab');
            const badgeEl = document.getElementById('attendance-qr-badge-tab');
            
            if (subtitleEl) {
                subtitleEl.textContent = 'Pilih waiter, lalu minta waiter scan QR ini dari portal waiter.';
            }
            if (badgeEl) {
                badgeEl.textContent = '1x Pakai';
            }
            
            if (attendanceWaiterSelectEl) {
                attendanceWaiterSelectEl.style.display = 'block';
            }
            document.getElementById('attendance-global-mode').style.display = 'none';
            
            renderAttendanceWaiterOptions();
            loadAttendanceQrForSelectedWaiter();
            startAttendanceQrPolling();
            attendanceWaiterSelectEl?.addEventListener('change', loadAttendanceQrForSelectedWaiter);
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

                console.log('Expired orders query result:', {
                    exists: snapshot.exists(),
                    range: range,
                    nowSeconds: nowSeconds,
                    targetPage: targetPage
                });

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

                console.log('Filtered expired orders:', rows.length);

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
                
                console.log('Rendering expired orders:', {
                    pageRows: pageRows.length,
                    hasOlderPages: hasOlderPages,
                    targetPage: targetPage
                });
                
                renderExpiredOrdersTab();
            } catch (error) {
                console.error('Failed loading expired orders:', error);
                
                // Check if it's an index error
                if (error.message && error.message.includes('Index not defined')) {
                    expiredOrdersContainer.innerHTML = `
                        <div class="no-expired-orders">
                            ⚠️ Firebase index belum dikonfigurasi.<br>
                            <small style="font-size: 13px; margin-top: 8px; display: block;">
                                Tambahkan ".indexOn": "expires_at" di Firebase Database Rules.<br>
                                <a href="https://console.firebase.google.com" target="_blank" style="color: #00d9ff;">Buka Firebase Console →</a>
                            </small>
                        </div>
                    `;
                } else {
                    expiredOrdersContainer.innerHTML = '<div class="no-expired-orders">❌ Gagal memuat data. Silakan refresh halaman.</div>';
                }
                
                expiredOrdersToday = [];
                expiredHasOlderPages = false;
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

            stopAttendanceQrPolling();
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

            if (useGlobalQrMode) {
                startGlobalAttendanceQrPolling();
            } else {
                loadAttendanceQrForSelectedWaiter();
                startAttendanceQrPolling();
            }
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

        // ========== ATTENDANCE TAB BADGE ==========
        const attendanceBadge = document.getElementById('attendance-badge');
        const waitersNotClockedCountTab = document.getElementById('waiters-not-clocked-count-tab');

        function initAttendanceBadge() {
            const waitersNotYetClocked = @json($waitersNotYetClocked ?? []);
            const count = waitersNotYetClocked.length;

            // Update tab badge
            if (attendanceBadge) {
                attendanceBadge.textContent = count;
                if (count === 0) {
                    attendanceBadge.classList.add('zero');
                } else {
                    attendanceBadge.classList.remove('zero');
                }
            }

            // Update count in tab content
            if (waitersNotClockedCountTab) {
                waitersNotClockedCountTab.textContent = count;
                if (count === 0) {
                    waitersNotClockedCountTab.classList.add('is-zero');
                } else {
                    waitersNotClockedCountTab.classList.remove('is-zero');
                }
            }
        }

        // Initialize badge on page load
        initAttendanceBadge();

        // ========================================
        // DANA PAYMENTS TAB - REALTIME VIA FIREBASE
        // ========================================
        const danaContainer = document.getElementById('dana-payments-container');
        const danaBadge = document.getElementById('dana-badge');
        const danaSummaryTotal = document.getElementById('dana-summary-total');
        const danaSummaryCount = document.getElementById('dana-summary-count');
        const danaSummaryStatus = document.getElementById('dana-summary-status');
        const danaVoiceEnabledEl = document.getElementById('dana-voice-enabled');
        const danaVoiceTestBtn = document.getElementById('dana-voice-test');
        const danaVoiceSelectEl = document.getElementById('dana-voice-select');
        const danaPaymentsEndpoint = @json(route('cashier.dana_payments', [], false));

        // Voice config
        const DANA_VOICE_STORAGE_KEY = 'cashier_dana_voice_enabled';
        const DANA_VOICE_NAME_STORAGE_KEY = 'cashier_dana_voice_name';
        const danaVoiceSupported = typeof window.speechSynthesis !== 'undefined'
            && typeof window.SpeechSynthesisUtterance !== 'undefined';

        // Endpoint untuk Google Cloud TTS proxy. Server hide API key.
        const ttsSpeakEndpoint = @json(route('cashier.tts.speak', [], false));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // Daftar voice Google Cloud TTS Indonesia (akan di-merge dengan Web Speech API voices).
        // Tidak ada cara fetch ini dari API tanpa list-voices endpoint, jadi hard-code list yang umum.
        const GOOGLE_TTS_VOICES = [
            { name: 'id-ID-Wavenet-A', label: 'Google Wavenet A (Perempuan)', gender: 'F' },
            { name: 'id-ID-Wavenet-B', label: 'Google Wavenet B (Laki-laki)', gender: 'M' },
            { name: 'id-ID-Wavenet-C', label: 'Google Wavenet C (Laki-laki)', gender: 'M' },
            { name: 'id-ID-Wavenet-D', label: 'Google Wavenet D (Perempuan)', gender: 'F' },
            { name: 'id-ID-Standard-A', label: 'Google Standard A (Perempuan)', gender: 'F' },
            { name: 'id-ID-Standard-B', label: 'Google Standard B (Laki-laki)', gender: 'M' },
            { name: 'id-ID-Standard-C', label: 'Google Standard C (Laki-laki)', gender: 'M' },
            { name: 'id-ID-Standard-D', label: 'Google Standard D (Perempuan)', gender: 'F' },
        ];

        // Restore enabled toggle (default: ON)
        const storedVoice = localStorage.getItem(DANA_VOICE_STORAGE_KEY);
        if (storedVoice === '0') {
            danaVoiceEnabledEl.checked = false;
        }

        // Selected voice (default: Google Wavenet A — paling natural id-ID)
        let selectedVoiceName = localStorage.getItem(DANA_VOICE_NAME_STORAGE_KEY) || 'google:id-ID-Wavenet-A';

        if (!danaVoiceSupported) {
            // Web Speech tidak support, tapi Google TTS tetap bisa
            console.log('Web Speech API tidak support, akan pakai Google TTS saja');
        }

        danaVoiceEnabledEl.addEventListener('change', () => {
            localStorage.setItem(DANA_VOICE_STORAGE_KEY, danaVoiceEnabledEl.checked ? '1' : '0');
        });

        /**
         * Populate voice dropdown.
         * Group:
         *   - Google Cloud TTS (premium, online — recommended)
         *   - Browser (Web Speech API — fallback offline)
         */
        function populateVoiceDropdown() {
            danaVoiceSelectEl.innerHTML = '';

            // Group 1: Google Cloud TTS (premium id-ID)
            const optgroupGoogle = document.createElement('optgroup');
            optgroupGoogle.label = '☁️ Google Cloud TTS (Indonesia, Premium)';
            GOOGLE_TTS_VOICES.forEach((v) => {
                const opt = document.createElement('option');
                opt.value = 'google:' + v.name;
                opt.textContent = v.label;
                if (opt.value === selectedVoiceName) opt.selected = true;
                optgroupGoogle.appendChild(opt);
            });
            danaVoiceSelectEl.appendChild(optgroupGoogle);

            // Group 2: Web Speech API voices (kalau browser support)
            if (danaVoiceSupported) {
                const voices = window.speechSynthesis.getVoices() || [];
                const sorted = voices.slice().sort((a, b) => {
                    const aIsId = a.lang.startsWith('id') ? 0 : (a.lang.startsWith('en') ? 1 : 2);
                    const bIsId = b.lang.startsWith('id') ? 0 : (b.lang.startsWith('en') ? 1 : 2);
                    if (aIsId !== bIsId) return aIsId - bIsId;
                    return a.name.localeCompare(b.name);
                });

                let lastGroup = null;
                sorted.forEach((v) => {
                    const groupLabel = v.lang.startsWith('id') ? '🌐 Browser (Indonesia)'
                                    : v.lang.startsWith('en') ? '🌐 Browser (English)'
                                    : '🌐 Browser (Lainnya)';

                    if (groupLabel !== lastGroup) {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = groupLabel;
                        danaVoiceSelectEl.appendChild(optgroup);
                        lastGroup = groupLabel;
                    }

                    const opt = document.createElement('option');
                    opt.value = 'browser:' + v.name;
                    opt.textContent = `${v.name} (${v.lang})`;
                    if (opt.value === selectedVoiceName) opt.selected = true;
                    const lastOptgroup = danaVoiceSelectEl.lastElementChild;
                    lastOptgroup.appendChild(opt);
                });
            }
        }

        populateVoiceDropdown();
        if (danaVoiceSupported) {
            window.speechSynthesis.onvoiceschanged = populateVoiceDropdown;
        }

        danaVoiceSelectEl.addEventListener('change', () => {
            selectedVoiceName = danaVoiceSelectEl.value;
            localStorage.setItem(DANA_VOICE_NAME_STORAGE_KEY, selectedVoiceName);
            // Auto-preview saat ganti
            speakNominal(50000, 'Endra Putra');
        });

        /**
         * Convert angka ke kata Indonesia.
         * Support 0 - 999.999.999.999 (triliun).
         */
        function angkaKeKata(n) {
            n = Math.floor(Math.abs(Number(n) || 0));
            if (n === 0) return 'nol';

            const satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
                            'sepuluh', 'sebelas'];

            function ratusan(num) {
                if (num === 0) return '';
                if (num < 12) return satuan[num];
                if (num < 20) return satuan[num - 10] + ' belas';
                if (num < 100) {
                    const puluh = Math.floor(num / 10);
                    const sisa = num % 10;
                    return satuan[puluh] + ' puluh' + (sisa ? ' ' + satuan[sisa] : '');
                }
                if (num < 200) {
                    const sisa = num - 100;
                    return 'seratus' + (sisa ? ' ' + ratusan(sisa) : '');
                }
                const ratus = Math.floor(num / 100);
                const sisa = num % 100;
                return satuan[ratus] + ' ratus' + (sisa ? ' ' + ratusan(sisa) : '');
            }

            // Pecah ke triliun, miliar, juta, ribu, ratusan
            const segments = [
                { value: 1000000000000, label: 'triliun' },
                { value: 1000000000, label: 'miliar' },
                { value: 1000000, label: 'juta' },
                { value: 1000, label: 'ribu' },
            ];

            let result = '';
            let rem = n;

            for (const seg of segments) {
                if (rem >= seg.value) {
                    const count = Math.floor(rem / seg.value);
                    rem = rem % seg.value;
                    if (seg.label === 'ribu' && count === 1) {
                        result += 'seribu ';
                    } else {
                        result += ratusan(count) + ' ' + seg.label + ' ';
                    }
                }
            }

            if (rem > 0) {
                result += ratusan(rem);
            }

            return result.trim();
        }

        /**
         * Audio object untuk playback Google TTS. Dipakai untuk cancel/replace
         * supaya tidak antri kalau notif beruntun.
         */
        let currentTtsAudio = null;

        /**
         * Speak via Google Cloud TTS atau Web Speech API tergantung pilihan user.
         * Format selectedVoiceName:
         *   - "google:id-ID-Wavenet-A"  → server proxy
         *   - "browser:Microsoft Andika" → Web Speech API
         */
        function speakNominal(amount, senderName) {
            if (!danaVoiceEnabledEl.checked) return;

            const kata = angkaKeKata(amount);
            let teks = 'Diterima ' + kata + ' rupiah';
            if (senderName) {
                teks += ' dari ' + senderName;
            }

            const [provider, voiceName] = (selectedVoiceName || 'google:id-ID-Wavenet-A').split(':');

            if (provider === 'google') {
                speakViaGoogle(teks, voiceName);
            } else {
                speakViaBrowser(teks, voiceName);
            }
        }

        /**
         * Google Cloud TTS via server proxy.
         * Fallback ke Web Speech API kalau gagal (offline / quota habis).
         */
        async function speakViaGoogle(teks, voiceName) {
            // Cancel audio sebelumnya kalau masih jalan
            if (currentTtsAudio) {
                try { currentTtsAudio.pause(); } catch (e) { /* ignore */ }
                currentTtsAudio = null;
            }

            try {
                const res = await fetch(ttsSpeakEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'audio/mpeg, application/json',
                    },
                    body: JSON.stringify({
                        text: teks,
                        voice: voiceName || 'id-ID-Wavenet-A',
                        speed: 1.0,
                    }),
                });

                if (!res.ok) {
                    console.warn('Google TTS failed:', res.status, '— fallback ke Web Speech API');
                    speakViaBrowser(teks, '');
                    return;
                }

                const blob = await res.blob();
                if (blob.size === 0) {
                    console.warn('Google TTS returned empty audio — fallback');
                    speakViaBrowser(teks, '');
                    return;
                }

                const audioUrl = URL.createObjectURL(blob);
                currentTtsAudio = new Audio(audioUrl);
                currentTtsAudio.volume = 1.0;
                currentTtsAudio.onended = () => {
                    URL.revokeObjectURL(audioUrl);
                    currentTtsAudio = null;
                };
                currentTtsAudio.onerror = () => {
                    URL.revokeObjectURL(audioUrl);
                    currentTtsAudio = null;
                    console.warn('Audio playback error — fallback');
                    speakViaBrowser(teks, '');
                };
                await currentTtsAudio.play();
            } catch (e) {
                console.warn('Google TTS exception:', e.message, '— fallback ke Web Speech API');
                speakViaBrowser(teks, '');
            }
        }

        /**
         * Web Speech API (browser-native).
         */
        function speakViaBrowser(teks, voiceName) {
            if (!danaVoiceSupported) {
                console.warn('Web Speech API tidak support, audio tidak diputar');
                return;
            }

            try {
                window.speechSynthesis.cancel();

                const utterance = new SpeechSynthesisUtterance(teks);
                utterance.lang = 'id-ID';
                utterance.rate = 1.0;
                utterance.pitch = 1.0;
                utterance.volume = 1.0;

                const voices = window.speechSynthesis.getVoices();
                let voiceToUse = null;
                if (voiceName) {
                    voiceToUse = voices.find(v => v.name === voiceName);
                }
                if (!voiceToUse) {
                    voiceToUse = voices.find(v => v.lang === 'id-ID')
                              || voices.find(v => v.lang.startsWith('id'));
                }
                if (voiceToUse) utterance.voice = voiceToUse;

                window.speechSynthesis.speak(utterance);
            } catch (e) {
                console.warn('Voice synthesis error:', e);
            }
        }

        // Test button — preview suara dengan amount sample
        danaVoiceTestBtn.addEventListener('click', () => {
            speakNominal(50000, 'Endra Putra');
        });

        // State: id -> payment object (Map untuk maintain insertion order kalau perlu)
        const danaPaymentsById = new Map();
        let danaUnreadCount = 0;
        let danaInitialLoadComplete = false;
        // Cutoff: hanya treat sebagai "baru" kalau received_at_ms > cutoff (anti notif untuk historical)
        let danaInitialCutoffMs = Date.now();

        function formatRupiah(n) {
            const num = Math.round(Number(n) || 0);
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function formatTime(ts) {
            try {
                const d = ts instanceof Date ? ts : new Date(ts);
                if (isNaN(d.getTime())) return '-';
                const pad = (n) => String(n).padStart(2, '0');
                return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            } catch (e) {
                return '-';
            }
        }

        function formatRelativeTime(ms) {
            const diff = Math.floor((Date.now() - ms) / 1000);
            if (diff < 5) return 'baru saja';
            if (diff < 60) return diff + ' detik lalu';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            return Math.floor(diff / 86400) + ' hari lalu';
        }

        function isToday(ms) {
            if (!ms) return false;
            const d = new Date(ms);
            const now = new Date();
            return d.getFullYear() === now.getFullYear()
                && d.getMonth() === now.getMonth()
                && d.getDate() === now.getDate();
        }

        function renderDanaPaymentCard(payment) {
            const card = document.createElement('div');
            card.className = 'dana-payment-card';
            card.dataset.paymentId = String(payment.id);

            const receivedMs = payment.received_at_ms || (payment.received_at ? new Date(payment.received_at).getTime() : Date.now());

            const sender = payment.sender_name || '(Pengirim tidak terdeteksi)';
            const source = payment.source || 'DANA';
            const amount = formatRupiah(payment.amount);
            const timeStr = formatTime(receivedMs);
            const relativeStr = formatRelativeTime(receivedMs);

            card.innerHTML = `
                <div class="dana-payment-icon">💰</div>
                <div class="dana-payment-info">
                    <div class="dana-payment-amount">${amount}</div>
                    <div class="dana-payment-sender" title="${escapeHtml(sender)}">${escapeHtml(sender)}</div>
                    <div class="dana-payment-meta">
                        <span class="dana-source-badge">${escapeHtml(source)}</span>
                    </div>
                </div>
                <div class="dana-payment-time">
                    <div>${timeStr}</div>
                    <div class="dana-payment-time-relative" data-received-ms="${receivedMs}">${relativeStr}</div>
                </div>
            `;
            return card;
        }

        function updateDanaSummary() {
            // Filter hanya pembayaran hari ini
            const todayPayments = Array.from(danaPaymentsById.values()).filter(p => {
                const ms = p.received_at_ms || (p.received_at ? new Date(p.received_at).getTime() : 0);
                return isToday(ms);
            });

            const total = todayPayments.reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
            danaSummaryTotal.textContent = formatRupiah(total);
            danaSummaryCount.textContent = String(todayPayments.length);
        }

        function updateDanaBadge() {
            danaBadge.textContent = String(danaUnreadCount);
            if (danaUnreadCount > 0) {
                danaBadge.classList.remove('zero');
            } else {
                danaBadge.classList.add('zero');
            }
        }

        function refreshDanaContainer() {
            // Sort newest first
            const sorted = Array.from(danaPaymentsById.values()).sort((a, b) => {
                const am = a.received_at_ms || new Date(a.received_at || 0).getTime();
                const bm = b.received_at_ms || new Date(b.received_at || 0).getTime();
                return bm - am;
            });

            danaContainer.innerHTML = '';
            if (sorted.length === 0) {
                danaContainer.innerHTML = '<div class="no-dana-payments">Belum ada pembayaran DANA hari ini.</div>';
                return;
            }

            sorted.forEach((p) => {
                const card = renderDanaPaymentCard(p);
                danaContainer.appendChild(card);
            });
        }

        function notifyNewDanaPayment(payment) {
            // Voice — ucapkan nominal + sender (Bahasa Indonesia).
            // Tidak pakai chime ordermasuk.mp3 supaya beda dengan notif order masuk.
            speakNominal(payment.amount, payment.sender_name);

            // Toast
            const sender = payment.sender_name || 'Pembayaran masuk';
            const amount = formatRupiah(payment.amount);
            showToast(`💰 ${amount} dari ${sender}`, 'order');

            // Visual highlight on card (fresh class for 6 detik)
            setTimeout(() => {
                const card = danaContainer.querySelector(`.dana-payment-card[data-payment-id="${payment.id}"]`);
                if (card) {
                    card.classList.add('fresh');
                    setTimeout(() => card.classList.remove('fresh'), 6000);
                }
            }, 50);

            // Badge count (skip kalau tab DANA aktif)
            const danaTabActive = document.getElementById('tab-dana')?.classList.contains('active');
            if (!danaTabActive) {
                danaUnreadCount++;
                updateDanaBadge();
            }
        }

        async function loadInitialDanaPayments() {
            try {
                const today = new Date();
                const dateStr = today.getFullYear() + '-'
                    + String(today.getMonth() + 1).padStart(2, '0') + '-'
                    + String(today.getDate()).padStart(2, '0');

                const res = await fetch(danaPaymentsEndpoint + '?date=' + dateStr + '&limit=100', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (data && data.success && Array.isArray(data.payments)) {
                    data.payments.forEach((p) => {
                        const receivedMs = p.received_at ? new Date(p.received_at).getTime() : 0;
                        danaPaymentsById.set(p.id, {
                            id: p.id,
                            payhook_reference: p.payhook_reference,
                            amount: Number(p.amount) || 0,
                            source: p.source,
                            sender_name: p.sender_name,
                            notification_title: p.notification_title,
                            notification_text: p.notification_text,
                            notified_at: p.notified_at,
                            received_at: p.received_at,
                            received_at_ms: receivedMs,
                            firebase_key: p.firebase_key,
                        });
                    });
                }
            } catch (e) {
                console.warn('Gagal load initial DANA payments:', e.message);
            } finally {
                refreshDanaContainer();
                updateDanaSummary();
                // Setelah initial load: anggap semua data existing sudah di-acknowledge
                danaInitialCutoffMs = Date.now();
                danaInitialLoadComplete = true;
            }
        }

        function setDanaListeningStatus(online) {
            if (online) {
                danaSummaryStatus.classList.remove('status-offline');
                danaSummaryStatus.classList.add('status-listening');
                danaSummaryStatus.innerHTML = '<span class="status-dot"></span> Mendengarkan';
            } else {
                danaSummaryStatus.classList.remove('status-listening');
                danaSummaryStatus.classList.add('status-offline');
                danaSummaryStatus.innerHTML = '<span class="status-dot"></span> Offline';
            }
        }

        // Listener Firebase: /dana_payments
        // Pakai limitToLast(50) untuk hindari load semua history.
        const danaPaymentsRef = ref(database, 'dana_payments');
        const danaPaymentsQuery = query(danaPaymentsRef, limitToLast(50));

        loadInitialDanaPayments().then(() => {
            onValue(danaPaymentsQuery, (snapshot) => {
                if (!snapshot.exists()) {
                    return;
                }

                let hasNew = false;
                let newestPayment = null;

                snapshot.forEach((childSnapshot) => {
                    const fb = childSnapshot.val() || {};
                    const id = Number(fb.id) || 0;
                    if (!id) return;

                    const existing = danaPaymentsById.get(id);
                    if (!existing) {
                        const receivedMs = Number(fb.received_at_ms)
                            || (fb.received_at ? new Date(fb.received_at).getTime() : Date.now());

                        const payment = {
                            id,
                            payhook_reference: fb.payhook_reference,
                            amount: Number(fb.amount) || 0,
                            source: fb.source,
                            sender_name: fb.sender_name,
                            notification_title: fb.notification_title,
                            notification_text: fb.notification_text,
                            notified_at: fb.notified_at,
                            received_at: fb.received_at,
                            received_at_ms: receivedMs,
                            firebase_key: childSnapshot.key,
                        };

                        danaPaymentsById.set(id, payment);

                        // Hanya treat sebagai notif baru kalau diterima setelah initial cutoff
                        if (danaInitialLoadComplete && receivedMs > danaInitialCutoffMs) {
                            hasNew = true;
                            if (!newestPayment || receivedMs > (newestPayment.received_at_ms || 0)) {
                                newestPayment = payment;
                            }
                        }
                    }
                });

                refreshDanaContainer();
                updateDanaSummary();

                if (hasNew && newestPayment) {
                    notifyNewDanaPayment(newestPayment);
                }

                setDanaListeningStatus(true);
            }, (error) => {
                console.warn('Firebase /dana_payments listener error:', error.message);
                setDanaListeningStatus(false);
            });
        });

        // Reset badge saat tab DANA aktif
        const origSwitchTabHandler = window.switchTab;
        window.switchTab = function(tabName) {
            origSwitchTabHandler(tabName);
            if (tabName === 'dana') {
                danaUnreadCount = 0;
                updateDanaBadge();
            }
        };

        // Update relative timestamp tiap 30 detik
        setInterval(() => {
            const elements = danaContainer.querySelectorAll('.dana-payment-time-relative[data-received-ms]');
            elements.forEach((el) => {
                const ms = Number(el.dataset.receivedMs);
                if (ms) el.textContent = formatRelativeTime(ms);
            });
        }, 30000);
    </script>
</body>

</html>
