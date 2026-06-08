<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

/**
 * Purchase Order & Restock operations (Firebase RTDB).
 * Extracted from FirebaseService to reduce god-class size.
 */
class PurchaseOrderFirebaseService
{
    protected Database $database;
    protected array $requestCache = [];

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Proxy to get product categories map (shared helper).
     */
    protected function getProductCategoriesMap(): array
    {
        $snapshot = $this->database->getReference("product_categories")->getSnapshot();
        $map = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $cat) {
                if (!is_array($cat)) continue;
                $map[$key] = (string)($cat["name"] ?? $key);
            }
        }
        return $map;
    }

    /**
     * Proxy: get product by ID.
     */
    protected function getProductById(string $productId): ?array
    {
        $snapshot = $this->database->getReference("products/{$productId}")->getSnapshot();
        if (!$snapshot->exists()) return null;
        $data = $snapshot->getValue();
        $data["id"] = $productId;
        return $data;
    }

    /**
     * Proxy: get supplier by ID.
     */
    public function getSupplierById(string $supplierId): ?array
    {
        $snapshot = $this->database->getReference("suppliers/{$supplierId}")->getSnapshot();
        if (!$snapshot->exists()) return null;
        $data = $snapshot->getValue();
        $data["id"] = $supplierId;
        return $data;
    }

    /**
     * Proxy: get product stock summary.
     */
    public function getProductStockSummary(string $productId): array
    {
        $snapshot = $this->database->getReference("stock_summary/{$productId}")->getSnapshot();
        return $snapshot->exists() ? (array)$snapshot->getValue() : [];
    }


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
                        'source' => $data['source'] ?? ($entry['source'] ?? null),
                        'note' => $data['note'] ?? ($entry['note'] ?? null),
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
            'source' => $data['source'] ?? null,
            'note' => $data['note'] ?? null,
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
        $productCategories = $this->getProductCategoriesMap();

        // Group by product_id, aggregate qty_needed across racks
        $grouped = [];
        foreach ($pending as $item) {
            $productId = $item['product_id'] ?? '';
            if (!$productId) continue;

            if (!isset($grouped[$productId])) {
                // Try to get fresh category from product master
                $catId = $item['product_category_id'] ?? null;
                $catName = $item['product_category_name'] ?? null;

                // If category missing, lookup from product master
                if (!$catId || !$catName || $catName === 'Tanpa Kategori') {
                    $productMaster = $this->getProductById($productId);
                    if ($productMaster) {
                        $masterCatId = $productMaster['category_id'] ?? null;
                        if ($masterCatId && isset($productCategories[$masterCatId])) {
                            $catId = $masterCatId;
                            $catName = $productCategories[$masterCatId]['name'] ?? 'Tanpa Kategori';
                        }
                    }
                }

                $grouped[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $item['product_name'] ?? '',
                    'product_category_id' => $catId,
                    'product_category_name' => $catName ?: 'Tanpa Kategori',
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
     * Find open POs (status=ordered|partial) containing any of given product IDs
     * for given supplier, created within $windowSeconds.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findOpenPOConflicts(array $productIds, ?string $supplierId, int $windowSeconds = 86400): array
    {
        if (empty($productIds)) return [];

        $productIds = array_values(array_unique(array_filter($productIds, fn($id) => is_string($id) && $id !== '')));
        if (empty($productIds)) return [];

        $cutoffMs = (int) (microtime(true) * 1000) - ($windowSeconds * 1000);

        // Bandwidth: pakai indexed query orderByChild('status') untuk hemat. RTDB
        // tidak support OR di server-side, jadi 2 calls (ordered + partial).
        // .indexOn 'status' di rules akan ditolak silently kalau belum ada — log warning.
        $orderedSnap = $this->database->getReference('purchase_orders')
            ->orderByChild('status')->equalTo('ordered')->getSnapshot();
        $partialSnap = $this->database->getReference('purchase_orders')
            ->orderByChild('status')->equalTo('partial')->getSnapshot();

        $allPOs = [];
        if ($orderedSnap->exists()) $allPOs += (array) $orderedSnap->getValue();
        if ($partialSnap->exists()) $allPOs += (array) $partialSnap->getValue();

        $conflicts = [];
        $supKey = ($supplierId === null || $supplierId === '') ? null : $supplierId;

        foreach ($allPOs as $poId => $po) {
            if (!is_array($po)) continue;

            $createdAtRaw = $po['created_at'] ?? 0;
            $createdAt = (int) $createdAtRaw;
            if ($createdAt > 0 && $createdAt < 1000000000000) {
                $createdAt *= 1000;
            }
            if ($createdAt < $cutoffMs) continue;

            $poSupplier = $po['supplier_id'] ?? null;
            if ($poSupplier === null || $poSupplier === '') {
                $poSupplier = $po['supplier'] ?? null;
            }
            $poSupKey = ($poSupplier === null || $poSupplier === '') ? null : $poSupplier;
            if ($supKey !== $poSupKey) continue;

            $matched = [];
            foreach (($po['items'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $pid = $item['product_id'] ?? null;
                if (is_string($pid) && in_array($pid, $productIds, true)) {
                    $matched[] = $pid;
                }
            }
            if (empty($matched)) continue;

            $conflicts[] = [
                'po_id' => (string) $poId,
                'po_number' => (string) ($po['po_number'] ?? $poId),
                'supplier_id' => $po['supplier_id'] ?? null,
                'supplier_name' => (string) ($po['supplier_name'] ?? $po['supplier'] ?? '-'),
                'created_at' => $createdAt,
                'matched_product_ids' => array_values(array_unique($matched)),
            ];
        }

        return $conflicts;
    }


    public function savePurchaseOrderDraft(array $data, ?string $draftId = null): string
    {
        $payload = [
            'supplier_id' => trim((string) ($data['supplier_id'] ?? '')),
            'supplier_name' => trim((string) ($data['supplier_name'] ?? '')),
            'rack_id' => trim((string) ($data['rack_id'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'items' => $this->normalizeDraftItems($data['items'] ?? []),
            'created_by' => trim((string) ($data['created_by'] ?? '')),
            'created_by_name' => trim((string) ($data['created_by_name'] ?? '')),
            'updated_at' => time(),
        ];

        if ($draftId !== null && trim($draftId) !== '') {
            $existing = $this->database->getReference('purchase_order_drafts/'.$draftId)->getSnapshot();
            if ($existing->exists()) {
                $current = (array) $existing->getValue();
                $payload['created_at'] = (int) ($current['created_at'] ?? time());
                $this->database->getReference('purchase_order_drafts/'.$draftId)->update($payload);

                return $draftId;
            }
        }

        $payload['created_at'] = time();
        $ref = $this->database->getReference('purchase_order_drafts')->push($payload);

        return $ref->getKey();
    }


    public function getPurchaseOrderDraft(string $draftId): ?array
    {
        $draftId = trim($draftId);
        if ($draftId === '') {
            return null;
        }
        $snapshot = $this->database->getReference('purchase_order_drafts/'.$draftId)->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }
        $row = (array) $snapshot->getValue();
        $row['id'] = $draftId;

        return $row;
    }


    public function getPurchaseOrderDrafts(?string $createdBy = null): array
    {
        $snapshot = $this->database->getReference('purchase_order_drafts')->getSnapshot();
        if (! $snapshot->exists()) {
            return [];
        }

        $drafts = [];
        foreach ((array) $snapshot->getValue() as $id => $row) {
            $row = (array) $row;
            if ($createdBy !== null && (string) ($row['created_by'] ?? '') !== $createdBy) {
                continue;
            }
            $row['id'] = $id;
            $drafts[] = $row;
        }

        usort($drafts, fn ($a, $b) => ((int) ($b['updated_at'] ?? 0)) <=> ((int) ($a['updated_at'] ?? 0)));

        return $drafts;
    }


    public function deletePurchaseOrderDraft(string $draftId): bool
    {
        $draftId = trim($draftId);
        if ($draftId === '') {
            return false;
        }
        try {
            $this->database->getReference('purchase_order_drafts/'.$draftId)->remove();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
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
    public function receivePoItem(string $poId, string $restockId, int $receivedQty, string $receivedBy, string $receivedByName, ?string $idempotencyKey = null, ?string $overrideRackId = null): array
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey !== '') {
            $idempotencySnapshot = $this->database->getReference('po_receive_idempotency/'.$idempotencyKey)->getSnapshot();
            if ($idempotencySnapshot->exists()) {
                $stored = $idempotencySnapshot->getValue();
                if (is_array($stored) && isset($stored['response']) && is_array($stored['response'])) {
                    return $stored['response'];
                }
            }
        }

        // Validasi qty: harus bilangan positif
        if ($receivedQty <= 0) {
            return ['success' => false, 'message' => 'Qty terima harus lebih dari 0.'];
        }

        $po = $this->getPurchaseOrder($poId);
        if (!$po) return ['success' => false, 'message' => 'PO tidak ditemukan'];

        // Guard status PO: tidak boleh receive pada PO cancelled/completed
        $poCurrentStatus = (string) ($po['status'] ?? '');
        if ($poCurrentStatus === 'cancelled') {
            return ['success' => false, 'message' => 'PO sudah dibatalkan, tidak bisa terima barang.'];
        }
        if ($poCurrentStatus === 'completed') {
            return ['success' => false, 'message' => 'PO sudah selesai, tidak bisa terima barang lagi.'];
        }

        $items = $po['items'] ?? [];
        if (!isset($items[$restockId])) return ['success' => false, 'message' => 'Item tidak ditemukan di PO'];

        $item = $items[$restockId];
        $qtyOrdered = (int) ($item['qty_ordered'] ?? 0);
        $currentReceived = (int) ($item['received_qty'] ?? 0);

        // Guard item-level: cegah over-receive dan terima pada item yang sudah closed
        if (!empty($item['closed_reason']) || ($item['accepted_as_is'] ?? false) === true) {
            return ['success' => false, 'message' => 'Item sudah ditutup, tidak bisa terima lagi.'];
        }
        $remaining = max(0, $qtyOrdered - $currentReceived);
        if ($remaining <= 0) {
            return ['success' => false, 'message' => 'Item sudah lengkap diterima.'];
        }
        if ($receivedQty > $remaining) {
            return ['success' => false, 'message' => "Qty terima ({$receivedQty}) melebihi sisa ordered ({$remaining})."];
        }

        $newReceived = $currentReceived + $receivedQty;

        // Resolve rack: override dari waiter > rack di item PO > kosong
        $resolvedRackId = trim((string) ($overrideRackId ?? ''));
        if ($resolvedRackId === '') {
            $resolvedRackId = trim((string) ($item['rack_id'] ?? ''));
        } else {
            // Validasi: rack ada dan aktif
            $rack = $this->getRackById($resolvedRackId);
            if (! $rack || ($rack['is_active'] ?? true) === false) {
                return ['success' => false, 'message' => 'Rak tujuan tidak valid atau tidak aktif.'];
            }
        }

        $resolvedRackName = '';
        if ($resolvedRackId !== '') {
            $rack = $this->getRackById($resolvedRackId);
            $resolvedRackName = (string) ($rack['name'] ?? '');
        }

        // Update PO item
        $itemUpdates = [
            "items/{$restockId}/received_qty" => $newReceived,
            "items/{$restockId}/received" => $newReceived >= $qtyOrdered,
            "items/{$restockId}/last_received_at" => time(),
            "items/{$restockId}/last_received_by" => $receivedBy,
            "items/{$restockId}/last_received_by_name" => $receivedByName,
        ];

        // Catat actual rack tujuan kalau berbeda dari rack PO awal
        if ($resolvedRackId !== '' && $resolvedRackId !== trim((string) ($item['rack_id'] ?? ''))) {
            $itemUpdates["items/{$restockId}/actual_rack_id"] = $resolvedRackId;
            $itemUpdates["items/{$restockId}/actual_rack_name"] = $resolvedRackName;
        }

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

        $rackId = $resolvedRackId;
        $productId = trim((string) ($item['product_id'] ?? ''));
        if ($rackId !== '' && $productId !== '') {
            $previousQty = null;
            $rackProductSnapshot = $this->database->getReference("waiter_racks/{$rackId}/products/{$productId}")->getSnapshot();
            if ($rackProductSnapshot->exists()) {
                $rackProduct = $rackProductSnapshot->getValue();
                if (is_array($rackProduct) && array_key_exists('current_qty', $rackProduct) && $rackProduct['current_qty'] !== null) {
                    $previousQty = max(0, (int) $rackProduct['current_qty']);
                }
            }

            $movementResult = $this->recordRackStockMovement([
                'rack_id' => $rackId,
                'product_id' => $productId,
                'movement_type' => 'po_receive',
                'source' => 'purchase_order',
                'po_id' => $poId,
                'restock_id' => $restockId,
                'waiter_id' => $receivedBy,
                'waiter_name' => $receivedByName,
                'product_name' => (string) ($item['product_name'] ?? ''),
                'product_unit' => (string) ($item['product_unit'] ?? 'pcs'),
                'actual_qty' => $previousQty !== null ? $previousQty + $receivedQty : $receivedQty,
                'current_qty' => $previousQty !== null ? $previousQty + $receivedQty : $receivedQty,
                'delta_qty' => $receivedQty,
                'note' => 'Penerimaan PO',
            ]);

            if (! ($movementResult['success'] ?? false)) {
                report(new \RuntimeException((string) ($movementResult['message'] ?? 'Gagal mencatat movement penerimaan PO.')));
            }
        }

        $response = [
            'success' => true,
            'po_status' => $poStatus,
            'received_count' => $receivedCount,
            'total_items' => $totalItems,
            'item_completed' => $newReceived >= $qtyOrdered,
            'po_completed' => $poStatus === 'completed',
            'new_received_qty' => $newReceived,
            'qty_ordered' => $qtyOrdered,
        ];

        if ($idempotencyKey !== '') {
            $this->database->getReference('po_receive_idempotency/'.$idempotencyKey)->set([
                'po_id' => $poId,
                'restock_id' => $restockId,
                'response' => $response,
                'created_at' => time(),
            ]);
        }

        return $response;
    }


    /**
     * Accept PO item "as is" - mark as completed even if qty doesn't match order
     * Supervisor action when supplier can't fulfill full order
     */
    public function acceptPoItemAsIs(string $poId, string $restockId, string $acceptedBy, string $acceptedByName): array
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po) return ['success' => false, 'message' => 'PO tidak ditemukan'];

        $poStatusNow = (string) ($po['status'] ?? '');
        if ($poStatusNow === 'cancelled') {
            return ['success' => false, 'message' => 'PO sudah dibatalkan.'];
        }

        $items = $po['items'] ?? [];
        if (!isset($items[$restockId])) return ['success' => false, 'message' => 'Item tidak ditemukan di PO'];

        $item = $items[$restockId];
        if (($item['accepted_as_is'] ?? false) === true || !empty($item['closed_reason'])) {
            return ['success' => false, 'message' => 'Item sudah ditutup.'];
        }
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
    public function reportPoItemIssue(string $poId, string $restockId, string $issueNote, string $reportedBy, string $reportedByName, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey !== '') {
            $idempotencySnapshot = $this->database->getReference('po_issue_idempotency/'.$idempotencyKey)->getSnapshot();
            if ($idempotencySnapshot->exists()) {
                $stored = $idempotencySnapshot->getValue();
                if (is_array($stored) && isset($stored['response']) && is_array($stored['response'])) {
                    return $stored['response'];
                }
            }
        }

        $po = $this->getPurchaseOrder($poId);
        if (!$po) return ['success' => false, 'message' => 'PO tidak ditemukan'];

        if ((string) ($po['status'] ?? '') === 'cancelled') {
            return ['success' => false, 'message' => 'PO sudah dibatalkan.'];
        }

        $items = $po['items'] ?? [];
        if (!isset($items[$restockId])) return ['success' => false, 'message' => 'Item tidak ditemukan di PO'];

        $itemNow = $items[$restockId];
        if (!empty($itemNow['closed_reason']) || ($itemNow['accepted_as_is'] ?? false) === true) {
            return ['success' => false, 'message' => 'Item sudah ditutup.'];
        }

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

            $response = [
                'success' => true,
                'item_closed' => true,
                'po_completed' => $poStatus === 'completed',
                'po_status' => $poStatus,
                'message' => 'Item ditandai tidak diterima.',
            ];

            if ($idempotencyKey !== '') {
                $this->database->getReference('po_issue_idempotency/'.$idempotencyKey)->set([
                    'po_id' => $poId,
                    'restock_id' => $restockId,
                    'response' => $response,
                    'created_at' => time(),
                ]);
            }

            return $response;
        }

        $response = ['success' => true, 'item_closed' => false, 'message' => 'Masalah berhasil dilaporkan.'];

        if ($idempotencyKey !== '') {
            $this->database->getReference('po_issue_idempotency/'.$idempotencyKey)->set([
                'po_id' => $poId,
                'restock_id' => $restockId,
                'response' => $response,
                'created_at' => time(),
            ]);
        }

        return $response;
    }


    /**
     * Cancel a purchase order
     */
    public function cancelPurchaseOrder(string $poId): bool
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po) return false;

        // Guard: tidak boleh cancel PO yang sudah cancelled atau completed
        $currentStatus = (string) ($po['status'] ?? '');
        if ($currentStatus === 'cancelled' || $currentStatus === 'completed') {
            return false;
        }

        $this->database->getReference("purchase_orders/{$poId}/status")->set('cancelled');
        $this->database->getReference("purchase_orders/{$poId}/cancelled_at")->set(time());

        // Revert HANYA restock requests yang belum punya received_qty.
        // Yang sudah ada penerimaan parsial dibiarkan agar stoknya tidak hilang
        // dan tidak ke-reorder ganda.
        $items = $po['items'] ?? [];
        foreach ($items as $restockId => $item) {
            $receivedQty = (int) ($item['received_qty'] ?? 0);

            if ($receivedQty > 0) {
                // Tutup di restock_requests sebagai partial agar tidak masuk lagi ke daftar pending.
                $this->database->getReference("restock_requests/{$restockId}")->update([
                    'status' => 'partial_cancelled',
                    'updated_at' => time(),
                    'po_cancelled_at' => time(),
                ]);
            } else {
                // Belum ada penerimaan -> aman direvert ke pending agar bisa dimasukkan PO baru.
                $this->database->getReference("restock_requests/{$restockId}")->update([
                    'status' => 'pending',
                    'po_id' => null,
                    'updated_at' => time(),
                ]);
            }
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
     * Bersihkan idempotency cache lama dari Firebase.
     *
     * Bandwidth: cache stock_movement_idempotency & waiter_task_idempotency
     * tumbuh terus tanpa TTL. Setiap waiter portal load yang subscribe ke
     * waiter_tasks juga otomatis fetch sub-tree termasuk cache idempotency
     * lama. Dijalankan harian via scheduler. Hapus entry dengan
     * created_at < $cutoffTs.
     *
     * @return array{stock_movement:int, waiter_task:int}
     */
    public function cleanupIdempotencyCaches(int $cutoffTs): array
    {
        $stats = ['stock_movement' => 0, 'waiter_task' => 0];

        foreach (['stock_movement_idempotency' => 'stock_movement', 'waiter_task_idempotency' => 'waiter_task'] as $path => $statKey) {
            try {
                $snap = $this->database->getReference($path)->getSnapshot();
                if (! $snap->exists()) {
                    continue;
                }

                $entries = (array) $snap->getValue();
                $toDelete = [];
                foreach ($entries as $key => $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }
                    $createdAt = (int) ($entry['created_at'] ?? 0);
                    // Normalize ms timestamps ke seconds.
                    if ($createdAt > 1000000000000) {
                        $createdAt = (int) ($createdAt / 1000);
                    }
                    if ($createdAt > 0 && $createdAt < $cutoffTs) {
                        $toDelete[(string) $key] = null;
                    }
                }

                if (! empty($toDelete)) {
                    $this->database->getReference($path)->update($toDelete);
                    $stats[$statKey] = count($toDelete);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $stats;
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
}
