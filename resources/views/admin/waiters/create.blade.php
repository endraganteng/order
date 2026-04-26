@extends('admin.layout')

@section('title', 'Tambah Waiter - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">Tambah Waiter Baru</h2>

    <div class="card">
        <form method="POST" action="{{ route('admin.waiters.store') }}">
            @csrf

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Nama</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('name')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('email')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                    Password Cadangan <span style="color: #777; font-weight: normal;">(opsional)</span>
                </label>
                <input type="password" name="password" minlength="6"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                <div style="font-size: 12px; color: #666; margin-top: 6px;">
                    Login utama waiter menggunakan Google Auth berdasarkan email di atas.
                    Isi password hanya jika ingin menyimpan login cadangan manual.
                </div>
                @error('password')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.waiters.index') }}" class="btn"
                    style="background: #6c757d; color: white;">Batal</a>
            </div>
        </form>
    </div>
@endsection
