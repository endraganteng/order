<?php

namespace App\Repositories;

use App\Repositories\Contracts\BonusRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * BonusRepository
 *
 * Relokasi read/delete penalties + bonus summary dari FirebaseService.
 * Behavior dipertahankan persis (RTDB read seperti semula). Business logic
 * (calculateBonus, finalize, scoring) TETAP di BonusService.
 */
class BonusRepository implements BonusRepositoryInterface
{
    public function __construct(private Database $database)
    {
    }

    public function penalties(?string $month = null, ?string $waiterId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate !== null && $endDate !== null) {
            $snapshot = $this->database->getReference('waiter_penalties')
                ->orderByChild('date')->startAt($startDate)->endAt($endDate)->getSnapshot();
        } else {
            $snapshot = $this->database->getReference('waiter_penalties')->getSnapshot();
        }
        if (! $snapshot->exists()) {
            return [];
        }

        $result = [];
        foreach ($snapshot->getValue() as $id => $penalty) {
            if ($month && ($penalty['month'] ?? '') !== $month) {
                continue;
            }
            if ($waiterId && ($penalty['waiter_id'] ?? '') !== $waiterId) {
                continue;
            }
            if ($month === null && $startDate !== null && $endDate !== null) {
                $penaltyDate = (string) ($penalty['date'] ?? '');
                if ($penaltyDate < $startDate || $penaltyDate > $endDate) {
                    continue;
                }
            }
            $penalty['id'] = $id;
            $result[] = $penalty;
        }

        usort($result, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

        return $result;
    }

    public function penaltyById(string $penaltyId): ?array
    {
        $snapshot = $this->database->getReference("waiter_penalties/{$penaltyId}")->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }
        $data = $snapshot->getValue();
        $data['id'] = $penaltyId;

        return $data;
    }

    public function deletePenalty(string $penaltyId): void
    {
        $this->database->getReference("waiter_penalties/{$penaltyId}")->remove();
    }

    public function bonusSummary(string $waiterId, string $periodKey): ?array
    {
        $snapshot = $this->database->getReference("waiter_bonus_summary/{$waiterId}/{$periodKey}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    public function allBonusSummaries(string $periodKey): array
    {
        $snapshot = $this->database->getReference('waiter_bonus_summary')->getSnapshot();
        if (! $snapshot->exists()) {
            return [];
        }

        $result = [];
        foreach ($snapshot->getValue() as $waiterId => $keys) {
            if (isset($keys[$periodKey])) {
                $summary = $keys[$periodKey];
                $summary['waiter_id'] = $waiterId;
                $result[] = $summary;
            }
        }

        return $result;
    }
}
