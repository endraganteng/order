@extends('admin.layout')

@section('title', 'Kelola Produk Rak - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Kelola Produk Rak: {{ $rack['name'] ?? 'Rak' }}</h2>
            @if(!empty($rack['location']))
                <div style="font-size: 14px; color: var(--color-text-muted); margin-top: 4px;">Lokasi: {{ $rack['location'] }}</div>
            @endif
        </div>
        <a href="{{ route('admin.racks.index') }}" class="btn" style="background: var(--color-border);">Kembali</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @push('styles')
    <style>
        .product-list-card {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .product-list-header {
            display: flex;
            padding: 16px;
            background: var(--color-bg);
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            color: var(--color-text-muted);
            font-size: 13px;
            text-transform: uppercase;
        }
        .product-list-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--color-border);
            transition: background 0.2s;
        }
        .product-list-item:last-child {
            border-bottom: none;
        }
        .product-list-item:hover {
            background: #f8fafc;
        }
        .product-list-item.selected {
            background: #f0fdf4;
        }
        .col-checkbox {
            width: 50px;
            display: flex;
            justify-content: center;
        }
        .col-name {
            flex: 1;
            font-weight: 600;
            color: var(--color-text);
        }
        .col-qty {
            width: 180px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .col-min-qty {
            width: 180px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .qty-input {
            width: 80px;
            padding: 6px 8px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            text-align: center;
        }
        .qty-input:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        .qty-input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        
        @media (max-width: 600px) {
            .product-list-header {
                display: none;
            }
            .product-list-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                position: relative;
                padding-left: 50px;
            }
            .col-checkbox {
                position: absolute;
                left: 16px;
                top: 16px;
                width: auto;
            }
            .col-name {
                width: 100%;
            }
            .col-qty, .col-min-qty {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
    @endpush

    <form method="POST" action="{{ route('admin.racks.products.save', $rack['id']) }}" id="rackProductForm">
        @csrf
        
        <div class="product-list-card mb-4">
            <div class="product-list-header">
                <div class="col-checkbox">
                    <input type="checkbox" id="selectAll" title="Pilih Semua">
                </div>
                <div class="col-name">Nama Produk Master</div>
                <div class="col-qty">Target Qty</div>
                <div class="col-min-qty">Min Qty (Restock)</div>
            </div>
            
            <div id="productList">
                @forelse($allProducts as $product)
                    @php
                        $productId = $product['id'];
                        $isAssigned = in_array($productId, $assignedProductIds);
                        
                        // Find assigned standard_qty and min_qty if assigned
                        $qty = $product['standard_qty'] ?? 0;
                        $minQty = 0;
                        if ($isAssigned) {
                            foreach ($rackProducts as $rp) {
                                if ($rp['id'] === $productId) {
                                    $qty = $rp['standard_qty'];
                                    $minQty = $rp['min_qty'] ?? 0;
                                    break;
                                }
                            }
                        }
                    @endphp
                    <div class="product-list-item {{ $isAssigned ? 'selected' : '' }}">
                        <div class="col-checkbox">
                            <input type="checkbox" class="js-product-check" name="product_ids[]" value="{{ $productId }}" {{ $isAssigned ? 'checked' : '' }}>
                        </div>
                        <div class="col-name">
                            {{ $product['name'] }}
                        </div>
                        <div class="col-qty">
                            <input type="number" class="qty-input js-qty-input" name="quantities[{{ $productId }}]" value="{{ $qty }}" min="0" {{ $isAssigned ? '' : 'disabled' }}>
                            <span style="font-size: 13px; color: var(--color-text-muted);">{{ $product['unit'] ?? 'pcs' }}</span>
                        </div>
                        <div class="col-min-qty">
                            <input type="number" class="qty-input js-min-qty-input" name="min_quantities[{{ $productId }}]" value="{{ $minQty }}" min="0" {{ $isAssigned ? '' : 'disabled' }} placeholder="0">
                            <span style="font-size: 13px; color: var(--color-text-muted);">{{ $product['unit'] ?? 'pcs' }}</span>
                        </div>
                    </div>
                @empty
                    <div style="padding: 30px; text-align: center; color: var(--color-text-muted);">
                        Belum ada produk master yang aktif. Silakan tambahkan di menu Master Produk terlebih dahulu.
                    </div>
                @endforelse
            </div>
        </div>
        
        @if(count($allProducts) > 0)
            <div style="display: flex; justify-content: flex-end; position: sticky; bottom: 20px;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px; box-shadow: var(--shadow-md);">Simpan Produk Rak</button>
            </div>
        @endif
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.js-product-check');
            
            // Handle individual checkbox change
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const row = this.closest('.product-list-item');
                    const qtyInput = row.querySelector('.js-qty-input');
                    const minQtyInput = row.querySelector('.js-min-qty-input');
                    
                    if (this.checked) {
                        row.classList.add('selected');
                        qtyInput.disabled = false;
                        if (minQtyInput) minQtyInput.disabled = false;
                        if (qtyInput.value === '0' || qtyInput.value === '') {
                            setTimeout(() => qtyInput.focus(), 50);
                        }
                    } else {
                        row.classList.remove('selected');
                        qtyInput.disabled = true;
                        if (minQtyInput) minQtyInput.disabled = true;
                    }
                    
                    updateSelectAllState();
                });
            });
            
            // Handle select all
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const isChecked = this.checked;
                    checkboxes.forEach(cb => {
                        cb.checked = isChecked;
                        // Trigger change event to update row styling and input disabled state
                        cb.dispatchEvent(new Event('change'));
                    });
                });
                
                // Initial state
                updateSelectAllState();
            }
            
            function updateSelectAllState() {
                if (!selectAll) return;
                const total = checkboxes.length;
                const checked = document.querySelectorAll('.js-product-check:checked').length;
                
                if (total === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if (checked === total) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else if (checked === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }
        });
    </script>
@endsection