@extends('admin.layout')

@section('title', 'Cleanup Orders - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">🗑️ Cleanup Orders</h2>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    {{-- Statistics --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <x-stat-card label="Total Orders" :value="$stats['total_orders']" />
        <x-stat-card label="> 30 Hari" :value="$stats['orders_30_days']" color="#ffc107" />
        <x-stat-card label="> 60 Hari" :value="$stats['orders_60_days']" color="#ff9800" />
        <x-stat-card label="> 90 Hari" :value="$stats['orders_90_days']" color="#f44336" />
    </div>

    {{-- Warning Info --}}
    <x-alert type="warning">
        <h4 style="margin: 0 0 10px 0;">Peringatan</h4>
        <p style="margin: 0; line-height: 1.6;">
            Cleanup akan <strong>menghapus permanen</strong> order yang lebih lama dari jumlah hari yang ditentukan.
            <br>Data yang sudah dihapus <strong>tidak dapat dikembalikan</strong>.
            <br><br>
            <strong>Rekomendasi:</strong> Hapus order > 30 hari untuk menghemat storage Firebase.
        </p>
    </x-alert>

    {{-- Cleanup Form --}}
    <div class="card">
        <h3 style="margin-bottom: 20px;">Hapus Order Lama</h3>

        <form method="POST" action="{{ route('admin.cleanup.process') }}"
            onsubmit="return confirm('Apakah Anda yakin ingin menghapus order lama? Tindakan ini tidak dapat dibatalkan!');">
            @csrf

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Hapus order lebih lama dari (hari):
                </label>
                <select name="days_old" required
                    style="width: 100%; max-width: 300px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
                    <option value="30">30 hari</option>
                    <option value="60">60 hari</option>
                    <option value="90">90 hari</option>
                    <option value="180">180 hari (6 bulan)</option>
                    <option value="365">365 hari (1 tahun)</option>
                </select>
                @error('days_old')
                    <p style="color: #dc3545; font-size: 14px; margin-top: 5px;">{{ $message }}</p>
                @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn" style="background: #dc3545; color: white;">
                    🗑️ Hapus Order Lama
                </button>
                <a href="{{ route('admin.dashboard') }}" class="btn" style="background: #6c757d; color: white;">
                    Batal
                </a>
            </div>
        </form>
    </div>

    {{-- Info Box --}}
    <div style="margin-top: 30px; padding: 20px; background: #e7f3ff; border: 1px solid #2196F3; border-radius: 8px;">
        <h4 style="margin: 0 0 10px 0; color: #0d47a1;">💡 Tips Menghemat Storage</h4>
        <ul style="margin: 0; padding-left: 20px; color: #0d47a1; line-height: 1.8;">
            <li>Jalankan cleanup secara rutin (misalnya setiap bulan)</li>
            <li>Hapus order > 30 hari jika tidak diperlukan untuk laporan</li>
            <li>Monitor usage Firebase di Console untuk melihat penggunaan storage</li>
            <li>Pertimbangkan export data penting sebelum cleanup</li>
        </ul>
    </div>
@endsection