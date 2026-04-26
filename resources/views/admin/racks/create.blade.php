@extends('admin.layout')

@section('title', 'Tambah Rak - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">Tambah Rak Baru</h2>

    <div class="card" style="max-width: 720px;">
        <form method="POST" action="{{ route('admin.racks.store') }}">
            @csrf

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Nama Rak</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                    placeholder="Contoh: Rak Minuman Kulkas 1"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('name')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Lokasi Rak</label>
                <input type="text" name="location" value="{{ old('location') }}" required maxlength="120"
                    placeholder="Contoh: Area Bar Sisi Kanan"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('location')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Deskripsi (opsional)</label>
                <textarea name="description" rows="3" maxlength="1000"
                    placeholder="Contoh: Wajib cek kebersihan, kerapian, dan ketersediaan stok"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px; resize: vertical;">{{ old('description') }}</textarea>
                @error('description')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #555; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                    Rak aktif (bisa dipilih saat membuat task)
                </label>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #334155; font-size: 13px;">
                Barcode akan otomatis digenerate setelah rak disimpan, lalu bisa langsung dipakai untuk verifikasi scan oleh waiter.
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Simpan Rak</button>
                <a href="{{ route('admin.racks.index') }}" class="btn" style="background: #6c757d; color: #fff;">Batal</a>
            </div>
        </form>
    </div>
@endsection
