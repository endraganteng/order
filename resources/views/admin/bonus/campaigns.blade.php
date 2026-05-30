@extends('admin.layout')

@section('title', '🎯 Bonus Produk — Campaign')

@section('content')
@php
    $activeCampaigns = collect($campaigns)->where('status', 'active');
    $draftCampaigns = collect($campaigns)->where('status', 'draft');
    $endedCampaigns = collect($campaigns)->where('status', 'ended');
@endphp

<div class="container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2>🎯 Bonus Produk (Campaign)</h2>
            <p class="text-muted small mb-0">Kelola campaign bonus penjualan per produk. Waiter klaim → Finance verifikasi → Poin masuk bonus bulanan.</p>
        </div>
        <button class="btn btn-primary" onclick="showCreateModal()">+ Buat Campaign</button>
    </div>

    {{-- KPI Row --}}
    <div class="kpi-row d-grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-success) !important;">
            <div class="text-muted small text-uppercase fw-bold">Aktif</div>
            <div class="fw-bold mt-2" style="font-size: 1.5rem; color: var(--color-success);">{{ $activeCampaigns->count() }}</div>
        </div>
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-warning) !important;">
            <div class="text-muted small text-uppercase fw-bold">Draft</div>
            <div class="fw-bold mt-2" style="font-size: 1.5rem; color: var(--color-warning);">{{ $draftCampaigns->count() }}</div>
        </div>
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-text-muted) !important;">
            <div class="text-muted small text-uppercase fw-bold">Selesai</div>
            <div class="fw-bold mt-2" style="font-size: 1.5rem; color: var(--color-text-muted);">{{ $endedCampaigns->count() }}</div>
        </div>
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-primary) !important;">
            <div class="text-muted small text-uppercase fw-bold">Total</div>
            <div class="fw-bold mt-2" style="font-size: 1.5rem; color: var(--color-primary);">{{ count($campaigns) }}</div>
        </div>
    </div>

    {{-- Campaign List --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <h3 class="mb-0" style="font-size: 1rem; font-weight: 700;">Daftar Campaign</h3>
        </div>

        @if(count($campaigns) === 0)
            <div class="text-center py-5">
                <div style="font-size: 3rem; margin-bottom: 12px;">📦</div>
                <p class="text-muted">Belum ada campaign. Klik "+ Buat Campaign" untuk mulai.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Periode</th>
                            <th>Produk</th>
                            <th>Eligible</th>
                            <th>Status</th>
                            <th style="width:120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                        @php
                            $products = (array) ($campaign['products'] ?? []);
                            $eligible = $campaign['eligible_users'] ?? ['type' => 'all'];
                            $eligibleLabel = match($eligible['type'] ?? 'all') {
                                'all' => '👥 Semua',
                                'role' => '🏷️ ' . implode(', ', array_map('ucfirst', (array) ($eligible['roles'] ?? []))),
                                'specific' => '👤 ' . count((array) ($eligible['user_ids'] ?? [])) . ' user',
                                default => '-',
                            };
                            $statusClass = match($campaign['status'] ?? '') {
                                'active' => 'status-active',
                                'draft' => 'status-draft',
                                'ended' => 'status-ended',
                                default => 'status-ended',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $campaign['title'] ?? '-' }}</strong>
                                <div class="text-muted small">{{ count($products) }} produk</div>
                            </td>
                            <td>
                                <span class="small">
                                    {{ $campaign['start_date'] ?? '∞' }} → {{ $campaign['end_date'] ?? '∞' }}
                                </span>
                            </td>
                            <td>
                                @foreach(array_slice($products, 0, 2) as $p)
                                    <span class="product-tag">{{ $p['name'] ?? '-' }} <strong>({{ $p['points_per_unit'] ?? 0 }}p)</strong></span>
                                @endforeach
                                @if(count($products) > 2)
                                    <span class="text-muted small">+{{ count($products) - 2 }} lagi</span>
                                @endif
                            </td>
                            <td><span class="small">{{ $eligibleLabel }}</span></td>
                            <td><span class="status-badge {{ $statusClass }}">{{ ucfirst($campaign['status'] ?? '-') }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline" onclick="viewCampaign('{{ $campaign['id'] }}')" title="Detail">👁️</button>
                                    <button class="btn btn-sm btn-outline" onclick="editCampaign('{{ $campaign['id'] }}')" title="Edit">✏️</button>
                                    <button class="btn btn-sm btn-outline btn-outline-danger" onclick="deleteCampaign('{{ $campaign['id'] }}', '{{ addslashes($campaign['title'] ?? '') }}')" title="Hapus">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Create/Edit Modal --}}
<div id="campaignModal" class="modal-overlay" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modalTitle">Buat Campaign Baru</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="campaignForm" class="modal-body">
            <input type="hidden" id="editId" value="">

            {{-- Section: Info Dasar --}}
            <div class="form-section">
                <div class="form-section-title">📋 Informasi Campaign</div>
                <div class="form-group">
                    <label for="fTitle">Judul Campaign <span class="text-danger">*</span></label>
                    <input type="text" id="fTitle" class="form-control" placeholder="Misal: Bonus Royal Canin Juni" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fStartDate">Tanggal Mulai</label>
                        <input type="date" id="fStartDate" class="form-control">
                        <span class="form-hint">Kosong = mulai sekarang</span>
                    </div>
                    <div class="form-group">
                        <label for="fEndDate">Tanggal Selesai</label>
                        <input type="date" id="fEndDate" class="form-control">
                        <span class="form-hint">Kosong = berlaku selamanya</span>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fStatus">Status</label>
                        <select id="fStatus" class="form-control">
                            <option value="active">Aktif</option>
                            <option value="draft">Draft</option>
                            <option value="ended">Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fEligibleType">Target Karyawan</label>
                        <select id="fEligibleType" class="form-control" onchange="toggleEligible()">
                            <option value="all">Semua Karyawan</option>
                            <option value="role">Berdasarkan Role</option>
                            <option value="specific">Pilih Manual</option>
                        </select>
                    </div>
                </div>

                <div id="eligibleRolesDiv" style="display:none;" class="form-group">
                    <label>Pilih Role</label>
                    <div class="checkbox-grid">
                        @foreach(['pelayan', 'kasir', 'finance', 'supervisor', 'backup'] as $role)
                        <label class="checkbox-item">
                            <input type="checkbox" class="js-role-check" value="{{ $role }}">
                            <span>{{ ucfirst($role) }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div id="eligibleUsersDiv" style="display:none;" class="form-group">
                    <label>Pilih Karyawan</label>
                    <div class="user-select-grid">
                        @foreach($waiters as $w)
                        @php $wid = $w['id'] ?? ''; $wname = $w['name'] ?? $w['email'] ?? ''; @endphp
                        @if($wid)
                        <label class="checkbox-item">
                            <input type="checkbox" class="js-user-check" value="{{ $wid }}">
                            <span>{{ $wname }}</span>
                        </label>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Section: Produk --}}
            <div class="form-section">
                <div class="form-section-title">🏷️ Produk & Poin <span class="text-danger">*</span></div>
                <div class="products-header">
                    <span class="products-col-name">Nama Produk</span>
                    <span class="products-col-points">Poin/Unit</span>
                    <span class="products-col-action"></span>
                </div>
                <div id="productsContainer"></div>
                <button type="button" class="btn-add-product" onclick="addProductRow()">
                    <span>+</span> Tambah Produk
                </button>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmit">💾 Simpan Campaign</button>
            </div>
        </form>
    </div>
</div>

{{-- Detail Modal --}}
<div id="detailModal" class="modal-overlay" style="display:none;">
    <div class="modal-dialog" style="max-width: 550px;">
        <div class="modal-header">
            <h3 id="detailTitle">Detail Campaign</h3>
            <button class="modal-close" onclick="document.getElementById('detailModal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body" id="detailContent">Loading...</div>
    </div>
</div>

@push('styles')
<style>
    .product-tag {
        display: inline-block;
        background: var(--color-primary-bg);
        color: var(--color-primary-dark);
        padding: 2px 8px;
        border-radius: var(--radius-sm);
        font-size: 12px;
        margin: 2px 2px 2px 0;
    }
    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-active { background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success-border); }
    .status-draft { background: var(--color-warning-bg); color: var(--color-warning); border: 1px solid var(--color-warning-border); }
    .status-ended { background: #f1f5f9; color: var(--color-text-muted); border: 1px solid var(--color-border); }

    .btn-outline { background: white; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 4px 8px; cursor: pointer; transition: all 0.2s; }
    .btn-outline:hover { background: var(--color-primary-bg); border-color: var(--color-primary); }
    .btn-outline-danger:hover { background: var(--color-danger-bg); border-color: var(--color-danger); }

    .modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; padding:16px; backdrop-filter: blur(2px); }
    .modal-dialog { background:#fff; border-radius: var(--radius-lg); width:100%; max-width:680px; max-height:92vh; overflow-y:auto; box-shadow: var(--shadow-md); }
    .modal-header { display:flex; justify-content:space-between; align-items:center; padding: 14px 20px; border-bottom: 1px solid var(--color-border); position: sticky; top: 0; background: white; z-index: 1; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
    .modal-header h3 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--color-text); }
    .modal-close { background:none; border:none; font-size:22px; cursor:pointer; color: var(--color-text-muted); width:32px; height:32px; border-radius: var(--radius-sm); display:flex; align-items:center; justify-content:center; transition: background 0.2s; }
    .modal-close:hover { background: #f1f5f9; color: var(--color-text); }
    .modal-body { padding: 16px 20px; }
    .modal-footer { display:flex; gap:10px; justify-content:flex-end; padding: 14px 20px; border-top: 1px solid var(--color-border); position: sticky; bottom: 0; background: white; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }

    /* Form Sections */
    .form-section { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; margin-bottom: 14px; }
    .form-section-title { font-size: 0.8rem; font-weight: 700; color: var(--color-text); margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid var(--color-border); }

    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }

    .form-group { margin-bottom: 10px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 4px; }
    .form-hint { display: block; font-size: 0.68rem; color: var(--color-text-muted); margin-top: 3px; }
    .form-control { padding: 8px 10px; font-size: 0.82rem; }

    .checkbox-grid, .user-select-grid { display:flex; flex-wrap:wrap; gap:6px; padding:10px; background: white; border:1px solid var(--color-border); border-radius: var(--radius-md); max-height:130px; overflow-y:auto; }
    .checkbox-item { display:flex; align-items:center; gap:6px; font-size:13px; padding:5px 10px; border-radius: var(--radius-sm); cursor:pointer; border: 1px solid transparent; transition: all 0.15s; }
    .checkbox-item:hover { background: var(--color-primary-bg); border-color: var(--color-primary); }
    .checkbox-item input[type="checkbox"] { accent-color: var(--color-primary); }

    /* Products Table */
    .products-header { display: flex; gap: 8px; padding: 6px 0 8px; border-bottom: 1px solid var(--color-border); margin-bottom: 8px; }
    .products-col-name { flex: 2; font-size: 0.72rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.3px; }
    .products-col-points { flex: 0 0 90px; font-size: 0.72rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.3px; }
    .products-col-action { flex: 0 0 32px; }

    .product-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
    .product-row input[type="text"] { flex:2; }
    .product-row input[type="number"] { flex: 0 0 90px; text-align: center; }
    .product-row .btn-remove { background: white; color: var(--color-danger); border: 1px solid var(--color-danger-border); border-radius: var(--radius-sm); width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; font-weight: 700; transition: all 0.15s; }
    .product-row .btn-remove:hover { background: var(--color-danger); color: white; border-color: var(--color-danger); }

    .btn-add-product { display: flex; align-items: center; gap: 6px; width: 100%; padding: 10px; margin-top: 8px; background: white; border: 1.5px dashed var(--color-border); border-radius: var(--radius-md); color: var(--color-primary); font-size: 0.82rem; font-weight: 600; cursor: pointer; justify-content: center; transition: all 0.2s; }
    .btn-add-product:hover { border-color: var(--color-primary); background: var(--color-primary-bg); }
    .btn-add-product span { font-size: 1.1rem; font-weight: 700; }

    .detail-stat-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; margin-bottom:16px; }
    .detail-stat { text-align:center; padding:10px; background: #f8fafc; border-radius: var(--radius-md); }
    .detail-stat .val { font-size:1.2rem; font-weight:700; }
    .detail-stat .lbl { font-size:11px; color: var(--color-text-muted); text-transform:uppercase; }
</style>
@endpush

<script>
const campaigns = @json($campaigns);

function showCreateModal() {
    document.getElementById('editId').value = '';
    document.getElementById('modalTitle').textContent = 'Buat Campaign Baru';
    document.getElementById('fTitle').value = '';
    document.getElementById('fStartDate').value = '';
    document.getElementById('fEndDate').value = '';
    document.getElementById('fStatus').value = 'active';
    document.getElementById('fEligibleType').value = 'all';
    toggleEligible();
    document.getElementById('productsContainer').innerHTML = '';
    addProductRow();
    document.getElementById('campaignModal').style.display = 'flex';
}

function editCampaign(id) {
    const c = campaigns.find(x => x.id === id);
    if (!c) return;

    document.getElementById('editId').value = id;
    document.getElementById('modalTitle').textContent = 'Edit Campaign';
    document.getElementById('fTitle').value = c.title || '';
    document.getElementById('fStartDate').value = c.start_date || '';
    document.getElementById('fEndDate').value = c.end_date || '';
    document.getElementById('fStatus').value = c.status || 'active';

    const eligible = c.eligible_users || { type: 'all' };
    document.getElementById('fEligibleType').value = eligible.type || 'all';
    toggleEligible();

    if (eligible.type === 'role') {
        const roles = eligible.roles || [];
        document.querySelectorAll('.js-role-check').forEach(cb => { cb.checked = roles.includes(cb.value); });
    }
    if (eligible.type === 'specific') {
        const ids = eligible.user_ids || [];
        document.querySelectorAll('.js-user-check').forEach(cb => { cb.checked = ids.includes(cb.value); });
    }

    const container = document.getElementById('productsContainer');
    container.innerHTML = '';
    const products = c.products || {};
    Object.values(products).forEach(p => addProductRow(p.name, p.points_per_unit));
    if (Object.keys(products).length === 0) addProductRow();

    document.getElementById('campaignModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('campaignModal').style.display = 'none';
}

function toggleEligible() {
    const type = document.getElementById('fEligibleType').value;
    document.getElementById('eligibleRolesDiv').style.display = type === 'role' ? 'block' : 'none';
    document.getElementById('eligibleUsersDiv').style.display = type === 'specific' ? 'block' : 'none';
}

function addProductRow(name = '', points = '') {
    const container = document.getElementById('productsContainer');
    const row = document.createElement('div');
    row.className = 'product-row';
    row.innerHTML = `
        <input type="text" class="form-control js-prod-name" placeholder="Nama produk" value="${name}" required>
        <input type="number" class="form-control js-prod-points" placeholder="Poin" value="${points}" min="1" required>
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(row);
}

document.getElementById('campaignForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const editId = document.getElementById('editId').value;
    const isEdit = editId !== '';

    const products = [];
    document.querySelectorAll('#productsContainer .product-row').forEach(row => {
        const name = row.querySelector('.js-prod-name').value.trim();
        const points = parseInt(row.querySelector('.js-prod-points').value) || 0;
        if (name && points > 0) products.push({ name, points_per_unit: points });
    });

    if (products.length === 0) { alert('Tambahkan minimal 1 produk.'); return; }

    const eligibleType = document.getElementById('fEligibleType').value;
    let eligibleRoles = [], eligibleUserIds = [];
    if (eligibleType === 'role') document.querySelectorAll('.js-role-check:checked').forEach(cb => eligibleRoles.push(cb.value));
    if (eligibleType === 'specific') document.querySelectorAll('.js-user-check:checked').forEach(cb => eligibleUserIds.push(cb.value));

    const payload = {
        title: document.getElementById('fTitle').value.trim(),
        start_date: document.getElementById('fStartDate').value || null,
        end_date: document.getElementById('fEndDate').value || null,
        status: document.getElementById('fStatus').value,
        eligible_type: eligibleType,
        eligible_roles: eligibleRoles,
        eligible_user_ids: eligibleUserIds,
        products,
    };

    const url = isEdit ? '{{ route("admin.bonus.campaigns.store") }}/' + editId : '{{ route("admin.bonus.campaigns.store") }}';
    const method = isEdit ? 'PUT' : 'POST';

    try {
        document.getElementById('btnSubmit').disabled = true;
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message || 'Gagal menyimpan.');
    } catch (err) { alert('Error: ' + err.message); }
    finally { document.getElementById('btnSubmit').disabled = false; }
});

async function deleteCampaign(id, title) {
    if (!confirm(`Hapus campaign "${title}"? Aksi ini tidak bisa dibatalkan.`)) return;
    try {
        const res = await fetch('{{ route("admin.bonus.campaigns.store") }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message || 'Gagal hapus.');
    } catch (err) { alert('Error: ' + err.message); }
}

async function viewCampaign(id) {
    document.getElementById('detailModal').style.display = 'flex';
    document.getElementById('detailContent').innerHTML = '<div class="text-center py-4"><span class="text-muted">Memuat...</span></div>';

    try {
        const res = await fetch('{{ route("admin.bonus.campaigns.store") }}/' + id, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!data.success) { document.getElementById('detailContent').innerHTML = '<p class="text-danger">Gagal memuat data.</p>'; return; }

        const c = data.campaign;
        const stats = data.stats;
        const products = Object.values(c.products || {});
        const eligible = c.eligible_users || { type: 'all' };
        const eligibleLabel = eligible.type === 'all' ? 'Semua' : eligible.type === 'role' ? (eligible.roles || []).join(', ') : (eligible.user_ids || []).length + ' user';

        document.getElementById('detailTitle').textContent = c.title;
        document.getElementById('detailContent').innerHTML = `
            <div style="margin-bottom:12px; padding:10px; background:#f8fafc; border-radius:var(--radius-md); font-size:13px;">
                <strong>Periode:</strong> ${c.start_date || '∞'} → ${c.end_date || '∞'} &nbsp;|&nbsp;
                <strong>Status:</strong> ${c.status} &nbsp;|&nbsp;
                <strong>Eligible:</strong> ${eligibleLabel}
            </div>
            <div class="detail-stat-grid">
                <div class="detail-stat"><div class="val" style="color:var(--color-warning);">${stats.total_pending}</div><div class="lbl">Pending</div></div>
                <div class="detail-stat"><div class="val" style="color:var(--color-success);">${stats.total_approved}</div><div class="lbl">Approved</div></div>
                <div class="detail-stat"><div class="val" style="color:var(--color-danger);">${stats.total_rejected}</div><div class="lbl">Rejected</div></div>
                <div class="detail-stat"><div class="val" style="color:var(--color-primary);">${stats.total_points_approved}</div><div class="lbl">Total Poin</div></div>
            </div>
            <div style="margin-top:12px;">
                <strong style="font-size:13px;">Produk:</strong>
                <div style="margin-top:6px;">
                    ${products.map(p => `<div class="product-tag" style="margin-bottom:4px;">${p.name} — <strong>${p.points_per_unit} poin/unit</strong></div>`).join('')}
                </div>
            </div>
        `;
    } catch (err) { document.getElementById('detailContent').innerHTML = '<p class="text-danger">Error: ' + err.message + '</p>'; }
}
</script>
@endsection
