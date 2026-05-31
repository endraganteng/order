<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Kasir - {{ $waiterName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa; color: #1e293b; min-height: 100vh; padding-bottom: 40px;
        }
        .header {
            background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
            color: white; padding: 1rem 1.25rem; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
        }
        .header-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .header h1 { font-size: 1.1rem; font-weight: 700; }
        .back-btn { color: white; text-decoration: none; font-size: 1.1rem; padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.15); }
        .header-name { font-size: 0.8rem; opacity: 0.9; }
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }

        .empty-state { background: white; border-radius: 14px; padding: 40px 20px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .empty-state .icon { font-size: 3.5rem; margin-bottom: 12px; }
        .empty-state h2 { font-size: 1.05rem; margin-bottom: 6px; }
        .empty-state p { color: #64748b; font-size: 0.9rem; }

        .today-card {
            background: white; border-radius: 14px; padding: 18px; margin-bottom: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); position: relative; overflow: hidden;
        }
        .today-card.is-libur { background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); }
        .today-card.is-shift1 { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 5px solid #2563eb; }
        .today-card.is-shift2 { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 5px solid #059669; }
        .today-card__label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px; }
        .today-card__day { font-size: 1.05rem; font-weight: 700; margin-top: 2px; }
        .today-card__shift { font-size: 1.65rem; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0.5px; }
        .today-card.is-shift1 .today-card__shift { color: #1e40af; }
        .today-card.is-shift2 .today-card__shift { color: #065f46; }
        .today-card__time { font-size: 0.95rem; color: #475569; font-weight: 500; }
        .today-card__hours { font-size: 0.8rem; color: #64748b; margin-top: 2px; }
        .today-card__backup-badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; margin-top: 6px; }

        .week-nav {
            display: flex; align-items: center; justify-content: space-between;
            background: white; border-radius: 12px; padding: 8px 12px; margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .week-nav__btn {
            background: none; border: 1px solid #e5e7eb; color: #475569;
            padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center;
        }
        .week-nav__btn:hover { background: #f8fafc; border-color: #cbd5e1; }
        .week-nav__title { font-weight: 700; font-size: 0.9rem; }

        .week-card { background: white; border-radius: 14px; padding: 14px; margin-bottom: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .week-card__title { font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 10px; text-transform: uppercase; }
        .day-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 8px; border-radius: 9px; margin-bottom: 4px; }
        .day-row.is-today { background: #fef3c7; border: 1px solid #fcd34d; }
        .day-row.is-weekend:not(.is-today) { background: #fafaf9; }
        .day-row__date { display: flex; flex-direction: column; }
        .day-row__day { font-weight: 700; font-size: 0.92rem; }
        .day-row__date-text { font-size: 0.72rem; color: #64748b; }
        .day-row.is-today .day-row__day::after { content: ' ← Hari ini'; font-size: 0.7rem; color: #d97706; font-weight: 600; }

        .shift-pill {
            display: inline-flex; flex-direction: column; align-items: center;
            padding: 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;
            min-width: 110px; line-height: 1.15;
        }
        .shift-pill__time { font-size: 0.65rem; opacity: 0.85; font-weight: 500; margin-top: 1px; }
        .shift-pill.shift-1 { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
        .shift-pill.shift-2 { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .shift-pill.shift-libur { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .day-row.is-backup-day .shift-pill { box-shadow: 0 0 0 2px #fde68a; }
        .day-row.is-backup-day .day-row__day::before { content: '🛟 '; }

        .summary-card { background: white; border-radius: 14px; padding: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 14px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
        .summary-item .label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; }
        .summary-item .value { font-size: 1.15rem; font-weight: 800; margin-top: 2px; }
        .info-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 10px 14px; font-size: 0.8rem; color: #92400e; margin-bottom: 14px; }
        .role-badge { display: inline-block; background: rgba(255,255,255,0.2); color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; margin-left: 6px; vertical-align: middle; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-top">
        <a href="{{ route('waiter.tasks') }}" class="back-btn">←</a>
        <h1>💰 Jadwal Kasir</h1>
        <div class="header-name">
            {{ $waiterName }}
            @if($schedule && ($schedule['is_backup_role'] ?? false))
                <span class="role-badge">Backup</span>
            @endif
        </div>
    </div>
</div>

<div class="container">
    @if(! $isKasirOrBackup)
        <div class="empty-state">
            <div class="icon">💰</div>
            <h2>Jadwal Kasir Belum Tersedia</h2>
            <p>Anda belum terdaftar sebagai kasir atau backup di sistem jadwal.</p>
        </div>
    @elseif(! $schedule)
        <div class="empty-state">
            <div class="icon">⚠️</div>
            <h2>Schedule Tidak Tersedia</h2>
            <p>Coba refresh atau hubungi admin.</p>
        </div>
    @else
        @php
            $today = $schedule['shift_today'];
            $todayClass = $today ? match($today['shift']) {
                'SHIFT_1' => 'is-shift1',
                'SHIFT_2' => 'is-shift2',
                default => 'is-libur',
            } : 'is-libur';
            $shiftLabel = $today ? match($today['shift']) {
                'SHIFT_1' => 'SHIFT 1',
                'SHIFT_2' => 'SHIFT 2',
                default => 'LIBUR',
            } : 'LIBUR';
        @endphp

        @if($isCurrentWeek && $today)
            <div class="today-card {{ $todayClass }}">
                <div class="today-card__label">Hari ini</div>
                <div class="today-card__day">{{ $today['day_label'] }}, {{ $today['date_label'] }}</div>
                <div class="today-card__shift">
                    @if($today['shift'] === 'LIBUR')
                        @if($schedule['is_backup_role'])
                            🛌 Tidak Bertugas
                        @else
                            🏖️ LIBUR
                        @endif
                    @else
                        {{ $shiftLabel }}
                    @endif
                </div>
                @if($today['shift_meta']['start'] ?? null)
                    <div class="today-card__time">{{ $today['shift_meta']['start'] }} – {{ $today['shift_meta']['end'] }}</div>
                    <div class="today-card__hours">{{ number_format($today['shift_meta']['duration'], 1, ',', '.') }} jam kerja</div>
                @else
                    <div class="today-card__time">@if($schedule['is_backup_role']) Tidak ada panggilan hari ini @else Selamat istirahat ya @endif</div>
                @endif
                @if($today['is_backup'] ?? false)
                    <div class="today-card__backup-badge">🛟 Backup Cover</div>
                @endif
            </div>
        @endif

        <div class="week-nav">
            <a href="{{ route('waiter.kasir_jadwal', ['week_start' => $prevWeek]) }}" class="week-nav__btn">←</a>
            <div class="week-nav__title">Minggu {{ $schedule['week_iso'] }}</div>
            <a href="{{ route('waiter.kasir_jadwal', ['week_start' => $nextWeek]) }}" class="week-nav__btn">→</a>
        </div>

        @if($schedule['has_override'])
            <div class="info-banner">📌 Jadwal minggu ini di-custom oleh admin.</div>
        @endif

        <div class="week-card">
            <div class="week-card__title">📅 Jadwal Mingguan</div>
            @foreach($schedule['days'] as $day)
                @php
                    $pillClass = match($day['shift']) {
                        'SHIFT_1' => 'shift-1',
                        'SHIFT_2' => 'shift-2',
                        default => 'shift-libur',
                    };
                    $pillLabel = match($day['shift']) {
                        'SHIFT_1' => 'SHIFT 1',
                        'SHIFT_2' => 'SHIFT 2',
                        default => 'LIBUR',
                    };
                @endphp
                <div class="day-row {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_weekend'] ? 'is-weekend' : '' }} {{ ($day['is_backup'] ?? false) && $day['shift'] !== 'LIBUR' ? 'is-backup-day' : '' }}">
                    <div class="day-row__date">
                        <div class="day-row__day">{{ $day['day_label'] }}</div>
                        <div class="day-row__date-text">{{ $day['date_label'] }}</div>
                    </div>
                    <div class="shift-pill {{ $pillClass }}">
                        {{ $pillLabel }}
                        @if($day['shift_meta']['start'] ?? null)
                            <span class="shift-pill__time">{{ $day['shift_meta']['start'] }}–{{ $day['shift_meta']['end'] }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="summary-card">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Total Jam</div>
                    <div class="value">{{ number_format($schedule['total_hours'], 1, ',', '.') }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Hari Libur</div>
                    <div class="value" style="font-size: 1rem;">{{ $schedule['libur_day'] ?? '-' }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Status</div>
                    <div class="value" style="font-size: 1rem;">
                        @if($schedule['is_backup_role'])
                            🛟 Backup
                        @else
                            💰 Kasir
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

</body>
</html>
