<?php

namespace App\Repositories\Contracts;

/**
 * WaiterRepositoryInterface
 *
 * Kontrak data-access allowed_waiters (master akun waiter). Relokasi dari
 * FirebaseService. Behavior dipertahankan persis (RTDB + requestCache internal,
 * role normalize). AUTH-CRITICAL: shape & nilai harus identik dengan semula.
 */
interface WaiterRepositoryInterface
{
    /** Semua waiter (dengan id + role ternormalisasi). Request-cached. */
    public function all(): array;

    /** Waiter aktif saja. */
    public function allActive(): array;

    /** Waiter aktif by role. */
    public function activeByRole(string $waiterRole): array;

    /** Waiter by id, null jika tak ada. */
    public function find(string $id): ?array;

    /** Tambah akun waiter (optional password hash + atribut). */
    public function add(string $email, string $name, ?string $passwordHash = null, string $waiterRole = 'pelayan', ?string $shiftId = null, ?string $phone = null, bool $attendanceExempt = false): void;

    /** Update waiter by id. */
    public function update(string $id, array $data): void;

    /** Hapus waiter by id. */
    public function delete(string $id): void;
}
