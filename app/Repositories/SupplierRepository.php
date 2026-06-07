<?php

namespace App\Repositories;

use App\Repositories\Contracts\SupplierRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * SupplierRepository
 *
 * Relokasi CRUD supplier dari FirebaseService. Behavior dipertahankan persis
 * (RTDB seperti semula).
 */
class SupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        $snapshot = $this->database->getReference('suppliers')->getSnapshot();
        if (! $snapshot->exists()) {
            return [];
        }

        $suppliers = [];
        foreach ($snapshot->getValue() as $id => $supplier) {
            if (! is_array($supplier)) {
                continue;
            }
            $supplier['id'] = $id;
            $suppliers[] = $supplier;
        }

        usort($suppliers, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return $suppliers;
    }

    public function find(string $id): ?array
    {
        $snapshot = $this->database->getReference("suppliers/{$id}")->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }

        $supplier = (array) $snapshot->getValue();
        $supplier['id'] = $id;

        return $supplier;
    }

    public function create(array $data): string
    {
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'contact_person' => (string) ($data['contact_person'] ?? ''),
            'created_at' => time(),
            'updated_at' => time(),
        ];

        return $this->database->getReference('suppliers')->push($payload)->getKey();
    }

    public function update(string $id, array $data): bool
    {
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'contact_person' => (string) ($data['contact_person'] ?? ''),
            'updated_at' => time(),
        ];

        $this->database->getReference("suppliers/{$id}")->update($payload);

        return true;
    }

    public function delete(string $id): bool
    {
        $this->database->getReference("suppliers/{$id}")->remove();

        return true;
    }
}
