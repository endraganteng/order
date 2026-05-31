<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Services\RetailScheduleService;
use App\Services\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    protected ScheduleGeneratorService $generator;
    protected RetailScheduleService $retail;
    protected FirebaseService $firebase;

    public function __construct(ScheduleGeneratorService $generator, RetailScheduleService $retail, FirebaseService $firebase)
    {
        $this->generator = $generator;
        $this->retail = $retail;
        $this->firebase = $firebase;
    }

    private function loadDefaultEmployees(): array
    {
        $targets = [
            ['key' => 'anjar', 'display' => 'Anjar'],
            ['key' => 'randy', 'display' => 'Rendy'],
            ['key' => 'bagas', 'display' => 'Bagas'],
        ];

        $resolved = array_fill(0, 3, null);

        try {
            $allWaiters = $this->firebase->getAllowedEmails();
            foreach ($allWaiters as $w) {
                $name = strtolower($w['name'] ?? '');
                foreach ($targets as $i => $t) {
                    if (str_contains($name, $t['key']) && $resolved[$i] === null) {
                        $resolved[$i] = [
                            'id' => $w['id'] ?? null,
                            'name' => $t['display'],
                            'firebase_name' => $w['name'] ?? null,
                            'role' => $w['waiter_role'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        foreach ($resolved as $i => $emp) {
            if ($emp === null) {
                $resolved[$i] = ['id' => null, 'name' => $targets[$i]['display'], 'firebase_name' => null, 'role' => null];
            }
        }

        return $resolved;
    }

    public function index(Request $request)
    {
        $weekStart = $request->query('week_start');
        if (! $weekStart) {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }

        $employees = $this->loadDefaultEmployees();
        $weekStartCarbon = Carbon::parse($weekStart);
        if (! $weekStartCarbon->isMonday()) {
            $weekStartCarbon = $weekStartCarbon->startOfWeek(Carbon::MONDAY);
        }
        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        $prefs = [];
        try {
            $prefs = $this->retail->getPreferences();
        } catch (\Throwable $e) {
            report($e);
        }

        $weekOverride = null;
        try {
            $weekOverride = $this->retail->getWeekOverride($weekIso);
        } catch (\Throwable $e) {
            report($e);
        }

        $genPrefs = [
            'libur_days' => $prefs['libur_days'] ?? null,
            'holder_name' => null,
        ];
        if (($prefs['holder_mode'] ?? 'auto') === 'locked' && ! empty($prefs['holder_name'])) {
            $genPrefs['holder_name'] = $prefs['holder_name'];
        }

        $override = null;
        if ($weekOverride && ! empty($weekOverride['cells'])) {
            $override = ['cells' => $weekOverride['cells']];
            if (! empty($weekOverride['holder_used'])) {
                $genPrefs['holder_name'] = $weekOverride['holder_used'];
            }
        }

        $schedule = $this->generator->generate($weekStart, $employees, $genPrefs, $override);

        $prevWeek = Carbon::parse($schedule['week_start'])->subWeek()->toDateString();
        $nextWeek = Carbon::parse($schedule['week_start'])->addWeek()->toDateString();

        return view('admin.jadwal.index', [
            'schedule' => $schedule,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'currentWeek' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'preferences' => $prefs,
            'hasWeekOverride' => $weekOverride !== null,
            'shiftCodes' => array_keys(ScheduleGeneratorService::SHIFTS),
            'weekdayKeys' => ScheduleGeneratorService::WEEKDAY_KEYS,
            'dayLabels' => ScheduleGeneratorService::DAY_LABELS,
        ]);
    }

    public function savePreferences(Request $request)
    {
        $request->validate([
            'libur_days' => 'required|array',
            'libur_days.*' => 'required|string|in:monday,tuesday,wednesday,thursday,friday',
            'holder_mode' => 'required|string|in:auto,locked',
            'holder_name' => 'nullable|string',
        ]);

        $employees = $this->loadDefaultEmployees();

        $prefs = [
            'libur_days' => $request->libur_days,
            'holder_mode' => $request->holder_mode,
            'holder_name' => $request->holder_mode === 'locked' ? $request->holder_name : null,
        ];

        $validation = $this->generator->validatePreferences($employees, $prefs);
        if (! $validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Preferensi tidak valid.',
                'errors' => $validation['errors'],
            ], 422);
        }

        $this->retail->savePreferences($prefs);

        return response()->json([
            'success' => true,
            'message' => 'Preferensi tersimpan. Akan berlaku untuk semua minggu ke depan.',
        ]);
    }

    public function saveWeek(Request $request)
    {
        $request->validate([
            'week_iso' => 'required|string|regex:/^\d{4}-W\d{2}$/',
            'week_start' => 'required|date_format:Y-m-d',
            'cells' => 'required|array',
            'holder_name' => 'nullable|string',
        ]);

        $employees = $this->loadDefaultEmployees();

        $override = ['cells' => $request->cells];
        $genPrefs = ['holder_name' => $request->holder_name];
        $schedule = $this->generator->generate($request->week_start, $employees, $genPrefs, $override);

        $this->retail->saveWeekSchedule($request->week_iso, [
            'cells' => $request->cells,
            'libur_days_used' => $schedule['libur_days'],
            'holder_used' => $schedule['holder_name'],
            'saved_by' => session('admin_email', 'admin'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal minggu '.$request->week_iso.' tersimpan.',
            'validation' => $schedule['validation'],
        ]);
    }

    public function resetWeek(Request $request)
    {
        $request->validate(['week_iso' => 'required|string|regex:/^\d{4}-W\d{2}$/']);
        $this->retail->deleteWeekSchedule($request->week_iso);

        return response()->json([
            'success' => true,
            'message' => 'Override minggu '.$request->week_iso.' dihapus.',
        ]);
    }

    public function applyAttendance(Request $request)
    {
        $request->validate(['week_start' => 'required|date_format:Y-m-d']);

        $employees = $this->loadDefaultEmployees();

        $missing = [];
        foreach ($employees as $e) {
            if (empty($e['id'])) {
                $missing[] = $e['name'];
            }
        }
        if (! empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan belum terdaftar di /allowed_emails: '.implode(', ', $missing),
            ], 422);
        }

        $weekStartCarbon = Carbon::parse($request->week_start);
        if (! $weekStartCarbon->isMonday()) {
            $weekStartCarbon = $weekStartCarbon->startOfWeek(Carbon::MONDAY);
        }
        $weekIso = $weekStartCarbon->isoFormat('GGGG-[W]WW');

        $prefs = $this->retail->getPreferences();
        $genPrefs = [
            'libur_days' => $prefs['libur_days'] ?? null,
            'holder_name' => ($prefs['holder_mode'] ?? 'auto') === 'locked' ? ($prefs['holder_name'] ?? null) : null,
        ];

        $weekOverride = $this->retail->getWeekOverride($weekIso);
        $override = null;
        if ($weekOverride && ! empty($weekOverride['cells'])) {
            $override = ['cells' => $weekOverride['cells']];
            if (! empty($weekOverride['holder_used'])) {
                $genPrefs['holder_name'] = $weekOverride['holder_used'];
            }
        }

        $schedule = $this->generator->generate($request->week_start, $employees, $genPrefs, $override);

        if (! $schedule['validation']['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal invalid, fix dulu:',
                'errors' => $schedule['validation']['errors'],
            ], 422);
        }

        $result = $this->retail->applyToAttendance($schedule['matrix']);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Berhasil — {$result['applied']} karyawan ter-update di schedule template. QR attendance & penalty otomatis aktif."
                : 'Apply gagal sebagian.',
            'applied' => $result['applied'],
            'errors' => $result['errors'],
        ]);
    }
}
