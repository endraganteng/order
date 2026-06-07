<?php

namespace App\Repositories\Contracts;

/**
 * CashierTaskRepositoryInterface
 *
 * Kontrak akses data cashier task. Menyembunyikan MySQL vs RTDB (flag-routed)
 * + dual-write mirror. FirebaseService delegasi ke sini.
 */
interface CashierTaskRepositoryInterface
{
    /** Semua task, terurut created_at desc. Shape RTDB-compatible. */
    public function all(): array;

    /** Task pending saja. */
    public function allActive(): array;

    /** Buat task dari payload lengkap. Return legacy key. */
    public function create(array $taskData): string;

    /** Update status task. Return ['success'=>bool,'message'=>string]. */
    public function updateStatus(string $id, string $status, ?string $note = null, ?string $workerId = null, ?string $workerName = null): array;

    /** Hapus task by id. */
    public function delete(string $id): void;

    /** Mark pending tasks overdue jika lewat deadline. Return jumlah. */
    public function markOverdue(): int;

    /** Map template_id => true untuk recurring instance pending pada tanggal. */
    public function existingRecurringMap(string $date): array;

    /** Apakah template punya instance pending pada tanggal. */
    public function hasPendingRecurringInstance(string $templateId, string $date): bool;

    /** Apakah template punya instance done pada tanggal. */
    public function hasDoneRecurringInstance(string $templateId, string $date): bool;
}
