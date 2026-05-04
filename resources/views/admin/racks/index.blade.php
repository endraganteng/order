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

    <form id="rackBulkActionForm" method="GET" action="{{ route('admin.racks.print_labels') }}" target="_blank">
        <div class="rack-bulk-card">
            <div>
                <div style="font-size: 14px; font-weight: 700; color: var(--color-text);">Print / Export QR Code Rak</div>
                <div style="font-size: 12px; color: var(--color-text-muted);">Pilih rak lalu print label atau export CSV. Jika tidak ada yang dipilih, semua rak akan diproses.</div>
            </div>
            <div class="rack-bulk-actions">
                <button type="submit" class="btn btn-primary" id="btnPrint">Print Label</button>
                <button type="submit" class="btn btn-success" id="btnExport" formaction="{{ route('admin.racks.export_barcodes') }}" formtarget="_self">Export CSV</button>
            </div>
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
                            @endphp
                            <tr>
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
                                    <a href="{{ route('admin.racks.products', $rackId) }}" class="btn btn-info btn-sm">
                                        {{ count($rack['products'] ?? []) }} Produk
                                    </a>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
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
                                <td colspan="8" style="text-align: center; color: var(--color-text-muted);">Belum ada data rak. Tambahkan rak dulu agar bisa dipakai untuk task cek rak.</td>
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
                @endphp
                <div class="rack-mobile-card">
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
                    </div>
                    <div class="rack-mobile-actions">
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
            // Select all checkbox
            var selectAllEl = document.getElementById('selectAllRacks');
            var checkboxes = Array.from(document.querySelectorAll('.js-rack-checkbox'));

            if (selectAllEl) {
                selectAllEl.addEventListener('change', function() {
                    checkboxes.forEach(function(cb) { cb.checked = selectAllEl.checked; });
                });
            }

            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    if (selectAllEl) {
                        selectAllEl.checked = checkboxes.length > 0 && checkboxes.every(function(item) { return item.checked; });
                    }
                });
            });

            // Smart bulk actions — auto-detect selected vs all
            var bulkForm = document.getElementById('rackBulkActionForm');
            var btnPrint = document.getElementById('btnPrint');
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

            // Delegated confirm handler (replaces inline onsubmit)
            document.addEventListener('submit', function(e) {
                var form = e.target;
                var confirmMsg = form.getAttribute('data-confirm');
                if (confirmMsg && !confirm(confirmMsg)) {
                    e.preventDefault();
                }
            });
        })();
    </script>
@endsection
