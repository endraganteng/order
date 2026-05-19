@extends('admin.layout')

@section('title', 'Tutup Buku Bulanan')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📕 Tutup Buku Bulanan</h1>
            <p class="fm-page-subtitle">Kunci data keuangan bulan tertentu dan generate snapshot laporan final.</p>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Close Month Form --}}
    <div class="fm-filter" style="align-items:flex-end;">
        <div class="fm-form-group">
            <label class="fm-label">Bulan</label>
            <input type="month" class="fm-input" id="closeMonth" value="{{ date('Y-m', strtotime('-1 month')) }}" style="width:180px;">
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Catatan (opsional)</label>
            <input type="text" class="fm-input" id="closeNotes" placeholder="Catatan tutup buku" style="width:250px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="doClose()">🔒 Tutup Buku</button>
    </div>

    {{-- History --}}
    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Bulan</th><th>Status</th><th>Ditutup Oleh</th><th>Tanggal Tutup</th><th>Laba Bersih</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody id="closingBody">
                @forelse($closings as $c)
                <tr>
                    <td><strong>{{ $c['month'] }}</strong></td>
                    <td>
                        @if($c['status'] === 'closed')
                            <span class="fm-badge fm-badge-active">🔒 Closed</span>
                        @elseif($c['status'] === 'reopened')
                            <span class="fm-badge fm-badge-draft">🔓 Reopened</span>
                        @else
                            <span class="fm-badge fm-badge-inactive">Open</span>
                        @endif
                    </td>
                    <td>{{ $c['closed_by'] ?? '-' }}</td>
                    <td>{{ $c['closed_at'] ? \Carbon\Carbon::parse($c['closed_at'])->format('d/m/Y H:i') : '-' }}</td>
                    <td class="fm-money {{ json_decode($c['snapshot'] ?? '{}', true)['laba_bersih'] ?? 0 >= 0 ? 'income' : 'expense' }}">
                        @php $snap = json_decode($c['snapshot'] ?? '{}', true); @endphp
                        Rp {{ number_format($snap['laba_bersih'] ?? 0, 0, ',', '.') }}
                    </td>
                    <td style="font-size:12px;color:#64748b;">{{ $c['notes'] ?? '' }}</td>
                    <td>
                        @if($c['status'] === 'closed')
                            <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="doReopen('{{ $c['month'] }}')">🔓 Reopen</button>
                        @endif
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="viewSnapshot('{{ $c['month'] }}', {{ json_encode($snap) }})">👁️ Detail</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:24px;color:#94a3b8;">Belum ada tutup buku.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Snapshot Modal --}}
    <div class="fm-modal-backdrop" id="snapModal">
        <div class="fm-modal" style="max-width:500px;">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="snapTitle">Snapshot</span>
                <button class="fm-modal-close" onclick="document.getElementById('snapModal').classList.remove('active')">&times;</button>
            </div>
            <div class="fm-modal-body" id="snapBody" style="font-size:13px;"></div>
        </div>
    </div>

    {{-- PIN Modal --}}
    <div class="fm-modal-backdrop" id="pinModal" style="z-index:2100;">
        <div class="fm-modal" style="max-width:320px;">
            <div class="fm-modal-header">
                <span class="fm-modal-title">🔐 PIN Supervisor</span>
                <button class="fm-modal-close" onclick="closePinModal()">&times;</button>
            </div>
            <div class="fm-modal-body" style="text-align:center;">
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;">Masukkan PIN supervisor untuk melanjutkan.</p>
                <input type="password" id="pinInput" maxlength="6" placeholder="••••••"
                    style="width:160px;text-align:center;font-size:24px;letter-spacing:8px;padding:12px;border:2px solid #e2e8f0;border-radius:8px;"
                    onkeydown="if(event.key==='Enter')submitPin()">
                <p id="pinError" style="display:none;color:#dc2626;font-size:12px;margin-top:8px;"></p>
            </div>
            <div class="fm-modal-footer" style="justify-content:center;">
                <button class="fm-btn fm-btn-outline" onclick="closePinModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="submitPin()">Konfirmasi</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
let pinCallback = null;

function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'fm-toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

function requestPin(cb) {
    pinCallback = cb;
    document.getElementById('pinInput').value = '';
    document.getElementById('pinError').style.display = 'none';
    document.getElementById('pinModal').classList.add('active');
    setTimeout(() => document.getElementById('pinInput').focus(), 100);
}
function closePinModal() { document.getElementById('pinModal').classList.remove('active'); pinCallback = null; }
function submitPin() {
    const pin = document.getElementById('pinInput').value.trim();
    if (pin.length < 4) { document.getElementById('pinError').style.display=''; document.getElementById('pinError').textContent='PIN minimal 4 digit'; return; }
    const cb = pinCallback;
    closePinModal();
    if (cb) cb(pin);
}

function doClose() {
    const month = document.getElementById('closeMonth').value;
    const notes = document.getElementById('closeNotes').value;
    if (!month) { showToast('Pilih bulan', 'error'); return; }

    requestPin(async function(pin) {
        try {
            const res = await fetch('{{ route("admin.finance.tutup_buku.close") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({month, notes, supervisor_pin: pin})
            });
            const data = await res.json();
            if (data.pin_required) { showToast('PIN supervisor salah', 'error'); return; }
            if (data.success) { showToast('Bulan ' + month + ' berhasil ditutup!'); setTimeout(() => location.reload(), 800); }
            else showToast(data.message || 'Gagal', 'error');
        } catch (e) { showToast(e.message, 'error'); }
    });
}

function doReopen(month) {
    requestPin(async function(pin) {
        try {
            const res = await fetch('{{ route("admin.finance.tutup_buku.reopen") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({month, supervisor_pin: pin})
            });
            const data = await res.json();
            if (data.pin_required) { showToast('PIN supervisor salah', 'error'); return; }
            if (data.success) { showToast('Bulan ' + month + ' dibuka kembali.'); setTimeout(() => location.reload(), 800); }
            else showToast(data.message || 'Gagal', 'error');
        } catch (e) { showToast(e.message, 'error'); }
    });
}

function viewSnapshot(month, snap) {
    document.getElementById('snapTitle').textContent = '📊 Snapshot ' + month;
    let html = '<table style="width:100%;border-collapse:collapse;">';
    html += '<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:6px 0;color:#64748b;">Pendapatan</td><td style="text-align:right;font-weight:600;color:#059669;">Rp ' + (snap.pendapatan?.total || 0).toLocaleString('id') + '</td></tr>';
    html += '<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:6px 0;color:#64748b;">Pengeluaran</td><td style="text-align:right;font-weight:600;color:#dc2626;">Rp ' + (snap.pengeluaran?.total || 0).toLocaleString('id') + '</td></tr>';
    html += '<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:6px 0;font-weight:700;">Laba Bersih</td><td style="text-align:right;font-weight:700;color:' + ((snap.laba_bersih||0) >= 0 ? '#059669' : '#dc2626') + ';">Rp ' + (snap.laba_bersih || 0).toLocaleString('id') + '</td></tr>';
    if (snap.pengeluaran?.by_category) {
        html += '<tr><td colspan="2" style="padding:10px 0 4px;font-weight:600;font-size:12px;color:#64748b;">PENGELUARAN PER KATEGORI</td></tr>';
        for (const [cat, amt] of Object.entries(snap.pengeluaran.by_category)) {
            html += '<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 0 4px 12px;font-size:12px;">' + cat + '</td><td style="text-align:right;font-size:12px;">Rp ' + parseInt(amt).toLocaleString('id') + '</td></tr>';
        }
    }
    if (snap.saldo_akun) {
        html += '<tr><td colspan="2" style="padding:10px 0 4px;font-weight:600;font-size:12px;color:#64748b;">SALDO AKUN</td></tr>';
        snap.saldo_akun.forEach(a => {
            html += '<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 0 4px 12px;font-size:12px;">' + a.name + '</td><td style="text-align:right;font-size:12px;">Rp ' + parseInt(a.balance).toLocaleString('id') + '</td></tr>';
        });
    }
    html += '</table>';
    document.getElementById('snapBody').innerHTML = html;
    document.getElementById('snapModal').classList.add('active');
}
</script>
@endpush
