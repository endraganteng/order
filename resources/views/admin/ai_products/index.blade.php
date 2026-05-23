@extends('admin.layout')

@section('title', 'AI Knowledge Produk')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">AI Knowledge Produk</h2>
        <p style="color: var(--color-text-muted); font-size: 13px;">Generate, review, dan approve knowledge produk untuk fitur AI chat.</p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" class="btn" style="background: var(--color-primary); color:#fff;" onclick="openGenerateModal()">+ Generate Single</button>
        <button type="button" class="btn" style="background: var(--color-info); color:#fff;" onclick="openBatchModal()">⚡ Batch Background</button>
        <button type="button" class="btn" style="background: #6366f1; color:#fff;" onclick="openSyncModal()">🔄 Sync Vector Massal</button>
    </div>
</div>

{{-- Active batch progress card --}}
<div id="batchPanel" style="display:none; background:#fff; border-left:4px solid #0284c7; padding:14px 18px; border-radius:8px; box-shadow:var(--shadow-sm); margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
        <div>
            <div style="font-weight:700; font-size:14px;">
                <span id="batchModeLabel">-</span> · Batch <span id="batchIdLabel">-</span> ·
                <span id="batchStatusBadge" style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; background:#0284c7; color:#fff;">running</span>
            </div>
            <div style="font-size:12px; color:var(--color-text-muted); margin-top:2px;" id="batchInitiator">-</div>
        </div>
        <div style="display:flex; gap:6px;">
            <button type="button" class="btn" id="batchCancelBtn" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:5px 12px; font-size:12px;" onclick="cancelBatch()">Cancel</button>
            <button type="button" class="btn" id="batchCloseBtn" style="background:var(--color-border); padding:5px 12px; font-size:12px; display:none;" onclick="closeBatchPanel()">Tutup</button>
        </div>
    </div>
    <div style="background:#f1f5f9; height:14px; border-radius:8px; overflow:hidden; position:relative;">
        <div id="batchProgressBar" style="height:100%; background:linear-gradient(90deg, #0284c7, #0ea5e9); width:0%; transition:width 0.3s;"></div>
        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#0f172a;" id="batchProgressLabel">0%</div>
    </div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:8px; margin-top:10px; font-size:12px;">
        <div><strong id="batchProcessed">0</strong> / <span id="batchTotal">0</span> diproses</div>
        <div style="color:#16a34a;">✓ <strong id="batchSuccess">0</strong> sukses</div>
        <div style="color:#dc2626;">✗ <strong id="batchFailed">0</strong> gagal</div>
        <div style="color:#64748b;">⊘ <strong id="batchSkipped">0</strong> skip</div>
    </div>
    <div style="margin-top:8px; padding:6px 10px; background:#f8fafc; border-radius:6px; font-size:12px;">
        <div id="batchCurrentProduct">-</div>
        <div id="batchLastMessage" style="color:var(--color-text-muted); margin-top:2px;">-</div>
    </div>
    <div id="batchSpawnError" style="display:none; margin-top:8px; padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:12px; color:#dc2626;">
        <strong>⚠️ Spawn Error:</strong> <span id="batchSpawnErrorText"></span>
    </div>
    <div style="margin-top:10px; display:flex; gap:6px; flex-wrap:wrap;">
        <button type="button" id="batchLogBtn" style="display:none; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:5px 12px; font-size:11px; border-radius:6px; cursor:pointer;" onclick="showBatchLog()">📋 Lihat Log Lengkap</button>
        <a href="#" id="batchDiagnoseBtn" style="display:none; background:#fef3c7; color:#a16207; border:1px solid #fde68a; padding:5px 12px; font-size:11px; border-radius:6px; text-decoration:none; cursor:pointer;" onclick="showDiagnoseInfo(); return false;">🔧 Cara Debug</a>
    </div>
</div>

{{-- Modal log viewer --}}
<div id="logModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#0f172a; color:#f1f5f9; border-radius:10px; padding:18px; width:95%; max-width:900px; max-height:85vh; display:flex; flex-direction:column;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <div>
                <h3 style="margin:0; color:#fff; font-size:15px;">📋 Log Batch <span id="logBatchId">-</span></h3>
                <div id="logFilePath" style="font-size:11px; color:#94a3b8; margin-top:2px; font-family:monospace;">-</div>
            </div>
            <div style="display:flex; gap:6px;">
                <button onclick="copyLogContent()" style="background:#1e293b; color:#cbd5e1; border:1px solid #334155; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:11px;">📋 Copy</button>
                <button onclick="closeLogModal()" style="background:#dc2626; color:#fff; border:none; padding:5px 14px; border-radius:4px; cursor:pointer;">Tutup</button>
            </div>
        </div>
        <div id="logContent" style="flex:1; overflow:auto; background:#1e293b; padding:12px; border-radius:6px; font-family:'Consolas', monospace; font-size:12px; white-space:pre-wrap; word-break:break-all; line-height:1.5; min-height:200px;">Loading...</div>
    </div>
</div>

{{-- Modal diagnose info --}}
<div id="diagnoseModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:20px; width:95%; max-width:680px;">
        <h3 style="margin:0 0 12px;">🔧 Cara Debug Production Batch Error</h3>
        <p style="font-size:13px; color:#475569; margin-bottom:14px;">Kalau batch gagal di production, jalankan di terminal server:</p>

        <div style="background:#0f172a; color:#a5f3fc; padding:12px; border-radius:6px; font-family:monospace; font-size:13px; margin-bottom:12px; overflow-x:auto;">
<span style="color:#94a3b8;"># 1. Cek environment + spawn capability</span><br>
php artisan ai:product:diagnose<br><br>
<span style="color:#94a3b8;"># 2. Cek lengkap dengan test koneksi real (recommended pertama kali)</span><br>
php artisan ai:product:diagnose --test-firebase --test-gemini --test-supabase --test-spawn<br><br>
<span style="color:#94a3b8;"># 3. Test enrichment manual 1 produk</span><br>
php artisan ai:product-enrichment:generate --limit=1<br><br>
<span style="color:#94a3b8;"># 4. Lihat log Laravel</span><br>
tail -n 100 storage/logs/laravel.log
        </div>

        <div style="background:#fef3c7; border:1px solid #fde68a; padding:12px; border-radius:6px; font-size:12px; margin-bottom:14px;">
            <strong style="color:#a16207;">Tips Production:</strong>
            <ul style="margin:6px 0 0 18px; color:#78350f;">
                <li><strong>shell_exec disabled</strong>: Buka php.ini, hapus <code>shell_exec</code> dari <code>disable_functions</code>, restart php-fpm/apache.</li>
                <li><strong>Permission log</strong>: <code>chmod -R 775 storage/logs</code> + own ke www-data.</li>
                <li><strong>nohup tidak ada</strong>: Install GNU coreutils: <code>apt install coreutils</code>.</li>
                <li><strong>Process spawn tidak persist</strong>: Pakai supervisor untuk dependable background worker.</li>
            </ul>
        </div>

        <div style="display:flex; justify-content:flex-end;">
            <button onclick="closeDiagnoseModal()" class="btn btn-primary">Mengerti</button>
        </div>
    </div>
</div>

{{-- Stats --}}
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:12px; margin-bottom:20px;">
    @foreach (['total'=>['Total','#0f172a'],'pending_review'=>['Pending Review','#d97706'],'needs_review'=>['Need Review','#0284c7'],'approved'=>['Approved','#16a34a'],'rejected'=>['Rejected','#dc2626'],'failed'=>['Failed','#64748b']] as $key => $meta)
        <div style="background:#fff; padding:14px; border-radius:8px; box-shadow:var(--shadow-sm); border-left:4px solid {{ $meta[1] }};">
            <div style="font-size:12px; color:var(--color-text-muted);">{{ $meta[0] }}</div>
            <div style="font-size:24px; font-weight:700; color:{{ $meta[1] }};">{{ number_format($stats[$key] ?? 0) }}</div>
        </div>
    @endforeach
</div>

{{-- Filter --}}
<form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
    <select name="status" style="padding:8px 10px; border:1px solid var(--color-border); border-radius:6px;">
        <option value="">Semua status</option>
        @foreach (['pending_review','needs_review','approved','rejected','failed','extracting'] as $s)
            <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
        @endforeach
    </select>
    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / id / base name..." style="padding:8px 10px; border:1px solid var(--color-border); border-radius:6px; min-width:240px; flex:1;">
    <button type="submit" class="btn btn-primary">Filter</button>
    @if($status || $search)
        <a href="{{ route('admin.ai_products.index') }}" class="btn" style="background:var(--color-border);">Reset</a>
    @endif
</form>

{{-- Table --}}
<div style="background:#fff; border-radius:8px; overflow:hidden; box-shadow:var(--shadow-sm);">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th style="text-align:left; padding:10px;">ID</th>
                    <th style="text-align:left; padding:10px;">Produk</th>
                    <th style="text-align:left; padding:10px;">Base / Variant</th>
                    <th style="text-align:left; padding:10px;">Status</th>
                    <th style="text-align:right; padding:10px;">Confidence</th>
                    <th style="text-align:right; padding:10px;">Sumber</th>
                    <th style="text-align:left; padding:10px;">Dibuat</th>
                    <th style="text-align:right; padding:10px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($jobs as $job)
                <tr style="border-top:1px solid var(--color-border);">
                    <td style="padding:10px; color:var(--color-text-muted);">#{{ $job->id }}</td>
                    <td style="padding:10px;">
                        <div style="font-weight:600;">{{ $job->product_name }}</div>
                        <div style="font-size:11px; color:var(--color-text-muted);">id: <code>{{ $job->product_id }}</code></div>
                    </td>
                    <td style="padding:10px;">
                        <div>{{ $job->base_name ?? '-' }}</div>
                        @if($job->variant_label)
                            <div style="font-size:11px; color:var(--color-text-muted);">var: <span style="background:#eef2ff; padding:1px 6px; border-radius:4px; color:#4338ca;">{{ $job->variant_label }}</span></div>
                        @endif
                        @if($job->is_inherited)
                            <div style="font-size:11px; color:#0284c7;">↪ inherited dari {{ $job->inherited_from_product_id }}</div>
                        @endif
                    </td>
                    <td style="padding:10px;">
                        @php
                            $colors = ['approved'=>'#16a34a','pending_review'=>'#d97706','needs_review'=>'#0284c7','rejected'=>'#dc2626','failed'=>'#64748b','extracting'=>'#7c3aed'];
                            $bg = $colors[$job->status] ?? '#64748b';
                        @endphp
                        <span style="display:inline-block; padding:3px 8px; border-radius:4px; background:{{ $bg }}20; color:{{ $bg }}; font-weight:600; font-size:11px;">{{ $job->status }}</span>
                    </td>
                    <td style="padding:10px; text-align:right;">{{ $job->confidence_score !== null ? number_format((float)$job->confidence_score, 2) : '-' }}</td>
                    <td style="padding:10px; text-align:right;">{{ $job->source_count }}</td>
                    <td style="padding:10px; color:var(--color-text-muted); white-space:nowrap;">{{ $job->created_at?->format('d M H:i') }}</td>
                    <td style="padding:10px; text-align:right;">
                        <a href="{{ route('admin.ai_products.show', $job->id) }}" class="btn" style="padding:4px 10px; font-size:12px; background:var(--color-primary); color:#fff;">Detail</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="padding:30px; text-align:center; color:var(--color-text-muted);">Belum ada job. Klik "Generate Single" untuk mulai.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:12px;">{{ $jobs->links() }}</div>
</div>

{{-- Generate single modal --}}
<div id="genModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:20px; width:90%; max-width:520px; box-shadow:var(--shadow-md);">
        <h3 style="margin-bottom:14px;">Generate Knowledge — Pilih Produk</h3>
        <input type="text" id="searchInput" placeholder="Ketik nama produk..." oninput="searchProducts()" style="width:100%; padding:10px; border:1px solid var(--color-border); border-radius:6px;">
        <label style="display:flex; align-items:center; gap:6px; margin-top:10px; font-size:13px;">
            <input type="checkbox" id="onlyMissing" checked onchange="searchProducts()"> Hanya produk yang belum punya knowledge
        </label>
        <div id="searchResults" style="margin-top:12px; max-height:340px; overflow-y:auto; border:1px solid var(--color-border); border-radius:6px;"></div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
            <button type="button" class="btn" style="background:var(--color-border);" onclick="closeGenerateModal()">Tutup</button>
        </div>
    </div>
</div>

{{-- Batch enrichment modal --}}
<div id="batchModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:20px; width:90%; max-width:520px; box-shadow:var(--shadow-md);">
        <h3 style="margin-bottom:14px;">Batch Enrichment Latar Belakang</h3>
        <p style="font-size:13px; color:var(--color-text-muted); margin-bottom:14px;">
            Proses berjalan di server tanpa block UI. Anda bisa kerja lain sementara batch jalan, progress di-update real-time.
        </p>
        <form id="batchForm" onsubmit="startBatch(event)">
            <div style="margin-bottom:10px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Jumlah produk</label>
                <input type="number" name="limit" value="20" min="1" max="500" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
                <small style="color:var(--color-text-muted);">Setiap produk butuh ~15-20 detik (Gemini grounded). 20 produk = ~5-7 menit.</small>
            </div>
            <label style="display:flex; gap:6px; align-items:center; padding:8px; background:#f8fafc; border-radius:6px; margin-bottom:6px; font-size:13px; cursor:pointer;">
                <input type="checkbox" name="only_missing" checked> Hanya produk yang belum punya knowledge
            </label>
            <label style="display:flex; gap:6px; align-items:center; padding:8px; background:#fef9c3; border-radius:6px; margin-bottom:6px; font-size:13px; cursor:pointer;">
                <input type="checkbox" name="auto_approve"> 🔓 Auto-approve setelah generate (skip review manual)
            </label>
            <label style="display:flex; gap:6px; align-items:center; padding:8px; background:#dcfce7; border-radius:6px; margin-bottom:14px; font-size:13px; cursor:pointer;">
                <input type="checkbox" name="auto_sync"> 🚀 Auto-sync vector ke Supabase setelah approve
            </label>
            <p style="font-size:11px; color:#a16207; padding:6px 10px; background:#fef3c7; border-radius:4px; margin-bottom:14px;">
                <strong>Catatan:</strong> Auto-approve melewati review manual. Pastikan Anda OK dengan output AI yang otomatis di-publish ke chat. Disarankan tetap review confidence rendah secara manual setelahnya.
            </p>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn" style="background:var(--color-border);" onclick="closeBatchModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Mulai Batch</button>
            </div>
        </form>
    </div>
</div>

{{-- Vector sync modal --}}
<div id="syncModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:20px; width:90%; max-width:520px; box-shadow:var(--shadow-md);">
        <h3 style="margin-bottom:14px;">Sync Vector ke Supabase (Massal)</h3>
        <p style="font-size:13px; color:var(--color-text-muted); margin-bottom:14px;">
            Re-sync semua knowledge yang sudah di-approve ke vector store. Wajib dijalankan setelah edit knowledge atau bulk approve.
        </p>
        <form id="syncForm" onsubmit="startSync(event)">
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Maksimum produk</label>
                <input type="number" name="limit" value="500" min="1" max="2000" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
                <small style="color:var(--color-text-muted);">Setiap produk butuh ~1 detik (Gemini embed + Supabase upsert). 500 produk = ~10 menit.</small>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn" style="background:var(--color-border);" onclick="closeSyncModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Mulai Sync</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

function openGenerateModal() {
    document.getElementById('genModal').style.display = 'flex';
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '<div style="padding:14px; color:#64748b; font-size:13px;">Ketik nama produk untuk mencari...</div>';
    setTimeout(() => document.getElementById('searchInput').focus(), 100);
}
function closeGenerateModal() {
    document.getElementById('genModal').style.display = 'none';
}

let searchTimer = null;
function searchProducts() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        const q = document.getElementById('searchInput').value.trim();
        const onlyMissing = document.getElementById('onlyMissing').checked ? '1' : '0';
        const res = await fetch(`{{ route('admin.ai_products.search') }}?q=${encodeURIComponent(q)}&only_missing=${onlyMissing}&limit=30`);
        const data = await res.json();
        const box = document.getElementById('searchResults');
        if (!data.success || !data.products.length) {
            box.innerHTML = '<div style="padding:14px; color:#64748b; font-size:13px;">Tidak ada produk yang cocok.</div>';
            return;
        }
        box.innerHTML = data.products.map(p => `
            <div style="padding:10px 12px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <div style="min-width:0;">
                    <div style="font-weight:600; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(p.name)}</div>
                    <div style="font-size:11px; color:#64748b;">id: ${escapeHtml(p.id)} ${p.has_knowledge ? '· <span style="color:#16a34a;">✓ knowledge ada</span>' : ''}</div>
                </div>
                <button class="btn btn-primary" style="padding:5px 12px; font-size:12px; flex-shrink:0;" onclick="generateNow('${escapeHtml(p.id)}', this)">${p.has_knowledge ? 'Re-generate' : 'Generate'}</button>
            </div>`).join('');
    }, 250);
}

async function generateNow(productId, btn) {
    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = 'Memproses...';
    try {
        const res = await fetch(`{{ route('admin.ai_products.generate') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({product_id: productId})
        });
        const data = await res.json();
        if (data.success) {
            alert('OK: ' + (data.message || 'Job dibuat.') + (data.job_id ? ' (job #' + data.job_id + ')' : ''));
            location.reload();
        } else {
            alert('Gagal: ' + (data.message || 'unknown'));
            btn.disabled = false; btn.textContent = original;
        }
    } catch (e) {
        alert('Error: ' + e.message);
        btn.disabled = false; btn.textContent = original;
    }
}

async function runBatch() {
    // Legacy synchronous batch button - replaced by background batch (openBatchModal).
    openBatchModal();
}

// === Background Batch ===
let pollTimer = null;
let activeBatchId = null;

function openBatchModal() { document.getElementById('batchModal').style.display = 'flex'; }
function closeBatchModal() { document.getElementById('batchModal').style.display = 'none'; }
function openSyncModal() { document.getElementById('syncModal').style.display = 'flex'; }
function closeSyncModal() { document.getElementById('syncModal').style.display = 'none'; }

async function startBatch(e) {
    e.preventDefault();
    const form = document.getElementById('batchForm');
    const fd = new FormData(form);
    const body = {
        mode: 'enrichment',
        limit: parseInt(fd.get('limit') || '20', 10),
        only_missing: fd.get('only_missing') === 'on',
        auto_approve: fd.get('auto_approve') === 'on',
        auto_sync: fd.get('auto_sync') === 'on',
    };
    try {
        const res = await fetch(`{{ route('admin.ai_products.batch.start') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            closeBatchModal();
            startPolling(data.batch_id);
        } else {
            alert('Gagal mulai batch: ' + (data.message || 'unknown'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function startSync(e) {
    e.preventDefault();
    const form = document.getElementById('syncForm');
    const fd = new FormData(form);
    const body = {
        mode: 'vector_sync',
        limit: parseInt(fd.get('limit') || '500', 10),
    };
    try {
        const res = await fetch(`{{ route('admin.ai_products.batch.start') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            closeSyncModal();
            startPolling(data.batch_id);
        } else {
            alert('Gagal mulai sync: ' + (data.message || 'unknown'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

function startPolling(batchId) {
    activeBatchId = batchId;
    document.getElementById('batchPanel').style.display = 'block';
    document.getElementById('batchIdLabel').textContent = '#' + batchId;
    document.getElementById('batchCancelBtn').style.display = '';
    document.getElementById('batchCloseBtn').style.display = 'none';
    pollOnce();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(pollOnce, 2000);
}

async function pollOnce() {
    if (!activeBatchId) return;
    try {
        const res = await fetch(`{{ url('admin/ai-products/batch') }}/${activeBatchId}/status`, {
            headers: {'Accept':'application/json'}
        });
        const data = await res.json();
        if (!data.success || !data.batch) return;
        const b = data.batch;
        document.getElementById('batchModeLabel').textContent = b.mode === 'enrichment' ? 'Enrichment' : 'Vector Sync';
        document.getElementById('batchStatusBadge').textContent = b.status;
        document.getElementById('batchStatusBadge').style.background = statusColor(b.status);
        document.getElementById('batchProgressBar').style.width = b.progress_pct + '%';
        document.getElementById('batchProgressLabel').textContent = b.progress_pct + '%';
        document.getElementById('batchProcessed').textContent = b.processed_items;
        document.getElementById('batchTotal').textContent = b.total_items;
        document.getElementById('batchSuccess').textContent = b.success_count;
        document.getElementById('batchFailed').textContent = b.failed_count;
        document.getElementById('batchSkipped').textContent = b.skipped_count;
        document.getElementById('batchCurrentProduct').innerHTML = b.current_product_name
            ? `<strong>Sedang dikerjakan:</strong> ${escapeHtml(b.current_product_name)}`
            : '<em style="color:#94a3b8;">menunggu...</em>';
        document.getElementById('batchLastMessage').textContent = b.last_message || '';
        const initiatorEl = document.getElementById('batchInitiator');
        if (b.started_at) initiatorEl.textContent = `Mulai: ${b.started_at}` + (b.auto_approve ? ' · auto-approve' : '') + (b.auto_sync ? ' · auto-sync' : '');

        // Spawn error
        if (b.spawn_error) {
            document.getElementById('batchSpawnError').style.display = 'block';
            document.getElementById('batchSpawnErrorText').textContent = b.spawn_error;
        } else {
            document.getElementById('batchSpawnError').style.display = 'none';
        }

        // Tombol Lihat Log + Diagnose visible kalau ada log file atau status problematik
        const logBtn = document.getElementById('batchLogBtn');
        const diagBtn = document.getElementById('batchDiagnoseBtn');
        if (b.has_log) logBtn.style.display = ''; else logBtn.style.display = 'none';
        if (b.status === 'failed' || b.is_stale || (b.status === 'queued' && b.processed_items === 0)) {
            diagBtn.style.display = '';
        } else {
            diagBtn.style.display = 'none';
        }

        if (b.is_terminal) {
            clearInterval(pollTimer);
            pollTimer = null;
            document.getElementById('batchCancelBtn').style.display = 'none';
            document.getElementById('batchCloseBtn').style.display = '';
            // Reload table HANYA kalau success, kalau failed biarkan supaya user bisa baca log
            if (b.status === 'completed') {
                setTimeout(() => location.reload(), 1500);
            }
        }
    } catch (err) {
        console.warn('poll error', err);
    }
}

function statusColor(s) {
    return ({running:'#0284c7', completed:'#16a34a', failed:'#dc2626', cancelled:'#64748b', queued:'#7c3aed'}[s] || '#64748b');
}

async function cancelBatch() {
    if (!activeBatchId) return;
    if (!confirm('Hentikan batch yang sedang berjalan?')) return;
    const res = await fetch(`{{ url('admin/ai-products/batch') }}/${activeBatchId}/cancel`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
    });
    const data = await res.json();
    if (!data.success) alert('Gagal cancel: ' + (data.message || ''));
}

function closeBatchPanel() {
    document.getElementById('batchPanel').style.display = 'none';
    activeBatchId = null;
}

// === Log viewer ===
async function showBatchLog() {
    if (!activeBatchId) return;
    document.getElementById('logModal').style.display = 'flex';
    document.getElementById('logBatchId').textContent = '#' + activeBatchId;
    document.getElementById('logContent').textContent = 'Loading...';
    try {
        const res = await fetch(`{{ url('admin/ai-products/batch') }}/${activeBatchId}/log`, {
            headers: {'Accept':'application/json'}
        });
        const data = await res.json();
        if (!data.success) {
            document.getElementById('logContent').textContent = 'Error: ' + (data.message || '');
            return;
        }
        document.getElementById('logFilePath').textContent = data.log_file || '(tidak ada path log)';
        let display = '';
        if (data.spawn_error) {
            display += '════════ SPAWN ERROR ════════\n' + data.spawn_error + '\n\n';
        }
        if (data.artisan_command) {
            display += '════════ COMMAND ════════\n' + data.artisan_command + '\n\n';
        }
        if (!data.exists) {
            display += '════════ LOG FILE ════════\n(File log tidak ditemukan: ' + (data.log_file || '-') + ')\n\nKemungkinan penyebab:\n- Spawn process belum sempat menulis ke file (gagal di tahap awal)\n- Permission storage/logs tidak writable\n- Path log salah';
        } else {
            display += '════════ LOG FILE (' + (data.size || 0) + ' bytes' + (data.truncated ? ', tail 200KB' : '') + ') ════════\n';
            display += data.content || '(kosong)';
        }
        document.getElementById('logContent').textContent = display;
        // Scroll ke bawah supaya lihat tail terakhir
        document.getElementById('logContent').scrollTop = document.getElementById('logContent').scrollHeight;
    } catch (err) {
        document.getElementById('logContent').textContent = 'Error: ' + err.message;
    }
}

function closeLogModal() { document.getElementById('logModal').style.display = 'none'; }

function copyLogContent() {
    const text = document.getElementById('logContent').textContent;
    navigator.clipboard.writeText(text).then(() => alert('Log disalin ke clipboard.'));
}

function showDiagnoseInfo() { document.getElementById('diagnoseModal').style.display = 'flex'; }
function closeDiagnoseModal() { document.getElementById('diagnoseModal').style.display = 'none'; }

// On page load, attach polling jika ada batch yang masih running.
(async function attachActiveBatch() {
    try {
        const res = await fetch(`{{ route('admin.ai_products.batch.list') }}`, { headers: {'Accept':'application/json'} });
        const data = await res.json();
        if (data.success && data.batches.length > 0) {
            const active = data.batches.find(b => b.status === 'running' || b.status === 'queued');
            if (active) startPolling(active.id);
        }
    } catch (e) { /* ignore */ }
})();

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>
@endsection
