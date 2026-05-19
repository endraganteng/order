@extends('admin.layout')

@section('title', 'Alokasi Dana')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📊 Alokasi Dana</h1>
            <p class="fm-page-subtitle">Pembagian persentase pendapatan ke kategori pengeluaran</p>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="openModal()">+ Tambah Alokasi</button>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Simulasi Live --}}
    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Simulasi Alokasi</label>
            <input type="number" class="fm-input" id="simTotal" placeholder="Total pendapatan (Rp)" style="width:220px;" oninput="liveSimulate()">
        </div>
        <div id="simResult" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;"></div>
    </div>

    {{-- Total Indicator --}}
    <div id="totalBar" class="fm-alert" style="margin-bottom:12px;"></div>

    {{-- Table --}}
    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Kategori</th><th>Persentase</th><th>Berlaku Dari</th><th>Sampai</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody id="allocBody">
                <tr><td colspan="7" class="fm-loading">Memuat...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div class="fm-modal-backdrop" id="modal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="modalTitle">Tambah Alokasi</span>
                <button class="fm-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="allocForm">
                    <input type="hidden" name="id" id="fId">
                    <div class="fm-form-group">
                        <label class="fm-label">Kategori (Expense)</label>
                        <select class="fm-select" name="finance_category_id" id="fCatId" required>
                            <option value="">-- Pilih --</option>
                            @foreach($categories as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Persentase (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="fm-input" name="percentage" id="fPct" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Berlaku Dari</label>
                        <input type="date" class="fm-input" name="effective_date" id="fFrom" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Sampai (opsional)</label>
                        <input type="date" class="fm-input" name="end_date" id="fTo">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Catatan</label>
                        <input type="text" class="fm-input" name="notes" id="fNotes">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveAlloc()">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
let allocations = [];
const categories = @json($categories);

function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'fm-toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

function catName(id) {
    const c = categories.find(x => x.id == id);
    return c ? c.name : '?';
}

function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return dt.toLocaleDateString('id', {day:'2-digit', month:'2-digit', year:'numeric'});
}

// Load data
async function loadAllocations() {
    try {
        const res = await fetch('{{ route("admin.finance.allocations") }}', {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await res.json();
        allocations = data.allocations || data;
        render();
    } catch (e) { showToast(e.message, 'error'); }
}

function render() {
    const tbody = document.getElementById('allocBody');
    if (allocations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#94a3b8;">Belum ada alokasi.</td></tr>';
    } else {
        tbody.innerHTML = allocations.map(a => `<tr data-id="${a.id}">
            <td><strong>${a.category_name}</strong></td>
            <td><span class="fm-money">${parseFloat(a.percentage).toFixed(2)}%</span></td>
            <td>${formatDate(a.effective_date)}</td>
            <td>${formatDate(a.end_date)}</td>
            <td><span class="fm-badge fm-badge-${a.is_active ? 'active' : 'inactive'}">${a.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
            <td style="font-size:12px;color:#64748b;">${a.notes || ''}</td>
            <td>
                <button class="fm-btn fm-btn-sm fm-btn-outline" onclick='editAlloc(${JSON.stringify(a)})'>✏️</button>
                <button class="fm-btn fm-btn-sm fm-btn-danger" onclick="deleteAlloc(${a.id})">🗑️</button>
            </td>
        </tr>`).join('');
    }
    updateTotal();
    liveSimulate();
}

function updateTotal() {
    const total = allocations.filter(a => a.is_active).reduce((s, a) => s + parseFloat(a.percentage), 0);
    const bar = document.getElementById('totalBar');
    const ok = Math.abs(total - 100) < 0.01;
    bar.className = 'fm-alert ' + (ok ? 'fm-alert-success' : 'fm-alert-warning');
    bar.innerHTML = `Total alokasi aktif: <strong>${total.toFixed(2)}%</strong> ${ok ? '✅' : '⚠️ (harus 100%)'}`;
}

function liveSimulate() {
    const raw = parseInt((document.getElementById('simTotal').value + '').replace(/\./g, '')) || 0;
    const el = document.getElementById('simResult');
    if (raw <= 0) { el.innerHTML = ''; return; }
    const active = allocations.filter(a => a.is_active);
    el.innerHTML = active.map(a => {
        const amt = Math.round(raw * parseFloat(a.percentage) / 100);
        return `<span class="fm-badge fm-badge-draft">${a.category_name}: <strong>Rp ${amt.toLocaleString('id')}</strong> (${parseFloat(a.percentage).toFixed(1)}%)</span>`;
    }).join('');
}

// Modal
function openModal(t = 'Tambah Alokasi') {
    document.getElementById('modalTitle').textContent = t;
    document.getElementById('modal').classList.add('active');
}
function closeModal() {
    document.getElementById('modal').classList.remove('active');
    document.getElementById('allocForm').reset();
    document.getElementById('fId').value = '';
}

function editAlloc(a) {
    document.getElementById('fId').value = a.id;
    document.getElementById('fCatId').value = a.finance_category_id;
    document.getElementById('fPct').value = a.percentage;
    document.getElementById('fFrom').value = (a.effective_date || '').split('T')[0];
    document.getElementById('fTo').value = (a.end_date || '').split('T')[0] || '';
    document.getElementById('fNotes').value = a.notes || '';
    openModal('Edit Alokasi');
}

async function saveAlloc() {
    const fd = new FormData(document.getElementById('allocForm'));
    const body = Object.fromEntries(fd);
    const id = body.id; delete body.id;
    if (!body.end_date) body.end_date = null;
    const url = id ? '{{ url("admin/finance/allocations") }}/' + id : '{{ route("admin.finance.allocations.store") }}';

    try {
        const res = await fetch(url, {
            method: id ? 'PUT' : 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            showToast(id ? 'Alokasi diupdate!' : 'Alokasi ditambahkan!');
            closeModal();
            loadAllocations();
        } else {
            showToast(data.message || 'Gagal', 'error');
        }
    } catch (e) { showToast(e.message, 'error'); }
}

async function deleteAlloc(id) {
    if (!confirm('Hapus alokasi ini?')) return;
    try {
        const res = await fetch('{{ url("admin/finance/allocations") }}/' + id, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) {
            showToast('Dihapus!');
            allocations = allocations.filter(a => a.id !== id);
            render();
        }
    } catch (e) { showToast(e.message, 'error'); }
}

// Init
loadAllocations();
</script>
@endpush
