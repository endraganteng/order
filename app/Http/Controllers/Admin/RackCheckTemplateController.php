<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

/**
 * CRUD template Cek Rak. Assignment petugas ditangani oleh
 * planning system (/admin/rack-check/planning) secara manual.
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
     * Daftar semua template rack_check.
     */
    public function index()
    {
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();

        $templates = array_values(array_filter($allTemplates, function ($tpl) {
            return ($tpl['task_type'] ?? '') === 'rack_check';
        }));

        // Normalize racks for each template
        foreach ($templates as &$tpl) {
            $tpl['_racks'] = $this->firebase->normalizeTemplateRacks($tpl);
        }
        unset($tpl);

        // Sort: aktif dulu, lalu by created_at DESC
        usort($templates, function ($a, $b) {
            $aActive = ($a['is_active'] ?? true) ? 0 : 1;
            $bActive = ($b['is_active'] ?? true) ? 0 : 1;
            if ($aActive !== $bActive) {
                return $aActive <=> $bActive;
            }
            return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
        });

        return view('admin.rack_check.templates.index', compact('templates'));
    }

    /**
     * GET /admin/rack-check/templates/create
     */
    public function create()
    {
        $racks = $this->firebase->getActiveRacks();

        // Active rack-check templates untuk warning duplikat rak
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRackMap = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (empty($tpl['is_active'])) {
                continue;
            }
            $tplRackIds = array_map(fn ($r) => (string) ($r['id'] ?? ''), $this->firebase->normalizeTemplateRacks($tpl));
            foreach ($tplRackIds as $rid) {
                if ($rid === '') continue;
                $lockedRackMap[$rid] = [
                    'template_id' => (string) ($tpl['id'] ?? ''),
                    'strategy' => (string) ($tpl['assignment_strategy'] ?? ''),
                    'rack_name' => (string) ($tpl['rack_name'] ?? $rid),
                ];
            }
        }

        return view('admin.rack_check.templates.create', compact('racks', 'lockedRackMap'));
    }

    /**
     * GET /admin/rack-check/templates/{id}/edit
     */
    public function edit($id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            abort(404, 'Template tidak ditemukan.');
        }

        if (($template['task_type'] ?? '') !== 'rack_check') {
            abort(403, 'Template ini bukan rack_check.');
        }

        $racks = $this->firebase->getActiveRacks();

        // Normalize racks from template (supports multi-rak and legacy)
        $templateRacks = $this->firebase->normalizeTemplateRacks($template);
        $templateRackIds = array_map(fn ($r) => (string) ($r['id'] ?? ''), $templateRacks);

        // Build lockedRackMap: rak yang sudah punya template aktif LAIN
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRackMap = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') continue;
            if (empty($tpl['is_active'])) continue;
            if (($tpl['id'] ?? '') === $id) continue; // skip self
            $tplRackIds = array_map(fn ($r) => (string) ($r['id'] ?? ''), $this->firebase->normalizeTemplateRacks($tpl));
            foreach ($tplRackIds as $tplRack) {
                if ($tplRack === '') continue;
                $lockedRackMap[$tplRack] = [
                    'template_id' => (string) ($tpl['id'] ?? ''),
                    'strategy' => (string) ($tpl['assignment_strategy'] ?? ''),
                    'rack_name' => (string) ($tpl['rack_name'] ?? $tplRack),
                ];
            }
        }

        return view('admin.rack_check.templates.create', compact('template', 'racks', 'lockedRackMap', 'templateRackIds'));
    }

    /**
     * PUT /admin/rack-check/templates/{id}
     */
    public function update(Request $request, $id)
    {
        $template = $this->firebase->getRecurringWaiterTaskTemplateById($id);
        if (! $template) {
            abort(404);
        }

        if (($template['task_type'] ?? '') !== 'rack_check') {
            abort(403);
        }

        $request->validate([
            'rack_ids' => ['required', 'array', 'min:1'],
            'rack_ids.*' => ['string'],
            'template_name' => ['nullable', 'string', 'max:100'],
            'recurrence_type' => ['required', 'in:daily,weekly,every_n_days'],
            'weekly_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'recurrence_anchor_date' => ['nullable', 'date_format:Y-m-d'],
            'requires_barcode_scan' => ['nullable'],
            'requires_photo_before' => ['nullable'],
            'requires_photo_proof' => ['nullable'],
            'allow_note' => ['nullable'],
            'enable_empty_product_report' => ['nullable'],
            'full_shift_daily_cap' => ['nullable', 'integer', 'min:0'],
            'partial_shift_daily_cap' => ['nullable', 'integer', 'min:0'],
        ]);

        // Rack IDs
        $rackIds = array_values(array_unique(array_filter(
            array_map(fn ($x) => trim((string) $x), (array) $request->input('rack_ids', [])),
            fn ($x) => $x !== ''
        )));

        if (empty($rackIds)) {
            return back()->withErrors(['rack_ids' => 'Pilih minimal satu rak.'])->withInput();
        }

        // Validate racks aktif
        $activeRacks = $this->firebase->getActiveRacks();
        $rackMap = [];
        foreach ($activeRacks as $r) {
            $rid = (string) ($r['id'] ?? '');
            if ($rid !== '') {
                $rackMap[$rid] = $r;
            }
        }

        $invalidRacks = array_values(array_filter($rackIds, fn ($rid) => ! isset($rackMap[$rid])));
        if (count($invalidRacks) > 0) {
            return back()->withErrors(['rack_ids' => 'Beberapa rak tidak valid atau nonaktif.'])->withInput();
        }

        // Anti-duplikat: cek rak yang sudah punya template aktif lain
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRacks = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') continue;
            if (empty($tpl['is_active'])) continue;
            if (($tpl['id'] ?? '') === $id) continue; // skip self
            $tplRackIds = array_map(fn ($r) => (string) ($r['id'] ?? ''), $this->firebase->normalizeTemplateRacks($tpl));
            foreach ($tplRackIds as $tplRack) {
                if ($tplRack !== '' && in_array($tplRack, $rackIds, true)) {
                    $lockedRacks[$tplRack] = (string) ($tpl['rack_name'] ?? $tplRack);
                }
            }
        }
        if (count($lockedRacks) > 0) {
            return back()
                ->withErrors(['rack_ids' => 'Rak berikut sudah punya template aktif lain: '.implode(', ', array_values($lockedRacks))])
                ->withInput();
        }

        // Build racks payload
        $racksPayload = [];
        foreach ($rackIds as $rid) {
            $rack = $rackMap[$rid];
            $racksPayload[] = [
                'id' => $rid,
                'name' => (string) ($rack['name'] ?? ''),
                'location' => (string) ($rack['location'] ?? ''),
                'barcode_value' => (string) ($rack['barcode_value'] ?? ''),
                'rack_type' => (string) ($rack['rack_type'] ?? 'storage'),
            ];
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

        // Template name
        $templateName = trim((string) $request->input('template_name', ''));
        if ($templateName === '') {
            if (count($racksPayload) === 1) {
                $templateName = $racksPayload[0]['name'] ?: 'Cek Rak';
            } else {
                $templateName = 'Cek '.count($racksPayload).' Rak';
            }
        }

        $this->firebase->updateRecurringWaiterTaskTemplate($id, [
            'title' => $templateName,
            'name' => $templateName,
            'description' => (string) ($template['description'] ?? ''),
            'priority' => 'normal',
            'assignment_strategy' => 'manual_planning',
            'simple_lowest_load_enabled' => false,
            'rolling_enabled' => false,
            'selected_waiter_ids' => [],
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
            // Multi-rak
            'racks' => $racksPayload,
            'rack_target_scope' => count($racksPayload) > 1 ? 'multi' : 'single',
            'rack_id' => $racksPayload[0]['id'] ?? '',
            'rack_name' => $racksPayload[0]['name'] ?? '',
            'rack_location' => $racksPayload[0]['location'] ?? '',
            'rack_barcode_value' => $racksPayload[0]['barcode_value'] ?? '',
            'rack_type' => $racksPayload[0]['rack_type'] ?? 'storage',
            'updated_at' => time(),
        ]);

        $this->firebase->logAuditAction('update', 'rack_check_template_simple', $id, [
            'rack_count' => count($rackIds),
            'recurrence_type' => $recurrenceType,
            'strategy' => 'manual_planning',
        ]);

        return redirect()->route('admin.rack_check.templates.index')
            ->with('success', 'Template cek rak berhasil diperbarui.');
    }

    /**
     * POST /admin/rack-check/templates
     */
    public function store(Request $request)
    {
        $request->validate([
            'rack_ids' => ['required', 'array', 'min:1'],
            'rack_ids.*' => ['string'],
            'template_name' => ['nullable', 'string', 'max:100'],
            'recurrence_type' => ['required', 'in:daily,weekly,every_n_days'],
            'weekly_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'recurrence_anchor_date' => ['nullable', 'date_format:Y-m-d'],
            'requires_barcode_scan' => ['nullable'],
            'requires_photo_before' => ['nullable'],
            'requires_photo_proof' => ['nullable'],
            'allow_note' => ['nullable'],
            'enable_empty_product_report' => ['nullable'],
            'full_shift_daily_cap' => ['nullable', 'integer', 'min:0'],
            'partial_shift_daily_cap' => ['nullable', 'integer', 'min:0'],
        ]);

        $rackIds = array_values(array_unique(array_filter(
            array_map(fn ($x) => trim((string) $x), (array) $request->input('rack_ids', [])),
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

        // Anti-duplikat: cek rak yang sudah punya template aktif
        $allTemplates = $this->firebase->getRecurringWaiterTaskTemplates();
        $lockedRacks = [];
        foreach ($allTemplates as $tpl) {
            if (($tpl['task_type'] ?? '') !== 'rack_check') continue;
            if (empty($tpl['is_active'])) continue;
            $tplRackIds = array_map(fn ($r) => (string) ($r['id'] ?? ''), $this->firebase->normalizeTemplateRacks($tpl));
            foreach ($tplRackIds as $tplRack) {
                if ($tplRack !== '' && in_array($tplRack, $rackIds, true)) {
                    $lockedRacks[$tplRack] = (string) ($tpl['rack_name'] ?? $tplRack);
                }
            }
        }
        if (count($lockedRacks) > 0) {
            return back()
                ->withErrors(['rack_ids' => 'Rak berikut sudah punya template aktif: '
                    .implode(', ', array_values($lockedRacks))
                    .'. Hapus/nonaktifkan template lama dulu.'])
                ->withInput();
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

        $fullShiftCapRaw = $request->input('full_shift_daily_cap');
        $fullShiftDailyCap = ($fullShiftCapRaw !== null && $fullShiftCapRaw !== '')
            ? max(0, (int) $fullShiftCapRaw)
            : null;
        $partialShiftCapRaw = $request->input('partial_shift_daily_cap');
        $partialShiftDailyCap = ($partialShiftCapRaw !== null && $partialShiftCapRaw !== '')
            ? max(0, (int) $partialShiftCapRaw)
            : null;

        // Build racks payload
        $racksPayload = [];
        foreach ($rackIds as $rid) {
            $rack = $rackMap[$rid];
            $racksPayload[] = [
                'id' => $rid,
                'name' => (string) ($rack['name'] ?? ''),
                'location' => (string) ($rack['location'] ?? ''),
                'barcode_value' => (string) ($rack['barcode_value'] ?? ''),
                'rack_type' => (string) ($rack['rack_type'] ?? 'storage'),
            ];
        }

        // Template name (auto-generate jika kosong)
        $templateName = trim((string) $request->input('template_name', ''));
        if ($templateName === '') {
            if (count($racksPayload) === 1) {
                $templateName = $racksPayload[0]['name'] ?: 'Cek Rak';
            } else {
                $templateName = 'Cek '.count($racksPayload).' Rak';
            }
        }

        try {
            $this->firebase->createRecurringWaiterTaskTemplate([
                'title' => $templateName,
                'name' => $templateName,
                'description' => '',
                'priority' => 'normal',
                'assigned_by' => 'Supervisor',
                'task_type' => 'rack_check',
                'category_id' => null,
                'category_name' => null,
                'requires_barcode_scan' => (bool) $request->boolean('requires_barcode_scan', true),
                'requires_photo_proof' => (bool) $request->boolean('requires_photo_proof', true),
                'requires_photo_before' => (bool) $request->boolean('requires_photo_before', true),

                // Multi-rak
                'racks' => $racksPayload,
                'rack_target_scope' => count($racksPayload) > 1 ? 'multi' : 'single',
                // Backward-compat flat fields (first rack)
                'rack_id' => $racksPayload[0]['id'] ?? '',
                'rack_name' => $racksPayload[0]['name'] ?? '',
                'rack_location' => $racksPayload[0]['location'] ?? '',
                'rack_barcode_value' => $racksPayload[0]['barcode_value'] ?? '',
                'rack_type' => $racksPayload[0]['rack_type'] ?? 'storage',

                // Manual planning — no auto-assignment
                'assignment_type' => 'role',
                'assignment_strategy' => 'manual_planning',
                'assigned_waiter_id' => null,
                'assigned_waiter_role' => null,
                'selected_waiter_ids' => [],
                'simple_lowest_load_enabled' => false,
                'rolling_enabled' => false,

                // Schedule
                'schedule_mode' => 'shift_relative',
                'schedule_time' => '08:00',
                'time_limit_minutes' => 480,
                'deadline_mode' => 'before_shift_end',
                'deadline_before_end_minutes' => 0,
                'shift_offset_minutes' => 0,

                // Recurrence
                'recurrence_type' => $recurrenceType,
                'weekly_day' => $weeklyDay,
                'interval_days' => $intervalDays,
                'recurrence_anchor_date' => $anchorDate,

                // Disable rolling
                'rolling_period' => 'daily',
                'rolling_waiter_ids' => [],
                'rolling_anchor_date' => '',

                'target_shift_id' => '',

                // Flags
                'skip_when_no_eligible_waiter' => true,
                'daily_cap_mode' => 'shift_aware',
                'allow_note' => (bool) $request->boolean('allow_note', true),
                'enable_empty_product_report' => (bool) $request->boolean('enable_empty_product_report', true),
                'full_shift_daily_cap' => $fullShiftDailyCap,
                'partial_shift_daily_cap' => $partialShiftDailyCap,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return back()
                ->withErrors(['rack_ids' => 'Gagal membuat template: '.$e->getMessage()])
                ->withInput();
        }

        $this->firebase->logAuditAction('create', 'rack_check_template_simple', null, [
            'rack_count' => count($rackIds),
            'recurrence_type' => $recurrenceType,
            'strategy' => 'manual_planning',
        ]);

        return redirect()
            ->route('admin.rack_check.templates.index')
            ->with('success', "Template cek rak berhasil dibuat (".count($rackIds)." rak).");
    }

    /**
     * POST /admin/rack-check/templates/{id}/toggle
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
