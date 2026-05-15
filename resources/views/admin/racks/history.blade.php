@extends('admin.layout')

@section('title', 'Riwayat Cek Rak: ' . ($rack['name'] ?? '-') . ' - Admin')

@section('content')
    <div class="page-header">
        <h2 class="page-title">Riwayat Cek Rak: {{ $rack['name'] ?? '-' }}</h2>
        <a href="{{ route('admin.racks.index') }}" class="btn btn-secondary">← Kembali</a>
    </div>

    @push('styles')
    <style>
        .rack-history-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 14px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-info-bg);
        }
        .rack-history-meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .rack-history-meta-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }
        .rack-history-meta-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text);
        }
        .history-card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 12px;
            background: #fff;
            box-shadow: var(--shadow-sm);
        }
        .history-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .history-card-waiter {
            font-weight: 700;
            font-size: 14px;
            color: var(--color-text);
        }
        .history-card-date {
            font-size: 12px;
            color: var(--color-text-muted);
        }
        .history-card-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .history-stock-section {
            padding: 10px;
            border-radius: var(--radius-sm);
            background: var(--color-bg-secondary, #f8f9fa);
            border: 1px solid var(--color-border);
        }
        .history-stock-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-muted);
            margin-bottom: 6px;
        }
        .history-stock-items {
            font-size: 13px;
            color: var(--color-text);
            line-height: 1.5;
        }
        .history-stock-item {
            padding: 3px 0;
            border-bottom: 1px dashed var(--color-border);
        }
        .history-stock-item:last-child {
            border-bottom: none;
        }
        .history-stock-ok {
            color: var(--color-success, #10B981);
            font-weight: 600;
            font-size: 13px;
        }
        .history-photo-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
            cursor: pointer;
        }
        .history-checklist-table {
            width: 100%;
            font-size: 12px;
            border-collapse: collapse;
        }
        .history-checklist-table th,
        .history-checklist-table td {
            padding: 4px 8px;
            border-bottom: 1px solid var(--color-border);
            text-align: left;
        }
        .history-checklist-table th {
            font-weight: 600;
            color: var(--color-text-muted);
            background: var(--color-bg-secondary, #f8f9fa);
        }
        .shortage-flag {
            color: var(--color-danger, #EF4444);
            font-weight: 700;
        }
        .history-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--color-text-muted);
            font-size: 14px;
        }
        .stock-live-section,
        .movement-section {
            margin-bottom: 20px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 14px;
            background: #fff;
        }
        .stock-live-title,
        .movement-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 10px;
        }
        .stock-live-table,
        .movement-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .stock-live-table th,
        .stock-live-table td,
        .movement-table th,
        .movement-table td {
            padding: 8px;
            border-bottom: 1px solid var(--color-border);
            text-align: left;
        }
        .stock-live-table th,
        .movement-table th {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--color-text-muted);
            background: var(--color-bg-secondary, #f8f9fa);
        }
        .movement-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: end;
            margin-bottom: 10px;
        }
        .movement-filter-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .movement-filter-field label {
            font-size: 11px;
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
        }
        .movement-filter-field input,
        .movement-filter-field select {
            min-width: 150px;
            padding: 7px 9px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 13px;
        }
        .status-chip {
            display: inline-flex;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .status-chip.shortage {
            background: var(--color-danger-bg);
            color: var(--color-danger);
        }
        .status-chip.ok {
            background: var(--color-success-bg);
            color: var(--color-success);
        }

        /* Photo modal */
        .photo-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .photo-modal-overlay.is-active {
            display: flex;
        }
        .photo-modal-img {
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }

        /* Photo Comparison */
        .photo-compare-section {
            margin-bottom: 20px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 16px;
            background: #fff;
        }
        .photo-compare-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--color-text);
        }
        .photo-compare-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .photo-compare-card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        .photo-compare-card-label {
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: var(--color-bg-secondary, #f8f9fa);
            color: var(--color-text-muted);
            border-bottom: 1px solid var(--color-border);
        }
        .photo-compare-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            cursor: pointer;
        }
        .photo-compare-card-meta {
            padding: 6px 10px;
            font-size: 11px;
            color: var(--color-text-muted);
        }
        .photo-compare-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            background: var(--color-bg-secondary, #f8f9fa);
            color: var(--color-text-muted);
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .rack-history-meta {
                gap: 12px;
            }
            .history-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .photo-compare-grid {
                grid-template-columns: 1fr;
            }
            .photo-compare-card img {
                height: 160px;
            }
        }
    </style>
    @endpush

    {{-- Rack Info --}}
    <div class="rack-history-meta">
        <div class="rack-history-meta-item">
            <span class="rack-history-meta-label">Nama Rak</span>
            <span class="rack-history-meta-value">{{ $rack['name'] ?? '-' }}</span>
        </div>
        <div class="rack-history-meta-item">
            <span class="rack-history-meta-label">Lokasi</span>
            <span class="rack-history-meta-value">{{ $rack['location'] ?? '-' }}</span>
        </div>
        <div class="rack-history-meta-item">
            <span class="rack-history-meta-label">Total Pengecekan</span>
            <span class="rack-history-meta-value">{{ count($history) }}</span>
        </div>
        @if(count($history) > 0)
        <div class="rack-history-meta-item">
            <span class="rack-history-meta-label">Terakhir Dicek</span>
            <span class="rack-history-meta-value">{{ date('d M Y H:i', ($history[0]['completed_at'] ?? 0) / 1000) }}</span>
        </div>
        @endif
    </div>

    {{-- Live Stock per Produk --}}
    <div class="stock-live-section">
        <div class="stock-live-title">📦 Live Stok Per Produk</div>
        <div style="overflow-x:auto;">
            <table class="stock-live-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Target Qty</th>
                        <th>Min Qty</th>
                        <th>Current Qty</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rackProducts as $product)
                        @php
                            $productId = (string) ($product['id'] ?? '');
                            $live = $liveStockMap[$productId] ?? null;
                            $currentQty = is_array($live) ? ($live['current_qty'] ?? null) : null;
                            $lastUpdatedAt = is_array($live) ? (int) ($live['last_updated_at'] ?? 0) : 0;
                        @endphp
                        <tr>
                            <td>{{ $product['name'] ?? '-' }}</td>
                            <td>{{ (int) ($product['standard_qty'] ?? 0) }} {{ $product['unit'] ?? 'pcs' }}</td>
                            <td>{{ (int) ($product['min_qty'] ?? 0) }} {{ $product['unit'] ?? 'pcs' }}</td>
                            <td>
                                @if($currentQty !== null)
                                    {{ (int) $currentQty }} {{ $product['unit'] ?? 'pcs' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $lastUpdatedAt > 0 ? date('d M Y H:i', $lastUpdatedAt) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center; color: var(--color-text-muted);">Belum ada produk terpasang di rak ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stock Movement History --}}
    <div class="movement-section">
        <div class="movement-title">🔄 Riwayat Pergerakan Stok</div>

        <form method="GET" action="{{ route('admin.racks.history', $rack['id'] ?? '') }}" class="movement-filter-form">
            <div class="movement-filter-field">
                <label for="movement_product_id">Produk</label>
                <select id="movement_product_id" name="movement_product_id">
                    <option value="">Semua Produk</option>
                    @foreach($rackProducts as $product)
                        @php $optionProductId = (string) ($product['id'] ?? ''); @endphp
                        <option value="{{ $optionProductId }}" {{ $filterProductId === $optionProductId ? 'selected' : '' }}>
                            {{ $product['name'] ?? '-' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="movement-filter-field">
                <label for="movement_status">Status</label>
                <select id="movement_status" name="movement_status">
                    <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>Semua</option>
                    <option value="shortage" {{ $filterStatus === 'shortage' ? 'selected' : '' }}>Kurang</option>
                    <option value="ok" {{ $filterStatus === 'ok' ? 'selected' : '' }}>OK</option>
                </select>
            </div>
            <div class="movement-filter-field">
                <label for="movement_date_from">Dari Tanggal</label>
                <input id="movement_date_from" type="date" name="movement_date_from" value="{{ $filterDateFrom }}">
            </div>
            <div class="movement-filter-field">
                <label for="movement_date_to">Sampai Tanggal</label>
                <input id="movement_date_to" type="date" name="movement_date_to" value="{{ $filterDateTo }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.racks.history', $rack['id'] ?? '') }}" class="btn btn-sm" style="background: var(--color-border);">Reset</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="movement-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Standar</th>
                        <th>Aktual</th>
                        <th>Delta</th>
                        <th>Status</th>
                        <th>Waiter</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filteredStockMovements as $movement)
                        @php
                            $movementAt = (int) ($movement['completed_at'] ?? 0);
                            $isShortage = !empty($movement['is_shortage']);
                            $delta = (int) ($movement['delta_qty'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $movementAt > 0 ? date('d M Y H:i', $movementAt) : '-' }}</td>
                            <td>{{ $movement['product_name'] ?? '-' }}</td>
                            <td>{{ (int) ($movement['standard_qty'] ?? 0) }} {{ $movement['product_unit'] ?? 'pcs' }}</td>
                            <td>{{ (int) ($movement['actual_qty'] ?? 0) }} {{ $movement['product_unit'] ?? 'pcs' }}</td>
                            <td>{{ $delta > 0 ? '+' . $delta : (string) $delta }}</td>
                            <td>
                                <span class="status-chip {{ $isShortage ? 'shortage' : 'ok' }}">
                                    {{ $isShortage ? 'Kurang' : 'OK' }}
                                </span>
                            </td>
                            <td>{{ $movement['waiter_name'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center; color: var(--color-text-muted);">Tidak ada data movement sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Photo Comparison View --}}
    @php
        $photosWithDates = collect($history)->filter(function ($task) {
            return !empty($task['completed_photo_prof_url']);
        })->values();
    @endphp
    @if($photosWithDates->count() >= 2)
    <div class="photo-compare-section">
        <div class="photo-compare-title">📸 Perbandingan Foto (Terbaru vs Sebelumnya)</div>
        <div class="photo-compare-grid">
            @php
                $latest = $photosWithDates[0];
                $previous = $photosWithDates[1];
                $latestDate = ($latest['completed_at'] ?? 0) > 0 ? date('d M Y H:i', ($latest['completed_at']) / 1000) : '-';
                $prevDate = ($previous['completed_at'] ?? 0) > 0 ? date('d M Y H:i', ($previous['completed_at']) / 1000) : '-';
                $latestWaiter = $waiters[$latest['assigned_waiter_id'] ?? '']['name'] ?? ($latest['assigned_waiter_name'] ?? '-');
                $prevWaiter = $waiters[$previous['assigned_waiter_id'] ?? '']['name'] ?? ($previous['assigned_waiter_name'] ?? '-');
            @endphp
            <div class="photo-compare-card">
                <div class="photo-compare-card-label">📅 Terbaru — {{ $latestDate }}</div>
                <img src="{{ $latest['completed_photo_prof_url'] }}" alt="Foto terbaru" onclick="showPhotoModal(this.src)">
                <div class="photo-compare-card-meta">👤 {{ $latestWaiter }}</div>
            </div>
            <div class="photo-compare-card">
                <div class="photo-compare-card-label">📅 Sebelumnya — {{ $prevDate }}</div>
                <img src="{{ $previous['completed_photo_prof_url'] }}" alt="Foto sebelumnya" onclick="showPhotoModal(this.src)">
                <div class="photo-compare-card-meta">👤 {{ $prevWaiter }}</div>
            </div>
        </div>
    </div>
    @elseif($photosWithDates->count() === 1)
    <div class="photo-compare-section">
        <div class="photo-compare-title">📸 Foto Terakhir</div>
        <div class="photo-compare-grid">
            @php
                $latest = $photosWithDates[0];
                $latestDate = ($latest['completed_at'] ?? 0) > 0 ? date('d M Y H:i', ($latest['completed_at']) / 1000) : '-';
                $latestWaiter = $waiters[$latest['assigned_waiter_id'] ?? '']['name'] ?? ($latest['assigned_waiter_name'] ?? '-');
            @endphp
            <div class="photo-compare-card">
                <div class="photo-compare-card-label">📅 Terbaru — {{ $latestDate }}</div>
                <img src="{{ $latest['completed_photo_prof_url'] }}" alt="Foto terbaru" onclick="showPhotoModal(this.src)">
                <div class="photo-compare-card-meta">👤 {{ $latestWaiter }}</div>
            </div>
            <div class="photo-compare-card">
                <div class="photo-compare-card-label">📅 Sebelumnya</div>
                <div class="photo-compare-empty">Belum ada foto sebelumnya</div>
            </div>
        </div>
    </div>
    @endif

    {{-- History List --}}
    @if(count($history) === 0)
        <div class="history-empty">
            <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
            Belum ada riwayat pengecekan untuk rak ini.
        </div>
    @else
        @foreach($history as $task)
            @php
                $waiterId = $task['assigned_waiter_id'] ?? '';
                $waiterName = $waiters[$waiterId]['name'] ?? ($task['assigned_waiter_name'] ?? 'Unknown');
                $completedAt = ($task['completed_at'] ?? 0);
                $completedDate = $completedAt > 0 ? date('d M Y H:i', $completedAt / 1000) : '-';
                $stockReport = $task['completed_stock_report'] ?? '';
                $stockItems = $task['completed_stock_report_items'] ?? [];
                $noOutOfStock = !empty($task['completed_no_out_of_stock']);
                $productChecklist = $task['completed_product_checklist'] ?? [];
                $photoUrl = $task['completed_photo_prof_url'] ?? '';
                $scheduledDate = $task['scheduled_for_date'] ?? '';
            @endphp
            <div class="history-card">
                <div class="history-card-header">
                    <div>
                        <span class="history-card-waiter">👤 {{ $waiterName }}</span>
                        @if($scheduledDate)
                            <span style="font-size: 12px; color: var(--color-text-muted); margin-left: 8px;">📅 {{ $scheduledDate }}</span>
                        @endif
                    </div>
                    <span class="history-card-date">✅ Selesai: {{ $completedDate }}</span>
                </div>
                <div class="history-card-body">
                    {{-- Product Checklist --}}
                    @if(!empty($productChecklist) && is_array($productChecklist))
                        <div class="history-stock-section">
                            <div class="history-stock-title">📦 Checklist Produk</div>
                            <table class="history-checklist-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Standar</th>
                                        <th>Aktual</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($productChecklist as $item)
                                        @php
                                            $isShortage = !empty($item['is_shortage']);
                                            $productName = $item['product_name'] ?? $item['name'] ?? '-';
                                            $standardQty = $item['standard_qty'] ?? '-';
                                            $actualQty = $item['actual_qty'] ?? '-';
                                        @endphp
                                        <tr>
                                            <td>{{ $productName }}</td>
                                            <td>{{ $standardQty }}</td>
                                            <td>{{ $actualQty }}</td>
                                            <td>
                                                @if($isShortage)
                                                    <span class="shortage-flag">⚠️ Kurang</span>
                                                @else
                                                    <span style="color: var(--color-success, #10B981);">✓ OK</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif($noOutOfStock)
                        <div class="history-stock-section">
                            <div class="history-stock-ok">✅ Semua stok aman — tidak ada yang habis</div>
                        </div>
                    @elseif(!empty($stockItems) && is_array($stockItems))
                        <div class="history-stock-section">
                            <div class="history-stock-title">📝 Laporan Stok Habis/Rendah</div>
                            <div class="history-stock-items">
                                @foreach($stockItems as $item)
                                    <div class="history-stock-item">⚠️ {{ is_array($item) ? ($item['name'] ?? $item[0] ?? '-') : $item }}</div>
                                @endforeach
                            </div>
                        </div>
                    @elseif(!empty($stockReport))
                        <div class="history-stock-section">
                            <div class="history-stock-title">📝 Laporan Stok</div>
                            <div class="history-stock-items">{{ $stockReport }}</div>
                        </div>
                    @endif

                    {{-- Photo Proof --}}
                    @if(!empty($photoUrl))
                        <div>
                            <img src="{{ $photoUrl }}" alt="Foto bukti" class="history-photo-thumb" onclick="showPhotoModal(this.src)">
                        </div>
                    @endif

                    {{-- Note --}}
                    @if(!empty($task['completed_note'] ?? $task['note'] ?? ''))
                        <div style="font-size: 12px; color: var(--color-text-muted); font-style: italic;">
                            💬 {{ $task['completed_note'] ?? $task['note'] ?? '' }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- Photo Modal --}}
    <div class="photo-modal-overlay" id="photoModal" onclick="this.classList.remove('is-active')">
        <img class="photo-modal-img" id="photoModalImg" src="" alt="Foto bukti">
    </div>

    <script>
        function showPhotoModal(src) {
            var modal = document.getElementById('photoModal');
            var img = document.getElementById('photoModalImg');
            img.src = src;
            modal.classList.add('is-active');
        }
    </script>
@endsection
