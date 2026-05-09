@extends('admin.layout')

@section('title', 'Purchase Orders - Admin')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #1e293b; font-size: clamp(24px, 5vw, 32px); font-weight: 800;">📋 Purchase Orders</h2>
        <a href="{{ route('admin.restock.index') }}" class="btn" style="background: white; border: 1px solid var(--color-border); color: var(--color-text);">📦 Daftar Restock</a>
    </div>
</div>

<div style="background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 20px;">
    
    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 15px;">
        <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn" style="background: {{ is_null($status) ? 'var(--color-primary)' : 'var(--color-bg)' }}; color: {{ is_null($status) ? 'white' : 'var(--color-text)' }}; border: 1px solid {{ is_null($status) ? 'var(--color-primary)' : 'var(--color-border)' }};">Semua</a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'ordered']) }}" class="btn" style="background: {{ $status === 'ordered' ? 'var(--color-info)' : 'var(--color-bg)' }}; color: {{ $status === 'ordered' ? 'white' : 'var(--color-text)' }}; border: 1px solid {{ $status === 'ordered' ? 'var(--color-info)' : 'var(--color-border)' }};">Ordered</a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'partial']) }}" class="btn" style="background: {{ $status === 'partial' ? 'var(--color-warning)' : 'var(--color-bg)' }}; color: {{ $status === 'partial' ? 'white' : 'var(--color-text)' }}; border: 1px solid {{ $status === 'partial' ? 'var(--color-warning)' : 'var(--color-border)' }};">Partial</a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}" class="btn" style="background: {{ $status === 'completed' ? 'var(--color-success)' : 'var(--color-bg)' }}; color: {{ $status === 'completed' ? 'white' : 'var(--color-text)' }}; border: 1px solid {{ $status === 'completed' ? 'var(--color-success)' : 'var(--color-border)' }};">Completed</a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'cancelled']) }}" class="btn" style="background: {{ $status === 'cancelled' ? 'var(--color-danger)' : 'var(--color-bg)' }}; color: {{ $status === 'cancelled' ? 'white' : 'var(--color-text)' }}; border: 1px solid {{ $status === 'cancelled' ? 'var(--color-danger)' : 'var(--color-border)' }};">Cancelled</a>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--color-bg); text-align: left; border-bottom: 2px solid var(--color-border);">
                    <th style="padding: 12px;">PO Number</th>
                    <th style="padding: 12px;">Tanggal</th>
                    <th style="padding: 12px;">Supplier</th>
                    <th style="padding: 12px; text-align: center;">Items</th>
                    <th style="padding: 12px; text-align: center;">Progress</th>
                    <th style="padding: 12px; text-align: center;">Status</th>
                    <th style="padding: 12px; text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px; font-weight: bold;">{{ $order['po_number'] }}</td>
                    <td style="padding: 12px; color: var(--color-text-secondary);">{{ date('d M Y H:i', $order['created_at']) }}</td>
                    <td style="padding: 12px; color: var(--color-text-secondary);">{{ $order['supplier'] ?? '-' }}</td>
                    <td style="padding: 12px; text-align: center;">{{ $order['items_count'] }}</td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="font-weight: 500; color: {{ $order['received_count'] == $order['items_count'] ? 'var(--color-success)' : 'var(--color-text)' }};">
                            {{ $order['received_count'] }}/{{ $order['items_count'] }}
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        @if($order['status'] === 'ordered')
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: var(--color-info-bg); color: var(--color-info);">Ordered</span>
                        @elseif($order['status'] === 'partial')
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: var(--color-warning-bg); color: var(--color-warning);">Partial</span>
                        @elseif($order['status'] === 'completed')
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: var(--color-success-bg); color: var(--color-success);">Completed</span>
                        @elseif($order['status'] === 'cancelled')
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: var(--color-danger-bg); color: var(--color-danger);">Cancelled</span>
                        @endif
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <a href="{{ route('admin.restock.order_detail', $order['id']) }}" class="btn" style="background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text); padding: 6px 12px; font-size: 13px;">Detail</a>
                        
                        @if(in_array($order['status'], ['ordered', 'partial']))
                        <button class="btn btn-cancel-po" data-id="{{ $order['id'] }}" data-po="{{ $order['po_number'] }}" style="background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); color: var(--color-danger); padding: 6px 12px; font-size: 13px; margin-left: 5px;">
                            Batal
                        </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="padding: 20px; text-align: center; color: var(--color-text-muted);">Tidak ada purchase order ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-cancel-po').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const po = this.getAttribute('data-po');
                
                if (confirm(`Apakah Anda yakin ingin membatalkan PO ${po}? Items yang belum diterima akan dibatalkan.`)) {
                    
                    this.textContent = '...';
                    this.disabled = true;
                    
                    fetch('{{ route("admin.restock.index") }}/' + id + '/cancel', {
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
                            this.textContent = 'Batal';
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan sistem');
                        this.textContent = 'Batal';
                        this.disabled = false;
                    });
                }
            });
        });
    });
</script>
@endpush
