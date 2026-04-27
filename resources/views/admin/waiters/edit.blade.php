@extends('admin.layout')

@section('title', 'Edit Waiter - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">Edit Waiter</h2>

    <div class="card">
        <form method="POST" action="{{ route('admin.waiters.update', $waiter['id']) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Nama</label>
                <input type="text" name="name" value="{{ old('name', $waiter['name']) }}" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('name')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Email</label>
                <input type="email" name="email" value="{{ old('email', $waiter['email']) }}" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('email')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Status</label>
                <select name="is_active" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                    <option value="1" {{ old('is_active', $waiter['is_active']) ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ !old('is_active', $waiter['is_active']) ? 'selected' : '' }}>Nonaktif</option>
                </select>
                @error('is_active')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Role Waiter</label>
                <select name="waiter_role" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                    @php $waiterRoleValue = old('waiter_role', $waiter['waiter_role'] ?? 'pelayan'); @endphp
                    <option value="pelayan" {{ $waiterRoleValue === 'pelayan' ? 'selected' : '' }}>Pelayan</option>
                    <option value="kasir" {{ $waiterRoleValue === 'kasir' ? 'selected' : '' }}>Kasir</option>
                </select>
                @error('waiter_role')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                    Password Cadangan Baru <span style="color: #777; font-weight: normal;">(opsional)</span>
                </label>
                <input type="password" name="password" minlength="6"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;"
                    placeholder="Kosongkan jika tidak ingin ganti password cadangan">
                <div style="font-size: 12px; color: #666; margin-top: 6px;">
                    Login utama waiter tetap melalui Google Auth berdasarkan email waiter ini.
                </div>
                @error('password')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.waiters.index') }}" class="btn"
                    style="background: #6c757d; color: white;">Batal</a>
            </div>
        </form>
    </div>
@endsection
