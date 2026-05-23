@extends('admin.layout')

@section('title', 'Daftar Restock & Buat PO - Admin')

@section('content')
<style>
    .rs-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .rs-page-title { margin: 0; color: #1e293b; font-size: clamp(24px, 5vw, 32px); font-weight: 800; }
    .rs-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 24px; }
    .rs-kpi-card { background: white; border-radius: var(--radius-md); padding: 15px; box-shadow: var(--shadow-sm); }
    .rs-kpi-label { color: var(--color-text-secondary); font-size: 13px; margin-bottom: 4px; }
    .rs-kpi-value { font-size: 24px; font-weight: bold; color: var(--color-text); }
    .rs-section { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 24px; }
    .rs-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
    .rs-section-title { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0; }
    .rs-filter-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .rs-category-group { margin-bottom: 12px; }
    .rs-category-group summary { font-weight: 600; font-size: 15px; color: #334155; cursor: pointer; padding: 8px 12px; background: #f8fafc; border-radius: var(--radius-sm); border: 1px solid var(--color-border); user-select: none; }
    .rs-category-group summary:hover { background: #f1f5f9; }
    .rs-product-row { display: flex; align-items: center; padding: 10px 12px; border-bottom: 1px solid #f1f5f9; gap: 12px; transition: background 0.15s; }
    .rs-product-row:hover { background: #f8fafc; }
    .rs-product-row:last-child { border-bottom: none; }
    .rs-drag-handle { cursor: grab; color: #94a3b8; font-size: 18px; user-select: none; padding: 4px; }
    .rs-drag-handle:active { cursor: grabbing; }
    .rs-product-name { font-weight: 500; color: #1e293b; flex: 1; min-width: 120px; }
    .rs-product-qty { font-weight: 700; color: var(--color-warning); min-width: 60px; text-align: center; }
    .rs-product-racks { font-size: 12px; color: #64748b; max-width: 200px; }
    .rs-product-date { font-size: 12px; color: #94a3b8; min-width: 80px; text-align: right; }
    .rs-board { display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; min-height: 400px; }
    @media (max-width: 768px) { .rs-board { grid-template-columns: 1fr; } }
    .rs-pool { border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 15px; max-height: 500px; overflow-y: auto; }
    .rs-pool-header { font-weight: 600; color: #475569; margin-bottom: 10px; font-size: 14px; }
    .rs-pool-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-sm); padding: 10px 12px; margin-bottom: 8px; cursor: grab; display: flex; align-items: center; gap: 10px; transition: opacity 0.2s, transform 0.1s; }
    .rs-pool-card:active { cursor: grabbing; }
    .rs-pool-card.rs-dragging { opacity: 0.4; transform: scale(0.95); }
    .rs-pool-card-name { flex: 1; font-weight: 500; font-size: 14px; color: #1e293b; }
    .rs-pool-card-qty { font-weight: 700; font-size: 13px; color: var(--color-warning); background: #fef3c7; padding: 2px 8px; border-radius: 10px; }
    .rs-lanes-container { display: flex; flex-direction: column; gap: 15px; }
    .rs-lane { border: 2px dashed #cbd5e1; border-radius: var(--radius-md); padding: 15px; min-height: 100px; transition: border-color 0.2s, background 0.2s; }
    .rs-lane.rs-drop-active { border-color: var(--color-primary); background: #eff6ff; }
    .rs-lane-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .rs-lane-title { font-weight: 600; color: #1e293b; font-size: 15px; }
    .rs-lane-count { font-size: 12px; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 10px; }
    .rs-lane-item { background: white; border: 1px solid #e2e8f0; border-radius: var(--radius-sm); padding: 10px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
    .rs-lane-item-name { flex: 1; font-size: 14px; font-weight: 500; }
    .rs-lane-item-qty { width: 70px; padding: 4px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); text-align: center; font-size: 13px; }
    .rs-lane-item-remove { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 16px; padding: 4px; }
    .rs-add-supplier-btn { border: 2px dashed #94a3b8; border-radius: var(--radius-md); padding: 12px; text-align: center; cursor: pointer; color: #64748b; font-weight: 500; transition: border-color 0.2s, color 0.2s; }
    .rs-add-supplier-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
    .rs-submit-bar { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--color-border); }
    .rs-empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
    .rs-empty-state-icon { font-size: 48px; margin-bottom: 10px; }
    .rs-category-group[open] > summary > span:first-child { transform: rotate(90deg); }
</style>

<div class="rs-page-header">
    <h2 class="rs-page-title">📦 Restock & Buat PO</h2>
    <div style="display:flex;gap:8px;">
        <button type="button" id="btn-open-manual-po" class="btn" style="background: var(--color-primary); color: white;">➕ Buat PO Manual</button>
        <a href="{{ route('admin.restock.orders') }}" class="btn" style="background: var(--color-info); color: white;">📋 Purchase Orders</a>
    </div>
</div>

{{-- KPI Cards --}}
@php
    $pendingCount = $summary['pending_count'] ?? 0;
    $orderedCount = $summary['ordered_count'] ?? 0;
    $receivedCount = $summary['received_count'] ?? 0;
    $monthlyPoCount = $summary['monthly_po_count'] ?? 0;
    $avgFulfillment = $summary['avg_fulfillment_hours'] ?? 0;
@endphp

<div class="rs-kpi-grid">
    <div class="rs-kpi-card" style="border-left: 4px solid var(--color-warning);">
        <div class="rs-kpi-label">Pending Restock</div>
        <div class="rs-kpi-value">{{ $pendingCount }}</div>
    </div>
    <div class="rs-kpi-card" style="border-left: 4px solid var(--color-info);">
        <div class="rs-kpi-label">Ordered</div>
        <div class="rs-kpi-value">{{ $orderedCount }}</div>
    </div>
    <div class="rs-kpi-card" style="border-left: 4px solid var(--color-success);">
        <div class="rs-kpi-label">Received</div>
        <div class="rs-kpi-value">{{ $receivedCount }}</div>
    </div>
    <div class="rs-kpi-card" style="border-left: 4px solid var(--color-primary);">
        <div class="rs-kpi-label">PO Bulan Ini</div>
        <div class="rs-kpi-value">{{ $monthlyPoCount }}</div>
    </div>
    <div class="rs-kpi-card" style="border-left: 4px solid #94a3b8;">
        <div class="rs-kpi-label">Avg Fulfillment</div>
        <div class="rs-kpi-value">{{ number_format($avgFulfillment, 1) }}h</div>
    </div>
</div>

<div id="modal-manual-po" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:var(--radius-md);width:100%;max-width:880px;padding:20px;max-height:92vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;flex-wrap:wrap;">
            <h3 style="margin:0;">📦 Buat PO Manual <span id="manual-po-draft-badge" style="display:none;font-size:11px;background:#fef3c7;color:#a16207;padding:2px 8px;border-radius:4px;font-weight:normal;margin-left:6px;">Draft #<span id="manual-po-draft-id-label">-</span></span></h3>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn" id="btn-show-drafts" style="background:#f1f5f9;color:#334155;font-size:12px;padding:6px 10px;">📋 Daftar Draft</button>
                <button type="button" class="btn-close-manual-po" style="background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
            </div>
        </div>

        <form id="form-manual-po">
            <input type="hidden" id="manual-po-draft-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div style="position:relative;">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Nama Supplier *</label>
                    <input type="hidden" id="manual-po-supplier-id">
                    <input id="manual-po-supplier" required maxlength="120" placeholder="Ketik nama supplier..." autocomplete="off" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:4px;">
                    <div id="manual-po-supplier-results" style="display:none;position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid #cbd5e1;border-radius:4px;max-height:240px;overflow-y:auto;z-index:50;box-shadow:0 4px 12px rgba(0,0,0,0.08);margin-top:2px;"></div>
                    <div style="margin-top:4px;font-size:11px;">
                        <span id="manual-po-supplier-status" style="color:#94a3b8;">Pilih dari daftar atau ketik nama baru</span>
                        <button type="button" id="btn-add-new-supplier" style="display:none;background:none;border:none;color:#0284c7;text-decoration:underline;cursor:pointer;font-size:11px;padding:0;">+ Tambah supplier baru</button>
                    </div>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Rak default <span style="color:#94a3b8;font-weight:normal;">(opsional, waiter bisa override saat terima)</span></label>
                    <input id="manual-po-rack" maxlength="60" placeholder="ID rak (kosongkan kalau waiter pilih sendiri)" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:4px;">
                </div>
            </div>

            <div style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:flex-end;">
                <label style="font-size:12px;font-weight:600;">Item Produk</label>
                <div style="font-size:12px;color:#64748b;">
                    Total Qty: <strong id="manual-po-total-qty" style="color:#0f172a;">0</strong>
                    · Item: <strong id="manual-po-item-count" style="color:#0f172a;">0</strong>
                </div>
            </div>

            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="border:1px solid #e2e8f0;padding:8px;text-align:left;width:38%;">Produk</th>
                        <th style="border:1px solid #e2e8f0;padding:8px;text-align:left;width:12%;">Qty</th>
                        <th style="border:1px solid #e2e8f0;padding:8px;text-align:left;width:12%;">Satuan</th>
                        <th style="border:1px solid #e2e8f0;padding:8px;text-align:left;width:12%;">Stok Saat Ini</th>
                        <th style="border:1px solid #e2e8f0;padding:8px;text-align:left;width:18%;">Note</th>
                        <th style="border:1px solid #e2e8f0;padding:8px;text-align:center;width:8%;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="manual-po-items"></tbody>
            </table>

            <button type="button" class="btn" id="btn-add-manual-item" style="margin-top:10px;background:#e2e8f0;color:#334155;font-size:12px;">+ Tambah produk</button>

            <div style="margin-top:12px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Catatan PO</label>
                <textarea id="manual-po-notes" maxlength="500" rows="2" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:4px;"></textarea>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap;">
                <div style="font-size:11px;color:#64748b;">
                    💡 Auto-save lokal aktif. Tutup tab tidak menghilangkan input.
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-close-manual-po" style="background:#e2e8f0;color:#475569;">Batal</button>
                    <button type="button" id="btn-save-draft-manual-po" class="btn" style="background:#fef3c7;color:#a16207;">💾 Simpan Draft</button>
                    <button type="submit" class="btn" id="btn-submit-manual-po" style="background:var(--color-primary);color:white;">✓ Simpan PO Manual</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Modal: Drafts list --}}
<div id="modal-drafts-list" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1001;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:var(--radius-md);width:100%;max-width:560px;padding:20px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h3 style="margin:0;">📋 Daftar Draft PO Manual</h3>
            <button type="button" id="btn-close-drafts" style="background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <div id="drafts-list-container" style="font-size:13px;">
            <div style="text-align:center;padding:30px;color:#94a3b8;">Loading...</div>
        </div>
    </div>
</div>

@if(empty($groupedItems))
    <div class="rs-section">
        <div class="rs-empty-state">
            <div class="rs-empty-state-icon">✅</div>
            <p style="font-size: 16px; font-weight: 500;">Tidak ada produk yang perlu restock saat ini.</p>
            <p style="font-size: 13px; color: #94a3b8;">Produk akan muncul otomatis saat waiter melaporkan stok di bawah standar pada rak storage.</p>
        </div>
    </div>
@else

@php
    $grouped = [];
    foreach ($groupedItems as $item) {
        $catName = $item['product_category_name'] ?? 'Lainnya';
        $catId = $item['product_category_id'] ?? 'uncategorized';
        if (!isset($grouped[$catId])) {
            $grouped[$catId] = ['name' => $catName, 'items' => []];
        }
        $grouped[$catId]['items'][] = $item;
    }
@endphp

{{-- Board Builder --}}
<div class="rs-section">
    <div class="rs-section-header">
        <h3 class="rs-section-title">🏗️ Board Builder — Buat PO</h3>
        <span style="font-size: 13px; color: #64748b;">Drag produk ke supplier lane untuk membuat PO</span>
    </div>

    <div class="rs-board">
        {{-- Left: Product Pool with Category Accordion --}}
        <div class="rs-pool" id="rs-product-pool">
            <div class="rs-pool-header">📦 Produk Restock ({{ count($groupedItems) }})</div>
            @foreach($grouped as $catId => $catGroup)
            <details open class="rs-category-group" data-category-id="{{ $catId }}" style="margin-bottom: 8px;">
                <summary style="font-weight: 600; font-size: 14px; color: #334155; cursor: pointer; padding: 8px 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; user-select: none; list-style: none; display: flex; align-items: center; gap: 8px;">
                    <span style="transition: transform 0.2s;">▶</span>
                    <span style="flex: 1;">{{ $catGroup['name'] }}</span>
                    <span style="font-size: 12px; color: #64748b; background: #e2e8f0; padding: 2px 8px; border-radius: 10px;">{{ count($catGroup['items']) }}</span>
                </summary>
                <div style="padding: 8px 0;">
                    @foreach($catGroup['items'] as $item)
                    <div class="rs-pool-card" 
                         draggable="true"
                         data-product-id="{{ $item['product_id'] }}"
                         data-product-name="{{ $item['product_name'] }}"
                         data-qty-needed="{{ $item['total_qty_needed'] }}"
                         data-restock-ids='@json($item['restock_ids'])'
                         data-category-id="{{ $catId }}"
                         data-category="{{ $catGroup['name'] }}">
                        <span style="color: #94a3b8; font-size: 14px;">≡</span>
                        <span class="rs-pool-card-name">{{ $item['product_name'] }}</span>
                        <span class="rs-pool-card-qty">{{ $item['total_qty_needed'] }}</span>
                    </div>
                    @endforeach
                </div>
            </details>
            @endforeach
        </div>

        {{-- Right: Supplier Lanes --}}
        <div class="rs-lanes-container" id="rs-lanes-container">
            <div class="rs-add-supplier-btn" id="rs-add-supplier">
                ➕ Tambah Supplier
            </div>
        </div>
    </div>

    <div class="rs-submit-bar" id="rs-submit-bar" style="display: none;">
        <button class="btn" style="background: #64748b; color: white;" id="rs-reset-btn">🔄 Reset</button>
        <button class="btn" style="background: var(--color-primary); color: white; font-weight: 600;" id="rs-submit-btn">📦 Buat PO</button>
    </div>
</div>

@endif

{{-- Modal Create Supplier --}}
<div id="modal-create-supplier" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: var(--radius-md); width: 100%; max-width: 500px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Tambah Supplier Baru</h3>
            <button class="btn-close-modal" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <form id="form-create-supplier">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Nama Supplier *</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">No. HP / WhatsApp *</label>
                <input type="text" name="phone" required placeholder="0812..." style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Contact Person</label>
                <input type="text" name="contact_person" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Alamat</label>
                <textarea name="address" rows="2" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-close-modal" style="background: #e2e8f0; color: #475569;">Batal</button>
                <button type="submit" class="btn btn-primary" id="btn-save-supplier">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal PO Success --}}
<div id="modal-po-success" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: var(--radius-md); width: 100%; max-width: 500px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto;">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 48px; color: var(--color-success); margin-bottom: 10px;">✅</div>
            <h3 style="margin: 0; color: var(--color-success);">PO Berhasil Dibuat!</h3>
            <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Pesan WhatsApp sudah disiapkan untuk dikirim ke supplier.</p>
        </div>
        
        <div id="po-success-list">
            <!-- PO Success items will be appended here -->
        </div>

        <div style="display: flex; justify-content: center; margin-top: 20px;">
            <button type="button" class="btn" id="btn-close-po-success" style="background: #e2e8f0; color: #475569;">Tutup & Muat Ulang</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';


    const pool = document.getElementById('rs-product-pool');
    const lanesContainer = document.getElementById('rs-lanes-container');
    const addSupplierBtn = document.getElementById('rs-add-supplier');
    const submitBar = document.getElementById('rs-submit-bar');
    const submitBtn = document.getElementById('rs-submit-btn');
    const resetBtn = document.getElementById('rs-reset-btn');
    const manualPoModal = document.getElementById('modal-manual-po');
    const manualPoBtn = document.getElementById('btn-open-manual-po');
    const manualPoForm = document.getElementById('form-manual-po');

    if (!pool) return; // empty state

    let laneCounter = 0;
    let draggedCard = null;
    let touchClone = null;
    let suppliersData = [];

    function formatRelativeTime(createdAt) {
        const ts = Number(createdAt) || 0;
        if (!ts) return '-';
        const now = Date.now();
        const diffMs = Math.max(0, now - ts);
        const mins = Math.floor(diffMs / 60000);
        if (mins < 60) return mins + ' menit lalu';
        const hours = Math.floor(mins / 60);
        if (hours < 24) return hours + ' jam lalu';
        const days = Math.floor(hours / 24);
        return days + ' hari lalu';
    }

    function showDuplicateConfirm(conflicts) {
        const safeConflicts = Array.isArray(conflicts) ? conflicts : [];
        const lines = safeConflicts.map(function(c) {
            const poNumber = c && c.po_number ? c.po_number : (c && c.po_id ? c.po_id : '-');
            const supplierName = c && c.supplier_name ? c.supplier_name : '-';
            const overlapCount = Array.isArray(c && c.matched_product_ids) ? c.matched_product_ids.length : 0;
            const rel = formatRelativeTime(c && c.created_at);
            return '• PO ' + poNumber + ' • supplier ' + supplierName + ' • dibuat ' + rel + ' • produk overlap: ' + overlapCount;
        });
        const body = (safeConflicts.length) + ' PO masih open untuk produk yang sama:\n\n' + lines.join('\n');
        return window.confirm('PO duplikat terdeteksi\n\n' + body + '\n\nPilih OK = Lanjutkan tetap, Cancel = Batal.');
    }

    // Fetch suppliers on load
    fetch('{{ route("admin.suppliers.index") }}', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if(data && Array.isArray(data.data)) {
            suppliersData = data.data;
        } else if (Array.isArray(data)) {
            suppliersData = data;
        }
    })
    .catch(err => console.error('Gagal memuat supplier:', err));

    // Modal Logic
    const modalCreateSupplier = document.getElementById('modal-create-supplier');
    const modalPoSuccess = document.getElementById('modal-po-success');
    const formCreateSupplier = document.getElementById('form-create-supplier');
    const btnSaveSupplier = document.getElementById('btn-save-supplier');
    
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            modalCreateSupplier.style.display = 'none';
        });
    });

    document.getElementById('btn-close-po-success').addEventListener('click', function() {
        modalPoSuccess.style.display = 'none';
        window.location.reload();
    });

    let currentLaneDropdownToUpdate = null;

    formCreateSupplier.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(formCreateSupplier);
        const data = Object.fromEntries(formData.entries());
        
        btnSaveSupplier.disabled = true;
        btnSaveSupplier.textContent = 'Menyimpan...';

        fetch('{{ route("admin.suppliers.ajax_store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                // Tambahkan ke suppliersData
                suppliersData.push(resData.data);
                
                // Update semua dropdown yang ada
                document.querySelectorAll('.rs-lane-name').forEach(select => {
                    const currentVal = select.value;
                    const option = document.createElement('option');
                    option.value = resData.data.name;
                    option.textContent = resData.data.name;
                    option.dataset.phone = resData.data.phone;
                    select.appendChild(option);
                    
                    // Kalau ini select yang memicu tambah supplier, otomatis pilih yang baru
                    if (currentLaneDropdownToUpdate === select) {
                        select.value = resData.data.name;
                    }
                });

                modalCreateSupplier.style.display = 'none';
                formCreateSupplier.reset();
            } else {
                alert('Gagal menyimpan: ' + (resData.message || 'Error tidak diketahui'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan saat menyimpan supplier.');
        })
        .finally(() => {
            btnSaveSupplier.disabled = false;
            btnSaveSupplier.textContent = 'Simpan';
            currentLaneDropdownToUpdate = null;
        });
    });

    // Add supplier lane
    addSupplierBtn.addEventListener('click', function() {
        createLane();
    });

    function createLane(supplierName) {
        laneCounter++;
        const laneId = 'rs-lane-' + laneCounter;
        const lane = document.createElement('div');
        lane.className = 'rs-lane';
        lane.id = laneId;
        lane.setAttribute('data-lane-id', laneCounter);
        
        let optionsHtml = '<option value="">-- Pilih Supplier --</option>';
        suppliersData.forEach(sup => {
            const selected = supplierName === sup.name ? 'selected' : '';
            optionsHtml += `<option value="${sup.name}" data-phone="${sup.phone || ''}" ${selected}>${sup.name}</option>`;
        });

        lane.innerHTML = 
            '<div class="rs-lane-header">' +
                '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<select class="form-input rs-lane-name" style="font-weight: 600; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px; width: 180px;">' + optionsHtml + '</select>' +
                    '<button type="button" class="btn-new-supplier" style="background: none; border: 1px dashed var(--color-primary); color: var(--color-primary); padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500;" title="Supplier Baru">+ Baru</button>' +
                    '<span class="rs-lane-count" style="margin-left: 8px;">0 item</span>' +
                '</div>' +
                '<button class="rs-lane-delete" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 14px; padding: 4px 8px;" title="Hapus lane">🗑️ Hapus</button>' +
            '</div>' +
            '<div class="rs-lane-dropzone" style="min-height: 60px; padding: 5px 0;"></div>';

        lanesContainer.insertBefore(lane, addSupplierBtn);

        // Event listener for + Baru
        lane.querySelector('.btn-new-supplier').addEventListener('click', function() {
            currentLaneDropdownToUpdate = lane.querySelector('.rs-lane-name');
            modalCreateSupplier.style.display = 'flex';
            formCreateSupplier.querySelector('[name="name"]').focus();
        });

        // Delete lane
        lane.querySelector('.rs-lane-delete').addEventListener('click', function() {
            // Return items to pool
            lane.querySelectorAll('.rs-lane-item').forEach(function(item) {
                returnItemToPool(item);
            });
            lane.remove();
            updateSubmitBar();
        });

        // Drop zone events
        const dropzone = lane.querySelector('.rs-lane-dropzone');
        dropzone.addEventListener('dragover', handleDragOver);
        dropzone.addEventListener('dragenter', handleDragEnter);
        dropzone.addEventListener('dragleave', handleDragLeave);
        dropzone.addEventListener('drop', handleDrop);

        // Touch drop
        dropzone.setAttribute('data-dropzone', '1');

        updateSubmitBar();
        return lane;
    }

    // Drag & Drop - HTML5
    pool.querySelectorAll('.rs-pool-card').forEach(function(card) {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
        setupTouchDrag(card);
    });

    function handleDragStart(e) {
        draggedCard = this;
        this.classList.add('rs-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.getAttribute('data-product-id'));
    }

    function handleDragEnd(e) {
        this.classList.remove('rs-dragging');
        document.querySelectorAll('.rs-drop-active').forEach(function(el) {
            el.classList.remove('rs-drop-active');
        });
        draggedCard = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function handleDragEnter(e) {
        e.preventDefault();
        this.closest('.rs-lane').classList.add('rs-drop-active');
    }

    function handleDragLeave(e) {
        if (!this.contains(e.relatedTarget)) {
            this.closest('.rs-lane').classList.remove('rs-drop-active');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        this.closest('.rs-lane').classList.remove('rs-drop-active');

        if (!draggedCard) return;

        const productId = draggedCard.getAttribute('data-product-id');
        const dropzone = this;
        const lane = dropzone.closest('.rs-lane');

        // Check if already in this lane
        if (lane.querySelector('[data-product-id="' + productId + '"]')) return;

        addItemToLane(lane, draggedCard);
        draggedCard.style.display = 'none';
        draggedCard = null;
        updateSubmitBar();
    }

    // Touch drag support
    function setupTouchDrag(card) {
        let startX, startY, isDragging = false;

        card.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = false;
            draggedCard = this;
        }, { passive: true });

        card.addEventListener('touchmove', function(e) {
            if (!draggedCard) return;
            const dx = Math.abs(e.touches[0].clientX - startX);
            const dy = Math.abs(e.touches[0].clientY - startY);

            if (dx > 10 || dy > 10) {
                isDragging = true;
                e.preventDefault();
                this.classList.add('rs-dragging');

                if (!touchClone) {
                    touchClone = this.cloneNode(true);
                    touchClone.style.position = 'fixed';
                    touchClone.style.pointerEvents = 'none';
                    touchClone.style.zIndex = '9999';
                    touchClone.style.opacity = '0.8';
                    touchClone.style.width = this.offsetWidth + 'px';
                    document.body.appendChild(touchClone);
                }
                touchClone.style.left = (e.touches[0].clientX - 40) + 'px';
                touchClone.style.top = (e.touches[0].clientY - 20) + 'px';

                // Highlight drop zones
                const elem = document.elementFromPoint(e.touches[0].clientX, e.touches[0].clientY);
                document.querySelectorAll('.rs-drop-active').forEach(function(el) { el.classList.remove('rs-drop-active'); });
                if (elem) {
                    const lane = elem.closest('.rs-lane');
                    if (lane) lane.classList.add('rs-drop-active');
                }
            }
        }, { passive: false });

        card.addEventListener('touchend', function(e) {
            if (touchClone) {
                document.body.removeChild(touchClone);
                touchClone = null;
            }
            this.classList.remove('rs-dragging');
            document.querySelectorAll('.rs-drop-active').forEach(function(el) { el.classList.remove('rs-drop-active'); });

            if (!isDragging || !draggedCard) {
                draggedCard = null;
                return;
            }

            const touch = e.changedTouches[0];
            const elem = document.elementFromPoint(touch.clientX, touch.clientY);
            if (elem) {
                const lane = elem.closest('.rs-lane');
                if (lane) {
                    const productId = draggedCard.getAttribute('data-product-id');
                    if (!lane.querySelector('.rs-lane-item[data-product-id="' + productId + '"]')) {
                        addItemToLane(lane, draggedCard);
                        draggedCard.style.display = 'none';
                    }
                }
            }
            draggedCard = null;
            isDragging = false;
            updateSubmitBar();
        });
    }

    function addItemToLane(lane, card) {
        const productId = card.getAttribute('data-product-id');
        const productName = card.getAttribute('data-product-name');
        const qtyNeeded = card.getAttribute('data-qty-needed');
        const restockIds = card.getAttribute('data-restock-ids');

        const dropzone = lane.querySelector('.rs-lane-dropzone');
        const item = document.createElement('div');
        item.className = 'rs-lane-item';
        item.setAttribute('data-product-id', productId);
        item.setAttribute('data-restock-ids', restockIds);
        item.innerHTML = 
            '<span class="rs-lane-item-name">' + escapeHtml(productName) + '</span>' +
            '<input type="number" class="rs-lane-item-qty" value="' + qtyNeeded + '" min="1" title="Qty order">' +
            '<button class="rs-lane-item-remove" title="Kembalikan ke pool">&times;</button>';

        item.querySelector('.rs-lane-item-remove').addEventListener('click', function() {
            returnItemToPool(item);
            updateSubmitBar();
        });

        dropzone.appendChild(item);
        updateLaneCount(lane);
    }

    function returnItemToPool(item) {
        const productId = item.getAttribute('data-product-id');
        const poolCard = pool.querySelector('[data-product-id="' + productId + '"]');
        if (poolCard) poolCard.style.display = '';
        const lane = item.closest('.rs-lane');
        item.remove();
        if (lane) updateLaneCount(lane);
    }

    function updateLaneCount(lane) {
        const count = lane.querySelectorAll('.rs-lane-item').length;
        const badge = lane.querySelector('.rs-lane-count');
        if (badge) badge.textContent = count + ' item';
    }

    function updateSubmitBar() {
        const lanes = lanesContainer.querySelectorAll('.rs-lane');
        let totalItems = 0;
        lanes.forEach(function(lane) {
            totalItems += lane.querySelectorAll('.rs-lane-item').length;
        });
        if (submitBar) {
            submitBar.style.display = totalItems > 0 ? 'flex' : 'none';
            if (submitBtn) {
                const laneCount = lanes.length;
                submitBtn.textContent = '📦 Buat PO (' + laneCount + ' supplier)';
            }
        }
    }

    // Submit
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            const lanes = lanesContainer.querySelectorAll('.rs-lane');
            const orders = [];

            lanes.forEach(function(lane) {
                const supplierInput = lane.querySelector('.rs-lane-name');
                const supplier = supplierInput ? supplierInput.value.trim() : '';
                const items = [];

                lane.querySelectorAll('.rs-lane-item').forEach(function(item) {
                    const restockIds = JSON.parse(item.getAttribute('data-restock-ids') || '[]');
                    const qty = parseInt(item.querySelector('.rs-lane-item-qty').value) || 1;
                    items.push({ restock_ids: restockIds, qty_ordered: qty });
                });

                if (items.length > 0) {
                    if (!supplier) {
                        alert('Mohon isi nama supplier untuk semua lane.');
                        supplierInput.focus();
                        return;
                    }
                    orders.push({ supplier: supplier, notes: null, items: items });
                }
            });

            if (orders.length === 0) {
                alert('Belum ada produk yang di-assign ke supplier.');
                return;
            }

            // Validate all suppliers filled
            for (var i = 0; i < orders.length; i++) {
                if (!orders[i].supplier) return; // already alerted above
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Menyimpan...';

            const submitBatch = function(forceDuplicate) {
                return fetch('{{ route("admin.restock.create_batch_po") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ orders: orders, force_duplicate: forceDuplicate ? 1 : 0 })
            })
            .then(function(response) {
                return response.json().then(function(data) {
                    return { status: response.status, data: data };
                });
            });
            };

            submitBatch(false)
            .then(function(data) {
                if (data.status === 409 && data.data && data.data.code === 'po_duplicate') {
                    const proceed = showDuplicateConfirm(data.data.conflicts || []);
                    if (!proceed) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '📦 Buat PO';
                        return;
                    }
                    return submitBatch(true).then(function(second) {
                        data = second;
                        if (!(data.data && data.data.success)) {
                            alert('❌ Error: ' + ((data.data && data.data.message) || 'Gagal membuat PO'));
                            submitBtn.disabled = false;
                            submitBtn.textContent = '📦 Buat PO';
                            return;
                        }

                        const poSuccessList = document.getElementById('po-success-list');
                        poSuccessList.innerHTML = '';
                        const today = new Date().toLocaleDateString('id-ID');
                        data.data.orders.forEach(function(order) {
                            const itemsList = order.items.map(function(item) { return `- ${item.qty_ordered}x ${item.product_name}`; }).join('\n');
                            const waText = `📦 *PURCHASE ORDER*\n\nPO: ${order.po_number}\nDari: [Nama Toko Anda]\nTanggal: ${today}\n\nDaftar Pesanan:\n${itemsList}\n\nTotal: ${order.items_count} item\n\nMohon konfirmasi ketersediaan barang.\nTerima kasih!`;
                            const phone = (order.supplier_phone || '').replace(/\D/g, '');
                            const waLink = phone ? `https://wa.me/${phone.startsWith('0') ? '62' + phone.substring(1) : phone}?text=${encodeURIComponent(waText)}` : '#';
                            const waTarget = phone ? 'target="_blank"' : 'onclick="alert(\'Nomor HP supplier tidak tersedia\'); return false;"';
                            const div = document.createElement('div');
                            div.style.border = '1px solid #e2e8f0';
                            div.style.borderRadius = 'var(--radius-sm)';
                            div.style.padding = '15px';
                            div.style.marginBottom = '15px';
                            div.innerHTML = `
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div>
                                        <h4 style="margin: 0; font-size: 16px;">${order.supplier}</h4>
                                        <div style="font-size: 13px; color: #64748b;">PO: ${order.po_number}</div>
                                    </div>
                                    <div style="font-size: 12px; background: #e2e8f0; padding: 2px 8px; border-radius: 10px;">${order.items_count} item</div>
                                </div>
                                <textarea readonly style="width: 100%; height: 120px; padding: 8px; font-family: monospace; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 4px; margin-bottom: 10px; background: #f8fafc; resize: none;">${waText}</textarea>
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" class="btn btn-sm btn-copy-wa" data-text="${encodeURIComponent(waText)}" style="background: white; border: 1px solid #cbd5e1; color: #334155; flex: 1;">📋 Copy Pesan</button>
                                    <a href="${waLink}" ${waTarget} class="btn btn-sm" style="background: #25D366; color: white; flex: 1; text-align: center; text-decoration: none;">💬 Buka WhatsApp</a>
                                </div>
                            `;
                            poSuccessList.appendChild(div);
                        });
                        poSuccessList.querySelectorAll('.btn-copy-wa').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                const text = decodeURIComponent(this.getAttribute('data-text'));
                                navigator.clipboard.writeText(text).then(() => {
                                    const originalText = this.innerHTML;
                                    this.innerHTML = '✅ Dicopy!';
                                    setTimeout(() => { this.innerHTML = originalText; }, 2000);
                                }).catch(err => {
                                    console.error('Failed to copy text: ', err);
                                    alert('Gagal copy teks');
                                });
                            });
                        });
                        document.getElementById('modal-po-success').style.display = 'flex';
                    });
                }

                if (data.data && data.data.success && data.data.orders) {
                    const poSuccessList = document.getElementById('po-success-list');
                    poSuccessList.innerHTML = ''; // clear
                    
                    const today = new Date().toLocaleDateString('id-ID');

                    data.data.orders.forEach(function(order) {
                        const itemsList = order.items.map(function(item) {
                            return `- ${item.qty_ordered}x ${item.product_name}`;
                        }).join('\n');

                        const waText = `📦 *PURCHASE ORDER*\n\nPO: ${order.po_number}\nDari: [Nama Toko Anda]\nTanggal: ${today}\n\nDaftar Pesanan:\n${itemsList}\n\nTotal: ${order.items_count} item\n\nMohon konfirmasi ketersediaan barang.\nTerima kasih!`;
                        
                        const phone = (order.supplier_phone || '').replace(/\D/g, '');
                        const waLink = phone ? `https://wa.me/${phone.startsWith('0') ? '62' + phone.substring(1) : phone}?text=${encodeURIComponent(waText)}` : '#';
                        const waTarget = phone ? 'target="_blank"' : 'onclick="alert(\'Nomor HP supplier tidak tersedia\'); return false;"';

                        const div = document.createElement('div');
                        div.style.border = '1px solid #e2e8f0';
                        div.style.borderRadius = 'var(--radius-sm)';
                        div.style.padding = '15px';
                        div.style.marginBottom = '15px';
                        div.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <div>
                                    <h4 style="margin: 0; font-size: 16px;">${order.supplier}</h4>
                                    <div style="font-size: 13px; color: #64748b;">PO: ${order.po_number}</div>
                                </div>
                                <div style="font-size: 12px; background: #e2e8f0; padding: 2px 8px; border-radius: 10px;">${order.items_count} item</div>
                            </div>
                            <textarea readonly style="width: 100%; height: 120px; padding: 8px; font-family: monospace; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 4px; margin-bottom: 10px; background: #f8fafc; resize: none;">${waText}</textarea>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="btn btn-sm btn-copy-wa" data-text="${encodeURIComponent(waText)}" style="background: white; border: 1px solid #cbd5e1; color: #334155; flex: 1;">📋 Copy Pesan</button>
                                <a href="${waLink}" ${waTarget} class="btn btn-sm" style="background: #25D366; color: white; flex: 1; text-align: center; text-decoration: none;">💬 Buka WhatsApp</a>
                            </div>
                        `;
                        poSuccessList.appendChild(div);
                    });

                    // Handle copy events
                    poSuccessList.querySelectorAll('.btn-copy-wa').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const text = decodeURIComponent(this.getAttribute('data-text'));
                            navigator.clipboard.writeText(text).then(() => {
                                const originalText = this.innerHTML;
                                this.innerHTML = '✅ Dicopy!';
                                setTimeout(() => { this.innerHTML = originalText; }, 2000);
                            }).catch(err => {
                                console.error('Failed to copy text: ', err);
                                alert('Gagal copy teks');
                            });
                        });
                    });

                    document.getElementById('modal-po-success').style.display = 'flex';
                } else if (data.data && data.data.success) {
                    alert('✅ Berhasil membuat ' + (data.data.po_count || orders.length) + ' Purchase Order!');
                    window.location.reload();
                } else {
                    alert('❌ Error: ' + ((data.data && data.data.message) || 'Gagal membuat PO'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = '📦 Buat PO';
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('❌ Terjadi kesalahan sistem');
                submitBtn.disabled = false;
                submitBtn.textContent = '📦 Buat PO';
            });
        });
    }

    // Reset
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (!confirm('Reset semua assignment?')) return;
            lanesContainer.querySelectorAll('.rs-lane').forEach(function(lane) {
                lane.querySelectorAll('.rs-lane-item').forEach(function(item) {
                    returnItemToPool(item);
                });
                lane.remove();
            });
            laneCounter = 0;
            updateSubmitBar();
        });
    }

    // Also allow drop back to pool
    pool.addEventListener('dragover', function(e) { e.preventDefault(); });
    pool.addEventListener('drop', function(e) {
        e.preventDefault();
        // If dragging from lane back to pool - handled by remove button
    });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function addManualItemRow(prefill) {
        const tb = document.getElementById('manual-po-items');
        if (!tb) return;
        const tr = document.createElement('tr');
        tr.className = 'manual-row';
        tr.dataset.productId = prefill && prefill.product_id ? prefill.product_id : '';
        tr.innerHTML = ''
          + '<td style="border:1px solid #e2e8f0;padding:6px;position:relative;">'
          +   '<input type="hidden" class="manual-product-id" value="' + (prefill && prefill.product_id ? escapeAttr(prefill.product_id) : '') + '">'
          +   '<input class="manual-product-search" placeholder="Ketik nama produk..." autocomplete="off" required'
          +     ' style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:4px;font-size:13px;"'
          +     ' value="' + (prefill && prefill.product_name ? escapeAttr(prefill.product_name) : '') + '">'
          +   '<div class="manual-search-results" style="display:none;position:absolute;left:6px;right:6px;background:#fff;border:1px solid #cbd5e1;border-radius:4px;max-height:240px;overflow-y:auto;z-index:50;box-shadow:0 4px 12px rgba(0,0,0,0.08);"></div>'
          + '</td>'
          + '<td style="border:1px solid #e2e8f0;padding:6px;"><input type="number" min="1" value="' + (prefill && prefill.qty ? prefill.qty : 1) + '" class="manual-product-qty" required style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:4px;"></td>'
          + '<td style="border:1px solid #e2e8f0;padding:6px;color:#64748b;font-size:12px;text-align:center;" class="manual-product-unit">-</td>'
          + '<td style="border:1px solid #e2e8f0;padding:6px;font-size:12px;text-align:center;" class="manual-product-stock">-</td>'
          + '<td style="border:1px solid #e2e8f0;padding:6px;"><input class="manual-product-note" maxlength="200" value="' + (prefill && prefill.note ? escapeAttr(prefill.note) : '') + '" style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:4px;"></td>'
          + '<td style="border:1px solid #e2e8f0;padding:6px;text-align:center;"><button type="button" class="rm-manual" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:16px;">✕</button></td>';
        tr.querySelector('.rm-manual').addEventListener('click', function() {
            tr.remove();
            if (!tb.querySelector('tr')) addManualItemRow();
            updateManualTotals();
            scheduleAutoSave();
        });
        const searchInput = tr.querySelector('.manual-product-search');
        searchInput.addEventListener('input', function() { handleProductSearch(tr, this.value); });
        searchInput.addEventListener('blur', function() {
            setTimeout(function() {
                tr.querySelector('.manual-search-results').style.display = 'none';
            }, 200);
        });
        searchInput.addEventListener('focus', function() {
            const box = tr.querySelector('.manual-search-results');
            if (box.children.length > 0) box.style.display = 'block';
        });
        tr.querySelector('.manual-product-qty').addEventListener('input', function() {
            updateManualTotals();
            scheduleAutoSave();
        });
        tr.querySelector('.manual-product-note').addEventListener('input', scheduleAutoSave);
        tb.appendChild(tr);

        // Kalau ada prefill product_id, langsung fetch unit + stock
        if (prefill && prefill.product_id) {
            fetchProductStock(tr, prefill.product_id);
        }
    }

    function escapeAttr(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    let searchTimers = new WeakMap();
    function handleProductSearch(tr, keyword) {
        const idInput = tr.querySelector('.manual-product-id');
        idInput.value = ''; // reset id sampai user pilih
        tr.querySelector('.manual-product-unit').textContent = '-';
        tr.querySelector('.manual-product-stock').textContent = '-';

        const existingTimer = searchTimers.get(tr);
        if (existingTimer) clearTimeout(existingTimer);

        if (!keyword || keyword.trim().length < 2) {
            tr.querySelector('.manual-search-results').style.display = 'none';
            return;
        }

        const timer = setTimeout(function() {
            fetch('{{ route("admin.products.search") }}?search=' + encodeURIComponent(keyword) + '&per_page=15', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { renderSearchResults(tr, data.products || []); })
            .catch(function() { /* swallow */ });
        }, 250);
        searchTimers.set(tr, timer);
    }

    function renderSearchResults(tr, products) {
        const box = tr.querySelector('.manual-search-results');
        if (products.length === 0) {
            box.innerHTML = '<div style="padding:10px 12px;color:#94a3b8;font-size:12px;">Tidak ada produk yang cocok.</div>';
        } else {
            box.innerHTML = products.map(function(p) {
                return '<div class="search-result-item" data-id="' + escapeAttr(p.id) + '" data-name="' + escapeAttr(p.name) + '" data-unit="' + escapeAttr(p.unit || 'pcs') + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;">'
                  + '<div style="font-weight:600;font-size:13px;">' + escapeAttr(p.name) + '</div>'
                  + '<div style="font-size:11px;color:#64748b;">' + escapeAttr(p.category_name || '-') + ' · satuan: ' + escapeAttr(p.unit || 'pcs') + ' · standar: ' + (p.standard_qty || 0) + '</div>'
                  + '</div>';
            }).join('');
            box.querySelectorAll('.search-result-item').forEach(function(item) {
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selectProduct(tr, this.dataset.id, this.dataset.name, this.dataset.unit);
                });
                item.addEventListener('mouseover', function() { this.style.background = '#f8fafc'; });
                item.addEventListener('mouseout', function() { this.style.background = ''; });
            });
        }
        box.style.display = 'block';
    }

    function selectProduct(tr, productId, productName, unit) {
        tr.querySelector('.manual-product-id').value = productId;
        tr.querySelector('.manual-product-search').value = productName;
        tr.querySelector('.manual-product-unit').textContent = unit || 'pcs';
        tr.querySelector('.manual-search-results').style.display = 'none';
        tr.dataset.productId = productId;
        fetchProductStock(tr, productId);
        scheduleAutoSave();
    }

    function fetchProductStock(tr, productId) {
        const stockCell = tr.querySelector('.manual-product-stock');
        stockCell.innerHTML = '<span style="color:#94a3b8;">⏳</span>';
        fetch('{{ url("admin/restock/product-stock") }}/' + encodeURIComponent(productId), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                stockCell.textContent = '?';
                return;
            }
            // Tampilkan total + breakdown gudang
            const totalAll = data.stock.total_all;
            const totalStorage = data.stock.total_storage;
            const totalDisplay = data.stock.total_display;
            const tooltip = data.stock.by_rack.map(function(r) {
                return r.rack_name + ' (' + r.rack_type + '): ' + r.current_qty;
            }).join('\n') || '(belum ada di rak manapun)';
            const color = totalAll === 0 ? '#dc2626' : (totalAll < 5 ? '#d97706' : '#16a34a');
            stockCell.innerHTML = '<span title="' + escapeAttr(tooltip) + '" style="color:' + color + ';font-weight:600;cursor:help;">'
              + totalAll + ' ' + (data.product.unit || 'pcs')
              + '</span>'
              + (totalStorage > 0 || totalDisplay > 0 ? '<div style="font-size:10px;color:#94a3b8;">G:' + totalStorage + ' D:' + totalDisplay + '</div>' : '');
            // Update unit kalau belum ada
            if (tr.querySelector('.manual-product-unit').textContent === '-') {
                tr.querySelector('.manual-product-unit').textContent = data.product.unit || 'pcs';
            }
        })
        .catch(function() { stockCell.textContent = '?'; });
    }

    function updateManualTotals() {
        const rows = document.querySelectorAll('#manual-po-items tr');
        let totalQty = 0;
        let count = 0;
        rows.forEach(function(tr) {
            const qty = parseInt(tr.querySelector('.manual-product-qty').value || '0', 10);
            const id = tr.querySelector('.manual-product-id').value;
            if (id && qty > 0) {
                totalQty += qty;
                count++;
            }
        });
        document.getElementById('manual-po-total-qty').textContent = totalQty;
        document.getElementById('manual-po-item-count').textContent = count;
    }

    // === DRAFT AUTOSAVE (localStorage + server) ===
    const DRAFT_LOCAL_KEY = 'manual_po_draft_local';
    const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;
    let autoSaveTimer = null;

    function collectDraftPayload() {
        const items = [];
        document.querySelectorAll('#manual-po-items tr').forEach(function(tr) {
            const product_id = tr.querySelector('.manual-product-id').value.trim();
            const product_name = tr.querySelector('.manual-product-search').value.trim();
            const qty = parseInt(tr.querySelector('.manual-product-qty').value || '0', 10);
            const note = tr.querySelector('.manual-product-note').value.trim();
            if (product_id && qty > 0) items.push({ product_id: product_id, product_name: product_name, qty: qty, note: note });
        });
        return {
            supplier_id: document.getElementById('manual-po-supplier-id').value.trim(),
            supplier_name: document.getElementById('manual-po-supplier').value.trim(),
            rack_id: document.getElementById('manual-po-rack').value.trim(),
            notes: document.getElementById('manual-po-notes').value.trim(),
            items: items
        };
    }

    function saveDraftLocal() {
        const payload = collectDraftPayload();
        const wrapper = { saved_at: Date.now(), data: payload };
        try { localStorage.setItem(DRAFT_LOCAL_KEY, JSON.stringify(wrapper)); } catch (e) {}
    }

    function loadDraftLocal() {
        try {
            const raw = localStorage.getItem(DRAFT_LOCAL_KEY);
            if (!raw) return null;
            const wrapper = JSON.parse(raw);
            if (Date.now() - wrapper.saved_at > DRAFT_TTL_MS) {
                localStorage.removeItem(DRAFT_LOCAL_KEY);
                return null;
            }
            return wrapper.data;
        } catch (e) { return null; }
    }

    function clearDraftLocal() { try { localStorage.removeItem(DRAFT_LOCAL_KEY); } catch (e) {} }

    function scheduleAutoSave() {
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(saveDraftLocal, 500);
    }

    function loadDraftDataIntoModal(data, draftId) {
        document.getElementById('manual-po-supplier').value = data.supplier_name || '';
        document.getElementById('manual-po-supplier-id').value = data.supplier_id || '';
        if (data.supplier_id) {
            supplierStatus.style.color = '#16a34a';
            supplierStatus.textContent = '✓ Supplier dari draft';
        } else {
            supplierStatus.style.color = '#94a3b8';
            supplierStatus.textContent = 'Pilih dari daftar atau ketik nama baru';
        }
        btnAddNewSupplier.style.display = 'none';
        document.getElementById('manual-po-rack').value = data.rack_id || '';
        document.getElementById('manual-po-notes').value = data.notes || '';
        document.getElementById('manual-po-items').innerHTML = '';
        if (data.items && data.items.length) {
            data.items.forEach(function(item) { addManualItemRow(item); });
        } else {
            addManualItemRow();
        }
        if (draftId) {
            document.getElementById('manual-po-draft-id').value = draftId;
            document.getElementById('manual-po-draft-id-label').textContent = draftId.substring(0, 8);
            document.getElementById('manual-po-draft-badge').style.display = '';
        } else {
            document.getElementById('manual-po-draft-id').value = '';
            document.getElementById('manual-po-draft-badge').style.display = 'none';
        }
        updateManualTotals();
    }

    function resetManualModal() {
        document.getElementById('manual-po-supplier').value = '';
        document.getElementById('manual-po-supplier-id').value = '';
        supplierStatus.style.color = '#94a3b8';
        supplierStatus.textContent = 'Pilih dari daftar atau ketik nama baru';
        btnAddNewSupplier.style.display = 'none';
        document.getElementById('manual-po-rack').value = '';
        document.getElementById('manual-po-notes').value = '';
        document.getElementById('manual-po-items').innerHTML = '';
        document.getElementById('manual-po-draft-id').value = '';
        document.getElementById('manual-po-draft-badge').style.display = 'none';
        addManualItemRow();
        updateManualTotals();
    }

    if (manualPoBtn) manualPoBtn.addEventListener('click', function() {
        manualPoModal.style.display = 'flex';
        const local = loadDraftLocal();
        if (local && (local.items.length > 0 || local.supplier_name)) {
            if (confirm('Ada draft tersimpan dari sesi sebelumnya. Lanjutkan draft?')) {
                loadDraftDataIntoModal(local, null);
                return;
            } else {
                clearDraftLocal();
            }
        }
        resetManualModal();
    });

    document.querySelectorAll('.btn-close-manual-po').forEach(function(btn) {
        btn.addEventListener('click', function() { manualPoModal.style.display = 'none'; });
    });
    document.getElementById('btn-add-manual-item').addEventListener('click', function() {
        addManualItemRow();
        updateManualTotals();
    });

    // === DRAFT SAVE BUTTON ===
    document.getElementById('btn-save-draft-manual-po').addEventListener('click', function() {
        const btn = this;
        const payload = collectDraftPayload();
        if (!payload.supplier_name && payload.items.length === 0) {
            alert('Tidak ada yang bisa disimpan. Isi minimal supplier atau 1 produk.');
            return;
        }
        payload.draft_id = document.getElementById('manual-po-draft-id').value || null;
        btn.disabled = true; btn.textContent = '⏳ Menyimpan...';
        fetch('{{ route("admin.restock.drafts.save") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false; btn.textContent = '💾 Simpan Draft';
            if (data.success) {
                document.getElementById('manual-po-draft-id').value = data.draft_id;
                document.getElementById('manual-po-draft-id-label').textContent = data.draft_id.substring(0, 8);
                document.getElementById('manual-po-draft-badge').style.display = '';
                clearDraftLocal();
                alert('✅ Draft tersimpan ke server.');
            } else {
                alert('❌ ' + (data.message || 'Gagal simpan draft'));
            }
        })
        .catch(function() { btn.disabled = false; btn.textContent = '💾 Simpan Draft'; alert('❌ Error koneksi'); });
    });

    // === DRAFTS LIST MODAL ===
    document.getElementById('btn-show-drafts').addEventListener('click', function() {
        const modal = document.getElementById('modal-drafts-list');
        modal.style.display = 'flex';
        const container = document.getElementById('drafts-list-container');
        container.innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8;">Loading...</div>';
        fetch('{{ route("admin.restock.drafts.list") }}', { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || data.drafts.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8;">Belum ada draft tersimpan.</div>';
                return;
            }
            container.innerHTML = data.drafts.map(function(d) {
                return '<div class="draft-card" style="padding:12px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:8px;">'
                  + '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">'
                  +   '<div style="flex:1;min-width:0;">'
                  +     '<div style="font-weight:600;">' + escapeAttr(d.supplier_name || '(tanpa supplier)') + '</div>'
                  +     '<div style="font-size:11px;color:#64748b;margin-top:2px;">' + d.item_count + ' item · update ' + escapeAttr(d.updated_at) + '</div>'
                  +     (d.notes ? '<div style="font-size:11px;color:#64748b;margin-top:4px;">📝 ' + escapeAttr(d.notes) + '</div>' : '')
                  +   '</div>'
                  +   '<div style="display:flex;gap:6px;flex-shrink:0;">'
                  +     '<button type="button" class="draft-load" data-id="' + escapeAttr(d.id) + '" style="background:#0284c7;color:#fff;border:none;padding:6px 10px;border-radius:4px;font-size:11px;cursor:pointer;">Lanjutkan</button>'
                  +     '<button type="button" class="draft-delete" data-id="' + escapeAttr(d.id) + '" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:6px 10px;border-radius:4px;font-size:11px;cursor:pointer;">✕</button>'
                  +   '</div>'
                  + '</div>'
                  + '</div>';
            }).join('');
            container.querySelectorAll('.draft-load').forEach(function(btn) {
                btn.addEventListener('click', function() { loadDraftFromServer(this.dataset.id); });
            });
            container.querySelectorAll('.draft-delete').forEach(function(btn) {
                btn.addEventListener('click', function() { deleteDraftFromServer(this.dataset.id, this); });
            });
        });
    });
    document.getElementById('btn-close-drafts').addEventListener('click', function() {
        document.getElementById('modal-drafts-list').style.display = 'none';
    });

    function loadDraftFromServer(draftId) {
        fetch('{{ url("admin/restock/drafts") }}/' + encodeURIComponent(draftId), { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { alert('❌ Draft tidak ditemukan'); return; }
            loadDraftDataIntoModal(data.draft, draftId);
            document.getElementById('modal-drafts-list').style.display = 'none';
        });
    }

    function deleteDraftFromServer(draftId, btn) {
        if (!confirm('Hapus draft ini?')) return;
        fetch('{{ url("admin/restock/drafts") }}/' + encodeURIComponent(draftId), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) btn.closest('.draft-card').remove();
            else alert('❌ ' + (data.message || 'Gagal hapus'));
        });
    }

    // Auto-save on supplier/rack/notes change
    ['manual-po-supplier', 'manual-po-rack', 'manual-po-notes'].forEach(function(id) {
        document.getElementById(id).addEventListener('input', scheduleAutoSave);
    });

    // === SUPPLIER AUTOCOMPLETE + CREATE-ON-THE-FLY ===
    let supplierSearchTimer = null;
    const supplierInput = document.getElementById('manual-po-supplier');
    const supplierIdInput = document.getElementById('manual-po-supplier-id');
    const supplierResults = document.getElementById('manual-po-supplier-results');
    const supplierStatus = document.getElementById('manual-po-supplier-status');
    const btnAddNewSupplier = document.getElementById('btn-add-new-supplier');

    supplierInput.addEventListener('input', function() {
        // Reset hidden ID kalau user ngetik (jadi tidak terikat ke supplier lama)
        supplierIdInput.value = '';
        supplierStatus.style.color = '#94a3b8';
        supplierStatus.textContent = 'Pilih dari daftar atau ketik nama baru';
        btnAddNewSupplier.style.display = 'none';

        const q = this.value.trim();
        if (supplierSearchTimer) clearTimeout(supplierSearchTimer);
        if (q.length < 2) {
            supplierResults.style.display = 'none';
            return;
        }
        supplierSearchTimer = setTimeout(function() {
            fetch('{{ route("admin.suppliers.search") }}?q=' + encodeURIComponent(q) + '&limit=15', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { renderSupplierResults(q, data.suppliers || []); })
            .catch(function() {});
        }, 250);
    });

    supplierInput.addEventListener('focus', function() {
        if (supplierResults.children.length > 0 && this.value.trim().length >= 2) {
            supplierResults.style.display = 'block';
        }
    });
    supplierInput.addEventListener('blur', function() {
        setTimeout(function() { supplierResults.style.display = 'none'; }, 200);
    });

    function renderSupplierResults(q, suppliers) {
        if (suppliers.length === 0) {
            supplierResults.innerHTML = '<div style="padding:10px 12px;color:#94a3b8;font-size:12px;">Supplier tidak ditemukan.</div>';
            supplierStatus.style.color = '#a16207';
            supplierStatus.textContent = '⚠️ "' + q + '" belum terdaftar.';
            btnAddNewSupplier.style.display = 'inline-block';
        } else {
            supplierResults.innerHTML = suppliers.map(function(s) {
                return '<div class="supplier-result-item" data-id="' + escapeAttr(s.id) + '" data-name="' + escapeAttr(s.name) + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;">'
                  + '<div style="font-weight:600;font-size:13px;">' + escapeAttr(s.name) + '</div>'
                  + '<div style="font-size:11px;color:#64748b;">📞 ' + escapeAttr(s.phone || '-') + (s.contact_person ? ' · ' + escapeAttr(s.contact_person) : '') + '</div>'
                  + '</div>';
            }).join('') + '<div style="padding:8px 12px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;">Tidak ada yang cocok? <button type="button" class="add-new-from-list" style="background:none;border:none;color:#0284c7;text-decoration:underline;cursor:pointer;font-size:11px;padding:0;">+ Tambah baru</button></div>';

            supplierResults.querySelectorAll('.supplier-result-item').forEach(function(item) {
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selectSupplier(this.dataset.id, this.dataset.name);
                });
                item.addEventListener('mouseover', function() { this.style.background = '#f8fafc'; });
                item.addEventListener('mouseout', function() { this.style.background = ''; });
            });
            const addNewBtn = supplierResults.querySelector('.add-new-from-list');
            if (addNewBtn) {
                addNewBtn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    promptCreateNewSupplier(q);
                });
            }
        }
        supplierResults.style.display = 'block';
    }

    function selectSupplier(id, name) {
        supplierIdInput.value = id;
        supplierInput.value = name;
        supplierResults.style.display = 'none';
        supplierStatus.style.color = '#16a34a';
        supplierStatus.textContent = '✓ Supplier terdaftar dipilih';
        btnAddNewSupplier.style.display = 'none';
        scheduleAutoSave();
    }

    btnAddNewSupplier.addEventListener('click', function() {
        const name = supplierInput.value.trim();
        if (name) promptCreateNewSupplier(name);
    });

    function promptCreateNewSupplier(suggestedName) {
        const name = prompt('Nama supplier baru:', suggestedName || '');
        if (!name || !name.trim()) return;
        const phone = prompt('Nomor telepon supplier (wajib):', '');
        if (!phone || !phone.trim()) {
            alert('Nomor telepon wajib diisi.');
            return;
        }
        fetch('{{ route("admin.suppliers.ajax_store") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ name: name.trim(), phone: phone.trim() })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                selectSupplier(data.data.id, data.data.name);
                alert('✅ Supplier "' + data.data.name + '" berhasil ditambahkan.');
            } else {
                alert('❌ ' + (data.message || 'Gagal tambah supplier'));
            }
        })
        .catch(function() { alert('❌ Error koneksi'); });
    }

    if (manualPoForm) manualPoForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const supplierName = document.getElementById('manual-po-supplier').value.trim();
        const items = [];
        document.querySelectorAll('#manual-po-items tr').forEach(function(tr) {
            const product_id = tr.querySelector('.manual-product-id').value.trim();
            const qty = parseInt(tr.querySelector('.manual-product-qty').value || '0', 10);
            const note = tr.querySelector('.manual-product-note').value.trim();
            if (product_id && qty > 0) items.push({ product_id: product_id, qty: qty, note: note });
        });
        if (!supplierName || !items.length) return alert('Isi supplier dan minimal 1 produk (pilih dari hasil pencarian).');
        const payload = { supplier_id: document.getElementById('manual-po-supplier-id').value.trim(), supplier_name: supplierName, rack_id: document.getElementById('manual-po-rack').value.trim(), notes: document.getElementById('manual-po-notes').value.trim(), items: items, force_duplicate: 0 };
        const draftId = document.getElementById('manual-po-draft-id').value;
        const btn = document.getElementById('btn-submit-manual-po');
        const submit = function(forceDuplicate) {
            payload.force_duplicate = forceDuplicate ? 1 : 0;
            btn.disabled = true; btn.textContent = '⏳ Menyimpan...';
            return fetch('{{ route("admin.restock.create_manual_po") }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify(payload) }).then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); });
        };
        submit(false).then(function(res){ if (res.status === 409 && res.data && res.data.code === 'po_duplicate' && showDuplicateConfirm(res.data.conflicts || [])) return submit(true); return res; }).then(function(res){
            btn.disabled = false; btn.textContent = '✓ Simpan PO Manual';
            if (res && res.data && res.data.success) {
                clearDraftLocal();
                if (draftId) {
                    fetch('{{ url("admin/restock/drafts") }}/' + encodeURIComponent(draftId), {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                }
                alert('✅ PO manual berhasil dibuat.');
                window.location.reload();
            } else if (res && res.status === 409) { } else { alert('❌ ' + ((res && res.data && res.data.message) || 'Gagal membuat PO manual')); }
        }).catch(function(){ btn.disabled = false; btn.textContent = '✓ Simpan PO Manual'; alert('❌ Terjadi kesalahan sistem'); });
    });

})();
</script>
@endsection
