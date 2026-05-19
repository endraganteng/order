@extends('admin.layout')

@section('title', 'Sinkronisasi Data')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">🔄 Sinkronisasi Data</h1>
            <p class="fm-page-subtitle">Tarik data dari API Shift Kasir ke sistem finance</p>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,350px),1fr));gap:20px;">
        {{-- Sync Hari Ini --}}
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;">📅 Sync Hari Ini</h3>
            <p style="font-size:13px;color:#64748b;margin-bottom:14px;">Refresh data terbaru untuk hari ini ({{ date('d M Y') }})</p>
            <button class="fm-btn fm-btn-primary" id="btnSyncToday">🔄 Sync Hari Ini</button>
            <div id="resultToday" style="margin-top:12px;"></div>
        </div>

        {{-- Manual Sync --}}
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;">📆 Manual Sync (Pilih Tanggal)</h3>
            <form id="manualSyncForm">
                <div class="fm-form-group">
                    <label class="fm-label">Dari Tanggal</label>
                    <input type="date" class="fm-input" name="from" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">Sampai Tanggal</label>
                    <input type="date" class="fm-input" name="to" value="{{ date('Y-m-d') }}" required>
                </div>
                <button type="submit" class="fm-btn fm-btn-primary">🔄 Mulai Sync</button>
            </form>
            <div id="resultManual" style="margin-top:12px;"></div>
        </div>
    </div>

    {{-- Sync Result Detail --}}
    <div id="syncDetail" style="margin-top:20px;display:none;">
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;">📋 Hasil Sync Terakhir</h3>
            <div id="syncDetailContent"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'fm-toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

function renderResult(el, data) {
    const cls = data.success ? 'fm-alert-success' : 'fm-alert-error';
    const icon = data.success ? '✅' : '❌';
    el.innerHTML = `<div class="fm-alert ${cls}">${icon} Status: <strong>${data.status}</strong> | Synced: ${data.synced} | Failed: ${data.failed} | Durasi: ${data.duration_ms}ms</div>`;

    document.getElementById('syncDetail').style.display = 'block';
    document.getElementById('syncDetailContent').innerHTML = `
        <div class="fm-cards" style="margin-bottom:0;">
            <div class="fm-card green"><div class="fm-card-value">${data.synced}</div><div class="fm-card-label">Records Synced</div></div>
            <div class="fm-card red"><div class="fm-card-value">${data.failed}</div><div class="fm-card-label">Records Failed</div></div>
            <div class="fm-card"><div class="fm-card-value">${data.duration_ms}ms</div><div class="fm-card-label">Durasi</div></div>
        </div>`;
}

document.getElementById('btnSyncToday').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '⏳ Syncing...';

    try {
        const res = await fetch('{{ route("admin.finance.sync.today") }}', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        renderResult(document.getElementById('resultToday'), data);
        showToast(data.success ? 'Sync berhasil!' : 'Sync gagal', data.success ? 'success' : 'error');
    } catch (e) {
        document.getElementById('resultToday').innerHTML = `<div class="fm-alert fm-alert-error">❌ ${e.message}</div>`;
    }

    btn.disabled = false;
    btn.textContent = '🔄 Sync Hari Ini';
});

document.getElementById('manualSyncForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = '⏳ Syncing...';

    try {
        const res = await fetch('{{ route("admin.finance.sync.run") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(Object.fromEntries(fd))
        });
        const data = await res.json();
        renderResult(document.getElementById('resultManual'), data);
        showToast(data.success ? 'Sync berhasil!' : 'Sync gagal', data.success ? 'success' : 'error');
    } catch (e) {
        document.getElementById('resultManual').innerHTML = `<div class="fm-alert fm-alert-error">❌ ${e.message}</div>`;
    }

    btn.disabled = false;
    btn.textContent = '🔄 Mulai Sync';
});
</script>
@endpush
