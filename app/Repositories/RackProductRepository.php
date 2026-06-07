<?php

namespace App\Repositories;

use App\Models\RackProduct;
use App\Repositories\Contracts\RackProductRepositoryInterface;
use Kreait\Firebase\Contract\Database;

/**
 * RackProductRepository
 *
 * Hybrid impl: flag mysql_rack_products pilih read MySQL atau RTDB;
 * legacy_write_rack_products kontrol mirror RTDB. Logika dipindah dari
 * FirebaseService (god object) ke sini. FirebaseService delegasi.
 */
class RackProductRepository implements RackProductRepositoryInterface
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        if (config('features.mysql_rack_products')) {
            return RackProduct::orderBy('name')->get()
                ->map(fn ($row) => $this->rowToPayload($row))
                ->all();
        }

        $snapshot = $this->database->getReference('rack_products')->getSnapshot();
        $products = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $product) {
                $products[] = array_merge(['id' => $key], $product);
            }
        }
        usort($products, fn ($a, $b) => ($a['name'] ?? '') <=> ($b['name'] ?? ''));

        return $products;
    }

    public function allActive(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn ($p) => ($p['is_active'] ?? true) !== false
        ));
    }

    public function find(string $id): ?array
    {
        if (config('features.mysql_rack_products')) {
            $row = RackProduct::where('firebase_legacy_key', $id)->first();
            return $row ? $this->rowToPayload($row) : null;
        }

        $snapshot = $this->database->getReference('rack_products/'.$id)->getSnapshot();
        return $snapshot->exists() ? array_merge(['id' => $id], $snapshot->getValue()) : null;
    }

    public function create(array $data): array
    {
        $payload = $this->buildPayload($data, true);

        $legacyKey = null;
        if (config('features.legacy_write_rack_products')) {
            $legacyKey = (string) $this->database->getReference('rack_products')->push($payload)->getKey();
        } else {
            $legacyKey = 'rp_local_'.substr(hash('sha256', json_encode($payload).microtime()), 0, 24);
        }

        $this->mirrorToMysql($legacyKey, $payload);

        return array_merge(['id' => $legacyKey], $payload);
    }

    public function update(string $id, array $data): void
    {
        $payload = $this->buildPayload($data, false);

        if (config('features.legacy_write_rack_products')) {
            $this->database->getReference('rack_products/'.$id)->update($payload);
        }

        if (config('features.mysql_rack_products')) {
            RackProduct::where('firebase_legacy_key', $id)->update([
                'name' => $payload['name'],
                'category_id' => $payload['category_id'],
                'standard_qty' => $payload['standard_qty'],
                'unit' => $payload['unit'],
                'is_active' => $payload['is_active'],
                'event_updated_at' => $payload['updated_at'],
            ]);
        }
    }

    public function delete(string $id): void
    {
        if (config('features.legacy_write_rack_products')) {
            $this->database->getReference('rack_products/'.$id)->remove();
        }

        if (config('features.mysql_rack_products')) {
            RackProduct::where('firebase_legacy_key', $id)->delete();
        }
    }

    private function buildPayload(array $data, bool $isCreate): array
    {
        $categoryId = isset($data['category_id']) && $data['category_id'] !== '' ? (string) $data['category_id'] : null;

        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'category_id' => $categoryId,
            'standard_qty' => max(0, (int) ($data['standard_qty'] ?? 0)),
            'unit' => trim((string) ($data['unit'] ?? 'pcs')),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'updated_at' => time(),
        ];

        if ($isCreate) {
            $payload['created_at'] = time();
        }

        return $payload;
    }

    private function mirrorToMysql(string $firebaseKey, array $payload): void
    {
        if (! config('features.mysql_rack_products')) {
            return;
        }

        try {
            RackProduct::updateOrCreate(
                ['firebase_legacy_key' => $firebaseKey],
                [
                    'name' => (string) ($payload['name'] ?? ''),
                    'category_id' => $payload['category_id'] ?? null,
                    'standard_qty' => max(0, (int) ($payload['standard_qty'] ?? 0)),
                    'unit' => (string) ($payload['unit'] ?? 'pcs'),
                    'is_active' => (bool) ($payload['is_active'] ?? true),
                    'firebase_payload' => $payload,
                    'event_created_at' => $payload['created_at'] ?? null,
                    'event_updated_at' => $payload['updated_at'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function rowToPayload(RackProduct $row): array
    {
        $id = $row->firebase_legacy_key ?: (string) $row->id;
        $payload = is_array($row->firebase_payload) ? $row->firebase_payload : [];

        if (empty($payload)) {
            $payload = [
                'name' => $row->name,
                'category_id' => $row->category_id,
                'standard_qty' => $row->standard_qty,
                'unit' => $row->unit,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->event_created_at,
                'updated_at' => $row->event_updated_at,
            ];
        }

        return array_merge(['id' => $id], $payload);
    }
}
