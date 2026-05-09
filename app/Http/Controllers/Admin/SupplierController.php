<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        $suppliers = $this->firebase->getSuppliers();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'data' => array_values($suppliers)]);
        }

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
        ]);

        $supplierId = $this->firebase->createSupplier($request->all());

        $this->firebase->logAuditAction('create', 'supplier', $supplierId, [
            'name' => $request->name,
        ]);

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(string $id)
    {
        $supplier = $this->firebase->getSupplierById($id);

        if (!$supplier) {
            return redirect()->route('admin.suppliers.index')->with('error', 'Supplier tidak ditemukan.');
        }

        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
        ]);

        $this->firebase->updateSupplier($id, $request->all());

        $this->firebase->logAuditAction('update', 'supplier', $id, [
            'name' => $request->name,
        ]);

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil diupdate.');
    }

    public function destroy(Request $request, string $id)
    {
        $supplier = $this->firebase->getSupplierById($id);

        if (!$supplier) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Supplier tidak ditemukan.'], 404);
            }
            return redirect()->route('admin.suppliers.index')->with('error', 'Supplier tidak ditemukan.');
        }

        $this->firebase->deleteSupplier($id);

        $this->firebase->logAuditAction('delete', 'supplier', $id, [
            'name' => $supplier['name'] ?? '',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Supplier berhasil dihapus.']);
        }

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil dihapus.');
    }

    /**
     * AJAX endpoint for board builder modal
     */
    public function storeAjax(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $supplierId = $this->firebase->createSupplier([
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => '',
            'contact_person' => '',
        ]);

        $supplier = $this->firebase->getSupplierById($supplierId);

        return response()->json([
            'success' => true,
            'message' => 'Supplier berhasil ditambahkan.',
            'data' => $supplier,
        ]);
    }
}
