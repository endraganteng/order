@extends('admin.layout')

@section('title', 'Current Order - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">🧾 Current Order</h2>

    <div class="card" style="margin-bottom: 16px;">
        <form id="current-order-filter-form" method="GET" action="{{ route('admin.current_order.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end;">
            <div style="grid-column: 1 / -1;">
                <label for="filter_search" style="display:block; font-size:12px; color:#64748b; margin-bottom:6px;">Live Search</label>
                <input
                    id="filter_search"
                    type="search"
                    name="filter_search"
                    value="{{ $filterSearch }}"
                    data-live-search="true"
                    placeholder="Cari no. antrian, waiter, email, produk, atau waktu..."
                    style="width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 8px;"
                >
                <div style="margin-top: 6px; font-size: 12px; color: #64748b;">Pencarian berjalan otomatis saat Anda mengetik (debounce).</div>
            </div>

            <div>
                <label for="filter_date" style="display:block; font-size:12px; color:#64748b; margin-bottom:6px;">Filter Tanggal</label>
                <input
                    id="filter_date"
                    type="date"
                    name="filter_date"
                    value="{{ $filterDate }}"
                    style="width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 8px;"
                >
            </div>

            <div>
                <label for="filter_hour" style="display:block; font-size:12px; color:#64748b; margin-bottom:6px;">Filter Jam</label>
                <select
                    id="filter_hour"
                    name="filter_hour"
                    style="width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 8px;"
                >
                    <option value="">Semua Jam</option>
                    @for($hour = 0; $hour <= 23; $hour++)
                        @php $hourValue = str_pad((string) $hour, 2, '0', STR_PAD_LEFT); @endphp
                        <option value="{{ $hourValue }}" {{ $filterHour === $hourValue ? 'selected' : '' }}>
                            {{ $hourValue }}:00 - {{ $hourValue }}:59
                        </option>
                    @endfor
                </select>
            </div>

            <div>
                <label for="filter_time" style="display:block; font-size:12px; color:#64748b; margin-bottom:6px;">Filter Waktu (HH:MM)</label>
                <input
                    id="filter_time"
                    type="time"
                    name="filter_time"
                    step="60"
                    value="{{ $filterTime }}"
                    style="width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 8px;"
                >
            </div>

            <div>
                <label for="filter_waiter" style="display:block; font-size:12px; color:#64748b; margin-bottom:6px;">Filter Waiter</label>
                <select
                    id="filter_waiter"
                    name="filter_waiter"
                    style="width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 8px;"
                >
                    <option value="">Semua Waiter</option>
                    @foreach($waiterOptions as $waiter)
                        @php
                            $waiterName = trim((string) ($waiter['name'] ?? ''));
                            $waiterEmail = trim((string) ($waiter['email'] ?? ''));
                            $waiterLabel = $waiterName !== '' ? $waiterName : '-';
                            if ($waiterEmail !== '') {
                                $waiterLabel .= ' ('.$waiterEmail.')';
                            }
                        @endphp
                        <option value="{{ $waiter['key'] ?? '' }}" {{ $filterWaiter === ($waiter['key'] ?? '') ? 'selected' : '' }}>
                            {{ $waiterLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                <a href="{{ route('admin.current_order.index') }}" class="btn" style="background:#64748b;color:#fff;">Reset</a>
            </div>
        </form>

        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px;">
            <span class="badge" style="background:#dcfce7;color:#166534;">Default tanggal: {{ date('d/m/Y', strtotime($todayDate)) }}</span>
            <span class="badge" style="background:#e2e8f0;color:#1e293b;">Total order: {{ $totalOrderCount }}</span>
            <span class="badge" style="background:#dbeafe;color:#1d4ed8;">Hasil filter: {{ $filteredOrderCount }}</span>
            @if($hasCustomFilters)
                <span class="badge" style="background:#fef3c7;color:#92400e;">Filter aktif</span>
            @endif
        </div>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 16px 0;">
            <h3 style="margin: 0; color: #333;">Daftar Order Terbaru</h3>
        </div>
        <div style="overflow-x: auto; padding: 16px;">
            <table style="display: table; min-width: 1080px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Antrian</th>
                        <th>Waiter</th>
                        <th>Produk</th>
                        <th>Total</th>
                        <th>Waktu Order</th>
                        <th>Expired</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $idx => $order)
                        @php
                            $rowNumber = (($orders->currentPage() - 1) * $orders->perPage()) + $idx + 1;
                            $createdAt = (int) ($order['created_at_ts'] ?? 0);
                            $expiresAt = (int) ($order['expires_at_ts'] ?? 0);
                            $isExpired = $expiresAt > 0 && $expiresAt < $nowTs;
                        @endphp
                        <tr>
                            <td>{{ $rowNumber }}</td>
                            <td>
                                @if(!empty($order['queue_number']))
                                    <span class="badge" style="background:#ede9fe;color:#5b21b6;">#{{ $order['queue_number'] }}</span>
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight:600;">{{ $order['waiter_name'] ?? '-' }}</div>
                                @if(!empty($order['waiter_email']))
                                    <div style="font-size:12px; color:#64748b;">{{ $order['waiter_email'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if(!empty($order['products']) && is_array($order['products']))
                                    <ul style="margin:0; padding-left: 18px; font-size: 13px; color:#334155;">
                                        @foreach($order['products'] as $product)
                                            <li>
                                                {{ $product['name'] ?? '-' }}
                                                <span style="color:#64748b;">
                                                    (Rp {{ number_format((int) ($product['price'] ?? 0), 0, ',', '.') }})
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight:700; color:#0f172a;">Rp {{ number_format((int) ($order['total_price'] ?? 0), 0, ',', '.') }}</div>
                                <div style="font-size:12px; color:#64748b;">{{ (int) ($order['product_count'] ?? 0) }} produk</div>
                            </td>
                            <td>
                                @if($createdAt > 0)
                                    {{ date('d/m/Y H:i', $createdAt) }}
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td>
                                @if($expiresAt > 0)
                                    {{ date('d/m/Y H:i', $expiresAt) }}
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td>
                                @if($expiresAt <= 0)
                                    <span class="badge" style="background:#e2e8f0;color:#334155;">Unknown</span>
                                @elseif($isExpired)
                                    <span class="badge" style="background:#fee2e2;color:#b91c1c;">Expired</span>
                                @else
                                    <span class="badge" style="background:#dcfce7;color:#166534;">Aktif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center; color:#777;">Belum ada order yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->total() > 0)
            <div style="padding: 0 16px 16px; display:flex; justify-content:space-between; gap:8px; align-items:center; flex-wrap:wrap;">
                <div style="font-size:12px; color:#64748b;">
                    Menampilkan {{ $orders->firstItem() }} - {{ $orders->lastItem() }} dari {{ $orders->total() }} order
                </div>

                @if($orders->lastPage() > 1)
                    @php
                        $startPage = max(1, $orders->currentPage() - 2);
                        $endPage = min($orders->lastPage(), $orders->currentPage() + 2);
                        $pageUrls = $orders->getUrlRange($startPage, $endPage);
                    @endphp
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        @if($orders->onFirstPage())
                            <span class="btn" style="background:#e5e7eb; color:#94a3b8; cursor:not-allowed;">&larr; Prev</span>
                        @else
                            <a class="btn" style="background:#f1f5f9; color:#0f172a;" href="{{ $orders->previousPageUrl() }}">&larr; Prev</a>
                        @endif

                        @foreach($pageUrls as $pageNumber => $pageUrl)
                            @if($pageNumber === $orders->currentPage())
                                <span class="btn" style="background:#1d4ed8; color:#fff;">{{ $pageNumber }}</span>
                            @else
                                <a class="btn" style="background:#fff; color:#0f172a; border:1px solid #d1d5db;" href="{{ $pageUrl }}">{{ $pageNumber }}</a>
                            @endif
                        @endforeach

                        @if($orders->hasMorePages())
                            <a class="btn" style="background:#f1f5f9; color:#0f172a;" href="{{ $orders->nextPageUrl() }}">Next &rarr;</a>
                        @else
                            <span class="btn" style="background:#e5e7eb; color:#94a3b8; cursor:not-allowed;">Next &rarr;</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script>
        (function () {
            const form = document.getElementById('current-order-filter-form');
            const liveSearchInput = document.querySelector('input[data-live-search="true"]');
            if (!form || !liveSearchInput) {
                return;
            }

            let debounceTimer = null;

            const triggerSubmit = () => {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            };

            liveSearchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    window.clearTimeout(debounceTimer);
                }

                debounceTimer = window.setTimeout(() => {
                    triggerSubmit();
                }, 350);
            });
        })();
    </script>
@endsection
