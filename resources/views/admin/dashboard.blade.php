@extends('admin.layout')

@section('title', 'Dashboard - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333; font-size: clamp(24px, 5vw, 32px);">Dashboard</h2>

    {{-- Quick Actions --}}
    <div class="card" style="padding: 20px 15px; margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px; font-size: clamp(18px, 4vw, 20px);">⚡ Quick Actions</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="{{ route('admin.waiters.create') }}" class="btn btn-primary"
                style="flex: 1 1 auto; min-width: 150px; text-align: center;">
                ➕ Tambah Waiter
            </a>
            <a href="{{ route('admin.settings') }}" class="btn btn-warning"
                style="flex: 1 1 auto; min-width: 150px; text-align: center;">
                ⚙️ Settings
            </a>
            <a href="{{ route('admin.cleanup') }}" class="btn btn-danger"
                style="flex: 1 1 auto; min-width: 150px; text-align: center;">
                🗑️ Cleanup Orders
            </a>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div style="
                display: grid; 
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); 
                gap: 15px; 
                margin-bottom: 30px;
            ">
        <div class="card" style="text-align: center; padding: 20px 15px;">
            <h3 style="color: #667eea; font-size: clamp(28px, 6vw, 36px); margin-bottom: 10px;">{{ count($waiters) }}</h3>
            <p style="color: #666; font-size: 14px;">Total Waiters</p>
        </div>

        <div class="card" style="text-align: center; padding: 20px 15px;">
            <h3 style="color: #28a745; font-size: clamp(28px, 6vw, 36px); margin-bottom: 10px;">
                {{ collect($waiters)->where('is_active', true)->count() }}
            </h3>
            <p style="color: #666; font-size: 14px;">Active Waiters</p>
        </div>

        <div class="card" style="text-align: center; padding: 20px 15px;">
            <h3 style="color: #ffc107; font-size: clamp(28px, 6vw, 36px); margin-bottom: 10px;">
                {{ $settings['order_timeout_minutes'] ?? 3 }}
            </h3>
            <p style="color: #666; font-size: 14px;">Timeout (menit)</p>
        </div>
    </div>

    {{-- Filter Statistik Supervisor --}}
    <div class="card" style="padding: 20px 15px; margin-bottom: 20px;">
        <h3 style="margin-bottom: 12px; font-size: clamp(18px, 4vw, 20px);">🧭 Filter Statistik Supervisor</h3>
        <form method="GET" action="{{ route('admin.dashboard') }}"
            style="display:flex; flex-wrap:wrap; gap:10px; align-items:end;">
            <div style="min-width: 200px; flex: 1 1 220px;">
                <label for="order_period" style="display:block; font-size:13px; color:#475569; margin-bottom:6px;">Periode
                    Statistik</label>
                <select id="order_period" name="order_period" class="input" style="width:100%;">
                    <option value="daily" {{ $orderPeriod === 'daily' ? 'selected' : '' }}>Harian (Hari Ini)</option>
                    <option value="weekly" {{ $orderPeriod === 'weekly' ? 'selected' : '' }}>Mingguan (Minggu Ini)</option>
                    <option value="monthly" {{ $orderPeriod === 'monthly' ? 'selected' : '' }}>Bulanan (Bulan Ini)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="min-width:140px;">Terapkan Filter</button>
        </form>
        <div style="margin-top: 10px; font-size: 13px; color: #64748b;">
            Menampilkan data <strong>{{ $orderPeriodLabel }}</strong> ({{ date('d M Y', $periodStartTs) }} -
            {{ date('d M Y', $periodEndTs) }})
        </div>
    </div>

    {{-- User Order Statistics --}}
    <div class="card" style="margin-bottom: 20px; padding: 20px 15px;">
        <h3 style="margin-bottom: 12px; color: #333; font-size: clamp(18px, 4vw, 24px);">📊 Statistik Order per Waiter
            ({{ $orderPeriodLabel }})</h3>

        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
            <span class="status pending" style="display:inline-flex; align-items:center; gap:6px;">Total Order:
                {{ $orderStatsSummary['total_orders'] ?? 0 }}</span>
            <span class="status done" style="display:inline-flex; align-items:center; gap:6px;">Waiter Aktif di Periode:
                {{ $orderStatsSummary['waiter_with_orders'] ?? 0 }}</span>
        </div>

        @if(count($userStats) > 0)
            <div style="
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr));
                    gap: 15px;
                ">
                @foreach($userStats as $index => $stat)
                    <div style="
                            border: 2px solid #e0e0e0;
                            border-radius: 12px;
                            padding: 16px 14px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                        ">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:8px;">
                            <h4
                                style="margin: 0; font-size: clamp(16px, 3.5vw, 18px); font-weight: 600; word-break: break-word;">
                                {{ $stat['waiter_name'] }}
                            </h4>
                            <span style="font-size:12px; font-weight:700; background:rgba(255,255,255,.2); border-radius:999px; padding:4px 8px;">
                                #{{ $index + 1 }}
                            </span>
                        </div>
                        @if(($stat['waiter_email'] ?? '') !== '')
                            <p style="margin: 0 0 10px 0; font-size: 12px; opacity: 0.92; word-break: break-all;">
                                {{ $stat['waiter_email'] }}
                            </p>
                        @endif

                        <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 12px; text-align: center;">
                            <div style="font-size: clamp(28px, 7vw, 36px); font-weight: bold; margin-bottom: 2px;">
                                {{ $stat['order_count'] }}
                            </div>
                            <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: .8px;">Total Orders</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty" style="margin:0;">Belum ada order pada periode {{ strtolower($orderPeriodLabel) }}.</div>
        @endif
    </div>

    {{-- Ranking Waiters --}}
    <div class="card" style="margin-bottom: 20px; padding: 20px 15px;">
        <h3 style="margin-bottom: 14px; color: #333; font-size: clamp(18px, 4vw, 24px);">🏆 Peringkat Waiter ({{ $orderPeriodLabel }})</h3>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 14px;">
            <div style="border:1px solid #e2e8f0; border-radius: 10px; overflow:hidden;">
                <div style="padding:10px 12px; background:#eef2ff; font-weight:700; color:#1e3a8a;">Order Terbanyak</div>
                @if(count($userStats) > 0)
                    <div style="overflow-x:auto;">
                        <table class="table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Waiter</th>
                                    <th>Total Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($userStats, 0, 10) as $rank => $stat)
                                    <tr>
                                        <td>{{ $rank + 1 }}</td>
                                        <td>
                                            <strong>{{ $stat['waiter_name'] }}</strong>
                                            @if(($stat['waiter_email'] ?? '') !== '')
                                                <div style="font-size:12px;color:#64748b;">{{ $stat['waiter_email'] }}</div>
                                            @endif
                                        </td>
                                        <td><span class="status done">{{ $stat['order_count'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty" style="margin:10px;">Belum ada data ranking order pada periode ini.</div>
                @endif
            </div>

            <div style="border:1px solid #e2e8f0; border-radius: 10px; overflow:hidden;">
                <div style="padding:10px 12px; background:#ecfeff; font-weight:700; color:#0f766e;">Paling Rajin Mengerjakan Tugas (Termasuk Cek Rak)</div>
                @if(count($waiterTaskRanking) > 0)
                    <div style="overflow-x:auto;">
                        <table class="table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Waiter</th>
                                    <th>Total Selesai</th>
                                    <th>Umum</th>
                                    <th>Cek Rak</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($waiterTaskRanking, 0, 10) as $rank => $stat)
                                    <tr>
                                        <td>{{ $rank + 1 }}</td>
                                        <td>
                                            <strong>{{ $stat['waiter_name'] }}</strong>
                                            @if(($stat['waiter_email'] ?? '') !== '')
                                                <div style="font-size:12px;color:#64748b;">{{ $stat['waiter_email'] }}</div>
                                            @endif
                                        </td>
                                        <td><span class="status done">{{ $stat['completed_count'] }}</span></td>
                                        <td>{{ $stat['general_done_count'] }}</td>
                                        <td>{{ $stat['rack_done_count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty" style="margin:10px;">Belum ada data penyelesaian tugas pada periode ini.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
