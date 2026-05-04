@extends('admin.layout')

@section('title', '🏆 Leaderboard Karyawan')

@section('content')
@php
    $month = $month ?? date('Y-m');
    $leaderboard = $leaderboard ?? null;
    $config = $config ?? [];

    $rankings = [];
    if (is_array($leaderboard)) {
        $rankings = $leaderboard['rankings'] ?? [];
    } elseif (is_object($leaderboard)) {
        $rankings = $leaderboard->rankings ?? [];
    }

    if (!is_array($rankings)) {
        $rankings = [];
    }

    $hasData = count($rankings) > 0;

    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

    $formatRp = function ($number) {
        return 'Rp ' . number_format((float) $number, 0, ',', '.');
    };

    $getRankRow = function ($rankNumber) use ($rankings) {
        foreach ($rankings as $item) {
            $rank = is_array($item) ? ($item['rank'] ?? null) : ($item->rank ?? null);
            if ((int) $rank === (int) $rankNumber) {
                return $item;
            }
        }
        return null;
    };

    $first = $getRankRow(1);
    $second = $getRankRow(2);
    $third = $getRankRow(3);

    $read = function ($item, $key, $default = null) {
        if (is_array($item)) {
            return $item[$key] ?? $default;
        }
        if (is_object($item)) {
            return $item->{$key} ?? $default;
        }
        return $default;
    };
@endphp

<div class="leaderboard-page">
    <div class="page-head">
        <div>
            <h2>🏆 Leaderboard Karyawan</h2>
            <p>Periode: <strong>{{ date('F Y', strtotime($month . '-01')) }}</strong></p>
        </div>
        <div class="head-actions">
            <div class="month-nav">
                <a href="?month={{ $prevMonth }}" class="nav-btn btn-secondary" aria-label="Bulan sebelumnya">&larr;</a>
                <input type="month" id="monthPicker" value="{{ $month }}">
                <a href="?month={{ $nextMonth }}" class="nav-btn btn-secondary" aria-label="Bulan berikutnya">&rarr;</a>
            </div>
            <button type="button" class="btn btn-primary" id="btnGenerate" style="margin-left: 8px;">Generate Leaderboard</button>
        </div>
    </div>

    @if(!$hasData)
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 16px;">📊</div>
            <h3>Belum ada data</h3>
            <p>Klik tombol 'Generate Leaderboard' untuk menghitung peringkat bulan ini.</p>
        </div>
    @else
        <div class="podium-wrap">
            <div class="podium-grid">
                <div class="podium-card silver">
                    @if($second)
                        <div class="medal">🥈</div>
                        <div class="name">{{ $read($second, 'waiter_name', '-') }}</div>
                        <div class="meta">{{ $read($second, 'waiter_role', '-') }}</div>
                        <div class="score">{{ number_format((float) $read($second, 'total_points', 0), 2, ',', '.') }} poin</div>
                        <div class="bonus">{{ $formatRp($read($second, 'total_bonus', 0)) }}</div>
                    @else
                        <div class="empty-mini">Belum ada peringkat #2</div>
                    @endif
                </div>

                <div class="podium-card gold first">
                    @if($first)
                        <div class="medal">🥇</div>
                        <div class="name">{{ $read($first, 'waiter_name', '-') }}</div>
                        <div class="meta">{{ $read($first, 'waiter_role', '-') }}</div>
                        <div class="score">{{ number_format((float) $read($first, 'total_points', 0), 2, ',', '.') }} poin</div>
                        <div class="bonus">{{ $formatRp($read($first, 'total_bonus', 0)) }}</div>
                    @else
                        <div class="empty-mini">Belum ada peringkat #1</div>
                    @endif
                </div>

                <div class="podium-card bronze">
                    @if($third)
                        <div class="medal">🥉</div>
                        <div class="name">{{ $read($third, 'waiter_name', '-') }}</div>
                        <div class="meta">{{ $read($third, 'waiter_role', '-') }}</div>
                        <div class="score">{{ number_format((float) $read($third, 'total_points', 0), 2, ',', '.') }} poin</div>
                        <div class="bonus">{{ $formatRp($read($third, 'total_bonus', 0)) }}</div>
                    @else
                        <div class="empty-mini">Belum ada peringkat #3</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="text-center">Rank</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th class="text-right">Total Poin</th>
                        <th class="text-center">Perfect Days</th>
                        <th class="text-center">Penalti</th>
                        <th class="text-right">Sales Persen</th>
                        <th class="text-right">Total Bonus Rp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rankings as $row)
                        @php
                            $rank = (int) $read($row, 'rank', 0);
                            $highlight = $rank >= 1 && $rank <= 3;
                            $trend = (string) $read($row, 'trend', 'stable');
                        @endphp
                        <tr class="{{ $highlight ? 'top-row' : '' }}">
                            <td class="text-center">
                                @if($rank === 1)
                                    <span style="font-size: 18px;">🥇</span>
                                @elseif($rank === 2)
                                    <span style="font-size: 18px;">🥈</span>
                                @elseif($rank === 3)
                                    <span style="font-size: 18px;">🥉</span>
                                @else
                                    <strong style="font-size: 18px;">{{ $rank }}</strong>
                                @endif
                            </td>
                            <td><strong>{{ $read($row, 'waiter_name', '-') }}</strong></td>
                            <td><span class="badge" style="background: #e2e8f0; color: #334155;">{{ str_replace('_', ' ', $read($row, 'waiter_role', '-')) }}</span></td>
                            <td class="text-right"><strong>{{ number_format((float) $read($row, 'total_points', 0), 2, ',', '.') }}</strong></td>
                            <td class="text-center">{{ (int) $read($row, 'perfect_days', 0) }}</td>
                            <td class="text-center"><span class="{{ ((int) $read($row, 'penalty_count', 0)) > 0 ? 'text-danger' : '' }}">{{ (int) $read($row, 'penalty_count', 0) }}</span></td>
                            <td class="text-right">{{ number_format((float) $read($row, 'sales_percentage', 0), 2, ',', '.') }}%</td>
                            <td class="text-right"><strong class="text-primary">{{ $formatRp($read($row, 'total_bonus', 0)) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php
            $lastCalculatedAt = null;
            if (is_array($leaderboard)) {
                $lastCalculatedAt = $leaderboard['last_calculated_at'] ?? null;
            } elseif (is_object($leaderboard)) {
                $lastCalculatedAt = $leaderboard->last_calculated_at ?? null;
            }
        @endphp
        @if($lastCalculatedAt)
            <div class="last-updated">Terakhir dihitung: {{ $lastCalculatedAt }}</div>
        @endif
    @endif
</div>

<style>
    .leaderboard-page { display: grid; gap: 16px; }

    .page-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-head h2 { margin: 0; }
    .page-head p { margin: 4px 0 0; color: #64748b; }

    .head-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
    .month-nav { display: inline-flex; align-items: center; gap: 8px; }
    .month-nav input {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 7px 10px;
        font: inherit;
    }
    .nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        background: #fff;
    }

    .empty-state {
        padding: 48px 24px;
        border-radius: 12px;
        background: #fff;
        color: #64748b;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .empty-state h3 { margin: 0 0 8px 0; color: #0f172a; }
    .empty-state p { margin: 0; color: #64748b; }

    .podium-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        padding: 16px;
    }
    .podium-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        align-items: end;
    }
    .podium-card {
        border-radius: 12px;
        padding: 14px;
        text-align: center;
        border: 1px solid #e2e8f0;
        background: #fff;
    }
    .podium-card.first { min-height: 230px; }
    .podium-card:not(.first) { min-height: 200px; }
    .podium-card.gold { background: linear-gradient(180deg, #fffbeb, #fff); border-color: #fde68a; }
    .podium-card.silver { background: linear-gradient(180deg, #f8fafc, #fff); border-color: #cbd5e1; }
    .podium-card.bronze { background: linear-gradient(180deg, #fff7ed, #fff); border-color: #fdba74; }

    .medal { font-size: 28px; margin-bottom: 8px; }
    .name { font-size: 18px; font-weight: 800; color: #0f172a; }
    .meta { font-size: 12px; color: #64748b; margin-top: 4px; }
    .score { margin-top: 10px; font-weight: 700; color: #1e293b; }
    .bonus { margin-top: 4px; font-size: 16px; font-weight: 800; color: #166534; }
    .empty-mini { margin-top: 60px; color: #94a3b8; }

    .table-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        overflow-x: auto;
    }
    table {
        width: 100%;
        min-width: 920px;
        border-collapse: collapse;
    }
    th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
    th { background: #f8fafc; white-space: nowrap; font-size: 13px; }
    .top-row { background: #f8fafc; }

    .trend {
        font-weight: 800;
        display: inline-flex;
        width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
    }
    .trend.up { color: #16a34a; background: #dcfce7; }
    .trend.down { color: #dc2626; background: #fee2e2; }
    .trend.stable { color: #6b7280; background: #f1f5f9; }

    .last-updated {
        color: #64748b;
        font-size: 13px;
        text-align: right;
    }

    @media (max-width: 900px) {
        .podium-grid {
            grid-template-columns: 1fr;
            align-items: stretch;
        }
        .podium-card.first,
        .podium-card:not(.first) {
            min-height: auto;
        }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-danger { color: #dc2626; }
    .text-primary { color: #667eea; }

    .last-updated {
            text-align: left;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const monthPicker = document.getElementById('monthPicker');
    const btnGenerate = document.getElementById('btnGenerate');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (monthPicker) {
        monthPicker.addEventListener('change', function () {
            if (!monthPicker.value) return;
            window.location.href = '?month=' + encodeURIComponent(monthPicker.value);
        });
    }

    if (btnGenerate) {
        btnGenerate.addEventListener('click', async function () {
            const month = monthPicker ? monthPicker.value : '{{ $month }}';
            const original = btnGenerate.textContent;

            btnGenerate.disabled = true;
            btnGenerate.textContent = 'Generating...';

            try {
                const response = await fetch('{{ route('admin.bonus.leaderboard.generate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ month: month })
                });

                if (!response.ok) {
                    let message = 'Gagal generate leaderboard.';
                    try {
                        const data = await response.json();
                        if (data && data.message) {
                            message = data.message;
                        }
                    } catch (e) {
                        // ignore json parse error
                    }
                    throw new Error(message);
                }

                window.location.reload();
            } catch (error) {
                alert(error.message || 'Terjadi kesalahan saat generate leaderboard.');
            } finally {
                btnGenerate.disabled = false;
                btnGenerate.textContent = original;
            }
        });
    }
});
</script>
@endsection
