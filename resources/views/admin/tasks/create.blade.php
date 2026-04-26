@extends('admin.layout')

@section('title', 'Buat Tugas Baru - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333; font-size: clamp(24px, 5vw, 32px);">📝 Buat Tugas Baru</h2>

    @if($errors->any())
        <div class="alert" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; margin-bottom: 20px; padding: 12px 20px; border-radius: 6px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="max-width: 600px; padding: 30px;">
        <form action="{{ route('admin.tasks.store') }}" method="POST">
            @csrf

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

            @php
                $taskType = old('task_type', 'general');
                $rackTargetScope = old('rack_target_scope', 'single');
            @endphp
            <div style="margin-bottom: 20px;">
                <label for="task_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Jenis Tugas <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="task_type"
                    name="task_type"
                    onchange="toggleTaskTypeFields()"
                    style="width: 280px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                >
                    <option value="general" {{ $taskType === 'general' ? 'selected' : '' }}>Tugas Umum</option>
                    <option value="rack_check" {{ $taskType === 'rack_check' ? 'selected' : '' }}>Cek Rak (Wajib Scan + Laporan Stok Opsional)</option>
                </select>
                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Pilih <b>Cek Rak</b> jika waiter harus scan barcode rak. Setelah scan, waiter bisa langsung selesai atau isi laporan barang menipis/habis jika ada.
                </div>
            </div>

            <div id="rack-selector-wrapper" style="margin-bottom: 20px; display: none;">
                <label for="rack_target_scope" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Target Rak <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="rack_target_scope"
                    name="rack_target_scope"
                    onchange="toggleTaskTypeFields()"
                    style="width: 100%; max-width: 320px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; margin-bottom: 10px;"
                >
                    <option value="single" {{ $rackTargetScope === 'single' ? 'selected' : '' }}>Satu Rak Tertentu</option>
                    <option value="all" {{ $rackTargetScope === 'all' ? 'selected' : '' }}>Semua Rak Aktif</option>
                </select>

                <label for="rack_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Pilih Rak Target <span style="color: #dc3545;">*</span>
                </label>
                <select
                    id="rack_id"
                    name="rack_id"
                    style="width: 100%; max-width: 460px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                >
                    <option value="">-- Pilih rak --</option>
                    @foreach(($racks ?? []) as $rack)
                        <option value="{{ $rack['id'] }}" {{ old('rack_id') === ($rack['id'] ?? '') ? 'selected' : '' }}>
                            {{ $rack['name'] ?? '-' }} | {{ $rack['location'] ?? '-' }} | {{ $rack['barcode_value'] ?? '-' }}
                        </option>
                    @endforeach
                    </select>
                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Jika pilih <b>Semua Rak Aktif</b>, sistem akan membuat task cek-rak per rak, jadi waiter wajib scan barcode semua rak (satu task per rak).
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
                </select>
                <div style="font-size: 13px; color: #666; margin-top: 8px;">
                    Supervisor bisa kirim ke 1 waiter atau ke semua waiter aktif sekaligus.
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

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px; flex: 1;">
                    📤 Delegasikan Tugas ke Waiter
                </button>
                <a href="{{ route('admin.tasks.index') }}" class="btn" 
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

            if (recurringCheckbox.checked) {
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

        function toggleTaskTypeFields() {
            const taskType = document.getElementById('task_type').value;
            const rackWrapper = document.getElementById('rack-selector-wrapper');
            const rackTargetScopeInput = document.getElementById('rack_target_scope');
            const rackInput = document.getElementById('rack_id');
            const rackLabel = document.querySelector('label[for="rack_id"]');

            if (taskType === 'rack_check') {
                rackWrapper.style.display = 'block';
                rackTargetScopeInput.required = true;

                const rackTargetScope = rackTargetScopeInput.value || 'single';
                if (rackTargetScope === 'all') {
                    rackInput.required = false;
                    rackInput.value = '';
                    rackInput.disabled = true;
                    if (rackLabel) {
                        rackLabel.style.opacity = '0.6';
                    }
                    rackInput.style.opacity = '0.7';
                } else {
                    rackInput.required = true;
                    rackInput.disabled = false;
                    if (rackLabel) {
                        rackLabel.style.opacity = '1';
                    }
                    rackInput.style.opacity = '1';
                }
            } else {
                rackWrapper.style.display = 'none';
                rackTargetScopeInput.required = false;
                rackInput.required = false;
                rackInput.disabled = false;
            }
        }

        function toggleAssignmentFields() {
            const assignmentType = document.getElementById('assignment_type').value;
            const singleWaiterWrapper = document.getElementById('single-waiter-wrapper');
            const assignedWaiterInput = document.getElementById('assigned_waiter_id');

            if (assignmentType === 'single') {
                singleWaiterWrapper.style.display = 'block';
                assignedWaiterInput.required = true;
            } else {
                singleWaiterWrapper.style.display = 'none';
                assignedWaiterInput.required = false;
            }
        }

        function toggleRecurrenceDetailFields() {
            const recurrenceType = document.getElementById('recurrence_type').value;
            const weeklyWrapper = document.getElementById('weekly-day-wrapper');
            const intervalWrapper = document.getElementById('interval-days-wrapper');
            const weeklyDayInput = document.getElementById('weekly_day');
            const intervalDaysInput = document.getElementById('interval_days');

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

        toggleAssignmentFields();
        toggleRecurringFields();
        toggleTaskTypeFields();
    </script>
@endsection
