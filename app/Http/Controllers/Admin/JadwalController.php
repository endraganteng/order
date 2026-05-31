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
        // 1. Coba dari preferences (waiter_ids tersimpan)
        try {
            $prefs = $this->retail->getPreferences();
            $employeeIds = $prefs['employees'] ?? [];
            if (count($employeeIds) === 3) {
                $resolved = [];
                foreach ($employeeIds as $wid) {
                    if (! $wid) {
                        continue;
                    }
                    $waiter = $this->firebase->getWaiterById($wid);
                    if ($waiter) {
                        $resolved[] = [
                            'id' => $waiter['id'] ?? $wid,
                            'name' => $waiter['name'] ?? '?',
                            'firebase_name' => $waiter['name'] ?? null,
                            'role' => $waiter['waiter_role'] ?? null,
                        ];
                    }
                }
                if (count($resolved) === 3) {
                    return $resolved;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 2. Fallback ke name-match (Anjar/Rendy/Bagas)
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
                            'name' => $w['name'] ?? $t['display'],
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

    /**
     * Load all active waiters untuk dropdown selection.
     */
    private function loadAllActiveWaiters(): array
    {
        try {
            $waiters = $this->firebase->getAllowedEmails();
            $active = [];
            foreach ($waiters as $w) {
                if (! ($w['is_active'] ?? false)) {
                    continue;
                }
                $active[] = [
                    'id' => $w['id'] ?? null,
                    'name' => $w['name'] ?? '?',
                    'role' => $w['waiter_role'] ?? null,
                ];
            }

            // Sort by name
            usort($active, fn ($a, $b) => strcmp(strtolower($a['name']), strtolower($b['name'])));

            return $active;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
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
            'shift_modes' => $prefs['shift_modes'] ?? null,
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

        // Resolve mapping status untuk indicator
        $mappingStatus = [];
        foreach ($employees as $emp) {
            $mappingStatus[] = [
                'name' => $emp['name'],
                'firebase_name' => $emp['firebase_name'] ?? null,
                'firebase_id' => $emp['id'] ?? null,
                'role' => $emp['role'] ?? null,
                'matched' => ! empty($emp['id']),
            ];
        }

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
            'allWaiters' => $this->loadAllActiveWaiters(),
            'mappingStatus' => $mappingStatus,
        ]);
    }

    public function savePreferences(Request $request)
    {
        $request->validate([
            'libur_days' => 'required|array',
            'libur_days.*' => 'required|string|in:monday,tuesday,wednesday,thursday,friday',
            'holder_mode' => 'required|string|in:auto,locked',
            'holder_name' => 'nullable|string',
            'employees' => 'nullable|array|size:3',
            'employees.*' => 'nullable|string',
            'shift_modes' => 'nullable|array',
            'shift_modes.*' => 'nullable|string|in:default,prefer_full,prefer_short',
        ]);

        // Validate 3 different employees
        $newEmployeeIds = $request->input('employees', []);
        if (count($newEmployeeIds) === 3) {
            $unique = array_unique(array_filter($newEmployeeIds));
            if (count($unique) !== 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih 3 karyawan yang berbeda.',
                    'errors' => ['Ada karyawan yang dipilih lebih dari sekali atau ada slot kosong.'],
                ], 422);
            }
        }

        // Resolve employees from selected waiter_ids
        $resolvedEmployees = [];
        if (count($newEmployeeIds) === 3) {
            foreach ($newEmployeeIds as $wid) {
                $waiter = $this->firebase->getWaiterById($wid);
                if (! $waiter) {
                    return response()->json([
                        'success' => false,
                        'message' => "Waiter ID $wid tidak ditemukan.",
                    ], 422);
                }
                $resolvedEmployees[] = [
                    'id' => $waiter['id'] ?? $wid,
                    'name' => $waiter['name'] ?? '?',
                    'firebase_name' => $waiter['name'] ?? null,
                    'role' => $waiter['waiter_role'] ?? null,
                ];
            }
        } else {
            $resolvedEmployees = $this->loadDefaultEmployees();
        }

        $prefs = [
            'libur_days' => $request->libur_days,
            'holder_mode' => $request->holder_mode,
            'holder_name' => $request->holder_mode === 'locked' ? $request->holder_name : null,
            'employees' => count($newEmployeeIds) === 3 ? $newEmployeeIds : null,
            'shift_modes' => $request->input('shift_modes', []),
        ];

        $validation = $this->generator->validatePreferences($resolvedEmployees, $prefs);
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
}
