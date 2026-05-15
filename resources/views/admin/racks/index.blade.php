@extends('admin.layout')

@section('title', 'Master Rak - Admin')

@section('content')
    <div class="page-header">
        <h2 class="page-title">Master Rak & QR Code</h2>
        <a href="{{ route('admin.racks.create') }}" class="btn btn-primary">+ Tambah Rak</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @push('styles')
    <style>
        .rack-bulk-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            padding: 14px;
            border: 1px solid var(--color-info-border);
            border-radius: var(--radius-md);
            background: var(--color-info-bg);
        }
        .rack-bulk-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rack-bulk-type-form {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .rack-bulk-type-select {
            min-width: 220px;
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: #fff;
            font-size: 13px;
        }
        .rack-type-chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
        }
        .rack-type-chip.storage {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .rack-type-chip.display {
            background: #ecfdf5;
            color: #047857;
        }
        .rack-label-preview {
            display: inline-flex;
            flex-direction: column;
            gap: 4px;
            min-width: 160px;
            padding: 6px;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-sm);
            background: #fff;
        }
        .rack-label-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--color-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rack-label-value {
            font-size: 12px;
            color: var(--color-text-muted);
        }
        .rack-qrcode {
            width: 70px;
            height: 70px;
            background: #fff;
        }

        /* Filter toolbar */
        .rack-filter-toolbar {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--color-bg, #f8fafc);
            border: 1px solid var(--color-border, #e2e8f0);
            border-radius: var(--radius-md, 8px);
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,.08));
            padding: 12px 14px 10px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .rack-filter-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rack-filter-row--search {
            flex-wrap: nowrap;
        }
        .rack-search-wrap {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
        }
        .rack-search-icon {
            position: absolute;
            left: 10px;
            width: 16px;
            height: 16px;
            color: var(--color-text-muted, #94a3b8);
            pointer-events: none;
            flex-shrink: 0;
        }
        .rack-search-input {
            width: 100%;
            padding: 8px 36px 8px 34px;
            border: 1px solid var(--color-border, #e2e8f0);
            border-radius: var(--radius-sm, 6px);
            font-size: 13px;
            color: var(--color-text, #1e293b);
            background: #fff;
            outline: none;
            transition: border-color 0.15s;
            box-sizing: border-box;
        }
        .rack-search-input:focus {
            border-color: var(--color-primary, #3b82f6);
            box-shadow: 0 0 0 2px rgba(59,130,246,.15);
        }
        .rack-search-clear {
            position: absolute;
            right: 6px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-text-muted, #94a3b8);
            font-size: 14px;
            line-height: 1;
            padding: 4px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rack-search-clear:hover {
            color: var(--color-text, #1e293b);
            background: var(--color-border, #e2e8f0);
        }
        .rack-type-pills {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .rack-filter-pill {
            min-height: 36px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid var(--color-border, #e2e8f0);
            background: #fff;
            color: var(--color-text-muted, #64748b);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.13s, color 0.13s, border-color 0.13s;
            line-height: 1.2;
        }
        .rack-filter-pill:hover {
            border-color: var(--color-primary, #3b82f6);
            color: var(--color-primary, #3b82f6);
        }
        .rack-filter-pill.active {
            background: var(--color-primary, #3b82f6);
            color: #fff;
            border-color: var(--color-primary, #3b82f6);
        }
        .rack-filter-pill[data-type="storage"].active {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        .rack-filter-pill[data-type="display"].active {
            background: #047857;
            border-color: #047857;
        }
        .rack-filter-counter {
            margin-left: auto;
            font-size: 12px;
            color: var(--color-text-muted, #64748b);
            white-space: nowrap;
            background: var(--color-border, #e2e8f0);
            border-radius: 999px;
            padding: 3px 10px;
            font-weight: 600;
        }
        .rack-filter-empty {
            font-size: 13px;
            color: var(--color-text-muted, #64748b);
            text-align: center;
            padding: 8px 0 2px;
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 600px) {
            .rack-filter-toolbar {
                top: 0;
                gap: 10px;
            }
            .rack-filter-row--search {
                flex-wrap: wrap;
            }
            .rack-search-wrap {
                flex: 1 1 100%;
            }
            .rack-filter-row--type {
                flex-wrap: wrap;
            }
            .rack-filter-counter {
                margin-left: 0;
            }
        }

        /* Desktop table */
        .rack-table-desktop {
            display: block;
        }

        /* Mobile card layout */
        .rack-cards-mobile {
            display: none;
        }

        @media (max-width: 900px) {
            .rack-table-desktop {
                display: none;
            }
            .rack-cards-mobile {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .rack-mobile-card {
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 14px;
                background: #fff;
                box-shadow: var(--shadow-sm);
            }
            .rack-mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 10px;
            }
            .rack-mobile-name {
                font-weight: 700;
                color: var(--color-text);
                font-size: 15px;
            }
            .rack-mobile-desc {
                font-size: 12px;
                color: var(--color-text-muted);
                margin-top: 2px;
            }
            .rack-mobile-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 10px;
                font-size: 13px;
            }
            .rack-mobile-field-label {
                font-size: 12px;
                font-weight: 600;
                color: var(--color-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .rack-mobile-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                padding-top: 10px;
                border-top: 1px solid var(--color-border);
            }
        }
    </style>
    @endpush

    <form id="rackBulkTypeForm" method="POST" action="{{ route('admin.racks.bulk_update_type') }}" style="display:none;">
        @csrf
        <input type="hidden" name="rack_type" id="bulkRackTypeValue" value="{{ old('rack_type', 'storage') }}">
        <div id="bulkRackTypeIds"></div>
    </form>

    <form id="rackBulkActionForm" method="GET" action="{{ route('admin.racks.print_labels') }}" target="_blank">
        <div class="rack-bulk-card">
            <div>
                <div style="font-size: 14px; font-weight: 700; color: var(--color-text);">Print / Export QR Code Rak</div>
                <div style="font-size: 12px; color: var(--color-text-muted);">Pilih rak lalu print label atau export CSV. Jika tidak ada yang dipilih, semua rak akan diproses.</div>
            </div>
            <div class="rack-bulk-actions">
                <div class="rack-bulk-type-form">
                    <select id="bulkRackTypeSelect" class="rack-bulk-type-select" aria-label="Pilih tipe rak massal">
                        <option value="storage" {{ old('rack_type', 'storage') === 'storage' ? 'selected' : '' }}>📦 Ubah ke Storage (Gudang/Stok)</option>
                        <option value="display" {{ old('rack_type') === 'display' ? 'selected' : '' }}>🏪 Ubah ke Display (Etalase/Customer)</option>
                    </select>
                    <button type="button" class="btn btn-warning" id="btnBulkUpdateType">Update Tipe</button>
                </div>
                <button type="submit" class="btn btn-primary" id="btnPrint">Print Label</button>
                <button type="submit" class="btn btn-success" id="btnExport" formaction="{{ route('admin.racks.export_barcodes') }}" formtarget="_self">Export CSV</button>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div id="rackFilterToolbar" class="rack-filter-toolbar">
            <div class="rack-filter-row rack-filter-row--search">
                <label for="rackSearchInput" class="sr-only">Cari rak</label>
                <div class="rack-search-wrap">
                    <svg class="rack-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.8"/><path d="M14.5 14.5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input
                        type="search"
                        id="rackSearchInput"
                        class="rack-search-input"
                        placeholder="Cari nama / lokasi / QR code rak..."
                        aria-label="Cari rak"
                        autocomplete="off"
                    >
                    <button type="button" id="rackSearchClear" class="rack-search-clear" aria-label="Hapus pencarian" hidden>&#x2715;</button>
                </div>
            </div>
            <div class="rack-filter-row rack-filter-row--type">
                <div class="rack-type-pills" role="group" aria-label="Filter tipe rak">
                    <button type="button" class="rack-filter-pill active" data-type="" aria-pressed="true">Semua</button>
                    <button type="button" class="rack-filter-pill" data-type="storage" aria-pressed="false">Storage</button>
                    <button type="button" class="rack-filter-pill" data-type="display" aria-pressed="false">Display</button>
                </div>
                <span id="rackFilterCounter" class="rack-filter-counter" role="status" aria-live="polite"></span>
            </div>
            <div id="rackFilterEmptyMsg" class="rack-filter-empty" hidden>Tidak ada rak yang cocok dengan filter.</div>
        </div>

        {{-- Desktop Table --}}
        <div class="card rack-table-desktop" style="padding: 0; overflow: hidden;">
            <div class="table-scroll" style="padding: 16px;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px;"><input type="checkbox" id="selectAllRacks" title="Pilih semua"></th>
                            <th>Nama Rak</th>
                            <th>Lokasi</th>
                            <th>QR Value</th>
                            <th>Preview Label QR</th>
                            <th>Status</th>
                            <th>Tipe</th>
                            <th>Produk</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($racks as $rack)
                            @php
                                $rackId = (string) ($rack['id'] ?? '');
                                $rackName = (string) ($rack['name'] ?? '-');
                                $barcodeValue = (string) ($rack['barcode_value'] ?? '');
                                $rackType = (($rack['rack_type'] ?? 'storage') === 'display') ? 'display' : 'storage';
                                $rackTypeLabel = $rackType === 'display' ? 'Display' : 'Storage';
                            @endphp
                            <tr data-rack-name="{{ $rack['name'] ?? '' }}" data-rack-location="{{ $rack['location'] ?? '' }}" data-rack-barcode="{{ $rack['barcode_value'] ?? '' }}" data-rack-type="{{ $rackType }}">
                                <td>
                                    @if($rackId !== '')
                                        <input type="checkbox" class="js-rack-checkbox" name="rack_ids[]" value="{{ $rackId }}">
                                    @endif
                                </td>
                                <td>
                                    <div style="font-weight: 600;">{{ $rackName }}</div>
                                    @if(!empty($rack['description']))
                                        <div style="font-size: 12px; color: var(--color-text-muted);">{{ $rack['description'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $rack['location'] ?? '-' }}</td>
                                <td>
                                    <code style="font-size: 13px; color: var(--color-text-secondary);">{{ $barcodeValue !== '' ? $barcodeValue : '-' }}</code>
                                </td>
                                <td>
                                    @if($barcodeValue !== '')
                                        <div class="rack-label-preview">
                                            <div class="rack-label-name">{{ $rackName }}</div>
                                            <div class="rack-qrcode" data-code="{{ $barcodeValue }}"></div>
                                            <div class="rack-label-value">{{ $barcodeValue }}</div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if(($rack['is_active'] ?? true) === true)
                                        <span class="badge-status active">Aktif</span>
                                    @else
                                        <span class="badge-status inactive">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="rack-type-chip {{ $rackType }}">{{ $rackTypeLabel }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.racks.products', $rackId) }}" class="btn btn-info btn-sm">
                                        {{ count($rack['products'] ?? []) }} Produk
                                    </a>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <a href="{{ route('admin.racks.history', $rackId) }}" class="btn btn-success btn-sm">Riwayat</a>
                                        <a href="{{ route('admin.racks.print_labels', ['rack_ids' => [$rackId]]) }}" target="_blank" class="btn btn-info btn-sm">Print</a>
                                        <a href="{{ route('admin.racks.edit', $rackId) }}" class="btn btn-warning btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.racks.regenerate_barcode', $rackId) }}" data-confirm="Generate ulang QR code rak ini? QR code lama tidak bisa dipakai lagi untuk verifikasi task baru.">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Regenerate QR</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.racks.destroy', $rackId) }}" data-confirm="Yakin hapus rak ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--color-text-muted);">Belum ada data rak. Tambahkan rak dulu agar bisa dipakai untuk task cek rak.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile Card Layout --}}
        <div class="rack-cards-mobile">
            @forelse($racks as $rack)
                @php
                    $rackId = (string) ($rack['id'] ?? '');
                    $rackName = (string) ($rack['name'] ?? '-');
                    $barcodeValue = (string) ($rack['barcode_value'] ?? '');
                    $rackType = (($rack['rack_type'] ?? 'storage') === 'display') ? 'display' : 'storage';
                    $rackTypeLabel = $rackType === 'display' ? 'Display' : 'Storage';
                @endphp
                <div class="rack-mobile-card" data-rack-name="{{ $rack['name'] ?? '' }}" data-rack-location="{{ $rack['location'] ?? '' }}" data-rack-barcode="{{ $rack['barcode_value'] ?? '' }}" data-rack-type="{{ $rackType }}">
                    <div class="rack-mobile-header">
                        <div>
                            @if($rackId !== '')
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="checkbox" class="js-rack-checkbox-mobile" name="rack_ids_mobile[]" value="{{ $rackId }}">
                                    <span class="rack-mobile-name">{{ $rackName }}</span>
                                </label>
                            @else
                                <div class="rack-mobile-name">{{ $rackName }}</div>
                            @endif
                            @if(!empty($rack['description']))
                                <div class="rack-mobile-desc">{{ $rack['description'] }}</div>
                            @endif
                        </div>
                        @if(($rack['is_active'] ?? true) === true)
                            <span class="badge-status active">Aktif</span>
                        @else
                            <span class="badge-status inactive">Nonaktif</span>
                        @endif
                    </div>
                    <div class="rack-mobile-grid">
                        <div>
                            <div class="rack-mobile-field-label">Lokasi</div>
                            <div>{{ $rack['location'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="rack-mobile-field-label">QR Value</div>
                            <code style="font-size: 12px;">{{ $barcodeValue !== '' ? $barcodeValue : '-' }}</code>
                        </div>
                        <div>
                            <div class="rack-mobile-field-label">Tipe</div>
                            <div><span class="rack-type-chip {{ $rackType }}">{{ $rackTypeLabel }}</span></div>
                        </div>
                    </div>
                    <div class="rack-mobile-actions">
                        <a href="{{ route('admin.racks.history', $rackId) }}" class="btn btn-success btn-sm">Riwayat</a>
                        <a href="{{ route('admin.racks.products', $rackId) }}" class="btn btn-info btn-sm">Produk</a>
                        <a href="{{ route('admin.racks.print_labels', ['rack_ids' => [$rackId]]) }}" target="_blank" class="btn btn-info btn-sm">Print</a>
                        <a href="{{ route('admin.racks.edit', $rackId) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form method="POST" action="{{ route('admin.racks.regenerate_barcode', $rackId) }}" data-confirm="Generate ulang QR code rak ini? QR code lama tidak bisa dipakai lagi.">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">Regenerate</button>
                        </form>
                        <form method="POST" action="{{ route('admin.racks.destroy', $rackId) }}" data-confirm="Yakin hapus rak ini?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty">Belum ada data rak. Tambahkan rak dulu agar bisa dipakai untuk task cek rak.</div>
            @endforelse
        </div>
    </form>

    @include('admin.partials._qr-renderer', ['selector' => '.rack-qrcode', 'size' => 70])
    <script>
        (function() {
            // ── existing bulk/select-all refs ──────────────────────────────
            var selectAllEl    = document.getElementById('selectAllRacks');
            var checkboxes     = Array.from(document.querySelectorAll('.js-rack-checkbox'));
            var mobileCheckboxes = Array.from(document.querySelectorAll('.js-rack-checkbox-mobile'));
            var bulkTypeButton = document.getElementById('btnBulkUpdateType');
            var bulkTypeSelect = document.getElementById('bulkRackTypeSelect');
            var bulkTypeForm   = document.getElementById('rackBulkTypeForm');
            var bulkTypeIds    = document.getElementById('bulkRackTypeIds');
            var bulkTypeValue  = document.getElementById('bulkRackTypeValue');

            function getSelectedRackIds() {
                var ids = checkboxes.concat(mobileCheckboxes)
                    .filter(function(cb) { return cb.checked; })
                    .map(function(cb) { return cb.value; })
                    .filter(function(value, index, arr) { return value && arr.indexOf(value) === index; });
                return ids;
            }

            // ── filter refs ────────────────────────────────────────────────
            var searchInput  = document.getElementById('rackSearchInput');
            var searchClear  = document.getElementById('rackSearchClear');
            var filterPills  = Array.from(document.querySelectorAll('.rack-filter-pill'));
            var counter      = document.getElementById('rackFilterCounter');
            var emptyMsg     = document.getElementById('rackFilterEmptyMsg');

            // All desktop rows (tr) and mobile cards keyed by rack id or index
            // We pair them by position: $racks order is the same for both loops.
            var desktopRows  = Array.from(document.querySelectorAll('tr[data-rack-name]'));
            var mobileCards  = Array.from(document.querySelectorAll('.rack-mobile-card[data-rack-name]'));
            var totalRacks   = desktopRows.length; // unique rack count

            // ── sessionStorage persistence ─────────────────────────────────
            var STORAGE_KEY = 'admin.racks.filters';
            var currentSearch = '';
            var currentType   = '';

            function loadFilterState() {
                try {
                    var raw = sessionStorage.getItem(STORAGE_KEY);
                    if (raw) {
                        var state = JSON.parse(raw);
                        currentSearch = state.search || '';
                        currentType   = state.type   || '';
                    }
                } catch(e) {}
            }

            function saveFilterState() {
                try {
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ search: currentSearch, type: currentType }));
                } catch(e) {}
            }

            // ── apply filter ───────────────────────────────────────────────
            function applyFilter() {
                var q    = currentSearch.trim().toLowerCase();
                var type = currentType;
                var visible = 0;

                for (var i = 0; i < desktopRows.length; i++) {
                    var row  = desktopRows[i];
                    var card = mobileCards[i] || null;

                    var name     = (row.getAttribute('data-rack-name')     || '').toLowerCase();
                    var location = (row.getAttribute('data-rack-location') || '').toLowerCase();
                    var barcode  = (row.getAttribute('data-rack-barcode')  || '').toLowerCase();
                    var rtype    = (row.getAttribute('data-rack-type')     || '').toLowerCase();

                    var matchSearch = !q || name.indexOf(q) !== -1 || location.indexOf(q) !== -1 || barcode.indexOf(q) !== -1;
                    var matchType   = !type || rtype === type;
                    var show = matchSearch && matchType;

                    row.style.display = show ? '' : 'none';
                    if (card) card.style.display = show ? '' : 'none';

                    // Uncheck hidden rows' checkboxes
                    if (!show) {
                        var cbs = row.querySelectorAll('.js-rack-checkbox');
                        cbs.forEach(function(cb) { cb.checked = false; });
                        if (card) {
                            var mcbs = card.querySelectorAll('.js-rack-checkbox-mobile');
                            mcbs.forEach(function(cb) { cb.checked = false; });
                        }
                    }

                    if (show) visible++;
                }

                // counter
                if (counter) {
                    counter.textContent = visible + ' dari ' + totalRacks + ' rak';
                }

                // empty state
                if (emptyMsg) {
                    emptyMsg.hidden = visible > 0;
                }

                saveFilterState();
            }

            // ── search input ───────────────────────────────────────────────
            var debounceTimer = null;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    currentSearch = searchInput.value;
                    if (searchClear) searchClear.hidden = !currentSearch;
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(applyFilter, 120);
                });
            }
            if (searchClear) {
                searchClear.addEventListener('click', function() {
                    searchInput.value = '';
                    currentSearch = '';
                    searchClear.hidden = true;
                    applyFilter();
                    searchInput.focus();
                });
            }

            // ── type pills ─────────────────────────────────────────────────
            filterPills.forEach(function(pill) {
                pill.addEventListener('click', function() {
                    currentType = pill.getAttribute('data-type') || '';
                    filterPills.forEach(function(p) {
                        var active = p === pill;
                        p.classList.toggle('active', active);
                        p.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                    applyFilter();
                });
            });

            // ── select all (only visible rows) ─────────────────────────────
            if (selectAllEl) {
                selectAllEl.addEventListener('change', function() {
                    desktopRows.forEach(function(row, i) {
                        if (row.style.display === 'none') return;
                        var cbs = row.querySelectorAll('.js-rack-checkbox');
                        cbs.forEach(function(cb) { cb.checked = selectAllEl.checked; });
                    });
                    // mobile cards: same index
                    mobileCards.forEach(function(card) {
                        if (card.style.display === 'none') return;
                        var cbs = card.querySelectorAll('.js-rack-checkbox-mobile');
                        cbs.forEach(function(cb) { cb.checked = selectAllEl.checked; });
                    });
                });
            }

            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    if (selectAllEl) {
                        var visibleCbs = checkboxes.filter(function(c) {
                            var row = c.closest('tr[data-rack-name]');
                            return !row || row.style.display !== 'none';
                        });
                        selectAllEl.checked = visibleCbs.length > 0 && visibleCbs.every(function(item) { return item.checked; });
                    }
                });
            });

            // ── bulk type update ───────────────────────────────────────────
            if (bulkTypeButton && bulkTypeForm && bulkTypeIds && bulkTypeValue && bulkTypeSelect) {
                bulkTypeButton.addEventListener('click', function() {
                    var selectedIds = getSelectedRackIds();
                    if (selectedIds.length === 0) {
                        alert('Pilih minimal satu rak untuk update tipe.');
                        return;
                    }
                    var targetType = bulkTypeSelect.value === 'display' ? 'Display' : 'Storage';
                    if (!confirm('Ubah tipe ' + selectedIds.length + ' rak menjadi ' + targetType + '?')) return;
                    bulkTypeValue.value = bulkTypeSelect.value;
                    bulkTypeIds.innerHTML = '';
                    selectedIds.forEach(function(id) {
                        var input = document.createElement('input');
                        input.type  = 'hidden';
                        input.name  = 'rack_ids[]';
                        input.value = id;
                        bulkTypeIds.appendChild(input);
                    });
                    bulkTypeForm.submit();
                });
            }

            // ── print / export smart handlers ──────────────────────────────
            var bulkForm = document.getElementById('rackBulkActionForm');
            var btnPrint  = document.getElementById('btnPrint');
            var btnExport = document.getElementById('btnExport');

            if (bulkForm && btnPrint) {
                btnPrint.addEventListener('click', function(e) {
                    var selected = checkboxes.filter(function(cb) { return cb.checked; });
                    if (selected.length === 0) {
                        e.preventDefault();
                        if (confirm('Tidak ada rak yang dipilih. Print semua rak?')) {
                            bulkForm.action = '{{ route("admin.racks.print_labels", ["all" => 1]) }}';
                            bulkForm.submit();
                        }
                    }
                });
            }

            if (bulkForm && btnExport) {
                btnExport.addEventListener('click', function(e) {
                    var selected = checkboxes.filter(function(cb) { return cb.checked; });
                    if (selected.length === 0) {
                        e.preventDefault();
                        if (confirm('Tidak ada rak yang dipilih. Export semua rak?')) {
                            btnExport.formAction = '{{ route("admin.racks.export_barcodes", ["all" => 1]) }}';
                            bulkForm.target = '_self';
                            bulkForm.submit();
                        }
                    }
                });
            }

            // ── delegated confirm ──────────────────────────────────────────
            document.addEventListener('submit', function(e) {
                var form = e.target;
                var confirmMsg = form.getAttribute('data-confirm');
                if (confirmMsg && !confirm(confirmMsg)) {
                    e.preventDefault();
                }
            });

            // ── init ───────────────────────────────────────────────────────
            loadFilterState();
            if (searchInput && currentSearch) {
                searchInput.value = currentSearch;
                if (searchClear) searchClear.hidden = false;
            }
            if (currentType) {
                filterPills.forEach(function(p) {
                    var active = (p.getAttribute('data-type') || '') === currentType;
                    p.classList.toggle('active', active);
                    p.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            applyFilter();
        })();
    </script>
@endsection
