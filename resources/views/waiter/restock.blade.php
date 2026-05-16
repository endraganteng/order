<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Penerimaan Barang PO</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f3f6fb;
            color: #273444;
            padding: 20px;
        }
        .wrap { max-width: 600px; margin: 0 auto; }
        
        .header {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .back-btn {
            text-decoration: none;
            color: #475569;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f1f5f9;
        }
        
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }
        
        .po-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 4px solid #3b82f6;
        }
        
        .po-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .po-title {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #1e293b;
        }
        
        .po-meta {
            font-size: 13px;
            color: #64748b;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #3b82f6;
            transition: width 0.3s ease;
        }
        
        .item-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            background: #f8fafc;
        }
        
        .item-card.completed {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .item-name {
            font-weight: 600;
            color: #334155;
            font-size: 15px;
        }
        
        .item-rack {
            font-size: 12px;
            color: #64748b;
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .item-stats {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #475569;
            margin-bottom: 12px;
        }
        
        .stat-box {
            background: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .receive-action {
            display: flex;
            gap: 10px;
        }
        
        .qty-input {
            width: 80px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-align: center;
            font-size: 15px;
        }
        
        .btn-receive {
            flex: 1;
            background: #22c55e;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            padding: 8px;
            font-size: 14px;
        }
        
        .btn-receive:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .completed-msg {
            color: #16a34a;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .empty-state {
            background: #fff;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        #flash-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #22c55e;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }
    </style>
</head>
<body>
@include('partials.firebase-rtdb-client')

<div id="flash-message">Berhasil disimpan!</div>

<div class="wrap">
    <div class="header">
        <a href="{{ url('/waiter/tasks') }}" class="back-btn">&larr;</a>
        <h1>📦 Penerimaan Barang</h1>
    </div>

    @if(empty($activeOrders))
        <div class="empty-state">
            <div style="font-size: 40px; margin-bottom: 15px;">🎉</div>
            <h3>Semua Beres!</h3>
            <p>Tidak ada purchase order yang menunggu penerimaan barang saat ini.</p>
        </div>
    @else
        @foreach($activeOrders as $order)
            @php
                $progressPercent = $order['items_count'] > 0 ? round(($order['received_count'] / $order['items_count']) * 100) : 0;
            @endphp
            
            <div class="po-card" id="po-{{ $order['id'] }}">
                <div class="po-header">
                    <div>
                        <h3 class="po-title">{{ $order['po_number'] }}</h3>
                        <div class="po-meta">Supplier: {{ $order['supplier'] ?? '-' }}</div>
                        <div class="po-meta">Tgl: {{ date('d M Y H:i', $order['created_at']) }}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 600; color: #3b82f6;" class="po-progress-text">{{ $order['received_count'] }}/{{ $order['items_count'] }} Item</div>
                        <div style="font-size: 12px; color: #64748b;">Diterima</div>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $progressPercent }}%;"></div>
                </div>
                
                <div style="margin-top: 15px;">
                    @foreach(($order['items'] ?? []) as $restockKey => $item)
                        @php
                            $qtyOrdered = $item['qty_ordered'] ?? 0;
                            $qtyReceived = $item['qty_received'] ?? $item['received_qty'] ?? 0;
                            $remaining = $qtyOrdered - $qtyReceived;
                            $isCompleted = $remaining <= 0 || !empty($item['received']);
                            $itemRestockId = $item['restock_id'] ?? $restockKey;
                        @endphp
                        
                        <div class="item-card {{ $isCompleted ? 'completed' : '' }}" id="item-{{ $itemRestockId }}">
                            <div class="item-header">
                                <div class="item-name">{{ $item['product_name'] ?? '-' }}</div>
                                <div class="item-rack">{{ $item['rack_name'] ?? '-' }}</div>
                            </div>
                            
                            <div class="item-stats">
                                <div class="stat-box">Dipesan: <strong>{{ $qtyOrdered }}</strong></div>
                                <div class="stat-box">Diterima: <strong class="received-val">{{ $qtyReceived }}</strong></div>
                            </div>
                            
                            @if(!empty($item['issue']))
                                <div style="margin-top:8px;padding:6px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#dc2626;font-weight:500;">
                                    ⚠️ Dilaporkan: {{ $item['issue']['note'] ?? '-' }}
                                    <span style="color:#94a3b8;font-weight:400;margin-left:6px;">oleh {{ $item['issue']['reported_by_name'] ?? '-' }}</span>
                                </div>
                            @endif

                            @if($isCompleted)
                                <div class="completed-msg">✅ Item sudah diterima penuh</div>
                            @else
                                <div class="receive-action">
                                    <input type="number" class="qty-input" value="{{ $remaining }}" min="1" max="{{ $remaining }}" id="input-{{ $itemRestockId }}">
                                    <button class="btn-receive" onclick="receiveItem('{{ $order['id'] }}', '{{ $itemRestockId }}', {{ $remaining }})">
                                        Terima
                                    </button>
                                </div>
                                <div style="margin-top: 8px;">
                                    <button style="background:none;border:1px solid #fca5a5;color:#dc2626;border-radius:6px;padding:6px 10px;font-size:12px;cursor:pointer;font-weight:500;" onclick="reportIssue('{{ $order['id'] }}', '{{ $itemRestockId }}', '{{ addslashes($item['product_name'] ?? '') }}')">
                                        ⚠️ Laporkan Masalah
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>

<script>
    // Bandwidth: limitToLast(20) — PO terbaru saja yang relevan untuk halaman ini.
    // PO completed/cancelled lama tidak dipantau real-time.
    (function setupRestockListener() {
        if (!window.RTDB_READY || !window.firebaseDB) return;
        const debounceMs = 1500; // longer debounce: PO update lebih jarang
        let pending = false;
        const trigger = () => {
            if (pending) return;
            pending = true;
            setTimeout(() => {
                pending = false;
                window.location.reload();
            }, debounceMs);
        };
        try {
            window.firebaseDB.ref('purchase_orders').limitToLast(20).on('value', trigger);
        } catch (e) {
            console.warn('[RTDB] restock listener failed:', e);
        }
    })();

    const poReceiveFormInstanceByPo = new Map();

    const newFormInstanceId = () => {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
        return Math.random().toString(36).slice(2) + Date.now().toString(36);
    };

    // ─── Draft autosave helpers ───
    const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;
    function saveDraftLocal(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify({ data: data, saved_at: Date.now() }));
        } catch (e) { console.warn('[draft] save failed:', e); }
    }
    function loadDraftLocal(key) {
        try {
            const stored = localStorage.getItem(key);
            if (!stored) return null;
            const parsed = JSON.parse(stored);
            if (!parsed || typeof parsed !== 'object') return null;
            const savedAt = parseInt(parsed.saved_at || 0, 10);
            if (Date.now() - savedAt > DRAFT_TTL_MS) { localStorage.removeItem(key); return null; }
            return parsed.data || null;
        } catch (e) { return null; }
    }
    function clearDraftLocal(key) {
        try { localStorage.removeItem(key); } catch (e) {}
    }
    const _draftSaveTimers = {};
    function debounceSaveDraft(key, getDataFn) {
        clearTimeout(_draftSaveTimers[key]);
        _draftSaveTimers[key] = setTimeout(() => {
            const data = getDataFn();
            if (data && Object.keys(data).length > 0) saveDraftLocal(key, data);
        }, 500);
    }
    function poDraftKey(poId) { return `waiter_draft:po_receive:${poId}`; }

    // Restore all PO item drafts on page load
    (function restoreAllPoDrafts() {
        document.querySelectorAll('.po-card').forEach(poCard => {
            const poId = (poCard.id || '').replace('po-', '');
            if (!poId) return;
            const draft = loadDraftLocal(poDraftKey(poId));
            if (!draft || !draft.qty) return;
            Object.entries(draft.qty).forEach(([restockId, qty]) => {
                const input = document.getElementById(`input-${restockId}`);
                if (input && !input.closest('.item-card.completed')) {
                    input.value = qty;
                }
            });
        });
    })();

    // Attach input listeners to all qty inputs for draft save
    (function attachPoQtyListeners() {
        document.querySelectorAll('.qty-input').forEach(input => {
            const itemCard = input.closest('.item-card');
            const poCard = input.closest('.po-card');
            if (!itemCard || !poCard || itemCard.classList.contains('completed')) return;
            const poId = (poCard.id || '').replace('po-', '');
            const restockId = (itemCard.id || '').replace('item-', '');
            if (!poId || !restockId) return;

            input.addEventListener('input', () => {
                debounceSaveDraft(poDraftKey(poId), () => {
                    const qtyMap = {};
                    poCard.querySelectorAll('.qty-input').forEach(inp => {
                        const iCard = inp.closest('.item-card');
                        if (!iCard || iCard.classList.contains('completed')) return;
                        const rId = (iCard.id || '').replace('item-', '');
                        const v = parseFloat(inp.value);
                        if (rId && v > 0) qtyMap[rId] = v;
                    });
                    return { qty: qtyMap };
                });
            });
        });
    })();

    function showFlash(message) {
        const flash = document.getElementById('flash-message');
        flash.textContent = message;
        flash.style.display = 'block';
        setTimeout(() => {
            flash.style.display = 'none';
        }, 3000);
    }

    function receiveItem(poId, restockId, maxQty) {
        const poKey = String(poId || '');
        if (poKey && !poReceiveFormInstanceByPo.has(poKey)) {
            poReceiveFormInstanceByPo.set(poKey, newFormInstanceId());
        }
        const input = document.getElementById(`input-${restockId}`);
        let qty = parseInt(input.value);
        
        if (isNaN(qty) || qty < 1) {
            alert('Jumlah tidak valid');
            return;
        }
        
        if (qty > maxQty) {
            alert(`Maksimal yang bisa diterima adalah ${maxQty}`);
            input.value = maxQty;
            qty = maxQty;
        }
        
        const btn = input.closest('.receive-action').querySelector('.btn-receive');
        btn.disabled = true;
        btn.textContent = '...';
        
        fetch(`/waiter/restock/${poId}/receive`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                restock_id: restockId,
                received_qty: qty,
                idempotency_key: `po-receive:${poId}:${poReceiveFormInstanceByPo.get(poKey) || newFormInstanceId()}`
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showFlash('✅ Penerimaan dicatat! (' + qty + ' unit)');
                
                const itemCard = document.getElementById(`item-${restockId}`);
                const receivedEl = itemCard.querySelector('.received-val');
                const newReceived = data.new_received_qty || (parseInt(receivedEl.textContent) + qty);
                receivedEl.textContent = newReceived;
                
                // Update progress bar for this PO
                const poCard = itemCard.closest('.po-card');
                if (poCard && data.received_count !== undefined) {
                    const progressText = poCard.querySelector('.po-progress-text');
                    if (progressText) progressText.textContent = data.received_count + '/' + data.total_items + ' Item';
                    const progressFill = poCard.querySelector('.progress-fill');
                    if (progressFill) progressFill.style.width = Math.round((data.received_count / data.total_items) * 100) + '%';
                }
                
                if (data.item_completed) {
                    itemCard.classList.add('completed');
                    const receiveAction = itemCard.querySelector('.receive-action');
                    if (receiveAction) receiveAction.innerHTML = '<div class="completed-msg">✅ Item sudah diterima penuh</div>';
                    // Clear just this item from draft
                    if (poCard) {
                        const poId = (poCard.id || '').replace('po-', '');
                        const draft = loadDraftLocal(poDraftKey(poId)) || { qty: {} };
                        delete draft.qty[restockId];
                        if (Object.keys(draft.qty).length > 0) saveDraftLocal(poDraftKey(poId), draft);
                        else clearDraftLocal(poDraftKey(poId));
                    }
                } else {
                    const newMax = (data.qty_ordered || maxQty) - newReceived;
                    input.value = newMax > 0 ? newMax : 1;
                    input.max = newMax > 0 ? newMax : 1;
                    btn.setAttribute('onclick', `receiveItem('${poId}', '${restockId}', ${newMax})`);
                    btn.disabled = false;
                    btn.textContent = 'Terima';
                }
                
                if (data.po_completed) {
                    const poId2 = String(poId || '');
                    poReceiveFormInstanceByPo.delete(poId2);
                    clearDraftLocal(poDraftKey(poId2));
                    showFlash('🎉 PO selesai! Semua barang sudah diterima.');
                    setTimeout(() => window.location.reload(), 2000);
                }
            } else {
                alert('Error: ' + (data.message || 'Gagal menyimpan'));
                btn.disabled = false;
                btn.textContent = 'Terima';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan sistem');
            btn.disabled = false;
            btn.textContent = 'Terima';
        });
    }

    function reportIssue(poId, restockId, productName) {
        const reason = prompt(`Laporkan masalah untuk "${productName}":\n\n1 = Barang tidak datang (qty = 0)\n2 = Qty tidak sesuai\n3 = Barang rusak/cacat\n\nPilih (1/2/3) atau ketik alasan:`);
        if (!reason) return;

        let note = reason;
        if (reason === '1') note = 'Barang tidak datang';
        else if (reason === '2') note = 'Qty tidak sesuai pesanan';
        else if (reason === '3') note = 'Barang rusak/cacat';

        if (note === 'Barang tidak datang' && !confirm('Konfirmasi: Item ini akan ditandai TIDAK DITERIMA (qty = 0) dan ditutup. Lanjutkan?')) {
            return;
        }

        fetch(`/waiter/restock/${poId}/report-issue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                restock_id: restockId,
                issue_note: note
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const itemCard = document.getElementById(`item-${restockId}`);

                if (data.item_closed) {
                    // Item auto-closed (barang tidak datang)
                    showFlash('❌ Item ditandai tidak diterima');
                    if (itemCard) {
                        itemCard.classList.add('completed');
                        const receiveAction = itemCard.querySelector('.receive-action');
                        if (receiveAction) receiveAction.remove();
                        const reportBtn = itemCard.querySelector('[onclick*="reportIssue"]');
                        if (reportBtn) reportBtn.parentElement.remove();
                        const closedMsg = document.createElement('div');
                        closedMsg.className = 'completed-msg';
                        closedMsg.style.cssText = 'background:#fef2f2;color:#dc2626;border-color:#fecaca;';
                        closedMsg.textContent = '❌ Barang tidak datang — ditutup';
                        itemCard.appendChild(closedMsg);
                    }

                    if (data.po_completed) {
                        showFlash('PO selesai (semua item sudah diproses).');
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } else {
                    // Issue reported only (qty tidak sesuai, rusak)
                    showFlash('⚠️ Masalah dilaporkan ke supervisor');
                    if (itemCard) {
                        const issueTag = document.createElement('div');
                        issueTag.style.cssText = 'margin-top:8px;padding:6px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#dc2626;font-weight:500;';
                        issueTag.textContent = '⚠️ Dilaporkan: ' + note;
                        itemCard.appendChild(issueTag);
                    }
                }
            } else {
                alert('Error: ' + (data.message || 'Gagal melaporkan'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan sistem');
        });
    }
</script>

</body>
</html>
