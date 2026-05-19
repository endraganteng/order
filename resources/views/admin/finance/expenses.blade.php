@extends('admin.layout')

@section('title', 'Pengeluaran')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<script src="{{ asset('js/finance-rupiah.js') }}" defer></script>
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">💸 Catat Pengeluaran</h1>
            <p class="fm-page-subtitle">Catat pengeluaran dari akun kas dengan kategori</p>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:20px;">
        {{-- Form Pengeluaran --}}
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">📝 Catat Pengeluaran / Bayar Supplier</h3>
            <form id="expenseForm">
                <div class="fm-form-group">
                    <label class="fm-label">Kategori Pengeluaran</label>
                    <select class="fm-select" name="finance_category_id" id="expCatSelect" required style="font-weight:600;font-size:15px;padding:10px 12px;" onchange="toggleSupplierField(this)">
                        @foreach($categories as $c)
                        <option value="{{ $c['id'] }}" data-name="{{ strtolower($c['name']) }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">Keterangan</label>
                    <input type="text" class="fm-input" name="description" placeholder="Beli pakan 10 sak, Bayar gaji, dll" required>
                </div>
                <div class="fm-form-group" id="supplierGroup" style="display:none;">
                    <label class="fm-label">Nama Supplier</label>
                    <input type="text" class="fm-input" name="supplier_name" placeholder="Nama supplier">
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">Total Belanja (Rp)</label>
                    <input type="number" class="fm-input fm-rupiah" name="total_amount" id="totalAmount" required>
                </div>

                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:12px 0;">
                    <p style="font-size:13px;font-weight:700;margin-bottom:8px;">💳 Metode Pembayaran</p>
                    <div class="fm-form-group">
                        <label class="fm-label">Bayar Cash (Rp) — langsung keluar dari akun kas</label>
                        <input type="number" class="fm-input fm-rupiah" name="cash_amount" id="cashAmount" value="0">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Dari Akun Kas</label>
                        <select class="fm-select" name="cash_account_id">
                            @foreach($accounts as $a)
                            <option value="{{ $a['id'] }}">{{ $a['name'] }} (Rp {{ number_format($a['balance'],0,',','.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Tempo / Hutang (Rp) — masuk ke daftar hutang</label>
                        <input type="number" class="fm-input fm-rupiah" name="debt_amount" id="debtAmount" value="0" readonly style="background:#f1f5f9;">
                    </div>
                    <div class="fm-form-group" id="dueDateGroup" style="display:none;">
                        <label class="fm-label">Jatuh Tempo Hutang</label>
                        <input type="date" class="fm-input" name="due_date">
                    </div>
                </div>

                <div class="fm-form-group">
                    <label class="fm-label">Tanggal</label>
                    <input type="date" class="fm-input" name="transaction_date" value="{{ date('Y-m-d') }}" required>
                </div>
                <button type="submit" class="fm-btn fm-btn-primary" style="margin-top:8px;">💸 Simpan</button>
            </form>
        </div>

        {{-- Budget vs Realisasi --}}
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">📊 Budget vs Realisasi ({{ date('F Y') }})</h3>
            @if($budget['total_pendapatan'] > 0)
            <p style="font-size:13px;color:#64748b;margin-bottom:12px;">Pendapatan bulan ini: <strong class="fm-money income">Rp {{ number_format($budget['total_pendapatan'], 0, ',', '.') }}</strong></p>
            @foreach($budget['allocations'] as $alloc)
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span><strong>{{ $alloc['category_name'] }}</strong> ({{ $alloc['percentage'] }}%)</span>
                    <span class="fm-money {{ $alloc['sisa'] >= 0 ? 'income' : 'expense' }}">Sisa: Rp {{ number_format($alloc['sisa'], 0, ',', '.') }}</span>
                </div>
                <div class="fm-progress">
                    <div class="fm-progress-bar {{ $alloc['pct_used'] > 90 ? 'red' : ($alloc['pct_used'] > 70 ? 'amber' : 'green') }}" style="width:{{ min($alloc['pct_used'], 100) }}%"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-top:2px;">
                    <span>Terpakai: Rp {{ number_format($alloc['realisasi'], 0, ',', '.') }} ({{ $alloc['pct_used'] }}%)</span>
                    <span>Budget: Rp {{ number_format($alloc['budget'], 0, ',', '.') }}</span>
                </div>
            </div>
            @endforeach
            @else
            <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada data pendapatan. Sync dulu.</div></div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'fm-toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

function unformat(val) { return parseInt((val+'').replace(/\./g, '')) || 0; }

function isSupplierCategory(select) {
    const opt = select.options[select.selectedIndex];
    const name = (opt.dataset.name || '').toLowerCase();
    return name.includes('restok') || name.includes('modal barang') || name.includes('supplier') || name.includes('belanja');
}

function toggleSupplierField(select) {
    document.getElementById('supplierGroup').style.display = isSupplierCategory(select) ? '' : 'none';
}

// Init on load
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('expCatSelect');
    if (sel) toggleSupplierField(sel);
});

// Auto-calculate hutang = total - cash
function recalcDebt() {
    const total = unformat(document.getElementById('totalAmount').value);
    const cash = unformat(document.getElementById('cashAmount').value);
    const debt = Math.max(0, total - cash);
    const debtEl = document.getElementById('debtAmount');
    debtEl.value = debt > 0 ? debt.toLocaleString('id') : '0';
    document.getElementById('dueDateGroup').style.display = debt > 0 ? '' : 'none';
}

document.getElementById('totalAmount').addEventListener('input', recalcDebt);
document.getElementById('cashAmount').addEventListener('input', recalcDebt);

document.getElementById('expenseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const body = {};
    fd.forEach((v, k) => body[k] = v);

    body.total_amount = unformat(body.total_amount);
    body.cash_amount = unformat(body.cash_amount);
    body.debt_amount = Math.max(0, body.total_amount - body.cash_amount);

    if (body.total_amount < 1) { showToast('Total belanja harus diisi', 'error'); return; }

    try {
        const res = await fetch('{{ route("admin.finance.expenses.store") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            showToast('Pengeluaran dicatat!' + (body.debt_amount > 0 ? ' Hutang Rp ' + body.debt_amount.toLocaleString('id') + ' tercatat.' : ''));
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Gagal', 'error');
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
});
</script>
@endpush
