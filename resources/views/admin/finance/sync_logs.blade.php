@extends('admin.layout')

@section('title', 'Riwayat Sinkronisasi')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📋 Riwayat Sinkronisasi</h1>
            <p class="fm-page-subtitle">Log semua aktivitas sync data dari API</p>
        </div>
        <a href="{{ route('admin.finance.sync') }}" class="fm-btn fm-btn-primary">🔄 Sync Baru</a>
    </div>

    @if(count($logs['data']) === 0)
    <div class="fm-empty">
        <div class="fm-empty-icon">📭</div>
        <div class="fm-empty-text">Belum ada riwayat sync</div>
    </div>
    @else
    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tipe</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th>Synced</th>
                    <th>Failed</th>
                    <th>Durasi</th>
                    <th>Oleh</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs['data'] as $log)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($log['created_at'])->format('d/m/Y H:i') }}</td>
                    <td><span class="fm-badge fm-badge-draft">{{ $log['type'] }}</span></td>
                    <td>{{ \Carbon\Carbon::parse($log['sync_date_from'])->format('d/m') }} - {{ \Carbon\Carbon::parse($log['sync_date_to'])->format('d/m/Y') }}</td>
                    <td><span class="fm-badge fm-badge-{{ $log['status'] }}">{{ $log['status'] }}</span></td>
                    <td>{{ $log['records_synced'] }}</td>
                    <td>{{ $log['records_failed'] }}</td>
                    <td>{{ $log['duration_ms'] }}ms</td>
                    <td>{{ $log['triggered_by'] ?? '-' }}</td>
                </tr>
                @if($log['error_message'])
                <tr><td colspan="8" style="color:#991b1b;font-size:12px;padding:4px 12px;">⚠️ {{ $log['error_message'] }}</td></tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>

    @if($logs['total'] > 20)
    <div class="fm-pagination">
        <span>Total: {{ $logs['total'] }} records</span>
    </div>
    @endif
    @endif
</div>
@endsection
