@extends('admin.layout')

@section('title', '🎁 Bonus Manual')

@section('content')
<div class="container">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2 style="margin:0;">🎁 Bonus Point Manual</h2>
            <p class="muted small mb-0">Supervisor bisa menambah / mengurangi point ke beberapa karyawan sekaligus dengan alasan kustom.</p>
        </div>
        <form method="GET" style="display:flex; gap:8px; align-items:center;">
            <label class="small fw-bold" style="font-weight:600;">Bulan:</label>
            <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" style="padding:6px 8px; border:1px solid var(--color-border); border-radius:6px;">
        </form>
    </div>

    {{-- ── FORM TAMBAH BONUS BULK ── --}}
    <div class="card" style="background:#fff; border-radius:10px; box-shadow:var(--shadow-sm); padding:18px; margin-bottom:20px; border-top:4px solid var(--color-primary);">
        <h3 style="margin:0 0 10px; font-size:15px;">➕ Tambah Bonus / Penalti Manual</h3>
        <form id="bonusForm" onsubmit="submitBonus(event)">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Tanggal</label>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" required style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Poin <span class="muted small">(positif = bonus, negatif = pengurangan)</span></label>
                    <input type="number" name="points" required min="-100" max="100" step="1" placeholder="contoh: 10 atau -5" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Alasan</label>
                <input type="text" name="reason" required maxlength="500" placeholder="contoh: Inisiatif bagus saat shift ramai, Telat tanpa kabar, dll" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px;">
            </div>

            <div style="margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-size:12px; font-weight:600;">Pilih Karyawan</label>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="selectAll(true)" style="background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11px;">Pilih Semua</button>
                        <button type="button" onclick="selectAll(false)" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11px;">Kosongkan</button>
                    </div>
                </div>
                <div style="border:1px solid var(--color-border); border-radius:8px; padding:6px; max-height:240px; overflow-y:auto; background:#fafbfc;">
                    @forelse($waiters as $w)
                        @php
                            $wid = $w['id'] ?? '';
                            $wname = $w['name'] ?? '-';
                            $wemail = $w['email'] ?? '';
                            $totalSoFar = $totalsByWaiter[$wid] ?? 0;
                        @endphp
                        <label class="waiter-pick" style="display:flex; gap:8px; align-items:center; padding:8px 10px; border-radius:6px; cursor:pointer; transition:background 0.15s;">
                            <input type="checkbox" name="waiter_ids[]" value="{{ $wid }}" class="waiter-cb" style="margin:0;">
                            <div style="flex:1;">
                                <div style="font-weight:600; font-size:13px;">{{ $wname }}</div>
                                <div style="font-size:11px; color:#64748b;">{{ $wemail }}</div>
                            </div>
                            @if($totalSoFar !== 0)
                                <span style="font-size:11px; padding:2px 8px; border-radius:10px; background:{{ $totalSoFar > 0 ? '#dcfce7' : '#fef2f2' }}; color:{{ $totalSoFar > 0 ? '#15803d' : '#dc2626' }}; font-weight:600;">
                                    {{ $totalSoFar > 0 ? '+' : '' }}{{ $totalSoFar }} pt bulan ini
                                </span>
                            @endif
                        </label>
                    @empty
                        <div class="muted" style="padding:14px; text-align:center;">Tidak ada karyawan.</div>
                    @endforelse
                </div>
                <div style="margin-top:6px; font-size:12px;">
                    <span class="muted">Karyawan terpilih:</span> <strong id="selectedCount">0</strong> dari {{ count($waiters) }}
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="padding:10px 18px;">💾 Terapkan ke Karyawan Terpilih</button>
        </form>
    </div>

    {{-- ── RIWAYAT BONUS BULAN INI ── --}}
    <div class="card" style="background:#fff; border-radius:10px; box-shadow:var(--shadow-sm); padding:18px;">
        <h3 style="margin:0 0 14px; font-size:15px;">📜 Riwayat Bulan {{ $month }} ({{ count($bonuses) }} entri)</h3>

        @if(count($bonuses) === 0)
            <p class="muted" style="text-align:center; padding:24px;">Belum ada bonus manual untuk bulan ini.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="text-align:left; padding:8px;">Tanggal</th>
                            <th style="text-align:left; padding:8px;">Karyawan</th>
                            <th style="text-align:right; padding:8px;">Poin</th>
                            <th style="text-align:left; padding:8px;">Alasan</th>
                            <th style="text-align:left; padding:8px;">Oleh</th>
                            <th style="text-align:right; padding:8px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bonuses as $b)
                            <tr style="border-top:1px solid var(--color-border);">
                                <td style="padding:8px;">{{ $b['date'] ?? '-' }}</td>
                                <td style="padding:8px;">
                                    <div style="font-weight:600;">{{ $b['waiter_name'] ?? '-' }}</div>
                                    <div style="font-size:11px; color:#64748b;">{{ $b['waiter_id'] ?? '' }}</div>
                                </td>
                                <td style="padding:8px; text-align:right;">
                                    @php $pts = (int)($b['points'] ?? 0); @endphp
                                    <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-weight:700; background:{{ $pts > 0 ? '#dcfce7' : '#fef2f2' }}; color:{{ $pts > 0 ? '#15803d' : '#dc2626' }};">
                                        {{ $pts > 0 ? '+' : '' }}{{ $pts }}
                                    </span>
                                </td>
                                <td style="padding:8px;">{{ $b['reason'] ?? '-' }}</td>
                                <td style="padding:8px; color:#64748b; font-size:11px;">
                                    {{ $b['created_by'] ?? '-' }}<br>
                                    <small>{{ isset($b['created_at']) ? date('d M H:i', (int)$b['created_at']) : '' }}</small>
                                </td>
                                <td style="padding:8px; text-align:right;">
                                    <button type="button" onclick="deleteBonus('{{ $b['bonus_id'] ?? '' }}')" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11px;">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<style>
    .waiter-pick:hover { background:#eef2ff; }
    .muted { color:#64748b; }
    .small { font-size:0.875rem; }
</style>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

function selectAll(state) {
    document.querySelectorAll('.waiter-cb').forEach(cb => { cb.checked = state; });
    updateSelectedCount();
}

function updateSelectedCount() {
    const cnt = document.querySelectorAll('.waiter-cb:checked').length;
    document.getElementById('selectedCount').textContent = cnt;
}
document.querySelectorAll('.waiter-cb').forEach(cb => cb.addEventListener('change', updateSelectedCount));

async function submitBonus(e) {
    e.preventDefault();
    const form = document.getElementById('bonusForm');
    const fd = new FormData(form);
    const body = {
        waiter_ids: fd.getAll('waiter_ids[]'),
        points: parseInt(fd.get('points'), 10),
        reason: fd.get('reason').trim(),
        date: fd.get('date'),
    };
    if (body.waiter_ids.length === 0) { alert('Pilih minimal 1 karyawan.'); return; }
    if (body.points === 0 || isNaN(body.points)) { alert('Poin tidak boleh 0 atau kosong.'); return; }
    if (!body.reason) { alert('Alasan wajib diisi.'); return; }

    if (!confirm(`Tambahkan ${body.points > 0 ? '+' : ''}${body.points} poin ke ${body.waiter_ids.length} karyawan?\n\nAlasan: ${body.reason}`)) return;

    try {
        const res = await fetch(`{{ route('admin.bonus.manual_bonus.store') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        alert(data.message + (data.failed > 0 ? `\n\n${data.failed} gagal.` : ''));
        if (data.success) location.reload();
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function deleteBonus(id) {
    if (!id) return;
    if (!confirm('Hapus bonus ini? Tidak bisa di-undo.')) return;
    try {
        const res = await fetch(`{{ url('admin/bonus/manual-bonus') }}/${id}`, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message || 'Gagal hapus.');
    } catch (err) {
        alert('Error: ' + err.message);
    }
}
</script>
@endsection
