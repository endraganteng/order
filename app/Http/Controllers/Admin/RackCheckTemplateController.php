<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRackCheckTemplateRequest;
use App\Http\Requests\UpdateRackCheckTemplateRequest;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

/**
 * Wizard sederhana untuk membuat template Cek Rak otomatis dengan
 * mode `simple_lowest_load`. Mode ini bypass AI Balancing lama —
 * memilih petugas dengan beban paling ringan dari kandidat yang
 * dipilih supervisor.
 *
 * Tidak menggantikan /admin/tasks/studio (mode lama tetap jalan).
 */
class RackCheckTemplateController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * GET /admin/rack-check/templates
     * Daftar template aktif (mode simple_lowest_load saja).
     */
    public function index()
    {
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();

        $templates = array_values(array_filter($allTemplates, function ($tpl) {
            $strategy = (string) ($tpl['assignment_strategy'] ?? '');
            return ($tpl['task_type'] ?? '') === 'rack_check'
                && in_array($strategy, ['simple_lowest_load', 'round_robin_simple'], true);
        }));

        // Build waiter name map untuk tampilan
        $waiters = $this->firebase->getActiveWaiters();
        $waiterMap = [];
        foreach ($waiters as $w) {
            $wid = (string) ($w['id'] ?? '');
            if ($wid !== '') {
                $waiterMap[$wid] = (string) ($w['name'] ?? $wid);
            }
        }

        // Sort: aktif dulu, lalu by created_at DESC
        usort($templates, function ($a, $b) {
            $aActive = ($a['is_active'] ?? true) ? 0 : 1;
            $bActive = ($b['is_active'] ?? true) ? 0 : 1;
            if ($aActive !== $bActive) {
                return $aActive <=> $bActive;
            }
            return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
        });

        return view('admin.rack_check.templates.index', compact('templates', 'waiterMap'));
    }

    /**
     * GET /admin/rack-check/templates/create
     * Tampilkan wizard 4 step.
     */
    public function create()
    {
        $racks = $this->firebase->getActiveRacks();
        $waiters = $this->firebase->getActiveWaiters();

        // Active rack-check templates (semua strategy) untuk warning duplikat rak
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRackMap = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (empty($tpl['is_active'])) {
                continue;
            }
            $rid = (string) ($tpl['rack_id'] ?? '');
            if ($rid === '') {
                continue;
            }
            $lockedRackMap[$rid] = [
                'template_id' => (string) ($tpl['id'] ?? ''),
                'strategy' => (string) ($tpl['assignment_strategy'] ?? ''),
                'rack_name' => (string) ($tpl['rack_name'] ?? $rid),
            ];
        }

        return view('admin.rack_check.templates.create', compact('racks', 'waiters', 'lockedRackMap'));
    }

    /**
     * POST /admin/rack-check/templates
     * Simpan satu template per rack (multi-rack = banyak template).
     */
    
    /**
     * GET /admin/rack-check/templates/{id}/edit
     * Tampilkan wizard dengan data template yang sudah ada.
     */
    public function edit($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            abort(404, 'Template tidak ditemukan.');
        }

        $strategy = (string) ($template['assignment_strategy'] ?? '');
        if (($template['task_type'] ?? '') !== 'rack_check'
            || ! in_array($strategy, ['simple_lowest_load', 'round_robin_simple'], true)) {
            abort(403, 'Template ini bukan mode wizard, tidak bisa diedit di sini.');
        }

        $racks = $this->firebase->getActiveRacks();
        $waiters = $this->firebase->getActiveWaiters();

        // Also build lockedRackMap (same as create() does) for the view
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRackMap = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') continue;
            if (empty($tpl['is_active'])) continue;
            $rid = (string) ($tpl['rack_id'] ?? '');
            if ($rid === '') continue;
            // Don't lock the rack that belongs to THIS template being edited
            if ($rid === (string)($template['rack_id'] ?? '')) continue;
            $lockedRackMap[$rid] = [
                'template_id' => (string) ($tpl['id'] ?? ''),
                'strategy' => (string) ($tpl['assignment_strategy'] ?? ''),
                'rack_name' => (string) ($tpl['rack_name'] ?? $rid),
            ];
        }

        return view('admin.rack_check.templates.create', compact('template', 'racks', 'waiters', 'lockedRackMap'));
    }

    /**
     * PUT /admin/rack-check/templates/{id}
     * Update template yang sudah ada.
     */
    public function update(UpdateRackCheckTemplateRequest $request, $id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            abort(404);
        }

        $strategy = (string) ($template['assignment_strategy'] ?? '');
        if (($template['task_type'] ?? '') !== 'rack_check'
            || ! in_array($strategy, ['simple_lowest_load', 'round_robin_simple'], true)) {
            abort(403);
        }

        $waiterIds = array_values(array_unique(array_filter(
            array_map(fn ($x) => trim((string) $x), (array) $request->input('selected_waiter_ids', [])),
            fn ($x) => $x !== ''
        )));

        $invalidWaiters = [];
        foreach ($waiterIds as $wid) {
            $w = $this->firebase->getWaiterById($wid);
            if (! $w || ($w['is_active'] ?? true) === false) {
                $invalidWaiters[] = $wid;
            }
        }
        if (count($invalidWaiters) > 0) {
            return back()->withErrors(['selected_waiter_ids' => 'Beberapa petugas tidak aktif.'])->withInput();
        }

        $assignmentStrategy = (string) $request->input('assignment_strategy', 'simple_lowest_load');
        if (! in_array($assignmentStrategy, ['simple_lowest_load', 'round_robin_simple'], true)) {
            $assignmentStrategy = 'simple_lowest_load';
        }

        $recurrenceType = (string) $request->input('recurrence_type', 'daily');
        $weeklyDay = $recurrenceType === 'weekly'
            ? max(1, min(7, (int) $request->input('weekly_day', 1)))
            : null;
        $intervalDays = $recurrenceType === 'every_n_days'
            ? max(1, (int) $request->input('interval_days', 2))
            : null;
        $anchorDate = (string) $request->input('recurrence_anchor_date', $template['recurrence_anchor_date'] ?? date('Y-m-d'));

        $fullShiftCapRaw = $request->input('full_shift_daily_cap');
        $fullShiftDailyCap = ($fullShiftCapRaw !== null && $fullShiftCapRaw !== '')
            ? max(0, (int) $fullShiftCapRaw)
            : null;
        $partialShiftCapRaw = $request->input('partial_shift_daily_cap');
        $partialShiftDailyCap = ($partialShiftCapRaw !== null && $partialShiftCapRaw !== '')
            ? max(0, (int) $partialShiftCapRaw)
            : null;

        $this->firebase->updateRecurringWaiterTaskTemplate($id, [
            'title' => (string) ($template['rack_name'] ?? $template['title'] ?? ''),
            'description' => (string) ($template['description'] ?? ''),
            'priority' => 'normal',
            'assignment_strategy' => $assignmentStrategy,
            'simple_lowest_load_enabled' => $assignmentStrategy === 'simple_lowest_load',
            'selected_waiter_ids' => $waiterIds,
            'requires_barcode_scan' => (bool) $request->boolean('requires_barcode_scan', true),
            'requires_photo_before' => (bool) $request->boolean('requires_photo_before', true),
            'requires_photo_proof' => (bool) $request->boolean('requires_photo_proof', true),
            'allow_note' => (bool) $request->boolean('allow_note', true),
            'enable_empty_product_report' => (bool) $request->boolean('enable_empty_product_report', true),
            'full_shift_daily_cap' => $fullShiftDailyCap,
            'partial_shift_daily_cap' => $partialShiftDailyCap,
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $weeklyDay,
            'interval_days' => $intervalDays,
            'recurrence_anchor_date' => $anchorDate,
            'updated_at' => time(),
        ]);

        $this->firebase->logAuditAction('update', 'rack_check_template_simple', $id, [
            'waiter_count' => count($waiterIds),
            'recurrence_type' => $recurrenceType,
            'strategy' => $assignmentStrategy,
        ]);

        return redirect()->route('admin.rack_check.templates.index')
            ->with('success', 'Template cek rak otomatis berhasil diperbarui.');
    }

    /**
     * POST /admin/rack-check/templates
     * Simpan satu template per rack (multi-rack = banyak template).
     */
    public function store(StoreRackCheckTemplateRequest $request)
    {
        $rackIds = (array) $request->input('rack_ids', []);
        $rackIds = array_values(array_unique(array_filter(
            array_map(fn ($x) => trim((string) $x), $rackIds),
            fn ($x) => $x !== ''
        )));

        $waiterIds = (array) $request->input('selected_waiter_ids', []);
        $waiterIds = array_values(array_unique(array_filter(
            array_map(fn ($x) => trim((string) $x), $waiterIds),
            fn ($x) => $x !== ''
        )));

        // Validate racks aktif
        $activeRacks = $this->firebase->getActiveRacks();
        $rackMap = [];
        foreach ($activeRacks as $r) {
            $rid = (string) ($r['id'] ?? '');
            if ($rid !== '') {
                $rackMap[$rid] = $r;
            }
        }

        $invalidRacks = array_values(array_filter($rackIds, fn ($id) => ! isset($rackMap[$id])));
        if (count($invalidRacks) > 0) {
            return back()
                ->withErrors(['rack_ids' => 'Beberapa rak tidak valid atau nonaktif. Refresh halaman dan pilih ulang.'])
                ->withInput();
        }

        // Reject rack yang sudah punya template aktif (anti-duplikat)
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRacks = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (empty($tpl['is_active'])) {
                continue;
            }
            $tplRack = (string) ($tpl['rack_id'] ?? '');
            if ($tplRack !== '' && in_array($tplRack, $rackIds, true)) {
                $lockedRacks[$tplRack] = (string) ($tpl['rack_name'] ?? $tplRack);
            }
        }
        if (count($lockedRacks) > 0) {
            return back()
                ->withErrors(['rack_ids' => 'Rak berikut sudah punya template aktif: '
                    .implode(', ', array_values($lockedRacks))
                    .'. Hapus/nonaktifkan template lama dulu.'])
                ->withInput();
        }

        // Validate waiters aktif
        $invalidWaiters = [];
        $resolvedWaiters = [];
        foreach ($waiterIds as $wid) {
            $w = $this->firebase->getWaiterById($wid);
            if (! $w || ($w['is_active'] ?? true) === false) {
                $invalidWaiters[] = $wid;
                continue;
            }
            $resolvedWaiters[] = $w;
        }
        if (count($invalidWaiters) > 0) {
            return back()
                ->withErrors(['selected_waiter_ids' => 'Beberapa petugas tidak aktif atau tidak ditemukan.'])
                ->withInput();
        }

        // Mode pembagian (default: simple_lowest_load untuk backward compat)
        $assignmentStrategy = (string) $request->input('assignment_strategy', 'simple_lowest_load');
        if (! in_array($assignmentStrategy, ['simple_lowest_load', 'round_robin_simple'], true)) {
            $assignmentStrategy = 'simple_lowest_load';
        }

        // Schedule fields
        $recurrenceType = (string) $request->input('recurrence_type', 'daily');
        $weeklyDay = $recurrenceType === 'weekly'
            ? max(1, min(7, (int) $request->input('weekly_day', 1)))
            : null;
        $intervalDays = $recurrenceType === 'every_n_days'
            ? max(1, (int) $request->input('interval_days', 2))
            : null;
        $anchorDate = (string) $request->input('recurrence_anchor_date', date('Y-m-d'));

        // Jam & deadline mengikuti shift waiter (set per task saat generate),
        // template hanya menyimpan placeholder default supaya kompatibel skema lama.
        $scheduleTime = '08:00';        // placeholder, akan di-override per shift waiter
        $timeLimitMinutes = 480;        // placeholder

        $requiresBarcodeScan = (bool) $request->boolean('requires_barcode_scan', true);
        $requiresPhotoBefore = (bool) $request->boolean('requires_photo_before', true);
        $requiresPhotoProof = (bool) $request->boolean('requires_photo_proof', true);
        $allowNote = (bool) $request->boolean('allow_note', true);
        $enableEmptyProductReport = (bool) $request->boolean('enable_empty_product_report', true);

        // Custom daily cap per shift type (wizard setting).
        // null = use hardcoded defaults; 0 = exclude from rack_check entirely.
        $fullShiftCapRaw = $request->input('full_shift_daily_cap');
        $fullShiftDailyCap = ($fullShiftCapRaw !== null && $fullShiftCapRaw !== '')
            ? max(0, (int) $fullShiftCapRaw)
            : null;
        $partialShiftCapRaw = $request->input('partial_shift_daily_cap');
        $partialShiftDailyCap = ($partialShiftCapRaw !== null && $partialShiftCapRaw !== '')
            ? max(0, (int) $partialShiftCapRaw)
            : null;

        $createdTemplates = 0;
        $errors = [];

        foreach ($rackIds as $rid) {
            $rack = $rackMap[$rid];
            try {
                $this->firebase->createRecurringWaiterTaskTemplate([
                    'title' => (string) ($rack['name'] ?? 'Rak'),
                    'description' => '',
                    'priority' => 'normal',
                    'assigned_by' => 'Supervisor',
                    'task_type' => 'rack_check',
                    'category_id' => null,
                    'category_name' => null,
                    'requires_barcode_scan' => $requiresBarcodeScan,
                    'requires_photo_proof' => $requiresPhotoProof,
                    'requires_photo_before' => $requiresPhotoBefore,
                    'rack_target_scope' => 'single',
                    'rack_id' => $rid,
                    'rack_name' => (string) ($rack['name'] ?? ''),
                    'rack_location' => (string) ($rack['location'] ?? ''),
                    'rack_barcode_value' => (string) ($rack['barcode_value'] ?? ''),
                    'rack_type' => (string) ($rack['rack_type'] ?? 'storage'),

                    // Mode baru
                    'assignment_type' => 'role',
                    'assignment_strategy' => $assignmentStrategy,
                    'assigned_waiter_id' => null,
                    'assigned_waiter_role' => null,
                    'selected_waiter_ids' => $waiterIds,

                    // Schedule (mengikuti shift waiter saat task di-generate)
                    'schedule_mode' => 'shift_relative',
                    'schedule_time' => $scheduleTime,
                    'time_limit_minutes' => $timeLimitMinutes,
                    'deadline_mode' => 'before_shift_end',
                    'deadline_before_end_minutes' => 0,
                    'shift_offset_minutes' => 0,

                    // Recurrence
                    'recurrence_type' => $recurrenceType,
                    'weekly_day' => $weeklyDay,
                    'interval_days' => $intervalDays,
                    'recurrence_anchor_date' => $anchorDate,

                    // Disable rolling lama
                    'rolling_enabled' => false,
                    'rolling_period' => 'daily',
                    'rolling_waiter_ids' => [],
                    'rolling_anchor_date' => '',

                    'target_shift_id' => '',

                    // Flags simple-lowest-load (digunakan saat strategy = simple_lowest_load)
                    'simple_lowest_load_enabled' => $assignmentStrategy === 'simple_lowest_load',
                    'skip_when_no_eligible_waiter' => true,
                    'daily_cap_mode' => 'shift_aware',
                    'allow_note' => $allowNote,
                    'enable_empty_product_report' => $enableEmptyProductReport,
                    // Custom daily cap overrides (null = fallback ke hardcoded: full=2, partial=1)
                    'full_shift_daily_cap' => $fullShiftDailyCap,
                    'partial_shift_daily_cap' => $partialShiftDailyCap,
                ]);
                $createdTemplates++;
            } catch (\Throwable $e) {
                report($e);
                $errors[] = ($rack['name'] ?? $rid).': '.$e->getMessage();
            }
        }

        if ($createdTemplates === 0) {
            return back()
                ->withErrors(['rack_ids' => 'Gagal membuat template: '.implode('; ', $errors)])
                ->withInput();
        }

        $this->firebase->logAuditAction('create', 'rack_check_template_simple', null, [
            'rack_count' => count($rackIds),
            'waiter_count' => count($waiterIds),
            'recurrence_type' => $recurrenceType,
            'created' => $createdTemplates,
        ]);

        $message = "Template cek rak otomatis berhasil dibuat ({$createdTemplates} rak).";
        if (! empty($errors)) {
            $message .= ' Catatan: '.count($errors).' rak gagal diproses.';
        }

        return redirect()
            ->route('admin.rack_check.templates.index')
            ->with('success', $message);
    }

    /**
     * POST /admin/rack-check/templates/{id}/generate
     * Force-generate task untuk template ini hari ini.
     */
    public function generateNow($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        if (($template['task_type'] ?? '') !== 'rack_check') {
            return back()->with('error', 'Template ini bukan Cek Rak.');
        }
        $strategy = (string) ($template['assignment_strategy'] ?? '');
        if (! in_array($strategy, ['simple_lowest_load', 'round_robin_simple'], true)) {
            return back()->with('error', 'Template ini tidak mendukung generate manual.');
        }
        if (empty($template['is_active'])) {
            return back()->with('error', 'Template tidak aktif. Aktifkan dulu sebelum generate.');
        }

        $today = date('Y-m-d');
        try {
            if ($strategy === 'round_robin_simple') {
                $result = $this->firebase->processRoundRobinSimpleTemplate($template, $today, false, true);
            } else {
                $result = $this->firebase->processSimpleLowestLoadTemplate($template, $today, false, true);
            }
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal generate: '.$e->getMessage());
        }

        $generated = (int) ($result['generated_count'] ?? 0);
        $status = (string) ($result['status'] ?? '');
        $rackName = (string) ($template['rack_name'] ?? 'rak ini');

        if ($generated > 0) {
            $waiterId = (string) ($result['assigned_waiter_id'] ?? '');
            $waiterName = '';
            if ($waiterId !== '') {
                $waiter = $this->firebase->getWaiterById($waiterId);
                $waiterName = (string) ($waiter['name'] ?? '');
            }
            $msg = "✓ Task berhasil dibuat untuk {$rackName}";
            if ($waiterName !== '') {
                $msg .= " → ditugaskan ke {$waiterName}";
            }
            return back()->with('success', $msg.'.');
        }

        if ($status === 'skipped_no_eligible_waiter') {
            return back()->with('error', "Tidak ada petugas eligible untuk {$rackName} hari ini (libur/hit cap).");
        }
        if ($status === 'lock_exists') {
            $lockReason = (string) ($result['reason'] ?? '');
            if ($lockReason === 'generated') {
                return back()->with('success', "Task untuk {$rackName} sudah dibuat sebelumnya hari ini.");
            }
            if ($lockReason === 'cancelled_by_admin') {
                return back()->with('error', "Task untuk {$rackName} hari ini dibatalkan admin. Tidak akan diregenerasi otomatis.");
            }
            return back()->with('error', "Generate diblokir: status lock = {$lockReason}.");
        }
        if ($status === 'create_failed') {
            return back()->with('error', "Gagal create task: ".(string) ($result['reason'] ?? 'unknown'));
        }

        return back()->with('error', "Tidak ada task yang dibuat (status: {$status}).");
    }

    /**
     * GET /admin/rack-check/templates/{id}/preview?date=YYYY-MM-DD
     * Dry-run pembagian. Tidak buat task.
     */
    public function preview(Request $request, $id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            return response()->json(['success' => false, 'message' => 'Template tidak ditemukan.'], 404);
        }
        if (($template['task_type'] ?? '') !== 'rack_check'
            || ! in_array((string) ($template['assignment_strategy'] ?? ''), ['simple_lowest_load', 'round_robin_simple'], true)) {
            return response()->json(['success' => false, 'message' => 'Template ini bukan mode wizard cek rak otomatis.'], 422);
        }

        $date = (string) $request->query('date', date('Y-m-d', strtotime('+1 day')));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        try {
            $strategy = (string) ($template['assignment_strategy'] ?? 'simple_lowest_load');
            if ($strategy === 'round_robin_simple') {
                $result = $this->firebase->previewRoundRobinSimpleTemplate($template, $date);
            } else {
                $result = $this->firebase->previewSimpleLowestLoadTemplate($template, $date);
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Gagal preview: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'template_id' => (string) ($template['id'] ?? ''),
            'rack_name' => (string) ($template['rack_name'] ?? ''),
            'preview' => $result,
        ]);
    }

    /**
     * POST /admin/rack-check/templates/{id}/toggle
     * Aktifkan/nonaktifkan template.
     */
    public function toggle($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        $newActive = ! ($template['is_active'] ?? true);
        $this->firebase->updateRecurringWaiterTaskTemplate($id, [
            'title' => (string) ($template['title'] ?? 'Rak'),
            'description' => (string) ($template['description'] ?? ''),
            'priority' => (string) ($template['priority'] ?? 'normal'),
            'schedule_time' => (string) ($template['schedule_time'] ?? '09:00'),
            'time_limit_minutes' => (int) ($template['time_limit_minutes'] ?? 60),
            'recurrence_type' => (string) ($template['recurrence_type'] ?? 'daily'),
            'weekly_day' => $template['weekly_day'] ?? null,
            'interval_days' => $template['interval_days'] ?? null,
            'is_active' => $newActive,
        ]);

        return back()->with('success', $newActive
            ? 'Template diaktifkan kembali.'
            : 'Template dinonaktifkan. Cron tidak akan generate task baru.');
    }

    /**
     * DELETE /admin/rack-check/templates/{id}
     * Hapus template + cancel task pending hari ini.
     */
    public function destroy($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        $result = $this->firebase->deleteRecurringWaiterTaskTemplate($id);
        $cancelled = is_array($result) ? (int) ($result['cancelled_tasks'] ?? 0) : 0;

        $msg = 'Template cek rak otomatis berhasil dihapus.';
        if ($cancelled > 0) {
            $msg .= ' '.$cancelled.' task pending dibatalkan otomatis.';
        }

        return redirect()
            ->route('admin.rack_check.templates.index')
            ->with('success', $msg);
    }
}
