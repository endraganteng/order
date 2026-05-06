@extends('admin.layout')

@section('title', 'Daftar Restock - Admin')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #1e293b; font-size: clamp(24px, 5vw, 32px); font-weight: 800;">📦 Daftar Restock</h2>
        <a href="{{ route('admin.restock.orders') }}" class="btn" style="background: var(--color-info); color: white;">📋 Purchase Orders</a>
    </div>
</div>

@php
    $totalRequests = $summary['total_requests'] ?? 0;
    $pendingCount = $summary['pending_count'] ?? 0;
    $orderedCount = $summary['ordered_count'] ?? 0;
    $receivedCount = $summary['received_count'] ?? 0;
    $monthlyPoCount = $summary['monthly_po_count'] ?? 0;
    $avgFulfillment = $summary['avg_fulfillment_hours'] ?? 0;
@endphp

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 24px;">
    <div style="background: white; border-radius: var(--radius-md); padding: 15px; box-shadow: var(--shadow-sm); border-left: 4px solid var(--color-warning);">
        <div style="color: var(--color-text-secondary); font-size: 14px;">Pending</div>
        <div style="font-size: 24px; font-weight: bold; color: var(--color-text);">{{ $pendingCount }}</div>
    </div>
    <div style="background: white; border-radius: var(--radius-md); padding: 15px; box-shadow: var(--shadow-sm); border-left: 4px solid var(--color-info);">
        <div style="color: var(--color-text-secondary); font-size: 14px;">Ordered</div>
        <div style="font-size: 24px; font-weight: bold; color: var(--color-text);">{{ $orderedCount }}</div>
    </div>
    <div style="background: white; border-radius: var(--radius-md); padding: 15px; box-shadow: var(--shadow-sm); border-left: 4px solid var(--color-success);">
        <div style="color: var(--color-text-secondary); font-size: 14px;">Received</div>
        <div style="font-size: 24px; font-weight: bold; color: var(--color-text);">{{ $receivedCount }}</div>
    </div>
    <div style="background: white; border-radius: var(--radius-md); padding: 15px; box-shadow: var(--shadow-sm); border-left: 4px solid var(--color-primary);">
        <div style="color: var(--color-text-secondary); font-size: 14px;">PO Bulan Ini</div>
        <div style="font-size: 24px; font-weight: bold; color: var(--color-text);">{{ $monthlyPoCount }}</div>
    </div>
    <div style="background: white; border-radius: var(--radius-md); padding: 15px; box-shadow: var(--shadow-sm); border-left: 4px solid var(--color-text-muted);">
        <div style="color: var(--color-text-secondary); font-size: 14px;">Avg Fulfillment</div>
        <div style="font-size: 24px; font-weight: bold; color: var(--color-text);">{{ number_format($avgFulfillment, 1) }}h</div>
    </div>
</div>

<div style="background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div>
            <label style="display: inline-flex; align-items: center; cursor: pointer; user-select: none;">
                <input type="checkbox" id="select-all" style="margin-right: 8px;">
                <strong>Select All</strong>
            </label>
            <span style="margin-left: 15px; color: var(--color-text-secondary);" id="selected-count-display">0 selected</span>
        </div>
        <button id="btn-create-po" class="btn" style="background: var(--color-primary); color: white; opacity: 0.6; cursor: not-allowed;" disabled>
            🛒 Buat PO
        </button>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--color-bg); text-align: left; border-bottom: 2px solid var(--color-border);">
                    <th style="padding: 12px; width: 40px;"></th>
                    <th style="padding: 12px;">Produk</th>
                    <th style="padding: 12px;">Rak</th>
                    <th style="padding: 12px; text-align: center;">Qty Aktual</th>
                    <th style="padding: 12px; text-align: center;">Qty Standar</th>
                    <th style="padding: 12px; text-align: center;">Qty Dibutuhkan</th>
                    <th style="padding: 12px;">Pelapor</th>
                    <th style="padding: 12px;">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingItems as $item)
                <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px;">
                        <input type="checkbox" class="js-restock-check" value="{{ $item['id'] }}" 
                               data-product-name="{{ $item['product_name'] }}"
                               data-rack-name="{{ $item['rack_name'] }}"
                               data-qty="{{ $item['qty_needed'] }}">
                    </td>
                    <td style="padding: 12px; font-weight: 500;">{{ $item['product_name'] }}</td>
                    <td style="padding: 12px; color: var(--color-text-secondary);">{{ $item['rack_name'] }}</td>
                    <td style="padding: 12px; text-align: center; color: var(--color-danger); font-weight: bold;">{{ $item['reported_qty'] }}</td>
                    <td style="padding: 12px; text-align: center;">{{ $item['standard_qty'] }}</td>
                    <td style="padding: 12px; text-align: center; color: var(--color-warning); font-weight: bold;">{{ $item['qty_needed'] }}</td>
                    <td style="padding: 12px; color: var(--color-text-secondary);">{{ $item['reported_by_name'] }}</td>
                    <td style="padding: 12px; color: var(--color-text-secondary);">{{ $item['date'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="padding: 20px; text-align: center; color: var(--color-text-muted);">Tidak ada restock pending saat ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Create PO -->
<div id="po-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: var(--radius-lg); width: 90%; max-width: 600px; max-height: 90vh; display: flex; flex-direction: column;">
        <div style="padding: 20px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--color-text);">🛒 Buat Purchase Order</h3>
            <button id="po-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>
        
        <div style="padding: 20px; overflow-y: auto; flex: 1;">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--color-text);">Supplier (Opsional)</label>
                <input type="text" id="po-supplier" style="width: 100%; padding: 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);" placeholder="Nama Supplier">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--color-text);">Notes (Opsional)</label>
                <textarea id="po-notes" rows="3" style="width: 100%; padding: 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);" placeholder="Catatan tambahan..."></textarea>
            </div>
            
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--color-text);">Item yang Dipesan</label>
            <div id="po-items-container" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 15px;">
                <!-- Items injected here by JS -->
            </div>
        </div>
        
        <div style="padding: 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 10px;">
            <button id="po-modal-cancel" class="btn" style="background: white; border: 1px solid var(--color-border); color: var(--color-text);">Batal</button>
            <button id="po-modal-submit" class="btn" style="background: var(--color-primary); color: white; border: none;">Kirim PO</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.js-restock-check');
        const selectAll = document.getElementById('select-all');
        const btnCreatePo = document.getElementById('btn-create-po');
        const countDisplay = document.getElementById('selected-count-display');
        const modal = document.getElementById('po-modal');
        const itemsContainer = document.getElementById('po-items-container');
        
        function updateState() {
            const checkedCount = document.querySelectorAll('.js-restock-check:checked').length;
            const totalCount = checkboxes.length;
            
            countDisplay.textContent = checkedCount + ' selected';
            selectAll.checked = checkedCount > 0 && checkedCount === totalCount;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            
            if (checkedCount > 0) {
                btnCreatePo.style.opacity = '1';
                btnCreatePo.style.cursor = 'pointer';
                btnCreatePo.disabled = false;
            } else {
                btnCreatePo.style.opacity = '0.6';
                btnCreatePo.style.cursor = 'not-allowed';
                btnCreatePo.disabled = true;
            }
        }
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateState);
        });
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => {
                    cb.checked = selectAll.checked;
                });
                updateState();
            });
        }
        
        btnCreatePo.addEventListener('click', function() {
            if (this.disabled) return;
            
            const selected = Array.from(document.querySelectorAll('.js-restock-check:checked'));
            itemsContainer.innerHTML = '';
            
            selected.forEach(cb => {
                const id = cb.value;
                const name = cb.getAttribute('data-product-name');
                const rack = cb.getAttribute('data-rack-name');
                const qty = cb.getAttribute('data-qty');
                
                const itemDiv = document.createElement('div');
                itemDiv.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--color-border);';
                
                itemDiv.innerHTML = `
                    <div style="flex: 1;">
                        <div style="font-weight: 500;">${name}</div>
                        <div style="font-size: 12px; color: var(--color-text-secondary);">${rack}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-size: 13px; color: var(--color-text-secondary);">Qty Pesan:</label>
                        <input type="number" class="po-item-qty" data-id="${id}" value="${qty}" min="1" style="width: 70px; padding: 5px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); text-align: center;">
                    </div>
                `;
                itemsContainer.appendChild(itemDiv);
            });
            
            modal.style.display = 'flex';
        });
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        document.getElementById('po-modal-close').addEventListener('click', closeModal);
        document.getElementById('po-modal-cancel').addEventListener('click', closeModal);
        
        document.getElementById('po-modal-submit').addEventListener('click', function() {
            const supplier = document.getElementById('po-supplier').value;
            const notes = document.getElementById('po-notes').value;
            const items = {};
            
            document.querySelectorAll('.po-item-qty').forEach(input => {
                items[input.getAttribute('data-id')] = input.value;
            });
            
            const submitBtn = this;
            submitBtn.textContent = 'Menyimpan...';
            submitBtn.disabled = true;
            
            fetch('{{ route("admin.restock.create_po") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    supplier: supplier,
                    notes: notes,
                    items: items
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Gagal membuat PO'));
                    submitBtn.textContent = 'Kirim PO';
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan sistem');
                submitBtn.textContent = 'Kirim PO';
                submitBtn.disabled = false;
            });
        });
    });
</script>
@endpush
