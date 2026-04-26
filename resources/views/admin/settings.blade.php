@extends('admin.layout')

@section('title', 'Settings - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">Settings</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Waktu Timeout Order
                    (Menit)</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Order akan otomatis hilang dari kasir setelah
                    waktu ini</p>
                <input type="number" name="order_timeout_minutes"
                    value="{{ old('order_timeout_minutes', $settings['order_timeout_minutes'] ?? 3) }}" min="1" required
                    style="width: 200px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('order_timeout_minutes')
                    <span style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection