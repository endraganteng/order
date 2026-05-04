@extends('admin.layout')

@section('title', 'Tambah Rak - Admin')

@section('content')
    <div class="page-header">
        <h2 class="page-title">Tambah Rak Baru</h2>
    </div>

    <div class="card" style="max-width: 720px;">
        <form method="POST" action="{{ route('admin.racks.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="rack-name">Nama Rak</label>
                <input type="text" id="rack-name" name="name" value="{{ old('name') }}" required maxlength="120"
                    placeholder="Contoh: Rak Minuman Kulkas 1"
                    class="form-input">
                @error('name')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="rack-location">Lokasi Rak</label>
                <input type="text" id="rack-location" name="location" value="{{ old('location') }}" required maxlength="120"
                    placeholder="Contoh: Area Bar Sisi Kanan"
                    class="form-input">
                @error('location')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="rack-description">Deskripsi (opsional)</label>
                <textarea id="rack-description" name="description" rows="3" maxlength="1000"
                    placeholder="Contoh: Wajib cek kebersihan, kerapian, dan ketersediaan stok"
                    class="form-textarea">{{ old('description') }}</textarea>
                @error('description')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label-inline">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                    Rak aktif (bisa dipilih saat membuat task)
                </label>
            </div>

            <div class="form-info-box" style="margin-bottom: 20px;">
                QR code akan otomatis digenerate setelah rak disimpan, lalu bisa langsung dipakai untuk verifikasi scan oleh waiter.
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan Rak</button>
                <a href="{{ route('admin.racks.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
