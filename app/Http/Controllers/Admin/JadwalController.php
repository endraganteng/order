<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Services\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    protected ScheduleGeneratorService $generator;
    protected FirebaseService $firebase;

    public function __construct(ScheduleGeneratorService $generator, FirebaseService $firebase)
    {
        $this->generator = $generator;
        $this->firebase = $firebase;
    }

    /**
     * Default 3 employees from /allowed_emails by name match.
     * Spec: Anjar (Mas anjar), Rendy (randy), Bagas.
     */
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

        // Fallback ke nama spec saja kalau Firebase gagal
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
        $holderOverride = $request->query('holder_idx');
        $holderIdx = $holderOverride !== null ? (int) $holderOverride : null;

        $schedule = $this->generator->generate($weekStart, $employees, $holderIdx);

        // Compute prev/next week for navigation
        $prevWeek = Carbon::parse($schedule['week_start'])->subWeek()->toDateString();
        $nextWeek = Carbon::parse($schedule['week_start'])->addWeek()->toDateString();

        return view('admin.jadwal.index', [
            'schedule' => $schedule,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'currentWeek' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);
    }

    /**
     * AJAX endpoint: re-generate dengan parameter berbeda (override holder).
     */
    public function generate(Request $request)
    {
        $request->validate([
            'week_start' => 'required|date_format:Y-m-d',
            'holder_idx' => 'nullable|integer|in:0,1,2',
        ]);

        $employees = $this->loadDefaultEmployees();
        $schedule = $this->generator->generate(
            $request->week_start,
            $employees,
            $request->holder_idx !== null ? (int) $request->holder_idx : null
        );

        return response()->json([
            'success' => true,
            'schedule' => $schedule,
        ]);
    }
}
