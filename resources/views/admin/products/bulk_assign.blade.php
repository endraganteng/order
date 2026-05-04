@extends('admin.layout')

@section('title', 'Assign Produk Massal - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Assign Produk ke Rak (Massal)</h2>
            <div style="font-size: 14px; color: var(--color-text-muted); margin-top: 4px;">
                3 langkah: Pilih produk &rarr; Pilih rak tujuan &rarr; Set qty &amp; simpan.
            </div>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn" style="background: var(--color-border);">Master Produk</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @push('styles')
    <style>
        /* ===== Stepper ===== */
        .stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 24px;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text-muted);
            background: var(--color-bg);
            border: 2px solid var(--color-border);
            transition: all 0.25s;
            cursor: default;
            white-space: nowrap;
        }
        .step-item.active {
            color: #fff;
            background: var(--color-primary);
            border-color: var(--color-primary);
        }
        .step-item.done {
            color: var(--color-success);
            background: var(--color-success-bg);
            border-color: var(--color-success-border);
            cursor: pointer;
        }
        .step-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            background: rgba(0,0,0,0.08);
            flex-shrink: 0;
        }
        .step-item.active .step-num {
            background: rgba(255,255,255,0.25);
        }
        .step-item.done .step-num {
            background: var(--color-success);
            color: #fff;
        }
        .step-connector {
            width: 32px;
            height: 2px;
            background: var(--color-border);
            flex-shrink: 0;
        }
        .step-connector.done {
            background: var(--color-success);
        }

        @media (max-width: 600px) {
            .step-item span:not(.step-num) { display: none; }
            .step-item { padding: 8px 12px; }
            .step-connector { width: 20px; }
        }

        /* ===== Panels ===== */
        .step-panel {
            display: none;
        }
        .step-panel.visible {
            display: block;
        }

        /* ===== Shared list card ===== */
        .pick-card {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .pick-toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-bg);
            flex-wrap: wrap;
        }
        .pick-search {
            flex: 1;
            min-width: 160px;
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
        }
        .pick-search:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.15);
        }
        .pick-select-all {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--color-text-secondary);
            cursor: pointer;
            white-space: nowrap;
        }
        .pick-counter {
            font-size: 13px;
            color: var(--color-text-muted);
            white-space: nowrap;
        }
        .pick-counter strong {
            color: var(--color-primary);
        }

        .pick-list {
            max-height: 420px;
            overflow-y: auto;
        }
        .pick-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            transition: background 0.15s;
        }
        .pick-item:last-child { border-bottom: none; }
        .pick-item:hover { background: #f8fafc; }
        .pick-item.selected { background: #eef2ff; }
        .pick-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .pick-item-info {
            flex: 1;
            min-width: 0;
        }
        .pick-item-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--color-text);
        }
        .pick-item-meta {
            font-size: 12px;
            color: var(--color-text-muted);
            margin-top: 2px;
        }
        .pick-empty {
            padding: 30px;
            text-align: center;
            color: var(--color-text-muted);
        }

        /* ===== Step 3: Review table ===== */
        .review-card {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .review-header {
            padding: 14px 16px;
            background: var(--color-bg);
            border-bottom: 1px solid var(--color-border);
            font-weight: 700;
            font-size: 15px;
            color: var(--color-text);
        }
        .review-group {
            border-bottom: 1px solid var(--color-border);
        }
        .review-group:last-child { border-bottom: none; }
        .review-group-title {
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            color: var(--color-text);
            background: #fafbfc;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .review-group-title .rack-loc {
            font-weight: 400;
            font-size: 12px;
            color: var(--color-text-muted);
        }
        .review-product-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px 10px 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        .review-product-row:last-child { border-bottom: none; }
        .review-product-name {
            flex: 1;
            font-size: 14px;
            color: var(--color-text);
        }
        .review-product-name .unit {
            font-size: 12px;
            color: var(--color-text-muted);
        }
        .review-qty-input {
            width: 80px;
            padding: 6px 8px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            text-align: center;
            font-size: 14px;
        }
        .review-qty-input:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.15);
        }

        /* ===== Bottom bar ===== */
        .bottom-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 2px solid var(--color-border);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            z-index: 10;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.06);
            flex-wrap: wrap;
        }
        .bottom-bar .summary {
            font-size: 14px;
            color: var(--color-text-secondary);
        }
        .bottom-bar .summary strong {
            color: var(--color-primary);
        }
        .bottom-bar .btn-group {
            display: flex;
            gap: 8px;
        }

        .bulk-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--color-text-muted);
            font-size: 15px;
        }

        /* ===== Add Product Modal ===== */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: #fff;
            padding: 24px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-md);
        }
        .modal-box h3 {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--color-text);
        }
        .modal-field {
            margin-bottom: 14px;
        }
        .modal-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text-secondary);
            margin-bottom: 4px;
        }
        .modal-field input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
        }
        .modal-field input:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.15);
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
        }
        .modal-error {
            color: var(--color-danger);
            font-size: 13px;
            margin-top: 8px;
            display: none;
        }

        /* Add product button in toolbar */
        .btn-add-product {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            background: var(--color-primary);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .btn-add-product:hover { background: var(--color-primary-dark); }
        .js-bulk-lazy-hidden { display: none !important; }
    </style>
    @endpush

    @php
        $hasProducts = count($products) > 0;
        $hasRacks = count($racks) > 0;
    @endphp

    @if(!$hasProducts || !$hasRacks)
        <div class="card">
            <div class="bulk-empty">
                @if(!$hasProducts && !$hasRacks)
                    Belum ada produk dan rak aktif. Silakan tambahkan terlebih dahulu.
                @elseif(!$hasProducts)
                    Belum ada produk aktif. <a href="{{ route('admin.products.index') }}">Tambah produk</a> terlebih dahulu.
                @else
                    Belum ada rak aktif. <a href="{{ route('admin.racks.index') }}">Tambah rak</a> terlebih dahulu.
                @endif
            </div>
        </div>
    @else

    {{-- Stepper --}}
    <div class="stepper" id="stepper">
        <div class="step-item active" data-step="1">
            <span class="step-num">1</span>
            <span>Pilih Produk</span>
        </div>
        <div class="step-connector" data-after="1"></div>
        <div class="step-item" data-step="2">
            <span class="step-num">2</span>
            <span>Pilih Rak</span>
        </div>
        <div class="step-connector" data-after="2"></div>
        <div class="step-item" data-step="3">
            <span class="step-num">3</span>
            <span>Set Qty &amp; Simpan</span>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.bulk_assign.save') }}" id="bulkForm">
        @csrf

        {{-- ===== STEP 1: Pilih Produk ===== --}}
        <div class="step-panel visible" id="panel1">
            <div class="pick-card">
                <div class="pick-toolbar">
                    <input type="text" class="pick-search" id="searchProduct" placeholder="Cari produk...">
                    @if(count($categories ?? []) > 0)
                    <select id="filterCategory" class="pick-search" style="flex: 0 0 auto; min-width: 140px;" onchange="filterProductsByCategory()">
                        <option value="">Semua Kategori</option>
                        <option value="__none__">Tanpa Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                    @endif
                    <button type="button" class="btn-add-product" onclick="openAddProductModal()">+ Tambah Produk</button>
                    <label class="pick-select-all">
                        <input type="checkbox" id="selectAllProducts">
                        Pilih Semua
                    </label>
                    <div class="pick-counter" id="productCounter"><strong>0</strong> dipilih</div>
                </div>
                <div class="pick-list" id="productList">
                    @forelse($products as $idx => $product)
                        @php
                            $pid = (string)($product['id'] ?? '');
                            $pname = (string)($product['name'] ?? '-');
                            $punit = (string)($product['unit'] ?? 'pcs');
                            $pqty = (int)($product['standard_qty'] ?? 0);
                            $pcatId = (string)($product['category_id'] ?? '');
                            $pcatName = $pcatId !== '' ? ($categoryMap[$pcatId] ?? '') : '';
                        @endphp
                        <label class="pick-item js-product-item{{ $idx >= 50 ? ' js-bulk-lazy-hidden' : '' }}" data-id="{{ $pid }}" data-name="{{ strtolower($pname) }}" data-unit="{{ $punit }}" data-qty="{{ $pqty }}" data-category="{{ $pcatId }}">
                            <input type="checkbox" class="js-pick-product" value="{{ $pid }}">
                            <div class="pick-item-info">
                                <div class="pick-item-name">{{ $pname }}@if($pcatName) <span style="font-size: 11px; font-weight: 400; color: var(--color-text-muted);">({{ $pcatName }})</span>@endif</div>
                                <div class="pick-item-meta">Qty standar: {{ $pqty }} {{ $punit }}</div>
                            </div>
                        </label>
                    @empty
                        <div class="pick-empty">Tidak ada produk aktif.</div>
                    @endforelse
                </div>
                <div id="bulkLoadMoreBar" style="display: none; padding: 12px; text-align: center;">
                    <button type="button" style="background: var(--color-primary); color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;" onclick="bulkLoadMore()">Muat Lebih Banyak</button>
                    <span id="bulkLoadMoreInfo" style="display: block; font-size: 12px; color: var(--color-text-muted); margin-top: 6px;"></span>
                </div>
            </div>
        </div>

        {{-- ===== STEP 2: Pilih Rak ===== --}}
        <div class="step-panel" id="panel2">
            <div class="pick-card">
                <div class="pick-toolbar">
                    <input type="text" class="pick-search" id="searchRack" placeholder="Cari rak...">
                    <label class="pick-select-all">
                        <input type="checkbox" id="selectAllRacks">
                        Pilih Semua
                    </label>
                    <div class="pick-counter" id="rackCounter"><strong>0</strong> dipilih</div>
                </div>
                <div class="pick-list" id="rackList">
                    @forelse($racks as $rack)
                        @php
                            $rid = (string)($rack['id'] ?? '');
                            $rname = (string)($rack['name'] ?? '-');
                            $rloc = (string)($rack['location'] ?? '');
                        @endphp
                        <label class="pick-item js-rack-item" data-id="{{ $rid }}" data-name="{{ strtolower($rname . ' ' . $rloc) }}">
                            <input type="checkbox" class="js-pick-rack" value="{{ $rid }}">
                            <div class="pick-item-info">
                                <div class="pick-item-name">{{ $rname }}</div>
                                @if($rloc)
                                    <div class="pick-item-meta">{{ $rloc }}</div>
                                @endif
                            </div>
                        </label>
                    @empty
                        <div class="pick-empty">Tidak ada rak aktif.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ===== STEP 3: Review & Set Qty ===== --}}
        <div class="step-panel" id="panel3">
            <div class="review-card">
                <div class="review-header">Review &amp; Set Qty per Rak</div>
                <div id="reviewContent">
                    {{-- Rendered by JS --}}
                </div>
            </div>
            {{-- Hidden inputs rendered by JS --}}
            <div id="hiddenInputs"></div>
        </div>
    </form>

    {{-- Bottom bar --}}
    <div class="bottom-bar" id="bottomBar">
        <div class="summary" id="summaryText"></div>
        <div class="btn-group">
            <button type="button" class="btn" style="background: var(--color-border);" id="btnBack" onclick="goBack()" style="display:none;">Kembali</button>
            <button type="button" class="btn btn-primary" id="btnNext" onclick="goNext()">Lanjut: Pilih Rak</button>
        </div>
    </div>

    {{-- Add Product Modal --}}
    <div class="modal-overlay" id="addProductModal">
        <div class="modal-box">
            <h3>Tambah Produk Baru</h3>
            <div class="modal-field">
                <label for="newProductName">Nama Produk *</label>
                <input type="text" id="newProductName" maxlength="120" placeholder="Contoh: Teh Botol Sosro">
            </div>
            <div class="modal-field">
                <label for="newProductQty">Qty Standar *</label>
                <input type="number" id="newProductQty" min="0" value="0">
            </div>
            <div class="modal-field">
                <label for="newProductUnit">Satuan *</label>
                <input type="text" id="newProductUnit" maxlength="30" value="pcs" placeholder="pcs, botol, dus...">
            </div>
            <div class="modal-field">
                <label for="newProductCategory">Kategori</label>
                <select id="newProductCategory" style="width: 100%; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px;" onchange="onBulkCategorySelectChange(this)">
                    <option value="">-- Tanpa Kategori --</option>
                    @foreach($categories ?? [] as $cat)
                        <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                    @endforeach
                    <option value="__new__">+ Tambah Kategori Baru...</option>
                </select>
                <div id="bulkNewCategoryInline" style="display: none; margin-top: 8px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="bulkNewCategoryName" placeholder="Nama kategori baru" maxlength="100" style="flex: 1; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px;">
                        <button type="button" class="btn btn-primary btn-sm" id="btnBulkSaveNewCat" onclick="saveBulkNewCategory()">Simpan</button>
                        <button type="button" class="btn btn-sm" style="background: var(--color-border);" onclick="cancelBulkNewCategory()">Batal</button>
                    </div>
                    <div id="bulkNewCategoryError" style="color: var(--color-danger); font-size: 12px; margin-top: 4px; display: none;"></div>
                </div>
            </div>
            <div class="modal-error" id="addProductError"></div>
            <div class="modal-actions">
                <button type="button" class="btn" style="background: var(--color-border);" onclick="closeAddProductModal()">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveNewProduct" onclick="saveNewProduct()">Simpan</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentStep = 1;

        // ===== Data from PHP =====
        const productsData = @json($products);
        const racksData = @json($racks);
        const currentAssignments = @json($currentAssignments);

        // Build lookup maps
        const productMap = {};
        productsData.forEach(p => { productMap[p.id] = p; });
        const rackMap = {};
        racksData.forEach(r => { rackMap[r.id] = r; });

        // ===== Step navigation =====
        window.goNext = function() {
            if (currentStep === 1) {
                const selected = getSelectedProducts();
                if (selected.length === 0) {
                    alert('Pilih minimal 1 produk.');
                    return;
                }
                setStep(2);
            } else if (currentStep === 2) {
                const selected = getSelectedRacks();
                if (selected.length === 0) {
                    alert('Pilih minimal 1 rak.');
                    return;
                }
                buildReview();
                setStep(3);
            } else if (currentStep === 3) {
                submitForm();
            }
        };

        window.goBack = function() {
            if (currentStep > 1) {
                setStep(currentStep - 1);
            }
        };

        function setStep(step) {
            currentStep = step;

            // Panels
            document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('visible'));
            document.getElementById('panel' + step).classList.add('visible');

            // Stepper indicators
            document.querySelectorAll('.step-item').forEach(el => {
                const s = parseInt(el.dataset.step);
                el.classList.remove('active', 'done');
                if (s === step) el.classList.add('active');
                else if (s < step) el.classList.add('done');
            });
            document.querySelectorAll('.step-connector').forEach(el => {
                const after = parseInt(el.dataset.after);
                el.classList.toggle('done', after < step);
            });

            // Clickable done steps
            document.querySelectorAll('.step-item.done').forEach(el => {
                el.onclick = () => setStep(parseInt(el.dataset.step));
            });

            // Bottom bar
            const btnBack = document.getElementById('btnBack');
            const btnNext = document.getElementById('btnNext');
            btnBack.style.display = step > 1 ? '' : 'none';

            if (step === 1) {
                btnNext.textContent = 'Lanjut: Pilih Rak';
                btnNext.className = 'btn btn-primary';
            } else if (step === 2) {
                btnNext.textContent = 'Lanjut: Set Qty';
                btnNext.className = 'btn btn-primary';
            } else {
                btnNext.textContent = 'Simpan Assign Massal';
                btnNext.className = 'btn btn-primary';
                btnNext.style.background = '#16a34a';
            }
            if (step !== 3) {
                btnNext.style.background = '';
            }

            updateSummary();
        }

        // ===== Selections =====
        function getSelectedProducts() {
            return Array.from(document.querySelectorAll('.js-pick-product:checked')).map(cb => cb.value);
        }
        function getSelectedRacks() {
            return Array.from(document.querySelectorAll('.js-pick-rack:checked')).map(cb => cb.value);
        }

        // ===== Infinite scroll for product list =====
        const BULK_BATCH_SIZE = 50;
        let bulkVisibleCount = BULK_BATCH_SIZE;
        const allBulkProductItems = Array.from(document.querySelectorAll('.js-product-item'));
        const totalBulkProducts = allBulkProductItems.length;

        function bulkLoadMore() {
            const nextBatch = Math.min(bulkVisibleCount + BULK_BATCH_SIZE, totalBulkProducts);
            for (let i = bulkVisibleCount; i < nextBatch; i++) {
                allBulkProductItems[i].classList.remove('js-bulk-lazy-hidden');
            }
            bulkVisibleCount = nextBatch;
            updateBulkLoadMoreBar();
            applyProductFilters();
        }

        function updateBulkLoadMoreBar() {
            const bar = document.getElementById('bulkLoadMoreBar');
            const info = document.getElementById('bulkLoadMoreInfo');
            if (bulkVisibleCount >= totalBulkProducts) {
                bar.style.display = 'none';
            } else {
                bar.style.display = 'block';
                info.textContent = 'Menampilkan ' + bulkVisibleCount + ' dari ' + totalBulkProducts + ' produk';
            }
        }

        (function initBulkScroll() {
            if (totalBulkProducts <= BULK_BATCH_SIZE) return;
            updateBulkLoadMoreBar();

            const list = document.getElementById('productList');
            if (list) {
                list.addEventListener('scroll', function() {
                    if (list.scrollTop + list.clientHeight >= list.scrollHeight - 100) {
                        if (bulkVisibleCount < totalBulkProducts) bulkLoadMore();
                    }
                });
            }
        })();

        // ===== Search & category filters =====
        function applyProductFilters() {
            const q = (document.getElementById('searchProduct').value || '').toLowerCase().trim();
            const catFilter = document.getElementById('filterCategory');
            const catId = catFilter ? catFilter.value : '';
            const isFiltering = q !== '' || catId !== '';

            document.querySelectorAll('.js-product-item').forEach(el => {
                if (!isFiltering && el.classList.contains('js-bulk-lazy-hidden')) {
                    el.style.display = 'none';
                    return;
                }

                const matchesSearch = !q || el.dataset.name.includes(q);
                let matchesCat = true;
                if (catId === '__none__') {
                    matchesCat = !el.dataset.category || el.dataset.category === '';
                } else if (catId !== '') {
                    matchesCat = el.dataset.category === catId;
                }

                if (isFiltering) {
                    el.style.display = (matchesSearch && matchesCat) ? '' : 'none';
                } else {
                    el.style.display = '';
                }
            });

            const bar = document.getElementById('bulkLoadMoreBar');
            if (isFiltering) {
                bar.style.display = 'none';
            } else {
                updateBulkLoadMoreBar();
            }
        }

        document.getElementById('searchProduct').addEventListener('input', applyProductFilters);
        window.filterProductsByCategory = applyProductFilters;
        document.getElementById('searchRack').addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.js-rack-item').forEach(el => {
                el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
            });
        });

        // ===== Select all =====
        document.getElementById('selectAllProducts').addEventListener('change', function() {
            const checked = this.checked;
            document.querySelectorAll('.js-product-item:not([style*="display: none"]) .js-pick-product').forEach(cb => {
                cb.checked = checked;
                cb.closest('.pick-item').classList.toggle('selected', checked);
            });
            updateProductCounter();
        });
        document.getElementById('selectAllRacks').addEventListener('change', function() {
            const checked = this.checked;
            document.querySelectorAll('.js-rack-item:not([style*="display: none"]) .js-pick-rack').forEach(cb => {
                cb.checked = checked;
                cb.closest('.pick-item').classList.toggle('selected', checked);
            });
            updateRackCounter();
        });

        // ===== Individual checkbox toggle =====
        document.getElementById('productList').addEventListener('change', function(e) {
            if (e.target.classList.contains('js-pick-product')) {
                e.target.closest('.pick-item').classList.toggle('selected', e.target.checked);
                updateProductCounter();
            }
        });
        document.getElementById('rackList').addEventListener('change', function(e) {
            if (e.target.classList.contains('js-pick-rack')) {
                e.target.closest('.pick-item').classList.toggle('selected', e.target.checked);
                updateRackCounter();
            }
        });

        function updateProductCounter() {
            const count = getSelectedProducts().length;
            document.getElementById('productCounter').innerHTML = '<strong>' + count + '</strong> dipilih';
            updateSummary();
        }
        function updateRackCounter() {
            const count = getSelectedRacks().length;
            document.getElementById('rackCounter').innerHTML = '<strong>' + count + '</strong> dipilih';
            updateSummary();
        }

        function updateSummary() {
            const pCount = getSelectedProducts().length;
            const rCount = getSelectedRacks().length;
            const el = document.getElementById('summaryText');
            if (currentStep === 1) {
                el.innerHTML = '<strong>' + pCount + '</strong> produk dipilih';
            } else if (currentStep === 2) {
                el.innerHTML = '<strong>' + pCount + '</strong> produk &times; <strong>' + rCount + '</strong> rak = <strong>' + (pCount * rCount) + '</strong> assignment';
            } else {
                el.innerHTML = '<strong>' + pCount + '</strong> produk &rarr; <strong>' + rCount + '</strong> rak';
            }
        }
        updateSummary();

        // ===== Step 3: Build review =====
        function buildReview() {
            const selectedProducts = getSelectedProducts();
            const selectedRacks = getSelectedRacks();
            const reviewEl = document.getElementById('reviewContent');
            const hiddenEl = document.getElementById('hiddenInputs');

            let reviewHtml = '';
            let hiddenHtml = '';

            selectedRacks.forEach(rackId => {
                const rack = rackMap[rackId] || {};
                const rackName = rack.name || '-';
                const rackLoc = rack.location || '';

                reviewHtml += '<div class="review-group">';
                reviewHtml += '<div class="review-group-title">' + escHtml(rackName);
                if (rackLoc) reviewHtml += ' <span class="rack-loc">' + escHtml(rackLoc) + '</span>';
                reviewHtml += '</div>';

                selectedProducts.forEach(productId => {
                    const product = productMap[productId] || {};
                    const pName = product.name || '-';
                    const pUnit = product.unit || 'pcs';
                    // Use existing assignment qty if available, else master standard_qty
                    const existingQty = (currentAssignments[rackId] && currentAssignments[rackId][productId] !== undefined)
                        ? currentAssignments[rackId][productId]
                        : (product.standard_qty || 0);
                    const inputName = 'assignments[' + rackId + '][' + productId + ']';

                    reviewHtml += '<div class="review-product-row">';
                    reviewHtml += '<div class="review-product-name">' + escHtml(pName) + ' <span class="unit">(' + escHtml(pUnit) + ')</span></div>';
                    reviewHtml += '<input type="number" class="review-qty-input js-review-qty" name="' + inputName + '" value="' + existingQty + '" min="0">';
                    reviewHtml += '</div>';
                });

                reviewHtml += '</div>';
            });

            reviewEl.innerHTML = reviewHtml;
            hiddenEl.innerHTML = hiddenHtml;
        }

        // ===== Submit =====
        function submitForm() {
            const btn = document.getElementById('btnNext');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';
            document.getElementById('bulkForm').submit();
        }

        // ===== Helpers =====
        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // ===== Add Product Modal =====
        window.openAddProductModal = function() {
            document.getElementById('newProductName').value = '';
            document.getElementById('newProductQty').value = '0';
            document.getElementById('newProductUnit').value = 'pcs';
            const catSel = document.getElementById('newProductCategory');
            if (catSel) catSel.value = '';
            cancelBulkNewCategory();
            document.getElementById('addProductError').style.display = 'none';
            document.getElementById('btnSaveNewProduct').disabled = false;
            document.getElementById('btnSaveNewProduct').textContent = 'Simpan';
            document.getElementById('addProductModal').classList.add('show');
            setTimeout(() => document.getElementById('newProductName').focus(), 50);
        };

        window.closeAddProductModal = function() {
            document.getElementById('addProductModal').classList.remove('show');
            cancelBulkNewCategory();
        };

        // ===== Inline category creation (bulk assign modal) =====
        window.onBulkCategorySelectChange = function(select) {
            if (select.value === '__new__') {
                document.getElementById('bulkNewCategoryInline').style.display = 'block';
                document.getElementById('bulkNewCategoryName').value = '';
                document.getElementById('bulkNewCategoryError').style.display = 'none';
                setTimeout(function() { document.getElementById('bulkNewCategoryName').focus(); }, 50);
                select.value = '';
            } else {
                document.getElementById('bulkNewCategoryInline').style.display = 'none';
            }
        };

        window.cancelBulkNewCategory = function() {
            document.getElementById('bulkNewCategoryInline').style.display = 'none';
            document.getElementById('bulkNewCategoryName').value = '';
            document.getElementById('bulkNewCategoryError').style.display = 'none';
        };

        window.saveBulkNewCategory = async function() {
            const nameInput = document.getElementById('bulkNewCategoryName');
            const errorEl = document.getElementById('bulkNewCategoryError');
            const btn = document.getElementById('btnBulkSaveNewCat');
            const name = nameInput.value.trim();

            if (!name) {
                errorEl.textContent = 'Nama kategori wajib diisi.';
                errorEl.style.display = 'block';
                return;
            }

            errorEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            try {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('name', name);
                formData.append('is_active', '1');

                const resp = await fetch('{{ route("admin.product_categories.store") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                const data = await resp.json();

                if (data.success && data.category) {
                    const cat = data.category;
                    const catId = String(cat.id || '');
                    const catName = String(cat.name || name);

                    // Add to modal select
                    const select = document.getElementById('newProductCategory');
                    const newOpt = document.createElement('option');
                    newOpt.value = catId;
                    newOpt.textContent = catName;
                    const addNewOpt = select.querySelector('option[value="__new__"]');
                    select.insertBefore(newOpt, addNewOpt);
                    select.value = catId;

                    // Also add to filter dropdown if exists
                    const filterSelect = document.getElementById('filterCategory');
                    if (filterSelect) {
                        const filterOpt = document.createElement('option');
                        filterOpt.value = catId;
                        filterOpt.textContent = catName;
                        filterSelect.appendChild(filterOpt);
                    }

                    cancelBulkNewCategory();
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
        };

        // Enter key to save
        document.getElementById('bulkNewCategoryName').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); saveBulkNewCategory(); }
        });

        // Close modal on overlay click
        document.getElementById('addProductModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddProductModal();
        });

        window.saveNewProduct = async function() {
            const name = document.getElementById('newProductName').value.trim();
            const qty = parseInt(document.getElementById('newProductQty').value) || 0;
            const unit = document.getElementById('newProductUnit').value.trim() || 'pcs';
            const catSelect = document.getElementById('newProductCategory');
            const categoryId = catSelect ? catSelect.value : '';
            const errorEl = document.getElementById('addProductError');
            const btn = document.getElementById('btnSaveNewProduct');

            if (!name) {
                errorEl.textContent = 'Nama produk wajib diisi.';
                errorEl.style.display = 'block';
                return;
            }

            errorEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            try {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('name', name);
                formData.append('standard_qty', qty);
                formData.append('unit', unit);
                formData.append('is_active', '1');
                if (categoryId) formData.append('category_id', categoryId);

                const resp = await fetch('{{ route("admin.products.store") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                const data = await resp.json();

                if (data.success && data.product) {
                    const p = data.product;
                    const pid = String(p.id || '');
                    const pname = String(p.name || name);
                    const punit = String(p.unit || unit);
                    const pqty = parseInt(p.standard_qty) || qty;

                    const pcatId = String(p.category_id || categoryId || '');

                    // Add to JS data maps
                    productMap[pid] = { id: pid, name: pname, unit: punit, standard_qty: pqty, category_id: pcatId, is_active: true };

                    // Insert into DOM list (at top, already checked + selected)
                    const listEl = document.getElementById('productList');
                    const emptyEl = listEl.querySelector('.pick-empty');
                    if (emptyEl) emptyEl.remove();

                    const label = document.createElement('label');
                    label.className = 'pick-item js-product-item selected';
                    label.dataset.id = pid;
                    label.dataset.name = pname.toLowerCase();
                    label.dataset.unit = punit;
                    label.dataset.qty = pqty;
                    label.dataset.category = pcatId;
                    label.innerHTML =
                        '<input type="checkbox" class="js-pick-product" value="' + escHtml(pid) + '" checked>' +
                        '<div class="pick-item-info">' +
                            '<div class="pick-item-name">' + escHtml(pname) + '</div>' +
                            '<div class="pick-item-meta">Qty standar: ' + pqty + ' ' + escHtml(punit) + '</div>' +
                        '</div>';
                    listEl.prepend(label);

                    updateProductCounter();
                    closeAddProductModal();
                } else {
                    errorEl.textContent = data.message || 'Gagal menyimpan produk.';
                    errorEl.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Simpan';
                }
            } catch (err) {
                errorEl.textContent = 'Terjadi kesalahan koneksi.';
                errorEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Simpan';
            }
        };

        // Init
        setStep(1);
    });
    </script>

    @endif
@endsection
