<?php

namespace App\Repositories\Contracts;

/**
 * RackProductRepositoryInterface
 *
 * Kontrak akses data master product. Implementasi menyembunyikan detail
 * MySQL vs Firebase RTDB (flag-routed). FirebaseService delegasi ke sini
 * selama transisi — call site lama tidak berubah.
 */
interface RackProductRepositoryInterface
{
    /** Semua produk, terurut nama. Shape RTDB-compatible (array + 'id'). */
    public function all(): array;

    /** Produk aktif saja. */
    public function allActive(): array;

    /** Satu produk by id (legacy key), null jika tak ada. */
    public function find(string $id): ?array;

    /** Buat produk. Return payload + 'id'. */
    public function create(array $data): array;

    /** Update produk by id. */
    public function update(string $id, array $data): void;

    /** Hapus produk by id. */
    public function delete(string $id): void;
}
