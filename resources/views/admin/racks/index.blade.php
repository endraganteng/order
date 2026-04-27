@extends('admin.layout')

@section('title', 'Master Rak - Admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 10px; flex-wrap: wrap;">
        <h2 style="color: #333; margin: 0; font-size: clamp(24px, 5vw, 32px);">📦 Master Rak & QR Code</h2>
        <a href="{{ route('admin.racks.create') }}" class="btn btn-primary">➕ Tambah Rak</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">✅ {{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert" style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b;">⚠️ {{ session('error') }}</div>
    @endif

    <style>
        .rack-bulk-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            padding: 12px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #f8fbff;
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
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            background: #fff;
        }
        .rack-label-name {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rack-label-value {
            font-size: 11px;
            color: #64748b;
        }
        .rack-qrcode {
            width: 70px;
            height: 70px;
            background: #fff;
        }
    </style>

    <form id="rackBulkActionForm" method="GET" action="{{ route('admin.racks.print_labels') }}" target="_blank">
        <div class="rack-bulk-card">
            <div>
                <div style="font-size: 14px; font-weight: 700; color: #1e293b;">🖨️ Print / Export QR Code Rak (Massal)</div>
                <div style="font-size: 12px; color: #64748b;">Pilih beberapa rak lalu print label massal atau export CSV. Label akan menampilkan nama rak + QR value.</div>
            </div>
            <div class="rack-bulk-actions">
                <button type="submit" class="btn btn-primary" data-requires-selection="1">🖨️ Print Terpilih</button>
                <button type="submit" class="btn" formaction="{{ route('admin.racks.print_labels', ['all' => 1]) }}" style="background:#1d4ed8; color:#fff;">🖨️ Print Semua</button>
                <button type="submit" class="btn btn-success" data-requires-selection="1" formaction="{{ route('admin.racks.export_barcodes') }}" formtarget="_self">📤 Export CSV Terpilih</button>
                <button type="submit" class="btn" formaction="{{ route('admin.racks.export_barcodes', ['all' => 1]) }}" formtarget="_self" style="background:#15803d; color:#fff;">📤 Export CSV Semua</button>
            </div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="overflow-x: auto; padding: 16px;">
                <table style="display: table; min-width: 1100px;">
                    <thead>
                        <tr>
                            <th style="width:44px;"><input type="checkbox" id="selectAllRacks" title="Pilih semua"></th>
                            <th>Nama Rak</th>
                            <th>Lokasi</th>
                            <th>QR Value</th>
                            <th>Preview Label QR</th>
                            <th>Status</th>
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
                                        <div style="font-size: 12px; color: #666;">{{ $rack['description'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $rack['location'] ?? '-' }}</td>
                                <td>
                                    <code style="font-size: 13px; color: #334155;">{{ $barcodeValue !== '' ? $barcodeValue : '-' }}</code>
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
                                        <span class="badge badge-success">Aktif</span>
                                    @else
                                        <span class="badge badge-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <a href="{{ route('admin.racks.print_labels', ['rack_ids' => [$rackId]]) }}" target="_blank" class="btn" style="padding: 6px 10px; font-size: 12px; background:#1d4ed8; color:#fff;">🖨️ Print Label</a>
                                        <a href="{{ route('admin.racks.edit', $rackId) }}" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px;">✏️ Edit</a>

                                        <form method="POST" action="{{ route('admin.racks.regenerate_barcode', $rackId) }}" onsubmit="return confirm('Generate ulang QR code rak ini? QR code lama tidak bisa dipakai lagi untuk verifikasi task baru.')">
                                            @csrf
                                            <button type="submit" class="btn" style="padding: 6px 10px; font-size: 12px; background: #2563eb; color: #fff;">🔄 Generate Ulang</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.racks.destroy', $rackId) }}" onsubmit="return confirm('Yakin hapus rak ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;">🗑️ Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: #777;">Belum ada data rak. Tambahkan rak dulu agar bisa dipakai untuk task cek rak.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const rackBulkActionForm = document.getElementById('rackBulkActionForm');
        const selectAllRacksEl = document.getElementById('selectAllRacks');
        const rackCheckboxes = Array.from(document.querySelectorAll('.js-rack-checkbox'));

        document.querySelectorAll('.rack-qrcode').forEach((el) => {
            const value = String(el.getAttribute('data-code') || '').trim();
            if (!value) return;

            try {
                el.innerHTML = '';
                new QRCode(el, {
                    text: value,
                    width: 70,
                    height: 70,
                    correctLevel: QRCode.CorrectLevel.M,
                });
            } catch (error) {
                el.outerHTML = '<span style="font-size:12px;color:#b91c1c;">QR code invalid</span>';
            }
        });

        if (selectAllRacksEl) {
            selectAllRacksEl.addEventListener('change', () => {
                rackCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectAllRacksEl.checked;
                });
            });
        }

        rackCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (!selectAllRacksEl) {
                    return;
                }

                const allChecked = rackCheckboxes.length > 0 && rackCheckboxes.every((item) => item.checked);
                selectAllRacksEl.checked = allChecked;
            });
        });

        if (rackBulkActionForm) {
            rackBulkActionForm.addEventListener('submit', (event) => {
                const submitter = event.submitter;
                const requiresSelection = submitter && submitter.getAttribute('data-requires-selection') === '1';
                if (!requiresSelection) {
                    return;
                }

                const selectedCount = rackCheckboxes.filter((checkbox) => checkbox.checked).length;
                if (selectedCount === 0) {
                    event.preventDefault();
                    alert('Pilih minimal satu rak terlebih dahulu.');
                }
            });
        }
    </script>
@endsection
