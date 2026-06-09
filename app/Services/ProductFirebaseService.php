<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class ProductFirebaseService
{
    protected $database;
    protected FirebaseService $firebase;
    protected array $requestCache = [];

    public function __construct(Database $database, FirebaseService $firebase)
    {
        $this->database = $database;
        $this->firebase = $firebase;
    }

    /**
     * Build per-product live stock snapshot for one rack from latest rack_check records.
     *
     * @param  array<int, array<string, mixed>>|null  $rackProducts
     * @return array<string, array<string, mixed>>
     */
    public function getRackProductLiveStock(string $rackId, ?array $rackProducts = null): array
    {
        $assignedProducts = is_array($rackProducts) ? $rackProducts : $this->getRackProducts($rackId);
        $assignedMap = [];
        foreach ($assignedProducts as $product) {
            $productId = trim((string) ($product['id'] ?? ''));
            if ($productId === '') {
                continue;
            }
            $assignedMap[$productId] = $product;
        }

        $liveMap = [];
        foreach (array_keys($assignedMap) as $productId) {
            $storedCurrentQty = array_key_exists('current_qty', $assignedMap[$productId]) && $assignedMap[$productId]['current_qty'] !== null
                ? max(0, (int) $assignedMap[$productId]['current_qty'])
                : null;

            if ($storedCurrentQty !== null) {
                $standardQty = max(0, (int) ($assignedMap[$productId]['standard_qty'] ?? 0));
                $liveMap[$productId] = [
                    'product_id' => $productId,
                    'current_qty' => $storedCurrentQty,
                    'last_updated_at' => isset($assignedMap[$productId]['last_updated_at']) ? (int) $assignedMap[$productId]['last_updated_at'] : (isset($assignedMap[$productId]['updated_at']) ? (int) $assignedMap[$productId]['updated_at'] : null),
                    'is_shortage' => $standardQty > 0 ? $storedCurrentQty < $standardQty : false,
                ];
                continue;
            }

            $liveMap[$productId] = [
                'product_id' => $productId,
                'current_qty' => null,
                'last_updated_at' => null,
                'is_shortage' => null,
            ];
        }

        if (count($assignedMap) === 0) {
            return $liveMap;
        }

        $history = $this->firebase->getRackCheckHistory($rackId, 300);
        foreach ($history as $task) {
            $completedAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($task['completed_at'] ?? 0));
            $checklist = $task['completed_product_checklist'] ?? [];
            if (! is_array($checklist) || count($checklist) === 0) {
                continue;
            }

            foreach ($checklist as $checklistKey => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $productId = trim((string) ($item['product_id'] ?? $checklistKey));
                if ($productId === '' || ! isset($assignedMap[$productId])) {
                    continue;
                }

                if (($liveMap[$productId]['last_updated_at'] ?? null) !== null) {
                    continue;
                }

                $currentQty = max(0, (int) ($item['actual_qty'] ?? 0));
                $standardQty = max(0, (int) ($item['standard_qty'] ?? ($assignedMap[$productId]['standard_qty'] ?? 0)));
                $liveMap[$productId] = [
                    'product_id' => $productId,
                    'current_qty' => $currentQty,
                    'last_updated_at' => $completedAt > 0 ? $completedAt : null,
                    'is_shortage' => $standardQty > 0 ? $currentQty < $standardQty : false,
                ];
            }
        }

        return $liveMap;
    }

    /**
     * Resolve storage rack by barcode and return assigned products.
     *
     * @return array<string,mixed>
     */
    public function getStorageRackProductsByBarcode(string $barcodeValue): array
    {
        $rack = app(RackStockFirebaseService::class)->resolveStorageRackByBarcode($barcodeValue);
        if (! $rack) {
            return [
                'success' => false,
                'message' => 'Rak tidak ditemukan atau tidak aktif.',
            ];
        }

        $rackId = trim((string) ($rack['id'] ?? ''));
        $rackType = 'storage';

        return [
            'success' => true,
            'rack' => [
                'id' => $rackId,
                'name' => (string) ($rack['name'] ?? ''),
                'barcode_value' => strtoupper(trim((string) ($rack['barcode_value'] ?? ''))),
                'rack_type' => $rackType,
                'location' => (string) ($rack['location'] ?? ''),
            ],
            'products' => $this->getRackProducts($rackId),
        ];
    }

    /**
     * Get all product categories.
     */
    public function getProductCategories()
    {
        if (config('features.mysql_product_categories')) {
            return \App\Models\ProductCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->firebase_legacy_key ?: (string) $row->id,
                        'name' => $row->name,
                        'description' => $row->description,
                        'sort_order' => $row->sort_order,
                        'is_active' => $row->is_active,
                        'created_at' => $row->event_created_at,
                        'updated_at' => $row->event_updated_at,
                    ];
                })->all();
        }

        $reference = $this->database->getReference('product_categories');
        $snapshot = $reference->getSnapshot();

        $categories = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $category) {
                $categories[] = array_merge(['id' => $key], $category);
            }
        }

        usort($categories, function ($a, $b) {
            return ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999)
                ?: ($a['name'] ?? '') <=> ($b['name'] ?? '');
        });

        return $categories;
    }

    /**
     * Get active product categories.
     */
    public function getActiveProductCategories()
    {
        return array_values(array_filter($this->getProductCategories(), function ($cat) {
            return ($cat['is_active'] ?? true) !== false;
        }));
    }

    /**
     * Get product categories as id => data map (for quick lookup)
     */
    public function getProductCategoriesMap(): array
    {
        $categories = $this->getProductCategories();
        $map = [];
        foreach ($categories as $cat) {
            $map[$cat['id']] = $cat;
        }
        return $map;
    }

    /**
     * Get product category by id.
     */
    public function getProductCategoryById($id)
    {
        $reference = $this->database->getReference('product_categories/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    /**
     * Create product category.
     */
    public function createProductCategory(array $data)
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $legacyKey = null;
        if (config('features.legacy_write_product_categories')) {
            $legacyKey = (string) $this->database->getReference('product_categories')->push($payload)->getKey();
        }

        if (config('features.mysql_product_categories')) {
            try {
                $attrs = [
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?: null,
                    'sort_order' => $payload['sort_order'],
                    'is_active' => $payload['is_active'],
                    'event_created_at' => $payload['created_at'],
                    'event_updated_at' => $payload['updated_at'],
                ];
                if ($legacyKey !== null) {
                    \App\Models\ProductCategory::updateOrCreate(['firebase_legacy_key' => $legacyKey], $attrs);
                } else {
                    \App\Models\ProductCategory::create($attrs);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return array_merge(['id' => $legacyKey], $payload);
    }

    /**
     * Update product category.
     */
    public function updateProductCategory($id, array $data)
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'updated_at' => time(),
        ];

        $this->database->getReference('product_categories/'.$id)->update($payload);

        if (config('features.mysql_product_categories')) {
            try {
                \App\Models\ProductCategory::updateOrCreate(
                    ['firebase_legacy_key' => (string) $id],
                    [
                        'name' => $payload['name'],
                        'description' => $payload['description'] ?: null,
                        'sort_order' => $payload['sort_order'],
                        'is_active' => $payload['is_active'],
                        'event_updated_at' => $payload['updated_at'],
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Delete product category. Unlinks products (sets category_id to null).
     */
    public function deleteProductCategory($id)
    {
        // Unlink products that reference this category
        $productsRef = $this->database->getReference('rack_products');
        $productsSnap = $productsRef->getSnapshot();

        if ($productsSnap->exists()) {
            $updates = [];
            foreach ($productsSnap->getValue() as $productId => $product) {
                if (($product['category_id'] ?? null) === $id) {
                    $updates[$productId.'/category_id'] = null;
                }
            }
            if (! empty($updates)) {
                $productsRef->update($updates);
            }
        }

        $this->database->getReference('product_categories/'.$id)->remove();

        if (config('features.mysql_product_categories')) {
            try {
                \App\Models\ProductCategory::where('firebase_legacy_key', (string) $id)->delete();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Get all master products.
     */
    public function getProducts()
    {
        return app(\App\Repositories\Contracts\RackProductRepositoryInterface::class)->all();
    }

    /**
     * Get active master products.
     */
    public function getActiveProducts()
    {
        return app(\App\Repositories\Contracts\RackProductRepositoryInterface::class)->allActive();
    }

    /**
     * Get product by id.
     */
    public function getProductById($id)
    {
        return app(\App\Repositories\Contracts\RackProductRepositoryInterface::class)->find((string) $id);
    }

    /**
     * Create master product.
     */
    public function createProduct(array $data)
    {
        return app(\App\Repositories\Contracts\RackProductRepositoryInterface::class)->create($data);
    }

    /**
     * Reset (delete) ALL master products and their rack assignments.
     * Optionally also reset all product categories.
     */
    public function resetAllProducts(bool $resetCategories = false): array
    {
        $deleted = 0;
        $categoriesDeleted = 0;

        // 1. Remove all product assignments from racks
        $racksSnapshot = $this->database->getReference('waiter_racks')->getSnapshot();
        if ($racksSnapshot->exists()) {
            $updates = [];
            foreach ($racksSnapshot->getValue() as $rackId => $rack) {
                if (!empty($rack['products']) && is_array($rack['products'])) {
                    $updates[$rackId . '/products'] = null;
                }
            }
            if (!empty($updates)) {
                $this->database->getReference('waiter_racks')->update($updates);
            }
        }

        // 2. Count and remove all master products
        $productsSnapshot = $this->database->getReference('rack_products')->getSnapshot();
        if ($productsSnapshot->exists()) {
            $deleted = count($productsSnapshot->getValue());
            $this->database->getReference('rack_products')->remove();
        }

        // 3. Optionally remove all categories
        if ($resetCategories) {
            $categoriesSnapshot = $this->database->getReference('product_categories')->getSnapshot();
            if ($categoriesSnapshot->exists()) {
                $categoriesDeleted = count($categoriesSnapshot->getValue());
                $this->database->getReference('product_categories')->remove();
            }
        }

        return [
            'success' => true,
            'deleted' => $deleted,
            'categories_deleted' => $categoriesDeleted,
        ];
    }

    /**
     * Import products from Excel file (Olsera format).
     * Reads name (col A) and category (col D).
     * Auto-creates categories that don't exist yet.
     * Skips duplicate product names (case-insensitive).
     */
    public function importProductsFromExcel(string $filePath, int $defaultStandardQty = 0): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        $existingCategories = [];
        foreach ($this->getProductCategories() as $cat) {
            $existingCategories[mb_strtolower(trim($cat['name']))] = (string) $cat['id'];
        }

        $existingProductNames = [];
        foreach ($this->getProducts() as $product) {
            $existingProductNames[mb_strtolower(trim($product['name']))] = true;
        }

        $imported = 0;
        $skipped = 0;
        $categoriesCreated = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $baseName = isset($row[0]) ? trim((string) $row[0]) : '';
            $categoryName = isset($row[3]) ? trim((string) $row[3]) : '';
            $variantNames = isset($row[5]) ? trim((string) $row[5]) : '';

            if ($baseName === '') {
                continue;
            }

            $name = $variantNames !== '' ? $baseName . ' - ' . $variantNames : $baseName;

            $nameLower = mb_strtolower($name);
            if (isset($existingProductNames[$nameLower])) {
                $skipped++;
                continue;
            }

            // Resolve category
            $categoryId = null;
            if ($categoryName !== '') {
                $catLower = mb_strtolower($categoryName);
                if (isset($existingCategories[$catLower])) {
                    $categoryId = $existingCategories[$catLower];
                } else {
                    // Auto-create category
                    try {
                        $newCat = $this->createProductCategory([
                            'name' => $categoryName,
                            'description' => '',
                            'sort_order' => 0,
                            'is_active' => true,
                        ]);
                        $categoryId = (string) $newCat['id'];
                        $existingCategories[$catLower] = $categoryId;
                        $categoriesCreated++;
                    } catch (\Throwable $e) {
                        $errors[] = "Baris " . ($i + 1) . ": Gagal buat kategori '{$categoryName}'";
                        continue;
                    }
                }
            }

            // Create product
            try {
                $this->createProduct([
                    'name' => $name,
                    'category_id' => $categoryId,
                    'standard_qty' => $defaultStandardQty,
                    'unit' => 'pcs',
                    'is_active' => true,
                ]);
                $existingProductNames[$nameLower] = true;
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Baris " . ($i + 1) . ": Gagal import '{$name}'";
                $skipped++;
            }
        }

        return [
            'success' => true,
            'total' => $imported + $skipped,
            'imported' => $imported,
            'skipped' => $skipped,
            'categories_created' => $categoriesCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Get products assigned to one rack.
     */
    public function getRackProducts($rackId)
    {
        $reference = $this->database->getReference('waiter_racks/'.$rackId.'/products');
        $snapshot = $reference->getSnapshot();

        $masterMap = [];
        foreach ($this->getProducts() as $product) {
            $masterMap[(string) ($product['id'] ?? '')] = $product;
        }

        $products = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $productId => $assignment) {
                $productId = trim((string) $productId);
                if ($productId === '') {
                    continue;
                }

                $masterProduct = $masterMap[$productId] ?? [];
                $fallbackName = trim((string) ($assignment['product_name'] ?? ''));
                $fallbackUnit = trim((string) ($assignment['product_unit'] ?? 'pcs'));
                $products[] = [
                    'id' => $productId,
                    'name' => (string) ($masterProduct['name'] ?? ($fallbackName !== '' ? $fallbackName : ('Produk #'.$productId))),
                    'standard_qty' => isset($assignment['standard_qty'])
                        ? max(0, (int) $assignment['standard_qty'])
                        : max(0, (int) ($masterProduct['standard_qty'] ?? 0)),
                    'min_qty' => max(0, (int) ($assignment['min_qty'] ?? 0)),
                    'current_qty' => array_key_exists('current_qty', $assignment) && $assignment['current_qty'] !== null
                        ? max(0, (int) $assignment['current_qty'])
                        : null,
                    'last_updated_at' => $assignment['last_updated_at'] ?? ($assignment['updated_at'] ?? null),
                    'unit' => (string) ($masterProduct['unit'] ?? ($fallbackUnit !== '' ? $fallbackUnit : 'pcs')),
                    'is_active' => ! isset($masterProduct['is_active']) || ($masterProduct['is_active'] !== false),
                    'assigned_at' => $assignment['assigned_at'] ?? null,
                    'updated_at' => $assignment['updated_at'] ?? null,
                ];
            }
        }

        usort($products, function ($a, $b) {
            return ($a['name'] ?? '') <=> ($b['name'] ?? '');
        });

        return $products;
    }

    /**
     * Assign products to one rack.
     */
    public function assignProductsToRack($rackId, array $productAssignments)
    {
        $reference = $this->database->getReference('waiter_racks/'.$rackId.'/products');
        $existingSnapshot = $reference->getSnapshot();
        $existingProducts = $existingSnapshot->exists() ? (array) $existingSnapshot->getValue() : [];

        $masterMap = [];
        foreach ($this->getProducts() as $product) {
            $productId = trim((string) ($product['id'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $masterMap[$productId] = $product;
        }

        $now = time();
        $payload = [];

        foreach ($productAssignments as $productId => $assignment) {
            $productId = trim((string) $productId);
            if ($productId === '' || ! isset($masterMap[$productId])) {
                continue;
            }

            $masterProduct = $masterMap[$productId];
            $standardQty = isset($assignment['standard_qty'])
                ? max(0, (int) $assignment['standard_qty'])
                : max(0, (int) ($masterProduct['standard_qty'] ?? 0));

            $minQty = max(0, (int) ($assignment['min_qty'] ?? $existingProducts[$productId]['min_qty'] ?? 0));

            $payload[$productId] = [
                'product_id' => $productId,
                'standard_qty' => $standardQty,
                'min_qty' => $minQty,
                'current_qty' => array_key_exists('current_qty', $existingProducts[$productId] ?? []) && $existingProducts[$productId]['current_qty'] !== null
                    ? max(0, (int) $existingProducts[$productId]['current_qty'])
                    : null,
                'assigned_at' => $existingProducts[$productId]['assigned_at'] ?? $now,
                'updated_at' => $now,
            ];
        }

        $reference->set($payload);
    }

    /**
     * Bulk assign multiple products to multiple racks at once.
     *
     * @param array $assignments  [ rackId => [ productId => ['standard_qty' => int], ... ], ... ]
     */
    public function bulkAssignProductsToRacks(array $assignments)
    {
        $masterMap = [];
        foreach ($this->getProducts() as $product) {
            $productId = trim((string) ($product['id'] ?? ''));
            if ($productId !== '') {
                $masterMap[$productId] = $product;
            }
        }

        $rackIds = array_keys($assignments);
        $now = time();

        foreach ($rackIds as $rackId) {
            $rackId = trim((string) $rackId);
            if ($rackId === '') {
                continue;
            }

            $reference = $this->database->getReference('waiter_racks/' . $rackId . '/products');
            $existingSnapshot = $reference->getSnapshot();
            $existingProducts = $existingSnapshot->exists() ? (array) $existingSnapshot->getValue() : [];

            $productAssignments = $assignments[$rackId] ?? [];
            $payload = $existingProducts;

            foreach ($productAssignments as $productId => $assignment) {
                $productId = trim((string) $productId);
                if ($productId === '' || ! isset($masterMap[$productId])) {
                    continue;
                }

                $masterProduct = $masterMap[$productId];
                $standardQty = isset($assignment['standard_qty'])
                    ? max(0, (int) $assignment['standard_qty'])
                    : max(0, (int) ($masterProduct['standard_qty'] ?? 0));

                $minQty = max(0, (int) ($assignment['min_qty'] ?? $existingProducts[$productId]['min_qty'] ?? 0));

                $payload[$productId] = [
                    'product_id' => $productId,
                    'standard_qty' => $standardQty,
                    'min_qty' => $minQty,
                    'current_qty' => array_key_exists('current_qty', $existingProducts[$productId] ?? []) && $existingProducts[$productId]['current_qty'] !== null
                        ? max(0, (int) $existingProducts[$productId]['current_qty'])
                        : null,
                    'assigned_at' => $existingProducts[$productId]['assigned_at'] ?? $now,
                    'updated_at' => $now,
                ];
            }

            $reference->set($payload);
        }
    }

    /**
     * Additively assign ONE product to a rack without overwriting other entries.
     * Path-level set so we never replace the whole `products` node.
     *
     * @return array{success:bool,message:string,product?:array}
     */
    public function addSingleProductToRack(string $rackId, string $productId, ?int $standardQty = null, int $minQty = 0): array
    {
        $rackId = trim($rackId);
        $productId = trim($productId);
        if ($rackId === '' || $productId === '') {
            return ['success' => false, 'message' => 'Rak atau produk tidak valid.'];
        }

        $masterProduct = $this->getProductById($productId);
        if (! $masterProduct) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan di master.'];
        }

        $existingRef = $this->database->getReference('waiter_racks/' . $rackId . '/products/' . $productId);
        $existingSnap = $existingRef->getSnapshot();
        $existing = $existingSnap->exists() ? (array) $existingSnap->getValue() : [];

        $now = time();
        $resolvedStandard = $standardQty !== null
            ? max(0, (int) $standardQty)
            : max(0, (int) ($existing['standard_qty'] ?? $masterProduct['standard_qty'] ?? 0));
        $resolvedMin = max(0, (int) ($minQty ?? $existing['min_qty'] ?? 0));

        $payload = [
            'product_id'   => $productId,
            'standard_qty' => $resolvedStandard,
            'min_qty'      => $resolvedMin,
            'current_qty'  => array_key_exists('current_qty', $existing) && $existing['current_qty'] !== null
                ? max(0, (int) $existing['current_qty'])
                : null,
            'assigned_at'  => $existing['assigned_at'] ?? $now,
            'updated_at'   => $now,
        ];

        $existingRef->set($payload);

        return [
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke rak.',
            'product' => [
                'id'           => $productId,
                'name'         => (string) ($masterProduct['name'] ?? ('Produk #' . $productId)),
                'unit'         => (string) ($masterProduct['unit'] ?? 'pcs'),
                'standard_qty' => $resolvedStandard,
                'min_qty'      => $resolvedMin,
                'current_qty'  => $payload['current_qty'],
                'is_active'    => ! isset($masterProduct['is_active']) || ($masterProduct['is_active'] !== false),
                'assigned_at'  => $payload['assigned_at'],
                'updated_at'   => $payload['updated_at'],
            ],
        ];
    }

    /**
     * Search master products by name/barcode, with optional exclusion of already-assigned IDs.
     *
     * @param array<int,string> $excludeIds
     * @return array<int,array>
     */
    public function searchMasterProducts(string $query, array $excludeIds = [], int $limit = 30): array
    {
        $query = trim($query);
        $excludeMap = [];
        foreach ($excludeIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $excludeMap[$id] = true;
            }
        }

        $results = [];
        foreach ($this->getProducts() as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = (string) ($p['id'] ?? '');
            if ($id === '' || isset($excludeMap[$id])) {
                continue;
            }
            if (isset($p['is_active']) && $p['is_active'] === false) {
                continue;
            }
            if ($query !== '') {
                $name = (string) ($p['name'] ?? '');
                $barcode = (string) ($p['barcode'] ?? '');
                if (stripos($name, $query) === false && stripos($barcode, $query) === false) {
                    continue;
                }
            }
            $results[] = [
                'id'           => $id,
                'name'         => (string) ($p['name'] ?? '-'),
                'unit'         => (string) ($p['unit'] ?? 'pcs'),
                'barcode'      => (string) ($p['barcode'] ?? ''),
                'standard_qty' => (int) ($p['standard_qty'] ?? 0),
                'category_name' => (string) ($p['category_name'] ?? ''),
            ];
            if (count($results) >= max(1, $limit)) {
                break;
            }
        }

        usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $results;
    }

    /**
     * Get warehouse stock availability for a list of product IDs.
     * Returns map: { productId => { total_qty, racks: [{rack_id, rack_name, qty}], status } }
     * Status values: 'available' (total>0), 'empty' (registered in storage but qty=0), 'missing' (not in any storage rack).
     *
     * @param array<int,string> $productIds
     * @return array<string,array>
     */
    public function getStorageInfoForProducts(array $productIds): array
    {
        $cleanIds = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $cleanIds[$id] = true;
            }
        }
        if (empty($cleanIds)) {
            return [];
        }

        $info = [];
        foreach (array_keys($cleanIds) as $pid) {
            $info[$pid] = [
                'total_qty' => 0,
                'racks' => [],
                'status' => 'missing',
            ];
        }

        foreach ($this->firebase->getActiveRacks() as $rack) {
            $rackType = (string) ($rack['rack_type'] ?? 'storage');
            if ($rackType !== 'storage') {
                continue;
            }
            $rackId = (string) ($rack['id'] ?? '');
            $rackName = trim((string) ($rack['name'] ?? ($rack['rack_name'] ?? '')));
            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts)) {
                continue;
            }

            foreach (array_keys($cleanIds) as $pid) {
                if (! array_key_exists($pid, $rackProducts)) {
                    continue;
                }
                $rp = $rackProducts[$pid];
                $qty = is_array($rp) ? max(0, (int) ($rp['current_qty'] ?? 0)) : 0;
                $info[$pid]['racks'][] = [
                    'rack_id' => $rackId,
                    'rack_name' => $rackName !== '' ? $rackName : $rackId,
                    'qty' => $qty,
                ];
                $info[$pid]['total_qty'] += $qty;
                // If we found product in any storage rack, upgrade status from 'missing' to 'empty' or 'available'
                if ($info[$pid]['status'] === 'missing') {
                    $info[$pid]['status'] = 'empty';
                }
            }
        }

        foreach ($info as $pid => $data) {
            if ($data['total_qty'] > 0) {
                $info[$pid]['status'] = 'available';
            }
            // Sort racks by qty desc so the most-stocked rack appears first
            usort($info[$pid]['racks'], fn($a, $b) => $b['qty'] <=> $a['qty']);
        }

        return $info;
    }

    /**
     * Get map of rack => assigned products for all active racks.
     */
    public function getAllRackProductsMap()
    {
        // Read master products ONCE (avoids N+1 from getRackProducts calling getProducts per rack)
        $masterMap = [];
        foreach ($this->getProducts() as $product) {
            $masterMap[(string) ($product['id'] ?? '')] = $product;
        }

        $map = [];

        // getActiveRacks() reads the full waiter_racks node which includes products sub-nodes
        foreach ($this->firebase->getActiveRacks() as $rack) {
            $rackId = trim((string) ($rack['id'] ?? ''));
            if ($rackId === '') {
                continue;
            }

            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts)) {
                $map[$rackId] = [];
                continue;
            }

            $products = [];
            foreach ($rackProducts as $productId => $assignment) {
                $productId = trim((string) $productId);
                if ($productId === '') {
                    continue;
                }

                $masterProduct = $masterMap[$productId] ?? [];
                if (isset($masterProduct['is_active']) && ($masterProduct['is_active'] === false)) {
                    continue;
                }

                $fallbackName = trim((string) ($assignment['product_name'] ?? ''));
                $fallbackUnit = trim((string) ($assignment['product_unit'] ?? 'pcs'));

                $products[] = [
                    'id' => $productId,
                    'name' => (string) ($masterProduct['name'] ?? ($fallbackName !== '' ? $fallbackName : ('Produk #'.$productId))),
                    'standard_qty' => isset($assignment['standard_qty'])
                        ? max(0, (int) $assignment['standard_qty'])
                        : max(0, (int) ($masterProduct['standard_qty'] ?? 0)),
                    'min_qty' => max(0, (int) ($assignment['min_qty'] ?? 0)),
                    'current_qty' => array_key_exists('current_qty', $assignment) && $assignment['current_qty'] !== null
                        ? max(0, (int) $assignment['current_qty'])
                        : null,
                    'last_updated_at' => $assignment['last_updated_at'] ?? ($assignment['updated_at'] ?? null),
                    'unit' => (string) ($masterProduct['unit'] ?? ($fallbackUnit !== '' ? $fallbackUnit : 'pcs')),
                    'is_active' => true,
                    'assigned_at' => $assignment['assigned_at'] ?? null,
                    'updated_at' => $assignment['updated_at'] ?? null,
                ];
            }

            usort($products, function ($a, $b) {
                return ($a['name'] ?? '') <=> ($b['name'] ?? '');
            });

            $map[$rackId] = $products;
        }

        return $map;
    }

    /**
     * Get total current qty for one product across all active storage racks.
     */
    public function getTotalStorageQtyForProduct(string $productId): int
    {
        $productId = trim($productId);
        if ($productId === '') {
            return 0;
        }

        $total = 0;
        foreach ($this->firebase->getActiveRacks() as $rack) {
            $rackType = (string) ($rack['rack_type'] ?? 'storage');
            if ($rackType !== 'storage') {
                continue;
            }

            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts) || ! array_key_exists($productId, $rackProducts)) {
                continue;
            }

            $currentQty = $rackProducts[$productId]['current_qty'] ?? 0;
            $total += max(0, (int) $currentQty);
        }

        return $total;
    }

    /**
     * Get detailed stock summary untuk produk: total per type + breakdown per rak.
     *
     * @return array{
     *   total_storage: int,        // Stok di gudang
     *   total_display: int,        // Stok di rak jualan
     *   total_all: int,            // Total keseluruhan
     *   by_rack: array<int, array{rack_id: string, rack_name: string, rack_type: string, current_qty: int, standard_qty: int}>,
     * }
     */
    public function getProductStockSummary(string $productId): array
    {
        $productId = trim($productId);
        $result = [
            'total_storage' => 0,
            'total_display' => 0,
            'total_all' => 0,
            'by_rack' => [],
        ];
        if ($productId === '') {
            return $result;
        }

        foreach ($this->firebase->getActiveRacks() as $rack) {
            $rackId = trim((string) ($rack['id'] ?? ''));
            if ($rackId === '') {
                continue;
            }
            $rackType = (string) ($rack['rack_type'] ?? 'storage');
            $rackName = (string) ($rack['name'] ?? '-');

            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts) || ! array_key_exists($productId, $rackProducts)) {
                continue;
            }

            $rp = (array) $rackProducts[$productId];
            $currentQty = max(0, (int) ($rp['current_qty'] ?? 0));
            $standardQty = max(0, (int) ($rp['standard_qty'] ?? 0));

            if ($currentQty === 0 && $standardQty === 0) {
                continue;
            }

            $result['by_rack'][] = [
                'rack_id' => $rackId,
                'rack_name' => $rackName,
                'rack_type' => $rackType,
                'current_qty' => $currentQty,
                'standard_qty' => $standardQty,
            ];

            if ($rackType === 'storage') {
                $result['total_storage'] += $currentQty;
            } else {
                $result['total_display'] += $currentQty;
            }
            $result['total_all'] += $currentQty;
        }

        // Sort: storage racks dulu, lalu display, alphabetic dalam grup
        usort($result['by_rack'], function ($a, $b) {
            if ($a['rack_type'] !== $b['rack_type']) {
                return $a['rack_type'] === 'storage' ? -1 : 1;
            }

            return strcasecmp($a['rack_name'], $b['rack_name']);
        });

        return $result;
    }

    /**
     * Dual-write a master product into MySQL when the flag is on. Idempotent
     * via firebase_legacy_key. Mirrors verbatim payload into firebase_payload.
     */
    protected function dualWriteRackProductToMysql(string $firebaseKey, array $payload): void
    {
        if (! config('features.mysql_rack_products')) {
            return;
        }

        try {
            \App\Models\RackProduct::updateOrCreate(
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

    /**
     * Reconstruct the RTDB master-product shape from a MySQL row. Prefers
     * verbatim firebase_payload; falls back to columns for pre-payload rows.
     */
    protected function rackProductRowToPayload(\App\Models\RackProduct $row): array
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

    public function getProductAuditTrail(string $productId, int $limit = 200): array
    {
        $productId = trim($productId);
        if ($productId === '') return [];
        $rackMap = [];
        try {
            foreach ($this->firebase->getRacks() as $rack) {
                $rid = trim((string) ($rack['id'] ?? ''));
                if ($rid !== '') $rackMap[$rid] = (string) ($rack['name'] ?? $rack['rack_name'] ?? $rid);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        $timeline = [];
        try {
            $snap = $this->database->getReference('rack_stock_movements')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $id => $mv) {
                if (!is_array($mv)) continue;
                $createdAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($mv['created_at'] ?? 0));
                $rackId = trim((string) ($mv['rack_id'] ?? ''));
                $rackName = $rackMap[$rackId] ?? (string) ($mv['rack_name'] ?? $rackId ?: '-');
                $actor = (string) ($mv['actor_name'] ?? $mv['waiter_name'] ?? 'Sistem');
                $delta = (int) ($mv['delta'] ?? $mv['delta_qty'] ?? 0);
                // Real schema: no 'prev'/'result' field. Derive from to_qty/current_qty/actual_qty.
                if (array_key_exists('result', $mv)) {
                    $result = (int) $mv['result'];
                } elseif (array_key_exists('to_qty', $mv)) {
                    $result = (int) $mv['to_qty'];
                } elseif (array_key_exists('current_qty', $mv)) {
                    $result = (int) $mv['current_qty'];
                } elseif (array_key_exists('actual_qty', $mv)) {
                    $result = (int) $mv['actual_qty'];
                } else {
                    $result = 0;
                }
                $prev = array_key_exists('prev', $mv) ? (int) $mv['prev'] : ($result - $delta);
                $type = (string) ($mv['type'] ?? $mv['movement_type'] ?? 'stock_take');
                $summary = 'Movement stok';
                if ($type === 'stock_take') $summary = ucfirst("Cek rak: {$prev}→{$result} (" . sprintf('%+d', $delta) . ") oleh {$actor} di {$rackName}");
                elseif ($type === 'po_receive') $summary = ucfirst("Terima PO: +" . max(0, $delta) . " pcs ke {$rackName} oleh {$actor}");
                elseif ($type === 'storage_out') $summary = ucfirst("Ambil stok: -" . abs($delta) . " pcs dari {$rackName} oleh {$actor}");
                $timeline[] = ['kind' => 'movement', 'created_at' => $createdAt, 'event_id' => (string) $id, 'data' => $mv, 'rack_id' => $rackId, 'rack_name' => $rackName, 'actor_name' => $actor, 'summary' => $summary];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail movement: ' . $e->getMessage()));
        }
        try {
            $snap = $this->database->getReference('restock_requests')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $id => $req) {
                if (!is_array($req)) continue;
                $createdAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($req['created_at'] ?? $req['reported_at'] ?? 0));
                $rackId = trim((string) ($req['rack_id'] ?? ''));
                $rackName = $rackMap[$rackId] ?? (string) ($req['rack_name'] ?? $rackId ?: '-');
                $qtyNeeded = (int) ($req['qty_needed'] ?? 0);
                $source = (string) ($req['source'] ?? '-');
                $status = (string) ($req['status'] ?? '-');
                $timeline[] = ['kind' => 'restock_request', 'created_at' => $createdAt, 'event_id' => (string) $id, 'data' => $req, 'rack_id' => $rackId, 'rack_name' => $rackName, 'summary' => ucfirst("Restock request: {$qtyNeeded} pcs untuk {$rackName} (source: {$source}, status: {$status})")];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail restock_requests: ' . $e->getMessage()));
        }
        try {
            $cutoff = time() - (90 * 86400);
            foreach ($this->firebase->getPurchaseOrders() as $po) {
                if (!is_array($po)) continue;
                $createdAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($po['created_at'] ?? 0));
                if ($createdAt < $cutoff) continue;
                $matchQty = 0;
                foreach ((array) ($po['items'] ?? []) as $item) if (is_array($item) && (string) ($item['product_id'] ?? '') === $productId) $matchQty += (int) ($item['qty_ordered'] ?? 0);
                if ($matchQty <= 0) continue;
                $poId = (string) ($po['id'] ?? $po['po_id'] ?? '');
                $poNumber = (string) ($po['po_number'] ?? $poId ?: '-');
                $supplier = (string) ($po['supplier_name'] ?? $po['supplier'] ?? '-');
                $status = (string) ($po['status'] ?? '-');
                $timeline[] = ['kind' => 'purchase_order', 'created_at' => $createdAt, 'event_id' => ($poId !== '' ? $poId : $poNumber), 'data' => $po, 'summary' => ucfirst("PO #{$poNumber}: {$matchQty} pcs dari {$supplier} ({$status})")];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail purchase_orders: ' . $e->getMessage()));
        }
        try {
            $snap = $this->database->getReference('audit_logs/stock_anomalies')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $id => $an) {
                if (!is_array($an)) continue;
                $createdAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($an['created_at'] ?? 0));
                $rackId = trim((string) ($an['rack_id'] ?? ''));
                $rackName = $rackMap[$rackId] ?? (string) ($an['rack_name'] ?? $rackId ?: '-');
                $prev = (int) ($an['prev'] ?? 0);
                $result = (int) ($an['result'] ?? 0);
                $severity = (string) ($an['severity'] ?? 'low');
                $timeline[] = ['kind' => 'anomaly', 'created_at' => $createdAt, 'event_id' => (string) $id, 'data' => $an, 'rack_id' => $rackId, 'rack_name' => $rackName, 'actor_name' => (string) ($an['actor_name'] ?? ''), 'summary' => ucfirst("⚠️ Anomali: {$prev}→{$result} (severity: {$severity})")];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail stock_anomalies: ' . $e->getMessage()));
        }
        usort($timeline, fn($a, $b) => ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0)));
        return array_slice($timeline, 0, max(1, $limit));
    }

    public function getProductStats(string $productId): array
    {
        $productId = trim($productId);
        $stats = ['total_in' => 0, 'total_out' => 0, 'last_movement_at' => 0, 'active_restock_requests' => 0, 'open_pos_containing' => 0, 'racks_holding' => []];
        if ($productId === '') return $stats;
        $cutoff30 = time() - (30 * 86400);
        try {
            $snap = $this->database->getReference('rack_stock_movements')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $mv) {
                if (!is_array($mv)) continue;
                $createdAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($mv['created_at'] ?? 0));
                $delta = (int) ($mv['delta'] ?? $mv['delta_qty'] ?? 0);
                if ($createdAt > $stats['last_movement_at']) $stats['last_movement_at'] = $createdAt;
                if ($createdAt >= $cutoff30) {
                    if ($delta > 0) $stats['total_in'] += $delta;
                    if ($delta < 0) $stats['total_out'] += abs($delta);
                }
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats movements: ' . $e->getMessage()));
        }
        try {
            $pending = $this->database->getReference('restock_requests')->orderByChild('status')->equalTo('pending')->getSnapshot();
            if ($pending->exists()) foreach ((array) $pending->getValue() as $req) if (is_array($req) && (string) ($req['product_id'] ?? '') === $productId) $stats['active_restock_requests']++;
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats restocks: ' . $e->getMessage()));
        }
        try {
            foreach ($this->firebase->getPurchaseOrders() as $po) {
                if (!is_array($po) || !in_array((string) ($po['status'] ?? ''), ['ordered', 'partial'], true)) continue;
                foreach ((array) ($po['items'] ?? []) as $item) if (is_array($item) && (string) ($item['product_id'] ?? '') === $productId) {
                    $stats['open_pos_containing']++;
                    break;
                }
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats purchase_orders: ' . $e->getMessage()));
        }
        try {
            foreach ($this->firebase->getRacks() as $rack) {
                $rackId = trim((string) ($rack['id'] ?? ''));
                if ($rackId === '') continue;
                $snap = $this->database->getReference("waiter_racks/{$rackId}/products/{$productId}")->getSnapshot();
                $row = $snap->exists() ? $snap->getValue() : null;
                if (is_array($row) && (int) ($row['current_qty'] ?? 0) > 0) $stats['racks_holding'][] = $rackId;
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats racks_holding: ' . $e->getMessage()));
        }
        return $stats;
    }
}
