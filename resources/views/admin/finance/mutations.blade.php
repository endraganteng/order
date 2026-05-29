@extends('admin.layout')

@section('title', 'Mutasi Kas')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<style>
.fm-summary-row { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
.fm-summary-item { background:white; border:1px solid #e2e8f0; border-radius:8px; padding:10px 16px; font-size:13px; }
.fm-summary-item strong { display:block; font-size:16px; margin-top:2px; }
</style>
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📒 Mutasi Kas</h1>
            <p class="fm-page-subtitle">Riwayat pergerakan saldo semua akun kas</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Akun</label>
            <select class="fm-select" id="fAccount" style="width:180px;">
                <option value="">Semua Akun</option>
                @foreach($accounts as $a)
                <option value="{{ $a['id'] }}">{{ $a['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Periode</label>
            <input type="text" class="fm-input" id="fDateRange" style="width:240px;cursor:pointer;" readonly>
            <input type="hidden" id="fFrom">
            <input type="hidden" id="fTo">
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Tipe</label>
            <select class="fm-select" id="fType" style="width:140px;">
                <option value="">Semua</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
                <option value="transfer_in">Transfer Masuk</option>
                <option value="transfer_out">Transfer Keluar</option>
            </select>
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Cari</label>
            <input type="text" class="fm-input" id="fSearch" placeholder="Deskripsi..." style="width:160px;">
        </div>
        <div class="fm-form-group" style="align-self:flex-end;">
            <button class="fm-btn fm-btn-primary" onclick="loadMutations()">🔍 Filter</button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="fm-summary-row" id="summaryRow">
        <div class="fm-summary-item">Total Masuk: <strong class="fm-money income" id="sumIncome">-</strong></div>
        <div class="fm-summary-item">Total Keluar: <strong class="fm-money expense" id="sumExpense">-</strong></div>
        <div class="fm-summary-item">Net: <strong class="fm-money" id="sumNet">-</strong></div>
        <div class="fm-summary-item">Transaksi: <strong id="sumCount">-</strong></div>
    </div>

    {{-- Table --}}
    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Tanggal</th><th>Akun</th><th>Tipe</th><th>Deskripsi</th><th>Kategori</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Saldo Setelah</th></tr></thead>
            <tbody id="mutationBody">
                <tr><td colspan="7" class="fm-loading"><div class="fm-spinner"></div> Memuat data...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="fm-pagination" id="paginationRow">
        <span id="paginationInfo"></span>
        <div class="fm-pagination-btns" id="paginationBtns"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
let currentPage = 0;
const perPage = 30;

// Init daterangepicker
$(function() {
    $('#fDateRange').daterangepicker({
        startDate: moment().startOf('month'),
        endDate: moment(),
        ranges: {
            'Hari Ini': [moment(), moment()],
            'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
            '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
            'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
            'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        },
        locale: {
            format: 'DD/MM/YYYY',
            applyLabel: 'Terapkan',
            cancelLabel: 'Batal',
            customRangeLabel: 'Pilih Tanggal',
        },
        alwaysShowCalendars: true,
    }, function(start, end) {
        $('#fFrom').val(start.format('YYYY-MM-DD'));
        $('#fTo').val(end.format('YYYY-MM-DD'));
        loadMutations();
    });

    // Set initial values
    var drp = $('#fDateRange').data('daterangepicker');
    $('#fFrom').val(drp.startDate.format('YYYY-MM-DD'));
    $('#fTo').val(drp.endDate.format('YYYY-MM-DD'));

    loadMutations();
});

function formatRp(n) { return 'Rp ' + Math.abs(n).toLocaleString('id'); }

function loadMutations(offset = 0) {
    currentPage = offset;
    const params = new URLSearchParams();
    const acc = document.getElementById('fAccount').value;
    const from = document.getElementById('fFrom').value;
    const to = document.getElementById('fTo').value;
    const type = document.getElementById('fType').value;
    const search = document.getElementById('fSearch').value;

    if (acc) params.set('account_id', acc);
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    params.set('limit', perPage);
    params.set('offset', offset);

    const tbody = document.getElementById('mutationBody');
    tbody.innerHTML = '<tr><td colspan="7" class="fm-loading"><div class="fm-spinner"></div> Memuat...</td></tr>';

    fetch('{{ route("admin.finance.mutations") }}?' + params.toString(), {
        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(result => {
        let rows = result.data || [];
        const total = result.total || 0;

        // Client-side filter: type & search
        if (type) rows = rows.filter(m => m.type === type);
        if (search) {
            const q = search.toLowerCase();
            rows = rows.filter(m => (m.description || '').toLowerCase().includes(q) || (m.category_name || '').toLowerCase().includes(q) || (m.account_name || '').toLowerCase().includes(q));
        }

        // Summary — dari server (seluruh periode, bukan hanya halaman ini)
        const sumIn = parseInt(result.sum_income) || 0;
        const sumOut = parseInt(result.sum_expense) || 0;
        document.getElementById('sumIncome').textContent = '+' + formatRp(sumIn);
        document.getElementById('sumExpense').textContent = '-' + formatRp(sumOut);
        const net = sumIn - sumOut;
        const netEl = document.getElementById('sumNet');
        netEl.textContent = (net >= 0 ? '+' : '-') + formatRp(net);
        netEl.className = 'fm-money ' + (net >= 0 ? 'income' : 'expense');
        document.getElementById('sumCount').textContent = total;

        // Render rows
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">Tidak ada data mutasi.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(m => {
                const isIn = m.type === 'income' || m.type === 'transfer_in';
                return `<tr>
                    <td>${m.transaction_date} <small style="color:#94a3b8">${m.transaction_time || ''}</small></td>
                    <td>${m.account_name}</td>
                    <td><span class="fm-badge fm-badge-${m.type}">${m.type}</span></td>
                    <td>${m.description}</td>
                    <td>${m.category_name || '—'}</td>
                    <td style="text-align:right" class="fm-money ${isIn ? 'income' : 'expense'}">${isIn ? '+' : '-'}${formatRp(m.amount)}</td>
                    <td style="text-align:right" class="fm-money">${formatRp(m.balance_after)}</td>
                </tr>`;
            }).join('');
        }

        // Pagination
        document.getElementById('paginationInfo').textContent = `Menampilkan ${offset + 1}-${Math.min(offset + perPage, total)} dari ${total}`;
        const totalPages = Math.ceil(total / perPage);
        let btns = '';
        if (offset > 0) btns += `<button onclick="loadMutations(${offset - perPage})">← Prev</button>`;
        if (offset + perPage < total) btns += `<button onclick="loadMutations(${offset + perPage})">Next →</button>`;
        document.getElementById('paginationBtns').innerHTML = btns;
    });
}

// Live search on typing (debounce)
let searchTimer;
document.getElementById('fSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadMutations, 400);
});

// Filter on select change
document.getElementById('fAccount').addEventListener('change', loadMutations);
document.getElementById('fType').addEventListener('change', loadMutations);
</script>
@endpush
