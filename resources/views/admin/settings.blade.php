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

            <hr style="margin: 30px 0; border: none; border-top: 2px solid #e0e0e0;">

            <h3 style="margin-bottom: 15px; color: #333;">Notifikasi WhatsApp (Fonnte)</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                Kirim notifikasi WhatsApp otomatis ke waiter saat tugas baru didelegasikan atau tugas overdue.
            </p>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Aktifkan Notifikasi WA</label>
                <select name="fonnte_enabled"
                    style="width: 200px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                    <option value="1" {{ old('fonnte_enabled', $settings['fonnte_enabled'] ?? false) ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ !old('fonnte_enabled', $settings['fonnte_enabled'] ?? false) ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Fonnte API Token</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                    Token dari dashboard <a href="https://fonnte.com" target="_blank" style="color: #007bff;">fonnte.com</a>. Pastikan device sudah terhubung.
                </p>
                <input type="text" name="fonnte_api_token"
                    value="{{ old('fonnte_api_token', $settings['fonnte_api_token'] ?? '') }}"
                    placeholder="Masukkan token Fonnte"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('fonnte_api_token')
                    <span style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>

    {{-- Test Fonnte --}}
    <div class="card" style="margin-top: 20px;">
        <h3 style="margin-bottom: 15px; color: #333;">🧪 Test Kirim Pesan WhatsApp</h3>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
            Pastikan Fonnte sudah diaktifkan dan token sudah disimpan sebelum test.
        </p>

        @if(session('fonnte_success'))
            <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;">
                ✅ {{ session('fonnte_success') }}
            </div>
        @endif

        @if(session('fonnte_error'))
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;">
                ❌ {{ session('fonnte_error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.test_fonnte') }}">
            @csrf

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Nomor HP Tujuan</label>
                <input type="text" name="test_phone" value="{{ old('test_phone') }}" required
                    placeholder="08123456789"
                    style="width: 300px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('test_phone')
                    <span style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">Pesan (opsional)</label>
                <textarea name="test_message" rows="3"
                    placeholder="Kosongkan untuk pesan default test"
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px; resize: vertical;">{{ old('test_message') }}</textarea>
            </div>

            <button type="submit" class="btn" style="background: #28a745; color: white;">
                📤 Kirim Test
            </button>
        </form>
    </div>
@endsection