<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KasirScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KasirJadwalController extends Controller
{
    protected KasirScheduleService $kasir;

    public function __construct(KasirScheduleService $kasir)
    {
        $this->kasir = $kasir;
    }

    public function index(Request $request)
    {
        $weekStart = $request->query('week_start');
        if (! $weekStart) {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }
        $weekStartCarbon = Carbon::parse($weekStart);
        if (! $weekStartCarbon->isMonday()) {
            $weekStartCarbon = $weekStartCarbon->startOfWeek(Carbon::MONDAY);
        }
        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        $schedule = null;
        $hasWeekOverride = false;
        $error = null;
        $preferences = [];

        try {
            $preferences = $this->kasir->getPreferences();
            $weekOverride = $this->kasir->getWeekOverride($weekIso);
            $hasWeekOverride = $weekOverride !== null;
            $override = ($weekOverride && ! empty($weekOverride['cells'])) ? ['cells' => $weekOverride['cells']] : null;

            // No per-week override. Try apply global default template.
            if (! $override) {
                $snap = app(\App\Services\FirebaseService::class)->getDatabase()->getReference('templates/kasir_default_week_schedule')->getSnapshot();
                if ($snap->exists()) {
                    $tpl = (array) $snap->getValue();
                    if (! empty($tpl['cells'])) {
                        $override = ['cells' => $tpl['cells']];
                    }
                }
            }

            $schedule = $this->kasir->generate($weekStartCarbon->toDateString(), $override);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            report($e);
        }

        $prevWeek = $weekStartCarbon->copy()->subWeek()->toDateString();
        $nextWeek = $weekStartCarbon->copy()->addWeek()->toDateString();

        return view('admin.kasir.index', [
            'schedule' => $schedule,
            'hasWeekOverride' => $hasWeekOverride,
            'preferences' => $preferences,
            'error' => $error,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'currentWeek' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'weekIso' => $weekIso,
            'weekStart' => $weekStartCarbon->toDateString(),
            'allWaiters' => $this->kasir->loadAllActiveWaiters(),
        ]);
    }

    public function savePreferences(Request $request)
    {
        $request->validate([
            'kasir_ids' => 'required|array|size:2',
            'kasir_ids.*' => 'required|string',
            'backup_id' => 'nullable|string',
            'libur_days' => 'nullable|array',
            'libur_days.*' => 'nullable|string|in:monday,tuesday',
        ]);

        // Validate 2 different kasirs
        $kasirIds = $request->input('kasir_ids', []);
        if (count(array_unique(array_filter($kasirIds))) !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih 2 kasir yang berbeda.',
                'errors' => ['Kasir 1 dan Kasir 2 tidak boleh sama atau kosong.'],
            ], 422);
        }

        $prefs = [
            'kasir_ids' => $kasirIds,
            'backup_id' => $request->input('backup_id') ?: null,
            'libur_days' => $request->input('libur_days', []),
        ];

        // Build kasir array for validation
        $kasirs = [];
        foreach ($kasirIds as $wid) {
            $waiter = app(\App\Services\FirebaseService::class)->getWaiterById($wid);
            if (! $waiter) {
                return response()->json([
                    'success' => false,
                    'message' => "Waiter ID $wid tidak ditemukan.",
                ], 422);
            }
            $kasirs[] = ['id' => $waiter['id'], 'name' => $waiter['name']];
        }

        $validation = $this->kasir->validatePreferences($kasirs, $prefs);
        if (! $validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Preferensi tidak valid.',
                'errors' => $validation['errors'],
            ], 422);
        }

        $this->kasir->savePreferences($prefs);

        return response()->json([
            'success' => true,
            'message' => 'Preferensi tersimpan.',
        ]);
    }

    public function resetWeek(Request $request)
    {
        $request->validate(['week_iso' => 'required|string|regex:/^\d{4}-W\d{2}$/']);
        $this->kasir->deleteWeekSchedule($request->week_iso);

        return response()->json([
            'success' => true,
            'message' => 'Override minggu '.$request->week_iso.' dihapus.',
        ]);
    }

    public function setDefault(Request $request)
    {
        $request->validate([
            'week_iso' => 'required|string|regex:/^\d{4}-W\d{2}$/',
            'week_start' => 'required|date_format:Y-m-d',
            'cells' => 'required|array',
        ]);

        // Generate untuk resolve libur_days
        $schedule = $this->kasir->generate($request->week_start, ['cells' => $request->cells]);

        $payload = [
            'week_iso' => $request->week_iso,
            'week_start' => $request->week_start,
            'cells' => $request->cells,
            'libur_days_used' => $schedule['libur_days'] ?? [],
            'saved_by' => session('admin_email', 'admin'),
            '_v' => 1,
            'saved_at' => time(),
        ];

        try {
            app(\App\Services\FirebaseService::class)->getDatabase()->getReference('templates/kasir_default_week_schedule_backup/'.time())->set($payload);
            app(\App\Services\FirebaseService::class)->getDatabase()->getReference('templates/kasir_default_week_schedule')->set($payload);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan template: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jadwal minggu '.$request->week_iso.' disimpan sebagai default (kasir).',
        ]);
    }

    /**
     * Save manual override per-cell untuk minggu spesifik.
     * Cells format: { day_key => { employee_name => shift } }
     * Valid shifts: SHIFT_1, SHIFT_2, LIBUR
     */
    public function saveWeek(Request $request)
    {
        $request->validate([
            'week_iso' => 'required|string|regex:/^\d{4}-W\d{2}$/',
            'week_start' => 'required|date_format:Y-m-d',
            'cells' => 'required|array',
            'cells.*' => 'array',
            'cells.*.*' => 'string|in:SHIFT_1,SHIFT_2,LIBUR',
        ]);

        $weekStart = $request->input('week_start');
        $cells = $request->input('cells', []);

        // Validate hasil generate dengan override sebelum save
        try {
            $schedule = $this->kasir->generate($weekStart, ['cells' => $cells]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate: '.$e->getMessage(),
            ], 422);
        }

        if (! $schedule['validation']['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak valid: '.implode('; ', $schedule['validation']['errors']),
                'errors' => $schedule['validation']['errors'],
            ], 422);
        }

        $this->kasir->saveWeekSchedule($request->input('week_iso'), [
            'cells' => $cells,
            'libur_days_used' => $schedule['libur_days'] ?? [],
            'saved_by' => session('admin_name') ?? session('admin_email') ?? 'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal minggu '.$request->input('week_iso').' tersimpan.',
        ]);
    }
}
