@extends('admin.layout')

@section('content')
@php
    \Carbon\Carbon::setLocale('id');
    $rackMap = [];
    foreach(($racks ?? []) as $r){ $rackMap[(string)($r['id'] ?? '')] = (string)($r['name'] ?? $r['rack_name'] ?? '-'); }
    $visible = array_slice($events ?? [], 0, 50);

    // Kind palette
    $kindColor  = ['movement'=>'#2563eb','restock_request'=>'#d97706','purchase_order'=>'#059669','anomaly'=>'#dc2626'];
    $kindIcon   = ['movement'=>'📊','restock_request'=>'📝','purchase_order'=>'📦','anomaly'=>'⚠️'];
    $kindLabel  = ['movement'=>'Pergerakan','restock_request'=>'Restock','purchase_order'=>'PO','anomaly'=>'Anomali'];

    // Status badge map
    $statusPill = [
        'pending'   => ['bg'=>'var(--color-warning-bg)','color'=>'var(--color-warning)','border'=>'var(--color-warning-border)'],
        'approved'  => ['bg'=>'var(--color-success-bg)','color'=>'var(--color-success)','border'=>'var(--color-success-border)'],
        'rejected'  => ['bg'=>'var(--color-danger-bg)','color'=>'var(--color-danger)','border'=>'var(--color-danger-border)'],
        'fulfilled' => ['bg'=>'var(--color-info-bg)','color'=>'var(--color-info)','border'=>'var(--color-info-border)'],
        'draft'     => ['bg'=>'rgba(100,116,139,.1)','color'=>'#64748b','border'=>'rgba(100,116,139,.3)'],
        'ordered'   => ['bg'=>'var(--color-info-bg)','color'=>'var(--color-info)','border'=>'var(--color-info-border)'],
        'partial'   => ['bg'=>'var(--color-warning-bg)','color'=>'var(--color-warning)','border'=>'var(--color-warning-border)'],
        'received'  => ['bg'=>'var(--color-success-bg)','color'=>'var(--color-success)','border'=>'var(--color-success-border)'],
        'cancelled' => ['bg'=>'var(--color-danger-bg)','color'=>'var(--color-danger)','border'=>'var(--color-danger-border)'],
    ];
    $severityPill = [
        'low'      => ['bg'=>'rgba(100,116,139,.1)','color'=>'#64748b','border'=>'rgba(100,116,139,.3)'],
        'medium'   => ['bg'=>'var(--color-warning-bg)','color'=>'var(--color-warning)','border'=>'var(--color-warning-border)'],
        'high'     => ['bg'=>'var(--color-danger-bg)','color'=>'var(--color-danger)','border'=>'var(--color-danger-border)'],
        'critical' => ['bg'=>'var(--color-danger-bg)','color'=>'var(--color-danger)','border'=>'var(--color-danger-border)'],
    ];

    if (!function_exists('auditFormatTs')) {
        function auditFormatTs($ts) {
            if (!$ts || !(int)$ts) return '-';
            return \Carbon\Carbon::createFromTimestamp((int)$ts)->format('d M Y, H:i');
        }
    }
    if (!function_exists('auditPillStyle')) {
        function auditPillStyle($map, $key, $default='') {
            $p = $map[$key] ?? null;
            if (!$p) return $default;
            return "display:inline-block;padding:2px 10px;border-radius:999px;font-size:0.78rem;font-weight:600;background:{$p['bg']};color:{$p['color']};border:1px solid {$p['border']};";
        }
    }
@endphp
<div class="card">
    <h2>📜 Riwayat Audit: {{ $product['name'] ?? '-' }}</h2>
    <div style="color:var(--color-text-muted)">Barcode: {{ $product['barcode'] ?? '-' }} · Unit: {{ $product['unit'] ?? '-' }} · Supplier: {{ $product['supplier_name'] ?? '-' }}</div>

    {{-- Stats cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:16px;">
        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:14px 16px;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);font-weight:600;margin-bottom:4px;">Stok Masuk 30 hari</div>
            <div style="font-size:1.6rem;font-weight:700;color:var(--color-text);line-height:1.1;">{{ (int)($stats['total_in'] ?? 0) }} <span style="font-size:0.85rem;font-weight:400;color:var(--color-text-muted);">pcs</span></div>
        </div>
        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:14px 16px;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);font-weight:600;margin-bottom:4px;">Stok Keluar 30 hari</div>
            <div style="font-size:1.6rem;font-weight:700;color:var(--color-text);line-height:1.1;">{{ (int)($stats['total_out'] ?? 0) }} <span style="font-size:0.85rem;font-weight:400;color:var(--color-text-muted);">pcs</span></div>
        </div>
        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:14px 16px;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);font-weight:600;margin-bottom:4px;">Last Movement</div>
            <div style="font-size:1rem;font-weight:600;color:var(--color-text);">{{ !empty($stats['last_movement_at']) ? \Carbon\Carbon::createFromTimestamp((int)$stats['last_movement_at'])->diffForHumans() : '-' }}</div>
        </div>
        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:14px 16px;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);font-weight:600;margin-bottom:4px;">Active Restock</div>
            <div style="font-size:1.6rem;font-weight:700;color:var(--color-text);line-height:1.1;">{{ (int)($stats['active_restock_requests'] ?? 0) }}</div>
        </div>
        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:14px 16px;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);font-weight:600;margin-bottom:4px;">Open POs</div>
            <div style="font-size:1.6rem;font-weight:700;color:var(--color-text);line-height:1.1;">{{ (int)($stats['open_pos_containing'] ?? 0) }}</div>
        </div>
    </div>

    {{-- Rack pills --}}
    <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:12px 16px;margin-top:12px;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
        <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary);font-weight:600;">Rak Tempat Stok</span>
        @forelse(($stats['racks_holding'] ?? []) as $rid)
            <span style="display:inline-block;padding:4px 12px;border-radius:999px;background:var(--color-primary-bg);color:var(--color-primary);border:1px solid var(--color-primary);font-size:0.82rem;font-weight:600;">{{ $rackMap[$rid] ?? $rid }}</span>
        @empty
            <span style="color:var(--color-text-muted);">-</span>
        @endforelse
    </div>

    {{-- Filter bar — mirroring audit/index.blade.php pattern --}}
    <div style="background:var(--color-bg);padding:1rem;border-radius:var(--radius-md);border:1px solid var(--color-border);box-shadow:var(--shadow-sm);margin-top:16px;">
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <div style="display:flex;flex-direction:column;gap:0.25rem;flex:1;min-width:160px;">
                <label for="kind" style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">Jenis Event</label>
                <select id="kind" style="width:100%;padding:0.5rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg);color:var(--color-text);font-family:inherit;">
                    <option value="all">Semua Jenis</option>
                    <option value="movement">Pergerakan</option>
                    <option value="restock_request">Restock</option>
                    <option value="purchase_order">Purchase Order</option>
                    <option value="anomaly">Anomali</option>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:140px;">
                <label for="from" style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">Dari Tanggal</label>
                <input type="date" id="from" style="padding:0.5rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg);color:var(--color-text);font-family:inherit;">
            </div>
            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:140px;">
                <label for="to" style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">Sampai Tanggal</label>
                <input type="date" id="to" style="padding:0.5rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg);color:var(--color-text);font-family:inherit;">
            </div>
            <div style="display:flex;flex-direction:column;gap:0.25rem;flex:2;min-width:200px;">
                <label for="q" style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">Cari Ringkasan</label>
                <input id="q" placeholder="Ketik kata kunci..." style="width:100%;padding:0.5rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg);color:var(--color-text);font-family:inherit;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;padding-bottom:2px;">
                <button type="button" onclick="document.getElementById('kind').value='all';document.getElementById('from').value='';document.getElementById('to').value='';document.getElementById('q').value='';applyFilter();"
                    style="padding:0.5rem 1rem;background:transparent;color:var(--color-text-secondary);border:1px solid var(--color-border);border-radius:var(--radius-md);cursor:pointer;font-size:0.9rem;">Reset</button>
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div id="timeline" style="margin-top:16px;">
        @forelse(($events ?? []) as $ev)
            @php
                $k   = $ev['kind'] ?? '';
                $c   = $kindColor[$k]  ?? '#64748b';
                $ico = $kindIcon[$k]   ?? '•';
                $lbl = $kindLabel[$k]  ?? ucfirst($k);
                $ts  = (int)($ev['created_at'] ?? 0);
                $d   = $ev['data'] ?? [];

                // Per-kind field extraction
                if ($k === 'movement') {
                    $delta     = $d['delta']          ?? $d['delta_qty'] ?? null;
                    // Stok sesudah: prefer 'result', else to_qty / current_qty / actual_qty
                    $resultRaw = $d['result']  ?? $d['to_qty'] ?? $d['current_qty'] ?? $d['actual_qty'] ?? null;
                    $result    = $resultRaw !== null ? (int)$resultRaw : null;
                    // Stok sebelum: prefer 'prev', else compute (sesudah - delta)
                    if (array_key_exists('prev', $d)) {
                        $prev = (int) $d['prev'];
                    } elseif ($result !== null && $delta !== null) {
                        $prev = $result - (int)$delta;
                    } else {
                        $prev = null;
                    }
                    $movType   = $d['type']           ?? $d['movement_type'] ?? '-';
                    $rackId    = $d['rack_id']        ?? '-';
                    $rackName  = $d['rack_name']      ?? $ev['rack_name']  ?? ($rackMap[(string)$rackId] ?? $rackId);
                    $actor     = $d['actor_name']     ?? $d['waiter_name'] ?? $ev['actor_name'] ?? '-';
                    $dCreated  = $d['created_at']     ?? null;
                    $note      = $d['note']           ?? null;
                } elseif ($k === 'restock_request') {
                    $qtyNeeded   = $d['qty_needed']    ?? '-';
                    $receivedQty = $d['received_qty']  ?? null;
                    $reportedQty = $d['reported_qty']  ?? null;
                    $standardQty = $d['standard_qty']  ?? null;
                    $minQty      = $d['min_qty']       ?? null;
                    $rstStatus   = $d['status']        ?? '-';
                    $rackId      = $d['rack_id']       ?? '-';
                    $rackName    = $d['rack_name']     ?? ($rackMap[(string)$rackId] ?? $rackId);
                    $dCreated    = $d['created_at']    ?? $d['reported_at'] ?? null;
                    $reqBy       = $d['reported_by_name'] ?? null;
                    $category    = $d['product_category_name'] ?? null;
                } elseif ($k === 'purchase_order') {
                    $poNumber   = $d['po_number']     ?? '-';
                    $supplier   = $d['supplier_name'] ?? $d['supplier'] ?? '-';
                    $poStatus   = $d['status']        ?? '-';
                    $dCreated   = $d['created_at']    ?? null;
                    $createdBy  = $d['created_by_name'] ?? null;
                    // Items keyed by Firebase auto-id - filter to this product
                    $prodId     = $product['id'] ?? null;
                    $poItems    = array_filter((array)($d['items'] ?? []), fn($i) => is_array($i) && (string)($i['product_id'] ?? '') === (string)$prodId);
                    $sumOrdered  = array_sum(array_column($poItems, 'qty_ordered'));
                    $sumReceived = array_sum(array_column($poItems, 'received_qty'));
                    // Latest receive timestamp from this product's items
                    $lastReceiveAt = 0;
                    $lastReceiveBy = null;
                    foreach ($poItems as $it) {
                        $ts = (int)($it['last_received_at'] ?? 0);
                        if ($ts > $lastReceiveAt) {
                            $lastReceiveAt = $ts;
                            $lastReceiveBy = $it['last_received_by_name'] ?? null;
                        }
                    }
                } elseif ($k === 'anomaly') {
                    $prev       = $d['prev']          ?? '-';
                    $result     = $d['result']        ?? '-';
                    $severity   = $d['severity']      ?? '-';
                    $rackId     = $d['rack_id']       ?? '-';
                    $rackName   = $d['rack_name']     ?? ($rackMap[(string)$rackId] ?? $rackId);
                    $actor      = $d['actor_name']    ?? $ev['actor_name'] ?? '-';
                    $dCreated   = $d['created_at']    ?? null;
                    $reason     = $d['reason']        ?? null;
                }
            @endphp
            <div class="evt" data-kind="{{ $k }}" data-created-at="{{ $ts }}" data-summary="{{ strtolower($ev['summary'] ?? '') }}"
                 style="display:none;border-left:4px solid {{ $c }};padding:12px 14px;margin:8px 0;border-radius:0 var(--radius-sm) var(--radius-sm) 0;background:var(--color-bg);border-top:1px solid var(--color-border);border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);transition:box-shadow .15s;">
                {{-- Kind pill + timestamp --}}
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:6px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;background:{{ $c }}18;color:{{ $c }};border:1px solid {{ $c }}40;">{{ $ico }} {{ $lbl }}</span>
                    <span style="font-size:0.82rem;color:var(--color-text-secondary);">{{ $ts > 0 ? date('d M Y H:i', $ts) : '-' }}</span>
                    @if($ts > 0)
                        <span style="font-size:0.78rem;color:var(--color-text-muted);">({{ \Carbon\Carbon::createFromTimestamp($ts)->diffForHumans() }})</span>
                    @endif
                </div>
                {{-- Summary --}}
                <div style="font-weight:600;color:var(--color-text);margin-bottom:8px;">{{ $ev['summary'] ?? '-' }}</div>

                {{-- Structured detail --}}
                <details>
                    <summary style="color:var(--color-primary);font-size:0.85rem;cursor:pointer;user-select:none;outline:none;list-style:none;">Lihat detail</summary>
                    <div style="margin-top:10px;padding:10px 12px;background:rgba(0,0,0,0.02);border-radius:var(--radius-sm);border:1px solid var(--color-border);">
                        <dl style="display:grid;grid-template-columns:minmax(120px,auto) 1fr;gap:4px 12px;margin:0;font-size:0.85rem;">

                            @if($k === 'movement')
                                <dt style="color:var(--color-text-secondary);font-weight:500;">Stok Sebelum</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $prev !== null ? $prev : '-' }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Stok Sesudah</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $result !== null ? $result : '-' }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Delta</dt>
                                <dd style="margin:0;font-weight:600;color:{{ $delta !== null && (int)$delta >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                                    {{ $delta !== null ? sprintf('%+d', (int)$delta) : '-' }}
                                </dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Tipe</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $movType }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Rak</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $rackName }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Pelaku</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $actor }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Waktu</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $dCreated ? auditFormatTs($dCreated) : '-' }}</dd>

                                @if($note)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Catatan</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $note }}</dd>
                                @endif

                            @elseif($k === 'restock_request')
                                <dt style="color:var(--color-text-secondary);font-weight:500;">Qty Dibutuhkan</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $qtyNeeded }}</dd>

                                @if($reportedQty !== null)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Qty Saat Lapor</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $reportedQty }}</dd>
                                @endif

                                @if($receivedQty !== null && (int)$receivedQty > 0)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Qty Diterima</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $receivedQty }}</dd>
                                @endif

                                @if($standardQty !== null)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Standar / Min</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $standardQty }}{{ $minQty !== null ? ' / ' . $minQty : '' }}</dd>
                                @endif

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Status</dt>
                                <dd style="margin:0;">
                                    <span style="{{ auditPillStyle($statusPill, $rstStatus) }}">{{ $rstStatus }}</span>
                                </dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Rak</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $rackName }}</dd>

                                @if($category)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Kategori</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $category }}</dd>
                                @endif

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Dilaporkan</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $dCreated ? auditFormatTs($dCreated) : '-' }}</dd>

                                @if($reqBy)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Pelapor</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $reqBy }}</dd>
                                @endif

                            @elseif($k === 'purchase_order')
                                <dt style="color:var(--color-text-secondary);font-weight:500;">No. PO</dt>
                                <dd style="margin:0;color:var(--color-text);font-family:monospace;">{{ $poNumber }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Supplier</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $supplier }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Status</dt>
                                <dd style="margin:0;">
                                    <span style="{{ auditPillStyle($statusPill, $poStatus) }}">{{ $poStatus }}</span>
                                </dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Qty Dipesan</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $sumOrdered > 0 ? $sumOrdered : '-' }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Qty Diterima</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $sumReceived > 0 ? $sumReceived : '-' }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Dibuat</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $dCreated ? auditFormatTs($dCreated) : '-' }}</dd>

                                @if($createdBy)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Dibuat oleh</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $createdBy }}</dd>
                                @endif

                                @if($lastReceiveAt > 0)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Terakhir Diterima</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ auditFormatTs($lastReceiveAt) }}{{ $lastReceiveBy ? ' oleh ' . $lastReceiveBy : '' }}</dd>
                                @endif

                            @elseif($k === 'anomaly')
                                <dt style="color:var(--color-text-secondary);font-weight:500;">Stok Sebelum</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $prev }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Stok Sesudah</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $result }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Severity</dt>
                                <dd style="margin:0;">
                                    <span style="{{ auditPillStyle($severityPill, $severity) }}">{{ $severity }}</span>
                                </dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Rak</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $rackName }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Pelaku</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $actor }}</dd>

                                <dt style="color:var(--color-text-secondary);font-weight:500;">Waktu</dt>
                                <dd style="margin:0;color:var(--color-text);">{{ $dCreated ? auditFormatTs($dCreated) : '-' }}</dd>

                                @if($reason)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">Alasan</dt>
                                    <dd style="margin:0;color:var(--color-text);">{{ $reason }}</dd>
                                @endif

                            @else
                                {{-- Unknown kind: flat key-value --}}
                                @foreach((array)$d as $dKey => $dVal)
                                    <dt style="color:var(--color-text-secondary);font-weight:500;">{{ ucfirst(str_replace('_',' ',$dKey)) }}</dt>
                                    <dd style="margin:0;color:var(--color-text);">
                                        @if(is_null($dVal))
                                            -
                                        @elseif(is_bool($dVal))
                                            {{ $dVal ? '✓' : '✗' }}
                                        @elseif(is_array($dVal))
                                            @foreach($dVal as $subK => $subV)
                                                <span style="display:block;padding-left:8px;font-size:0.8rem;color:var(--color-text-secondary);">{{ ucfirst(str_replace('_',' ',$subK)) }}: <span style="color:var(--color-text);">{{ is_scalar($subV) ? $subV : '-' }}</span></span>
                                            @endforeach
                                        @else
                                            {{ $dVal }}
                                        @endif
                                    </dd>
                                @endforeach
                            @endif

                        </dl>
                    </div>
                </details>
            </div>
        @empty
            <div style="padding:2rem;text-align:center;color:var(--color-text-secondary);">Belum ada riwayat audit untuk produk ini.</div>
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
