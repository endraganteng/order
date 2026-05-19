@extends('admin.layout')

@section('title', 'Dashboard Keuangan')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<script src="{{ asset('js/finance-rupiah.js') }}" defer></script>
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📊 Dashboard Keuangan</h1>
            <p class="fm-page-subtitle">Ringkasan keuangan dari data shift kasir</p>
        </div>
        <button class="fm-btn fm-btn-primary" id="btnSyncToday">🔄 Refresh Data Hari Ini</button>
        <a href="{{ route('admin.finance.mutations') }}" class="fm-btn fm-btn-outline">📒 Mutasi Kas</a>
    </div>

    {{-- Status Sync --}}
    <div class="fm-alert fm-alert-info" id="syncStatus">
        @if($lastSync)
            Sync terakhir: {{ \Carbon\Carbon::parse($lastSync['created_at'])->format('d M Y H:i') }}
            — <span class="fm-badge fm-badge-{{ $lastSync['status'] }}">{{ $lastSync['status'] }}</span>
        @else
            Belum pernah sync. Klik "Refresh Data Hari Ini" untuk memulai.
        @endif
    </div>

    {{-- Saldo Kas per Akun (Cards - paling atas) --}}
    @if(count($accounts) > 0)
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 style="font-size:15px;font-weight:700;">💰 Saldo Kas per Akun</h3>
        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="openAddAccount()">+ Tambah Akun</button>
    </div>
    <div class="fm-cards">
        @foreach($accounts as $acc)
        <div class="fm-card {{ $acc['balance'] > 0 ? 'green' : ($acc['balance'] < 0 ? 'red' : '') }}">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div class="fm-card-icon">🏦</div>
                <div style="position:relative;" class="fm-kebab-wrap">
                    <button class="fm-btn fm-btn-sm fm-btn-outline" style="font-size:14px;padding:2px 8px;line-height:1;" onclick="toggleKebab(this)">⋮</button>
                    <div class="fm-kebab-menu" style="display:none;position:absolute;right:0;top:100%;background:white;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:100;min-width:140px;padding:4px 0;">
                        <a href="#" onclick="openEditAccount({{ json_encode($acc) }});closeKebabs();return false;" style="display:block;padding:8px 12px;font-size:12px;color:#334155;text-decoration:none;">✏️ Edit Akun</a>
                        <a href="#" onclick="resetAccount({{ $acc['id'] }},'{{ $acc['name'] }}');closeKebabs();return false;" style="display:block;padding:8px 12px;font-size:12px;color:#dc2626;text-decoration:none;">🔄 Reset Saldo</a>
                        <a href="#" onclick="toggleAccount({{ $acc['id'] }});closeKebabs();return false;" style="display:block;padding:8px 12px;font-size:12px;color:#64748b;text-decoration:none;">{{ $acc['is_active'] ? '🚫 Nonaktifkan' : '✅ Aktifkan' }}</a>
                    </div>
                </div>
            </div>
            <div class="fm-card-value fm-money {{ $acc['balance'] >= 0 ? 'income' : 'expense' }}">Rp {{ number_format($acc['balance'], 0, ',', '.') }}</div>
            <div class="fm-card-label">{{ $acc['name'] }}</div>
            <div style="display:flex;gap:4px;margin-top:8px;">
                <button class="fm-btn fm-btn-sm fm-btn-outline" style="font-size:11px;flex:1;" onclick="openTransfer({{ $acc['id'] }}, '{{ $acc['name'] }}', {{ $acc['balance'] }})">↔️ Transfer</button>
                <button class="fm-btn fm-btn-sm fm-btn-danger" style="font-size:11px;flex:1;" onclick="openExpense({{ $acc['id'] }}, '{{ $acc['name'] }}')">💸 Bayar</button>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="fm-empty" style="margin-bottom:20px;">
        <div class="fm-empty-icon">🏦</div>
        <div class="fm-empty-text">Belum ada akun kas. <button class="fm-btn fm-btn-sm fm-btn-primary" onclick="openAddAccount()">+ Tambah Akun</button></div>
    </div>
    @endif

    {{-- Modal Tambah/Edit Akun Kas --}}
    <div class="fm-modal-backdrop" id="accountModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="accModalTitle">Tambah Akun Kas</span>
                <button class="fm-modal-close" onclick="closeAccountModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="accountForm">
                    <input type="hidden" name="acc_id" id="accId">
                    <div class="fm-form-group">
                        <label class="fm-label">Nama Akun</label>
                        <input type="text" class="fm-input" name="name" id="accName" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Kode</label>
                        <input type="text" class="fm-input" name="code" id="accCode" placeholder="kas_laci, brankas, dll" required>
                    </div>
                    <div class="fm-form-group" id="accBalanceGroup">
                        <label class="fm-label">Saldo Awal (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="balance" id="accBalance" value="0">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeAccountModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveAccount()">Simpan</button>
            </div>
        </div>
    </div>

    {{-- Modal Transfer Cepat --}}
    <div class="fm-modal-backdrop" id="transferModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">↔️ Transfer Kas</span>
                <button class="fm-modal-close" onclick="closeTransfer()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="quickTransferForm">
                    <div class="fm-form-group">
                        <label class="fm-label">Dari Akun</label>
                        <select class="fm-select" name="from_account_id" id="tfFrom" required>
                            @foreach($accounts as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }} (Rp {{ number_format($a['balance'],0,',','.') }})</option>@endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Ke Akun</label>
                        <select class="fm-select" name="to_account_id" id="tfTo" required>
                            @foreach($accounts as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="amount" id="tfAmount" min="1" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Catatan (opsional)</label>
                        <input type="text" class="fm-input" name="notes" placeholder="Keterangan transfer">
                    </div>
                    <input type="hidden" name="status" value="pending">
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeTransfer()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="submitTransfer()">↔️ Transfer</button>
            </div>
        </div>
    </div>

    {{-- Modal Pengeluaran Cepat --}}
    <div class="fm-modal-backdrop" id="expenseModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">💸 Catat Pengeluaran</span>
                <button class="fm-modal-close" onclick="closeExpense()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="quickExpenseForm">
                    <input type="hidden" name="cash_account_id" id="expAccId">
                    <div class="fm-alert fm-alert-info" id="expAccLabel" style="margin-bottom:12px;"></div>
                    <div class="fm-form-group">
                        <label class="fm-label">Kategori Pengeluaran</label>
                        <select class="fm-select" name="finance_category_id" id="expModalCat" required onchange="toggleExpSupplier(this)">
                            @foreach($categories as $c)
                            <option value="{{ $c['id'] }}" data-name="{{ strtolower($c['name']) }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Total Belanja (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="total_amount" id="expTotal" required oninput="calcExpDebt()">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Bayar Cash (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="cash_amount" id="expCash" oninput="calcExpDebt()">
                    </div>
                    <div id="expDebtInfo" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:10px;margin-bottom:12px;font-size:13px;">
                        ⏳ Hutang tempo: <strong id="expDebtAmount">Rp 0</strong>
                    </div>
                    <div class="fm-form-group" id="expDueDateGroup" style="display:none;">
                        <label class="fm-label">Jatuh Tempo Hutang</label>
                        <input type="date" class="fm-input" name="due_date">
                    </div>
                    <div class="fm-form-group" id="expSupplierGroup" style="display:none;">
                        <label class="fm-label">Nama Supplier</label>
                        <input type="text" class="fm-input" name="supplier_name" placeholder="Nama supplier">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Keterangan</label>
                        <input type="text" class="fm-input" name="description" placeholder="Beli pakan, bayar listrik, dll" required>
                    </div>
                    <input type="hidden" name="transaction_date" value="{{ date('Y-m-d') }}">
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeExpense()">Batal</button>
                <button class="fm-btn fm-btn-danger" onclick="submitExpense()">💸 Simpan</button>
            </div>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Pending Transfers - Approval --}}
    @if(count($pendingTransfers) > 0)
    <div class="fm-alert fm-alert-warning" style="margin-bottom:16px;">
        ⏳ <strong>{{ count($pendingTransfers) }} transfer menunggu approval</strong>
    </div>
    <div class="fm-table-wrap" style="margin-bottom:20px;">
        <table class="fm-table">
            <thead><tr><th>Dari</th><th>Ke</th><th style="text-align:right">Jumlah</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach($pendingTransfers as $t)
                <tr data-id="{{ $t['id'] }}">
                    <td>{{ $t['from_name'] }}</td>
                    <td>{{ $t['to_name'] }}</td>
                    <td style="text-align:right" class="fm-money">Rp {{ number_format($t['amount'], 0, ',', '.') }}</td>
                    <td style="font-size:12px;">{{ $t['notes'] ?? '—' }}</td>
                    <td style="white-space:nowrap;">
                        <button class="fm-btn fm-btn-sm fm-btn-success" onclick="approveTransfer({{ $t['id'] }})">✅ Approve</button>
                        <button class="fm-btn fm-btn-sm fm-btn-danger" onclick="rejectTransfer({{ $t['id'] }})">❌ Reject</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Summary Cards - Hari Ini --}}
    <h3 style="font-size:15px;font-weight:700;margin:20px 0 10px;">Hari Ini ({{ date('d M Y') }})</h3>
    <div class="fm-cards">
        <div class="fm-card green">
            <div class="fm-card-icon">💵</div>
            <div class="fm-card-value fm-money income" id="todayTunai">Rp {{ number_format($today['penjualan_tunai'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Penjualan Tunai</div>
        </div>
        <div class="fm-card purple">
            <div class="fm-card-icon">📱</div>
            <div class="fm-card-value fm-money income" id="todayQris">Rp {{ number_format($today['penjualan_qris'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Penjualan QRIS</div>
        </div>
        <div class="fm-card">
            <div class="fm-card-icon">📈</div>
            <div class="fm-card-value fm-money income" id="todayPendapatan">Rp {{ number_format($today['total_pendapatan'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pendapatan</div>
        </div>
        <div class="fm-card red">
            <div class="fm-card-icon">📉</div>
            <div class="fm-card-value fm-money expense" id="todayPengeluaran">Rp {{ number_format($today['total_pengeluaran'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pengeluaran</div>
        </div>
        <div class="fm-card green">
            <div class="fm-card-icon">✅</div>
            <div class="fm-card-value fm-money income" id="todayBersih">Rp {{ number_format($today['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Pendapatan Bersih</div>
        </div>
        <div class="fm-card amber">
            <div class="fm-card-icon">🕐</div>
            <div class="fm-card-value" id="todayShift">{{ $today['jumlah_shift'] ?? 0 }}</div>
            <div class="fm-card-label">Jumlah Shift</div>
        </div>
    </div>

    {{-- Summary Cards - Bulan Ini --}}
    <h3 style="font-size:15px;font-weight:700;margin:20px 0 10px;">Bulan Ini ({{ date('F Y') }})</h3>
    <div class="fm-cards">
        <div class="fm-card">
            <div class="fm-card-icon">📈</div>
            <div class="fm-card-value fm-money income">Rp {{ number_format($month['total_pendapatan'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pendapatan</div>
        </div>
        <div class="fm-card red">
            <div class="fm-card-icon">📉</div>
            <div class="fm-card-value fm-money expense">Rp {{ number_format($month['total_pengeluaran'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Pengeluaran</div>
        </div>
        <div class="fm-card green">
            <div class="fm-card-icon">✅</div>
            <div class="fm-card-value fm-money income">Rp {{ number_format($month['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Pendapatan Bersih</div>
        </div>
        <div class="fm-card amber">
            <div class="fm-card-icon">⚠️</div>
            <div class="fm-card-value fm-money {{ ($month['total_selisih'] ?? 0) < 0 ? 'expense' : 'income' }}">Rp {{ number_format($month['total_selisih'] ?? 0, 0, ',', '.') }}</div>
            <div class="fm-card-label">Selisih Kas</div>
        </div>
        <div class="fm-card teal">
            <div class="fm-card-icon">📅</div>
            <div class="fm-card-value">{{ $month['days_synced'] ?? 0 }}</div>
            <div class="fm-card-label">Hari Tersync</div>
        </div>
        <div class="fm-card">
            <div class="fm-card-icon">🕐</div>
            <div class="fm-card-value">{{ $month['jumlah_shift'] ?? 0 }}</div>
            <div class="fm-card-label">Total Shift</div>
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

// Transfer
function openTransfer(fromId, fromName, balance) {
    document.getElementById('tfFrom').value = fromId;
    document.getElementById('transferModal').classList.add('active');
}

function closeTransfer() {
    document.getElementById('transferModal').classList.remove('active');
    document.getElementById('quickTransferForm').reset();
}

async function submitTransfer() {
    const fd = new FormData(document.getElementById('quickTransferForm'));
    const body = Object.fromEntries(fd);

    if (body.from_account_id === body.to_account_id) {
        showToast('Akun sumber dan tujuan tidak boleh sama', 'error');
        return;
    }

    try {
        const res = await fetch('{{ route("admin.finance.transfers.store") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            showToast('Transfer dibuat! Status: pending (perlu approval)');
            closeTransfer();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Gagal', 'error');
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function approveTransfer(id) {
    if (!confirm('Approve transfer ini? Saldo akan langsung berubah.')) return;
    try {
        const res = await fetch('{{ url("admin/finance/transfers") }}/' + id + '/approve', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) { showToast('Transfer approved!'); setTimeout(() => location.reload(), 800); }
        else showToast(data.message || 'Gagal (saldo tidak cukup?)', 'error');
    } catch (e) { showToast(e.message, 'error'); }
}

async function rejectTransfer(id) {
    if (!confirm('Reject transfer ini?')) return;
    try {
        const res = await fetch('{{ url("admin/finance/transfers") }}/' + id + '/reject', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) { showToast('Transfer rejected.'); document.querySelector(`tr[data-id="${id}"]`).remove(); }
    } catch (e) { showToast(e.message, 'error'); }
}

// Kebab Menu
function toggleKebab(btn) {
    closeKebabs();
    const menu = btn.nextElementSibling;
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

function closeKebabs() {
    document.querySelectorAll('.fm-kebab-menu').forEach(m => m.style.display = 'none');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.fm-kebab-wrap')) closeKebabs();
});

async function resetAccount(id, name) {
    if (!confirm('Reset saldo "' + name + '" ke Rp 0 dan hapus semua mutasi?\n\nAksi ini tidak bisa dibatalkan!')) return;
    try {
        const res = await fetch('{{ url("admin/finance/cash-accounts") }}/' + id + '/reset', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) { showToast('Saldo direset!'); setTimeout(() => location.reload(), 600); }
    } catch (e) { showToast(e.message, 'error'); }
}

async function toggleAccount(id) {
    try {
        const res = await fetch('{{ url("admin/finance/cash-accounts") }}/' + id + '/toggle', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) { showToast('Status diubah!'); setTimeout(() => location.reload(), 600); }
    } catch (e) { showToast(e.message, 'error'); }
}

// Account CRUD
function openAddAccount() {
    document.getElementById('accModalTitle').textContent = 'Tambah Akun Kas';
    document.getElementById('accId').value = '';
    document.getElementById('accBalanceGroup').style.display = '';
    document.getElementById('accountForm').reset();
    document.getElementById('accountModal').classList.add('active');
}

function openEditAccount(acc) {
    document.getElementById('accModalTitle').textContent = 'Edit Akun Kas';
    document.getElementById('accId').value = acc.id;
    document.getElementById('accName').value = acc.name;
    document.getElementById('accCode').value = acc.code;
    document.getElementById('accBalanceGroup').style.display = 'none';
    document.getElementById('accountModal').classList.add('active');
}

function closeAccountModal() {
    document.getElementById('accountModal').classList.remove('active');
}

async function saveAccount() {
    const fd = new FormData(document.getElementById('accountForm'));
    const body = {};
    fd.forEach((v, k) => body[k] = v);
    const id = body.acc_id; delete body.acc_id;
    if (body.balance) body.balance = parseInt((body.balance+'').replace(/\./g,'')) || 0;

    const url = id ? '{{ url("admin/finance/cash-accounts") }}/' + id : '{{ route("admin.finance.cash_accounts.store") }}';
    try {
        const res = await fetch(url, {
            method: id ? 'PUT' : 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { showToast(id ? 'Akun diupdate!' : 'Akun ditambahkan!'); closeAccountModal(); setTimeout(() => location.reload(), 600); }
        else showToast(data.message || 'Gagal', 'error');
    } catch (e) { showToast(e.message, 'error'); }
}

// Expense
function toggleExpSupplier(select) {
    const opt = select.options[select.selectedIndex];
    const name = (opt.dataset.name || '').toLowerCase();
    const show = name.includes('restok') || name.includes('modal barang') || name.includes('supplier') || name.includes('belanja');
    document.getElementById('expSupplierGroup').style.display = show ? '' : 'none';
}

function calcExpDebt() {
    const total = parseInt((document.getElementById('expTotal').value+'').replace(/\./g,'')) || 0;
    const cash = parseInt((document.getElementById('expCash').value+'').replace(/\./g,'')) || 0;
    const debt = Math.max(0, total - cash);
    const show = debt > 0 && total > 0;
    document.getElementById('expDebtInfo').style.display = show ? '' : 'none';
    document.getElementById('expDueDateGroup').style.display = show ? '' : 'none';
    document.getElementById('expDebtAmount').textContent = 'Rp ' + debt.toLocaleString('id');
}

function openExpense(accId, accName) {
    document.getElementById('expAccId').value = accId;
    document.getElementById('expAccLabel').innerHTML = '💰 Dari: <strong>' + accName + '</strong>';
    document.getElementById('expenseModal').classList.add('active');
}

function closeExpense() {
    document.getElementById('expenseModal').classList.remove('active');
    document.getElementById('quickExpenseForm').reset();
}

async function submitExpense() {
    const fd = new FormData(document.getElementById('quickExpenseForm'));
    const body = {};
    fd.forEach((v, k) => body[k] = v);
    body.total_amount = parseInt((body.total_amount+'').replace(/\./g,'')) || 0;
    body.cash_amount = parseInt((body.cash_amount+'').replace(/\./g,'')) || body.total_amount;
    if (!body.cash_amount) body.cash_amount = body.total_amount;

    if (body.total_amount < 1) { showToast('Total belanja harus diisi', 'error'); return; }

    try {
        const res = await fetch('{{ route("admin.finance.expenses.store") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            const debt = body.total_amount - body.cash_amount;
            showToast('Pengeluaran dicatat!' + (debt > 0 ? ' Hutang Rp ' + debt.toLocaleString('id') : ''));
            closeExpense();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Gagal', 'error');
        }
    } catch (e) { showToast(e.message, 'error'); }
}

// Sync Today
document.getElementById('btnSyncToday').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '⏳ Syncing...';

    try {
        const res = await fetch('{{ route("admin.finance.sync.today") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('syncStatus').className = 'fm-alert fm-alert-success';
            document.getElementById('syncStatus').innerHTML = '✅ Sync berhasil! ' + data.synced + ' record tersinkronisasi. <a href="" style="margin-left:8px" onclick="location.reload()">Refresh halaman</a>';
        } else {
            document.getElementById('syncStatus').className = 'fm-alert fm-alert-error';
            document.getElementById('syncStatus').textContent = '❌ Sync gagal: ' + (data.message || data.status);
        }
    } catch (e) {
        document.getElementById('syncStatus').className = 'fm-alert fm-alert-error';
        document.getElementById('syncStatus').textContent = '❌ Error: ' + e.message;
    }

    btn.disabled = false;
    btn.textContent = '🔄 Refresh Data Hari Ini';
});
</script>
@endpush
