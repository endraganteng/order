@php
    $lazyLoad = $lazyLoad ?? false;
@endphp

<div class="bonus-summary-mini" id="bonusSummaryMini" data-lazy="{{ $lazyLoad ? '1' : '0' }}">
    @if(!$lazyLoad)
        <div class="bsm-header">
            <h3 class="bsm-title">🏆 Bonus Bulan Ini</h3>
            <a href="{{ route('waiter.bonus') }}" class="bsm-link">Lihat detail →</a>
        </div>
        <div class="bsm-grid">
            <div class="bsm-stat">
                <div class="bsm-stat-value {{ $tierColor ?? '' }}">{{ $netPoints ?? 0 }} <span class="bsm-stat-sub">/ {{ $theoreticalMax ?? 0 }}</span></div>
                <div class="bsm-stat-label">Total Poin</div>
            </div>
            <div class="bsm-stat">
                <div class="bsm-stat-value">{{ $percentage ?? 0 }}%</div>
                <div class="bsm-stat-label">{{ $tierLabel ?? '-' }}</div>
            </div>
            <div class="bsm-stat">
                <div class="bsm-stat-value">Rp {{ number_format($projectedAmount ?? 0, 0, ',', '.') }}</div>
                <div class="bsm-stat-label">Proyeksi Bonus</div>
            </div>
        </div>
    @else
        {{-- Skeleton state, populated by JS via AJAX --}}
        <div class="bsm-header">
            <h3 class="bsm-title">🏆 Bonus Bulan Ini</h3>
            <a href="{{ route('waiter.bonus') }}" class="bsm-link">Lihat detail →</a>
        </div>
        <div class="bsm-grid">
            <div class="bsm-stat"><div class="bsm-stat-value bsm-skeleton">···</div><div class="bsm-stat-label">Total Poin</div></div>
            <div class="bsm-stat"><div class="bsm-stat-value bsm-skeleton">···</div><div class="bsm-stat-label">Tier</div></div>
            <div class="bsm-stat"><div class="bsm-stat-value bsm-skeleton">···</div><div class="bsm-stat-label">Proyeksi</div></div>
        </div>
    @endif
</div>

<style>
    .bonus-summary-mini { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 16px; }
    .bsm-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .bsm-title { font-size: 15px; font-weight: 600; color: #1f2937; margin: 0; }
    .bsm-link { font-size: 13px; color: #2563eb; text-decoration: none; }
    .bsm-link:hover { text-decoration: underline; }
    .bsm-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .bsm-stat { padding: 8px; background: #f8fafc; border-radius: 8px; text-align: center; }
    .bsm-stat-value { font-size: 16px; font-weight: 700; color: #1f2937; }
    .bsm-stat-sub { font-size: 11px; color: #64748b; font-weight: 400; }
    .bsm-stat-label { font-size: 11px; color: #64748b; margin-top: 2px; }
    .color-green { color: #059669; }
    .color-orange { color: #d97706; }
    .color-red { color: #dc2626; }
    .bsm-skeleton { background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: bsm-skel 1.5s ease-in-out infinite; color: transparent; border-radius: 4px; }
    @keyframes bsm-skel { 0%{background-position:200% 0;} 100%{background-position:-200% 0;} }
    @media (max-width: 480px) {
        .bsm-grid { grid-template-columns: 1fr 1fr; }
        .bsm-grid .bsm-stat:last-child { grid-column: 1 / -1; }
    }
</style>
