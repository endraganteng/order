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
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #fffbeb;">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 280px;">
                            <div style="font-weight: 700; font-size: 15px; color: #1f2937;">{{ $tx['waiter_name'] ?? 'Karyawan' }}</div>
                            <div style="font-size: 24px; font-weight: 700; color: #92400e; margin: 4px 0;">
                                Rp {{ number_format((int)($tx['amount'] ?? 0), 0, ',', '.') }}
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                                Diajukan: {{ \Carbon\Carbon::createFromTimestamp((int)($tx['created_at'] ?? time()))->format('d M Y H:i') }}
                            </div>
                            <div style="font-size: 13px; color: #475569; background: #fff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <div><strong>Bank:</strong> {{ $tx['bank_name'] ?? '-' }}</div>
                                <div><strong>No Rekening:</strong> {{ $tx['bank_account_number'] ?? '-' }}</div>
                                <div><strong>Atas Nama:</strong> {{ $tx['bank_account_holder'] ?? '-' }}</div>
                                @if(!empty($tx['note']))
                                    <div style="margin-top: 6px;"><strong>Catatan:</strong> {{ $tx['note'] }}</div>
                                @endif
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; flex-direction: column; min-width: 180px;">
                            <form method="POST" action="{{ route('admin.payroll.withdrawals.approve', $tx['id']) }}" onsubmit="return validateApprovePin(this);">
                                @csrf
                                <input type="password" name="supervisor_pin" maxlength="6" placeholder="PIN Supervisor" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; margin-bottom: 6px; text-align: center; letter-spacing: 4px;">
                                <button type="submit" style="background: #10b981; color: #fff; padding: 10px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; width: 100%;">✓ Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.payroll.withdrawals.reject', $tx['id']) }}">
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
@endsection

@push('scripts')
<script>
function validateApprovePin(form) {
    const pin = form.querySelector('input[name="supervisor_pin"]').value.trim();
    if (pin.length < 4) {
        form.querySelector('input[name="supervisor_pin"]').style.borderColor = '#ef4444';
        form.querySelector('input[name="supervisor_pin"]').placeholder = 'PIN minimal 4 digit!';
        return false;
    }
    return true;
}
</script>
@endpush
