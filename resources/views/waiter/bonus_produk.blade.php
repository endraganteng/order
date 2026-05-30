<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Produk - {{ $waiterName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #1e293b;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        .header-top { display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 1.1rem; font-weight: 700; }
        .back-btn {
            color: white; text-decoration: none; font-size: 1.1rem;
            padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.15);
            transition: background 0.2s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.25); }
        .header-name { font-size: 0.8rem; opacity: 0.85; }

        /* Container */
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }

        /* Summary Card */
        .summary-card {
            background: white;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
        .summary-item .label { font-size: 11px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px; }
        .summary-item .value { font-size: 1.3rem; font-weight: 800; margin-top: 2px; }
        .value-green { color: #059669; }
        .value-orange { color: #d97706; }
        .value-muted { color: #475569; }

        /* Tabs */
        .tab-bar {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .tab-btn {
            flex: 1; padding: 10px 8px; text-align: center;
            font-size: 0.78rem; font-weight: 600; color: #64748b;
            background: none; border: none; cursor: pointer;
            border-radius: 8px; transition: all 0.2s;
        }
        .tab-btn.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; box-shadow: 0 2px 8px rgba(102,126,234,0.3); }

        /* Section */
        .section-title { font-size: 0.82rem; font-weight: 700; color: #475569; margin-bottom: 10px; padding-left: 2px; }

        /* Product Card */
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #667eea;
            transition: transform 0.15s;
        }
        .product-card:active { transform: scale(0.98); }
        .product-card .info h4 { font-size: 0.88rem; color: #1e293b; font-weight: 600; margin-bottom: 3px; }
        .product-card .info .meta { font-size: 0.72rem; color: #94a3b8; }
        .points-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 5px 12px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; white-space: nowrap;
            box-shadow: 0 2px 6px rgba(102,126,234,0.3);
        }

        /* History Item */
        .history-item {
            background: white;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        }
        .history-item .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .history-item .product-name { font-weight: 600; font-size: 0.85rem; color: #1e293b; }
        .history-item .detail { font-size: 0.75rem; color: #64748b; line-height: 1.5; }

        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        /* Verify Card */
        .verify-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
            border-left: 4px solid #d97706;
        }
        .verify-card .waiter-name { font-weight: 700; font-size: 0.85rem; color: #1e293b; }
        .verify-card .claim-detail { font-size: 0.78rem; color: #475569; margin: 6px 0; }
        .verify-card .claim-points { font-weight: 700; color: #667eea; }
        .verify-card img { max-width: 100%; max-height: 180px; border-radius: 8px; margin: 8px 0; cursor: pointer; border: 1px solid #e2e8f0; }
        .verify-actions { display: flex; gap: 8px; margin-top: 10px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; border-radius: 10px; font-size: 0.85rem;
            font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; width: 100%; box-shadow: 0 3px 12px rgba(102,126,234,0.3); }
        .btn-primary:hover { box-shadow: 0 5px 20px rgba(102,126,234,0.4); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-sm { padding: 8px 14px; font-size: 0.78rem; border-radius: 8px; }
        .btn-approve { background: #059669; color: white; flex: 1; }
        .btn-approve:hover { background: #047857; }
        .btn-reject { background: #dc2626; color: white; flex: 1; }
        .btn-reject:hover { background: #b91c1c; }
        .btn-light { background: #f1f5f9; color: #475569; }

        /* Fixed Bottom Button */
        .claim-btn-container {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 12px 16px; background: white;
            box-shadow: 0 -4px 16px rgba(0,0,0,0.08); z-index: 50;
        }
        .claim-btn-container .btn { max-width: 600px; margin: 0 auto; }

        /* Modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.6); display:none; align-items:flex-end; justify-content:center; z-index:9999; backdrop-filter: blur(2px); }
        .modal-content { background:#fff; border-radius:20px 20px 0 0; padding:24px 20px; width:100%; max-width:600px; max-height:85vh; overflow-y:auto; box-shadow: 0 -8px 30px rgba(0,0,0,0.15); }
        .modal-handle { width: 40px; height: 4px; background: #cbd5e1; border-radius: 4px; margin: 0 auto 16px; }
        .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 16px; color: #1e293b; }

        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; transition: border-color 0.2s; background: #fafbfc; }
        .form-control:focus { outline: none; border-color: #667eea; background: white; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        select.form-control { appearance: auto; }

        /* Photo Upload */
        .photo-upload {
            border: 2px dashed #cbd5e1; border-radius: 12px;
            padding: 24px; text-align: center; cursor: pointer; transition: all 0.2s;
            background: #fafbfc;
        }
        .photo-upload:hover { border-color: #667eea; background: #f8faff; }
        .photo-upload.has-photo { border-color: #059669; background: #f0fdf4; border-style: solid; }
        .photo-upload .icon { font-size: 2rem; margin-bottom: 6px; }
        .photo-upload .text { font-size: 0.8rem; color: #64748b; }
        .photo-upload img { max-width: 100%; max-height: 150px; border-radius: 8px; margin-top: 10px; }

        /* Points Preview */
        .points-preview {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0; padding: 12px; border-radius: 10px;
            text-align: center; margin-bottom: 16px;
        }
        .points-preview .label { font-size: 0.75rem; color: #065f46; }
        .points-preview .value { font-size: 1.3rem; font-weight: 800; color: #059669; }

        /* Empty State */
        .empty-state { text-align: center; padding: 2.5rem 1rem; color: #94a3b8; }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 8px; }
        .empty-state p { font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <a href="{{ route('waiter.bonus') }}" class="back-btn">←</a>
            <h1>🎯 Bonus Produk</h1>
            <span class="header-name">{{ $waiterName }}</span>
        </div>
    </div>

    <div class="container">
        {{-- Summary --}}
        <div class="summary-card">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Disetujui</div>
                    <div class="value value-green">{{ $breakdown['total_approved'] ?? 0 }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Pending</div>
                    <div class="value value-orange">{{ $breakdown['total_pending'] ?? 0 }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Bulan</div>
                    <div class="value value-muted" style="font-size:1rem;">{{ date('M Y', strtotime($month . '-01')) }}</div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('products', this)">📦 Produk</button>
            <button class="tab-btn" onclick="switchTab('history', this)">📋 Riwayat</button>
            @if(in_array(session('waiter_role'), ['finance', 'supervisor']))
            <button class="tab-btn" onclick="switchTab('verify', this)">🔍 Verifikasi</button>
            @endif
        </div>

        {{-- Products Tab --}}
        <div id="tab-products">
            @if(count($campaigns) === 0)
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <p>Tidak ada campaign bonus produk aktif saat ini.</p>
                </div>
            @else
                @foreach($campaigns as $campaign)
                    <div class="section-title">{{ $campaign['title'] ?? 'Campaign' }}
                        @if($campaign['end_date'] ?? null)
                            <span style="font-weight:400; color:#94a3b8;"> — s/d {{ $campaign['end_date'] }}</span>
                        @endif
                    </div>
                    @php $products = (array) ($campaign['products'] ?? []); @endphp
                    @foreach($products as $key => $product)
                        <div class="product-card">
                            <div class="info">
                                <h4>{{ $product['name'] ?? '-' }}</h4>
                                <div class="meta">Jual 1 unit = dapat poin bonus</div>
                            </div>
                            <div class="points-badge">+{{ $product['points_per_unit'] ?? 0 }} poin</div>
                        </div>
                    @endforeach
                @endforeach
            @endif
        </div>

        {{-- History Tab --}}
        <div id="tab-history" style="display:none;">
            @php $allClaims = $breakdown['all_claims'] ?? []; @endphp
            @if(count($allClaims) === 0)
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <p>Belum ada klaim bulan ini.</p>
                </div>
            @else
                @foreach($allClaims as $claim)
                <div class="history-item">
                    <div class="top">
                        <span class="product-name">{{ $claim['product_name'] ?? '-' }}</span>
                        <span class="status-badge status-{{ $claim['status'] ?? 'pending' }}">{{ ucfirst($claim['status'] ?? 'pending') }}</span>
                    </div>
                    <div class="detail">
                        {{ $claim['quantity'] ?? 0 }} unit × {{ $claim['points_per_unit'] ?? 0 }} poin = <strong>{{ $claim['points_claimed'] ?? 0 }} poin</strong>
                        &bull; {{ $claim['date'] ?? '-' }}
                        @if(($claim['status'] ?? '') === 'rejected' && ($claim['reject_reason'] ?? ''))
                            <br><span style="color:#dc2626;">❌ {{ $claim['reject_reason'] }}</span>
                        @endif
                        @if(($claim['status'] ?? '') === 'approved')
                            <br><span style="color:#059669;">✅ Diverifikasi oleh {{ $claim['verified_by'] ?? '-' }}</span>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        {{-- Verification Tab (Finance/Supervisor only) --}}
        @if(in_array(session('waiter_role'), ['finance', 'supervisor']))
        <div id="tab-verify" style="display:none;">
            <div id="verifyLoading" class="empty-state">
                <div class="icon">⏳</div>
                <p>Memuat klaim pending...</p>
            </div>
            <div id="verifyContent" style="display:none;"></div>
        </div>
        @endif
    </div>

    {{-- Fixed Claim Button --}}
    @if(count($campaigns) > 0)
    <div class="claim-btn-container">
        <button class="btn btn-primary" onclick="openClaimModal()">📝 Klaim Bonus Penjualan</button>
    </div>
    @endif

    {{-- Claim Modal --}}
    <div id="claimModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-handle"></div>
            <div class="modal-title">📝 Klaim Bonus Penjualan</div>
            <form id="claimForm">
                <div class="form-group">
                    <label>Pilih Produk</label>
                    <select id="claimProduct" class="form-control" onchange="updatePointsPreview()" required>
                        <option value="">— Pilih produk —</option>
                        @foreach($campaigns as $campaign)
                            @php $products = (array) ($campaign['products'] ?? []); @endphp
                            @foreach($products as $key => $product)
                                <option value="{{ $campaign['id'] }}|{{ $key }}" data-points="{{ $product['points_per_unit'] ?? 0 }}">
                                    {{ $product['name'] ?? '-' }} ({{ $product['points_per_unit'] ?? 0 }} poin/unit)
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Jumlah Unit Terjual</label>
                    <input type="number" id="claimQty" class="form-control" min="1" value="1" oninput="updatePointsPreview()" required>
                </div>

                <div id="pointsPreview" class="points-preview" style="display:none;">
                    <div class="label">Total poin yang akan diklaim</div>
                    <div class="value" id="previewPoints">0</div>
                </div>

                <div class="form-group">
                    <label>Foto Struk / Bukti Penjualan</label>
                    <div class="photo-upload" id="photoUpload" onclick="document.getElementById('photoInput').click()">
                        <div id="photoPlaceholder">
                            <div class="icon">📷</div>
                            <div class="text">Tap untuk ambil foto struk</div>
                        </div>
                        <img id="photoPreview" style="display:none;">
                    </div>
                    <input type="file" id="photoInput" accept="image/*" capture="environment" style="display:none;" onchange="handlePhoto(this)">
                </div>

                <button type="submit" class="btn btn-primary" id="btnClaim" disabled>Kirim Klaim</button>
                <button type="button" class="btn btn-light" style="width:100%; margin-top:8px;" onclick="closeClaimModal()">Batal</button>
            </form>
        </div>
    </div>

    <script>
    let photoDataUrl = null;

    function switchTab(tab, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-products').style.display = tab === 'products' ? 'block' : 'none';
        document.getElementById('tab-history').style.display = tab === 'history' ? 'block' : 'none';
        const verifyTab = document.getElementById('tab-verify');
        if (verifyTab) verifyTab.style.display = tab === 'verify' ? 'block' : 'none';
        if (tab === 'verify') loadVerifyClaims();
    }

    // ===== VERIFY =====
    async function loadVerifyClaims() {
        const loading = document.getElementById('verifyLoading');
        const content = document.getElementById('verifyContent');
        if (!loading || !content) return;

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const res = await fetch('{{ route("waiter.bonus_produk.verify") }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!data.success) { loading.innerHTML = `<div class="icon">⚠️</div><p>${data.message || 'Gagal memuat.'}</p>`; return; }

            loading.style.display = 'none';
            content.style.display = 'block';

            const pending = data.pending || [];
            const approved = data.recent_approved || [];

            if (pending.length === 0 && approved.length === 0) {
                content.innerHTML = '<div class="empty-state"><div class="icon">✅</div><p>Tidak ada klaim yang perlu diverifikasi.</p></div>';
                return;
            }

            let html = '';
            if (pending.length > 0) {
                html += `<div class="section-title" style="color:#d97706;">⏳ Menunggu Verifikasi (${pending.length})</div>`;
                pending.forEach(c => { html += renderVerifyCard(c); });
            }
            if (approved.length > 0) {
                html += `<div class="section-title" style="margin-top:20px; color:#059669;">✅ Baru Disetujui</div>`;
                approved.forEach(c => {
                    html += `<div class="history-item">
                        <div class="top"><span class="product-name">${c.waiter_name || '-'} — ${c.product_name || '-'}</span><span class="status-badge status-approved">Approved</span></div>
                        <div class="detail">${c.quantity || 0} unit = <strong>${c.points_claimed || 0} poin</strong> &bull; ${c.date || '-'}</div>
                    </div>`;
                });
            }
            content.innerHTML = html;
        } catch (err) { loading.innerHTML = `<div class="icon">⚠️</div><p>Error: ${err.message}</p>`; }
    }

    function renderVerifyCard(claim) {
        const photoHtml = claim.photo_url
            ? `<img src="${claim.photo_url}" onclick="window.open(this.src)" alt="Bukti struk">`
            : '<div style="padding:8px; background:#fef2f2; border-radius:8px; font-size:0.75rem; color:#dc2626; text-align:center;">⚠️ Tidak ada foto bukti</div>';

        return `<div class="verify-card" id="claim-${claim.id}">
            <div class="waiter-name">${claim.waiter_name || '-'}</div>
            <div class="claim-detail">
                <strong>${claim.product_name || '-'}</strong> — ${claim.quantity || 0} unit × ${claim.points_per_unit || 0} = <span class="claim-points">${claim.points_claimed || 0} poin</span>
                <br><span style="color:#94a3b8; font-size:0.72rem;">${claim.date || '-'} • ${claim.campaign_title || ''}</span>
            </div>
            ${photoHtml}
            <div class="verify-actions">
                <button class="btn btn-sm btn-approve" onclick="verifyClaim('${claim.id}', 'approved')">✅ Approve</button>
                <button class="btn btn-sm btn-reject" onclick="rejectClaim('${claim.id}')">❌ Reject</button>
            </div>
        </div>`;
    }

    async function verifyClaim(id, status, reason = null) {
        const body = { status };
        if (reason) body.reason = reason;
        try {
            const res = await fetch('{{ url("waiter/bonus-produk/verify") }}/' + id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (data.success) { document.getElementById('claim-' + id)?.remove(); loadVerifyClaims(); }
            else alert(data.message || 'Gagal verifikasi.');
        } catch (err) { alert('Error: ' + err.message); }
    }

    function rejectClaim(id) {
        const reason = prompt('Alasan penolakan (opsional):');
        if (reason === null) return;
        verifyClaim(id, 'rejected', reason || '');
    }

    // ===== CLAIM =====
    function openClaimModal() { document.getElementById('claimModal').style.display = 'flex'; }
    function closeClaimModal() { document.getElementById('claimModal').style.display = 'none'; }

    function updatePointsPreview() {
        const select = document.getElementById('claimProduct');
        const qty = parseInt(document.getElementById('claimQty').value) || 0;
        const option = select.options[select.selectedIndex];
        const points = parseInt(option?.dataset?.points || 0);
        const total = points * qty;
        const preview = document.getElementById('pointsPreview');
        if (select.value && qty > 0) {
            preview.style.display = 'block';
            document.getElementById('previewPoints').textContent = total + ' poin';
        } else { preview.style.display = 'none'; }
        validateForm();
    }

    function handlePhoto(input) {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            photoDataUrl = e.target.result;
            document.getElementById('photoPreview').src = photoDataUrl;
            document.getElementById('photoPreview').style.display = 'block';
            document.getElementById('photoPlaceholder').innerHTML = '<div class="icon">✅</div><div class="text">Foto terpilih (tap untuk ganti)</div>';
            document.getElementById('photoUpload').classList.add('has-photo');
            validateForm();
        };
        reader.readAsDataURL(file);
    }

    function validateForm() {
        const product = document.getElementById('claimProduct').value;
        const qty = parseInt(document.getElementById('claimQty').value) || 0;
        document.getElementById('btnClaim').disabled = !(product && qty > 0 && photoDataUrl);
    }

    document.getElementById('claimForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const productVal = document.getElementById('claimProduct').value;
        const [campaignId, productKey] = productVal.split('|');
        const qty = parseInt(document.getElementById('claimQty').value);
        if (!photoDataUrl) { alert('Foto struk wajib diupload.'); return; }

        const btn = document.getElementById('btnClaim');
        btn.disabled = true; btn.textContent = 'Mengirim...';

        try {
            const res = await fetch('{{ route("waiter.bonus_produk.claim") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ campaign_id: campaignId, product_key: productKey, quantity: qty, photo_proof: photoDataUrl }),
            });
            const data = await res.json();
            if (data.success) { alert(data.message || 'Klaim berhasil!'); location.reload(); }
            else alert(data.message || 'Gagal submit klaim.');
        } catch (err) { alert('Error: ' + err.message); }
        finally { btn.disabled = false; btn.textContent = 'Kirim Klaim'; }
    });

    // Auto-switch to verify tab if URL has ?tab=verify (deep-link from quick-action tile)
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('tab') === 'verify') {
            const verifyBtn = document.querySelector('.tab-bar .tab-btn:nth-child(3)');
            if (verifyBtn) verifyBtn.click();
        }
    });
    </script>
</body>
</html>
