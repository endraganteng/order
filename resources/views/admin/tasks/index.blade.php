@extends('admin.layout')

@section('title', (($taskScope ?? 'general') === 'rack_check' ? 'Cek Rak Waiter - Admin' : 'Tugas Umum Waiter - Admin'))

@push('styles')
<style>
    /* ── KPI Grid ── */
    .task-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }
    .task-kpi-card {
        background: #ffffff;
        border: 1px solid var(--color-border);
        border-left: 4px solid #cbd5e1;
        border-radius: 10px;
        padding: 12px 14px;
    }
    .task-kpi-value {
        font-size: 26px;
        font-weight: 800;
        line-height: 1.1;
    }
    .task-kpi-label {
        margin-top: 4px;
        font-size: 12px;
        color: var(--color-text-muted);
    }

    /* ── Overdue Alert Banner ── */
    .overdue-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: var(--color-danger-bg);
        border: 1px solid var(--color-danger-border);
        border-radius: var(--radius-md);
        margin-bottom: 16px;
        font-size: 14px;
        color: #991b1b;
        font-weight: 600;
    }
    .overdue-banner-icon {
        font-size: 20px;
        flex-shrink: 0;
    }

    /* ── Section Cards ── */
    .task-section {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: #fff;
        margin-bottom: 16px;
        box-shadow: var(--shadow-sm);
    }
    .task-section[open] {
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }
    .task-section-summary {
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        cursor: pointer;
        font-weight: 700;
        color: var(--color-text);
        padding: 14px 16px;
        font-size: 15px;
    }
    .task-section-summary::-webkit-details-marker { display: none; }
    .task-section-summary::after {
        content: '▾';
        color: var(--color-text-muted);
        font-size: 14px;
        transition: transform 0.2s ease;
    }
    .task-section[open] > .task-section-summary::after {
        transform: rotate(180deg);
    }
    .task-section-body {
        padding: 0 16px 16px 16px;
        border-top: 1px solid #f1f5f9;
    }

    /* Kategori Modal & Breakdown */
    .task-category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        padding-top: 8px;
    }
    .task-category-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 12px;
        display: flex;
        flex-direction: column;
        background: #fff;
    }
    .task-category-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    .task-category-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .task-category-name {
        font-weight: 600;
        font-size: 13px;
        color: var(--color-text);
        display: flex;
        align-items: center;
    }
    .task-category-count {
        font-size: 12px;
        font-weight: 600;
        color: var(--color-text-muted);
    }
    .task-category-progress {
        height: 4px;
        background: var(--color-bg);
        border-radius: 2px;
        overflow: hidden;
    }
    .task-category-progress-bar {
        height: 100%;
        border-radius: 2px;
    }
    
    .category-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 1500;
    }
    .category-modal.show {
        display: flex;
    }
    .category-modal-box {
        width: min(100%, 500px);
        max-height: calc(100vh - 32px);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.35);
        display: flex;
        flex-direction: column;
    }
    .category-modal-header {
        padding: 16px;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .category-modal-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--color-text);
    }
    .category-modal-close {
        background: none;
        border: none;
        font-size: 20px;
        color: var(--color-text-muted);
        cursor: pointer;
    }
    .category-modal-body {
        padding: 16px;
        overflow-y: auto;
    }
    .category-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 20px;
    }
    .category-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-bg);
    }
    .category-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding-top: 16px;
        border-top: 1px solid var(--color-border);
    }
    .category-form-group {
        display: flex;
        gap: 10px;
    }
    .category-form-input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 14px;
    }
    .category-form-color {
        width: 40px;
        height: 38px;
        padding: 2px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        cursor: pointer;
    }

    /* "?"? Tools Row "?"? */
    .task-tools-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }
    .task-filter-wrap {
        min-width: 260px;
        flex: 1;
        max-width: 360px;
    }
    .task-inline-filter {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 9px 12px;
        font-size: 13px;
        color: var(--color-text);
        background: #ffffff;
        transition: border-color 0.2s;
    }
    .task-inline-filter:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
    }

    /* ── Data Cards Grid ── */
    .task-list-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 12px;
    }
    .task-data-card {
        border: 1px solid var(--color-border);
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
    }
    .task-data-card[open] {
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    }
    .task-data-card-summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        padding: 12px;
        background: var(--color-bg);
    }
    .task-data-card-summary::-webkit-details-marker { display: none; }
    .task-data-card-title {
        font-weight: 700;
        color: var(--color-text);
        font-size: 14px;
        margin-bottom: 4px;
    }
    .task-data-card-subtitle {
        font-size: 12px;
        color: var(--color-text-muted);
        line-height: 1.4;
    }
    .task-data-card-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .task-data-card-body {
        padding: 12px;
        border-top: 1px solid var(--color-border);
    }
    .task-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 8px;
        font-size: 12px;
        color: var(--color-text-secondary);
    }

    /* ── Status Columns ── */
    .task-status-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 10px;
    }
    .task-status-col {
        border-radius: 9px;
        border: 1px solid var(--color-border);
        padding: 10px;
        font-size: 12px;
        color: var(--color-text-secondary);
    }
    .task-status-col-success {
        border-color: var(--color-success-border);
        background: var(--color-success-bg);
    }
    .task-status-col-danger {
        border-color: var(--color-danger-border);
        background: var(--color-danger-bg);
    }
    .task-status-title {
        font-weight: 700;
        margin-bottom: 8px;
    }
    .task-status-empty {
        color: var(--color-text-muted);
    }
    .task-status-list {
        margin: 0;
        padding-left: 18px;
        line-height: 1.45;
    }
    .task-status-list li {
        margin-bottom: 7px;
    }
    .task-empty-filtered {
        margin-top: 12px;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 13px;
        color: var(--color-text-muted);
        background: var(--color-bg);
        display: none;
    }

    /* ── History Filter Bar ── */
    .history-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 14px;
        padding: 12px;
        background: var(--color-bg);
        border: 1px solid var(--color-border);
        border-radius: 10px;
    }
    .history-filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 150px;
        flex: 1;
    }
    .history-filter-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--color-text-secondary);
        letter-spacing: 0.02em;
    }
    .history-filter-select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-md);
        padding: 7px 10px;
        font-size: 13px;
        color: var(--color-text);
        background: #ffffff;
    }
    .history-filter-select:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.15);
    }
    .history-filter-reset-btn {
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-md);
        padding: 7px 14px;
        font-size: 13px;
        color: var(--color-text-muted);
        background: #ffffff;
        cursor: pointer;
        font-weight: 600;
        white-space: nowrap;
    }
    .history-filter-reset-btn:hover {
        background: #f1f5f9;
        color: var(--color-text);
    }
    .history-filter-summary {
        font-size: 12px;
        color: var(--color-text-secondary);
        padding: 8px 12px;
        background: var(--color-primary-bg);
        border: 1px solid #bfdbfe;
        border-radius: var(--radius-md);
        margin-bottom: 12px;
        display: none;
    }

    /* ── Waiter Cards ── */
    .history-waiter-card {
        border: 1px solid var(--color-border);
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
        margin-bottom: 10px;
    }
    .history-waiter-card[open] {
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    }
    .history-waiter-header {
        list-style: none;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        background: var(--color-bg);
        border-bottom: 1px solid transparent;
    }
    .history-waiter-card[open] > .history-waiter-header {
        border-bottom-color: var(--color-border);
    }
    .history-waiter-header::-webkit-details-marker { display: none; }
    .history-waiter-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .history-waiter-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 15px;
        flex-shrink: 0;
    }
    .history-waiter-name {
        font-weight: 700;
        color: var(--color-text);
        font-size: 14px;
    }
    .history-waiter-count {
        font-size: 12px;
        color: var(--color-text-muted);
    }
    .history-waiter-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .history-waiter-body {
        padding: 8px;
    }

    /* ── Task Rows ── */
    .history-task-row {
        border: 1px solid #f1f5f9;
        border-radius: var(--radius-md);
        padding: 10px 12px;
        margin-bottom: 6px;
        background: #ffffff;
        transition: background 0.15s;
    }
    .history-task-row:hover { background: var(--color-bg); }
    .history-task-row:last-child { margin-bottom: 0; }
    .history-task-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .history-task-title {
        font-weight: 600;
        font-size: 13px;
        color: var(--color-text);
    }
    .history-task-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 8px;
    }
    .history-meta-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .history-meta-label {
        font-size: 12px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .history-task-extras {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
    }
    .history-photo-btn {
        font-size: 12px;
        color: #1d4ed8;
        font-weight: 700;
        background: #e0ecff;
        border: 1px solid #bfdbfe;
        border-radius: var(--radius-md);
        padding: 5px 10px;
        cursor: pointer;
    }
    .history-photo-btn:hover { background: #dbeafe; }

    /* ── Photo Modal ── */
    .task-photo-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 1500;
    }
    .task-photo-modal-box {
        width: min(100%, 760px);
        max-height: calc(100vh - 32px);
        overflow: auto;
        background: #fff;
        border-radius: 14px;
        padding: 14px;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.35);
    }
    .task-photo-modal-image {
        width: 100%;
        max-height: 72vh;
        object-fit: contain;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: var(--color-bg);
    }
    .task-photo-modal-meta {
        margin-top: 8px;
        font-size: 12px;
        color: var(--color-text-muted);
    }

    @media (max-width: 640px) {
        .history-filter-bar { flex-direction: column; }
        .history-filter-group { min-width: 100%; }
        .history-waiter-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .history-task-meta {
            flex-direction: column;
            gap: 6px;
        }
    }

    /* ════════════════════════════════════════════════════
       TRACKING SECTION — REDESIGN
       Bersih, simple, mobile-first card-list
    ════════════════════════════════════════════════════ */
    .trk-wrap { background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; }
    .trk-head { padding: 16px 18px; border-bottom: 1px solid var(--color-border); background: linear-gradient(180deg, #fafbfc 0%, #fff 100%); }
    .trk-head-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .trk-title { margin: 0; font-size: 17px; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 8px; }
    .trk-title-icon { font-size: 22px; }
    .trk-date-pills { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .trk-date-pill {
        padding: 6px 12px; border-radius: 999px; border: 1px solid var(--color-border);
        background: #fff; color: var(--color-text-secondary); font-size: 12px; font-weight: 600;
        cursor: pointer; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
    }
    .trk-date-pill:hover { background: var(--color-bg); border-color: #cbd5e1; }
    .trk-date-pill.is-active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
    .trk-date-pill.is-active:hover { background: var(--color-primary); }
    .trk-date-input { padding: 6px 10px; border: 1px solid var(--color-border); border-radius: 8px; font-size: 12px; color: var(--color-text); background: #fff; }
    .trk-date-input:focus { outline: 2px solid var(--color-primary); outline-offset: 1px; }

    .trk-progress { display: flex; flex-direction: column; gap: 8px; }
    .trk-progress-stats { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .trk-progress-text { font-size: 13px; color: var(--color-text-secondary); }
    .trk-progress-text strong { color: var(--color-text); font-weight: 700; }
    .trk-progress-pct { font-size: 22px; font-weight: 800; color: var(--color-text); font-variant-numeric: tabular-nums; }
    .trk-progress-pct.is-perfect { color: #059669; }
    .trk-progress-pct.is-low { color: #dc2626; }
    .trk-progress-bar { height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
    .trk-progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #10b981, #34d399); transition: width 0.6s ease; }
    .trk-progress-fill.is-low { background: linear-gradient(90deg, #f59e0b, #ef4444); }
    .trk-progress-fill.is-mid { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

    .trk-toolbar {
        display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        padding: 10px 18px; background: #fafbfc; border-bottom: 1px solid var(--color-border);
    }
    .trk-status-tabs { display: inline-flex; background: #fff; border: 1px solid var(--color-border); border-radius: 10px; padding: 3px; gap: 2px; }
    .trk-status-tab {
        padding: 6px 14px; font-size: 12px; font-weight: 600; color: var(--color-text-secondary);
        background: none; border: 0; border-radius: 7px; cursor: pointer;
        display: inline-flex; align-items: center; gap: 5px; transition: all 0.15s;
    }
    .trk-status-tab:hover { background: var(--color-bg); }
    .trk-status-tab.is-active { background: var(--color-text); color: #fff; }
    .trk-status-tab.is-active:hover { background: var(--color-text); }
    .trk-status-tab-count {
        font-size: 10px; padding: 1px 6px; border-radius: 999px; background: rgba(0,0,0,0.08);
        font-weight: 700; min-width: 18px; text-align: center;
    }
    .trk-status-tab.is-active .trk-status-tab-count { background: rgba(255,255,255,0.25); }

    .trk-search-wrap { flex: 1; min-width: 180px; max-width: 320px; position: relative; }
    .trk-search { width: 100%; padding: 8px 12px 8px 32px; border: 1px solid var(--color-border); border-radius: 8px; font-size: 13px; background: #fff; }
    .trk-search:focus { outline: 2px solid var(--color-primary); outline-offset: -1px; border-color: var(--color-primary); }
    .trk-search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 14px; pointer-events: none; }

    .trk-cat-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .trk-cat-chip {
        padding: 5px 11px; border-radius: 999px; border: 1px solid var(--color-border);
        background: #fff; color: var(--color-text-secondary); font-size: 11px; font-weight: 600;
        cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 5px;
    }
    .trk-cat-chip:hover { border-color: #94a3b8; }
    .trk-cat-chip.is-active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
    .trk-cat-chip-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; background: currentColor; opacity: 0.7; }
    .trk-cat-chip.is-active .trk-cat-chip-dot { opacity: 1; }

    .trk-view-toggle { display: inline-flex; gap: 4px; margin-left: auto; }
    .trk-view-btn {
        padding: 6px 10px; border: 1px solid var(--color-border); border-radius: 8px;
        background: #fff; font-size: 12px; font-weight: 600; color: var(--color-text-secondary);
        cursor: pointer; transition: all 0.15s;
    }
    .trk-view-btn.is-active { background: var(--color-text); color: #fff; border-color: var(--color-text); }

    .trk-list { padding: 8px; max-height: 640px; overflow-y: auto; }
    .trk-list-empty {
        text-align: center; padding: 40px 20px; color: var(--color-text-muted);
        background: var(--color-bg); border-radius: var(--radius-md); border: 1px dashed var(--color-border);
        font-size: 13px;
    }
    .trk-list-empty-icon { font-size: 32px; margin-bottom: 8px; opacity: 0.6; }

    .trk-row {
        display: flex; align-items: center; gap: 12px; padding: 12px 14px;
        border-radius: 10px; transition: background 0.15s;
        border-left: 3px solid transparent;
        cursor: pointer;
    }
    .trk-row:hover { background: #f8fafc; }
    .trk-row + .trk-row { margin-top: 2px; }
    .trk-row.is-done { border-left-color: #10b981; }
    .trk-row.is-overdue { border-left-color: #ef4444; }
    .trk-row.is-pending { border-left-color: #f59e0b; }
    .trk-row.is-progress { border-left-color: #3b82f6; }

    .trk-row-status {
        flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700;
    }
    .trk-row-status.is-done { background: #d1fae5; color: #059669; }
    .trk-row-status.is-overdue { background: #fee2e2; color: #dc2626; }
    .trk-row-status.is-pending { background: #fef3c7; color: #d97706; }
    .trk-row-status.is-progress { background: #dbeafe; color: #2563eb; }

    .trk-row-main { flex: 1; min-width: 0; }
    .trk-row-title {
        font-size: 14px; font-weight: 600; color: var(--color-text);
        margin: 0 0 3px; line-height: 1.3;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .trk-row-meta {
        font-size: 12px; color: var(--color-text-secondary);
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    }
    .trk-row-meta-item { display: inline-flex; align-items: center; gap: 4px; }
    .trk-row-meta-icon { font-size: 11px; opacity: 0.7; }
    .trk-row-cat-pill {
        font-size: 10px; padding: 1px 7px; border-radius: 999px;
        background: var(--color-bg); color: var(--color-text-secondary); font-weight: 600;
    }

    .trk-row-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
    .trk-row-time { font-size: 11px; color: var(--color-text-muted); font-variant-numeric: tabular-nums; min-width: 42px; text-align: right; }
    .trk-row-time.is-overdue { color: #dc2626; font-weight: 700; }
    .trk-row-photo-btn {
        width: 30px; height: 30px; border: 1px solid var(--color-border); border-radius: 8px;
        background: #fff; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center;
        transition: all 0.15s; padding: 0;
    }
    .trk-row-photo-btn:hover { background: var(--color-primary-bg); border-color: var(--color-primary); }

    .trk-row-detail {
        display: none; padding: 10px 14px 12px 56px;
        font-size: 12px; color: var(--color-text-secondary);
        background: #f8fafc; border-radius: 0 0 10px 10px; margin-top: -2px;
    }
    .trk-row.is-expanded + .trk-row-detail { display: block; }
    .trk-row-detail-grid { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; }
    .trk-row-detail-label { font-weight: 600; color: var(--color-text); }

    /* Per-Waiter view (rack scope) */
    .trk-waiter-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 10px; padding: 12px;
    }
    .trk-waiter-card {
        background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-md);
        padding: 12px; transition: box-shadow 0.15s;
    }
    .trk-waiter-card:hover { box-shadow: var(--shadow-sm); }
    .trk-waiter-card-head {
        display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 10px;
    }
    .trk-waiter-name { font-size: 14px; font-weight: 700; color: var(--color-text); margin: 0; }
    .trk-waiter-bar {
        display: flex; height: 6px; border-radius: 999px; overflow: hidden; background: #e5e7eb;
        margin-bottom: 8px;
    }
    .trk-waiter-bar-done { background: #10b981; }
    .trk-waiter-bar-miss { background: #ef4444; }
    .trk-waiter-stats { display: flex; gap: 12px; font-size: 11px; color: var(--color-text-secondary); }
    .trk-waiter-stat-done { color: #059669; font-weight: 700; }
    .trk-waiter-stat-miss { color: #dc2626; font-weight: 700; }

    @media (max-width: 640px) {
        .trk-row { padding: 10px 12px; gap: 9px; }
        .trk-row-status { width: 24px; height: 24px; font-size: 12px; }
        .trk-row-title { font-size: 13px; }
        .trk-row-meta { font-size: 11px; gap: 8px; }
        .trk-toolbar { padding: 10px; gap: 8px; }
        .trk-search-wrap { max-width: none; flex: 1 1 100%; order: -1; }
        .trk-status-tabs { flex: 1; justify-content: stretch; }
        .trk-status-tab { flex: 1; justify-content: center; }
        .trk-view-toggle { margin-left: 0; }
        .trk-progress-pct { font-size: 18px; }
        .trk-row-detail { padding-left: 12px; }
    }
</style>
@endpush

@section('content')
    @php
        $createTaskType = $isRackScope ? 'rack_check' : 'general';
        $createScope = $isRackScope ? 'rack_check' : 'general';
        $pageTitle = $isRackScope ? 'Manajemen Cek Rak Waiter' : 'Manajemen Tugas Umum Waiter';
        $pageSubtitle = $isRackScope
            ? 'Kelola task cek rak secara terpisah dari tugas operasional umum.'
            : 'Kelola task operasional umum waiter tanpa bercampur dengan task cek rak.';
        $switchLabel = $isRackScope ? 'Buka Tugas Umum' : 'Buka Cek Rak';
    @endphp

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">{{ $pageTitle }}</h2>
            <div class="page-subtitle">{{ $pageSubtitle }}</div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            @if($isRackScope)
            <button type="button" class="btn" style="background:#dbeafe; color:#1d4ed8;" onclick="openExportStockModal()">
                📥 Export Stok
            </button>
            @endif
            <button type="button" class="btn" style="background:#fef3c7; color:#92400e;" onclick="openReassignModal()">
                🔄 Reassign
            </button>
            <button type="button" class="btn" style="background:#e2e8f0; color:var(--color-text);" onclick="openCategoryModal()">
                Kelola Kategori
            </button>
            <a href="{{ route($otherTaskScopeRouteName ?? 'admin.tasks.rack.index') }}" class="btn" style="background:#e2e8f0; color:var(--color-text);">
                {{ $switchLabel }}
            </a>
            <a href="{{ route('admin.tasks.create', ['task_type' => $createTaskType, 'task_scope' => $createScope]) }}" class="btn btn-primary">
                + Buat Tugas Baru
            </a>
            <a href="{{ route('admin.tasks.templates.board', ['scope' => $createScope]) }}" class="btn" style="background:#ede9fe; color:#5b21b6; border:1px solid #c4b5fd;" title="Edit template tugas secara drag-drop per kolom jadwal">
                📅 Board Edit Template
            </a>
            <form method="POST" action="{{ route('admin.tasks.force_generate') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn" style="background:#fef3c7; color:#92400e; border:1px solid #f59e0b;" title="Generate semua recurring task SEKARANG (bypass jadwal & waktu)">
                    ⚡ Force Generate
                </button>
            </form>
            <form method="POST" action="{{ route('admin.tasks.bulk_cancel_today') }}" style="display:inline;" onsubmit="return confirm('Yakin batalkan SEMUA task pending hari ini? Audit history tetap tersimpan.');">
                @csrf
                <input type="hidden" name="task_scope" value="{{ $createScope ?? 'rack_check' }}">
                <button type="submit" class="btn" style="background:#fee2e2; color:#991b1b; border:1px solid #f87171;" title="Batalkan semua task pending/in-progress hari ini (status -> cancelled)">
                    🚫 Cancel Pending Hari Ini
                </button>
            </form>
            <button type="button" class="btn" style="background:#7f1d1d; color:#fff; border:1px solid #7f1d1d;" title="Hapus SEMUA waiter_tasks, templates, idempotency, reminder state — operasi destruktif" onclick="document.getElementById('reset-all-tasks-modal').style.display='flex';">
                💣 Reset Semua Tugas
            </button>
        </div>
    </div>

    {{-- Reset all tasks modal --}}
    <div id="reset-all-tasks-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:16px;">
        <div style="width:min(560px,100%); background:#fff; border-radius:12px; padding:20px; box-shadow:var(--shadow-md);">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <span style="font-size:28px;">💣</span>
                <strong style="font-size:18px; color:#7f1d1d;">Reset Semua Tugas</strong>
            </div>
            <p style="color:#374151; font-size:14px; margin-bottom:8px;">Operasi ini akan menghapus <strong>SEMUA</strong> data tugas waiter, termasuk:</p>
            <ul style="color:#374151; font-size:14px; margin:0 0 14px 18px; padding-left:0;">
                <li><code>waiter_tasks</code> — pending, in_progress, done, overdue, cancelled</li>
                <li><code>waiter_task_templates</code> — semua template recurring</li>
                <li><code>waiter_task_idempotency</code> — cache prevent double-submit</li>
                <li><code>waiter_task_reminder_state</code> — tracking notifikasi WA</li>
            </ul>
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:13px; color:#991b1b;">
                ⚠️ Tugas yang sedang dikerjakan waiter saat ini juga akan hilang. Audit log akan dicatat.
            </div>
            <form method="POST" action="{{ route('admin.tasks.reset_all') }}">
                @csrf
                <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Ketik <code style="background:#fef2f2; color:#7f1d1d; padding:2px 6px; border-radius:4px;">RESET SEMUA TUGAS</code> untuk konfirmasi:
                </label>
                <input type="text" name="confirmation" id="reset-all-confirmation-input" autocomplete="off" required class="input" style="width:100%; margin-bottom:14px; font-family:monospace;" placeholder="RESET SEMUA TUGAS">
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="background:#e5e7eb; color:#374151;" onclick="document.getElementById('reset-all-tasks-modal').style.display='none'; document.getElementById('reset-all-confirmation-input').value='';">Batal</button>
                    <button type="submit" id="reset-all-submit-btn" class="btn" style="background:#7f1d1d; color:#fff;" disabled>💣 Ya, Reset Semua</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var input = document.getElementById('reset-all-confirmation-input');
            var btn = document.getElementById('reset-all-submit-btn');
            if (input && btn) {
                input.addEventListener('input', function() {
                    btn.disabled = (input.value.trim() !== 'RESET SEMUA TUGAS');
                });
            }
        })();
    </script>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         KPI CARDS (Reduced to 4 most important)
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="task-kpi-grid">
        <div class="task-kpi-card" style="border-left-color: var(--color-warning);">
            <div class="task-kpi-value" style="color: var(--color-warning);">{{ $kpi['pending'] }}</div>
            <div class="task-kpi-label">Task Pending</div>
        </div>
        <div class="task-kpi-card" style="border-left-color: var(--color-success);">
            <div class="task-kpi-value" style="color: var(--color-success);">{{ $kpi['done'] }}</div>
            <div class="task-kpi-label">Task Selesai</div>
        </div>
        <div class="task-kpi-card" style="border-left-color: var(--color-danger);">
            <div class="task-kpi-value" style="color: var(--color-danger);">{{ $kpi['overdue'] }}</div>
            <div class="task-kpi-label">Tidak Selesai</div>
        </div>
        @if($isRackScope)
            <div class="task-kpi-card" style="border-left-color: #f97316;">
                <div class="task-kpi-value" style="color: #ea580c;">{{ $rackNotDoneTotal }}</div>
                <div class="task-kpi-label">Rak Belum Dicek</div>
            </div>
        @else
            <div class="task-kpi-card" style="border-left-color: #4f46e5;">
                <div class="task-kpi-value" style="color: #4338ca;">{{ count($waiters ?? []) }}</div>
                <div class="task-kpi-label">Waiter Aktif</div>
            </div>
        @endif
    </div>

    {{-- Overdue Alert Banner --}}
    @if($kpi['overdue'] > 0 || $rackNotDoneTotal > 0)
        <div class="overdue-banner">
            <span class="overdue-banner-icon" aria-hidden="true">&#9888;</span>
            <span>
                @if($isRackScope)
                    {{ $rackNotDoneTotal }} rak belum dicek dan {{ $kpi['overdue'] }} task tidak selesai pada {{ $selectedDate }}.
                @else
                    {{ $kpi['overdue'] }} task tidak selesai tepat waktu. Periksa tracking di bawah.
                @endif
            </span>
        </div>
    @endif

    {{-- Quick Info Badges --}}
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom: 16px;">
        @if($isRackScope)
            <span class="badge" style="background:#fff7ed;color:#9a3412;">Rak Aktif: {{ $kpi['activeRackCount'] }}</span>
            <span class="badge" style="background:#ecfdf5;color:#166534;">Template: {{ count($recurringTemplates ?? []) }}</span>
            <span class="badge" style="background:#fef3c7;color:#92400e;">Stok Dilaporkan: {{ $collectedTotalMentions }}</span>
        @else
            <span class="badge" style="background:#ecfdf5;color:#166534;">Waiter Pelapor: {{ $activityWaiterCount }}</span>
            <span class="badge" style="background:#fef3c7;color:#92400e;">Laporan Kegiatan: {{ $activityTotalReports }}</span>
        @endif
        <a href="{{ route('admin.racks.index') }}" class="btn btn-sm" style="background:#2563eb;color:#fff;">Kelola Rak</a>
    </div>

    {{-- Category Breakdown Section --}}
    @if(!$isRackScope && !empty($categoryBreakdown))
    <details class="task-section" open>
        <summary class="task-section-summary">Breakdown Kategori Tugas <span class="badge" style="background:var(--color-primary-bg);color:var(--color-primary);">{{ count($categoryBreakdown) }} Kategori</span></summary>
        <div class="task-section-body">
            <div class="task-category-grid">
                @foreach($categoryBreakdown as $cb)
                    @php
                        $percent = $cb['total'] > 0 ? round(($cb['done'] / $cb['total']) * 100) : 0;
                        $color = $cb['category_color'] ?? '#94a3b8';
                    @endphp
                    <div class="task-category-card">
                        <div class="task-category-info">
                            <div class="task-category-name">
                                <span class="task-category-dot" style="background-color: {{ $color }};"></span>
                                {{ $cb['category_name'] ?: 'Tanpa Kategori' }}
                            </div>
                            <div class="task-category-count">{{ $cb['done'] }}/{{ $cb['total'] }}</div>
                        </div>
                        <div class="task-category-progress">
                            <div class="task-category-progress-bar" style="width: {{ $percent }}%; background-color: {{ $color }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </details>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 1: Monitoring Cek Rak (FIRST for rack_check scope)
         or Tracking Tugas per Tanggal (for general scope)
    ═══════════════════════════════════════════════════════════════ --}}
    @if($isRackScope)
    <details class="task-section" open>
        <summary class="task-section-summary">Monitoring Cek Rak <span class="badge" style="background:var(--color-danger-bg);color:#991b1b;">{{ $rackNotDoneTotal }} belum selesai</span></summary>
        <div class="task-section-body">
        @if(empty($rackExecutionBoard))
            <div class="empty">Tidak ada task cek rak pada tanggal ini.</div>
        @else
            <div class="task-tools-row">
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="badge" style="background:var(--color-success-bg);color:#166534;">Selesai: {{ $rackDoneTotal }}</span>
                    <span class="badge" style="background:var(--color-danger-bg);color:#991b1b;">Belum: {{ $rackNotDoneTotal }}</span>
                    <span class="badge" style="background:var(--color-primary-bg);color:#3730a3;">Rak Dipantau: {{ count($rackExecutionBoard) }}</span>
                </div>
                <div class="task-filter-wrap">
                    <input type="text" class="task-inline-filter js-task-inline-filter"
                        data-target-id="rack-monitoring-list"
                        data-empty-id="rack-monitoring-empty"
                        placeholder="Cari rak: nama, lokasi, waiter...">
                </div>
            </div>

            <div id="rack-monitoring-list" class="task-list-grid">
                @foreach($rackExecutionBoard as $rackBoard)
                    @php
                        $rackSearchText = strtolower(trim(implode(' ', [
                            (string) ($rackBoard['rack_name'] ?? ''),
                            (string) ($rackBoard['rack_location'] ?? ''),
                            (string) ($rackBoard['rack_barcode_value'] ?? ''),
                            implode(' ', array_map(fn ($waiter) => (string) ($waiter['name'] ?? ''), $rackBoard['done_waiters'] ?? [])),
                            implode(' ', array_map(fn ($waiter) => (string) ($waiter['name'] ?? ''), $rackBoard['not_done_waiters'] ?? [])),
                        ])));
                    @endphp

                    <details class="task-data-card js-task-filter-item" data-filter-text="{{ $rackSearchText }}">
                        <summary class="task-data-card-summary">
                            <div>
                                <div class="task-data-card-title">{{ $rackBoard['rack_name'] ?? '-' }}</div>
                                <div class="task-data-card-subtitle">{{ $rackBoard['rack_location'] ?? '-' }}</div>
                            </div>
                            <div class="task-data-card-badges">
                                <span class="badge" style="background:#fff7ed;color:#9a3412;">QR: {{ $rackBoard['rack_barcode_value'] ?? '-' }}</span>
                                <span class="badge" style="background:var(--color-success-bg);color:#166534;">{{ $rackBoard['done_count'] ?? 0 }} done</span>
                                <span class="badge" style="background:var(--color-danger-bg);color:#991b1b;">{{ $rackBoard['not_done_count'] ?? 0 }} pending</span>
                                @if(!empty($rackBoard['is_role_round_robin']))
                                    <span class="badge" style="background:#ecfeff;color:#0f766e;">Rotasi {{ ucfirst((string) ($rackBoard['assigned_waiter_role'] ?? 'pelayan')) }}</span>
                                @endif
                            </div>
                        </summary>

                        <div class="task-data-card-body">
                            @if(!empty($rackBoard['is_role_round_robin']))
                                <div style="font-size:12px; color:var(--color-text); background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:8px 10px; margin-bottom:10px;">
                                    Penanggung jawab rotasi {{ ($selectedDate ?? date('Y-m-d')) === date('Y-m-d') ? 'hari ini' : 'tanggal ini' }}:
                                    <strong>{{ $rackBoard['today_assignee_label'] ?? '-' }}</strong>
                                </div>
                            @endif

                            <div class="task-status-columns">
                                <div class="task-status-col task-status-col-success">
                                    <div class="task-status-title">Sudah mengerjakan ({{ $rackBoard['done_count'] ?? 0 }})</div>
                                    @if(empty($rackBoard['done_waiters']))
                                        <div class="task-status-empty">Belum ada waiter selesai.</div>
                                    @else
                                        <ul class="task-status-list">
                                            @foreach($rackBoard['done_waiters'] as $doneWaiter)
                                                <li>
                                                    <strong>{{ $doneWaiter['name'] ?? '-' }}</strong>
                                                    @if(!empty($doneWaiter['completed_scanned_barcode']))
                                                        <div>Scan QR: {{ $doneWaiter['completed_scanned_barcode'] }}</div>
                                                    @endif
                                                    <div>
                                                        Stok:
                                                        @if(!empty($doneWaiter['completed_no_out_of_stock']))
                                                            Lengkap
                                                        @elseif(!empty($doneWaiter['completed_stock_report']))
                                                            {{ $doneWaiter['completed_stock_report'] }}
                                                        @else
                                                            -
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div class="task-status-col task-status-col-danger">
                                    <div class="task-status-title">Belum mengerjakan ({{ $rackBoard['not_done_count'] ?? 0 }})</div>
                                    @if(empty($rackBoard['not_done_waiters']))
                                        <div class="task-status-empty">Semua waiter sudah selesai.</div>
                                    @else
                                        <ul class="task-status-list">
                                            @foreach($rackBoard['not_done_waiters'] as $pendingWaiter)
                                                <li>
                                                    <strong>{{ $pendingWaiter['name'] ?? '-' }}</strong>
                                                    <div>Status: {{ strtoupper($pendingWaiter['status'] ?? 'pending') }}</div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>

            <div id="rack-monitoring-empty" class="task-empty-filtered">
                Tidak ada data rak yang cocok dengan kata kunci pencarian.
            </div>
        @endif
        </div>
    </details>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 2: Tracking Tugas per Tanggal (REDESIGNED)
         Layout: sticky header (date pills + progress) → toolbar (status tabs + search + cat chips + view) → unified card list
    ═══════════════════════════════════════════════════════════════ --}}
    @php
        // Hitung statistik per tanggal terpilih
        $trkDoneCount = count($dateDoneTasks);
        $trkNotDoneCount = count($dateNotDoneTasks);
        $trkTotalCount = $trkDoneCount + $trkNotDoneCount;
        $trkPercent = $trkTotalCount > 0 ? (int) round(($trkDoneCount / $trkTotalCount) * 100) : 0;

        // Tier color
        $trkPctClass = $trkPercent >= 90 ? 'is-perfect' : ($trkPercent < 60 ? 'is-low' : '');
        $trkFillClass = $trkPercent >= 80 ? '' : ($trkPercent >= 50 ? 'is-mid' : 'is-low');

        // Pisah pending vs overdue vs in_progress dari notDoneTasks
        $trkOverdueCount = 0;
        $trkPendingCount = 0;
        $trkProgressCount = 0;
        foreach ($dateNotDoneTasks as $t) {
            $st = (string) ($t['status'] ?? 'pending');
            if ($st === 'overdue') $trkOverdueCount++;
            elseif ($st === 'in_progress') $trkProgressCount++;
            else $trkPendingCount++;
        }

        // Format tanggal Indonesia
        $trkDateLabel = '';
        try {
            $trkDateLabel = \Carbon\Carbon::parse($selectedDate)->locale('id')->isoFormat('dddd, D MMMM YYYY');
        } catch (\Throwable $e) {
            $trkDateLabel = $selectedDate;
        }

        $todayStr = date('Y-m-d');
        $yesterdayStr = date('Y-m-d', strtotime('-1 day'));
        $tomorrowStr = date('Y-m-d', strtotime('+1 day'));

        $trkRouteName = $taskScopeRouteName ?? 'admin.tasks.index';
    @endphp

    <section class="trk-wrap">
        <div class="trk-head">
            <div class="trk-head-top">
                <h3 class="trk-title">
                    <span class="trk-title-icon">📅</span>
                    <span>Tracking Tugas per Tanggal</span>
                </h3>
                <div class="trk-date-pills">
                    <a href="{{ route($trkRouteName, ['track_date' => $yesterdayStr]) }}" class="trk-date-pill {{ $selectedDate === $yesterdayStr ? 'is-active' : '' }}">Kemarin</a>
                    <a href="{{ route($trkRouteName, ['track_date' => $todayStr]) }}" class="trk-date-pill {{ $selectedDate === $todayStr ? 'is-active' : '' }}">Hari Ini</a>
                    <a href="{{ route($trkRouteName, ['track_date' => $tomorrowStr]) }}" class="trk-date-pill {{ $selectedDate === $tomorrowStr ? 'is-active' : '' }}">Besok</a>
                    <form method="GET" action="{{ route($trkRouteName) }}" style="display:inline-flex; gap:6px; align-items:center;">
                        <input type="date" name="track_date" value="{{ $selectedDate }}" class="trk-date-input" onchange="this.form.submit()">
                    </form>
                </div>
            </div>

            <div class="trk-progress">
                <div class="trk-progress-stats">
                    <div class="trk-progress-text">
                        <strong>{{ $trkDateLabel }}</strong> &middot;
                        @if($trkTotalCount === 0)
                            Tidak ada tugas dijadwalkan
                        @else
                            <strong>{{ $trkDoneCount }}</strong> selesai · <strong>{{ $trkNotDoneCount }}</strong> belum
                            @if($trkOverdueCount > 0)
                                · <span style="color:#dc2626; font-weight:700;">{{ $trkOverdueCount }} overdue</span>
                            @endif
                        @endif
                    </div>
                    <div class="trk-progress-pct {{ $trkPctClass }}">{{ $trkPercent }}%</div>
                </div>
                <div class="trk-progress-bar">
                    <div class="trk-progress-fill {{ $trkFillClass }}" style="width: {{ $trkPercent }}%;"></div>
                </div>
            </div>
        </div>

        @if($trkTotalCount > 0)
        <div class="trk-toolbar">
            <div class="trk-status-tabs" role="tablist" aria-label="Filter status tugas">
                <button type="button" class="trk-status-tab is-active" data-trk-status="all" role="tab" aria-selected="true">
                    Semua <span class="trk-status-tab-count">{{ $trkTotalCount }}</span>
                </button>
                <button type="button" class="trk-status-tab" data-trk-status="done" role="tab" aria-selected="false">
                    ✅ Selesai <span class="trk-status-tab-count">{{ $trkDoneCount }}</span>
                </button>
                <button type="button" class="trk-status-tab" data-trk-status="pending" role="tab" aria-selected="false">
                    ⏳ Belum <span class="trk-status-tab-count">{{ $trkNotDoneCount }}</span>
                </button>
            </div>

            <div class="trk-search-wrap">
                <span class="trk-search-icon">🔍</span>
                <input type="text" id="trkSearch" class="trk-search" placeholder="Cari tugas, waiter, atau rak..." autocomplete="off">
            </div>

            @if($isRackScope)
                <div class="trk-view-toggle" role="tablist" aria-label="Mode tampilan">
                    <button type="button" class="trk-view-btn is-active" data-trk-view="task">📋 Per Tugas</button>
                    <button type="button" class="trk-view-btn" data-trk-view="waiter">👥 Per Waiter</button>
                </div>
            @endif
        </div>

        @if(!$isRackScope && !empty($categories))
        <div class="trk-toolbar" style="border-top: 0; padding-top: 4px;">
            <div class="trk-cat-chips">
                <button type="button" class="trk-cat-chip is-active" data-trk-cat="">Semua kategori</button>
                <button type="button" class="trk-cat-chip" data-trk-cat="uncategorized">
                    <span class="trk-cat-chip-dot" style="color:#94a3b8;"></span>
                    Tanpa kategori
                </button>
                @foreach($categories as $cat)
                    <button type="button" class="trk-cat-chip" data-trk-cat="{{ $cat['id'] }}">
                        <span class="trk-cat-chip-dot" style="color: {{ $cat['color'] ?? '#3b82f6' }};"></span>
                        {{ $cat['name'] }}
                    </button>
                @endforeach
            </div>
        </div>
        @endif
        @endif

        {{-- ── Mode 1: Per Tugas (default, untuk semua scope) ── --}}
        <div class="trk-list" data-trk-view-pane="task">
            @if($trkTotalCount === 0)
                <div class="trk-list-empty">
                    <div class="trk-list-empty-icon">📭</div>
                    <div>Belum ada tugas pada tanggal ini.</div>
                    <div style="margin-top:6px; font-size:12px;">Coba pilih tanggal lain atau klik <strong>Force Generate</strong> untuk membuat task dari template.</div>
                </div>
            @else
                @php
                    // Merge done + not done jadi 1 list dengan info status untuk render
                    $trkAllRows = [];
                    foreach ($dateDoneTasks as $t) {
                        $trkAllRows[] = ['__status_group' => 'done', 'task' => $t];
                    }
                    foreach ($dateNotDoneTasks as $t) {
                        $st = (string) ($t['status'] ?? 'pending');
                        $group = $st === 'overdue' ? 'overdue' : ($st === 'in_progress' ? 'progress' : 'pending');
                        $trkAllRows[] = ['__status_group' => $group, 'task' => $t];
                    }
                    // Sort: overdue first, then pending/progress, then done by completed_at desc
                    usort($trkAllRows, function ($a, $b) {
                        $rank = ['overdue' => 0, 'pending' => 1, 'progress' => 1, 'done' => 2];
                        $ra = $rank[$a['__status_group']] ?? 9;
                        $rb = $rank[$b['__status_group']] ?? 9;
                        if ($ra !== $rb) return $ra <=> $rb;
                        if ($a['__status_group'] === 'done') {
                            return ((int) ($b['task']['completed_at'] ?? 0)) <=> ((int) ($a['task']['completed_at'] ?? 0));
                        }
                        return strcmp((string) ($a['task']['title'] ?? ''), (string) ($b['task']['title'] ?? ''));
                    });
                @endphp

                @foreach($trkAllRows as $rowIdx => $row)
                    @php
                        $task = $row['task'];
                        $sg = $row['__status_group'];
                        $statusIcon = match($sg) {
                            'done' => '✓',
                            'overdue' => '!',
                            'progress' => '⏵',
                            default => '·',
                        };
                        $statusLabel = match($sg) {
                            'done' => 'Selesai',
                            'overdue' => 'Overdue',
                            'progress' => 'Sedang dikerjakan',
                            default => 'Belum',
                        };
                        $waiterName = $sg === 'done'
                            ? ($task['completed_by_waiter_name'] ?? '-')
                            : ($task['assigned_waiter_name'] ?? 'Belum di-assign');
                        $catName = $task['category_name'] ?? '';
                        $catId = $task['category_id'] ?? 'uncategorized';
                        $rackName = $task['rack_name'] ?? '';
                        $isRackTask = ($task['task_type'] ?? 'general') === 'rack_check';
                        $title = $task['title'] ?? ($rackName ?: 'Tugas');
                        $completedAt = (int) ($task['completed_at'] ?? 0);
                        $timeLabel = '';
                        if ($sg === 'done' && $completedAt > 0) {
                            $timeLabel = date('H:i', $completedAt);
                        } elseif ($sg === 'overdue') {
                            $timeLabel = 'overdue';
                        }
                        $hasPhoto = !empty($task['completed_photo_proof_url']);
                        $rowId = 'trk-row-' . md5(($task['id'] ?? '') . '-' . $rowIdx);
                    @endphp

                    <div class="trk-row is-{{ $sg }} js-trk-row"
                         data-trk-status="{{ $sg === 'done' ? 'done' : 'pending' }}"
                         data-trk-cat="{{ $catId }}"
                         data-trk-search="{{ strtolower(($title) . ' ' . $waiterName . ' ' . $rackName . ' ' . $catName) }}"
                         data-trk-row="{{ $rowId }}"
                         onclick="trkToggleDetail(this)">
                        <div class="trk-row-status is-{{ $sg }}" aria-label="{{ $statusLabel }}">{{ $statusIcon }}</div>
                        <div class="trk-row-main">
                            <div class="trk-row-title">{{ $title }}</div>
                            <div class="trk-row-meta">
                                <span class="trk-row-meta-item"><span class="trk-row-meta-icon">👤</span>{{ $waiterName }}</span>
                                @if($isRackTask && $rackName)
                                    <span class="trk-row-meta-item"><span class="trk-row-meta-icon">📦</span>{{ $rackName }}</span>
                                @endif
                                @if($catName)
                                    <span class="trk-row-cat-pill" style="@if(!empty($task['category_color'])) background: {{ $task['category_color'] }}22; color: {{ $task['category_color'] }}; @endif">{{ $catName }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="trk-row-actions">
                            @if($timeLabel)
                                <span class="trk-row-time {{ $sg === 'overdue' ? 'is-overdue' : '' }}">{{ $timeLabel }}</span>
                            @endif
                            @if($hasPhoto)
                                <button type="button" class="trk-row-photo-btn" onclick="event.stopPropagation(); trkOpenPhoto('{{ $task['completed_photo_proof_url'] }}', '{{ addslashes($title) }}')" title="Lihat foto bukti">📷</button>
                            @endif
                        </div>
                    </div>

                    {{-- Detail expandable --}}
                    <div class="trk-row-detail" data-trk-detail-for="{{ $rowId }}">
                        <div class="trk-row-detail-grid">
                            @if(!empty($task['description']))
                                <div class="trk-row-detail-label">Deskripsi:</div>
                                <div>{{ $task['description'] }}</div>
                            @endif
                            <div class="trk-row-detail-label">Status:</div>
                            <div>{{ $statusLabel }}</div>
                            @if($sg === 'done' && !empty($task['completed_scanned_barcode']))
                                <div class="trk-row-detail-label">Scan QR:</div>
                                <div style="font-family: monospace;">{{ $task['completed_scanned_barcode'] }}</div>
                            @endif
                            @if($sg === 'done' && $isRackTask)
                                <div class="trk-row-detail-label">Stok:</div>
                                <div>
                                    @if(!empty($task['completed_no_out_of_stock']))
                                        ✅ Tidak ada barang habis
                                    @elseif(!empty($task['completed_stock_report']))
                                        ⚠️ {{ $task['completed_stock_report'] }}
                                    @else
                                        -
                                    @endif
                                </div>
                            @endif
                            @if(!empty($task['completed_note']))
                                <div class="trk-row-detail-label">Catatan:</div>
                                <div>{{ $task['completed_note'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="trk-list-empty js-trk-empty-filter" style="display:none; margin-top:8px;">
                    <div class="trk-list-empty-icon">🔎</div>
                    <div>Tidak ada tugas yang cocok dengan filter.</div>
                </div>
            @endif
        </div>

        {{-- ── Mode 2: Per Waiter (rack scope only) ── --}}
        @if($isRackScope && !empty($dateWaiterTrackingBoard))
        <div class="trk-list" data-trk-view-pane="waiter" style="display:none; padding:0;">
            <div class="trk-waiter-grid">
                @foreach($dateWaiterTrackingBoard as $waiterTracking)
                    @php
                        $wDone = (int) ($waiterTracking['done_count'] ?? 0);
                        $wMiss = (int) ($waiterTracking['not_done_count'] ?? 0);
                        $wTotal = $wDone + $wMiss;
                        $wDonePct = $wTotal > 0 ? round(($wDone / $wTotal) * 100) : 0;
                        $wMissPct = $wTotal > 0 ? (100 - $wDonePct) : 0;
                    @endphp
                    <div class="trk-waiter-card">
                        <div class="trk-waiter-card-head">
                            <h4 class="trk-waiter-name">{{ $waiterTracking['waiter_name'] ?? '-' }}</h4>
                            <span style="font-size:11px; color:var(--color-text-muted); font-weight:700;">{{ $wDonePct }}%</span>
                        </div>
                        @if($wTotal > 0)
                            <div class="trk-waiter-bar">
                                <div class="trk-waiter-bar-done" style="width:{{ $wDonePct }}%;"></div>
                                <div class="trk-waiter-bar-miss" style="width:{{ $wMissPct }}%;"></div>
                            </div>
                            <div class="trk-waiter-stats">
                                <span><span class="trk-waiter-stat-done">{{ $wDone }}</span> selesai</span>
                                <span><span class="trk-waiter-stat-miss">{{ $wMiss }}</span> belum</span>
                                <span style="margin-left:auto; color:var(--color-text-muted);">{{ $wTotal }} total</span>
                            </div>
                        @else
                            <div style="font-size:11px; color:var(--color-text-muted);">Tidak ada tugas.</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 3: Laporan Barang Menipis/Habis (rack_check only)
    ═══════════════════════════════════════════════════════════════ --}}
    @if($isRackScope)
    <details class="task-section">
        <summary class="task-section-summary">Laporan Barang Menipis/Habis <span class="badge">{{ $collectedTotalReports }} laporan</span></summary>
        <div class="task-section-body">

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 12px;">
            <span class="badge" style="background:var(--color-danger-bg);color:#991b1b;">Laporan: {{ $collectedTotalReports }}</span>
            <span class="badge" style="background:#fff7ed;color:#9a3412;">Item: {{ $collectedTotalMentions }}</span>
            <span class="badge" style="background:var(--color-info-bg);color:#0c4a6e;">Rak Terdampak: {{ count($collectedRacks) }}</span>
        </div>

        @if($collectedTotalReports === 0)
            <div class="empty">Belum ada laporan barang menipis/habis pada tanggal ini.</div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div style="border:1px solid var(--color-border); border-radius: 10px; padding: 12px; background: var(--color-bg);">
                    <div style="font-weight: 700; color: var(--color-text); margin-bottom: 8px;">Item Paling Sering Dilaporkan</div>
                    @if(empty($collectedTopItems))
                        <div style="font-size: 12px; color: var(--color-text-muted);">Belum ada item terkumpul.</div>
                    @else
                        <ol style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--color-text-secondary);">
                            @foreach(array_slice($collectedTopItems, 0, 12) as $item)
                                <li style="margin-bottom: 4px;">
                                    <strong>{{ $item['item'] ?? '-' }}</strong>
                                    <div style="font-size: 12px; color: var(--color-text-muted);">{{ $item['count'] ?? 0 }} laporan &middot; {{ $item['rack_count'] ?? 0 }} rak</div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                <div style="border:1px solid var(--color-border); border-radius: 10px; padding: 12px; background: var(--color-bg);">
                    <div style="font-weight: 700; color: var(--color-text); margin-bottom: 8px;">Ringkasan</div>
                    <div style="font-size: 12px; color: var(--color-text-secondary); line-height: 1.5;">
                        Dashboard ini mengumpulkan laporan waiter yang mengandung item menipis/habis.
                        Supervisor bisa lihat item apa yang berulang dilaporkan dan dari rak mana sumbernya.
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px;">
                @foreach($collectedRacks as $rackStock)
                    <div style="border:1px solid var(--color-border); border-radius: 10px; padding: 12px; background: #ffffff;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div>
                                <div style="font-weight: 700; color: var(--color-text);">{{ $rackStock['rack_name'] ?? '-' }}</div>
                                <div style="font-size: 12px; color: var(--color-text-secondary);">{{ $rackStock['rack_location'] ?? '-' }}</div>
                            </div>
                            <span class="badge" style="background:#fff7ed;color:#9a3412;">QR: {{ $rackStock['rack_barcode_value'] ?? '-' }}</span>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 8px;">
                            <span class="badge" style="background:var(--color-danger-bg);color:#991b1b;">{{ $rackStock['reports_count'] ?? 0 }} laporan</span>
                            <span class="badge" style="background:#fff7ed;color:#9a3412;">{{ $rackStock['item_mentions_count'] ?? 0 }} item</span>
                        </div>

                        <div style="font-size: 12px; color: var(--color-text-secondary); margin-bottom: 6px; font-weight: 600;">Item sering dilaporkan:</div>
                        @if(empty($rackStock['items']))
                            <div style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 8px;">Belum ada item tercatat.</div>
                        @else
                            <ul style="margin: 0 0 8px 0; padding-left: 18px; font-size: 12px; color: #9a3412;">
                                @foreach(array_slice($rackStock['items'], 0, 10) as $item)
                                    <li>{{ $item['item'] ?? '-' }} <span style="color:var(--color-text-muted);">({{ $item['count'] ?? 0 }}x)</span></li>
                                @endforeach
                            </ul>
                        @endif

                        <div style="font-size: 12px; color: var(--color-text-secondary); margin-bottom: 6px; font-weight: 600;">Laporan terbaru:</div>
                        @if(empty($rackStock['reports']))
                            <div style="font-size: 12px; color: var(--color-text-muted);">Belum ada laporan.</div>
                        @else
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--color-text-secondary);">
                                @foreach(array_slice($rackStock['reports'], 0, 5) as $report)
                                    <li style="margin-bottom: 4px;">
                                        <strong>{{ $report['waiter_name'] ?? '-' }}</strong>
                                        @if(!empty($report['reported_at']))
                                            <span style="color:var(--color-text-muted);">({{ date('d/m H:i', (int) $report['reported_at']) }})</span>
                                        @endif
                                        <div style="font-size: 12px; color: #9a3412;">{{ implode(', ', $report['items'] ?? []) }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </details>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 4: Laporan Kegiatan Waiter (general scope only)
    ═══════════════════════════════════════════════════════════════ --}}
    @if(!$isRackScope)
    <details class="task-section">
        <summary class="task-section-summary">Laporan Kegiatan Waiter <span class="badge">{{ $activityTotalReports }} laporan</span></summary>
        <div class="task-section-body">

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 12px;">
            <span class="badge" style="background:#ede9fe;color:#5b21b6;">Total: {{ $activityTotalReports }}</span>
            <span class="badge" style="background:var(--color-info-bg);color:#0c4a6e;">Pelapor: {{ $activityWaiterCount }}</span>
        </div>

        @if($activityTotalReports === 0)
            <div class="empty">Belum ada laporan kegiatan waiter pada tanggal ini.</div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px;">
                @foreach($activityWaiters as $waiterActivity)
                    <div style="border:1px solid var(--color-border); border-radius: 10px; padding: 12px; background: #ffffff;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom: 8px;">
                            <div>
                                <div style="font-weight:700; color:var(--color-text);">{{ $waiterActivity['waiter_name'] ?? '-' }}</div>
                                <div style="font-size:12px; color:var(--color-text-muted);">{{ $waiterActivity['waiter_email'] ?? '-' }}</div>
                            </div>
                            <span class="badge" style="background:#ede9fe;color:#5b21b6;">{{ $waiterActivity['report_count'] ?? 0 }} laporan</span>
                        </div>

                        @if(empty($waiterActivity['reports']))
                            <div style="font-size: 12px; color: var(--color-text-muted);">Belum ada detail laporan.</div>
                        @else
                            <ul style="margin:0; padding-left:18px; font-size:12px; color:var(--color-text-secondary);">
                                @foreach(array_slice($waiterActivity['reports'], 0, 5) as $report)
                                    <li style="margin-bottom: 8px;">
                                        <div style="font-size:12px; color:var(--color-text-muted); margin-bottom: 2px;">
                                            {{ !empty($report['created_at']) ? date('d/m H:i', (int) $report['created_at']) : '-' }}
                                        </div>
                                        <div style="color:var(--color-text-secondary);">{{ $report['activity_text'] ?? '-' }}</div>
                                        @if(!empty($report['activity_items']))
                                            <div style="font-size:12px; color:#5b21b6; margin-top: 2px;">
                                                {{ implode(' &middot; ', $report['activity_items']) }}
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </details>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 5: Jadwal Task Berulang (closed by default)
    ═══════════════════════════════════════════════════════════════ --}}
    <details class="task-section">
        <summary class="task-section-summary">Jadwal Task Berulang
            <span class="badge">
                @if($isRackScope)
                    {{ $recurringDisplayCount }} kelompok / {{ count($recurringTemplates ?? []) }} template
                @else
                    {{ count($recurringTemplates ?? []) }} template
                @endif
            </span>
        </summary>
        <div class="task-section-body">

        @if(empty($recurringTemplates) || count($recurringTemplates) === 0)
            <div class="empty">Belum ada template task berulang untuk waiter.</div>
        @else
            <div class="task-tools-row">
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    @if($isRackScope)
                        <span class="badge" style="background:var(--color-primary-bg);color:#3730a3;">Kelompok: {{ $recurringDisplayCount }}</span>
                        <span class="badge" style="background:var(--color-info-bg);color:#0c4a6e;">Template: {{ count($recurringTemplates ?? []) }}</span>
                    @else
                        <span class="badge" style="background:var(--color-primary-bg);color:#3730a3;">Harian: {{ $recurringDailyCount }}</span>
                        <span class="badge" style="background:var(--color-info-bg);color:#0c4a6e;">Single: {{ $recurringSingleDelegateCount }}</span>
                    @endif
                    <span class="badge" style="background:var(--color-warning-bg);color:#92400e;">Wajib Foto: {{ $recurringPhotoRequiredCount }}</span>
                </div>
                <div class="task-filter-wrap">
                    <input type="text" class="task-inline-filter js-task-inline-filter"
                        data-target-id="recurring-template-list"
                        data-empty-id="recurring-template-empty"
                        placeholder="{{ $isRackScope ? 'Cari kelompok jadwal...' : 'Cari jadwal: judul, waiter, rak...' }}">
                </div>
            </div>

            <div id="recurring-template-list" class="task-list-grid">
                @if($isRackScope)
                    @foreach($recurringGroupedRackTemplates as $group)
                        @php
                            $template = $group['first'];
                            $templateTypeLabel = $group['type_label'];
                            $templateSearchText = $group['search_text'];
                            $rackNames = $group['rack_names'];
                            $rackPreview = array_slice($rackNames, 0, 4);
                            $rackRemaining = max(0, count($rackNames) - count($rackPreview));
                        @endphp

                        <details class="task-data-card js-task-filter-item" data-filter-text="{{ $templateSearchText }}">
                            <summary class="task-data-card-summary">
                                <div>
                                    <div class="task-data-card-title">{{ $templateTypeLabel }} &middot; {{ $template['schedule_time'] ?? '-' }}</div>
                                    <div class="task-data-card-subtitle">{{ $group['delegate_label'] }} &middot; {{ $group['rack_count'] }} rak &middot; {{ $group['template_count'] }} template</div>
                                </div>
                                <div class="task-data-card-badges">
                                    <span class="badge" style="background:#fff7ed;color:#9a3412;">Cek Rak</span>
                                    <span class="badge" style="background:var(--color-info-bg);color:#0d47a1;">{{ $template['schedule_time'] ?? '-' }}</span>
                                    <span class="badge" style="background:var(--color-warning-bg);color:#856404;">{{ $template['time_limit_minutes'] ?? '-' }} mnt</span>
                                    @if(($template['assignment_strategy'] ?? '') === 'role_round_robin')
                                        <span class="badge" style="background:var(--color-success-bg);color:#166534;">Rolling</span>
                                    @endif
                                </div>
                            </summary>

                            <div class="task-data-card-body">
                                <div class="task-meta-grid">
                                    <div><strong>Pola</strong><br>{{ $templateTypeLabel }}</div>
                                    <div><strong>Delegasi</strong><br>{{ $group['delegate_label'] }}</div>
                                    <div><strong>Total Rak</strong><br>{{ $group['rack_count'] }}</div>
                                    <div><strong>Terakhir Generate</strong><br>{{ !empty($template['last_generated_date']) ? $template['last_generated_date'] : '-' }}</div>
                                </div>

                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                                    @foreach($rackPreview as $rackName)
                                        <span class="badge" style="background:#ffedd5;color:#9a3412;">{{ $rackName }}</span>
                                    @endforeach
                                    @if($rackRemaining > 0)
                                        <span class="badge" style="background:#f1f5f9;color:var(--color-text-secondary);">+{{ $rackRemaining }} lainnya</span>
                                    @endif
                                    @if(!empty($template['requires_photo_proof']))
                                        <span class="badge" style="background:var(--color-info-bg);color:#0369a1;">Bukti foto wajib</span>
                                    @endif
                                </div>

                                {{-- Batch delete --}}
                                <div style="margin-top:12px; padding:10px 14px; background:var(--color-danger-bg); border:1px solid var(--color-danger-border); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                                    <div style="font-size:13px; color:#991b1b;">
                                        Hapus seluruh kelompok (<b>{{ $group['template_count'] }}</b> template, <b>{{ $group['rack_count'] }}</b> rak)
                                    </div>
                                    <form action="{{ route('admin.tasks.recurring.batch_destroy') }}" method="POST"
                                        data-confirm="Yakin hapus SELURUH {{ $group['template_count'] }} template pada jadwal {{ $template['schedule_time'] ?? '-' }}? Aksi ini tidak bisa dibatalkan.">
                                        @csrf
                                        <input type="hidden" name="redirect_scope" value="rack_check">
                                        @foreach($group['templates'] as $templateItem)
                                            <input type="hidden" name="template_ids[]" value="{{ $templateItem['id'] }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus Kelompok</button>
                                    </form>
                                </div>

                                <div style="margin-top:12px; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:10px; background:var(--color-bg);">
                                    <div style="font-weight:600; color:var(--color-text); margin-bottom:8px;">Template per Rak</div>
                                    <div style="display:grid; gap:8px; max-height:260px; overflow:auto; padding-right:4px;">
                                        @foreach($group['templates'] as $templateItem)
                                            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; background:#fff; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:8px 10px;">
                                                <div>
                                                    <div style="font-weight:600; color:var(--color-text);">{{ $templateItem['rack_name'] ?? '-' }}</div>
                                                    <div style="font-size:12px; color:var(--color-text-muted);">ID: {{ $templateItem['id'] ?? '-' }}</div>
                                                </div>
                                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                    <a href="{{ route('admin.tasks.recurring.edit', $templateItem['id']) }}" class="btn btn-primary btn-sm">Edit</a>
                                                    <form action="{{ route('admin.tasks.recurring.destroy', $templateItem['id']) }}" method="POST"
                                                        data-confirm="Yakin hapus template ini?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </details>
                    @endforeach
                @else
                    @foreach($recurringTemplates as $template)
                        @php
                            $templateType = $template['recurrence_type'] ?? 'daily';
                            $templateTypeLabel = $templateType === 'weekly'
                                ? ('Mingguan ' . ($weeklyNames[(int) ($template['weekly_day'] ?? 0)] ?? '-'))
                                : ($templateType === 'every_n_days'
                                    ? ('Setiap ' . ($template['interval_days'] ?? '-') . ' hari')
                                    : 'Harian');
                            $templateSearchText = strtolower(trim(implode(' ', [
                                (string) ($template['title'] ?? ''),
                                (string) ($template['description'] ?? ''),
                                (string) ($template['task_type'] ?? ''),
                                (string) ($template['assigned_waiter_name'] ?? ''),
                                (string) ($template['rack_name'] ?? ''),
                                (string) ($template['schedule_time'] ?? ''),
                                (string) $templateTypeLabel,
                            ])));
                        @endphp

                        <details class="task-data-card js-task-filter-item" data-filter-text="{{ $templateSearchText }}">
                            <summary class="task-data-card-summary">
                                <div>
                                    <div class="task-data-card-title">{{ $template['title'] ?? '-' }}</div>
                                    <div class="task-data-card-subtitle">{{ $template['description'] ?? 'Tanpa deskripsi.' }}</div>
                                </div>
                                <div class="task-data-card-badges">
                                    @if(($template['task_type'] ?? 'general') === 'rack_check')
                                        <span class="badge" style="background:#fff7ed;color:#9a3412;">Cek Rak</span>
                                    @else
                                        <span class="badge" style="background:var(--color-primary-bg);color:#3730a3;">Umum</span>
                                    @endif
                                    <span class="badge" style="background:var(--color-info-bg);color:#0d47a1;">{{ $template['schedule_time'] ?? '-' }}</span>
                                    <span class="badge" style="background:var(--color-warning-bg);color:#856404;">{{ $template['time_limit_minutes'] ?? '-' }} mnt</span>
                                </div>
                            </summary>

                            <div class="task-data-card-body">
                                <div class="task-meta-grid">
                                    <div><strong>Pola</strong><br>{{ $templateTypeLabel }}</div>
                                    <div>
                                        <strong>Delegasi</strong><br>
                                        @if(($template['assignment_type'] ?? 'all') === 'single')
                                            {{ $template['assigned_waiter_name'] ?? '-' }}
                                        @elseif(($template['assignment_type'] ?? 'all') === 'role')
                                            Role: {{ ucfirst((string) ($template['assigned_waiter_role'] ?? 'pelayan')) }}
                                        @else
                                            Semua Waiter
                                        @endif
                                    </div>
                                    <div><strong>Prioritas</strong><br>{{ strtoupper((string) ($template['priority'] ?? 'normal')) }}</div>
                                    <div><strong>Terakhir Generate</strong><br>{{ !empty($template['last_generated_date']) ? $template['last_generated_date'] : '-' }}</div>
                                </div>

                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                                    @if(($template['task_type'] ?? 'general') === 'rack_check')
                                        <span class="badge" style="background:#ffedd5;color:#9a3412;">Rak: {{ $template['rack_name'] ?? '-' }}</span>
                                    @endif
                                    @if(($template['assignment_strategy'] ?? '') === 'role_round_robin')
                                        <span class="badge" style="background:var(--color-success-bg);color:#166534;">Rolling Role</span>
                                    @endif
                                    @if(!empty($template['requires_photo_proof']))
                                        <span class="badge" style="background:var(--color-info-bg);color:#0369a1;">Bukti foto wajib</span>
                                    @endif
                                </div>

                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                                    <a href="{{ route('admin.tasks.recurring.edit', $template['id']) }}" class="btn btn-primary btn-sm">Edit</a>
                                    <form action="{{ route('admin.tasks.recurring.destroy', $template['id']) }}" method="POST"
                                        data-confirm="Yakin hapus template task berulang ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    @endforeach
                @endif
            </div>

            <div id="recurring-template-empty" class="task-empty-filtered">
                Tidak ada template yang cocok dengan kata kunci pencarian.
            </div>
        @endif
        </div>
    </details>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 6: Performa Waiter (general scope only)
    ═══════════════════════════════════════════════════════════════ --}}
    @if(!$isRackScope)
    <details class="task-section">
        <summary class="task-section-summary">Performa Waiter <span class="badge">Ranking penyelesaian</span></summary>
        <div class="task-section-body">
        @if(empty($waiterPerformance))
            <div class="empty">Belum ada data penyelesaian tugas waiter.</div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Peringkat</th>
                            <th>Nama Waiter</th>
                            <th>Total Selesai</th>
                            <th>Terakhir Mengerjakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($waiterPerformance as $idx => $stat)
                            <tr>
                                <td>#{{ $idx + 1 }}</td>
                                <td>{{ $stat['name'] }}</td>
                                <td>{{ $stat['done_count'] }}</td>
                                <td>{{ !empty($stat['last_done_at']) ? date('d/m/Y H:i', $stat['last_done_at']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </details>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 7: Daftar Waiter Aktif (closed by default)
    ═══════════════════════════════════════════════════════════════ --}}
    <details class="task-section">
        <summary class="task-section-summary">Daftar Waiter Aktif <span class="badge">{{ count($waiters ?? []) }}</span></summary>
        <div class="task-section-body">
            @if(empty($waiters) || count($waiters) === 0)
                <div class="empty">Belum ada waiter aktif. Tambahkan dulu dari menu Waiters.</div>
            @else
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    @foreach($waiters as $waiter)
                        <span class="badge" style="background: var(--color-primary-bg); color: #304087; padding: 8px 12px; border-radius: 999px;">
                            {{ $waiter['name'] ?? '-' }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </details>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 8: Ringkasan Rak (rack_check, closed by default)
    ═══════════════════════════════════════════════════════════════ --}}
    @if($isRackScope)
    <details class="task-section">
        <summary class="task-section-summary">Ringkasan Rak <span class="badge">{{ $kpi['activeRackCount'] }} aktif</span></summary>
        <div class="task-section-body">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <span class="badge" style="background: #fff7ed; color: #9a3412; padding: 8px 12px; border-radius: 999px;">
                    Total: {{ count($racks ?? []) }}
                </span>
                <span class="badge" style="background: var(--color-success-bg); color: #166534; padding: 8px 12px; border-radius: 999px;">
                    Aktif: {{ $kpi['activeRackCount'] }}
                </span>
            </div>
        </div>
    </details>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 9: Riwayat Tugas Waiter (closed by default)
    ═══════════════════════════════════════════════════════════════ --}}
    <details class="task-section">
        <summary class="task-section-summary">Riwayat Tugas Waiter <span class="badge">{{ count($taskHistory ?? []) }} data</span></summary>
        <div class="task-section-body">
            <div style="font-size:12px; color:var(--color-text-muted); margin-bottom:12px;">
                Filter dan telusuri riwayat tugas per waiter. Klik nama waiter untuk melihat detail.
            </div>

            {{-- Filter Bar --}}
            <div class="history-filter-bar">
                <div class="history-filter-group">
                    <label class="history-filter-label" for="historyFilterWaiter">Waiter</label>
                    <select id="historyFilterWaiter" class="history-filter-select">
                        <option value="">Semua Waiter</option>
                        @foreach($historyWaiterNames as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="history-filter-group">
                    <label class="history-filter-label" for="historyFilterRack">Rak</label>
                    <select id="historyFilterRack" class="history-filter-select">
                        <option value="">Semua Rak</option>
                        @foreach($historyRackNames as $rackName)
                            <option value="{{ $rackName }}">{{ $rackName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="history-filter-group">
                    <label class="history-filter-label" for="historyFilterDateFrom">Dari Tanggal</label>
                    <input type="date" id="historyFilterDateFrom" class="history-filter-select">
                </div>
                <div class="history-filter-group">
                    <label class="history-filter-label" for="historyFilterDateTo">Sampai Tanggal</label>
                    <input type="date" id="historyFilterDateTo" class="history-filter-select">
                </div>
                <div class="history-filter-group" style="align-self: flex-end;">
                    <button type="button" id="historyFilterReset" class="history-filter-reset-btn">Reset Filter</button>
                </div>
            </div>

            <div id="historyFilterSummary" class="history-filter-summary"></div>

            {{-- Grouped by Waiter --}}
            <div id="historyWaiterList">
                @forelse($historyByWaiter as $waiterName => $waiterTasks)
                    @php
                        $doneCount = $waiterTasks->where('status', 'done')->count();
                        $overdueCount = $waiterTasks->where('status', 'overdue')->count();
                        $pendingCount = $waiterTasks->where('status', 'pending')->count();
                    @endphp
                    <details class="history-waiter-card js-history-waiter-card"
                             data-waiter-name="{{ $waiterName }}"
                             data-task-count="{{ $waiterTasks->count() }}">
                        <summary class="history-waiter-header">
                            <div class="history-waiter-info">
                                <div class="history-waiter-avatar">{{ mb_strtoupper(mb_substr($waiterName, 0, 1)) }}</div>
                                <div>
                                    <div class="history-waiter-name">{{ $waiterName }}</div>
                                    <div class="history-waiter-count">{{ $waiterTasks->count() }} tugas total</div>
                                </div>
                            </div>
                            <div class="history-waiter-badges">
                                @if($doneCount > 0)
                                    <span class="badge badge-success">{{ $doneCount }} done</span>
                                @endif
                                @if($overdueCount > 0)
                                    <span class="badge" style="background:var(--color-danger-bg);color:#721c24;">{{ $overdueCount }} miss</span>
                                @endif
                                @if($pendingCount > 0)
                                    <span class="badge" style="background:var(--color-warning-bg);color:#856404;">{{ $pendingCount }} pending</span>
                                @endif
                            </div>
                        </summary>
                        <div class="history-waiter-body">
                            @foreach($waiterTasks->sortByDesc(function($t) { return $t['created_at'] ?? 0; }) as $task)
                                <div class="history-task-row js-history-task-row"
                                     data-rack-name="{{ $task['rack_name'] ?? '' }}"
                                     data-completed-date="{{ !empty($task['completed_at']) ? date('Y-m-d', (int) $task['completed_at']) : '' }}"
                                     data-tracking-date="{{ $task['tracking_date'] ?? '' }}"
                                     data-status="{{ $task['status'] ?? 'pending' }}"
                                     data-category-id="{{ $task['category_id'] ?? 'uncategorized' }}">
                                    <div class="history-task-main">
                                        <div class="history-task-title-row">
                                            <span class="history-task-title">{{ $task['title'] ?? '-' }}</span>
                                            @if(($task['status'] ?? '') === 'done')
                                                <span class="badge badge-success" style="font-size:12px;">Selesai</span>
                                            @elseif(($task['status'] ?? '') === 'in_progress')
                                                <span class="badge" style="background:#ecfdf5;color:#065f46;font-size:12px;">{{ ($task['completed_count'] ?? 0) }}/{{ ($task['repeat_count'] ?? 1) }}</span>
                                            @elseif(($task['status'] ?? '') === 'overdue')
                                                <span class="badge" style="background:var(--color-danger-bg);color:#721c24;font-size:12px;">Tidak Selesai</span>
                                            @else
                                                <span class="badge" style="background:var(--color-warning-bg);color:#856404;font-size:12px;">Pending</span>
                                            @endif
                                            @if(($task['repeat_count'] ?? 1) > 1 && ($task['status'] ?? '') !== 'in_progress')
                                                <span style="font-size:11px;color:#6366f1;font-weight:600;">🔄 {{ $task['repeat_count'] }}x</span>
                                            @endif
                                        </div>
                                        @if(!empty($task['description']))
                                            <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;">{{ $task['description'] }}</div>
                                        @endif
                                    </div>
                                    <div class="history-task-meta">
                                        @if(($task['task_type'] ?? 'general') === 'rack_check')
                                            <div class="history-meta-item">
                                                <span class="history-meta-label">Rak</span>
                                                <span class="badge" style="background:#fff7ed;color:#9a3412;font-size:12px;">{{ $task['rack_name'] ?? '-' }}</span>
                                            </div>
                                        @endif
                                        @if(!empty($task['completed_by_waiter_name']))
                                            <div class="history-meta-item">
                                                <span class="history-meta-label">Diverifikasi</span>
                                                <span style="font-size:12px;color:var(--color-text-secondary);">{{ $task['completed_by_waiter_name'] }}</span>
                                            </div>
                                        @endif
                                        <div class="history-meta-item">
                                            <span class="history-meta-label">Tracking</span>
                                            <span style="font-size:12px;color:var(--color-text-secondary);">{{ $task['tracking_date'] ?? '-' }}</span>
                                        </div>
                                        <div class="history-meta-item">
                                            <span class="history-meta-label">Dibuat</span>
                                            <span style="font-size:12px;color:var(--color-text-secondary);">{{ !empty($task['created_at']) ? date('d/m/Y H:i', (int) $task['created_at']) : '-' }}</span>
                                        </div>
                                        @if(!empty($task['completed_at']))
                                            <div class="history-meta-item">
                                                <span class="history-meta-label">Selesai</span>
                                                <span style="font-size:12px;color:#166534;font-weight:600;">{{ date('d/m/Y H:i', (int) $task['completed_at']) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="history-task-extras">
                                        @if(($task['task_type'] ?? 'general') === 'rack_check')
                                            @if(!empty($task['completed_no_out_of_stock']))
                                                <span class="badge" style="background:var(--color-success-bg);color:#166534;font-size:12px;">Stok lengkap</span>
                                            @elseif(!empty($task['completed_stock_report']))
                                                <span style="font-size:12px;color:#9a3412;">{{ $task['completed_stock_report'] }}</span>
                                            @endif
                                        @endif
                                        @if(!empty($task['completed_photo_proof_url']))
                                            <button type="button" class="js-task-photo-view history-photo-btn"
                                                data-photo-url="{{ $task['completed_photo_proof_url'] }}"
                                                data-photo-size="{{ (int) ($task['completed_photo_proof_size_bytes'] ?? 0) }}"
                                                data-photo-mime="{{ $task['completed_photo_proof_mime_type'] ?? '' }}">
                                                Lihat Foto
                                                @if(!empty($task['completed_photo_proof_size_bytes']))
                                                    <span style="font-weight:400;opacity:0.7;"> &middot; {{ number_format(((int) $task['completed_photo_proof_size_bytes']) / 1024, 1) }} KB</span>
                                                @endif
                                            </button>
                                        @elseif(!empty($task['requires_photo_proof']))
                                            <span style="font-size:12px;color:#9a3412;">(wajib foto — belum ada)</span>
                                        @endif
                                        <form action="{{ route('admin.tasks.destroy', $task['id']) }}" method="POST"
                                            data-confirm="Yakin hapus tugas ini?" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="empty">Belum ada riwayat tugas waiter.</div>
                @endforelse
            </div>

            <div id="historyEmptyFiltered" class="task-empty-filtered">
                Tidak ada tugas yang cocok dengan filter yang dipilih.
            </div>
        </div>
    </details>

    {{-- ═══════════════════════════════════════════════════════════════
         RESET DATA (Two-step confirmation, only for rack_check)
    ═══════════════════════════════════════════════════════════════ --}}
    @if($isRackScope)
    <details class="task-section" style="border-color: var(--color-danger-border);">
        <summary class="task-section-summary" style="color: var(--color-danger);">
            Zona Berbahaya
            <span class="badge" style="background:var(--color-danger-bg);color:#991b1b;">Hati-hati</span>
        </summary>
        <div class="task-section-body">
            <div style="padding:14px; background:var(--color-danger-bg); border:1px solid var(--color-danger-border); border-radius:var(--radius-md);">
                <div style="font-weight:700; color:#991b1b; margin-bottom:8px;">Reset Semua Data Cek Rak</div>
                <div style="font-size:13px; color:var(--color-text-secondary); margin-bottom:12px;">
                    Tindakan ini akan menghapus <strong>seluruh task cek rak</strong> (pending/done/overdue) dan <strong>semua template berulang cek rak</strong>. Tidak bisa dibatalkan.
                </div>
                <button type="button" id="btnResetRackData" class="btn btn-danger">Reset Data Cek Rak</button>
            </div>
        </div>
    </details>

    {{-- Two-step confirmation overlay --}}
    <div id="resetConfirmOverlay" class="confirm-overlay" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="confirm-box">
            <h3>Konfirmasi Reset Data</h3>
            <p>Tindakan ini akan menghapus semua task cek rak dan template berulang. Ketik <strong>RESET</strong> untuk mengkonfirmasi.</p>
            <input type="text" id="resetConfirmInput" class="confirm-input" placeholder="Ketik RESET" autocomplete="off">
            <div class="confirm-actions">
                <button type="button" id="resetConfirmCancel" class="btn btn-secondary">Batal</button>
                <form method="POST" action="{{ route('admin.tasks.rack.reset') }}" id="resetForm">
                    @csrf
                    <button type="submit" id="resetConfirmSubmit" class="btn btn-danger" disabled>Hapus Semua</button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Photo Modal --}}
    <div id="task-photo-modal" class="task-photo-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="task-photo-modal-box">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
                <strong>Preview Bukti Foto</strong>
                <button type="button" id="task-photo-modal-close" class="btn btn-danger btn-sm">Tutup</button>
            </div>
            <img id="task-photo-modal-image" class="task-photo-modal-image" src="" alt="Preview bukti foto">
            <div id="task-photo-modal-meta" class="task-photo-modal-meta"></div>
        </div>
    </div>

    {{-- Category Modal --}}
    <div id="category-modal" class="category-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="category-modal-box">
            <div class="category-modal-header">
                <div class="category-modal-title">Kelola Kategori Tugas</div>
                <button type="button" class="category-modal-close" onclick="closeCategoryModal()">&times;</button>
            </div>
            <div class="category-modal-body">
                <div id="category-modal-message"></div>
                <div class="category-list" id="category-list-container">
                    <div style="text-align:center; padding: 20px; color: var(--color-text-muted);">Memuat data...</div>
                </div>

                <form id="category-add-form" class="category-form" onsubmit="submitCategoryAdd(event)">
                    <div style="font-weight: 600; color: var(--color-text);">Tambah Kategori Baru</div>
                    <div class="category-form-group">
                        <input type="text" id="category-new-name" class="category-form-input" placeholder="Nama Kategori (mis. Area Depan)" required>
                        <input type="color" id="category-new-color" class="category-form-color" value="#3b82f6" title="Warna Kategori">
                    </div>
                    <div class="category-form-group">
                        <input type="number" id="category-new-order" class="category-form-input" placeholder="Urutan (opsional)" style="max-width: 100px;">
                        <button type="submit" class="btn btn-primary" id="category-add-btn" style="flex: 1;">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- Scan Compliance Stats (rack_check scope only) --}}
    @if($isRackScope && !empty($scanStats))
    <details class="task-section" open>
        <summary class="task-section-summary">📊 Statistik Kepatuhan Scan <span class="badge" style="background:#dbeafe;color:#1d4ed8;">{{ count($scanStats) }} Waiter</span></summary>
        <div class="task-section-body">
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th style="padding:10px 12px; text-align:left;">Waiter</th>
                            <th style="padding:10px 12px; text-align:center;">Total Scan</th>
                            <th style="padding:10px 12px; text-align:center;">Berhasil</th>
                            <th style="padding:10px 12px; text-align:center;">Mismatch</th>
                            <th style="padding:10px 12px; text-align:center;">Akurasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scanStats as $wId => $stat)
                        @php
                            $waiterName = collect($waiters)->firstWhere('id', $wId)['name'] ?? $wId;
                            $accuracy = $stat['total'] > 0 ? round(($stat['success'] / $stat['total']) * 100) : 100;
                            $accColor = $accuracy >= 90 ? '#166534' : ($accuracy >= 70 ? '#92400e' : '#991b1b');
                            $accBg = $accuracy >= 90 ? '#f0fdf4' : ($accuracy >= 70 ? '#fef3c7' : '#fef2f2');
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px 12px; font-weight:600;">{{ $waiterName }}</td>
                            <td style="padding:10px 12px; text-align:center;">{{ $stat['total'] }}</td>
                            <td style="padding:10px 12px; text-align:center; color:#166534;">{{ $stat['success'] }}</td>
                            <td style="padding:10px 12px; text-align:center; color:#991b1b; font-weight:{{ $stat['mismatch'] > 0 ? '700' : '400' }};">{{ $stat['mismatch'] }}</td>
                            <td style="padding:10px 12px; text-align:center;">
                                <span style="display:inline-block; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700; background:{{ $accBg }}; color:{{ $accColor }};">{{ $accuracy }}%</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </details>
    @endif

    {{-- Export Stock Modal --}}
    @if($isRackScope)
    <div id="export-stock-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; padding:24px; max-width:380px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:18px;">📥 Export Laporan Stok</h3>
                <button type="button" onclick="closeExportStockModal()" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
            </div>
            <form action="{{ route('admin.tasks.export_stock') }}" method="GET">
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Dari Tanggal</label>
                    <input type="date" name="from_date" value="{{ now()->subDays(7)->format('Y-m-d') }}" required style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Sampai Tanggal</label>
                    <input type="date" name="to_date" value="{{ now()->format('Y-m-d') }}" required style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px;">
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="background:#e2e8f0;" onclick="closeExportStockModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">📥 Download Excel</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Reassign Modal --}}
    <div id="reassign-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; padding:24px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:18px;">🔄 Bulk Reassign Tugas</h3>
                <button type="button" onclick="closeReassignModal()" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
            </div>
            <p style="font-size:13px; color:var(--color-text-muted); margin-bottom:16px;">Pindahkan semua tugas pending dari waiter yang libur ke waiter lain.</p>
            <div style="margin-bottom:12px;">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Dari Waiter:</label>
                <select id="reassign-from" class="form-input" style="width:100%;">
                    <option value="">-- Pilih waiter asal --</option>
                    @foreach($waiters ?? [] as $w)
                        <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Ke Waiter:</label>
                <select id="reassign-to" class="form-input" style="width:100%;">
                    <option value="">-- Pilih waiter tujuan --</option>
                    @foreach($waiters ?? [] as $w)
                        <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Tanggal:</label>
                <input type="date" id="reassign-date" class="form-input" style="width:100%;" value="{{ $selectedDate ?? date('Y-m-d') }}">
            </div>
            <div id="reassign-result" style="display:none; margin-bottom:12px; padding:8px 12px; border-radius:6px; font-size:13px;"></div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn" style="background:#e2e8f0;" onclick="closeReassignModal()">Batal</button>
                <button type="button" class="btn btn-primary" id="reassign-submit-btn" onclick="submitReassign()">Pindahkan</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Delegated confirm handler ──
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var confirmMsg = form.getAttribute('data-confirm');
        if (confirmMsg && !confirm(confirmMsg)) {
            e.preventDefault();
        }
    });

    // ── Photo Modal ──
    var modal = document.getElementById('task-photo-modal');
    var imageEl = document.getElementById('task-photo-modal-image');
    var metaEl = document.getElementById('task-photo-modal-meta');
    var closeBtn = document.getElementById('task-photo-modal-close');

    function formatBytes(bytes) {
        var value = Number(bytes || 0);
        if (!Number.isFinite(value) || value <= 0) return '0 B';
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        return (value / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        if (imageEl) imageEl.src = '';
        if (metaEl) metaEl.textContent = '';
    }

    function openModal(url, sizeBytes, mimeType) {
        if (!modal || !imageEl) return;
        imageEl.src = url;
        var normalizedMime = String(mimeType || 'image/*').trim() || 'image/*';
        var extra = Number(sizeBytes || 0) > 0 ? ' \u00b7 ' + formatBytes(sizeBytes) : '';
        if (metaEl) metaEl.textContent = 'Format: ' + normalizedMime + extra;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.js-task-photo-view');
        if (trigger) {
            var photoUrl = String(trigger.getAttribute('data-photo-url') || '').trim();
            if (!photoUrl) return;
            openModal(photoUrl, Number(trigger.getAttribute('data-photo-size') || 0), trigger.getAttribute('data-photo-mime') || '');
            return;
        }
        if (e.target === modal) closeModal();
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') closeModal();
    });

    // ── Inline Filters ──
    var filterInputs = document.querySelectorAll('.js-task-inline-filter');
    filterInputs.forEach(function (input) {
        var targetId = input.getAttribute('data-target-id') || '';
        var emptyId = input.getAttribute('data-empty-id') || '';
        if (!targetId) return;

        var container = document.getElementById(targetId);
        var emptyState = emptyId ? document.getElementById(emptyId) : null;
        if (!container) return;

        function applyFilter() {
            var keyword = (input.value || '').trim().toLowerCase();
            var items = container.querySelectorAll('.js-task-filter-item');
            var visibleCount = 0;

            items.forEach(function (item) {
                var haystack = (item.getAttribute('data-filter-text') || '').toLowerCase();
                var isVisible = keyword === '' || haystack.indexOf(keyword) !== -1;
                item.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        input.addEventListener('input', applyFilter);
        applyFilter();
    });

    // ── History Filters ──
    (function () {
        var filterWaiter = document.getElementById('historyFilterWaiter');
        var filterRack = document.getElementById('historyFilterRack');
        var filterDateFrom = document.getElementById('historyFilterDateFrom');
        var filterDateTo = document.getElementById('historyFilterDateTo');
        var resetBtn = document.getElementById('historyFilterReset');
        var summaryEl = document.getElementById('historyFilterSummary');
        var emptyEl = document.getElementById('historyEmptyFiltered');
        var waiterCards = document.querySelectorAll('.js-history-waiter-card');

        if (!filterWaiter || !waiterCards.length) return;

        function applyHistoryFilters() {
            var selectedWaiter = (filterWaiter.value || '').trim();
            var selectedRack = (filterRack.value || '').trim().toLowerCase();
            var dateFrom = filterDateFrom.value || '';
            var dateTo = filterDateTo.value || '';
            var hasAnyFilter = selectedWaiter || selectedRack || dateFrom || dateTo;

            var totalVisible = 0;
            var totalShown = 0;

            waiterCards.forEach(function (card) {
                var waiterName = card.getAttribute('data-waiter-name') || '';
                if (selectedWaiter && waiterName !== selectedWaiter) {
                    card.style.display = 'none';
                    return;
                }

                card.style.display = '';
                var rows = card.querySelectorAll('.js-history-task-row');
                var visibleInCard = 0;

                rows.forEach(function (row) {
                    var rackName = (row.getAttribute('data-rack-name') || '').toLowerCase();
                    var completedDate = row.getAttribute('data-completed-date') || '';
                    var trackingDate = row.getAttribute('data-tracking-date') || '';
                    var rackMatch = !selectedRack || rackName.indexOf(selectedRack) !== -1;
                    var dateMatch = true;
                    var effectiveDate = completedDate || trackingDate;

                    if (dateFrom && effectiveDate) dateMatch = dateMatch && effectiveDate >= dateFrom;
                    else if (dateFrom && !effectiveDate) dateMatch = false;
                    if (dateTo && effectiveDate) dateMatch = dateMatch && effectiveDate <= dateTo;
                    else if (dateTo && !effectiveDate) dateMatch = false;

                    if (rackMatch && dateMatch) {
                        row.style.display = '';
                        visibleInCard++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                var countEl = card.querySelector('.history-waiter-count');
                if (countEl) {
                    countEl.textContent = hasAnyFilter
                        ? visibleInCard + ' dari ' + rows.length + ' tugas'
                        : rows.length + ' tugas total';
                }

                if (visibleInCard === 0 && (selectedRack || dateFrom || dateTo)) {
                    card.style.display = 'none';
                } else {
                    totalVisible++;
                    totalShown += visibleInCard;
                }
            });

            if (emptyEl) emptyEl.style.display = (hasAnyFilter && totalVisible === 0) ? 'block' : 'none';

            if (summaryEl) {
                if (hasAnyFilter) {
                    var parts = [];
                    if (selectedWaiter) parts.push('Waiter: <strong>' + selectedWaiter + '</strong>');
                    if (selectedRack) parts.push('Rak: <strong>' + filterRack.value + '</strong>');
                    if (dateFrom) parts.push('Dari: <strong>' + dateFrom + '</strong>');
                    if (dateTo) parts.push('Sampai: <strong>' + dateTo + '</strong>');
                    summaryEl.innerHTML = parts.join(' &middot; ') + ' \u2014 <strong>' + totalShown + '</strong> tugas ditemukan';
                    summaryEl.style.display = 'block';
                } else {
                    summaryEl.style.display = 'none';
                }
            }
        }

        filterWaiter.addEventListener('change', applyHistoryFilters);
        filterRack.addEventListener('change', applyHistoryFilters);
        filterDateFrom.addEventListener('change', applyHistoryFilters);
        filterDateTo.addEventListener('change', applyHistoryFilters);

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                filterWaiter.value = '';
                filterRack.value = '';
                filterDateFrom.value = '';
                filterDateTo.value = '';
                applyHistoryFilters();
            });
        }
    })();

    // ── Two-step Reset Confirmation ──
    (function () {
        var btnReset = document.getElementById('btnResetRackData');
        var overlay = document.getElementById('resetConfirmOverlay');
        var input = document.getElementById('resetConfirmInput');
        var cancelBtn = document.getElementById('resetConfirmCancel');
        var submitBtn = document.getElementById('resetConfirmSubmit');

        if (!btnReset || !overlay) return;

        btnReset.addEventListener('click', function () {
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            if (input) { input.value = ''; input.focus(); }
            if (submitBtn) submitBtn.disabled = true;
        });

        function closeOverlay() {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            if (input) input.value = '';
            if (submitBtn) submitBtn.disabled = true;
        }

        if (cancelBtn) cancelBtn.addEventListener('click', closeOverlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeOverlay();
        });

        if (input && submitBtn) {
            input.addEventListener('input', function () {
                submitBtn.disabled = input.value.trim() !== 'RESET';
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeOverlay();
        });
    })();

    // Kategori functions
    window.filterTasksByCategory = function(categoryId) {
        const tasks = document.querySelectorAll('.js-history-task-row');
        tasks.forEach(row => {
            if (!categoryId) {
                row.style.display = '';
            } else {
                const rowCatId = row.getAttribute('data-category-id') || 'uncategorized';
                row.style.display = rowCatId === categoryId ? '' : 'none';
            }
        });
    };

    window.openCategoryModal = function() {
        document.getElementById('category-modal').classList.add('show');
        loadCategories();
    };

    window.closeCategoryModal = function() {
        document.getElementById('category-modal').classList.remove('show');
    };

    function renderCategories(categories) {
        const container = document.getElementById('category-list-container');
        if (!categories || categories.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--color-text-muted);">Belum ada kategori.</div>';
            return;
        }
        
        let html = '';
        categories.forEach(cat => {
            html += `
                <div class="category-item">
                    <div class="category-item-info">
                        <span class="task-category-dot" style="background-color: ${cat.color || '#94a3b8'}; margin: 0;"></span>
                        <span style="font-weight: 600; color: var(--color-text);">${cat.name}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCategory('${cat.id}')">Hapus</button>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function showCategoryMessage(msg, isError = false) {
        const msgEl = document.getElementById('category-modal-message');
        msgEl.innerHTML = `<div style="padding: 10px; margin-bottom: 15px; border-radius: 6px; background-color: ${isError ? '#fef2f2' : '#f0fdf4'}; color: ${isError ? '#991b1b' : '#166534'}; border: 1px solid ${isError ? '#fecaca' : '#bbf7d0'};">${msg}</div>`;
        setTimeout(() => { msgEl.innerHTML = ''; }, 3000);
    }

    function loadCategories() {
        const container = document.getElementById('category-list-container');
        container.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--color-text-muted);">Memuat data...</div>';
        
        fetch('{{ url("admin/tasks/categories") }}', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.categories) {
                renderCategories(data.categories);
            } else {
                container.innerHTML = '<div style="text-align:center; padding: 20px; color: #991b1b;">Gagal memuat kategori.</div>';
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div style="text-align:center; padding: 20px; color: #991b1b;">Terjadi kesalahan sistem.</div>';
        });
    }

    window.submitCategoryAdd = function(e) {
        e.preventDefault();
        const btn = document.getElementById('category-add-btn');
        const name = document.getElementById('category-new-name').value;
        const color = document.getElementById('category-new-color').value;
        const order = document.getElementById('category-new-order').value || 0;
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        btn.disabled = true;
        btn.innerText = 'Memproses...';
        
        fetch('{{ url("admin/tasks/categories") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ name, color, order })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerText = 'Tambah';
            if (data.success) {
                document.getElementById('category-new-name').value = '';
                document.getElementById('category-new-order').value = '';
                showCategoryMessage('Kategori berhasil ditambahkan.');
                loadCategories();
                setTimeout(() => { window.location.reload(); }, 1500); // Reload to reflect changes in filter dropdown
            } else {
                showCategoryMessage(data.message || 'Gagal menambahkan kategori.', true);
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerText = 'Tambah';
            showCategoryMessage('Terjadi kesalahan sistem.', true);
        });
    };

    window.deleteCategory = function(id) {
        if (!confirm('Yakin ingin menghapus kategori ini?')) return;
        
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch(`{{ url("admin/tasks/categories") }}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showCategoryMessage('Kategori berhasil dihapus.');
                loadCategories();
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                showCategoryMessage(data.message || 'Gagal menghapus kategori.', true);
            }
        })
        .catch(err => {
            console.error(err);
            showCategoryMessage('Terjadi kesalahan sistem.', true);
        });
    };

    window.filterTrackingByCategory = function() {
        const val = document.getElementById('tracking-category-filter')?.value || '';
        if (typeof filterTasksByCategory === 'function') {
            filterTasksByCategory(val);
        }
    };

    // ════════════════════════════════════════════════════
    //  TRACKING SECTION (REDESIGNED) — interactivity
    // ════════════════════════════════════════════════════
    (function () {
        const wrap = document.querySelector('.trk-wrap');
        if (!wrap) return;

        const list = wrap.querySelector('[data-trk-view-pane="task"]');
        const rows = list ? Array.from(list.querySelectorAll('.js-trk-row')) : [];
        const emptyFilter = list ? list.querySelector('.js-trk-empty-filter') : null;
        const searchInput = wrap.querySelector('#trkSearch');
        const statusTabs = Array.from(wrap.querySelectorAll('.trk-status-tab'));
        const catChips = Array.from(wrap.querySelectorAll('.trk-cat-chip'));
        const viewBtns = Array.from(wrap.querySelectorAll('.trk-view-btn'));
        const taskPane = wrap.querySelector('[data-trk-view-pane="task"]');
        const waiterPane = wrap.querySelector('[data-trk-view-pane="waiter"]');

        let currentStatus = 'all';
        let currentCat = '';
        let currentSearch = '';

        function applyFilter() {
            if (!list) return;
            let visible = 0;
            const search = currentSearch.toLowerCase().trim();
            rows.forEach((row) => {
                const status = row.getAttribute('data-trk-status') || 'pending';
                const cat = row.getAttribute('data-trk-cat') || 'uncategorized';
                const haystack = row.getAttribute('data-trk-search') || '';
                let show = true;
                if (currentStatus !== 'all' && status !== currentStatus) show = false;
                if (currentCat && cat !== currentCat) show = false;
                if (search && !haystack.includes(search)) show = false;
                row.style.display = show ? '' : 'none';
                // Hide its sibling detail pane too
                const rowId = row.getAttribute('data-trk-row');
                const detail = list.querySelector(`[data-trk-detail-for="${rowId}"]`);
                if (detail) {
                    if (!show) {
                        detail.style.display = 'none';
                        row.classList.remove('is-expanded');
                    }
                }
                if (show) visible++;
            });
            if (emptyFilter) {
                emptyFilter.style.display = visible === 0 && rows.length > 0 ? 'block' : 'none';
            }
        }

        // Status tabs
        statusTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                statusTabs.forEach((t) => {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
                currentStatus = tab.getAttribute('data-trk-status') || 'all';
                applyFilter();
            });
        });

        // Category chips
        catChips.forEach((chip) => {
            chip.addEventListener('click', () => {
                catChips.forEach((c) => c.classList.remove('is-active'));
                chip.classList.add('is-active');
                currentCat = chip.getAttribute('data-trk-cat') || '';
                applyFilter();
            });
        });

        // Search debounced
        let searchTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    currentSearch = e.target.value || '';
                    applyFilter();
                }, 150);
            });
        }

        // View toggle (rack scope only)
        viewBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                viewBtns.forEach((b) => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                const view = btn.getAttribute('data-trk-view') || 'task';
                if (taskPane) taskPane.style.display = view === 'task' ? '' : 'none';
                if (waiterPane) waiterPane.style.display = view === 'waiter' ? '' : 'none';
            });
        });
    })();

    // Toggle detail expand
    window.trkToggleDetail = function (rowEl) {
        if (!rowEl) return;
        const rowId = rowEl.getAttribute('data-trk-row');
        if (!rowId) return;
        const detail = document.querySelector(`[data-trk-detail-for="${rowId}"]`);
        if (!detail) return;
        const isOpen = rowEl.classList.toggle('is-expanded');
        detail.style.display = isOpen ? 'block' : 'none';
    };

    // Photo lightbox (reuses existing task-photo-modal if present, else simple overlay)
    window.trkOpenPhoto = function (url, label) {
        if (!url) return;
        // Try existing modal first
        const existingModal = document.getElementById('task-photo-modal');
        const existingImg = document.getElementById('task-photo-modal-img');
        const existingTitle = document.getElementById('task-photo-modal-title');
        if (existingModal && existingImg) {
            existingImg.src = url;
            if (existingTitle) existingTitle.textContent = label || 'Foto Bukti';
            existingModal.style.display = 'flex';
            return;
        }
        // Fallback: build simple overlay
        let overlay = document.getElementById('trk-photo-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'trk-photo-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;cursor:zoom-out;';
            overlay.innerHTML = '<img id="trk-photo-overlay-img" style="max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">';
            overlay.addEventListener('click', () => { overlay.style.display = 'none'; });
            document.body.appendChild(overlay);
        }
        document.getElementById('trk-photo-overlay-img').src = url;
        overlay.style.display = 'flex';
    };

    // ── Export Stock Modal ──
    window.openExportStockModal = function() {
        const modal = document.getElementById('export-stock-modal');
        if (modal) modal.style.display = 'flex';
    };
    window.closeExportStockModal = function() {
        const modal = document.getElementById('export-stock-modal');
        if (modal) modal.style.display = 'none';
    };

    // ── Reassign Modal ──
    window.openReassignModal = function() {
        const modal = document.getElementById('reassign-modal');
        modal.style.display = 'flex';
        document.getElementById('reassign-result').style.display = 'none';
    };

    window.closeReassignModal = function() {
        document.getElementById('reassign-modal').style.display = 'none';
    };

    window.submitReassign = function() {
        const fromId = document.getElementById('reassign-from').value;
        const toId = document.getElementById('reassign-to').value;
        const date = document.getElementById('reassign-date').value;
        const resultEl = document.getElementById('reassign-result');
        const btn = document.getElementById('reassign-submit-btn');

        if (!fromId || !toId || !date) {
            resultEl.style.display = 'block';
            resultEl.style.background = '#fef2f2';
            resultEl.style.color = '#991b1b';
            resultEl.textContent = 'Lengkapi semua field.';
            return;
        }
        if (fromId === toId) {
            resultEl.style.display = 'block';
            resultEl.style.background = '#fef2f2';
            resultEl.style.color = '#991b1b';
            resultEl.textContent = 'Waiter asal dan tujuan harus berbeda.';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Memproses...';
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('{{ route("admin.tasks.bulk_reassign") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ from_waiter_id: fromId, to_waiter_id: toId, date: date })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Pindahkan';
            resultEl.style.display = 'block';
            if (data.success) {
                resultEl.style.background = '#f0fdf4';
                resultEl.style.color = '#166534';
                resultEl.textContent = data.message;
                if (data.reassigned_count > 0) {
                    setTimeout(() => { window.location.reload(); }, 1500);
                }
            } else {
                resultEl.style.background = '#fef2f2';
                resultEl.style.color = '#991b1b';
                resultEl.textContent = data.message || 'Gagal memproses.';
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.textContent = 'Pindahkan';
            resultEl.style.display = 'block';
            resultEl.style.background = '#fef2f2';
            resultEl.style.color = '#991b1b';
            resultEl.textContent = 'Terjadi kesalahan sistem.';
        });
    };

})();
</script>
@endpush
