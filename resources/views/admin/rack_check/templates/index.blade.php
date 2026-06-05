@extends('admin.layout')

@section('title', 'Template Cek Rak - Admin')

@section('content')
@php
    $dayLabels = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    $describeRecurrence = function ($tpl) use ($dayLabels) {
        $type = (string) ($tpl['recurrence_type'] ?? 'daily');
        if ($type === 'weekly') {
            $day = (int) ($tpl['weekly_day'] ?? 1);
            return 'Setiap '.($dayLabels[$day] ?? '-');
        }
        if ($type === 'every_n_days') {
            $n = (int) ($tpl['interval_days'] ?? 2);
            return "Setiap {$n} hari";
        }
        return 'Setiap hari';
    };
@endphp

<div style="max-width: 1100px; margin: 0 auto; padding: 0 12px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
        <div>
            <h2 style="margin: 0 0 6px; color: var(--color-text); font-size: clamp(22px, 5vw, 30px); display: flex; align-items: center; gap: 10px;">
                📦 Template Cek Rak
            </h2>
            <p style="margin: 0; color: var(--color-text-muted); font-size: 14px;">
                Template menentukan rak mana yang perlu dicek dan kapan jadwalnya. Pembagian ke karyawan dilakukan di halaman Planning.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="{{ route('admin.rack_check.planning.index') }}"
               style="background: var(--color-bg); color: var(--color-text-secondary); border: 1px solid var(--color-border); padding: 11px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: var(--shadow-sm); white-space: nowrap;">
                📋 Buka Planning
            </a>
            <a href="{{ route('admin.rack_check.templates.create') }}"
               style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 11px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: var(--shadow-sm); white-space: nowrap;">
                ➕ Buat Template Baru
            </a>
        </div>
    </div>

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.7); }
        }
        .status-row {
            padding: 8px 12px;
            background: var(--color-bg);
            border-radius: 7px;
            font-size: 12px;
            color: var(--color-text-secondary);
            line-height: 1.5;
        }
        .status-row .status-label {
            font-weight: 600;
            color: var(--color-text);
        }
    </style>

    <div style="margin-bottom: 16px; padding: 10px 14px; background: #f8fafc; border: 1px dashed var(--color-border); border-radius: 8px; font-size: 12px; color: var(--color-text-muted); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <span>📚 Butuh lihat task lama / mode AI Balancing?</span>
        <a href="{{ route('admin.tasks.rack.index') }}" style="color: var(--color-text-secondary); text-decoration: underline;">Buka arsip Cek Rak lama →</a>
    </div>

    @if(session('success'))
        <div style="background: var(--color-success-bg); border: 1px solid var(--color-success-border); color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: var(--color-danger-bg); border: 1px solid var(--color-danger-border); color: #7f1d1d; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            ⚠ {{ session('error') }}
        </div>
    @endif

    @if(count($templates) === 0)
        <div style="background: white; border: 2px dashed var(--color-border); border-radius: 12px; padding: 60px 20px; text-align: center; color: var(--color-text-muted);">
            <div style="font-size: 48px; margin-bottom: 12px;">📭</div>
            <h3 style="margin: 0 0 6px; color: var(--color-text); font-size: 18px;">Belum ada template cek rak otomatis</h3>
            <p style="margin: 0 0 16px; font-size: 14px;">Mulai buat template untuk mengotomasi cek rak harian dengan beban paling ringan.</p>
            <a href="{{ route('admin.rack_check.templates.create') }}"
               style="display: inline-block; background: var(--color-primary); color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
                ➕ Buat Template Pertama
            </a>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 14px;">
            @foreach($templates as $tpl)
                @php
                    $isActive = (bool) ($tpl['is_active'] ?? true);
                    $proofParts = [];
                    if ($tpl['requires_barcode_scan'] ?? false) $proofParts[] = 'Scan QR rak';
                    if ($tpl['requires_photo_before'] ?? false) $proofParts[] = 'Foto sebelum';
                    if ($tpl['requires_photo_proof'] ?? false) $proofParts[] = 'Foto sesudah';
                    $templateRacks = $tpl['_racks'] ?? [];
                    $rackCount = count($templateRacks);
                    $templateName = $tpl['name'] ?? $tpl['rack_name'] ?? $tpl['title'] ?? '—';
                @endphp
                <div class="rc-template-card" data-template-id="{{ $tpl['id'] }}" data-racks='@json($templateRacks)'
                     style="background: white; border: 1px solid var(--color-border); border-radius: 12px; padding: 16px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 10px; position: relative; {{ $isActive ? '' : 'opacity: 0.65;' }}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span style="font-weight: 700; color: var(--color-text); font-size: 16px; word-break: break-word;">{{ $templateName }}</span>
                            <span style="background: #f1f5f9; color: var(--color-text-secondary); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">{{ $rackCount }} rak</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            @if($isActive)
                                <span style="background: var(--color-success-bg); color: var(--color-success); padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap;">● Aktif</span>
                            @else
                                <span style="background: #f1f5f9; color: var(--color-text-muted); padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap;">○ Nonaktif</span>
                            @endif
                            <button type="button" class="kebab-trigger" onclick="toggleKebab(this)"
                                    style="background: none; border: none; cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 18px; line-height: 1; color: var(--color-text-muted); transition: background 0.1s;"
                                    onmouseenter="this.style.background='var(--color-bg)'" onmouseleave="this.style.background='none'">
                                ⋮
                            </button>
                        </div>
                    </div>

                    {{-- Kebab dropdown menu --}}
                    <div class="kebab-menu" style="display: none; position: absolute; top: 44px; right: 12px; background: white; border: 1px solid var(--color-border); border-radius: 10px; box-shadow: var(--shadow-md); z-index: 100; min-width: 200px; overflow: hidden;">
                        <a href="{{ route('admin.rack_check.templates.edit', $tpl['id']) }}"
                           style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; font-weight: 500; color: var(--color-text-secondary); text-decoration: none; transition: background 0.1s;"
                           onmouseenter="this.style.background='var(--color-bg)'" onmouseleave="this.style.background='transparent'">
                            ✏️ Edit Template
                        </a>
                        <div style="border-top: 1px solid var(--color-border); margin: 4px 0;"></div>
                        <form method="POST" action="{{ route('admin.rack_check.templates.toggle', $tpl['id']) }}"
                              onsubmit="return confirm('{{ $isActive ? 'Nonaktifkan template '.addslashes($templateName).'? Cron tidak akan generate task baru.' : 'Aktifkan kembali template '.addslashes($templateName).'?' }}');">
                            @csrf
                            <button type="submit"
                                    style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; font-weight: 500; color: {{ $isActive ? '#92400e' : 'var(--color-success)' }}; background: none; border: none; width: 100%; text-align: left; cursor: pointer; transition: background 0.1s;"
                                    onmouseenter="this.style.background='var(--color-bg)'" onmouseleave="this.style.background='transparent'">
                                {{ $isActive ? '⏸ Nonaktifkan' : '▶ Aktifkan' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.rack_check.templates.destroy', $tpl['id']) }}"
                              onsubmit="return confirm('Hapus template {{ addslashes($templateName) }}? Task pending hari ini akan dibatalkan.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; font-weight: 500; color: var(--color-danger); background: none; border: none; width: 100%; text-align: left; cursor: pointer; transition: background 0.1s;"
                                    onmouseenter="this.style.background='var(--color-danger-bg)'" onmouseleave="this.style.background='transparent'">
                                🗑 Hapus Template
                            </button>
                        </form>
                    </div>

                    <div style="font-size: 13px; color: var(--color-text-secondary); line-height: 1.6;">
                        <div><strong style="color: var(--color-text);">📅 Jadwal:</strong> {{ $describeRecurrence($tpl) }}</div>
                        <div><strong style="color: var(--color-text);">📸 Bukti:</strong>
                            @if(count($proofParts) > 0)
                                {{ implode(', ', $proofParts) }}
                            @else
                                <span style="color: var(--color-text-muted);">Tidak ada (hanya centang)</span>
                            @endif
                        </div>
                        @if(!empty($tpl['daily_cap']))
                            <div><strong style="color: var(--color-text);">⚙️ Maks petugas harian:</strong> {{ $tpl['daily_cap'] }} tugas/orang</div>
                        @endif
                    </div>

                    {{-- Collapsible rack status table --}}
                    <details style="margin-top: 4px;">
                        <summary style="cursor: pointer; font-size: 13px; font-weight: 600; color: var(--color-text-secondary); padding: 8px 0; list-style: none; display: flex; align-items: center; justify-content: space-between;">
                            <span>📦 Daftar Rak ({{ $rackCount }} rak)</span>
                            <span style="color: var(--color-text-muted); font-size: 12px; transition: transform 0.2s;" class="rack-chevron">▾</span>
                        </summary>
                        <div style="border-top: 1px solid #f1f5f9; padding-top: 8px; overflow-x: auto;">
                            <table style="width: 100%; font-size: 12px; border-collapse: collapse; min-width: 320px;">
                                <thead>
                                    <tr style="color: var(--color-text-muted); text-align: left; border-bottom: 1px solid var(--color-border);">
                                        <th style="padding: 6px 8px; font-weight: 600;">Rak</th>
                                        <th style="padding: 6px 8px; font-weight: 600;">Lokasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($templateRacks as $rack)
                                        <tr style="border-bottom: 1px solid #f8fafc;">
                                            <td style="padding: 6px 8px; font-weight: 500; color: var(--color-text);">{{ $rack['name'] ?? '—' }}</td>
                                            <td style="padding: 6px 8px; color: var(--color-text-muted);">{{ $rack['location'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function openOverflowModal() { document.getElementById('overflowModal').style.display = 'flex'; }
function closeOverflowModal() { document.getElementById('overflowModal').style.display = 'none'; }
document.getElementById('overflowModal')?.addEventListener('click', function (e) { if (e.target === this) closeOverflowModal(); });
</script>

{{-- Preview Modal --}}
<div id="previewModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: white; border-radius: 12px; max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-md);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
            <div style="font-weight: 700; color: var(--color-text); font-size: 16px;">🔍 Preview Pembagian</div>
            <button type="button" onclick="closePreview()" style="background: none; border: none; font-size: 22px; color: var(--color-text-muted); cursor: pointer; padding: 0; line-height: 1;">×</button>
        </div>
        <div style="padding: 12px 20px 0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <label style="font-size: 13px; font-weight: 600; color: var(--color-text);">Tanggal:</label>
            <input type="date" id="previewDatePicker" style="padding: 6px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
            <button type="button" id="previewRefreshBtn" style="background: var(--color-primary); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">Refresh</button>
        </div>
        <div id="previewBody" style="padding: 18px 20px; font-size: 14px; line-height: 1.6;">
            <div style="text-align: center; color: var(--color-text-muted); padding: 30px 0;">Memuat preview…</div>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end;">
            <button type="button" onclick="closePreview()" style="background: var(--color-bg); color: var(--color-text-secondary); border: 1px solid var(--color-border); padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Tutup</button>
        </div>
    </div>
</div>

<script>
// Kebab menu toggle
function closeAllKebabs() {
    document.querySelectorAll('.kebab-menu').forEach(m => m.style.display = 'none');
}
function toggleKebab(btn) {
    const card = btn.closest('.rc-template-card');
    const menu = card.querySelector('.kebab-menu');
    const isOpen = menu.style.display === 'block';
    closeAllKebabs();
    if (!isOpen) menu.style.display = 'block';
}
document.addEventListener('click', function (e) {
    if (!e.target.closest('.kebab-trigger') && !e.target.closest('.kebab-menu')) {
        closeAllKebabs();
    }
});
</script>

@endsection
