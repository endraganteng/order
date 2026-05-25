@extends('admin.layout')

@section('title', '⚙️ Pengaturan Kasbon')

@section('content')
<div class="container">
    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.kasbon.index') }}" style="color: #667eea; text-decoration: none; font-size: 13px;">← Kembali ke daftar kasbon</a>
    </div>

    <div class="page-header" style="margin-bottom: 20px;">
        <h2>⚙️ Pengaturan Kasbon</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Konfigurasi default limit, minimal nominal, dan aturan auto-deduct.</p>
    </div>

    @if(session('success'))
        <div style="background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="card" style="padding: 20px; max-width: 600px;">
        <form method="POST" action="{{ route('admin.kasbon.settings_update') }}">
            @csrf
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Default Limit (%)</label>
                <input type="number" name="default_limit_percent" value="{{ $config['default_limit_percent'] }}" min="1" max="100" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                <p style="font-size:12px; color:#64748b; margin-top:4px;">Persentase dari gaji berjalan (prorated harian). Contoh: 30% = max kasbon 30% dari gaji yang sudah berjalan bulan ini.</p>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Limit Fixed (Rp)</label>
                <input type="number" name="kasbon_limit_fixed" value="{{ $config['kasbon_limit_fixed'] }}" min="0" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                <p style="font-size:12px; color:#64748b; margin-top:4px;">Tambahan limit nominal tetap. Juga dipakai sebagai fallback jika gaji belum di-set.</p>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Minimal Kasbon (Rp)</label>
                <input type="number" name="min_kasbon_amount" value="{{ $config['min_kasbon_amount'] }}" min="0" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Max Kasbon Aktif per Karyawan</label>
                <input type="number" name="max_active_kasbon" value="{{ $config['max_active_kasbon'] }}" min="1" max="10" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="auto_deduct_enabled" value="1" {{ $config['auto_deduct_enabled'] === '1' ? 'checked' : '' }} style="width:16px; height:16px;">
                    <span style="font-weight:600; font-size:13px;">Auto-deduct dari gaji/bonus</span>
                </label>
                <p style="font-size:12px; color:#64748b; margin-top:4px; margin-left:24px;">Jika aktif, setiap credit masuk (gaji/bonus) otomatis dipotong untuk kasbon.</p>
            </div>
            <button type="submit" style="padding:10px 20px; border-radius:6px; border:none; background:#667eea; color:#fff; cursor:pointer; font-size:14px; font-weight:600;">Simpan Pengaturan</button>
        </form>
    </div>
</div>
@endsection
