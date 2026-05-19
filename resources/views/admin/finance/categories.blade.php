@extends('admin.layout')

@section('title', 'Kategori Keuangan')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📂 Kategori Keuangan</h1>
            <p class="fm-page-subtitle">Kelola kategori pemasukan dan pengeluaran</p>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="openModal()">+ Tambah Kategori</button>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Filter --}}
    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Tipe</label>
            <select class="fm-select" id="filterType" onchange="filterTable()" style="width:150px;">
                <option value="">Semua</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
            </select>
        </div>
        <div class="fm-form-group">
            <label class="fm-label">Status</label>
            <select class="fm-select" id="filterStatus" onchange="filterTable()" style="width:150px;">
                <option value="">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Nama</th><th>Tipe</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody id="tableBody">
                @foreach($categories as $cat)
                <tr data-id="{{ $cat['id'] }}" data-type="{{ $cat['type'] }}" data-active="{{ $cat['is_active'] ? '1' : '0' }}">
                    <td><strong>{{ $cat['name'] }}</strong></td>
                    <td><span class="fm-badge fm-badge-{{ $cat['type'] }}">{{ $cat['type'] }}</span></td>
                    <td><span class="fm-badge fm-badge-{{ $cat['is_active'] ? 'active' : 'inactive' }}">{{ $cat['is_active'] ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="editCat({{ json_encode($cat) }})">✏️</button>
                        <button class="fm-btn fm-btn-sm fm-btn-warning" onclick="toggleCat({{ $cat['id'] }})">{{ $cat['is_active'] ? '🚫' : '✅' }}</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($categories) === 0)
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada kategori.</div></div>
    @endif

    {{-- Modal --}}
    <div class="fm-modal-backdrop" id="modal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="modalTitle">Tambah Kategori</span>
                <button class="fm-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="catForm">
                    <input type="hidden" name="id" id="fId">
                    <div class="fm-form-group">
                        <label class="fm-label">Nama Kategori</label>
                        <input type="text" class="fm-input" name="name" id="fName" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Tipe</label>
                        <select class="fm-select" name="type" id="fType" required>
                            <option value="expense">Expense (Pengeluaran)</option>
                            <option value="income">Income (Pemasukan)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveCat()">Simpan</button>
            </div>
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

function openModal(title = 'Tambah Kategori') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modal').classList.add('active');
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
    document.getElementById('catForm').reset();
    document.getElementById('fId').value = '';
}

function editCat(c) {
    document.getElementById('fId').value = c.id;
    document.getElementById('fName').value = c.name;
    document.getElementById('fType').value = c.type;
    openModal('Edit Kategori');
}

async function saveCat() {
    const fd = new FormData(document.getElementById('catForm'));
    const body = Object.fromEntries(fd);
    const id = body.id; delete body.id;
    const url = id ? '{{ url("admin/finance/categories") }}/' + id : '{{ route("admin.finance.categories.store") }}';

    try {
        const res = await fetch(url, {
            method: id ? 'PUT' : 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { showToast('Berhasil!'); closeModal(); location.reload(); }
        else showToast(data.message || 'Gagal', 'error');
    } catch (e) { showToast(e.message, 'error'); }
}

async function toggleCat(id) {
    try {
        const res = await fetch('{{ url("admin/finance/categories") }}/' + id + '/toggle', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) { showToast('Status diubah!'); location.reload(); }
    } catch (e) { showToast(e.message, 'error'); }
}

function filterTable() {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    document.querySelectorAll('#tableBody tr').forEach(tr => {
        const matchType = !type || tr.dataset.type === type;
        const matchStatus = !status || tr.dataset.active === status;
        tr.style.display = (matchType && matchStatus) ? '' : 'none';
    });
}
</script>
@endpush
