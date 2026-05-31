<?php

namespace App\Http\Controllers;

use App\Services\KasirScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WaiterKasirJadwalController extends Controller
{
    protected KasirScheduleService $kasir;

    public function __construct(KasirScheduleService $kasir)
    {
        $this->kasir = $kasir;
    }

    public function index(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        $weekStart = $request->query('week_start');
        if (! $weekStart) {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }

        $isKasirOrBackup = false;
        $schedule = null;

        try {
            $isKasirOrBackup = $this->kasir->isKasirOrBackup($waiterId);
            if ($isKasirOrBackup) {
                $schedule = $this->kasir->getWaiterWeekSchedule($waiterId, $weekStart);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $prevWeek = Carbon::parse($weekStart)->subWeek()->toDateString();
        $nextWeek = Carbon::parse($weekStart)->addWeek()->toDateString();
        $currentWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        return view('waiter.kasir_jadwal', [
            'waiterId' => $waiterId,
            'waiterName' => $waiterName,
            'isKasirOrBackup' => $isKasirOrBackup,
            'schedule' => $schedule,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'currentWeek' => $currentWeek,
            'isCurrentWeek' => $weekStart === $currentWeek,
        ]);
    }
}
