<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $categories = $this->firebase->getProductCategories();

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
            $category = $this->firebase->createProductCategory([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);

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
        $category = $this->firebase->getProductCategoryById($id);
        if (! $category) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $this->firebase->updateProductCategory($id, [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : false,
            ]);

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
        $category = $this->firebase->getProductCategoryById($id);
        if (! $category) {
            abort(404);
        }

        try {
            $this->firebase->deleteProductCategory($id);

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
