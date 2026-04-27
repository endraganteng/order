@extends('admin.layout')

@section('title', 'Edit Rak - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">Edit Rak</h2>

    <div class="card" style="max-width: 760px;">
        <form method="POST" action="{{ route('admin.racks.update', $rack['id']) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Nama Rak</label>
                <input type="text" name="name" value="{{ old('name', $rack['name'] ?? '') }}" required maxlength="120"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('name')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Lokasi Rak</label>
                <input type="text" name="location" value="{{ old('location', $rack['location'] ?? '') }}" required maxlength="120"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('location')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Deskripsi (opsional)</label>
                <textarea name="description" rows="3" maxlength="1000"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px; resize: vertical;">{{ old('description', $rack['description'] ?? '') }}</textarea>
                @error('description')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Status</label>
                <select name="is_active" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                    <option value="1" {{ old('is_active', ($rack['is_active'] ?? true) ? 1 : 0) ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ !old('is_active', ($rack['is_active'] ?? true) ? 1 : 0) ? 'selected' : '' }}>Nonaktif</option>
                </select>
                @error('is_active')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
                <div style="font-weight: 600; color: #334155; margin-bottom: 8px;">QR Code Saat Ini</div>
                <code style="display: inline-block; margin-bottom: 10px; color: #0f172a;">{{ $rack['barcode_value'] ?? '-' }}</code>
                <div>
                    <div id="rack-qrcode-preview" data-code="{{ $rack['barcode_value'] ?? '' }}"></div>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                    Untuk generate ulang QR code, gunakan tombol "Generate Ulang" di halaman daftar rack.
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Update Rak</button>
                <a href="{{ route('admin.racks.index') }}" class="btn" style="background: #6c757d; color: #fff;">Batal</a>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const preview = document.getElementById('rack-qrcode-preview');
        const value = String(preview?.getAttribute('data-code') || '').trim();
        if (preview && value) {
            try {
                preview.innerHTML = '';
                new QRCode(preview, {
                    text: value,
                    width: 110,
                    height: 110,
                    correctLevel: QRCode.CorrectLevel.M,
                });
            } catch (error) {
                preview.outerHTML = '<span style="font-size:12px;color:#b91c1c;">QR code invalid</span>';
            }
        }
    </script>
@endsection
