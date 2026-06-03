@extends('admin.layout')

@section('title', '📋 Penarikan Pending')

@section('content')
<div class="container">
    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.payroll.index') }}" style="color: #3b82f6; text-decoration: none; font-size: 14px;">← Kembali ke Payroll</a>
    </div>

    <div class="page-header" style="margin-bottom: 20px;">
        <h2>📋 Penarikan Pending</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Approve atau reject permintaan penarikan dari karyawan.</p>
    </div>

    @if(session('success'))
        <div style="background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="card" style="padding: 16px;">
        @if(empty($pending))
            <div style="text-align: center; padding: 40px; color: #64748b;">
                <div style="font-size: 48px; margin-bottom: 8px;">📭</div>
                <div style="font-size: 16px;">Tidak ada penarikan pending saat ini.</div>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 12px;">
            @foreach($pending as $tx)
                @php
                    $txId = $tx['id'];
                    $txName = $tx['waiter_name'] ?? 'Karyawan';
                    $txAmount = (int)($tx['amount'] ?? 0);
                    $txDate = \Carbon\Carbon::createFromTimestamp((int)($tx['created_at'] ?? time()))->format('d M Y H:i');
                    $txBank = $tx['bank_name'] ?? '-';
                    $txAcc = $tx['bank_account_number'] ?? '-';
                    $txHolder = $tx['bank_account_holder'] ?? '-';
                    $txNote = $tx['note'] ?? '';
                @endphp
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #fffbeb;">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 280px;">
                            <div style="font-weight: 700; font-size: 15px; color: #1f2937;">{{ $txName }}</div>
                            <div style="font-size: 24px; font-weight: 700; color: #92400e; margin: 4px 0;">
                                Rp {{ number_format($txAmount, 0, ',', '.') }}
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                                Diajukan: {{ $txDate }}
                            </div>
                            <div style="font-size: 13px; color: #475569; background: #fff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <div><strong>Bank:</strong> {{ $txBank }}</div>
                                <div><strong>No Rekening:</strong> {{ $txAcc }}</div>
                                <div><strong>Atas Nama:</strong> {{ $txHolder }}</div>
                                @if($txNote)
                                    <div style="margin-top: 6px;"><strong>Catatan:</strong> {{ $txNote }}</div>
                                @endif
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; flex-direction: column; min-width: 180px;">
                            <button type="button" onclick="openApproveModal('{{ $txId }}', '{{ addslashes($txName) }}', {{ $txAmount }})" style="background: #10b981; color: #fff; padding: 10px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; width: 100%;">✓ Approve</button>
                            <form method="POST" action="{{ route('admin.payroll.withdrawals.reject', $txId) }}">
                                @csrf
                                <input type="text" name="reason" maxlength="200" placeholder="Alasan reject (opsional)" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; margin-bottom: 6px;">
                                <button type="submit" style="background: #ef4444; color: #fff; padding: 10px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; width: 100%;">✗ Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
            </div>
        @endif
    </div>
</div>

{{-- MODAL: Pilih Kas untuk Approve --}}
<div id="approveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:480px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <h3 style="margin:0 0 4px;">💵 Konfirmasi Pembayaran Penarikan</h3>
        <p style="color:#64748b; font-size:13px; margin-top:0; margin-bottom:20px;">Pilih sumber kas untuk membayar penarikan ini.</p>

        <form method="POST" action="" id="approveForm">
            @csrf
            <div id="modalTxInfo" style="background:#f8fafc; border-radius:8px; padding:12px; margin-bottom:16px; font-size:13px;">
                <div><strong id="modalTxName"></strong></div>
                <div style="font-size:20px; font-weight:700; color:#92400e; margin-top:4px;" id="modalTxAmount"></div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Sumber Kas</label>
                <select name="cash_account_id" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <option value="">-- Pilih akun kas --</option>
                    @foreach(\Illuminate\Support\Facades\DB::table('cash_accounts')->where('is_active', 1)->orderBy('name')->get(['id','name','balance']) as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->name }} (Saldo Rp {{ number_format($ca->balance, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">Catatan (opsional)</label>
                <input type="text" name="note" maxlength="200" class="form-control" placeholder="Contoh: Dibayar dari Kas Laci" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">PIN Supervisor</label>
                <input type="password" name="supervisor_pin" maxlength="32" required autocomplete="new-password" class="form-control" placeholder="••••" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; letter-spacing: 4px;">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="closeApproveModal()" class="btn" style="flex:1; background:#e2e8f0; color:#475569; padding:10px 16px; border-radius:6px; border:none; font-weight:600; cursor:pointer;">Batal</button>
                <button type="submit" class="btn" style="flex:1; background:#10b981; color:#fff; padding:10px 16px; border-radius:6px; border:none; font-weight:600; cursor:pointer;">💵 Konfirmasi Approve & Bayar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openApproveModal(txId, name, amount) {
    var modal = document.getElementById('approveModal');
    var form = document.getElementById('approveForm');
    form.action = '{{ url("admin/payroll/withdrawals") }}/' + txId + '/approve';
    document.getElementById('modalTxName').textContent = name;
    document.getElementById('modalTxAmount').textContent = 'Rp ' + amount.toLocaleString('id-ID');
    modal.style.display = 'flex';
}

function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('approveModal').addEventListener('click', function(e) {
    if (e.target === this) closeApproveModal();
});

// Validate PIN before submit
document.getElementById('approveForm').addEventListener('submit', function(e) {
    var pin = this.querySelector('input[name="supervisor_pin"]').value.trim();
    if (pin.length < 4) {
        e.preventDefault();
        alert('PIN supervisor minimal 4 digit.');
        return;
    }
    var cash = this.querySelector('select[name="cash_account_id"]').value;
    if (! cash) {
        e.preventDefault();
        alert('Pilih akun kas dulu.');
        return;
    }
});
</script>
@endpush
