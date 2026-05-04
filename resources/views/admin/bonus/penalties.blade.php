@extends('admin.layout')

@section('title', 'Penalti Karyawan')

@section('content')
@php
    $defaultPenaltyTypes = [
        'late_arrival' => ['points' => -5, 'label' => 'Terlambat'],
        'mandatory_task_missed' => ['points' => -10, 'label' => 'Tugas Wajib Terlewat'],
        'careless_work' => ['points' => -10, 'label' => 'Kerja Kurang Teliti'],
        'missing_photo_proof' => ['points' => -5, 'label' => 'Bukti Foto Kurang'],
        'valid_complaint' => ['points' => -10, 'label' => 'Komplain Valid'],
    ];

    $configPenaltyTypes = $config['penalty_types'] ?? [];
    $penaltyTypes = [];

    foreach (($configPenaltyTypes ?: $defaultPenaltyTypes) as $typeKey => $typeValue) {
        if (is_array($typeValue)) {
            $penaltyTypes[$typeKey] = [
                'points' => (int) ($typeValue['points'] ?? $defaultPenaltyTypes[$typeKey]['points'] ?? -5),
                'label' => $typeValue['label'] ?? ($defaultPenaltyTypes[$typeKey]['label'] ?? ucwords(str_replace('_', ' ', $typeKey))),
            ];
        } else {
            $penaltyTypes[$typeKey] = [
                'points' => (int) $typeValue,
                'label' => $defaultPenaltyTypes[$typeKey]['label'] ?? ucwords(str_replace('_', ' ', $typeKey)),
            ];
        }
    }

    $badgeClasses = [
        'late_arrival' => 'badge-danger',
        'mandatory_task_missed' => 'badge-danger',
        'careless_work' => 'badge-warning',
        'missing_photo_proof' => 'badge-info',
        'valid_complaint' => 'badge-dark',
    ];

    $totalPenalties = count($penalties ?? []);
    $totalPointsDeducted = 0;
    $uniqueWaiters = [];

    foreach (($penalties ?? []) as $penalty) {
        $totalPointsDeducted += (int) data_get($penalty, 'points_deducted', 0);
        $uniqueWaiters[(string) data_get($penalty, 'waiter_id', '')] = true;
    }
    $affectedWaiters = count(array_filter(array_keys($uniqueWaiters), fn($id) => $id !== ''));
@endphp

<div class="container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h2>⚠️ Penalti Karyawan</h2>
        <button type="button" class="btn btn-primary" id="openPenaltyModal">➕ Tambah Penalti</button>
    </div>

    <div class="card mb-4">
        <form method="GET" class="filter-grid" id="filterForm">
            <div>
                <label class="form-label" for="filterMonth">Bulan</label>
                <input type="month" id="filterMonth" name="month" value="{{ $month }}" class="form-control" required>
            </div>
            <div>
                <label class="form-label" for="filterWaiter">Karyawan</label>
                <select id="filterWaiter" name="waiter_id" class="form-control">
                    <option value="">Semua Karyawan</option>
                    @foreach($waiters as $waiterKey => $waiter)
                        @php
                            $wid = data_get($waiter, 'id', $waiterKey);
                            $wname = data_get($waiter, 'name', data_get($waiter, 'email', 'Tanpa Nama'));
                        @endphp
                        <option value="{{ $wid }}" {{ (string) $waiterId === (string) $wid ? 'selected' : '' }}>{{ $wname }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-end">
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
            </div>
        </form>
    </div>

    <div class="kpi-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-label">Total Penalti</div>
            <div class="kpi-value">{{ $totalPenalties }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Poin Dikurangi</div>
            <div class="kpi-value text-danger">{{ $totalPointsDeducted }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Karyawan Terkena</div>
            <div class="kpi-value">{{ $affectedWaiters }}</div>
        </div>
    </div>

    <div class="card desktop-only">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Tipe</th>
                        <th>Poin</th>
                        <th>Alasan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($penalties as $penalty)
                        @php
                            $type = data_get($penalty, 'penalty_type');
                            $meta = $penaltyTypes[$type] ?? ['label' => ucwords(str_replace('_', ' ', (string) $type)), 'points' => (int) data_get($penalty, 'points_deducted', 0)];
                        @endphp
                        <tr>
                            <td>{{ data_get($penalty, 'date') }}</td>
                            <td>{{ data_get($penalty, 'waiter_name', '-') }}</td>
                            <td>
                                <span class="badge {{ $badgeClasses[$type] ?? 'badge-secondary' }}">{{ $meta['label'] }}</span>
                            </td>
                            <td class="text-danger fw-bold">{{ data_get($penalty, 'points_deducted', $meta['points']) }}</td>
                            <td>{{ data_get($penalty, 'reason', '-') }}</td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm js-delete-penalty" data-id="{{ data_get($penalty, 'id') }}">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada data penalti untuk filter saat ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mobile-only mobile-cards">
        @forelse($penalties as $penalty)
            @php
                $type = data_get($penalty, 'penalty_type');
                $meta = $penaltyTypes[$type] ?? ['label' => ucwords(str_replace('_', ' ', (string) $type)), 'points' => (int) data_get($penalty, 'points_deducted', 0)];
            @endphp
            <div class="card mobile-card">
                <div class="mobile-card-head d-flex justify-content-between align-items-center">
                    <strong>{{ data_get($penalty, 'waiter_name', '-') }}</strong>
                    <span class="badge {{ $badgeClasses[$type] ?? 'badge-secondary' }}">{{ $meta['label'] }}</span>
                </div>
                <div class="mobile-row"><span>Tanggal</span><span>{{ data_get($penalty, 'date') }}</span></div>
                <div class="mobile-row"><span>Poin</span><span class="text-danger fw-bold">{{ data_get($penalty, 'points_deducted', $meta['points']) }}</span></div>
                <div class="mobile-row stack"><span>Alasan</span><span>{{ data_get($penalty, 'reason', '-') }}</span></div>
                <div class="mobile-actions">
                    <button type="button" class="btn btn-danger btn-sm btn-block js-delete-penalty" data-id="{{ data_get($penalty, 'id') }}">Hapus</button>
                </div>
            </div>
        @empty
            <div class="card text-center text-muted">Belum ada data penalti untuk filter saat ini.</div>
        @endforelse
    </div>
</div>

<div class="modal-backdrop" id="penaltyModalBackdrop"></div>
<div class="modal" id="penaltyModal" role="dialog" aria-modal="true" aria-labelledby="penaltyModalTitle">
    <div class="modal-content">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h3 id="penaltyModalTitle">Tambah Penalti</h3>
            <button type="button" class="btn btn-sm btn-light" id="closePenaltyModal">✕</button>
        </div>
        <form id="penaltyForm">
            <div class="form-group">
                <label class="form-label" for="penaltyWaiter">Karyawan</label>
                <select id="penaltyWaiter" name="waiter_id" class="form-control" required>
                    <option value="">Pilih Karyawan</option>
                    @foreach($waiters as $waiterKey => $waiter)
                        @php
                            $wid = data_get($waiter, 'id', $waiterKey);
                            $wname = data_get($waiter, 'name', data_get($waiter, 'email', 'Tanpa Nama'));
                        @endphp
                        <option value="{{ $wid }}" data-name="{{ $wname }}">{{ $wname }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="waiter_name" id="penaltyWaiterName">
            </div>

            <div class="form-group">
                <label class="form-label" for="penaltyDate">Tanggal</label>
                <input type="date" id="penaltyDate" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="penaltyType">Tipe Penalti</label>
                <select id="penaltyType" name="penalty_type" class="form-control" required>
                    @foreach($penaltyTypes as $typeKey => $meta)
                        <option value="{{ $typeKey }}" data-points="{{ (int) $meta['points'] }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="penaltyPointsDisplay">Poin Dikurangi</label>
                <input type="text" id="penaltyPointsDisplay" class="form-control" readonly>
            </div>

            <div class="form-group">
                <label class="form-label" for="penaltyReason">Alasan</label>
                <textarea id="penaltyReason" name="reason" class="form-control" rows="3" required placeholder="Tulis alasan penalti..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="submitPenaltyBtn">Simpan Penalti</button>
        </form>
    </div>
</div>

<style>
    .d-flex { display: flex; }
    .justify-content-between { justify-content: space-between; }
    .align-items-center { align-items: center; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-3 { gap: 1rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .text-center { text-align: center; }
    .text-muted { color: var(--color-text-muted); }
    .text-danger { color: var(--color-danger, #dc3545); }
    .fw-bold { font-weight: bold; }
    .btn-block { width: 100%; }
    .align-end { align-items: end; }

    .page-header { margin-bottom: 1.25rem; }
    .card {
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }
    .form-label {
        display: block;
        margin-bottom: 0.4rem;
        font-weight: 600;
    }
    .form-control {
        width: 100%;
        padding: 0.65rem 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 0.95rem;
    }
    .form-group { margin-bottom: 1rem; }

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
    .badge-danger { background: #dc3545; }
    .badge-warning { background: #f59e0b; }
    .badge-info { background: #0ea5e9; }
    .badge-dark { background: #111827; }
    .badge-secondary { background: #6b7280; }

    .btn-sm { padding: 0.35rem 0.65rem; font-size: 0.85rem; }
    .btn-light { background: #f8f9fa; border: 1px solid var(--color-border); }

    .mobile-only { display: none; }
    .mobile-cards { display: grid; gap: 0.9rem; }
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
    .mobile-row.stack {
        flex-direction: column;
        align-items: flex-start;
    }
    .mobile-actions { margin-top: 0.75rem; }

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
        width: min(520px, calc(100% - 1.5rem));
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1001;
    }
    .modal-content {
        background: #fff;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        padding: 1rem;
    }
    .modal-header { margin-bottom: 0.6rem; }

    .modal-open .modal-backdrop,
    .modal-open .modal {
        opacity: 1;
        visibility: visible;
    }
    .modal-open .modal {
        transform: translate(-50%, -50%);
    }

    @media (max-width: 768px) {
        .desktop-only { display: none; }
        .mobile-only { display: block; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const penaltyTypes = @json($penaltyTypes);
    const storeUrl = @json(route('admin.bonus.penalties.store'));
    const deleteUrlTemplate = @json(route('admin.bonus.penalties.destroy', '__ID__'));

    const root = document.documentElement;
    const openModalBtn = document.getElementById('openPenaltyModal');
    const closeModalBtn = document.getElementById('closePenaltyModal');
    const modalBackdrop = document.getElementById('penaltyModalBackdrop');
    const penaltyForm = document.getElementById('penaltyForm');
    const waiterSelect = document.getElementById('penaltyWaiter');
    const waiterNameInput = document.getElementById('penaltyWaiterName');
    const penaltyTypeSelect = document.getElementById('penaltyType');
    const pointsDisplay = document.getElementById('penaltyPointsDisplay');
    const submitPenaltyBtn = document.getElementById('submitPenaltyBtn');

    function openModal() {
        root.classList.add('modal-open');
    }

    function closeModal() {
        root.classList.remove('modal-open');
        penaltyForm.reset();
        updateWaiterName();
        updatePointsDisplay();
    }

    function updateWaiterName() {
        const selected = waiterSelect.options[waiterSelect.selectedIndex];
        waiterNameInput.value = selected ? (selected.dataset.name || '') : '';
    }

    function updatePointsDisplay() {
        const selected = penaltyTypeSelect.options[penaltyTypeSelect.selectedIndex];
        const points = selected ? Number(selected.dataset.points || 0) : 0;
        pointsDisplay.value = points;
    }

    openModalBtn?.addEventListener('click', openModal);
    closeModalBtn?.addEventListener('click', closeModal);
    modalBackdrop?.addEventListener('click', closeModal);

    waiterSelect?.addEventListener('change', updateWaiterName);
    penaltyTypeSelect?.addEventListener('change', updatePointsDisplay);

    updateWaiterName();
    updatePointsDisplay();

    penaltyForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        updateWaiterName();
        const payload = {
            waiter_id: waiterSelect.value,
            waiter_name: waiterNameInput.value,
            penalty_type: penaltyTypeSelect.value,
            date: document.getElementById('penaltyDate').value,
            reason: document.getElementById('penaltyReason').value,
        };

        if (!payload.waiter_id || !payload.waiter_name || !payload.penalty_type || !payload.date || !payload.reason) {
            alert('Semua field wajib diisi.');
            return;
        }

        const originalText = submitPenaltyBtn.textContent;
        submitPenaltyBtn.disabled = true;
        submitPenaltyBtn.textContent = 'Menyimpan...';

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('Gagal menyimpan penalti');
            }

            window.location.reload();
        } catch (error) {
            console.error(error);
            alert('Terjadi kesalahan saat menyimpan penalti.');
            submitPenaltyBtn.disabled = false;
            submitPenaltyBtn.textContent = originalText;
        }
    });

    document.querySelectorAll('.js-delete-penalty').forEach((btn) => {
        btn.addEventListener('click', async function () {
            const penaltyId = this.dataset.id;
            if (!penaltyId) {
                return;
            }

            const confirmed = window.confirm('Yakin ingin menghapus penalti ini?');
            if (!confirmed) {
                return;
            }

            const deleteUrl = deleteUrlTemplate.replace('__ID__', encodeURIComponent(penaltyId));

            try {
                const response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal menghapus penalti');
                }

                window.location.reload();
            } catch (error) {
                console.error(error);
                alert('Terjadi kesalahan saat menghapus penalti.');
            }
        });
    });
});
</script>
@endsection
