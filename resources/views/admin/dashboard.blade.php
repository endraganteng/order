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

    {{-- User Order Statistics --}}
    @if(count($userStats) > 0)
        <div class="card" style="margin-bottom: 30px; padding: 20px 15px;">
            <h3 style="margin-bottom: 20px; color: #333; font-size: clamp(18px, 4vw, 24px);">📊 Statistik Order per Waiter</h3>

            <div style="
                                display: grid; 
                                grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr)); 
                                gap: 15px;
                            ">
                @foreach($userStats as $stat)
                    <div style="
                                                border: 2px solid #e0e0e0; 
                                                border-radius: 12px; 
                                                padding: 20px 15px; 
                                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                                color: white;
                                                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                                                transition: transform 0.2s;
                                            " onmouseover="this.style.transform='translateY(-5px)'"
                        onmouseout="this.style.transform='translateY(0)'">
                        <div style="margin-bottom: 15px;">
                            <h4
                                style="margin: 0 0 5px 0; font-size: clamp(16px, 3.5vw, 18px); font-weight: 600; word-break: break-word;">
                                {{ $stat['waiter_name'] }}
                            </h4>
                            <p style="margin: 0; font-size: clamp(12px, 2.5vw, 13px); opacity: 0.9; word-break: break-all;">
                                {{ $stat['waiter_email'] }}
                            </p>
                        </div>

                        <div style="
                                                    background: rgba(255,255,255,0.2); 
                                                    border-radius: 8px; 
                                                    padding: 15px; 
                                                    text-align: center;
                                                    backdrop-filter: blur(10px);
                                                ">
                            <div style="font-size: clamp(32px, 8vw, 42px); font-weight: bold; margin-bottom: 5px;">
                                {{ $stat['order_count'] }}
                            </div>
                            <div
                                style="font-size: clamp(12px, 2.5vw, 14px); opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">
                                Total Orders
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection