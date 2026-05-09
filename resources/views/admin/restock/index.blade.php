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
    <a href="{{ route('admin.restock.orders') }}" class="btn" style="background: var(--color-info); color: white;">📋 Purchase Orders</a>
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

    if (!pool) return; // empty state

    let laneCounter = 0;
    let draggedCard = null;
    let touchClone = null;
    let suppliersData = [];

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

            fetch('{{ route("admin.restock.create_batch_po") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ orders: orders })
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.orders) {
                    const poSuccessList = document.getElementById('po-success-list');
                    poSuccessList.innerHTML = ''; // clear
                    
                    const today = new Date().toLocaleDateString('id-ID');

                    data.orders.forEach(function(order) {
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
                } else if (data.success) {
                    alert('✅ Berhasil membuat ' + (data.po_count || orders.length) + ' Purchase Order!');
                    window.location.reload();
                } else {
                    alert('❌ Error: ' + (data.message || 'Gagal membuat PO'));
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

})();
</script>
@endsection
