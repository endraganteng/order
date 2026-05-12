<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;

class FirebaseService
{
    protected $database;

    protected $auth;

    public function __construct(Database $database, Auth $auth)
    {
        $this->database = $database;
        $this->auth = $auth;
    }

    /**
     * Get all allowed waiter emails
     */
    public function getAllowedEmails()
    {
        $reference = $this->database->getReference('allowed_waiters');
        $snapshot = $reference->getSnapshot();

        $waiters = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $waiter) {
                $merged = array_merge(['id' => $key], $waiter);
                $merged['waiter_role'] = $this->normalizeWaiterRole($merged['waiter_role'] ?? 'pelayan');
                $waiters[] = $merged;
            }
        }

        return $waiters;
    }

    /**
     * Add new allowed email
     */
    public function addAllowedEmail($email, $name)
    {
        $this->addAllowedEmailWithPassword($email, $name, null);
    }

    /**
     * Add new waiter account (with optional password hash).
     */
    public function addAllowedEmailWithPassword($email, $name, $passwordHash = null, $waiterRole = 'pelayan', $shiftId = null, $phone = null)
    {
        $payload = [
            'email' => strtolower(trim((string) $email)),
            'name' => trim((string) $name),
            'waiter_role' => $this->normalizeWaiterRole($waiterRole),
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
            $payload['phone'] = trim((string) $phone);
        }

        $this->database->getReference('allowed_waiters')->push($payload);
    }

    /**
     * Update allowed email
     */
    public function updateAllowedEmail($id, $data)
    {
        if (array_key_exists('waiter_role', $data)) {
            $data['waiter_role'] = $this->normalizeWaiterRole($data['waiter_role']);
        }

        $this->database->getReference('allowed_waiters/'.$id)
            ->update($data);
    }

    /**
     * Get active waiter accounts only.
     */
    public function getActiveWaiters()
    {
        return array_values(array_filter($this->getAllowedEmails(), function ($waiter) {
            return ($waiter['is_active'] ?? true) !== false;
        }));
    }

    /**
     * Get active waiters who are still required to attend.
     */
    public function getAttendanceEligibleWaiters(): array
    {
        $waiters = array_values(array_filter($this->getActiveWaiters(), function ($waiter) {
            return empty($waiter['attendance_exempt']);
        }));

        usort($waiters, function ($a, $b) {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $waiters;
    }

    /**
     * Get active waiters by role.
     */
    public function getActiveWaitersByRole($waiterRole)
    {
        $normalizedRole = $this->normalizeWaiterRole($waiterRole);

        return array_values(array_filter($this->getActiveWaiters(), function ($waiter) use ($normalizedRole) {
            return $this->normalizeWaiterRole($waiter['waiter_role'] ?? 'pelayan') === $normalizedRole;
        }));
    }

    /**
     * Get waiter by id.
     */
    public function getWaiterById($id)
    {
        $waiters = $this->getAllowedEmails();

        foreach ($waiters as $waiter) {
            if (($waiter['id'] ?? null) === $id) {
                return $waiter;
            }
        }

        return null;
    }

    /**
     * Get waiter by email.
     */
    public function getWaiterByEmail($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        foreach ($this->getAllowedEmails() as $waiter) {
            $waiterEmail = strtolower(trim((string) ($waiter['email'] ?? '')));
            if ($waiterEmail === $email) {
                return $waiter;
            }
        }

        return null;
    }

    /**
     * Verify waiter credentials.
     */
    public function verifyWaiterCredentials($email, $password)
    {
        $password = (string) $password;
        $waiter = $this->getWaiterByEmail($email);

        if (! $waiter) {
            return null;
        }

        if (($waiter['is_active'] ?? true) === false) {
            return null;
        }

        $hash = $waiter['password_hash'] ?? null;
        if (! is_string($hash) || trim($hash) === '') {
            return null;
        }

        if (! Hash::check($password, $hash)) {
            return null;
        }

        return $waiter;
    }

    /**
     * Verify waiter login using Firebase Google auth id token.
     */
    public function verifyWaiterGoogleToken($idToken)
    {
        $idToken = trim((string) $idToken);
        if ($idToken === '') {
            return null;
        }

        try {
            $verifiedToken = $this->auth->verifyIdToken($idToken, true);
        } catch (\Throwable $e) {
            return null;
        }

        $claims = $verifiedToken->claims();
        if (! $claims->has('email')) {
            return null;
        }

        $email = strtolower(trim((string) $claims->get('email')));
        $emailVerified = $claims->has('email_verified')
            ? (bool) $claims->get('email_verified')
            : false;

        if (! $claims->has('sub')) {
            return null;
        }

        $firebaseUid = trim((string) $claims->get('sub'));
        if ($firebaseUid === '') {
            return null;
        }

        $firebaseClaim = $claims->has('firebase') ? $claims->get('firebase') : [];
        $provider = is_array($firebaseClaim)
            ? (string) ($firebaseClaim['sign_in_provider'] ?? '')
            : '';

        if ($provider !== 'google.com' || $email === '' || ! $emailVerified) {
            return null;
        }

        $waiter = $this->getWaiterByEmail($email);
        if (! $waiter) {
            return null;
        }

        if (($waiter['is_active'] ?? true) === false) {
            return null;
        }

        $storedFirebaseUid = trim((string) ($waiter['firebase_uid'] ?? ''));
        if ($storedFirebaseUid !== '' && $storedFirebaseUid !== $firebaseUid) {
            return null;
        }

        if ($storedFirebaseUid === '' && ! empty($waiter['id'])) {
            $this->database->getReference('allowed_waiters/'.$waiter['id'])->update([
                'firebase_uid' => $firebaseUid,
                'updated_at' => time(),
            ]);

            $waiter['firebase_uid'] = $firebaseUid;
        }

        return $waiter;
    }

    /**
     * Delete allowed email
     */
    public function deleteAllowedEmail($id)
    {
        $this->database->getReference('allowed_waiters/'.$id)
            ->remove();
    }

    /**
     * Get all rack master data.
     */
    public function getRacks()
    {
        $reference = $this->database->getReference('waiter_racks');
        $snapshot = $reference->getSnapshot();

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

        return $racks;
    }

    /**
     * Get active rack master data.
     */
    public function getActiveRacks()
    {
        return array_values(array_filter($this->getRacks(), function ($rack) {
            return ($rack['is_active'] ?? true) !== false;
        }));
    }

    /**
     * Get rack by id.
     */
    public function getRackById($id)
    {
        foreach ($this->getRacks() as $rack) {
            if (($rack['id'] ?? null) === $id) {
                return $rack;
            }
        }

        return null;
    }

    /**
     * Create new rack with generated barcode value.
     */
    public function createRack(array $data)
    {
        $name = trim((string) ($data['name'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        $payload = [
            'name' => $name,
            'location' => $location,
            'description' => $description,
            'barcode_value' => $this->generateUniqueRackBarcodeValue($name),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'rack_type' => in_array($data['rack_type'] ?? '', ['display', 'storage']) ? $data['rack_type'] : 'storage',
            'check_order' => max(0, (int) ($data['check_order'] ?? 0)),
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $created = $this->database->getReference('waiter_racks')->push($payload);

        return array_merge(['id' => $created->getKey()], $payload);
    }

    /**
     * Update rack metadata.
     */
    public function updateRack($id, array $data)
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'location' => trim((string) ($data['location'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'rack_type' => in_array($data['rack_type'] ?? '', ['display', 'storage']) ? $data['rack_type'] : 'storage',
            'check_order' => max(0, (int) ($data['check_order'] ?? 0)),
            'updated_at' => time(),
        ];

        $this->database->getReference('waiter_racks/'.$id)->update($payload);
    }

    /**
     * Regenerate rack barcode value.
     */
    public function regenerateRackBarcode($id)
    {
        $rack = $this->getRackById($id);
        if (! $rack) {
            return null;
        }

        $barcode = $this->generateUniqueRackBarcodeValue((string) ($rack['name'] ?? 'RAK'));
        $this->database->getReference('waiter_racks/'.$id)->update([
            'barcode_value' => $barcode,
            'updated_at' => time(),
        ]);

        return $barcode;
    }

    /**
     * Delete rack by id.
     */
    public function deleteRack($id)
    {
        $this->database->getReference('waiter_racks/'.$id)->remove();
    }

    /**
     * Get completed rack-check task history for a specific rack.
     */
    public function getRackCheckHistory(string $rackId, ?int $limit = 50): array
    {
        $reference = $this->database->getReference('waiter_tasks')
            ->orderByChild('rack_id')
            ->equalTo($rackId);
        $snapshot = $reference->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                if (($task['task_type'] ?? '') !== 'rack_check') {
                    continue;
                }
                if (($task['status'] ?? '') !== 'done') {
                    continue;
                }
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        usort($tasks, function ($a, $b) {
            return ($b['completed_at'] ?? 0) - ($a['completed_at'] ?? 0);
        });

        if ($limit !== null && count($tasks) > $limit) {
            $tasks = array_slice($tasks, 0, $limit);
        }

        return $tasks;
    }

    /**
     * =========================================================================
     *  PRODUCT CATEGORIES
     * =========================================================================
     */

    /**
     * Get all product categories.
     */
    public function getProductCategories()
    {
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

        $created = $this->database->getReference('product_categories')->push($payload);

        return array_merge(['id' => $created->getKey()], $payload);
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
    }

    /**
     * =========================================================================
     *  MASTER PRODUCTS
     * =========================================================================
     */

    /**
     * Get all master products.
     */
    public function getProducts()
    {
        $reference = $this->database->getReference('rack_products');
        $snapshot = $reference->getSnapshot();

        $products = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $product) {
                $products[] = array_merge(['id' => $key], $product);
            }
        }

        usort($products, function ($a, $b) {
            return ($a['name'] ?? '') <=> ($b['name'] ?? '');
        });

        return $products;
    }

    /**
     * Get active master products.
     */
    public function getActiveProducts()
    {
        return array_values(array_filter($this->getProducts(), function ($product) {
            return ($product['is_active'] ?? true) !== false;
        }));
    }

    /**
     * Get product by id.
     */
    public function getProductById($id)
    {
        $reference = $this->database->getReference('rack_products/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    /**
     * Create master product.
     */
    public function createProduct(array $data)
    {
        $categoryId = isset($data['category_id']) && $data['category_id'] !== '' ? (string) $data['category_id'] : null;

        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'category_id' => $categoryId,
            'standard_qty' => max(0, (int) ($data['standard_qty'] ?? 0)),
            'unit' => trim((string) ($data['unit'] ?? 'pcs')),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $created = $this->database->getReference('rack_products')->push($payload);

        return array_merge(['id' => $created->getKey()], $payload);
    }

    /**
     * Update master product.
     */
    public function updateProduct($id, array $data)
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

        $this->database->getReference('rack_products/'.$id)->update($payload);
    }

    /**
     * Delete master product and remove all rack assignments.
     */
    public function deleteProduct($id)
    {
        $racksReference = $this->database->getReference('waiter_racks');
        $racksSnapshot = $racksReference->getSnapshot();

        if ($racksSnapshot->exists()) {
            $updates = [];

            foreach ($racksSnapshot->getValue() as $rackId => $rack) {
                $rackProducts = $rack['products'] ?? [];
                if (! is_array($rackProducts) || ! array_key_exists($id, $rackProducts)) {
                    continue;
                }

                $updates[$rackId.'/products/'.$id] = null;
            }

            if (! empty($updates)) {
                $racksReference->update($updates);
            }
        }

        $this->database->getReference('rack_products/'.$id)->remove();
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
                if ($productId === '' || ! isset($masterMap[$productId])) {
                    continue;
                }

                $masterProduct = $masterMap[$productId];
                $products[] = [
                    'id' => $productId,
                    'name' => (string) ($masterProduct['name'] ?? ''),
                    'standard_qty' => isset($assignment['standard_qty'])
                        ? max(0, (int) $assignment['standard_qty'])
                        : max(0, (int) ($masterProduct['standard_qty'] ?? 0)),
                    'min_qty' => max(0, (int) ($assignment['min_qty'] ?? 0)),
                    'unit' => (string) ($masterProduct['unit'] ?? 'pcs'),
                    'is_active' => ($masterProduct['is_active'] ?? true) !== false,
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
                    'assigned_at' => $existingProducts[$productId]['assigned_at'] ?? $now,
                    'updated_at' => $now,
                ];
            }

            $reference->set($payload);
        }
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
        foreach ($this->getActiveRacks() as $rack) {
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
                if ($productId === '' || ! isset($masterMap[$productId])) {
                    continue;
                }

                $masterProduct = $masterMap[$productId];
                if (($masterProduct['is_active'] ?? true) === false) {
                    continue;
                }

                $products[] = [
                    'id' => $productId,
                    'name' => (string) ($masterProduct['name'] ?? ''),
                    'standard_qty' => isset($assignment['standard_qty'])
                        ? max(0, (int) $assignment['standard_qty'])
                        : max(0, (int) ($masterProduct['standard_qty'] ?? 0)),
                    'min_qty' => max(0, (int) ($assignment['min_qty'] ?? 0)),
                    'unit' => (string) ($masterProduct['unit'] ?? 'pcs'),
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
     * Get rack types map (rackId => rack_type).
     */
    public function getRackTypesMap(): array
    {
        $map = [];
        foreach ($this->getActiveRacks() as $rack) {
            $rackId = trim((string) ($rack['id'] ?? ''));
            if ($rackId === '') {
                continue;
            }
            $map[$rackId] = (string) ($rack['rack_type'] ?? 'storage');
        }

        return $map;
    }

    /**
     * Get app settings
     */
    public function getSettings()
    {
        $reference = $this->database->getReference('settings');
        $snapshot = $reference->getSnapshot();

        if ($snapshot->exists()) {
            return $snapshot->getValue();
        }

        // Default settings
        return [
            'order_timeout_minutes' => 3,
        ];
    }

    /**
     * Update settings
     */
    public function updateSettings($data)
    {
        $this->database->getReference('settings')
            ->update($data);
    }

    /**
     * Get all active orders
     */
    public function getOrders()
    {
        $reference = $this->database->getReference('orders');
        $snapshot = $reference->getSnapshot();

        $orders = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $order) {
                $orders[] = array_merge(['id' => $key], $order);
            }
        }

        return $orders;
    }

    /**
     * Get orders filtered by date range using Firebase query.
     * Much more efficient than getOrders() when you only need a specific period.
     *
     * @param  int  $startTimestamp  Unix timestamp for range start (inclusive)
     * @param  int  $endTimestamp    Unix timestamp for range end (inclusive)
     * @return array
     */
    public function getOrdersByDateRange(int $startTimestamp, int $endTimestamp): array
    {
        $reference = $this->database->getReference('orders')
            ->orderByChild('created_at')
            ->startAt($startTimestamp)
            ->endAt($endTimestamp);

        $snapshot = $reference->getSnapshot();

        $orders = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $order) {
                $orders[] = array_merge(['id' => $key], (array) $order);
            }
        }

        return $orders;
    }

    /**
     * Get orders for a specific date (convenience wrapper).
     *
     * @param  string  $date  Format 'Y-m-d'
     * @return array
     */
    public function getOrdersByDate(string $date): array
    {
        $startOfDay = strtotime($date . ' 00:00:00');
        $endOfDay   = strtotime($date . ' 23:59:59');

        return $this->getOrdersByDateRange($startOfDay, $endOfDay);
    }

    /**
     * Create new order
     */
    public function createOrder($orderData)
    {
        // 1. Get current queue counter
        $today = date('Y-m-d');
        $counterRef = $this->database->getReference('settings/queue_counter');
        $counterSnapshot = $counterRef->getSnapshot();

        $currentCounter = 0;

        if ($counterSnapshot->exists()) {
            $data = $counterSnapshot->getValue();
            // Reset if different date
            if (isset($data['date']) && $data['date'] === $today) {
                $currentCounter = $data['current'];
            }
        }

        // 2. Increment counter
        $newCounter = $currentCounter + 1;

        // 3. Update counter in DB
        $counterRef->set([
            'date' => $today,
            'current' => $newCounter,
        ]);

        // 4. Add queue number to order data
        $orderData['queue_number'] = $newCounter;

        // 5. Save order
        $this->database->getReference('orders')
            ->push($orderData);
    }

    /**
     * Get order statistics per user
     */
    public function getUserOrderStats()
    {
        $ordersRef = $this->database->getReference('orders');
        $ordersSnapshot = $ordersRef->getSnapshot();

        $stats = [];

        if ($ordersSnapshot->exists()) {
            foreach ($ordersSnapshot->getValue() as $order) {
                $waiterId = $order['waiter_id'] ?? 'unknown';
                $waiterName = $order['waiter_name'] ?? 'Unknown';
                $waiterEmail = $order['waiter_email'] ?? '';

                if (! isset($stats[$waiterId])) {
                    $stats[$waiterId] = [
                        'waiter_id' => $waiterId,
                        'waiter_name' => $waiterName,
                        'waiter_email' => $waiterEmail,
                        'order_count' => 0,
                    ];
                }

                $stats[$waiterId]['order_count']++;
            }
        }

        // Sort by order count descending
        usort($stats, function ($a, $b) {
            return $b['order_count'] - $a['order_count'];
        });

        return $stats;
    }

    /**
     * Delete orders older than specified days
     */
    public function cleanupOldOrders($daysOld = 30)
    {
        $ordersRef = $this->database->getReference('orders');
        $ordersSnapshot = $ordersRef->getSnapshot();

        $deletedCount = 0;
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);

        if ($ordersSnapshot->exists()) {
            $updates = [];

            foreach ($ordersSnapshot->getValue() as $key => $order) {
                $createdAt = $order['created_at'] ?? 0;

                if ($createdAt < $cutoffTime) {
                    $updates[$key] = null; // null value means delete
                    $deletedCount++;
                }
            }

            if (! empty($updates)) {
                $ordersRef->update($updates);
            }
        }

        return $deletedCount;
    }

    /**
     * Get cleanup statistics
     */
    public function getCleanupStats()
    {
        $ordersRef = $this->database->getReference('orders');
        $ordersSnapshot = $ordersRef->getSnapshot();

        $stats = [
            'total_orders' => 0,
            'orders_30_days' => 0,
            'orders_60_days' => 0,
            'orders_90_days' => 0,
        ];

        if ($ordersSnapshot->exists()) {
            $now = time();

            foreach ($ordersSnapshot->getValue() as $order) {
                $stats['total_orders']++;
                $createdAt = $order['created_at'] ?? 0;
                $ageInDays = ($now - $createdAt) / (24 * 60 * 60);

                if ($ageInDays > 90) {
                    $stats['orders_90_days']++;
                } elseif ($ageInDays > 60) {
                    $stats['orders_60_days']++;
                } elseif ($ageInDays > 30) {
                    $stats['orders_30_days']++;
                }
            }
        }

        return $stats;
    }

    // ========================================
    // Waiter Task Management (Supervisor → Waiter)
    // ========================================

    /**
     * Create one-off waiter tasks for single/all assignees.
     */
    public function createWaiterTasksFromAssignment(array $data)
    {
        $assignmentType = $data['assignment_type'] ?? 'all';
        $assignedWaiterId = $data['assigned_waiter_id'] ?? null;
        $assignedWaiterRole = $data['assigned_waiter_role'] ?? null;
        $selectedWaiterIds = $data['selected_waiter_ids'] ?? [];
        $targetWaiters = $this->resolveTargetWaiters($assignmentType, $assignedWaiterId, $assignedWaiterRole, $selectedWaiterIds);
        $count = 0;
        $createdEntries = [];

        foreach ($targetWaiters as $waiter) {
            $taskData = $this->buildWaiterTaskPayload($data, $waiter, [
                'is_recurring_instance' => false,
                'scheduled_time' => null,
                'scheduled_for_date' => $data['scheduled_for_date'] ?? null,
                'source_template_id' => null,
                'time_limit_minutes' => null,
                'deadline_at' => $data['deadline_at'] ?? null,
                'recurrence_type' => null,
            ]);

            $this->database->getReference('waiter_tasks')->push($taskData);
            $createdEntries[] = ['waiter' => $waiter, 'task' => $taskData];
            $count++;
        }

        return ['count' => $count, 'entries' => $createdEntries];
    }

    /**
     * Create a refill task for display rack shortage (auto-generated).
     * Assigns to the same waiter who reported the shortage.
     */
    public function createDisplayRefillTask(
        string $waiterId,
        string $waiterName,
        string $rackId,
        string $rackName,
        array $shortageItems
    ): ?string {
        if (empty($shortageItems)) return null;

        // Build description listing all shortage items
        $lines = [];
        foreach ($shortageItems as $item) {
            $productName = $item['product_name'] ?? '';
            $needed = (int) ($item['qty_needed'] ?? 0);
            $lines[] = "• {$productName}: ambil {$needed} pcs dari gudang";
        }
        $description = "Isi ulang rak display dari gudang:\n" . implode("\n", $lines);

        $taskData = [
            'title' => "Isi ulang {$rackName} dari gudang",
            'description' => $description,
            'task_type' => 'general',
            'status' => 'pending',
            'assigned_waiter_id' => $waiterId,
            'assigned_waiter_name' => $waiterName,
            'assignment_type' => 'single',
            'created_at' => time(),
            'created_by' => 'system',
            'created_by_name' => 'Sistem Otomatis',
            'scheduled_for_date' => date('Y-m-d'),
            'deadline_at' => null,
            'requires_photo_proof' => false,
            'requires_photo_before' => false,
            'repeat_count' => 1,
            'completed_count' => 0,
            'completions' => [],
            'category_id' => null,
            'category_name' => null,
            'rack_id' => $rackId,
            'rack_name' => $rackName,
            'refill_source' => 'display_shortage',
            'refill_items' => $shortageItems,
            'is_recurring_instance' => false,
            'source_template_id' => null,
        ];

        $ref = $this->database->getReference('waiter_tasks')->push($taskData);
        return $ref->getKey();
    }

    /**
     * Bulk reassign pending/in_progress tasks from one waiter to another for a given date.
     */
    public function bulkReassignPendingTasks(string $fromWaiterId, string $toWaiterId, string $date): int
    {
        $toWaiter = $this->getWaiterById($toWaiterId);
        if (! $toWaiter) {
            return 0;
        }

        $reference = $this->database->getReference('waiter_tasks');
        $snapshot = $reference->orderByChild('assigned_waiter_id')->equalTo($fromWaiterId)->getSnapshot();

        if (! $snapshot->exists()) {
            return 0;
        }

        $reassignedCount = 0;
        $toWaiterName = trim(($toWaiter['name'] ?? ''));

        foreach ($snapshot->getValue() as $taskId => $task) {
            $taskDate = $task['scheduled_for_date'] ?? '';
            $status = $task['status'] ?? '';

            if ($taskDate !== $date || ! in_array($status, ['pending', 'in_progress'])) {
                continue;
            }

            $this->database->getReference('waiter_tasks/'.$taskId)->update([
                'assigned_waiter_id' => $toWaiterId,
                'assigned_waiter_name' => $toWaiterName,
                'reassigned_at' => time(),
                'reassigned_from' => $fromWaiterId,
            ]);
            $reassignedCount++;
        }

        return $reassignedCount;
    }

    /**
     * Log a barcode scan attempt (success or mismatch).
     */
    public function logScanAttempt(string $waiterId, string $rackId, bool $success, string $scanned, string $expected): void
    {
        $date = now()->format('Y-m-d');
        $ref = $this->database->getReference("scan_attempts/{$waiterId}/{$date}");
        $snapshot = $ref->getSnapshot();

        $data = $snapshot->exists() ? $snapshot->getValue() : ['total' => 0, 'mismatch' => 0, 'logs' => []];
        $data['total'] = ((int) ($data['total'] ?? 0)) + 1;
        if (! $success) {
            $data['mismatch'] = ((int) ($data['mismatch'] ?? 0)) + 1;
        }

        // Keep last 20 logs per waiter per day
        $logs = is_array($data['logs'] ?? null) ? $data['logs'] : [];
        $logs[] = [
            'rack_id' => $rackId,
            'scanned' => $scanned,
            'expected' => $expected,
            'success' => $success,
            'at' => time(),
        ];
        if (count($logs) > 20) {
            $logs = array_slice($logs, -20);
        }
        $data['logs'] = array_values($logs);

        $ref->set($data);
    }

    /**
     * Get scan compliance stats for a date (all waiters).
     */
    public function getScanStats(string $date): array
    {
        $ref = $this->database->getReference('scan_attempts');
        $snapshot = $ref->getSnapshot();

        $stats = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $waiterId => $dates) {
                if (! is_array($dates) || ! isset($dates[$date])) {
                    continue;
                }
                $dayData = $dates[$date];
                $stats[$waiterId] = [
                    'total' => (int) ($dayData['total'] ?? 0),
                    'mismatch' => (int) ($dayData['mismatch'] ?? 0),
                    'success' => ((int) ($dayData['total'] ?? 0)) - ((int) ($dayData['mismatch'] ?? 0)),
                    'logs' => is_array($dayData['logs'] ?? null) ? $dayData['logs'] : [],
                ];
            }
        }

        return $stats;
    }

    /**
     * Get a single waiter task by ID.
     */
    public function getWaiterTaskById(string $taskId): ?array
    {
        $snapshot = $this->database->getReference('waiter_tasks/'.$taskId)->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $taskId], $snapshot->getValue());
    }

    /**
     * Get all waiter tasks.
     */
    public function getWaiterTasks()
    {
        $reference = $this->database->getReference('waiter_tasks');
        $snapshot = $reference->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        usort($tasks, function ($a, $b) {
            return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
        });

        return $tasks;
    }

    /**
     * Get tasks assigned to one waiter.
     */
    public function getWaiterTasksByWaiterId($waiterId)
    {
        $reference = $this->database->getReference('waiter_tasks')
            ->orderByChild('assigned_waiter_id')
            ->equalTo((string) $waiterId);
        $snapshot = $reference->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        usort($tasks, function ($a, $b) {
            return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
        });

        return $tasks;
    }

    /**
     * Get waiter tasks for a specific date only (uses scheduled_for_date query).
     * More efficient than getWaiterTasksByWaiterId + PHP filter for single-date lookups.
     */
    public function getWaiterTasksForDate(string $waiterId, string $date): array
    {
        $reference = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->equalTo($date);
        $snapshot = $reference->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                if (($task['assigned_waiter_id'] ?? '') === $waiterId) {
                    $tasks[] = array_merge(['id' => $key], $task);
                }
            }
        }

        return $tasks;
    }

    /**
     * Get ALL tasks for a specific date (all waiters).
     */
    public function getWaiterTasksByDate(string $date): array
    {
        $reference = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->equalTo($date);
        $snapshot = $reference->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        return $tasks;
    }

    /**
     * Store optional waiter activity report for daily supervision.
     */
    public function createWaiterActivityReport(array $data): array
    {
        $waiterId = trim((string) ($data['waiter_id'] ?? ''));
        $activityText = trim((string) ($data['activity_text'] ?? ''));

        if ($waiterId === '') {
            return [
                'success' => false,
                'message' => 'Akun waiter tidak valid.',
            ];
        }

        if ($activityText === '') {
            return [
                'success' => false,
                'message' => 'Isi kegiatan wajib diisi sebelum disimpan.',
            ];
        }

        $reportDate = $this->normalizeReportDate($data['report_date'] ?? null);
        $items = $this->extractStockReportItems($activityText);

        $payload = [
            'waiter_id' => $waiterId,
            'waiter_name' => trim((string) ($data['waiter_name'] ?? 'Waiter')),
            'waiter_email' => trim((string) ($data['waiter_email'] ?? '')),
            'report_date' => $reportDate,
            'activity_text' => $activityText,
            'activity_items' => $items,
            'created_at' => time(),
        ];

        $ref = $this->database->getReference('waiter_activity_reports')->push($payload);

        return [
            'success' => true,
            'id' => $ref->getKey(),
        ];
    }

    /**
     * Get all waiter activity reports.
     */
    public function getWaiterActivityReports(): array
    {
        $reference = $this->database->getReference('waiter_activity_reports');
        $snapshot = $reference->getSnapshot();

        $reports = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $report) {
                $reports[] = array_merge(['id' => $key], $report);
            }
        }

        usort($reports, function ($a, $b) {
            return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
        });

        return $reports;
    }

    /**
     * Get waiter activity reports by waiter and date.
     */
    public function getWaiterActivityReportsByWaiterIdForDate(string $waiterId, ?string $reportDate = null): array
    {
        $date = $this->normalizeReportDate($reportDate);

        $reference = $this->database->getReference('waiter_activity_reports')
            ->orderByChild('waiter_id')
            ->equalTo((string) $waiterId);
        $snapshot = $reference->getSnapshot();

        $reports = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $report) {
                if ((string) ($report['report_date'] ?? '') === $date) {
                    $reports[] = array_merge(['id' => $key], $report);
                }
            }
        }

        usort($reports, function ($a, $b) {
            return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
        });

        return $reports;
    }

    /**
     * Get all waiter activity reports for one date.
     */
    public function getWaiterActivityReportsByDate(?string $reportDate = null): array
    {
        $date = $this->normalizeReportDate($reportDate);

        return array_values(array_filter($this->getWaiterActivityReports(), function ($report) use ($date) {
            return (string) ($report['report_date'] ?? '') === $date;
        }));
    }

    /**
     * Update waiter task status by assigned waiter.
     */
    public function updateWaiterTaskStatus(
        $taskId,
        $status,
        $waiterId,
        $waiterName,
        $waiterEmail = '',
        $note = null,
        $scannedBarcode = null,
        $stockReportItems = null,
        $noOutOfStock = false,
        $photoProofDataUrl = null,
        $productChecklist = null,
        $photoBeforeDataUrl = null
    ) {
        $taskReference = $this->database->getReference('waiter_tasks/'.$taskId);
        $snapshot = $taskReference->getSnapshot();

        if (! $snapshot->exists()) {
            return [
                'success' => false,
                'message' => 'Tugas tidak ditemukan.',
            ];
        }

        $task = $snapshot->getValue();
        $assignedWaiterId = (string) ($task['assigned_waiter_id'] ?? '');
        if ($assignedWaiterId === '' || $assignedWaiterId !== (string) $waiterId) {
            return [
                'success' => false,
                'message' => 'Tugas ini bukan milik akun waiter Anda.',
            ];
        }

        $currentStatus = $task['status'] ?? 'pending';
        if ($currentStatus !== 'pending' && $currentStatus !== 'in_progress') {
            return [
                'success' => false,
                'message' => 'Tugas ini sudah tidak aktif.',
            ];
        }

        $now = time();
        $deadlineAt = (int) ($task['deadline_at'] ?? 0);
        if ($deadlineAt > 0 && $now > $deadlineAt) {
            $taskReference->update([
                'status' => 'overdue',
                'completed_at' => $now,
                'completed_note' => 'Auto: batas waktu habis',
            ]);

            return [
                'success' => false,
                'message' => 'Tugas sudah melewati batas waktu dan dihitung tidak selesai.',
            ];
        }

        $requiresBarcodeScan = (bool) ($task['requires_barcode_scan'] ?? false);
        $taskType = (string) ($task['task_type'] ?? 'general');
        if ($taskType === 'rack_check') {
            $requiresBarcodeScan = true;
        }

        $requiresStockReport = $taskType === 'rack_check';
        $requiresPhotoProof = (bool) ($task['requires_photo_proof'] ?? false);
        $validatedExpectedBarcode = null;

        $normalizedPhoto = $this->normalizePhotoProofDataUrl($photoProofDataUrl);
        if (! ($normalizedPhoto['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $normalizedPhoto['message'] ?? 'Format bukti foto tidak valid.',
            ];
        }

        $validatedPhotoProofDataUrl = (string) ($normalizedPhoto['data_url'] ?? '');
        if ($requiresPhotoProof && $validatedPhotoProofDataUrl === '') {
            return [
                'success' => false,
                'message' => 'Task ini wajib upload foto bukti sebelum verifikasi selesai.',
            ];
        }

        // Validate photo before (if required)
        $requiresPhotoBefore = (bool) ($task['requires_photo_before'] ?? false);
        $validatedPhotoBeforeDataUrl = '';
        if ($photoBeforeDataUrl !== null && $photoBeforeDataUrl !== '') {
            $normalizedPhotoBefore = $this->normalizePhotoProofDataUrl($photoBeforeDataUrl);
            if ($normalizedPhotoBefore['success'] ?? false) {
                $validatedPhotoBeforeDataUrl = (string) ($normalizedPhotoBefore['data_url'] ?? '');
            }
        }
        if ($requiresPhotoBefore && $validatedPhotoBeforeDataUrl === '') {
            return [
                'success' => false,
                'message' => 'Task ini wajib upload foto SEBELUM (kondisi awal) sebelum verifikasi selesai.',
            ];
        }

        if ($requiresBarcodeScan) {
            $expectedBarcode = strtoupper(trim((string) ($task['rack_barcode_value'] ?? '')));
            $rackId = trim((string) ($task['rack_id'] ?? ''));

            if ($taskType === 'rack_check' && $rackId === '') {
                return [
                    'success' => false,
                    'message' => 'Task cek rak ini tidak memiliki data rak target. Hubungi supervisor.',
                ];
            }

            if ($rackId !== '') {
                $rack = $this->getRackById($rackId);
                if (! $rack) {
                    return [
                        'success' => false,
                        'message' => 'Data rak target untuk task ini tidak ditemukan. Hubungi supervisor.',
                    ];
                }

                $masterBarcode = strtoupper(trim((string) ($rack['barcode_value'] ?? '')));
                if ($masterBarcode === '') {
                    return [
                        'success' => false,
                        'message' => 'QR code rak target untuk task ini belum terdaftar. Hubungi supervisor.',
                    ];
                }

                $expectedBarcode = $masterBarcode;
            }

            $providedBarcode = strtoupper(trim((string) $scannedBarcode));
            $validatedExpectedBarcode = $expectedBarcode;

            if ($expectedBarcode === '') {
                return [
                    'success' => false,
                    'message' => 'QR code rak untuk tugas ini belum terdaftar. Hubungi supervisor.',
                ];
            }

            if ($providedBarcode === '') {
                return [
                    'success' => false,
                    'message' => 'Task ini wajib scan QR code rak sebelum verifikasi selesai.',
                ];
            }

            if ($providedBarcode !== $expectedBarcode) {
                // Log mismatch attempt
                $this->logScanAttempt($waiterId, $rackId, false, $providedBarcode, $expectedBarcode);
                return [
                    'success' => false,
                    'message' => 'QR code tidak sesuai dengan rak target. Silakan scan ulang QR code rak yang benar.',
                ];
            }

            // Log successful scan
            $this->logScanAttempt($waiterId, $rackId, true, $providedBarcode, $expectedBarcode);
        }

        $stockReportText = trim((string) $stockReportItems);
        $parsedStockReportItems = $this->extractStockReportItems($stockReportText);
        $noOutOfStockChecked = (bool) $noOutOfStock;

        if ($requiresStockReport && $noOutOfStockChecked && $stockReportText !== '') {
            return [
                'success' => false,
                'message' => 'Centang "Tidak ada barang habis" atau isi laporan barang menipis/habis, pilih salah satu.',
            ];
        }

        if ($requiresStockReport && $stockReportText === '' && ! $noOutOfStockChecked) {
            $noOutOfStockChecked = true;
        }

        // Repeat/multi-checklist logic
        $repeatCount = max(1, (int) ($task['repeat_count'] ?? 1));
        $completedCount = (int) ($task['completed_count'] ?? 0);
        $completions = (array) ($task['completions'] ?? []);
        $isRepeatTask = $repeatCount > 1;
        $newCompletedCount = $completedCount + 1;
        $isFullyDone = $newCompletedCount >= $repeatCount;

        // Build completion entry for this repetition
        $completionEntry = [
            'completed_at' => $now,
            'note' => ! empty($note) ? $note : null,
        ];

        if ($validatedPhotoProofDataUrl !== '') {
            $completionEntry['photo_proof_url'] = $validatedPhotoProofDataUrl;
            $completionEntry['photo_proof_mime_type'] = $normalizedPhoto['mime_type'] ?? null;
            $completionEntry['photo_proof_size_bytes'] = (int) ($normalizedPhoto['size_bytes'] ?? 0);
        }

        $completions[(string) $newCompletedCount] = $completionEntry;

        $updates = [
            'completed_count' => $newCompletedCount,
            'completions' => $completions,
            'completed_by_waiter_id' => (string) $waiterId,
            'completed_by_waiter_name' => (string) $waiterName,
            'completed_by_waiter_email' => (string) $waiterEmail,
        ];

        if ($isFullyDone) {
            $updates['status'] = $status;
            $updates['completed_at'] = $now;
        } else {
            // Partial completion — keep task active
            $updates['status'] = 'in_progress';
        }

        if ($requiresBarcodeScan) {
            $updates['completed_scanned_barcode'] = strtoupper(trim((string) $scannedBarcode));
            $updates['barcode_verified_at'] = $now;

            if ($validatedExpectedBarcode && strtoupper(trim((string) ($task['rack_barcode_value'] ?? ''))) !== $validatedExpectedBarcode) {
                $updates['rack_barcode_value'] = $validatedExpectedBarcode;
            }
        }

        if ($requiresStockReport) {
            $hasStockReportText = $stockReportText !== '';
            $updates['completed_stock_report'] = $hasStockReportText ? $stockReportText : null;
            $updates['completed_stock_report_items'] = $hasStockReportText ? $parsedStockReportItems : [];
            $updates['completed_no_out_of_stock'] = $noOutOfStockChecked || ! $hasStockReportText;
            $updates['stock_reported_at'] = $now;
        }

        // Product checklist handling for rack_check tasks
        if ($taskType === 'rack_check' && is_array($productChecklist) && count($productChecklist) > 0) {
            $validatedChecklist = [];
            foreach ($productChecklist as $productId => $checkData) {
                $productId = trim((string) $productId);
                if ($productId === '') {
                    continue;
                }
                $checked = (bool) ($checkData['checked'] ?? false);
                $actualQty = max(0, (int) ($checkData['actual_qty'] ?? 0));
                $standardQty = max(0, (int) ($checkData['standard_qty'] ?? 0));
                $validatedChecklist[$productId] = [
                    'checked' => $checked,
                    'actual_qty' => $actualQty,
                    'standard_qty' => $standardQty,
                    'is_shortage' => $checked && $actualQty < $standardQty,
                    'product_name' => trim((string) ($checkData['product_name'] ?? '')),
                    'product_unit' => trim((string) ($checkData['product_unit'] ?? 'pcs')),
                ];
            }
            if (count($validatedChecklist) > 0) {
                $updates['completed_product_checklist'] = $validatedChecklist;
                $updates['product_checklist_completed_at'] = $now;
            }
        }

        // For single-repeat tasks or final completion, store photo at top level too
        if ($isFullyDone && ($requiresPhotoProof || $validatedPhotoProofDataUrl !== '')) {
            $hasPhotoProof = $validatedPhotoProofDataUrl !== '';
            $updates['completed_photo_proof_url'] = $hasPhotoProof ? $validatedPhotoProofDataUrl : null;
            $updates['completed_photo_proof_mime_type'] = $hasPhotoProof ? ($normalizedPhoto['mime_type'] ?? null) : null;
            $updates['completed_photo_proof_size_bytes'] = $hasPhotoProof ? (int) ($normalizedPhoto['size_bytes'] ?? 0) : null;
            $updates['photo_proof_uploaded_at'] = $hasPhotoProof ? $now : null;
        }

        // Store photo before (kondisi awal) if provided
        if ($validatedPhotoBeforeDataUrl !== '') {
            $updates['completed_photo_before_url'] = $validatedPhotoBeforeDataUrl;
        }

        if (! empty($note) && $isFullyDone) {
            $updates['completed_note'] = $note;
        }

        $taskReference->update($updates);

        if ($isRepeatTask && ! $isFullyDone) {
            return [
                'success' => true,
                'partial' => true,
                'completed_count' => $newCompletedCount,
                'repeat_count' => $repeatCount,
                'message' => "Pengulangan #{$newCompletedCount} dari {$repeatCount} selesai.",
            ];
        }

        return [
            'success' => true,
            'partial' => false,
            'completed_count' => $newCompletedCount,
            'repeat_count' => $repeatCount,
            'message' => 'Tugas berhasil diverifikasi.',
        ];
    }

    /**
     * Delete waiter task by id.
     */
    public function deleteWaiterTask($id)
    {
        $this->database->getReference('waiter_tasks/'.$id)->remove();
    }

    /**
     * Create recurring template for waiter assignment.
     */
    public function createRecurringWaiterTaskTemplate(array $data)
    {
        $taskType = (string) ($data['task_type'] ?? 'general');
        $recurrenceType = $data['recurrence_type'] ?? 'daily';
        $scheduleTime = (string) ($data['schedule_time'] ?? '');
        $timeLimitMinutes = (int) ($data['time_limit_minutes'] ?? 0);
        $assignmentType = $data['assignment_type'] ?? 'all';
        $assignedWaiterRole = $assignmentType === 'role'
            ? $this->normalizeWaiterRole($data['assigned_waiter_role'] ?? 'pelayan')
            : null;
        $selectedWaiterIdsInput = $data['selected_waiter_ids'] ?? [];
        if (! is_array($selectedWaiterIdsInput)) {
            $selectedWaiterIdsInput = explode(',', (string) $selectedWaiterIdsInput);
        }
        $selectedWaiterIds = array_values(array_unique(array_filter(array_map(function ($waiterId) {
            return trim((string) $waiterId);
        }, $selectedWaiterIdsInput), function ($waiterId) {
            return $waiterId !== '';
        })));
        $assignedWaiterId = $assignmentType === 'single' ? ($data['assigned_waiter_id'] ?? null) : null;
        $assignedWaiter = $assignedWaiterId ? $this->getWaiterById($assignedWaiterId) : null;

        $scheduleMode = (string) ($data['schedule_mode'] ?? 'fixed');
        $shiftOffsetMinutes = max(0, (int) ($data['shift_offset_minutes'] ?? 0));
        $deadlineMode = (string) ($data['deadline_mode'] ?? 'fixed');
        $deadlineBeforeEndMinutes = max(0, (int) ($data['deadline_before_end_minutes'] ?? 60));

        $templateData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'assigned_by' => $data['assigned_by'] ?? 'Supervisor',
            'task_type' => $taskType,
            'category_id' => $data['category_id'] ?? null,
            'category_name' => $data['category_name'] ?? null,
            'requires_barcode_scan' => (bool) ($data['requires_barcode_scan'] ?? false),
            'requires_photo_proof' => (bool) ($data['requires_photo_proof'] ?? false),
            'requires_photo_before' => (bool) ($data['requires_photo_before'] ?? false),
            'rack_target_scope' => $data['rack_target_scope'] ?? null,
            'rack_id' => $data['rack_id'] ?? null,
            'rack_name' => $data['rack_name'] ?? null,
            'rack_location' => $data['rack_location'] ?? null,
            'rack_barcode_value' => $data['rack_barcode_value'] ?? null,
            'rack_type' => $data['rack_type'] ?? null,
            'assignment_type' => $assignmentType,
            'assignment_strategy' => $data['assignment_strategy'] ?? null,
            'rolling_slot_index' => isset($data['rolling_slot_index']) ? max(0, (int) $data['rolling_slot_index']) : null,
            'assigned_waiter_id' => $assignmentType === 'single' ? ($assignedWaiter['id'] ?? $assignedWaiterId) : null,
            'assigned_waiter_name' => $assignmentType === 'single' ? ($assignedWaiter['name'] ?? null) : null,
            'assigned_waiter_email' => $assignmentType === 'single' ? ($assignedWaiter['email'] ?? null) : null,
            'assigned_waiter_role' => $assignmentType === 'single'
                ? $this->normalizeWaiterRole($assignedWaiter['waiter_role'] ?? $assignedWaiterRole)
                : ($assignmentType === 'role' ? $assignedWaiterRole : null),
            'selected_waiter_ids' => $assignmentType === 'role' ? $selectedWaiterIds : [],
            'schedule_time' => $scheduleTime,
            'time_limit_minutes' => $timeLimitMinutes,
            'schedule_mode' => $scheduleMode,
            'shift_offset_minutes' => $shiftOffsetMinutes,
            'deadline_mode' => $deadlineMode,
            'deadline_before_end_minutes' => $deadlineBeforeEndMinutes,
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) ($data['weekly_day'] ?? date('N')) : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) ($data['interval_days'] ?? 1) : null,
            'recurrence_anchor_date' => $data['recurrence_anchor_date'] ?? date('Y-m-d'),
            'is_active' => true,
            'created_at' => time(),
            'last_generated_date' => null,
        ];

        $this->database->getReference('waiter_task_templates')->push($templateData);
    }

    /**
     * Get recurring waiter task templates.
     */
    public function getRecurringWaiterTaskTemplates()
    {
        $reference = $this->database->getReference('waiter_task_templates');
        $snapshot = $reference->getSnapshot();

        $templates = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $template) {
                $templates[] = array_merge(['id' => $key], $template);
            }
        }

        usort($templates, function ($a, $b) {
            return ($a['schedule_time'] ?? '99:99') <=> ($b['schedule_time'] ?? '99:99');
        });

        return $templates;
    }

    /**
     * Get recurring waiter template by id.
     */
    public function getRecurringWaiterTaskTemplateById($id)
    {
        $reference = $this->database->getReference('waiter_task_templates/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    /**
     * Update recurring waiter template.
     */
    public function updateRecurringWaiterTaskTemplate($id, $data)
    {
        $existing = $this->getRecurringWaiterTaskTemplateById($id);
        if (! $existing) {
            return;
        }

        $recurrenceType = $data['recurrence_type'] ?? ($existing['recurrence_type'] ?? 'daily');
        $anchorDate = $existing['recurrence_anchor_date'] ?? date('Y-m-d');
        if ($recurrenceType === 'every_n_days' && ! empty($data['reset_anchor_date'])) {
            $anchorDate = date('Y-m-d');
        }

        $updatedScheduleTime = (string) ($data['schedule_time'] ?? ($existing['schedule_time'] ?? ''));

        $updatedTimeLimitMinutes = (int) ($data['time_limit_minutes'] ?? ($existing['time_limit_minutes'] ?? 0));

        $updatedScheduleMode = (string) ($data['schedule_mode'] ?? ($existing['schedule_mode'] ?? 'fixed'));
        $updatedShiftOffsetMinutes = max(0, (int) ($data['shift_offset_minutes'] ?? ($existing['shift_offset_minutes'] ?? 0)));
        $updatedDeadlineMode = (string) ($data['deadline_mode'] ?? ($existing['deadline_mode'] ?? 'fixed'));
        $updatedDeadlineBeforeEndMinutes = max(0, (int) ($data['deadline_before_end_minutes'] ?? ($existing['deadline_before_end_minutes'] ?? 60)));

        $updates = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'schedule_time' => $updatedScheduleTime,
            'time_limit_minutes' => $updatedTimeLimitMinutes,
            'schedule_mode' => $updatedScheduleMode,
            'shift_offset_minutes' => $updatedShiftOffsetMinutes,
            'deadline_mode' => $updatedDeadlineMode,
            'deadline_before_end_minutes' => $updatedDeadlineBeforeEndMinutes,
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) ($data['weekly_day'] ?? date('N')) : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) ($data['interval_days'] ?? 1) : null,
            'recurrence_anchor_date' => $anchorDate,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ];

        $this->database->getReference('waiter_task_templates/'.$id)->update($updates);
    }

    /**
     * Generate due recurring waiter tasks.
     */
    public function generateDueRecurringWaiterTasks(bool $force = false)
    {
        $templates = $this->getRecurringWaiterTaskTemplates();
        $generatedCount = 0;
        $todayDate = date('Y-m-d');
        $currentTime = date('H:i');
        $existingRecurringMap = $this->getExistingWaiterRecurringMapForDate($todayDate);

        foreach ($templates as $template) {
            if (empty($template['is_active'])) {
                continue;
            }

            $scheduleTime = $template['schedule_time'] ?? null;
            $templateScheduleModeCheck = (string) ($template['schedule_mode'] ?? 'fixed');

            // For shift_relative mode, schedule_time is optional (used as fallback only)
            if (! $force && $templateScheduleModeCheck === 'fixed' && ! $scheduleTime) {
                continue;
            }

            $lastGeneratedDate = $template['last_generated_date'] ?? null;
            // For shift_relative mode, don't skip based on last_generated_date because
            // different waiters may have different shift start times throughout the day
            $alreadyGeneratedToday = $force ? false : ($templateScheduleModeCheck === 'shift_relative' ? false : ($lastGeneratedDate === $todayDate));
            // For shift_relative mode, skip the global time check (handled per-waiter in loop)
            $isDueToday = $force ? true : ($templateScheduleModeCheck === 'shift_relative' ? true : ($currentTime >= $scheduleTime));
            $recurrenceMatchedToday = $force ? true : $this->isTemplateDueForDate($template, $todayDate);

            if ($alreadyGeneratedToday || ! $isDueToday || ! $recurrenceMatchedToday) {
                continue;
            }

            $templateAssignmentType = (string) ($template['assignment_type'] ?? 'all');
            $assignmentStrategy = (string) ($template['assignment_strategy'] ?? '');
            $assignedWaiterRole = $this->normalizeWaiterRole($template['assigned_waiter_role'] ?? 'pelayan');
            $isRackRollingTemplate = (string) ($template['task_type'] ?? 'general') === 'rack_check'
                && $assignmentStrategy === 'role_round_robin'
                && trim((string) ($template['assigned_waiter_role'] ?? '')) !== '';

            if ($isRackRollingTemplate) {
                $templateAssignmentType = 'role';
            }

            $targetWaiters = $this->resolveTargetWaiters(
                $templateAssignmentType,
                $template['assigned_waiter_id'] ?? null,
                $assignedWaiterRole,
                $template['selected_waiter_ids'] ?? []
            );

            if (empty($targetWaiters)) {
                continue;
            }

            // Filter out waiters who are off today (not scheduled to work)
            $targetWaiters = array_values(array_filter($targetWaiters, function ($waiter) use ($todayDate) {
                $wId = $waiter['id'] ?? '';
                if ($wId === '') {
                    return true;
                }
                return $this->isWorkingDay($wId, $todayDate);
            }));

            if (empty($targetWaiters)) {
                continue;
            }

            if ($isRackRollingTemplate) {
                usort($targetWaiters, function ($a, $b) {
                    $nameCompare = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                    if ($nameCompare !== 0) {
                        return $nameCompare;
                    }

                    return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
                });

                $rotationOffset = $this->resolveDailyRotationOffset($todayDate);
                $slotIndex = $this->resolveRollingSlotIndex($template);
                $selectedWaiterIndex = ($slotIndex + $rotationOffset) % count($targetWaiters);
                $targetWaiters = [$targetWaiters[$selectedWaiterIndex]];

                $templateUpdates = [];
                if ((string) ($template['assignment_type'] ?? '') !== 'role') {
                    $templateUpdates['assignment_type'] = 'role';
                    $templateUpdates['assigned_waiter_id'] = null;
                    $templateUpdates['assigned_waiter_name'] = null;
                    $templateUpdates['assigned_waiter_email'] = null;
                }

                if (! isset($template['rolling_slot_index']) || $template['rolling_slot_index'] === null || $template['rolling_slot_index'] === '') {
                    $templateUpdates['rolling_slot_index'] = $slotIndex;
                }

                if (! empty($templateUpdates)) {
                    $this->database->getReference('waiter_task_templates/'.$template['id'])->update($templateUpdates);
                }
            }

            $timeLimitMinutes = (int) ($template['time_limit_minutes'] ?? 0);
            $templateScheduleMode = (string) ($template['schedule_mode'] ?? 'fixed');
            $templateShiftOffsetMinutes = max(0, (int) ($template['shift_offset_minutes'] ?? 0));
            $templateDeadlineMode = (string) ($template['deadline_mode'] ?? 'fixed');
            $templateDeadlineBeforeEndMinutes = max(0, (int) ($template['deadline_before_end_minutes'] ?? 60));

            $generatedForTemplate = 0;

            foreach ($targetWaiters as $waiter) {
                $mapKey = $this->buildWaiterRecurringInstanceKey($template['id'], $waiter['id'] ?? null);
                if (isset($existingRecurringMap[$mapKey])) {
                    continue;
                }

                // Resolve per-waiter schedule time and deadline based on schedule_mode
                $waiterScheduleTime = $scheduleTime;
                $waiterDeadlineAt = null;
                $waiterId = $waiter['id'] ?? '';

                if ($templateScheduleMode === 'shift_relative' && $waiterId !== '') {
                    $waiterShift = $this->getWaiterShiftForDate($waiterId, $todayDate);
                    if ($waiterShift) {
                        $shiftStart = $waiterShift['clock_in_time'] ?? '08:00';
                        $shiftEnd = $waiterShift['clock_out_time'] ?? '17:00';

                        // Calculate schedule time: shift start + offset
                        $shiftStartTimestamp = $this->buildScheduledTimestamp($todayDate, $shiftStart);
                        $waiterScheduleTimestamp = $shiftStartTimestamp + ($templateShiftOffsetMinutes * 60);
                        $waiterScheduleTime = date('H:i', $waiterScheduleTimestamp);

                        // Check if current time has reached this waiter's schedule time
                        if (! $force && $currentTime < $waiterScheduleTime) {
                            continue; // Not yet time for this waiter
                        }

                        // Calculate deadline based on deadline_mode
                        if ($templateDeadlineMode === 'before_shift_end') {
                            $shiftEndTimestamp = $this->buildScheduledTimestamp($todayDate, $shiftEnd);
                            // Handle overnight shifts (end < start)
                            if ($shiftEndTimestamp <= $shiftStartTimestamp) {
                                $shiftEndTimestamp += 86400; // +24h
                            }
                            $waiterDeadlineAt = $shiftEndTimestamp - ($templateDeadlineBeforeEndMinutes * 60);
                        } elseif ($timeLimitMinutes > 0) {
                            $waiterDeadlineAt = $waiterScheduleTimestamp + ($timeLimitMinutes * 60);
                        }
                    } else {
                        // Waiter has no shift today — fallback to fixed mode
                        if ($timeLimitMinutes > 0) {
                            $scheduleTimestamp = $this->buildScheduledTimestamp($todayDate, $scheduleTime);
                            $waiterDeadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
                        }
                    }
                } else {
                    // Fixed mode: original behavior
                    if ($timeLimitMinutes > 0) {
                        $scheduleTimestamp = $this->buildScheduledTimestamp($todayDate, $scheduleTime);
                        $waiterDeadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
                    }
                }

                $recurringInstanceKey = $this->buildWaiterRecurringInstanceIdentity(
                    $template['id'],
                    $waiter['id'] ?? null,
                    $todayDate
                );
                $taskNodeKey = $this->buildWaiterRecurringTaskNodeKey($recurringInstanceKey);
                $taskReference = $this->database->getReference('waiter_tasks/'.$taskNodeKey);
                if ($taskReference->getSnapshot()->exists()) {
                    $existingRecurringMap[$mapKey] = true;

                    continue;
                }

                $taskData = $this->buildWaiterTaskPayload($template, $waiter, [
                    'status' => 'pending',
                    'created_at' => time(),
                    'completed_at' => null,
                    'completed_note' => null,
                    'completed_by_waiter_id' => null,
                    'completed_by_waiter_name' => null,
                    'completed_by_waiter_email' => null,
                    'is_recurring_instance' => true,
                    'scheduled_time' => $waiterScheduleTime,
                    'scheduled_for_date' => $todayDate,
                    'source_template_id' => $template['id'],
                    'recurring_instance_key' => $recurringInstanceKey,
                    'time_limit_minutes' => $timeLimitMinutes > 0 ? $timeLimitMinutes : null,
                    'deadline_at' => $waiterDeadlineAt,
                    'recurrence_type' => $template['recurrence_type'] ?? 'daily',
                ]);

                $taskReference->set($taskData);
                $existingRecurringMap[$mapKey] = true;
                $generatedForTemplate++;
                $generatedCount++;
            }

            if ($generatedForTemplate > 0) {
                $this->database->getReference('waiter_task_templates/'.$template['id'])->update([
                    'last_generated_date' => $todayDate,
                ]);
            }
        }

        return $generatedCount;
    }

    /**
     * Mark overdue waiter tasks.
     */
    public function markOverdueWaiterTasks()
    {
        $now = time();
        $updates = [];
        $overdueCount = 0;
        $overdueTasks = [];
        $baseRef = $this->database->getReference('waiter_tasks');

        // Check both 'pending' and 'in_progress' tasks for overdue
        foreach (['pending', 'in_progress'] as $activeStatus) {
            $reference = $this->database->getReference('waiter_tasks')
                ->orderByChild('status')
                ->equalTo($activeStatus);
            $snapshot = $reference->getSnapshot();

            if (! $snapshot->exists()) {
                continue;
            }

            foreach ($snapshot->getValue() as $taskId => $task) {
                $deadlineAt = (int) ($task['deadline_at'] ?? 0);
                if ($deadlineAt <= 0 || $now <= $deadlineAt) {
                    continue;
                }

                $updates[$taskId.'/status'] = 'overdue';
                $updates[$taskId.'/completed_at'] = $now;
                if (empty($task['completed_note'])) {
                    $updates[$taskId.'/completed_note'] = 'Auto: batas waktu habis';
                }
                $overdueTasks[] = array_merge(['id' => $taskId], $task);
                $overdueCount++;
            }
        }

        if (count($updates) === 0) {
            return ['count' => 0, 'overdue_tasks' => []];
        }

        $baseRef->update($updates);

        // Auto-apply penalties for newly overdue tasks
        if ($overdueCount > 0) {
            try {
                $bonusService = app(\App\Services\BonusService::class);
                $today = date('Y-m-d');
                $month = substr($today, 0, 7);

                // Batch fetch ALL penalties for this month ONCE (not per-waiter)
                $allMonthPenalties = $bonusService->getPenaltiesByMonth($month);

                // Build lookup: "taskId::waiterId" => true for existing mandatory_task_missed penalties
                $existingPenaltyKeys = [];
                foreach ($allMonthPenalties as $p) {
                    if (($p['penalty_type'] ?? '') === 'mandatory_task_missed') {
                        $key = ($p['related_task_id'] ?? '') . '::' . ($p['waiter_id'] ?? '');
                        $existingPenaltyKeys[$key] = true;
                    }
                }

                foreach ($snapshot->getValue() as $taskId => $task) {
                    $deadlineAt = (int) ($task['deadline_at'] ?? 0);
                    if ($deadlineAt <= 0 || $now <= $deadlineAt) {
                        continue;
                    }

                    $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
                    $waiterName = (string) ($task['assigned_waiter_name'] ?? '');
                    $taskTitle = (string) ($task['title'] ?? 'Tugas');

                    if ($waiterId === '') {
                        continue;
                    }

                    // Check if penalty already exists using pre-built lookup
                    $penaltyKey = $taskId . '::' . $waiterId;
                    if (isset($existingPenaltyKeys[$penaltyKey])) {
                        continue;
                    }

                    $bonusService->applyPenalty([
                        'waiter_id' => $waiterId,
                        'waiter_name' => $waiterName,
                        'penalty_type' => 'mandatory_task_missed',
                        'date' => $today,
                        'reason' => 'Tugas "'.$taskTitle.'" tidak dikerjakan tepat waktu (otomatis)',
                        'related_task_id' => $taskId,
                    ]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['count' => $overdueCount, 'overdue_tasks' => $overdueTasks];
    }

    /**
     * Delete recurring waiter template.
     */
    public function deleteRecurringWaiterTaskTemplate($id)
    {
        $this->database->getReference('waiter_task_templates/'.$id)->remove();
    }

    /**
     * Reset all rack-check waiter data (tasks + recurring templates).
     */
    public function resetRackCheckWaiterData(): array
    {
        $deletedTasks = 0;
        $deletedTemplates = 0;
        $updates = [];

        $tasksReference = $this->database->getReference('waiter_tasks');
        $tasksSnapshot = $tasksReference->getSnapshot();
        if ($tasksSnapshot->exists()) {
            foreach ($tasksSnapshot->getValue() as $taskId => $task) {
                if ((string) ($task['task_type'] ?? 'general') !== 'rack_check') {
                    continue;
                }

                $updates['waiter_tasks/'.$taskId] = null;
                $deletedTasks++;
            }
        }

        $templatesReference = $this->database->getReference('waiter_task_templates');
        $templatesSnapshot = $templatesReference->getSnapshot();
        if ($templatesSnapshot->exists()) {
            foreach ($templatesSnapshot->getValue() as $templateId => $template) {
                if ((string) ($template['task_type'] ?? 'general') !== 'rack_check') {
                    continue;
                }

                $updates['waiter_task_templates/'.$templateId] = null;
                $deletedTemplates++;
            }
        }

        if (! empty($updates)) {
            $this->database->getReference()->update($updates);
        }

        return [
            'deleted_tasks' => $deletedTasks,
            'deleted_templates' => $deletedTemplates,
        ];
    }

    /**
     * Resolve target waiters from assignment.
     */
    protected function resolveTargetWaiters($assignmentType, $assignedWaiterId = null, $assignedWaiterRole = null, $selectedWaiterIdsInput = [])
    {
        if ($assignmentType === 'single') {
            if (! $assignedWaiterId) {
                return [];
            }

            $waiter = $this->getWaiterById($assignedWaiterId);
            if (! $waiter || ($waiter['is_active'] ?? true) === false) {
                return [];
            }

            return [$waiter];
        }

        if ($assignmentType === 'role') {
            if (! $assignedWaiterRole) {
                return [];
            }

            $roleWaiters = $this->getActiveWaitersByRole($assignedWaiterRole);
            if (! is_array($selectedWaiterIdsInput)) {
                $selectedWaiterIdsInput = explode(',', (string) $selectedWaiterIdsInput);
            }

            $selectedWaiterIds = array_values(array_unique(array_filter(array_map(function ($waiterId) {
                return trim((string) $waiterId);
            }, $selectedWaiterIdsInput), function ($waiterId) {
                return $waiterId !== '';
            })));

            if (count($selectedWaiterIds) === 0) {
                return $roleWaiters;
            }

            $selectedWaiterMap = array_fill_keys($selectedWaiterIds, true);

            return array_values(array_filter($roleWaiters, function ($waiter) use ($selectedWaiterMap) {
                $waiterId = trim((string) ($waiter['id'] ?? ''));

                return $waiterId !== '' && isset($selectedWaiterMap[$waiterId]);
            }));
        }

        return $this->getActiveWaiters();
    }

    /**
     * Build waiter task payload from base data + target waiter.
     */
    protected function buildWaiterTaskPayload(array $data, array $waiter, array $overrides = [])
    {
        $taskType = (string) ($data['task_type'] ?? 'general');
        $rackName = trim((string) ($data['rack_name'] ?? ''));

        $resolvedTitle = (string) ($data['title'] ?? '');
        if ($taskType === 'rack_check' && $rackName !== '') {
            $resolvedTitle = $rackName;
        }

        $resolvedDescription = $taskType === 'rack_check'
            ? ''
            : (string) ($data['description'] ?? '');

        $resolvedPriority = $taskType === 'rack_check'
            ? 'normal'
            : (string) ($data['priority'] ?? 'normal');

        $payload = [
            'title' => $resolvedTitle,
            'description' => $resolvedDescription,
            'priority' => $resolvedPriority,
            'task_type' => $taskType,
            'category_id' => $data['category_id'] ?? null,
            'category_name' => $data['category_name'] ?? null,
            'requires_barcode_scan' => (bool) ($data['requires_barcode_scan'] ?? false),
            'requires_photo_proof' => (bool) ($data['requires_photo_proof'] ?? false),
            'requires_photo_before' => (bool) ($data['requires_photo_before'] ?? false),
            'rack_target_scope' => $data['rack_target_scope'] ?? null,
            'rack_id' => $data['rack_id'] ?? null,
            'rack_name' => $data['rack_name'] ?? null,
            'rack_location' => $data['rack_location'] ?? null,
            'rack_barcode_value' => $data['rack_barcode_value'] ?? null,
            'rack_type' => $data['rack_type'] ?? null,
            'status' => 'pending',
            'assigned_by' => $data['assigned_by'] ?? 'Supervisor',
            'assignment_type' => $data['assignment_type'] ?? 'single',
            'assignment_strategy' => $data['assignment_strategy'] ?? null,
            'assigned_waiter_id' => $waiter['id'] ?? null,
            'assigned_waiter_name' => $waiter['name'] ?? null,
            'assigned_waiter_email' => $waiter['email'] ?? null,
            'assigned_waiter_role' => $this->normalizeWaiterRole($waiter['waiter_role'] ?? ($data['assigned_waiter_role'] ?? 'pelayan')),
            'created_at' => time(),
            'completed_at' => null,
            'completed_note' => null,
            'completed_by_waiter_id' => null,
            'completed_by_waiter_name' => null,
            'completed_by_waiter_email' => null,
            'completed_stock_report' => null,
            'completed_stock_report_items' => [],
            'completed_no_out_of_stock' => null,
            'stock_reported_at' => null,
            'completed_product_checklist' => null,
            'product_checklist_completed_at' => null,
            'completed_photo_proof_url' => null,
            'completed_photo_before_url' => null,
            'completed_photo_proof_mime_type' => null,
            'completed_photo_proof_size_bytes' => null,
            'photo_proof_uploaded_at' => null,
            'is_recurring_instance' => false,
            'scheduled_time' => null,
            'scheduled_for_date' => null,
            'source_template_id' => null,
            'time_limit_minutes' => null,
            'deadline_at' => null,
            'recurrence_type' => null,
            'recurring_instance_key' => null,
            'repeat_count' => max(1, (int) ($data['repeat_count'] ?? 1)),
            'completed_count' => 0,
            'completions' => [],
        ];

        return array_merge($payload, $overrides);
    }

    /**
     * Normalize waiter role to supported values.
     */
    protected function normalizeWaiterRole($waiterRole): string
    {
        $role = strtolower(trim((string) $waiterRole));

        return in_array($role, ['kasir', 'pelayan', 'supervisor'], true) ? $role : 'pelayan';
    }

    /**
     * Get all task categories.
     */
    public function getTaskCategories(): array
    {
        $reference = $this->database->getReference('task_categories');
        $snapshot = $reference->getSnapshot();

        $categories = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $category) {
                $categories[] = array_merge(['id' => $key], $category);
            }
        }

        usort($categories, function ($a, $b) {
            return ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
        });

        return $categories;
    }

    /**
     * Create a new task category.
     */
    public function createTaskCategory(string $name, string $color, int $order = 0): string
    {
        $ref = $this->database->getReference('task_categories')->push([
            'name' => trim($name),
            'color' => $color,
            'order' => $order,
            'created_at' => time(),
        ]);

        return $ref->getKey();
    }

    /**
     * Update an existing task category.
     */
    public function updateTaskCategory(string $id, array $data): void
    {
        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['order'])) {
            $updateData['order'] = (int) $data['order'];
        }

        if (count($updateData) > 0) {
            $this->database->getReference('task_categories/' . $id)->update($updateData);
        }
    }

    /**
     * Delete a task category.
     */
    public function deleteTaskCategory(string $id): void
    {
        $this->database->getReference('task_categories/' . $id)->remove();
    }

    /**
     * Resolve rolling slot index used for daily waiter rotation.
     */
    protected function resolveRollingSlotIndex(array $template): int
    {
        if (isset($template['rolling_slot_index']) && $template['rolling_slot_index'] !== null && $template['rolling_slot_index'] !== '') {
            $value = (int) $template['rolling_slot_index'];

            return $value >= 0 ? $value : 0;
        }

        $rackId = trim((string) ($template['rack_id'] ?? ''));
        if ($rackId !== '') {
            return abs((int) sprintf('%u', crc32($rackId)));
        }

        $templateId = trim((string) ($template['id'] ?? ''));
        if ($templateId !== '') {
            return abs((int) sprintf('%u', crc32($templateId)));
        }

        return 0;
    }

    /**
     * Resolve day-based rotation offset from date string.
     */
    protected function resolveDailyRotationOffset(string $date): int
    {
        $dateTimestamp = strtotime($date.' 00:00:00');
        if ($dateTimestamp === false) {
            return 0;
        }

        return (int) floor($dateTimestamp / 86400);
    }

    /**
     * Extract structured item list from stock report text.
     */
    protected function extractStockReportItems(string $reportText): array
    {
        if ($reportText === '') {
            return [];
        }

        $rawItems = preg_split('/[\r\n,;]+/', $reportText) ?: [];
        $items = [];
        $seen = [];

        foreach ($rawItems as $rawItem) {
            $item = trim(preg_replace('/\s+/', ' ', (string) $rawItem) ?? '');
            if ($item === '') {
                continue;
            }

            $key = strtolower($item);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Normalize and validate photo proof data URL payload.
     */
    protected function normalizePhotoProofDataUrl($photoProofDataUrl): array
    {
        $raw = trim((string) ($photoProofDataUrl ?? ''));
        if ($raw === '') {
            return [
                'success' => true,
                'data_url' => '',
                'mime_type' => null,
                'size_bytes' => null,
            ];
        }

        if (! preg_match('/^data:(image\/(?:jpeg|jpg|png|webp));base64,([A-Za-z0-9+\/=\r\n]+)$/i', $raw, $matches)) {
            return [
                'success' => false,
                'message' => 'Format bukti foto tidak valid. Gunakan foto JPG/PNG/WEBP.',
            ];
        }

        $mimeType = strtolower((string) ($matches[1] ?? ''));
        if ($mimeType === 'image/jpg') {
            $mimeType = 'image/jpeg';
        }

        $base64Payload = preg_replace('/\s+/', '', (string) ($matches[2] ?? ''));
        if ($base64Payload === '') {
            return [
                'success' => false,
                'message' => 'Data bukti foto kosong. Silakan ambil ulang foto bukti.',
            ];
        }

        $decoded = base64_decode($base64Payload, true);
        if ($decoded === false) {
            return [
                'success' => false,
                'message' => 'Data bukti foto rusak. Silakan ambil ulang foto bukti.',
            ];
        }

        $sizeBytes = strlen($decoded);
        $maxSizeBytes = 3 * 1024 * 1024;
        if ($sizeBytes > $maxSizeBytes) {
            return [
                'success' => false,
                'message' => 'Ukuran bukti foto terlalu besar. Maksimal 3MB setelah kompresi.',
            ];
        }

        $normalizedBase64 = base64_encode($decoded);

        return [
            'success' => true,
            'data_url' => sprintf('data:%s;base64,%s', $mimeType, $normalizedBase64),
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Normalize report date to Y-m-d.
     */
    protected function normalizeReportDate(?string $date): string
    {
        $raw = trim((string) ($date ?? ''));
        if ($raw === '') {
            return date('Y-m-d');
        }

        $timestamp = strtotime($raw);

        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Generate unique barcode value for rack.
     */
    protected function generateUniqueRackBarcodeValue(string $rackName = ''): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', Str::limit($rackName !== '' ? $rackName : 'RAK', 8, '')));
        if ($base === '') {
            $base = 'RAK';
        }

        $existing = array_map(function ($rack) {
            return strtoupper(trim((string) ($rack['barcode_value'] ?? '')));
        }, $this->getRacks());

        do {
            $candidate = sprintf('RAK-%s-%04d', $base, random_int(0, 9999));
        } while (in_array($candidate, $existing, true));

        return $candidate;
    }

    /**
     * Build map key for recurring waiter instance uniqueness.
     */
    protected function buildWaiterRecurringInstanceKey($templateId, $waiterId)
    {
        return (string) $templateId.'::'.(string) $waiterId;
    }

    /**
     * Build unique recurring instance identity per template, waiter, and date.
     */
    protected function buildWaiterRecurringInstanceIdentity($templateId, $waiterId, $scheduledDate)
    {
        return (string) $templateId.'::'.(string) $waiterId.'::'.(string) $scheduledDate;
    }

    /**
     * Build deterministic Firebase node key for recurring waiter tasks.
     */
    protected function buildWaiterRecurringTaskNodeKey($recurringInstanceKey)
    {
        return 'waiter_rec_'.substr(hash('sha256', (string) $recurringInstanceKey), 0, 32);
    }

    /**
     * Existing recurring waiter instances for a date.
     */
    protected function getExistingWaiterRecurringMapForDate($date)
    {
        // Query only tasks for this specific date (requires .indexOn: scheduled_for_date)
        $reference = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->equalTo($date);
        $snapshot = $reference->getSnapshot();
        $map = [];

        if (! $snapshot->exists()) {
            return $map;
        }

        foreach ($snapshot->getValue() as $task) {
            $sourceTemplateId = $task['source_template_id'] ?? null;
            $assignedWaiterId = $task['assigned_waiter_id'] ?? null;
            if (! $sourceTemplateId || ! $assignedWaiterId) {
                continue;
            }

            $map[$this->buildWaiterRecurringInstanceKey($sourceTemplateId, $assignedWaiterId)] = true;
        }

        return $map;
    }

    // ========================================
    // Task Management (Supervisor Tasks)
    // ========================================

    /**
     * Create a new task for the cashier
     */
    public function createTask($data)
    {
        $taskData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'pending',
            'assigned_by' => $data['assigned_by'] ?? 'Supervisor',
            'created_at' => time(),
            'completed_at' => null,
            'completed_note' => null,
            'is_recurring_instance' => false,
            'scheduled_time' => null,
            'scheduled_for_date' => null,
            'source_template_id' => null,
            'time_limit_minutes' => null,
            'deadline_at' => null,
            'completed_by_worker_id' => null,
            'completed_by_worker_name' => null,
        ];

        $this->database->getReference('cashier_tasks')
            ->push($taskData);
    }

    /**
     * Get all tasks
     */
    public function getTasks()
    {
        $reference = $this->database->getReference('cashier_tasks');
        $snapshot = $reference->getSnapshot();

        $tasks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $task) {
                $tasks[] = array_merge(['id' => $key], $task);
            }
        }

        // Sort by created_at descending (newest first)
        usort($tasks, function ($a, $b) {
            return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
        });

        return $tasks;
    }

    /**
     * Get active (pending) tasks only
     */
    public function getActiveTasks()
    {
        $tasks = $this->getTasks();

        return array_filter($tasks, function ($task) {
            return ($task['status'] ?? 'pending') === 'pending';
        });
    }

    /**
     * Update task status
     */
    public function updateTaskStatus($id, $status, $note = null, $workerId = null, $workerName = null)
    {
        $taskReference = $this->database->getReference('cashier_tasks/'.$id);
        $snapshot = $taskReference->getSnapshot();

        if (! $snapshot->exists()) {
            return [
                'success' => false,
                'message' => 'Tugas tidak ditemukan',
            ];
        }

        $task = $snapshot->getValue();
        $currentStatus = $task['status'] ?? 'pending';

        if ($currentStatus !== 'pending') {
            return [
                'success' => false,
                'message' => 'Tugas ini sudah tidak aktif',
            ];
        }

        $now = time();
        $deadlineAt = (int) ($task['deadline_at'] ?? 0);
        if ($deadlineAt > 0 && $now > $deadlineAt) {
            $taskReference->update([
                'status' => 'overdue',
                'completed_at' => $now,
                'completed_note' => 'Auto: batas waktu habis',
            ]);

            return [
                'success' => false,
                'message' => 'Tugas sudah melewati batas waktu dan dihitung tidak selesai',
            ];
        }

        $updates = [
            'status' => $status,
            'completed_at' => $now,
        ];

        if (! empty($note)) {
            $updates['completed_note'] = $note;
        }

        if ($status === 'done') {
            $updates['completed_by_worker_id'] = $workerId;
            $updates['completed_by_worker_name'] = $workerName;
        }

        $taskReference->update($updates);

        return [
            'success' => true,
            'message' => 'Status tugas berhasil diupdate',
        ];
    }

    /**
     * Delete a task
     */
    public function deleteTask($id)
    {
        $this->database->getReference('cashier_tasks/'.$id)
            ->remove();
    }

    /**
     * Create recurring task template
     */
    public function createRecurringTaskTemplate($data)
    {
        $recurrenceType = $data['recurrence_type'] ?? 'daily';
        $templateData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'assigned_by' => $data['assigned_by'] ?? 'Supervisor',
            'schedule_time' => $data['schedule_time'], // HH:MM
            'time_limit_minutes' => (int) ($data['time_limit_minutes'] ?? 0),
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) ($data['weekly_day'] ?? date('N')) : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) ($data['interval_days'] ?? 1) : null,
            'recurrence_anchor_date' => $data['recurrence_anchor_date'] ?? date('Y-m-d'),
            'is_active' => true,
            'created_at' => time(),
            'last_generated_date' => null,
        ];

        $this->database->getReference('cashier_task_templates')
            ->push($templateData);
    }

    /**
     * Get recurring task templates
     */
    public function getRecurringTaskTemplates()
    {
        $reference = $this->database->getReference('cashier_task_templates');
        $snapshot = $reference->getSnapshot();

        $templates = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $template) {
                $templates[] = array_merge(['id' => $key], $template);
            }
        }

        usort($templates, function ($a, $b) {
            return ($a['schedule_time'] ?? '99:99') <=> ($b['schedule_time'] ?? '99:99');
        });

        return $templates;
    }

    /**
     * Get recurring task template by id
     */
    public function getRecurringTaskTemplateById($id)
    {
        $reference = $this->database->getReference('cashier_task_templates/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    /**
     * Update recurring task template
     */
    public function updateRecurringTaskTemplate($id, $data)
    {
        $existing = $this->getRecurringTaskTemplateById($id);
        if (! $existing) {
            return;
        }

        $recurrenceType = $data['recurrence_type'] ?? ($existing['recurrence_type'] ?? 'daily');
        $anchorDate = $existing['recurrence_anchor_date'] ?? date('Y-m-d');
        if ($recurrenceType === 'every_n_days' && ! empty($data['reset_anchor_date'])) {
            $anchorDate = date('Y-m-d');
        }

        $updatedScheduleTime = $data['schedule_time'];
        $updatedWeeklyDay = $recurrenceType === 'weekly' ? (int) ($data['weekly_day'] ?? date('N')) : null;
        $updatedIntervalDays = $recurrenceType === 'every_n_days' ? (int) ($data['interval_days'] ?? 1) : null;

        $scheduleAffectsGeneration =
            ($existing['schedule_time'] ?? null) !== $updatedScheduleTime ||
            ($existing['recurrence_type'] ?? 'daily') !== $recurrenceType ||
            (int) ($existing['weekly_day'] ?? 0) !== (int) ($updatedWeeklyDay ?? 0) ||
            (int) ($existing['interval_days'] ?? 0) !== (int) ($updatedIntervalDays ?? 0) ||
            ($existing['recurrence_anchor_date'] ?? null) !== $anchorDate;

        $todayDate = date('Y-m-d');
        $hasPendingInstanceToday = $this->hasPendingRecurringInstanceForDate($id, $todayDate);
        $hasDoneInstanceToday = $this->hasDoneRecurringInstanceForDate($id, $todayDate);

        $updates = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'schedule_time' => $updatedScheduleTime,
            'time_limit_minutes' => (int) ($data['time_limit_minutes'] ?? 0),
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $updatedWeeklyDay,
            'interval_days' => $updatedIntervalDays,
            'recurrence_anchor_date' => $anchorDate,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ];

        if ($scheduleAffectsGeneration && ! $hasPendingInstanceToday && ! $hasDoneInstanceToday) {
            // Allow regeneration for today after schedule edits when no pending instance exists.
            $updates['last_generated_date'] = null;
        }

        $this->database->getReference('cashier_task_templates/'.$id)
            ->update($updates);

        $updatedTemplate = array_merge($existing, $updates, ['id' => $id]);
        $this->syncPendingRecurringInstancesForDate($id, $todayDate, $updatedTemplate);
    }

    /**
     * Generate due recurring tasks for today
     */
    public function generateDueRecurringTasks()
    {
        $templates = $this->getRecurringTaskTemplates();
        $generatedCount = 0;
        $todayDate = date('Y-m-d');
        $currentTime = date('H:i');
        $existingRecurringMap = $this->getExistingRecurringMapForDate($todayDate);

        foreach ($templates as $template) {
            if (empty($template['is_active'])) {
                continue;
            }

            $scheduleTime = $template['schedule_time'] ?? null;
            if (! $scheduleTime) {
                continue;
            }

            $lastGeneratedDate = $template['last_generated_date'] ?? null;
            $alreadyGeneratedToday = $lastGeneratedDate === $todayDate;
            $isDueToday = $currentTime >= $scheduleTime;
            $alreadyHasInstance = isset($existingRecurringMap[$template['id']]);
            $recurrenceMatchedToday = $this->isTemplateDueForDate($template, $todayDate);

            if ($alreadyGeneratedToday || ! $isDueToday || ! $recurrenceMatchedToday || $alreadyHasInstance) {
                continue;
            }

            $timeLimitMinutes = (int) ($template['time_limit_minutes'] ?? 0);
            $deadlineAt = null;
            if ($timeLimitMinutes > 0) {
                $scheduleTimestamp = $this->buildScheduledTimestamp($todayDate, $scheduleTime);
                $deadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
            }

            $taskData = [
                'title' => $template['title'] ?? '',
                'description' => $template['description'] ?? '',
                'priority' => $template['priority'] ?? 'normal',
                'status' => 'pending',
                'assigned_by' => $template['assigned_by'] ?? 'Supervisor',
                'created_at' => time(),
                'completed_at' => null,
                'completed_note' => null,
                'is_recurring_instance' => true,
                'scheduled_time' => $scheduleTime,
                'scheduled_for_date' => $todayDate,
                'source_template_id' => $template['id'],
                'time_limit_minutes' => $timeLimitMinutes > 0 ? $timeLimitMinutes : null,
                'deadline_at' => $deadlineAt,
                'recurrence_type' => $template['recurrence_type'] ?? 'daily',
                'completed_by_worker_id' => null,
                'completed_by_worker_name' => null,
            ];

            $this->database->getReference('cashier_tasks')
                ->push($taskData);

            $this->database->getReference('cashier_task_templates/'.$template['id'])
                ->update([
                    'last_generated_date' => $todayDate,
                ]);

            $existingRecurringMap[$template['id']] = true;
            $generatedCount++;
        }

        return $generatedCount;
    }

    /**
     * Mark pending tasks as overdue when deadline passes
     */
    public function markOverdueTasks()
    {
        $reference = $this->database->getReference('cashier_tasks')
            ->orderByChild('status')
            ->equalTo('pending');
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return 0;
        }

        $now = time();
        $updates = [];
        $overdueCount = 0;
        $baseRef = $this->database->getReference('cashier_tasks');

        foreach ($snapshot->getValue() as $taskId => $task) {
            $deadlineAt = (int) ($task['deadline_at'] ?? 0);
            if ($deadlineAt <= 0 || $now <= $deadlineAt) {
                continue;
            }

            $updates[$taskId.'/status'] = 'overdue';
            $updates[$taskId.'/completed_at'] = $now;
            if (empty($task['completed_note'])) {
                $updates[$taskId.'/completed_note'] = 'Auto: batas waktu habis';
            }
            $overdueCount++;
        }

        if (! empty($updates)) {
            $baseRef->update($updates);
        }

        return $overdueCount;
    }

    /**
     * Build a map of recurring instances already generated for a date
     */
    protected function getExistingRecurringMapForDate($date)
    {
        $reference = $this->database->getReference('cashier_tasks');
        $snapshot = $reference->getSnapshot();
        $map = [];

        if (! $snapshot->exists()) {
            return $map;
        }

        foreach ($snapshot->getValue() as $task) {
            $sourceTemplateId = $task['source_template_id'] ?? null;
            $scheduledDate = $task['scheduled_for_date'] ?? null;
            $status = $task['status'] ?? 'pending';

            if ($sourceTemplateId && $scheduledDate === $date && $status === 'pending') {
                $map[$sourceTemplateId] = true;
            }
        }

        return $map;
    }

    /**
     * Check whether a template already has a pending recurring instance for a date
     */
    protected function hasPendingRecurringInstanceForDate($templateId, $date)
    {
        $reference = $this->database->getReference('cashier_tasks')
            ->orderByChild('source_template_id')
            ->equalTo($templateId);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return false;
        }

        foreach ($snapshot->getValue() as $task) {
            if (($task['scheduled_for_date'] ?? null) === $date && ($task['status'] ?? 'pending') === 'pending') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a template already has a completed recurring instance for a date
     */
    protected function hasDoneRecurringInstanceForDate($templateId, $date)
    {
        $reference = $this->database->getReference('cashier_tasks')
            ->orderByChild('source_template_id')
            ->equalTo($templateId);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return false;
        }

        foreach ($snapshot->getValue() as $task) {
            if (($task['scheduled_for_date'] ?? null) === $date && ($task['status'] ?? 'pending') === 'done') {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync today's pending generated instances with current template values
     */
    protected function syncPendingRecurringInstancesForDate($templateId, $date, array $template)
    {
        $reference = $this->database->getReference('cashier_tasks')
            ->orderByChild('source_template_id')
            ->equalTo($templateId);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return;
        }

        $scheduleTime = $template['schedule_time'] ?? null;
        $timeLimitMinutes = (int) ($template['time_limit_minutes'] ?? 0);
        $deadlineAt = null;
        if ($timeLimitMinutes > 0 && $scheduleTime) {
            $deadlineAt = $this->buildScheduledTimestamp($date, $scheduleTime) + ($timeLimitMinutes * 60);
        }

        $updates = [];
        $baseRef = $this->database->getReference('cashier_tasks');

        foreach ($snapshot->getValue() as $taskId => $task) {
            $scheduledDate = $task['scheduled_for_date'] ?? null;
            $status = $task['status'] ?? 'pending';

            if ($scheduledDate !== $date || $status !== 'pending') {
                continue;
            }

            $updates[$taskId.'/title'] = $template['title'] ?? ($task['title'] ?? '');
            $updates[$taskId.'/description'] = $template['description'] ?? ($task['description'] ?? '');
            $updates[$taskId.'/priority'] = $template['priority'] ?? ($task['priority'] ?? 'normal');
            $updates[$taskId.'/assigned_by'] = $template['assigned_by'] ?? ($task['assigned_by'] ?? 'Supervisor');
            $updates[$taskId.'/scheduled_time'] = $scheduleTime;
            $updates[$taskId.'/time_limit_minutes'] = $timeLimitMinutes > 0 ? $timeLimitMinutes : null;
            $updates[$taskId.'/deadline_at'] = $deadlineAt;
            $updates[$taskId.'/recurrence_type'] = $template['recurrence_type'] ?? ($task['recurrence_type'] ?? 'daily');
        }

        if (! empty($updates)) {
            $baseRef->update($updates);
        }
    }

    /**
     * Convert YYYY-MM-DD and HH:MM to Unix timestamp
     */
    protected function buildScheduledTimestamp($date, $time)
    {
        return strtotime($date.' '.$time);
    }

    /**
     * Decide if a recurring template should run on a given date
     */
    protected function isTemplateDueForDate($template, $date)
    {
        $type = $template['recurrence_type'] ?? 'daily';

        if ($type === 'weekly') {
            $weeklyDay = (int) ($template['weekly_day'] ?? 0); // 1 (Mon) - 7 (Sun)
            if ($weeklyDay < 1 || $weeklyDay > 7) {
                return false;
            }

            return (int) date('N', strtotime($date)) === $weeklyDay;
        }

        if ($type === 'every_n_days') {
            $intervalDays = (int) ($template['interval_days'] ?? 0);
            if ($intervalDays < 1) {
                return false;
            }

            $anchorDate = $template['recurrence_anchor_date'] ?? null;
            if (! $anchorDate) {
                return true;
            }

            $anchorTimestamp = strtotime($anchorDate.' 00:00:00');
            $dateTimestamp = strtotime($date.' 00:00:00');
            if ($anchorTimestamp === false || $dateTimestamp === false || $dateTimestamp < $anchorTimestamp) {
                return false;
            }

            $diffDays = (int) floor(($dateTimestamp - $anchorTimestamp) / 86400);

            return $diffDays % $intervalDays === 0;
        }

        // Default mode: daily
        return true;
    }

    /**
     * Delete recurring task template
     */
    public function deleteRecurringTaskTemplate($id)
    {
        $this->database->getReference('cashier_task_templates/'.$id)
            ->remove();
    }

    /**
     * Get cashier worker list
     */
    public function getCashierWorkers()
    {
        $reference = $this->database->getReference('cashier_workers');
        $snapshot = $reference->getSnapshot();

        $workers = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $id => $worker) {
                $workers[] = array_merge(['id' => $id], $worker);
            }
        }

        usort($workers, function ($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $workers;
    }

    /**
     * Get active cashier workers only
     */
    public function getActiveCashierWorkers()
    {
        return array_values(array_filter($this->getCashierWorkers(), function ($worker) {
            return ($worker['is_active'] ?? true) !== false;
        }));
    }

    /**
     * Get cashier worker by id
     */
    public function getCashierWorkerById($id)
    {
        $reference = $this->database->getReference('cashier_workers/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    /**
     * Add cashier worker
     */
    public function addCashierWorker($name)
    {
        $this->database->getReference('cashier_workers')
            ->push([
                'name' => trim($name),
                'is_active' => true,
                'created_at' => time(),
            ]);
    }

    /**
     * Delete cashier worker
     */
    public function deleteCashierWorker($id)
    {
        if ($this->isCashierWorkerReferenced($id)) {
            $this->database->getReference('cashier_workers/'.$id)
                ->update([
                    'is_active' => false,
                    'deactivated_at' => time(),
                ]);

            return;
        }

        $this->database->getReference('cashier_workers/'.$id)
            ->remove();
    }

    /**
     * Check whether cashier worker already referenced in completed tasks
     */
    protected function isCashierWorkerReferenced($id)
    {
        $tasksRef = $this->database->getReference('cashier_tasks');
        $snapshot = $tasksRef->getSnapshot();

        if (! $snapshot->exists()) {
            return false;
        }

        foreach ($snapshot->getValue() as $task) {
            if (($task['completed_by_worker_id'] ?? null) === $id) {
                return true;
            }
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  ATTENDANCE SYSTEM
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get the current attendance QR code value. If none exists, generate one.
     */
    public function getAttendanceQrCode(): string
    {
        $ref = $this->database->getReference('attendance_config/qr_code_value');
        $snapshot = $ref->getSnapshot();

        if ($snapshot->exists() && trim((string) $snapshot->getValue()) !== '') {
            return (string) $snapshot->getValue();
        }

        return $this->regenerateAttendanceQrCode();
    }

    /**
     * Regenerate the attendance QR code with a new random value.
     */
    public function regenerateAttendanceQrCode(): string
    {
        $value = 'ATTENDANCE:'.bin2hex(random_bytes(8));

        $this->database->getReference('attendance_config')->update([
            'qr_code_value' => $value,
            'updated_at' => time(),
        ]);

        return $value;
    }

    /**
     * Verify a scanned QR code value against the stored attendance QR code.
     */
    public function verifyAttendanceQrCode(string $scannedValue): bool
    {
        $stored = $this->getAttendanceQrCode();

        return $scannedValue === $stored;
    }

    /**
     * Get current attendance QR payload for cashier display.
     */
    public function getCashierAttendanceQrData(string $waiterId): array
    {
        $today = date('Y-m-d');
        $waiter = $this->getWaiterById($waiterId);

        if (! $waiter || (($waiter['is_active'] ?? true) === false) || ! empty($waiter['attendance_exempt'])) {
            return [
                'found' => false,
                'available' => false,
                'message' => 'Waiter tidak tersedia untuk absensi QR.',
            ];
        }

        $attendance = $this->getAttendanceByDate($waiterId, $today) ?? [];
        $settings = $this->getSettings();
        $clockOutEnabled = ! empty($settings['clock_out_enabled']);

        $purpose = null;
        $message = 'QR siap dipindai.';
        if (! empty($attendance['clock_out'])) {
            $message = 'Waiter ini sudah menyelesaikan absensi hari ini.';
        } elseif (! empty($attendance['clock_in'])) {
            if ($clockOutEnabled) {
                $purpose = 'clock_out';
                $message = 'QR absen pulang siap dipindai.';
            } else {
                $message = 'Absen masuk sudah tercatat. Absen pulang sedang nonaktif.';
            }
        } else {
            $purpose = 'clock_in';
            $message = 'QR absen masuk siap dipindai.';
        }

        $qrTokens = $this->ensureAttendanceQrTokens($waiterId, $today);

        return [
            'found' => true,
            'available' => $purpose !== null,
            'waiter_id' => $waiterId,
            'waiter_name' => (string) ($waiter['name'] ?? 'Waiter'),
            'date' => $today,
            'purpose' => $purpose,
            'purpose_label' => $purpose === 'clock_out'
                ? 'Absen Pulang'
                : ($purpose === 'clock_in' ? 'Absen Masuk' : 'Tidak Perlu QR'),
            'qr_value' => $purpose ? (string) ($qrTokens[$purpose]['value'] ?? '') : '',
            'message' => $message,
            'attendance' => [
                'clock_in' => $attendance['clock_in'] ?? null,
                'clock_out' => $attendance['clock_out'] ?? null,
                'status' => $attendance['status'] ?? null,
                'late_minutes' => (int) ($attendance['late_minutes'] ?? 0),
            ],
        ];
    }

    /**
     * Ensure per-waiter attendance QR tokens exist for the given date.
     */
    public function ensureAttendanceQrTokens(string $waiterId, string $date): array
    {
        $reference = $this->database->getReference('waiter_attendance_qr/'.$waiterId.'/'.$date);
        $snapshot = $reference->getSnapshot();
        $currentTokens = $snapshot->exists() && is_array($snapshot->getValue()) ? (array) $snapshot->getValue() : [];
        $normalizedTokens = $this->normalizeAttendanceQrTokens($currentTokens, $waiterId, $date, time());

        if ($normalizedTokens !== $currentTokens) {
            $reference->set($normalizedTokens);
        }

        return $normalizedTokens;
    }

    /**
     * Consume a one-time attendance QR token and record the attendance action.
     */
    public function processAttendanceQrScan(string $waiterId, string $purpose, string $scannedValue, string $method = 'qr_scan'): array
    {
        $waiterId = trim($waiterId);
        $purpose = $purpose === 'clock_out' ? 'clock_out' : 'clock_in';
        $scannedValue = trim($scannedValue);

        if ($waiterId === '' || $scannedValue === '' || ! str_starts_with(strtoupper($scannedValue), 'ATTENDANCE:')) {
            return ['success' => false, 'message' => 'QR code absensi tidak valid'];
        }

        $waiter = $this->getWaiterById($waiterId);
        if (! $waiter || (($waiter['is_active'] ?? true) === false)) {
            return ['success' => false, 'message' => 'Data waiter tidak ditemukan'];
        }

        if (! empty($waiter['attendance_exempt'])) {
            return ['success' => false, 'message' => 'Waiter ini tidak wajib menggunakan absensi QR'];
        }

        $settings = $this->getSettings();
        if ($purpose === 'clock_out' && empty($settings['clock_out_enabled'])) {
            return ['success' => false, 'message' => 'Fitur absen pulang tidak aktif'];
        }

        $today = date('Y-m-d');
        $nowTimestamp = time();
        $nowTime = date('H:i', $nowTimestamp);
        $status = 'present';
        $lateMinutes = 0;

        if ($purpose === 'clock_in') {
            $shift = $this->getWaiterShiftForDate($waiterId, $today);
            if ($shift) {
                $clockInTime = $shift['clock_in_time'] ?? null;
                $tolerance = (int) ($shift['late_tolerance_minutes'] ?? 0);

                if ($clockInTime) {
                    $expectedTimestamp = strtotime($today.' '.$clockInTime);
                    $toleranceTimestamp = $expectedTimestamp + ($tolerance * 60);
                    $actualTimestamp = strtotime($today.' '.$nowTime);

                    if ($actualTimestamp > $toleranceTimestamp) {
                        $status = 'late';
                        $lateMinutes = (int) round(($actualTimestamp - $expectedTimestamp) / 60);
                    }
                }
            }
        }

        $attendancePath = 'waiter_attendance/'.$waiterId.'/'.$today;
        $tokenPath = 'waiter_attendance_qr/'.$waiterId.'/'.$today;
        $result = ['success' => false, 'message' => 'Gagal memproses absensi.'];

        $this->database->runTransaction(function ($transaction) use ($attendancePath, $tokenPath, $waiterId, $today, $purpose, $scannedValue, $method, $nowTimestamp, $nowTime, $status, $lateMinutes, &$result) {
            $attendanceReference = $this->database->getReference($attendancePath);
            $tokenReference = $this->database->getReference($tokenPath);
            $attendanceSnapshot = $transaction->snapshot($attendanceReference);
            $tokenSnapshot = $transaction->snapshot($tokenReference);
            $record = $attendanceSnapshot->exists() ? (array) $attendanceSnapshot->getValue() : [];
            $qrTokens = $this->normalizeAttendanceQrTokens($tokenSnapshot->exists() ? (array) $tokenSnapshot->getValue() : [], $waiterId, $today, $nowTimestamp);

            if ($purpose === 'clock_in' && ! empty($record['clock_in'])) {
                $result = [
                    'success' => false,
                    'message' => 'Sudah absen masuk hari ini pada '.$record['clock_in'],
                ];
                return;
            }

            if ($purpose === 'clock_out') {
                if (empty($record['clock_in'])) {
                    $result = ['success' => false, 'message' => 'Belum absen masuk hari ini'];
                    return;
                }

                if (! empty($record['clock_out'])) {
                    $result = [
                        'success' => false,
                        'message' => 'Sudah absen keluar hari ini pada '.$record['clock_out'],
                    ];
                    return;
                }
            }

            $expectedToken = trim((string) ($qrTokens[$purpose]['value'] ?? ''));
            if ($expectedToken === '' || ! hash_equals($expectedToken, $scannedValue)) {
                $result = ['success' => false, 'message' => 'QR code absensi tidak valid'];
                return;
            }

            $existingPurposeState = is_array($qrTokens[$purpose] ?? null) ? $qrTokens[$purpose] : [];
            $qrTokens[$purpose] = array_merge($existingPurposeState, [
                'value' => $this->generateAttendanceQrToken($waiterId, $today, $purpose),
                'generated_at' => $nowTimestamp,
                'updated_at' => $nowTimestamp,
                'last_used_at' => $nowTimestamp,
                'last_used_value_hash' => hash('sha256', $expectedToken),
                'use_count' => (int) ($existingPurposeState['use_count'] ?? 0) + 1,
            ]);

            $record['updated_at'] = $nowTimestamp;

            if ($purpose === 'clock_in') {
                $record['clock_in'] = $nowTime;
                $record['clock_in_timestamp'] = $nowTimestamp;
                $record['status'] = $status;
                $record['late_minutes'] = $lateMinutes;
                $record['method'] = $method;
                $record['note'] = (string) ($record['note'] ?? '');

                $result = [
                    'success' => true,
                    'message' => $status === 'late'
                        ? 'Absen masuk tercatat (terlambat '.$lateMinutes.' menit)'
                        : 'Absen masuk tercatat tepat waktu',
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                ];
            } else {
                $record['clock_out'] = $nowTime;
                $record['clock_out_timestamp'] = $nowTimestamp;

                $result = [
                    'success' => true,
                    'message' => 'Absen keluar tercatat pada '.$nowTime,
                ];
            }

            $transaction->set($attendanceReference, $record);
            $transaction->set($tokenReference, $qrTokens);
        });

        return $result;
    }

    /**
     * Build normalized attendance QR token state for one waiter/date.
     */
    protected function normalizeAttendanceQrTokens(mixed $rawTokens, string $waiterId, string $date, int $nowTimestamp): array
    {
        $tokens = is_array($rawTokens) ? $rawTokens : [];

        foreach (['clock_in', 'clock_out'] as $purpose) {
            $state = is_array($tokens[$purpose] ?? null) ? $tokens[$purpose] : [];
            $value = trim((string) ($state['value'] ?? ''));

            if ($value === '') {
                $value = $this->generateAttendanceQrToken($waiterId, $date, $purpose);
            }

            $generatedAt = (int) ($state['generated_at'] ?? 0);
            $updatedAt = (int) ($state['updated_at'] ?? 0);

            $tokens[$purpose] = array_merge($state, [
                'value' => $value,
                'generated_at' => $generatedAt > 0 ? $generatedAt : $nowTimestamp,
                'updated_at' => $updatedAt > 0 ? $updatedAt : $nowTimestamp,
            ]);
        }

        return $tokens;
    }

    /**
     * Generate one-time attendance QR token.
     */
    protected function generateAttendanceQrToken(string $waiterId, string $date, string $purpose): string
    {
        return 'ATTENDANCE:'.strtoupper($purpose).':'.substr(hash('sha256', $waiterId.'|'.$date.'|'.$purpose.'|'.Str::random(40)), 0, 40);
    }

    /**
     * Record clock-in for a waiter.
     */
    public function clockIn(string $waiterId, string $method = 'qr_scan'): array
    {
        $today = date('Y-m-d');
        $now = date('H:i');

        // Check if already clocked in today
        $existing = $this->getAttendanceByDate($waiterId, $today);
        if ($existing && ! empty($existing['clock_in'])) {
            return [
                'success' => false,
                'message' => 'Sudah absen masuk hari ini pada '.$existing['clock_in'],
            ];
        }

        // Get waiter data
        $waiter = $this->getWaiterById($waiterId);
        if (! $waiter) {
            return ['success' => false, 'message' => 'Data waiter tidak ditemukan'];
        }

        // Determine late status based on today's schedule template (not static shift_id)
        $status = 'present';
        $lateMinutes = 0;
        $shift = $this->getWaiterShiftForDate($waiterId, $today);

        if ($shift) {
            $clockInTime = $shift['clock_in_time'] ?? null;
            $tolerance = (int) ($shift['late_tolerance_minutes'] ?? 0);

            if ($clockInTime) {
                $expectedTimestamp = strtotime($today.' '.$clockInTime);
                $toleranceTimestamp = $expectedTimestamp + ($tolerance * 60);
                $actualTimestamp = strtotime($today.' '.$now);

                if ($actualTimestamp > $toleranceTimestamp) {
                    $status = 'late';
                    $lateMinutes = (int) round(($actualTimestamp - $expectedTimestamp) / 60);
                }
            }
        }

        $record = [
            'clock_in' => $now,
            'clock_in_timestamp' => time(),
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'method' => $method,
            'note' => '',
            'updated_at' => time(),
        ];

        $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$today)->update($record);

        return [
            'success' => true,
            'message' => $status === 'late'
                ? 'Absen masuk tercatat (terlambat '.$lateMinutes.' menit)'
                : 'Absen masuk tercatat tepat waktu',
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ];
    }

    /**
     * Record clock-out for a waiter.
     */
    public function clockOut(string $waiterId, string $method = 'qr_scan'): array
    {
        $today = date('Y-m-d');
        $now = date('H:i');

        $existing = $this->getAttendanceByDate($waiterId, $today);

        if (! $existing || empty($existing['clock_in'])) {
            return ['success' => false, 'message' => 'Belum absen masuk hari ini'];
        }

        if (! empty($existing['clock_out'])) {
            return [
                'success' => false,
                'message' => 'Sudah absen keluar hari ini pada '.$existing['clock_out'],
            ];
        }

        $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$today)->update([
            'clock_out' => $now,
            'clock_out_timestamp' => time(),
            'updated_at' => time(),
        ]);

        return ['success' => true, 'message' => 'Absen keluar tercatat pada '.$now];
    }

    // ===================================================================
    // SHIFT MANAGEMENT
    // ===================================================================

    public function getShifts()
    {
        $reference = $this->database->getReference('work_shifts');
        $snapshot = $reference->getSnapshot();

        $shifts = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $shift) {
                $shifts[] = array_merge(['id' => $key], $shift);
            }
        }

        usort($shifts, function ($a, $b) {
            return ($a['name'] ?? '') <=> ($b['name'] ?? '');
        });

        return $shifts;
    }

    public function getActiveShifts()
    {
        return array_values(array_filter($this->getShifts(), function ($shift) {
            return ($shift['is_active'] ?? true) !== false;
        }));
    }

    public function getShiftById($id)
    {
        $reference = $this->database->getReference('work_shifts/'.$id);
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $id], $snapshot->getValue());
    }

    public function createShift(array $data)
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'clock_in_time' => trim((string) ($data['clock_in_time'] ?? '08:00')),
            'clock_out_time' => trim((string) ($data['clock_out_time'] ?? '17:00')),
            'late_tolerance_minutes' => max(0, (int) ($data['late_tolerance_minutes'] ?? 15)),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $created = $this->database->getReference('work_shifts')->push($payload);

        return array_merge(['id' => $created->getKey()], $payload);
    }

    public function updateShift($id, array $data)
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'clock_in_time' => trim((string) ($data['clock_in_time'] ?? '08:00')),
            'clock_out_time' => trim((string) ($data['clock_out_time'] ?? '17:00')),
            'late_tolerance_minutes' => max(0, (int) ($data['late_tolerance_minutes'] ?? 15)),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'updated_at' => time(),
        ];

        $this->database->getReference('work_shifts/'.$id)->update($payload);
    }

    public function deleteShift($id)
    {
        $waitersRef = $this->database->getReference('allowed_waiters');
        $waitersSnap = $waitersRef->getSnapshot();

        if ($waitersSnap->exists()) {
            $updates = [];
            foreach ($waitersSnap->getValue() as $waiterId => $waiter) {
                if (($waiter['shift_id'] ?? null) === $id) {
                    $updates[$waiterId.'/shift_id'] = null;
                }
            }
            if (! empty($updates)) {
                $waitersRef->update($updates);
            }
        }

        $this->database->getReference('work_shifts/'.$id)->remove();
    }

    // ===================================================================
    // SCHEDULE TEMPLATE (permanent, no per-week)
    // ===================================================================

    /**
     * In-memory cache for schedule template (avoids repeated Firebase reads within same request).
     */
    private ?array $scheduleTemplateCache = null;

    /**
     * Get schedule template for all waiters.
     * Returns: ['waiter_id' => ['monday' => 'shift_id'|'off', ...], ...]
     * Cached per-request to avoid N+1 reads.
     */
    public function getScheduleTemplate(): array
    {
        if ($this->scheduleTemplateCache !== null) {
            return $this->scheduleTemplateCache;
        }

        $ref = $this->database->getReference('waiter_schedule_template');
        $snapshot = $ref->getSnapshot();
        if (!$snapshot->exists()) {
            $this->scheduleTemplateCache = [];
            return [];
        }

        $this->scheduleTemplateCache = $snapshot->getValue();
        return $this->scheduleTemplateCache;
    }

    /**
     * Save entire schedule template for all waiters.
     * $schedule = ['waiter_id' => ['monday' => 'shift_id'|'off', ...], ...]
     */
    public function saveScheduleTemplate(array $schedule): void
    {
        $payload = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($schedule as $waiterId => $dayAssignments) {
            foreach ($days as $day) {
                $payload[$waiterId][$day] = $dayAssignments[$day] ?? 'off';
            }
            $payload[$waiterId]['updated_at'] = time();
        }

        $this->database->getReference('waiter_schedule_template')->set($payload);
        $this->scheduleTemplateCache = null; // Invalidate cache
    }

    /**
     * Get waiter's shift for a specific date (from template).
     * Returns the shift data array or null if day off / no schedule.
     */
    public function getWaiterShiftForDate(string $waiterId, string $date): ?array
    {
        $dayOfWeek = strtolower(date('l', strtotime($date))); // monday, tuesday, etc.
        $template = $this->getScheduleTemplate();
        $shiftId = $template[$waiterId][$dayOfWeek] ?? null;

        if (!$shiftId || $shiftId === 'off') {
            return null;
        }

        return $this->getShiftById($shiftId);
    }

    /**
     * Check if a waiter is working on a specific date (from template).
     */
    public function isWorkingDay(string $waiterId, string $date): bool
    {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        $template = $this->getScheduleTemplate();
        $shiftId = $template[$waiterId][$dayOfWeek] ?? null;

        return $shiftId !== null && $shiftId !== 'off';
    }

    /**
     * BACKWARD COMPAT: Get waiter's shift for TODAY.
     */
    public function getWaiterShift(string $waiterId): ?array
    {
        return $this->getWaiterShiftForDate($waiterId, date('Y-m-d'));
    }

    /**
     * BACKWARD COMPAT: Get waiter schedule as boolean map.
     */
    public function getWaiterSchedule(string $waiterId, ?string $weekKey = null): array
    {
        $template = $this->getScheduleTemplate();
        $waiterSchedule = $template[$waiterId] ?? [];

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $result = [];
        foreach ($days as $day) {
            $val = $waiterSchedule[$day] ?? null;
            $result[$day] = ($val !== null && $val !== 'off');
        }

        return $result;
    }

    /**
     * BACKWARD COMPAT: Get all waiter schedules as boolean maps.
     */
    public function getAllWaiterSchedules(?string $weekKey = null): array
    {
        $template = $this->getScheduleTemplate();
        $result = [];

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($template as $waiterId => $waiterSchedule) {
            if (!is_array($waiterSchedule)) continue;
            foreach ($days as $day) {
                $val = $waiterSchedule[$day] ?? null;
                $result[$waiterId][$day] = ($val !== null && $val !== 'off');
            }
        }

        return $result;
    }

    public function getAttendanceByDate(string $waiterId, string $date): ?array
    {
        $ref = $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$date);
        $snapshot = $ref->getSnapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return $snapshot->getValue();
    }

    /**
     * Resolve a HH:MM attendance value into a Unix timestamp for the given date.
     */
    protected function resolveAttendanceTimestamp(string $date, mixed $timeValue): ?int
    {
        $timeString = trim((string) $timeValue);
        if ($timeString === '') {
            return null;
        }

        if (is_numeric($timeValue)) {
            $numeric = (int) $timeValue;
            if ($numeric > 0) {
                return $numeric;
            }
        }

        $normalized = preg_match('/^\d{2}:\d{2}$/', $timeString) ? $timeString.':00' : $timeString;
        $timestamp = strtotime($date.' '.$normalized);

        return $timestamp !== false ? $timestamp : null;
    }

    public function getAttendanceByMonth(string $waiterId, string $yearMonth): array
    {
        $ref = $this->database->getReference('waiter_attendance/'.$waiterId);
        $snapshot = $ref->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $all = $snapshot->getValue();
        $filtered = [];
        $prefix = $yearMonth.'-';

        foreach ($all as $date => $record) {
            if (strpos($date, $prefix) === 0) {
                $filtered[$date] = $record;
            }
        }

        ksort($filtered);

        return $filtered;
    }

    /**
     * Get all waiters' attendance for a specific date.
     */
    public function getAllAttendanceByDate(string $date): array
    {
        $ref = $this->database->getReference('waiter_attendance');
        $snapshot = $ref->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $result = [];
        foreach ($snapshot->getValue() as $waiterId => $dates) {
            if (isset($dates[$date]) && is_array($dates[$date])) {
                $result[$waiterId] = $dates[$date];
            }
        }

        return $result;
    }

    /**
     * Admin override: update attendance record for a waiter on a date.
     */
    public function updateAttendance(string $waiterId, string $date, array $data): void
    {
        $allowed = ['clock_in', 'clock_out', 'status', 'late_minutes', 'note'];
        $payload = ['updated_at' => time(), 'method' => 'admin_override'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('clock_in', $payload)) {
            $payload['clock_in_timestamp'] = $this->resolveAttendanceTimestamp($date, $payload['clock_in']);
        }

        if (array_key_exists('clock_out', $payload)) {
            $payload['clock_out_timestamp'] = $this->resolveAttendanceTimestamp($date, $payload['clock_out']);
        }

        $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$date)->update($payload);
    }

    /**
     * Claim a durable reminder dispatch slot for one waiter/date/type.
     */
    public function claimTaskReminderDispatch(string $waiterId, string $date, string $type, int $cooldownSeconds, int $now, int $maxSends = 0, int $lockSeconds = 300): bool
    {
        $path = 'waiter_task_reminder_state/'.$waiterId.'/'.$date.'/'.$type;
        $allowed = false;

        $this->database->runTransaction(function ($transaction) use ($path, $cooldownSeconds, $now, $maxSends, $lockSeconds, &$allowed) {
            $reference = $this->database->getReference($path);
            $snapshot = $transaction->snapshot($reference);
            $state = $snapshot->exists() ? (array) $snapshot->getValue() : [];

            $lastSentAt = (int) ($state['last_sent_at'] ?? 0);
            $dispatchingUntil = (int) ($state['dispatching_until'] ?? 0);
            $sendCount = (int) ($state['send_count'] ?? 0);

            // Check max sends limit
            if ($maxSends > 0 && $sendCount >= $maxSends) {
                $allowed = false;
                return;
            }

            if (($lastSentAt > 0 && ($now - $lastSentAt) < $cooldownSeconds) || $dispatchingUntil > $now) {
                $allowed = false;

                return;
            }

            $allowed = true;
            $transaction->set($reference, array_merge($state, [
                'dispatching_until' => $now + $lockSeconds,
                'last_attempt_at' => $now,
                'updated_at' => $now,
            ]));
        });

        return $allowed;
    }

    /**
     * Persist a successful reminder send and clear any dispatch lock.
     */
    public function completeTaskReminderDispatch(string $waiterId, string $date, string $type, int $sentAt, array $metadata = []): void
    {
        $path = 'waiter_task_reminder_state/'.$waiterId.'/'.$date.'/'.$type;
        $reference = $this->database->getReference($path);
        $snapshot = $reference->getSnapshot();
        $state = $snapshot->exists() ? (array) $snapshot->getValue() : [];
        
        $sendCount = (int) ($state['send_count'] ?? 0);
        
        $payload = [
            'last_sent_at' => $sentAt,
            'dispatching_until' => null,
            'updated_at' => $sentAt,
            'send_count' => $sendCount + 1,
        ];

        foreach ($metadata as $key => $value) {
            $payload[$key] = $value;
        }

        $reference->update($payload);
    }

    /**
     * Release a reminder dispatch lock after a failed send.
     */
    public function releaseTaskReminderDispatch(string $waiterId, string $date, string $type, int $releasedAt): void
    {
        $this->database->getReference('waiter_task_reminder_state/'.$waiterId.'/'.$date.'/'.$type)->update([
            'dispatching_until' => null,
            'updated_at' => $releasedAt,
        ]);
    }

    /**
     * Get attendance summary for a waiter in a given month.
     */
    public function getAttendanceSummary(string $waiterId, string $yearMonth): array
    {
        $records = $this->getAttendanceByMonth($waiterId, $yearMonth);
        $schedule = $this->getWaiterSchedule($waiterId);

        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 5, 2);
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $today = date('Y-m-d');

        $summary = [
            'total_days_worked' => 0,
            'total_on_time' => 0,
            'total_late' => 0,
            'total_absent' => 0,
            'total_day_off' => 0,
            'total_sick' => 0,
        ];

        $dayMap = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);

            // Skip future dates
            if ($dateStr > $today) {
                break;
            }

            $dayOfWeek = (int) date('N', strtotime($dateStr));
            $dayName = $dayMap[$dayOfWeek] ?? '';

            // Check if scheduled to work
            $isWorkDay = true;
            if ($schedule && $dayName !== '') {
                $isWorkDay = ! empty($schedule[$dayName]);
            }

            $record = $records[$dateStr] ?? null;

            if (! $isWorkDay) {
                $summary['total_day_off']++;
                continue;
            }

            if (! $record || empty($record['clock_in'])) {
                // Check if status was manually set
                $manualStatus = $record['status'] ?? null;
                if ($manualStatus === 'sick') {
                    $summary['total_sick']++;
                } elseif ($manualStatus === 'day_off') {
                    $summary['total_day_off']++;
                } else {
                    $summary['total_absent']++;
                }
                continue;
            }

            $status = $record['status'] ?? 'present';
            $summary['total_days_worked']++;

            if ($status === 'late') {
                $summary['total_late']++;
            } elseif ($status === 'sick') {
                $summary['total_sick']++;
                $summary['total_days_worked']--;
            } elseif ($status === 'day_off') {
                $summary['total_day_off']++;
                $summary['total_days_worked']--;
            } else {
                $summary['total_on_time']++;
            }
        }

        return $summary;
    }

    // ===== BONUS SYSTEM METHODS =====

    /**
     * Get bonus configuration
     */
    public function getBonusConfig(): array
    {
        $snapshot = $this->database->getReference('bonus_config')->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : [];
    }

    /**
     * Update bonus configuration
     */
    public function updateBonusConfig(array $data): void
    {
        $data['updated_at'] = time();
        $this->database->getReference('bonus_config')->set($data);
    }

    /**
     * Save daily points for a waiter
     */
    public function saveDailyPoints(string $waiterId, string $date, array $data): void
    {
        $data['updated_at'] = time();
        $this->database->getReference("waiter_daily_points/{$waiterId}/{$date}")->set($data);
    }

    /**
     * Get daily points for a waiter on a specific date
     */
    public function getDailyPoints(string $waiterId, string $date): ?array
    {
        $snapshot = $this->database->getReference("waiter_daily_points/{$waiterId}/{$date}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Get all daily points for a waiter in a month
     */
    public function getMonthlyDailyPoints(string $waiterId, string $month): array
    {
        $snapshot = $this->database->getReference("waiter_daily_points/{$waiterId}")->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $allDays = $snapshot->getValue();
        $result = [];
        foreach ($allDays as $date => $record) {
            if (str_starts_with($date, $month)) {
                $result[$date] = $record;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Get all waiters' daily points for a specific date
     */
    public function getAllDailyPointsByDate(string $date): array
    {
        $snapshot = $this->database->getReference('waiter_daily_points')->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $all = $snapshot->getValue();
        $result = [];
        foreach ($all as $waiterId => $days) {
            if (isset($days[$date])) {
                $result[$waiterId] = $days[$date];
            }
        }
        return $result;
    }

    /**
     * Create a penalty record
     */
    public function createPenalty(array $data): string
    {
        $ref = $this->database->getReference('waiter_penalties')->push($data);
        return $ref->getKey();
    }

    /**
     * Get all penalties, optionally filtered by month and/or waiter
     */
    public function getPenalties(?string $month = null, ?string $waiterId = null): array
    {
        $snapshot = $this->database->getReference('waiter_penalties')->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $all = $snapshot->getValue();
        $result = [];
        foreach ($all as $id => $penalty) {
            if ($month && ($penalty['month'] ?? '') !== $month) continue;
            if ($waiterId && ($penalty['waiter_id'] ?? '') !== $waiterId) continue;
            $penalty['id'] = $id;
            $result[] = $penalty;
        }
        
        // Sort by date desc
        usort($result, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        return $result;
    }

    /**
     * Delete a penalty
     */
    public function deletePenalty(string $penaltyId): void
    {
        $this->database->getReference("waiter_penalties/{$penaltyId}")->remove();
    }

    /**
     * Get a single penalty by ID
     */
    public function getPenaltyById(string $penaltyId): ?array
    {
        $snapshot = $this->database->getReference("waiter_penalties/{$penaltyId}")->getSnapshot();
        if (!$snapshot->exists()) return null;
        $data = $snapshot->getValue();
        $data['id'] = $penaltyId;
        return $data;
    }

    /**
     * Save sales target for a waiter/month
     */
    public function saveSalesTarget(string $waiterId, string $month, array $data): void
    {
        $data['last_updated_at'] = time();
        $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}")->update($data);
    }

    /**
     * Get sales target for a waiter/month
     */
    public function getSalesTarget(string $waiterId, string $month): ?array
    {
        $snapshot = $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Get all sales targets for a month
     */
    public function getAllSalesTargets(string $month): array
    {
        $snapshot = $this->database->getReference('waiter_sales_targets')->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $all = $snapshot->getValue();
        $result = [];
        foreach ($all as $waiterId => $months) {
            if (isset($months[$month])) {
                $target = $months[$month];
                $target['waiter_id'] = $waiterId;
                $result[] = $target;
            }
        }
        return $result;
    }

    /**
     * Record daily sales for a waiter
     */
    public function recordDailySales(string $waiterId, string $month, string $date, array $salesData): void
    {
        $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}/daily_sales/{$date}")->set($salesData);
        
        // Recalculate current_achievement
        $snapshot = $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}/daily_sales")->getSnapshot();
        $totalAchievement = 0;
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $day => $record) {
                $totalAchievement += (int)($record['amount'] ?? 0);
            }
        }
        
        $targetSnapshot = $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}/target_amount")->getSnapshot();
        $targetAmount = $targetSnapshot->exists() ? (int)$targetSnapshot->getValue() : 0;
        $percentage = $targetAmount > 0 ? round(($totalAchievement / $targetAmount) * 100, 1) : 0;
        
        $this->database->getReference("waiter_sales_targets/{$waiterId}/{$month}")->update([
            'current_achievement' => $totalAchievement,
            'achievement_percentage' => $percentage,
            'last_updated_at' => time(),
        ]);
    }

    /**
     * Save monthly bonus summary
     */
    public function saveBonusSummary(string $waiterId, string $month, array $data): void
    {
        $data['updated_at'] = time();
        $this->database->getReference("waiter_bonus_summary/{$waiterId}/{$month}")->set($data);
    }

    /**
     * Get monthly bonus summary for a waiter
     */
    public function getBonusSummary(string $waiterId, string $month): ?array
    {
        $snapshot = $this->database->getReference("waiter_bonus_summary/{$waiterId}/{$month}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Get all bonus summaries for a month
     */
    public function getAllBonusSummaries(string $month): array
    {
        $snapshot = $this->database->getReference('waiter_bonus_summary')->getSnapshot();
        if (!$snapshot->exists()) return [];
        
        $all = $snapshot->getValue();
        $result = [];
        foreach ($all as $waiterId => $months) {
            if (isset($months[$month])) {
                $summary = $months[$month];
                $summary['waiter_id'] = $waiterId;
                $result[] = $summary;
            }
        }
        return $result;
    }

    /**
     * Save leaderboard for a month
     */
    public function saveLeaderboard(string $month, array $data): void
    {
        $data['last_calculated_at'] = time();
        $this->database->getReference("waiter_leaderboard/{$month}")->set($data);
    }

    /**
     * Get leaderboard for a month
     */
    public function getLeaderboard(string $month): ?array
    {
        $snapshot = $this->database->getReference("waiter_leaderboard/{$month}")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Get waiter specialist role
     */
    public function getWaiterSpecialistRole(string $waiterId): ?string
    {
        $snapshot = $this->database->getReference("allowed_waiters/{$waiterId}/specialist_role")->getSnapshot();
        return $snapshot->exists() ? $snapshot->getValue() : null;
    }

    /**
     * Update waiter specialist role
     */
    public function updateWaiterSpecialistRole(string $waiterId, ?string $role): void
    {
        $this->database->getReference("allowed_waiters/{$waiterId}/specialist_role")->set($role);
    }

    // ==========================================
    // RESTOCK & PURCHASE ORDER SYSTEM
    // ==========================================

    /**
     * Create or update a restock request (dedup: same product+rack+pending = update)
     */
    public function createOrUpdateRestockRequest(array $data): string
    {
        $productId = $data['product_id'];
        $rackId = $data['rack_id'];

        // Check for existing pending entry for same product+rack
        $existing = $this->database->getReference('restock_requests')
            ->orderByChild('product_id')
            ->equalTo($productId)
            ->getSnapshot()
            ->getValue();

        if ($existing) {
            foreach ($existing as $key => $entry) {
                if (($entry['rack_id'] ?? '') === $rackId && ($entry['status'] ?? '') === 'pending') {
                    // Update existing entry with latest qty
                    $this->database->getReference("restock_requests/{$key}")->update([
                        'reported_qty' => (int) $data['reported_qty'],
                        'qty_needed' => (int) $data['qty_needed'],
                        'reported_by' => $data['reported_by'],
                        'reported_by_name' => $data['reported_by_name'],
                        'reported_at' => time(),
                        'updated_at' => time(),
                    ]);
                    return $key;
                }
            }
        }

        // Create new entry
        $payload = [
            'product_id' => $productId,
            'product_name' => $data['product_name'] ?? '',
            'product_category_id' => $data['product_category_id'] ?? null,
            'product_category_name' => $data['product_category_name'] ?? 'Tanpa Kategori',
            'rack_id' => $rackId,
            'rack_name' => $data['rack_name'] ?? '',
            'reported_qty' => (int) ($data['reported_qty'] ?? 0),
            'standard_qty' => (int) ($data['standard_qty'] ?? 0),
            'min_qty' => (int) ($data['min_qty'] ?? 0),
            'qty_needed' => (int) ($data['qty_needed'] ?? 0),
            'reported_by' => $data['reported_by'] ?? '',
            'reported_by_name' => $data['reported_by_name'] ?? '',
            'reported_at' => time(),
            'date' => $data['date'] ?? date('Y-m-d'),
            'status' => 'pending',
            'po_id' => null,
            'received_at' => null,
            'received_by' => null,
            'received_by_name' => null,
            'received_qty' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $newRef = $this->database->getReference('restock_requests')->push($payload);
        return $newRef->getKey();
    }

    /**
     * Get pending restock requests
     */
    public function getPendingRestockRequests(): array
    {
        $snapshot = $this->database->getReference('restock_requests')
            ->orderByChild('status')
            ->equalTo('pending')
            ->getSnapshot();

        $items = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $item) {
                $item['id'] = $key;
                $items[] = $item;
            }
            // Sort by reported_at desc
            usort($items, fn($a, $b) => ($b['reported_at'] ?? 0) - ($a['reported_at'] ?? 0));
        }
        return $items;
    }

    /**
     * Get pending restock requests grouped by product (aggregated across racks)
     */
    public function getPendingRestockGroupedByProduct(): array
    {
        $pending = $this->getPendingRestockRequests();

        // Group by product_id, aggregate qty_needed across racks
        $grouped = [];
        foreach ($pending as $item) {
            $productId = $item['product_id'] ?? '';
            if (!$productId) continue;

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $item['product_name'] ?? '',
                    'product_category_id' => $item['product_category_id'] ?? null,
                    'product_category_name' => $item['product_category_name'] ?? 'Tanpa Kategori',
                    'total_qty_needed' => 0,
                    'racks' => [],
                    'restock_ids' => [],
                    'last_reported_at' => 0,
                ];
            }

            $grouped[$productId]['total_qty_needed'] += (int) ($item['qty_needed'] ?? 0);
            $grouped[$productId]['restock_ids'][] = $item['id'];
            $grouped[$productId]['racks'][] = [
                'rack_id' => $item['rack_id'] ?? '',
                'rack_name' => $item['rack_name'] ?? '',
                'qty_needed' => (int) ($item['qty_needed'] ?? 0),
                'reported_qty' => (int) ($item['reported_qty'] ?? 0),
                'standard_qty' => (int) ($item['standard_qty'] ?? 0),
                'restock_id' => $item['id'],
            ];

            $reportedAt = (int) ($item['reported_at'] ?? 0);
            if ($reportedAt > $grouped[$productId]['last_reported_at']) {
                $grouped[$productId]['last_reported_at'] = $reportedAt;
            }
        }

        // Sort by category then product name
        $result = array_values($grouped);
        usort($result, function ($a, $b) {
            $catCmp = ($a['product_category_name'] ?? '') <=> ($b['product_category_name'] ?? '');
            if ($catCmp !== 0) return $catCmp;
            return ($a['product_name'] ?? '') <=> ($b['product_name'] ?? '');
        });

        return $result;
    }

    /**
     * Get all restock requests (optionally filtered by status)
     */
    public function getRestockRequests(?string $status = null): array
    {
        if ($status) {
            $snapshot = $this->database->getReference('restock_requests')
                ->orderByChild('status')
                ->equalTo($status)
                ->getSnapshot();
        } else {
            $snapshot = $this->database->getReference('restock_requests')->getSnapshot();
        }

        $items = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $item) {
                if (is_array($item)) {
                    $item['id'] = $key;
                    $items[] = $item;
                }
            }
            usort($items, fn($a, $b) => ($b['reported_at'] ?? 0) - ($a['reported_at'] ?? 0));
        }
        return $items;
    }

    /**
     * Create a Purchase Order from selected restock requests
     */
    public function createPurchaseOrder(array $restockIds, string $createdBy, string $createdByName, ?string $supplier = null, ?string $notes = null, array $qtyOverrides = []): ?string
    {
        // Generate PO number
        $today = date('Ymd');
        $existingPOs = $this->database->getReference('purchase_orders')
            ->orderByChild('created_date')
            ->equalTo($today)
            ->getSnapshot()
            ->getValue();
        $seq = $existingPOs ? count($existingPOs) + 1 : 1;
        $poNumber = "PO-{$today}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);

        // Build PO items from restock requests
        $items = [];
        $itemsCount = 0;
        foreach ($restockIds as $restockId) {
            $snapshot = $this->database->getReference("restock_requests/{$restockId}")->getSnapshot();
            if (!$snapshot->exists()) continue;

            $req = $snapshot->getValue();
            $qtyNeeded = (int) ($req['qty_needed'] ?? 0);
            $qtyOrdered = isset($qtyOverrides[$restockId]) ? (int) $qtyOverrides[$restockId] : $qtyNeeded;

            $items[$restockId] = [
                'product_id' => $req['product_id'] ?? '',
                'product_name' => $req['product_name'] ?? '',
                'product_category_id' => $req['product_category_id'] ?? null,
                'product_category_name' => $req['product_category_name'] ?? 'Tanpa Kategori',
                'rack_id' => $req['rack_id'] ?? '',
                'rack_name' => $req['rack_name'] ?? '',
                'qty_needed' => $qtyNeeded,
                'qty_ordered' => $qtyOrdered,
                'received_qty' => 0,
                'received' => false,
            ];
            $itemsCount++;
        }

        if ($itemsCount === 0) return null;

        $poPayload = [
            'po_number' => $poNumber,
            'created_at' => time(),
            'created_date' => $today,
            'created_by' => $createdBy,
            'created_by_name' => $createdByName,
            'status' => 'ordered',
            'supplier' => $supplier,
            'notes' => $notes,
            'items_count' => $itemsCount,
            'received_count' => 0,
            'items' => $items,
        ];

        $poRef = $this->database->getReference('purchase_orders')->push($poPayload);
        $poId = $poRef->getKey();

        // Update restock requests status to 'ordered' and link po_id
        foreach ($restockIds as $restockId) {
            $this->database->getReference("restock_requests/{$restockId}")->update([
                'status' => 'ordered',
                'po_id' => $poId,
                'updated_at' => time(),
            ]);
        }

        return $poId;
    }

    /**
     * Get all purchase orders
     */
    public function getPurchaseOrders(?string $status = null): array
    {
        $ref = $this->database->getReference('purchase_orders');
        $snapshot = $ref->getSnapshot();

        $orders = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $order) {
                if (!is_array($order)) continue;
                if ($status && ($order['status'] ?? '') !== $status) continue;
                $order['id'] = $key;
                $orders[] = $order;
            }
            usort($orders, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        }
        return $orders;
    }

    /**
     * Get a single purchase order by ID
     */
    public function getPurchaseOrder(string $poId): ?array
    {
        $snapshot = $this->database->getReference("purchase_orders/{$poId}")->getSnapshot();
        if (!$snapshot->exists()) return null;
        $order = $snapshot->getValue();
        $order['id'] = $poId;
        return $order;
    }

    /**
     * Receive an item in a PO (partial receive with qty)
     */
    public function receivePoItem(string $poId, string $restockId, int $receivedQty, string $receivedBy, string $receivedByName): array
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po) return ['success' => false, 'message' => 'PO tidak ditemukan'];

        $items = $po['items'] ?? [];
        if (!isset($items[$restockId])) return ['success' => false, 'message' => 'Item tidak ditemukan di PO'];

        $item = $items[$restockId];
        $qtyOrdered = (int) ($item['qty_ordered'] ?? 0);
        $currentReceived = (int) ($item['received_qty'] ?? 0);
        $newReceived = $currentReceived + $receivedQty;

        // Update PO item
        $itemUpdates = [
            "items/{$restockId}/received_qty" => $newReceived,
            "items/{$restockId}/received" => $newReceived >= $qtyOrdered,
            "items/{$restockId}/last_received_at" => time(),
            "items/{$restockId}/last_received_by" => $receivedBy,
            "items/{$restockId}/last_received_by_name" => $receivedByName,
        ];

        $this->database->getReference("purchase_orders/{$poId}")->update($itemUpdates);

        // Count total received items
        $receivedCount = 0;
        $totalItems = count($items);
        foreach ($items as $rId => $itm) {
            $rQty = ($rId === $restockId) ? $newReceived : (int) ($itm['received_qty'] ?? 0);
            $oQty = (int) ($itm['qty_ordered'] ?? 0);
            if ($rQty >= $oQty) $receivedCount++;
        }

        // Update PO status
        $poStatus = 'ordered';
        if ($receivedCount >= $totalItems) {
            $poStatus = 'completed';
        } elseif ($receivedCount > 0 || $newReceived > 0) {
            $poStatus = 'partial';
        }

        $this->database->getReference("purchase_orders/{$poId}")->update([
            'received_count' => $receivedCount,
            'status' => $poStatus,
        ]);

        // Update restock request
        $restockUpdates = [
            'received_qty' => $newReceived,
            'received_by' => $receivedBy,
            'received_by_name' => $receivedByName,
            'received_at' => time(),
            'updated_at' => time(),
        ];
        if ($newReceived >= $qtyOrdered) {
            $restockUpdates['status'] = 'received';
        }
        $this->database->getReference("restock_requests/{$restockId}")->update($restockUpdates);

        return [
            'success' => true,
            'po_status' => $poStatus,
            'received_count' => $receivedCount,
            'total_items' => $totalItems,
            'item_completed' => $newReceived >= $qtyOrdered,
            'po_completed' => $poStatus === 'completed',
            'new_received_qty' => $newReceived,
            'qty_ordered' => $qtyOrdered,
        ];
    }

    /**
     * Accept PO item "as is" - mark as completed even if qty doesn't match order
     * Supervisor action when supplier can't fulfill full order
     */
    public function acceptPoItemAsIs(string $poId, string $restockId, string $acceptedBy, string $acceptedByName): array
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po) return ['success' => false, 'message' => 'PO tidak ditemukan'];

        $items = $po['items'] ?? [];
        if (!isset($items[$restockId])) return ['success' => false, 'message' => 'Item tidak ditemukan di PO'];

        $item = $items[$restockId];
        $receivedQty = (int) ($item['received_qty'] ?? 0);

        // Mark item as completed regardless of qty
        $itemUpdates = [
            "items/{$restockId}/received" => true,
            "items/{$restockId}/accepted_as_is" => true,
            "items/{$restockId}/accepted_as_is_at" => time(),
            "items/{$restockId}/accepted_as_is_by" => $acceptedBy,
            "items/{$restockId}/accepted_as_is_by_name" => $acceptedByName,
        ];

        $this->database->getReference("purchase_orders/{$poId}")->update($itemUpdates);

        // Recount received items
        $receivedCount = 0;
        $totalItems = count($items);
        foreach ($items as $rId => $itm) {
            $isReceived = ($rId === $restockId) ? true : (bool) ($itm['received'] ?? false);
            if ($isReceived) $receivedCount++;
        }

        // Update PO status
        $poStatus = ($receivedCount >= $totalItems) ? 'completed' : 'partial';

        $this->database->getReference("purchase_orders/{$poId}")->update([
            'received_count' => $receivedCount,
            'status' => $poStatus,
        ]);

        // Update restock request
        $this->database->getReference("restock_requests/{$restockId}")->update([
            'status' => 'received',
            'accepted_as_is' => true,
            'updated_at' => time(),
        ]);

        return [
            'success' => true,
            'po_status' => $poStatus,
            'received_count' => $receivedCount,
            'total_items' => $totalItems,
            'po_completed' => $poStatus === 'completed',
        ];
    }

    /**
     * Report an issue with a PO item (not received, wrong qty, damaged)
     * "Barang tidak datang" auto-closes item with received = true (qty stays 0)
     */
    public function reportPoItemIssue(string $poId, string $restockId, string $issueNote, string $reportedBy, string $reportedByName): array
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po) return ['success' => false, 'message' => 'PO tidak ditemukan'];

        $items = $po['items'] ?? [];
        if (!isset($items[$restockId])) return ['success' => false, 'message' => 'Item tidak ditemukan di PO'];

        // Store issue
        $issueData = [
            'note' => $issueNote,
            'reported_by' => $reportedBy,
            'reported_by_name' => $reportedByName,
            'reported_at' => time(),
        ];
        $this->database->getReference("purchase_orders/{$poId}/items/{$restockId}/issue")->set($issueData);

        // "Barang tidak datang" = auto-close item (received_qty stays at current, mark as closed)
        $itemClosed = false;
        $isNotReceived = str_contains(strtolower($issueNote), 'tidak datang');

        if ($isNotReceived) {
            $this->database->getReference("purchase_orders/{$poId}/items/{$restockId}")->update([
                'received' => true,
                'closed_reason' => 'not_received',
                'closed_at' => time(),
                'closed_by' => $reportedBy,
                'closed_by_name' => $reportedByName,
            ]);
            $itemClosed = true;

            // Recount received items and update PO status
            $receivedCount = 0;
            $totalItems = count($items);
            foreach ($items as $rId => $itm) {
                if ($rId === $restockId) {
                    $receivedCount++; // This item is now closed
                } else {
                    $rQty = (int) ($itm['received_qty'] ?? 0);
                    $oQty = (int) ($itm['qty_ordered'] ?? 0);
                    if (!empty($itm['received']) || $rQty >= $oQty) $receivedCount++;
                }
            }

            $poStatus = 'ordered';
            if ($receivedCount >= $totalItems) {
                $poStatus = 'completed';
            } elseif ($receivedCount > 0) {
                $poStatus = 'partial';
            }

            $this->database->getReference("purchase_orders/{$poId}")->update([
                'received_count' => $receivedCount,
                'status' => $poStatus,
            ]);

            // Update restock request status
            $this->database->getReference("restock_requests/{$restockId}")->update([
                'status' => 'not_received',
                'issue_note' => $issueNote,
                'updated_at' => time(),
            ]);

            return [
                'success' => true,
                'item_closed' => true,
                'po_completed' => $poStatus === 'completed',
                'po_status' => $poStatus,
                'message' => 'Item ditandai tidak diterima.',
            ];
        }

        return ['success' => true, 'item_closed' => false, 'message' => 'Masalah berhasil dilaporkan.'];
    }

    /**
     * Cancel a purchase order
     */
    public function cancelPurchaseOrder(string $poId): bool
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po) return false;

        $this->database->getReference("purchase_orders/{$poId}/status")->set('cancelled');

        // Revert restock requests back to pending
        $items = $po['items'] ?? [];
        foreach (array_keys($items) as $restockId) {
            $this->database->getReference("restock_requests/{$restockId}")->update([
                'status' => 'pending',
                'po_id' => null,
                'updated_at' => time(),
            ]);
        }

        return true;
    }

    /**
     * Get restock history for a specific product
     */
    public function getProductRestockHistory(string $productId, int $limit = 20): array
    {
        $snapshot = $this->database->getReference('restock_requests')
            ->orderByChild('product_id')
            ->equalTo($productId)
            ->getSnapshot();

        $items = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $item) {
                $item['id'] = $key;
                $items[] = $item;
            }
            usort($items, fn($a, $b) => ($b['reported_at'] ?? 0) - ($a['reported_at'] ?? 0));
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    }

    /**
     * Get stale POs (ordered for more than N days)
     */
    public function getStalePurchaseOrders(int $staleDays = 3): array
    {
        $snapshot = $this->database->getReference('purchase_orders')
            ->orderByChild('status')
            ->equalTo('ordered')
            ->getSnapshot();

        $staleOrders = [];
        $threshold = time() - ($staleDays * 86400);

        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $order) {
                if (($order['created_at'] ?? time()) < $threshold) {
                    $order['id'] = $key;
                    $staleOrders[] = $order;
                }
            }
        }
        return $staleOrders;
    }

    /**
     * Get restock summary stats
     */
    public function getRestockSummary(): array
    {
        $allRequests = $this->getRestockRequests();
        $allPOs = $this->getPurchaseOrders();

        $thisMonth = date('Y-m');
        $monthlyPOs = array_filter($allPOs, fn($po) => date('Y-m', $po['created_at'] ?? 0) === $thisMonth);

        // Most restocked products
        $productCounts = [];
        foreach ($allRequests as $req) {
            $pid = $req['product_id'] ?? '';
            if (!isset($productCounts[$pid])) {
                $productCounts[$pid] = ['name' => $req['product_name'] ?? '', 'count' => 0];
            }
            $productCounts[$pid]['count']++;
        }
        arsort($productCounts);

        // Average fulfillment time
        $fulfillTimes = [];
        foreach ($allRequests as $req) {
            if (($req['status'] ?? '') === 'received' && !empty($req['received_at']) && !empty($req['reported_at'])) {
                $fulfillTimes[] = (int) $req['received_at'] - (int) $req['reported_at'];
            }
        }
        $avgFulfillment = count($fulfillTimes) > 0 ? array_sum($fulfillTimes) / count($fulfillTimes) : 0;

        return [
            'total_requests' => count($allRequests),
            'pending_count' => count(array_filter($allRequests, fn($r) => ($r['status'] ?? '') === 'pending')),
            'ordered_count' => count(array_filter($allRequests, fn($r) => ($r['status'] ?? '') === 'ordered')),
            'received_count' => count(array_filter($allRequests, fn($r) => ($r['status'] ?? '') === 'received')),
            'monthly_po_count' => count($monthlyPOs),
            'avg_fulfillment_hours' => round($avgFulfillment / 3600, 1),
            'top_products' => array_slice($productCounts, 0, 5, true),
        ];
    }

    // ==========================================
    // AUDIT LOG SYSTEM
    // ==========================================

    /**
     * Log an admin action to audit_logs node
     */
    public function logAuditAction(string $action, string $entity, ?string $entityId, array $details = []): void
    {
        $adminId = session('admin_id', 'system');
        $adminName = session('admin_name', 'System');

        $entry = [
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'details' => $details ?: null,
            'ip' => request()->ip(),
            'timestamp' => time(),
            'date' => now()->format('Y-m-d'),
        ];

        $this->database->getReference('audit_logs')->push($entry);
    }

    /**
     * Get audit logs with optional filters
     */
    public function getAuditLogs(?string $date = null, ?string $entity = null, ?string $adminId = null, int $limit = 100): array
    {
        $snapshot = $this->database->getReference('audit_logs')
            ->orderByChild('timestamp')
            ->getSnapshot();

        $logs = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $id => $log) {
                if (!is_array($log)) continue;

                if ($date && ($log['date'] ?? '') !== $date) continue;
                if ($entity && ($log['entity'] ?? '') !== $entity) continue;
                if ($adminId && ($log['admin_id'] ?? '') !== $adminId) continue;

                $log['details'] = $log['details'] ?? null;
                $log['id'] = $id;
                $logs[] = $log;
            }
        }

        // Sort by timestamp desc
        usort($logs, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));

        return array_slice($logs, 0, $limit);
    }

    // ==========================================
    // WAITER PERFORMANCE SUMMARY
    // ==========================================

    /**
     * Get waiter task performance for a date range
     */
    public function getWaiterTaskPerformance(string $waiterId, string $fromDate, string $toDate): array
    {
        $tasks = $this->database->getReference('waiter_tasks')
            ->orderByChild('assigned_waiter_id')
            ->equalTo($waiterId)
            ->getSnapshot()
            ->getValue();

        $dailyStats = [];
        $totalDone = 0;
        $totalOverdue = 0;
        $totalTasks = 0;

        if ($tasks) {
            foreach ($tasks as $task) {
                if (!is_array($task)) continue;
                $taskDate = $task['scheduled_for_date'] ?? '';
                if ($taskDate < $fromDate || $taskDate > $toDate) continue;

                $totalTasks++;
                $status = $task['status'] ?? 'pending';

                if (!isset($dailyStats[$taskDate])) {
                    $dailyStats[$taskDate] = ['total' => 0, 'done' => 0, 'overdue' => 0];
                }
                $dailyStats[$taskDate]['total']++;

                if ($status === 'done') {
                    $dailyStats[$taskDate]['done']++;
                    $totalDone++;
                }
                if ($status === 'overdue') {
                    $dailyStats[$taskDate]['overdue']++;
                    $totalOverdue++;
                }
            }
        }

        ksort($dailyStats);

        return [
            'total_tasks' => $totalTasks,
            'total_done' => $totalDone,
            'total_overdue' => $totalOverdue,
            'completion_rate' => $totalTasks > 0 ? round(($totalDone / $totalTasks) * 100, 1) : 0,
            'daily_stats' => $dailyStats,
        ];
    }

    /**
     * Get waiter bonus history for multiple months
     */
    public function getWaiterBonusHistory(string $waiterId, int $monthsBack = 6): array
    {
        $history = [];
        $now = now();

        for ($i = 0; $i < $monthsBack; $i++) {
            $month = $now->copy()->subMonths($i)->format('Y-m');
            $summary = $this->database->getReference("waiter_bonus_summary/{$waiterId}/{$month}")->getSnapshot();
            if ($summary->exists()) {
                $data = $summary->getValue();
                $data['month'] = $month;
                $history[] = $data;
            } else {
                $history[] = ['month' => $month, 'net_points' => 0, 'total_bonus' => 0];
            }
        }

        return array_reverse($history);
    }

    // ==========================================
    // SHIFT HANDOVER NOTES
    // ==========================================

    /**
     * Save handover note at clock-out
     */
    public function saveHandoverNote(string $waiterId, string $waiterName, string $date, string $note): void
    {
        $entry = [
            'waiter_id' => $waiterId,
            'waiter_name' => $waiterName,
            'date' => $date,
            'note' => $note,
            'created_at' => time(),
        ];

        $this->database->getReference("handover_notes/{$date}/{$waiterId}")->set($entry);
    }

    /**
     * Get handover notes for a date (from previous shift)
     */
    public function getHandoverNotes(string $date): array
    {
        $snapshot = $this->database->getReference("handover_notes/{$date}")->getSnapshot();
        if (!$snapshot->exists()) return [];

        $notes = [];
        foreach ($snapshot->getValue() as $waiterId => $note) {
            if (!is_array($note)) continue;
            $note['id'] = $waiterId;
            $notes[] = $note;
        }

        usort($notes, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        return $notes;
    }

    /**
     * Get latest handover notes (yesterday or last working day)
     */
    public function getLatestHandoverNotes(): array
    {
        // Try yesterday first, then go back up to 3 days
        for ($i = 1; $i <= 3; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $notes = $this->getHandoverNotes($date);
            if (!empty($notes)) return $notes;
        }
        return [];
    }

    // ========================================
    // SUPPLIER MANAGEMENT
    // ========================================

    /**
     * Get all suppliers
     */
    public function getSuppliers(): array
    {
        $snapshot = $this->database->getReference('suppliers')->getSnapshot();
        if (!$snapshot->exists()) return [];

        $suppliers = [];
        foreach ($snapshot->getValue() as $id => $supplier) {
            if (!is_array($supplier)) continue;
            $supplier['id'] = $id;
            $suppliers[] = $supplier;
        }

        usort($suppliers, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
        return $suppliers;
    }

    /**
     * Get supplier by ID
     */
    public function getSupplierById(string $id): ?array
    {
        $snapshot = $this->database->getReference("suppliers/{$id}")->getSnapshot();
        if (!$snapshot->exists()) return null;

        $supplier = (array) $snapshot->getValue();
        $supplier['id'] = $id;
        return $supplier;
    }

    /**
     * Create supplier
     */
    public function createSupplier(array $data): string
    {
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'contact_person' => (string) ($data['contact_person'] ?? ''),
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $ref = $this->database->getReference('suppliers')->push($payload);
        return $ref->getKey();
    }

    /**
     * Update supplier
     */
    public function updateSupplier(string $id, array $data): bool
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

    /**
     * Delete supplier
     */
    public function deleteSupplier(string $id): bool
    {
        $this->database->getReference("suppliers/{$id}")->remove();
        return true;
    }
}
