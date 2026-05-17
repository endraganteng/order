@extends('admin.layout')

@section('title', '📊 Penilaian Harian (Otomatis)')

@section('content')
<div class="container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2>📊 Penilaian Harian (Otomatis)</h2>
            <p class="text-muted small mb-0">Semua skor dihitung otomatis. Penilaian Pelayanan &amp; Penjualan dilakukan saat finalisasi bulanan.</p>
        </div>
        
        <div class="date-navigation d-flex align-items-center bg-white p-2 rounded shadow-sm">
            <a href="?date={{ date('Y-m-d', strtotime($date . ' -1 day')) }}" class="btn btn-sm btn-light border">&larr;</a>
            <input type="date" id="score_date" class="form-control border-0 mx-2" value="{{ $date }}" style="width: auto;">
            <a href="?date={{ date('Y-m-d', strtotime($date . ' +1 day')) }}" class="btn btn-sm btn-light border">&rarr;</a>
            <a href="?date={{ date('Y-m-d') }}" class="btn btn-sm btn-primary ml-2 ms-2">Hari Ini</a>
        </div>
    </div>

    @php
        $totalWaiters = count($waiters);
        $scoredCount = count($existingScores);

        $totalPoints = 0;
        foreach($existingScores as $score) {
            // Saved record nests per-category values inside 'categories'. Fallback to flat keys for legacy records.
            $cats = is_array($score['categories'] ?? null) ? $score['categories'] : [];
            $totalPoints += (
                (int) ($cats['discipline']  ?? $score['discipline']  ?? 0) +
                (int) ($cats['operational'] ?? $score['operational'] ?? 0) +
                (int) ($cats['attitude']    ?? $score['attitude']    ?? 0)
            );
        }
        $avgPoints = $scoredCount > 0 ? round($totalPoints / $scoredCount, 1) : 0;
    @endphp

    <div class="kpi-row d-grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-primary) !important;">
            <div class="text-muted small text-uppercase fw-bold">Total Karyawan</div>
            <div class="fs-3 fw-bold mt-2" style="font-size: 1.5rem;">{{ $totalWaiters }}</div>
        </div>
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-success) !important;">
            <div class="text-muted small text-uppercase fw-bold">Sudah Tercatat</div>
            <div class="fs-3 fw-bold mt-2" style="font-size: 1.5rem;">{{ $scoredCount }}</div>
        </div>
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-info) !important;">
            <div class="text-muted small text-uppercase fw-bold">Rata-rata Poin</div>
            <div class="fs-3 fw-bold mt-2" style="font-size: 1.5rem;">{{ $avgPoints }}</div>
        </div>
        <div class="card p-3 text-center border-0 shadow-sm" style="border-bottom: 4px solid var(--color-warning) !important;">
            <div class="text-muted small text-uppercase fw-bold">Max Harian</div>
            <div class="fs-3 fw-bold mt-2" style="font-size: 1.5rem;">25</div>
        </div>
    </div>

    <div class="info-banner mb-4 p-3 rounded" style="background: #eff6ff; border: 1px solid #bfdbfe;">
        <span class="fw-bold" style="color: #1d4ed8;">ℹ️ Halaman ini bersifat informatif.</span>
        <span class="text-muted small"> Skor dihitung otomatis dari absensi, tugas, dan laporan kegiatan. Penilaian Pelayanan &amp; Penjualan dilakukan saat <a href="{{ route('admin.bonus.monthly_summary') }}">finalisasi bulanan</a>.</span>
    </div>

    <div class="waiter-cards d-grid gap-4">
        @foreach($waiters as $waiter)
            @php
                $wid = $waiter['id'] ?? '';
                $score = $existingScores[$wid] ?? null;
                $isScored = !is_null($score);
                $auto = $autoScores[$wid] ?? ['discipline' => 0, 'operational' => 0, 'attitude' => 0, 'auto_details' => []];
                $details = $auto['auto_details'] ?? [];

                // Saved record stores per-category values inside 'categories' key. Fallback to flat keys for legacy records.
                $savedCategories = is_array($score['categories'] ?? null) ? $score['categories'] : [];
                $savedDiscipline  = (int) ($savedCategories['discipline']  ?? $score['discipline']  ?? 0);
                $savedOperational = (int) ($savedCategories['operational'] ?? $score['operational'] ?? 0);
                $savedAttitude    = (int) ($savedCategories['attitude']    ?? $score['attitude']    ?? 0);

                // Use saved values if already scored, otherwise use auto values
                $valDiscipline  = $isScored ? $savedDiscipline  : (int)($auto['discipline']  ?? 0);
                $valOperational = $isScored ? $savedOperational : (int)($auto['operational'] ?? 0);
                $valAttitude    = $isScored ? $savedAttitude    : (int)($auto['attitude']    ?? 0);

                $total = $valDiscipline + $valOperational + $valAttitude;
                $maxTotal = 20;
                $allFilled = $valDiscipline > 0 && $valOperational > 0 && $valAttitude > 0;
                $perfectDayBonus = $allFilled ? 5 : 0;
                $totalWithPerfect = $total + $perfectDayBonus;

                $cardBorder = '';
                if ($allFilled) {
                    $cardBorder = 'border-color: var(--color-success); border-width: 2px;';
                }
            @endphp
            <div class="card" style="{{ $cardBorder }}">
                <div class="card-header d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                    <div>
                        <h4 class="mb-1">{{ $waiter['name'] ?? '' }}</h4>
                        <span class="text-muted small">{{ $waiter['email'] ?? '' }}</span>
                    </div>
                    <div>
                        @if($allFilled)
                            <span class="badge badge-success px-3 py-2 rounded-pill">✨ Perfect Day</span>
                        @elseif($isScored)
                            <span class="badge badge-success px-3 py-2 rounded-pill">Tercatat ✅</span>
                        @else
                            <span class="badge" style="background: var(--color-text-muted); color: white; padding: 0.5rem 1rem; border-radius: 50rem;">Belum Ada Data</span>
                        @endif
                    </div>
                </div>

                <div class="scoring-grid">
                    {{-- DISIPLIN — auto --}}
                    <div class="score-row">
                        <div class="score-label">
                            <label class="fw-bold mb-0">Disiplin</label>
                            <span class="auto-badge">AUTO</span>
                        </div>
                        <div class="score-value-area">
                            <span class="score-display {{ $valDiscipline >= 4 ? 'score-good' : ($valDiscipline >= 2 ? 'score-mid' : 'score-low') }}">{{ $valDiscipline }}</span>
                            <span class="text-muted small">/5</span>
                            <span class="score-reason">✅ {{ $details['discipline_reason'] ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- OPERASIONAL — auto --}}
                    <div class="score-row">
                        <div class="score-label">
                            <label class="fw-bold mb-0">Operasional</label>
                            <span class="auto-badge">AUTO</span>
                        </div>
                        <div class="score-value-area">
                            <span class="score-display {{ $valOperational >= 8 ? 'score-good' : ($valOperational >= 5 ? 'score-mid' : 'score-low') }}">{{ $valOperational }}</span>
                            <span class="text-muted small">/10</span>
                            <span class="score-reason">📋 {{ $details['operational_reason'] ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- SIKAP — auto --}}
                    <div class="score-row">
                        <div class="score-label">
                            <label class="fw-bold mb-0">Sikap</label>
                            <span class="auto-badge">AUTO</span>
                        </div>
                        <div class="score-value-area">
                            <span class="score-display {{ $valAttitude >= 4 ? 'score-good' : 'score-low' }}">{{ $valAttitude }}</span>
                            <span class="text-muted small">/5</span>
                            <span class="score-reason">📝 {{ $details['attitude_reason'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                {{-- AUTO PENALTIES DETECTED --}}
                @php
                    $waiterPenalties = $autoPenalties[$wid] ?? [];
                @endphp
                @if(count($waiterPenalties) > 0)
                    <div class="mt-3 p-2 rounded" style="background: #fef2f2; border: 1px solid #fecaca;">
                        <span class="small fw-bold text-danger">⚠️ Penalti Otomatis Terdeteksi:</span>
                        <ul class="mb-0 mt-1 ps-3" style="font-size: 0.82rem;">
                            @foreach($waiterPenalties as $ap)
                                <li class="text-danger">{{ $ap['points_deducted'] ?? -5 }} poin — sudah dicatat otomatis</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top bg-light p-3 rounded">
                    <div class="total-display">
                        <span class="text-muted text-uppercase small fw-bold d-block">Total Harian</span>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="fs-4 fw-bold text-primary" style="font-size: 1.5rem;">{{ $totalWithPerfect }}</span>
                            <span class="text-muted">/ 25</span>
                            @if($allFilled)
                                <span class="badge badge-success ms-2">✨ +5 Perfect Day</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('styles')
<style>
    .page-header {
        margin-bottom: 1.5rem;
    }
    .d-flex { display: flex; }
    .justify-content-between { justify-content: space-between; }
    .align-items-center { align-items: center; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 1rem; }
    .gap-4 { gap: 1.5rem; }
    .d-grid { display: grid; }
    .text-center { text-align: center; }
    .fw-bold { font-weight: bold; }
    .small { font-size: 0.875rem; }
    .text-muted { color: var(--color-text-muted); }
    .text-uppercase { text-transform: uppercase; }
    .text-primary { color: var(--color-primary); }
    .mb-0 { margin-bottom: 0; }
    .mb-1 { margin-bottom: 0.25rem; }
    .mb-3 { margin-bottom: 1rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-3 { margin-top: 1rem; }
    .mt-4 { margin-top: 1.5rem; }
    .pt-3 { padding-top: 1rem; }
    .pb-3 { padding-bottom: 1rem; }
    .p-2 { padding: 0.5rem; }
    .p-3 { padding: 1rem; }
    .px-3 { padding-left: 1rem; padding-right: 1rem; }
    .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    .ms-2 { margin-left: 0.5rem; }
    .border { border: 1px solid var(--color-border); }
    .border-0 { border: 0 !important; }
    .border-bottom { border-bottom: 1px solid var(--color-border); }
    .border-top { border-top: 1px solid var(--color-border); }
    .rounded { border-radius: var(--radius-md); }
    .rounded-pill { border-radius: 50rem; }
    .shadow-sm { box-shadow: var(--shadow-sm); }
    .bg-white { background-color: #fff; }
    .bg-light { background-color: var(--color-bg); }
    .d-block { display: block; }

    .form-control {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 1rem;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: var(--radius-sm);
    }

    .btn-light {
        background-color: #f8f9fa;
        color: #212529;
    }
    .btn-light:hover {
        background-color: #e2e6ea;
    }

    /* Scoring grid */
    .score-row {
        display: grid;
        grid-template-columns: 160px 1fr;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px dashed var(--color-border);
    }
    .score-row:last-child {
        border-bottom: none;
    }

    .score-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .auto-badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        background: #e0f2fe;
        color: #0369a1;
    }

    .score-value-area {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .score-display {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .score-good {
        background: #dcfce7;
        color: #166534;
    }
    .score-mid {
        background: #fef9c3;
        color: #854d0e;
    }
    .score-low {
        background: #fee2e2;
        color: #991b1b;
    }

    .score-reason {
        font-size: 0.8rem;
        color: var(--color-text-muted);
        margin-left: 0.25rem;
    }

    .info-banner a {
        color: #1d4ed8;
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .score-row {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
        .date-navigation {
            width: 100%;
            justify-content: center;
        }
        .score-value-area {
            padding-left: 0;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date picker navigation
    const datePicker = document.getElementById('score_date');
    if (datePicker) {
        datePicker.addEventListener('change', function() {
            window.location.href = '?date=' + this.value;
        });
    }
});
</script>
@endpush
