@extends('admin.layout')

@section('title', 'Detail PO - Admin')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="{{ route('admin.restock.orders') }}" class="btn" style="background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text); padding: 8px 12px;">&larr; Kembali</a>
            <h2 style="margin: 0; color: #1e293b; font-size: clamp(20px, 4vw, 28px); font-weight: 800;">
                PO: {{ $order['po_number'] }}
                
                @if($order['status'] === 'ordered')
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 14px; font-weight: 600; background: var(--color-info-bg); color: var(--color-info); margin-left: 10px; vertical-align: middle;">Ordered</span>
                @elseif($order['status'] === 'partial')
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 14px; font-weight: 600; background: var(--color-warning-bg); color: var(--color-warning); margin-left: 10px; vertical-align: middle;">Partial</span>
                @elseif($order['status'] === 'completed')
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 14px; font-weight: 600; background: var(--color-success-bg); color: var(--color-success); margin-left: 10px; vertical-align: middle;">Completed</span>
                @elseif($order['status'] === 'cancelled')
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 14px; font-weight: 600; background: var(--color-danger-bg); color: var(--color-danger); margin-left: 10px; vertical-align: middle;">Cancelled</span>
                @endif
            </h2>
        </div>
        
        @if(in_array($order['status'], ['ordered', 'partial']))
        <button id="btn-cancel-po" class="btn" style="background: var(--color-danger); color: white; border: none;">Batalkan PO</button>
        @endif
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Meta Info -->
    <div style="background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: var(--color-text); border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">Informasi PO</h3>
        
        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px; margin-bottom: 10px;">
            <div style="color: var(--color-text-secondary); font-weight: 500;">Dibuat Oleh:</div>
            <div style="color: var(--color-text);">{{ $order['created_by_name'] ?? '-' }}</div>
            
            <div style="color: var(--color-text-secondary); font-weight: 500;">Tanggal:</div>
            <div style="color: var(--color-text);">{{ !empty($order['created_at']) ? date('d M Y H:i', $order['created_at']) : '-' }}</div>
            
            <div style="color: var(--color-text-secondary); font-weight: 500;">Supplier:</div>
            <div style="color: var(--color-text);">{{ $order['supplier'] ?? '-' }}</div>
            
            <div style="color: var(--color-text-secondary); font-weight: 500;">Catatan:</div>
            <div style="color: var(--color-text); font-style: {{ !empty($order['notes']) ? 'normal' : 'italic' }};">{{ $order['notes'] ?? 'Tidak ada catatan' }}</div>
        </div>
    </div>
    
    <!-- Progress -->
    <div style="background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: var(--color-text); border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">Progress Penerimaan</h3>
        
        @php
            $itemsCount = $order['items_count'] ?? 0;
            $receivedCount = $order['received_count'] ?? 0;
            $progressPercent = $itemsCount > 0 ? round(($receivedCount / $itemsCount) * 100) : 0;
            $progressColor = $progressPercent === 100 ? 'var(--color-success)' : 'var(--color-primary)';
        @endphp
        
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-weight: 600; color: var(--color-text);">Item Diterima</span>
            <span style="font-weight: bold; color: {{ $progressPercent === 100 ? 'var(--color-success)' : 'var(--color-text)' }};">{{ $receivedCount }} / {{ $itemsCount }} ({{ $progressPercent }}%)</span>
        </div>
        
        <div style="width: 100%; height: 12px; background: var(--color-bg); border-radius: 6px; overflow: hidden; border: 1px solid var(--color-border);">
            <div style="height: 100%; width: {{ $progressPercent }}%; background: {{ $progressColor }}; transition: width 0.3s ease;"></div>
        </div>
        
        <div style="margin-top: 15px; font-size: 13px; color: var(--color-text-secondary); line-height: 1.5;">
            Status item: <span style="color: var(--color-success);">●</span> Full diterima, <span style="color: var(--color-warning);">●</span> Sebagian diterima, <span style="color: var(--color-text-muted);">●</span> Belum diterima.
        </div>
    </div>
</div>

<div style="background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 24px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: var(--color-text);">Daftar Item</h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--color-bg); text-align: left; border-bottom: 2px solid var(--color-border);">
                    <th style="padding: 12px;">Produk</th>
                    <th style="padding: 12px;">Rak</th>
                    <th style="padding: 12px; text-align: center;">Qty Dibutuhkan</th>
                    <th style="padding: 12px; text-align: center;">Qty Dipesan</th>
                    <th style="padding: 12px; text-align: center;">Diterima</th>
                    <th style="padding: 12px; text-align: center;">Status</th>
                    <th style="padding: 12px;">Terakhir Diterima</th>
                    <th style="padding: 12px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($order['items'] ?? []) as $itemKey => $item)
                @php 
                    $receivedQty = $item['received_qty'] ?? 0; 
                    $qtyOrdered = $item['qty_ordered'] ?? 0;
                    $isReceived = !empty($item['received']); 
                    $hasMismatch = $receivedQty > 0 && $receivedQty < $qtyOrdered && !$isReceived;
                    $acceptedAsIs = !empty($item['accepted_as_is']);
                @endphp
                <tr style="border-bottom: 1px solid var(--color-border);" data-item-key="{{ $itemKey }}">
                    <td style="padding: 12px; font-weight: 500;">{{ $item['product_name'] ?? '-' }}</td>
                    <td style="padding: 12px; color: var(--color-text-secondary);">{{ $item['rack_name'] ?? '-' }}</td>
                    <td style="padding: 12px; text-align: center; color: var(--color-text-muted);">{{ $item['qty_needed'] ?? 0 }}</td>
                    <td style="padding: 12px; text-align: center; font-weight: bold;">{{ $qtyOrdered }}</td>
                    <td style="padding: 12px; text-align: center; font-weight: bold; color: {{ $receivedQty > 0 ? ($isReceived ? 'var(--color-success)' : 'var(--color-warning)') : 'var(--color-text-muted)' }};">
                        {{ $receivedQty }}
                        @if($hasMismatch)
                            <span style="color: var(--color-warning); font-size: 11px; display: block; margin-top: 2px;">⚠️ Kurang {{ $qtyOrdered - $receivedQty }}</span>
                        @endif
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        @if($isReceived)
                            <span style="color: var(--color-success); font-size: 18px;" title="Selesai">✅</span>
                            @if($acceptedAsIs)
                                <div style="font-size: 10px; color: var(--color-warning); margin-top: 2px;">Diterima apa adanya</div>
                            @endif
                        @elseif($receivedQty > 0)
                            <span style="color: var(--color-warning); font-size: 18px;" title="Parsial">⏳</span>
                        @else
                            <span style="color: var(--color-text-muted); font-size: 18px;" title="Menunggu">⚪</span>
                        @endif
                    </td>
                    <td style="padding: 12px; color: var(--color-text-secondary); font-size: 13px;">
                        @if(!empty($item['last_received_at']))
                            <div>{{ date('d M Y H:i', $item['last_received_at']) }}</div>
                            <div style="font-size: 12px;">Oleh: {{ $item['last_received_by_name'] ?? '-' }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        @if($hasMismatch && in_array($order['status'], ['ordered', 'partial']))
                            <button class="btn-accept-as-is" data-restock-id="{{ $itemKey }}" style="background: var(--color-warning); color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; white-space: nowrap;">
                                ✓ Terima Apa Adanya
                            </button>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div style="background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" id="toggle-history">
        <h3 style="margin: 0; font-size: 16px; color: var(--color-text);">Riwayat Restock per Produk</h3>
        <span id="history-icon" style="transition: transform 0.3s ease;">▼</span>
    </div>
    
    <div id="history-content" style="display: none; margin-top: 15px; border-top: 1px solid var(--color-border); padding-top: 15px;">
        @if(empty($productHistories))
            <div style="color: var(--color-text-muted); text-align: center; padding: 20px;">Belum ada riwayat restock sebelumnya.</div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                @foreach($productHistories as $productId => $histories)
                    @php 
                        $firstItem = collect($order['items'])->firstWhere('product_id', $productId);
                        $productName = $firstItem ? $firstItem['product_name'] : 'Product #'.$productId;
                    @endphp
                    
                    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 15px;">
                        <h4 style="margin-top: 0; margin-bottom: 10px; color: var(--color-primary); font-size: 14px;">{{ $productName }}</h4>
                        
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            @foreach($histories as $h)
                            <div style="display: flex; justify-content: space-between; font-size: 13px; border-bottom: 1px dashed var(--color-border); padding-bottom: 5px;">
                                <span style="color: var(--color-text-secondary);">{{ date('d M Y', strtotime($h['date'] ?? 'now')) }}</span>
                                <span>
                                    Qty: <strong>{{ $h['qty_ordered'] ?? 0 }}</strong>
                                    @if(!empty($h['received']))
                                        <span style="color: var(--color-success); margin-left: 5px;">✅</span>
                                    @endif
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleHistory = document.getElementById('toggle-history');
        const historyContent = document.getElementById('history-content');
        const historyIcon = document.getElementById('history-icon');
        
        toggleHistory.addEventListener('click', function() {
            if (historyContent.style.display === 'none') {
                historyContent.style.display = 'block';
                historyIcon.style.transform = 'rotate(180deg)';
            } else {
                historyContent.style.display = 'none';
                historyIcon.style.transform = 'rotate(0deg)';
            }
        });
        
        const btnCancelPo = document.getElementById('btn-cancel-po');
        if (btnCancelPo) {
            btnCancelPo.addEventListener('click', function() {
                if (confirm('Apakah Anda yakin ingin membatalkan PO ini? Items yang belum diterima akan dibatalkan.')) {
                    this.textContent = 'Membatalkan...';
                    this.disabled = true;
                    
                    fetch('{{ route("admin.restock.cancel_order", $order["id"]) }}', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Gagal membatalkan PO'));
                            this.textContent = 'Batalkan PO';
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan sistem');
                        this.textContent = 'Batalkan PO';
                        this.disabled = false;
                    });
                }
            });
        }

        // Accept as-is buttons
        const acceptButtons = document.querySelectorAll('.btn-accept-as-is');
        acceptButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const restockId = this.getAttribute('data-restock-id');
                const row = this.closest('tr');
                const productName = row.querySelector('td:first-child').textContent.trim();
                
                if (confirm(`Terima item "${productName}" apa adanya?\n\nQty yang sudah diterima akan dianggap final.`)) {
                    this.textContent = 'Memproses...';
                    this.disabled = true;
                    
                    fetch('{{ route("admin.restock.accept_as_is", ["poId" => $order["id"], "restockId" => "__RESTOCK_ID__"]) }}'.replace('__RESTOCK_ID__', restockId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert(data.message || 'Gagal memproses');
                            this.textContent = '✓ Terima Apa Adanya';
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan sistem');
                        this.textContent = '✓ Terima Apa Adanya';
                        this.disabled = false;
                    });
                }
            });
        });
    });
</script>
@endpush
