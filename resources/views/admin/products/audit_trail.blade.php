@extends('admin.layout')

@section('content')
@php
    \Carbon\Carbon::setLocale('id');
    $rackMap = [];
    foreach(($racks ?? []) as $r){ $rackMap[(string)($r['id'] ?? '')] = (string)($r['name'] ?? $r['rack_name'] ?? '-'); }
    $visible = array_slice($events ?? [], 0, 50);
@endphp
<div class="card">
    <h2>📜 Riwayat Audit: {{ $product['name'] ?? '-' }}</h2>
    <div style="color:var(--color-text-muted)">Barcode: {{ $product['barcode'] ?? '-' }} · Unit: {{ $product['unit'] ?? '-' }} · Supplier: {{ $product['supplier_name'] ?? '-' }}</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:12px;">
        <div class="card">Stok Masuk 30 hari<br><b>{{ (int)($stats['total_in'] ?? 0) }} pcs</b></div>
        <div class="card">Stok Keluar 30 hari<br><b>{{ (int)($stats['total_out'] ?? 0) }} pcs</b></div>
        <div class="card">Last Movement<br><b>{{ !empty($stats['last_movement_at']) ? \Carbon\Carbon::createFromTimestamp((int)$stats['last_movement_at'])->diffForHumans() : '-' }}</b></div>
        <div class="card">Active Restock Requests<br><b>{{ (int)($stats['active_restock_requests'] ?? 0) }}</b></div>
        <div class="card">Open POs<br><b>{{ (int)($stats['open_pos_containing'] ?? 0) }}</b></div>
    </div>
    <div class="card" style="margin-top:10px;">Rak Tempat Stok:
        @forelse(($stats['racks_holding'] ?? []) as $rid)<span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;margin:4px;">{{ $rackMap[$rid] ?? $rid }}</span>@empty <span>-</span>@endforelse
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;">
        <select id="kind"><option value="all">All</option><option value="movement">Movement</option><option value="restock_request">Restock</option><option value="purchase_order">PO</option><option value="anomaly">Anomaly</option></select>
        <input type="date" id="from"><input type="date" id="to"><input id="q" placeholder="Cari ringkasan..." style="min-width:220px;">
    </div>
    <div id="timeline">
        @forelse(($events ?? []) as $ev)
            @php $k=$ev['kind']??''; $c=['movement'=>'#2563eb','restock_request'=>'#d97706','purchase_order'=>'#059669','anomaly'=>'#dc2626'][$k]??'#64748b'; $icon=['movement'=>'📊','restock_request'=>'📝','purchase_order'=>'📦','anomaly'=>'⚠️'][$k]??'•'; $ts=(int)($ev['created_at']??0); @endphp
            <div class="evt" data-kind="{{ $k }}" data-created-at="{{ $ts }}" data-summary="{{ strtolower($ev['summary'] ?? '') }}" style="display:none;border-left:4px solid {{ $c }};padding:8px 10px;margin:8px 0;">
                <div><b>{{ $icon }} {{ date('d M Y H:i',$ts) }}</b> ({{ $ts>0 ? \Carbon\Carbon::createFromTimestamp($ts)->diffForHumans() : '-' }})</div>
                <div><b>{{ $ev['summary'] ?? '-' }}</b></div>
                <details><summary>Detail mentah</summary><pre style="white-space:pre-wrap">{{ json_encode($ev['data'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
            </div>
        @empty
            <div>Belum ada riwayat audit untuk produk ini.</div>
        @endforelse
    </div>
    <button id="showMore" class="btn" style="margin-top:8px;">Show more</button>
</div>
<script>
let visibleCount=50;
function applyFilter(){const k=document.getElementById('kind').value,f=document.getElementById('from').value?new Date(document.getElementById('from').value+'T00:00:00').getTime()/1000:0,t=document.getElementById('to').value?new Date(document.getElementById('to').value+'T23:59:59').getTime()/1000:9999999999,q=(document.getElementById('q').value||'').toLowerCase();let shown=0;document.querySelectorAll('.evt').forEach(el=>{const okK=k==='all'||el.dataset.kind===k,ts=parseInt(el.dataset.createdAt||'0',10),okD=ts>=f&&ts<=t,okQ=(el.dataset.summary||'').includes(q),ok=okK&&okD&&okQ; if(ok&&shown<visibleCount){el.style.display='block';shown++;}else{el.style.display='none';}});}
document.getElementById('kind').addEventListener('change',applyFilter);document.getElementById('from').addEventListener('change',applyFilter);document.getElementById('to').addEventListener('change',applyFilter);document.getElementById('q').addEventListener('input',applyFilter);document.getElementById('showMore').addEventListener('click',()=>{visibleCount+=50;applyFilter();});applyFilter();
</script>
@endsection
