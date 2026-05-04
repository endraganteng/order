<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Daily attendance overview for a selected date.
     */
    public function index(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        $weekKey = date('o-\\WW', strtotime($date));

        $waiters = $this->firebase->getActiveWaiters();
        $attendanceByDate = $this->firebase->getAllAttendanceByDate($date);

        // Build today's shifts from schedule template (1 Firebase read via cached getScheduleTemplate)
        // Also build shifts lookup and schedules from the same template data
        $todayShifts = [];
        $shifts = [];
        $schedules = [];
        foreach ($waiters as $waiter) {
            $wId = $waiter['id'] ?? '';
            $todayShift = $this->firebase->getWaiterShiftForDate($wId, $date);
            $todayShifts[$wId] = $todayShift;
            if ($todayShift && isset($todayShift['id'])) {
                $shifts[$todayShift['id']] = $todayShift;
            }
            $schedules[$wId] = $this->firebase->getWaiterSchedule($wId);
        }

        return view('admin.attendance.index', compact('date', 'waiters', 'attendanceByDate', 'shifts', 'schedules', 'todayShifts'));
    }

    /**
     * Monthly attendance summary per waiter.
     */
    public function monthly(Request $request)
    {
        $yearMonth = $request->input('month', date('Y-m'));
        $waiters = $this->firebase->getActiveWaiters();

        $summaries = [];
        foreach ($waiters as $waiter) {
            $wId = $waiter['id'] ?? '';
            $summaries[$wId] = $this->firebase->getAttendanceSummary($wId, $yearMonth);
        }

        return view('admin.attendance.monthly', compact('yearMonth', 'waiters', 'summaries'));
    }

    /**
     * Admin override attendance record.
     */
    public function override(Request $request, $waiterId, $date)
    {
        $data = [];

        if ($request->filled('clock_in')) {
            $data['clock_in'] = $request->input('clock_in');
        }
        if ($request->filled('clock_out')) {
            $data['clock_out'] = $request->input('clock_out');
        }
        if ($request->filled('status')) {
            $data['status'] = $request->input('status');
        }
        if ($request->has('late_minutes')) {
            $data['late_minutes'] = (int) $request->input('late_minutes', 0);
        }
        if ($request->filled('note')) {
            $data['note'] = $request->input('note');
        }

        $this->firebase->updateAttendance($waiterId, $date, $data);

        return response()->json(['success' => true, 'message' => 'Data absensi berhasil diperbarui']);
    }

    /**
     * Delete attendance record.
     */
    public function destroy($waiterId, $date)
    {
        $this->firebase->deleteAttendance($waiterId, $date);

        return response()->json(['success' => true, 'message' => 'Data absensi berhasil dihapus']);
    }

    /**
     * QR code configuration page.
     */
    public function qrConfig()
    {
        $qrValue = $this->firebase->getAttendanceQrCode();

        return view('admin.attendance.qr_config', compact('qrValue'));
    }

    /**
     * Regenerate attendance QR code.
     */
    public function regenerateQr()
    {
        $newValue = $this->firebase->regenerateAttendanceQrCode();

        return response()->json(['success' => true, 'qr_value' => $newValue, 'message' => 'QR code berhasil di-regenerate']);
    }

    /**
     * Printable QR code page.
     */
    public function printQr()
    {
        $qrValue = $this->firebase->getAttendanceQrCode();

        return view('admin.attendance.print_qr', compact('qrValue'));
    }
}
