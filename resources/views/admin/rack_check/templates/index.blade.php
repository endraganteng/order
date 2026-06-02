@extends('admin.layout')

@section('title', 'Cek Rak Otomatis - Admin')

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

    $todayStr = date('Y-m-d');
    $todayOverflows = $todayOverflows ?? [];
    $overflowCount = count($todayOverflows);
@endphp

<div style="max-width: 1100px; margin: 0 auto; padding: 0 12px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
        <div>
            <h2 style="margin: 0 0 6px; color: var(--color-text); font-size: clamp(22px, 5vw, 30px); display: flex; align-items: center; gap: 10px;">
                📦 Cek Rak Otomatis
                <span id="liveIndicator" style="display: none; align-items: center; gap: 6px; background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success-border); padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--color-success); animation: pulse 1.5s ease-in-out infinite;"></span>
                    LIVE
                </span>
            </h2>
            <p style="margin: 0; color: var(--color-text-muted); font-size: 14px;">
                Template membuat tugas cek rak setiap hari sesuai jadwal. Petugas dipilih otomatis dari yang masuk kerja dan beban tugasnya paling ringan.
            </p>
        </div>
        <a href="{{ route('admin.rack_check.templates.create') }}"
           style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 11px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: var(--shadow-sm); white-space: nowrap;">
            ➕ Buat Template Baru
        </a>
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
                    $selectedIds = is_array($tpl['selected_waiter_ids'] ?? null) ? $tpl['selected_waiter_ids'] : [];
                    $waiterNames = array_map(fn ($id) => $waiterMap[$id] ?? '—', $selectedIds);
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
                        <button type="button"
                                onclick="previewTemplate('{{ $tpl['id'] }}', @js($templateName)); closeAllKebabs();"
                                style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; font-weight: 500; color: var(--color-text-secondary); background: none; border: none; width: 100%; text-align: left; cursor: pointer; transition: background 0.1s;"
                                onmouseenter="this.style.background='var(--color-bg)'" onmouseleave="this.style.background='transparent'">
                            🔍 Preview Pembagian
                        </button>
                        @if($isActive)
                            <form method="POST" action="{{ route('admin.rack_check.templates.generate', $tpl['id']) }}"
                                  onsubmit="return confirm('Generate task hari ini untuk {{ addslashes($templateName) }} sekarang?\n\nSistem akan langsung memilih petugas dan membuat task tanpa menunggu cron.');">
                                @csrf
                                <button type="submit"
                                        style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; font-weight: 500; color: var(--color-primary); background: none; border: none; width: 100%; text-align: left; cursor: pointer; transition: background 0.1s;"
                                        onmouseenter="this.style.background='var(--color-bg)'" onmouseleave="this.style.background='transparent'">
                                    ⚡ Generate Sekarang
                                </button>
                            </form>
                        @endif
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
                        <div><strong style="color: var(--color-text);">👥 Petugas rotasi:</strong>
                            @if(count($waiterNames) > 0)
                                {{ implode(', ', array_slice($waiterNames, 0, 5)) }}{{ count($waiterNames) > 5 ? ' +'.( count($waiterNames) - 5).' lagi' : '' }}
                            @else
                                <span style="color: var(--color-warning);">Belum ada petugas</span>
                            @endif
                        </div>
                        <div><strong style="color: var(--color-text);">🎯 Mode:</strong>
                            @php $strat = (string) ($tpl['assignment_strategy'] ?? 'simple_lowest_load'); @endphp
                            @if($strat === 'round_robin_simple')
                                <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 1px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">🔁 Giliran Tetap</span>
                            @else
                                <span style="background: var(--color-info-bg); color: var(--color-info); border: 1px solid var(--color-info-border); padding: 1px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">⚖ Beban Paling Ringan</span>
                            @endif
                        </div>
                    </div>

                    {{-- Collapsible rack status table --}}
                    <details style="margin-top: 4px;">
                        <summary style="cursor: pointer; font-size: 13px; font-weight: 600; color: var(--color-text-secondary); padding: 8px 0; list-style: none; display: flex; align-items: center; justify-content: space-between;">
                            <span>📦 Status hari ini ({{ $rackCount }} rak)</span>
                            <span style="color: var(--color-text-muted); font-size: 12px; transition: transform 0.2s;" class="rack-chevron">▾</span>
                        </summary>
                        <div style="border-top: 1px solid #f1f5f9; padding-top: 8px; overflow-x: auto;">
                            <table style="width: 100%; font-size: 12px; border-collapse: collapse; min-width: 320px;">
                                <thead>
                                    <tr style="color: var(--color-text-muted); text-align: left; border-bottom: 1px solid var(--color-border);">
                                        <th style="padding: 6px 8px; font-weight: 600;">Rak</th>
                                        <th style="padding: 6px 8px; font-weight: 600;">Lokasi</th>
                                        <th style="padding: 6px 8px; font-weight: 600;">Status</th>
                                        <th style="padding: 6px 8px; font-weight: 600;">Petugas</th>
                                    </tr>
                                </thead>
                                <tbody data-racks-tbody>
                                    @foreach($templateRacks as $rack)
                                        <tr data-rack-row="{{ $rack['id'] ?? '' }}" style="border-bottom: 1px solid #f8fafc;">
                                            <td style="padding: 6px 8px; font-weight: 500; color: var(--color-text);">{{ $rack['name'] ?? '—' }}</td>
                                            <td style="padding: 6px 8px; color: var(--color-text-muted);">{{ $rack['location'] ?? '—' }}</td>
                                            <td style="padding: 6px 8px;" data-rack-status="{{ $rack['id'] ?? '' }}">
                                                <span style="color: var(--color-text-muted);">⏳ memuat…</span>
                                            </td>
                                            <td style="padding: 6px 8px;" data-rack-waiter="{{ $rack['id'] ?? '' }}">—</td>
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

{{-- Floating Overflow Button --}}
<button type="button" onclick="openOverflowModal()"
        style="position: fixed; left: 18px; bottom: 18px; z-index: 9998; background: {{ $overflowCount > 0 ? '#dc2626' : '#475569' }}; color: white; border: none; border-radius: 999px; padding: 12px 16px; font-weight: 700; box-shadow: var(--shadow-md); cursor: pointer; display: flex; align-items: center; gap: 8px;">
    🚨 Overflow
    <span style="background: white; color: {{ $overflowCount > 0 ? '#dc2626' : '#475569' }}; border-radius: 999px; min-width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">{{ $overflowCount }}</span>
</button>

{{-- Overflow Modal --}}
<div id="overflowModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: white; border-radius: 12px; max-width: 920px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-md);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
            <div style="font-weight: 700; color: var(--color-text); font-size: 16px;">🚨 Overflow Hari Ini ({{ $overflowCount }})</div>
            <button type="button" onclick="closeOverflowModal()" style="background: none; border: none; font-size: 22px; color: var(--color-text-muted); cursor: pointer; padding: 0; line-height: 1;">×</button>
        </div>
        <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;">
            @forelse($todayOverflows as $overflow)
                @php
                    $overflowId = (string) ($overflow['id'] ?? '');
                    $candidates = is_array($overflow['evaluated_candidates'] ?? null) ? $overflow['evaluated_candidates'] : [];
                    $rejected = is_array($overflow['rejected_candidates'] ?? null) ? $overflow['rejected_candidates'] : [];
                @endphp
                <div style="border: 1px solid var(--color-border); border-radius: 10px; padding: 14px; background: #fff7ed;">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                        <div>
                            <div style="font-weight:700;color:var(--color-text);">{{ $overflow['rack_name'] ?? 'Rak' }}</div>
                            <div style="font-size:12px;color:var(--color-text-muted);">Tanggal {{ $overflow['target_date'] ?? $todayStr }} · alasan: {{ $overflow['reason'] ?? '-' }}</div>
                        </div>
                        <span style="background:#fed7aa;color:#9a3412;border:1px solid #fdba74;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:700;">Pending/Overflow</span>
                    </div>

                    @if(count($candidates) > 0)
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
                            @foreach(array_slice($candidates, 0, 6) as $candidate)
                                @php
                                    $candidateName = $candidate['waiter']['name'] ?? $candidate['name'] ?? $candidate['waiter_id'] ?? '-';
                                    $candidateToday = (int) ($candidate['today_count'] ?? 0);
                                    $candidateCap = (int) ($candidate['daily_cap'] ?? 0);
                                    $candidatePoints = (int) ($candidate['monthly_points'] ?? 0);
                                @endphp
                                <span style="background:white;border:1px solid #fed7aa;border-radius:999px;padding:3px 8px;font-size:11px;color:#9a3412;">{{ $candidateName }} · {{ $candidateToday }}/{{ $candidateCap }} · {{ $candidatePoints }} poin</span>
                            @endforeach
                        </div>
                    @elseif(count($rejected) > 0)
                        <div style="font-size:12px;color:#9a3412;margin-bottom:10px;">
                            @foreach(array_slice($rejected, 0, 4) as $candidate)
                                <div>• {{ $candidate['name'] ?? $candidate['waiter_id'] ?? '-' }}: {{ $candidate['reason'] ?? '-' }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;align-items:start;">
                        <form method="POST" action="{{ route('admin.rack_check.overflows.assign', $overflowId) }}" style="background:white;border:1px solid var(--color-border);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:8px;">
                            @csrf
                            <strong style="font-size:12px;">Assign Manual</strong>
                            <select name="waiter_id" required style="padding:7px;border:1px solid var(--color-border);border-radius:6px;font-size:12px;">
                                <option value="">Pilih karyawan</option>
                                @foreach($waiters as $waiter)
                                    <option value="{{ $waiter['id'] ?? '' }}">{{ $waiter['name'] ?? ($waiter['id'] ?? '-') }}</option>
                                @endforeach
                            </select>
                            <label style="font-size:12px;color:#92400e;"><input type="checkbox" name="override_cap" value="1"> Lewati cap (manual supervisor)</label>
                            <input name="note" placeholder="Catatan opsional" style="padding:7px;border:1px solid var(--color-border);border-radius:6px;font-size:12px;">
                            <button type="submit" style="background:var(--color-primary);color:white;border:none;border-radius:6px;padding:7px;font-size:12px;font-weight:700;cursor:pointer;">Assign</button>
                        </form>
                        <form method="POST" action="{{ route('admin.rack_check.overflows.move_tomorrow', $overflowId) }}" style="background:white;border:1px solid var(--color-border);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:8px;">
                            @csrf
                            <strong style="font-size:12px;">Pindah ke Besok</strong>
                            <input name="note" placeholder="Catatan opsional" style="padding:7px;border:1px solid var(--color-border);border-radius:6px;font-size:12px;">
                            <button type="submit" style="background:#0f766e;color:white;border:none;border-radius:6px;padding:7px;font-size:12px;font-weight:700;cursor:pointer;">Pindahkan</button>
                        </form>
                        <form method="POST" action="{{ route('admin.rack_check.overflows.move_next_shift', $overflowId) }}" style="background:white;border:1px solid var(--color-border);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:8px;">
                            @csrf
                            <strong style="font-size:12px;">Pindah Shift Berikutnya</strong>
                            <input name="note" placeholder="Catatan opsional" style="padding:7px;border:1px solid var(--color-border);border-radius:6px;font-size:12px;">
                            <button type="submit" style="background:#0369a1;color:white;border:none;border-radius:6px;padding:7px;font-size:12px;font-weight:700;cursor:pointer;">Pindahkan</button>
                        </form>
                        <form method="POST" action="{{ route('admin.rack_check.overflows.ignore', $overflowId) }}" style="background:white;border:1px solid var(--color-border);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:8px;">
                            @csrf
                            <strong style="font-size:12px;">Abaikan dengan Alasan</strong>
                            <input name="reason" required placeholder="Alasan wajib" style="padding:7px;border:1px solid var(--color-border);border-radius:6px;font-size:12px;">
                            <button type="submit" style="background:var(--color-danger);color:white;border:none;border-radius:6px;padding:7px;font-size:12px;font-weight:700;cursor:pointer;">Abaikan</button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="text-align:center;color:var(--color-text-muted);padding:32px 0;">Tidak ada overflow hari ini.</div>
            @endforelse
        </div>
    </div>
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

<script>
(function () {
    const modal = document.getElementById('previewModal');
    const body = document.getElementById('previewBody');

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function buildPreviewHtml(rackName, preview) {
        const parts = [];
        const dateStr = escapeHtml(preview.date || '');
        parts.push(`<div style="margin-bottom:10px;color:var(--color-text-muted);font-size:13px;">Tanggal preview: <strong style="color:var(--color-text);">${dateStr}</strong></div>`);
        parts.push(`<div style="margin-bottom:14px;font-weight:700;color:var(--color-text);font-size:15px;">${escapeHtml(rackName || preview.rack_name || 'Rak')}</div>`);

        if (preview.status === 'eligible' && preview.selected) {
            const sel = preview.selected;
            parts.push(`<div style="background:var(--color-success-bg);border:1px solid var(--color-success-border);border-radius:8px;padding:12px;margin-bottom:12px;">`);
            parts.push(`<div style="font-weight:700;color:#065f46;margin-bottom:4px;">Petugas terpilih: ${escapeHtml(sel.name)}</div>`);
            parts.push(`<div style="color:#065f46;font-size:13px;">Beban hari ini: <strong>${sel.today_count}/${sel.daily_cap}</strong> · Beban minggu ini: <strong>${sel.weekly_count}</strong> · Poin bulan ini: <strong>${sel.monthly_points ?? 0}</strong></div>`);
            parts.push(`</div>`);

            parts.push(`<div style="font-weight:600;color:var(--color-text);margin-bottom:6px;">Alasan:</div>`);
            parts.push(`<ul style="margin:0 0 14px 20px;color:var(--color-text-secondary);font-size:13px;">`);
            parts.push(`<li>Masuk kerja hari ${dateStr}</li>`);
            parts.push(`<li>Task cek rak hari ini paling sedikit (${sel.today_count})</li>`);
            parts.push(`<li>Belum mencapai batas tugas harian (${sel.daily_cap})</li>`);
            parts.push(`</ul>`);
        } else {
            parts.push(`<div style="background:var(--color-warning-bg);border:1px solid var(--color-warning-border);border-radius:8px;padding:12px;margin-bottom:12px;color:#92400e;font-weight:600;">`);
            parts.push(`⚠ Tidak ada petugas yang eligible. Task tidak akan dibuat dan ditandai skipped.`);
            parts.push(`</div>`);
        }

        const others = Array.isArray(preview.rejected_candidates) ? preview.rejected_candidates : [];
        if (others.length > 0) {
            parts.push(`<div style="font-weight:600;color:var(--color-text);margin-bottom:6px;">Kandidat lain:</div>`);
            parts.push(`<ul style="margin:0;padding-left:20px;color:var(--color-text-secondary);font-size:13px;">`);
            others.forEach(o => {
                parts.push(`<li><strong>${escapeHtml(o.name)}:</strong> ${escapeHtml(o.reason || '-')}</li>`);
            });
            parts.push(`</ul>`);
        }

        return parts.join('');
    }

    let currentPreviewTemplateId = null;
    let currentPreviewRackName = null;

    function getPreviewDate() {
        const picker = document.getElementById('previewDatePicker');
        if (picker && picker.value) return picker.value;
        const tomorrow = new Date(Date.now() + 86400000);
        const yyyy = tomorrow.getFullYear();
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function loadPreview(templateId, rackName, dateStr) {
        body.innerHTML = '<div style="text-align:center;color:var(--color-text-muted);padding:30px 0;">Memuat preview…</div>';

        const url = `{{ url('admin/rack-check/templates') }}/${encodeURIComponent(templateId)}/preview?date=${dateStr}`;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(json => {
                if (!json.success) {
                    body.innerHTML = `<div style="color:var(--color-danger);">Gagal memuat preview: ${escapeHtml(json.message || 'Unknown')}</div>`;
                    return;
                }
                body.innerHTML = buildPreviewHtml(rackName, json.preview || {});
            })
            .catch(err => {
                body.innerHTML = `<div style="color:var(--color-danger);">Gagal memuat preview: ${escapeHtml(err.message || err)}</div>`;
            });
    }

    window.previewTemplate = function (templateId, rackName) {
        currentPreviewTemplateId = templateId;
        currentPreviewRackName = rackName;
        modal.style.display = 'flex';

        const picker = document.getElementById('previewDatePicker');
        const tomorrow = new Date(Date.now() + 86400000);
        const yyyy = tomorrow.getFullYear();
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;
        if (picker) picker.value = dateStr;

        loadPreview(templateId, rackName, dateStr);
    };

    document.getElementById('previewRefreshBtn').addEventListener('click', function () {
        if (currentPreviewTemplateId) {
            loadPreview(currentPreviewTemplateId, currentPreviewRackName, getPreviewDate());
        }
    });

    document.getElementById('previewDatePicker').addEventListener('change', function () {
        if (currentPreviewTemplateId) {
            loadPreview(currentPreviewTemplateId, currentPreviewRackName, getPreviewDate());
        }
    });

    window.closePreview = function () {
        modal.style.display = 'none';
    };

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closePreview();
    });
})();
</script>

{{-- ─────── LIVE STATUS via Firebase Realtime DB ─────── --}}
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
    import { getDatabase, ref, query, orderByChild, equalTo, onValue } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js';
    import { getAuth, signInWithCredential, GoogleAuthProvider } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

    const firebaseConfig = {
        apiKey: "{{ env('FIREBASE_API_KEY') }}",
        authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
        projectId: "{{ env('FIREBASE_PROJECT_ID') }}",
        storageBucket: "{{ env('FIREBASE_STORAGE_BUCKET') }}",
        messagingSenderId: "{{ env('FIREBASE_MESSAGING_SENDER_ID') }}",
        appId: "{{ env('FIREBASE_APP_ID') }}",
        databaseURL: "{{ env('FIREBASE_DATABASE_URL') }}"
    };

    const app = initializeApp(firebaseConfig);
    const database = getDatabase(app);
    const auth = getAuth(app);
    const today = "{{ $todayStr }}";

    const storedToken = {!! json_encode(session('admin_firebase_token')) !!};
    if (storedToken) {
        try {
            const credential = GoogleAuthProvider.credential(storedToken);
            await signInWithCredential(auth, credential);
        } catch (e) {
            console.warn('[rack-check live] Firebase auth failed:', e.message);
        }
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function statusBadgeHtml(status, isOverdue) {
        let label, bg, text, border;
        if (status === 'done')             { label = '✓ Selesai';     bg = '#dcfce7'; text = '#166534'; border = '#bbf7d0'; }
        else if (status === 'in_progress') { label = '🔄 Dikerjakan'; bg = '#dbeafe'; text = '#1e40af'; border = '#bfdbfe'; }
        else if (status === 'cancelled')   { label = '❌ Dibatalkan'; bg = '#fee2e2'; text = '#7f1d1d'; border = '#fecaca'; }
        else if (status === 'overdue' || isOverdue) { label = '⚠ Overdue'; bg = '#fef3c7'; text = '#92400e'; border = '#fde68a'; }
        else { label = '⏳ Pending'; bg = '#f1f5f9'; text = '#475569'; border = '#cbd5e1'; }
        return `<span style="display:inline-flex;align-items:center;gap:4px;background:${bg};color:${text};border:1px solid ${border};padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;">${label}</span>`;
    }

    // Group tasks by source_template_id + rack_id
    let tasksByTplRack = {};
    // Locks indexed by tplId → rackId → lock data
    let locksByTplRack = {};

    function renderAllRackStatuses() {
        document.querySelectorAll('.rc-template-card').forEach(card => {
            const tplId = card.dataset.templateId;
            let racks;
            try { racks = JSON.parse(card.dataset.racks || '[]'); } catch(e) { racks = []; }

            racks.forEach(rack => {
                const rackId = rack.id || '';
                if (!rackId) return;

                const statusCell = card.querySelector(`[data-rack-status="${CSS.escape(rackId)}"]`);
                const waiterCell = card.querySelector(`[data-rack-waiter="${CSS.escape(rackId)}"]`);
                if (!statusCell) return;

                const taskKey = `${tplId}__${rackId}`;
                const task = tasksByTplRack[taskKey];

                if (task) {
                    statusCell.innerHTML = statusBadgeHtml(task.status || 'pending', task.is_overdue);
                    if (waiterCell) waiterCell.textContent = task.assigned_waiter_name || '—';
                } else {
                    // Check lock
                    const lock = (locksByTplRack[tplId] || {})[rackId];
                    if (lock && lock.status === 'skipped_no_eligible_waiter') {
                        if (lock.overflow_status === 'pending') {
                            statusCell.innerHTML = '<span style="color:#dc2626;font-size:11px;font-weight:700;">🚨 Pending/Overflow</span>';
                        } else {
                            statusCell.innerHTML = '<span style="color:#92400e;font-size:11px;">💤 Skipped</span>';
                        }
                        if (waiterCell) waiterCell.textContent = '—';
                    } else if (lock && lock.status === 'cancelled_by_admin') {
                        statusCell.innerHTML = '<span style="color:#7f1d1d;font-size:11px;">❌ Dibatalkan</span>';
                        if (waiterCell) waiterCell.textContent = '—';
                    } else {
                        statusCell.innerHTML = '<span style="color:var(--color-text-muted);font-size:11px;">⏳ Belum</span>';
                        if (waiterCell) waiterCell.textContent = '—';
                    }
                }
            });
        });
    }

    // Listen tasks for today
    const tasksRef = query(ref(database, 'waiter_tasks'), orderByChild('scheduled_for_date'), equalTo(today));
    const indicator = document.getElementById('liveIndicator');

    onValue(tasksRef, (snapshot) => {
        const allTasks = snapshot.val() || {};
        tasksByTplRack = {};
        Object.values(allTasks).forEach(t => {
            const mode = t.assignment_mode || '';
            if (mode !== 'simple_lowest_load' && mode !== 'round_robin_simple') return;
            const tplId = t.source_template_id;
            const rackId = t.rack_id || '';
            if (!tplId || !rackId) return;
            const key = `${tplId}__${rackId}`;
            if (!tasksByTplRack[key] || (t.created_at || 0) > (tasksByTplRack[key].created_at || 0)) {
                tasksByTplRack[key] = t;
            }
        });
        renderAllRackStatuses();
        if (indicator) indicator.style.display = 'inline-flex';
    }, (error) => {
        console.error('[rack-check live] listener error:', error);
    });

    // Batch lock listener
    const todayCompact = today.replace(/-/g, '');
    const locksRef = ref(database, 'waiter_task_generation_locks');

    onValue(locksRef, (snapshot) => {
        locksByTplRack = {};
        const allLocks = snapshot.val() || {};
        Object.entries(allLocks).forEach(([tplId, children]) => {
            if (!children || typeof children !== 'object') return;
            // New format: children keys are rackIds, each containing dateCompact keys
            // Old format: children keys are dateCompact directly
            Object.entries(children).forEach(([childKey, childVal]) => {
                if (childVal && typeof childVal === 'object' && childVal[todayCompact]) {
                    // New format: childKey = rackId, childVal[todayCompact] = lock
                    if (!locksByTplRack[tplId]) locksByTplRack[tplId] = {};
                    locksByTplRack[tplId][childKey] = childVal[todayCompact];
                } else if (childKey === todayCompact && childVal && typeof childVal === 'object') {
                    // Old format: childKey = todayCompact, childVal = lock (single-rak)
                    // Map to the template's first rack
                    const card = document.querySelector(`.rc-template-card[data-template-id="${CSS.escape(tplId)}"]`);
                    if (card) {
                        let racks;
                        try { racks = JSON.parse(card.dataset.racks || '[]'); } catch(e) { racks = []; }
                        if (racks.length > 0) {
                            if (!locksByTplRack[tplId]) locksByTplRack[tplId] = {};
                            locksByTplRack[tplId][racks[0].id] = childVal;
                        }
                    }
                }
            });
        });
        renderAllRackStatuses();
    });
</script>

@endsection
