<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class RestockController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Daftar Restock — pending items grouped by product category
     */
    public function index()
    {
        $groupedItems = $this->firebase->getPendingRestockGroupedByProduct();
        $summary = $this->firebase->getRestockSummary();
        $categories = $this->firebase->getActiveProductCategories();

        return view('admin.restock.index', compact('groupedItems', 'summary', 'categories'));
    }

    /**
     * Create Purchase Order from selected restock items (legacy single PO)
     */
    public function createPO(Request $request)
    {
        $request->validate([
            'restock_ids' => 'required|array|min:1',
            'restock_ids.*' => 'required|string',
            'supplier' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:1000',
            'qty_overrides' => 'nullable|array',
            'qty_overrides.*' => 'nullable|integer|min:1',
        ]);

        $createdBy = session('admin_id', 'admin');
        $createdByName = session('admin_name', 'Admin');

        $restockIds = $request->input('restock_ids', []);
        $restockRows = $this->firebase->getRestockRequests();
        $productIds = [];
        foreach ($restockRows as $row) {
            if (!is_array($row)) continue;
            $rid = $row['id'] ?? null;
            if (!is_string($rid) || !in_array($rid, $restockIds, true)) continue;
            $pid = $row['product_id'] ?? null;
            if (is_string($pid) && $pid !== '') $productIds[] = $pid;
        }

        $forceDuplicate = (string) $request->input('force_duplicate', '0') === '1';
        $supplierKey = $request->input('supplier');
        $conflicts = $this->firebase->findOpenPOConflicts(array_values(array_unique($productIds)), $supplierKey, 86400);
        if (!$forceDuplicate && !empty($conflicts)) {
            return response()->json([
                'success' => false,
                'code' => 'po_duplicate',
                'message' => 'PO duplikat terdeteksi.',
                'conflicts' => $conflicts,
            ], 409);
        }

        $poId = $this->firebase->createPurchaseOrder(
            $restockIds,
            $createdBy,
            $createdByName,
            $request->input('supplier'),
            $request->input('notes'),
            $request->input('qty_overrides', [])
        );

        if (!$poId) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat PO. Pastikan item valid.'], 422);
        }

        $this->firebase->logAuditAction('create', 'purchase_order', $poId, ['items_count' => count($request->input('restock_ids')), 'supplier' => $request->input('supplier')]);

        return response()->json(['success' => true, 'po_id' => $poId, 'message' => 'Purchase Order berhasil dibuat.']);
    }

    /**
     * Create batch POs from board builder (one PO per supplier lane)
     */
    public function createBatchPO(Request $request)
    {
        $request->validate([
            'orders' => 'required|array|min:1',
            'orders.*.supplier_id' => 'nullable|string',
            'orders.*.supplier' => 'required|string|max:200',
            'orders.*.notes' => 'nullable|string|max:1000',
            'orders.*.items' => 'required|array|min:1',
            'orders.*.items.*.restock_ids' => 'required|array|min:1',
            'orders.*.items.*.qty_ordered' => 'required|integer|min:1',
        ]);

        $createdBy = session('admin_id', 'admin');
        $createdByName = session('admin_name', 'Admin');
        $results = [];
        $forceDuplicate = (string) $request->input('force_duplicate', '0') === '1';
        $restockRows = $this->firebase->getRestockRequests();
        $restockToProduct = [];
        foreach ($restockRows as $row) {
            if (!is_array($row)) continue;
            $rid = $row['id'] ?? null;
            $pid = $row['product_id'] ?? null;
            if (is_string($rid) && is_string($pid) && $pid !== '') {
                $restockToProduct[$rid] = $pid;
            }
        }

        foreach ($request->input('orders') as $order) {
            $supplierId = $order['supplier_id'] ?? null;
            $supplierName = $order['supplier'];
            $notes = $order['notes'] ?? null;

            // Resolve supplier details if ID provided
            $supplierPhone = null;
            if ($supplierId) {
                $supplierData = $this->firebase->getSupplierById($supplierId);
                if ($supplierData) {
                    $supplierName = $supplierData['name'];
                    $supplierPhone = $supplierData['phone'] ?? null;
                }
            }

            // Collect all restock_ids and qty_overrides for this supplier
            $restockIds = [];
            $qtyOverrides = [];
            $productIds = [];
            foreach ($order['items'] as $item) {
                foreach ($item['restock_ids'] as $restockId) {
                    $restockIds[] = $restockId;
                    $qtyOverrides[$restockId] = (int) $item['qty_ordered'];
                    if (isset($restockToProduct[$restockId])) {
                        $productIds[] = $restockToProduct[$restockId];
                    }
                }
            }

            $conflicts = $this->firebase->findOpenPOConflicts(array_values(array_unique($productIds)), $supplierName, 86400);
            if (!$forceDuplicate && !empty($conflicts)) {
                return response()->json([
                    'success' => false,
                    'code' => 'po_duplicate',
                    'message' => 'PO duplikat terdeteksi.',
                    'conflicts' => $conflicts,
                    'supplier' => $supplierName,
                ], 409);
            }

            $poId = $this->firebase->createPurchaseOrder(
                $restockIds,
                $createdBy,
                $createdByName,
                $supplierName,
                $notes,
                $qtyOverrides
            );

            if ($poId) {
                $this->firebase->logAuditAction('create', 'purchase_order', $poId, [
                    'items_count' => count($restockIds),
                    'supplier' => $supplierName,
                    'batch_mode' => true,
                ]);
                
                // Get PO details for response
                $po = $this->firebase->getPurchaseOrder($poId);
                
                $results[] = [
                    'po_id' => $poId,
                    'po_number' => $po['po_number'] ?? '',
                    'supplier' => $supplierName,
                    'supplier_phone' => $supplierPhone,
                    'items_count' => count($restockIds),
                    'items' => $po['items'] ?? [],
                ];
            }
        }

        if (empty($results)) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat PO. Pastikan item valid.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($results) . ' Purchase Order berhasil dibuat.',
            'orders' => $results,
        ]);
    }

    public function createManualPO(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|string|max:60',
            'supplier_name' => 'required|string|max:120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string|max:60',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:200',
            'rack_id' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:500',
            'force_duplicate' => 'nullable|in:0,1',
        ]);

        $createdBy = session('admin_id', 'admin');
        $createdByName = session('admin_name', 'Admin');

        $supplierId = $request->input('supplier_id');
        $supplierName = $request->input('supplier_name');
        if ($supplierId) {
            $supplierData = $this->firebase->getSupplierById($supplierId);
            if ($supplierData) {
                $supplierName = $supplierData['name'] ?? $supplierName;
            }
        }

        $forceDuplicate = (string) $request->input('force_duplicate', '0') === '1';
        $productIds = array_map(fn($i) => $i['product_id'], $request->input('items'));
        $conflicts = $this->firebase->findOpenPOConflicts(array_values(array_unique($productIds)), $supplierName, 86400);
        if (!$forceDuplicate && !empty($conflicts)) {
            return response()->json([
                'success' => false,
                'code' => 'po_duplicate',
                'message' => 'PO duplikat terdeteksi.',
                'conflicts' => $conflicts,
            ], 409);
        }

        $rackId = (string) $request->input('rack_id', '');
        $rackName = '';
        if ($rackId !== '') {
            $rack = $this->firebase->getRackById($rackId);
            $rackName = (string) ($rack['name'] ?? '');
        }

        $restockIds = [];
        $qtyOverrides = [];
        foreach ($request->input('items') as $item) {
            $product = $this->firebase->getProductById($item['product_id']);
            $qty = (int) $item['qty'];

            $restockId = $this->firebase->createOrUpdateRestockRequest([
                'product_id' => $item['product_id'],
                'product_name' => (string) ($product['name'] ?? $item['product_id']),
                'product_category_id' => $product['category_id'] ?? null,
                'rack_id' => $rackId,
                'rack_name' => $rackName,
                'reported_qty' => 0,
                'standard_qty' => $qty,
                'qty_needed' => $qty,
                'reported_by' => $createdBy,
                'reported_by_name' => $createdByName . ' (manual PO)',
                'date' => date('Y-m-d'),
                'source' => 'manual',
                'note' => $item['note'] ?? '',
            ]);

            $restockIds[] = $restockId;
            $qtyOverrides[$restockId] = $qty;
        }

        $poId = $this->firebase->createPurchaseOrder(
            $restockIds,
            $createdBy,
            $createdByName,
            $supplierName,
            $request->input('notes'),
            $qtyOverrides
        );

        if (!$poId) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat PO.'], 500);
        }

        $this->firebase->logAuditAction('create', 'purchase_order', $poId, [
            'items_count' => count($restockIds),
            'supplier' => $supplierName,
            'source' => 'manual',
        ]);

        return response()->json([
            'success' => true,
            'po_id' => $poId,
            'message' => 'PO manual berhasil dibuat.',
        ]);
    }

    /**
     * List all Purchase Orders
     */
    public function orders(Request $request)
    {
        $status = $request->query('status');
        $orders = $this->firebase->getPurchaseOrders($status);

        return view('admin.restock.orders', compact('orders', 'status'));
    }

    /**
     * Purchase Order detail
     */
    public function orderDetail(string $id)
    {
        $order = $this->firebase->getPurchaseOrder($id);
        if (!$order) {
            return redirect()->route('admin.restock.orders')->with('error', 'PO tidak ditemukan.');
        }

        // Get product restock history for each item
        $productHistories = [];
        foreach (($order['items'] ?? []) as $restockId => $item) {
            $pid = $item['product_id'] ?? '';
            if ($pid && !isset($productHistories[$pid])) {
                $productHistories[$pid] = $this->firebase->getProductRestockHistory($pid, 10);
            }
        }

        return view('admin.restock.order_detail', compact('order', 'productHistories'));
    }

    /**
     * Cancel a Purchase Order
     */
    public function cancelOrder(string $id)
    {
        $result = $this->firebase->cancelPurchaseOrder($id);

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Gagal membatalkan PO.'], 422);
        }

        $this->firebase->logAuditAction('cancel', 'purchase_order', $id, []);

        return response()->json(['success' => true, 'message' => 'PO berhasil dibatalkan. Item dikembalikan ke daftar restock.']);
    }

    /**
     * Accept PO item "as is" - supervisor confirms qty received is final
     */
    public function acceptAsIs(Request $request, string $poId, string $restockId)
    {
        $adminId = (string) session('admin_id', 'admin');
        $adminName = (string) session('admin_name', 'Supervisor');

        $result = $this->firebase->acceptPoItemAsIs($poId, $restockId, $adminId, $adminName);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        $this->firebase->logAuditAction('accept_as_is', 'po_item', "{$poId}/{$restockId}", [
            'po_id' => $poId,
            'restock_id' => $restockId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil diterima apa adanya.',
            'po_status' => $result['po_status'],
            'received_count' => $result['received_count'],
            'total_items' => $result['total_items'],
            'po_completed' => $result['po_completed'],
        ]);
    }
}
