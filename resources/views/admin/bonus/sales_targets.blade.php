@extends('admin.layout')

@section('title', 'Target Penjualan')

@section('content')
@php
    $monthDate = strtotime(($month ?: date('Y-m')) . '-01');
    $prevMonth = date('Y-m', strtotime('-1 month', $monthDate));
    $nextMonth = date('Y-m', strtotime('+1 month', $monthDate));

    $targetsByWaiter = [];
    foreach (($targets ?? []) as $target) {
        $wid = (string) data_get($target, 'waiter_id');
        if ($wid !== '') {
            $targetsByWaiter[$wid] = $target;
        }
    }

    $totalTarget = 0;
    $totalAchieved = 0;
    $achievementCount = 0;

    foreach (($targets ?? []) as $target) {
        $totalTarget += (int) data_get($target, 'target_amount', 0);
        $totalAchieved += (int) data_get($target, 'current_achievement', 0);
        $achievementCount++;
    }

    $averageAchievement = $achievementCount > 0 ? round(array_sum(array_map(function ($target) {
        return (float) data_get($target, 'achievement_percentage', 0);
    }, $targets ?? [])) / $achievementCount, 1) : 0;
@endphp

<div class="container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h2>💰 Target Penjualan</h2>
        <div class="month-nav d-flex align-items-center gap-2">
            @php $currentMonth = date('Y-m'); @endphp
            <a href="?month={{ $prevMonth }}" class="btn btn-sm btn-light">&larr;</a>
            <input type="month" id="monthPicker" class="form-control" value="{{ $month }}">
            <a href="?month={{ $nextMonth }}" class="btn btn-sm btn-light">&rarr;</a>
            <a href="?month={{ $currentMonth }}" class="btn btn-primary btn-sm ms-2">Bulan Ini</a>
        </div>
    </div>

    <div class="kpi-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-label">Total Target</div>
            <div class="kpi-value">Rp {{ number_format($totalTarget, 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Tercapai</div>
            <div class="kpi-value">Rp {{ number_format($totalAchieved, 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Rata-rata Achievement %</div>
            <div class="kpi-value">{{ $averageAchievement }}%</div>
        </div>
    </div>

    <div class="card mb-4">
        <h3 class="section-title">Set Target Bulanan</h3>

        <div class="desktop-only">
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th>Role</th>
                            <th>Target (Rp)</th>
                            <th>Tercapai (Rp)</th>
                            <th>Progress</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($waiters as $waiterKey => $waiter)
                            @php
                                $wid = (string) data_get($waiter, 'id', $waiterKey);
                                $wname = data_get($waiter, 'name', data_get($waiter, 'email', 'Tanpa Nama'));
                                $target = $targetsByWaiter[$wid] ?? null;
                                $role = data_get($target, 'role', 'bird_specialist');
                                $targetAmount = (int) data_get($target, 'target_amount', 0);
                                $currentAchievement = (int) data_get($target, 'current_achievement', 0);
                                $achievementPercentage = (float) data_get($target, 'achievement_percentage', 0);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $wname }}</strong><br>
                                    <span class="text-muted small">{{ data_get($waiter, 'email', '-') }}</span>
                                </td>
                                <td>
                                    <select class="form-control js-role" data-waiter-id="{{ $wid }}">
                                        <option value="bird_specialist" {{ $role === 'bird_specialist' ? 'selected' : '' }}>🐦 Bird Specialist</option>
                                        <option value="fishing_specialist" {{ $role === 'fishing_specialist' ? 'selected' : '' }}>🎣 Fishing Specialist</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" min="0" class="form-control js-target-amount" data-waiter-id="{{ $wid }}" value="{{ $targetAmount }}">
                                </td>
                                <td>Rp <span class="js-achievement-amount" data-waiter-id="{{ $wid }}">{{ number_format($currentAchievement, 0, ',', '.') }}</span></td>
                                <td>
                                    <div style="font-size: 0.875rem; font-weight: bold; text-align: right;"><span class="js-achievement-percent" data-waiter-id="{{ $wid }}">{{ rtrim(rtrim(number_format($achievementPercentage, 1, '.', ''), '0'), '.') }}</span>%</div>
                                    <div class="progress-wrap mt-1">
                                        <div class="progress-bar {{ $achievementPercentage >= 100 ? 'progress-green' : ($achievementPercentage >= 80 ? 'progress-blue' : ($achievementPercentage >= 60 ? 'progress-yellow' : 'progress-red')) }}" style="width: {{ min(100, max(0, $achievementPercentage)) }}%;"></div>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm js-save-target" data-waiter-id="{{ $wid }}">Simpan</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-only mobile-target-cards">
            @foreach($waiters as $waiterKey => $waiter)
                @php
                    $wid = (string) data_get($waiter, 'id', $waiterKey);
                    $wname = data_get($waiter, 'name', data_get($waiter, 'email', 'Tanpa Nama'));
                    $target = $targetsByWaiter[$wid] ?? null;
                    $role = data_get($target, 'role', 'bird_specialist');
                    $targetAmount = (int) data_get($target, 'target_amount', 0);
                    $currentAchievement = (int) data_get($target, 'current_achievement', 0);
                    $achievementPercentage = (float) data_get($target, 'achievement_percentage', 0);
                @endphp
                <div class="card mobile-card">
                    <div class="mobile-title">{{ $wname }}</div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-control js-role" data-waiter-id="{{ $wid }}">
                            <option value="bird_specialist" {{ $role === 'bird_specialist' ? 'selected' : '' }}>🐦 Bird Specialist</option>
                            <option value="fishing_specialist" {{ $role === 'fishing_specialist' ? 'selected' : '' }}>🎣 Fishing Specialist</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target (Rp)</label>
                        <input type="number" min="0" class="form-control js-target-amount" data-waiter-id="{{ $wid }}" value="{{ $targetAmount }}">
                    </div>
                    <div class="mobile-row"><span>Tercapai</span><span>Rp <span class="js-achievement-amount" data-waiter-id="{{ $wid }}">{{ number_format($currentAchievement, 0, ',', '.') }}</span></span></div>
                    <div class="mobile-row"><span>Achievement</span><span><span class="js-achievement-percent" data-waiter-id="{{ $wid }}">{{ rtrim(rtrim(number_format($achievementPercentage, 1, '.', ''), '0'), '.') }}</span>%</span></div>
                    <div class="progress-wrap mb-3">
                        <div class="progress-bar {{ $achievementPercentage >= 100 ? 'progress-green' : ($achievementPercentage >= 80 ? 'progress-blue' : ($achievementPercentage >= 60 ? 'progress-yellow' : 'progress-red')) }}" style="width: {{ min(100, max(0, $achievementPercentage)) }}%;"></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-block js-save-target" data-waiter-id="{{ $wid }}">Simpan</button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Catat Penjualan Harian</h3>
        <form id="salesRecordForm" class="sales-record-grid">
            <div>
                <label class="form-label" for="recordWaiter">Karyawan</label>
                <select id="recordWaiter" name="waiter_id" class="form-control" required>
                    <option value="">Pilih Karyawan</option>
                    @foreach($waiters as $waiterKey => $waiter)
                        @php
                            $wid = (string) data_get($waiter, 'id', $waiterKey);
                            $wname = data_get($waiter, 'name', data_get($waiter, 'email', 'Tanpa Nama'));
                        @endphp
                        <option value="{{ $wid }}">{{ $wname }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="recordDate">Tanggal</label>
                <input type="date" id="recordDate" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div>
                <label class="form-label" for="recordAmount">Amount (Rp)</label>
                <input type="number" id="recordAmount" name="amount" class="form-control" min="0" required>
            </div>
            <div>
                <label class="form-label" for="recordItemsSold">Items Sold (opsional)</label>
                <input type="number" id="recordItemsSold" name="items_sold" class="form-control" min="0">
            </div>
            <div class="record-submit-wrap">
                <button type="submit" class="btn btn-primary btn-block" id="salesRecordSubmit">Catat</button>
            </div>
        </form>
        <div id="salesRecordFeedback" class="feedback-message"></div>
    </div>
</div>

<style>
    .d-flex { display: flex; }
    .justify-content-between { justify-content: space-between; }
    .align-items-center { align-items: center; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 1rem; }
    .mb-3 { margin-bottom: 1rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .text-muted { color: var(--color-text-muted); }
    .text-success { color: var(--color-success, #16a34a); }
    .text-primary { color: var(--color-primary, #667eea); }
    .small { font-size: 0.85rem; }
    .ms-2 { margin-left: 0.5rem; }

    .page-header { margin-bottom: 1.25rem; }
    .month-nav .form-control { min-width: 160px; }

    .card {
        background: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
    }
    .section-title { margin-bottom: 1rem; }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }
    .kpi-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: #fff;
        padding: 1rem;
        border-bottom: 4px solid var(--color-primary);
    }
    .kpi-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        font-weight: 700;
        color: var(--color-text-muted);
    }
    .kpi-value {
        margin-top: 0.4rem;
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--color-primary, #667eea);
    }

    .table-scroll { overflow-x: auto; }
    .table {
        width: 100%;
        min-width: 950px;
        border-collapse: collapse;
    }
    .table th, .table td {
        padding: 0.65rem;
        border-bottom: 1px solid var(--color-border);
        text-align: left;
        vertical-align: middle;
    }

    .form-label {
        display: block;
        margin-bottom: 0.4rem;
        font-weight: 600;
    }
    .form-control {
        width: 100%;
        padding: 0.6rem 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 0.95rem;
    }
    .form-group { margin-bottom: 0.75rem; }

    .progress-wrap {
        height: 10px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
        min-width: 140px;
    }
    .progress-bar {
        height: 100%;
        width: 0;
        transition: width 0.2s ease;
    }
    .progress-green { background: #16a34a; }
    .progress-blue { background: #2563eb; }
    .progress-yellow { background: #d97706; }
    .progress-red { background: #dc2626; }

    .sales-record-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
        align-items: end;
    }
    .record-submit-wrap { align-self: end; }

    .feedback-message {
        margin-top: 0.8rem;
        font-size: 0.9rem;
        font-weight: 600;
        min-height: 1.2rem;
    }
    .feedback-success { color: #16a34a; }
    .feedback-error { color: #dc2626; }

    .mobile-only { display: none; }
    .mobile-target-cards { display: grid; gap: 0.9rem; }
    .mobile-card { padding: 0.85rem; }
    .mobile-title { font-weight: 700; margin-bottom: 0.65rem; }
    .mobile-row {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.3rem 0;
        border-bottom: 1px dashed var(--color-border);
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }
    .btn-block { width: 100%; }

    @media (max-width: 900px) {
        .desktop-only { display: none; }
        .mobile-only { display: block; }
    }
    @media (max-width: 640px) {
        .month-nav { width: 100%; justify-content: center; }
        .sales-record-grid { grid-template-columns: 1fr; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const monthPicker = document.getElementById('monthPicker');
    const monthValue = @json($month);
    const storeTargetUrl = @json(route('admin.bonus.sales_targets.store'));
    const salesRecordUrl = @json(route('admin.bonus.sales_record'));
    const formatter = new Intl.NumberFormat('id-ID');

    function setFeedback(message, isSuccess) {
        const el = document.getElementById('salesRecordFeedback');
        el.textContent = message || '';
        el.classList.remove('feedback-success', 'feedback-error');
        if (message) {
            el.classList.add(isSuccess ? 'feedback-success' : 'feedback-error');
        }
    }

    function progressColorClass(percent) {
        if (percent >= 100) return 'progress-green';
        if (percent >= 80) return 'progress-blue';
        if (percent >= 60) return 'progress-yellow';
        return 'progress-red';
    }

    monthPicker?.addEventListener('change', function () {
        if (!this.value) return;
        window.location.href = '?month=' + encodeURIComponent(this.value);
    });

    document.querySelectorAll('.js-save-target').forEach((btn) => {
        btn.addEventListener('click', async function () {
            const waiterId = this.dataset.waiterId;
            if (!waiterId) return;

            const roleEl = document.querySelector(`.js-role[data-waiter-id="${CSS.escape(waiterId)}"]`);
            const targetEl = document.querySelector(`.js-target-amount[data-waiter-id="${CSS.escape(waiterId)}"]`);

            const role = roleEl ? roleEl.value : 'bird_specialist';
            const targetAmount = targetEl ? Number(targetEl.value || 0) : 0;

            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Menyimpan...';

            try {
                const response = await fetch(storeTargetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        waiter_id: waiterId,
                        month: monthValue,
                        target_amount: targetAmount,
                        role: role,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Gagal menyimpan target');
                }

                const json = await response.json().catch(() => ({}));
                const achievement = Number(json.current_achievement ?? 0);
                const percentage = Number(json.achievement_percentage ?? (targetAmount > 0 ? (achievement / targetAmount) * 100 : 0));

                document.querySelectorAll(`.js-achievement-amount[data-waiter-id="${CSS.escape(waiterId)}"]`).forEach((el) => {
                    el.textContent = formatter.format(Math.round(achievement));
                });
                document.querySelectorAll(`.js-achievement-percent[data-waiter-id="${CSS.escape(waiterId)}"]`).forEach((el) => {
                    const normalized = Number.isFinite(percentage) ? percentage : 0;
                    el.textContent = (Math.round(normalized * 10) / 10).toString();
                });

                const colorClass = progressColorClass(percentage);
                document.querySelectorAll(`.js-save-target[data-waiter-id="${CSS.escape(waiterId)}"]`).forEach((saveBtn) => {
                    const row = saveBtn.closest('tr') || saveBtn.closest('.mobile-card');
                    const bar = row ? row.querySelector('.progress-bar') : null;
                    if (bar) {
                        bar.classList.remove('progress-green', 'progress-blue', 'progress-yellow', 'progress-red');
                        bar.classList.add(colorClass);
                        bar.style.width = Math.min(100, Math.max(0, percentage)) + '%';
                    }
                });

                this.textContent = 'Tersimpan';
                setTimeout(() => {
                    this.disabled = false;
                    this.textContent = originalText;
                }, 1200);
            } catch (error) {
                console.error(error);
                alert('Gagal menyimpan target bulanan.');
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });

    const salesRecordForm = document.getElementById('salesRecordForm');
    const salesRecordSubmit = document.getElementById('salesRecordSubmit');

    salesRecordForm?.addEventListener('submit', async function (e) {
        e.preventDefault();
        setFeedback('', true);

        const payload = {
            waiter_id: document.getElementById('recordWaiter').value,
            date: document.getElementById('recordDate').value,
            amount: Number(document.getElementById('recordAmount').value || 0),
            items_sold: document.getElementById('recordItemsSold').value,
        };

        if (!payload.waiter_id || !payload.date || payload.amount <= 0) {
            setFeedback('Karyawan, tanggal, dan amount wajib diisi dengan benar.', false);
            return;
        }

        const originalText = salesRecordSubmit.textContent;
        salesRecordSubmit.disabled = true;
        salesRecordSubmit.textContent = 'Mencatat...';

        try {
            const response = await fetch(salesRecordUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('Gagal mencatat penjualan');
            }

            setFeedback('Penjualan harian berhasil dicatat.', true);
            salesRecordForm.reset();
            document.getElementById('recordDate').value = @json(date('Y-m-d'));
        } catch (error) {
            console.error(error);
            setFeedback('Terjadi kesalahan saat mencatat penjualan.', false);
        } finally {
            salesRecordSubmit.disabled = false;
            salesRecordSubmit.textContent = originalText;
        }
    });
});
</script>
@endsection
