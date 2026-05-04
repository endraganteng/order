@extends('admin.layout')

@section('title', 'QR Absensi - Admin')

@push('styles')
<style>
    .qr-config-card {
        max-width: 520px;
        margin: 0 auto;
        text-align: center;
    }
    .qr-preview-box {
        display: inline-block;
        padding: 20px;
        background: #fff;
        border: 2px solid var(--color-border);
        border-radius: var(--radius-lg);
        margin-bottom: 16px;
    }
    .qr-preview-box canvas { display: block; }
    .qr-value-display {
        font-family: 'Courier New', monospace;
        font-size: 14px;
        color: var(--color-text-muted);
        background: #f1f5f9;
        padding: 8px 14px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        word-break: break-all;
    }
    .qr-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .qr-instructions {
        background: var(--color-info-bg);
        border: 1px solid var(--color-info-border);
        border-radius: var(--radius-md);
        padding: 14px;
        font-size: 13px;
        color: #0c4a6e;
        text-align: left;
        line-height: 1.6;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">QR Code Absensi</h2>
        <p class="page-subtitle">Kelola QR code untuk absen masuk/keluar waiter</p>
    </div>
    <a href="{{ route('admin.attendance.index') }}" class="btn btn-secondary">◀ Kembali</a>
</div>

<div class="card qr-config-card">
    <div class="qr-preview-box">
        <div id="qrCodeContainer" style="width:220px;height:220px;"></div>
    </div>

    <div class="qr-value-display" id="qrValueDisplay">{{ $qrValue }}</div>

    <div class="qr-actions">
        <button class="btn btn-danger" onclick="regenerateQr()">🔄 Regenerate QR</button>
        <a href="{{ route('admin.attendance.qr.print') }}" class="btn btn-primary" target="_blank">🖨️ Print QR</a>
    </div>

    <div class="qr-instructions">
        <strong>Petunjuk:</strong><br>
        1. Tempel QR ini di lokasi strategis toko (dekat pintu masuk, area kasir, dll).<br>
        2. Waiter scan QR ini dari portal waiter untuk absen masuk/keluar.<br>
        3. Klik "Regenerate QR" jika QR code perlu diganti (misalnya bocor ke pihak luar).<br>
        4. Gunakan "Print QR" untuk mencetak QR dalam ukuran besar.
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>
<script>
var qrValue = @json($qrValue);

function renderQr(value) {
    var container = document.getElementById('qrCodeContainer');
    container.innerHTML = '';
    new QRCode(container, {
        text: value,
        width: 220,
        height: 220,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
}

renderQr(qrValue);

function regenerateQr() {
    if (!confirm('Regenerate QR code? QR lama tidak akan bisa digunakan lagi.')) return;

    fetch('{{ route("admin.attendance.qr.regenerate") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            qrValue = data.qr_value;
            document.getElementById('qrValueDisplay').textContent = qrValue;
            renderQr(qrValue);
            alert('QR code berhasil di-regenerate!');
        } else {
            alert(data.message || 'Gagal regenerate');
        }
    })
    .catch(function() { alert('Terjadi kesalahan jaringan'); });
}
</script>
@endpush
