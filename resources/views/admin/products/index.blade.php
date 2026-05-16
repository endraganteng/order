@extends('admin.layout')

@section('title', 'Master Produk - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Master Produk</h2>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn" style="background: #dc2626; color: #fff;" onclick="confirmResetProducts()">Reset Produk</button>
            <button type="button" class="btn" style="background: var(--color-border);" onclick="toggleSelectMode()" id="btnSelectMode">Pilih</button>
            <a href="{{ route('admin.product_categories.index') }}" class="btn" style="background: var(--color-border);">Kategori</a>
            <a href="{{ route('admin.products.bulk_assign') }}" class="btn" style="background: var(--color-info); color: #fff;">Assign Massal</a>
            <button type="button" class="btn" style="background: #059669; color: #fff;" onclick="openImportModal()">Import Excel</button>
            <button type="button" class="btn btn-primary" onclick="openProductModal()">+ Tambah Produk</button>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    @php
        $activeCatLabel = '';
        if ($categoryFilter === '__none__') {
            $activeCatLabel = 'Tanpa Kategori';
        } elseif ($categoryFilter !== '') {
            $activeCatLabel = $categoryMap[$categoryFilter] ?? '';
        }
    @endphp
    <div style="margin-bottom: 16px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <form id="filterForm" method="GET" action="{{ route('admin.products.index') }}" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
                <div style="position: relative; flex: 1; max-width: 350px; min-width: 200px;">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari produk..."
                        id="productSearchInput"
                        autocomplete="off"
                        style="padding: 9px 36px 9px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); font-size: 14px; width: 100%; box-sizing: border-box;">
                    <span id="searchSpinner" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; border: 2px solid #cbd5e1; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.7s linear infinite; align-items: center; justify-content: center;"></span>
                </div>
                @if(count($categories ?? []) > 0)
                <select name="category" id="productCategoryFilter" onchange="document.getElementById('filterForm').submit()"
                    style="padding: 9px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); font-size: 14px; min-width: 160px;">
                    <option value="">Semua Kategori</option>
                    <option value="__none__" {{ $categoryFilter === '__none__' ? 'selected' : '' }}>Tanpa Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat['id'] }}" {{ $categoryFilter === (string)$cat['id'] ? 'selected' : '' }}>{{ $cat['name'] }}</option>
                    @endforeach
                </select>
                @endif
                <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-sm" id="resetAllBtn" style="background: var(--color-border); {{ ($search === '' && $categoryFilter === '') ? 'display:none;' : '' }}">Reset semua</a>
            </form>
            <span id="productCountSpan" style="font-size: 13px; color: var(--color-text-muted);">{{ $totalFiltered }} produk</span>
        </div>

        <div id="activeFilterChips" style="margin-top: 10px; display: {{ ($search !== '' || $categoryFilter !== '') ? 'flex' : 'none' }}; gap: 8px; align-items: center; flex-wrap: wrap;">
            @if($search !== '' || $categoryFilter !== '')
                <span style="font-size: 12px; color: var(--color-text-muted); font-weight: 600;">Filter aktif:</span>
                @if($search !== '')
                    <a href="{{ route('admin.products.index', array_filter(['category' => $categoryFilter])) }}"
                       data-clear="search"
                       style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #dbeafe; color: #1e40af; font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #93c5fd;"
                       title="Hapus filter pencarian">
                        🔍 "{{ $search }}" <span style="font-weight: 700;">×</span>
                    </a>
                @endif
                @if($categoryFilter !== '')
                    <a href="{{ route('admin.products.index', array_filter(['search' => $search])) }}"
                       data-clear="category"
                       style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #fbbf24;"
                       title="Hapus filter kategori">
                        📁 {{ $activeCatLabel ?: 'Kategori' }} <span style="font-weight: 700;">×</span>
                    </a>
                @endif
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @push('styles')
    <style>
        /* Desktop table */
        .product-table-desktop {
            display: block;
        }

        /* Mobile card layout */
        .product-cards-mobile {
            display: none;
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: #fff;
            padding: 24px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--color-border);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--color-text);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            color: var(--color-text-muted);
            padding: 0;
            margin: 0;
        }
        .modal-close:hover {
            color: var(--color-text);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--color-border);
        }

        /* Bulk action bar */
        .bulk-action-bar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            margin-bottom: 16px;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: var(--shadow-sm);
        }
        .bulk-action-bar.show {
            display: flex;
        }
        .bulk-action-bar .bulk-count {
            font-weight: 700;
            font-size: 14px;
            color: #92400e;
        }
        .bulk-action-bar .btn-bulk-delete {
            background: var(--color-danger);
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .bulk-action-bar .btn-bulk-delete:hover {
            opacity: 0.9;
        }
        .bulk-action-bar .btn-bulk-cancel {
            background: transparent;
            border: 1px solid #d97706;
            color: #92400e;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Checkbox styling */
        .product-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--color-primary);
        }

        .pagination-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            padding: 16px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .pagination-bar .page-btn {
            min-width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: #fff;
            font-size: 14px;
            font-weight: 500;
            color: var(--color-text);
            cursor: pointer;
            text-decoration: none;
            padding: 0 10px;
            transition: all 0.15s;
        }
        .pagination-bar .page-btn:hover {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
        }
        .pagination-bar .page-btn.active {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
            pointer-events: none;
        }
        .pagination-bar .page-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        .pagination-bar .page-info {
            font-size: 13px;
            color: var(--color-text-muted);
            margin: 0 8px;
        }

        @media (max-width: 900px) {
            .product-table-desktop {
                display: none;
            }
            .product-cards-mobile {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .product-mobile-card {
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 14px;
                background: #fff;
                box-shadow: var(--shadow-sm);
            }
            .product-mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 10px;
            }
            .product-mobile-name {
                font-weight: 700;
                color: var(--color-text);
                font-size: 15px;
            }
            .product-mobile-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 10px;
                font-size: 13px;
            }
            .product-mobile-field-label {
                font-size: 12px;
                font-weight: 600;
                color: var(--color-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .product-mobile-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                padding-top: 10px;
                border-top: 1px solid var(--color-border);
            }
        }

        /* AJAX live search */
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
        #searchSpinner { display: none; }
        #searchSpinner[style*="display: inline-flex"] { display: inline-flex !important; }
        #productSearchInput.searching { background-color: #fafafa; }
    </style>
    @endpush



    {{-- Bulk Action Bar --}}
    <div class="bulk-action-bar" id="bulkActionBar">
        <span class="bulk-count" id="bulkCount">0 produk dipilih</span>
        <button type="button" class="btn-bulk-delete" onclick="bulkDeleteSelected()">🗑️ Hapus Terpilih</button>
        <button type="button" class="btn-bulk-cancel" onclick="clearSelection()">Batal Pilih</button>
        <label style="margin-left: auto; display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #92400e; cursor: pointer;">
            <input type="checkbox" id="selectAllCheckbox" class="product-checkbox" onchange="toggleSelectAll(this.checked)">
            Pilih Semua
        </label>
    </div>

    {{-- Desktop Table --}}
    <div class="card product-table-desktop" style="padding: 0; overflow: hidden;">
        <div class="table-scroll" style="padding: 16px;">
            <table>
                <thead>
                    <tr>
                        <th class="js-select-col" style="display: none; width: 40px;"><input type="checkbox" class="product-checkbox" onchange="toggleSelectAll(this.checked)"></th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Qty Standar</th>
                        <th>Satuan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="productsTbody">
                    @forelse($products as $product)
                        @php
                            $productId = (string) ($product['id'] ?? '');
                            $productName = (string) ($product['name'] ?? '-');
                            $productCategoryId = (string) ($product['category_id'] ?? '');
                            $productCategoryName = $productCategoryId !== '' ? ($categoryMap[$productCategoryId] ?? '-') : '';
                            $standardQty = (int) ($product['standard_qty'] ?? 0);
                            $unit = (string) ($product['unit'] ?? 'pcs');
                            $isActive = ($product['is_active'] ?? true) === true;
                        @endphp
                        <tr class="js-product-row" data-category="{{ $productCategoryId }}" data-product-id="{{ $productId }}" data-name="{{ $productName }}">
                            <td class="js-select-col" style="display: none;"><input type="checkbox" class="product-checkbox js-product-select" value="{{ $productId }}" onchange="updateBulkCount()"></td>
                            <td>
                                <div style="font-weight: 600;">{{ $productName }}</div>
                            </td>
                            <td>
                                @if($productCategoryName)
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #eef2ff; color: #4338ca;">{{ $productCategoryName }}</span>
                                @else
                                    <span style="color: var(--color-text-muted); font-size: 13px;">-</span>
                                @endif
                            </td>
                            <td>{{ $standardQty }}</td>
                            <td>{{ $unit }}</td>
                            <td>
                                @if($isActive)
                                    <span class="badge-status active">Aktif</span>
                                @else
                                    <span class="badge-status inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <a href="{{ route('admin.products.audit_trail', $productId) }}" class="btn btn-sm" style="background:#e0e7ff;color:#3730a3;">📜 Audit</a>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openProductModal('{{ $productId }}', '{{ addslashes($productName) }}', {{ $standardQty }}, '{{ addslashes($unit) }}', {{ $isActive ? 'true' : 'false' }}, '{{ $productCategoryId }}')">Edit</button>
                                    <form method="POST" action="{{ route('admin.products.destroy', $productId) }}" data-confirm="Yakin hapus produk ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 24px;">
                                @if($search !== '' || $categoryFilter !== '')
                                    <div style="font-size: 14px; margin-bottom: 6px;">🔍 Tidak ada produk yang cocok dengan filter aktif.</div>
                                    <div style="font-size: 13px;">Coba <a href="{{ route('admin.products.index') }}" style="color: #2563eb; font-weight: 600;">reset filter</a> atau ubah kata kunci.</div>
                                @else
                                    Belum ada data produk. Silakan tambah produk master.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Card Layout --}}
    <div class="product-cards-mobile" id="productsCards">
        @forelse($products as $product)
            @php
                $productId = (string) ($product['id'] ?? '');
                $productName = (string) ($product['name'] ?? '-');
                $productCategoryId = (string) ($product['category_id'] ?? '');
                $productCategoryName = $productCategoryId !== '' ? ($categoryMap[$productCategoryId] ?? '-') : '';
                $standardQty = (int) ($product['standard_qty'] ?? 0);
                $unit = (string) ($product['unit'] ?? 'pcs');
                $isActive = ($product['is_active'] ?? true) === true;
            @endphp
            <div class="product-mobile-card js-product-card" data-category="{{ $productCategoryId }}" data-product-id="{{ $productId }}" data-name="{{ $productName }}">
                <div class="product-mobile-header">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" class="product-checkbox js-product-select-mobile" value="{{ $productId }}" onchange="updateBulkCount()" style="display: none; margin-top: 3px;">
                        <div>
                            <div class="product-mobile-name">{{ $productName }}</div>
                            @if($productCategoryName)
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #eef2ff; color: #4338ca; margin-top: 4px;">{{ $productCategoryName }}</span>
                            @endif
                        </div>
                    </div>
                    @if($isActive)
                        <span class="badge-status active">Aktif</span>
                    @else
                        <span class="badge-status inactive">Nonaktif</span>
                    @endif
                </div>
                <div class="product-mobile-grid">
                    <div>
                        <div class="product-mobile-field-label">Qty Standar</div>
                        <div>{{ $standardQty }}</div>
                    </div>
                    <div>
                        <div class="product-mobile-field-label">Satuan</div>
                        <div>{{ $unit }}</div>
                    </div>
                </div>
                <div class="product-mobile-actions">
                    <a href="{{ route('admin.products.audit_trail', $productId) }}" class="btn btn-sm" style="background:#e0e7ff;color:#3730a3;">📜 Audit</a>
                    <button type="button" class="btn btn-warning btn-sm" onclick="openProductModal('{{ $productId }}', '{{ addslashes($productName) }}', {{ $standardQty }}, '{{ addslashes($unit) }}', {{ $isActive ? 'true' : 'false' }}, '{{ $productCategoryId }}')">Edit</button>
                    <form method="POST" action="{{ route('admin.products.destroy', $productId) }}" data-confirm="Yakin hapus produk ini?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty">
                @if($search !== '' || $categoryFilter !== '')
                    <div style="font-size: 14px; margin-bottom: 6px;">🔍 Tidak ada produk yang cocok dengan filter aktif.</div>
                    <div style="font-size: 13px;">Coba <a href="{{ route('admin.products.index') }}" style="color: #2563eb; font-weight: 600;">reset filter</a> atau ubah kata kunci.</div>
                @else
                    Belum ada data produk. Silakan tambah produk master.
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="pagination-bar" id="paginationWrap" style="display: {{ $totalPages > 1 ? 'flex' : 'none' }};">
        @if($totalPages > 1)
        @php
            $baseParams = array_filter(['search' => $search, 'category' => $categoryFilter, 'per_page' => $perPage != 50 ? $perPage : null]);
        @endphp

        <a href="{{ route('admin.products.index', array_merge($baseParams, ['page' => $page - 1])) }}"
           data-page="{{ $page - 1 }}"
           class="page-btn {{ $page <= 1 ? 'disabled' : '' }}">&laquo;</a>

        @for($p = 1; $p <= $totalPages; $p++)
            @if($p == 1 || $p == $totalPages || abs($p - $page) <= 2)
                <a href="{{ route('admin.products.index', array_merge($baseParams, ['page' => $p])) }}"
                   data-page="{{ $p }}"
                   class="page-btn {{ $p == $page ? 'active' : '' }}">{{ $p }}</a>
            @elseif($p == 2 && $page > 4)
                <span class="page-info">...</span>
            @elseif($p == $totalPages - 1 && $page < $totalPages - 3)
                <span class="page-info">...</span>
            @endif
        @endfor

        <a href="{{ route('admin.products.index', array_merge($baseParams, ['page' => $page + 1])) }}"
           data-page="{{ $page + 1 }}"
           class="page-btn {{ $page >= $totalPages ? 'disabled' : '' }}">&raquo;</a>

        <span class="page-info">Hal {{ $page }}/{{ $totalPages }}</span>
        @endif
    </div>

    {{-- Product Modal --}}
    <div class="modal-overlay" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="productModalTitle">Tambah Produk</h3>
                <button type="button" class="modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <form id="productForm" method="POST" action="{{ route('admin.products.store') }}">
                @csrf
                <div id="methodField"></div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="productName">Nama Produk *</label>
                    <input type="text" id="productName" name="name" class="form-input" required maxlength="120">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="productCategory">Kategori</label>
                    <select id="productCategory" name="category_id" class="form-select" onchange="onCategorySelectChange(this)">
                        <option value="">-- Tanpa Kategori --</option>
                        @foreach($categories ?? [] as $cat)
                            <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                        <option value="__new__">+ Tambah Kategori Baru...</option>
                    </select>
                    <div id="newCategoryInline" style="display: none; margin-top: 8px;">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="text" id="newCategoryName" class="form-input" placeholder="Nama kategori baru" maxlength="100" style="flex: 1;">
                            <button type="button" class="btn btn-primary btn-sm" id="btnSaveNewCategory" onclick="saveNewCategoryInline()">Simpan</button>
                            <button type="button" class="btn btn-sm" style="background: var(--color-border);" onclick="cancelNewCategory()">Batal</button>
                        </div>
                        <div id="newCategoryError" style="color: var(--color-danger); font-size: 12px; margin-top: 4px; display: none;"></div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="productQty">Qty Standar *</label>
                    <input type="number" id="productQty" name="standard_qty" class="form-input" required min="0" value="0">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="productUnit">Satuan *</label>
                    <input type="text" id="productUnit" name="unit" class="form-input" required maxlength="30" value="pcs">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="productIsActive" name="is_active" value="1" checked>
                        <span>Produk Aktif</span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background: var(--color-border);" onclick="closeProductModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveProduct">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Import Modal --}}
    <div class="modal-overlay" id="importModal">
        <div class="modal-content" style="max-width: 500px;">
            <h3 style="margin: 0 0 8px;">Import Produk dari Excel</h3>
            <p style="font-size: 13px; color: var(--color-text-secondary); margin: 0 0 16px;">
                Upload file Excel export dari Olsera (.xlsx). Kolom yang diimport: <strong>Nama</strong> (A) + <strong>Variant</strong> (F) + <strong>Kategori</strong> (D).
                Nama produk = Nama + Variant (jika ada). Duplikat dilewati. Kategori baru otomatis dibuat.
            </p>

            <form id="importForm" enctype="multipart/form-data">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 6px;">File Excel (.xlsx)</label>
                    <input type="file" name="excel_file" id="importFileInput" accept=".xlsx,.xls" required
                        style="width: 100%; padding: 8px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 6px;">Default Qty Standar</label>
                    <input type="number" name="default_standard_qty" id="importDefaultQty" value="0" min="0"
                        style="width: 100%; padding: 8px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                    <small style="font-size: 12px; color: var(--color-text-muted); margin-top: 4px; display: block;">
                        Qty standar default untuk semua produk yang diimport. Bisa diubah nanti per produk.
                    </small>
                </div>

                <div id="importProgress" style="display: none; margin-bottom: 16px;">
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-size: 14px; font-weight: 600; color: #0369a1;" id="importProgressLabel">Mengupload file...</span>
                            <span style="font-size: 13px; font-weight: 600; color: #0369a1;" id="importProgressPercent">0%</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: #e0f2fe; border-radius: 999px; overflow: hidden;">
                            <div id="importProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #0ea5e9, #0284c7); border-radius: 999px; transition: width 0.3s ease;"></div>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 6px;" id="importProgressDetail">Mohon tunggu, jangan tutup halaman ini.</div>
                    </div>
                </div>

                <div id="importResult" style="display: none; margin-bottom: 16px;"></div>

                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: var(--color-border);" onclick="closeImportModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnImportSubmit">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reset Modal --}}
    <div class="modal-overlay" id="resetModal">
        <div class="modal-content" style="max-width: 450px;">
            <h3 style="margin: 0 0 8px; color: #dc2626;">Reset Produk</h3>
            <p style="font-size: 13px; color: var(--color-text-secondary); margin: 0 0 16px;">
                Menghapus <strong>semua produk</strong> dari master produk dan semua assignment produk ke rak.
                Aksi ini tidak bisa dibatalkan.
            </p>

            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="resetCategoryCheckbox" style="width: 18px; height: 18px; margin-top: 2px; accent-color: #dc2626;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px; color: #991b1b;">Reset kategori juga</div>
                        <div style="font-size: 12px; color: #b91c1c; margin-top: 2px;">Hapus semua kategori produk. Centang jika ingin memulai dari awal sepenuhnya.</div>
                    </div>
                </label>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: var(--color-border);" onclick="closeResetModal()">Batal</button>
                <button type="button" class="btn" style="background: #dc2626; color: #fff;" id="btnResetSubmit" onclick="executeResetProducts()">Reset Sekarang</button>
            </div>
        </div>
    </div>

    <script>
        // ======================================================================
        // AJAX live search for products list.
        // ======================================================================
        (function() {
            const searchInput = document.getElementById('productSearchInput');
            const categorySelect = document.getElementById('productCategoryFilter');
            const filterForm = document.getElementById('filterForm');
            const tbody = document.getElementById('productsTbody');
            const cardsWrap = document.getElementById('productsCards');
            const counterSpan = document.getElementById('productCountSpan');
            const filterChipsWrap = document.getElementById('activeFilterChips');

            if (!searchInput || !categorySelect || !filterForm || !tbody) return;

            const SEARCH_URL = @json(route('admin.products.search'));
            const INDEX_URL = @json(route('admin.products.index'));
            const PER_PAGE = {{ $perPage }};
            const CATEGORY_LABEL_MAP = @json($categoryMap);

            let inflight = null;
            let debounceTimer = null;
            let currentPage = {{ $page }};
            let currentSearch = @json($search);
            let currentCategory = @json($categoryFilter);
            let isFetching = false;

            // Helper: HTML escape
            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }
            function attr(s) { return esc(s); }
            function jsArg(s) { return String(s == null ? '' : s).replace(/\\/g, '\\\\').replace(/'/g, "\\'"); }

            // Build category dropdown URL keeping search query
            function buildIndexUrl(params) {
                const u = new URL(INDEX_URL, window.location.origin);
                Object.entries(params).forEach(([k, v]) => {
                    if (v !== '' && v !== null && v !== undefined) {
                        u.searchParams.set(k, v);
                    }
                });
                return u.toString();
            }

            // Render row HTML for desktop tbody
            function renderRow(p) {
                const catBadge = p.category_name
                    ? `<span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #eef2ff; color: #4338ca;">${esc(p.category_name)}</span>`
                    : `<span style="color: var(--color-text-muted); font-size: 13px;">-</span>`;
                const statusBadge = p.is_active
                    ? `<span class="badge-status active">Aktif</span>`
                    : `<span class="badge-status inactive">Nonaktif</span>`;
                const auditUrl = `{{ url('admin/products') }}/${encodeURIComponent(p.id)}/audit-trail`;
                const destroyUrl = `{{ url('admin/products') }}/${encodeURIComponent(p.id)}`;
                return `
                    <tr class="js-product-row" data-category="${attr(p.category_id)}" data-product-id="${attr(p.id)}" data-name="${attr(p.name)}">
                        <td class="js-select-col" style="display: none;"><input type="checkbox" class="product-checkbox js-product-select" value="${attr(p.id)}" onchange="updateBulkCount()"></td>
                        <td><div style="font-weight: 600;">${esc(p.name)}</div></td>
                        <td>${catBadge}</td>
                        <td>${p.standard_qty}</td>
                        <td>${esc(p.unit)}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <a href="${auditUrl}" class="btn btn-sm" style="background:#e0e7ff;color:#3730a3;">📜 Audit</a>
                                <button type="button" class="btn btn-warning btn-sm" onclick="openProductModal('${jsArg(p.id)}', '${jsArg(p.name)}', ${p.standard_qty}, '${jsArg(p.unit)}', ${p.is_active ? 'true' : 'false'}, '${jsArg(p.category_id)}')">Edit</button>
                                <form method="POST" action="${destroyUrl}" data-confirm="Yakin hapus produk ini?" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>`;
            }

            function renderCard(p) {
                const catBadge = p.category_name
                    ? `<span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #eef2ff; color: #4338ca; margin-top: 4px;">${esc(p.category_name)}</span>`
                    : '';
                const statusBadge = p.is_active
                    ? `<span class="badge-status active">Aktif</span>`
                    : `<span class="badge-status inactive">Nonaktif</span>`;
                const auditUrl = `{{ url('admin/products') }}/${encodeURIComponent(p.id)}/audit-trail`;
                const destroyUrl = `{{ url('admin/products') }}/${encodeURIComponent(p.id)}`;
                return `
                    <div class="product-mobile-card js-product-card" data-category="${attr(p.category_id)}" data-product-id="${attr(p.id)}" data-name="${attr(p.name)}">
                        <div class="product-mobile-header">
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" class="product-checkbox js-product-select-mobile" value="${attr(p.id)}" onchange="updateBulkCount()" style="display: none; margin-top: 3px;">
                                <div>
                                    <div class="product-mobile-name">${esc(p.name)}</div>
                                    ${catBadge}
                                </div>
                            </div>
                            ${statusBadge}
                        </div>
                        <div class="product-mobile-grid">
                            <div><div class="product-mobile-field-label">Qty Standar</div><div>${p.standard_qty}</div></div>
                            <div><div class="product-mobile-field-label">Satuan</div><div>${esc(p.unit)}</div></div>
                        </div>
                        <div class="product-mobile-actions">
                            <a href="${auditUrl}" class="btn btn-sm" style="background:#e0e7ff;color:#3730a3;">📜 Audit</a>
                            <button type="button" class="btn btn-warning btn-sm" onclick="openProductModal('${jsArg(p.id)}', '${jsArg(p.name)}', ${p.standard_qty}, '${jsArg(p.unit)}', ${p.is_active ? 'true' : 'false'}, '${jsArg(p.category_id)}')">Edit</button>
                            <form method="POST" action="${destroyUrl}" data-confirm="Yakin hapus produk ini?" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </div>
                    </div>`;
            }

            function renderEmptyState(hasFilter) {
                const msg = hasFilter
                    ? `<div style="font-size: 14px; margin-bottom: 6px;">🔍 Tidak ada produk yang cocok dengan filter aktif.</div>
                       <div style="font-size: 13px;">Coba <a href="${INDEX_URL}" style="color: #2563eb; font-weight: 600;">reset filter</a> atau ubah kata kunci.</div>`
                    : `Belum ada data produk. Silakan tambah produk master.`;
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 24px;">${msg}</td></tr>`;
                if (cardsWrap) {
                    cardsWrap.innerHTML = `<div class="empty">${msg}</div>`;
                }
            }

            function renderFilterChips(search, category) {
                if (!filterChipsWrap) return;
                if (!search && !category) {
                    filterChipsWrap.style.display = 'none';
                    filterChipsWrap.innerHTML = '';
                    return;
                }
                let catLabel = '';
                if (category === '__none__') catLabel = 'Tanpa Kategori';
                else if (category) catLabel = CATEGORY_LABEL_MAP[category] || 'Kategori';

                let html = `<span style="font-size: 12px; color: var(--color-text-muted); font-weight: 600;">Filter aktif:</span>`;
                if (search) {
                    const url = buildIndexUrl({ category: category });
                    html += `<a href="${url}" data-clear="search" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #dbeafe; color: #1e40af; font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #93c5fd;" title="Hapus filter pencarian">🔍 "${esc(search)}" <span style="font-weight: 700;">×</span></a>`;
                }
                if (category) {
                    const url = buildIndexUrl({ search: search });
                    html += `<a href="${url}" data-clear="category" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #fbbf24;" title="Hapus filter kategori">📁 ${esc(catLabel)} <span style="font-weight: 700;">×</span></a>`;
                }
                filterChipsWrap.innerHTML = html;
                filterChipsWrap.style.display = 'flex';

                // Intercept chip click → ajax fetch instead of full reload
                filterChipsWrap.querySelectorAll('a[data-clear]').forEach(a => {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        const which = this.dataset.clear;
                        if (which === 'search') {
                            currentSearch = '';
                            searchInput.value = '';
                        } else if (which === 'category') {
                            currentCategory = '';
                            categorySelect.value = '';
                        }
                        currentPage = 1;
                        runSearch();
                    });
                });
            }

            // Render pagination - simple prev/next/info, intercepted via ajax
            function renderPagination(pagination) {
                const wrap = document.getElementById('paginationWrap');
                if (!wrap) return;
                if (pagination.total_pages <= 1) {
                    wrap.style.display = 'none';
                    wrap.innerHTML = '';
                    return;
                }
                wrap.style.display = 'flex';
                const p = pagination.page;
                const tp = pagination.total_pages;
                let html = `<a href="#" data-page="${Math.max(1, p - 1)}" class="page-btn ${p <= 1 ? 'disabled' : ''}">&laquo;</a>`;
                // 5 page window
                let start = Math.max(1, p - 2);
                let end = Math.min(tp, start + 4);
                if (end - start < 4) start = Math.max(1, end - 4);
                if (start > 1) html += `<a href="#" data-page="1" class="page-btn">1</a>`;
                if (start > 2) html += `<span class="page-info">…</span>`;
                for (let i = start; i <= end; i++) {
                    html += `<a href="#" data-page="${i}" class="page-btn ${i === p ? 'active' : ''}">${i}</a>`;
                }
                if (end < tp - 1) html += `<span class="page-info">…</span>`;
                if (end < tp) html += `<a href="#" data-page="${tp}" class="page-btn">${tp}</a>`;
                html += `<a href="#" data-page="${Math.min(tp, p + 1)}" class="page-btn ${p >= tp ? 'disabled' : ''}">&raquo;</a>`;
                html += `<span class="page-info">Hal ${p}/${tp}</span>`;
                wrap.innerHTML = html;

                wrap.querySelectorAll('a[data-page]').forEach(a => {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (this.classList.contains('disabled') || this.classList.contains('active')) return;
                        currentPage = parseInt(this.dataset.page, 10) || 1;
                        runSearch();
                    });
                });
            }

            function setLoadingState(on) {
                const indicator = document.getElementById('searchSpinner');
                if (indicator) indicator.style.display = on ? 'inline-flex' : 'none';
                searchInput.classList.toggle('searching', on);
            }

            function syncUrlState() {
                const u = new URL(INDEX_URL, window.location.origin);
                if (currentSearch) u.searchParams.set('search', currentSearch);
                if (currentCategory) u.searchParams.set('category', currentCategory);
                if (currentPage > 1) u.searchParams.set('page', currentPage);
                history.replaceState({ search: currentSearch, category: currentCategory, page: currentPage }, '', u.toString());
            }

            async function runSearch() {
                if (inflight) inflight.abort();
                const ctrl = new AbortController();
                inflight = ctrl;
                isFetching = true;
                setLoadingState(true);

                const params = new URLSearchParams();
                if (currentSearch) params.set('search', currentSearch);
                if (currentCategory) params.set('category', currentCategory);
                if (currentPage) params.set('page', currentPage);
                if (PER_PAGE !== 50) params.set('per_page', PER_PAGE);

                try {
                    const resp = await fetch(`${SEARCH_URL}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: ctrl.signal,
                    });
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    const data = await resp.json();
                    if (!data || !data.success) throw new Error('Bad payload');

                    currentPage = data.pagination.page;
                    if (data.products.length === 0) {
                        renderEmptyState(currentSearch !== '' || currentCategory !== '');
                    } else {
                        tbody.innerHTML = data.products.map(renderRow).join('');
                        if (cardsWrap) cardsWrap.innerHTML = data.products.map(renderCard).join('');
                    }
                    if (counterSpan) counterSpan.textContent = `${data.pagination.total_filtered} produk`;
                    renderFilterChips(currentSearch, currentCategory);
                    renderPagination(data.pagination);
                    syncUrlState();
                    // Reset bulk select
                    const selAll = document.getElementById('selectAllCheckbox');
                    if (selAll) selAll.checked = false;
                    if (typeof updateBulkCount === 'function') updateBulkCount();
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #b91c1c; padding: 16px;">⚠️ Gagal memuat hasil. <a href="#" id="retrySearch" style="color: #2563eb; font-weight: 600;">Coba lagi</a></td></tr>`;
                    const retry = document.getElementById('retrySearch');
                    if (retry) retry.addEventListener('click', function(e) { e.preventDefault(); runSearch(); });
                } finally {
                    isFetching = false;
                    setLoadingState(false);
                    if (inflight === ctrl) inflight = null;
                }
            }

            // Debounced trigger from typing
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    currentSearch = searchInput.value.trim();
                    // Universal: typing search → clear category
                    if (currentCategory) {
                        currentCategory = '';
                        categorySelect.value = '';
                    }
                    currentPage = 1;
                    runSearch();
                }, 280);
            });

            // Enter key submits immediately
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(debounceTimer);
                    currentSearch = searchInput.value.trim();
                    if (currentCategory) {
                        currentCategory = '';
                        categorySelect.value = '';
                    }
                    currentPage = 1;
                    runSearch();
                }
            });

            // Category dropdown change → run search keeping current search
            categorySelect.addEventListener('change', function() {
                currentCategory = categorySelect.value;
                currentPage = 1;
                runSearch();
            });

            // Original button still works (no full reload)
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                currentSearch = searchInput.value.trim();
                // Tombol Cari = universal, clear kategori
                currentCategory = '';
                categorySelect.value = '';
                currentPage = 1;
                runSearch();
            });

            // Reset link (button "Reset semua")
            document.querySelectorAll('a[href="' + INDEX_URL + '"]').forEach(a => {
                if (a.textContent.trim().toLowerCase().includes('reset')) {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        currentSearch = '';
                        currentCategory = '';
                        currentPage = 1;
                        searchInput.value = '';
                        categorySelect.value = '';
                        runSearch();
                    });
                }
            });
        })();

        function openProductModal(id = null, name = '', qty = 0, unit = 'pcs', isActive = true, categoryId = '') {
            const modal = document.getElementById('productModal');
            const title = document.getElementById('productModalTitle');
            const form = document.getElementById('productForm');
            const methodField = document.getElementById('methodField');
            
            document.getElementById('productName').value = name;
            document.getElementById('productCategory').value = categoryId || '';
            document.getElementById('productQty').value = qty;
            document.getElementById('productUnit').value = unit;
            document.getElementById('productIsActive').checked = isActive;
            
            if (id) {
                title.textContent = 'Edit Produk';
                form.action = `/admin/products/${id}`;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            } else {
                title.textContent = 'Tambah Produk';
                form.action = '{{ route("admin.products.store") }}';
                methodField.innerHTML = '';
            }
            
            modal.classList.add('show');
            document.getElementById('productName').focus();
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('show');
            cancelNewCategory();
        }

        // ===== Inline category creation =====
        function onCategorySelectChange(select) {
            if (select.value === '__new__') {
                document.getElementById('newCategoryInline').style.display = 'block';
                document.getElementById('newCategoryName').value = '';
                document.getElementById('newCategoryError').style.display = 'none';
                setTimeout(function() { document.getElementById('newCategoryName').focus(); }, 50);
                // Reset select to empty so form doesn't submit "__new__"
                select.value = '';
            } else {
                document.getElementById('newCategoryInline').style.display = 'none';
            }
        }

        function cancelNewCategory() {
            document.getElementById('newCategoryInline').style.display = 'none';
            document.getElementById('newCategoryName').value = '';
            document.getElementById('newCategoryError').style.display = 'none';
        }

        async function saveNewCategoryInline() {
            var nameInput = document.getElementById('newCategoryName');
            var errorEl = document.getElementById('newCategoryError');
            var btn = document.getElementById('btnSaveNewCategory');
            var name = nameInput.value.trim();

            if (!name) {
                errorEl.textContent = 'Nama kategori wajib diisi.';
                errorEl.style.display = 'block';
                return;
            }

            errorEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            try {
                var formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('name', name);
                formData.append('is_active', '1');

                var resp = await fetch('{{ route("admin.product_categories.store") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                var data = await resp.json();

                if (data.success && data.category) {
                    var cat = data.category;
                    var catId = String(cat.id || '');
                    var catName = String(cat.name || name);

                    // Add new option to select (before the "+ Tambah" option)
                    var select = document.getElementById('productCategory');
                    var newOption = document.createElement('option');
                    newOption.value = catId;
                    newOption.textContent = catName;
                    var addNewOption = select.querySelector('option[value="__new__"]');
                    select.insertBefore(newOption, addNewOption);

                    // Select the new category
                    select.value = catId;

                    // Hide inline form
                    cancelNewCategory();
                } else {
                    errorEl.textContent = data.message || 'Gagal menyimpan kategori.';
                    errorEl.style.display = 'block';
                }
            } catch (err) {
                errorEl.textContent = 'Terjadi kesalahan koneksi.';
                errorEl.style.display = 'block';
            }

            btn.disabled = false;
            btn.textContent = 'Simpan';
        }

        // Allow Enter key to save new category
        document.getElementById('newCategoryName').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveNewCategoryInline();
            }
        });

        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });

        // AJAX Form Submission
        document.getElementById('productForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = this;
            const btn = document.getElementById('btnSaveProduct');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';
            
            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Gagal menyimpan produk: ' + (data.message || 'Error tidak diketahui'));
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (err) {
                alert('Terjadi kesalahan koneksi');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });



        // Delegated confirm handler
        document.addEventListener('submit', function(e) {
            if (e.target.id === 'productForm') return; // Handled by AJAX
            
            var form = e.target;
            var confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) {
                e.preventDefault();
            }
        });

        // ===== Bulk Select & Delete =====
        let selectMode = false;

        function toggleSelectMode() {
            selectMode = !selectMode;
            var btn = document.getElementById('btnSelectMode');
            var bar = document.getElementById('bulkActionBar');

            if (selectMode) {
                btn.textContent = 'Batal Pilih';
                btn.style.background = '#fbbf24';
                btn.style.color = '#78350f';
                bar.classList.add('show');
                // Show checkboxes
                document.querySelectorAll('.js-select-col').forEach(function(el) { el.style.display = ''; });
                document.querySelectorAll('.js-product-select-mobile').forEach(function(el) { el.style.display = ''; });
            } else {
                clearSelection();
            }
        }

        function clearSelection() {
            selectMode = false;
            var btn = document.getElementById('btnSelectMode');
            var bar = document.getElementById('bulkActionBar');

            btn.textContent = 'Pilih';
            btn.style.background = 'var(--color-border)';
            btn.style.color = '';
            bar.classList.remove('show');

            // Hide checkboxes & uncheck all
            document.querySelectorAll('.js-select-col').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.js-product-select-mobile').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.js-product-select, .js-product-select-mobile').forEach(function(cb) { cb.checked = false; });
            document.getElementById('selectAllCheckbox').checked = false;
            document.querySelectorAll('.product-table-desktop .js-select-col input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
            updateBulkCount();
        }

        function toggleSelectAll(checked) {
            // Desktop
            document.querySelectorAll('.js-product-select').forEach(function(cb) {
                var row = cb.closest('.js-product-row');
                if (row && row.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            // Mobile
            document.querySelectorAll('.js-product-select-mobile').forEach(function(cb) {
                var card = cb.closest('.js-product-card');
                if (card && card.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            // Sync header checkbox
            document.getElementById('selectAllCheckbox').checked = checked;
            document.querySelectorAll('.product-table-desktop thead .js-select-col input[type="checkbox"]').forEach(function(cb) { cb.checked = checked; });
            updateBulkCount();
        }

        function getSelectedIds() {
            var ids = new Set();
            document.querySelectorAll('.js-product-select:checked').forEach(function(cb) { ids.add(cb.value); });
            document.querySelectorAll('.js-product-select-mobile:checked').forEach(function(cb) { ids.add(cb.value); });
            return Array.from(ids);
        }

        function updateBulkCount() {
            var ids = getSelectedIds();
            var countEl = document.getElementById('bulkCount');
            countEl.textContent = ids.length + ' produk dipilih';
        }

        async function bulkDeleteSelected() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                alert('Belum ada produk yang dipilih.');
                return;
            }

            if (!confirm('Yakin hapus ' + ids.length + ' produk terpilih? Aksi ini tidak bisa dibatalkan.')) {
                return;
            }

            var btn = document.querySelector('.btn-bulk-delete');
            btn.disabled = true;
            btn.textContent = 'Menghapus...';

            try {
                var resp = await fetch('{{ route("admin.products.bulk_destroy") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids: ids })
                });

                var data = await resp.json();

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Gagal: ' + (data.message || 'Error tidak diketahui'));
                    btn.disabled = false;
                    btn.textContent = '🗑️ Hapus Terpilih';
                }
            } catch (err) {
                alert('Terjadi kesalahan koneksi.');
                btn.disabled = false;
                btn.textContent = '🗑️ Hapus Terpilih';
            }
        }

        // ===== Import Excel =====
        function openImportModal() {
            document.getElementById('importFileInput').value = '';
            document.getElementById('importDefaultQty').value = '0';
            document.getElementById('importProgress').style.display = 'none';
            document.getElementById('importResult').style.display = 'none';
            document.getElementById('btnImportSubmit').disabled = false;
            document.getElementById('importProgressBar').style.width = '0%';
            document.getElementById('importProgressBar').style.background = 'linear-gradient(90deg, #0ea5e9, #0284c7)';
            document.getElementById('importProgressPercent').textContent = '0%';
            document.getElementById('importModal').classList.add('show');
            document.getElementById('importModal').style.display = 'flex';
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.remove('show');
            document.getElementById('importModal').style.display = 'none';
        }

        document.getElementById('importModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImportModal();
            }
        });

        // ===== Reset Products =====
        function confirmResetProducts() {
            var totalProducts = document.querySelectorAll('.js-product-row, .js-product-card').length;
            if (totalProducts === 0) {
                alert('Tidak ada produk untuk direset.');
                return;
            }
            document.getElementById('resetModal').classList.add('show');
            document.getElementById('resetModal').style.display = 'flex';
            document.getElementById('resetCategoryCheckbox').checked = false;
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('show');
            document.getElementById('resetModal').style.display = 'none';
        }

        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) closeResetModal();
        });

        async function executeResetProducts() {
            var totalProducts = document.querySelectorAll('.js-product-row, .js-product-card').length;
            var resetCategories = document.getElementById('resetCategoryCheckbox').checked;

            var msg = '⚠️ KONFIRMASI FINAL\n\nAnda akan menghapus SEMUA ' + totalProducts + ' produk + assignment rak.';
            if (resetCategories) {
                msg += '\n+ SEMUA KATEGORI juga akan dihapus.';
            }
            msg += '\n\nAksi ini TIDAK BISA dibatalkan. Lanjutkan?';

            if (!confirm(msg)) return;

            var btn = document.getElementById('btnResetSubmit');
            btn.disabled = true;
            btn.textContent = 'Mereset...';

            try {
                var resp = await fetch('{{ route("admin.products.reset") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ reset_categories: resetCategories })
                });

                var data = await resp.json();

                if (data.success) {
                    alert('✅ ' + data.message);
                    window.location.reload();
                } else {
                    alert('Gagal: ' + (data.message || 'Error tidak diketahui'));
                    btn.disabled = false;
                    btn.textContent = 'Reset Sekarang';
                }
            } catch (err) {
                alert('Terjadi kesalahan koneksi.');
                btn.disabled = false;
                btn.textContent = 'Reset Sekarang';
            }
        }

        document.getElementById('importForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('importFileInput');
            if (!fileInput.files.length) {
                alert('Pilih file Excel terlebih dahulu.');
                return;
            }

            const formData = new FormData();
            formData.append('excel_file', fileInput.files[0]);
            formData.append('default_standard_qty', document.getElementById('importDefaultQty').value || '0');
            formData.append('_token', '{{ csrf_token() }}');

            const progressEl = document.getElementById('importProgress');
            const progressBar = document.getElementById('importProgressBar');
            const progressLabel = document.getElementById('importProgressLabel');
            const progressPercent = document.getElementById('importProgressPercent');
            const progressDetail = document.getElementById('importProgressDetail');

            progressEl.style.display = 'block';
            document.getElementById('importResult').style.display = 'none';
            document.getElementById('btnImportSubmit').disabled = true;

            // Phase 1: Upload file (0-30%)
            progressBar.style.width = '0%';
            progressPercent.textContent = '0%';
            progressLabel.textContent = 'Mengupload file...';
            progressDetail.textContent = 'Mengirim file ke server...';

            let fakeProgress = 0;
            let processingInterval = null;

            try {
                // Use XMLHttpRequest for upload progress tracking
                const data = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();

                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const uploadPercent = Math.round((evt.loaded / evt.total) * 30);
                            progressBar.style.width = uploadPercent + '%';
                            progressPercent.textContent = uploadPercent + '%';
                            progressDetail.textContent = `Upload: ${formatBytes(evt.loaded)} / ${formatBytes(evt.total)}`;
                        }
                    });

                    xhr.upload.addEventListener('load', function() {
                        // Phase 2: Processing on server (30-90%)
                        progressBar.style.width = '30%';
                        progressPercent.textContent = '30%';
                        progressLabel.textContent = 'Memproses data...';
                        progressDetail.textContent = 'Membaca file Excel & mengimport produk ke database...';

                        fakeProgress = 30;
                        processingInterval = setInterval(function() {
                            if (fakeProgress < 90) {
                                fakeProgress += Math.random() * 3;
                                if (fakeProgress > 90) fakeProgress = 90;
                                progressBar.style.width = Math.round(fakeProgress) + '%';
                                progressPercent.textContent = Math.round(fakeProgress) + '%';
                            }
                        }, 500);
                    });

                    xhr.addEventListener('load', function() {
                        if (processingInterval) clearInterval(processingInterval);
                        try {
                            resolve(JSON.parse(xhr.responseText));
                        } catch (err) {
                            reject(new Error('Response bukan JSON valid'));
                        }
                    });

                    xhr.addEventListener('error', function() {
                        if (processingInterval) clearInterval(processingInterval);
                        reject(new Error('Koneksi gagal'));
                    });

                    xhr.addEventListener('timeout', function() {
                        if (processingInterval) clearInterval(processingInterval);
                        reject(new Error('Request timeout'));
                    });

                    xhr.open('POST', '{{ route("admin.products.import") }}');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.timeout = 300000; // 5 minutes
                    xhr.send(formData);
                });

                // Phase 3: Complete (100%)
                progressBar.style.width = '100%';
                progressPercent.textContent = '100%';
                progressLabel.textContent = 'Selesai!';

                let resultHtml = '';
                if (data.success) {
                    progressBar.style.background = 'linear-gradient(90deg, #22c55e, #16a34a)';
                    progressDetail.textContent = `${data.imported} produk berhasil diimport dari ${data.total} total data.`;

                    resultHtml = `<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px;">
                        <div style="font-weight: 700; color: #166534; margin-bottom: 10px; font-size: 15px;">Import Berhasil!</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                            <div style="background: #dcfce7; border-radius: 6px; padding: 10px; text-align: center;">
                                <div style="font-size: 22px; font-weight: 700; color: #166534;">${data.imported}</div>
                                <div style="font-size: 11px; color: #15803d; font-weight: 600;">Produk Diimport</div>
                            </div>
                            <div style="background: #fef9c3; border-radius: 6px; padding: 10px; text-align: center;">
                                <div style="font-size: 22px; font-weight: 700; color: #854d0e;">${data.skipped}</div>
                                <div style="font-size: 11px; color: #a16207; font-weight: 600;">Dilewati (Duplikat)</div>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div style="background: #ede9fe; border-radius: 6px; padding: 10px; text-align: center;">
                                <div style="font-size: 22px; font-weight: 700; color: #5b21b6;">${data.categories_created}</div>
                                <div style="font-size: 11px; color: #6d28d9; font-weight: 600;">Kategori Baru</div>
                            </div>
                            <div style="background: #f0f9ff; border-radius: 6px; padding: 10px; text-align: center;">
                                <div style="font-size: 22px; font-weight: 700; color: #0369a1;">${data.total}</div>
                                <div style="font-size: 11px; color: #0284c7; font-weight: 600;">Total Data</div>
                            </div>
                        </div>
                        ${data.errors && data.errors.length > 0 ? `<div style="margin-top: 10px; padding: 8px; background: #fef2f2; border-radius: 6px; font-size: 12px; color: #b91c1c;">${data.errors.slice(0, 10).join('<br>')}${data.errors.length > 10 ? '<br>...dan ' + (data.errors.length - 10) + ' error lainnya' : ''}</div>` : ''}
                    </div>`;

                    setTimeout(() => { window.location.reload(); }, 3000);
                } else {
                    progressBar.style.background = 'linear-gradient(90deg, #ef4444, #dc2626)';
                    progressDetail.textContent = 'Import gagal.';

                    resultHtml = `<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px;">
                        <div style="font-weight: 600; color: #991b1b;">Gagal Import</div>
                        <div style="font-size: 13px; color: #b91c1c;">${data.message || 'Terjadi kesalahan.'}</div>
                    </div>`;
                    document.getElementById('btnImportSubmit').disabled = false;
                }

                document.getElementById('importResult').innerHTML = resultHtml;
                document.getElementById('importResult').style.display = 'block';
            } catch (error) {
                if (processingInterval) clearInterval(processingInterval);
                progressBar.style.width = '100%';
                progressBar.style.background = 'linear-gradient(90deg, #ef4444, #dc2626)';
                progressPercent.textContent = 'Error';
                progressLabel.textContent = 'Gagal';
                progressDetail.textContent = error.message;

                document.getElementById('importResult').innerHTML = `<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px;">
                    <div style="font-weight: 600; color: #991b1b;">Error</div>
                    <div style="font-size: 13px; color: #b91c1c;">${error.message}</div>
                </div>`;
                document.getElementById('importResult').style.display = 'block';
                document.getElementById('btnImportSubmit').disabled = false;
            }
        });

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    </script>
@endsection
