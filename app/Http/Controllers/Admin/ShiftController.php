<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Display shifts list with CRUD modal.
     */
    public function index()
    {
        $shifts = $this->firebase->getShifts();

        return view('admin.shifts.index', compact('shifts'));
    }

    /**
     * Store a new shift.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'clock_in_time' => 'required|date_format:H:i',
            'clock_out_time' => 'required|date_format:H:i',
            'late_tolerance_minutes' => 'required|integer|min:0|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $shift = $this->firebase->createShift([
                'name' => $validated['name'],
                'clock_in_time' => $validated['clock_in_time'],
                'clock_out_time' => $validated['clock_out_time'],
                'late_tolerance_minutes' => $validated['late_tolerance_minutes'],
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Shift berhasil ditambahkan.',
                    'shift' => $shift,
                ]);
            }

            return redirect()->route('admin.shifts.index')
                ->with('success', 'Shift berhasil ditambahkan.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambahkan shift.',
                ], 422);
            }

            return redirect()->route('admin.shifts.index')
                ->with('error', 'Gagal menambahkan shift.');
        }
    }

    /**
     * Update an existing shift.
     */
    public function update(Request $request, $id)
    {
        $shift = $this->firebase->getShiftById($id);
        if (! $shift) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'clock_in_time' => 'required|date_format:H:i',
            'clock_out_time' => 'required|date_format:H:i',
            'late_tolerance_minutes' => 'required|integer|min:0|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $this->firebase->updateShift($id, [
                'name' => $validated['name'],
                'clock_in_time' => $validated['clock_in_time'],
                'clock_out_time' => $validated['clock_out_time'],
                'late_tolerance_minutes' => $validated['late_tolerance_minutes'],
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : false,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Shift berhasil diperbarui.',
                ]);
            }

            return redirect()->route('admin.shifts.index')
                ->with('success', 'Shift berhasil diperbarui.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui shift.',
                ], 422);
            }

            return redirect()->route('admin.shifts.index')
                ->with('error', 'Gagal memperbarui shift.');
        }
    }

    /**
     * Delete a shift.
     */
    public function destroy($id)
    {
        $shift = $this->firebase->getShiftById($id);
        if (! $shift) {
            abort(404);
        }

        try {
            $this->firebase->deleteShift($id);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Shift berhasil dihapus.',
                ]);
            }

            return redirect()->route('admin.shifts.index')
                ->with('success', 'Shift berhasil dihapus.');
        } catch (\Throwable $e) {
            report($e);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus shift.',
                ], 422);
            }

            return redirect()->route('admin.shifts.index')
                ->with('error', 'Gagal menghapus shift.');
        }
    }

    /**
     * Show schedule template grid (permanent, no week picker).
     */
    public function schedules(Request $request)
    {
        $waiters = $this->firebase->getAllowedEmails();
        $shifts = $this->firebase->getActiveShifts();
        $template = $this->firebase->getScheduleTemplate();

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $scheduleMap = [];
        foreach ($waiters as $waiter) {
            $wId = $waiter['id'];
            $waiterTemplate = $template[$wId] ?? [];
            foreach ($days as $day) {
                $scheduleMap[$wId][$day] = $waiterTemplate[$day] ?? 'off';
            }
        }

        return view('admin.shifts.schedules', compact('waiters', 'shifts', 'scheduleMap'));
    }

    /**
     * Save schedule template (AJAX).
     */
    public function saveScheduleTemplate(Request $request)
    {
        $validated = $request->validate([
            'schedule' => 'required|array',
            'schedule.*' => 'required|array',
            'schedule.*.*' => 'required|string',
        ]);

        try {
            $this->firebase->saveScheduleTemplate($validated['schedule']);

            $this->firebase->logAuditAction('update', 'schedule_template', null, ['waiters_count' => count($validated['schedule'])]);

            return response()->json([
                'success' => true,
                'message' => 'Template jadwal berhasil disimpan. Berlaku untuk seterusnya.',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 422);
        }
    }
}
