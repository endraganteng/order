@extends('admin.layout')

@section('title', 'Edit Task Berulang - Admin')

@section('content')
    @php
        $isRackTemplate = ($template['task_type'] ?? 'general') === 'rack_check';
        $backRouteName = $isRackTemplate ? 'admin.tasks.rack.index' : 'admin.tasks.index';
    @endphp

    <h2 style="margin-bottom: 20px; color: #333; font-size: clamp(24px, 5vw, 32px);">✏️ Edit Task Berulang</h2>

    @if($errors->any())
        <div class="alert" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; margin-bottom: 20px; padding: 12px 20px; border-radius: 6px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="max-width: 700px; padding: 30px;">
        <form action="{{ route('admin.tasks.recurring.update', $template['id']) }}" method="POST">
            @csrf
            @method('PUT')

            <div style="margin-bottom: 20px;">
                <label for="title" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Judul Tugas <span style="color: #dc3545;">*</span>
                </label>
                <input type="text" id="title" name="title" value="{{ old('title', $template['title'] ?? '') }}"
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                    required>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="description" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Deskripsi
                </label>
                <textarea id="description" name="description" rows="3"
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; resize: vertical; font-family: inherit;">{{ old('description', $template['description'] ?? '') }}</textarea>
            </div>

            @if($isRackTemplate)
                <div style="margin-bottom: 20px; padding: 12px; border: 1px solid #fed7aa; border-radius: 8px; background: #fff7ed;">
                    <div style="font-weight: 700; color: #9a3412; margin-bottom: 6px;">📦 Template Cek Rak (Wajib Scan QR Code)</div>
                    <div style="font-size: 13px; color: #9a3412;">Rak: <strong>{{ $template['rack_name'] ?? '-' }}</strong> ({{ $template['rack_location'] ?? '-' }})</div>
                    <div style="font-size: 13px; color: #9a3412;">QR Code: <code>{{ $template['rack_barcode_value'] ?? '-' }}</code></div>
                    <div style="font-size: 12px; color: #7c2d12; margin-top: 6px;">
                        Jadwal cek rak dapat disesuaikan supervisor sesuai kebutuhan operasional.
                    </div>
                </div>
            @endif

            <div style="margin-bottom: 20px;">
                <label for="priority" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Prioritas <span style="color: #dc3545;">*</span>
                </label>
                <select id="priority" name="priority"
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                    required>
                    @php $priorityValue = old('priority', $template['priority'] ?? 'normal'); @endphp
                    <option value="urgent" {{ $priorityValue === 'urgent' ? 'selected' : '' }}>🔴 Urgent</option>
                    <option value="normal" {{ $priorityValue === 'normal' ? 'selected' : '' }}>🔵 Normal</option>
                    <option value="low" {{ $priorityValue === 'low' ? 'selected' : '' }}>⚪ Low</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="category_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    Kategori Tugas <span style="font-weight: 400; color: #999;">(opsional)</span>
                </label>
                <select id="category_id" name="category_id" onchange="updateCategoryName(this)"
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                    <option value="">- Tanpa Kategori -</option>
                    @foreach(($categories ?? []) as $cat)
                        <option value="{{ $cat['id'] }}" data-name="{{ $cat['name'] }}" {{ ($template['category_id'] ?? '') === $cat['id'] ? 'selected' : '' }}>
                            {{ $cat['name'] }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" id="category_name" name="category_name" value="{{ $template['category_name'] ?? '' }}">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="grid-column: 1 / -1;">
                    @php $recurrenceType = old('recurrence_type', $template['recurrence_type'] ?? 'daily'); @endphp
                    <label for="recurrence_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Pola Perulangan <span style="color: #dc3545;">*</span>
                    </label>
                    <select id="recurrence_type" name="recurrence_type" onchange="toggleRecurrenceDetailFields()"
                        style="width: 280px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;"
                        required>
                        <option value="daily" {{ $recurrenceType === 'daily' ? 'selected' : '' }}>Setiap Hari</option>
                        <option value="weekly" {{ $recurrenceType === 'weekly' ? 'selected' : '' }}>Mingguan (hari tertentu)</option>
                        <option value="every_n_days" {{ $recurrenceType === 'every_n_days' ? 'selected' : '' }}>Setiap N Hari</option>
                    </select>
                </div>

                <div id="weekly-day-wrapper" style="display: none;">
                    @php $weeklyDay = old('weekly_day', $template['weekly_day'] ?? date('N')); @endphp
                    <label for="weekly_day" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Hari (Mode Mingguan) <span style="color: #dc3545;">*</span>
                    </label>
                    <select id="weekly_day" name="weekly_day"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <option value="1" {{ (string) $weeklyDay === '1' ? 'selected' : '' }}>Senin</option>
                        <option value="2" {{ (string) $weeklyDay === '2' ? 'selected' : '' }}>Selasa</option>
                        <option value="3" {{ (string) $weeklyDay === '3' ? 'selected' : '' }}>Rabu</option>
                        <option value="4" {{ (string) $weeklyDay === '4' ? 'selected' : '' }}>Kamis</option>
                        <option value="5" {{ (string) $weeklyDay === '5' ? 'selected' : '' }}>Jumat</option>
                        <option value="6" {{ (string) $weeklyDay === '6' ? 'selected' : '' }}>Sabtu</option>
                        <option value="7" {{ (string) $weeklyDay === '7' ? 'selected' : '' }}>Minggu</option>
                    </select>
                </div>

                <div id="interval-days-wrapper" style="display: none;">
                    <label for="interval_days" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Interval Hari (Mode Setiap N Hari) <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="number" id="interval_days" name="interval_days" min="1" max="365"
                        value="{{ old('interval_days', $template['interval_days'] ?? 2) }}"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                </div>

                {{-- Schedule Mode: Fixed vs Shift Relative --}}
                <div style="grid-column: 1 / -1;">
                    @php $scheduleMode = old('schedule_mode', $template['schedule_mode'] ?? 'fixed'); @endphp
                    <label for="schedule_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Mode Jadwal
                    </label>
                    <select id="schedule_mode" name="schedule_mode" onchange="toggleScheduleMode()"
                        style="width: 280px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <option value="fixed" {{ $scheduleMode === 'fixed' ? 'selected' : '' }}>⏰ Jam Tetap (Fixed)</option>
                        <option value="shift_relative" {{ $scheduleMode === 'shift_relative' ? 'selected' : '' }}>🔄 Ikuti Shift Waiter</option>
                    </select>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                        Mode "Ikuti Shift" = jam tugas & deadline otomatis menyesuaikan shift masing-masing waiter.
                    </div>
                </div>

                {{-- Fixed mode fields --}}
                <div id="fixed-schedule-wrapper">
                    <label for="schedule_time" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Jam Jadwal <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="time" id="schedule_time" name="schedule_time"
                        value="{{ old('schedule_time', $template['schedule_time'] ?? '') }}"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                </div>

                <div id="fixed-deadline-wrapper">
                    <label for="time_limit_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Batas Waktu (menit) <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="number" id="time_limit_minutes" name="time_limit_minutes" min="0" max="1440"
                        value="{{ old('time_limit_minutes', $template['time_limit_minutes'] ?? 30) }}"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                </div>

                {{-- Shift-relative mode fields --}}
                <div id="shift-offset-wrapper" style="display: none;">
                    @php $shiftOffset = old('shift_offset_minutes', $template['shift_offset_minutes'] ?? 30); @endphp
                    <label for="shift_offset_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Muncul Setelah Shift Mulai (menit)
                    </label>
                    <input type="number" id="shift_offset_minutes" name="shift_offset_minutes" min="0" max="480"
                        value="{{ $shiftOffset }}"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                        Contoh: 30 = tugas muncul 30 menit setelah shift dimulai
                    </div>
                </div>

                <div id="shift-deadline-wrapper" style="display: none;">
                    @php $deadlineMode = old('deadline_mode', $template['deadline_mode'] ?? 'fixed'); @endphp
                    <label for="deadline_mode" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Mode Deadline
                    </label>
                    <select id="deadline_mode" name="deadline_mode" onchange="toggleDeadlineMode()"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                        <option value="fixed" {{ $deadlineMode === 'fixed' ? 'selected' : '' }}>Batas waktu tetap (menit dari muncul)</option>
                        <option value="before_shift_end" {{ $deadlineMode === 'before_shift_end' ? 'selected' : '' }}>Sebelum shift selesai</option>
                    </select>
                </div>

                <div id="deadline-before-end-wrapper" style="display: none;">
                    @php $deadlineBeforeEnd = old('deadline_before_end_minutes', $template['deadline_before_end_minutes'] ?? 60); @endphp
                    <label for="deadline_before_end_minutes" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Deadline Sebelum Shift Selesai (menit)
                    </label>
                    <input type="number" id="deadline_before_end_minutes" name="deadline_before_end_minutes" min="0" max="480"
                        value="{{ $deadlineBeforeEnd }}"
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                        Contoh: 60 = deadline 1 jam sebelum shift berakhir
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                @php $isActive = old('is_active', !empty($template['is_active']) ? 1 : 0); @endphp
                <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ (int) $isActive === 1 ? 'checked' : '' }}>
                    Template aktif (akan otomatis generate task sesuai pola)
                </label>
            </div>

            <div id="reset-anchor-wrapper" style="margin-bottom: 25px; display: none;">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333; cursor: pointer;">
                    <input type="checkbox" name="reset_anchor_date" value="1" {{ old('reset_anchor_date') ? 'checked' : '' }}>
                    Reset hitungan interval mulai hari ini (khusus mode setiap N hari)
                </label>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px; flex: 1;">
                    💾 Simpan Perubahan
                </button>
                <a href="{{ route($backRouteName) }}" class="btn"
                    style="padding: 12px 20px; font-size: 16px; background: #e0e0e0; color: #333; text-align: center;">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <script>
        function toggleRecurrenceDetailFields() {
            const recurrenceType = document.getElementById('recurrence_type').value;
            const weeklyWrapper = document.getElementById('weekly-day-wrapper');
            const intervalWrapper = document.getElementById('interval-days-wrapper');
            const resetAnchorWrapper = document.getElementById('reset-anchor-wrapper');
            const weeklyDayInput = document.getElementById('weekly_day');
            const intervalDaysInput = document.getElementById('interval_days');

            if (recurrenceType === 'weekly') {
                weeklyWrapper.style.display = 'block';
                intervalWrapper.style.display = 'none';
                resetAnchorWrapper.style.display = 'none';
                weeklyDayInput.required = true;
                intervalDaysInput.required = false;
            } else if (recurrenceType === 'every_n_days') {
                weeklyWrapper.style.display = 'none';
                intervalWrapper.style.display = 'block';
                resetAnchorWrapper.style.display = 'block';
                weeklyDayInput.required = false;
                intervalDaysInput.required = true;
            } else {
                weeklyWrapper.style.display = 'none';
                intervalWrapper.style.display = 'none';
                resetAnchorWrapper.style.display = 'none';
                weeklyDayInput.required = false;
                intervalDaysInput.required = false;
            }
        }

        function toggleScheduleMode() {
            const mode = document.getElementById('schedule_mode').value;
            const fixedSchedule = document.getElementById('fixed-schedule-wrapper');
            const fixedDeadline = document.getElementById('fixed-deadline-wrapper');
            const shiftOffset = document.getElementById('shift-offset-wrapper');
            const shiftDeadline = document.getElementById('shift-deadline-wrapper');
            const scheduleTimeInput = document.getElementById('schedule_time');
            const timeLimitInput = document.getElementById('time_limit_minutes');

            if (mode === 'shift_relative') {
                fixedSchedule.style.display = 'none';
                fixedDeadline.style.display = 'none';
                shiftOffset.style.display = 'block';
                shiftDeadline.style.display = 'block';
                scheduleTimeInput.required = false;
                timeLimitInput.required = false;
                toggleDeadlineMode();
            } else {
                fixedSchedule.style.display = 'block';
                fixedDeadline.style.display = 'block';
                shiftOffset.style.display = 'none';
                shiftDeadline.style.display = 'none';
                document.getElementById('deadline-before-end-wrapper').style.display = 'none';
                scheduleTimeInput.required = true;
                timeLimitInput.required = true;
            }
        }

        function toggleDeadlineMode() {
            const deadlineMode = document.getElementById('deadline_mode').value;
            const beforeEndWrapper = document.getElementById('deadline-before-end-wrapper');
            const fixedDeadline = document.getElementById('fixed-deadline-wrapper');

            if (deadlineMode === 'before_shift_end') {
                beforeEndWrapper.style.display = 'block';
                fixedDeadline.style.display = 'none';
            } else {
                beforeEndWrapper.style.display = 'none';
                fixedDeadline.style.display = 'block';
            }
        }

        toggleRecurrenceDetailFields();
        toggleScheduleMode();
    </script>
@endsection

@push('scripts')
<script>
function updateCategoryName(selectEl) {
    const selected = selectEl.options[selectEl.selectedIndex];
    document.getElementById('category_name').value = selected.dataset.name || '';
}
</script>
@endpush
