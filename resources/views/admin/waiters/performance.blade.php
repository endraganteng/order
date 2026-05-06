@extends('admin.layout')

@section('title', '📊 Performa: ' . ($waiter['name'] ?? 'Waiter'))

@section('content')
<div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; width: 100%; box-sizing: border-box;">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="{{ route('admin.waiters.index') }}" style="color: var(--color-text-secondary); text-decoration: none; font-size: 1.2rem;">←</a>
            <div>
                <h1 style="margin: 0; font-size: 1.3rem; color: var(--color-text);">📊 {{ $waiter['name'] ?? 'Waiter' }}</h1>
                <span style="font-size: 0.85rem; color: var(--color-text-secondary);">{{ ucfirst($waiter['waiter_role'] ?? 'pelayan') }} · Performa {{ $fromDate }} s/d {{ $toDate }}</span>
            </div>
        </div>
        <form method="GET" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <input type="date" name="from" value="{{ $fromDate }}" style="padding: 0.4rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.85rem; background: var(--color-bg); color: var(--color-text);">
            <span style="color: var(--color-text-secondary);">—</span>
            <input type="date" name="to" value="{{ $toDate }}" style="padding: 0.4rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.85rem; background: var(--color-bg); color: var(--color-text);">
            <button type="submit" style="padding: 0.4rem 0.8rem; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 0.85rem;">Filter</button>
        </form>
    </div>

    <!-- KPI Cards -->
    @php
        $totalTasks = $taskPerformance['total_tasks'] ?? 0;
        $doneTasks = $taskPerformance['total_done'] ?? 0;
        $overdueTasks = $taskPerformance['total_overdue'] ?? 0;
        $completionRate = $taskPerformance['completion_rate'] ?? 0;
        $dailyStats = $taskPerformance['daily_stats'] ?? [];
    @endphp
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem;">
        <div style="background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm); text-align: center;">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-primary);">{{ $totalTasks }}</div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 0.25rem;">Total Tugas</div>
        </div>
        <div style="background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm); text-align: center;">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-success);">{{ $doneTasks }}</div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 0.25rem;">Selesai</div>
        </div>
        <div style="background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm); text-align: center;">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-danger);">{{ $overdueTasks }}</div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 0.25rem;">Terlambat</div>
        </div>
        <div style="background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm); text-align: center;">
            <div style="font-size: 1.8rem; font-weight: 700; color: {{ $completionRate >= 80 ? 'var(--color-success)' : ($completionRate >= 50 ? 'var(--color-warning)' : 'var(--color-danger)') }};">{{ $completionRate }}%</div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 0.25rem;">Completion Rate</div>
        </div>
    </div>

    <!-- Daily Trend Chart (CSS bars) -->
    @if(!empty($dailyStats))
    <div style="background: var(--color-bg); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: var(--color-text);">📈 Trend Harian</h3>
        <div style="display: flex; align-items: flex-end; gap: 2px; height: 120px; overflow-x: auto; padding-bottom: 1.5rem; position: relative;">
            @php
                $maxTotal = max(array_column($dailyStats, 'total') ?: [1]);
            @endphp
            @foreach($dailyStats as $date => $day)
                @php
                    $barHeight = $maxTotal > 0 ? ($day['total'] / $maxTotal * 100) : 0;
                    $doneHeight = $maxTotal > 0 ? ($day['done'] / $maxTotal * 100) : 0;
                    $dayLabel = \Carbon\Carbon::parse($date)->format('d');
                @endphp
                <div style="display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 18px; position: relative;" title="{{ $date }}: {{ $day['done'] }}/{{ $day['total'] }} selesai, {{ $day['overdue'] ?? 0 }} terlambat">
                    <div style="width: 100%; max-width: 24px; height: {{ $barHeight }}%; background: var(--color-border); border-radius: 3px 3px 0 0; position: relative; min-height: 2px;">
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: {{ $day['total'] > 0 ? ($day['done'] / $day['total'] * 100) : 0 }}%; background: var(--color-success); border-radius: 3px 3px 0 0; transition: height 0.3s;"></div>
                    </div>
                    <span style="font-size: 0.6rem; color: var(--color-text-secondary); position: absolute; bottom: -1.2rem;">{{ $dayLabel }}</span>
                </div>
            @endforeach
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.75rem; color: var(--color-text-secondary);">
            <span><span style="display: inline-block; width: 10px; height: 10px; background: var(--color-success); border-radius: 2px; margin-right: 4px;"></span>Selesai</span>
            <span><span style="display: inline-block; width: 10px; height: 10px; background: var(--color-border); border-radius: 2px; margin-right: 4px;"></span>Total</span>
        </div>
    </div>
    @endif

    <!-- Attendance Summary -->
    <div style="background: var(--color-bg); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: var(--color-text);">🕐 Kehadiran Bulan Ini ({{ $currentMonth }})</h3>
        @php
            $att = $attendanceSummary ?? [];
            $totalWorked = $att['total_days_worked'] ?? 0;
            $onTime = $att['on_time'] ?? 0;
            $late = $att['late'] ?? 0;
            $absent = $att['absent'] ?? 0;
        @endphp
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem;">
            <div style="padding: 0.75rem; background: var(--color-success-bg); border: 1px solid var(--color-success-border); border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: var(--color-success);">{{ $totalWorked }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Hari Kerja</div>
            </div>
            <div style="padding: 0.75rem; background: var(--color-info-bg); border: 1px solid var(--color-info-border); border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: var(--color-info);">{{ $onTime }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Tepat Waktu</div>
            </div>
            <div style="padding: 0.75rem; background: var(--color-warning-bg); border: 1px solid var(--color-warning-border); border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: var(--color-warning);">{{ $late }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Terlambat</div>
            </div>
            <div style="padding: 0.75rem; background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: var(--color-danger);">{{ $absent }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Absen</div>
            </div>
        </div>
    </div>

    <!-- Bonus History -->
    @if(!empty($bonusHistory))
    <div style="background: var(--color-bg); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: var(--color-text);">💰 Riwayat Bonus (6 Bulan Terakhir)</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--color-border);">
                        <th style="padding: 0.6rem; text-align: left; color: var(--color-text-secondary); font-weight: 600;">Bulan</th>
                        <th style="padding: 0.6rem; text-align: center; color: var(--color-text-secondary); font-weight: 600;">Poin</th>
                        <th style="padding: 0.6rem; text-align: center; color: var(--color-text-secondary); font-weight: 600;">Penalti</th>
                        <th style="padding: 0.6rem; text-align: center; color: var(--color-text-secondary); font-weight: 600;">Net</th>
                        <th style="padding: 0.6rem; text-align: right; color: var(--color-text-secondary); font-weight: 600;">Bonus</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bonusHistory as $bonus)
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.6rem; color: var(--color-text);">{{ $bonus['month'] ?? '-' }}</td>
                            <td style="padding: 0.6rem; text-align: center; color: var(--color-primary); font-weight: 600;">{{ $bonus['total_points_earned'] ?? 0 }}</td>
                            <td style="padding: 0.6rem; text-align: center; color: var(--color-danger);">-{{ $bonus['total_penalties'] ?? 0 }}</td>
                            <td style="padding: 0.6rem; text-align: center; font-weight: 600; color: {{ ($bonus['net_points'] ?? 0) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">{{ $bonus['net_points'] ?? 0 }}</td>
                            <td style="padding: 0.6rem; text-align: right; color: var(--color-success); font-weight: 600;">Rp {{ number_format($bonus['total_bonus'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Current Month Bonus Progress -->
    @if(!empty($bonusProgress))
    <div style="background: var(--color-bg); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: var(--color-text);">🎯 Progress Bonus Bulan Ini</h3>
        @php
            $monthlyPoints = $bonusProgress['total_earned'] ?? 0;
            $monthlyPenalties = $bonusProgress['total_penalties'] ?? 0;
            $netPoints = $bonusProgress['net_points'] ?? 0;
        @endphp
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem;">
            <div style="padding: 0.75rem; background: var(--color-primary-bg); border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 700; color: var(--color-primary);">{{ $monthlyPoints }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Poin Terkumpul</div>
            </div>
            <div style="padding: 0.75rem; background: var(--color-danger-bg); border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 700; color: var(--color-danger);">-{{ $monthlyPenalties }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Penalti</div>
            </div>
            <div style="padding: 0.75rem; background: {{ $netPoints >= 0 ? 'var(--color-success-bg)' : 'var(--color-danger-bg)' }}; border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 700; color: {{ $netPoints >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">{{ $netPoints }}</div>
                <div style="font-size: 0.75rem; color: var(--color-text-secondary);">Poin Bersih</div>
            </div>
        </div>
    </div>
    @endif

    <!-- Penalties This Month -->
    @if(!empty($penalties))
    <div style="background: var(--color-bg); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: var(--color-text);">⚠️ Penalti Bulan Ini</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @foreach($penalties as $penalty)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.75rem; background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); border-radius: var(--radius-md);">
                    <div>
                        <span style="font-size: 0.85rem; color: var(--color-text);">{{ $penalty['reason'] ?? 'Penalti' }}</span>
                        <span style="font-size: 0.75rem; color: var(--color-text-secondary); margin-left: 0.5rem;">{{ $penalty['date'] ?? '' }}</span>
                    </div>
                    <span style="font-weight: 700; color: var(--color-danger); font-size: 0.85rem;">{{ $penalty['points_deducted'] ?? 0 }} poin</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
