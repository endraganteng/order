@extends('admin.layout')

@section('title', 'Master Rak - Admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 10px; flex-wrap: wrap;">
        <h2 style="color: #333; margin: 0; font-size: clamp(24px, 5vw, 32px);">📦 Master Rak & Barcode</h2>
        <a href="{{ route('admin.racks.create') }}" class="btn btn-primary">➕ Tambah Rak</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">✅ {{ session('success') }}</div>
    @endif

    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto; padding: 16px;">
            <table style="display: table; min-width: 980px;">
                <thead>
                    <tr>
                        <th>Nama Rak</th>
                        <th>Lokasi</th>
                        <th>Barcode Value</th>
                        <th>Preview Barcode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($racks as $rack)
                        @php
                            $barcodeValue = $rack['barcode_value'] ?? '-';
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight: 600;">{{ $rack['name'] ?? '-' }}</div>
                                @if(!empty($rack['description']))
                                    <div style="font-size: 12px; color: #666;">{{ $rack['description'] }}</div>
                                @endif
                            </td>
                            <td>{{ $rack['location'] ?? '-' }}</td>
                            <td>
                                <code style="font-size: 13px; color: #334155;">{{ $barcodeValue }}</code>
                            </td>
                            <td>
                                @if(!empty($barcodeValue))
                                    <svg class="rack-barcode" data-barcode="{{ $barcodeValue }}"></svg>
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
                                    <a href="{{ route('admin.racks.edit', $rack['id']) }}" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px;">✏️ Edit</a>

                                    <form method="POST" action="{{ route('admin.racks.regenerate_barcode', $rack['id']) }}" onsubmit="return confirm('Generate ulang barcode rak ini? Barcode lama tidak bisa dipakai lagi untuk verifikasi task baru.')">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 6px 10px; font-size: 12px; background: #2563eb; color: #fff;">🔄 Generate Ulang</button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.racks.destroy', $rack['id']) }}" onsubmit="return confirm('Yakin hapus rak ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;">🗑️ Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: #777;">Belum ada data rak. Tambahkan rak dulu agar bisa dipakai untuk task cek rak.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        document.querySelectorAll('.rack-barcode').forEach((el) => {
            const value = String(el.getAttribute('data-barcode') || '').trim();
            if (!value) return;

            try {
                JsBarcode(el, value, {
                    format: 'CODE128',
                    width: 1.4,
                    height: 44,
                    displayValue: true,
                    fontSize: 12,
                    margin: 0,
                });
            } catch (error) {
                el.outerHTML = '<span style="font-size:12px;color:#b91c1c;">Barcode invalid</span>';
            }
        });
    </script>
@endsection
