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
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            position: relative;
        }
        .top h1 { margin: 0; font-size: 1rem; font-weight: 700; }
        .muted { color: #6b7280; font-size: 12px; }
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
        .top-menu-btn {
            background: none;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            color: #6b7280;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .top-menu-btn:hover { background: #f3f4f6; }
        .top-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            right: 14px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            min-width: 180px;
            z-index: 200;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .top-dropdown.open { display: block; }
        .top-dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            text-decoration: none;
            border: none;
            background: none;
            width: 100%;
            cursor: pointer;
            transition: background 0.15s;
        }
        .top-dropdown-item:hover { background: #f9fafb; }
        .top-dropdown-item.danger { color: #ef4444; }
        .top-dropdown-item.danger:hover { background: #fef2f2; }
        .top-dropdown-divider { height: 1px; background: #f3f4f6; margin: 0; }
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
        .btn-flash {
            background: #0ea5e9;
            color: #fff;
            padding: 6px 10px;
            font-size: 12px;
        }
        .btn-flash.active {
            background: #0284c7;
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
        .rack-tools {
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: minmax(180px, 1fr) auto;
            gap: 8px;
            align-items: center;
        }
        .rack-tools .input {
            margin-bottom: 0;
        }
        .rack-tools-hint {
            grid-column: 1 / -1;
            font-size: 12px;
            color: #64748b;
            margin-top: -2px;
        }
        .btn-soft {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            padding: 8px 4px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            font-size: 11px;
            line-height: 1.2;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            position: relative;
        }
        .menu-badge {
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 999px;
            font-size: 10px;
            line-height: 16px;
            font-weight: 700;
            text-align: center;
            background: #ef4444;
            color: #fff;
        }
        .mobile-nav-btn .menu-badge {
            position: absolute;
            top: 2px;
            right: 50%;
            transform: translateX(16px);
        }
        .menu-badge.hidden {
            display: none;
        }
        .hidden {
            display: none !important;
        }
        .tab-btn.active .menu-badge {
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
        }
        .mobile-nav-btn.active .menu-badge {
            background: #ef4444;
            color: #fff;
        }
        .rack-task-item.is-hidden {
            display: none;
        }
        .mobile-nav-btn.active {
            color: #1d4ed8;
            border-top: 3px solid #1d4ed8;
            background: #f0f5ff;
        }
        .mobile-nav-btn .nav-icon {
            font-size: 18px;
            line-height: 1;
        }
        .mobile-nav-btn .nav-label {
            font-size: 10px;
            line-height: 1;
        }
        .product-checklist {
            margin-top: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            background: #f8fafc;
        }
        .product-checklist-header {
            padding: 10px 12px;
            background: #eef2ff;
            font-weight: 700;
            font-size: 14px;
            color: #3730a3;
            border-bottom: 1px solid #e2e8f0;
        }
        .product-checklist-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }
        .product-checklist-item:last-child {
            border-bottom: none;
        }
        .product-checklist-item.checked {
            background: #f0fdf4;
        }
        .product-checklist-item.shortage {
            background: #fff7ed;
        }
        .product-checklist-name {
            flex: 1;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            min-width: 120px;
        }
        .product-checklist-qty {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .product-checklist-qty input {
            width: 60px;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
        }
        .product-checklist-qty input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
        .product-checklist-standard {
            font-size: 12px;
            color: #6b7280;
        }
        .product-checklist-status {
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
        }
        .product-checklist-status.ok {
            background: #dcfce7;
            color: #166534;
        }
        .product-checklist-status.restock {
            color: #c2410c;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }
        .product-checklist-item.restock {
            background: #fffbeb;
        }
        .product-checklist-status.shortage {
            background: #ffedd5;
            color: #9a3412;
        }
        .product-checklist-status.habis {
            background: #fee2e2;
            color: #991b1b;
        }
        .product-checklist-item.habis {
            background: #fef2f2;
        }
        .product-checklist-summary {
            padding: 10px 12px;
            background: #eef2ff;
            font-size: 13px;
            color: #4338ca;
            border-top: 1px solid #e2e8f0;
        }
        .product-checklist-empty {
            padding: 14px 12px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            background: #f8fafc;
        }
        .product-checklist-assign {
            padding: 10px 12px;
            background: #f0f9ff;
            border-top: 1px solid #e2e8f0;
        }
        .product-checklist-assign .btn {
            background: #1d4ed8;
            color: #fff;
            width: 100%;
            font-weight: 600;
        }
        .assign-product-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .assign-product-row:last-child { border-bottom: none; }
        .assign-product-row:hover { background: #f8fafc; }
        .assign-product-info {
            flex: 1;
            min-width: 0;
        }
        .assign-product-name {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .assign-product-meta {
            font-size: 11px;
            color: #6b7280;
        }
        .assign-product-add {
            flex-shrink: 0;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .assign-product-add:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }
        .assign-product-add:hover:not(:disabled) {
            background: #059669;
        }
        .refill-step {
            margin-top: 10px;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            overflow: hidden;
            background: #fffbeb;
        }
        .refill-step-header {
            padding: 10px 12px;
            background: #fef3c7;
            font-weight: 700;
            font-size: 14px;
            color: #92400e;
            border-bottom: 1px solid #fde68a;
        }
        .refill-step-hint {
            padding: 8px 12px;
            font-size: 12px;
            color: #78350f;
            background: #fef9c3;
            border-bottom: 1px solid #fde68a;
        }
        .refill-step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #fef3c7;
            flex-wrap: wrap;
        }
        .refill-step-item:last-child {
            border-bottom: none;
        }
        .refill-step-name {
            flex: 1;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            min-width: 100px;
        }
        .refill-step-info {
            font-size: 12px;
            color: #92400e;
        }
        .refill-step-qty {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .refill-step-qty input {
            width: 60px;
            padding: 6px 8px;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            background: #fff;
        }
        .refill-step-qty input:focus {
            border-color: #d97706;
            outline: none;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }
        .refill-step-actions {
            padding: 10px 12px;
            background: #fef3c7;
            border-top: 1px solid #fde68a;
            display: flex;
            gap: 8px;
        }
        .refill-step-actions button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .refill-step-actions .btn-refill-submit {
            background: #f59e0b;
            color: #fff;
        }
        .refill-step-actions .btn-refill-skip {
            background: #e5e7eb;
            color: #374151;
        }
        .refill-step-storage {
            margin-top: 4px;
            padding: 6px 8px;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.4;
        }
        .refill-step-storage--available {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .refill-step-storage--empty {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .refill-step-item--empty,
        .refill-step-item--missing {
            background: #fff7ed;
        }
        .refill-step-item--empty .js-refill-qty,
        .refill-step-item--missing .js-refill-qty {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
        .attendance-bar {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .attendance-bar.clocked-in { background: #ecfdf5; border-color: #a7f3d0; }
        .attendance-bar.late { background: #fff7ed; border-color: #fed7aa; }
        .attendance-bar.clocked-out { background: #f0fdf4; border-color: #bbf7d0; }
        .attendance-info { display: flex; flex-direction: column; gap: 2px; }
        .attendance-shift { font-size: 12px; color: #6b7280; }
        .attendance-status { font-weight: 700; font-size: 15px; }
        .attendance-status.present { color: #059669; }
        .attendance-status.late { color: #d97706; }
        .attendance-status.not-yet { color: #6b7280; }
        .btn-attendance {
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-attendance:disabled { background: #9ca3af; cursor: not-allowed; }
        @media (max-width: 768px) {
            body {
                padding: 14px 14px 86px;
            }
            .portal-tabs {
                display: none;
            }
            .mobile-nav {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1000;
                background: #ffffff;
                border-top: 1px solid #dbe2ea;
                box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.08);
                padding: 4px 0 env(safe-area-inset-bottom, 4px);
            }
        }
        /* === BONUS DASHBOARD STYLES === */
        #panel-bonus .bonus-container { max-width: 600px; margin: 0 auto; padding: 0; }
        #panel-bonus .bonus-month-bar {
            display: flex; align-items: center; justify-content: space-between;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 0.75rem 1rem; border-radius: 12px; margin-bottom: 1rem;
        }
        #panel-bonus .bonus-month-bar .bonus-month-label { font-weight: 600; font-size: 0.9rem; }
        #panel-bonus .bonus-month-picker {
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
            color: white; padding: 0.4rem 0.6rem; border-radius: 8px; font-size: 0.85rem; cursor: pointer;
        }
        #panel-bonus .bonus-month-picker::-webkit-calendar-picker-indicator { filter: invert(1); }

        #panel-bonus .progress-ring-wrapper {
            display: flex; flex-direction: column; align-items: center;
            padding: 2rem 1rem; background: white; border-radius: 16px;
            margin-bottom: 1rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        #panel-bonus .progress-ring-container { position: relative; width: 180px; height: 180px; margin-bottom: 1rem; }
        #panel-bonus .progress-ring-svg { transform: rotate(-90deg); width: 180px; height: 180px; }
        #panel-bonus .progress-ring-bg { fill: none; stroke: #e8ecf0; stroke-width: 12; }
        #panel-bonus .progress-ring-fill { fill: none; stroke-width: 12; stroke-linecap: round; transition: stroke-dashoffset 1.5s ease-in-out, stroke 0.3s; }
        #panel-bonus .progress-ring-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
        #panel-bonus .progress-ring-percent { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        #panel-bonus .progress-ring-label { font-size: 0.8rem; color: #888; margin-top: 0.25rem; }
        #panel-bonus .progress-tier { font-size: 0.9rem; font-weight: 600; padding: 0.3rem 1rem; border-radius: 20px; margin-top: 0.5rem; }

        #panel-bonus .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem; }
        #panel-bonus .stat-card { background: white; border-radius: 12px; padding: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); text-align: center; }
        #panel-bonus .stat-value { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
        #panel-bonus .stat-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        #panel-bonus .stat-sub { font-size: 0.7rem; color: #aaa; margin-top: 0.15rem; }
        #panel-bonus .stat-card.penalty .stat-value { color: #e53e3e; }
        #panel-bonus .stat-card.perfect .stat-value { color: #38a169; }

        #panel-bonus .bonus-card {
            background: white; border-radius: 12px; padding: 1.25rem;
            margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        #panel-bonus .bonus-card-title {
            font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; color: #444;
            display: flex; align-items: center; gap: 0.5rem;
        }

        #panel-bonus .projected-bonus { text-align: center; padding: 1.5rem; }
        #panel-bonus .projected-amount { font-size: 2rem; font-weight: 800; margin: 0.5rem 0; }
        #panel-bonus .projected-tier-label { font-size: 0.85rem; color: #666; }
        #panel-bonus .projected-note { font-size: 0.75rem; color: #999; margin-top: 0.5rem; }

        #panel-bonus .explain-block { margin-bottom: 1rem; padding: 0.85rem; border-radius: 10px; background: #f8fafc; border: 1px solid #edf2f7; }
        #panel-bonus .explain-block:last-child { margin-bottom: 0; }
        #panel-bonus .explain-title { font-size: 0.8rem; font-weight: 700; color: #4a5568; margin-bottom: 0.4rem; }
        #panel-bonus .explain-list { list-style: none; display: grid; gap: 0.45rem; font-size: 0.8rem; color: #4a5568; padding: 0; margin: 0; }
        #panel-bonus .explain-line { display: flex; justify-content: space-between; align-items: baseline; gap: 0.75rem; line-height: 1.45; }
        #panel-bonus .explain-line strong { white-space: nowrap; color: #2d3748; }

        #panel-bonus .bonus-tier-wrapper { display: grid; gap: 0.85rem; }
        #panel-bonus .tier-box { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
        #panel-bonus .tier-box-title { padding: 0.65rem 0.8rem; font-size: 0.78rem; font-weight: 700; background: #f7fafc; color: #4a5568; border-bottom: 1px solid #edf2f7; }
        #panel-bonus .tier-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.65rem 0.8rem; font-size: 0.82rem; border-bottom: 1px solid #f1f5f9; }
        #panel-bonus .tier-row:last-child { border-bottom: none; }
        #panel-bonus .tier-row.active { border-left: 3px solid #38a169; font-weight: 700; }
        #panel-bonus .tier-range { color: #4a5568; white-space: nowrap; }
        #panel-bonus .tier-amount { font-weight: 700; color: #2d3748; text-align: right; margin-left: auto; }
        #panel-bonus .tier-badge { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.3px; padding: 0.2rem 0.45rem; border-radius: 999px; }
        #panel-bonus .total-max-note { font-size: 0.82rem; font-weight: 700; text-align: center; color: #2d3748; padding-top: 0.25rem; }

        #panel-bonus .daily-points-note { list-style: none; display: grid; gap: 0.55rem; font-size: 0.8rem; color: #4a5568; line-height: 1.45; padding: 0; margin: 0; }
        #panel-bonus .daily-points-note strong { color: #2d3748; }

        #panel-bonus .category-bar-row { display: flex; align-items: center; margin-bottom: 0.75rem; gap: 0.5rem; }
        #panel-bonus .category-bar-label { width: 80px; font-size: 0.75rem; color: #666; text-transform: capitalize; flex-shrink: 0; }
        #panel-bonus .category-bar-track { flex: 1; height: 8px; background: #e8ecf0; border-radius: 4px; overflow: hidden; }
        #panel-bonus .category-bar-fill { height: 100%; border-radius: 4px; transition: width 1s ease; }
        #panel-bonus .category-bar-value { width: 35px; font-size: 0.75rem; font-weight: 600; text-align: right; flex-shrink: 0; }

        #panel-bonus .daily-list { max-height: 400px; overflow-y: auto; }
        #panel-bonus .daily-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f0f0f0; }
        #panel-bonus .daily-item:last-child { border-bottom: none; }
        #panel-bonus .daily-date { font-size: 0.8rem; color: #666; width: 70px; flex-shrink: 0; }
        #panel-bonus .daily-categories { display: flex; gap: 3px; flex: 1; margin: 0 0.5rem; }
        #panel-bonus .daily-cat-dot { width: 6px; height: 20px; border-radius: 3px; flex: 1; max-width: 30px; }
        #panel-bonus .daily-total { font-weight: 700; font-size: 0.9rem; width: 40px; text-align: right; }

        #panel-bonus .penalty-item { padding: 0.75rem 0; border-bottom: 1px solid #f0f0f0; }
        #panel-bonus .penalty-item:last-child { border-bottom: none; }
        #panel-bonus .penalty-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
        #panel-bonus .penalty-type { font-size: 0.8rem; font-weight: 600; color: #e53e3e; }
        #panel-bonus .penalty-points { font-size: 0.85rem; font-weight: 700; color: #e53e3e; }
        #panel-bonus .penalty-reason { font-size: 0.75rem; color: #888; }
        #panel-bonus .penalty-date { font-size: 0.7rem; color: #aaa; margin-top: 0.15rem; }

        #panel-bonus .sales-progress-bar { width: 100%; height: 12px; background: #e8ecf0; border-radius: 6px; overflow: hidden; margin: 0.75rem 0; }
        #panel-bonus .sales-progress-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 1s ease; }
        #panel-bonus .sales-stats { display: flex; justify-content: space-between; font-size: 0.8rem; color: #666; }

        #panel-bonus .leaderboard-item { display: flex; align-items: center; padding: 0.6rem 0.75rem; border-radius: 8px; margin-bottom: 0.4rem; gap: 0.75rem; transition: background 0.2s; }
        #panel-bonus .leaderboard-item.is-me { background: linear-gradient(135deg, rgba(102,126,234,0.08), rgba(118,75,162,0.08)); border: 1px solid rgba(102,126,234,0.2); }
        #panel-bonus .leaderboard-rank { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; background: #f0f0f0; color: #666; flex-shrink: 0; }
        #panel-bonus .leaderboard-rank.gold { background: #fef3c7; color: #d97706; }
        #panel-bonus .leaderboard-rank.silver { background: #e5e7eb; color: #6b7280; }
        #panel-bonus .leaderboard-rank.bronze { background: #fed7aa; color: #c2410c; }
        #panel-bonus .leaderboard-name { flex: 1; font-size: 0.85rem; font-weight: 500; }
        #panel-bonus .leaderboard-points { font-size: 0.85rem; font-weight: 700; color: #667eea; }

        #panel-bonus .finalized-banner { background: linear-gradient(135deg, #38a169, #2f855a); color: white; border-radius: 12px; padding: 1.5rem; text-align: center; margin-bottom: 1rem; box-shadow: 0 4px 15px rgba(56,161,105,0.3); }
        #panel-bonus .finalized-label { font-size: 0.85rem; opacity: 0.9; margin-bottom: 0.25rem; }
        #panel-bonus .finalized-amount { font-size: 2.5rem; font-weight: 800; }
        #panel-bonus .finalized-status { font-size: 0.75rem; opacity: 0.8; margin-top: 0.5rem; }

        #panel-bonus .bonus-empty-state { text-align: center; padding: 2rem; color: #aaa; font-size: 0.85rem; }

        #panel-bonus .b-color-green { color: #38a169; }
        #panel-bonus .b-color-yellow { color: #d69e2e; }
        #panel-bonus .b-color-orange { color: #dd6b20; }
        #panel-bonus .b-color-red { color: #e53e3e; }
        #panel-bonus .b-bg-green { background: #f0fff4; }
        #panel-bonus .b-bg-yellow { background: #fffff0; }
        #panel-bonus .b-bg-orange { background: #fffaf0; }
        #panel-bonus .b-bg-red { background: #fff5f5; }

        @keyframes bonus-ring-fill { from { stroke-dashoffset: 440; } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

        @media (max-width: 420px) {
            #panel-bonus .tier-row { flex-wrap: wrap; gap: 0.35rem 0.65rem; }
            #panel-bonus .tier-amount { margin-left: 0; }
        }

        /* History accordion */
        details[open] > summary > span:first-child {
            transform: rotate(90deg);
        }
        /* Quick Actions tiles */
        .quick-actions {
            margin-bottom: 14px;
        }
        .quick-actions-label {
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .quick-action-tile {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 80px;
            padding: 14px 14px 14px 16px;
            border-radius: 13px;
            text-decoration: none;
            box-shadow: 0 3px 14px rgba(0,0,0,0.08);
            transition: transform 0.12s, box-shadow 0.12s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .quick-action-tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(0,0,0,0.13);
        }
        .quick-action-tile:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .quick-action-tile--stock {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border: 1px solid #7dd3fc;
        }
        .quick-action-tile--restock {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #6ee7b7;
        }
        .quick-action-tile__icon {
            font-size: 30px;
            line-height: 1;
            flex-shrink: 0;
        }
        .quick-action-tile__body {
            flex: 1;
            min-width: 0;
        }
        .quick-action-tile__title {
            font-size: 14px;
            font-weight: 700;
            color: #1e3a5f;
            line-height: 1.25;
            margin-bottom: 3px;
        }
        .quick-action-tile--restock .quick-action-tile__title {
            color: #065f46;
        }
        .quick-action-tile__sub {
            font-size: 11px;
            color: #0369a1;
            line-height: 1.35;
        }
        .quick-action-tile--restock .quick-action-tile__sub {
            color: #047857;
        }
        .quick-action-tile__chevron {
            font-size: 18px;
            color: #0284c7;
            flex-shrink: 0;
            opacity: 0.6;
        }
        .quick-action-tile--restock .quick-action-tile__chevron {
            color: #059669;
        }
        /* Compact tiles on mobile/tablet ≤640px */
        @media (max-width: 640px) {
            .quick-actions {
                margin-bottom: 10px;
            }
            .quick-actions-label {
                display: none;
            }
            .quick-actions-grid {
                gap: 8px;
            }
            .quick-action-tile {
                min-height: 52px;
                gap: 10px;
                padding: 8px 10px 8px 12px;
                border-radius: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }
            .quick-action-tile--stock {
                background: #eff6ff;
                border-color: #bfdbfe;
            }
            .quick-action-tile--restock {
                background: #ecfdf5;
                border-color: #a7f3d0;
            }
            .quick-action-tile__icon {
                font-size: 22px;
            }
            .quick-action-tile__title {
                font-size: 12.5px;
                margin-bottom: 0;
                line-height: 1.2;
            }
            .quick-action-tile__sub {
                display: none;
            }
            .quick-action-tile__chevron {
                font-size: 14px;
            }
        }
        /* Stack only on very narrow phones */
        @media (max-width: 340px) {
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            .quick-action-tile__sub {
                display: block;
                font-size: 10.5px;
            }
        }

        /* ===== REWARD OVERLAY ===== */
        .reward-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, rgba(15, 23, 42, 0.55) 0%, rgba(15, 23, 42, 0.85) 100%);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .reward-overlay.is-open {
            display: flex;
            opacity: 1;
        }
        .reward-card {
            position: relative;
            width: 100%;
            max-width: 380px;
            background: linear-gradient(160deg, #ffffff 0%, #fef9f3 50%, #fff7ed 100%);
            border-radius: 24px;
            padding: 28px 22px 22px;
            box-shadow: 0 30px 80px -10px rgba(251, 191, 36, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            text-align: center;
            transform: scale(0.7) translateY(40px);
            opacity: 0;
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
            overflow: hidden;
        }
        .reward-overlay.is-open .reward-card {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        .reward-card::before {
            content: '';
            position: absolute;
            inset: -50% -50% auto -50%;
            height: 200%;
            background: radial-gradient(circle at 50% 30%, rgba(251, 191, 36, 0.25) 0%, transparent 60%);
            pointer-events: none;
            animation: reward-shine 3s ease-in-out infinite;
        }
        @keyframes reward-shine {
            0%, 100% { transform: rotate(0deg); opacity: 0.7; }
            50% { transform: rotate(180deg); opacity: 1; }
        }
        .reward-trophy {
            font-size: 64px;
            line-height: 1;
            margin-bottom: 8px;
            display: inline-block;
            animation: reward-bounce 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            filter: drop-shadow(0 8px 16px rgba(251, 191, 36, 0.5));
            position: relative;
            z-index: 2;
        }
        @keyframes reward-bounce {
            0% { transform: scale(0) rotate(-180deg); opacity: 0; }
            60% { transform: scale(1.2) rotate(15deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
        .reward-title {
            font-size: 22px;
            font-weight: 800;
            color: #78350f;
            margin: 0 0 4px;
            letter-spacing: 0.3px;
            position: relative;
            z-index: 2;
        }
        .reward-subtitle {
            font-size: 13px;
            color: #92400e;
            margin: 0 0 16px;
            opacity: 0.85;
            position: relative;
            z-index: 2;
        }
        .reward-points-block {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #fff;
            border-radius: 18px;
            padding: 16px 12px;
            margin: 0 0 14px;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.35);
            position: relative;
            z-index: 2;
        }
        .reward-points-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.9;
            font-weight: 700;
        }
        .reward-points-value {
            font-size: 56px;
            font-weight: 900;
            line-height: 1;
            margin-top: 6px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-variant-numeric: tabular-nums;
        }
        .reward-points-suffix {
            display: inline-block;
            margin-left: 4px;
            font-size: 24px;
            font-weight: 700;
            opacity: 0.9;
            vertical-align: top;
            margin-top: 14px;
        }
        .reward-perfect {
            display: none;
            margin: 10px auto 0;
            padding: 6px 14px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
            animation: reward-pulse 1.5s ease-in-out infinite;
        }
        .reward-perfect.is-shown {
            display: inline-block;
        }
        @keyframes reward-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .reward-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin: 0 0 14px;
            position: relative;
            z-index: 2;
        }
        .reward-cat {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 8px 4px;
            font-size: 11px;
            color: #78350f;
            font-weight: 600;
        }
        .reward-cat-icon {
            font-size: 18px;
            display: block;
            margin-bottom: 2px;
        }
        .reward-cat-label {
            display: block;
            font-size: 10px;
            opacity: 0.7;
            margin-bottom: 2px;
        }
        .reward-cat-value {
            display: block;
            font-size: 14px;
            font-weight: 800;
            color: #92400e;
        }
        .reward-cat.is-gain {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-color: #fbbf24;
        }
        .reward-cat-delta {
            display: block;
            font-size: 10px;
            color: #059669;
            font-weight: 700;
            margin-top: 1px;
        }
        .reward-progress-block {
            margin: 0 0 14px;
            position: relative;
            z-index: 2;
        }
        .reward-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #78350f;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .reward-progress-bar {
            height: 10px;
            background: #fde68a;
            border-radius: 999px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) inset;
        }
        .reward-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #f59e0b, #fbbf24, #fde047);
            border-radius: 999px;
            width: 0%;
            transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 12px rgba(251, 191, 36, 0.6);
        }
        .reward-message {
            font-size: 13px;
            color: #92400e;
            margin: 0 0 16px;
            font-style: italic;
            position: relative;
            z-index: 2;
        }
        .reward-actions {
            display: flex;
            gap: 8px;
            position: relative;
            z-index: 2;
        }
        .reward-btn {
            flex: 1;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .reward-btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            box-shadow: 0 6px 16px rgba(217, 119, 6, 0.35);
        }
        .reward-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(217, 119, 6, 0.45);
        }
        .reward-btn-secondary {
            background: #fff;
            color: #78350f;
            border: 1px solid #fde68a;
        }
        .reward-btn-secondary:hover {
            background: #fef3c7;
        }

        /* CONFETTI */
        .reward-confetti {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 1;
        }
        .reward-confetti span {
            position: absolute;
            top: -20px;
            width: 10px;
            height: 14px;
            opacity: 0;
            animation: reward-fall 2.6s linear forwards;
        }
        @keyframes reward-fall {
            0% {
                transform: translateY(-20px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(540px) rotate(720deg);
                opacity: 0;
            }
        }

        /* Floating points pop near button (small variant) */
        .reward-float {
            position: fixed;
            z-index: 9998;
            pointer-events: none;
            font-size: 18px;
            font-weight: 800;
            color: #f59e0b;
            text-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
            animation: reward-float-up 1.4s ease-out forwards;
        }
        @keyframes reward-float-up {
            0% { transform: translateY(0) scale(0.6); opacity: 0; }
            20% { transform: translateY(-10px) scale(1.2); opacity: 1; }
            100% { transform: translateY(-80px) scale(1); opacity: 0; }
        }

        @media (max-width: 420px) {
            .reward-card { padding: 24px 18px 18px; max-width: calc(100vw - 24px); }
            .reward-trophy { font-size: 56px; }
            .reward-points-value { font-size: 48px; }
            .reward-title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>🧑‍🍳 {{ $waiterName }}</h1>
                <div class="muted">{{ $waiterEmail }}</div>
            </div>
            <button type="button" class="top-menu-btn" id="topMenuBtn" aria-label="Menu">⋮</button>
            <div class="top-dropdown" id="topDropdown">
                <div style="padding: 10px 14px; font-size: 12px; color: #9ca3af;">Login sebagai <strong style="color:#374151;">{{ $waiterName }}</strong></div>
                <div class="top-dropdown-divider"></div>
                <a href="{{ route('waiter.payroll', [], false) }}" class="top-dropdown-item">💰 Gaji Saya</a>
                <div class="top-dropdown-divider"></div>
                <a href="{{ route('waiter.logout', [], false) }}" class="top-dropdown-item danger" onclick="return confirm('Yakin mau logout?')">🚪 Logout</a>
            </div>
        </div>

        <div id="flash-success" class="alert ok{{ session('success') ? '' : ' hidden' }}">
            ✅ {{ session('success') ?? '' }}
        </div>
        <div id="flash-error" class="alert err{{ session('error') ? '' : ' hidden' }}">
            ❌ {{ session('error') ?? '' }}
        </div>

        <div id="attendance-bar" class="attendance-bar">
            <div class="attendance-info">
                <span class="attendance-shift" id="attendance-shift-label">Memuat info shift...</span>
                <span class="attendance-status not-yet" id="attendance-status-label">Memuat status absensi...</span>
            </div>
            <button type="button" class="btn-attendance" id="btn-attendance-action" disabled>Memuat...</button>
        </div>

        <div class="quick-actions">
            <div class="quick-actions-label">Akses Cepat</div>
            <div class="quick-actions-grid">
                <a href="{{ route('waiter.stock_take', [], false) }}" class="quick-action-tile quick-action-tile--stock">
                    <span class="quick-action-tile__icon">🧾</span>
                    <span class="quick-action-tile__body">
                        <span class="quick-action-tile__title">Ambil Stok Gudang</span>
                        <span class="quick-action-tile__sub">Pindahkan stok dari gudang ke display</span>
                    </span>
                    <span class="quick-action-tile__chevron">›</span>
                </a>
                <a href="/waiter/restock" class="quick-action-tile quick-action-tile--restock">
                    <span class="quick-action-tile__icon">📦</span>
                    <span class="quick-action-tile__body">
                        <span class="quick-action-tile__title">Penerimaan Barang</span>
                        <span class="quick-action-tile__sub">Catat barang masuk dari supplier (PO)</span>
                    </span>
                    <span class="quick-action-tile__chevron">›</span>
                </a>
            </div>
        </div>

        <div class="portal-tabs">
            <button type="button" class="tab-btn js-tab-btn active" data-tab="rack">📦 Cek Rak <span id="badge-tab-rack" class="menu-badge js-rack-menu-badge hidden">0</span></button>
            <button type="button" class="tab-btn js-tab-btn" data-tab="tasks">📝 Tugas <span id="badge-tab-general" class="menu-badge js-general-menu-badge hidden">0</span></button>
            <button type="button" class="tab-btn js-tab-btn" data-tab="reports">📔 Laporan Kegiatan</button>
            <button type="button" class="tab-btn js-tab-btn" data-tab="bonus">🏆 Bonus</button>
        </div>

        <section id="panel-rack" class="portal-panel active">
            <h2 style="margin: 0 0 10px 0;">Cek Rak Saya (<span id="rack-pending-count">0</span>)</h2>
            <div id="rack-pending-container"></div>

            <details style="margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
                <summary style="cursor: pointer; font-weight: 700; font-size: 16px; color: #334155; user-select: none; list-style: none; display: flex; align-items: center; gap: 8px;">
                    <span style="transition: transform 0.2s;">▶</span>
                    <span>Riwayat Cek Rak</span>
                </summary>
                <div style="overflow-x: auto; margin-top: 12px;">
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
                        <tbody id="rack-history-body"></tbody>
                    </table>
                </div>
            </details>
        </section>

        <section id="panel-tasks" class="portal-panel">
            <h2 style="margin: 0 0 10px 0;">Tugas Umum Saya (<span id="general-pending-count">0</span>)</h2>
            <div id="general-pending-container"></div>

            <details style="margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
                <summary style="cursor: pointer; font-weight: 700; font-size: 16px; color: #334155; user-select: none; list-style: none; display: flex; align-items: center; gap: 8px;">
                    <span style="transition: transform 0.2s;">▶</span>
                    <span>Riwayat Tugas Umum</span>
                </summary>
                <div style="overflow-x: auto; margin-top: 12px;">
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
                        <tbody id="general-history-body"></tbody>
                    </table>
                </div>
            </details>
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

        <section id="panel-bonus" class="portal-panel">
            @include('waiter._bonus_summary', ['lazyLoad' => true])
        </section>
    </div>

    <nav class="mobile-nav" id="waiter-mobile-nav" aria-label="Menu Portal Waiter Mobile">
        <button type="button" class="mobile-nav-btn js-tab-btn active" data-tab="rack">
            <span class="nav-icon">📦</span>
            <span class="nav-label">Cek Rak</span>
            <span id="badge-mobile-rack" class="menu-badge js-rack-menu-badge hidden">0</span>
        </button>
        <button type="button" class="mobile-nav-btn js-tab-btn" data-tab="tasks">
            <span class="nav-icon">📝</span>
            <span class="nav-label">Tugas</span>
            <span id="badge-mobile-general" class="menu-badge js-general-menu-badge hidden">0</span>
        </button>
        <button type="button" class="mobile-nav-btn js-tab-btn" data-tab="reports">
            <span class="nav-icon">📔</span>
            <span class="nav-label">Laporan</span>
        </button>
        <button type="button" class="mobile-nav-btn js-tab-btn" data-tab="bonus">
            <span class="nav-icon">🏆</span>
            <span class="nav-label">Bonus</span>
        </button>
    </nav>

    <div id="scanner-modal" class="scanner-modal" aria-hidden="true">
        <div class="scanner-box">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <strong id="scanner-modal-title">📷 Scan QR Code Rak</strong>
                <div style="display:flex; align-items:center; gap:8px;">
                    <button type="button" id="scanner-flash-btn" class="btn btn-flash hidden" disabled>🔦 Nyalakan Flash</button>
                    <button type="button" id="scanner-close-btn" class="btn" style="background:#ef4444;color:#fff;padding:6px 10px;">Tutup</button>
                </div>
            </div>
            <div id="scanner-task-meta" class="muted" style="margin-top: 6px;"></div>
            <div id="scanner-reader" class="scanner-reader"></div>
            <div id="scanner-feedback" class="muted">Arahkan kamera ke QR code rak sampai terbaca.</div>
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

    {{-- Assign Product to Rack modal --}}
    <div id="assign-product-modal" class="photo-view-modal" aria-hidden="true">
        <div class="photo-view-box">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
                <strong id="assign-product-modal-title">➕ Tambahkan Produk ke Rak</strong>
                <button type="button" id="assign-product-modal-close-btn" class="btn" style="background:#ef4444;color:#fff;padding:6px 10px;">Tutup</button>
            </div>
            <div id="assign-product-modal-meta" class="muted" style="margin-bottom:8px;"></div>
            <input type="text" id="assign-product-search" class="input" placeholder="Cari nama atau barcode produk..." autocomplete="off" style="margin-bottom:8px;">
            <div id="assign-product-feedback" class="meta" style="font-size:12px;color:#6b7280;margin-bottom:8px;">Ketik minimal 2 huruf untuk mulai mencari.</div>
            <div id="assign-product-results" style="max-height:50vh;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;background:#fff;"></div>
        </div>
    </div>

    <script id="waiter-context" type="application/json">{!! json_encode([
        'waiterId' => $waiterId,
        'reportDate' => $reportDate ?? date('Y-m-d'),
        'pendingTasks' => $pendingTasks,
        'taskHistory' => $taskHistory,
        'activityReports' => $activityReports ?? [],
        'rackProductsMap' => $rackProductsMap ?? [],
        'rackTypesMap' => $rackTypesMap ?? [],
        'todayAttendance' => $todayAttendance ?? null,
        'waiterShift' => $waiterShift ?? null,
        'shiftStartTime' => $shiftStartTime ?? null,
        'attendanceClockInUrl' => route('waiter.attendance.clock_in', [], false),
        'attendanceClockOutUrl' => route('waiter.attendance.clock_out', [], false),
        'attendanceStatusUrl' => route('waiter.attendance.status', [], false),
        'bonusApiUrl' => route('waiter.bonus.api', [], false),
        'clockOutEnabled' => $clockOutEnabled ?? false,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@include('partials.firebase-rtdb-client')
<script>

        const contextEl = document.getElementById('waiter-context');
        const context = contextEl ? JSON.parse(contextEl.textContent || '{}') : {};

        const waiterId = String(context.waiterId || '');
        const shiftStartTime = context.shiftStartTime || null;
        const clockOutEnabled = !!context.clockOutEnabled;
        const rackPendingCountEl = document.getElementById('rack-pending-count');
        const rackPendingContainer = document.getElementById('rack-pending-container');
        const generalPendingCountEl = document.getElementById('general-pending-count');
        const generalPendingContainer = document.getElementById('general-pending-container');
        const rackHistoryBody = document.getElementById('rack-history-body');
        const generalHistoryBody = document.getElementById('general-history-body');
        const successEl = document.getElementById('flash-success');
        const errorEl = document.getElementById('flash-error');
        const scannerModalEl = document.getElementById('scanner-modal');
        const scannerCloseBtn = document.getElementById('scanner-close-btn');
        const scannerFlashBtn = document.getElementById('scanner-flash-btn');
        const scannerReaderElId = 'scanner-reader';
        const scannerFeedbackEl = document.getElementById('scanner-feedback');
        const scannerTaskMetaEl = document.getElementById('scanner-task-meta');
        const photoPreviewModalEl = document.getElementById('photo-preview-modal');
        const photoPreviewImageEl = document.getElementById('photo-preview-image');
        const photoPreviewMetaEl = document.getElementById('photo-preview-meta');
        const photoPreviewCloseBtn = document.getElementById('photo-preview-close-btn');
        const csrfToken = '{{ csrf_token() }}';
        const completeUrlTemplate = "{{ route('waiter.task.complete', ['id' => '__TASK_ID__'], false) }}";
        const claimUrlTemplate = "{{ route('waiter.task.claim', ['id' => '__TASK_ID__'], false) }}";
        const releaseUrlTemplate = "{{ route('waiter.task.release', ['id' => '__TASK_ID__'], false) }}";
        const pollUrl = "{{ route('waiter.task.poll', [], false) }}";
        const syncDueUrl = "{{ route('waiter.task.sync_due', [], false) }}";
        const activityStoreUrl = "{{ route('waiter.activity.store', [], false) }}";

        // === TOP MENU DROPDOWN ===
        (function() {
            const menuBtn = document.getElementById('topMenuBtn');
            const dropdown = document.getElementById('topDropdown');
            if (menuBtn && dropdown) {
                menuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('open');
                });
                document.addEventListener('click', function() {
                    dropdown.classList.remove('open');
                });
                dropdown.addEventListener('click', function(e) { e.stopPropagation(); });
            }
        })();

        const tabButtons = Array.from(document.querySelectorAll('.js-tab-btn'));
        const rackMenuBadgeEls = Array.from(document.querySelectorAll('.js-rack-menu-badge'));
        const generalMenuBadgeEls = Array.from(document.querySelectorAll('.js-general-menu-badge'));
        const panelRack = document.getElementById('panel-rack');
        const panelTasks = document.getElementById('panel-tasks');
        const panelStockTake = document.getElementById('panel-stocktake');
        const panelReports = document.getElementById('panel-reports');
        const panelBonus = document.getElementById('panel-bonus');
        const stockTakePendingCountEl = document.getElementById('stocktake-pending-count');
        const stockTakePendingContainer = document.getElementById('stocktake-pending-container');
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
        const photoBeforeByTask = new Map();
        const productChecklistByTask = new Map();
        const refillStepByTask = new Map(); // tracks display rack tasks in refill mode
        const taskCompleteFormInstanceByTask = new Map();

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
        const _draftSaveTimers = {};
        function debounceSaveDraft(key, getDataFn) {
            clearTimeout(_draftSaveTimers[key]);
            _draftSaveTimers[key] = setTimeout(() => {
                const data = getDataFn();
                if (data && Object.keys(data).length > 0) saveDraftLocal(key, data);
            }, 500);
        }
        function taskDraftKey(taskId) { return `waiter_draft:complete_task:${taskId}`; }
        function activityDraftKey(date) { return `waiter_draft:activity_report:${date}`; }
        function restoreTaskDraft(taskId) {
            const draft = loadDraftLocal(taskDraftKey(taskId));
            if (!draft) return;
            if (draft.note) noteDraftByTask.set(taskId, draft.note);
            if (draft.scanned_barcode) scannedBarcodeByTask.set(taskId, draft.scanned_barcode);
            if (draft.product_checklist && typeof draft.product_checklist === 'object') {
                productChecklistByTask.set(taskId, draft.product_checklist);
            }
        }
        function collectTaskDraft(taskId) {
            const note = noteDraftByTask.get(taskId) || '';
            const scanned_barcode = scannedBarcodeByTask.get(taskId) || '';
            const product_checklist = productChecklistByTask.get(taskId) || {};
            return { note, scanned_barcode, product_checklist };
        }
        let activeScannerTaskId = '';
        let activeScannerTaskLabel = '';
        let activeScannerExpectedBarcode = '';
        let scannerInstance = null;
        let scannerRunning = false;
        let scannerTorchSupported = false;
        let scannerTorchEnabled = false;
        let scannerTorchBusy = false;
        let pendingRenderDeferred = false;

        let waiterTasks = [
            ...(Array.isArray(context.pendingTasks) ? context.pendingTasks : []),
            ...(Array.isArray(context.taskHistory) ? context.taskHistory : []),
        ];
        let reportDate = String(context.reportDate || new Date().toISOString().slice(0, 10));
        let activityReports = Array.isArray(context.activityReports) ? context.activityReports : [];
        let rackProductsMap = context.rackProductsMap && typeof context.rackProductsMap === 'object' ? context.rackProductsMap : {};
        let rackTypesMap = context.rackTypesMap && typeof context.rackTypesMap === 'object' ? context.rackTypesMap : {};
        let syncDueInFlight = false;
        let syncDueCooldownUntil = 0;
        let syncDueBackoffMs = 0;
        let lastSyncDueAttemptAt = 0;
        let pollInFlight = false;
        let pollCooldownUntil = 0;
        let pollBackoffMs = 0;
        let rackSearchKeyword = '';

        const newFormInstanceId = () => {
            if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
            return Math.random().toString(36).slice(2) + Date.now().toString(36);
        };

        // === ATTENDANCE STATE & ELEMENTS ===
        const attendanceBarEl = document.getElementById('attendance-bar');
        const attendanceShiftLabelEl = document.getElementById('attendance-shift-label');
        const attendanceStatusLabelEl = document.getElementById('attendance-status-label');
        const btnAttendanceAction = document.getElementById('btn-attendance-action');
        const scannerModalTitleEl = document.getElementById('scanner-modal-title');
        const attendanceClockInUrl = context.attendanceClockInUrl || '';
        const attendanceClockOutUrl = context.attendanceClockOutUrl || '';
        const attendanceStatusUrl = context.attendanceStatusUrl || '';

        let attendanceState = {
            clock_in: null,
            clock_out: null,
            clock_in_time: null,
            clock_out_time: null,
            status: null,
            shift: context.waiterShift || null,
            late_minutes: 0,
        };
        let attendanceScanMode = null; // 'clock_in' or 'clock_out'

        function formatAttendanceTime(value) {
            if (!value) return '-';
            const num = Number(value);
            if (Number.isFinite(num) && num > 1000000000) {
                const d = new Date(num * 1000);
                return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            }
            return String(value);
        }

        function renderAttendanceBar() {
            if (!attendanceBarEl) return;

            const shift = attendanceState.shift;
            const shiftName = shift ? (shift.name || 'Shift') : 'Belum ada shift';
            const shiftTime = shift ? `${shift.start_time || '?'} - ${shift.end_time || '?'}` : '';
            if (attendanceShiftLabelEl) {
                attendanceShiftLabelEl.textContent = shiftTime ? `${shiftName} (${shiftTime})` : shiftName;
            }

            attendanceBarEl.classList.remove('clocked-in', 'late', 'clocked-out');
            if (attendanceStatusLabelEl) {
                attendanceStatusLabelEl.classList.remove('present', 'late', 'not-yet');
            }

            if (attendanceState.clock_out) {
                const outTime = attendanceState.clock_out_time || attendanceState.clock_out;
                attendanceBarEl.classList.add('clocked-out');
                if (attendanceStatusLabelEl) {
                    attendanceStatusLabelEl.classList.add('present');
                    attendanceStatusLabelEl.textContent = `Sudah Pulang (${formatAttendanceTime(outTime)})`;
                }
                if (btnAttendanceAction) {
                    btnAttendanceAction.textContent = 'Selesai Hari Ini';
                    btnAttendanceAction.disabled = true;
                }
            } else if (attendanceState.clock_in) {
                const inTime = attendanceState.clock_in_time || attendanceState.clock_in;
                if (attendanceState.late_minutes > 0) {
                    attendanceBarEl.classList.add('late');
                    if (attendanceStatusLabelEl) {
                        attendanceStatusLabelEl.classList.add('late');
                        attendanceStatusLabelEl.textContent = `Terlambat (${formatAttendanceTime(inTime)}, +${attendanceState.late_minutes} menit)`;
                    }
                } else {
                    attendanceBarEl.classList.add('clocked-in');
                    if (attendanceStatusLabelEl) {
                        attendanceStatusLabelEl.classList.add('present');
                        attendanceStatusLabelEl.textContent = `Sudah Masuk (${formatAttendanceTime(inTime)})`;
                    }
                }
                if (btnAttendanceAction) {
                    if (clockOutEnabled) {
                        btnAttendanceAction.textContent = '📷 Scan Absen Pulang';
                        btnAttendanceAction.disabled = false;
                    } else {
                        btnAttendanceAction.textContent = 'Selesai Hari Ini';
                        btnAttendanceAction.disabled = true;
                    }
                }
            } else {
                if (attendanceStatusLabelEl) {
                    attendanceStatusLabelEl.classList.add('not-yet');
                    attendanceStatusLabelEl.textContent = 'Belum Absen Masuk';
                }
                if (btnAttendanceAction) {
                    btnAttendanceAction.textContent = '📷 Scan Absen Masuk';
                    btnAttendanceAction.disabled = false;
                }
            }
        }

        function initAttendanceFromContext() {
            const att = context.todayAttendance;
            if (att && typeof att === 'object') {
                attendanceState.clock_in = att.clock_in || null;
                attendanceState.clock_out = att.clock_out || null;
                attendanceState.clock_in_time = att.clock_in_time || null;
                attendanceState.clock_out_time = att.clock_out_time || null;
                attendanceState.status = att.status || null;
                attendanceState.late_minutes = Number(att.late_minutes || 0);
            }
            attendanceState.shift = context.waiterShift || null;
            renderAttendanceBar();
        }

        async function loadAttendanceStatus() {
            if (!attendanceStatusUrl) return;
            try {
                const res = await fetch(attendanceStatusUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.attendance && typeof data.attendance === 'object') {
                    attendanceState.clock_in = data.attendance.clock_in || null;
                    attendanceState.clock_out = data.attendance.clock_out || null;
                    attendanceState.clock_in_time = data.attendance.clock_in_time || null;
                    attendanceState.clock_out_time = data.attendance.clock_out_time || null;
                    attendanceState.status = data.attendance.status || null;
                    attendanceState.late_minutes = Number(data.attendance.late_minutes || 0);
                }
                if (data.shift) {
                    attendanceState.shift = data.shift;
                }
                renderAttendanceBar();
            } catch (e) {
                console.log('loadAttendanceStatus failed', e);
            }
        }

        async function startAttendanceScan() {
            if (attendanceState.clock_out) return;
            if (attendanceState.clock_in && !clockOutEnabled) return;
            attendanceScanMode = attendanceState.clock_in ? 'clock_out' : 'clock_in';

            if (scannerModalTitleEl) {
                scannerModalTitleEl.textContent = '📷 Scan QR Code Absensi';
            }
            scannerTaskMetaEl.textContent = attendanceScanMode === 'clock_in'
                ? 'Scan QR code absensi untuk MASUK'
                : 'Scan QR code absensi untuk PULANG';
            scannerFeedbackEl.textContent = 'Arahkan kamera ke QR code absensi.';
            scannerModalEl.style.display = 'flex';
            scannerModalEl.setAttribute('aria-hidden', 'false');
            resetScannerTorchState();
            updateScannerFlashButton();

            activeScannerTaskId = '__ATTENDANCE__';
            activeScannerTaskLabel = attendanceScanMode === 'clock_in' ? 'Absen Masuk' : 'Absen Pulang';
            activeScannerExpectedBarcode = '';

            if (typeof Html5Qrcode === 'undefined') {
                scannerFeedbackEl.textContent = 'Library scanner belum termuat. Refresh halaman.';
                return;
            }

            if (!scannerInstance) {
                scannerInstance = new Html5Qrcode(scannerReaderElId);
            }

            await stopScannerIfRunning();

            try {
                const formats = typeof Html5QrcodeSupportedFormats !== 'undefined'
                    ? [Html5QrcodeSupportedFormats.QR_CODE]
                    : undefined;

                await scannerInstance.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 280, height: 280 },
                        ...(formats ? { formatsToSupport: formats } : {}),
                    },
                    async (decodedText) => {
                        if (activeScannerTaskId !== '__ATTENDANCE__') return;

                        const cleanValue = String(decodedText || '').trim();
                        if (!cleanValue.toUpperCase().startsWith('ATTENDANCE:')) {
                            scannerFeedbackEl.textContent = '❌ Bukan QR code absensi. Cari QR code dengan label ABSENSI.';
                            return;
                        }

                        scannerFeedbackEl.textContent = '⏳ Memproses absensi...';
                        await closeScannerModal();
                        await submitAttendanceScan(cleanValue);
                    },
                    () => {}
                );

                scannerRunning = true;
                syncScannerTorchStateFromTrack();
                if (!scannerTorchSupported) {
                    scannerFeedbackEl.textContent = 'Scanner aktif. Flash tidak tersedia, lanjut scan dengan pencahayaan normal.';
                }
            } catch (error) {
                resetScannerTorchState();
                updateScannerFlashButton();
                scannerFeedbackEl.textContent = `Gagal menyalakan kamera: ${error?.message || 'Unknown error'}`;
            }
        }

        async function submitAttendanceScan(scannedValue) {
            const url = attendanceScanMode === 'clock_in' ? attendanceClockInUrl : attendanceClockOutUrl;
            if (!url) {
                showFlash('error', 'URL absensi belum dikonfigurasi.');
                attendanceScanMode = null;
                return;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ scanned_value: scannedValue }),
                });

                const payload = await response.json();
                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Gagal memproses absensi.');
                }

                showFlash('success', payload?.message || (attendanceScanMode === 'clock_in' ? 'Absen masuk berhasil!' : 'Absen pulang berhasil!'));
                await loadAttendanceStatus();
            } catch (error) {
                showFlash('error', error?.message || 'Gagal memproses absensi.');
            } finally {
                attendanceScanMode = null;
            }
        }
        // === END ATTENDANCE ===

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
            const allowedTabs = ['rack', 'tasks', 'reports', 'bonus'];
            const targetTab = allowedTabs.includes(tab) ? tab : 'rack';

            panelRack.classList.toggle('active', targetTab === 'rack');
            panelStockTake?.classList.toggle('active', targetTab === 'stocktake');
            panelTasks.classList.toggle('active', targetTab === 'tasks');
            panelReports.classList.toggle('active', targetTab === 'reports');
            panelBonus.classList.toggle('active', targetTab === 'bonus');

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

            const isInsideTaskInputArea =
                (rackPendingContainer && rackPendingContainer.contains(active)) ||
                (generalPendingContainer && generalPendingContainer.contains(active));

            if (!isInsideTaskInputArea) {
                return false;
            }

            return active.matches('.js-stock-report, .js-complete-form input[name="note"], .js-photo-proof, .js-rack-search, .js-product-qty');
        }

        function normalizeSearchKeyword(value) {
            return String(value || '').trim().toLowerCase();
        }

        function buildRackTaskSearchText(task) {
            return normalizeSearchKeyword([
                task?.title,
                task?.rack_name,
                task?.rack_location,
                task?.rack_barcode_value,
            ].map((item) => String(item || '')).join(' '));
        }

        function matchRackTaskByKeyword(task, keyword) {
            if (keyword === '') {
                return true;
            }

            return buildRackTaskSearchText(task).includes(keyword);
        }

        function updateMenuBadge(badgeEls, count) {
            const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
            badgeEls.forEach((el) => {
                if (!(el instanceof HTMLElement)) {
                    return;
                }

                el.textContent = String(safeCount);
                el.classList.toggle('hidden', safeCount <= 0);
            });
        }

        function applyRackSearchFilterInPlace() {
            if (!rackPendingContainer) {
                return;
            }

            const rackSection = rackPendingContainer.querySelector('.js-rack-group-section');
            if (!(rackSection instanceof HTMLElement)) {
                return;
            }

            const inputEl = rackSection.querySelector('.js-rack-search');
            if (!(inputEl instanceof HTMLInputElement)) {
                return;
            }

            const keyword = normalizeSearchKeyword(inputEl.value);
            rackSearchKeyword = String(inputEl.value || '');

            const rackTaskItems = Array.from(rackSection.querySelectorAll('.js-rack-task-item'));
            const total = rackTaskItems.length;
            let visible = 0;

            rackTaskItems.forEach((item) => {
                if (!(item instanceof HTMLElement)) {
                    return;
                }

                const haystack = normalizeSearchKeyword(item.getAttribute('data-rack-search-text') || '');
                const matched = keyword === '' ? true : haystack.includes(keyword);
                item.classList.toggle('is-hidden', !matched);
                if (matched) {
                    visible += 1;
                }
            });

            const hintEl = rackSection.querySelector('.js-rack-search-hint');
            if (hintEl instanceof HTMLElement) {
                hintEl.textContent = keyword === ''
                    ? `${visible} dari ${total} rak tugas aktif ditampilkan.`
                    : `${visible} dari ${total} rak cocok dengan kata kunci "${keyword}".`;
            }

            const clearBtn = rackSection.querySelector('.js-rack-search-clear');
            if (clearBtn instanceof HTMLElement) {
                clearBtn.classList.toggle('hidden', keyword === '');
            }

            const emptyFilteredEl = rackSection.querySelector('.js-rack-search-empty');
            if (emptyFilteredEl instanceof HTMLElement) {
                emptyFilteredEl.classList.toggle('hidden', visible > 0 || keyword === '');
            }
        }

        function compareRackTaskOrder(a, b) {
            const aLoc = String(a?.rack_location || '').toLowerCase();
            const bLoc = String(b?.rack_location || '').toLowerCase();
            if (aLoc !== bLoc) {
                return aLoc.localeCompare(bLoc, 'id');
            }

            const aName = String(a?.rack_name || '').toLowerCase();
            const bName = String(b?.rack_name || '').toLowerCase();
            if (aName !== bName) {
                return aName.localeCompare(bName, 'id');
            }

            return parseTimestamp(b?.created_at) - parseTimestamp(a?.created_at);
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

        function renderRefillStep(task) {
            const shortageItems = refillStepByTask.get(task.id);
            if (!shortageItems || shortageItems.length === 0) return '';

            const itemsHtml = shortageItems.map(item => {
                const status = String(item.storage_status || 'missing');
                const storageQty = Number(item.storage_total_qty || 0);
                const racks = Array.isArray(item.storage_racks) ? item.storage_racks : [];
                const isAvailable = status === 'available';

                let storageBadge = '';
                if (isAvailable) {
                    const topRack = racks[0];
                    const rackHint = topRack ? ` di <strong>${escapeHtml(topRack.rack_name)}</strong>` : '';
                    storageBadge = `<div class="refill-step-storage refill-step-storage--available">✅ Stok gudang: <strong>${storageQty}</strong> ${escapeHtml(item.product_unit)}${rackHint}</div>`;
                } else if (status === 'empty') {
                    storageBadge = `<div class="refill-step-storage refill-step-storage--empty">⚠️ Stok gudang habis. <strong>Otomatis dibuat permintaan restock</strong> saat task diselesaikan.</div>`;
                } else {
                    // missing: tidak ter-assign ke rak gudang manapun. Tetap auto-PO supaya
                    // supervisor bisa beli dari supplier.
                    storageBadge = `<div class="refill-step-storage refill-step-storage--empty">⚠️ Tidak ada di gudang. <strong>Otomatis dibuat permintaan restock</strong> saat task diselesaikan.</div>`;
                }

                const inputValue = isAvailable ? item.standard_qty : item.initial_qty;
                const inputDisabled = isAvailable ? '' : 'disabled';
                const inputHint = isAvailable
                    ? `<span style="font-size:12px;color:#6b7280;">${escapeHtml(item.product_unit)}</span>`
                    : `<span style="font-size:11px;color:#b91c1c;">tidak bisa diambil</span>`;

                return `<div class="refill-step-item refill-step-item--${status}">
                    <div class="refill-step-name">${escapeHtml(item.product_name)}</div>
                    <div class="refill-step-info">Sebelum: ${item.initial_qty} / Standar: ${item.standard_qty}</div>
                    ${storageBadge}
                    <div class="refill-step-qty">
                        <input type="number" class="js-refill-qty" data-task-id="${escapeAttr(task.id)}" data-product-id="${escapeAttr(item.product_id)}" data-initial-qty="${item.initial_qty}" data-storage-status="${escapeAttr(status)}" min="0" max="999" value="${inputValue}" placeholder="Qty" ${inputDisabled}>
                        ${inputHint}
                    </div>
                </div>`;
            }).join('');

            const availableCount = shortageItems.filter(i => i.storage_status === 'available').length;
            const unavailableCount = shortageItems.length - availableCount;

            const hintParts = [];
            if (availableCount > 0) {
                hintParts.push(`Ambil <strong>${availableCount}</strong> produk dari gudang lalu update qty`);
            }
            if (unavailableCount > 0) {
                hintParts.push(`<strong>${unavailableCount}</strong> stok gudang tidak cukup, otomatis jadi permintaan restock`);
            }
            const hint = hintParts.join(' • ') || 'Klik selesai untuk menyimpan hasil cek rak.';

            return `<div class="refill-step" data-task-id="${escapeAttr(task.id)}">
                <div class="refill-step-header">⚠️ ${shortageItems.length} Produk Perlu Diisi Ulang</div>
                <div class="refill-step-hint">${hint}</div>
                ${itemsHtml}
            </div>`;
        }

        function renderPendingTaskCard(task) {
            const taskIdStr = String(task?.id || '');
            if (taskIdStr && !taskCompleteFormInstanceByTask.has(taskIdStr)) {
                taskCompleteFormInstanceByTask.set(taskIdStr, newFormInstanceId());
            }
            // Restore draft from localStorage if not yet in memory state
            if (taskIdStr && !noteDraftByTask.has(taskIdStr) && !scannedBarcodeByTask.has(taskIdStr)) {
                restoreTaskDraft(taskIdStr);
            }
            const requiresScan = isRackScanTask(task);
            const priority = task.priority || 'normal';
            const cls = requiresScan
                ? ''
                : (priority === 'urgent' ? 'urgent' : (priority === 'low' ? 'low' : ''));
            const scheduleText = task.scheduled_time
                ? `<div class="meta">Jadwal: ${escapeHtml(task.scheduled_for_date || '-')} ${escapeHtml(task.scheduled_time)}</div>`
                : '';
            const deadlineText = task.deadline_at
                ? `<div class="meta">Batas waktu: ${escapeHtml(formatDateTime(task.deadline_at))}</div>`
                : '';
            const repeatCount = Math.max(1, Number(task.repeat_count || 1));
            const completedCount = Number(task.completed_count || 0);
            const isRepeatTask = repeatCount > 1;
            const repeatProgressBlock = isRepeatTask
                ? `<div class="meta" style="margin: 6px 0; padding: 4px 8px; background: ${completedCount > 0 ? '#ecfdf5' : '#f0f9ff'}; border-radius: 6px; font-weight: 600; color: ${completedCount > 0 ? '#065f46' : '#1e40af'};">
                    🔄 Pengulangan: ${completedCount}/${repeatCount} selesai
                   </div>`
                : '';
            const requiresPhotoProof = Boolean(task?.requires_photo_proof);
            const existingScan = String(scannedBarcodeByTask.get(task.id) || '');
            const existingStockReport = String(stockReportItemsByTask.get(task.id) || '');
            const existingNoteDraft = String(noteDraftByTask.get(task.id) || '');
            const existingPhotoProof = photoProofByTask.get(task.id) || null;
            const existingPhotoDataUrl = String(existingPhotoProof?.dataUrl || '');
            const rackTargetScope = String(task.rack_target_scope || 'single');
            const rackId = String(task.rack_id || '');
            const rackProducts = rackId && rackProductsMap[rackId] ? rackProductsMap[rackId] : [];
            const hasRackProducts = rackProducts.length > 0;
            const existingChecklist = productChecklistByTask.get(task.id) || {};

            let productChecklistBlock = '';
            const showAssignBtn = requiresScan && existingScan && rackId;
            const assignBtnBlock = showAssignBtn
                ? `<div class="product-checklist-assign">
                        <button type="button" class="btn btn-soft js-open-assign-product"
                            data-task-id="${escapeAttr(task.id)}"
                            data-rack-id="${escapeAttr(rackId)}"
                            data-rack-name="${escapeAttr(task.rack_name || '')}">
                            ➕ Tambahkan produk lain ke rak ini
                        </button>
                        <div class="meta" style="font-size:11px;color:#6b7280;margin-top:4px;">Tidak ketemu produk yang ingin diisi qty? Tambahkan dari master.</div>
                    </div>`
                : '';

            if (requiresScan && existingScan && hasRackProducts) {
                const checklistItems = rackProducts.map((product) => {
                    const productData = existingChecklist[product.id] || {};
                    const isFilled = Boolean(productData.filled);
                    const actualQty = Number(productData.actual_qty || 0);
                    const standardQty = Number(product.standard_qty || 0);
                    const minQty = Number(product.min_qty || 0);
                    const qtyInputValue = isFilled ? String(actualQty) : '';
                    let itemClass = '';
                    let statusHtml = '';
                    if (isFilled) {
                        if (actualQty === 0) {
                            itemClass = 'habis';
                            statusHtml = '<span class="product-checklist-status habis">Habis</span>';
                        } else if (minQty > 0 && actualQty <= minQty) {
                            itemClass = 'restock';
                            statusHtml = '<span class="product-checklist-status restock">Perlu Restock!</span>';
                        } else if (actualQty < standardQty) {
                            itemClass = 'shortage';
                            statusHtml = `<span class="product-checklist-status shortage">Kurang ${standardQty - actualQty}</span>`;
                        } else {
                            itemClass = 'checked';
                            statusHtml = '<span class="product-checklist-status ok">OK</span>';
                        }
                    }

                    return `<div class="product-checklist-item ${itemClass}">
                        <div class="product-checklist-name">${escapeHtml(product.name)}</div>
                        <div class="product-checklist-qty">
                            <input type="number" class="js-product-qty" data-task-id="${escapeAttr(task.id)}" data-product-id="${escapeAttr(product.id)}" value="${qtyInputValue}" min="0" placeholder="Qty">
                            <span class="product-checklist-standard">/ ${standardQty} ${escapeHtml(product.unit)}</span>
                        </div>
                        ${statusHtml}
                    </div>`;
                }).join('');

                const filledCount = Object.values(existingChecklist).filter(v => v.filled).length;
                const shortageCount = Object.values(existingChecklist).filter(v => v.filled && v.actual_qty < (v.standard_qty || 0) && v.actual_qty > 0).length;
                const habisCount = Object.values(existingChecklist).filter(v => v.filled && v.actual_qty === 0).length;
                const restockCount = rackProducts.filter(p => {
                    const d = existingChecklist[p.id];
                    return d && d.filled && d.actual_qty > 0 && Number(p.min_qty || 0) > 0 && d.actual_qty <= Number(p.min_qty);
                }).length;
                let summaryText = `${filledCount}/${rackProducts.length} produk diisi`;
                if (restockCount > 0) {
                    summaryText += ` • ${restockCount} perlu restock`;
                }
                if (shortageCount > 0) {
                    summaryText += ` • ${shortageCount} produk kurang`;
                }
                if (habisCount > 0) {
                    summaryText += ` • ${habisCount} habis`;
                }
                if (habisCount > 0) {
                    summaryText += ` \u2022 ${habisCount} habis`;
                }

                productChecklistBlock = `<div class="product-checklist" data-task-id="${escapeAttr(task.id)}">
                    <div class="product-checklist-header">\ud83d\udccb Checklist Produk Rak (${rackProducts.length} produk)</div>
                    ${checklistItems}
                    <div class="product-checklist-summary">${summaryText}</div>
                    ${assignBtnBlock}
                </div>`;
            } else if (showAssignBtn && !hasRackProducts) {
                productChecklistBlock = `<div class="product-checklist" data-task-id="${escapeAttr(task.id)}">
                    <div class="product-checklist-header">\ud83d\udccb Checklist Produk Rak (0 produk)</div>
                    <div class="product-checklist-empty">Belum ada produk di rak ini. Tambahkan produk untuk mulai input qty.</div>
                    ${assignBtnBlock}
                </div>`;
            }

            const stockReportBlock = existingScan
                ? `<label class="meta" style="display:block; margin-top: 8px; margin-bottom: 4px; color:#111827; font-weight:600;">Laporan Barang Menipis/Habis (Opsional)</label>
                    <textarea class="input js-stock-report" name="stock_report_items" data-task-id="${escapeAttr(task.id)}" maxlength="2000" placeholder="Jika ada barang menipis/habis, tulis di sini. Boleh dikosongkan jika tidak ada.">${escapeHtml(existingStockReport)}</textarea>
                    <div class="meta" style="font-size:12px; color:#6b7280;">Alur cek rak: scan QR code rak → (jika ada) isi barang menipis/habis → selesai.</div>`
                : `<div class="meta" style="font-size:12px; color:#9a3412; margin-top: 8px;">🔒 Form barang menipis/habis muncul setelah QR code rak berhasil di-scan.</div>`;
            const requiresPhotoBefore = Boolean(task?.requires_photo_before);
            const existingPhotoBefore = photoBeforeByTask.get(task.id) || null;
            const existingPhotoBeforeDataUrl = String(existingPhotoBefore?.dataUrl || '');
            const photoBeforeBlock = requiresPhotoBefore
                ? `<div class="photo-proof-wrap" style="border-color: #fbbf24; background: #fffbeb;">
                        <div class="photo-proof-head" style="color: #92400e;">
                            <span>📷 Foto SEBELUM (Kondisi Awal) — Wajib</span>
                            ${existingPhotoBeforeDataUrl
                                ? `<button type="button" class="btn-photo-clear js-photo-before-clear" data-task-id="${escapeAttr(task.id)}">Hapus</button>`
                                : ''}
                        </div>
                        <input
                            class="input js-photo-before"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            data-task-id="${escapeAttr(task.id)}"
                            style="margin-bottom: 6px;"
                        >
                        <div class="photo-proof-meta">Foto kondisi rak/area SEBELUM dikerjakan.</div>
                        ${existingPhotoBeforeDataUrl
                            ? `<img src="${escapeAttr(existingPhotoBeforeDataUrl)}" alt="Foto sebelum" class="photo-proof-preview">
                               <div class="photo-proof-meta" style="color:#065f46;">✅ Foto sebelum siap.</div>`
                            : '<div class="photo-proof-meta" style="color:#9a3412;">⚠️ Belum ada foto sebelum.</div>'}
                    </div>`
                : '';
            const photoProofBlock = requiresPhotoProof
                ? `<div class="photo-proof-wrap">
                        <div class="photo-proof-head">
                            <span>📷 ${requiresPhotoBefore ? 'Foto SESUDAH (Hasil Akhir) — Wajib' : 'Bukti Foto Wajib'}</span>
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
                        <div class="photo-proof-meta">${requiresPhotoBefore ? 'Foto kondisi SESUDAH selesai dikerjakan.' : 'Ambil/upload foto bukti. Sistem akan kompres otomatis sebelum kirim.'}</div>
                        ${existingPhotoDataUrl
                            ? `<img src="${escapeAttr(existingPhotoDataUrl)}" alt="Bukti foto task ${escapeAttr(task.title || '-')}" class="photo-proof-preview">
                               <div class="photo-proof-meta">Foto siap dikirim • ${escapeHtml(formatBytes(existingPhotoProof?.sizeBytes || estimateDataUrlBytes(existingPhotoDataUrl)))}.</div>`
                            : '<div class="photo-proof-meta" style="color:#9a3412;">⚠️ Belum ada foto bukti.</div>'}
                    </div>`
                : '';
            const taskRackType = getRackTypeForTask(task);
            const rackTypeBadge = taskRackType === 'display'
                ? '<span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;background:#fef3c7;color:#92400e;margin-left:6px;">🏪 Display</span>'
                : '<span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;background:#dbeafe;color:#1e40af;margin-left:6px;">📦 Gudang</span>';
            const rackBlock = requiresScan
                ? `<div style="margin: 8px 0 10px 0;">
                        <span class="tag-rack">📦 Cek Rak - Wajib Scan</span>${rackTypeBadge}
                        <div class="meta">Rak: <strong>${escapeHtml(task.rack_name || '-')}</strong> (${escapeHtml(task.rack_location || '-')})</div>
                        <div class="meta">QR Code Rak: <code>${escapeHtml(task.rack_barcode_value || '-')}</code></div>
                        ${rackTargetScope === 'all' ? '<div class="meta" style="font-size:12px;color:#334155;">🎯 Bagian dari assignment <b>Semua Rak Aktif</b> (wajib scan tiap rak melalui task masing-masing).</div>' : ''}
                        <input type="hidden" name="scanned_barcode" value="${escapeAttr(existingScan)}">
                        <button type="button" class="btn btn-scan js-open-scanner" data-task-id="${escapeAttr(task.id)}" data-task-label="${escapeAttr(task.title || 'Task')}" data-rack-name="${escapeAttr(task.rack_name || '-')}" data-rack-barcode="${escapeAttr(task.rack_barcode_value || '')}">📷 Scan QR Code Rak</button>
                        <div class="meta" style="font-size:12px;color:${existingScan ? '#166534' : '#9a3412'};" data-scan-status>
                            ${existingScan ? `✅ QR code ter-scan: <code>${escapeHtml(existingScan)}</code>` : '⚠️ Belum scan QR code rak.'}
                        </div>
                        ${hasRackProducts ? productChecklistBlock : stockReportBlock}
                        ${renderRefillStep(task)}
                    </div>`
                : '';
            const defaultMetaBlock = requiresScan
                ? ''
                : `<div class="meta">Prioritas: ${escapeHtml(String(priority).toUpperCase())}</div>
                   <div class="meta">Dibuat: ${escapeHtml(formatDateTime(task.created_at))}</div>
                   ${scheduleText}
                   ${deadlineText}`;

            const isInRefillMode = refillStepByTask.has(task.id);
            const nowTs = Math.floor(Date.now() / 1000);
            const claimedBy = String(task?.claimed_by || '');
            const claimedByName = String(task?.claimed_by_name || 'waiter lain');
            const claimExpiresAt = Number(task?.claim_expires_at || 0);
            const claimActive = claimedBy !== '' && claimExpiresAt > nowTs;
            const claimIsMine = claimActive && claimedBy === waiterId;
            const claimBanner = claimActive
                ? (claimIsMine
                    ? `<div class="meta" style="margin:6px 0; padding:6px 8px; border-radius:6px; background:#ecfdf5; color:#065f46; font-weight:600;">🔐 Anda klaim sampai ${escapeHtml(formatDateTime(claimExpiresAt))}</div>`
                    : `<div class="meta" style="margin:6px 0; padding:6px 8px; border-radius:6px; background:#fff7ed; color:#9a3412; font-weight:600;">⏳ Sedang dikerjakan oleh ${escapeHtml(claimedByName)} sampai ${escapeHtml(formatDateTime(claimExpiresAt))}</div>`)
                : '';
            const completeBtnLabel = isRepeatTask
                ? (completedCount + 1 >= repeatCount ? '✅ Selesaikan (Terakhir)' : `✅ Selesai #${completedCount + 1}`)
                : '✅ Verifikasi Selesai';
            const rackCompleteBtnLabel = isInRefillMode ? '✅ Sudah Diisi, Selesaikan' : '✅ Selesaikan Cek Rak';
            const completeActionBlock = requiresScan
                ? (existingScan
                    ? `<form class="js-complete-form" data-task-id="${escapeHtml(task.id)}" style="margin-top: 10px;">
                           <button type="submit" class="btn btn-done">${rackCompleteBtnLabel}</button>
                       </form>`
                    : '<div class="meta" style="font-size:12px; color:#9a3412; margin-top: 10px;">🔒 Tombol selesai akan muncul setelah QR code rak berhasil di-scan.</div>')
                : `<form class="js-complete-form" data-task-id="${escapeHtml(task.id)}" style="margin-top: 10px;">
                       <input class="input" type="text" name="note" maxlength="500" placeholder="Catatan verifikasi (opsional)" value="${escapeAttr(existingNoteDraft)}">
                       <button type="submit" class="btn btn-done">${completeBtnLabel}</button>
                   </form>`;

            // Klaim hanya berguna untuk task yg dishare oleh banyak waiter sekaligus.
            // Sembunyikan untuk:
            // - assignment_type='single' (sudah jelas 1 waiter)
            // - assignment_strategy='role_round_robin' (rolling rotation: scanner sudah pilih 1 waiter)
            // Tampilkan klaim untuk role-based shared task (semua role member punya akses task sama).
            const taskAssignmentType = String(task?.assignment_type || '');
            const taskAssignmentStrategy = String(task?.assignment_strategy || '');
            const showClaimAction = taskAssignmentType !== 'single'
                && taskAssignmentStrategy !== 'role_round_robin';
            const claimActionBlock = !showClaimAction
                ? ''
                : `<div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn ${claimActive && !claimIsMine ? '' : 'btn-soft'} js-claim-task" data-task-id="${escapeAttr(task.id)}" ${claimActive && !claimIsMine ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''}>▶️ Mulai</button>
                        ${claimIsMine ? `<button type="button" class="btn btn-soft js-release-task" data-task-id="${escapeAttr(task.id)}">Lepas klaim</button>` : ''}
                   </div>`;

            return `<div class="card ${cls}">
                <div class="title">${escapeHtml(task.title || '-')}</div>
                ${requiresScan ? '' : (task.description ? `<div class="desc">${escapeHtml(task.description)}</div>` : '')}
                ${repeatProgressBlock}
                ${claimBanner}
                ${defaultMetaBlock}
                ${rackBlock}
                ${photoBeforeBlock}
                ${photoProofBlock}
                ${claimActionBlock}
                ${completeActionBlock}
            </div>`;
        }

        async function claimTask(taskId) {
            const url = claimUrlTemplate.replace('__TASK_ID__', encodeURIComponent(taskId));
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
            const payload = await response.json();
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || 'Tugas sedang dikerjakan waiter lain.');
            }
            return payload;
        }

        async function releaseTaskClaim(taskId) {
            const url = releaseUrlTemplate.replace('__TASK_ID__', encodeURIComponent(taskId));
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
            const payload = await response.json();
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || 'Gagal melepas klaim task.');
            }
            return payload;
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

        function getRackTypeForTask(task) {
            const rackId = String(task?.rack_id || '');
            if (rackId && rackTypesMap[rackId]) return rackTypesMap[rackId];
            return String(task?.rack_type || 'storage');
        }

        function renderRackTaskGroupSection(rackTasks) {
            const sortedRackTasks = rackTasks.slice().sort(compareRackTaskOrder);
            const searchKeyword = normalizeSearchKeyword(rackSearchKeyword);

            // Split by rack type
            const displayTasks = sortedRackTasks.filter(t => getRackTypeForTask(t) === 'display');
            const storageTasks = sortedRackTasks.filter(t => getRackTypeForTask(t) !== 'display');

            const displayDone = displayTasks.filter(t => t.status === 'done').length;
            const storageDone = storageTasks.filter(t => t.status === 'done').length;

            const hintText = searchKeyword === ''
                ? `${rackTasks.length} tugas cek rak aktif.`
                : `${rackTasks.length} rak cocok dengan kata kunci "${escapeHtml(searchKeyword)}".`;

            let groupsHtml = '';

            if (displayTasks.length > 0) {
                groupsHtml += `<div class="rack-type-group" style="margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fef3c7;border-radius:8px;margin-bottom:8px;">
                        <span style="font-size:16px;">🏪</span>
                        <span style="font-weight:700;font-size:14px;color:#92400e;">Rak Display</span>
                        <span style="margin-left:auto;font-size:12px;color:#92400e;font-weight:600;">${displayTasks.length} rak</span>
                    </div>
                    <div class="grid">${displayTasks.map((task) => `<div class="rack-task-item js-rack-task-item" data-rack-search-text="${escapeAttr(buildRackTaskSearchText(task))}">${renderPendingTaskCard(task)}</div>`).join('')}</div>
                </div>`;
            }

            if (storageTasks.length > 0) {
                groupsHtml += `<div class="rack-type-group" style="margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#dbeafe;border-radius:8px;margin-bottom:8px;">
                        <span style="font-size:16px;">📦</span>
                        <span style="font-weight:700;font-size:14px;color:#1e40af;">Rak Gudang</span>
                        <span style="margin-left:auto;font-size:12px;color:#1e40af;font-weight:600;">${storageTasks.length} rak</span>
                    </div>
                    <div class="grid">${storageTasks.map((task) => `<div class="rack-task-item js-rack-task-item" data-rack-search-text="${escapeAttr(buildRackTaskSearchText(task))}">${renderPendingTaskCard(task)}</div>`).join('')}</div>
                </div>`;
            }

            return `<section class="task-group js-rack-group-section">
                <div class="task-group-head">
                    <div>
                        <h3 class="task-group-title">📦 Tugas Cek Rak</h3>
                        <div class="task-group-subtitle">Dikelompokkan berdasarkan tipe rak. Gunakan pencarian untuk langsung lompat ke rak target.</div>
                    </div>
                    <span class="task-group-badge">${rackTasks.length} tugas</span>
                </div>

                <div class="rack-tools">
                    <input
                        class="input js-rack-search"
                        type="search"
                        placeholder="Cari rak (nama/lokasi/QR)..."
                        value="${escapeAttr(rackSearchKeyword)}"
                        autocomplete="off"
                    >
                    <button type="button" class="btn-soft js-rack-search-clear${searchKeyword === '' ? ' hidden' : ''}">Reset</button>
                    <div class="rack-tools-hint js-rack-search-hint">${hintText}</div>
                </div>

                ${sortedRackTasks.length
                    ? `${groupsHtml}
                       <div class="task-group-empty js-rack-search-empty hidden">Tidak ada rak yang cocok dengan pencarian. Coba kata kunci lain atau reset filter.</div>`
                    : '<div class="task-group-empty">Tidak ada rak yang cocok dengan pencarian. Coba kata kunci lain atau reset filter.</div>'}
            </section>`;
        }

        function renderPending(pendingTasks) {
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
            for (const taskId of Array.from(photoBeforeByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    photoBeforeByTask.delete(taskId);
                }
            }
            for (const taskId of Array.from(productChecklistByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    productChecklistByTask.delete(taskId);
                }
            }
            for (const taskId of Array.from(refillStepByTask.keys())) {
                if (!pendingTaskIds.has(taskId)) {
                    refillStepByTask.delete(taskId);
                }
            }

            const rackTasks = pendingTasks.filter((task) => isRackScanTask(task));
            const stockTakeTasks = rackTasks.filter((task) => getRackTypeForTask(task) === 'storage');
            const generalTasks = pendingTasks.filter((task) => !isRackScanTask(task));

            if (rackPendingCountEl) {
                rackPendingCountEl.textContent = String(rackTasks.length);
            }
            updateMenuBadge(rackMenuBadgeEls, rackTasks.length);

            if (generalPendingCountEl) {
                generalPendingCountEl.textContent = String(generalTasks.length);
            }
            updateMenuBadge(generalMenuBadgeEls, generalTasks.length);

            // Build shift-not-started message if applicable
            var shiftNotStartedMsg = '';
            if (shiftStartTime && !rackTasks.length && !generalTasks.length) {
                var now = new Date();
                var parts = shiftStartTime.split(':');
                var shiftHour = parseInt(parts[0], 10);
                var shiftMin = parseInt(parts[1], 10);
                if (now.getHours() < shiftHour || (now.getHours() === shiftHour && now.getMinutes() < shiftMin)) {
                    shiftNotStartedMsg = '<div class="empty" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:16px;border-radius:8px;text-align:center;">Shift Anda dimulai pukul ' + shiftStartTime + '. Tugas akan muncul saat shift dimulai.</div>';
                }
            }

            if (rackPendingContainer) {
                rackPendingContainer.innerHTML = rackTasks.length
                    ? renderRackTaskGroupSection(rackTasks)
                    : (shiftNotStartedMsg || '<div class="empty">Tidak ada tugas cek rak aktif saat ini.</div>');
                applyRackSearchFilterInPlace();
            }

            if (stockTakePendingCountEl) {
                stockTakePendingCountEl.textContent = String(stockTakeTasks.length);
            }
            if (stockTakePendingContainer) {
                stockTakePendingContainer.innerHTML = stockTakeTasks.length
                    ? `<section class="task-group"><div class="task-group-head"><div><h3 class="task-group-title">🧾 Rak Gudang</h3><div class="task-group-subtitle">Scan rak, cek stok produk, lalu submit. Item shortage akan otomatis dibuat permintaan restock.</div></div><span class="task-group-badge">${stockTakeTasks.length} tugas</span></div><div class="grid">${stockTakeTasks.map(renderPendingTaskCard).join('')}</div></section>`
                    : '<div class="empty">Tidak ada tugas cek rak gudang aktif saat ini.</div>';
            }

            if (generalPendingContainer) {
                generalPendingContainer.innerHTML = generalTasks.length
                    ? renderTaskGroupSection(
                        '📝 Tugas Umum',
                        'Tugas operasional waiter di luar cek rak.',
                        generalTasks,
                        'Tidak ada tugas umum aktif saat ini.'
                    )
                    : (shiftNotStartedMsg || '<div class="empty">Tidak ada tugas umum aktif saat ini.</div>');
            }
        }

        function renderHistory(historyTasks, historyTarget, emptyMessage) {
            if (!historyTarget) {
                return;
            }

            if (!historyTasks.length) {
                historyTarget.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #6b7280;">${escapeHtml(emptyMessage || 'Belum ada riwayat.')}</td></tr>`;
                return;
            }

            historyTarget.innerHTML = historyTasks.map((task) => {
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
                    <td>${task.completed_product_checklist
                        ? (() => {
                            const items = Object.values(task.completed_product_checklist);
                            const total = items.length;
                            const checked = items.filter(i => i.checked).length;
                            const shortages = items.filter(i => i.is_shortage).length;
                            return shortages > 0
                                ? `<span style="color:#9a3412;">\u26a0\ufe0f ${checked}/${total} dicek, ${shortages} kurang</span>`
                                : `<span style="color:#166534;">\u2705 ${checked}/${total} produk OK</span>`;
                        })()
                        : (isRackScanTask(task)
                            ? (task.completed_no_out_of_stock
                                ? '<span style="color:#166534;">✅ Tidak ada barang habis</span>'
                                : (task.completed_stock_report
                                    ? `<span style="color:#9a3412;">⚠️ ${escapeHtml(task.completed_stock_report)}</span>`
                                    : '<span style="color:#9ca3af;">-</span>'))
                            : '-')}</td>
                    <td>${task.completed_photo_proof_url
                        ? `<button type="button" class="btn-photo-view js-photo-view" data-task-id="${escapeAttr(task.id)}">📷 Lihat Foto</button>`
                        : (task.requires_photo_proof
                            ? '<span style="color:#9a3412; font-size:12px;">(wajib foto)</span>'
                            : '-')}</td>
                    <td>
                        ${isRackScanTask(task)
                            ? `Selesai: ${escapeHtml(formatDateTime(task.completed_at))}`
                            : `Dibuat: ${escapeHtml(formatDateTime(task.created_at))}
                               <div style="font-size: 12px; color: #6b7280;">
                                   Selesai: ${escapeHtml(formatDateTime(task.completed_at))}
                               </div>`}
                    </td>
                </tr>`;
            }).join('');
        }

        function renderAllTasks() {
            const normalized = waiterTasks
                .map(normalizeTask)
                .filter((task) => task.assigned_waiter_id === waiterId && task.id !== '');

            // Pending = pending + in_progress (task ter-klaim tetap di list pending,
            // bukan pindah ke history)
            const pendingTasks = normalized
                .filter((task) => task.status === 'pending' || task.status === 'in_progress')
                .sort((a, b) => b.created_at - a.created_at);

            const historyTasks = normalized
                .filter((task) => task.status !== 'pending' && task.status !== 'in_progress')
                .sort((a, b) => {
                    const bScore = b.completed_at || b.created_at;
                    const aScore = a.completed_at || a.created_at;
                    return bScore - aScore;
                });

            const rackHistoryTasks = historyTasks.filter((task) => isRackScanTask(task));
            const generalHistoryTasks = historyTasks.filter((task) => !isRackScanTask(task));

            renderPending(pendingTasks);
            renderHistory(rackHistoryTasks, rackHistoryBody, 'Belum ada riwayat cek rak.');
            renderHistory(generalHistoryTasks, generalHistoryBody, 'Belum ada riwayat tugas umum.');
        }

        function hydrateTasksFromPayload(payload) {
            waiterTasks = [
                ...(Array.isArray(payload?.pending_tasks) ? payload.pending_tasks : []),
                ...(Array.isArray(payload?.task_history) ? payload.task_history : []),
            ];

            if (payload && typeof payload === 'object' && payload.rack_products_map) {
                rackProductsMap = payload.rack_products_map;
            }
            if (payload && typeof payload === 'object' && payload.rack_types_map) {
                rackTypesMap = payload.rack_types_map;
            }

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
                clearDraftLocal(activityDraftKey(reportDate));

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

        // Bandwidth: limitToLast(50) untuk batasi initial snapshot.
        // Tasks listener mempersempit snapshot ke 50 task terbaru saja.
        // Empty waiter_task_idempotency listener DIHAPUS (waste bandwidth).
        (function setupTaskListener() {
            if (!window.RTDB_READY || !window.firebaseDB) return;
            const debounceMs = 600;
            let pending = false;
            const trigger = () => {
                if (pending) return;
                pending = true;
                setTimeout(() => {
                    pending = false;
                    pollTasks().catch(() => {});
                }, debounceMs);
            };
            try {
                // limitToLast(50) untuk batasi initial download. Push-id RTDB
                // chronological, jadi task terbaru dijamin masuk.
                window.firebaseDB.ref('waiter_tasks').limitToLast(50).on('value', trigger);
            } catch (e) {
                console.warn('[RTDB] tasks listener failed:', e);
            }
        })();

        (function lazyLoadBonusSummary() {
            const widget = document.getElementById('bonusSummaryMini');
            if (!widget || widget.dataset.lazy !== '1') return;

            const fetchAndRender = async () => {
                try {
                    const res = await fetch(`{{ route('waiter.bonus.api') }}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();
                    renderBonusSummary(data);
                } catch (e) {
                    console.warn('Lazy bonus fetch failed', e);
                }
            };

            window.__lazyLoadBonusSummary = fetchAndRender;

            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => fetchAndRender(), { timeout: 1500 });
            } else {
                setTimeout(fetchAndRender, 800);
            }

            function renderBonusSummary(d) {
                const netPoints = parseInt(d.net_points || 0, 10);
                const theoreticalMax = parseInt(d.theoretical_max || 0, 10);
                const percentage = parseFloat(d.percentage || 0);
                const projectedAmount = computeProjectedAmount(d);
                const tier = computeTier(percentage);

                const grid = widget.querySelector('.bsm-grid');
                if (!grid) return;
                grid.innerHTML = `
                    <div class="bsm-stat"><div class="bsm-stat-value ${tier.color}">${netPoints} <span class="bsm-stat-sub">/ ${theoreticalMax}</span></div><div class="bsm-stat-label">Total Poin</div></div>
                    <div class="bsm-stat"><div class="bsm-stat-value">${percentage.toFixed(0)}%</div><div class="bsm-stat-label">${tier.label}</div></div>
                    <div class="bsm-stat"><div class="bsm-stat-value">Rp ${formatRp(projectedAmount)}</div><div class="bsm-stat-label">Proyeksi Bonus</div></div>
                `;
                widget.dataset.lazy = '0';
            }

            function computeTier(pct) {
                if (pct >= 80) return { label: 'Excellent', color: 'color-green' };
                if (pct >= 70) return { label: 'Good', color: 'color-green' };
                if (pct >= 60) return { label: 'Average', color: 'color-orange' };
                return { label: 'Needs Improvement', color: 'color-red' };
            }

            function computeProjectedAmount(d) {
                const pct = parseFloat(d.percentage || 0);
                if (pct >= 80) return 300000;
                if (pct >= 70) return 250000;
                if (pct >= 60) return 200000;
                return 0;
            }

            function formatRp(n) {
                return (parseInt(n, 10) || 0).toLocaleString('id-ID');
            }
        })();

        (function attachPenaltyListener() {
            if (!window.RTDB_READY || !window.firebase || !window.firebaseDB) return;

            const waiterId = '{{ session('waiter_id') }}';
            if (!waiterId) return;

            const sessionStart = Math.floor(Date.now() / 1000);
            const seenPenalties = new Set();

            window.firebaseDB.ref('waiter_penalties')
                .orderByChild('waiter_id')
                .equalTo(waiterId)
                .limitToLast(20)
                .on('child_added', (snap) => {
                    const data = snap.val() || {};
                    const id = snap.key;
                    const createdAt = parseInt(data.created_at || 0, 10);

                    if (createdAt < sessionStart - 5) return;
                    if (seenPenalties.has(id)) return;
                    seenPenalties.add(id);

                    const label = data.penalty_label || data.penalty_type || 'Pelanggaran';
                    const points = Math.abs(parseInt(data.points_deducted || 0, 10));
                    const reason = data.reason || '';

                    showPenaltyToast({
                        title: `Penalty: ${label}`,
                        points: points,
                        reason: reason,
                    });

                    setTimeout(() => {
                        const lazyFn = window.__lazyLoadBonusSummary;
                        if (typeof lazyFn === 'function') lazyFn();
                    }, 1500);
                });
        })();

        function showPenaltyToast({ title, points, reason }) {
            let wrap = document.getElementById('penaltyToastWrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = 'penaltyToastWrap';
                wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:340px;';
                document.body.appendChild(wrap);
            }

            const toast = document.createElement('div');
            toast.style.cssText = 'background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #dc2626;padding:12px 14px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:slideInRight 0.3s ease;';
            toast.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:start;gap:8px;">
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;color:#991b1b;margin-bottom:4px;">⚠️ ${escapeHtml(title)}</div>
                        <div style="font-size:13px;color:#7f1d1d;font-weight:600;margin-bottom:4px;">-${points} poin</div>
                        ${reason ? `<div style="font-size:12px;color:#64748b;">${escapeHtml(reason)}</div>` : ''}
                    </div>
                    <button type="button" style="background:none;border:0;font-size:18px;color:#991b1b;cursor:pointer;padding:0 4px;line-height:1;">×</button>
                </div>
            `;
            const closer = toast.firstElementChild?.querySelector('button');
            if (closer) closer.addEventListener('click', () => toast.remove());

            wrap.appendChild(toast);

            setTimeout(() => {
                toast.style.transition = 'opacity 0.3s, transform 0.3s';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 8000);
        }

        // ===== REWARD OVERLAY =====
        const rewardOverlayEl = document.getElementById('reward-overlay');
        const rewardConfettiEl = document.getElementById('reward-confetti');
        const rewardPointsValueEl = document.getElementById('reward-points-value');
        const rewardPerfectEl = document.getElementById('reward-perfect');
        const rewardBreakdownEl = document.getElementById('reward-breakdown');
        const rewardProgressFillEl = document.getElementById('reward-progress-fill');
        const rewardProgressTextEl = document.getElementById('reward-progress-text');
        const rewardSubtitleEl = document.getElementById('reward-subtitle');
        const rewardTitleEl = document.getElementById('reward-title');
        const rewardMessageEl = document.getElementById('reward-message');
        const rewardTrophyEl = document.getElementById('reward-trophy');
        const rewardCloseBtn = document.getElementById('reward-btn-close');

        const REWARD_MESSAGES = [
            '"Setiap tugas yang selesai membawa kamu lebih dekat ke bonus bulan ini."',
            '"Kerja keras hari ini, hadiah besok. Lanjutkan!"',
            '"Konsistensi adalah kunci. Tetap semangat!"',
            '"Setiap poin berarti. Kamu hebat!"',
            '"Tugas selesai = poin bertambah = bonus mendekat."',
            '"Pertahankan ritme ini, kamu sedang on fire 🔥"',
        ];

        const REWARD_CONFETTI_COLORS = ['#fbbf24', '#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#a855f7', '#fde047'];

        let rewardAutoCloseTimer = null;

        function spawnRewardConfetti(count = 36) {
            if (!rewardConfettiEl) return;
            rewardConfettiEl.innerHTML = '';
            for (let i = 0; i < count; i++) {
                const piece = document.createElement('span');
                const left = Math.random() * 100;
                const delay = Math.random() * 0.6;
                const duration = 1.8 + Math.random() * 1.2;
                const color = REWARD_CONFETTI_COLORS[Math.floor(Math.random() * REWARD_CONFETTI_COLORS.length)];
                const rotate = Math.random() * 360;
                const isCircle = Math.random() > 0.65;
                piece.style.left = left + '%';
                piece.style.background = color;
                piece.style.animationDelay = delay + 's';
                piece.style.animationDuration = duration + 's';
                piece.style.transform = `rotate(${rotate}deg)`;
                if (isCircle) {
                    piece.style.borderRadius = '50%';
                    piece.style.width = '8px';
                    piece.style.height = '8px';
                }
                rewardConfettiEl.appendChild(piece);
            }
        }

        function animateCountUp(el, from, to, durationMs) {
            if (!el) return;
            const start = performance.now();
            const delta = to - from;
            function tick(now) {
                const elapsed = now - start;
                const progress = Math.min(1, elapsed / durationMs);
                // easeOutCubic
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = Math.round(from + delta * eased);
                el.textContent = String(value);
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    el.textContent = String(to);
                }
            }
            requestAnimationFrame(tick);
        }

        function spawnFloatingPoints(originEl, points) {
            if (!originEl || !points || points <= 0) return;
            const rect = originEl.getBoundingClientRect();
            const float = document.createElement('div');
            float.className = 'reward-float';
            float.textContent = `+${points} pts`;
            float.style.left = (rect.left + rect.width / 2 - 24) + 'px';
            float.style.top = (rect.top + 8) + 'px';
            document.body.appendChild(float);
            setTimeout(() => float.remove(), 1500);
        }

        function pickRewardMessage(reward) {
            if (reward?.perfect_day) {
                return '"PERFECT DAY! Semua kategori penuh — kamu bintangnya hari ini! ⭐"';
            }
            return REWARD_MESSAGES[Math.floor(Math.random() * REWARD_MESSAGES.length)];
        }

        function pickRewardTrophy(reward) {
            const earned = Number(reward?.points_earned || 0);
            if (reward?.perfect_day) return '🏆';
            if (earned >= 8) return '🌟';
            if (earned >= 4) return '⭐';
            if (earned >= 1) return '✨';
            return '🎉';
        }

        function showRewardOverlay(reward, payload) {
            if (!rewardOverlayEl) return;

            const pointsEarned = Math.max(0, Number(reward?.points_earned || 0));
            const dailyTotal = Number(reward?.daily_total || 0);
            const perfectDay = Boolean(reward?.perfect_day);
            const tasksDone = Number(reward?.tasks_done || 0);
            const tasksTotal = Number(reward?.tasks_total || 0);
            const breakdown = Array.isArray(reward?.category_breakdown) ? reward.category_breakdown : [];

            // Title varies by partial vs full completion
            if (payload?.partial) {
                const cc = Number(payload.completed_count || 0);
                const rc = Number(payload.repeat_count || 0);
                rewardTitleEl.textContent = 'Pengulangan Selesai!';
                rewardSubtitleEl.textContent = `${cc} dari ${rc} pengulangan rampung. Lanjut!`;
            } else {
                rewardTitleEl.textContent = 'Tugas Selesai!';
                rewardSubtitleEl.textContent = pointsEarned > 0
                    ? `+${pointsEarned} poin dikreditkan ke bonus bulanan kamu.`
                    : 'Tugas tercatat. Poin akan diperbarui setelah evaluasi.';
            }

            rewardTrophyEl.textContent = pickRewardTrophy(reward);
            rewardMessageEl.textContent = pickRewardMessage(reward);

            // Perfect day badge
            if (perfectDay) {
                rewardPerfectEl.classList.add('is-shown');
            } else {
                rewardPerfectEl.classList.remove('is-shown');
            }

            // Progress bar
            const progressPct = tasksTotal > 0 ? Math.min(100, Math.round((tasksDone / tasksTotal) * 100)) : 0;
            rewardProgressTextEl.textContent = tasksTotal > 0 ? `${tasksDone}/${tasksTotal} tugas` : 'Tidak ada target hari ini';
            // Reset to 0 first so the transition animates
            rewardProgressFillEl.style.width = '0%';

            // Breakdown
            rewardBreakdownEl.innerHTML = '';
            if (breakdown.length > 0) {
                breakdown.forEach((cat) => {
                    const delta = Number(cat?.delta || 0);
                    const after = Number(cat?.after || 0);
                    const isGain = delta > 0;
                    const card = document.createElement('div');
                    card.className = 'reward-cat' + (isGain ? ' is-gain' : '');
                    card.innerHTML = `
                        <span class="reward-cat-icon">${cat.icon || '⭐'}</span>
                        <span class="reward-cat-label">${cat.label || ''}</span>
                        <span class="reward-cat-value">${after}</span>
                        ${isGain ? `<span class="reward-cat-delta">+${delta}</span>` : ''}
                    `;
                    rewardBreakdownEl.appendChild(card);
                });
                rewardBreakdownEl.style.display = 'grid';
            } else {
                rewardBreakdownEl.style.display = 'none';
            }

            // Open
            rewardOverlayEl.setAttribute('aria-hidden', 'false');
            rewardOverlayEl.classList.add('is-open');
            document.body.style.overflow = 'hidden';

            // Animations: count-up + confetti + progress fill
            rewardPointsValueEl.textContent = '0';
            spawnRewardConfetti(perfectDay ? 60 : 36);

            // Slight delay so the modal scale-in finishes before count-up starts
            setTimeout(() => {
                animateCountUp(rewardPointsValueEl, 0, pointsEarned > 0 ? pointsEarned : dailyTotal, 1100);
                rewardProgressFillEl.style.width = progressPct + '%';
            }, 280);

            // Auto-close after 6s for partial, 8s for full
            clearTimeout(rewardAutoCloseTimer);
            rewardAutoCloseTimer = setTimeout(closeRewardOverlay, payload?.partial ? 6000 : 8000);
        }

        function closeRewardOverlay() {
            if (!rewardOverlayEl) return;
            rewardOverlayEl.classList.remove('is-open');
            rewardOverlayEl.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            clearTimeout(rewardAutoCloseTimer);
        }

        if (rewardCloseBtn) {
            rewardCloseBtn.addEventListener('click', closeRewardOverlay);
        }
        if (rewardOverlayEl) {
            rewardOverlayEl.addEventListener('click', (e) => {
                if (e.target === rewardOverlayEl) closeRewardOverlay();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && rewardOverlayEl?.classList.contains('is-open')) {
                closeRewardOverlay();
            }
        });

        async function completeTask(taskId, note, submitButton, stockReportItems, photoProofDataUrl, photoBeforeDataUrl) {
            submitButton.disabled = true;

            const scannedBarcode = String(scannedBarcodeByTask.get(taskId) || '');
            const rawChecklist = productChecklistByTask.get(taskId) || null;
            let productChecklistJson = null;
            if (rawChecklist && Object.keys(rawChecklist).length > 0) {
                const serialized = {};
                for (const [productId, entry] of Object.entries(rawChecklist)) {
                    if (entry && entry.filled) {
                        serialized[productId] = {
                            checked: true,
                            product_id: productId,
                            actual_qty: Number(entry.actual_qty || 0),
                            standard_qty: Number(entry.standard_qty || 0),
                            min_qty: Number(entry.min_qty || 0),
                            product_name: String(entry.product_name || ''),
                            product_unit: String(entry.product_unit || 'pcs'),
                            is_shortage: Boolean(entry.is_shortage),
                            initial_qty: entry.was_refilled ? Number(entry.initial_qty || 0) : undefined,
                            was_refilled: entry.was_refilled ? true : undefined,
                        };
                    }
                }
                productChecklistJson = Object.keys(serialized).length > 0
                    ? JSON.stringify(serialized)
                    : null;
            }

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
                        photo_before_data_url: photoBeforeDataUrl || '',
                        product_checklist: productChecklistJson,
                        idempotency_key: `task-complete:${taskId}:${taskCompleteFormInstanceByTask.get(taskId) || newFormInstanceId()}`,
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Gagal memverifikasi tugas.');
                }

                if (payload?.partial) {
                    // Partial completion — clear note/photo drafts but keep task in list
                    noteDraftByTask.delete(taskId);
                    photoProofByTask.delete(taskId);
                    photoBeforeByTask.delete(taskId);
                    clearDraftLocal(taskDraftKey(taskId));
                    const reward = payload?.reward || {
                        points_earned: 0,
                        daily_total: 0,
                        perfect_day: false,
                        category_breakdown: [],
                        tasks_done: Number(payload.completed_count || 0),
                        tasks_total: Number(payload.repeat_count || 0),
                    };
                    const earned = Math.max(0, Number(reward.points_earned || 0));
                    if (earned > 0) spawnFloatingPoints(submitButton, earned);
                    showRewardOverlay(reward, payload);
                } else {
                    scannedBarcodeByTask.delete(taskId);
                    stockReportItemsByTask.delete(taskId);
                    noteDraftByTask.delete(taskId);
                    photoProofByTask.delete(taskId);
                    photoBeforeByTask.delete(taskId);
                    productChecklistByTask.delete(taskId);
                    refillStepByTask.delete(taskId);
                    taskCompleteFormInstanceByTask.delete(taskId);
                    clearDraftLocal(taskDraftKey(taskId));
                    const reward = payload?.reward || {
                        points_earned: 0,
                        daily_total: 0,
                        perfect_day: false,
                        category_breakdown: [],
                        tasks_done: 0,
                        tasks_total: 0,
                    };
                    const earned = Math.max(0, Number(reward.points_earned || 0));
                    if (earned > 0) spawnFloatingPoints(submitButton, earned);
                    showRewardOverlay(reward, payload);
                }
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

        function resetScannerTorchState() {
            scannerTorchSupported = false;
            scannerTorchEnabled = false;
            scannerTorchBusy = false;
        }

        function updateScannerFlashButton() {
            if (!scannerFlashBtn) {
                return;
            }

            if (!scannerRunning) {
                scannerFlashBtn.classList.add('hidden');
                scannerFlashBtn.classList.remove('active');
                scannerFlashBtn.disabled = true;
                scannerFlashBtn.textContent = '🔦 Nyalakan Flash';
                return;
            }

            scannerFlashBtn.classList.remove('hidden');

            if (!scannerTorchSupported) {
                scannerFlashBtn.classList.remove('active');
                scannerFlashBtn.disabled = true;
                scannerFlashBtn.textContent = '🔦 Flash tidak didukung';
                return;
            }

            if (scannerTorchBusy) {
                scannerFlashBtn.classList.toggle('active', scannerTorchEnabled);
                scannerFlashBtn.disabled = true;
                scannerFlashBtn.textContent = '⏳ Mengubah Flash...';
                return;
            }

            scannerFlashBtn.classList.toggle('active', scannerTorchEnabled);
            scannerFlashBtn.disabled = false;
            scannerFlashBtn.textContent = scannerTorchEnabled ? '🔦 Matikan Flash' : '🔦 Nyalakan Flash';
        }

        function getScannerTorchSupportCapability() {
            if (!scannerInstance || typeof scannerInstance.getRunningTrackCapabilities !== 'function') {
                return false;
            }

            try {
                const capabilities = scannerInstance.getRunningTrackCapabilities();
                return Boolean(capabilities && capabilities.torch);
            } catch (error) {
                console.log('getRunningTrackCapabilities failed', error);
                return false;
            }
        }

        function getScannerTorchEnabledSetting() {
            if (!scannerInstance || typeof scannerInstance.getRunningTrackSettings !== 'function') {
                return false;
            }

            try {
                const settings = scannerInstance.getRunningTrackSettings();
                return Boolean(settings && settings.torch);
            } catch (error) {
                console.log('getRunningTrackSettings failed', error);
                return false;
            }
        }

        function syncScannerTorchStateFromTrack() {
            scannerTorchSupported = getScannerTorchSupportCapability();
            scannerTorchEnabled = scannerTorchSupported ? getScannerTorchEnabledSetting() : false;
            updateScannerFlashButton();
        }

        async function toggleScannerTorch() {
            if (!scannerRunning || !scannerInstance) {
                showFlash('error', 'Scanner belum aktif, tidak bisa mengatur flash.');
                return;
            }

            if (!scannerTorchSupported) {
                showFlash('error', 'Flash tidak didukung pada device/browser ini.');
                return;
            }

            if (scannerTorchBusy) {
                return;
            }

            const targetTorchState = !scannerTorchEnabled;
            scannerTorchBusy = true;
            updateScannerFlashButton();

            try {
                await scannerInstance.applyVideoConstraints({
                    torch: targetTorchState,
                    advanced: [{ torch: targetTorchState }],
                });

                scannerTorchEnabled = getScannerTorchEnabledSetting();
                if (scannerTorchEnabled !== targetTorchState) {
                    scannerTorchEnabled = targetTorchState;
                }

                scannerFeedbackEl.textContent = scannerTorchEnabled
                    ? '🔦 Flash aktif. Arahkan kamera ke QR code rak.'
                    : '🔦 Flash dimatikan. Arahkan kamera ke QR code rak.';
            } catch (error) {
                showFlash('error', `Gagal mengubah flash: ${error?.message || 'Tidak didukung browser/device.'}`);
            } finally {
                scannerTorchBusy = false;
                syncScannerTorchStateFromTrack();
            }
        }

        async function closeScannerModal() {
            await stopScannerIfRunning();
            resetScannerTorchState();
            updateScannerFlashButton();
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

            if (scannerModalTitleEl) {
                scannerModalTitleEl.textContent = '📷 Scan QR Code Rak';
            }

            if (activeScannerExpectedBarcode === '') {
                showFlash('error', 'QR code rak target belum terkonfigurasi pada task ini. Hubungi supervisor.');
                activeScannerTaskId = '';
                activeScannerTaskLabel = '';
                activeScannerExpectedBarcode = '';
                return;
            }

            scannerTaskMetaEl.textContent = `Task: ${taskLabel} | Rak: ${rackName} | Target: ${activeScannerExpectedBarcode}`;
            scannerFeedbackEl.textContent = 'Arahkan kamera ke QR code rak sampai terbaca.';
            scannerModalEl.style.display = 'flex';
            scannerModalEl.setAttribute('aria-hidden', 'false');
            resetScannerTorchState();
            updateScannerFlashButton();

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
                    ? [Html5QrcodeSupportedFormats.QR_CODE]
                    : undefined;

                await scannerInstance.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 280, height: 280 },
                        ...(formats ? { formatsToSupport: formats } : {}),
                    },
                    async (decodedText) => {
                        if (!activeScannerTaskId) return;

                        const cleanBarcode = normalizeBarcodeValue(decodedText);
                        if (cleanBarcode === '') {
                            return;
                        }

                        if (cleanBarcode !== activeScannerExpectedBarcode) {
                            scannerFeedbackEl.textContent = `❌ QR code tidak cocok. Target ${activeScannerExpectedBarcode}, terbaca ${cleanBarcode}. Scan ulang rak yang benar.`;
                            return;
                        }

                        scannedBarcodeByTask.set(activeScannerTaskId, cleanBarcode);
                        scannerFeedbackEl.textContent = `✅ QR code cocok: ${cleanBarcode}`;
                        debounceSaveDraft(taskDraftKey(activeScannerTaskId), () => collectTaskDraft(activeScannerTaskId));
                        renderAllTasks();
                        await closeScannerModal();
                    },
                    () => {
                        // ignore per-frame decode errors
                    }
                );

                scannerRunning = true;
                syncScannerTorchStateFromTrack();
                if (!scannerTorchSupported) {
                    scannerFeedbackEl.textContent = 'Scanner aktif. Flash tidak tersedia di device/browser ini, lanjut scan dengan pencahayaan normal.';
                }
            } catch (error) {
                resetScannerTorchState();
                updateScannerFlashButton();
                scannerFeedbackEl.textContent = `Gagal menyalakan kamera: ${error?.message || 'Unknown error'}`;
            }
        }

        function attachPendingContainerListeners(container) {
            if (!container) {
                return;
            }

            container.addEventListener('submit', async (event) => {
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
                if (String(currentTask?.assignment_type || '') !== 'single') {
                    try {
                        await claimTask(taskId);
                    } catch (error) {
                        showFlash('error', error?.message || 'Tugas sedang dikerjakan waiter lain.');
                        await pollTasks();
                        return;
                    }
                }
                const requiresPhotoProof = Boolean(currentTask?.requires_photo_proof);
                const photoProofDataUrl = String(photoProofByTask.get(taskId)?.dataUrl || '');
                const expectedBarcode = normalizeBarcodeValue(currentTask?.rack_barcode_value || '');
                const scannedBarcode = normalizeBarcodeValue(scannedBarcodeByTask.get(taskId) || '');
                const currentRackType = getRackTypeForTask(currentTask);

                if (isRackScanTask(currentTask) && expectedBarcode === '') {
                    showFlash('error', 'QR code rak target pada task ini belum terdaftar. Hubungi supervisor.');
                    return;
                }

                if (isRackScanTask(currentTask) && !String(scannedBarcodeByTask.get(taskId) || '').trim()) {
                    showFlash('error', 'Task cek rak wajib scan QR code rak terlebih dahulu.');
                    return;
                }

                if (isRackScanTask(currentTask) && scannedBarcode !== expectedBarcode) {
                    showFlash('error', `QR code tidak sesuai task. Target ${expectedBarcode}, yang ter-scan ${scannedBarcode || '-'}.`);
                    return;
                }

                if (requiresPhotoProof && photoProofDataUrl === '') {
                    showFlash('error', 'Task ini wajib foto bukti sebelum verifikasi selesai.');
                    return;
                }

                const requiresPhotoBefore = Boolean(currentTask?.requires_photo_before);
                const photoBeforeDataUrl = String(photoBeforeByTask.get(taskId)?.dataUrl || '');
                if (requiresPhotoBefore && photoBeforeDataUrl === '') {
                    showFlash('error', 'Task ini wajib foto SEBELUM (kondisi awal) sebelum verifikasi selesai.');
                    return;
                }

                // DISPLAY RACK REFILL INTERCEPT
                const taskRackId = String(currentTask?.rack_id || '');
                const isDisplayRack = (rackTypesMap[taskRackId] || String(currentTask?.rack_type || '')) === 'display';
                const isRackCheck = String(currentTask?.task_type || '') === 'rack_check';
                if (isRackCheck && isDisplayRack && !refillStepByTask.has(taskId)) {
                    // Check if there are shortage items that need refill
                    const checklist = productChecklistByTask.get(taskId) || {};
                    const rackId = String(currentTask?.rack_id || '');
                    const rackProducts = rackProductsMap[rackId] || [];
                    const shortageItems = [];
                    for (const product of rackProducts) {
                        const entry = checklist[product.id];
                        if (!entry || !entry.filled) continue;
                        const actualQty = Number(entry.actual_qty || 0);
                        const minQty = Number(product.min_qty || 0);
                        const standardQty = Number(product.standard_qty || 0);
                        // Needs refill if: habis OR below min_qty
                        if (actualQty === 0 || (minQty > 0 && actualQty <= minQty) || (standardQty > 0 && actualQty < standardQty)) {
                            shortageItems.push({
                                product_id: product.id,
                                product_name: product.name,
                                product_unit: product.unit || 'pcs',
                                initial_qty: actualQty,
                                standard_qty: standardQty,
                                min_qty: minQty,
                            });
                        }
                    }

                    if (shortageItems.length > 0) {
                        // Fetch warehouse availability so the refill UI can show
                        // which items are actually pickable vs. need a restock request.
                        let storageInfo = {};
                        try {
                            const resp = await fetch(storageInfoUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ product_ids: shortageItems.map(i => i.product_id) }),
                            });
                            const payload = await resp.json();
                            if (resp.ok && payload?.success) {
                                storageInfo = payload.storage_info || {};
                            }
                        } catch (err) {
                            // Non-blocking: refill UI still works without storage info, just without badges.
                            console.warn('Storage info fetch failed', err);
                        }

                        // Merge storage availability into each shortage entry.
                        for (const item of shortageItems) {
                            const info = storageInfo[item.product_id] || { total_qty: 0, racks: [], status: 'missing' };
                            item.storage_status = String(info.status || 'missing');
                            item.storage_total_qty = Number(info.total_qty || 0);
                            item.storage_racks = Array.isArray(info.racks) ? info.racks : [];
                        }

                        // Enter refill mode — show refill UI instead of submitting
                        refillStepByTask.set(taskId, shortageItems);
                        renderAllTasks();
                        const availableCount = shortageItems.filter(i => i.storage_status === 'available').length;
                        const unavailableCount = shortageItems.length - availableCount;
                        if (unavailableCount > 0 && availableCount > 0) {
                            showFlash('info', `${availableCount} bisa diambil dari gudang, ${unavailableCount} otomatis dibuat permintaan restock.`);
                        } else if (unavailableCount > 0) {
                            showFlash('info', `${unavailableCount} produk stok gudang tidak cukup. Otomatis dibuat permintaan restock saat selesai.`);
                        } else {
                            showFlash('info', `${shortageItems.length} produk siap diambil dari gudang.`);
                        }
                        return;
                    }
                }

                // If in refill mode, merge refill data into checklist before submit
                if (refillStepByTask.has(taskId)) {
                    const refillContainer = form.closest('.card')?.querySelector(`.refill-step[data-task-id="${taskId}"]`);
                    if (refillContainer) {
                        const checklist = productChecklistByTask.get(taskId) || {};
                        const refillInputs = refillContainer.querySelectorAll('.js-refill-qty');
                        refillInputs.forEach(input => {
                            const productId = String(input.getAttribute('data-product-id') || '');
                            const initialQty = Number(input.getAttribute('data-initial-qty') || 0);
                            const refillQty = Math.max(0, parseInt(input.value) || 0);
                            if (productId && checklist[productId]) {
                                checklist[productId].initial_qty = initialQty;
                                checklist[productId].actual_qty = refillQty;
                                checklist[productId].was_refilled = true;
                            }
                        });
                        productChecklistByTask.set(taskId, checklist);
                    }
                    refillStepByTask.delete(taskId);
                }

                await completeTask(taskId, note, submitButton, stockReportItems, photoProofDataUrl, photoBeforeDataUrl);
                await pollTasks();
            });

            container.addEventListener('input', (event) => {
                const rackSearchInput = event.target.closest('.js-rack-search');
                if (rackSearchInput) {
                    applyRackSearchFilterInPlace();
                    return;
                }

                const reportField = event.target.closest('.js-stock-report');
                if (reportField) {
                    const taskId = String(reportField.getAttribute('data-task-id') || '');
                    if (!taskId) {
                        return;
                    }

                    stockReportItemsByTask.set(taskId, String(reportField.value || ''));
                    return;
                }

                const productQtyInput = event.target.closest('.js-product-qty');
                if (productQtyInput) {
                    const taskId = String(productQtyInput.getAttribute('data-task-id') || '');
                    const productId = String(productQtyInput.getAttribute('data-product-id') || '');
                    if (taskId && productId) {
                        const checklist = productChecklistByTask.get(taskId) || {};
                        const rawValue = String(productQtyInput.value || '').trim();
                        const isFilled = rawValue !== '';
                        const actualQty = Math.max(0, parseInt(rawValue) || 0);
                        const rackId = String((waiterTasks.find(t => String(t?.id || '') === taskId) || {}).rack_id || '');
                        const rackProducts = rackProductsMap[rackId] || [];
                        const product = rackProducts.find(p => p.id === productId);
                        const standardQty = product ? Number(product.standard_qty || 0) : Number(checklist[productId]?.standard_qty || 0);
                        const minQty = product ? Number(product.min_qty || 0) : Number(checklist[productId]?.min_qty || 0);
                        checklist[productId] = {
                            actual_qty: actualQty,
                            standard_qty: standardQty,
                            min_qty: minQty,
                            product_name: product ? (product.name || '') : (checklist[productId]?.product_name || ''),
                            product_unit: product ? (product.unit || 'pcs') : (checklist[productId]?.product_unit || 'pcs'),
                            filled: isFilled,
                            checked: isFilled,
                            is_shortage: isFilled && actualQty < standardQty,
                        };
                        productChecklistByTask.set(taskId, checklist);
                        debounceSaveDraft(taskDraftKey(taskId), () => collectTaskDraft(taskId));
                    }
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
                debounceSaveDraft(taskDraftKey(taskId), () => collectTaskDraft(taskId));
            });

            container.addEventListener('change', async (event) => {
                const productQtyInput = event.target.closest('.js-product-qty');
                if (productQtyInput) {
                    const taskId = String(productQtyInput.getAttribute('data-task-id') || '');
                    const productId = String(productQtyInput.getAttribute('data-product-id') || '');
                    if (taskId && productId) {
                        const checklist = productChecklistByTask.get(taskId) || {};
                        const rawValue = String(productQtyInput.value || '').trim();
                        const isFilled = rawValue !== '';
                        const actualQty = Math.max(0, parseInt(rawValue) || 0);
                        const rackId = String((waiterTasks.find(t => String(t?.id || '') === taskId) || {}).rack_id || '');
                        const rackProducts = rackProductsMap[rackId] || [];
                        const product = rackProducts.find(p => p.id === productId);
                        const standardQty = product ? Number(product.standard_qty || 0) : Number(checklist[productId]?.standard_qty || 0);
                        const minQty = product ? Number(product.min_qty || 0) : Number(checklist[productId]?.min_qty || 0);
                        checklist[productId] = {
                            actual_qty: actualQty,
                            standard_qty: standardQty,
                            min_qty: minQty,
                            product_name: product ? (product.name || '') : (checklist[productId]?.product_name || ''),
                            product_unit: product ? (product.unit || 'pcs') : (checklist[productId]?.product_unit || 'pcs'),
                            filled: isFilled,
                            checked: isFilled,
                            is_shortage: isFilled && actualQty < standardQty,
                        };
                        productChecklistByTask.set(taskId, checklist);
                        renderAllTasks();
                    }
                    return;
                }

                const photoBeforeInput = event.target.closest('.js-photo-before');
                if (photoBeforeInput) {
                    const taskId = String(photoBeforeInput.getAttribute('data-task-id') || '');
                    if (!taskId) { return; }

                    const selectedFile = photoBeforeInput.files && photoBeforeInput.files.length > 0
                        ? photoBeforeInput.files[0]
                        : null;

                    if (!selectedFile) {
                        photoBeforeByTask.delete(taskId);
                        renderAllTasks();
                        return;
                    }

                    try {
                        const compressed = await compressPhotoProofFile(selectedFile);
                        photoBeforeByTask.set(taskId, compressed);
                        showFlash('success', `Foto sebelum siap (${formatBytes(compressed.sizeBytes)}).`);
                    } catch (error) {
                        photoBeforeByTask.delete(taskId);
                        showFlash('error', error?.message || 'Gagal memproses foto sebelum.');
                    }

                    renderAllTasks();
                    return;
                }

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

            container.addEventListener('focusout', () => {
                setTimeout(() => {
                    flushDeferredPendingRender();
                }, 0);
            });

            container.addEventListener('click', async (event) => {
                const rackSearchClearBtn = event.target.closest('.js-rack-search-clear');
                if (rackSearchClearBtn) {
                    rackSearchKeyword = '';
                    const rackSearchInput = container.querySelector('.js-rack-search');
                    if (rackSearchInput instanceof HTMLInputElement) {
                        rackSearchInput.value = '';
                        rackSearchInput.focus();
                    }
                    applyRackSearchFilterInPlace();
                    return;
                }

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

                const photoBeforeClearBtn = event.target.closest('.js-photo-before-clear');
                if (photoBeforeClearBtn) {
                    const taskId = String(photoBeforeClearBtn.getAttribute('data-task-id') || '');
                    if (!taskId) {
                        return;
                    }

                    photoBeforeByTask.delete(taskId);
                    renderAllTasks();
                    showFlash('success', 'Foto sebelum dihapus dari draft task ini.');
                    return;
                }

                const btn = event.target.closest('.js-open-scanner');
                const claimBtn = event.target.closest('.js-claim-task');
                if (claimBtn) {
                    const taskId = String(claimBtn.getAttribute('data-task-id') || '');
                    if (!taskId) return;
                    try {
                        const payload = await claimTask(taskId);
                        showFlash('success', payload?.message || 'Tugas berhasil di-klaim.');
                        await pollTasks();
                    } catch (error) {
                        showFlash('error', error?.message || 'Tugas sedang dikerjakan waiter lain.');
                        await pollTasks();
                    }
                    return;
                }

                const releaseBtn = event.target.closest('.js-release-task');
                if (releaseBtn) {
                    const taskId = String(releaseBtn.getAttribute('data-task-id') || '');
                    if (!taskId) return;
                    try {
                        const payload = await releaseTaskClaim(taskId);
                        showFlash('success', payload?.message || 'Klaim tugas dilepas.');
                        await pollTasks();
                    } catch (error) {
                        showFlash('error', error?.message || 'Gagal melepas klaim tugas.');
                    }
                    return;
                }
                const assignProductBtn = event.target.closest('.js-open-assign-product');
                if (assignProductBtn) {
                    const taskId = String(assignProductBtn.getAttribute('data-task-id') || '');
                    const rackId = String(assignProductBtn.getAttribute('data-rack-id') || '');
                    const rackName = String(assignProductBtn.getAttribute('data-rack-name') || '');
                    openAssignProductModal(taskId, rackId, rackName);
                    return;
                }

                const assignAddBtn = event.target.closest('.js-assign-product-add');
                if (assignAddBtn) {
                    // Handled by modal click listener (modal lives outside the pending container).
                    return;
                }
                if (!btn) {
                    return;
                }

                const taskId = String(btn.getAttribute('data-task-id') || '');
                const taskLabel = String(btn.getAttribute('data-task-label') || 'Task');
                const rackName = String(btn.getAttribute('data-rack-name') || '-');
                const rackBarcode = String(btn.getAttribute('data-rack-barcode') || '');
                if (!taskId) {
                    return;
                }

                await startScannerForTask(taskId, taskLabel, rackName, rackBarcode);
            });
        }

        // === Assign Product to Rack modal ===
        const assignProductModalEl = document.getElementById('assign-product-modal');
        const assignProductTitleEl = document.getElementById('assign-product-modal-title');
        const assignProductMetaEl = document.getElementById('assign-product-modal-meta');
        const assignProductSearchEl = document.getElementById('assign-product-search');
        const assignProductFeedbackEl = document.getElementById('assign-product-feedback');
        const assignProductResultsEl = document.getElementById('assign-product-results');
        const assignProductCloseBtn = document.getElementById('assign-product-modal-close-btn');
        const assignProductSearchUrl = "{{ route('waiter.rack_products.search', [], false) }}";
        const assignProductAssignUrl = "{{ route('waiter.rack_products.assign', [], false) }}";
        const storageInfoUrl = "{{ route('waiter.rack_products.storage_info', [], false) }}";
        let assignActiveTaskId = '';
        let assignActiveRackId = '';
        let assignSearchTimer = null;

        function closeAssignProductModal() {
            if (!assignProductModalEl) return;
            assignProductModalEl.style.display = 'none';
            assignProductModalEl.setAttribute('aria-hidden', 'true');
            assignActiveTaskId = '';
            assignActiveRackId = '';
            if (assignSearchTimer) { clearTimeout(assignSearchTimer); assignSearchTimer = null; }
            if (assignProductSearchEl) assignProductSearchEl.value = '';
            if (assignProductResultsEl) assignProductResultsEl.innerHTML = '';
            if (assignProductFeedbackEl) {
                assignProductFeedbackEl.textContent = 'Ketik minimal 2 huruf untuk mulai mencari.';
                assignProductFeedbackEl.style.color = '#6b7280';
            }
        }

        function openAssignProductModal(taskId, rackId, rackName) {
            if (!assignProductModalEl) return;
            if (!rackId) {
                showFlash('error', 'Rak tidak ditemukan untuk task ini.');
                return;
            }
            assignActiveTaskId = String(taskId || '');
            assignActiveRackId = String(rackId || '');
            if (assignProductTitleEl) assignProductTitleEl.textContent = '➕ Tambahkan Produk ke Rak';
            if (assignProductMetaEl) assignProductMetaEl.textContent = rackName ? `Rak: ${rackName}` : `Rak ID: ${rackId}`;
            assignProductModalEl.style.display = 'flex';
            assignProductModalEl.setAttribute('aria-hidden', 'false');
            // Trigger empty search to show recent products
            runAssignSearch('');
            setTimeout(() => assignProductSearchEl?.focus(), 50);
        }

        async function runAssignSearch(query) {
            if (!assignActiveRackId) return;
            const q = String(query || '').trim();
            if (q.length === 1) {
                if (assignProductFeedbackEl) {
                    assignProductFeedbackEl.textContent = 'Ketik minimal 2 huruf untuk mulai mencari.';
                    assignProductFeedbackEl.style.color = '#6b7280';
                }
                if (assignProductResultsEl) assignProductResultsEl.innerHTML = '';
                return;
            }
            if (assignProductFeedbackEl) {
                assignProductFeedbackEl.textContent = 'Mencari...';
                assignProductFeedbackEl.style.color = '#6b7280';
            }
            try {
                const url = new URL(assignProductSearchUrl, window.location.origin);
                url.searchParams.set('rack_id', assignActiveRackId);
                if (q !== '') url.searchParams.set('q', q);
                url.searchParams.set('limit', '30');
                const res = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data?.message || 'Gagal memuat daftar produk.');
                }
                renderAssignSearchResults(data.products || []);
            } catch (err) {
                if (assignProductFeedbackEl) {
                    assignProductFeedbackEl.textContent = err.message || 'Gagal memuat produk.';
                    assignProductFeedbackEl.style.color = '#b91c1c';
                }
                if (assignProductResultsEl) assignProductResultsEl.innerHTML = '';
            }
        }

        function renderAssignSearchResults(products) {
            if (!assignProductResultsEl) return;
            if (!products.length) {
                assignProductResultsEl.innerHTML = '<div class="assign-product-row" style="color:#6b7280;">Tidak ada produk yang cocok.</div>';
                if (assignProductFeedbackEl) {
                    assignProductFeedbackEl.textContent = 'Tidak ada hasil.';
                    assignProductFeedbackEl.style.color = '#6b7280';
                }
                return;
            }
            const html = products.map((p) => {
                const meta = [
                    p.unit ? escapeHtml(p.unit) : '',
                    p.standard_qty ? `Std: ${p.standard_qty}` : '',
                    p.barcode ? `Barcode: ${escapeHtml(p.barcode)}` : '',
                    p.category_name ? escapeHtml(p.category_name) : '',
                ].filter(Boolean).join(' • ');
                return `<div class="assign-product-row" data-product-id="${escapeAttr(p.id)}">
                    <div class="assign-product-info">
                        <div class="assign-product-name">${escapeHtml(p.name || '-')}</div>
                        <div class="assign-product-meta">${meta || '-'}</div>
                    </div>
                    <button type="button" class="assign-product-add js-assign-product-add"
                        data-product-id="${escapeAttr(p.id)}"
                        data-product-name="${escapeAttr(p.name || '')}"
                        data-product-unit="${escapeAttr(p.unit || 'pcs')}"
                        data-standard-qty="${Number(p.standard_qty || 0)}">
                        Tambah
                    </button>
                </div>`;
            }).join('');
            assignProductResultsEl.innerHTML = html;
            if (assignProductFeedbackEl) {
                assignProductFeedbackEl.textContent = `${products.length} produk tersedia.`;
                assignProductFeedbackEl.style.color = '#059669';
            }
        }

        async function submitAssignProduct(productId, btnEl) {
            if (!productId || !assignActiveRackId) return;
            const originalLabel = btnEl ? btnEl.textContent : '';
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.textContent = '...';
            }
            try {
                const res = await fetch(assignProductAssignUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        rack_id: assignActiveRackId,
                        product_id: productId,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data?.message || 'Gagal menambahkan produk.');
                }
                // Append to in-memory map and re-render
                const newProduct = data.product;
                if (newProduct) {
                    const list = rackProductsMap[assignActiveRackId] || [];
                    if (!list.some((p) => String(p.id) === String(newProduct.id))) {
                        list.push(newProduct);
                        list.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
                        rackProductsMap[assignActiveRackId] = list;
                    }
                }
                showFlash('success', data.message || 'Produk berhasil ditambahkan.');
                closeAssignProductModal();
                renderAllTasks();
            } catch (err) {
                showFlash('error', err.message || 'Gagal menambahkan produk.');
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.textContent = originalLabel || 'Tambah';
                }
            }
        }

        if (assignProductCloseBtn) {
            assignProductCloseBtn.addEventListener('click', closeAssignProductModal);
        }
        if (assignProductModalEl) {
            assignProductModalEl.addEventListener('click', (e) => {
                const addBtn = e.target.closest('.js-assign-product-add');
                if (addBtn) {
                    const productId = String(addBtn.getAttribute('data-product-id') || '');
                    submitAssignProduct(productId, addBtn);
                    return;
                }
                if (e.target === assignProductModalEl) closeAssignProductModal();
            });
        }
        if (assignProductSearchEl) {
            assignProductSearchEl.addEventListener('input', () => {
                if (assignSearchTimer) clearTimeout(assignSearchTimer);
                const v = assignProductSearchEl.value;
                assignSearchTimer = setTimeout(() => runAssignSearch(v), 250);
            });
        }

        attachPendingContainerListeners(rackPendingContainer);
        attachPendingContainerListeners(stockTakePendingContainer);
        attachPendingContainerListeners(generalPendingContainer);

        [rackHistoryBody, generalHistoryBody].forEach((historyTarget) => {
            historyTarget?.addEventListener('click', (event) => {
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
        });

        scannerCloseBtn.addEventListener('click', async () => {
            await closeScannerModal();
        });

        scannerFlashBtn?.addEventListener('click', async () => {
            await toggleScannerTorch();
        });

        btnAttendanceAction?.addEventListener('click', async () => {
            await startAttendanceScan();
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

        // === BONUS MONTH PICKER AJAX ===
        const bonusApiUrl = context.bonusApiUrl || '';
        const bonusMonthPickerEl = document.getElementById('bonusMonthPicker');
        const bonusContentArea = document.getElementById('bonus-content-area');

        function formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }

        function formatDateShort(dateStr) {
            try {
                const d = new Date(dateStr + 'T00:00:00');
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
            } catch (e) { return dateStr; }
        }

        async function loadBonusData(month) {
            if (!bonusApiUrl || !bonusContentArea) return;

            bonusContentArea.style.opacity = '0.5';
            bonusContentArea.style.pointerEvents = 'none';

            try {
                const resp = await fetch(bonusApiUrl + '?month=' + encodeURIComponent(month), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();

                const totalEarned = Number(data.total_earned || 0);
                const totalPenalties = Number(data.total_penalties || 0);
                const netPoints = Number(data.net_points || 0);
                const percentage = Number(data.percentage || 0);
                const perfectDays = Number(data.perfect_days || 0);
                const monthlyPoints = data.monthly_points || {};
                const penalties = data.penalties || [];
                const salesTarget = data.sales_target || {};
                const leaderboard = data.leaderboard || {};

                const daysScored = Object.keys(monthlyPoints).length;

                // Determine tier color
                let ringColor = '#e53e3e', tierLabel = 'Needs Improvement', tierColorClass = 'b-color-red', tierBgClass = 'b-bg-red';
                if (percentage >= 80) { ringColor = '#38a169'; tierLabel = 'Excellent'; tierColorClass = 'b-color-green'; tierBgClass = 'b-bg-green'; }
                else if (percentage >= 70) { ringColor = '#d69e2e'; tierLabel = 'Good'; tierColorClass = 'b-color-yellow'; tierBgClass = 'b-bg-yellow'; }
                else if (percentage >= 60) { ringColor = '#dd6b20'; tierLabel = 'Average'; tierColorClass = 'b-color-orange'; tierBgClass = 'b-bg-orange'; }

                const circumference = 2 * Math.PI * 70;
                const dashoffset = circumference - (circumference * Math.min(percentage, 100) / 100);

                // Sales
                const salesAchieved = Number(salesTarget.achieved || 0);
                const salesGoal = Number(salesTarget.target || 1);
                const salesPercent = salesGoal > 0 ? Math.min(100, Math.round((salesAchieved / salesGoal) * 100)) : 0;

                // Category averages
                const catKeys = ['discipline', 'operational', 'service', 'sales', 'attitude'];
                const catLabels = { discipline: 'Disiplin', operational: 'Operasional', service: 'Service', sales: 'Sales', attitude: 'Attitude' };
                const catColors = { discipline: '#667eea', operational: '#764ba2', service: '#38a169', sales: '#d69e2e', attitude: '#e53e3e' };
                const catMaxes = { discipline: 5, operational: 10, service: 5, sales: 5, attitude: 5 };
                const catTotals = {};
                catKeys.forEach(k => catTotals[k] = 0);
                const dayCountAjax = Math.max(Object.keys(monthlyPoints).length, 1);
                Object.values(monthlyPoints).forEach(rec => {
                    catKeys.forEach(k => {
                        catTotals[k] += Number((rec.categories && rec.categories[k] && rec.categories[k].points) || rec[k] || 0);
                    });
                });

                // Build category bars HTML
                let catBarsHtml = '';
                catKeys.forEach(k => {
                    const avg = (catTotals[k] / dayCountAjax).toFixed(1);
                    const maxC = catMaxes[k];
                    const pct = maxC > 0 ? Math.min(100, Math.round((avg / maxC) * 100)) : 0;
                    catBarsHtml += `<div class="category-bar-row">
                        <span class="category-bar-label">${catLabels[k]}</span>
                        <div class="category-bar-track"><div class="category-bar-fill" style="width:${pct}%;background:${catColors[k]};"></div></div>
                        <span class="category-bar-value">${avg}/${maxC}</span>
                    </div>`;
                });

                // Build daily history HTML (last 10 days)
                const sortedDates = Object.keys(monthlyPoints).sort().reverse().slice(0, 10);
                let dailyHtml = '';
                if (sortedDates.length === 0) {
                    dailyHtml = '<div class="bonus-empty-state">Belum ada data harian</div>';
                } else {
                    sortedDates.forEach(date => {
                        const rec = monthlyPoints[date];
                        let dotsHtml = '';
                        catKeys.forEach(k => {
                            const pts = Number((rec.categories && rec.categories[k] && rec.categories[k].points) || rec[k] || 0);
                            const maxC = catMaxes[k];
                            const opacity = maxC > 0 ? Math.max(0.2, pts / maxC) : 0.2;
                            dotsHtml += `<div class="daily-cat-dot" style="background:${catColors[k]};opacity:${opacity};"></div>`;
                        });
                        dailyHtml += `<div class="daily-item">
                            <span class="daily-date">${formatDateShort(date)}</span>
                            <div class="daily-categories">${dotsHtml}</div>
                            <span class="daily-total">${rec.daily_total || 0}</span>
                        </div>`;
                    });
                }

                // Build penalties HTML
                let penaltiesHtml = '';
                if (penalties.length > 0) {
                    penaltiesHtml = `<div class="bonus-card"><div class="bonus-card-title">⚠️ Penalti</div>`;
                    penalties.forEach(p => {
                        penaltiesHtml += `<div class="penalty-item">
                            <div class="penalty-header">
                                <span class="penalty-type">${p.type || p.penalty_type || 'Pelanggaran'}</span>
                                <span class="penalty-points">-${Math.abs(Number(p.points_deducted || 0))} poin</span>
                            </div>
                            <div class="penalty-reason">${p.reason || p.notes || '-'}</div>
                            <div class="penalty-date">${p.date || p.created_at || ''}</div>
                        </div>`;
                    });
                    penaltiesHtml += '</div>';
                }

                // Build sales HTML
                let salesHtml = '';
                if (salesTarget && (salesTarget.target || salesTarget.achieved)) {
                    salesHtml = `<div class="bonus-card">
                        <div class="bonus-card-title">🎯 Target Penjualan</div>
                        <div class="sales-progress-bar"><div class="sales-progress-fill" style="width:${salesPercent}%;"></div></div>
                        <div class="sales-stats">
                            <span>${formatRupiah(salesAchieved)}</span>
                            <span>${salesPercent}%</span>
                            <span>${formatRupiah(salesGoal)}</span>
                        </div>
                    </div>`;
                }

                // Build leaderboard HTML
                let leaderboardHtml = '';
                if (leaderboard && leaderboard.rankings && leaderboard.rankings.length > 0) {
                    leaderboardHtml = `<div class="bonus-card"><div class="bonus-card-title">🏅 Leaderboard</div>`;
                    const top5 = leaderboard.rankings.slice(0, 5);
                    top5.forEach((entry, idx) => {
                        const rankNum = idx + 1;
                        const rankClass = rankNum === 1 ? 'gold' : rankNum === 2 ? 'silver' : rankNum === 3 ? 'bronze' : '';
                        const isMe = (entry.waiter_id || '') === waiterId;
                        leaderboardHtml += `<div class="leaderboard-item ${isMe ? 'is-me' : ''}">
                            <div class="leaderboard-rank ${rankClass}">${rankNum}</div>
                            <div class="leaderboard-name">${entry.waiter_name || 'Waiter'}${isMe ? ' (Anda)' : ''}</div>
                            <div class="leaderboard-points">${entry.total_points || entry.net_points || 0} pts</div>
                        </div>`;
                    });
                    leaderboardHtml += '</div>';
                }

                bonusContentArea.innerHTML = `
                    <div class="progress-ring-wrapper">
                        <div class="progress-ring-container">
                            <svg class="progress-ring-svg" viewBox="0 0 180 180">
                                <circle class="progress-ring-bg" cx="90" cy="90" r="70"></circle>
                                <circle class="progress-ring-fill" cx="90" cy="90" r="70"
                                    stroke="${ringColor}"
                                    stroke-dasharray="${circumference}"
                                    stroke-dashoffset="${dashoffset}"
                                    style="animation: bonus-ring-fill 1.5s ease-in-out;"></circle>
                            </svg>
                            <div class="progress-ring-text">
                                <div class="progress-ring-percent ${tierColorClass}">${percentage}%</div>
                                <div class="progress-ring-label">dari maksimum</div>
                            </div>
                        </div>
                        <span class="progress-tier ${tierBgClass} ${tierColorClass}">${tierLabel}</span>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value ${tierColorClass}">${netPoints}</div>
                            <div class="stat-label">Total Poin</div>
                            <div class="stat-sub">saat ini</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${daysScored}</div>
                            <div class="stat-label">Hari Dinilai</div>
                            <div class="stat-sub">bulan ini</div>
                        </div>
                        <div class="stat-card perfect">
                            <div class="stat-value">${perfectDays} ✨</div>
                            <div class="stat-label">Perfect Days</div>
                            <div class="stat-sub">skor sempurna</div>
                        </div>
                        <div class="stat-card penalty">
                            <div class="stat-value">-${totalPenalties}</div>
                            <div class="stat-label">Penalti</div>
                            <div class="stat-sub">poin dikurangi</div>
                        </div>
                    </div>

                    <div class="bonus-card">
                        <div class="bonus-card-title">📊 Rata-rata per Kategori</div>
                        ${catBarsHtml}
                    </div>

                    <div class="bonus-card">
                        <div class="bonus-card-title">📅 Riwayat Harian</div>
                        <div class="daily-list">${dailyHtml}</div>
                    </div>

                    ${penaltiesHtml}
                    ${salesHtml}
                    ${leaderboardHtml}
                `;
            } catch (err) {
                console.error('Failed to load bonus data:', err);
                showFlash('error', 'Gagal memuat data bonus. Coba lagi.');
            } finally {
                bonusContentArea.style.opacity = '1';
                bonusContentArea.style.pointerEvents = '';
            }
        }

        if (bonusMonthPickerEl) {
            bonusMonthPickerEl.addEventListener('change', function() {
                const month = this.value;
                if (month) loadBonusData(month);
            });
        }

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
            clearDraftLocal(activityDraftKey(reportDate));
        });

        // Autosave activity text draft
        if (activityTextEl) {
            activityTextEl.addEventListener('input', () => {
                debounceSaveDraft(activityDraftKey(reportDate), () => ({ text: activityTextEl.value }));
            });
            // Restore activity draft on page load
            const actDraft = loadDraftLocal(activityDraftKey(reportDate));
            if (actDraft && actDraft.text && activityTextEl.value === '') {
                activityTextEl.value = actDraft.text;
            }
        }

        window.addEventListener('beforeunload', () => {
            if (scannerInstance && scannerRunning) {
                scannerInstance.stop().catch(() => {});
            }
        });

        setActiveTab('rack');
        renderAllTasks();
        renderActivityReports();
        initAttendanceFromContext();
        loadAttendanceStatus();
        syncDueTasks();

        // Bandwidth: polling adaptif. Kalau RTDB listener aktif, polling cuma jadi
        // safety net — interval longgar (5min). Kalau RTDB tidak tersedia, fallback
        // ke 30s seperti dulu.
        const FAST_POLL = 30000;
        const SLOW_POLL = 300000; // 5 minutes
        const SYNC_DUE_INTERVAL = 300000; // 5 minutes (was 2min)

        function getPollInterval() {
            return (window.RTDB_READY && !window.RTDB_DISABLED) ? SLOW_POLL : FAST_POLL;
        }

        let pollIntervalId = setInterval(pollTasks, getPollInterval());
        let syncIntervalId = setInterval(syncDueTasks, SYNC_DUE_INTERVAL);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(pollIntervalId);
                clearInterval(syncIntervalId);
                pollIntervalId = null;
                syncIntervalId = null;
            } else {
                // Immediately poll on return, then resume intervals
                pollTasks();
                syncDueTasks();
                pollIntervalId = setInterval(pollTasks, getPollInterval());
                syncIntervalId = setInterval(syncDueTasks, SYNC_DUE_INTERVAL);
            }
        });
    </script>

    <!-- ===== REWARD OVERLAY ===== -->
    <div id="reward-overlay" class="reward-overlay" role="dialog" aria-labelledby="reward-title" aria-hidden="true">
        <div class="reward-confetti" id="reward-confetti" aria-hidden="true"></div>
        <div class="reward-card">
            <div class="reward-trophy" id="reward-trophy">🏆</div>
            <h2 class="reward-title" id="reward-title">Tugas Selesai!</h2>
            <p class="reward-subtitle" id="reward-subtitle">Kerja bagus, lanjutkan!</p>

            <div class="reward-points-block">
                <div class="reward-points-label">Poin Diraih</div>
                <div>
                    <span class="reward-points-value" id="reward-points-value">0</span><span class="reward-points-suffix">pts</span>
                </div>
                <div class="reward-perfect" id="reward-perfect">✨ Perfect Day Bonus!</div>
            </div>

            <div class="reward-breakdown" id="reward-breakdown"></div>

            <div class="reward-progress-block">
                <div class="reward-progress-label">
                    <span>Progress Hari Ini</span>
                    <span id="reward-progress-text">0/0</span>
                </div>
                <div class="reward-progress-bar">
                    <div class="reward-progress-fill" id="reward-progress-fill"></div>
                </div>
            </div>

            <p class="reward-message" id="reward-message">"Setiap tugas yang selesai membawa kamu lebih dekat ke bonus bulan ini."</p>

            <div class="reward-actions">
                <button type="button" class="reward-btn reward-btn-secondary" id="reward-btn-close">Tutup</button>
                <a href="{{ route('waiter.bonus', [], false) }}" class="reward-btn reward-btn-primary" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">Lihat Bonus</a>
            </div>
        </div>
    </div>
</body>

</html>
