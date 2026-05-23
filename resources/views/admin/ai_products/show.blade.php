@extends('admin.layout')

@section('title', 'Detail Knowledge: '.$job->product_name)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.ai_products.index') }}" style="color:var(--color-primary); text-decoration:none; font-size:13px;">← Kembali ke daftar</a>
        <h2 class="page-title" style="margin-top:6px;">{{ $job->product_name }}</h2>
        <p style="color:var(--color-text-muted); font-size:13px;">
            Job #{{ $job->id }} ·
            <code>{{ $job->product_id }}</code> ·
            kategori: {{ $categoryName }} ·
            base: <strong>{{ $job->base_name ?? '-' }}</strong>
            @if($job->variant_label) · varian: <span style="background:#eef2ff; padding:1px 6px; border-radius:4px; color:#4338ca;">{{ $job->variant_label }}</span>@endif
        </p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        @if(in_array($job->status, ['pending_review','needs_review','failed'], true))
            <button type="button" class="btn" style="background:var(--color-success); color:#fff;" onclick="approveJob()">✅ Approve & Sync</button>
            <button type="button" class="btn" style="background:var(--color-danger); color:#fff;" onclick="rejectJob()">✕ Reject</button>
        @elseif($job->status === 'approved')
            <button type="button" class="btn" style="background:var(--color-info); color:#fff;" onclick="resyncVector()">🔄 Re-sync Vector</button>
        @endif
    </div>
</div>

<div style="display:grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap:18px;">
    <div>
        <div style="background:#fff; padding:18px; border-radius:8px; box-shadow:var(--shadow-sm); margin-bottom:16px;">
            <h3 style="font-size:15px; margin-bottom:14px;">Knowledge</h3>

            @if(!$knowledge)
                <p style="color:var(--color-text-muted);">Belum ada knowledge tersimpan untuk produk ini.</p>
            @else
                @php $tipe = $knowledge['tipe_produk'] ?? 'general'; @endphp
                <div style="margin-bottom:14px; padding:8px 10px; background:#f0f9ff; border-radius:6px; font-size:13px;">
                    <strong>Tipe produk:</strong>
                    <span style="display:inline-block; padding:2px 8px; background:#0284c7; color:#fff; border-radius:4px; font-size:11px; margin-left:6px;">{{ $tipe }}</span>
                    <span style="color:var(--color-text-muted); font-size:11px; margin-left:8px;">(klasifikasi otomatis dari kategori)</span>
                </div>
                <form id="knowledgeForm" onsubmit="saveKnowledge(event)">
                    @php
                        $arrFields = ['fungsi','target_hewan','gejala_terkait','kategori_penggunaan','ukuran_varian'];
                        $textFields = ['brand','manfaat','aturan_pakai','peringatan'];
                    @endphp

                    @foreach($textFields as $field)
                        <div style="margin-bottom:12px;">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">{{ ucfirst(str_replace('_',' ',$field)) }}</label>
                            @if(in_array($field,['manfaat','aturan_pakai','peringatan']))
                                <textarea name="{{ $field }}" rows="3" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">{{ $knowledge[$field] ?? '' }}</textarea>
                            @else
                                <input type="text" name="{{ $field }}" value="{{ $knowledge[$field] ?? '' }}" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
                            @endif
                        </div>
                    @endforeach

                    @foreach($arrFields as $field)
                        @php $arr = is_array($knowledge[$field] ?? null) ? $knowledge[$field] : []; @endphp
                        <div style="margin-bottom:12px;">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">{{ ucfirst(str_replace('_',' ',$field)) }} <span style="color:var(--color-text-muted); font-weight:normal;">(pisah dengan koma)</span></label>
                            <input type="text" name="{{ $field }}" value="{{ implode(', ', $arr) }}" data-array="1" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
                        </div>
                    @endforeach

                    {{-- Spesifikasi: key-value editor --}}
                    @php $spec = is_array($knowledge['spesifikasi'] ?? null) ? $knowledge['spesifikasi'] : []; @endphp
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Spesifikasi <span style="color:var(--color-text-muted); font-weight:normal;">(tabel kunci-nilai bebas: panjang, material, kapasitas, dll)</span></label>
                        <div id="specEditor" style="border:1px solid var(--color-border); border-radius:6px; padding:8px; background:#fafbfc;">
                            @forelse($spec as $sk => $sv)
                                <div class="spec-row" style="display:flex; gap:6px; margin-bottom:6px;">
                                    <input type="text" placeholder="kunci (mis. panjang)" value="{{ $sk }}" data-spec-key style="flex:1; padding:6px; border:1px solid var(--color-border); border-radius:4px;">
                                    <input type="text" placeholder="nilai (mis. 180cm)" value="{{ is_array($sv) ? implode(', ', $sv) : $sv }}" data-spec-value style="flex:2; padding:6px; border:1px solid var(--color-border); border-radius:4px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:0 10px; border-radius:4px; cursor:pointer;">×</button>
                                </div>
                            @empty
                                {{-- empty initial --}}
                            @endforelse
                        </div>
                        <button type="button" onclick="addSpecRow()" style="margin-top:6px; background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px;">+ Tambah baris spesifikasi</button>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:6px;">💾 Simpan Edit Knowledge</button>
                </form>
            @endif
        </div>

        @if($knowledge && !empty($knowledge['sources']))
            <div style="background:#fff; padding:18px; border-radius:8px; box-shadow:var(--shadow-sm); margin-bottom:16px;">
                <h3 style="font-size:15px; margin-bottom:10px;">Sumber ({{ count($knowledge['sources']) }})</h3>
                @foreach($knowledge['sources'] as $src)
                    <div style="padding:8px 0; border-top:1px solid var(--color-border); font-size:13px;">
                        <div style="font-weight:600;">{{ $src['title'] ?? '-' }}
                            <span style="font-size:11px; padding:1px 6px; border-radius:3px; margin-left:6px;
                                background:{{ ($src['source_type'] ?? '')==='official_website' ? '#dcfce7' : (($src['source_type'] ?? '')==='marketplace' ? '#fef3c7' : '#f1f5f9') }};
                                color:{{ ($src['source_type'] ?? '')==='official_website' ? '#15803d' : (($src['source_type'] ?? '')==='marketplace' ? '#a16207' : '#475569') }};">
                                {{ $src['source_type'] ?? 'unknown' }}
                            </span>
                        </div>
                        @if(!empty($src['url']))
                            <a href="{{ $src['url'] }}" target="_blank" rel="noopener" style="font-size:11px; color:var(--color-primary); word-break:break-all;">{{ $src['url'] }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div style="background:#fff; padding:18px; border-radius:8px; box-shadow:var(--shadow-sm);">
            <h3 style="font-size:15px; margin-bottom:10px;">Log</h3>
            @forelse($logs as $log)
                <div style="padding:8px 0; border-top:1px solid var(--color-border); font-size:12px;">
                    <div><strong>{{ $log->action }}</strong> · <span style="color:var(--color-text-muted);">{{ $log->created_at?->format('d M Y H:i:s') }}</span></div>
                    <div style="color:var(--color-text-secondary);">{{ $log->message }}</div>
                </div>
            @empty
                <p style="color:var(--color-text-muted); font-size:13px;">Belum ada log.</p>
            @endforelse
        </div>
    </div>

    <div>
        <div style="background:#fff; padding:18px; border-radius:8px; box-shadow:var(--shadow-sm); margin-bottom:16px;">
            <h3 style="font-size:15px; margin-bottom:10px;">Status Job</h3>
            <table style="width:100%; font-size:13px;">
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Status</td><td style="padding:4px 0; font-weight:600;">{{ $job->status }}</td></tr>
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Source count</td><td style="padding:4px 0;">{{ $job->source_count }}</td></tr>
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Confidence</td><td style="padding:4px 0;">{{ $job->confidence_score !== null ? number_format((float)$job->confidence_score,3) : '-' }}</td></tr>
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Generated by</td><td style="padding:4px 0;">{{ $job->generated_by ?? '-' }}</td></tr>
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Approved by</td><td style="padding:4px 0;">{{ $job->approved_by ?? '-' }}</td></tr>
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Approved at</td><td style="padding:4px 0;">{{ $job->approved_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                <tr><td style="padding:4px 0; color:var(--color-text-muted);">Inherited?</td><td style="padding:4px 0;">{{ $job->is_inherited ? 'Ya, dari '.$job->inherited_from_product_id : 'Tidak' }}</td></tr>
                @if($job->error_message)
                    <tr><td style="padding:4px 0; color:var(--color-text-muted); vertical-align:top;">Error</td><td style="padding:4px 0; color:var(--color-danger);">{{ $job->error_message }}</td></tr>
                @endif
            </table>
        </div>
    </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const jobId = {{ $job->id }};

async function approveJob() {
    if (!confirm('Approve knowledge dan sync ke vector store?')) return;
    const res = await fetch(`{{ url('admin/ai-products') }}/${jobId}/approve`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: '{}'
    });
    const data = await res.json();
    if (data.success) {
        alert('Approved. Vector sync: ' + (data.vector_sync?.status || 'unknown'));
        location.reload();
    } else {
        alert('Gagal: ' + (data.message || 'unknown'));
    }
}

async function rejectJob() {
    const reason = prompt('Alasan reject?');
    if (reason === null) return;
    const res = await fetch(`{{ url('admin/ai-products') }}/${jobId}/reject`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({reason: reason})
    });
    const data = await res.json();
    if (data.success) { alert('Rejected.'); location.reload(); }
    else alert('Gagal: ' + (data.message || 'unknown'));
}

async function resyncVector() {
    const res = await fetch(`{{ url('admin/ai-products') }}/${jobId}/resync`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: '{}'
    });
    const data = await res.json();
    alert(data.success ? 'Re-sync: ' + (data.sync?.status || 'ok') : 'Gagal: ' + (data.message || 'unknown'));
}

async function saveKnowledge(e) {
    e.preventDefault();
    const form = document.getElementById('knowledgeForm');
    const body = {};
    form.querySelectorAll('input[name],textarea[name]').forEach(el => {
        if (el.dataset.array === '1') {
            body[el.name] = el.value.split(',').map(s => s.trim()).filter(Boolean);
        } else {
            body[el.name] = el.value;
        }
    });
    // Spesifikasi key-value
    const spec = {};
    document.querySelectorAll('#specEditor .spec-row').forEach(row => {
        const k = row.querySelector('[data-spec-key]').value.trim();
        const v = row.querySelector('[data-spec-value]').value.trim();
        if (k && v) spec[k] = v;
    });
    body.spesifikasi = spec;

    const res = await fetch(`{{ url('admin/ai-products') }}/${jobId}/knowledge`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify(body)
    });
    const data = await res.json();
    alert(data.success ? 'Knowledge updated.' : 'Gagal: ' + (data.message || 'unknown'));
    if (data.success) location.reload();
}

function addSpecRow() {
    const editor = document.getElementById('specEditor');
    const row = document.createElement('div');
    row.className = 'spec-row';
    row.style.cssText = 'display:flex; gap:6px; margin-bottom:6px;';
    row.innerHTML = `
        <input type="text" placeholder="kunci (mis. panjang)" data-spec-key style="flex:1; padding:6px; border:1px solid var(--color-border); border-radius:4px;">
        <input type="text" placeholder="nilai (mis. 180cm)" data-spec-value style="flex:2; padding:6px; border:1px solid var(--color-border); border-radius:4px;">
        <button type="button" onclick="this.parentElement.remove()" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:0 10px; border-radius:4px; cursor:pointer;">×</button>
    `;
    editor.appendChild(row);
}
</script>
@endsection
