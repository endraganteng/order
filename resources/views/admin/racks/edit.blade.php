@extends('admin.layout')

@section('title', 'Edit Rak - Admin')

@section('content')
    <div class="page-header">
        <h2 class="page-title">Edit Rak</h2>
    </div>

    <div class="card" style="max-width: 720px;">
        <form method="POST" action="{{ route('admin.racks.update', $rack['id']) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="rack-name">Nama Rak</label>
                <input type="text" id="rack-name" name="name" value="{{ old('name', $rack['name'] ?? '') }}" required maxlength="120"
                    class="form-input">
                @error('name')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="rack-location">Lokasi Rak</label>
                <input type="text" id="rack-location" name="location" value="{{ old('location', $rack['location'] ?? '') }}" required maxlength="120"
                    class="form-input">
                @error('location')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="rack-description">Deskripsi (opsional)</label>
                <textarea id="rack-description" name="description" rows="3" maxlength="1000"
                    class="form-textarea">{{ old('description', $rack['description'] ?? '') }}</textarea>
                @error('description')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="rack-check-order">Urutan Cek (prioritas)</label>
                <input type="number" id="rack-check-order" name="check_order" value="{{ old('check_order', $rack['check_order'] ?? 0) }}" min="0" max="999"
                    class="form-input" style="max-width: 120px;">
                <div class="form-hint">Angka kecil = dicek lebih dulu. 0 = tanpa urutan khusus.</div>
                @error('check_order')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label-inline">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', ($rack['is_active'] ?? true)) ? 'checked' : '' }}>
                    Rak aktif (bisa dipilih saat membuat task)
                </label>
            </div>

            <div class="form-info-box" style="margin-bottom: 20px;">
                <div style="font-weight: 600; color: var(--color-text); margin-bottom: 8px;">QR Code Saat Ini</div>
                <code style="display: inline-block; margin-bottom: 10px; color: var(--color-text);">{{ $rack['barcode_value'] ?? '-' }}</code>
                <div>
                    <div id="rack-qrcode-preview" data-code="{{ $rack['barcode_value'] ?? '' }}"></div>
                </div>
                <div class="form-hint" style="margin-top: 8px;">
                    Untuk generate ulang QR code, gunakan tombol "Generate Ulang" di halaman daftar rack.
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Rak</button>
                <a href="{{ route('admin.racks.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    @include('admin.partials._qr-renderer', ['selector' => '#rack-qrcode-preview', 'size' => 110])
@endsection
