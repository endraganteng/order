@extends('admin.layout')

@section('title', 'Edit Supplier - Admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="color: var(--color-text-dark, #333);">Edit Supplier</h2>
        <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier['id'] ?? $supplier->id) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Nama Supplier</label>
                <input type="text" name="name" value="{{ old('name', $supplier['name'] ?? $supplier->name) }}" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('name')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">No. HP / WhatsApp</label>
                <input type="text" name="phone" value="{{ old('phone', $supplier['phone'] ?? $supplier->phone) }}" required
                    placeholder="Contoh: 08123456789"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('phone')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                    Contact Person <span style="color: #777; font-weight: normal;">(opsional)</span>
                </label>
                <input type="text" name="contact_person" value="{{ old('contact_person', $supplier['contact_person'] ?? $supplier->contact_person) }}"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('contact_person')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                    Alamat <span style="color: #777; font-weight: normal;">(opsional)</span>
                </label>
                <textarea name="address" rows="3"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px; resize: vertical;">{{ old('address', $supplier['address'] ?? $supplier->address) }}</textarea>
                @error('address')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
