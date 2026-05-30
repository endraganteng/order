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
        <button type="button" class="btn btn-primary" id="openCampaignModal">➕ Buat Campaign</button>
    </div>

    <div class="kpi-grid mb-4">
        <div class="kpi-card kpi-success">
            <div class="kpi-label">Aktif</div>
            <div class="kpi-value text-success">{{ $activeCampaigns->count() }}</div>
        </div>
        <div class="kpi-card kpi-warning">
            <div class="kpi-label">Draft</div>
            <div class="kpi-value text-warning">{{ $draftCampaigns->count() }}</div>
        </div>
        <div class="kpi-card kpi-muted">
            <div class="kpi-label">Selesai</div>
            <div class="kpi-value text-muted">{{ $endedCampaigns->count() }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Campaign</div>
            <div class="kpi-value">{{ count($campaigns) }}</div>
        </div>
    </div>

    {{-- Desktop Table --}}
    <div class="card desktop-only">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Periode</th>
                        <th>Produk</th>
                        <th>Eligible</th>
                        <th>Status</th>
                        <th style="width:140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        @php
                            $products = (array) ($campaign['products'] ?? []);
                            $eligible = $campaign['eligible_users'] ?? ['type' => 'all'];
                            $eligibleLabel = match($eligible['type'] ?? 'all') {
                                'all' => '👥 Semua Karyawan',
                                'role' => '🏷️ ' . implode(', ', array_map('ucfirst', (array) ($eligible['roles'] ?? []))),
                                'specific' => '👤 ' . count((array) ($eligible['user_ids'] ?? [])) . ' user',
                                default => '-',
                            };
                            $badgeClass = match($campaign['status'] ?? '') {
                                'active' => 'badge-success',
                                'draft' => 'badge-warning',
                                'ended' => 'badge-secondary',
                                default => 'badge-secondary',
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
                            <td><span class="badge {{ $badgeClass }}">{{ ucfirst($campaign['status'] ?? '-') }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-light js-view-campaign" data-id="{{ $campaign['id'] }}" title="Detail">👁️</button>
                                    <button type="button" class="btn btn-sm btn-light js-edit-campaign" data-id="{{ $campaign['id'] }}" title="Edit">✏️</button>
                                    <button type="button" class="btn btn-sm btn-danger js-delete-campaign" data-id="{{ $campaign['id'] }}" data-title="{{ $campaign['title'] ?? '' }}" title="Hapus">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada campaign. Klik "Buat Campaign" untuk mulai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Cards --}}
    <div class="mobile-only mobile-cards">
        @forelse($campaigns as $campaign)
            @php
                $products = (array) ($campaign['products'] ?? []);
                $eligible = $campaign['eligible_users'] ?? ['type' => 'all'];
                $eligibleLabel = match($eligible['type'] ?? 'all') {
                    'all' => '👥 Semua',
                    'role' => '🏷️ ' . implode(', ', array_map('ucfirst', (array) ($eligible['roles'] ?? []))),
                    'specific' => '👤 ' . count((array) ($eligible['user_ids'] ?? [])) . ' user',
                    default => '-',
                };
                $badgeClass = match($campaign['status'] ?? '') {
                    'active' => 'badge-success',
                    'draft' => 'badge-warning',
                    'ended' => 'badge-secondary',
                    default => 'badge-secondary',
                };
            @endphp
            <div class="card mobile-card">
                <div class="mobile-card-head d-flex justify-content-between align-items-center">
                    <strong>{{ $campaign['title'] ?? '-' }}</strong>
                    <span class="badge {{ $badgeClass }}">{{ ucfirst($campaign['status'] ?? '-') }}</span>
                </div>
                <div class="mobile-row"><span>Periode</span><span class="small">{{ $campaign['start_date'] ?? '∞' }} → {{ $campaign['end_date'] ?? '∞' }}</span></div>
                <div class="mobile-row"><span>Eligible</span><span class="small">{{ $eligibleLabel }}</span></div>
                <div class="mobile-row stack">
                    <span>Produk ({{ count($products) }})</span>
                    <span>
                        @foreach(array_slice($products, 0, 3) as $p)
                            <span class="product-tag">{{ $p['name'] ?? '-' }} <strong>({{ $p['points_per_unit'] ?? 0 }}p)</strong></span>
                        @endforeach
                        @if(count($products) > 3)
                            <span class="text-muted small">+{{ count($products) - 3 }} lagi</span>
                        @endif
                    </span>
                </div>
                <div class="mobile-actions d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light js-view-campaign" data-id="{{ $campaign['id'] }}">👁️ Detail</button>
                    <button type="button" class="btn btn-sm btn-light js-edit-campaign" data-id="{{ $campaign['id'] }}">✏️ Edit</button>
                    <button type="button" class="btn btn-sm btn-danger js-delete-campaign" data-id="{{ $campaign['id'] }}" data-title="{{ $campaign['title'] ?? '' }}">🗑️</button>
                </div>
            </div>
        @empty
            <div class="card text-center text-muted">Belum ada campaign. Klik "Buat Campaign" untuk mulai.</div>
        @endforelse
    </div>
</div>

{{-- Create/Edit Modal --}}
<div class="modal-backdrop" id="campaignModalBackdrop"></div>
<div class="modal modal-lg" id="campaignModal" role="dialog" aria-modal="true" aria-labelledby="campaignModalTitle">
    <div class="modal-content">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h3 id="campaignModalTitle">Buat Campaign Baru</h3>
            <button type="button" class="btn btn-sm btn-light" id="closeCampaignModal">✕</button>
        </div>
        <form id="campaignForm">
            <input type="hidden" id="editId" value="">

            <div class="form-section">
                <div class="form-section-title">📋 Informasi Campaign</div>

                <div class="form-group">
                    <label class="form-label" for="fTitle">Judul Campaign <span class="text-danger">*</span></label>
                    <input type="text" id="fTitle" class="form-control" placeholder="Misal: Bonus Royal Canin Juni" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="fStartDate">Tanggal Mulai</label>
                        <input type="date" id="fStartDate" class="form-control">
                        <small class="text-muted">Kosongkan untuk mulai sekarang</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fEndDate">Tanggal Selesai</label>
                        <input type="date" id="fEndDate" class="form-control">
                        <small class="text-muted">Kosongkan untuk berlaku selamanya</small>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="fStatus">Status</label>
                        <select id="fStatus" class="form-control">
                            <option value="active">Aktif</option>
                            <option value="draft">Draft</option>
                            <option value="ended">Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fEligibleType">Target Karyawan</label>
                        <select id="fEligibleType" class="form-control">
                            <option value="all">Semua Karyawan</option>
                            <option value="role">Berdasarkan Role</option>
                            <option value="specific">Pilih Manual</option>
                        </select>
                    </div>
                </div>

                <div id="eligibleRolesDiv" class="form-group" style="display:none;">
                    <label class="form-label">Pilih Role</label>
                    <div class="checkbox-grid">
                        @foreach(['pelayan', 'kasir', 'finance', 'supervisor', 'backup'] as $role)
                            <label class="checkbox-item">
                                <input type="checkbox" class="js-role-check" value="{{ $role }}">
                                <span>{{ ucfirst($role) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div id="eligibleUsersDiv" class="form-group" style="display:none;">
                    <label class="form-label">Pilih Karyawan</label>
                    <div class="checkbox-grid checkbox-grid-scroll">
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

            <div class="form-section">
                <div class="form-section-title">🏷️ Produk &amp; Poin <span class="text-danger">*</span></div>
                <div class="products-header d-flex gap-2">
                    <span class="products-col-name">Nama Produk</span>
                    <span class="products-col-points">Poin/Unit</span>
                    <span class="products-col-action"></span>
                </div>
                <div id="productsContainer"></div>
                <button type="button" class="btn-add-product" id="addProductBtn">+ Tambah Produk</button>
                <small class="text-muted" style="display:block; margin-top:0.5rem;">
                    Ketik untuk mencari dari {{ count($masterProducts ?? []) }} produk master, atau ketik bebas untuk produk baru.
                </small>
            </div>

            <script id="masterProductsData" type="application/json">@php
                $list = [];
                foreach (($masterProducts ?? []) as $p) {
                    if (!empty($p['name'])) {
                        $list[] = ['name' => $p['name'], 'category' => $p['category'] ?? ''];
                    }
                }
                echo json_encode($list);
            @endphp</script>

            <div class="modal-footer d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light" id="cancelCampaignBtn">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmit">💾 Simpan Campaign</button>
            </div>
        </form>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal-backdrop" id="detailModalBackdrop"></div>
<div class="modal" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle">
    <div class="modal-content">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h3 id="detailModalTitle">Detail Campaign</h3>
            <button type="button" class="btn btn-sm btn-light" id="closeDetailModal">✕</button>
        </div>
        <div id="detailContent" class="modal-body">Loading...</div>
    </div>
</div>

<style>
    .d-flex { display: flex; }
    .justify-content-between { justify-content: space-between; }
    .justify-content-end { justify-content: flex-end; }
    .align-items-center { align-items: center; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-1 { gap: 0.35rem; }
    .gap-2 { gap: 0.55rem; }
    .gap-3 { gap: 1rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .text-center { text-align: center; }
    .text-muted { color: var(--color-text-muted); }
    .text-danger { color: var(--color-danger, #dc3545); }
    .text-success { color: var(--color-success, #16a34a); }
    .text-warning { color: var(--color-warning, #d97706); }

    .page-header { margin-bottom: 1.25rem; }
    .card {
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
    }

    .form-label { display: block; margin-bottom: 0.4rem; font-weight: 600; }
    .form-control {
        width: 100%;
        padding: 0.65rem 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 0.95rem;
        background: #fff;
    }
    .form-group { margin-bottom: 1rem; }
    .form-group small { display: block; margin-top: 0.3rem; font-size: 0.78rem; }

    .form-section {
        background: #f8fafc;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .form-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 0.85rem;
        padding-bottom: 0.55rem;
        border-bottom: 1px solid var(--color-border);
    }
    .form-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    @media (max-width: 600px) {
        .form-grid-2 { grid-template-columns: 1fr; }
    }

    .checkbox-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 0.55rem;
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
    }
    .checkbox-grid-scroll { max-height: 160px; overflow-y: auto; }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.6rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 0.88rem;
        border: 1px solid transparent;
        transition: background 0.15s, border-color 0.15s;
    }
    .checkbox-item:hover { background: #eef2ff; border-color: #c7d2fe; }
    .checkbox-item input[type="checkbox"] { margin: 0; accent-color: var(--color-primary); }

    .products-header {
        padding: 0.4rem 0 0.5rem;
        border-bottom: 1px solid var(--color-border);
        margin-bottom: 0.5rem;
    }
    .products-col-name { flex: 2; font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.3px; }
    .products-col-points { flex: 0 0 110px; font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.3px; }
    .products-col-action { flex: 0 0 36px; }
    .product-row {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        align-items: center;
    }
    .product-row input[type="text"] { flex: 2; }
    .product-row input[type="number"] { flex: 0 0 110px; text-align: center; }
    .product-row .btn-remove {
        background: #fff;
        color: var(--color-danger, #dc3545);
        border: 1px solid var(--color-danger, #dc3545);
        border-radius: var(--radius-sm);
        width: 36px;
        height: 38px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 700;
        transition: all 0.15s;
    }
    .product-row .btn-remove:hover { background: var(--color-danger, #dc3545); color: #fff; }
    .btn-add-product {
        display: block;
        width: 100%;
        padding: 0.65rem;
        margin-top: 0.4rem;
        background: #fff;
        border: 1.5px dashed var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-primary);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-add-product:hover {
        border-color: var(--color-primary);
        background: #eef2ff;
    }

    /* Custom autocomplete suggestions - portal-rendered to body to escape modal overflow */
    .product-row .autocomplete-wrap input[type="text"] { flex: none; width: 100%; }
    .autocomplete-suggestions {
        position: fixed;
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        max-height: 320px;
        overflow-y: auto;
        z-index: 1100;
    }
    .autocomplete-item {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        font-size: 0.875rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 2px;
        transition: background 0.1s;
    }
    .autocomplete-item:last-child { border-bottom: none; }
    .autocomplete-item:hover {
        background: #eef2ff;
    }
    .autocomplete-name {
        font-weight: 600;
        color: var(--color-text, #1e293b);
    }
    .autocomplete-cat {
        font-size: 0.7rem;
        color: var(--color-text-muted);
        font-weight: 500;
    }
    .autocomplete-more {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        color: var(--color-text-muted);
        background: #f8fafc;
        text-align: center;
        font-style: italic;
        border-top: 1px solid var(--color-border);
    }
    .autocomplete-empty {
        padding: 0.65rem 0.75rem;
        font-size: 0.8rem;
        color: var(--color-text-muted);
        line-height: 1.4;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }
    .kpi-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        background: #fff;
        border-bottom: 4px solid var(--color-primary);
    }
    .kpi-success { border-bottom-color: var(--color-success, #16a34a); }
    .kpi-warning { border-bottom-color: var(--color-warning, #d97706); }
    .kpi-muted { border-bottom-color: var(--color-text-muted, #94a3b8); }
    .kpi-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        color: var(--color-text-muted);
        font-weight: 700;
    }
    .kpi-value {
        margin-top: 0.4rem;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .table-scroll { overflow-x: auto; }
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }
    .table th, .table td {
        text-align: left;
        padding: 0.7rem;
        border-bottom: 1px solid var(--color-border);
        vertical-align: top;
    }

    .badge {
        display: inline-block;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #fff;
    }
    .badge-success { background: #16a34a; }
    .badge-warning { background: #d97706; }
    .badge-danger { background: #dc3545; }
    .badge-secondary { background: #6b7280; }

    .product-tag {
        display: inline-block;
        background: #eef2ff;
        color: #4338ca;
        padding: 0.15rem 0.55rem;
        border-radius: var(--radius-sm);
        font-size: 0.78rem;
        margin: 0.1rem 0.15rem 0.1rem 0;
    }

    .btn-sm { padding: 0.35rem 0.6rem; font-size: 0.85rem; }
    .btn-light {
        background: #f8f9fa;
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    .btn-light:hover { background: #eef2ff; }
    .btn-danger {
        background: var(--color-danger, #dc3545);
        color: #fff;
        border: 1px solid var(--color-danger, #dc3545);
    }
    .btn-danger:hover { background: #b91c1c; border-color: #b91c1c; }

    .mobile-only { display: none; }
    .mobile-cards { gap: 0.9rem; }
    .mobile-card { padding: 0.9rem; }
    .mobile-card-head { margin-bottom: 0.7rem; }
    .mobile-row {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.35rem 0;
        border-bottom: 1px dashed var(--color-border);
        font-size: 0.9rem;
    }
    .mobile-row.stack { flex-direction: column; align-items: flex-start; }
    .mobile-actions { margin-top: 0.75rem; flex-wrap: wrap; }

    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease;
        z-index: 1000;
    }
    .modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -48%);
        width: min(560px, calc(100% - 1.5rem));
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1001;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal.modal-lg { width: min(720px, calc(100% - 1.5rem)); }
    .modal-content {
        background: #fff;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        padding: 1rem;
    }
    .modal-header { margin-bottom: 0.6rem; }
    .modal-footer { padding-top: 0.85rem; border-top: 1px solid var(--color-border); margin-top: 0.5rem; }
    .modal-body { padding: 0.5rem 0; }

    .modal-open-create #campaignModal,
    .modal-open-create #campaignModalBackdrop,
    .modal-open-detail #detailModal,
    .modal-open-detail #detailModalBackdrop {
        opacity: 1;
        visibility: visible;
    }
    .modal-open-create #campaignModal,
    .modal-open-detail #detailModal {
        transform: translate(-50%, -50%);
    }

    .detail-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.55rem;
        margin-bottom: 1rem;
    }
    .detail-stat {
        text-align: center;
        padding: 0.7rem 0.5rem;
        background: #f8fafc;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
    }
    .detail-stat .val { font-size: 1.25rem; font-weight: 700; }
    .detail-stat .lbl { font-size: 0.7rem; color: var(--color-text-muted); text-transform: uppercase; }

    /* Claim history tabs */
    .claim-history { margin-top: 0.85rem; border-top: 1px solid var(--color-border); padding-top: 0.85rem; }
    .claim-history-header { margin-bottom: 0.6rem; }
    .claim-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 0.65rem;
        border-bottom: 1px solid var(--color-border);
    }
    .claim-tab-btn {
        background: transparent;
        border: none;
        padding: 0.55rem 0.85rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--color-text-muted);
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s;
    }
    .claim-tab-btn:hover { color: var(--color-text, #1e293b); }
    .claim-tab-btn.active {
        color: var(--color-primary);
        border-bottom-color: var(--color-primary);
    }
    .claim-tab-btn .badge {
        font-size: 0.7rem;
        padding: 1px 7px;
    }
    .claim-tab-content .table { font-size: 0.825rem; }
    .claim-tab-content .table th { background: #f8fafc; font-size: 0.7rem; text-transform: uppercase; color: var(--color-text-muted); }
    .claim-tab-content .table td { padding: 0.45rem 0.55rem; vertical-align: middle; }

    @media (max-width: 768px) {
        .desktop-only { display: none; }
        .mobile-only { display: block; }
        .mobile-cards { display: grid; }
        .detail-stat-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const campaigns = @json($campaigns);
    const storeUrl = @json(route('admin.bonus.campaigns.store'));
    const showUrlTemplate = @json(route('admin.bonus.campaigns.show', '__ID__'));
    const updateUrlTemplate = @json(route('admin.bonus.campaigns.update', '__ID__'));
    const destroyUrlTemplate = @json(route('admin.bonus.campaigns.destroy', '__ID__'));

    const root = document.documentElement;
    const form = document.getElementById('campaignForm');
    const modalCreate = document.getElementById('campaignModal');
    const modalCreateBackdrop = document.getElementById('campaignModalBackdrop');
    const modalDetail = document.getElementById('detailModal');
    const modalDetailBackdrop = document.getElementById('detailModalBackdrop');

    function openCreate() { root.classList.add('modal-open-create'); }
    function closeCreate() {
        root.classList.remove('modal-open-create');
        // Cleanup any orphan portal-rendered autocomplete dropdowns
        document.querySelectorAll('body > .autocomplete-suggestions').forEach(el => el.remove());
    }
    function openDetail() { root.classList.add('modal-open-detail'); }
    function closeDetail() { root.classList.remove('modal-open-detail'); }

    function urlWith(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    function showCreateModal() {
        document.getElementById('editId').value = '';
        document.getElementById('campaignModalTitle').textContent = 'Buat Campaign Baru';
        document.getElementById('fTitle').value = '';
        document.getElementById('fStartDate').value = '';
        document.getElementById('fEndDate').value = '';
        document.getElementById('fStatus').value = 'active';
        document.getElementById('fEligibleType').value = 'all';
        toggleEligible();
        document.querySelectorAll('.js-role-check').forEach(cb => cb.checked = false);
        document.querySelectorAll('.js-user-check').forEach(cb => cb.checked = false);
        document.getElementById('productsContainer').innerHTML = '';
        // Cleanup orphan portal dropdowns from previous open
        document.querySelectorAll('body > .autocomplete-suggestions').forEach(el => el.remove());
        addProductRow();
        openCreate();
    }

    function editCampaign(id) {
        const c = campaigns.find(x => x.id === id);
        if (!c) return;

        document.getElementById('editId').value = id;
        document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
        document.getElementById('fTitle').value = c.title || '';
        document.getElementById('fStartDate').value = c.start_date || '';
        document.getElementById('fEndDate').value = c.end_date || '';
        document.getElementById('fStatus').value = c.status || 'active';

        const eligible = c.eligible_users || { type: 'all' };
        document.getElementById('fEligibleType').value = eligible.type || 'all';
        toggleEligible();

        document.querySelectorAll('.js-role-check').forEach(cb => cb.checked = false);
        document.querySelectorAll('.js-user-check').forEach(cb => cb.checked = false);
        if (eligible.type === 'role') {
            const roles = eligible.roles || [];
            document.querySelectorAll('.js-role-check').forEach(cb => cb.checked = roles.includes(cb.value));
        }
        if (eligible.type === 'specific') {
            const ids = eligible.user_ids || [];
            document.querySelectorAll('.js-user-check').forEach(cb => cb.checked = ids.includes(cb.value));
        }

        const container = document.getElementById('productsContainer');
        container.innerHTML = '';
        // Cleanup orphan portal dropdowns from previous open
        document.querySelectorAll('body > .autocomplete-suggestions').forEach(el => el.remove());
        const products = c.products || {};
        Object.values(products).forEach(p => addProductRow(p.name, p.points_per_unit));
        if (Object.keys(products).length === 0) addProductRow();

        openCreate();
    }

    function toggleEligible() {
        const type = document.getElementById('fEligibleType').value;
        document.getElementById('eligibleRolesDiv').style.display = type === 'role' ? 'block' : 'none';
        document.getElementById('eligibleUsersDiv').style.display = type === 'specific' ? 'block' : 'none';
    }

    // Master products data (loaded once on page load)
    const MASTER_PRODUCTS = (function() {
        try {
            return JSON.parse(document.getElementById('masterProductsData').textContent || '[]');
        } catch (e) { return []; }
    })();

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function addProductRow(name = '', points = '') {
        const container = document.getElementById('productsContainer');
        const row = document.createElement('div');
        row.className = 'product-row';
        row.innerHTML = `
            <div class="autocomplete-wrap" style="position:relative; flex:1; min-width:0;">
                <input type="text" class="form-control js-prod-name" placeholder="Cari produk..." value="${escapeHtml(name)}" autocomplete="off" required>
            </div>
            <input type="number" class="form-control js-prod-points" placeholder="Poin" value="${escapeHtml(points)}" min="1" required style="flex:0 0 100px;">
            <button type="button" class="btn-remove">×</button>
        `;

        const input = row.querySelector('.js-prod-name');

        // Portal-rendered dropdown attached to <body> to escape modal overflow clipping.
        const sugg = document.createElement('div');
        sugg.className = 'autocomplete-suggestions';
        sugg.style.display = 'none';
        document.body.appendChild(sugg);

        const positionDropdown = () => {
            const rect = input.getBoundingClientRect();
            sugg.style.left = rect.left + 'px';
            sugg.style.top = (rect.bottom + 4) + 'px';
            sugg.style.width = rect.width + 'px';
        };

        const renderSuggestions = (term) => {
            const q = (term || '').trim().toLowerCase();
            if (!q) { sugg.style.display = 'none'; sugg.innerHTML = ''; return; }
            const matches = MASTER_PRODUCTS
                .filter(p => p.name.toLowerCase().includes(q));
            if (matches.length === 0) {
                sugg.innerHTML = `<div class="autocomplete-empty">Tidak ada produk cocok. Tetap pakai "<strong>${escapeHtml(term)}</strong>" sebagai produk baru.</div>`;
                positionDropdown();
                sugg.style.display = 'block';
                return;
            }
            const visible = matches.slice(0, 50);
            const moreCount = matches.length - visible.length;
            sugg.innerHTML = visible.map(p =>
                `<div class="autocomplete-item" data-name="${escapeHtml(p.name)}">
                    <span class="autocomplete-name">${escapeHtml(p.name)}</span>
                    ${p.category ? `<span class="autocomplete-cat">${escapeHtml(p.category)}</span>` : ''}
                </div>`
            ).join('') + (moreCount > 0 ? `<div class="autocomplete-more">+${moreCount} produk lain — perjelas pencarian</div>` : '');
            positionDropdown();
            sugg.style.display = 'block';
        };

        input.addEventListener('input', e => renderSuggestions(e.target.value));
        input.addEventListener('focus', e => renderSuggestions(e.target.value));
        input.addEventListener('blur', () => setTimeout(() => { sugg.style.display = 'none'; }, 200));

        // Reposition on modal scroll & window resize while dropdown is visible.
        const onScrollOrResize = () => {
            if (sugg.style.display !== 'none') positionDropdown();
        };
        document.querySelector('#campaignModal').addEventListener('scroll', onScrollOrResize);
        window.addEventListener('resize', onScrollOrResize);

        sugg.addEventListener('mousedown', e => {
            const item = e.target.closest('.autocomplete-item');
            if (!item) return;
            e.preventDefault();
            input.value = item.getAttribute('data-name');
            sugg.style.display = 'none';
            row.querySelector('.js-prod-points').focus();
        });

        // Remove portal dropdown when row is removed
        row.querySelector('.btn-remove').addEventListener('click', () => {
            sugg.remove();
            row.remove();
        });
        container.appendChild(row);
    }

    async function viewCampaign(id) {
        document.getElementById('detailContent').innerHTML = '<div class="text-center text-muted" style="padding:1.5rem;">Memuat...</div>';
        openDetail();

        try {
            const res = await fetch(urlWith(showUrlTemplate, id), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) {
                document.getElementById('detailContent').innerHTML = '<p class="text-danger">Gagal memuat data.</p>';
                return;
            }

            const c = data.campaign;
            const stats = data.stats;
            const claims = data.claims || { pending: [], approved: [], rejected: [] };
            const products = Object.values(c.products || {});
            const eligible = c.eligible_users || { type: 'all' };
            const eligibleLabel = eligible.type === 'all'
                ? 'Semua Karyawan'
                : eligible.type === 'role'
                    ? (eligible.roles || []).map(r => r[0].toUpperCase() + r.slice(1)).join(', ')
                    : (eligible.user_ids || []).length + ' user';

            const fmtDate = (val) => {
                if (!val) return '-';
                if (typeof val === 'number') return new Date(val * 1000).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' });
                return val;
            };

            const renderClaimRows = (list, badge) => {
                if (!list || list.length === 0) {
                    return '<tr><td colspan="7" class="text-center text-muted" style="padding:1rem;">Belum ada klaim ' + badge.label + '.</td></tr>';
                }
                return list.map(cl => {
                    const verifierCell = (cl.status === 'approved' || cl.status === 'rejected')
                        ? `<div class="small">
                                <strong>${escapeHtml(cl.verified_by || '-')}</strong>
                                <div class="text-muted" style="font-size:0.7rem;">${escapeHtml(fmtDate(cl.verified_at))}</div>
                                ${cl.reject_reason ? `<div class="text-danger" style="font-size:0.7rem; margin-top:2px;">⚠️ ${escapeHtml(cl.reject_reason)}</div>` : ''}
                           </div>`
                        : '<span class="text-muted small">—</span>';
                    return `
                        <tr>
                            <td>${escapeHtml(cl.waiter_name || '-')}</td>
                            <td>${escapeHtml(cl.product_name || cl.product_key || '-')}</td>
                            <td class="text-center">${escapeHtml(String(cl.quantity || 0))}</td>
                            <td class="text-center"><strong>${escapeHtml(String(cl.points_claimed || 0))}</strong></td>
                            <td class="small text-muted">${escapeHtml(fmtDate(cl.submitted_at || cl.date))}</td>
                            <td>${verifierCell}</td>
                            <td>${cl.photo_url ? `<a href="${escapeHtml(cl.photo_url)}" target="_blank" class="small">📷 Foto</a>` : '<span class="text-muted small">-</span>'}</td>
                        </tr>
                    `;
                }).join('');
            };

            const tabBtn = (key, label, count, badgeClass) =>
                `<button type="button" class="claim-tab-btn ${key === 'pending' ? 'active' : ''}" data-tab="${key}">
                    ${label} <span class="badge ${badgeClass}">${count}</span>
                </button>`;

            document.getElementById('detailModalTitle').textContent = c.title || 'Detail Campaign';
            document.getElementById('detailContent').innerHTML = `
                <div class="card" style="background:#f8fafc; margin-bottom:0.85rem;">
                    <div class="small"><strong>Periode:</strong> ${c.start_date || '∞'} → ${c.end_date || '∞'}</div>
                    <div class="small"><strong>Status:</strong> ${c.status || '-'}</div>
                    <div class="small"><strong>Eligible:</strong> ${eligibleLabel}</div>
                </div>
                <div class="detail-stat-grid">
                    <div class="detail-stat"><div class="val text-warning">${stats.total_pending}</div><div class="lbl">Pending</div></div>
                    <div class="detail-stat"><div class="val text-success">${stats.total_approved}</div><div class="lbl">Approved</div></div>
                    <div class="detail-stat"><div class="val text-danger">${stats.total_rejected}</div><div class="lbl">Rejected</div></div>
                    <div class="detail-stat"><div class="val">${stats.total_points_approved}</div><div class="lbl">Total Poin</div></div>
                </div>
                <div style="margin-bottom:0.85rem;">
                    <strong style="font-size:0.9rem;">Produk (${products.length})</strong>
                    <div style="margin-top:0.5rem;">
                        ${products.map(p => `<span class="product-tag" style="margin-bottom:0.2rem;">${escapeHtml(p.name)} — <strong>${escapeHtml(String(p.points_per_unit))} poin/unit</strong></span>`).join('')}
                    </div>
                </div>
                <div class="claim-history">
                    <div class="claim-history-header">
                        <strong style="font-size:0.9rem;">📋 Riwayat Klaim</strong>
                    </div>
                    <div class="claim-tabs">
                        ${tabBtn('pending', 'Pending', stats.total_pending, 'badge-warning')}
                        ${tabBtn('approved', 'Approved', stats.total_approved, 'badge-success')}
                        ${tabBtn('rejected', 'Rejected', stats.total_rejected, 'badge-danger')}
                    </div>
                    <div class="claim-tab-content" data-tab="pending">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Waiter</th><th>Produk</th><th class="text-center">Qty</th><th class="text-center">Poin</th><th>Disubmit</th><th>Diverifikasi</th><th>Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>${renderClaimRows(claims.pending, { label: 'pending' })}</tbody>
                            </table>
                        </div>
                    </div>
                    <div class="claim-tab-content" data-tab="approved" style="display:none;">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Waiter</th><th>Produk</th><th class="text-center">Qty</th><th class="text-center">Poin</th><th>Disubmit</th><th>Diverifikasi</th><th>Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>${renderClaimRows(claims.approved, { label: 'approved' })}</tbody>
                            </table>
                        </div>
                    </div>
                    <div class="claim-tab-content" data-tab="rejected" style="display:none;">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Waiter</th><th>Produk</th><th class="text-center">Qty</th><th class="text-center">Poin</th><th>Disubmit</th><th>Diverifikasi</th><th>Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>${renderClaimRows(claims.rejected, { label: 'rejected' })}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            // Tab switching for claim history
            document.querySelectorAll('#detailContent .claim-tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const target = btn.dataset.tab;
                    document.querySelectorAll('#detailContent .claim-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === target));
                    document.querySelectorAll('#detailContent .claim-tab-content').forEach(c => {
                        c.style.display = c.dataset.tab === target ? 'block' : 'none';
                    });
                });
            });
        } catch (err) {
            document.getElementById('detailContent').innerHTML = `<p class="text-danger">Error: ${err.message}</p>`;
        }
    }

    async function deleteCampaign(id, title) {
        if (!window.confirm(`Hapus campaign "${title}"? Aksi ini tidak bisa dibatalkan.`)) return;
        try {
            const res = await fetch(urlWith(destroyUrlTemplate, id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.success) window.location.reload();
            else alert(data.message || 'Gagal hapus.');
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }

    // === Event bindings ===
    document.getElementById('openCampaignModal').addEventListener('click', showCreateModal);
    document.getElementById('closeCampaignModal').addEventListener('click', closeCreate);
    document.getElementById('cancelCampaignBtn').addEventListener('click', closeCreate);
    modalCreateBackdrop.addEventListener('click', closeCreate);

    document.getElementById('closeDetailModal').addEventListener('click', closeDetail);
    modalDetailBackdrop.addEventListener('click', closeDetail);

    document.getElementById('fEligibleType').addEventListener('change', toggleEligible);
    document.getElementById('addProductBtn').addEventListener('click', () => addProductRow());

    document.querySelectorAll('.js-view-campaign').forEach(btn => {
        btn.addEventListener('click', () => viewCampaign(btn.dataset.id));
    });
    document.querySelectorAll('.js-edit-campaign').forEach(btn => {
        btn.addEventListener('click', () => editCampaign(btn.dataset.id));
    });
    document.querySelectorAll('.js-delete-campaign').forEach(btn => {
        btn.addEventListener('click', () => deleteCampaign(btn.dataset.id, btn.dataset.title || ''));
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const editId = document.getElementById('editId').value;
        const isEdit = editId !== '';

        const products = [];
        document.querySelectorAll('#productsContainer .product-row').forEach(row => {
            const name = row.querySelector('.js-prod-name').value.trim();
            const points = parseInt(row.querySelector('.js-prod-points').value, 10) || 0;
            if (name && points > 0) products.push({ name, points_per_unit: points });
        });
        if (products.length === 0) { alert('Tambahkan minimal 1 produk.'); return; }

        const eligibleType = document.getElementById('fEligibleType').value;
        const eligibleRoles = [];
        const eligibleUserIds = [];
        if (eligibleType === 'role') {
            document.querySelectorAll('.js-role-check:checked').forEach(cb => eligibleRoles.push(cb.value));
        }
        if (eligibleType === 'specific') {
            document.querySelectorAll('.js-user-check:checked').forEach(cb => eligibleUserIds.push(cb.value));
        }

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

        const url = isEdit ? urlWith(updateUrlTemplate, editId) : storeUrl;
        const method = isEdit ? 'PUT' : 'POST';
        const btn = document.getElementById('btnSubmit');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) window.location.reload();
            else { alert(data.message || 'Gagal menyimpan.'); btn.disabled = false; btn.textContent = originalText; }
        } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
})();
</script>
@endsection
