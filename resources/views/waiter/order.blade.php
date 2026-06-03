<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buat Order - Waiter</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f6fb;
            color: #273444;
            min-height: 100vh;
            padding-bottom: 90px;
        }
        .wrap { max-width: 600px; margin: 0 auto; padding: 20px 16px; }

        /* Header */
        .header {
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }
        .back-btn {
            text-decoration: none;
            color: #475569;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: #f1f5f9;
            flex-shrink: 0;
        }
        .back-btn:hover { background: #e2e8f0; }
        .header-title { flex: 1; font-size: 17px; font-weight: 700; color: #1e293b; }
        .header-icon {
            border: none;
            background: #f1f5f9;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .header-icon:hover { background: #e2e8f0; }

        /* Form area */
        .order-form {
            background: #fff;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }
        .product-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
            position: relative;
            background: #fafbfc;
        }
        .product-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .product-label {
            font-weight: 700;
            font-size: 14px;
            color: #334155;
        }
        .btn-remove {
            border: none;
            background: #fef2f2;
            color: #dc2626;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .btn-remove:hover { background: #fee2e2; }
        .form-group { margin-bottom: 10px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 5px;
        }
        .form-input {
            width: 100%;
            border: 1.5px solid #dbe3ef;
            border-radius: 10px;
            padding: 11px 13px;
            font-size: 15px;
            color: #1e293b;
            background: #fff;
            transition: border-color 0.2s;
        }
        .form-input:focus { outline: none; border-color: #3b82f6; }
        .form-input.error { border-color: #ef4444; }
        .error-text {
            font-size: 12px;
            color: #ef4444;
            margin-top: 4px;
            display: none;
        }
        .error-text.show { display: block; }

        /* Add product button */
        .btn-add {
            width: 100%;
            border: 2px dashed #cbd5e1;
            background: transparent;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            color: #3b82f6;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s;
            margin-top: 4px;
        }
        .btn-add:hover { border-color: #3b82f6; background: #f0f7ff; }

        /* Footer sticky */
        .footer-sticky {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            padding: 14px 16px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
            z-index: 100;
        }
        .footer-inner {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
        }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-hold {
            background: #f1f5f9;
            color: #334155;
            border: 1.5px solid #e2e8f0;
        }
        .btn-hold:hover:not(:disabled) { background: #e2e8f0; }
        .btn-submit {
            background: #2563eb;
            color: #fff;
        }
        .btn-submit:hover:not(:disabled) { background: #1d4ed8; }

        /* Modal overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 14px;
            padding: 24px 20px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1e293b;
        }
        .modal-text {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .modal-close-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            background: #2563eb;
            color: #fff;
        }
        .modal-close-btn:hover { background: #1d4ed8; }

        /* History modal specifics */
        .history-search {
            width: 100%;
            border: 1.5px solid #dbe3ef;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .history-search:focus { outline: none; border-color: #3b82f6; }
        .history-filter {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .filter-btn {
            border: 1px solid #dbe3ef;
            background: #fff;
            color: #334155;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filter-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
        .history-list { max-height: 50vh; overflow-y: auto; }
        .history-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .history-item:hover { background: #f8fafc; }
        .history-item-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .history-queue {
            font-weight: 700;
            font-size: 14px;
            color: #1e293b;
        }
        .history-time {
            font-size: 12px;
            color: #64748b;
        }
        .history-products {
            font-size: 13px;
            color: #475569;
            line-height: 1.4;
        }
        .history-total {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 4px;
        }
        .history-empty {
            text-align: center;
            padding: 30px 10px;
            color: #94a3b8;
            font-size: 14px;
        }
        .history-loading {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 14px;
        }

        /* Hold modal specifics */
        .hold-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
        }
        .hold-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .hold-item-time {
            font-size: 12px;
            color: #64748b;
        }
        .hold-item-products {
            font-size: 13px;
            color: #475569;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .hold-item-actions {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            border: none;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-continue { background: #2563eb; color: #fff; }
        .btn-continue:hover { background: #1d4ed8; }
        .btn-delete { background: #fef2f2; color: #dc2626; }
        .btn-delete:hover { background: #fee2e2; }
        .hold-empty {
            text-align: center;
            padding: 30px 10px;
            color: #94a3b8;
            font-size: 14px;
        }

        /* Flash message */
        .flash-msg {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 10000;
        }
        .flash-msg.success { background: #22c55e; color: #fff; }
        .flash-msg.error { background: #ef4444; color: #fff; }
        .flash-msg.show { display: block; }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Success icon */
        .success-icon {
            width: 60px;
            height: 60px;
            background: #ecfdf5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .wrap { padding: 12px 10px; }
            .header { padding: 12px; }
            .order-form { padding: 12px; }
            .product-item { padding: 12px; }
        }
    </style>
</head>

<body>
    <!-- Flash message -->
    <div id="flash-msg" class="flash-msg"></div>

    <div class="wrap">
        <!-- Header -->
        <div class="header">
            <a href="{{ route('waiter.tasks') }}" class="back-btn" title="Kembali">&larr;</a>
            <span class="header-title">🛒 Buat Order</span>
            <button type="button" class="header-icon" id="btn-history" title="Riwayat Order">📋</button>
            <button type="button" class="header-icon" id="btn-hold-open" title="Hold Order">⏸️</button>
        </div>

        <!-- Order Form -->
        <div class="order-form">
            <div id="products-container">
                <!-- Barang 1 (default) -->
                <div class="product-item" data-index="1">
                    <div class="product-item-header">
                        <span class="product-label">Barang 1</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" class="form-input product-name" placeholder="Masukkan nama produk" autocomplete="off">
                        <div class="error-text">Nama produk wajib diisi</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga</label>
                        <input type="text" class="form-input product-price" placeholder="Rp 0" inputmode="numeric" autocomplete="off">
                        <div class="error-text">Harga wajib diisi</div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-add" id="btn-add-product">
                <span>+</span> Tambah Produk
            </button>
        </div>
    </div>

    <!-- Footer Sticky -->
    <div class="footer-sticky">
        <div class="footer-inner">
            <button type="button" class="btn btn-hold" id="btn-hold-save">
                ⏸️ Hold
            </button>
            <button type="button" class="btn btn-submit" id="btn-submit-order">
                🛒 Buat Order
            </button>
        </div>
    </div>

    <!-- Modal: Order Berhasil -->
    <div class="modal-overlay" id="modal-success" onclick="if(event.target===this)closeSuccessModal()">
        <div class="modal-box" style="text-align:center;">
            <div class="success-icon">✅</div>
            <div class="modal-title">Order Berhasil!</div>
            <div class="modal-text" id="success-message">Order sudah masuk ke kasir dan akan segera diproses.</div>
            <button type="button" class="modal-close-btn" onclick="closeSuccessModal()">Tutup</button>
        </div>
    </div>

    <!-- Modal: Riwayat Order -->
    <div class="modal-overlay" id="modal-history" onclick="if(event.target===this)closeHistoryModal()">
        <div class="modal-box">
            <div class="modal-title">📋 Riwayat Order</div>
            <input type="text" class="history-search" id="history-search" placeholder="Cari order...">
            <div class="history-filter">
                <button type="button" class="filter-btn active" data-filter="all">Semua</button>
                <button type="button" class="filter-btn" data-filter="1h">1 Jam</button>
                <button type="button" class="filter-btn" data-filter="3h">3 Jam</button>
                <button type="button" class="filter-btn" data-filter="today">Hari Ini</button>
            </div>
            <div class="history-list" id="history-list">
                <div class="history-loading">Memuat riwayat...</div>
            </div>
            <button type="button" class="modal-close-btn" style="margin-top:14px;" onclick="closeHistoryModal()">Tutup</button>
        </div>
    </div>

    <!-- Modal: Hold Order -->
    <div class="modal-overlay" id="modal-hold" onclick="if(event.target===this)closeHoldModal()">
        <div class="modal-box">
            <div class="modal-title">⏸️ Order Ditahan</div>
            <div class="modal-text" style="margin-bottom:12px;">Daftar order yang sedang ditahan sementara.</div>
            <div id="hold-list">
                <div class="hold-empty">Belum ada order yang ditahan.</div>
            </div>
            <button type="button" class="modal-close-btn" style="margin-top:14px;" onclick="closeHoldModal()">Tutup</button>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const SUBMIT_URL = "{{ route('waiter.order.submit', [], false) }}";
        const HISTORY_URL = "{{ route('waiter.order.history', [], false) }}";
        const HOLD_KEY = 'waiter_hold_orders_{{ $waiterId }}';

        let productCount = 1;
        let isSubmitting = false;

        // === DOM References ===
        const container = document.getElementById('products-container');
        const btnAdd = document.getElementById('btn-add-product');
        const btnSubmit = document.getElementById('btn-submit-order');
        const btnHoldSave = document.getElementById('btn-hold-save');
        const btnHistory = document.getElementById('btn-history');
        const btnHoldOpen = document.getElementById('btn-hold-open');
        const flashEl = document.getElementById('flash-msg');

        // === Utility: Format Rupiah ===
        function formatRupiah(num) {
            if (!num && num !== 0) return '';
            return 'Rp ' + Number(num).toLocaleString('id-ID');
        }

        function parseRupiah(str) {
            if (!str) return 0;
            return parseInt(str.replace(/[^0-9]/g, ''), 10) || 0;
        }

        function applyRupiahFormat(input) {
            const raw = input.value.replace(/[^0-9]/g, '');
            if (raw === '') {
                input.value = '';
                return;
            }
            input.value = formatRupiah(parseInt(raw, 10));
        }

        // === Utility: Flash message ===
        function showFlash(message, type) {
            flashEl.textContent = message;
            flashEl.className = 'flash-msg ' + type + ' show';
            setTimeout(() => { flashEl.classList.remove('show'); }, 3000);
        }

        // === Product Management ===
        function createProductItem(index) {
            const div = document.createElement('div');
            div.className = 'product-item';
            div.setAttribute('data-index', index);
            div.innerHTML = `
                <div class="product-item-header">
                    <span class="product-label">Barang ${index}</span>
                    <button type="button" class="btn-remove" onclick="removeProduct(this)" title="Hapus">&times;</button>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" class="form-input product-name" placeholder="Masukkan nama produk" autocomplete="off">
                    <div class="error-text">Nama produk wajib diisi</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga</label>
                    <input type="text" class="form-input product-price" placeholder="Rp 0" inputmode="numeric" autocomplete="off">
                    <div class="error-text">Harga wajib diisi</div>
                </div>
            `;
            return div;
        }

        function reindexProducts() {
            const items = container.querySelectorAll('.product-item');
            items.forEach((item, i) => {
                const idx = i + 1;
                item.setAttribute('data-index', idx);
                item.querySelector('.product-label').textContent = 'Barang ' + idx;
                // Show/hide remove button (hide if only 1 item)
                const removeBtn = item.querySelector('.btn-remove');
                if (removeBtn) {
                    removeBtn.style.display = items.length > 1 ? 'flex' : 'none';
                }
            });
            productCount = items.length;
        }

        btnAdd.addEventListener('click', function() {
            productCount++;
            const item = createProductItem(productCount);
            container.appendChild(item);
            reindexProducts();
            // Attach price formatter
            const priceInput = item.querySelector('.product-price');
            priceInput.addEventListener('input', function() { applyRupiahFormat(this); });
            // Focus new name input
            item.querySelector('.product-name').focus();
        });

        window.removeProduct = function(btn) {
            const item = btn.closest('.product-item');
            if (container.querySelectorAll('.product-item').length > 1) {
                item.remove();
                reindexProducts();
            }
        };

        // Initial setup: hide remove for single item
        reindexProducts();

        // === Price formatting on existing inputs ===
        document.querySelectorAll('.product-price').forEach(function(input) {
            input.addEventListener('input', function() { applyRupiahFormat(this); });
        });

        // === Validation ===
        function validateForm() {
            let valid = true;
            const items = container.querySelectorAll('.product-item');
            items.forEach(function(item) {
                const nameInput = item.querySelector('.product-name');
                const priceInput = item.querySelector('.product-price');
                const nameError = nameInput.parentElement.querySelector('.error-text');
                const priceError = priceInput.parentElement.querySelector('.error-text');

                // Reset
                nameInput.classList.remove('error');
                priceInput.classList.remove('error');
                nameError.classList.remove('show');
                priceError.classList.remove('show');

                if (!nameInput.value.trim()) {
                    nameInput.classList.add('error');
                    nameError.classList.add('show');
                    valid = false;
                }
                if (parseRupiah(priceInput.value) <= 0) {
                    priceInput.classList.add('error');
                    priceError.classList.add('show');
                    valid = false;
                }
            });
            return valid;
        }

        function getProductsData() {
            const items = container.querySelectorAll('.product-item');
            const products = [];
            items.forEach(function(item) {
                products.push({
                    name: item.querySelector('.product-name').value.trim(),
                    price: parseRupiah(item.querySelector('.product-price').value),
                });
            });
            return products;
        }

        // === Submit Order ===
        btnSubmit.addEventListener('click', async function() {
            if (isSubmitting) return;
            if (!validateForm()) return;

            isSubmitting = true;
            btnSubmit.disabled = true;
            btnHoldSave.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner"></span> Memproses...';

            try {
                const response = await fetch(SUBMIT_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ products: getProductsData() }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    // Handle validation errors
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0];
                        showFlash(Array.isArray(firstError) ? firstError[0] : firstError, 'error');
                    } else {
                        showFlash(data.message || 'Gagal membuat order.', 'error');
                    }
                    return;
                }

                // Success
                const queueNum = data.queue_number || '-';
                document.getElementById('success-message').textContent =
                    'Order #' + queueNum + ' sudah masuk ke kasir dan akan segera diproses.';
                document.getElementById('modal-success').classList.add('open');

            } catch (err) {
                showFlash('Koneksi gagal. Coba lagi.', 'error');
            } finally {
                isSubmitting = false;
                btnSubmit.disabled = false;
                btnHoldSave.disabled = false;
                btnSubmit.innerHTML = '🛒 Buat Order';
            }
        });

        // === Success Modal ===
        window.closeSuccessModal = function() {
            document.getElementById('modal-success').classList.remove('open');
            resetForm();
        };

        function resetForm() {
            container.innerHTML = '';
            const firstItem = createProductItem(1);
            container.appendChild(firstItem);
            // No remove button for single item
            const removeBtn = firstItem.querySelector('.btn-remove');
            if (removeBtn) removeBtn.style.display = 'none';
            // Attach price formatter
            firstItem.querySelector('.product-price').addEventListener('input', function() { applyRupiahFormat(this); });
            productCount = 1;
        }

        // === Hold Order ===
        function getHoldOrders() {
            try {
                return JSON.parse(localStorage.getItem(HOLD_KEY)) || [];
            } catch (e) {
                return [];
            }
        }

        function saveHoldOrders(orders) {
            localStorage.setItem(HOLD_KEY, JSON.stringify(orders));
        }

        btnHoldSave.addEventListener('click', function() {
            const products = getProductsData();
            // Check at least one product has data
            const hasData = products.some(p => p.name || p.price > 0);
            if (!hasData) {
                showFlash('Tidak ada data untuk ditahan.', 'error');
                return;
            }

            const holds = getHoldOrders();
            holds.unshift({
                id: Date.now(),
                products: products,
                created_at: new Date().toISOString(),
            });
            saveHoldOrders(holds);
            showFlash('Order ditahan berhasil.', 'success');
            resetForm();
        });

        btnHoldOpen.addEventListener('click', function() {
            renderHoldList();
            document.getElementById('modal-hold').classList.add('open');
        });

        window.closeHoldModal = function() {
            document.getElementById('modal-hold').classList.remove('open');
        };

        function renderHoldList() {
            const holds = getHoldOrders();
            const listEl = document.getElementById('hold-list');

            if (holds.length === 0) {
                listEl.innerHTML = '<div class="hold-empty">Belum ada order yang ditahan.</div>';
                return;
            }

            let html = '';
            holds.forEach(function(hold, idx) {
                const time = new Date(hold.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                const productNames = hold.products.map(p => p.name || '(kosong)').join(', ');
                const total = hold.products.reduce((s, p) => s + (p.price || 0), 0);
                html += `
                    <div class="hold-item">
                        <div class="hold-item-header">
                            <span class="product-label">Hold #${idx + 1}</span>
                            <span class="hold-item-time">${time}</span>
                        </div>
                        <div class="hold-item-products">${productNames}</div>
                        <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:8px;">${formatRupiah(total)}</div>
                        <div class="hold-item-actions">
                            <button type="button" class="btn-sm btn-continue" onclick="continueHold(${hold.id})">Lanjutkan</button>
                            <button type="button" class="btn-sm btn-delete" onclick="deleteHold(${hold.id})">Hapus</button>
                        </div>
                    </div>
                `;
            });
            listEl.innerHTML = html;
        }

        window.continueHold = function(id) {
            const holds = getHoldOrders();
            const holdIndex = holds.findIndex(h => h.id === id);
            if (holdIndex === -1) return;

            const hold = holds[holdIndex];
            // Remove from hold list
            holds.splice(holdIndex, 1);
            saveHoldOrders(holds);

            // Fill form with hold data
            container.innerHTML = '';
            hold.products.forEach(function(p, i) {
                const item = createProductItem(i + 1);
                container.appendChild(item);
                item.querySelector('.product-name').value = p.name || '';
                if (p.price > 0) {
                    item.querySelector('.product-price').value = formatRupiah(p.price);
                }
                item.querySelector('.product-price').addEventListener('input', function() { applyRupiahFormat(this); });
            });
            reindexProducts();
            closeHoldModal();
            showFlash('Order dari hold berhasil dimuat.', 'success');
        };

        window.deleteHold = function(id) {
            const holds = getHoldOrders();
            const filtered = holds.filter(h => h.id !== id);
            saveHoldOrders(filtered);
            renderHoldList();
            showFlash('Hold order dihapus.', 'success');
        };

        // === History Modal ===
        let historyData = [];
        let historyFilter = 'all';

        btnHistory.addEventListener('click', function() {
            document.getElementById('modal-history').classList.add('open');
            loadHistory();
        });

        window.closeHistoryModal = function() {
            document.getElementById('modal-history').classList.remove('open');
        };

        async function loadHistory() {
            const listEl = document.getElementById('history-list');
            listEl.innerHTML = '<div class="history-loading">Memuat riwayat...</div>';

            try {
                const response = await fetch(HISTORY_URL + '?date=' + getTodayDate(), {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                });
                const data = await response.json();

                if (data.success && Array.isArray(data.orders)) {
                    historyData = data.orders;
                } else {
                    historyData = [];
                }
                renderHistory();
            } catch (e) {
                listEl.innerHTML = '<div class="history-empty">Gagal memuat riwayat.</div>';
            }
        }

        function getTodayDate() {
            const now = new Date();
            return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        }

        function renderHistory() {
            const listEl = document.getElementById('history-list');
            const searchVal = (document.getElementById('history-search').value || '').toLowerCase();

            let filtered = historyData;

            // Time filter
            if (historyFilter !== 'all') {
                const now = Math.floor(Date.now() / 1000);
                let cutoff = 0;
                if (historyFilter === '1h') cutoff = now - 3600;
                else if (historyFilter === '3h') cutoff = now - 10800;
                else cutoff = 0; // today - already filtered by date
                if (cutoff > 0) {
                    filtered = filtered.filter(o => (o.created_at || 0) >= cutoff);
                }
            }

            // Search filter
            if (searchVal) {
                filtered = filtered.filter(function(o) {
                    const names = (o.products || []).map(p => p.name || '').join(' ').toLowerCase();
                    const queue = String(o.queue_number || '');
                    return names.includes(searchVal) || queue.includes(searchVal);
                });
            }

            if (filtered.length === 0) {
                listEl.innerHTML = '<div class="history-empty">Tidak ada order ditemukan.</div>';
                return;
            }

            let html = '';
            filtered.forEach(function(order) {
                const time = order.created_at ? new Date(order.created_at * 1000).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-';
                const products = order.products || [];
                const productList = products.map(p => p.name + ' (' + formatRupiah(p.price) + ')').join(', ');
                const total = products.reduce((s, p) => s + (p.price || 0), 0);
                const queueNum = order.queue_number || '-';

                html += `
                    <div class="history-item">
                        <div class="history-item-top">
                            <span class="history-queue">Order #${queueNum}</span>
                            <span class="history-time">${time}</span>
                        </div>
                        <div class="history-products">${productList}</div>
                        <div class="history-total">Total: ${formatRupiah(total)}</div>
                    </div>
                `;
            });
            listEl.innerHTML = html;
        }

        // History search
        document.getElementById('history-search').addEventListener('input', function() {
            renderHistory();
        });

        // History filter buttons
        document.querySelectorAll('.filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                historyFilter = this.getAttribute('data-filter');
                renderHistory();
            });
        });

    })();
    </script>
</body>

</html>
