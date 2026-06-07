<?php

namespace App\Repositories\Contracts;

/**
 * AttendanceRepositoryInterface
 *
 * Kontrak read + delete data attendance. Flag-routed mysql_attendance.
 * Business logic (clockIn/clockOut shift calc, admin override + readback sync)
 * TETAP di service. Ini murni data-access read/delete.
 */
interface AttendanceRepositoryInterface
{
    /** Record attendance satu waiter pada tanggal, null jika tak ada. */
    public function forWaiterOnDate(string $waiterId, string $date): ?array;

    /** Record attendance satu waiter dalam satu bulan (Y-m), keyed by date. */
    public function forWaiterInMonth(string $waiterId, string $yearMonth): array;

    /** Semua waiter attendance pada satu tanggal, keyed by waiter_id. */
    public function allOnDate(string $date): array;

    /** Hapus record attendance satu waiter pada tanggal. */
    public function delete(string $waiterId, string $date): void;
}
