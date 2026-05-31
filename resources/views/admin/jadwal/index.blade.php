@extends('admin.layout')

@section('title', 'Jadwal Shift Retail - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">🗓️ Jadwal Shift Retail</h2>
            <div class="page-subtitle">Generator otomatis untuk 3 karyawan retail. Standalone, tidak terhubung ke attendance system.</div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.jadwal.index', ['week_start' => $prevWeek]) }}" class="btn" style="background: var(--color-border);">← Minggu Lalu</a>
            <a href="{{ route('admin.jadwal.index', ['week_start' => $currentWeek]) }}" class="btn" style="background: var(--color-border);">📅 Minggu Ini</a>
            <a href="{{ route('admin.jadwal.index', ['week_start' => $nextWeek]) }}" class="btn btn-primary">Minggu Depan →</a>
        </div>
    </div>

    {{-- Week info banner --}}
    <div class="info-banner">
        <div class="info-banner__main">
            <strong>Minggu {{ $schedule['week_iso'] }}</strong>
            <span class="text-muted">({{ \Carbon\Carbon::parse($schedule['week_start'])->isoFormat('D MMM YYYY') }} – {{ \Carbon\Carbon::parse($schedule['week_start'])->addDays(6)->isoFormat('D MMM YYYY') }})</span>
        </div>
        <div class="info-banner__rotation">
            <span class="text-muted">4× Full Shift minggu ini:</span>
            <strong class="rotation-holder">{{ $schedule['holder_name'] }}</strong>
        </div>
    </div>

    {{-- Validation panel --}}
    @if(! $schedule['validation']['valid'] || count($schedule['validation']['warnings']) > 0)
        <div class="validation-panel {{ $schedule['validation']['valid'] ? 'is-warning' : 'is-error' }}">
            @if(! $schedule['validation']['valid'])
                <strong>⚠️ Jadwal melanggar aturan:</strong>
                <ul>
                    @foreach($schedule['validation']['errors'] as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
            @if(count($schedule['validation']['warnings']) > 0)
                <strong>ℹ️ Catatan:</strong>
                <ul>
                    @foreach($schedule['validation']['warnings'] as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <div class="validation-panel is-success">
            ✅ Jadwal valid — semua aturan terpenuhi.
        </div>
    @endif

    {{-- Holder override selector --}}
    <div class="control-row">
        <label class="form-label" style="margin-bottom: 0;">Override 4× Full Shift holder:</label>
        <select class="form-control" style="max-width: 220px;" onchange="changeHolder(this.value)">
            @foreach($schedule['employees'] as $i => $emp)
                <option value="{{ $i }}" @if($i === $schedule['holder_idx']) selected @endif>{{ $emp['name'] }}</option>
            @endforeach
        </select>
        <small class="text-muted">Default: rotasi otomatis berdasarkan ISO week.</small>
    </div>

    {{-- Schedule matrix --}}
    <div class="card-block">
        <h3 class="card-block__title">📊 Jadwal Mingguan</h3>
        <div class="table-scroll desktop-only">
            <table class="table schedule-matrix">
                <thead>
                    <tr>
                        <th>Hari</th>
                        @foreach($schedule['employees'] as $emp)
                            <th class="text-center">{{ $emp['name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule['matrix'] as $day)
                        <tr class="{{ $day['is_weekend'] ? 'is-weekend' : '' }}">
                            <td>
                                <strong>{{ $day['day_label'] }}</strong>
                                <div class="text-muted small">{{ $day['date_label'] }}</div>
                            </td>
                            @foreach($day['assignments'] as $a)
                                <td class="text-center">
                                    @php
                                        $shift = $a['shift'];
                                        $meta = $a['shift_meta'];
                                        $badgeClass = match($shift) {
                                            'FULL' => 'shift-badge shift-full',
                                            'PAGI' => 'shift-badge shift-pagi',
                                            'SORE' => 'shift-badge shift-sore',
                                            default => 'shift-badge shift-libur',
                                        };
                                    @endphp
                                    <div class="{{ $badgeClass }}">
                                        <strong>{{ $shift }}</strong>
                                        @if($meta['start'])
                                            <div class="shift-time">{{ $meta['start'] }}–{{ $meta['end'] }}</div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile version --}}
        <div class="mobile-only">
            @foreach($schedule['matrix'] as $day)
                <div class="mobile-day-card {{ $day['is_weekend'] ? 'is-weekend' : '' }}">
                    <div class="mobile-day-card__header">
                        <strong>{{ $day['day_label'] }}</strong>
                        <span class="text-muted small">{{ $day['date_label'] }}</span>
                    </div>
                    <div class="mobile-day-card__body">
                        @foreach($day['assignments'] as $a)
                            <div class="mobile-day-card__row">
                                <span class="text-muted">{{ $a['employee']['name'] }}</span>
                                @php
                                    $badgeClass = match($a['shift']) {
                                        'FULL' => 'shift-badge shift-full',
                                        'PAGI' => 'shift-badge shift-pagi',
                                        'SORE' => 'shift-badge shift-sore',
                                        default => 'shift-badge shift-libur',
                                    };
                                @endphp
                                <span class="{{ $badgeClass }}">
                                    <strong>{{ $a['shift'] }}</strong>
                                    @if($a['shift_meta']['start'])
                                        <span class="shift-time">{{ $a['shift_meta']['start'] }}–{{ $a['shift_meta']['end'] }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Summary --}}
    <div class="card-block">
        <h3 class="card-block__title">📈 Ringkasan Jam Kerja</h3>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th class="text-center">Full Shift</th>
                        <th class="text-center">Shift Pagi</th>
                        <th class="text-center">Shift Sore</th>
                        <th class="text-center">Libur</th>
                        <th>Hari Libur</th>
                        <th class="text-right">Total Jam</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule['summary'] as $s)
                        <tr>
                            <td><strong>{{ $s['name'] }}</strong></td>
                            <td class="text-center">
                                {{ $s['full_count'] }}×
                                @if($s['full_count'] === 4)
                                    <span class="badge badge-warning" style="margin-left: 4px;">4×</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $s['pagi_count'] }}×</td>
                            <td class="text-center">{{ $s['sore_count'] }}×</td>
                            <td class="text-center">{{ $s['libur_count'] }}×</td>
                            <td>{{ $s['libur_day'] ?? '-' }}</td>
                            <td class="text-right"><strong>{{ number_format($s['total_hours'], 1, ',', '.') }} jam</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Breaks --}}
    <div class="card-block">
        <h3 class="card-block__title">☕ Jadwal Istirahat</h3>
        <div class="break-grid">
            @foreach($schedule['breaks'] as $b)
                <div class="break-card {{ $b['mode'] === 'flex' ? 'is-flex' : '' }}">
                    <div class="break-card__header">
                        <strong>{{ $b['day_label'] }}</strong>
                        <span class="text-muted small">{{ $b['date_label'] }}</span>
                    </div>
                    @if($b['mode'] === 'rotation')
                        <div class="break-card__slots">
                            @foreach($b['slots'] as $slot)
                                <div class="break-slot">
                                    <span class="break-slot__time">{{ $slot['time'] }}</span>
                                    <strong>{{ $slot['employee'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="break-card__note">
                            <p style="margin: 0 0 6px 0;"><strong>Mode: Fleksibel</strong></p>
                            <p style="margin: 0; font-size: 0.85rem;">{{ $b['note'] }}</p>
                            <div style="margin-top: 8px; font-size: 0.85rem;">
                                <span class="text-muted">Yang masuk:</span> <strong>{{ implode(' & ', $b['workers']) }}</strong>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Rules info --}}
    <div class="card-block">
        <h3 class="card-block__title">📋 Aturan Jadwal</h3>
        <div class="rules-grid">
            <div class="rule-item">🏪 <strong>Jam buka:</strong> 06:30–21:00 setiap hari</div>
            <div class="rule-item">👥 <strong>Min staff:</strong> 2 orang sepanjang jam operasional</div>
            <div class="rule-item">🏖️ <strong>Libur:</strong> 1×/minggu/orang, dilarang Sabtu &amp; Minggu</div>
            <div class="rule-item">🔄 <strong>Rotasi 4× FULL:</strong> Rendy → Bagas → Anjar (rolling)</div>
            <div class="rule-item">⏰ <strong>Shift:</strong> FULL (06:30–21:00 / 14.5j), PAGI (06:30–15:30 / 9j), SORE (12:00–21:00 / 9j)</div>
            <div class="rule-item">☕ <strong>Istirahat:</strong> Hanya Kamis–Minggu (rotasi 12:00–15:00). Senin–Rabu fleksibel di toko.</div>
        </div>
    </div>

    @push('styles')
    <style>
        .info-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
            border: 1px solid #c4b5fd;
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .info-banner__main { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
        .info-banner__rotation { font-size: 0.95rem; }
        .rotation-holder {
            color: #4338ca;
            background: #fff;
            padding: 3px 10px;
            border-radius: 999px;
            border: 1px solid #c4b5fd;
            margin-left: 6px;
        }

        .validation-panel {
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 14px;
            font-size: 0.9rem;
        }
        .validation-panel ul { margin: 6px 0 0 0; padding-left: 22px; }
        .validation-panel.is-success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .validation-panel.is-warning { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
        .validation-panel.is-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        .control-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .card-block {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 14px 16px 16px 16px;
            margin-bottom: 14px;
        }
        .card-block__title {
            margin: 0 0 12px 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text, #1e293b);
        }

        .schedule-matrix th { background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; color: var(--color-text-muted); }
        .schedule-matrix tr.is-weekend td { background: #fffbeb; }
        .schedule-matrix td { padding: 10px 8px; vertical-align: middle; }

        .shift-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            min-width: 88px;
            text-align: center;
            line-height: 1.2;
        }
        .shift-badge .shift-time { font-size: 0.7rem; opacity: 0.85; margin-top: 2px; display: block; }
        .shift-full { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .shift-pagi { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
        .shift-sore { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .shift-libur { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .break-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }
        .break-card {
            background: #f8fafc;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
        }
        .break-card.is-flex { background: #fffbeb; border-color: #fde68a; }
        .break-card__header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--color-border);
        }
        .break-card.is-flex .break-card__header { border-bottom-color: #fde68a; }
        .break-card__slots { display: flex; flex-direction: column; gap: 5px; }
        .break-slot {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            padding: 3px 0;
        }
        .break-slot__time { color: var(--color-text-muted); }

        .rules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 8px;
        }
        .rule-item {
            font-size: 0.85rem;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
        }

        .desktop-only { display: block; }
        .mobile-only { display: none; }
        .mobile-day-card {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            margin-bottom: 8px;
            overflow: hidden;
        }
        .mobile-day-card.is-weekend { border-color: #fcd34d; background: #fffbeb; }
        .mobile-day-card__header {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            background: #f8fafc;
            border-bottom: 1px solid var(--color-border);
        }
        .mobile-day-card.is-weekend .mobile-day-card__header { background: #fef3c7; border-bottom-color: #fcd34d; }
        .mobile-day-card__body { padding: 8px 12px; }
        .mobile-day-card__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 0.85rem;
        }
        .mobile-day-card__row + .mobile-day-card__row { border-top: 1px dashed var(--color-border); }

        @media (max-width: 768px) {
            .desktop-only { display: none; }
            .mobile-only { display: block; }
            .info-banner { flex-direction: column; align-items: flex-start; }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        function changeHolder(idx) {
            const url = new URL(window.location.href);
            url.searchParams.set('holder_idx', idx);
            window.location.href = url.toString();
        }
    </script>
    @endpush
@endsection
