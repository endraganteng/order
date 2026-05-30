<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Dashboard - {{ $waiterName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #333;
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        .header-title {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .header-back {
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            transition: background 0.2s;
        }
        .header-back:hover { background: rgba(255,255,255,0.25); }
        .header-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .header-name {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .month-picker {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .month-picker::-webkit-calendar-picker-indicator { filter: invert(1); }

        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }

        .progress-ring-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
            background: white;
            border-radius: 16px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .progress-ring-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin-bottom: 1rem;
        }
        .progress-ring-svg {
            transform: rotate(-90deg);
            width: 180px;
            height: 180px;
        }
        .progress-ring-bg {
            fill: none;
            stroke: #e8ecf0;
            stroke-width: 12;
        }
        .progress-ring-fill {
            fill: none;
            stroke-width: 12;
            stroke-linecap: round;
            transition: stroke-dashoffset 1.5s ease-in-out, stroke 0.3s;
        }
        .progress-ring-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .progress-ring-percent {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }
        .progress-ring-label {
            font-size: 0.8rem;
            color: #888;
            margin-top: 0.25rem;
        }
        .progress-tier {
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            margin-top: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-sub {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 0.15rem;
        }
        .stat-card.penalty .stat-value { color: #e53e3e; }
        .stat-card.perfect .stat-value { color: #38a169; }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card-title {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #444;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .projected-bonus {
            text-align: center;
            padding: 1.5rem;
        }
        .projected-amount {
            font-size: 2rem;
            font-weight: 800;
            margin: 0.5rem 0;
        }
        .projected-tier-label {
            font-size: 0.85rem;
            color: #666;
        }
        .projected-note {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.5rem;
        }
        .projection-next-tier {
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
            line-height: 1.4;
        }

        .eval-status-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .eval-status-card {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0;
            padding: 1rem;
            border-left: 4px solid #64748b;
        }
        .eval-status-card.success { border-left-color: #059669; }
        .eval-status-card.pending { border-left-color: #f59e0b; }
        .eval-status-card.neutral { border-left-color: #64748b; }
        .eval-status-icon {
            font-size: 1.25rem;
            line-height: 1;
        }
        .eval-status-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #444;
            margin-bottom: 0.25rem;
        }
        .eval-status-text {
            font-size: 0.82rem;
            line-height: 1.35;
        }
        .eval-status-card.success .eval-status-text { color: #059669; }
        .eval-status-card.pending .eval-status-text { color: #f59e0b; }
        .eval-status-card.neutral .eval-status-text { color: #64748b; }

        .explain-block {
            margin-bottom: 1rem;
            padding: 0.85rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
        }
        .explain-block:last-child { margin-bottom: 0; }
        .explain-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 0.4rem;
        }
        .explain-list {
            list-style: none;
            display: grid;
            gap: 0.45rem;
            font-size: 0.8rem;
            color: #4a5568;
        }
        .explain-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.75rem;
            line-height: 1.45;
        }
        .explain-line strong {
            white-space: nowrap;
            color: #2d3748;
        }

        .bonus-tier-wrapper { display: grid; gap: 0.85rem; }
        .tier-box {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .tier-box-title {
            padding: 0.65rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
            background: #f7fafc;
            color: #4a5568;
            border-bottom: 1px solid #edf2f7;
        }
        .tier-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.8rem;
            font-size: 0.82rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .tier-row:last-child { border-bottom: none; }
        .tier-row.active {
            border-left: 3px solid #38a169;
            font-weight: 700;
        }
        .tier-range {
            color: #4a5568;
            white-space: nowrap;
        }
        .tier-amount {
            font-weight: 700;
            color: #2d3748;
            text-align: right;
            margin-left: auto;
        }
        .tier-badge {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            padding: 0.2rem 0.45rem;
            border-radius: 999px;
        }
        .total-max-note {
            font-size: 0.82rem;
            font-weight: 700;
            text-align: center;
            color: #2d3748;
            padding-top: 0.25rem;
        }

        .daily-points-note {
            list-style: none;
            display: grid;
            gap: 0.55rem;
            font-size: 0.8rem;
            color: #4a5568;
            line-height: 1.45;
        }
        .daily-points-note strong { color: #2d3748; }

        @media (max-width: 420px) {
            .tier-row {
                flex-wrap: wrap;
                gap: 0.35rem 0.65rem;
            }
            .tier-amount {
                margin-left: 0;
            }
        }

        .category-bar-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            gap: 0.5rem;
        }
        .category-bar-label {
            width: 80px;
            font-size: 0.75rem;
            color: #666;
            text-transform: capitalize;
            flex-shrink: 0;
        }
        .category-bar-track {
            flex: 1;
            height: 8px;
            background: #e8ecf0;
            border-radius: 4px;
            overflow: hidden;
        }
        .category-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease;
        }
        .category-bar-value {
            width: 35px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: right;
            flex-shrink: 0;
        }

        .daily-list { max-height: 400px; overflow-y: auto; }
        .daily-item { border-bottom: 1px solid #f0f0f0; }
        .daily-item:last-child { border-bottom: none; }
        .daily-item-header {
            background: none;
            border: 0;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            color: inherit;
            font: inherit;
            text-align: left;
        }
        .daily-date {
            font-size: 0.8rem;
            color: #666;
            width: 70px;
            flex-shrink: 0;
        }
        .daily-categories {
            display: flex;
            gap: 3px;
            flex: 1;
            margin: 0 0.5rem;
        }
        .daily-cat-dot {
            width: 6px;
            height: 20px;
            border-radius: 3px;
            flex: 1;
            max-width: 30px;
        }
        .daily-total {
            font-weight: 700;
            font-size: 0.9rem;
            width: 40px;
            text-align: right;
        }
        .daily-expand-icon {
            width: 18px;
            text-align: right;
            color: #888;
            font-size: 0.85rem;
        }
        .daily-detail {
            padding: 8px 12px;
            background: #f8fafc;
            font-size: 13px;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }
        .daily-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.5rem;
            padding: 4px 0;
        }
        .ddr-label { font-weight: 600; color: #4a5568; }
        .ddr-points { font-weight: 700; color: #2d3748; white-space: nowrap; }
        .ddr-reason {
            color: #64748b;
            flex: 1;
            text-align: right;
        }
        .penalty-row .ddr-label,
        .penalty-row .ddr-points { color: #e53e3e; }

        .penalty-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .penalty-item:last-child { border-bottom: none; }
        .penalty-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        .penalty-type {
            font-size: 0.8rem;
            font-weight: 600;
            color: #e53e3e;
        }
        .penalty-points {
            font-size: 0.85rem;
            font-weight: 700;
            color: #e53e3e;
        }
        .penalty-reason {
            font-size: 0.75rem;
            color: #888;
        }
        .penalty-date {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 0.15rem;
        }

        /* === Riwayat Poin Masuk timeline === */
        .events-list {
            max-height: 420px;
            overflow-y: auto;
            margin: 0 -0.25rem;
        }
        .event-item {
            display: grid;
            grid-template-columns: 36px 1fr auto;
            gap: 0.65rem;
            padding: 0.65rem 0.25rem;
            border-bottom: 1px solid #f3f4f6;
            align-items: flex-start;
        }
        .event-item:last-child { border-bottom: none; }
        .event-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .event-icon.rack    { background: #ecfdf5; color: #059669; }
        .event-icon.bonus   { background: #eff6ff; color: #2563eb; }
        .event-icon.penalty { background: #fef2f2; color: #dc2626; }
        .event-icon.deduction { background: #fef3c7; color: #d97706; }
        .event-body {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            min-width: 0;
        }
        .event-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #1f2937;
        }
        .event-reason {
            font-size: 0.74rem;
            color: #6b7280;
            word-break: break-word;
        }
        .event-meta {
            font-size: 0.68rem;
            color: #9ca3af;
            margin-top: 0.1rem;
        }
        .event-points {
            font-size: 0.95rem;
            font-weight: 700;
            white-space: nowrap;
            align-self: center;
        }
        .event-points.positive { color: #059669; }
        .event-points.negative { color: #dc2626; }
        .event-empty {
            text-align: center;
            padding: 1.25rem 0.5rem;
            color: #9ca3af;
            font-size: 0.78rem;
        }

        .sales-progress-bar {
            width: 100%;
            height: 12px;
            background: #e8ecf0;
            border-radius: 6px;
            overflow: hidden;
            margin: 0.75rem 0;
        }
        .sales-progress-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 1s ease;
        }
        .sales-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.4rem;
            gap: 0.75rem;
            transition: background 0.2s;
        }
        .leaderboard-item.is-me {
            background: linear-gradient(135deg, rgba(102,126,234,0.08), rgba(118,75,162,0.08));
            border: 1px solid rgba(102,126,234,0.2);
        }
        .leaderboard-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f0f0f0;
            color: #666;
            flex-shrink: 0;
        }
        .leaderboard-rank.gold { background: #fef3c7; color: #d97706; }
        .leaderboard-rank.silver { background: #e5e7eb; color: #6b7280; }
        .leaderboard-rank.bronze { background: #fed7aa; color: #c2410c; }
        .leaderboard-name {
            flex: 1;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .leaderboard-points {
            font-size: 0.85rem;
            font-weight: 700;
            color: #667eea;
        }

        .finalized-banner {
            background: linear-gradient(135deg, #38a169, #2f855a);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(56,161,105,0.3);
        }
        .finalized-label { font-size: 0.85rem; opacity: 0.9; margin-bottom: 0.25rem; }
        .finalized-amount { font-size: 2.5rem; font-weight: 800; }
        .finalized-status { font-size: 0.75rem; opacity: 0.8; margin-top: 0.5rem; }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #aaa;
            font-size: 0.85rem;
        }

        @keyframes ring-fill {
            from { stroke-dashoffset: 440; }
        }

        .color-green { color: #38a169; }
        .color-yellow { color: #d69e2e; }
        .color-orange { color: #dd6b20; }
        .color-red { color: #e53e3e; }
        .bg-green { background: #f0fff4; }
        .bg-yellow { background: #fffff0; }
        .bg-orange { background: #fffaf0; }
        .bg-red { background: #fff5f5; }

    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="header-title">🏆 Bonus Dashboard</div>
            <a href="{{ route('waiter.tasks') }}" class="header-back">← Kembali</a>
        </div>
        <div class="header-meta">
            <span class="header-name">{{ $waiterName }}</span>
            <input type="month" class="month-picker" id="monthPicker" value="{{ $month }}">
        </div>
    </div>

    <div class="container">
        {{-- Quick Link: Bonus Produk --}}
        <a href="{{ route('waiter.bonus_produk') }}" style="display:block; background:linear-gradient(135deg, #667eea, #764ba2); color:white; border-radius:10px; padding:12px 16px; margin-bottom:1rem; text-decoration:none; font-weight:600; font-size:0.9rem; box-shadow:0 2px 8px rgba(102,126,234,0.3);">
            🎯 Bonus Produk — Klaim bonus penjualan →
        </a>

        @if(!empty($bonusSummary) && ($bonusSummary['status'] ?? '') === 'finalized')
        <div class="finalized-banner">
            <div class="finalized-label">Bonus Final Bulan Ini</div>
            <div class="finalized-amount">Rp {{ number_format($bonusSummary['bonus_amount'] ?? 0, 0, ',', '.') }}</div>
            <div class="finalized-status">✓ Sudah Difinalisasi</div>
        </div>
        @endif

        @php
            $ringColor = '#38a169';
            $tierLabel = 'Excellent';
            $tierBg = 'bg-green';
            $tierColor = 'color-green';
            if ($percentage >= 80) {
                $ringColor = '#38a169';
                $tierLabel = 'Excellent';
                $tierBg = 'bg-green';
                $tierColor = 'color-green';
            } elseif ($percentage >= 70) {
                $ringColor = '#d69e2e';
                $tierLabel = 'Good';
                $tierBg = 'bg-yellow';
                $tierColor = 'color-yellow';
            } elseif ($percentage >= 60) {
                $ringColor = '#dd6b20';
                $tierLabel = 'Average';
                $tierBg = 'bg-orange';
                $tierColor = 'color-orange';
            } else {
                $ringColor = '#e53e3e';
                $tierLabel = 'Needs Improvement';
                $tierBg = 'bg-red';
                $tierColor = 'color-red';
            }
            $circumference = 2 * 3.14159 * 70;
            $dashoffset = $circumference - ($circumference * min($percentage, 100) / 100);
        @endphp

        <div class="progress-ring-wrapper">
            <div class="progress-ring-container">
                <svg class="progress-ring-svg" viewBox="0 0 180 180">
                    <circle class="progress-ring-bg" cx="90" cy="90" r="70"></circle>
                    <circle class="progress-ring-fill" cx="90" cy="90" r="70"
                        stroke="{{ $ringColor }}"
                        stroke-dasharray="{{ $circumference }}"
                        stroke-dashoffset="{{ $dashoffset }}"
                        style="animation: ring-fill 1.5s ease-in-out;"></circle>
                </svg>
                <div class="progress-ring-text">
                    <div class="progress-ring-percent {{ $tierColor }}">{{ $percentage }}%</div>
                    <div class="progress-ring-label">dari maksimum</div>
                </div>
            </div>
            <span class="progress-tier {{ $tierBg }} {{ $tierColor }}">{{ $tierLabel }}</span>
        </div>

        @php
            $currentMonth = date('Y-m');
            $resolvedWaiterRole = $waiterRole ?? '';
            $salesTargetRoles = $config['sales_target_roles'] ?? [];
            // Default eligible kalau role kosong (unknown) supaya tidak salah-tampil "N/A".
            $isSalesEligible = empty($salesTargetRoles)
                || $resolvedWaiterRole === ''
                || in_array($resolvedWaiterRole, $salesTargetRoles, true);

            $serviceEvaluated = false;
            $salesEvaluated = false;
            $servicePoints = 0;
            $salesPoints = 0;
            $serviceMax = (int)($config['point_categories']['service']['max_daily_points'] ?? 5);
            $salesMax = (int)($config['point_categories']['sales']['max_daily_points'] ?? 5);

            foreach ($monthlyPoints as $date => $record) {
                if (strpos((string)$date, $currentMonth) !== 0) {
                    continue;
                }

                $serviceData = $record['categories']['service'] ?? [];
                $salesData = $record['categories']['sales'] ?? [];
                $autoDetails = $record['auto_details'] ?? [];
                $dayServicePoints = (int)($serviceData['points'] ?? $record['service'] ?? 0);
                $daySalesPoints = (int)($salesData['points'] ?? $record['sales'] ?? 0);

                $servicePoints = max($servicePoints, $dayServicePoints);
                $salesPoints = max($salesPoints, $daySalesPoints);
                $serviceEvaluated = $serviceEvaluated
                    || $dayServicePoints > 0
                    || !empty($serviceData['evaluated_at'])
                    || (($autoDetails['service_evaluated'] ?? false) === true);
                $salesEvaluated = $salesEvaluated
                    || $daySalesPoints > 0
                    || !empty($salesData['evaluated_at'])
                    || (($autoDetails['sales_evaluated'] ?? false) === true)
                    || (($autoDetails['sales_manual_evaluated'] ?? false) === true);
            }
        @endphp

        <div class="eval-status-row">
            <div class="card eval-status-card {{ $serviceEvaluated ? 'success' : 'pending' }}">
                <span class="eval-status-icon">🤝</span>
                <div>
                    <div class="eval-status-label">Pelayanan</div>
                    <div class="eval-status-text">{{ $serviceEvaluated ? ('Sudah dinilai: ' . $servicePoints . '/' . $serviceMax . ' poin') : 'Belum dinilai bulan ini' }}</div>
                </div>
            </div>
            <div class="card eval-status-card {{ !$isSalesEligible ? 'neutral' : ($salesEvaluated ? 'success' : 'pending') }}">
                <span class="eval-status-icon">🎯</span>
                <div>
                    <div class="eval-status-label">Penjualan</div>
                    <div class="eval-status-text">
                        @if(!$isSalesEligible)
                            N/A — bukan role spesialis
                        @else
                            {{ $salesEvaluated ? ('Sudah dinilai: ' . $salesPoints . '/' . $salesMax . ' poin') : 'Belum dinilai bulan ini' }}
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value {{ $tierColor }}">{{ $netPoints }}</div>
                <div class="stat-label">Total Poin</div>
                <div class="stat-sub">/ {{ $theoreticalMax }} maks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $daysScored }}</div>
                <div class="stat-label">Hari Dinilai</div>
                <div class="stat-sub">/ {{ $workingDays }} hari kerja</div>
            </div>
            <div class="stat-card perfect">
                <div class="stat-value">{{ $perfectDays }} ✨</div>
                <div class="stat-label">Perfect Days</div>
                <div class="stat-sub">skor sempurna</div>
            </div>
            <div class="stat-card penalty">
                <div class="stat-value">-{{ $totalPenalties }}</div>
                <div class="stat-label">Penalti</div>
                <div class="stat-sub">poin dikurangi</div>
            </div>
        </div>

        @php
            $pointsTiersRaw = $config['point_bonus_tiers'] ?? [
                'tier_1' => ['min_percentage' => 80, 'bonus_amount' => 300000],
                'tier_2' => ['min_percentage' => 70, 'bonus_amount' => 250000],
                'tier_3' => ['min_percentage' => 60, 'bonus_amount' => 200000],
                'tier_4' => ['min_percentage' => 0, 'bonus_amount' => 0],
            ];
            $salesTiersRaw = $config['sales_bonus_tiers'] ?? [
                'tier_1' => ['min_percentage' => 100, 'bonus_amount' => 200000],
                'tier_2' => ['min_percentage' => 80, 'bonus_amount' => 150000],
                'tier_3' => ['min_percentage' => 60, 'bonus_amount' => 100000],
                'tier_4' => ['min_percentage' => 0, 'bonus_amount' => 0],
            ];

            $pointsTiers = collect($pointsTiersRaw)
                ->map(fn($tier) => [
                    'min_percentage' => (int)($tier['min_percentage'] ?? 0),
                    'bonus_amount' => (int)($tier['bonus_amount'] ?? 0),
                ])
                ->sortByDesc('min_percentage')
                ->values()
                ->all();

            $salesTiers = collect($salesTiersRaw)
                ->map(fn($tier) => [
                    'min_percentage' => (int)($tier['min_percentage'] ?? 0),
                    'bonus_amount' => (int)($tier['bonus_amount'] ?? 0),
                ])
                ->sortByDesc('min_percentage')
                ->values()
                ->all();

            $resolveTier = function (array $tiers, float $value) {
                foreach ($tiers as $idx => $tier) {
                    if ($value >= (float)($tier['min_percentage'] ?? 0)) {
                        return [
                            'index' => $idx,
                            'tier' => $tier,
                            'amount' => (int)($tier['bonus_amount'] ?? 0),
                        ];
                    }
                }

                return [
                    'index' => null,
                    'tier' => null,
                    'amount' => 0,
                ];
            };

            $salesAchievedProjection = (int)($salesTarget['achieved'] ?? 0);
            $salesGoalProjection = (int)($salesTarget['target'] ?? 0);
            $salesPercentageProjection = $salesGoalProjection > 0
                ? round(($salesAchievedProjection / $salesGoalProjection) * 100)
                : 0;

            $pointsProjection = $resolveTier($pointsTiers, $percentage);
            $salesProjection = $resolveTier($salesTiers, $salesPercentageProjection);

            $pointsBonusProjection = $pointsProjection['amount'];
            $salesBonusProjection = $salesProjection['amount'];
            $projectedAmount = $pointsBonusProjection + $salesBonusProjection;

            $buildTierRows = function (array $tiers) {
                $rows = [];

                foreach ($tiers as $idx => $tier) {
                    $min = (int)($tier['min_percentage'] ?? 0);

                    if ($idx === 0) {
                        $label = '≥' . $min . '%';
                    } else {
                        $prevMin = (int)($tiers[$idx - 1]['min_percentage'] ?? 0);
                        $upper = max($prevMin - 1, $min);
                        $label = $min > 0 ? ($min . '-' . $upper . '%') : ('<' . $prevMin . '%');
                    }

                    $rows[] = [
                        'label' => $label,
                        'bonus_amount' => (int)($tier['bonus_amount'] ?? 0),
                    ];
                }

                return $rows;
            };

            $pointsTierRows = $buildTierRows($pointsTiers);
            $salesTierRows = $buildTierRows($salesTiers);

            $maxPointsBonus = (int)collect($pointsTiers)->max('bonus_amount');
            $maxSalesBonus = (int)collect($salesTiers)->max('bonus_amount');

            $nextPointsTier = null;
            foreach (array_reverse($pointsTiers) as $tier) {
                if ((float)($tier['min_percentage'] ?? 0) > $percentage) {
                    $nextPointsTier = $tier;
                    break;
                }
            }
            $pointsNeeded = $nextPointsTier !== null
                ? (int)ceil((((float)$nextPointsTier['min_percentage'] - $percentage) / 100) * $theoreticalMax)
                : 0;

            $nextSalesTier = null;
            if (!empty($salesTarget)) {
                foreach (array_reverse($salesTiers) as $tier) {
                    if ((float)($tier['min_percentage'] ?? 0) > $salesPercentageProjection) {
                        $nextSalesTier = $tier;
                        break;
                    }
                }
            }
            $salesNeeded = ($nextSalesTier !== null && $salesGoalProjection > 0)
                ? (int)ceil((((float)$nextSalesTier['min_percentage'] - $salesPercentageProjection) / 100) * $salesGoalProjection)
                : 0;
        @endphp

        <div class="card projected-bonus">
            <div class="card-title">💰 Proyeksi Bonus</div>
            <div class="projected-tier-label">Poin tugas: {{ $percentage }}% • Target penjualan: {{ $salesPercentageProjection }}%</div>
            <div class="projected-amount {{ $projectedAmount > 0 ? 'color-green' : 'color-red' }}">Rp {{ number_format($projectedAmount, 0, ',', '.') }}</div>
            <div class="projected-note">Rp {{ number_format($pointsBonusProjection, 0, ',', '.') }} (poin) + Rp {{ number_format($salesBonusProjection, 0, ',', '.') }} (penjualan)</div>
            @php
                // FIX #3: Next tier projection
                $nextPointsTier = null;
                foreach (array_reverse($pointsTiers) as $tier) {
                    if ((int)($tier['min_percentage'] ?? 0) > (int)$percentage) {
                        $nextPointsTier = $tier;
                        break;
                    }
                }
                $pointsNeeded = $nextPointsTier
                    ? (int)ceil(((int)($nextPointsTier['min_percentage'] ?? 0) - (int)$percentage) / 100 * $theoreticalMax)
                    : 0;
                $nextSalesTier = null;
                $salesNeeded = 0;
                if (!empty($salesTarget)) {
                    foreach (array_reverse($salesTiers) as $tier) {
                        if ((int)($tier['min_percentage'] ?? 0) > (int)$salesPercentageProjection) {
                            $nextSalesTier = $tier;
                            break;
                        }
                    }
                    if ($nextSalesTier) {
                        $salesGoalForNextTier = (int)($salesGoalProjection * ((int)($nextSalesTier['min_percentage'] ?? 0) / 100));
                        $salesNeeded = max(0, $salesGoalForNextTier - $salesAchievedProjection);
                    }
                }
            @endphp
            @if($nextPointsTier !== null)
                <div class="projection-next-tier">⬆️ +{{ $pointsNeeded }} poin lagi untuk tier Rp {{ number_format((int)$nextPointsTier['bonus_amount'], 0, ',', '.') }}</div>
            @endif
            @if($nextSalesTier !== null)
                <div class="projection-next-tier">🎯 +Rp {{ number_format($salesNeeded, 0, ',', '.') }} penjualan lagi untuk tier Rp {{ number_format((int)$nextSalesTier['bonus_amount'], 0, ',', '.') }}</div>
            @endif
        </div>

        @php
            $activePointCategories = collect($config['point_categories'] ?? [])->filter(fn($meta) => ($meta['is_active'] ?? true) !== false);
            $dailyConfiguredMax = (int)$activePointCategories
                ->filter(fn($meta) => ($meta['scoring_type'] ?? 'daily') === 'daily')
                ->sum(fn($meta) => (int)($meta['max_daily_points'] ?? $meta['max_monthly_points'] ?? 0));
            $monthlyConfiguredMax = (int)$activePointCategories
                ->filter(fn($meta) => ($meta['scoring_type'] ?? 'daily') === 'monthly')
                ->sum(fn($meta) => (int)($meta['max_monthly_points'] ?? $meta['max_daily_points'] ?? 0));
            $perfectDayBonus = (int)($config['perfect_day_bonus'] ?? 5);
        @endphp

        <div class="card">
            <div class="card-title">💡 Cara Kerja Poin</div>
            <div class="explain-block">
                <ul class="explain-list">
                    <li class="explain-line"><strong>Poin harian max</strong><span>{{ $dailyMaxWithPerfect }} poin/hari ({{ $dailyConfiguredMax }} kategori harian + {{ $perfectDayBonus }} perfect day bonus)</span></li>
                    <li class="explain-line"><strong>Poin bulanan max</strong><span>{{ $theoreticalMax }} poin ({{ $dailyMaxWithPerfect * $workingDays }} harian + {{ $monthlyConfiguredMax }} bulanan)</span></li>
                    <li class="explain-line"><strong>Poin kamu saat ini</strong><span>{{ $netPoints }} poin ({{ $percentage }}%)</span></li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-title">💰 Konversi Poin ke Bonus</div>
            <div class="bonus-tier-wrapper">
                <div class="tier-box">
                    <div class="tier-box-title">📋 Bonus dari Poin Tugas (max Rp {{ number_format($maxPointsBonus, 0, ',', '.') }})</div>
                    @foreach($pointsTierRows as $idx => $row)
                        @php $isActive = $pointsProjection['index'] === $idx; @endphp
                        <div class="tier-row {{ $isActive ? 'active bg-green color-green' : '' }}">
                            <span class="tier-range">{{ $row['label'] }}</span>
                            <span class="tier-amount">Rp {{ number_format($row['bonus_amount'], 0, ',', '.') }}</span>
                            @if($isActive)
                                <span class="tier-badge bg-green color-green">KAMU DISINI</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="tier-box">
                    <div class="tier-box-title">🎯 Bonus dari Target Penjualan (max Rp {{ number_format($maxSalesBonus, 0, ',', '.') }})</div>
                    @foreach($salesTierRows as $idx => $row)
                        @php $isActive = $salesProjection['index'] === $idx; @endphp
                        <div class="tier-row {{ $isActive ? 'active bg-green color-green' : '' }}">
                            <span class="tier-range">{{ $row['label'] }}</span>
                            <span class="tier-amount">Rp {{ number_format($row['bonus_amount'], 0, ',', '.') }}</span>
                            @if($isActive)
                                <span class="tier-badge bg-green color-green">KAMU DISINI</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="total-max-note">Total Maksimal: Rp {{ number_format($maxPointsBonus + $maxSalesBonus, 0, ',', '.') }}/bulan</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">📊 Poin Harian Kamu</div>
            <ul class="daily-points-note">
                @php
                    $fallbackReasons = [
                        'discipline'  => fn($max) => "Dari absensi - tepat waktu = {$max} poin",
                        'operational' => fn($max) => "Dari tugas - semua selesai = {$max} poin (default poin penuh kalau tidak ada tugas)",
                        'service'     => fn($max) => "Penilaian supervisor (bulanan)",
                        'sales'       => fn($max) => "Default 0, ditambah supervisor jika aktif menjual/upselling (bulanan)",
                        'attitude'    => fn($max) => "Submit laporan kegiatan = {$max} poin",
                    ];
                @endphp
                @foreach(($config['point_categories'] ?? []) as $key => $meta)
                    @if(($meta['is_active'] ?? true) === false)
                        @continue
                    @endif
                    @php
                        $catLabel = $meta['label'] ?? ucfirst($key);
                        $scoringType = $meta['scoring_type'] ?? 'daily';
                        $maxPoints = $scoringType === 'monthly'
                            ? (int)($meta['max_monthly_points'] ?? $meta['max_daily_points'] ?? 5)
                            : (int)($meta['max_daily_points'] ?? 5);
                        $reason = $meta['description'] ?? (isset($fallbackReasons[$key]) ? $fallbackReasons[$key]($maxPoints) : "Penilaian {$catLabel}");
                    @endphp
                    <li><strong>{{ $catLabel }} (max {{ $maxPoints }}):</strong> {{ $reason }}</li>
                @endforeach
                <li><strong>Perfect Day (+{{ $perfectDayBonus }}):</strong> Bonus jika semua kategori &gt; 0</li>
            </ul>
        </div>

        @php
            $categories = ['discipline' => 'Disiplin', 'operational' => 'Operasional', 'service' => 'Service', 'sales' => 'Sales', 'attitude' => 'Attitude'];
            $categoryTotals = [];
            $categoryMaxes = [];
            $cats = $config['point_categories'] ?? [];
            foreach ($categories as $key => $label) {
                $categoryTotals[$key] = 0;
                $categoryMaxes[$key] = (int)($cats[$key]['max_daily_points'] ?? 5);
            }
            $dayCount = max(count($monthlyPoints), 1);
            foreach ($monthlyPoints as $record) {
                foreach ($categories as $key => $label) {
                    // BonusService menyimpan categories sebagai {key: int}, bukan {key: {points: int}}.
                    // Fallback ke struktur lama (object dengan .points) untuk legacy record.
                    $catVal = $record['categories'][$key] ?? null;
                    if (is_array($catVal)) {
                        $catVal = (int)($catVal['points'] ?? 0);
                    } else {
                        $catVal = (int)($catVal ?? $record[$key] ?? 0);
                    }
                    $categoryTotals[$key] += $catVal;
                }
            }
            $categoryColors = [
                'discipline' => '#667eea',
                'operational' => '#764ba2',
                'service' => '#38a169',
                'sales' => '#d69e2e',
                'attitude' => '#e53e3e',
            ];
        @endphp

        <div class="card">
            <div class="card-title">📊 Rata-rata per Kategori</div>
            @foreach($categories as $key => $label)
                @php
                    $avg = $dayCount > 0 ? round($categoryTotals[$key] / $dayCount, 1) : 0;
                    $maxCat = $categoryMaxes[$key];
                    $catPercent = $maxCat > 0 ? min(100, round(($avg / $maxCat) * 100)) : 0;
                @endphp
                <div class="category-bar-row">
                    <span class="category-bar-label">{{ $label }}</span>
                    <div class="category-bar-track">
                        <div class="category-bar-fill" style="width: {{ $catPercent }}%; background: {{ $categoryColors[$key] ?? '#667eea' }};"></div>
                    </div>
                    <span class="category-bar-value">{{ $avg }}/{{ $maxCat }}</span>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-title">📅 Riwayat Harian</div>
            <div class="daily-list">
                @php
                    $sortedDays = collect($monthlyPoints)->sortKeysDesc()->take(10);
                @endphp
                @forelse($sortedDays as $date => $record)
                    <div class="daily-item">
                        <button class="daily-item-header" onclick="toggleDailyDetail('{{ $date }}')" type="button">
                            <span class="daily-date">{{ \Carbon\Carbon::parse($date)->format('d M') }}</span>
                            <div class="daily-categories">
                                @foreach($categories as $key => $label)
                                    @php
                                        $catVal = $record['categories'][$key] ?? null;
                                        if (is_array($catVal)) {
                                            $catPts = (int)($catVal['points'] ?? 0);
                                        } else {
                                            $catPts = (int)($catVal ?? $record[$key] ?? 0);
                                        }
                                        $catMax = $categoryMaxes[$key];
                                        $opacity = $catMax > 0 ? max(0.2, $catPts / $catMax) : 0.2;
                                    @endphp
                                    <div class="daily-cat-dot" style="background: {{ $categoryColors[$key] ?? '#667eea' }}; opacity: {{ $opacity }};"></div>
                                @endforeach
                            </div>
                            <span class="daily-total">{{ $record['daily_total'] ?? 0 }}</span>
                            <span class="daily-expand-icon" id="exp-{{ $date }}">▾</span>
                        </button>
                        <div class="daily-detail" id="det-{{ $date }}" style="display:none;">
                            @foreach($categories as $key => $label)
                                @php
                                    $catVal = $record['categories'][$key] ?? null;
                                    if (is_array($catVal)) {
                                        $catPts = (int)($catVal['points'] ?? 0);
                                        $reasonNested = (string)($catVal['reason'] ?? '');
                                    } else {
                                        $catPts = (int)($catVal ?? $record[$key] ?? 0);
                                        $reasonNested = '';
                                    }
                                    $catMax = $categoryMaxes[$key];
                                    $reason = $record['auto_details'][$key.'_reason'] ?? $reasonNested;
                                @endphp
                                <div class="daily-detail-row">
                                    <span class="ddr-label">{{ $label }}</span>
                                    <span class="ddr-points">{{ $catPts }}/{{ $catMax }}</span>
                                    @if($reason)
                                        <span class="ddr-reason">{{ $reason }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if(!empty($record['penalties']) || !empty($record['penalty_total']))
                                <div class="daily-detail-row penalty-row">
                                    <span class="ddr-label">Penalti</span>
                                    <span class="ddr-points">-{{ abs((int)($record['penalty_total'] ?? 0)) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Belum ada data harian</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-title">📥 Riwayat Poin Masuk</div>
            <div style="font-size: 0.72rem; color: #9ca3af; margin-bottom: 0.5rem;">
                Setiap kali Finance review rak, supervisor menambah bonus, atau ada penalti — masuk di sini.
            </div>
            <div class="events-list">
                @php
                    $events = $pointEvents ?? [];
                @endphp
                @forelse($events as $ev)
                    @php
                        $type = (string) ($ev['type'] ?? '');
                        $points = (int) ($ev['points'] ?? 0);
                        $iconClass = match ($type) {
                            'rack_recheck'      => 'rack',
                            'manual_bonus'      => 'bonus',
                            'manual_deduction'  => 'deduction',
                            'penalty'           => 'penalty',
                            default             => 'bonus',
                        };
                        $iconChar = match ($type) {
                            'rack_recheck'      => '📦',
                            'manual_bonus'      => '✨',
                            'manual_deduction'  => '➖',
                            'penalty'           => '⚠️',
                            default             => '•',
                        };
                        $pointsClass = $points >= 0 ? 'positive' : 'negative';
                        $pointsText = ($points > 0 ? '+' : '') . $points;
                        $createdAt = (int) ($ev['created_at'] ?? 0);
                        $when = $createdAt > 0
                            ? \Carbon\Carbon::createFromTimestamp($createdAt)->setTimezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M, H:i')
                            : (string) ($ev['date'] ?? '');
                        $actor = trim((string) ($ev['actor'] ?? ''));
                        $reason = trim((string) ($ev['reason'] ?? ''));
                    @endphp
                    <div class="event-item">
                        <div class="event-icon {{ $iconClass }}">{{ $iconChar }}</div>
                        <div class="event-body">
                            <span class="event-label">{{ $ev['label'] ?? '-' }}</span>
                            @if($reason !== '')
                                <span class="event-reason">{{ $reason }}</span>
                            @endif
                            <span class="event-meta">
                                {{ $when }}
                                @if($actor !== '')
                                    · oleh {{ $actor }}
                                @endif
                            </span>
                        </div>
                        <span class="event-points {{ $pointsClass }}">{{ $pointsText }}</span>
                    </div>
                @empty
                    <div class="event-empty">Belum ada riwayat poin bulan ini.</div>
                @endforelse
            </div>
        </div>

        @if(count($penalties) > 0)
        <div class="card">
            <div class="card-title">⚠️ Penalti</div>
            @foreach($penalties as $penalty)
                <div class="penalty-item">
                    <div class="penalty-header">
                        <span class="penalty-type">{{ $penalty['type'] ?? $penalty['penalty_type'] ?? 'Pelanggaran' }}</span>
                        <span class="penalty-points">-{{ abs((int)($penalty['points_deducted'] ?? 0)) }} poin</span>
                    </div>
                    <div class="penalty-reason">{{ $penalty['reason'] ?? $penalty['notes'] ?? '-' }}</div>
                    <div class="penalty-date">{{ $penalty['date'] ?? $penalty['created_at'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
        @endif

        @if(!empty($salesTarget))
        @php
            $salesAchieved = (int)($salesTarget['achieved'] ?? 0);
            $salesGoal = (int)($salesTarget['target'] ?? 1);
            $salesPercent = $salesGoal > 0 ? min(100, round(($salesAchieved / $salesGoal) * 100)) : 0;
        @endphp
        <div class="card">
            <div class="card-title">🎯 Target Penjualan</div>
            <div class="sales-progress-bar">
                <div class="sales-progress-fill" style="width: {{ $salesPercent }}%;"></div>
            </div>
            <div class="sales-stats">
                <span>Rp {{ number_format($salesAchieved, 0, ',', '.') }}</span>
                <span>{{ $salesPercent }}%</span>
                <span>Rp {{ number_format($salesGoal, 0, ',', '.') }}</span>
            </div>
        </div>
        @endif

        @if(!empty($leaderboard) && !empty($leaderboard['rankings']))
        <div class="card">
            <div class="card-title">🏅 Leaderboard</div>
            @php
                $topRankings = array_slice($leaderboard['rankings'], 0, 5);
            @endphp
            @foreach($topRankings as $idx => $entry)
                @php
                    $rankNum = $idx + 1;
                    $rankClass = match($rankNum) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
                    $isMe = ($entry['waiter_id'] ?? '') === $waiterId;
                @endphp
                <div class="leaderboard-item {{ $isMe ? 'is-me' : '' }}">
                    <div class="leaderboard-rank {{ $rankClass }}">{{ $rankNum }}</div>
                    <div class="leaderboard-name">{{ $entry['waiter_name'] ?? 'Waiter' }}{{ $isMe ? ' (Anda)' : '' }}</div>
                    <div class="leaderboard-points">{{ $entry['total_points'] ?? $entry['net_points'] ?? 0 }} pts</div>
                </div>
            @endforeach

            @if($myRank && ($myRank['rank'] ?? 0) > 5)
                <div style="text-align: center; padding: 0.5rem; color: #aaa; font-size: 0.75rem;">···</div>
                <div class="leaderboard-item is-me">
                    <div class="leaderboard-rank">{{ $myRank['rank'] ?? '?' }}</div>
                    <div class="leaderboard-name">{{ $waiterName }} (Anda)</div>
                    <div class="leaderboard-points">{{ $myRank['total_points'] ?? $myRank['net_points'] ?? 0 }} pts</div>
                </div>
            @endif
        </div>
        @endif
    </div>

    <script>
        function toggleDailyDetail(date) {
            const det = document.getElementById('det-' + date);
            const exp = document.getElementById('exp-' + date);
            if (!det || !exp) return;
            const isHidden = det.style.display === 'none';
            det.style.display = isHidden ? 'block' : 'none';
            exp.textContent = isHidden ? '▴' : '▾';
        }

        document.getElementById('monthPicker').addEventListener('change', function() {
            const month = this.value;
            if (month) {
                window.location.href = window.location.pathname + '?month=' + month;
            }
        });
    </script>
</body>
</html>
