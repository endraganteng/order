@extends('admin.layout')

@section('title', 'Alokasi Dana')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<script src="{{ asset('js/finance-rupiah.js') }}" defer></script>
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📊 Alokasi Dana</h1>
            <p class="fm-page-subtitle">Pembagian persentase pendapatan ke kategori pengeluaran</p>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="openModal()">+ Tambah Alokasi</button>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Simulasi --}}
    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Simulasi Alokasi</label>
            <input type="number" class="fm-input fm-rupiah" id="simTotal" placeholder="Total pendapatan" style="width:220px;">
        </div>
        <button class="fm-btn fm-btn-outline" onclick="simulate()">🧮 Hitung</button>
        <div id="simResult" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;"></div>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Kategori</th><th>Persentase</th><th>Berlaku Dari</th><th>Sampai</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach($allocations as $a)
                <tr data-id="{{ $a['id'] }}">
                    <td><strong>{{ $a['category_name'] }}</strong></td>
                    <td><span class="fm-money">{{ $a['percentage'] }}%</span></td>
                    <td>{{ \Carbon\Carbon::parse($a['effective_date'])->format('d/m/Y') }}</td>
                    <td>{{ $a['end_date'] ? \Carbon\Carbon::parse($a['end_date'])->format('d/m/Y') : '—' }}</td>
                    <td><span class="fm-badge fm-badge-{{ $a['is_active'] ? 'active' : 'inactive' }}">{{ $a['is_active'] ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td style="font-size:12px;color:#64748b;">{{ $a['notes'] ?? '' }}</td>
                    <td>
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="editAlloc({{ json_encode($a) }})">✏️</button>
                        <button class="fm-btn fm-btn-sm fm-btn-danger" onclick="deleteAlloc({{ $a['id'] }})">🗑️</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php $totalPct = collect($allocations)->where('is_active', true)->sum('percentage'); @endphp
    <div class="fm-alert {{ abs($totalPct - 100) < 0.01 ? 'fm-alert-success' : 'fm-alert-warning' }}" style="margin-top:12px;">
        Total alokasi aktif: <strong>{{ $totalPct }}%</strong> {{ abs($totalPct - 100) < 0.01 ? '✅' : '⚠️ (harus 100%)' }}
    </div>

    {{-- Modal --}}
    <div class="fm-modal-backdrop" id="modal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="modalTitle">Tambah Alokasi</span>
                <button class="fm-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="allocForm">
                    <input type="hidden" name="id" id="fId">
                    <div class="fm-form-group">
                        <label class="fm-label">Kategori (Expense)</label>
                        <select class="fm-select" name="finance_category_id" id="fCatId" required>
                            <option value="">-- Pilih --</option>
                            @foreach($categories as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Persentase (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="fm-input" name="percentage" id="fPct" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Berlaku Dari</label>
                        <input type="date" class="fm-input" name="effective_date" id="fFrom" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Sampai (opsional)</label>
                        <input type="date" class="fm-input" name="end_date" id="fTo">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Catatan</label>
                        <input type="text" class="fm-input" name="notes" id="fNotes">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveAlloc()">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
function showToast(msg, type='success'){const t=document.getElementById('toast');t.textContent=msg;t.className='fm-toast '+type+' show';setTimeout(()=>t.classList.remove('show'),3000);}
function openModal(t='Tambah Alokasi'){document.getElementById('modalTitle').textContent=t;document.getElementById('modal').classList.add('active');}
function closeModal(){document.getElementById('modal').classList.remove('active');document.getElementById('allocForm').reset();document.getElementById('fId').value='';}

function editAlloc(a){
    document.getElementById('fId').value=a.id;
    document.getElementById('fCatId').value=a.finance_category_id;
    document.getElementById('fPct').value=a.percentage;
    document.getElementById('fFrom').value=a.effective_date?.split('T')[0]||'';
    document.getElementById('fTo').value=a.end_date?.split('T')[0]||'';
    document.getElementById('fNotes').value=a.notes||'';
    openModal('Edit Alokasi');
}

async function saveAlloc(){
    const fd=new FormData(document.getElementById('allocForm'));
    const body=Object.fromEntries(fd);const id=body.id;delete body.id;
    const url=id?'{{url("admin/finance/allocations")}}/'+id:'{{route("admin.finance.allocations.store")}}';
    try{
        const res=await fetch(url,{method:id?'PUT':'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)});
        const data=await res.json();
        if(data.success){showToast('Berhasil!');closeModal();location.reload();}
        else showToast(data.message||'Gagal','error');
    }catch(e){showToast(e.message,'error');}
}

async function deleteAlloc(id){
    if(!confirm('Hapus alokasi ini?'))return;
    try{
        const res=await fetch('{{url("admin/finance/allocations")}}/'+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
        const data=await res.json();
        if(data.success){showToast('Dihapus!');document.querySelector(`tr[data-id="${id}"]`).remove();}
    }catch(e){showToast(e.message,'error');}
}

async function simulate(){
    const raw=document.getElementById('simTotal').value.replace(/\./g,'');
    if(!raw){showToast('Masukkan total pendapatan','error');return;}
    try{
        const res=await fetch('{{route("admin.finance.allocations.simulate")}}',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({total:parseInt(raw)})});
        const data=await res.json();
        const el=document.getElementById('simResult');
        el.innerHTML=data.map(d=>`<span class="fm-badge fm-badge-draft">${d.category_name}: <strong>Rp ${d.amount.toLocaleString('id')}</strong> (${d.percentage}%)</span>`).join('');
    }catch(e){showToast(e.message,'error');}
}
</script>
@endpush
