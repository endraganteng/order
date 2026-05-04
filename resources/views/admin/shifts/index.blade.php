@extends('admin.layout')

@section('title', 'Shift Kerja - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">Shift Kerja</h2>
            <div class="page-subtitle">Kelola shift kerja untuk waiter dan kasir.</div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.schedules.index') }}" class="btn" style="background: var(--color-border);">Jadwal Mingguan</a>
            <button type="button" class="btn btn-primary" onclick="openShiftModal()">+ Tambah Shift</button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @push('styles')
    <style>
        .shift-table-desktop { display: block; }
        .shift-cards-mobile { display: none; }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: #fff;
            padding: 24px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--color-border);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--color-text);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            color: var(--color-text-muted);
            padding: 0;
        }
        .modal-close:hover { color: var(--color-text); }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--color-border);
        }

        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            background: #eef2ff;
            color: #3730a3;
        }

        .tolerance-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            background: #fff7ed;
            color: #9a3412;
        }

        @media (max-width: 900px) {
            .shift-table-desktop { display: none; }
            .shift-cards-mobile {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .shift-mobile-card {
                border: 1px solid var(--color-border);
                border-radius: var(--radius-lg);
                padding: 14px;
                background: #fff;
                box-shadow: var(--shadow-sm);
            }
            .shift-mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 10px;
            }
            .shift-mobile-name {
                font-weight: 700;
                color: var(--color-text);
                font-size: 15px;
            }
            .shift-mobile-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 10px;
            }
            .shift-mobile-field {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .shift-mobile-field-label {
                font-size: 12px;
                font-weight: 600;
                color: var(--color-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .shift-mobile-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                padding-top: 10px;
                border-top: 1px solid var(--color-border);
            }
        }
    </style>
    @endpush

    {{-- Desktop Table --}}
    <div class="card shift-table-desktop" style="padding: 0; overflow: hidden;">
        <div class="table-scroll" style="padding: 16px;">
            <table>
                <thead>
                    <tr>
                        <th>Nama Shift</th>
                        <th>Jam Masuk</th>
                        <th>Jam Keluar</th>
                        <th>Toleransi Telat</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        @php
                            $shiftId = (string) ($shift['id'] ?? '');
                            $shiftName = (string) ($shift['name'] ?? '-');
                            $clockIn = (string) ($shift['clock_in_time'] ?? '-');
                            $clockOut = (string) ($shift['clock_out_time'] ?? '-');
                            $tolerance = (int) ($shift['late_tolerance_minutes'] ?? 0);
                            $isActive = ($shift['is_active'] ?? true) === true;
                        @endphp
                        <tr>
                            <td><div style="font-weight: 600;">{{ $shiftName }}</div></td>
                            <td><span class="time-badge">{{ $clockIn }}</span></td>
                            <td><span class="time-badge">{{ $clockOut }}</span></td>
                            <td><span class="tolerance-badge">{{ $tolerance }} menit</span></td>
                            <td>
                                @if($isActive)
                                    <span class="badge-status active">Aktif</span>
                                @else
                                    <span class="badge-status inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openShiftModal('{{ $shiftId }}', '{{ addslashes($shiftName) }}', '{{ $clockIn }}', '{{ $clockOut }}', {{ $tolerance }}, {{ $isActive ? 'true' : 'false' }})">Edit</button>
                                    <form method="POST" action="{{ route('admin.shifts.destroy', $shiftId) }}" data-confirm="Yakin hapus shift ini? Waiter yang menggunakan shift ini akan kehilangan assignment-nya.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-muted);">Belum ada shift. Silakan tambah shift baru.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Card Layout --}}
    <div class="shift-cards-mobile">
        @forelse($shifts as $shift)
            @php
                $shiftId = (string) ($shift['id'] ?? '');
                $shiftName = (string) ($shift['name'] ?? '-');
                $clockIn = (string) ($shift['clock_in_time'] ?? '-');
                $clockOut = (string) ($shift['clock_out_time'] ?? '-');
                $tolerance = (int) ($shift['late_tolerance_minutes'] ?? 0);
                $isActive = ($shift['is_active'] ?? true) === true;
            @endphp
            <div class="shift-mobile-card">
                <div class="shift-mobile-header">
                    <div>
                        <div class="shift-mobile-name">{{ $shiftName }}</div>
                    </div>
                    @if($isActive)
                        <span class="badge-status active">Aktif</span>
                    @else
                        <span class="badge-status inactive">Nonaktif</span>
                    @endif
                </div>
                <div class="shift-mobile-grid">
                    <div class="shift-mobile-field">
                        <div class="shift-mobile-field-label">Jam Masuk</div>
                        <span class="time-badge">{{ $clockIn }}</span>
                    </div>
                    <div class="shift-mobile-field">
                        <div class="shift-mobile-field-label">Jam Keluar</div>
                        <span class="time-badge">{{ $clockOut }}</span>
                    </div>
                    <div class="shift-mobile-field">
                        <div class="shift-mobile-field-label">Toleransi Telat</div>
                        <span class="tolerance-badge">{{ $tolerance }} menit</span>
                    </div>
                </div>
                <div class="shift-mobile-actions">
                    <button type="button" class="btn btn-warning btn-sm" onclick="openShiftModal('{{ $shiftId }}', '{{ addslashes($shiftName) }}', '{{ $clockIn }}', '{{ $clockOut }}', {{ $tolerance }}, {{ $isActive ? 'true' : 'false' }})">Edit</button>
                    <form method="POST" action="{{ route('admin.shifts.destroy', $shiftId) }}" data-confirm="Yakin hapus shift ini?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty">Belum ada shift. Silakan tambah shift baru.</div>
        @endforelse
    </div>

    {{-- Shift Modal --}}
    <div class="modal-overlay" id="shiftModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="shiftModalTitle">Tambah Shift</h3>
                <button type="button" class="modal-close" onclick="closeShiftModal()">&times;</button>
            </div>
            <form id="shiftForm" method="POST" action="{{ route('admin.shifts.store') }}">
                @csrf
                <div id="shiftMethodField"></div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="shiftName">Nama Shift *</label>
                    <input type="text" id="shiftName" name="name" class="form-input" required maxlength="100" placeholder="Misal: Pagi, Siang, Malam">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="shiftClockIn">Jam Masuk *</label>
                        <input type="time" id="shiftClockIn" name="clock_in_time" class="form-input" required value="08:00">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="shiftClockOut">Jam Keluar *</label>
                        <input type="time" id="shiftClockOut" name="clock_out_time" class="form-input" required value="17:00">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="shiftTolerance">Toleransi Telat (menit) *</label>
                    <input type="number" id="shiftTolerance" name="late_tolerance_minutes" class="form-input" required min="0" max="120" value="15">
                    <div class="form-hint">Waktu toleransi keterlambatan setelah jam masuk.</div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="shiftIsActive" name="is_active" value="1" checked>
                        <span>Shift Aktif</span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background: var(--color-border);" onclick="closeShiftModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveShift">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openShiftModal(id, name, clockIn, clockOut, tolerance, isActive) {
            var modal = document.getElementById('shiftModal');
            var title = document.getElementById('shiftModalTitle');
            var form = document.getElementById('shiftForm');
            var methodField = document.getElementById('shiftMethodField');

            document.getElementById('shiftName').value = name || '';
            document.getElementById('shiftClockIn').value = clockIn || '08:00';
            document.getElementById('shiftClockOut').value = clockOut || '17:00';
            document.getElementById('shiftTolerance').value = tolerance !== undefined ? tolerance : 15;
            document.getElementById('shiftIsActive').checked = isActive !== undefined ? isActive : true;

            if (id) {
                title.textContent = 'Edit Shift';
                form.action = '/admin/shifts/' + id;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            } else {
                title.textContent = 'Tambah Shift';
                form.action = '{{ route("admin.shifts.store") }}';
                methodField.innerHTML = '';
            }

            modal.classList.add('show');
            document.getElementById('shiftName').focus();
        }

        function closeShiftModal() {
            document.getElementById('shiftModal').classList.remove('show');
        }

        document.getElementById('shiftModal').addEventListener('click', function(e) {
            if (e.target === this) closeShiftModal();
        });

        document.getElementById('shiftForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            var form = this;
            var btn = document.getElementById('btnSaveShift');
            var originalText = btn.textContent;

            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            try {
                var formData = new FormData(form);
                var response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                var data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Gagal menyimpan: ' + (data.message || 'Error'));
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (err) {
                alert('Terjadi kesalahan koneksi');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });

        document.addEventListener('submit', function(e) {
            if (e.target.id === 'shiftForm') return;
            var form = e.target;
            var confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    </script>
@endsection
