<?php

namespace App\Repositories\Contracts;

/**
 * RackRepositoryInterface
 *
 * Kontrak data-access waiter_racks MASTER (metadata rak). Relokasi dari
 * FirebaseService. Behavior dipertahankan persis (RTDB + requestCache).
 * CATATAN: sub-node waiter_racks/{id}/products (dipakai live listener stock_take)
 * TIDAK di sini — tetap di service. Ini cuma master CRUD.
 */
interface RackRepositoryInterface
{
    /** Semua rack, terurut check_order lalu nama. Request-cached. */
    public function all(): array;

    /** Rack aktif saja. */
    public function allActive(): array;

    /** Rack by id, null jika tak ada. */
    public function find(string $id): ?array;

    /** Buat rack. Return payload + 'id'. */
    public function create(array $payload): array;

    /** Update rack metadata by id. */
    public function update(string $id, array $payload): void;

    /** Hapus rack by id. */
    public function delete(string $id): void;
}
