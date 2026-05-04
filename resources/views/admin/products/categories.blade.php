@extends('admin.layout')

@section('title', 'Kategori Produk - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Kategori Produk</h2>
            <div class="page-subtitle">Kelola kategori untuk mengelompokkan produk master.</div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.products.index') }}" class="btn" style="background: var(--color-border);">Master Produk</a>
            <button type="button" class="btn btn-primary" onclick="openCategoryModal()">+ Tambah Kategori</button>
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
        .cat-table-desktop { display: block; }
        .cat-cards-mobile { display: none; }

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
        .modal-overlay.show { display: flex; }
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
        }
        .modal-close:hover { color: var(--color-text); }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--color-border);
        }

        @media (max-width: 900px) {
            .cat-table-desktop { display: none; }
            .cat-cards-mobile {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .cat-mobile-card {
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 14px;
                background: #fff;
                box-shadow: var(--shadow-sm);
            }
            .cat-mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 10px;
            }
            .cat-mobile-name {
                font-weight: 700;
                color: var(--color-text);
                font-size: 15px;
            }
            .cat-mobile-desc {
                font-size: 13px;
                color: var(--color-text-muted);
                margin-bottom: 10px;
            }
            .cat-mobile-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                padding-top: 10px;
                border-top: 1px solid var(--color-border);
            }
        }
    </style>
    @endpush

    {{-- Desktop Table --}}
    <div class="card cat-table-desktop" style="padding: 0; overflow: hidden;">
        <div class="table-scroll" style="padding: 16px;">
            <table>
                <thead>
                    <tr>
                        <th>Urutan</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                        @php
                            $catId = (string) ($cat['id'] ?? '');
                            $catName = (string) ($cat['name'] ?? '-');
                            $catDesc = (string) ($cat['description'] ?? '');
                            $catSort = (int) ($cat['sort_order'] ?? 0);
                            $catActive = ($cat['is_active'] ?? true) === true;
                        @endphp
                        <tr>
                            <td>{{ $catSort }}</td>
                            <td><div style="font-weight: 600;">{{ $catName }}</div></td>
                            <td style="color: var(--color-text-muted); font-size: 13px;">{{ $catDesc ?: '-' }}</td>
                            <td>
                                @if($catActive)
                                    <span class="badge-status active">Aktif</span>
                                @else
                                    <span class="badge-status inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openCategoryModal('{{ $catId }}', '{{ addslashes($catName) }}', '{{ addslashes($catDesc) }}', {{ $catSort }}, {{ $catActive ? 'true' : 'false' }})">Edit</button>
                                    <form method="POST" action="{{ route('admin.product_categories.destroy', $catId) }}" data-confirm="Yakin hapus kategori ini? Produk yang terkait akan kehilangan kategorinya.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-text-muted);">Belum ada kategori. Silakan tambah kategori baru.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Card Layout --}}
    <div class="cat-cards-mobile">
        @forelse($categories as $cat)
            @php
                $catId = (string) ($cat['id'] ?? '');
                $catName = (string) ($cat['name'] ?? '-');
                $catDesc = (string) ($cat['description'] ?? '');
                $catSort = (int) ($cat['sort_order'] ?? 0);
                $catActive = ($cat['is_active'] ?? true) === true;
            @endphp
            <div class="cat-mobile-card">
                <div class="cat-mobile-header">
                    <div>
                        <div class="cat-mobile-name">{{ $catName }}</div>
                        <div style="font-size: 12px; color: var(--color-text-muted);">Urutan: {{ $catSort }}</div>
                    </div>
                    @if($catActive)
                        <span class="badge-status active">Aktif</span>
                    @else
                        <span class="badge-status inactive">Nonaktif</span>
                    @endif
                </div>
                @if($catDesc)
                    <div class="cat-mobile-desc">{{ $catDesc }}</div>
                @endif
                <div class="cat-mobile-actions">
                    <button type="button" class="btn btn-warning btn-sm" onclick="openCategoryModal('{{ $catId }}', '{{ addslashes($catName) }}', '{{ addslashes($catDesc) }}', {{ $catSort }}, {{ $catActive ? 'true' : 'false' }})">Edit</button>
                    <form method="POST" action="{{ route('admin.product_categories.destroy', $catId) }}" data-confirm="Yakin hapus kategori ini?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty">Belum ada kategori. Silakan tambah kategori baru.</div>
        @endforelse
    </div>

    {{-- Category Modal --}}
    <div class="modal-overlay" id="categoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="catModalTitle">Tambah Kategori</h3>
                <button type="button" class="modal-close" onclick="closeCategoryModal()">&times;</button>
            </div>
            <form id="categoryForm" method="POST" action="{{ route('admin.product_categories.store') }}">
                @csrf
                <div id="catMethodField"></div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="catName">Nama Kategori *</label>
                    <input type="text" id="catName" name="name" class="form-input" required maxlength="100" placeholder="Misal: Minuman, Snack, Rokok">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="catDesc">Deskripsi</label>
                    <input type="text" id="catDesc" name="description" class="form-input" maxlength="255" placeholder="Opsional">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="catSort">Urutan</label>
                    <input type="number" id="catSort" name="sort_order" class="form-input" min="0" value="0">
                    <div class="form-hint">Angka kecil tampil lebih dulu.</div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="catIsActive" name="is_active" value="1" checked>
                        <span>Kategori Aktif</span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background: var(--color-border);" onclick="closeCategoryModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveCat">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCategoryModal(id = null, name = '', desc = '', sortOrder = 0, isActive = true) {
            const modal = document.getElementById('categoryModal');
            const title = document.getElementById('catModalTitle');
            const form = document.getElementById('categoryForm');
            const methodField = document.getElementById('catMethodField');

            document.getElementById('catName').value = name;
            document.getElementById('catDesc').value = desc;
            document.getElementById('catSort').value = sortOrder;
            document.getElementById('catIsActive').checked = isActive;

            if (id) {
                title.textContent = 'Edit Kategori';
                form.action = `/admin/product-categories/${id}`;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            } else {
                title.textContent = 'Tambah Kategori';
                form.action = '{{ route("admin.product_categories.store") }}';
                methodField.innerHTML = '';
            }

            modal.classList.add('show');
            document.getElementById('catName').focus();
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.remove('show');
        }

        document.getElementById('categoryModal').addEventListener('click', function(e) {
            if (e.target === this) closeCategoryModal();
        });

        // AJAX Form Submission
        document.getElementById('categoryForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = this;
            const btn = document.getElementById('btnSaveCat');
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
                    alert('Gagal menyimpan: ' + (data.message || 'Error'));
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
            if (e.target.id === 'categoryForm') return;
            var form = e.target;
            var confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    </script>
@endsection
