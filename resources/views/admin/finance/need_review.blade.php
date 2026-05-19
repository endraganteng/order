@extends('admin.layout')

@section('title', 'Data Perlu Review')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">⚠️ Data Perlu Review</h1>
            <p class="fm-page-subtitle">Item pengeluaran dari sync yang belum memiliki mapping kategori</p>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    @if($items['total'] > 0)
    <div class="fm-alert fm-alert-warning">
        {{ $items['total'] }} item perlu ditinjau. Pilih kategori atau abaikan.
    </div>
    @endif

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Tanggal</th><th>Tipe</th><th>Deskripsi</th><th>Supplier</th><th style="text-align:right">Total</th><th>Aksi</th></tr></thead>
            <tbody id="reviewBody">
                @foreach($items['data'] as $item)
                <tr data-id="{{ $item['id'] }}">
                    <td>{{ \Carbon\Carbon::parse($item['tanggal'])->format('d/m/Y') }}</td>
                    <td><span class="fm-badge fm-badge-draft">{{ $item['line_type'] }}</span></td>
                    <td>{{ $item['deskripsi'] }}</td>
                    <td>{{ $item['supplier'] ?? '—' }}</td>
                    <td style="text-align:right" class="fm-money expense">Rp {{ number_format($item['total'], 0, ',', '.') }}</td>
                    <td style="white-space:nowrap;">
                        <select class="fm-select" style="width:140px;display:inline-block;font-size:12px;padding:4px 8px;" data-cat-select="{{ $item['id'] }}">
                            <option value="">-- Kategori --</option>
                            @foreach($categories as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                        <button class="fm-btn fm-btn-sm fm-btn-success" onclick="resolveItem({{ $item['id'] }}, 'resolve')">✅</button>
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="resolveItem({{ $item['id'] }}, 'ignore')">🚫</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($items['total'] === 0)
    <div class="fm-empty"><div class="fm-empty-icon">✅</div><div class="fm-empty-text">Semua data sudah di-review. Tidak ada yang pending.</div></div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

function showToast(msg, type='success'){
    const t=document.getElementById('toast');t.textContent=msg;t.className='fm-toast '+type+' show';setTimeout(()=>t.classList.remove('show'),3000);
}

async function resolveItem(id, action) {
    const catSelect = document.querySelector(`[data-cat-select="${id}"]`);
    const categoryId = catSelect ? catSelect.value : '';

    if (action === 'resolve' && !categoryId) {
        showToast('Pilih kategori terlebih dahulu', 'error');
        return;
    }

    try {
        const res = await fetch('{{ url("admin/finance/need-review") }}/' + id, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({action, finance_category_id: categoryId || null})
        });
        const data = await res.json();
        if (data.success) {
            showToast(action === 'resolve' ? 'Kategori ditetapkan!' : 'Item diabaikan!');
            document.querySelector(`tr[data-id="${id}"]`).remove();
        } else {
            showToast(data.message || 'Gagal', 'error');
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
}
</script>
@endpush
