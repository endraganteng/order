@extends('admin.layout')

@section('title', 'Mapping Kategori API')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">🏷️ Mapping Kategori API</h1>
            <p class="fm-page-subtitle">Mapping line_type dari API ke kategori keuangan internal</p>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="openModal()">+ Tambah Mapping</button>
    </div>

    <div id="toast" class="fm-toast"></div>

    <div class="fm-alert fm-alert-info">
        💡 Data pengeluaran dari API memiliki <code>line_type</code> (product, kasbon, custom). Mapping ini menentukan kategori keuangan mana yang digunakan. Jika tidak ada mapping, data masuk status <strong>need_review</strong>.
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table" id="mappingTable">
            <thead><tr><th>API Key</th><th>API Value</th><th>→ Kategori Internal</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody id="tableBody">
                @foreach($mappings as $m)
                <tr data-id="{{ $m['id'] }}">
                    <td><code>{{ $m['api_key'] }}</code></td>
                    <td><strong>{{ $m['api_value'] }}</strong></td>
                    <td>{{ $m['target_name'] ?? '⚠️ Tidak ditemukan' }}</td>
                    <td><span class="fm-badge fm-badge-{{ $m['is_active'] ? 'active' : 'inactive' }}">{{ $m['is_active'] ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="editMapping({{ json_encode($m) }})">✏️</button>
                        <button class="fm-btn fm-btn-sm fm-btn-danger" onclick="deleteMapping({{ $m['id'] }})">🗑️</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($mappings) === 0)
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada mapping. Tambahkan mapping default.</div></div>
    @endif

    {{-- Modal --}}
    <div class="fm-modal-backdrop" id="modal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="modalTitle">Tambah Mapping</span>
                <button class="fm-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="mappingForm">
                    <input type="hidden" name="id" id="fId">
                    <input type="hidden" name="mapping_type" value="category">
                    <div class="fm-form-group">
                        <label class="fm-label">API Key</label>
                        <select class="fm-select" name="api_key" id="fApiKey">
                            <option value="line_type">line_type</option>
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">API Value</label>
                        <input type="text" class="fm-input" name="api_value" id="fApiValue" placeholder="product, kasbon, custom" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Kategori Internal</label>
                        <select class="fm-select" name="target_id" id="fTargetId" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}">{{ $cat['name'] }} ({{ $cat['type'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" id="btnSave" onclick="saveMapping()">Simpan</button>
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

function openModal(title = 'Tambah Mapping') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modal').classList.add('active');
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
    document.getElementById('mappingForm').reset();
    document.getElementById('fId').value = '';
}

function editMapping(m) {
    document.getElementById('fId').value = m.id;
    document.getElementById('fApiKey').value = m.api_key;
    document.getElementById('fApiValue').value = m.api_value;
    document.getElementById('fTargetId').value = m.target_id;
    openModal('Edit Mapping');
}

async function saveMapping() {
    const form = document.getElementById('mappingForm');
    const fd = new FormData(form);
    const body = Object.fromEntries(fd);
    const id = body.id;
    delete body.id;

    const url = id ? '{{ url("admin/finance/mappings") }}/' + id : '{{ route("admin.finance.mappings.store") }}';
    const method = id ? 'PUT' : 'POST';

    try {
        const res = await fetch(url, {
            method, headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            showToast(id ? 'Mapping diupdate!' : 'Mapping ditambahkan!');
            closeModal();
            location.reload();
        } else {
            showToast(data.message || 'Gagal menyimpan', 'error');
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function deleteMapping(id) {
    if (!confirm('Hapus mapping ini?')) return;
    try {
        const res = await fetch('{{ url("admin/finance/mappings") }}/' + id, {
            method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) {
            showToast('Mapping dihapus!');
            document.querySelector(`tr[data-id="${id}"]`).remove();
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
}
</script>
@endpush
