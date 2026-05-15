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
        .col-live-qty {
            width: 140px;
            font-size: 13px;
            color: var(--color-text);
            font-weight: 700;
        }
        .col-last-update {
            width: 180px;
            font-size: 12px;
            color: var(--color-text-muted);
        }
        .stock-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
        }
        .stock-chip.shortage {
            background: var(--color-danger-bg);
            color: var(--color-danger);
        }
        .stock-chip.ok {
            background: var(--color-success-bg);
            color: var(--color-success);
        }
        .stock-chip.unknown {
            background: var(--color-bg);
            color: var(--color-text-muted);
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

        /* Section headings */
        .section-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .section-heading h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--color-text);
        }
        .count-chip {
            display: inline-block;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-muted);
            padding: 2px 10px;
        }

        /* Row pending remove */
        .product-list-item.row-pending-remove {
            opacity: 0.45;
            background: #fff7f7;
        }
        .pending-remove-chip {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            color: var(--color-danger, #dc2626);
            background: var(--color-danger-bg, #fee2e2);
            border-radius: 999px;
            padding: 2px 8px;
            margin-left: 8px;
            white-space: nowrap;
        }

        /* Section B collapsible */
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
            font-weight: 600;
            color: var(--color-text);
            font-size: 15px;
        }
        .section-header:hover {
            background: #f1f5f9;
        }
        .section-header .chevron {
            display: inline-block;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            transition: transform 0.2s;
            color: var(--color-text-muted);
        }
        .section-header.open .chevron {
            transform: rotate(180deg);
        }
        .section-b-body {
            display: none;
            margin-top: 8px;
        }
        .section-b-body.open {
            display: block;
        }

        /* Search bar */
        .search-bar {
            padding: 14px 16px;
            border-bottom: 1px solid var(--color-border);
        }
        .search-bar input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }
        .search-bar input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        /* Add product list counter */
        .add-product-counter {
            padding: 8px 16px;
            font-size: 12px;
            color: var(--color-text-muted);
            border-bottom: 1px solid var(--color-border);
            background: var(--color-bg);
        }

        /* Empty state */
        .empty-state {
            padding: 30px;
            text-align: center;
            color: var(--color-text-muted);
        }
        .empty-state a {
            color: var(--color-primary, #2563eb);
            text-decoration: underline;
            cursor: pointer;
        }

        /* Bottom action bar */
        .action-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            position: sticky;
            bottom: 20px;
        }
        .status-chip {
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text-muted);
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: 999px;
            padding: 6px 14px;
            box-shadow: var(--shadow-sm);
            white-space: nowrap;
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
            .col-qty, .col-min-qty, .col-live-qty, .col-last-update {
                width: 100%;
                justify-content: flex-start;
            }
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .status-chip {
                text-align: center;
            }
        }
    </style>
    @endpush

    <form method="POST" action="{{ route('admin.racks.products.save', $rack['id']) }}" id="rackProductForm">
        @csrf

        {{-- ======================= SECTION A: Produk Ter-assign ======================= --}}
        <div class="section-heading">
            <h3>Produk Ter-assign ke Rak</h3>
            <span class="count-chip" id="assignedCounter">{{ count($rackProducts) }} produk</span>
        </div>

        <div class="product-list-card mb-4">
            <div class="product-list-header">
                <div class="col-checkbox">
                    <input type="checkbox" id="selectAll" title="Pilih Semua">
                </div>
                <div class="col-name">Nama Produk Master</div>
                <div class="col-qty">Target Qty</div>
                <div class="col-min-qty">Min Qty (Restock)</div>
                <div class="col-live-qty">Live Qty</div>
                <div class="col-last-update">Update Terakhir</div>
            </div>

            <div id="assignedList">
                @forelse($rackProducts as $product)
                    @php
                        $productId = $product['id'];
                        $qty = $product['standard_qty'] ?? 0;
                        $minQty = $product['min_qty'] ?? 0;

                        $liveData = $liveStockMap[$productId] ?? null;
                        $currentQty = is_array($liveData) ? ($liveData['current_qty'] ?? null) : null;
                        $lastUpdatedAt = is_array($liveData) ? (int) ($liveData['last_updated_at'] ?? 0) : 0;
                        $isShortage = is_array($liveData) ? (bool) ($liveData['is_shortage'] ?? false) : false;
                        $lastUpdatedLabel = $lastUpdatedAt > 0 ? date('d M Y H:i', $lastUpdatedAt) : '-';
                    @endphp
                    <div class="product-list-item selected js-assigned-row" data-product-name="{{ strtolower($product['name']) }}">
                        <div class="col-checkbox">
                            <input type="checkbox" class="js-product-check js-assigned-check" name="product_ids[]" value="{{ $productId }}" checked>
                        </div>
                        <div class="col-name">
                            {{ $product['name'] }}
                            <span class="pending-remove-chip" style="display:none;">Akan dihapus dari rak</span>
                        </div>
                        <div class="col-qty">
                            <input type="number" class="qty-input js-qty-input" name="quantities[{{ $productId }}]" value="{{ $qty }}" min="0">
                            <span style="font-size: 13px; color: var(--color-text-muted);">{{ $product['unit'] ?? 'pcs' }}</span>
                        </div>
                        <div class="col-min-qty">
                            <input type="number" class="qty-input js-min-qty-input" name="min_quantities[{{ $productId }}]" value="{{ $minQty }}" min="0" placeholder="0">
                            <span style="font-size: 13px; color: var(--color-text-muted);">{{ $product['unit'] ?? 'pcs' }}</span>
                        </div>
                        <div class="col-live-qty">
                            @if($currentQty !== null)
                                <span class="stock-chip {{ $isShortage ? 'shortage' : 'ok' }}">
                                    {{ $currentQty }} {{ $product['unit'] ?? 'pcs' }}
                                </span>
                            @else
                                <span class="stock-chip unknown">Belum ada data</span>
                            @endif
                        </div>
                        <div class="col-last-update">
                            {{ $lastUpdatedLabel }}
                        </div>
                    </div>
                @empty
                    <div class="empty-state" id="assignedEmptyState">
                        Belum ada produk ter-assign di rak ini.
                        <br>
                        <a onclick="openSectionB()">Tambah produk dari section di bawah</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ======================= SECTION B: Tambah Produk ke Rak ======================= --}}
        @php
            $unassignedProducts = array_filter($allProducts, function($p) use ($assignedProductIds) {
                return !in_array($p['id'], $assignedProductIds);
            });
            $unassignedCount = count($unassignedProducts);
        @endphp

        <div class="mb-4">
            <div class="section-header" id="sectionBHeader" onclick="toggleSectionB()">
                <svg class="chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.085l3.71-3.755a.75.75 0 111.08 1.04l-4.25 4.3a.75.75 0 01-1.08 0l-4.25-4.3a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
                <span>Tambah Produk ke Rak <span id="sectionBCountLabel">({{ $unassignedCount }} produk tersedia)</span></span>
            </div>

            <div class="section-b-body product-list-card" id="sectionBBody">
                <div class="search-bar">
                    <input type="text" id="searchUnassigned" placeholder="Cari nama produk..." oninput="filterUnassigned(this.value)">
                </div>
                <div class="add-product-counter" id="addProductCounter">
                    <span id="addCountVisible">{{ $unassignedCount }}</span> dari {{ $unassignedCount }} produk
                </div>

                <div id="addProductList">
                    @forelse($unassignedProducts as $product)
                        @php
                            $productId = $product['id'];
                            $defaultQty = $product['standard_qty'] ?? 0;
                        @endphp
                        <div class="product-list-item js-add-row" data-product-name="{{ strtolower($product['name']) }}">
                            <div class="col-checkbox">
                                <input type="checkbox" class="js-product-check js-add-check" name="product_ids[]" value="{{ $productId }}">
                            </div>
                            <div class="col-name">
                                {{ $product['name'] }}
                            </div>
                            <div class="col-qty">
                                <input type="number" class="qty-input js-qty-input" name="quantities[{{ $productId }}]" value="{{ $defaultQty }}" min="0" disabled>
                                <span style="font-size: 13px; color: var(--color-text-muted);">{{ $product['unit'] ?? 'pcs' }}</span>
                            </div>
                            <div class="col-min-qty">
                                <input type="number" class="qty-input js-min-qty-input" name="min_quantities[{{ $productId }}]" value="0" min="0" placeholder="0" disabled>
                                <span style="font-size: 13px; color: var(--color-text-muted);">{{ $product['unit'] ?? 'pcs' }}</span>
                            </div>
                            <div class="col-live-qty"></div>
                            <div class="col-last-update"></div>
                        </div>
                    @empty
                        <div class="empty-state">
                            Semua produk master sudah ter-assign ke rak ini.
                        </div>
                    @endforelse
                </div>

                <div class="empty-state" id="searchEmptyState" style="display:none;">
                    Tidak ada produk cocok.
                </div>
            </div>
        </div>

        {{-- ======================= Bottom Action Bar ======================= --}}
        @if(count($allProducts) > 0)
            <div class="action-bar">
                <span class="status-chip" id="statusChip">Memuat...</span>
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px; box-shadow: var(--shadow-md);">Simpan Produk Rak</button>
            </div>
        @endif
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ---- Select All (Section A only) ----
            const selectAll = document.getElementById('selectAll');
            const assignedChecks = document.querySelectorAll('.js-assigned-check');

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const isChecked = this.checked;
                    assignedChecks.forEach(cb => {
                        cb.checked = isChecked;
                        cb.dispatchEvent(new Event('change'));
                    });
                });
                updateSelectAllState();
            }

            function updateSelectAllState() {
                if (!selectAll) return;
                const total = assignedChecks.length;
                const checked = document.querySelectorAll('.js-assigned-check:checked').length;
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

            // ---- Section A: assigned checkbox change ----
            assignedChecks.forEach(cb => {
                cb.addEventListener('change', function() {
                    const row = this.closest('.product-list-item');
                    const qtyInput = row.querySelector('.js-qty-input');
                    const minQtyInput = row.querySelector('.js-min-qty-input');
                    const removeChip = row.querySelector('.pending-remove-chip');

                    if (this.checked) {
                        row.classList.add('selected');
                        row.classList.remove('row-pending-remove');
                        if (removeChip) removeChip.style.display = 'none';
                        if (qtyInput) qtyInput.disabled = false;
                        if (minQtyInput) minQtyInput.disabled = false;
                        if (qtyInput && (qtyInput.value === '0' || qtyInput.value === '')) {
                            setTimeout(() => qtyInput.focus(), 50);
                        }
                    } else {
                        row.classList.remove('selected');
                        row.classList.add('row-pending-remove');
                        if (removeChip) removeChip.style.display = 'inline-block';
                        if (qtyInput) qtyInput.disabled = true;
                        if (minQtyInput) minQtyInput.disabled = true;
                    }

                    updateSelectAllState();
                    updateStatusChip();
                });
            });

            // ---- Section B: add checkbox change ----
            const addChecks = document.querySelectorAll('.js-add-check');
            addChecks.forEach(cb => {
                cb.addEventListener('change', function() {
                    const row = this.closest('.product-list-item');
                    const qtyInput = row.querySelector('.js-qty-input');
                    const minQtyInput = row.querySelector('.js-min-qty-input');

                    if (this.checked) {
                        row.classList.add('selected');
                        if (qtyInput) qtyInput.disabled = false;
                        if (minQtyInput) minQtyInput.disabled = false;
                        if (qtyInput && (qtyInput.value === '0' || qtyInput.value === '')) {
                            setTimeout(() => qtyInput.focus(), 50);
                        }
                    } else {
                        row.classList.remove('selected');
                        if (qtyInput) qtyInput.disabled = true;
                        if (minQtyInput) minQtyInput.disabled = true;
                    }

                    updateStatusChip();
                });
            });

            // ---- Status chip ----
            function updateStatusChip() {
                const chip = document.getElementById('statusChip');
                if (!chip) return;

                const totalAssigned = assignedChecks.length;
                const keptCount = document.querySelectorAll('.js-assigned-check:checked').length;
                const removedCount = totalAssigned - keptCount;
                const addedCount = document.querySelectorAll('.js-add-check:checked').length;

                chip.textContent = keptCount + ' tetap • ' + removedCount + ' dihapus • ' + addedCount + ' ditambah';
            }

            updateStatusChip();

            // ---- Section B toggle ----
            window.toggleSectionB = function() {
                const header = document.getElementById('sectionBHeader');
                const body = document.getElementById('sectionBBody');
                header.classList.toggle('open');
                body.classList.toggle('open');
            };

            window.openSectionB = function() {
                const header = document.getElementById('sectionBHeader');
                const body = document.getElementById('sectionBBody');
                header.classList.add('open');
                body.classList.add('open');
                body.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };

            // ---- Search unassigned ----
            const totalUnassigned = document.querySelectorAll('.js-add-row').length;

            window.filterUnassigned = function(query) {
                const q = query.toLowerCase().trim();
                const rows = document.querySelectorAll('.js-add-row');
                let visible = 0;

                rows.forEach(row => {
                    const name = row.getAttribute('data-product-name') || '';
                    const match = name.includes(q);
                    row.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                const counter = document.getElementById('addCountVisible');
                if (counter) counter.textContent = visible;

                const emptySearch = document.getElementById('searchEmptyState');
                const addList = document.getElementById('addProductList');
                if (emptySearch && addList) {
                    if (visible === 0 && rows.length > 0) {
                        emptySearch.style.display = 'block';
                    } else {
                        emptySearch.style.display = 'none';
                    }
                }
            };
        });
    </script>
@endsection
