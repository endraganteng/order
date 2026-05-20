@extends('admin.layout')

@section('title', 'Hutang Supplier')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<script src="{{ asset('js/finance-rupiah.js') }}" defer></script>
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📋 Hutang Supplier</h1>
            <p class="fm-page-subtitle">Catat dan kelola hutang ke supplier</p>
        </div>
        <button class="fm-btn fm-btn-primary" onclick="openModal()">+ Catat Hutang</button>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Summary --}}
    <div class="fm-cards">
        <div class="fm-card red">
            <div class="fm-card-icon">💳</div>
            <div class="fm-card-value fm-money expense">Rp {{ number_format($summary['total_hutang'], 0, ',', '.') }}</div>
            <div class="fm-card-label">Total Hutang Belum Lunas</div>
        </div>
        <div class="fm-card amber">
            <div class="fm-card-icon">📋</div>
            <div class="fm-card-value">{{ $summary['jumlah_hutang'] }}</div>
            <div class="fm-card-label">Jumlah Hutang Aktif</div>
        </div>
        <div class="fm-card {{ $summary['jatuh_tempo_minggu_ini'] > 0 ? 'red' : '' }}">
            <div class="fm-card-icon">⏰</div>
            <div class="fm-card-value">{{ $summary['jatuh_tempo_minggu_ini'] }}</div>
            <div class="fm-card-label">Jatuh Tempo 7 Hari</div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="fm-filter">
        <div class="fm-form-group">
            <label class="fm-label">Status</label>
            <select class="fm-select" id="filterStatus" onchange="window.location.href='{{ route('admin.finance.debts') }}?status='+this.value" style="width:150px;">
                <option value="">Semua</option>
                <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Belum Bayar</option>
                <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Cicilan</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas</option>
            </select>
        </div>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr><th>Tanggal</th><th>Supplier</th><th>Keterangan</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Dibayar</th><th style="text-align:right">Sisa</th><th>Jatuh Tempo</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach($debts as $d)
                @php $sisa = $d['amount'] - $d['paid']; @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($d['debt_date'])->format('d/m/Y') }}</td>
                    <td><strong>{{ $d['supplier_name'] }}</strong></td>
                    <td style="font-size:12px;">{{ $d['description'] ?? '—' }}</td>
                    <td style="text-align:right" class="fm-money">Rp {{ number_format($d['amount'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money income">Rp {{ number_format($d['paid'], 0, ',', '.') }}</td>
                    <td style="text-align:right" class="fm-money expense">Rp {{ number_format($sisa, 0, ',', '.') }}</td>
                    <td>{{ $d['due_date'] ? \Carbon\Carbon::parse($d['due_date'])->format('d/m/Y') : '—' }}</td>
                    <td><span class="fm-badge fm-badge-{{ $d['status'] === 'paid' ? 'approved' : ($d['status'] === 'partial' ? 'pending' : 'failed') }}">{{ $d['status'] }}</span></td>
                    <td style="white-space:nowrap;">
                        @if($d['status'] !== 'paid')
                        <button class="fm-btn fm-btn-sm fm-btn-success" onclick="openPay({{ $d['id'] }}, '{{ $d['supplier_name'] }}', {{ $sisa }})">💰 Bayar</button>
                        @endif
                        @if((int)($d['paid'] ?? 0) > 0)
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="viewPayments({{ $d['id'] }}, '{{ $d['supplier_name'] }}')">📋 Riwayat</button>
                        @endif
                        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="openEditDebt({{ json_encode($d) }})">✏️</button>
                        @if((int)($d['paid'] ?? 0) === 0)
                        <button class="fm-btn fm-btn-sm fm-btn-danger" onclick="deleteDebt({{ $d['id'] }}, '{{ $d['supplier_name'] }}')">🗑️</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($debts) === 0)
    <div class="fm-empty"><div class="fm-empty-icon">✅</div><div class="fm-empty-text">Tidak ada hutang.</div></div>
    @endif

    {{-- Modal Catat Hutang --}}
    <div class="fm-modal-backdrop" id="modal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">Catat Hutang Baru</span>
                <button class="fm-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="debtForm">
                    <div class="fm-form-group">
                        <label class="fm-label">Nama Supplier</label>
                        <input type="text" class="fm-input" name="supplier_name" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah Hutang (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="amount" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Keterangan</label>
                        <input type="text" class="fm-input" name="description" placeholder="Beli pakan 10 sak, dll">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Tanggal Hutang</label>
                        <input type="date" class="fm-input" name="debt_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jatuh Tempo (opsional)</label>
                        <input type="date" class="fm-input" name="due_date">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveDebt()">Simpan</button>
            </div>
        </div>
    </div>

    {{-- Modal Bayar Hutang --}}
    <div class="fm-modal-backdrop" id="payModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="payTitle">Bayar Hutang</span>
                <button class="fm-modal-close" onclick="closePayModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="payForm">
                    <input type="hidden" name="debt_id" id="payDebtId">
                    <div class="fm-form-group">
                        <label class="fm-label">Dari Akun Kas</label>
                        <select class="fm-select" name="cash_account_id" required>
                            @foreach($accounts as $a)
                            <option value="{{ $a['id'] }}">{{ $a['name'] }} (Rp {{ number_format($a['balance'],0,',','.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah Bayar (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="amount" id="payAmount" required>
                        <p id="payRemaining" style="font-size:12px;color:#64748b;margin-top:4px;"></p>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Tanggal Bayar</label>
                        <input type="date" class="fm-input" name="payment_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Catatan (opsional)</label>
                        <input type="text" class="fm-input" name="notes" placeholder="Transfer/tunai/dll">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closePayModal()">Batal</button>
                <button class="fm-btn fm-btn-success" onclick="submitPay()">💰 Bayar</button>
            </div>
        </div>
    </div>

    {{-- Modal Edit Hutang --}}
    <div class="fm-modal-backdrop" id="editDebtModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">✏️ Edit Hutang</span>
                <button class="fm-modal-close" onclick="closeEditDebt()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="editDebtForm">
                    <input type="hidden" name="id" id="edId">
                    <div class="fm-form-group">
                        <label class="fm-label">Supplier</label>
                        <input type="text" class="fm-input" name="supplier_name" id="edSupplier" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah (Rp)</label>
                        <input type="number" class="fm-input" name="amount" id="edAmount" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Keterangan</label>
                        <input type="text" class="fm-input" name="description" id="edDesc">
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jatuh Tempo</label>
                        <input type="date" class="fm-input" name="due_date" id="edDue">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeEditDebt()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="submitEditDebt()">Simpan</button>
            </div>
        </div>
    </div>
    {{-- Modal Riwayat Pembayaran --}}
    <div class="fm-modal-backdrop" id="historyModal">
        <div class="fm-modal" style="max-width:500px;">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="historyTitle">📋 Riwayat Pembayaran</span>
                <button class="fm-modal-close" onclick="document.getElementById('historyModal').classList.remove('active')">&times;</button>
            </div>
            <div class="fm-modal-body" id="historyBody" style="font-size:13px;">Memuat...</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
function showToast(msg,type='success'){const t=document.getElementById('toast');t.textContent=msg;t.className='fm-toast '+type+' show';setTimeout(()=>t.classList.remove('show'),3000);}

function openModal(){document.getElementById('modal').classList.add('active');}
function closeModal(){document.getElementById('modal').classList.remove('active');document.getElementById('debtForm').reset();}

function openPay(id, name, sisa){
    document.getElementById('payDebtId').value = id;
    document.getElementById('payTitle').textContent = 'Bayar Hutang: ' + name;
    const input = document.getElementById('payAmount');
    input.value = sisa.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.dataset.max = sisa;
    document.getElementById('payRemaining').textContent = 'Sisa hutang: Rp ' + sisa.toLocaleString('id') + ' — bisa bayar sebagian.';
    document.getElementById('payModal').classList.add('active');
}
function closePayModal(){document.getElementById('payModal').classList.remove('active');document.getElementById('payForm').reset();}

async function saveDebt(){
    const fd = new FormData(document.getElementById('debtForm'));
    const body = Object.fromEntries(fd);
    body.amount = parseInt((body.amount+'').replace(/\./g,'')) || 0;
    try{
        const res = await fetch('{{ route("admin.finance.debts.store") }}',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)});
        const data = await res.json();
        if(data.success){showToast('Hutang dicatat!');closeModal();setTimeout(()=>location.reload(),800);}
        else showToast(data.message||'Gagal','error');
    }catch(e){showToast(e.message,'error');}
}

async function submitPay(){
    const fd = new FormData(document.getElementById('payForm'));
    const body = Object.fromEntries(fd);
    const id = body.debt_id; delete body.debt_id;
    body.amount = parseInt((body.amount+'').replace(/\./g,'')) || 0;
    try{
        const res = await fetch('{{ url("admin/finance/debts") }}/'+id+'/pay',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)});
        const data = await res.json();
        if(data.success){showToast('Pembayaran dicatat!');closePayModal();setTimeout(()=>location.reload(),800);}
        else showToast(data.message||'Gagal','error');
    }catch(e){showToast(e.message,'error');}
}

function openEditDebt(d) {
    document.getElementById('edId').value = d.id;
    document.getElementById('edSupplier').value = d.supplier_name;
    document.getElementById('edAmount').value = d.amount;
    document.getElementById('edDesc').value = d.description || '';
    document.getElementById('edDue').value = (d.due_date || '').split('T')[0] || '';
    document.getElementById('editDebtModal').classList.add('active');
}
function closeEditDebt() { document.getElementById('editDebtModal').classList.remove('active'); }

async function submitEditDebt() {
    const fd = new FormData(document.getElementById('editDebtForm'));
    const body = Object.fromEntries(fd);
    const id = body.id; delete body.id;
    body.amount = parseInt((body.amount+'').replace(/\./g,'')) || 0;
    try {
        const res = await fetch('{{ url("admin/finance/debts") }}/' + id, {method:'PUT', headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify(body)});
        const data = await res.json();
        if (data.success) { showToast('Hutang diupdate!'); closeEditDebt(); setTimeout(()=>location.reload(),800); }
        else showToast(data.message||'Gagal','error');
    } catch(e) { showToast(e.message,'error'); }
}

async function deleteDebt(id, name) {
    if (!confirm('Hapus hutang "' + name + '"?')) return;
    try {
        const res = await fetch('{{ url("admin/finance/debts") }}/' + id, {method:'DELETE', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
        const data = await res.json();
        if (data.success) { showToast('Hutang dihapus!'); setTimeout(()=>location.reload(),600); }
        else showToast(data.message||'Gagal','error');
    } catch(e) { showToast(e.message,'error'); }
}

async function viewPayments(id, name) {
    document.getElementById('historyTitle').textContent = '📋 Riwayat: ' + name;
    document.getElementById('historyBody').innerHTML = 'Memuat...';
    document.getElementById('historyModal').classList.add('active');
    try {
        const res = await fetch('{{ url("admin/finance/debts") }}/' + id + '/payments', {headers:{'Accept':'application/json'}});
        const data = await res.json();
        if (!data.length) {
            document.getElementById('historyBody').innerHTML = '<p style="color:#94a3b8;text-align:center;">Belum ada pembayaran.</p>';
            return;
        }
        let html = '<table style="width:100%;border-collapse:collapse;">';
        html += '<tr style="border-bottom:2px solid #e2e8f0;"><th style="padding:8px 0;text-align:left;">Tanggal</th><th style="text-align:left;">Akun</th><th style="text-align:right;">Jumlah</th><th style="text-align:left;">Catatan</th></tr>';
        data.forEach(p => {
            html += '<tr style="border-bottom:1px solid #f1f5f9;">';
            html += '<td style="padding:8px 0;">' + p.payment_date + '</td>';
            html += '<td>' + (p.account_name||'-') + '</td>';
            html += '<td style="text-align:right;font-weight:600;">Rp ' + parseInt(p.amount).toLocaleString('id') + '</td>';
            html += '<td style="color:#64748b;">' + (p.notes||'-') + '</td>';
            html += '</tr>';
        });
        html += '</table>';
        document.getElementById('historyBody').innerHTML = html;
    } catch(e) { document.getElementById('historyBody').innerHTML = 'Error: ' + e.message; }
}
</script>
@endpush
