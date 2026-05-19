@extends('admin.layout')

@section('title', 'Laporan Saldo Kas')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">💳 Laporan Saldo Kas</h1>
            <p class="fm-page-subtitle">Posisi saldo semua akun kas aktif</p>
        </div>
        <a href="{{ route('admin.finance.report.export', ['month' => request('month', date('Y-m'))]) }}" class="fm-btn fm-btn-success">📥 Export CSV</a>
    </div>

    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Periode</label>
            <input type="month" class="fm-input" id="filterMonth" value="{{ request('month', date('Y-m')) }}" style="width:180px;">
        </div>
        <button class="fm-btn fm-btn-primary" onclick="window.location.href='{{ route('admin.finance.report.balance') }}?month='+document.getElementById('filterMonth').value">🔍 Tampilkan</button>
    </div>

    @php $totalSaldo = collect($accounts)->sum('balance'); @endphp

    <div class="fm-cards">
        <div class="fm-card green">
            <div class="fm-card-icon">💰</div>
            <div class="fm-card-value fm-money income">Rp {{ number_format($totalSaldo, 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Saldo Semua Akun</div>
        </div>
        <div class="fm-card">
            <div class="fm-card-icon">🏦</div>
            <div class="fm-card-value">{{ count($accounts) }}</div>
            <div class="fm-card-label">Jumlah Akun Aktif</div>
        </div>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Akun</th><th style="text-align:right">Saldo Awal</th><th style="text-align:right">Pemasukan</th><th style="text-align:right">Pengeluaran</th><th style="text-align:right">Transfer Masuk</th><th style="text-align:right">Transfer Keluar</th><th style="text-align:right">Saldo Akhir</th></tr></thead>
            <tbody>
                @foreach($accounts as $acc)
                @php
                    $income = $acc['period_income'] ?? 0;
                    $expense = $acc['period_expense'] ?? 0;
                    $trIn = $acc['period_transfer_in'] ?? 0;
                    $trOut = $acc['period_transfer_out'] ?? 0;
                    $saldoAwal = $acc['balance'] - $income + $expense - $trIn + $trOut;
                @endphp
                <tr>
                    <td><strong>{{ $acc['name'] }}</strong><br><code style="font-size:11px;">{{ $acc['code'] }}</code></td>
                    <td style="text-align:right" class="fm-money">Rp {{ number_format($saldoAwal, 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money income">+Rp {{ number_format($income, 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money expense">-Rp {{ number_format($expense, 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money income">+Rp {{ number_format($trIn, 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money expense">-Rp {{ number_format($trOut, 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money {{ $acc['balance'] >= 0 ? 'income' : 'expense' }}"><strong>Rp {{ number_format($acc['balance'], 0, ',', '.') }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
