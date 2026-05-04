<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Dashboard')</title>
    @stack('styles')
    <style>
        /* ===== Design Tokens ===== */
        :root {
            --color-primary: #667eea;
            --color-primary-dark: #5568d3;
            --color-primary-bg: #eef2ff;
            --color-success: #16a34a;
            --color-success-bg: #f0fdf4;
            --color-success-border: #bbf7d0;
            --color-warning: #d97706;
            --color-warning-bg: #fffbeb;
            --color-warning-border: #fde68a;
            --color-danger: #dc2626;
            --color-danger-bg: #fef2f2;
            --color-danger-border: #fecaca;
            --color-info: #0284c7;
            --color-info-bg: #f0f9ff;
            --color-info-border: #bae6fd;
            --color-text: #0f172a;
            --color-text-secondary: #475569;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --color-bg: #f8fafc;
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.1);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .navbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            font-size: clamp(16px, 4vw, 20px);
            white-space: nowrap;
        }

        .navbar nav {
            display: grid;
            width: 100%;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }

        .nav-group {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 10px;
            padding: 10px;
        }

        .nav-group-title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.92);
            margin-bottom: 8px;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 7px 11px;
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 13px;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-link.is-active {
            background: rgba(255, 255, 255, 0.28);
            font-weight: 700;
        }

        .nav-link.is-danger {
            background: rgba(220, 53, 69, 0.75);
        }

        .nav-link.is-danger:hover {
            background: rgba(220, 53, 69, 0.9);
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 5px;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background: white;
            margin: 3px 0;
            border-radius: 2px;
            transition: 0.3s;
        }

        /* Mobile Menu */
        @media (max-width: 768px) {
            .navbar-container {
                gap: 10px;
            }

            .hamburger {
                display: flex;
            }

            .navbar nav {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 5px;
                margin-top: 0;
                padding-top: 12px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
            }

            .navbar nav.active {
                display: flex;
            }

            .nav-group {
                width: 100%;
            }

            .nav-links {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .nav-link {
                width: 100%;
                text-align: left;
                padding: 10px 12px;
            }

            /* Hamburger Animation */
            .hamburger.active span:nth-child(1) {
                transform: rotate(45deg) translate(8px, 8px);
            }

            .hamburger.active span:nth-child(2) {
                opacity: 0;
            }

            .hamburger.active span:nth-child(3) {
                transform: rotate(-45deg) translate(7px, -7px);
            }
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-scroll table {
            min-width: 100%;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        @media (max-width: 768px) {
            table {
                font-size: 14px;
            }

            table th,
            table td {
                padding: 8px;
            }
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* ===== Dashboard Status Badges ===== */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status.done {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status.pending {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde68a;
        }

        .status.overdue {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ===== Dashboard KPI Cards ===== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border-left: 4px solid transparent;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .kpi-card.kpi-blue { border-left-color: #667eea; }
        .kpi-card.kpi-green { border-left-color: #22c55e; }
        .kpi-card.kpi-amber { border-left-color: #f59e0b; }
        .kpi-card.kpi-red { border-left-color: #ef4444; }

        .kpi-icon {
            font-size: 28px;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-size: clamp(28px, 6vw, 40px);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .kpi-card.kpi-blue .kpi-value { color: #667eea; }
        .kpi-card.kpi-green .kpi-value { color: #16a34a; }
        .kpi-card.kpi-amber .kpi-value { color: #d97706; }
        .kpi-card.kpi-red .kpi-value { color: #dc2626; }

        .kpi-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .kpi-trend {
            font-size: 12px;
            margin-top: 6px;
            font-weight: 600;
        }

        .kpi-trend.up { color: #16a34a; }
        .kpi-trend.down { color: #dc2626; }
        .kpi-trend.neutral { color: #94a3b8; }

        /* ===== Dashboard Section Headers ===== */
        .section-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .section-title {
            margin: 0;
            font-size: clamp(18px, 4vw, 22px);
            font-weight: 700;
        }

        .section-subtitle {
            font-size: 12px;
            color: #64748b;
        }

        /* ===== Dashboard Summary Pills ===== */
        .summary-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }

        /* ===== Dashboard Bar Chart (CSS-only) ===== */
        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 14px;
        }

        .bar-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bar-label {
            min-width: 100px;
            max-width: 140px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bar-track {
            flex: 1;
            height: 26px;
            background: #f1f5f9;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }

        .bar-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            min-width: 32px;
            transition: width 0.6s ease;
        }

        .bar-fill.gold { background: linear-gradient(90deg, #f59e0b, #eab308); }
        .bar-fill.silver { background: linear-gradient(90deg, #94a3b8, #64748b); }
        .bar-fill.bronze { background: linear-gradient(90deg, #d97706, #b45309); }

        .bar-count {
            font-size: 12px;
            font-weight: 700;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .bar-rank {
            min-width: 28px;
            font-size: 16px;
            text-align: center;
        }

        /* ===== Dashboard Data Panel ===== */
        .data-panel {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }

        .data-panel-header {
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-panel-header.blue { background: #eef2ff; color: #1e3a8a; }
        .data-panel-header.teal { background: #ecfeff; color: #0f766e; }
        .data-panel-header.gray { background: #f8fafc; color: #0f172a; }
        .data-panel-header.orange { background: #fff7ed; color: #9a3412; }

        /* ===== Dashboard Filter Area ===== */
        .filter-area {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .filter-area label {
            display: block;
            font-size: 13px;
            color: #475569;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .filter-area .input {
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .filter-area .input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .filter-hint {
            font-size: 12px;
            color: #94a3b8;
            padding-top: 6px;
        }

        /* ===== Dashboard Grid Layouts ===== */
        .dashboard-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
            gap: 16px;
        }

        /* ===== Follow-up Card Layout (Mobile) ===== */
        .followup-cards {
            display: none;
        }

        @media (max-width: 900px) {
            .followup-table-wrap {
                display: none;
            }

            .followup-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .followup-card {
                border: 1px solid #fed7aa;
                border-radius: 10px;
                padding: 14px;
                background: #fffbeb;
            }

            .followup-card-name {
                font-weight: 700;
                font-size: 15px;
                color: #1e293b;
                margin-bottom: 4px;
            }

            .followup-card-email {
                font-size: 12px;
                color: #64748b;
                margin-bottom: 10px;
            }

            .followup-card-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .followup-card-item {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .followup-card-item-label {
                font-size: 12px;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }

            .followup-card-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #fde68a;
            }

            .attention-tag {
                display: inline-flex;
                align-items: center;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 12px;
                font-weight: 700;
                background: #fff7ed;
                color: #9a3412;
                border: 1px solid #fdba74;
            }
        }

        @media (min-width: 901px) {
            .followup-table-wrap {
                display: block;
            }
        }

        /* ===== Quick Actions ===== */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .quick-actions .btn {
            flex: 1 1 auto;
            min-width: 150px;
            text-align: center;
            font-size: 14px;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
        }

        /* ===== Empty State ===== */
        .empty {
            padding: 16px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px dashed #cbd5e1;
        }

        .empty.success {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }

        /* ===== Alert Danger ===== */
        .alert-danger {
            background: var(--color-danger-bg);
            border: 1px solid var(--color-danger-border);
            color: #991b1b;
        }

        .alert-warning {
            background: var(--color-warning-bg);
            border: 1px solid var(--color-warning-border);
            color: #92400e;
        }

        .alert-info {
            background: var(--color-info-bg);
            border: 1px solid var(--color-info-border);
            color: #0c4a6e;
        }

        /* ===== Shared Form Classes ===== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--color-text-secondary);
            font-weight: 600;
            font-size: 14px;
        }

        .form-label-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--color-text-secondary);
            cursor: pointer;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 15px;
            color: var(--color-text);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-textarea {
            resize: vertical;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .form-hint {
            font-size: 13px;
            color: var(--color-text-muted);
            margin-top: 6px;
        }

        .form-error {
            color: var(--color-danger);
            font-size: 12px;
            margin-top: 4px;
        }

        .form-info-box {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 12px;
            color: var(--color-text-secondary);
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ===== Page Header ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-title {
            color: var(--color-text);
            margin: 0;
            font-size: clamp(24px, 5vw, 32px);
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--color-text-muted);
            margin-top: 6px;
        }

        /* ===== Accessible Status Badges ===== */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-status::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .badge-status.active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-status.active::before {
            background: #16a34a;
        }

        .badge-status.inactive {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-status.inactive::before {
            background: #94a3b8;
        }

        /* ===== Confirm Dialog (two-step) ===== */
        .confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 2000;
        }

        .confirm-overlay.is-open {
            display: flex;
        }

        .confirm-box {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 24px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
        }

        .confirm-box h3 {
            margin: 0 0 8px;
            color: var(--color-danger);
            font-size: 18px;
        }

        .confirm-box p {
            margin: 0 0 16px;
            color: var(--color-text-secondary);
            font-size: 14px;
            line-height: 1.5;
        }

        .confirm-input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--color-danger-border);
            border-radius: var(--radius-md);
            font-size: 15px;
            margin-bottom: 16px;
        }

        .confirm-input:focus {
            outline: none;
            border-color: var(--color-danger);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }

        .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* ===== Responsive Card Layout for Tables ===== */
        @media (max-width: 768px) {
            .mobile-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .mobile-card {
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 14px;
                background: #fff;
                box-shadow: var(--shadow-sm);
            }

            .mobile-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 10px;
            }

            .mobile-card-title {
                font-weight: 700;
                color: var(--color-text);
                font-size: 15px;
            }

            .mobile-card-subtitle {
                font-size: 12px;
                color: var(--color-text-muted);
                margin-top: 2px;
            }

            .mobile-card-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 10px;
            }

            .mobile-card-field {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .mobile-card-field-label {
                font-size: 12px;
                font-weight: 600;
                color: var(--color-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }

            .mobile-card-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                padding-top: 10px;
                border-top: 1px solid var(--color-border);
            }
        }

        /* ===== Btn Secondary ===== */
        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-info {
            background: var(--color-info);
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-top">
                <h1>📋 Order App Admin</h1>
                <div class="hamburger" onclick="toggleMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <nav id="navMenu">
                <div class="nav-group">
                    <div class="nav-group-title">Ringkasan</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>
                        <a class="nav-link {{ request()->routeIs('admin.current_order.*') ? 'is-active' : '' }}" href="{{ route('admin.current_order.index') }}">🧾 Current Order</a>
                        <a class="nav-link {{ request()->routeIs('admin.test_order') ? 'is-active' : '' }}" href="{{ route('admin.test_order') }}">🧪 Test Order</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Tim & Area</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.waiters.*') ? 'is-active' : '' }}" href="{{ route('admin.waiters.index') }}">👥 Waiters</a>
                        <a class="nav-link {{ request()->routeIs('admin.racks.*') ? 'is-active' : '' }}" href="{{ route('admin.racks.index') }}">📦 Racks</a>
<a class="nav-link {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}" href="{{ route('admin.products.index') }}">🏷️ Produk</a>
<a class="nav-link {{ request()->routeIs('admin.product_categories.*') ? 'is-active' : '' }}" href="{{ route('admin.product_categories.index') }}">📂 Kategori</a>
<a class="nav-link {{ request()->routeIs('admin.shifts.*') ? 'is-active' : '' }}" href="{{ route('admin.shifts.index') }}">⏰ Shift</a>
<a class="nav-link {{ request()->routeIs('admin.schedules.*') ? 'is-active' : '' }}" href="{{ route('admin.schedules.index') }}">📅 Jadwal</a>
                        <a class="nav-link" href="{{ route('waiter.login') }}" target="_blank" rel="noopener">🧑‍🍳 Portal Waiter</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Operasional</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.tasks.index') ? 'is-active' : '' }}" href="{{ route('admin.tasks.index') }}">📝 Tugas Umum</a>
                        <a class="nav-link {{ request()->routeIs('admin.tasks.rack.*') ? 'is-active' : '' }}" href="{{ route('admin.tasks.rack.index') }}">📦 Cek Rak</a>
                        <a class="nav-link {{ request()->routeIs('admin.attendance.*') ? 'is-active' : '' }}" href="{{ route('admin.attendance.index') }}">📋 Absensi</a>
                        <a class="nav-link {{ request()->routeIs('admin.cleanup') ? 'is-active' : '' }}" href="{{ route('admin.cleanup') }}">🧹 Cleanup</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Bonus & Performa</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.bonus.config') ? 'is-active' : '' }}" href="{{ route('admin.bonus.config') }}">⚙️ Konfigurasi</a>
                        <a class="nav-link {{ request()->routeIs('admin.bonus.daily_scoring') ? 'is-active' : '' }}" href="{{ route('admin.bonus.daily_scoring') }}">📊 Penilaian Harian</a>
                        <a class="nav-link {{ request()->routeIs('admin.bonus.penalties') ? 'is-active' : '' }}" href="{{ route('admin.bonus.penalties') }}">⚠️ Penalti</a>
                        <a class="nav-link {{ request()->routeIs('admin.bonus.sales_targets') ? 'is-active' : '' }}" href="{{ route('admin.bonus.sales_targets') }}">💰 Target Penjualan</a>
                        <a class="nav-link {{ request()->routeIs('admin.bonus.monthly_summary') ? 'is-active' : '' }}" href="{{ route('admin.bonus.monthly_summary') }}">📋 Rekap Bulanan</a>
                        <a class="nav-link {{ request()->routeIs('admin.bonus.leaderboard') ? 'is-active' : '' }}" href="{{ route('admin.bonus.leaderboard') }}">🏆 Leaderboard</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Sistem</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.settings') ? 'is-active' : '' }}" href="{{ route('admin.settings') }}">⚙️ Settings</a>
                        <a class="nav-link is-danger" href="{{ route('admin.logout') }}">🚪 Logout</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        @yield('content')
    </div>

    <script>
        function toggleMenu() {
            const nav = document.getElementById('navMenu');
            const hamburger = document.querySelector('.hamburger');
            nav.classList.toggle('active');
            hamburger.classList.toggle('active');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function (event) {
            const nav = document.getElementById('navMenu');
            const hamburger = document.querySelector('.hamburger');
            const navbar = document.querySelector('.navbar');

            if (!navbar.contains(event.target)) {
                nav.classList.remove('active');
                hamburger.classList.remove('active');
            }
        });

        // Close menu when window is resized to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                const nav = document.getElementById('navMenu');
                const hamburger = document.querySelector('.hamburger');
                nav.classList.remove('active');
                hamburger.classList.remove('active');
            }
        });
    </script>
    @stack('scripts')
</body>

</html>
