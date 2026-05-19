@extends('admin.layout')

@section('title', 'Audit Log Finance')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📜 Audit Log Finance</h1>
            <p class="fm-page-subtitle">Riwayat semua aksi penting modul keuangan</p>
        </div>
    </div>

    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Module</label>
            <select class="fm-select" id="filterModule" style="width:160px;">
                <option value="">Semua</option>
                <option value="category">Kategori</option>
                <option value="allocation">Alokasi</option>
                <option value="cash_account">Akun Kas</option>
                <option value="transfer">Transfer</option>
                <option value="sync">Sync</option>
                <option value="mapping">Mapping</option>
                <option value="settings">Settings</option>
                <option value="review">Review</option>
            </select>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="window.location.href='{{ route('admin.finance.audit_log') }}?module='+document.getElementById('filterModule').value">🔍 Filter</button>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Module</th><th>Record</th><th>Detail</th></tr></thead>
            <tbody>
                @foreach($logs['data'] as $log)
                <tr>
                    <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($log['created_at'])->format('d/m/Y H:i') }}</td>
                    <td>{{ $log['user_name'] }}</td>
                    <td><span class="fm-badge fm-badge-draft">{{ $log['action'] }}</span></td>
                    <td>{{ $log['module'] }}</td>
                    <td>{{ $log['record_id'] ?? '—' }}</td>
                    <td style="font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                        @if($log['new_values'])
                        {{ \Illuminate\Support\Str::limit($log['new_values'], 80) }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($logs['data']) === 0)
    <div class="fm-empty"><div class="fm-empty-icon">📭</div><div class="fm-empty-text">Belum ada audit log.</div></div>
    @endif

    <div class="fm-pagination"><span>Total: {{ $logs['total'] }} records</span></div>
</div>
@endsection
