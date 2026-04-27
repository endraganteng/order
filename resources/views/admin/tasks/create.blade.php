@extends('admin.layout')

@section('title', 'Buat Tugas Baru - Admin')

@section('content')
    @php
        $taskScopeMode = ($taskScope ?? 'general') === 'rack_check' ? 'rack_check' : 'general';
        $taskScopeLabel = $taskScopeMode === 'rack_check' ? 'Cek Rak' : 'Tugas Umum';
    @endphp

    <h2 style="margin-bottom: 20px; color: #333; font-size: clamp(24px, 5vw, 32px);">
        {{ $taskScopeMode === 'rack_check' ? '📦 Buat Tugas Cek Rak' : '📝 Buat Tugas Umum' }}
    </h2>

    @if($errors->any())
        <div class="alert" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; margin-bottom: 20px; padding: 12px 20px; border-radius: 6px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <style>
        .task-create-card {
            max-width: 1100px !important;
        }

        @media (min-width: 1024px) {
            #rack-checkbox-list {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                max-height: 360px !important;
            }

            #assignment_type,
            #assigned_waiter_id,
            #assigned_waiter_role,
            #role_assignment_mode,
            #recurrence_type {
                width: 100% !important;
                max-width: 460px !important;
            }

            #schedule_time,
            #time_limit_minutes,
            #weekly_day,
            #interval_days {
                width: 100% !important;
                max-width: 320px !important;
            }

            .task-create-actions {
                max-width: 700px;
            }
        }
    </style>

    <div class="card task-create-card" style="padding: 30px;">
        <form action="{{ route('admin.tasks.store') }}" method="POST">
            @csrf
            <input type="hidden" name="task_scope" value="{{ $taskScope ?? 'general' }}">
            <input type="hidden" id="task_type" name="task_type" value="{{ $taskScopeMode === 'rack_check' ? 'rack_check' : 'general' }}">

            @if($taskScopeMode === 'rack_check')
                <div style="margin-bottom: 20px; padding: 14px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a;">
                    <div style="font-weight: 700; margin-bottom: 6px;">🧭 Form Cek Rak Disederhanakan</div>
                    <div style="font-size: 13px; line-height: 1.5;">
                        Untuk tugas cek rak, supervisor hanya perlu memilih rak target dan delegasi waiter.
                        <b>Judul tugas otomatis menggunakan nama rak</b> agar waiter langsung fokus ke rak tujuan.
                    </div>
                </div>
            @else
                <div style="margin-bottom: 20px;">
                    <label for="title" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Judul Tugas <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}"
                        placeholder="Contoh: Bersihkan area meja 5"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        onfocus="this.style.borderColor='#667eea'"
                        onblur="this.style.borderColor='#e0e0e0'"
                        required>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="description" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Deskripsi <span style="color: #999; font-weight: normal;">(opsional)</span>
                    </label>
                    <textarea id="description" name="description" rows="3"
                        placeholder="Detail tambahan tentang tugas..."
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; resize: vertical; font-family: inherit; transition: border-color 0.3s;"
                        onfocus="this.style.borderColor='#667eea'"
                        onblur="this.style.borderColor='#e0e0e0'">{{ old('description') }}</textarea>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                        Prioritas <span style="color: #dc3545;">*</span>
                    </label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label style="flex: 1; min-width: 120px; cursor: pointer;">
                            <input type="radio" name="priority" value="urgent" {{ old('priority') === 'urgent' ? 'checked' : '' }}
                                style="display: none;" onchange="updatePriorityStyle(this)">
                            <div class="priority-option" data-priority="urgent"
                                style="padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; text-align: center; transition: all 0.3s;">
                                <div style="font-size: 24px; margin-bottom: 6px;">🔴</div>
                                <div style="font-weight: 600; font-size: 14px;">Urgent</div>
                            </div>
                        </label>
                        <label style="flex: 1; min-width: 120px; cursor: pointer;">
                            <input type="radio" name="priority" value="normal" {{ old('priority', 'normal') === 'normal' ? 'checked' : '' }}
                                style="display: none;" onchange="updatePriorityStyle(this)">
                            <div class="priority-option" data-priority="normal"
                                style="padding: 14px; border: 2px solid #667eea; border-radius: 8px; text-align: center; background: #f0f3ff; transition: all 0.3s;">
                                <div style="font-size: 24px; margin-bottom: 6px;">🔵</div>
                                <div style="font-weight: 600; font-size: 14px;">Normal</div>
                            </div>
                        </label>
                        <label style="flex: 1; min-width: 120px; cursor: pointer;">
                            <input type="radio" name="priority" value="low" {{ old('priority') === 'low' ? 'checked' : '' }}
                                style="display: none;" onchange="updatePriorityStyle(this)">
                            <div class="priority-option" data-priority="low"
                                style="padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; text-align: center; transition: all 0.3s;">
                                <div style="font-size: 24px; margin-bottom: 6px;">⚪</div>
                                <div style="font-weight: 600; font-size: 14px;">Low</div>
                            </div>
                        </label>
                    </div>
                </div>
            @endif

            @php
                $taskType = $taskScopeMode === 'rack_check' ? 'rack_check' : 'general';
                $oldRackIdsInput = old('rack_ids', []);
                if (!is_array($oldRackIdsInput)) {
                    $oldRackIdsInput = explode(',', (string) $oldRackIdsInput);
                }
                $oldRackIdsInput = array_values(array_filter(array_map(function ($rackId) {
                    return trim((string) $rackId);
                }, $oldRackIdsInput), function ($rackId) {
                    return $rackId !== '';
                }));

                $legacyRackId = trim((string) old('rack_id', ''));
                if ($legacyRackId !== '') {
                    $oldRackIdsInput[] = $legacyRackId;
                }

                $selectedRackIds = array_values(array_unique($oldRackIdsInput));
                $rackTotalCount = count($racks ?? []);
                $selectedRackCount = count($selectedRackIds);
                $rackTargetScope = old('rack_target_scope', ($rackTotalCount > 0 && $selectedRackCount === $rackTotalCount) ? 'all' : 'single');
            @endphp
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Jenis Halaman
                </label>
                @if($taskScopeMode === 'rack_check')
                    <div style="display: inline-block; padding: 10px 14px; border-radius: 8px; background: #fff7ed; border: 1px solid #fdba74; color: #1f2937; font-weight: 700; font-size: 14px;">📦 Cek Rak</div>
                @else
                    <div style="display: inline-block; padding: 10px 14px; border-radius: 8px; background: #eef2ff; border: 1px solid #c7d2fe; color: #1f2937; font-weight: 700; font-size: 14px;">📝 Tugas Umum</div>
                @endif
                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Anda sedang membuat <b>{{ $taskScopeLabel }}</b>. Jenis tugas dikunci sesuai halaman agar tidak tercampur.
                </div>
            </div>

            <div id="rack-selector-wrapper" style="margin-bottom: 20px; display: none;">
                <input type="hidden" id="rack_target_scope" name="rack_target_scope" value="{{ $rackTargetScope }}">

                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Pilih Rak Target (Bisa Multi-Pilih) <span style="color: #dc3545;">*</span>
                </label>

                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; cursor: pointer;">
                        <input type="checkbox" id="rack_select_all" {{ ($rackTotalCount > 0 && $selectedRackCount === $rackTotalCount) ? 'checked' : '' }}>
                        Pilih semua rak aktif
                    </label>
                    <span id="rack-selection-count-hint" style="font-size: 12px; color: #64748b;">
                        {{ $selectedRackCount }} dari {{ $rackTotalCount }} rak dipilih
                    </span>
                </div>

                <div id="rack-checkbox-list" style="display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); max-height: 280px; overflow: auto; padding: 10px; border: 1px solid #dbe2ea; border-radius: 10px; background: #f8fafc;">
                    @forelse(($racks ?? []) as $rack)
                        @php
                            $rackId = (string) ($rack['id'] ?? '');
                            $isChecked = in_array($rackId, $selectedRackIds, true);
                        @endphp
                        <label class="rack-checkbox-item" style="display: block; border: 1px solid #dbe2ea; border-radius: 8px; padding: 10px; background: #fff; cursor: pointer;">
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <input
                                    type="checkbox"
                                    class="js-rack-checkbox"
                                    name="rack_ids[]"
                                    value="{{ $rackId }}"
                                    {{ $isChecked ? 'checked' : '' }}
                                    style="margin-top: 3px;"
                                >
                                <div style="min-width: 0;">
                                    <div style="font-weight: 700; color: #0f172a; word-break: break-word;">{{ $rack['name'] ?? '-' }}</div>
                                    <div style="font-size: 12px; color: #64748b; word-break: break-word;">📍 {{ $rack['location'] ?? '-' }}</div>
                                    <div style="font-size: 11px; color: #94a3b8; word-break: break-all;">QR: {{ $rack['barcode_value'] ?? '-' }}</div>
                                </div>
                            </div>
                        </label>
                    @empty
                        <div style="font-size: 13px; color: #b91c1c;">Belum ada rak aktif untuk dipilih.</div>
                    @endforelse
                </div>

                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Supervisor bisa memilih satu atau beberapa rak sekaligus. Sistem akan membuat task cek-rak per rak terpilih.
                    <br>
                    Rak belum ada? <a href="{{ route('admin.racks.create') }}" target="_blank" rel="noopener">Tambah rak dulu</a>.
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                    <input
                        type="checkbox"
                        id="requires_photo_proof"
                        name="requires_photo_proof"
                        value="1"
                        {{ old('requires_photo_proof') ? 'checked' : '' }}
                        style="margin-top: 3px;"
                    >
                    <span>📷 Wajib bukti foto saat waiter menyelesaikan tugas</span>
                </label>
                <div style="font-size: 13px; color: #666; margin-top: 8px; margin-left: 26px;">
                    Jika aktif, waiter harus ambil/upload foto bukti (kamera HP) sebelum tombol verifikasi bisa diproses.
                </div>
            </div>

            @php
                $assignmentType = old('assignment_type', 'all');
                $assignedWaiterRole = old('assigned_waiter_role', 'pelayan');
                $roleAssignmentMode = old('role_assignment_mode', $taskScopeMode === 'rack_check' ? 'rolling' : 'all');
                $activePelayanCount = collect($waiters ?? [])->filter(function ($waiter) {
                    return strtolower((string) ($waiter['waiter_role'] ?? 'pelayan')) === 'pelayan';
                })->count();
                $activeKasirCount = collect($waiters ?? [])->filter(function ($waiter) {
                    return strtolower((string) ($waiter['waiter_role'] ?? 'pelayan')) === 'kasir';
                })->count();
            @endphp
            <div style="margin-bottom: 20px;">
                <label for="assignment_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Delegasi Ke <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="assignment_type"
                    name="assignment_type"
                    onchange="toggleAssignmentFields()"
                    style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                >
                    <option value="all" {{ $assignmentType === 'all' ? 'selected' : '' }}>Semua Waiter Aktif</option>
                    <option value="single" {{ $assignmentType === 'single' ? 'selected' : '' }}>Waiter Tertentu</option>
                    <option value="role" {{ $assignmentType === 'role' ? 'selected' : '' }}>Berdasarkan Role</option>
                </select>
                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Supervisor bisa kirim ke 1 waiter, semua waiter aktif, atau waiter berdasarkan role.
                </div>
            </div>

            <div id="single-waiter-wrapper" style="margin-bottom: 20px; display: none;">
                <label for="assigned_waiter_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Pilih Nama Waiter <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="assigned_waiter_id"
                    name="assigned_waiter_id"
                    style="width: 100%; max-width: 420px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                >
                    <option value="">-- Pilih waiter --</option>
                    @foreach(($waiters ?? []) as $waiter)
                        <option value="{{ $waiter['id'] }}" {{ old('assigned_waiter_id') === ($waiter['id'] ?? '') ? 'selected' : '' }}>
                            {{ $waiter['name'] ?? '-' }} ({{ $waiter['email'] ?? '-' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="role-waiter-wrapper" style="margin-bottom: 20px; display: none;">
                <label for="assigned_waiter_role" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Pilih Role Waiter <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="assigned_waiter_role"
                    name="assigned_waiter_role"
                    style="width: 100%; max-width: 320px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                >
                    <option value="pelayan" {{ $assignedWaiterRole === 'pelayan' ? 'selected' : '' }}>Pelayan ({{ $activePelayanCount }} aktif)</option>
                    <option value="kasir" {{ $assignedWaiterRole === 'kasir' ? 'selected' : '' }}>Kasir ({{ $activeKasirCount }} aktif)</option>
                </select>
            </div>

            <div id="role-assignment-mode-wrapper" style="margin-bottom: 20px; display: none;">
                <label for="role_assignment_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Mode Delegasi Role <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="role_assignment_mode"
                    name="role_assignment_mode"
                    style="width: 100%; max-width: 360px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                >
                    <option value="rolling" {{ $roleAssignmentMode === 'rolling' ? 'selected' : '' }}>Rolling per Rak (disarankan untuk Cek Rak)</option>
                    <option value="all" {{ $roleAssignmentMode === 'all' ? 'selected' : '' }}>Semua Waiter dalam Role</option>
                </select>
                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Rolling per rak akan membagi rak terpilih ke waiter dalam role secara bergantian
                    dan <b>berotasi otomatis setiap hari</b> agar pembagian kerja lebih merata.
                </div>
            </div>

            @if($taskScopeMode === 'rack_check')
                <div style="margin-bottom: 20px; padding: 14px; border: 1px solid #fdba74; border-radius: 10px; background: #fff7ed;">
                    <div style="font-weight: 700; color: #9a3412; margin-bottom: 6px;">⏱️ Jadwal Cek Rak</div>
                    <div style="font-size: 13px; color: #7c2d12; line-height: 1.5;">
                        Supervisor dapat mengubah pola jadwal, jam mulai, dan batas waktu penyelesaian sesuai kebutuhan operasional.
                    </div>
                </div>
                <input type="hidden" id="is_recurring" name="is_recurring" value="1">

                <div id="recurring-time-wrapper" style="margin-bottom: 25px; display: block;">
                    <div style="margin-bottom: 14px;">
                        <label for="recurrence_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Pola Perulangan <span style="color: #dc3545;">*</span>
                        </label>
                        @php $recurrenceType = old('recurrence_type', 'daily'); @endphp
                        <select
                            id="recurrence_type"
                            name="recurrence_type"
                            onchange="toggleRecurrenceDetailFields()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                            required
                        >
                            <option value="daily" {{ $recurrenceType === 'daily' ? 'selected' : '' }}>Setiap Hari</option>
                            <option value="weekly" {{ $recurrenceType === 'weekly' ? 'selected' : '' }}>Mingguan (hari tertentu)</option>
                            <option value="every_n_days" {{ $recurrenceType === 'every_n_days' ? 'selected' : '' }}>Setiap N Hari</option>
                        </select>
                    </div>

                    <div id="weekly-day-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="weekly_day" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Hari (Mode Mingguan) <span style="color: #dc3545;">*</span>
                        </label>
                        @php $weeklyDay = old('weekly_day', date('N')); @endphp
                        <select
                            id="weekly_day"
                            name="weekly_day"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                            <option value="1" {{ (string) $weeklyDay === '1' ? 'selected' : '' }}>Senin</option>
                            <option value="2" {{ (string) $weeklyDay === '2' ? 'selected' : '' }}>Selasa</option>
                            <option value="3" {{ (string) $weeklyDay === '3' ? 'selected' : '' }}>Rabu</option>
                            <option value="4" {{ (string) $weeklyDay === '4' ? 'selected' : '' }}>Kamis</option>
                            <option value="5" {{ (string) $weeklyDay === '5' ? 'selected' : '' }}>Jumat</option>
                            <option value="6" {{ (string) $weeklyDay === '6' ? 'selected' : '' }}>Sabtu</option>
                            <option value="7" {{ (string) $weeklyDay === '7' ? 'selected' : '' }}>Minggu</option>
                        </select>
                    </div>

                    <div id="interval-days-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="interval_days" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Interval Hari (Mode Setiap N Hari) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="interval_days"
                            name="interval_days"
                            min="1"
                            max="365"
                            value="{{ old('interval_days', 2) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 2 berarti task muncul setiap 2 hari sekali.
                        </div>
                    </div>

                    <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jam Jadwal <span style="color: #dc3545;">*</span>
                    </label>
                    <input
                        type="time"
                        id="schedule_time"
                        name="schedule_time"
                        value="{{ old('schedule_time', '06:00') }}"
                        style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        onfocus="this.style.borderColor='#667eea'"
                        onblur="this.style.borderColor='#e0e0e0'"
                        required
                    >
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Format 24 jam, contoh: 06:00 atau 07:30.
                    </div>

                    <div style="margin-top: 14px;">
                        <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Batas Waktu Penyelesaian (menit) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="time_limit_minutes"
                            name="time_limit_minutes"
                            min="1"
                            max="1440"
                            value="{{ old('time_limit_minutes', 900) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#667eea'"
                            onblur="this.style.borderColor='#e0e0e0'"
                            required
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 900 berarti task harus selesai maksimal 15 jam setelah jam jadwal.
                        </div>
                    </div>
                </div>
            @else
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                        <input
                            type="checkbox"
                            id="is_recurring"
                            name="is_recurring"
                            value="1"
                            {{ old('is_recurring') ? 'checked' : '' }}
                            onchange="toggleRecurringFields()"
                        >
                        🔁 Jadwalkan sebagai task berulang
                    </label>
                    <div style="font-size: 13px; color: #666; margin-top: 8px; margin-left: 26px;">
                        Pilih pola: harian, mingguan, atau setiap N hari.
                    </div>
                </div>

                <div id="recurring-time-wrapper" style="margin-bottom: 25px; display: none;">
                    <div style="margin-bottom: 14px;">
                        <label for="recurrence_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Pola Perulangan <span style="color: #dc3545;">*</span>
                        </label>
                        @php $recurrenceType = old('recurrence_type', 'daily'); @endphp
                        <select
                            id="recurrence_type"
                            name="recurrence_type"
                            onchange="toggleRecurrenceDetailFields()"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                            <option value="daily" {{ $recurrenceType === 'daily' ? 'selected' : '' }}>Setiap Hari</option>
                            <option value="weekly" {{ $recurrenceType === 'weekly' ? 'selected' : '' }}>Mingguan (hari tertentu)</option>
                            <option value="every_n_days" {{ $recurrenceType === 'every_n_days' ? 'selected' : '' }}>Setiap N Hari</option>
                        </select>
                    </div>

                    <div id="weekly-day-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="weekly_day" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Hari (Mode Mingguan) <span style="color: #dc3545;">*</span>
                        </label>
                        @php $weeklyDay = old('weekly_day', date('N')); @endphp
                        <select
                            id="weekly_day"
                            name="weekly_day"
                            style="width: 260px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                            <option value="1" {{ (string) $weeklyDay === '1' ? 'selected' : '' }}>Senin</option>
                            <option value="2" {{ (string) $weeklyDay === '2' ? 'selected' : '' }}>Selasa</option>
                            <option value="3" {{ (string) $weeklyDay === '3' ? 'selected' : '' }}>Rabu</option>
                            <option value="4" {{ (string) $weeklyDay === '4' ? 'selected' : '' }}>Kamis</option>
                            <option value="5" {{ (string) $weeklyDay === '5' ? 'selected' : '' }}>Jumat</option>
                            <option value="6" {{ (string) $weeklyDay === '6' ? 'selected' : '' }}>Sabtu</option>
                            <option value="7" {{ (string) $weeklyDay === '7' ? 'selected' : '' }}>Minggu</option>
                        </select>
                    </div>

                    <div id="interval-days-wrapper" style="margin-bottom: 14px; display: none;">
                        <label for="interval_days" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Interval Hari (Mode Setiap N Hari) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="interval_days"
                            name="interval_days"
                            min="1"
                            max="365"
                            value="{{ old('interval_days', 2) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 2 berarti task muncul setiap 2 hari sekali.
                        </div>
                    </div>

                    <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jam Jadwal <span style="color: #dc3545;">*</span>
                    </label>
                    <input
                        type="time"
                        id="schedule_time"
                        name="schedule_time"
                        value="{{ old('schedule_time') }}"
                        style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                        onfocus="this.style.borderColor='#667eea'"
                        onblur="this.style.borderColor='#e0e0e0'"
                    >
                    <div style="font-size: 13px; color: #666; margin-top: 8px;">
                        Format 24 jam, contoh: 10:30 atau 16:45.
                    </div>

                    <div style="margin-top: 14px;">
                        <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Batas Waktu Penyelesaian (menit) <span style="color: #dc3545;">*</span>
                        </label>
                        <input
                            type="number"
                            id="time_limit_minutes"
                            name="time_limit_minutes"
                            min="1"
                            max="1440"
                            value="{{ old('time_limit_minutes', 30) }}"
                            style="width: 220px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#667eea'"
                            onblur="this.style.borderColor='#e0e0e0'"
                        >
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Contoh: isi 30 berarti task harus selesai maksimal 30 menit setelah jam jadwal.
                        </div>
                    </div>
                </div>
            @endif

            <div class="task-create-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px; flex: 1;">
                    📤 Delegasikan Tugas ke Waiter
                </button>
                <a href="{{ route($backRouteName ?? 'admin.tasks.index') }}" class="btn" 
                    style="padding: 12px 20px; font-size: 16px; background: #e0e0e0; color: #333; text-align: center;">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <script>
        function updatePriorityStyle(radio) {
            // Reset all options
            document.querySelectorAll('.priority-option').forEach(el => {
                el.style.borderColor = '#e0e0e0';
                el.style.background = 'white';
            });

            // Highlight selected
            const selected = radio.closest('label').querySelector('.priority-option');
            const priority = selected.dataset.priority;
            
            if (priority === 'urgent') {
                selected.style.borderColor = '#dc3545';
                selected.style.background = '#fff5f5';
            } else if (priority === 'normal') {
                selected.style.borderColor = '#667eea';
                selected.style.background = '#f0f3ff';
            } else {
                selected.style.borderColor = '#6c757d';
                selected.style.background = '#f8f9fa';
            }
        }

        function toggleRecurringFields() {
            const recurringCheckbox = document.getElementById('is_recurring');
            const recurringWrapper = document.getElementById('recurring-time-wrapper');
            const scheduleTimeInput = document.getElementById('schedule_time');
            const timeLimitInput = document.getElementById('time_limit_minutes');
            const recurrenceTypeInput = document.getElementById('recurrence_type');

            if (!recurringCheckbox || !recurringWrapper || !scheduleTimeInput || !timeLimitInput || !recurrenceTypeInput) {
                return;
            }

            const forceRecurring = recurringCheckbox.type === 'hidden';
            if (forceRecurring || recurringCheckbox.checked) {
                recurringWrapper.style.display = 'block';
                scheduleTimeInput.required = true;
                timeLimitInput.required = true;
                recurrenceTypeInput.required = true;
                toggleRecurrenceDetailFields();
            } else {
                recurringWrapper.style.display = 'none';
                scheduleTimeInput.required = false;
                timeLimitInput.required = false;
                recurrenceTypeInput.required = false;

                document.getElementById('weekly_day').required = false;
                document.getElementById('interval_days').required = false;
            }
        }

        function syncRackSelectionState() {
            const rackTargetScopeInput = document.getElementById('rack_target_scope');
            const rackCheckboxes = Array.from(document.querySelectorAll('.js-rack-checkbox'));
            const selectAllCheckbox = document.getElementById('rack_select_all');
            const countHintEl = document.getElementById('rack-selection-count-hint');

            if (!rackTargetScopeInput || rackCheckboxes.length === 0) {
                if (countHintEl) {
                    countHintEl.textContent = '0 rak tersedia';
                }
                return;
            }

            const totalCount = rackCheckboxes.length;
            const selectedCount = rackCheckboxes.filter((checkbox) => checkbox.checked).length;

            rackTargetScopeInput.value = selectedCount > 0 && selectedCount === totalCount ? 'all' : 'single';

            if (countHintEl) {
                countHintEl.textContent = `${selectedCount} dari ${totalCount} rak dipilih`;
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCount;
                selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCount;
            }

            rackCheckboxes.forEach((checkbox, index) => {
                checkbox.required = index === 0 && selectedCount === 0;
            });
        }

        function toggleTaskTypeFields() {
            const taskTypeInput = document.getElementById('task_type');
            const taskType = taskTypeInput ? taskTypeInput.value : 'general';
            const rackWrapper = document.getElementById('rack-selector-wrapper');
            const rackTargetScopeInput = document.getElementById('rack_target_scope');
            const rackCheckboxes = Array.from(document.querySelectorAll('.js-rack-checkbox'));
            const selectAllCheckbox = document.getElementById('rack_select_all');

            if (!rackWrapper || !rackTargetScopeInput) {
                toggleAssignmentFields();
                return;
            }

            if (taskType === 'rack_check') {
                rackWrapper.style.display = 'block';
                rackTargetScopeInput.required = true;

                rackCheckboxes.forEach((checkbox) => {
                    checkbox.disabled = false;
                });

                if (selectAllCheckbox) {
                    selectAllCheckbox.disabled = false;
                }

                syncRackSelectionState();
            } else {
                rackWrapper.style.display = 'none';
                rackTargetScopeInput.required = false;

                rackCheckboxes.forEach((checkbox) => {
                    checkbox.required = false;
                    checkbox.disabled = true;
                });

                if (selectAllCheckbox) {
                    selectAllCheckbox.disabled = true;
                }
            }

            toggleAssignmentFields();
        }

        function toggleAssignmentFields() {
            const assignmentType = document.getElementById('assignment_type').value;
            const singleWaiterWrapper = document.getElementById('single-waiter-wrapper');
            const roleWaiterWrapper = document.getElementById('role-waiter-wrapper');
            const roleAssignmentModeWrapper = document.getElementById('role-assignment-mode-wrapper');
            const assignedWaiterInput = document.getElementById('assigned_waiter_id');
            const assignedWaiterRoleInput = document.getElementById('assigned_waiter_role');
            const roleAssignmentModeInput = document.getElementById('role_assignment_mode');
            const taskTypeInput = document.getElementById('task_type');
            const taskType = taskTypeInput ? taskTypeInput.value : 'general';

            if (assignmentType === 'single') {
                singleWaiterWrapper.style.display = 'block';
                roleWaiterWrapper.style.display = 'none';
                roleAssignmentModeWrapper.style.display = 'none';
                assignedWaiterInput.required = true;
                assignedWaiterRoleInput.required = false;
                roleAssignmentModeInput.required = false;
            } else if (assignmentType === 'role') {
                singleWaiterWrapper.style.display = 'none';
                roleWaiterWrapper.style.display = 'block';
                roleAssignmentModeWrapper.style.display = taskType === 'rack_check' ? 'block' : 'none';
                assignedWaiterInput.required = false;
                assignedWaiterRoleInput.required = true;
                roleAssignmentModeInput.required = taskType === 'rack_check';
            } else {
                singleWaiterWrapper.style.display = 'none';
                roleWaiterWrapper.style.display = 'none';
                roleAssignmentModeWrapper.style.display = 'none';
                assignedWaiterInput.required = false;
                assignedWaiterRoleInput.required = false;
                roleAssignmentModeInput.required = false;
            }
        }

        function toggleRecurrenceDetailFields() {
            const recurrenceTypeInput = document.getElementById('recurrence_type');
            const weeklyWrapper = document.getElementById('weekly-day-wrapper');
            const intervalWrapper = document.getElementById('interval-days-wrapper');
            const weeklyDayInput = document.getElementById('weekly_day');
            const intervalDaysInput = document.getElementById('interval_days');

            if (!recurrenceTypeInput || !weeklyWrapper || !intervalWrapper || !weeklyDayInput || !intervalDaysInput) {
                return;
            }

            const recurrenceType = recurrenceTypeInput.value;

            if (recurrenceType === 'weekly') {
                weeklyWrapper.style.display = 'block';
                intervalWrapper.style.display = 'none';
                weeklyDayInput.required = true;
                intervalDaysInput.required = false;
            } else if (recurrenceType === 'every_n_days') {
                weeklyWrapper.style.display = 'none';
                intervalWrapper.style.display = 'block';
                weeklyDayInput.required = false;
                intervalDaysInput.required = true;
            } else {
                weeklyWrapper.style.display = 'none';
                intervalWrapper.style.display = 'none';
                weeklyDayInput.required = false;
                intervalDaysInput.required = false;
            }
        }

        const rackSelectAllEl = document.getElementById('rack_select_all');
        if (rackSelectAllEl) {
            rackSelectAllEl.addEventListener('change', () => {
                const checked = rackSelectAllEl.checked;
                document.querySelectorAll('.js-rack-checkbox').forEach((checkbox) => {
                    if (!checkbox.disabled) {
                        checkbox.checked = checked;
                    }
                });
                syncRackSelectionState();
            });
        }

        document.querySelectorAll('.js-rack-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', syncRackSelectionState);
        });

        toggleAssignmentFields();
        toggleRecurringFields();
        toggleTaskTypeFields();
    </script>
@endsection
