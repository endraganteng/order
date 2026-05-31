<?php

namespace App\Http\Controllers;

use App\Services\RetailScheduleService;
use App\Services\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WaiterJadwalController extends Controller
{
    protected RetailScheduleService $retail;
    protected ScheduleGeneratorService $generator;

    public function __construct(RetailScheduleService $retail, ScheduleGeneratorService $generator)
    {
        $this->retail = $retail;
        $this->generator = $generator;
    }

    public function index(Request $request)
    {
        $waiterId = (string) session('waiter_id');
        $waiterName = (string) session('waiter_name', 'Waiter');

        $weekStart = $request->query('week_start');
        if (! $weekStart) {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }

        $schedule = null;
        $isRetailEmployee = false;

        try {
            $isRetailEmployee = $this->retail->isRetailEmployee($waiterId);
            if ($isRetailEmployee) {
                $schedule = $this->retail->getWaiterWeekSchedule($waiterId, $weekStart, $this->generator);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $prevWeek = Carbon::parse($weekStart)->subWeek()->toDateString();
        $nextWeek = Carbon::parse($weekStart)->addWeek()->toDateString();
        $currentWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        return view('waiter.jadwal', [
            'waiterId' => $waiterId,
            'waiterName' => $waiterName,
            'isRetailEmployee' => $isRetailEmployee,
            'schedule' => $schedule,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'currentWeek' => $currentWeek,
            'isCurrentWeek' => $weekStart === $currentWeek,
        ]);
    }
}
