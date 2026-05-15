@extends('admin.layout')

@section('content')
    <h1>🔍 Reconciliation Stok</h1>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <div style="display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap;">
        <form method="GET" action="{{ route('admin.reconciliation.index') }}">
            <label for="iso_year_week">Minggu:</label>
            <select id="iso_year_week" name="iso_year_week" onchange="this.form.submit()">
                @foreach ($weekOptions as $week)
                    <option value="{{ $week }}" {{ $selectedWeek === $week ? 'selected' : '' }}>{{ $week }}</option>
                @endforeach
            </select>
        </form>

        <form method="POST" action="{{ route('admin.reconciliation.run') }}">
            @csrf
            <button type="submit" class="btn btn-primary">🔄 Jalankan Sekarang</button>
        </form>
    </div>

    @if (empty($reports))
        <div class="alert alert-info">Belum ada report. Klik 'Jalankan Sekarang' atau tunggu jadwal mingguan.</div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Generate</th>
                    <th>Total Rak</th>
                    <th>Total Produk</th>
                    <th>Anomali</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td>{{ !empty($report['generated_at']) ? date('d/m/Y H:i', (int) $report['generated_at']) : '-' }}</td>
                        <td>{{ (int) ($report['total_racks_checked'] ?? 0) }}</td>
                        <td>{{ (int) ($report['total_products_checked'] ?? 0) }}</td>
                        <td><span class="badge">{{ (int) ($report['anomalies_count'] ?? count($report['anomalies'] ?? [])) }}</span></td>
                        <td>
                            <a href="{{ route('admin.reconciliation.show', ['isoYearWeek' => $report['iso_year_week'] ?? $selectedWeek, 'reportId' => $report['id'] ?? '']) }}">Detail</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
