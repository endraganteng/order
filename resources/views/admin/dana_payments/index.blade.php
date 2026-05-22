@extends('admin.layout')

@section('title', 'Riwayat Pembayaran DANA')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<style>
    .dp-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .dp-summary-card {
        background: var(--color-bg);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem 1.25rem;
        box-shadow: var(--shadow-sm);
    }
    .dp-summary-card.accent-green { border-left: 3px solid #4ade80; }
    .dp-summary-card.accent-blue  { border-left: 3px solid #60a5fa; }
    .dp-summary-card.accent-amber { border-left: 3px solid #fbbf24; }
    .dp-summary-card.accent-purple { border-left: 3px solid #a78bfa; }
    .dp-summary-label {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        margin-bottom: 0.4rem;
    }
    .dp-summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-text);
        font-variant-numeric: tabular-nums;
    }
    .dp-summary-sub {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
        margin-top: 0.25rem;
    }
    .dp-grid-2col {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    @media (max-width: 900px) {
        .dp-grid-2col { grid-template-columns: 1fr; }
    }
    .dp-amount-cell {
        font-weight: 700;
        color: #16a34a;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .dp-sender-cell {
        font-weight: 500;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dp-time-cell {
        font-size: 0.8rem;
        color: var(--color-text-secondary);
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .dp-source-badge {
        display: inline-block;
        padding: 2px 8px;
        background: rgba(74, 222, 128, 0.15);
        color: #16a34a;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .dp-detail-btn {
        background: transparent;
        border: 1px solid var(--color-border);
        color: var(--color-text-secondary);
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.15s;
    }
    .dp-detail-btn:hover {
        background: var(--color-bg-hover, rgba(0,0,0,0.04));
        color: var(--color-text);
    }
    .dp-top-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .dp-top-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0.8rem;
        background: var(--color-bg);
        border-radius: var(--radius-sm);
        border: 1px solid var(--color-border);
    }
    .dp-top-name {
        font-weight: 500;
        max-width: 60%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dp-top-amount {
        font-weight: 700;
        color: #16a34a;
        font-variant-numeric: tabular-nums;
    }
    .dp-top-count {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
    }
    .dp-modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 1rem;
    }
    .dp-modal-overlay.active { display: flex; }
    .dp-modal {
        background: var(--color-bg);
        border-radius: var(--radius-md);
        max-width: 700px;
        width: 100%;
        max-height: 85vh;
        overflow: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }
    .dp-modal-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .dp-modal-body {
        padding: 1.25rem;
    }
    .dp-modal-close {
        background: transparent;
        border: 0;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--color-text-secondary);
        line-height: 1;
        padding: 0 0.5rem;
    }
    .dp-detail-row {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 0.5rem;
        padding: 0.5rem 0;
        border-bottom: 1px dashed var(--color-border);
        font-size: 0.85rem;
    }
    .dp-detail-row:last-child { border-bottom: 0; }
    .dp-detail-label {
        color: var(--color-text-secondary);
        font-weight: 500;
    }
    .dp-detail-value {
        word-break: break-word;
    }
    .dp-detail-value pre {
        background: rgba(0,0,0,0.05);
        padding: 0.5rem;
        border-radius: 4px;
        max-height: 200px;
        overflow: auto;
        font-size: 0.7rem;
    }
</style>
@endpush

@section('content')
<div class="finance-module" style="padding: 1.5rem;">
    <div class="fm-page-header" style="margin-bottom: 1rem;">
        <div>
            <h1 class="fm-page-title">💰 Riwayat Pembayaran DANA</h1>
            <p class="fm-page-subtitle">Notifikasi pembayaran DANA Bisnis dari webhook PayHook.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dana_payments.export', request()->query()) }}" class="fm-btn fm-btn-secondary">
                📥 Export CSV
            </a>
            <button type="button" class="fm-btn" onclick="openDanaResetModal()" style="background: #ef4444; color: white; border-color: #dc2626;">
                🗑️ Reset Semua
            </button>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.dana_payments.index') }}" class="fm-filter" style="margin-bottom: 1rem;">
        <div class="fm-form-group">
            <label class="fm-label">Dari Tanggal</label>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="fm-input" style="width: 160px;">
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Sampai Tanggal</label>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="fm-input" style="width: 160px;">
        </div>
        <div class="fm-form-group" style="flex: 1; min-width: 200px;">
            <label class="fm-label">Cari</label>
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Nama pengirim, reference, atau teks notif..." class="fm-input">
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Per Halaman</label>
            <select name="per_page" class="fm-select" style="width: 100px;">
                @foreach([25, 50, 100, 200] as $opt)
                    <option value="{{ $opt }}" {{ $filters['per_page'] === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="fm-form-group" style="display: flex; align-items: flex-end; gap: 0.5rem;">
            <button type="submit" class="fm-btn fm-btn-primary">🔍 Filter</button>
            <a href="{{ route('admin.dana_payments.index') }}" class="fm-btn fm-btn-secondary">↺ Reset</a>
        </div>
    </form>

    {{-- Quick range presets --}}
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center;">
        <span style="font-size: 0.85rem; color: var(--color-text-secondary);">Cepat:</span>
        @php
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
            $weekStart = \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
            $monthStart = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
            $last7 = \Carbon\Carbon::now()->subDays(6)->format('Y-m-d');
            $last30 = \Carbon\Carbon::now()->subDays(29)->format('Y-m-d');
            $presets = [
                ['Hari Ini', $today, $today],
                ['Kemarin', $yesterday, $yesterday],
                ['7 Hari', $last7, $today],
                ['30 Hari', $last30, $today],
                ['Minggu Ini', $weekStart, $today],
                ['Bulan Ini', $monthStart, $today],
            ];
        @endphp
        @foreach($presets as $p)
            <a href="{{ route('admin.dana_payments.index', ['from' => $p[1], 'to' => $p[2], 'search' => $filters['search']]) }}"
               class="fm-btn fm-btn-secondary"
               style="padding: 4px 10px; font-size: 0.78rem; {{ $filters['from'] === $p[1] && $filters['to'] === $p[2] ? 'background: var(--color-primary, #3b82f6); color: white;' : '' }}">
                {{ $p[0] }}
            </a>
        @endforeach
    </div>

    {{-- Summary cards --}}
    <div class="dp-summary-grid">
        <div class="dp-summary-card accent-green">
            <div class="dp-summary-label">Total Diterima</div>
            <div class="dp-summary-value">Rp {{ number_format($summary['total_amount'], 0, ',', '.') }}</div>
            <div class="dp-summary-sub">{{ $summary['date_range'] }}</div>
        </div>
        <div class="dp-summary-card accent-blue">
            <div class="dp-summary-label">Jumlah Transaksi</div>
            <div class="dp-summary-value">{{ number_format($summary['total_count'], 0, ',', '.') }}</div>
            <div class="dp-summary-sub">notifikasi pembayaran</div>
        </div>
        <div class="dp-summary-card accent-amber">
            <div class="dp-summary-label">Rata-rata per Transaksi</div>
            <div class="dp-summary-value">Rp {{ number_format($summary['avg_amount'], 0, ',', '.') }}</div>
            <div class="dp-summary-sub">{{ $summary['total_count'] > 0 ? 'dari ' . $summary['total_count'] . ' tx' : 'tidak ada transaksi' }}</div>
        </div>
        <div class="dp-summary-card accent-purple">
            <div class="dp-summary-label">Pengirim Unik</div>
            <div class="dp-summary-value">{{ count($topSenders) }}<span style="font-size: 0.8rem; color: var(--color-text-secondary); font-weight: 400;"> top 5</span></div>
            <div class="dp-summary-sub">{{ count($topSenders) > 0 ? 'lihat detail di samping' : 'belum ada' }}</div>
        </div>
    </div>

    {{-- Main grid: table + top senders --}}
    <div class="dp-grid-2col">
        {{-- Table --}}
        <div>
            <div class="fm-table-wrap">
                <table class="fm-table">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Waktu</th>
                            <th>Pengirim</th>
                            <th style="text-align: right; width: 130px;">Nominal</th>
                            <th style="width: 80px;">Sumber</th>
                            <th style="width: 60px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                            <tr>
                                <td class="dp-time-cell">
                                    <div>{{ \Carbon\Carbon::parse($p->received_at)->format('d/m/Y') }}</div>
                                    <div style="opacity: 0.7;">{{ \Carbon\Carbon::parse($p->received_at)->format('H:i:s') }}</div>
                                </td>
                                <td class="dp-sender-cell" title="{{ $p->sender_name }}">
                                    {{ $p->sender_name ?: '(Tidak terdeteksi)' }}
                                </td>
                                <td class="dp-amount-cell" style="text-align: right;">
                                    Rp {{ number_format($p->amount, 0, ',', '.') }}
                                </td>
                                <td><span class="dp-source-badge">{{ $p->source }}</span></td>
                                <td>
                                    <button type="button" class="dp-detail-btn" onclick="showDanaDetail({{ $p->id }})">Detail</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--color-text-secondary);">
                                    Tidak ada pembayaran pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="fm-pagination" style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    Menampilkan {{ $payments->firstItem() ?? 0 }} - {{ $payments->lastItem() ?? 0 }} dari {{ $payments->total() }} transaksi
                </div>
                <div>
                    {{ $payments->links() }}
                </div>
            </div>
        </div>

        {{-- Top senders sidebar --}}
        <div>
            <div class="fm-table-wrap" style="padding: 1rem;">
                <h3 style="margin: 0 0 0.75rem 0; font-size: 1rem;">🏆 Top 5 Pengirim</h3>
                @if(count($topSenders) === 0)
                    <div class="fm-empty" style="padding: 1rem;">
                        <div class="fm-empty-text" style="font-size: 0.85rem;">Belum ada data.</div>
                    </div>
                @else
                    <div class="dp-top-list">
                        @foreach($topSenders as $sender)
                            <div class="dp-top-row">
                                <div style="flex: 1; min-width: 0;">
                                    <div class="dp-top-name" title="{{ $sender->sender_name }}">{{ $sender->sender_name }}</div>
                                    <div class="dp-top-count">{{ $sender->tx_count }} transaksi</div>
                                </div>
                                <div class="dp-top-amount">Rp {{ number_format($sender->tx_total, 0, ',', '.') }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Daily breakdown (jika range > 1 hari) --}}
            @if(count($dailyBreakdown) > 1)
                <div class="fm-table-wrap" style="padding: 1rem; margin-top: 1rem;">
                    <h3 style="margin: 0 0 0.75rem 0; font-size: 1rem;">📅 Breakdown Harian</h3>
                    <table class="fm-table" style="font-size: 0.82rem;">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th style="text-align: right;">Tx</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dailyBreakdown as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m') }}</td>
                                    <td style="text-align: right;">{{ $row->tx_count }}</td>
                                    <td style="text-align: right; font-weight: 600;">Rp {{ number_format($row->tx_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div id="dpModal" class="dp-modal-overlay" onclick="if(event.target===this)closeDanaDetail()">
    <div class="dp-modal">
        <div class="dp-modal-header">
            <h3 style="margin: 0; font-size: 1.1rem;">Detail Pembayaran #<span id="dpModalId">-</span></h3>
            <button type="button" class="dp-modal-close" onclick="closeDanaDetail()" aria-label="Tutup">×</button>
        </div>
        <div class="dp-modal-body" id="dpModalBody">
            <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary);">Memuat...</div>
        </div>
    </div>
</div>

{{-- Reset Confirmation Modal --}}
<div id="dpResetModal" class="dp-modal-overlay" onclick="if(event.target===this)closeDanaResetModal()">
    <div class="dp-modal" style="max-width: 520px;">
        <div class="dp-modal-header" style="background: rgba(239, 68, 68, 0.08);">
            <h3 style="margin: 0; font-size: 1.1rem; color: #dc2626;">⚠️ Reset Semua Data DANA</h3>
            <button type="button" class="dp-modal-close" onclick="closeDanaResetModal()" aria-label="Tutup">×</button>
        </div>
        <div class="dp-modal-body">
            <div style="background: rgba(239, 68, 68, 0.1); border-left: 3px solid #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
                <strong style="color: #dc2626;">Tindakan ini PERMANEN dan tidak bisa dibatalkan.</strong>
            </div>
            <p style="margin: 0 0 0.75rem 0;">Tombol ini akan:</p>
            <ul style="margin: 0 0 1rem 1.25rem; line-height: 1.7; font-size: 0.9rem;">
                <li>Menghapus <strong>semua</strong> riwayat pembayaran DANA dari database</li>
                <li>Menghapus node <code>/dana_payments</code> di Firebase Realtime DB</li>
                <li>Mereset auto-increment ID kembali ke 1</li>
            </ul>
            <p style="margin: 0 0 0.75rem 0; font-size: 0.9rem; color: var(--color-text-secondary);">
                Data testing dan transaksi yang sudah masuk akan hilang permanen. Pastikan kamu sudah export CSV
                kalau perlu backup. Aksi ini akan dicatat di log dengan ID admin yang melakukan.
            </p>
            <div style="margin: 1rem 0;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.85rem;">
                    Ketik <code style="background: rgba(239, 68, 68, 0.1); color: #dc2626; padding: 2px 6px; border-radius: 3px;">RESET DANA</code> untuk konfirmasi:
                </label>
                <input type="text" id="dpResetConfirmInput" class="fm-input" placeholder="RESET DANA" autocomplete="off"
                       oninput="dpResetCheckInput()" style="font-family: monospace; letter-spacing: 0.05em;">
            </div>
            <div id="dpResetMessage" style="margin: 0.5rem 0; min-height: 1.2rem; font-size: 0.85rem;"></div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" class="fm-btn fm-btn-secondary" onclick="closeDanaResetModal()">Batal</button>
                <button type="button" id="dpResetConfirmBtn" class="fm-btn" disabled
                        onclick="executeDanaReset()"
                        style="background: #ef4444; color: white; border-color: #dc2626; opacity: 0.5; cursor: not-allowed;">
                    🗑️ Reset Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const dpDetailEndpoint = @json(route('admin.dana_payments.show', ['id' => '__ID__']));
    const dpModal = document.getElementById('dpModal');
    const dpModalId = document.getElementById('dpModalId');
    const dpModalBody = document.getElementById('dpModalBody');

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function fmtRupiah(n) {
        return 'Rp ' + (Number(n) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    async function showDanaDetail(id) {
        dpModalId.textContent = id;
        dpModalBody.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--color-text-secondary);">Memuat...</div>';
        dpModal.classList.add('active');
        try {
            const res = await fetch(dpDetailEndpoint.replace('__ID__', id), {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Gagal');
            const p = data.payment;
            const rows = [
                ['Reference PayHook', p.payhook_reference],
                ['Nominal', '<span style="color:#16a34a;font-weight:700;">' + fmtRupiah(p.amount) + '</span>'],
                ['Pengirim', p.sender_name || '(Tidak terdeteksi)'],
                ['Sumber', p.source],
                ['Package', p.package_name],
                ['Notif Title', p.notification_title],
                ['Notif Text', p.notification_text],
                ['Notified at (HP)', p.notified_at],
                ['Received at (server)', p.received_at],
                ['Firebase Key', p.firebase_key || '-'],
            ];
            let html = '';
            rows.forEach(([label, value]) => {
                html += `<div class="dp-detail-row"><div class="dp-detail-label">${escapeHtml(label)}</div><div class="dp-detail-value">${value === null || value === '' ? '<em style="opacity:.6;">-</em>' : (typeof value === 'string' && value.startsWith('<') ? value : escapeHtml(value))}</div></div>`;
            });
            if (p.raw_payload) {
                html += `<div class="dp-detail-row"><div class="dp-detail-label">Raw Payload</div><div class="dp-detail-value"><pre>${escapeHtml(JSON.stringify(p.raw_payload, null, 2))}</pre></div></div>`;
            }
            dpModalBody.innerHTML = html;
        } catch (e) {
            dpModalBody.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">Gagal memuat detail: ' + escapeHtml(e.message) + '</div>';
        }
    }

    function closeDanaDetail() {
        dpModal.classList.remove('active');
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (dpModal.classList.contains('active')) closeDanaDetail();
            if (dpResetModal.classList.contains('active')) closeDanaResetModal();
        }
    });

    // ===== RESET MODAL =====
    const dpResetModal = document.getElementById('dpResetModal');
    const dpResetInput = document.getElementById('dpResetConfirmInput');
    const dpResetBtn = document.getElementById('dpResetConfirmBtn');
    const dpResetMsg = document.getElementById('dpResetMessage');
    const dpResetEndpoint = @json(route('admin.dana_payments.reset'));
    const dpListEndpoint = @json(route('admin.dana_payments.index'));

    function openDanaResetModal() {
        dpResetInput.value = '';
        dpResetMsg.textContent = '';
        dpResetMsg.style.color = '';
        dpResetCheckInput();
        dpResetModal.classList.add('active');
        setTimeout(() => dpResetInput.focus(), 100);
    }

    function closeDanaResetModal() {
        dpResetModal.classList.remove('active');
    }

    function dpResetCheckInput() {
        const ok = dpResetInput.value === 'RESET DANA';
        dpResetBtn.disabled = !ok;
        dpResetBtn.style.opacity = ok ? '1' : '0.5';
        dpResetBtn.style.cursor = ok ? 'pointer' : 'not-allowed';
    }

    async function executeDanaReset() {
        if (dpResetInput.value !== 'RESET DANA') return;

        dpResetBtn.disabled = true;
        dpResetBtn.textContent = '⏳ Menghapus...';
        dpResetMsg.style.color = 'var(--color-text-secondary)';
        dpResetMsg.textContent = 'Menghapus data...';

        try {
            const res = await fetch(dpResetEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ confirmation: 'RESET DANA' }),
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'HTTP ' + res.status);
            }

            dpResetMsg.style.color = '#16a34a';
            dpResetMsg.textContent = '✓ ' + data.message
                + (data.firebase_ok ? '' : ' (Catatan: Firebase reset gagal — ' + (data.firebase_error || 'unknown') + ')');

            setTimeout(() => {
                window.location.href = dpListEndpoint;
            }, 1500);
        } catch (e) {
            dpResetMsg.style.color = '#dc2626';
            dpResetMsg.textContent = '✗ Gagal: ' + e.message;
            dpResetBtn.disabled = false;
            dpResetBtn.textContent = '🗑️ Reset Sekarang';
            dpResetCheckInput();
        }
    }
</script>
@endsection
