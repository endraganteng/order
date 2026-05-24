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

        /* ===== Navbar ===== */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            height: 56px;
            position: relative;
        }

        .navbar-brand {
            font-size: 17px;
            font-weight: 700;
            white-space: nowrap;
            margin-right: 24px;
            text-decoration: none;
            color: white;
        }

        /* ===== Desktop Nav ===== */
        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
            height: 100%;
        }

        .navbar-menu > li {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 4px;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
            border: none;
            background: transparent;
        }

        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Direct nav links (no dropdown) — same style as dropdown-toggle */
        .nav-link-direct {
            display: flex;
            align-items: center;
            gap: 4px;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .nav-link-direct:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .nav-link-direct.is-active-group {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: inset 0 -2px 0 white;
        }

        /* Fix B: Active-group indicator */
        .dropdown-toggle.is-active-group {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: inset 0 -2px 0 white;
        }

        @media (max-width: 768px) {
            .dropdown-toggle.is-active-group {
                box-shadow: none;
                background: rgba(255, 255, 255, 0.12);
            }
            .dropdown-toggle.is-active-group::before {
                content: '';
                display: inline-block;
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background: white;
                margin-right: 8px;
                flex-shrink: 0;
            }
        }

        .dropdown-toggle .caret {
            font-size: 10px;
            transition: transform 0.2s;
        }

        /* Dropdown Panel */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            padding: 6px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(4px);
            transition: opacity 0.2s, transform 0.2s, visibility 0.2s;
            z-index: 1001;
        }

        .navbar-menu > li:hover > .dropdown-menu,
        .navbar-menu > li.open > .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            display: block;
            padding: 9px 16px;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.15s;
            white-space: nowrap;
        }

        .dropdown-menu a:hover {
            background: var(--color-primary-bg);
            color: var(--color-primary-dark);
        }

        .dropdown-menu a.is-active {
            background: var(--color-primary-bg);
            color: var(--color-primary);
            font-weight: 600;
        }

        .dropdown-menu a.is-danger {
            color: var(--color-danger);
        }

        .dropdown-menu a.is-danger:hover {
            background: var(--color-danger-bg);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--color-border);
            margin: 4px 0;
        }

        .dropdown-menu .submenu-label {
            display: block;
            padding: 8px 16px 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.5px;
        }

        /* Nested submenu (flyout) */
        .dropdown-menu .has-submenu {
            position: relative;
        }
        .dropdown-menu .has-submenu > .submenu-toggle {
            display: block;
            padding: 9px 16px;
            color: #334155;
            font-size: 13px;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .dropdown-menu .has-submenu > .submenu-toggle::after {
            content: '▸';
            float: right;
            margin-left: 8px;
            color: #94a3b8;
        }
        .dropdown-menu .has-submenu:hover > .submenu-toggle,
        .dropdown-menu .has-submenu.is-active-sub > .submenu-toggle {
            background: var(--color-primary-bg);
            color: var(--color-primary-dark);
        }
        .dropdown-menu .submenu-panel {
            position: absolute;
            left: 100%;
            top: 0;
            min-width: 180px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            padding: 6px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateX(4px);
            transition: opacity 0.15s, transform 0.15s, visibility 0.15s;
            z-index: 1002;
        }
        .dropdown-menu .has-submenu:hover > .submenu-panel {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }
        }
        }

        /* ===== Hamburger ===== */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 11px 11px;
            min-width: 44px;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            border: none;
            background: transparent;
        }

        .hamburger:focus-visible {
            outline: 2px solid rgba(255,255,255,0.8);
            outline-offset: 2px;
            border-radius: var(--radius-sm);
        }

        .hamburger span {
            width: 22px;
            height: 2.5px;
            background: white;
            margin: 2.5px 0;
            border-radius: 2px;
            transition: 0.3s;
        }

        /* ===== Mobile ===== */
        @media (max-width: 768px) {
            /* Fix D: keep header row non-wrapping */
            .navbar-container {
                flex-wrap: nowrap;
            }

            .hamburger {
                display: flex;
            }

            /* Fix D: Drawer backdrop */
            .nav-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                z-index: 1009;
                opacity: 0;
                transition: opacity 0.22s ease;
            }

            .nav-backdrop.active {
                display: block;
                opacity: 1;
            }

            /* Fix D: Drawer panel */
            .navbar-menu {
                position: fixed;
                top: 0;
                right: 0;
                width: min(85vw, 320px);
                height: 100vh;
                height: 100dvh;
                background: linear-gradient(160deg, #667eea 0%, #764ba2 100%);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                flex-direction: column;
                gap: 0;
                padding: 64px 0 16px;
                border-top: none;
                list-style: none;
                z-index: 1010;
                transform: translateX(100%);
                opacity: 0;
                transition: transform 0.23s ease, opacity 0.23s ease;
                display: flex;
                pointer-events: none;
            }

            .navbar-menu.active {
                transform: translateX(0);
                opacity: 1;
                pointer-events: auto;
            }

            .navbar-menu > li {
                flex-direction: column;
                height: auto;
                align-items: stretch;
            }

            .dropdown-toggle {
                width: 100%;
                justify-content: space-between;
                padding: 12px 16px;
                border-radius: 0;
            }

            .dropdown-menu {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                box-shadow: none;
                background: rgba(255, 255, 255, 0.08);
                border-radius: 0;
                display: none;
                padding: 4px 0;
            }

            .navbar-menu > li.open > .dropdown-menu {
                display: block;
            }

            .dropdown-menu a {
                color: rgba(255, 255, 255, 0.9);
                padding: 9px 20px 9px 28px;
            }

            .dropdown-menu a:hover {
                background: rgba(255, 255, 255, 0.12);
                color: white;
            }

            .dropdown-menu a.is-active {
                background: rgba(255, 255, 255, 0.18);
                color: white;
            }

            .dropdown-menu a.is-danger {
                color: #fca5a5;
            }

            .dropdown-toggle .caret {
                transition: transform 0.2s;
            }

            .navbar-menu > li.open > .dropdown-toggle .caret {
                transform: rotate(180deg);
            }

            /* Hamburger Animation */
            .hamburger.active span:nth-child(1) {
                transform: rotate(45deg) translate(5px, 5px);
            }

            .hamburger.active span:nth-child(2) {
                opacity: 0;
            }

            .hamburger.active span:nth-child(3) {
                transform: rotate(-45deg) translate(5px, -5px);
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

        /* Fix E: User identity chip — mobile only */
        .nav-user-chip {
            display: none;
        }

        @media (max-width: 768px) {
            .nav-user-chip {
                display: flex;
                align-items: center;
                gap: 5px;
                margin-left: auto;
                margin-right: 6px;
                background: rgba(255,255,255,0.18);
                border-radius: 20px;
                padding: 4px 8px;
                max-width: 100px;
                height: 28px;
                overflow: hidden;
                flex-shrink: 0;
                text-decoration: none;
                color: white;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }

            .nav-user-chip-text {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }
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
    @php $adminRole = session('admin_role', 'supervisor'); @endphp
    @php
        $permService = app(\App\Services\RolePermissionService::class);
        $allowedGroups = $permService->getAllowedGroups($adminRole);
    @endphp
    <header class="navbar">
        <div class="navbar-container">
            <a class="navbar-brand" href="{{ $adminRole === 'finance' ? route('admin.finance.dashboard') : route('admin.dashboard') }}">
                {{ $adminRole === 'finance' ? '💰 Manager Keuangan' : '📋 Supervisor' }}
            </a>

            @php
                $navUserName = session('admin_email')
                    ? explode('@', session('admin_email'))[0]
                    : (auth()->check() ? auth()->user()->name : 'Admin');
                $navUserName = mb_strtolower($navUserName);
                $navUserShort = mb_substr($navUserName, 0, 10);
            @endphp
            <a class="nav-user-chip" href="{{ route('admin.settings') }}" title="{{ $navUserName }}">
                <span>👤</span>
                <span class="nav-user-chip-text">{{ $navUserShort }}</span>
            </a>

            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

            <ul class="navbar-menu" id="navMenu">
                @php
                    $grpRingkasan  = request()->routeIs(['admin.dashboard','admin.current_order.*','admin.test_order']);
                    $grpTim        = request()->routeIs(['admin.waiters.*','admin.racks.*','admin.products.*','admin.product_categories.*','admin.shifts.*','admin.schedules.*']);
                    $grpOps        = request()->routeIs(['admin.tasks.live','admin.tasks.index','admin.tasks.rack.*','admin.restock.*','admin.suppliers.*','admin.attendance.*','admin.cleanup','admin.reconciliation.*']);
                    $grpBonus      = request()->routeIs(['admin.bonus.config','admin.bonus.daily_scoring','admin.bonus.penalties','admin.bonus.manual_bonus','admin.bonus.sales_targets','admin.bonus.monthly_summary','admin.bonus.leaderboard']);
                    $grpPayroll    = request()->routeIs(['admin.payroll.index','admin.payroll.show','admin.payroll.withdrawals']);
                    $grpFinance    = request()->routeIs('admin.finance.*') || $grpPayroll;
                    $grpSistem     = request()->routeIs(['admin.audit_log.*','admin.settings']);
                    $grpAi         = request()->routeIs(['admin.ai_products.*','admin.ai_chat.*']);
                @endphp
                @if(in_array('ringkasan', $allowedGroups))
                {{-- Ringkasan --}}
                <li class="{{ $grpRingkasan ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpRingkasan ? 'is-active-group' : '' }}">Ringkasan <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>
                        <a class="{{ request()->routeIs('admin.current_order.*') ? 'is-active' : '' }}" href="{{ route('admin.current_order.index') }}">🧾 Current Order</a>
                        <a class="{{ request()->routeIs('admin.test_order') ? 'is-active' : '' }}" href="{{ route('admin.test_order') }}">🧪 Test Order</a>
                    </div>
                </li>
                @endif

                @if(in_array('tim_area', $allowedGroups))
                {{-- Tim & Area --}}
                <li class="{{ $grpTim ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpTim ? 'is-active-group' : '' }}">Tim & Area <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.waiters.*') ? 'is-active' : '' }}" href="{{ route('admin.waiters.index') }}">👥 Waiters</a>
                        <a class="{{ request()->routeIs('admin.racks.*') ? 'is-active' : '' }}" href="{{ route('admin.racks.index') }}">📦 Racks</a>
                        <a class="{{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}" href="{{ route('admin.products.index') }}">🏷️ Produk</a>
                        <a class="{{ request()->routeIs('admin.product_categories.*') ? 'is-active' : '' }}" href="{{ route('admin.product_categories.index') }}">📂 Kategori</a>
                        <div class="dropdown-divider"></div>
                        <a class="{{ request()->routeIs('admin.shifts.*') ? 'is-active' : '' }}" href="{{ route('admin.shifts.index') }}">⏰ Shift</a>
                        <a class="{{ request()->routeIs('admin.schedules.*') ? 'is-active' : '' }}" href="{{ route('admin.schedules.index') }}">📅 Jadwal</a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('waiter.login') }}" target="_blank" rel="noopener">🧑‍🍳 Portal Waiter ↗</a>
                    </div>
                </li>
                @endif

                @if(in_array('operasional', $allowedGroups))
                {{-- Operasional --}}
                <li class="{{ $grpOps ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpOps ? 'is-active-group' : '' }}">Operasional <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.tasks.live') ? 'is-active' : '' }}" href="{{ route('admin.tasks.live') }}">📡 Live Monitor</a>
                        <a class="{{ request()->routeIs('admin.tasks.index') ? 'is-active' : '' }}" href="{{ route('admin.tasks.index') }}">📝 Tugas Umum</a>
                        <a class="{{ request()->routeIs('admin.tasks.rack.*') ? 'is-active' : '' }}" href="{{ route('admin.tasks.rack.index') }}">📦 Cek Rak</a>
                        <div class="dropdown-divider"></div>
                        <a class="{{ request()->routeIs('admin.restock.*') ? 'is-active' : '' }}" href="{{ route('admin.restock.index') }}">📦 Restock & PO</a>
                        <a class="{{ request()->routeIs('admin.suppliers.*') ? 'is-active' : '' }}" href="{{ route('admin.suppliers.index') }}">🏪 Supplier</a>
                        <div class="dropdown-divider"></div>
                        <a class="{{ request()->routeIs('admin.attendance.*') ? 'is-active' : '' }}" href="{{ route('admin.attendance.index') }}">📋 Absensi</a>
                        <a class="{{ request()->routeIs('admin.reconciliation.*') ? 'is-active' : '' }}" href="{{ route('admin.reconciliation.index') }}">🔍 Reconciliation</a>
                        <a class="{{ request()->routeIs('admin.dana_payments.*') ? 'is-active' : '' }}" href="{{ route('admin.dana_payments.index') }}">💰 DANA Masuk</a>
                        <a class="{{ request()->routeIs('admin.cleanup') ? 'is-active' : '' }}" href="{{ route('admin.cleanup') }}">🧹 Cleanup</a>
                    </div>
                </li>
                @endif

                @if(in_array('bonus', $allowedGroups))
                {{-- Bonus & Performa --}}
                <li class="{{ $grpBonus ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpBonus ? 'is-active-group' : '' }}">Bonus <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.bonus.config') ? 'is-active' : '' }}" href="{{ route('admin.bonus.config') }}">⚙️ Konfigurasi</a>
                        <a class="{{ request()->routeIs('admin.bonus.daily_scoring') ? 'is-active' : '' }}" href="{{ route('admin.bonus.daily_scoring') }}">📊 Penilaian Harian</a>
                        <a class="{{ request()->routeIs('admin.bonus.penalties') ? 'is-active' : '' }}" href="{{ route('admin.bonus.penalties') }}">⚠️ Penalti</a>
                        <a class="{{ request()->routeIs('admin.bonus.manual_bonus') ? 'is-active' : '' }}" href="{{ route('admin.bonus.manual_bonus') }}">🎁 Bonus Manual</a>
                        <div class="dropdown-divider"></div>
                        <a class="{{ request()->routeIs('admin.bonus.sales_targets') ? 'is-active' : '' }}" href="{{ route('admin.bonus.sales_targets') }}">💰 Target Penjualan</a>
                        <a class="{{ request()->routeIs('admin.bonus.monthly_summary') ? 'is-active' : '' }}" href="{{ route('admin.bonus.monthly_summary') }}">📋 Rekap Bulanan</a>
                        <a class="{{ request()->routeIs('admin.bonus.leaderboard') ? 'is-active' : '' }}" href="{{ route('admin.bonus.leaderboard') }}">🏆 Leaderboard</a>
                    </div>
                </li>
                @endif

                @if(in_array('keuangan', $allowedGroups) && $adminRole === 'finance')
                {{-- Menu Finance (finance role layout) --}}
                <li class="{{ request()->routeIs('admin.finance_dashboard') || request()->routeIs('admin.finance.dashboard') ? 'is-active-group' : '' }}">
                    <a href="{{ route('admin.finance.dashboard') }}" class="nav-link-direct {{ request()->routeIs('admin.finance.dashboard') ? 'is-active-group' : '' }}">🏠 Dashboard</a>
                </li>
                <li class="{{ $grpPayroll ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpPayroll ? 'is-active-group' : '' }}">💰 Payroll <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.payroll.index') ? 'is-active' : '' }}" href="{{ route('admin.payroll.index') }}">👥 Karyawan & Saldo</a>
                        <a class="{{ request()->routeIs('admin.payroll.withdrawals') ? 'is-active' : '' }}" href="{{ route('admin.payroll.withdrawals') }}">📋 Penarikan Gaji</a>
                    </div>
                </li>
                <li class="{{ request()->routeIs(['admin.finance.mutations','admin.finance.expenses','admin.finance.debts','admin.finance.shifts']) ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ request()->routeIs(['admin.finance.mutations','admin.finance.expenses','admin.finance.debts','admin.finance.shifts']) ? 'is-active-group' : '' }}">💸 Kas <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.finance.mutations') ? 'is-active' : '' }}" href="{{ route('admin.finance.mutations') }}">📒 Mutasi Kas</a>
                        <a class="{{ request()->routeIs('admin.finance.expenses') ? 'is-active' : '' }}" href="{{ route('admin.finance.expenses') }}">💸 Pengeluaran</a>
                        <a class="{{ request()->routeIs('admin.finance.debts') ? 'is-active' : '' }}" href="{{ route('admin.finance.debts') }}">📋 Hutang Supplier</a>
                        <a class="{{ request()->routeIs('admin.finance.shifts') ? 'is-active' : '' }}" href="{{ route('admin.finance.shifts') }}">🕐 Detail Shift</a>
                        <a class="{{ request()->routeIs('admin.dana_payments.*') ? 'is-active' : '' }}" href="{{ route('admin.dana_payments.index') }}">💰 DANA Masuk</a>
                    </div>
                </li>
                @if(in_array('laporan_keuangan', $allowedGroups))
                <li class="{{ request()->routeIs(['admin.finance.budget','admin.finance.report.*','admin.finance.audit_log']) ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ request()->routeIs(['admin.finance.budget','admin.finance.report.*','admin.finance.audit_log']) ? 'is-active-group' : '' }}">📈 Laporan <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.finance.budget') ? 'is-active' : '' }}" href="{{ route('admin.finance.budget') }}">📊 Budget vs Realisasi</a>
                        <a class="{{ request()->routeIs('admin.finance.report.monthly') ? 'is-active' : '' }}" href="{{ route('admin.finance.report.monthly') }}">📈 Laporan Bulanan</a>
                        <a class="{{ request()->routeIs('admin.finance.report.balance') ? 'is-active' : '' }}" href="{{ route('admin.finance.report.balance') }}">💳 Saldo Kas</a>
                        <a class="{{ request()->routeIs('admin.finance.laba_rugi') ? 'is-active' : '' }}" href="{{ route('admin.finance.laba_rugi') }}">📊 Laba Rugi</a>
                        <a class="{{ request()->routeIs('admin.finance.tutup_buku') ? 'is-active' : '' }}" href="{{ route('admin.finance.tutup_buku') }}">📕 Tutup Buku</a>
                        <a class="{{ request()->routeIs('admin.finance.audit_log') ? 'is-active' : '' }}" href="{{ route('admin.finance.audit_log') }}">📜 Audit Log</a>
                    </div>
                </li>
                @endif
                @if(in_array('setting_keuangan', $allowedGroups))
                <li class="{{ request()->routeIs(['admin.finance.sync','admin.finance.settings','admin.finance.sync_logs','admin.finance.mappings.*','admin.finance.categories','admin.finance.allocations']) ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ request()->routeIs(['admin.finance.sync','admin.finance.settings','admin.finance.sync_logs','admin.finance.mappings.*','admin.finance.categories','admin.finance.allocations']) ? 'is-active-group' : '' }}">⚙️ Setting <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.finance.sync') ? 'is-active' : '' }}" href="{{ route('admin.finance.sync') }}">🔄 Sinkronisasi</a>
                        <a class="{{ request()->routeIs('admin.finance.settings') ? 'is-active' : '' }}" href="{{ route('admin.finance.settings') }}">⚙️ Pengaturan Sync</a>
                        <a class="{{ request()->routeIs('admin.finance.sync_logs') ? 'is-active' : '' }}" href="{{ route('admin.finance.sync_logs') }}">📋 Riwayat Sync</a>
                        <div class="dropdown-divider"></div>
                        <a class="{{ request()->routeIs('admin.finance.mappings.category') ? 'is-active' : '' }}" href="{{ route('admin.finance.mappings.category') }}">🏷️ Mapping Kategori</a>
                        <a class="{{ request()->routeIs('admin.finance.mappings.account') ? 'is-active' : '' }}" href="{{ route('admin.finance.mappings.account') }}">🏦 Mapping Akun Kas</a>
                        <div class="dropdown-divider"></div>
                        <a class="{{ request()->routeIs('admin.finance.categories') ? 'is-active' : '' }}" href="{{ route('admin.finance.categories') }}">📂 Kategori Keuangan</a>
                        <a class="{{ request()->routeIs('admin.finance.allocations') ? 'is-active' : '' }}" href="{{ route('admin.finance.allocations') }}">📊 Alokasi Dana</a>
                    </div>
                </li>
                @endif
                <li>
                    <a href="{{ route('admin.logout') }}" onclick="return confirm('Yakin mau logout?')" class="nav-link-direct" style="color:#fca5a5;">🚪 Logout</a>
                </li>
                @elseif(in_array('keuangan', $allowedGroups))
                {{-- Menu Supervisor: Akuntansi --}}
                <li class="{{ $grpFinance ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpFinance ? 'is-active-group' : '' }}">Akuntansi <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.finance.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.finance.dashboard') }}">📊 Dashboard Keuangan</a>
                        <div class="dropdown-divider"></div>
                        <div class="has-submenu {{ $grpPayroll ? 'is-active-sub' : '' }}">
                            <span class="submenu-toggle">💰 Payroll</span>
                            <div class="submenu-panel">
                                <a class="{{ request()->routeIs('admin.payroll.index') ? 'is-active' : '' }}" href="{{ route('admin.payroll.index') }}">👥 Karyawan & Saldo</a>
                                <a class="{{ request()->routeIs('admin.payroll.withdrawals') ? 'is-active' : '' }}" href="{{ route('admin.payroll.withdrawals') }}">📋 Penarikan Gaji</a>
                            </div>
                        </div>
                        <div class="has-submenu {{ request()->routeIs(['admin.finance.mutations','admin.finance.expenses','admin.finance.debts','admin.finance.shifts']) ? 'is-active-sub' : '' }}">
                            <span class="submenu-toggle">💸 Kas & Transaksi</span>
                            <div class="submenu-panel">
                                <a class="{{ request()->routeIs('admin.finance.mutations') ? 'is-active' : '' }}" href="{{ route('admin.finance.mutations') }}">📒 Mutasi Kas</a>
                                <a class="{{ request()->routeIs('admin.finance.expenses') ? 'is-active' : '' }}" href="{{ route('admin.finance.expenses') }}">💸 Pengeluaran</a>
                                <a class="{{ request()->routeIs('admin.finance.debts') ? 'is-active' : '' }}" href="{{ route('admin.finance.debts') }}">📋 Hutang Supplier</a>
                                <a class="{{ request()->routeIs('admin.finance.shifts') ? 'is-active' : '' }}" href="{{ route('admin.finance.shifts') }}">🕐 Detail Shift</a>
                            </div>
                        </div>
                        <div class="has-submenu {{ request()->routeIs(['admin.finance.budget','admin.finance.report.*','admin.finance.audit_log']) ? 'is-active-sub' : '' }}">
                            <span class="submenu-toggle">📈 Laporan</span>
                            <div class="submenu-panel">
                                <a class="{{ request()->routeIs('admin.finance.budget') ? 'is-active' : '' }}" href="{{ route('admin.finance.budget') }}">📊 Budget vs Realisasi</a>
                                <a class="{{ request()->routeIs('admin.finance.report.monthly') ? 'is-active' : '' }}" href="{{ route('admin.finance.report.monthly') }}">📈 Laporan Bulanan</a>
                                <a class="{{ request()->routeIs('admin.finance.report.balance') ? 'is-active' : '' }}" href="{{ route('admin.finance.report.balance') }}">💳 Saldo Kas</a>
                                <a class="{{ request()->routeIs('admin.finance.laba_rugi') ? 'is-active' : '' }}" href="{{ route('admin.finance.laba_rugi') }}">📊 Laba Rugi</a>
                                <a class="{{ request()->routeIs('admin.finance.tutup_buku') ? 'is-active' : '' }}" href="{{ route('admin.finance.tutup_buku') }}">📕 Tutup Buku</a>
                                <a class="{{ request()->routeIs('admin.finance.audit_log') ? 'is-active' : '' }}" href="{{ route('admin.finance.audit_log') }}">📜 Audit Log</a>
                            </div>
                        </div>
                        <div class="has-submenu {{ request()->routeIs(['admin.finance.sync','admin.finance.settings','admin.finance.sync_logs','admin.finance.mappings.*','admin.finance.categories','admin.finance.allocations']) ? 'is-active-sub' : '' }}">
                            <span class="submenu-toggle">⚙️ Pengaturan</span>
                            <div class="submenu-panel">
                                <a class="{{ request()->routeIs('admin.finance.sync') ? 'is-active' : '' }}" href="{{ route('admin.finance.sync') }}">🔄 Sinkronisasi</a>
                                <a class="{{ request()->routeIs('admin.finance.settings') ? 'is-active' : '' }}" href="{{ route('admin.finance.settings') }}">⚙️ Setting Sync</a>
                                <a class="{{ request()->routeIs('admin.finance.sync_logs') ? 'is-active' : '' }}" href="{{ route('admin.finance.sync_logs') }}">📋 Riwayat Sync</a>
                                <div class="dropdown-divider"></div>
                                <a class="{{ request()->routeIs('admin.finance.mappings.category') ? 'is-active' : '' }}" href="{{ route('admin.finance.mappings.category') }}">🏷️ Mapping Kategori</a>
                                <a class="{{ request()->routeIs('admin.finance.mappings.account') ? 'is-active' : '' }}" href="{{ route('admin.finance.mappings.account') }}">🏦 Mapping Akun Kas</a>
                                <div class="dropdown-divider"></div>
                                <a class="{{ request()->routeIs('admin.finance.categories') ? 'is-active' : '' }}" href="{{ route('admin.finance.categories') }}">📂 Kategori Keuangan</a>
                                <a class="{{ request()->routeIs('admin.finance.allocations') ? 'is-active' : '' }}" href="{{ route('admin.finance.allocations') }}">📊 Alokasi Dana</a>
                            </div>
                        </div>
                    </div>
                </li>

                @endif

                @if(in_array('ai', $allowedGroups))
                {{-- AI --}}
                <li class="{{ $grpAi ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpAi ? 'is-active-group' : '' }}">AI <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.ai_chat.*') ? 'is-active' : '' }}" href="{{ route('admin.ai_chat.index') }}">💬 Chat Produk</a>
                        <a class="{{ request()->routeIs('admin.ai_products.*') ? 'is-active' : '' }}" href="{{ route('admin.ai_products.index') }}">🧠 Knowledge Produk</a>
                    </div>
                </li>
                @endif

                @if(in_array('sistem', $allowedGroups))
                {{-- Sistem --}}
                <li class="{{ $grpSistem ? 'is-active-group' : '' }}">
                    <button class="dropdown-toggle {{ $grpSistem ? 'is-active-group' : '' }}">Sistem <span class="caret">▾</span></button>
                    <div class="dropdown-menu">
                        <a class="{{ request()->routeIs('admin.audit_log.*') ? 'is-active' : '' }}" href="{{ route('admin.audit_log.index') }}">📜 Audit Log</a>
                        <a class="{{ request()->routeIs('admin.settings') ? 'is-active' : '' }}" href="{{ route('admin.settings') }}">⚙️ Settings</a>
                        <div class="dropdown-divider"></div>
                        <a class="is-danger" href="{{ route('admin.logout') }}" onclick="return confirm('Yakin mau logout dari panel admin/supervisor?')">🚪 Logout</a>
                    </div>
                </li>
                @endif
            </ul>
        </div>
    </header>

    <div class="container">
        @yield('content')
    </div>

    <script>
        (function() {
            const hamburger = document.getElementById('hamburgerBtn');
            const menu = document.getElementById('navMenu');
            const backdrop = document.getElementById('navBackdrop');
            const isMobile = () => window.innerWidth <= 768;

            function openDrawer() {
                menu.classList.add('active');
                hamburger.classList.add('active');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
                hamburger.setAttribute('aria-expanded', 'true');
            }

            function closeDrawer() {
                menu.classList.remove('active');
                hamburger.classList.remove('active');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
                hamburger.setAttribute('aria-expanded', 'false');
                menu.querySelectorAll(':scope > li').forEach(function(li) {
                    li.classList.remove('open');
                });
            }

            // Toggle mobile drawer
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                if (isMobile()) {
                    if (menu.classList.contains('active')) {
                        closeDrawer();
                    } else {
                        openDrawer();
                    }
                }
            });

            // Backdrop click closes drawer
            backdrop.addEventListener('click', function() {
                closeDrawer();
            });

            // Dropdown toggle (mobile: click accordion, desktop: hover handled by CSS)
            menu.querySelectorAll('.dropdown-toggle').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const li = btn.parentElement;

                    if (isMobile()) {
                        // Accordion: close siblings, toggle current
                        menu.querySelectorAll(':scope > li').forEach(function(sibling) {
                            if (sibling !== li) sibling.classList.remove('open');
                        });
                        li.classList.toggle('open');
                    }
                });
            });

            // Close everything on outside click (desktop only needed)
            document.addEventListener('click', function(e) {
                if (!document.querySelector('.navbar').contains(e.target)) {
                    if (isMobile()) {
                        closeDrawer();
                    } else {
                        menu.querySelectorAll(':scope > li').forEach(function(li) {
                            li.classList.remove('open');
                        });
                    }
                }
            });

            // Reset on resize to desktop
            window.addEventListener('resize', function() {
                if (!isMobile()) {
                    closeDrawer();
                }
            });
        })();
    </script>
    @stack('scripts')
</body>

</html>
