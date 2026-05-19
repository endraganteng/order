@extends('admin.layout')

@section('title', 'Mapping Akun Kas API')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">🏦 Mapping Akun Kas API</h1>
            <p class="fm-page-subtitle">Mapping sumber pendapatan/pengeluaran dari API ke akun kas internal</p>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="openModal()">+ Tambah Mapping</button>
    </div>

    <div id="toast" class="fm-toast"></div>

    <div class="fm-alert fm-alert-info">
        💡 Mapping ini menentukan ke akun kas mana data dari API masuk. Contoh: <code>penjualan_tunai → Kas Toko</code>, <code>penjualan_qris → QRIS</code>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>API Key</th><th>API Value</th><th>→ Akun Kas</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
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
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada mapping akun kas.</div></div>
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
                    <input type="hidden" name="mapping_type" value="cash_account">
                    <div class="fm-form-group">
                        <label class="fm-label">API Key</label>
                        <select class="fm-select" name="api_key" id="fApiKey">
                            <option value="source">source</option>
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">API Value</label>
                        <input type="text" class="fm-input" name="api_value" id="fApiValue" placeholder="penjualan_tunai, penjualan_qris, pengeluaran_shift" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Akun Kas Tujuan</label>
                        <select class="fm-select" name="target_id" id="fTargetId" required>
                            <option value="">-- Pilih Akun Kas --</option>
                            @foreach($accounts as $acc)
                            <option value="{{ $acc['id'] }}">{{ $acc['name'] }} ({{ $acc['code'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveMapping()">Simpan</button>
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
    const fd = new FormData(document.getElementById('mappingForm'));
    const body = Object.fromEntries(fd);
    const id = body.id; delete body.id;
    const url = id ? '{{ url("admin/finance/mappings") }}/' + id : '{{ route("admin.finance.mappings.store") }}';

    try {
        const res = await fetch(url, {
            method: id ? 'PUT' : 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { showToast('Berhasil!'); closeModal(); location.reload(); }
        else showToast(data.message || 'Gagal', 'error');
    } catch (e) { showToast(e.message, 'error'); }
}

async function deleteMapping(id) {
    if (!confirm('Hapus mapping ini?')) return;
    try {
        const res = await fetch('{{ url("admin/finance/mappings") }}/' + id, {
            method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        if (data.success) { showToast('Dihapus!'); document.querySelector(`tr[data-id="${id}"]`).remove(); }
    } catch (e) { showToast(e.message, 'error'); }
}
</script>
@endpush
