<?php

namespace App\Repositories;

use App\Repositories\Contracts\WaiterRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * WaiterRepository
 *
 * Relokasi data-access allowed_waiters dari FirebaseService. Behavior identik:
 * RTDB read + request-cache + role normalize. AUTH-CRITICAL — shape & nilai
 * dipertahankan persis. normalizeWaiterRole direplikasi di sini (pure function)
 * supaya repo self-contained; service tetap punya kopinya untuk caller lain.
 */
class WaiterRepository implements WaiterRepositoryInterface
{
    private const VALID_ROLES = ['kasir', 'pelayan', 'backup', 'supervisor', 'finance'];

    private ?array $cache = null;

    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $snapshot = $this->database->getReference('allowed_waiters')->getSnapshot();
        $waiters = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $waiter) {
                $merged = array_merge(['id' => $key], $waiter);
                $merged['waiter_role'] = $this->normalizeRole($merged['waiter_role'] ?? 'pelayan');
                $waiters[] = $merged;
            }
        }
        $this->cache = $waiters;

        return $waiters;
    }

    public function allActive(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn ($waiter) => ($waiter['is_active'] ?? true) !== false
        ));
    }

    public function activeByRole(string $waiterRole): array
    {
        $normalized = $this->normalizeRole($waiterRole);

        return array_values(array_filter(
            $this->allActive(),
            fn ($waiter) => $this->normalizeRole($waiter['waiter_role'] ?? 'pelayan') === $normalized
        ));
    }

    public function find(string $id): ?array
    {
        $snapshot = $this->database->getReference('allowed_waiters/'.$id)->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    public function add(string $email, string $name, ?string $passwordHash = null, string $waiterRole = 'pelayan', ?string $shiftId = null, ?string $phone = null, bool $attendanceExempt = false): void
    {
        $payload = [
            'email' => strtolower(trim($email)),
            'name' => trim($name),
            'waiter_role' => $this->normalizeRole($waiterRole),
            'is_active' => true,
            'created_at' => time(),
        ];

        if ($passwordHash) {
            $payload['password_hash'] = $passwordHash;
        }
        if ($shiftId) {
            $payload['shift_id'] = $shiftId;
        }
        if ($phone) {
            $payload['phone'] = trim($phone);
        }
        if ($attendanceExempt) {
            $payload['attendance_exempt'] = true;
        }

        $this->database->getReference('allowed_waiters')->push($payload);
        $this->cache = null;
    }

    public function update(string $id, array $data): void
    {
        if (array_key_exists('waiter_role', $data)) {
            $data['waiter_role'] = $this->normalizeRole($data['waiter_role']);
        }

        $this->database->getReference('allowed_waiters/'.$id)->update($data);
        $this->cache = null;
    }

    public function delete(string $id): void
    {
        $this->database->getReference('allowed_waiters/'.$id)->remove();
        $this->cache = null;
    }

    private function normalizeRole($waiterRole): string
    {
        $role = strtolower(trim((string) $waiterRole));

        return in_array($role, self::VALID_ROLES, true) ? $role : 'pelayan';
    }
}
