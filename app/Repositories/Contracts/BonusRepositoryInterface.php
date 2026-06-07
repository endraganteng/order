<?php

namespace App\Repositories\Contracts;

/**
 * BonusRepositoryInterface
 *
 * Kontrak read/delete penalties + bonus summary. Relokasi data-access dari
 * FirebaseService — behavior dipertahankan persis (penalties + bonus_summary
 * read tetap RTDB seperti semula; tidak menambah MySQL read path baru di sini).
 * Business logic (calculateBonus, finalize, scoring) TETAP di BonusService.
 */
interface BonusRepositoryInterface
{
    /** Penalties dengan filter opsional month/waiter/date-range. */
    public function penalties(?string $month = null, ?string $waiterId = null, ?string $startDate = null, ?string $endDate = null): array;

    /** Penalty by id, null jika tak ada. */
    public function penaltyById(string $penaltyId): ?array;

    /** Hapus penalty by id. */
    public function deletePenalty(string $penaltyId): void;

    /** Bonus summary satu waiter pada periode, null jika tak ada. */
    public function bonusSummary(string $waiterId, string $periodKey): ?array;

    /** Semua bonus summary pada periode. */
    public function allBonusSummaries(string $periodKey): array;
}
