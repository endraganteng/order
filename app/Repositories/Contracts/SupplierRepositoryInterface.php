<?php

namespace App\Repositories\Contracts;

/**
 * SupplierRepositoryInterface
 *
 * Kontrak CRUD supplier. Relokasi data-access dari FirebaseService.
 * Behavior dipertahankan persis (RTDB seperti semula).
 */
interface SupplierRepositoryInterface
{
    /** Semua supplier, terurut nama. */
    public function all(): array;

    /** Supplier by id, null jika tak ada. */
    public function find(string $id): ?array;

    /** Buat supplier, return legacy key. */
    public function create(array $data): string;

    /** Update supplier by id. */
    public function update(string $id, array $data): bool;

    /** Hapus supplier by id. */
    public function delete(string $id): bool;
}
