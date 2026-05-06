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
     * Daftar Restock — pending items ready for PO
     */
    public function index()
    {
        $pendingItems = $this->firebase->getPendingRestockRequests();
        $summary = $this->firebase->getRestockSummary();

        return view('admin.restock.index', compact('pendingItems', 'summary'));
    }

    /**
     * Create Purchase Order from selected restock items
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

        $poId = $this->firebase->createPurchaseOrder(
            $request->input('restock_ids'),
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
}
