@extends('admin.layout')

@section('title', 'Tugas Waiter - Admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h2 style="color: #333; font-size: clamp(24px, 5vw, 32px); margin: 0;">📝 Delegasi Tugas Waiter</h2>
        <a href="{{ route('admin.tasks.create') }}" class="btn btn-primary" style="font-size: 16px; padding: 10px 20px;">
            ➕ Buat Tugas Baru
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">
            ❌ {{ session('error') }}
        </div>
    @endif

    @php
        $activeRackCount = collect($racks ?? [])->filter(fn ($rack) => ($rack['is_active'] ?? true) === true)->count();
        $pending = collect($tasks ?? [])->where('status', 'pending')->count();
        $done = collect($tasks ?? [])->where('status', 'done')->count();
        $overdue = collect($tasks ?? [])->where('status', 'overdue')->count();
        $activityTotalReports = (int) ($waiterActivityBoard['total_reports'] ?? 0);
        $activityWaiterCount = (int) ($waiterActivityBoard['waiter_count'] ?? 0);
        $activityWaiters = $waiterActivityBoard['waiters'] ?? [];
        $collectedTotalReports = (int) ($collectedStockBoard['total_reports'] ?? 0);
        $collectedTotalMentions = (int) ($collectedStockBoard['total_item_mentions'] ?? 0);
        $collectedRacks = $collectedStockBoard['racks'] ?? [];
        $collectedTopItems = $collectedStockBoard['top_items'] ?? [];
        $dateNotDoneCount = count($dateNotDoneTasks ?? []);
        $rackNotDoneTotal = collect($rackExecutionBoard ?? [])->sum(fn ($board) => (int) ($board['not_done_count'] ?? 0));
    @endphp

    <div class="card" style="margin-bottom: 20px; padding: 20px;">
        <h3 style="margin: 0 0 8px 0; color: #333;">📊 Ringkasan Cepat Supervisor</h3>
        <div style="font-size: 13px; color: #64748b; margin-bottom: 14px;">
            Baca halaman ini dari atas ke bawah: <b>Ringkasan</b> → <b>blok detail yang relevan</b>. Section detail bisa dibuka/tutup agar tidak overload informasi.
        </div>

        <div class="admin-kpi-grid">
            <div class="admin-kpi-card" style="border-left-color:#ffc107;">
                <div class="admin-kpi-value" style="color:#f59e0b;">{{ $pending }}</div>
                <div class="admin-kpi-label">Task Pending</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#28a745;">
                <div class="admin-kpi-value" style="color:#28a745;">{{ $done }}</div>
                <div class="admin-kpi-label">Task Selesai</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#6c757d;">
                <div class="admin-kpi-value" style="color:#6b7280;">{{ $overdue }}</div>
                <div class="admin-kpi-label">Task Tidak Selesai</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#4f46e5;">
                <div class="admin-kpi-value" style="color:#4338ca;">{{ count($waiters ?? []) }}</div>
                <div class="admin-kpi-label">Waiter Aktif</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#f97316;">
                <div class="admin-kpi-value" style="color:#ea580c;">{{ $activeRackCount }}</div>
                <div class="admin-kpi-label">Rak Aktif</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#6366f1;">
                <div class="admin-kpi-value" style="color:#4f46e5;">{{ count($recurringTemplates ?? []) }}</div>
                <div class="admin-kpi-label">Template Berulang</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#7c3aed;">
                <div class="admin-kpi-value" style="color:#6d28d9;">{{ $activityTotalReports }}</div>
                <div class="admin-kpi-label">Laporan Kegiatan ({{ $selectedDate }})</div>
            </div>
            <div class="admin-kpi-card" style="border-left-color:#b45309;">
                <div class="admin-kpi-value" style="color:#92400e;">{{ $collectedTotalReports }}</div>
                <div class="admin-kpi-label">Laporan Stok ({{ $selectedDate }})</div>
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top: 14px;">
            <span class="badge" style="background: #fff7ed; color: #9a3412;">Total Rak: {{ count($racks ?? []) }}</span>
            <span class="badge" style="background: #ecfdf5; color: #166534;">Waiter Pelapor: {{ $activityWaiterCount }}</span>
            <span class="badge" style="background: #fef3c7; color: #92400e;">Item Stok Dilaporkan: {{ $collectedTotalMentions }}</span>
            <span class="badge" style="background: #fee2e2; color: #991b1b;">Butuh Tindak Lanjut: {{ $dateNotDoneCount }} task belum dikerjakan</span>
            <a href="{{ route('admin.racks.index') }}" class="btn" style="background: #2563eb; color: #fff; padding: 6px 12px; font-size: 13px;">Kelola Rak</a>
        </div>
    </div>

    <details class="card admin-section-card" style="margin-bottom: 20px;" open>
        <summary class="admin-section-summary">👥 Daftar Waiter Aktif <span class="badge">{{ count($waiters ?? []) }}</span></summary>
        <div class="admin-section-body">
            @if(empty($waiters) || count($waiters) === 0)
                <div style="font-size: 13px; color: #777;">Belum ada waiter aktif. Tambahkan dulu dari menu Waiters.</div>
            @else
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    @foreach($waiters as $waiter)
                        <span class="badge" style="background: #eef3ff; color: #304087; padding: 8px 12px; border-radius: 999px;">
                            {{ $waiter['name'] ?? '-' }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </details>

    <details class="card admin-section-card" style="margin-bottom: 20px;">
        <summary class="admin-section-summary">📦 Ringkasan Rak <span class="badge">{{ $activeRackCount }} aktif</span></summary>
        <div class="admin-section-body">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <span class="badge" style="background: #fff7ed; color: #9a3412; padding: 8px 12px; border-radius: 999px;">
                    Total Rak: {{ count($racks ?? []) }}
                </span>
                <span class="badge" style="background: #ecfdf5; color: #166534; padding: 8px 12px; border-radius: 999px;">
                    Rak Aktif: {{ $activeRackCount }}
                </span>
            </div>
        </div>
    </details>

    {{-- Recurring templates --}}
    <details class="card admin-section-card" style="margin-bottom: 20px;" open>
        <summary class="admin-section-summary">🔁 Jadwal Task Berulang (Waiter) <span class="badge">{{ count($recurringTemplates ?? []) }} template</span></summary>
        <div class="admin-section-body">

        @if(empty($recurringTemplates) || count($recurringTemplates) === 0)
            <div style="color: #777; font-size: 14px;">
                Belum ada template task berulang untuk waiter.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="display: table; min-width: 1040px;">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Jenis</th>
                            <th>Delegasi</th>
                            <th>Pola</th>
                            <th>Prioritas</th>
                            <th>Jam</th>
                            <th>Limit</th>
                            <th>Terakhir Generate</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recurringTemplates as $template)
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">{{ $template['title'] ?? '-' }}</div>
                                    @if(!empty($template['description']))
                                        <div style="font-size: 13px; color: #666;">{{ $template['description'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if(($template['task_type'] ?? 'general') === 'rack_check')
                                        <span class="badge" style="background: #fff7ed; color: #9a3412;">📦 Cek Rak</span>
                                        <div style="font-size: 12px; color: #9a3412; margin-top: 4px;">
                                            {{ $template['rack_name'] ?? '-' }}
                                        </div>
                                    @else
                                        <span class="badge" style="background: #eef2ff; color: #3730a3;">📝 Umum</span>
                                    @endif
                                    @if(!empty($template['requires_photo_proof']))
                                        <div style="font-size: 11px; color: #0369a1; margin-top: 4px;">📷 Bukti foto wajib</div>
                                    @endif
                                </td>
                                <td>
                                    @if(($template['assignment_type'] ?? 'all') === 'single')
                                        <span class="badge" style="background: #e3f2fd; color: #0d47a1;">🎯 {{ $template['assigned_waiter_name'] ?? '-' }}</span>
                                    @else
                                        <span class="badge" style="background: #ede7f6; color: #4a148c;">🌐 Semua Waiter</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $type = $template['recurrence_type'] ?? 'daily';
                                        $weeklyNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
                                    @endphp

                                    @if($type === 'weekly')
                                        <span class="badge" style="background: #e8f5e9; color: #1b5e20;">📅 Mingguan</span>
                                        <div style="font-size: 12px; color: #1b5e20; margin-top: 4px;">
                                            {{ $weeklyNames[(int) ($template['weekly_day'] ?? 0)] ?? '-' }}
                                        </div>
                                    @elseif($type === 'every_n_days')
                                        <span class="badge" style="background: #fff8e1; color: #8d6e00;">🔁 Setiap N Hari</span>
                                        <div style="font-size: 12px; color: #8d6e00; margin-top: 4px;">
                                            Tiap {{ $template['interval_days'] ?? '-' }} hari
                                        </div>
                                    @else
                                        <span class="badge" style="background: #e3f2fd; color: #0d47a1;">🗓️ Harian</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($template['priority'] ?? 'normal') === 'urgent')
                                        <span class="badge" style="background: #f8d7da; color: #721c24;">🔴 Urgent</span>
                                    @elseif(($template['priority'] ?? 'normal') === 'normal')
                                        <span class="badge" style="background: #cce5ff; color: #004085;">🔵 Normal</span>
                                    @else
                                        <span class="badge" style="background: #e2e3e5; color: #383d41;">⚪ Low</span>
                                    @endif
                                </td>
                                <td><span class="badge" style="background: #ede7f6; color: #5e35b1;">⏰ {{ $template['schedule_time'] ?? '-' }}</span></td>
                                <td><span class="badge" style="background: #fff3cd; color: #856404;">⏳ {{ $template['time_limit_minutes'] ?? '-' }} menit</span></td>
                                <td style="font-size: 13px; white-space: nowrap;">{{ !empty($template['last_generated_date']) ? $template['last_generated_date'] : '-' }}</td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <a href="{{ route('admin.tasks.recurring.edit', $template['id']) }}"
                                            class="btn" style="padding: 5px 10px; font-size: 13px; background: #e3f2fd; color: #0d47a1;">✏️ Edit</a>
                                        <form action="{{ route('admin.tasks.recurring.destroy', $template['id']) }}" method="POST"
                                            onsubmit="return confirm('Yakin hapus template task berulang ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 13px;">🗑️ Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </details>

    <details class="card admin-section-card" style="margin-bottom: 20px;">
        <summary class="admin-section-summary">🏆 Performa Waiter <span class="badge">Ranking penyelesaian</span></summary>
        <div class="admin-section-body">
        @if(empty($waiterPerformance))
            <div style="font-size: 13px; color: #777;">Belum ada data penyelesaian tugas waiter.</div>
        @else
            <div style="overflow-x: auto;">
                <table style="display: table; min-width: 520px;">
                    <thead>
                        <tr>
                            <th>Peringkat</th>
                            <th>Nama Waiter</th>
                            <th>Total Tugas Selesai</th>
                            <th>Terakhir Mengerjakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($waiterPerformance as $idx => $stat)
                            <tr>
                                <td>#{{ $idx + 1 }}</td>
                                <td>{{ $stat['name'] }}</td>
                                <td>{{ $stat['done_count'] }}</td>
                                <td>{{ !empty($stat['last_done_at']) ? date('d/m/Y H:i', $stat['last_done_at']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </details>

    <details class="card admin-section-card" style="margin-bottom: 20px;" open>
        <summary class="admin-section-summary">📅 Tracking Tugas per Tanggal <span class="badge">{{ $dateNotDoneCount }} belum dikerjakan</span></summary>
        <div class="admin-section-body">

        <form method="GET" action="{{ route('admin.tasks.index') }}" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px;">
            <input type="date" name="track_date" value="{{ $selectedDate }}"
                style="padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
        </form>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
            <div style="border: 1px solid #d4edda; border-radius: 8px; padding: 12px; background: #f6fffa;">
                <div style="font-weight: 600; color: #155724; margin-bottom: 8px;">✅ Dikerjakan ({{ count($dateDoneTasks) }})</div>
                @if(empty($dateDoneTasks))
                    <div style="font-size: 13px; color: #666;">Tidak ada tugas selesai pada tanggal ini.</div>
                @else
                    <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #333;">
                        @foreach($dateDoneTasks as $task)
                            <li style="margin-bottom: 5px;">
                                <strong>{{ $task['title'] ?? '-' }}</strong>
                                <div>Waiter: {{ $task['completed_by_waiter_name'] ?? '-' }}</div>
                                @if(($task['task_type'] ?? 'general') === 'rack_check')
                                    <div style="font-size: 12px; color: #9a3412;">Rak: {{ $task['rack_name'] ?? '-' }} ({{ $task['completed_scanned_barcode'] ?? '-' }})</div>
                                    <div style="font-size: 12px; color: #334155;">
                                        Laporan stok:
                                        @if(!empty($task['completed_no_out_of_stock']))
                                            ✅ Tidak ada barang habis
                                        @elseif(!empty($task['completed_stock_report']))
                                            ⚠️ {{ $task['completed_stock_report'] }}
                                        @else
                                            -
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div style="border: 1px solid #f5c6cb; border-radius: 8px; padding: 12px; background: #fff8f8;">
                <div style="font-weight: 600; color: #721c24; margin-bottom: 8px;">❌ Tidak Dikerjakan ({{ count($dateNotDoneTasks) }})</div>
                @if(empty($dateNotDoneTasks))
                    <div style="font-size: 13px; color: #666;">Semua tugas pada tanggal ini selesai.</div>
                @else
                    <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #333;">
                        @foreach($dateNotDoneTasks as $task)
                            <li style="margin-bottom: 5px;">
                                <strong>{{ $task['title'] ?? '-' }}</strong>
                                <div>Status: {{ strtoupper($task['status'] ?? '-') }}</div>
                                <div>Target: {{ $task['assigned_waiter_name'] ?? '-' }}</div>
                                @if(($task['task_type'] ?? 'general') === 'rack_check')
                                    <div style="font-size: 12px; color: #9a3412;">Rak: {{ $task['rack_name'] ?? '-' }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        </div>
    </details>

    <details class="card admin-section-card" style="margin-bottom: 20px;">
        <summary class="admin-section-summary">📔 Laporan Kegiatan Waiter <span class="badge">{{ $activityTotalReports }} laporan</span></summary>
        <div class="admin-section-body">

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 12px;">
            <span class="badge" style="background:#ede9fe;color:#5b21b6;">Total Laporan: {{ $activityTotalReports }}</span>
            <span class="badge" style="background:#e0f2fe;color:#0c4a6e;">Waiter Pelapor: {{ $activityWaiterCount }}</span>
        </div>

        @if($activityTotalReports === 0)
            <div style="font-size: 13px; color: #777;">Belum ada laporan kegiatan waiter pada tanggal ini.</div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px;">
                @foreach($activityWaiters as $waiterActivity)
                    <div style="border:1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #ffffff;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom: 8px;">
                            <div>
                                <div style="font-weight:700; color:#0f172a;">{{ $waiterActivity['waiter_name'] ?? '-' }}</div>
                                <div style="font-size:12px; color:#64748b;">{{ $waiterActivity['waiter_email'] ?? '-' }}</div>
                            </div>
                            <span class="badge" style="background:#ede9fe;color:#5b21b6;">{{ $waiterActivity['report_count'] ?? 0 }} laporan</span>
                        </div>

                        @if(empty($waiterActivity['reports']))
                            <div style="font-size: 12px; color: #64748b;">Belum ada detail laporan.</div>
                        @else
                            <ul style="margin:0; padding-left:18px; font-size:12px; color:#334155;">
                                @foreach(array_slice($waiterActivity['reports'], 0, 5) as $report)
                                    <li style="margin-bottom: 8px;">
                                        <div style="font-size:11px; color:#64748b; margin-bottom: 2px;">
                                            {{ !empty($report['created_at']) ? date('d/m H:i', (int) $report['created_at']) : '-' }}
                                        </div>
                                        <div style="color:#1f2937;">{{ $report['activity_text'] ?? '-' }}</div>
                                        @if(!empty($report['activity_items']))
                                            <div style="font-size:11px; color:#5b21b6; margin-top: 2px;">
                                                • {{ implode(' • ', $report['activity_items']) }}
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </details>

    <details class="card admin-section-card" style="margin-bottom: 20px;">
        <summary class="admin-section-summary">🔍 Monitoring Cek Rak <span class="badge">{{ $rackNotDoneTotal }} belum selesai</span></summary>
        <div class="admin-section-body">

        @if(empty($rackExecutionBoard))
            <div style="font-size: 13px; color: #777;">Tidak ada task cek rak pada tanggal ini.</div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px;">
                @foreach($rackExecutionBoard as $rackBoard)
                    <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div>
                                <div style="font-weight: 700; color: #0f172a;">{{ $rackBoard['rack_name'] ?? '-' }}</div>
                                <div style="font-size: 12px; color: #475569;">{{ $rackBoard['rack_location'] ?? '-' }}</div>
                            </div>
                            <span class="badge" style="background:#fff7ed;color:#9a3412;">{{ $rackBoard['rack_barcode_value'] ?? '-' }}</span>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 10px;">
                            <span class="badge" style="background:#dcfce7;color:#166534;">✅ Selesai: {{ $rackBoard['done_count'] ?? 0 }}</span>
                            <span class="badge" style="background:#fee2e2;color:#991b1b;">❌ Belum: {{ $rackBoard['not_done_count'] ?? 0 }}</span>
                        </div>

                        <div style="font-size: 12px; color: #334155; margin-bottom: 6px; font-weight: 600;">Yang sudah mengerjakan:</div>
                        @if(empty($rackBoard['done_waiters']))
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 10px;">Belum ada waiter selesai.</div>
                        @else
                            <ul style="margin: 0 0 10px 0; padding-left: 18px; font-size: 12px; color: #166534;">
                                @foreach($rackBoard['done_waiters'] as $doneWaiter)
                                    <li>
                                        {{ $doneWaiter['name'] ?? '-' }}
                                        @if(!empty($doneWaiter['completed_scanned_barcode']))
                                            - scan: {{ $doneWaiter['completed_scanned_barcode'] }}
                                        @endif
                                        <div style="font-size: 11px; color: #334155; margin-top: 2px;">
                                            Laporan stok:
                                            @if(!empty($doneWaiter['completed_no_out_of_stock']))
                                                ✅ Tidak ada barang habis
                                            @elseif(!empty($doneWaiter['completed_stock_report']))
                                                ⚠️ {{ $doneWaiter['completed_stock_report'] }}
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div style="font-size: 12px; color: #334155; margin-bottom: 6px; font-weight: 600;">Yang belum mengerjakan:</div>
                        @if(empty($rackBoard['not_done_waiters']))
                            <div style="font-size: 12px; color: #64748b;">Semua waiter target sudah selesai.</div>
                        @else
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #991b1b;">
                                @foreach($rackBoard['not_done_waiters'] as $pendingWaiter)
                                    <li>{{ $pendingWaiter['name'] ?? '-' }} - {{ strtoupper($pendingWaiter['status'] ?? 'pending') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </details>

    <details class="card admin-section-card" style="margin-bottom: 20px;">
        <summary class="admin-section-summary">📥 Laporan Barang Menipis/Habis Terkumpul <span class="badge">{{ $collectedTotalReports }} laporan</span></summary>
        <div class="admin-section-body">

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 12px;">
            <span class="badge" style="background:#fee2e2;color:#991b1b;">Laporan Masuk: {{ $collectedTotalReports }}</span>
            <span class="badge" style="background:#fff7ed;color:#9a3412;">Total Item Dilaporkan: {{ $collectedTotalMentions }}</span>
            <span class="badge" style="background:#e0f2fe;color:#0c4a6e;">Rak Terdampak: {{ count($collectedRacks) }}</span>
        </div>

        @if($collectedTotalReports === 0)
            <div style="font-size: 13px; color: #777;">Belum ada laporan barang menipis/habis pada tanggal ini.</div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div style="border:1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc;">
                    <div style="font-weight: 700; color: #0f172a; margin-bottom: 8px;">🔥 Item Paling Sering Dilaporkan</div>
                    @if(empty($collectedTopItems))
                        <div style="font-size: 12px; color: #64748b;">Belum ada item terkumpul.</div>
                    @else
                        <ol style="margin: 0; padding-left: 18px; font-size: 12px; color: #334155;">
                            @foreach(array_slice($collectedTopItems, 0, 12) as $item)
                                <li style="margin-bottom: 4px;">
                                    <strong>{{ $item['item'] ?? '-' }}</strong>
                                    <div style="font-size: 11px; color: #64748b;">{{ $item['count'] ?? 0 }} laporan • {{ $item['rack_count'] ?? 0 }} rak</div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                <div style="border:1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc;">
                    <div style="font-weight: 700; color: #0f172a; margin-bottom: 8px;">🧾 Ringkasan Cepat</div>
                    <div style="font-size: 12px; color: #334155; line-height: 1.5;">
                        Dashboard ini hanya mengumpulkan laporan waiter yang mengandung item menipis/habis (bukan yang centang “Tidak ada barang habis”).
                        Supervisor bisa lihat item apa yang berulang dilaporkan dan dari rak mana sumbernya.
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px;">
                @foreach($collectedRacks as $rackStock)
                    <div style="border:1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #ffffff;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div>
                                <div style="font-weight: 700; color: #0f172a;">{{ $rackStock['rack_name'] ?? '-' }}</div>
                                <div style="font-size: 12px; color: #475569;">{{ $rackStock['rack_location'] ?? '-' }}</div>
                            </div>
                            <span class="badge" style="background:#fff7ed;color:#9a3412;">{{ $rackStock['rack_barcode_value'] ?? '-' }}</span>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 8px;">
                            <span class="badge" style="background:#fee2e2;color:#991b1b;">Laporan: {{ $rackStock['reports_count'] ?? 0 }}</span>
                            <span class="badge" style="background:#fff7ed;color:#9a3412;">Item: {{ $rackStock['item_mentions_count'] ?? 0 }}</span>
                        </div>

                        <div style="font-size: 12px; color: #334155; margin-bottom: 6px; font-weight: 600;">Item yang paling sering dilaporkan:</div>
                        @if(empty($rackStock['items']))
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">Belum ada item tercatat.</div>
                        @else
                            <ul style="margin: 0 0 8px 0; padding-left: 18px; font-size: 12px; color: #9a3412;">
                                @foreach(array_slice($rackStock['items'], 0, 10) as $item)
                                    <li>{{ $item['item'] ?? '-' }} <span style="color:#64748b;">({{ $item['count'] ?? 0 }}x)</span></li>
                                @endforeach
                            </ul>
                        @endif

                        <div style="font-size: 12px; color: #334155; margin-bottom: 6px; font-weight: 600;">Laporan terbaru waiter:</div>
                        @if(empty($rackStock['reports']))
                            <div style="font-size: 12px; color: #64748b;">Belum ada laporan.</div>
                        @else
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #334155;">
                                @foreach(array_slice($rackStock['reports'], 0, 5) as $report)
                                    <li style="margin-bottom: 4px;">
                                        <strong>{{ $report['waiter_name'] ?? '-' }}</strong>
                                        @if(!empty($report['reported_at']))
                                            <span style="color:#64748b;">({{ date('d/m H:i', (int) $report['reported_at']) }})</span>
                                        @endif
                                        <div style="font-size: 11px; color: #9a3412;">{{ implode(', ', $report['items'] ?? []) }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </details>

    <details class="card admin-section-card" style="padding: 0; overflow: hidden; margin-bottom: 20px;">
        <summary class="admin-section-summary" style="padding: 16px;">🕘 Riwayat Tugas Waiter <span class="badge">{{ count($taskHistory ?? []) }} data</span></summary>
        <div class="admin-section-body" style="padding-top: 0;">
            <div style="font-size:12px; color:#64748b; margin-bottom:10px;">
                Gunakan bagian ini saat perlu audit detail per-task. Untuk monitoring cepat, gunakan ringkasan dan section tracking di atas.
            </div>
            <div style="overflow-x: auto;">
            <table style="display: table; min-width: 980px;">
                <thead>
                    <tr>
                        <th>Tugas</th>
                        <th>Jenis</th>
                        <th>Target Waiter</th>
                        <th>Status</th>
                        <th>Diverifikasi Oleh</th>
                        <th>Laporan Stok Rak</th>
                        <th>Bukti Foto</th>
                        <th>Tanggal Tracking</th>
                        <th>Waktu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taskHistory as $task)
                        <tr>
                            <td>
                                <div style="font-weight: 600;">{{ $task['title'] ?? '-' }}</div>
                                @if(!empty($task['description']))
                                    <div style="font-size: 12px; color: #666;">{{ $task['description'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if(($task['task_type'] ?? 'general') === 'rack_check')
                                    <span class="badge" style="background: #fff7ed; color: #9a3412;">📦 Cek Rak</span>
                                    <div style="font-size: 11px; color: #9a3412; margin-top: 4px;">
                                        {{ $task['rack_name'] ?? '-' }}
                                    </div>
                                @else
                                    <span class="badge" style="background: #eef2ff; color: #3730a3;">📝 Umum</span>
                                @endif
                            </td>
                            <td>{{ $task['assigned_waiter_name'] ?? '-' }}</td>
                            <td>
                                @if(($task['status'] ?? '') === 'done')
                                    <span class="badge badge-success">✅ Selesai</span>
                                @elseif(($task['status'] ?? '') === 'overdue')
                                    <span class="badge" style="background: #f8d7da; color: #721c24;">❌ Tidak Selesai</span>
                                @else
                                    <span class="badge" style="background: #fff3cd; color: #856404;">⏳ Pending</span>
                                @endif
                            </td>
                            <td>{{ $task['completed_by_waiter_name'] ?? '-' }}</td>
                            <td>
                                @if(($task['task_type'] ?? 'general') === 'rack_check')
                                    @if(!empty($task['completed_no_out_of_stock']))
                                        <span class="badge" style="background:#dcfce7;color:#166534;">✅ Tidak ada barang habis</span>
                                    @elseif(!empty($task['completed_stock_report']))
                                        <div style="font-size: 12px; color: #9a3412;">⚠️ {{ $task['completed_stock_report'] }}</div>
                                    @else
                                        <span style="color:#9ca3af;">-</span>
                                    @endif
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($task['completed_photo_proof_url']))
                                    <button
                                        type="button"
                                        class="js-admin-photo-view"
                                        data-photo-url="{{ $task['completed_photo_proof_url'] }}"
                                        data-photo-size="{{ (int) ($task['completed_photo_proof_size_bytes'] ?? 0) }}"
                                        data-photo-mime="{{ $task['completed_photo_proof_mime_type'] ?? '' }}"
                                        style="font-size: 12px; color: #1d4ed8; font-weight: 700; background:#e0ecff; border:1px solid #bfdbfe; border-radius:8px; padding:6px 10px; cursor:pointer;"
                                    >📷 Lihat Foto</button>
                                    @if(!empty($task['completed_photo_proof_size_bytes']))
                                        <div style="font-size: 11px; color: #64748b;">
                                            {{ number_format(((int) $task['completed_photo_proof_size_bytes']) / 1024, 1) }} KB
                                        </div>
                                    @endif
                                @elseif(!empty($task['requires_photo_proof']))
                                    <span style="font-size: 12px; color: #9a3412;">(wajib foto)</span>
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td>{{ $task['tracking_date'] ?? '-' }}</td>
                            <td>
                                Dibuat: {{ !empty($task['created_at']) ? date('d/m/Y H:i', (int) $task['created_at']) : '-' }}
                                @if(!empty($task['completed_at']))
                                    <div style="font-size: 11px; color: #28a745;">Selesai: {{ date('d/m/Y H:i', (int) $task['completed_at']) }}</div>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.tasks.destroy', $task['id']) }}" method="POST"
                                    onsubmit="return confirm('Yakin hapus tugas ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 13px;">🗑️ Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; color: #777;">Belum ada riwayat tugas waiter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </details>

    <style>
        .admin-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
        }
        .admin-kpi-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .admin-kpi-value {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
        }
        .admin-kpi-label {
            margin-top: 4px;
            font-size: 12px;
            color: #64748b;
        }
        .admin-section-card {
            border: 1px solid #e2e8f0;
        }
        .admin-section-card[open] {
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .admin-section-summary {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            font-weight: 700;
            color: #0f172a;
            padding: 14px 16px;
        }
        .admin-section-summary::-webkit-details-marker {
            display: none;
        }
        .admin-section-summary::after {
            content: '▾';
            color: #64748b;
            font-size: 14px;
            transition: transform 0.2s ease;
        }
        .admin-section-card[open] > .admin-section-summary::after {
            transform: rotate(180deg);
        }
        .admin-section-body {
            padding: 0 16px 16px 16px;
            border-top: 1px solid #f1f5f9;
        }
        .admin-photo-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.72);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1500;
        }
        .admin-photo-modal-box {
            width: min(100%, 760px);
            max-height: calc(100vh - 32px);
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.35);
        }
        .admin-photo-modal-image {
            width: 100%;
            max-height: 72vh;
            object-fit: contain;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #f8fafc;
        }
        .admin-photo-modal-meta {
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
        }
    </style>

    <div id="admin-photo-modal" class="admin-photo-modal" aria-hidden="true">
        <div class="admin-photo-modal-box">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
                <strong>📷 Preview Bukti Foto</strong>
                <button type="button" id="admin-photo-modal-close" class="btn btn-danger" style="padding:6px 10px;">Tutup</button>
            </div>
            <img id="admin-photo-modal-image" class="admin-photo-modal-image" src="" alt="Preview bukti foto">
            <div id="admin-photo-modal-meta" class="admin-photo-modal-meta"></div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('admin-photo-modal');
            const imageEl = document.getElementById('admin-photo-modal-image');
            const metaEl = document.getElementById('admin-photo-modal-meta');
            const closeBtn = document.getElementById('admin-photo-modal-close');

            if (!modal || !imageEl || !metaEl) {
                return;
            }

            function formatBytes(bytes) {
                const value = Number(bytes || 0);
                if (!Number.isFinite(value) || value <= 0) {
                    return '0 B';
                }
                if (value < 1024) {
                    return `${value} B`;
                }
                if (value < 1024 * 1024) {
                    return `${(value / 1024).toFixed(1)} KB`;
                }
                return `${(value / (1024 * 1024)).toFixed(2)} MB`;
            }

            function closeModal() {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                imageEl.src = '';
                metaEl.textContent = '';
            }

            function openModal(url, sizeBytes, mimeType) {
                imageEl.src = url;
                const normalizedMime = String(mimeType || 'image/*').trim() || 'image/*';
                const extra = Number(sizeBytes || 0) > 0 ? ` • ${formatBytes(sizeBytes)}` : '';
                metaEl.textContent = `Format: ${normalizedMime}${extra}`;
                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
            }

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('.js-admin-photo-view');
                if (trigger) {
                    const photoUrl = String(trigger.getAttribute('data-photo-url') || '').trim();
                    if (photoUrl === '') {
                        return;
                    }
                    const photoSize = Number(trigger.getAttribute('data-photo-size') || 0);
                    const photoMime = String(trigger.getAttribute('data-photo-mime') || '');
                    openModal(photoUrl, photoSize, photoMime);
                    return;
                }

                if (event.target === modal) {
                    closeModal();
                }
            });

            closeBtn?.addEventListener('click', function () {
                closeModal();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.style.display === 'flex') {
                    closeModal();
                }
            });
        })();
    </script>
@endsection
