<?php

namespace App\Services;

/**
 * VariantDetectorService
 *
 * Mendeteksi struktur "base name" + "variant label" dari nama produk.
 * Konvensi delimiter di dataset toko ini adalah ` - ` (spasi-dash-spasi).
 *
 * Contoh:
 *   "Vita Chick - Sachet 5g"  → base: "Vita Chick", variant: "Sachet 5g"
 *   "Pelet Apung Hi-Pro"      → base: "Pelet Apung Hi-Pro", variant: null
 *   "Pakan Ayam - 10 kg - A"  → base: "Pakan Ayam", variant: "10 kg - A" (split max 2)
 *
 * Group strategy:
 *   - Produk dengan base_name yang sama dianggap satu variant family.
 *   - Base produk = produk pertama (ID alfabetis terkecil) di group.
 */
class VariantDetectorService
{
    public const DELIMITER = '/\s+-\s+/';

    /**
     * Pisah nama menjadi base + variant_label.
     *
     * @return array{base: string, variant: string|null, has_variant: bool}
     */
    public function detect(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['base' => '', 'variant' => null, 'has_variant' => false];
        }

        $parts = preg_split(self::DELIMITER, $name, 2);
        if (! is_array($parts) || count($parts) === 0) {
            return ['base' => $name, 'variant' => null, 'has_variant' => false];
        }

        $base = trim($parts[0]);
        $variant = isset($parts[1]) ? trim($parts[1]) : null;

        return [
            'base' => $base,
            'variant' => $variant !== '' ? $variant : null,
            'has_variant' => $variant !== null && $variant !== '',
        ];
    }

    /**
     * Group produk by base_name.
     *
     * @param  array<int|string, array{id?: string, name?: string}>  $products  Array of product, key bebas.
     * @return array<string, array{base_name: string, primary_id: string, members: array<int, array{id: string, name: string, variant_label: string|null, has_variant: bool}>}>
     */
    public function groupByBase(array $products): array
    {
        $groups = [];

        foreach ($products as $key => $product) {
            $id = (string) ($product['id'] ?? $key);
            $name = (string) ($product['name'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $detected = $this->detect($name);
            $base = $detected['base'];
            if ($base === '') {
                continue;
            }

            $member = [
                'id' => $id,
                'name' => $name,
                'variant_label' => $detected['variant'],
                'has_variant' => $detected['has_variant'],
            ];

            if (! isset($groups[$base])) {
                $groups[$base] = [
                    'base_name' => $base,
                    'primary_id' => $id,
                    'members' => [$member],
                ];
            } else {
                $groups[$base]['members'][] = $member;
                // primary_id = lowest ID alfabetis (deterministik).
                if (strcmp($id, $groups[$base]['primary_id']) < 0) {
                    $groups[$base]['primary_id'] = $id;
                }
            }
        }

        return $groups;
    }

    /**
     * Apakah produk ini base (primary) di groupnya?
     *
     * @param  array<int|string, array{id?: string, name?: string}>  $allProducts
     */
    public function isPrimary(string $productId, string $productName, array $allProducts): bool
    {
        $detected = $this->detect($productName);
        $groups = $this->groupByBase($allProducts);
        $group = $groups[$detected['base']] ?? null;
        if (! $group) {
            return true; // standalone
        }

        return $group['primary_id'] === $productId;
    }
}
