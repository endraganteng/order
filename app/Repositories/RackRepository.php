<?php

namespace App\Repositories;

use App\Repositories\Contracts\RackRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * RackRepository
 *
 * Relokasi master CRUD waiter_racks dari FirebaseService. Behavior identik:
 * RTDB + request-cache + sort check_order. Barcode generation tetap di service
 * (createRack passes pre-built payload). Sub-node products (live listener)
 * TIDAK ditangani di sini.
 */
class RackRepository implements RackRepositoryInterface
{
    private ?array $cache = null;

    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $snapshot = $this->database->getReference('waiter_racks')->getSnapshot();
        $racks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $rack) {
                $racks[] = array_merge(['id' => $key], $rack);
            }
        }

        usort($racks, function ($a, $b) {
            $orderA = (int) ($a['check_order'] ?? 0);
            $orderB = (int) ($b['check_order'] ?? 0);
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            return ($a['name'] ?? '') <=> ($b['name'] ?? '');
        });

        $this->cache = $racks;

        return $racks;
    }

    public function allActive(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn ($rack) => ($rack['is_active'] ?? true) !== false
        ));
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $rack) {
            if (($rack['id'] ?? null) === $id) {
                return $rack;
            }
        }

        return null;
    }

    public function create(array $payload): array
    {
        $created = $this->database->getReference('waiter_racks')->push($payload);
        $this->cache = null;

        return array_merge(['id' => $created->getKey()], $payload);
    }

    public function update(string $id, array $payload): void
    {
        $this->database->getReference('waiter_racks/'.$id)->update($payload);
        $this->cache = null;
    }

    public function delete(string $id): void
    {
        $this->database->getReference('waiter_racks/'.$id)->remove();
        $this->cache = null;
    }
}
