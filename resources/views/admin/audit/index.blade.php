@extends('admin.layout')

@section('title', '📜 Audit Log')

@section('content')
<div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; width: 100%; box-sizing: border-box;">

    <!-- Page Header & Date Filter -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <h1 style="margin: 0; font-size: 1.5rem; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
            📜 Audit Log
        </h1>
        <form method="GET" action="" style="display: flex; align-items: center; gap: 0.5rem;">
            <!-- Keep existing filters -->
            @if(request('entity')) <input type="hidden" name="entity" value="{{ request('entity') }}"> @endif
            @if(request('admin')) <input type="hidden" name="admin" value="{{ request('admin') }}"> @endif
            
            <label for="date" style="color: var(--color-text-secondary); font-size: 0.9rem;">Tanggal:</label>
            <input type="date" id="date" name="date" value="{{ $date }}" onchange="this.form.submit()" 
                style="padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg); color: var(--color-text); font-family: inherit;">
        </form>
    </div>

    <!-- Filter Bar -->
    <div style="background: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);">
        <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            <input type="hidden" name="date" value="{{ $date }}">
            
            <div style="display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 200px;">
                <label for="entity" style="font-size: 0.85rem; color: var(--color-text-secondary); font-weight: 500;">Entitas</label>
                <select id="entity" name="entity" onchange="this.form.submit()" 
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg); color: var(--color-text); font-family: inherit;">
                    <option value="">Semua Entitas</option>
                    @foreach($entities as $e)
                        <option value="{{ $e }}" {{ $entity === $e ? 'selected' : '' }}>{{ ucfirst($e) }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 200px;">
                <label for="admin" style="font-size: 0.85rem; color: var(--color-text-secondary); font-weight: 500;">Admin</label>
                <select id="admin" name="admin" onchange="this.form.submit()" 
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg); color: var(--color-text); font-family: inherit;">
                    <option value="">Semua Admin</option>
                    @foreach($admins as $id => $name)
                        <option value="{{ $id }}" {{ $adminId == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                 <a href="?date={{ date('Y-m-d') }}" style="padding: 0.5rem 1rem; background: transparent; color: var(--color-text-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); text-decoration: none; font-size: 0.9rem; transition: all 0.2s;">Reset</a>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div style="background: var(--color-bg); border-radius: var(--radius-md); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm); overflow: hidden;">
        @if(empty($logs) || count($logs) === 0)
            <div style="padding: 3rem 1rem; text-align: center; color: var(--color-text-secondary);">
                <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5;">📭</div>
                <h3 style="margin: 0 0 0.5rem 0; color: var(--color-text); font-weight: 500;">Tidak ada data</h3>
                <p style="margin: 0; font-size: 0.9rem;">Tidak ada log untuk tanggal dan filter ini.</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--color-border); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary); white-space: nowrap;">Waktu</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary);">Admin</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary);">Aksi</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary);">Entitas</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary);">ID</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary);">Detail</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text-secondary);">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            @php
                                $actionConfig = [
                                    'create' => ['icon' => '✅', 'color' => 'var(--color-success)'],
                                    'update' => ['icon' => '✏️', 'color' => 'var(--color-info)'],
                                    'delete' => ['icon' => '🗑️', 'color' => 'var(--color-danger)'],
                                    'cancel' => ['icon' => '❌', 'color' => 'var(--color-warning)'],
                                    'override' => ['icon' => '🔄', 'color' => '#8b5cf6'],
                                    'bulk_reassign' => ['icon' => '🔀', 'color' => '#eab308']
                                ];
                                $act = $actionConfig[$log['action']] ?? ['icon' => '📌', 'color' => 'var(--color-text-secondary)'];
                            @endphp
                            <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.2s;">
                                <td style="padding: 0.75rem 1rem; color: var(--color-text); white-space: nowrap;">
                                    {{ date('H:i:s', is_numeric($log['timestamp']) ? $log['timestamp'] : strtotime($log['timestamp'])) }}
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-text);">
                                    {{ $log['admin_name'] ?: 'Sistem' }}
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.5rem; border-radius: 999px; background: {{ $act['color'] }}15; color: {{ $act['color'] }}; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">
                                        <span>{{ $act['icon'] }}</span>
                                        {{ $log['action'] }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-text);">
                                    <span style="font-family: monospace; background: var(--color-bg); padding: 0.1rem 0.3rem; border-radius: 4px; border: 1px solid var(--color-border);">{{ $log['entity'] }}</span>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-text);">
                                    {{ Str::limit($log['entity_id'], 15) }}
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    @if($log['details'])
                                        <details style="cursor: pointer; position: relative;">
                                            <summary style="color: var(--color-primary); font-size: 0.85rem; user-select: none; outline: none; list-style: none;">Lihat Data</summary>
                                            <div style="position: absolute; z-index: 10; left: 0; top: 100%; margin-top: 0.5rem; background: var(--color-bg); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm); padding: 0.75rem; border-radius: var(--radius-md); min-width: 250px; max-width: 400px; max-height: 200px; overflow-y: auto;">
                                                <pre style="margin: 0; font-size: 0.75rem; color: var(--color-text-secondary); white-space: pre-wrap; word-break: break-all;">{{ json_encode($log['details'], JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </details>
                                    @else
                                        <span style="color: var(--color-text-secondary); font-size: 0.85rem; font-style: italic;">-</span>
                                    @endif
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-text-secondary); font-family: monospace; font-size: 0.8rem;">
                                    {{ $log['ip'] ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
