<?php

namespace App\Repositories\Contracts;

/**
 * WaiterTaskRepositoryInterface
 *
 * Kontrak akses data waiter task (read + delete). Menyembunyikan MySQL vs RTDB
 * (flag-routed mysql_waiter_tasks). Business logic (assignment, status update)
 * TIDAK di sini — tetap di service/Action. Ini murni data-access.
 */
interface WaiterTaskRepositoryInterface
{
    /** Semua task (full node), terurut created_at desc. */
    public function all(): array;

    /** Task by id, null jika tak ada. */
    public function find(string $taskId): ?array;

    /** Task satu waiter, opsional date range. Shape RTDB-compatible. */
    public function forWaiter(string $waiterId, ?string $dateFrom = null, ?string $dateTo = null): array;

    /** Task satu waiter pada satu tanggal. */
    public function forWaiterOnDate(string $waiterId, string $date): array;

    /** Semua task pada satu tanggal (semua waiter). */
    public function forDate(string $date): array;

    /** Task pada rentang tanggal (semua waiter). */
    public function forDateRange(string $startDate, string $endDate): array;

    /** Hapus task by id. */
    public function delete(string $id): void;
}
