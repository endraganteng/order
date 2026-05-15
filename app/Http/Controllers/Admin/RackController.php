<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateRackTypeRequest;
use App\Http\Requests\StoreRackAjaxRequest;
use App\Http\Requests\StoreRackRequest;
use App\Http\Requests\UpdateRackRequest;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class RackController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $racks = $this->firebase->getRacks();

        return view('admin.racks.index', compact('racks'));
    }

    public function create()
    {
        return view('admin.racks.create');
    }

    public function store(StoreRackRequest $request)
    {
        $this->firebase->createRack([
            'name' => $request->name,
            'location' => $request->location,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
            'rack_type' => $request->input('rack_type', 'storage'),
            'check_order' => (int) $request->input('check_order', 0),
        ]);

        $this->firebase->logAuditAction('create', 'rack', null, ['name' => $request->name]);

        return redirect()->route('admin.racks.index')
            ->with('success', 'Rak berhasil ditambahkan dan QR code otomatis digenerate.');
    }

    public function storeAjax(StoreRackAjaxRequest $request)
    {
        $rack = $this->firebase->createRack([
            'name' => $request->name,
            'location' => $request->location,
            'description' => $request->description ?? '',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'rack' => [
                'id' => $rack['id'] ?? '',
                'name' => $rack['name'] ?? '',
                'location' => $rack['location'] ?? '',
                'barcode_value' => $rack['barcode_value'] ?? '',
            ],
        ]);
    }

    public function printLabels(Request $request)
    {
        $selectedRacks = $this->resolveSelectedRacks($request);

        if (count($selectedRacks) === 0) {
            return redirect()->route('admin.racks.index')
                ->with('error', 'Pilih minimal satu rak untuk print label QR code.');
        }

        $labelScope = $request->boolean('all') ? 'Semua Rak Aktif' : 'Rak Terpilih';

        return view('admin.racks.print_labels', [
            'racks' => $selectedRacks,
            'labelScope' => $labelScope,
            'printedAt' => time(),
        ]);
    }

    public function exportBarcodes(Request $request)
    {
        $selectedRacks = $this->resolveSelectedRacks($request);

        if (count($selectedRacks) === 0) {
            return redirect()->route('admin.racks.index')
                ->with('error', 'Pilih minimal satu rak untuk export QR code.');
        }

        $fileName = 'rack-qr-codes-'.date('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($selectedRacks) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Rack ID', 'Nama Rak', 'Lokasi', 'QR Value', 'Status']);

            foreach ($selectedRacks as $rack) {
                $status = (($rack['is_active'] ?? true) === true) ? 'Aktif' : 'Nonaktif';

                fputcsv($output, [
                    $this->sanitizeCsvCell((string) ($rack['id'] ?? '')),
                    $this->sanitizeCsvCell((string) ($rack['name'] ?? '')),
                    $this->sanitizeCsvCell((string) ($rack['location'] ?? '')),
                    $this->sanitizeCsvCell((string) ($rack['barcode_value'] ?? '')),
                    $status,
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulkUpdateType(BulkUpdateRackTypeRequest $request)
    {
        $selectedRacks = $this->resolveSelectedRacks($request);

        if (count($selectedRacks) === 0) {
            return redirect()->route('admin.racks.index')
                ->with('error', 'Pilih minimal satu rak yang valid untuk update tipe.');
        }

        $rackType = $request->input('rack_type', 'storage');

        foreach ($selectedRacks as $rack) {
            $rackId = (string) ($rack['id'] ?? '');
            if ($rackId === '') {
                continue;
            }

            $this->firebase->updateRack($rackId, [
                'name' => (string) ($rack['name'] ?? ''),
                'location' => (string) ($rack['location'] ?? ''),
                'description' => (string) ($rack['description'] ?? ''),
                'is_active' => (bool) ($rack['is_active'] ?? true),
                'rack_type' => $rackType,
                'check_order' => (int) ($rack['check_order'] ?? 0),
            ]);
        }

        $this->firebase->logAuditAction('bulk_update_type', 'rack', null, [
            'rack_ids' => array_values(array_map(fn ($rack) => (string) ($rack['id'] ?? ''), $selectedRacks)),
            'rack_type' => $rackType,
            'count' => count($selectedRacks),
        ]);

        $typeLabel = $rackType === 'display' ? 'Display' : 'Storage';

        return redirect()->route('admin.racks.index')
            ->with('success', count($selectedRacks).' rak berhasil diubah ke tipe '.$typeLabel.'.');
    }

    public function edit($id)
    {
        $rack = $this->firebase->getRackById($id);
        if (! $rack) {
            abort(404);
        }

        return view('admin.racks.edit', compact('rack'));
    }

    public function update(UpdateRackRequest $request, $id)
    {
        $rack = $this->firebase->getRackById($id);
        if (! $rack) {
            abort(404);
        }

        $this->firebase->updateRack($id, [
            'name' => $request->name,
            'location' => $request->location,
            'description' => $request->description,
            'is_active' => (bool) $request->is_active,
            'rack_type' => $request->input('rack_type', 'storage'),
            'check_order' => (int) $request->input('check_order', 0),
        ]);

        return redirect()->route('admin.racks.index')
            ->with('success', 'Data rak berhasil diupdate.');
    }

    public function regenerateBarcode($id)
    {
        $barcode = $this->firebase->regenerateRackBarcode($id);
        if (! $barcode) {
            abort(404);
        }

        return redirect()->route('admin.racks.index')
            ->with('success', 'QR code rak berhasil digenerate ulang.');
    }

    public function history(Request $request, $id)
    {
        $rack = $this->firebase->getRackById($id);
        if (! $rack) {
            abort(404);
        }

        $history = $this->firebase->getRackCheckHistory($id, 100);
        $rackProducts = $this->firebase->getRackProducts($id);
        $liveStockMap = $this->firebase->getRackProductLiveStock($id, $rackProducts);
        $stockMovements = $this->firebase->getRackStockMovements($id, 800);

        $filterProductId = trim((string) $request->input('movement_product_id', ''));
        $filterStatus = trim((string) $request->input('movement_status', 'all'));
        $filterDateFrom = trim((string) $request->input('movement_date_from', ''));
        $filterDateTo = trim((string) $request->input('movement_date_to', ''));

        $fromTimestamp = $filterDateFrom !== '' ? strtotime($filterDateFrom.' 00:00:00') : null;
        $toTimestamp = $filterDateTo !== '' ? strtotime($filterDateTo.' 23:59:59') : null;

        $filteredStockMovements = array_values(array_filter($stockMovements, function ($movement) use ($filterProductId, $filterStatus, $fromTimestamp, $toTimestamp) {
            if ($filterProductId !== '' && (string) ($movement['product_id'] ?? '') !== $filterProductId) {
                return false;
            }

            $isShortage = (bool) ($movement['is_shortage'] ?? false);
            if ($filterStatus === 'shortage' && ! $isShortage) {
                return false;
            }
            if ($filterStatus === 'ok' && $isShortage) {
                return false;
            }

            $completedAt = (int) ($movement['completed_at'] ?? 0);
            if ($fromTimestamp !== null && $completedAt < $fromTimestamp) {
                return false;
            }
            if ($toTimestamp !== null && $completedAt > $toTimestamp) {
                return false;
            }

            return true;
        }));

        // Resolve waiter names
        $waiters = collect($this->firebase->getActiveWaiters())->keyBy('id')->toArray();

        return view('admin.racks.history', compact(
            'rack',
            'history',
            'waiters',
            'rackProducts',
            'liveStockMap',
            'stockMovements',
            'filteredStockMovements',
            'filterProductId',
            'filterStatus',
            'filterDateFrom',
            'filterDateTo'
        ));
    }

    public function destroy($id)
    {
        $this->firebase->deleteRack($id);

        $this->firebase->logAuditAction('delete', 'rack', $id, []);

        return redirect()->route('admin.racks.index')
            ->with('success', 'Rak berhasil dihapus.');
    }

    protected function resolveSelectedRacks(Request $request): array
    {
        $allRacks = $this->firebase->getRacks();

        if ($request->boolean('all')) {
            return $allRacks;
        }

        $rawRackIds = $request->input('rack_ids', []);
        if (! is_array($rawRackIds)) {
            $rawRackIds = explode(',', (string) $rawRackIds);
        }

        $rackIds = array_values(array_unique(array_filter(array_map(function ($id) {
            return trim((string) $id);
        }, $rawRackIds), function ($id) {
            return $id !== '';
        })));

        if (count($rackIds) === 0) {
            return [];
        }

        return array_values(array_filter($allRacks, function ($rack) use ($rackIds) {
            return in_array((string) ($rack['id'] ?? ''), $rackIds, true);
        }));
    }

    protected function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
