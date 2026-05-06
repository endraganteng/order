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
                        <div class="po-meta">Supplier: {{ $order['supplier'] ?: '-' }}</div>
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
                    @foreach($order['items'] as $item)
                        @php
                            $remaining = $item['qty_ordered'] - $item['qty_received'];
                            $isCompleted = $remaining <= 0 || $item['received'];
                        @endphp
                        
                        <div class="item-card {{ $isCompleted ? 'completed' : '' }}" id="item-{{ $item['restock_id'] }}">
                            <div class="item-header">
                                <div class="item-name">{{ $item['product_name'] }}</div>
                                <div class="item-rack">{{ $item['rack_name'] }}</div>
                            </div>
                            
                            <div class="item-stats">
                                <div class="stat-box">Dipesan: <strong>{{ $item['qty_ordered'] }}</strong></div>
                                <div class="stat-box">Diterima: <strong class="received-val">{{ $item['qty_received'] }}</strong></div>
                            </div>
                            
                            @if($isCompleted)
                                <div class="completed-msg">✅ Item sudah diterima penuh</div>
                            @else
                                <div class="receive-action">
                                    <input type="number" class="qty-input" value="{{ $remaining }}" min="1" max="{{ $remaining }}" id="input-{{ $item['restock_id'] }}">
                                    <button class="btn-receive" onclick="receiveItem({{ $order['id'] }}, {{ $item['restock_id'] }}, {{ $remaining }})">
                                        Terima
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
    function showFlash(message) {
        const flash = document.getElementById('flash-message');
        flash.textContent = message;
        flash.style.display = 'block';
        setTimeout(() => {
            flash.style.display = 'none';
        }, 3000);
    }

    function receiveItem(poId, restockId, maxQty) {
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
        
        const btn = input.nextElementSibling;
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
                received_qty: qty
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showFlash('Penerimaan dicatat!');
                
                // Update UI visually
                const itemCard = document.getElementById(`item-${restockId}`);
                const currentReceived = parseInt(itemCard.querySelector('.received-val').textContent);
                const newReceived = currentReceived + qty;
                
                itemCard.querySelector('.received-val').textContent = newReceived;
                
                // If fully received
                if (data.item_completed) {
                    itemCard.classList.add('completed');
                    itemCard.querySelector('.receive-action').innerHTML = '<div class="completed-msg">✅ Item sudah diterima penuh</div>';
                } else {
                    // Update remaining in input
                    const newMax = maxQty - qty;
                    input.value = newMax;
                    input.max = newMax;
                    input.setAttribute('onchange', `if(this.value > ${newMax}) this.value = ${newMax}`);
                    
                    // Update onclick handler
                    btn.setAttribute('onclick', `receiveItem(${poId}, ${restockId}, ${newMax})`);
                    btn.disabled = false;
                    btn.textContent = 'Terima';
                }
                
                // If PO is fully completed, reload page to refresh state properly
                if (data.po_completed) {
                    setTimeout(() => window.location.reload(), 1500);
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
</script>

</body>
</html>