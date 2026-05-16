<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class RackProductController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        [$products, $categories, $categoryMap, $page, $totalPages, $totalFiltered, $perPage, $search, $categoryFilter] = $this->buildFilteredProducts($request);

        return view('admin.products.index', compact(
            'products', 'categories', 'categoryMap',
            'page', 'totalPages', 'totalFiltered', 'perPage', 'search', 'categoryFilter'
        ));
    }

    /**
     * AJAX live search endpoint. Return JSON struct yang sama dengan view payload
     * untuk kebutuhan frontend re-render rows + pagination tanpa full page reload.
     */
    public function searchJson(Request $request)
    {
        [$products, $categories, $categoryMap, $page, $totalPages, $totalFiltered, $perPage, $search, $categoryFilter] = $this->buildFilteredProducts($request);

        // Trim payload yang dikirim - frontend hanya butuh field yang ditampilkan.
        $rows = [];
        foreach ($products as $p) {
            $catId = (string) ($p['category_id'] ?? '');
            $rows[] = [
                'id' => (string) ($p['id'] ?? ''),
                'name' => (string) ($p['name'] ?? '-'),
                'category_id' => $catId,
                'category_name' => $catId !== '' ? ($categoryMap[$catId] ?? '-') : '',
                'standard_qty' => (int) ($p['standard_qty'] ?? 0),
                'unit' => (string) ($p['unit'] ?? 'pcs'),
                'is_active' => ($p['is_active'] ?? true) === true,
            ];
        }

        return response()->json([
            'success' => true,
            'products' => $rows,
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total_filtered' => $totalFiltered,
                'per_page' => $perPage,
            ],
            'filters' => [
                'search' => $search,
                'category' => $categoryFilter,
            ],
        ]);
    }

    /**
     * Shared filter logic - dipakai oleh index() (full render) dan
     * searchJson() (AJAX). Return positional array agar destructuring di
     * caller bersih.
     *
     * @return array{0:array,1:array,2:array,3:int,4:int,5:int,6:int,7:string,8:string}
     */
    private function buildFilteredProducts(Request $request): array
    {
        $allProducts = $this->firebase->getProducts();
        $categories = $this->firebase->getActiveProductCategories();

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[(string) $cat['id']] = $cat['name'];
        }

        $search = trim($request->input('search', ''));
        $categoryFilter = $request->input('category', '');
        $perPage = (int) $request->input('per_page', 50);
        $page = max(1, (int) $request->input('page', 1));

        $filtered = $allProducts;

        if ($search !== '') {
            $filtered = array_filter($filtered, function ($p) use ($search) {
                return stripos($p['name'] ?? '', $search) !== false;
            });
        }

        if ($categoryFilter !== '') {
            if ($categoryFilter === '__none__') {
                $filtered = array_filter($filtered, function ($p) {
                    return empty($p['category_id']);
                });
            } else {
                $filtered = array_filter($filtered, function ($p) use ($categoryFilter) {
                    return ($p['category_id'] ?? '') === $categoryFilter;
                });
            }
        }

        $filtered = array_values($filtered);
        $totalFiltered = count($filtered);
        $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
        $page = min($page, $totalPages);
        $products = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return [$products, $categories, $categoryMap, $page, $totalPages, $totalFiltered, $perPage, $search, $categoryFilter];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'category_id' => 'nullable|string|max:100',
            'standard_qty' => 'required|integer|min:0',
            'unit' => 'required|string|max:30',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $product = $this->firebase->createProduct([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'standard_qty' => $validated['standard_qty'],
                'unit' => $validated['unit'],
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk berhasil ditambahkan.',
                    'product' => $product,
                ]);
            }

            return redirect()->route('admin.products.index')
                ->with('success', 'Produk berhasil ditambahkan.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambahkan produk.',
                ], 422);
            }

            return redirect()->route('admin.products.index')
                ->with('error', 'Gagal menambahkan produk.');
        }
    }

    public function update(Request $request, $id)
    {
        $product = $this->firebase->getProductById($id);
        if (! $product) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'category_id' => 'nullable|string|max:100',
            'standard_qty' => 'required|integer|min:0',
            'unit' => 'required|string|max:30',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $this->firebase->updateProduct($id, [
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'standard_qty' => $validated['standard_qty'],
                'unit' => $validated['unit'],
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : false,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk berhasil diperbarui.',
                ]);
            }

            return redirect()->route('admin.products.index')
                ->with('success', 'Produk berhasil diperbarui.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui produk.',
                ], 422);
            }

            return redirect()->route('admin.products.index')
                ->with('error', 'Gagal memperbarui produk.');
        }
    }

    public function destroy($id)
    {
        $product = $this->firebase->getProductById($id);
        if (! $product) {
            abort(404);
        }

        try {
            $this->firebase->deleteProduct($id);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk berhasil dihapus.',
                ]);
            }

            return redirect()->route('admin.products.index')
                ->with('success', 'Produk berhasil dihapus.');
        } catch (\Throwable $e) {
            report($e);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus produk.',
                ], 422);
            }

            return redirect()->route('admin.products.index')
                ->with('error', 'Gagal menghapus produk.');
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string|max:100',
        ]);

        $ids = $request->input('ids');
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $product = $this->firebase->getProductById($id);
                if ($product) {
                    $this->firebase->deleteProduct($id);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$deleted} produk berhasil dihapus." . ($failed > 0 ? " {$failed} gagal." : ''),
                'deleted' => $deleted,
                'failed' => $failed,
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', "{$deleted} produk berhasil dihapus." . ($failed > 0 ? " {$failed} gagal." : ''));
    }

    public function resetProducts(Request $request)
    {
        try {
            $resetCategories = $request->boolean('reset_categories', false);
            $result = $this->firebase->resetAllProducts($resetCategories);

            $msg = "{$result['deleted']} produk berhasil dihapus. Semua assignment rak juga direset.";
            if ($result['categories_deleted'] > 0) {
                $msg .= " {$result['categories_deleted']} kategori juga dihapus.";
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'deleted' => $result['deleted'],
                    'categories_deleted' => $result['categories_deleted'],
                ]);
            }

            return redirect()->route('admin.products.index')->with('success', $msg);
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal reset: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->route('admin.products.index')
                ->with('error', 'Gagal reset: ' . $e->getMessage());
        }
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
            'default_standard_qty' => 'nullable|integer|min:0',
        ]);

        try {
            $file = $request->file('excel_file');
            $defaultQty = (int) ($request->input('default_standard_qty', 0));
            $result = $this->firebase->importProductsFromExcel($file->getRealPath(), $defaultQty);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$result['imported']} produk berhasil diimport. {$result['skipped']} dilewati (duplikat). {$result['categories_created']} kategori baru dibuat.",
                    'total' => $result['total'],
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'categories_created' => $result['categories_created'],
                    'errors' => $result['errors'],
                ]);
            }

            return redirect()->route('admin.products.index')
                ->with('success', "{$result['imported']} produk berhasil diimport. {$result['skipped']} dilewati. {$result['categories_created']} kategori baru.");
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal import: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->route('admin.products.index')
                ->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    public function bulkAssign()
    {
        $products = $this->firebase->getActiveProducts();
        $racks = $this->firebase->getActiveRacks();
        $categories = $this->firebase->getActiveProductCategories();

        // Build category map for display
        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[(string) $cat['id']] = $cat['name'];
        }

        // Build current assignment map: rackId => [productId => standard_qty, ...]
        $currentAssignments = [];
        foreach ($racks as $rack) {
            $rackId = (string) ($rack['id'] ?? '');
            if ($rackId === '') {
                continue;
            }

            $rackProducts = $this->firebase->getRackProducts($rackId);
            $currentAssignments[$rackId] = [];
            foreach ($rackProducts as $rp) {
                $currentAssignments[$rackId][(string) $rp['id']] = (int) ($rp['standard_qty'] ?? 0);
            }
        }

        return view('admin.products.bulk_assign', compact('products', 'racks', 'categories', 'categoryMap', 'currentAssignments'));
    }

    public function saveBulkAssign(Request $request)
    {
        $data = $request->input('assignments', []);
        if (! is_array($data)) {
            $data = [];
        }

        // Parse: assignments[rackId][productId] = qty
        $assignments = [];
        foreach ($data as $rackId => $productMap) {
            $rackId = trim((string) $rackId);
            if ($rackId === '' || ! is_array($productMap)) {
                continue;
            }

            foreach ($productMap as $productId => $qty) {
                $productId = trim((string) $productId);
                if ($productId === '') {
                    continue;
                }

                $qty = max(0, (int) $qty);
                if ($qty > 0) {
                    $assignments[$rackId][$productId] = ['standard_qty' => $qty];
                }
            }
        }

        try {
            $this->firebase->bulkAssignProductsToRacks($assignments);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk berhasil di-assign ke rak secara massal.',
                ]);
            }

            return redirect()->route('admin.products.bulk_assign')
                ->with('success', 'Produk berhasil di-assign ke rak secara massal.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan assign massal.',
                ], 422);
            }

            return redirect()->route('admin.products.bulk_assign')
                ->with('error', 'Gagal menyimpan assign massal.');
        }
    }

    public function rackProducts($rackId)
    {
        $rack = $this->firebase->getRackById($rackId);
        if (! $rack) {
            abort(404);
        }

        $allProducts = $this->firebase->getActiveProducts();
        $rackProducts = $this->firebase->getRackProducts($rackId);
        $liveStockMap = $this->firebase->getRackProductLiveStock($rackId, $rackProducts);
        $assignedProductIds = array_values(array_map(function ($product) {
            return (string) ($product['id'] ?? '');
        }, $rackProducts));

        return view('admin.products.rack_products', compact('rack', 'allProducts', 'rackProducts', 'assignedProductIds', 'liveStockMap'));
    }

    public function saveRackProducts(Request $request, $rackId)
    {
        $rack = $this->firebase->getRackById($rackId);
        if (! $rack) {
            abort(404);
        }

        $productIds = $request->input('product_ids', []);
        if (! is_array($productIds)) {
            $productIds = explode(',', (string) $productIds);
        }

        $productIds = array_values(array_unique(array_filter(array_map(function ($id) {
            return trim((string) $id);
        }, $productIds), function ($id) {
            return $id !== '';
        })));

        $quantities = $request->input('quantities', []);
        if (! is_array($quantities)) {
            $quantities = [];
        }

        $minQuantities = $request->input('min_quantities', []);
        if (! is_array($minQuantities)) {
            $minQuantities = [];
        }

        $assignments = [];
        foreach ($productIds as $productId) {
            $assignments[$productId] = [
                'standard_qty' => max(0, (int) ($quantities[$productId] ?? 0)),
                'min_qty' => max(0, (int) ($minQuantities[$productId] ?? 0)),
            ];
        }

        try {
            $this->firebase->assignProductsToRack($rackId, $assignments);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk rak berhasil disimpan.',
                ]);
            }

            return redirect()->route('admin.racks.products', $rackId)
                ->with('success', 'Produk rak berhasil disimpan.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan produk rak.',
                ], 422);
            }

            return redirect()->route('admin.racks.products', $rackId)
                ->with('error', 'Gagal menyimpan produk rak.');
        }
    }

    public function auditTrail(string $id)
    {
        $product = $this->firebase->getProductById($id);
        if (! $product) {
            abort(404);
        }

        $events = $this->firebase->getProductAuditTrail($id, 200);
        $stats = $this->firebase->getProductStats($id);
        $racks = $this->firebase->getRacks();

        return view('admin.products.audit_trail', compact('product', 'events', 'stats', 'racks'));
    }
}
