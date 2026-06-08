<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\FirebaseService;
use App\Services\ProductFirebaseService;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase, ProductFirebaseService $product)
    {
        $this->firebase = $firebase;
        $this->product = $product;
    }

    public function index()
    {
        if (config('features.mysql_product_categories')) {
            $categories = ProductCategory::orderBy('sort_order')->orderBy('name')->get()->map(fn ($c) => [
                'id' => $c->firebase_legacy_key ?: (string) $c->id,
                'name' => $c->name,
                'description' => $c->description ?? '',
                'sort_order' => $c->sort_order,
                'is_active' => (bool) $c->is_active,
            ])->all();
        } else {
            $categories = $this->product->getProductCategories();
        }

        return view('admin.products.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $data = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ];

            if (config('features.mysql_product_categories')) {
                $row = ProductCategory::create($data + [
                    'event_created_at' => time(),
                    'event_updated_at' => time(),
                ]);
                $category = array_merge(['id' => (string) $row->id], $data);

                if (config('features.legacy_write_product_categories')) {
                    $this->product->createProductCategory($data);
                }
            } else {
                $category = $this->product->createProductCategory($data);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kategori berhasil ditambahkan.',
                    'category' => $category,
                ]);
            }

            return redirect()->route('admin.product_categories.index')
                ->with('success', 'Kategori berhasil ditambahkan.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambahkan kategori.',
                ], 422);
            }

            return redirect()->route('admin.product_categories.index')
                ->with('error', 'Gagal menambahkan kategori.');
        }
    }

    public function update(Request $request, $id)
    {
        if (config('features.mysql_product_categories')) {
            $row = ProductCategory::where('firebase_legacy_key', $id)->orWhere('id', $id)->first();
            if (! $row) {
                abort(404);
            }
        } else {
            $category = $this->product->getProductCategoryById($id);
            if (! $category) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $data = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : false,
            ];

            if (config('features.mysql_product_categories')) {
                $row->update($data + ['event_updated_at' => time()]);

                if (config('features.legacy_write_product_categories')) {
                    $this->product->updateProductCategory($id, $data);
                }
            } else {
                $this->product->updateProductCategory($id, $data);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kategori berhasil diperbarui.',
                ]);
            }

            return redirect()->route('admin.product_categories.index')
                ->with('success', 'Kategori berhasil diperbarui.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui kategori.',
                ], 422);
            }

            return redirect()->route('admin.product_categories.index')
                ->with('error', 'Gagal memperbarui kategori.');
        }
    }

    public function destroy($id)
    {
        if (config('features.mysql_product_categories')) {
            $row = ProductCategory::where('firebase_legacy_key', $id)->orWhere('id', $id)->first();
            if (! $row) {
                abort(404);
            }
        } else {
            $category = $this->product->getProductCategoryById($id);
            if (! $category) {
                abort(404);
            }
        }

        try {
            if (config('features.mysql_product_categories')) {
                $row->delete();

                if (config('features.legacy_write_product_categories')) {
                    $this->product->deleteProductCategory($id);
                }
            } else {
                $this->product->deleteProductCategory($id);
            }

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kategori berhasil dihapus.',
                ]);
            }

            return redirect()->route('admin.product_categories.index')
                ->with('success', 'Kategori berhasil dihapus.');
        } catch (\Throwable $e) {
            report($e);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus kategori.',
                ], 422);
            }

            return redirect()->route('admin.product_categories.index')
                ->with('error', 'Gagal menghapus kategori.');
        }
    }
}
