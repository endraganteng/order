<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\RackRepositoryInterface;
use App\Services\FirebaseService;
use App\Services\RackCheckTemplateService;
use Illuminate\Http\Request;

/**
 * CRUD template Cek Rak. Assignment petugas ditangani oleh
 * planning system (/admin/rack-check/planning) secara manual.
 */
class RackCheckTemplateController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase,
        protected RackCheckTemplateService $templateService,
        protected RackRepositoryInterface $racks,
    ) {
    }

    /**
     * GET /admin/rack-check/templates
     */
    public function index()
    {
        $templates = $this->templateService->all();

        // Normalize racks for view
        $templates->transform(function ($tpl) {
            $tpl->_racks = $tpl->normalizedRacks();
            return $tpl;
        });

        return view('admin.rack_check.templates.index', compact('templates'));
    }

    /**
     * GET /admin/rack-check/templates/create
     */
    public function create()
    {
        $racks = $this->racks->allActive();
        $lockedRackMap = $this->templateService->getLockedRackMap();

        return view('admin.rack_check.templates.create', compact('racks', 'lockedRackMap'));
    }

    /**
     * GET /admin/rack-check/templates/{id}/edit
     */
    public function edit($id)
    {
        $template = $this->templateService->find($id);
        if (! $template) {
            abort(404, 'Template tidak ditemukan.');
        }

        if ($template->task_type !== 'rack_check') {
            abort(403, 'Template ini bukan rack_check.');
        }

        $racks = $this->racks->allActive();
        $templateRackIds = array_map(fn ($r) => (string) ($r['id'] ?? ''), $template->normalizedRacks());
        $lockedRackMap = $this->templateService->getLockedRackMap((string) $template->id);

        return view('admin.rack_check.templates.create', compact('template', 'racks', 'lockedRackMap', 'templateRackIds'));
    }

    /**
     * PUT /admin/rack-check/templates/{id}
     */
    public function update(Request $request, $id)
    {
        $template = $this->templateService->find($id);
        if (! $template) {
            abort(404);
        }

        if ($template->task_type !== 'rack_check') {
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

        $rackIds = $this->parseRackIds($request);
        if (empty($rackIds)) {
            return back()->withErrors(['rack_ids' => 'Pilih minimal satu rak.'])->withInput();
        }

        // Validate racks aktif
        $activeRacks = $this->racks->allActive();
        $rackMap = $this->buildRackMap($activeRacks);

        $invalidRacks = array_values(array_filter($rackIds, fn ($rid) => ! isset($rackMap[$rid])));
        if (count($invalidRacks) > 0) {
            return back()->withErrors(['rack_ids' => 'Beberapa rak tidak valid atau nonaktif.'])->withInput();
        }

        // Anti-duplikat
        $lockedRackMap = $this->templateService->getLockedRackMap((string) $template->id);
        $conflictRacks = array_intersect_key($lockedRackMap, array_flip($rackIds));
        if (count($conflictRacks) > 0) {
            $names = array_map(fn ($r) => $r['rack_name'], $conflictRacks);
            return back()
                ->withErrors(['rack_ids' => 'Rak berikut sudah punya template aktif lain: ' . implode(', ', $names)])
                ->withInput();
        }

        $racksPayload = $this->buildRacksPayload($rackIds, $rackMap);
        $recurrence = $this->parseRecurrence($request, $template->recurrence_anchor_date ?? date('Y-m-d'));
        $caps = $this->parseCaps($request);
        $templateName = $this->resolveTemplateName($request, $racksPayload);

        $this->templateService->update($id, [
            'title' => $templateName,
            'name' => $templateName,
            'assignment_strategy' => 'manual_planning',
            'simple_lowest_load_enabled' => false,
            'rolling_enabled' => false,
            'selected_waiter_ids' => [],
            'requires_barcode_scan' => $request->boolean('requires_barcode_scan', true),
            'requires_photo_before' => $request->boolean('requires_photo_before', true),
            'requires_photo_proof' => $request->boolean('requires_photo_proof', true),
            'allow_note' => $request->boolean('allow_note', true),
            'enable_empty_product_report' => $request->boolean('enable_empty_product_report', true),
            'full_shift_daily_cap' => $caps['full'],
            'partial_shift_daily_cap' => $caps['partial'],
            'recurrence_type' => $recurrence['type'],
            'weekly_day' => $recurrence['weekly_day'],
            'interval_days' => $recurrence['interval_days'],
            'recurrence_anchor_date' => $recurrence['anchor_date'],
            'racks' => $racksPayload,
            'rack_target_scope' => count($racksPayload) > 1 ? 'multi' : 'single',
            'rack_id' => $racksPayload[0]['id'] ?? '',
            'rack_name' => $racksPayload[0]['name'] ?? '',
            'rack_location' => $racksPayload[0]['location'] ?? '',
            'rack_barcode_value' => $racksPayload[0]['barcode_value'] ?? '',
            'rack_type' => $racksPayload[0]['rack_type'] ?? 'storage',
        ]);

        $this->firebase->logAuditAction('update', 'rack_check_template_simple', $id, [
            'rack_count' => count($rackIds),
            'recurrence_type' => $recurrence['type'],
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

        $rackIds = $this->parseRackIds($request);

        // Validate racks aktif
        $activeRacks = $this->racks->allActive();
        $rackMap = $this->buildRackMap($activeRacks);

        $invalidRacks = array_values(array_filter($rackIds, fn ($id) => ! isset($rackMap[$id])));
        if (count($invalidRacks) > 0) {
            return back()
                ->withErrors(['rack_ids' => 'Beberapa rak tidak valid atau nonaktif. Refresh halaman dan pilih ulang.'])
                ->withInput();
        }

        // Anti-duplikat
        $lockedRackMap = $this->templateService->getLockedRackMap();
        $conflictRacks = array_intersect_key($lockedRackMap, array_flip($rackIds));
        if (count($conflictRacks) > 0) {
            $names = array_map(fn ($r) => $r['rack_name'], $conflictRacks);
            return back()
                ->withErrors(['rack_ids' => 'Rak berikut sudah punya template aktif: ' . implode(', ', $names) . '. Hapus/nonaktifkan template lama dulu.'])
                ->withInput();
        }

        $racksPayload = $this->buildRacksPayload($rackIds, $rackMap);
        $recurrence = $this->parseRecurrence($request, date('Y-m-d'));
        $caps = $this->parseCaps($request);
        $templateName = $this->resolveTemplateName($request, $racksPayload);

        try {
            $this->templateService->create([
                'title' => $templateName,
                'name' => $templateName,
                'description' => '',
                'priority' => 'normal',
                'assigned_by' => 'Supervisor',
                'assignment_type' => 'role',
                'assignment_strategy' => 'manual_planning',
                'assigned_waiter_id' => null,
                'assigned_waiter_role' => null,
                'selected_waiter_ids' => [],
                'simple_lowest_load_enabled' => false,
                'rolling_enabled' => false,
                'rolling_waiter_ids' => [],
                'rolling_period' => 'daily',
                'rolling_anchor_date' => '',
                'schedule_mode' => 'shift_relative',
                'schedule_time' => '08:00',
                'time_limit_minutes' => 480,
                'deadline_mode' => 'before_shift_end',
                'deadline_before_end_minutes' => 0,
                'shift_offset_minutes' => 0,
                'target_shift_id' => '',
                'requires_barcode_scan' => $request->boolean('requires_barcode_scan', true),
                'requires_photo_proof' => $request->boolean('requires_photo_proof', true),
                'requires_photo_before' => $request->boolean('requires_photo_before', true),
                'allow_note' => $request->boolean('allow_note', true),
                'enable_empty_product_report' => $request->boolean('enable_empty_product_report', true),
                'skip_when_no_eligible_waiter' => true,
                'daily_cap_mode' => 'shift_aware',
                'full_shift_daily_cap' => $caps['full'],
                'partial_shift_daily_cap' => $caps['partial'],
                'recurrence_type' => $recurrence['type'],
                'weekly_day' => $recurrence['weekly_day'],
                'interval_days' => $recurrence['interval_days'],
                'recurrence_anchor_date' => $recurrence['anchor_date'],
                'racks' => $racksPayload,
                'rack_target_scope' => count($racksPayload) > 1 ? 'multi' : 'single',
                'rack_id' => $racksPayload[0]['id'] ?? '',
                'rack_name' => $racksPayload[0]['name'] ?? '',
                'rack_location' => $racksPayload[0]['location'] ?? '',
                'rack_barcode_value' => $racksPayload[0]['barcode_value'] ?? '',
                'rack_type' => $racksPayload[0]['rack_type'] ?? 'storage',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return back()
                ->withErrors(['rack_ids' => 'Gagal membuat template: ' . $e->getMessage()])
                ->withInput();
        }

        $this->firebase->logAuditAction('create', 'rack_check_template_simple', null, [
            'rack_count' => count($rackIds),
            'recurrence_type' => $recurrence['type'],
            'strategy' => 'manual_planning',
        ]);

        return redirect()
            ->route('admin.rack_check.templates.index')
            ->with('success', "Template cek rak berhasil dibuat (" . count($rackIds) . " rak).");
    }

    /**
     * POST /admin/rack-check/templates/{id}/toggle
     */
    public function toggle($id)
    {
        $template = $this->templateService->find($id);
        if (! $template) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        $this->templateService->toggle($id);

        $newActive = ! $template->is_active;

        return back()->with('success', $newActive
            ? 'Template diaktifkan kembali.'
            : 'Template dinonaktifkan. Cron tidak akan generate task baru.');
    }

    /**
     * DELETE /admin/rack-check/templates/{id}
     */
    public function destroy($id)
    {
        $template = $this->templateService->find($id);
        if (! $template) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        $this->templateService->delete($id);

        return redirect()
            ->route('admin.rack_check.templates.index')
            ->with('success', 'Template cek rak berhasil dihapus.');
    }

    // ─── Helpers ─────────────────────────────────────────────

    protected function parseRackIds(Request $request): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($x) => trim((string) $x), (array) $request->input('rack_ids', [])),
            fn ($x) => $x !== ''
        )));
    }

    protected function buildRackMap(array $racks): array
    {
        $map = [];
        foreach ($racks as $r) {
            $rid = (string) ($r['id'] ?? '');
            if ($rid !== '') {
                $map[$rid] = $r;
            }
        }
        return $map;
    }

    protected function buildRacksPayload(array $rackIds, array $rackMap): array
    {
        $payload = [];
        foreach ($rackIds as $rid) {
            $rack = $rackMap[$rid];
            $payload[] = [
                'id' => $rid,
                'name' => (string) ($rack['name'] ?? ''),
                'location' => (string) ($rack['location'] ?? ''),
                'barcode_value' => (string) ($rack['barcode_value'] ?? ''),
                'rack_type' => (string) ($rack['rack_type'] ?? 'storage'),
            ];
        }
        return $payload;
    }

    protected function parseRecurrence(Request $request, string $defaultAnchor): array
    {
        $type = (string) $request->input('recurrence_type', 'daily');
        return [
            'type' => $type,
            'weekly_day' => $type === 'weekly' ? max(1, min(7, (int) $request->input('weekly_day', 1))) : null,
            'interval_days' => $type === 'every_n_days' ? max(1, (int) $request->input('interval_days', 2)) : null,
            'anchor_date' => (string) $request->input('recurrence_anchor_date', $defaultAnchor),
        ];
    }

    protected function parseCaps(Request $request): array
    {
        $fullRaw = $request->input('full_shift_daily_cap');
        $partialRaw = $request->input('partial_shift_daily_cap');
        return [
            'full' => ($fullRaw !== null && $fullRaw !== '') ? max(0, (int) $fullRaw) : null,
            'partial' => ($partialRaw !== null && $partialRaw !== '') ? max(0, (int) $partialRaw) : null,
        ];
    }

    protected function resolveTemplateName(Request $request, array $racksPayload): string
    {
        $name = trim((string) $request->input('template_name', ''));
        if ($name !== '') {
            return $name;
        }

        if (count($racksPayload) === 1) {
            return $racksPayload[0]['name'] ?: 'Cek Rak';
        }

        return 'Cek ' . count($racksPayload) . ' Rak';
    }
}
