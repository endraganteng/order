<?php

namespace App\Repositories;

use App\Models\WaiterAttendance;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * AttendanceRepository
 *
 * Hybrid impl: flag mysql_attendance pilih read MySQL atau RTDB. Read + delete
 * data-access dipindah dari FirebaseService. Business logic (clockIn/clockOut,
 * admin override + readback sync) TETAP di service.
 */
class AttendanceRepository implements AttendanceRepositoryInterface
{
    public function __construct(private Database $database)
    {
    }

    public function forWaiterOnDate(string $waiterId, string $date): ?array
    {
        if (config('features.mysql_attendance')) {
            $row = WaiterAttendance::where('waiter_id', $waiterId)->where('date', $date)->first();
            return $row && is_array($row->data) ? $row->data : ($row ? [] : null);
        }

        $snapshot = $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$date)->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    public function forWaiterInMonth(string $waiterId, string $yearMonth): array
    {
        if (config('features.mysql_attendance')) {
            $rows = WaiterAttendance::where('waiter_id', $waiterId)
                ->where('date', 'like', $yearMonth.'-%')
                ->orderBy('date')
                ->get();
            $result = [];
            foreach ($rows as $row) {
                $result[$row->date] = is_array($row->data) ? $row->data : [];
            }
            return $result;
        }

        $snapshot = $this->database->getReference('waiter_attendance/'.$waiterId)->getSnapshot();
        if (! $snapshot->exists()) {
            return [];
        }

        $filtered = [];
        $prefix = $yearMonth.'-';
        foreach ($snapshot->getValue() as $date => $record) {
            if (strpos($date, $prefix) === 0) {
                $filtered[$date] = $record;
            }
        }
        ksort($filtered);

        return $filtered;
    }

    public function allOnDate(string $date): array
    {
        if (config('features.mysql_attendance')) {
            $result = [];
            foreach (WaiterAttendance::where('date', $date)->get() as $row) {
                $result[$row->waiter_id] = is_array($row->data) ? $row->data : [];
            }
            return $result;
        }

        $snapshot = $this->database->getReference('waiter_attendance')->getSnapshot();
        if (! $snapshot->exists()) {
            return [];
        }

        $result = [];
        foreach ($snapshot->getValue() as $waiterId => $dates) {
            if (isset($dates[$date]) && is_array($dates[$date])) {
                $result[$waiterId] = $dates[$date];
            }
        }

        return $result;
    }

    public function delete(string $waiterId, string $date): void
    {
        $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$date)->remove();

        if (config('features.mysql_attendance')) {
            try {
                WaiterAttendance::where('waiter_id', $waiterId)->where('date', $date)->delete();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
