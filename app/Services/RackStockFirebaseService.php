<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class RackStockFirebaseService
{
    protected $database;
    protected FirebaseService $firebase;
    protected ProductFirebaseService $product;
    protected ShiftScheduleFirebaseService $shift;
    protected array $requestCache = [];
    protected array $lastCreatedRestocks = [];

    public function __construct(
        Database $database,
        FirebaseService $firebase,
        ProductFirebaseService $product,
        ShiftScheduleFirebaseService $shift
    ) {
        $this->database = $database;
        $this->firebase = $firebase;
        $this->product = $product;
        $this->shift = $shift;
    }

    /**
     * Get all rack master data.
     *
     * Bandwidth: per-request memo. Beberapa flow (mis. recordRackStockMovement →
     * maybeAutoCreateRestockOnLowStock → getActiveRacks → getRacks) bisa fire
     * berkali-kali di satu request. Cache dalam request lifecycle saja, bust
     * otomatis hilang di akhir request PHP-FPM.
     */
    public function getRacks()
    {
        if (isset($this->requestCache['racks'])) {
            return $this->requestCache['racks'];
        }

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

        $this->requestCache['racks'] = $racks;
        return $racks;
    }

    /**
     * Get active rack master data.
     */
    public function getActiveRacks()
    {
        return app(\App\Repositories\Contracts\RackRepositoryInterface::class)->allActive();
    }

    /**
     * Get rack by id.
     */
    public function getRackById($id)
    {
        return app(\App\Repositories\Contracts\RackRepositoryInterface::class)->find((string) $id);
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
     * Get stock movement rows (one row = one product check event) for a rack.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRackStockMovements(string $rackId, ?int $limit = 500): array
    {
        $movements = [];

        $ledgerSnapshot = $this->database->getReference('rack_stock_movements')
            ->orderByChild('rack_id')
            ->equalTo($rackId)
            ->getSnapshot();

        if ($ledgerSnapshot->exists()) {
            foreach ($ledgerSnapshot->getValue() as $movementId => $movement) {
                if (! is_array($movement)) {
                    continue;
                }

                $movements[] = array_merge(['id' => $movementId], $movement);
            }

            usort($movements, function ($a, $b) {
                return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
            });

            if ($limit !== null && count($movements) > $limit) {
                $movements = array_slice($movements, 0, $limit);
            }

            return $movements;
        }

        $history = $this->getRackCheckHistory($rackId, $limit);

        foreach ($history as $task) {
            $taskId = (string) ($task['id'] ?? '');
            $completedAt = $this->firebase->normalizeUnixTimestampToSeconds((int) ($task['completed_at'] ?? 0));
            $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
            $waiterName = (string) ($task['assigned_waiter_name'] ?? '');

            $checklist = $task['completed_product_checklist'] ?? [];
            if (! is_array($checklist) || count($checklist) === 0) {
                continue;
            }

            foreach ($checklist as $checklistKey => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $productId = trim((string) ($item['product_id'] ?? $checklistKey));
                if ($productId === '') {
                    continue;
                }

                $standardQty = max(0, (int) ($item['standard_qty'] ?? 0));
                $actualQty = max(0, (int) ($item['actual_qty'] ?? 0));
                $delta = $actualQty - $standardQty;

                $movements[] = [
                    'id' => $taskId !== '' ? $taskId.':'.$productId : (string) count($movements),
                    'task_id' => $taskId,
                    'rack_id' => $rackId,
                    'product_id' => $productId,
                    'product_name' => trim((string) ($item['product_name'] ?? '')),
                    'product_unit' => trim((string) ($item['product_unit'] ?? 'pcs')),
                    'standard_qty' => $standardQty,
                    'actual_qty' => $actualQty,
                    'delta_qty' => $delta,
                    'is_shortage' => $standardQty > 0 ? $actualQty < $standardQty : false,
                    'completed_at' => $completedAt,
                    'waiter_id' => $waiterId,
                    'waiter_name' => $waiterName,
                ];
            }
        }

        usort($movements, function ($a, $b) {
            return ((int) ($b['completed_at'] ?? 0)) <=> ((int) ($a['completed_at'] ?? 0));
        });

        return $movements;
    }

    /**
     * Record a rack stock movement and persist the live balance.
     *
     * @return array<string, mixed>
     */
    protected function recordRackStockMovement(array $data): array
    {
        $rackId = trim((string) ($data['rack_id'] ?? ''));
        $productId = trim((string) ($data['product_id'] ?? ''));
        $movementType = trim((string) ($data['movement_type'] ?? 'stock_take'));
        $source = trim((string) ($data['source'] ?? 'waiter_task'));
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        if ($rackId === '' || $productId === '') {
            return [
                'success' => false,
                'message' => 'Data rak atau produk tidak lengkap.',
            ];
        }

        if ($idempotencyKey !== '') {
            $idempotencySnapshot = $this->database->getReference('stock_movement_idempotency/'.$idempotencyKey)->getSnapshot();
            if ($idempotencySnapshot->exists()) {
                $record = $idempotencySnapshot->getValue();
                if (is_array($record) && isset($record['response']) && is_array($record['response'])) {
                    return $record['response'];
                }
            }
        }

        // Read current product object (untuk metadata fallback) — bukan untuk current_qty atomic.
        $rackProductRef = $this->database->getReference("waiter_racks/{$rackId}/products/{$productId}");
        $rackProductSnapshot = $rackProductRef->getSnapshot();
        $rackProduct = $rackProductSnapshot->exists() && is_array($rackProductSnapshot->getValue())
            ? $rackProductSnapshot->getValue()
            : [];

        // Provided values dari caller — dipakai untuk menghitung next_qty di dalam transaksi.
        $providedActualQty = array_key_exists('actual_qty', $data) && $data['actual_qty'] !== null
            ? (int) $data['actual_qty']
            : (array_key_exists('current_qty', $data) && $data['current_qty'] !== null
                ? (int) $data['current_qty']
                : 0);

        $providedDelta = array_key_exists('delta_qty', $data) && $data['delta_qty'] !== null
            ? (int) $data['delta_qty']
            : 0;

        // Atomic CAS: read → compute → write current_qty di leaf path agar aman dari race
        // antar waiter (misal storage_out konkuren dari stok yang sama).
        $qtyRef = $this->database->getReference("waiter_racks/{$rackId}/products/{$productId}/current_qty");
        $capturedPrev = null; // null = leaf belum pernah ada
        $capturedNew = null;

        $maxAttempts = 3;
        $lastTxnError = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->database->runTransaction(function ($transaction) use (
                    $qtyRef,
                    $movementType,
                    $providedDelta,
                    $providedActualQty,
                    &$capturedPrev,
                    &$capturedNew
                ) {
                    $snap = $transaction->snapshot($qtyRef);
                    $rawValue = $snap->getValue();
                    $hadValue = $snap->exists() && $rawValue !== null && is_numeric($rawValue);
                    $existing = $hadValue ? (int) $rawValue : 0;
                    $capturedPrev = $hadValue ? $existing : null;

                    if ($movementType === 'stock_take') {
                        $next = $providedActualQty; // overwrite
                    } elseif ($movementType === 'po_receive' || $movementType === 'storage_out') {
                        // Caller mengirim signed delta (po_receive: positif, storage_out: negatif).
                        $next = $existing + $providedDelta;
                    } else {
                        $next = $existing;
                    }

                    $capturedNew = $next;
                    $transaction->set($qtyRef, $next);
                });
                $lastTxnError = null;
                break;
            } catch (TransactionFailed $e) {
                $lastTxnError = $e;
                if ($attempt < $maxAttempts) {
                    // Back-off singkat, lalu coba lagi (ETag conflict).
                    usleep(50000 * $attempt);
                }
            }
        }

        if ($lastTxnError !== null) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan stok rak akibat konflik penulisan, coba ulang.',
            ];
        }

        $previousQty = $capturedPrev;
        $currentQty = (int) $capturedNew;
        $deltaQty = $currentQty - ($previousQty ?? 0);

        $now = time();
        $movementPayload = [
            'rack_id' => $rackId,
            'product_id' => $productId,
            'product_name' => trim((string) ($data['product_name'] ?? ($rackProduct['product_name'] ?? ($rackProduct['name'] ?? '')))),
            'product_unit' => trim((string) ($data['product_unit'] ?? 'pcs')),
            'movement_type' => $movementType,
            'source' => $source,
            'task_id' => trim((string) ($data['task_id'] ?? '')),
            'po_id' => trim((string) ($data['po_id'] ?? '')),
            'restock_id' => trim((string) ($data['restock_id'] ?? '')),
            'waiter_id' => trim((string) ($data['waiter_id'] ?? '')),
            'waiter_name' => trim((string) ($data['waiter_name'] ?? '')),
            'reported_by' => trim((string) ($data['reported_by'] ?? '')),
            'reported_by_name' => trim((string) ($data['reported_by_name'] ?? '')),
            'note' => trim((string) ($data['note'] ?? '')),
            'standard_qty' => max(0, (int) ($data['standard_qty'] ?? 0)),
            'min_qty' => max(0, (int) ($data['min_qty'] ?? 0)),
            'previous_qty' => $previousQty,
            'current_qty' => $currentQty,
            'from_qty' => $previousQty,
            'to_qty' => $currentQty,
            'delta_qty' => $deltaQty,
            'actual_qty' => array_key_exists('actual_qty', $data) ? (int) $data['actual_qty'] : null,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'created_at' => $now,
        ];

        $movementRef = $this->database->getReference('rack_stock_movements')->push($movementPayload);
        $movementId = $movementRef->getKey();

        // P0-4: stok negatif bukan diblokir / di-clamp, tapi dicatat sebagai anomali kritis
        // agar supervisor bisa menelusuri sumbernya tanpa menghentikan operasi waiter.
        if ($currentQty < 0) {
            $anomalyProductName = trim((string) ($data['product_name'] ?? ''));
            if ($anomalyProductName === '') {
                $anomalyProductName = trim((string) ($rackProduct['product_name'] ?? ($rackProduct['name'] ?? '')));
            }

            $signedDelta = $movementType === 'stock_take' ? 'overwrite' : $deltaQty;

            $this->database->getReference('audit_logs/stock_anomalies')->push([
                'severity' => 'critical',
                'rack_id' => $rackId,
                'product_id' => $productId,
                'product_name' => $anomalyProductName,
                'movement_type' => $movementType,
                'previous_qty' => $previousQty,
                'delta_qty' => $signedDelta,
                'resulting_qty' => $currentQty,
                'actor_id' => trim((string) ($data['waiter_id'] ?? ($data['reported_by'] ?? ''))),
                'actor_name' => trim((string) ($data['waiter_name'] ?? ($data['reported_by_name'] ?? ''))),
                'movement_id' => $movementId,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'timestamp' => ['.sv' => 'timestamp'],
            ]);
        }

        // Tulis metadata pendamping di luar transaksi (last_movement_id, dsb).
        // current_qty sengaja TIDAK ditulis ulang di sini agar tidak menimpa hasil CAS.
        $rackProductUpdates = [
            'last_updated_at' => $now,
            'last_movement_id' => $movementId,
            'last_movement_type' => $movementType,
            'updated_at' => $now,
        ];

        if (array_key_exists('standard_qty', $data) && $data['standard_qty'] !== null) {
            $rackProductUpdates['standard_qty'] = max(0, (int) $data['standard_qty']);
        }
        if (array_key_exists('min_qty', $data) && $data['min_qty'] !== null) {
            $rackProductUpdates['min_qty'] = max(0, (int) $data['min_qty']);
        }

        $rackProductRef->update($rackProductUpdates);

        $response = [
            'success' => true,
            'movement_id' => $movementId,
            'rack_id' => $rackId,
            'product_id' => $productId,
            'current_qty' => $currentQty,
            'previous_qty' => $previousQty,
            'delta_qty' => $deltaQty,
        ];

        // P1-3: deteksi shortage berbasis threshold, independen dari lifecycle task.
        try {
            $this->maybeAutoCreateRestockOnLowStock(
                $rackId,
                $productId,
                $currentQty,
                $rackProduct,
                $data
            );
        } catch (\Throwable $e) {
            // Jangan ganggu flow utama pergerakan stok jika auto-restock gagal.
            report($e);
        }

        if ($idempotencyKey !== '') {
            $this->database->getReference('stock_movement_idempotency/'.$idempotencyKey)->set([
                'scope' => 'rack_stock',
                'movement_id' => $movementId,
                'response' => $response,
                'created_at' => $now,
            ]);
        }

        return $response;
    }

    protected function findActiveRackByBarcode(string $barcodeValue): ?array
    {
        $candidates = $this->extractRackBarcodeCandidates($barcodeValue);
        if (count($candidates) === 0) {
            return null;
        }

        foreach ($this->getActiveRacks() as $rack) {
            $rackBarcode = strtoupper(trim((string) ($rack['barcode_value'] ?? '')));
            if ($rackBarcode === '') {
                continue;
            }

            foreach ($candidates as $candidate) {
                if ($candidate === $rackBarcode) {
                    return $rack;
                }
            }
        }

        return null;
    }

    /**
     * Resolve storage rack from barcode/QR payload.
     * If multiple racks share same barcode, prefer the one that already has assigned products.
     *
     * @return array<string,mixed>|null
     */
    protected function resolveStorageRackByBarcode(string $barcodeValue): ?array
    {
        $candidates = $this->extractRackBarcodeCandidates($barcodeValue);
        if (count($candidates) === 0) {
            return null;
        }

        $matchedStorageRacks = [];
        foreach ($this->getActiveRacks() as $rack) {
            $rackType = trim((string) ($rack['rack_type'] ?? 'storage'));
            if ($rackType !== 'storage') {
                continue;
            }

            $rackBarcode = strtoupper(trim((string) ($rack['barcode_value'] ?? '')));
            if ($rackBarcode === '') {
                continue;
            }

            if (in_array($rackBarcode, $candidates, true)) {
                $matchedStorageRacks[] = $rack;
            }
        }

        if (count($matchedStorageRacks) === 0) {
            return null;
        }

        foreach ($matchedStorageRacks as $rack) {
            $rackId = trim((string) ($rack['id'] ?? ''));
            if ($rackId === '') {
                continue;
            }

            if (count($this->product->getRackProducts($rackId)) > 0) {
                return $rack;
            }
        }

        return $matchedStorageRacks[0] ?? null;
    }

    /**
     * Extract possible rack barcode values from raw QR payload.
     * Supports plain text, URL query/path, and simple JSON payload.
     *
     * @return array<int, string>
     */
    protected function extractRackBarcodeCandidates(string $rawValue): array
    {
        $raw = trim($rawValue);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $push = static function (array &$list, string $value): void {
            $normalized = strtoupper(trim($value));
            if ($normalized === '') {
                return;
            }
            if (! in_array($normalized, $list, true)) {
                $list[] = $normalized;
            }
        };

        $push($candidates, $raw);

        $decodedJson = json_decode($raw, true);
        if (is_array($decodedJson)) {
            foreach (['rack_barcode_value', 'barcode_value', 'rack_barcode', 'barcode', 'rack_code', 'code'] as $key) {
                if (isset($decodedJson[$key])) {
                    $push($candidates, (string) $decodedJson[$key]);
                }
            }
        }

        $url = filter_var($raw, FILTER_VALIDATE_URL) ? $raw : null;
        if ($url) {
            $parts = parse_url($url);
            if (is_array($parts)) {
                if (isset($parts['query'])) {
                    parse_str((string) $parts['query'], $query);
                    if (is_array($query)) {
                        foreach (['rack_barcode_value', 'barcode_value', 'rack_barcode', 'barcode', 'rack_code', 'code'] as $key) {
                            if (isset($query[$key])) {
                                $push($candidates, (string) $query[$key]);
                            }
                        }
                    }
                }

                if (isset($parts['path'])) {
                    $pathSegments = array_values(array_filter(explode('/', (string) $parts['path']), static function ($segment) {
                        return trim((string) $segment) !== '';
                    }));
                    if (count($pathSegments) > 0) {
                        $push($candidates, (string) end($pathSegments));
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Standalone stock take: waiter takes stock from storage rack without task flow.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitStandaloneStockTake(array $payload): array
    {
        // Reset capture-bag untuk ekspos restock_requests yg ter-create otomatis di end-of-call
        $this->lastCreatedRestocks = [];

        $waiterId = trim((string) ($payload['waiter_id'] ?? ''));
        $waiterName = trim((string) ($payload['waiter_name'] ?? 'Waiter'));
        $rackBarcodeValue = strtoupper(trim((string) ($payload['rack_barcode_value'] ?? '')));
        $items = $payload['items'] ?? [];
        $note = trim((string) ($payload['note'] ?? ''));
        $idempotencyPrefix = trim((string) ($payload['idempotency_key'] ?? ''));

        if ($waiterId === '') {
            return ['success' => false, 'message' => 'Sesi waiter tidak valid.'];
        }
        if ($rackBarcodeValue === '') {
            return ['success' => false, 'message' => 'Barcode rak wajib diisi.'];
        }
        if (! is_array($items) || count($items) === 0) {
            return ['success' => false, 'message' => 'Pilih minimal satu item yang diambil.'];
        }

        $rack = $this->resolveStorageRackByBarcode($rackBarcodeValue);
        if (! $rack) {
            return ['success' => false, 'message' => 'Rak tidak ditemukan atau tidak aktif.'];
        }

        $rackId = trim((string) ($rack['id'] ?? ''));

        $rackProducts = $this->product->getRackProducts($rackId);
        $rackProductMap = [];
        foreach ($rackProducts as $product) {
            $productId = trim((string) ($product['id'] ?? ''));
            if ($productId === '') {
                continue;
            }
            $rackProductMap[$productId] = $product;
        }

        $movementRows = [];
        $invalidRows = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = trim((string) ($item['product_id'] ?? ''));
            $takeQty = max(0, (int) ($item['qty'] ?? 0));
            if ($productId === '' || $takeQty <= 0) {
                continue;
            }

            if (! isset($rackProductMap[$productId])) {
                $invalidRows[] = ['product_id' => $productId, 'message' => 'Produk tidak terdaftar di rak ini.'];
                continue;
            }

            $rackProduct = $rackProductMap[$productId];
            $currentQty = array_key_exists('current_qty', $rackProduct) && $rackProduct['current_qty'] !== null
                ? max(0, (int) $rackProduct['current_qty'])
                : max(0, (int) ($rackProduct['standard_qty'] ?? 0));

            if ($currentQty <= 0) {
                $invalidRows[] = [
                    'product_id' => $productId,
                    'product_name' => (string) ($rackProduct['name'] ?? ''),
                    'message' => 'Stok '.($rackProduct['name'] ?? 'produk').' kosong. Tambah stok rak dulu sebelum diambil.',
                ];
                continue;
            }

            if ($takeQty > $currentQty) {
                $invalidRows[] = [
                    'product_id' => $productId,
                    'product_name' => (string) ($rackProduct['name'] ?? ''),
                    'message' => 'Qty diambil ('.$takeQty.') melebihi stok tersedia ('.$currentQty.') untuk '.($rackProduct['name'] ?? 'produk').'.',
                ];
                continue;
            }

            $nextQty = max(0, $currentQty - $takeQty);

            $movementRows[] = [
                'product_id' => $productId,
                'product_name' => (string) ($rackProduct['name'] ?? ''),
                'product_unit' => (string) ($rackProduct['unit'] ?? 'pcs'),
                'standard_qty' => max(0, (int) ($rackProduct['standard_qty'] ?? 0)),
                'min_qty' => max(0, (int) ($rackProduct['min_qty'] ?? 0)),
                'taken_qty' => $takeQty,
                'previous_qty' => $currentQty,
                'current_qty' => $nextQty,
                'idempotency_key' => $idempotencyPrefix !== ''
                    ? $idempotencyPrefix.':'.$rackId.':'.$productId.':'.$index
                    : '',
            ];
        }

        if (count($movementRows) === 0) {
            // Build human-readable summary from invalid items so frontend can show actionable error
            $summary = '';
            if (count($invalidRows) > 0) {
                $messages = array_values(array_filter(array_map(static function ($row) {
                    return is_array($row) && isset($row['message']) ? (string) $row['message'] : '';
                }, $invalidRows)));
                $summary = implode(' ', array_slice($messages, 0, 3));
            }

            return [
                'success' => false,
                'message' => $summary !== ''
                    ? $summary
                    : 'Tidak ada item valid untuk diproses.',
                'invalid_items' => $invalidRows,
            ];
        }

        $movementResults = [];
        foreach ($movementRows as $row) {
            $movementResult = $this->recordRackStockMovement([
                'rack_id' => $rackId,
                'product_id' => $row['product_id'],
                'movement_type' => 'storage_out',
                'source' => 'waiter_stock_take',
                'waiter_id' => $waiterId,
                'waiter_name' => $waiterName,
                'product_name' => $row['product_name'],
                'product_unit' => $row['product_unit'],
                'standard_qty' => $row['standard_qty'],
                'min_qty' => $row['min_qty'],
                'current_qty' => $row['current_qty'],
                'delta_qty' => -$row['taken_qty'],
                'actual_qty' => $row['current_qty'],
                'note' => $note !== '' ? $note : 'Pengambilan stok gudang mandiri',
                'idempotency_key' => $row['idempotency_key'],
            ]);

            if (! ($movementResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => (string) ($movementResult['message'] ?? 'Gagal menyimpan movement stok.'),
                ];
            }

            $movementResults[] = [
                'movement_id' => (string) ($movementResult['movement_id'] ?? ''),
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'taken_qty' => $row['taken_qty'],
                'previous_qty' => $row['previous_qty'],
                'current_qty' => (int) ($movementResult['current_qty'] ?? $row['current_qty']),
            ];
        }

        return [
            'success' => true,
            'rack_id' => $rackId,
            'rack_name' => (string) ($rack['name'] ?? ''),
            'rack_barcode_value' => $rackBarcodeValue,
            'processed_items' => $movementResults,
            'invalid_items' => $invalidRows,
            'created_restock_requests' => $this->lastCreatedRestocks,
            'message' => 'Pengambilan stok berhasil disimpan.',
        ];
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
     * Mark a legacy rack_check task as pending review.
     * Dipakai oleh BonusRackRecheckMarkLegacy command untuk migrasi data
     * task `done` yang dibuat sebelum field recheck_pending ditambahkan ke schema.
     *
     * Aman dipanggil idempoten: kalau task sudah punya recheck_pending field,
     * caller harus skip duluan.
     *
     * @return array{success: bool, message?: string}
     */
    public function markRackCheckPendingReview(string $taskId): array
    {
        $taskRef = $this->database->getReference('waiter_tasks/'.$taskId);
        $snapshot = $taskRef->getSnapshot();

        if (! $snapshot->exists()) {
            return ['success' => false, 'message' => 'Task tidak ditemukan.'];
        }

        $task = (array) $snapshot->getValue();
        $type = (string) ($task['task_type'] ?? '');
        $status = (string) ($task['status'] ?? '');

        if ($type !== 'rack_check') {
            return ['success' => false, 'message' => 'Bukan rack_check task.'];
        }
        if ($status !== 'done') {
            return ['success' => false, 'message' => 'Task belum done (status: '.$status.').'];
        }

        $taskRef->update([
            'recheck_pending' => true,
            'recheck_points' => null,
            'recheck_notes' => null,
            'recheck_by' => null,
            'recheck_by_name' => null,
            'recheck_at' => null,
        ]);

        return ['success' => true];
    }

    /**
     * Submit Finance recheck review untuk task rack_check yang sudah done.
     *
     * Validates:
     * - Task ada
     * - Task type rack_check
     * - Task status done
     * - Task masih recheck_pending (belum direview)
     * - Points 0..maxPoints
     *
     * Updates task fields: recheck_pending=false, recheck_points, recheck_notes,
     * recheck_by, recheck_by_name, recheck_at.
     *
     * Caller (controller) bertanggung jawab re-trigger autoScoreDailyPoints
     * untuk waiter pemilik task supaya kategori rack_recheck terupdate.
     *
     * @return array { success, task?, message? }
     */
    public function submitRackCheckReview(
        string $taskId,
        string $financeId,
        string $financeName,
        int $points,
        string $notes,
        int $maxPoints = 10
    ): array {
        $points = max(0, min($maxPoints, $points));
        $taskRef = $this->database->getReference('waiter_tasks/'.$taskId);
        $snapshot = $taskRef->getSnapshot();

        if (! $snapshot->exists()) {
            return ['success' => false, 'message' => 'Task tidak ditemukan.'];
        }

        $task = $snapshot->getValue();
        $taskType = (string) ($task['task_type'] ?? 'general');
        $status = (string) ($task['status'] ?? '');

        if ($taskType !== 'rack_check') {
            return ['success' => false, 'message' => 'Task bukan tugas Cek Rak.'];
        }
        if ($status !== 'done') {
            return ['success' => false, 'message' => 'Task belum selesai (status: '.$status.').'];
        }

        $now = time();
        $updates = [
            'recheck_pending' => false,
            'recheck_points' => $points,
            'recheck_notes' => trim($notes),
            'recheck_by' => $financeId,
            'recheck_by_name' => $financeName,
            'recheck_at' => $now,
        ];

        $taskRef->update($updates);

        $updatedTask = array_merge(['id' => $taskId], $task, $updates);

        return [
            'success' => true,
            'task' => $updatedTask,
            'message' => 'Review berhasil disimpan: '.$points.' poin.',
        ];
    }

    /**
     * Get list of rack_check tasks pending Finance review.
     * Returns tasks where: task_type=rack_check, status=done, recheck_pending=true.
     *
     * @param  string|null  $date  Filter by scheduled_for_date (Y-m-d), null = today
     * @param  int  $lookbackDays  Number of days backwards to include (0 = only $date itself)
     * @return array
     */
    public function getRackCheckPendingReview(?string $date = null, int $lookbackDays = 0): array
    {
        $date = $date ?: date('Y-m-d');
        $lookbackDays = max(0, $lookbackDays);

        $allPending = [];
        for ($offset = 0; $offset <= $lookbackDays; $offset++) {
            $checkDate = date('Y-m-d', strtotime($date . ' -' . $offset . ' days'));
            $tasks = $this->firebase->getWaiterTasksByDate($checkDate);
            foreach ($tasks as $t) {
                if (($t['task_type'] ?? '') === 'rack_check'
                    && ($t['status'] ?? '') === 'done'
                    && ! empty($t['recheck_pending'])) {
                    $allPending[] = $t;
                }
            }
        }

        usort($allPending, function ($a, $b) {
            return ($b['completed_at'] ?? 0) - ($a['completed_at'] ?? 0);
        });

        return $allPending;
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
     * Migrate legacy base64 task photos in /waiter_tasks to Firebase Storage,
     * replacing each data URL with a storage URL. Idempotent: skips entries
     * already holding an http URL. Returns counters for reporting.
     */
    public function migrateTaskPhotosToStorage(string $from, string $to, bool $dryRun = true): array
    {
        $snapshot = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->startAt($from)
            ->endAt($to)
            ->getSnapshot();

        $items = $snapshot->getValue() ?: [];
        $scanned = 0;
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($items as $taskId => $task) {
            if (! is_array($task)) {
                continue;
            }
            $scanned++;

            $fields = [
                'completed_photo_proof_url' => 'proof',
                'completed_photo_before_url' => 'before',
            ];

            $updates = [];
            foreach ($fields as $field => $kind) {
                $value = (string) ($task[$field] ?? '');
                if ($value === '') {
                    continue; // no photo in this field
                }
                if (strpos($value, 'data:image') !== 0) {
                    $skipped++; // already an http URL
                    continue;
                }

                if ($dryRun) {
                    $migrated++;
                    continue;
                }

                $url = $this->firebase->uploadTaskPhoto($value, (string) $taskId, $kind);
                if ($url === '') {
                    $failed++;
                    continue;
                }
                $updates[$field] = $url;
                $migrated++;
            }

            if (! $dryRun && ! empty($updates)) {
                $this->database->getReference('waiter_tasks/'.$taskId)->update($updates);
            }
        }

        return compact('scanned', 'migrated', 'failed', 'skipped');
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
     * P0-3: dipanggil dari updateWaiterTaskStatus() SEBELUM task status flip ke 'done'.
     * Iterasi product_checklist, decide kebutuhan restock berdasar rack_type
     * (storage vs display), lalu call createOrUpdateRestockRequest per item.
     *
     * Kalau salah satu item gagal → return success=false, caller wajib abort
     * sebelum status flip biar shortage signal tidak hilang.
     */
    protected function writeRestockRequestsForCompletion(
        string $taskId,
        array $task,
        array $productChecklist,
        string $waiterId,
        string $waiterName
    ): array {
        $rackId = (string) ($task['rack_id'] ?? '');
        if ($rackId === '') {
            // Tidak ada rack target → tidak ada restock yang perlu dicatat.
            return ['success' => true];
        }

        $rackName = (string) ($task['rack_name'] ?? ($task['title'] ?? ''));
        $rack = $this->getRackById($rackId);
        $rackType = (string) ($rack['rack_type'] ?? 'storage');

        // Hanya storage & display rack yang punya pipeline restock.
        if ($rackType !== 'storage' && $rackType !== 'display') {
            return ['success' => true];
        }

        $productCategories = $this->product->getProductCategoriesMap();
        $today = date('Y-m-d');

        try {
            foreach ($productChecklist as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $actualQty = (int) ($item['actual_qty'] ?? 0);
                $standardQty = (int) ($item['standard_qty'] ?? 0);
                $minQty = (int) ($item['min_qty'] ?? 0);

                $productId = (string) ($item['product_id'] ?? '');

                // P0-5: shortage detection harus independen dari `was_refilled`.
                //
                // STORAGE rack:
                //   actual_qty < standard_qty → restock request langsung
                //   (sumber barang dari supplier, jadi tidak ada fallback lain).
                //
                // DISPLAY rack:
                //   actual_qty < standard_qty di display saja TIDAK cukup —
                //   waiter biasanya bisa refill dari gudang. Tapi kalau gudang
                //   juga rendah (combined_available < standard_qty) maka signal
                //   harus naik ke supervisor. Was_refilled flag tetap dihormati
                //   sebagai sumber paling jelas, tapi storage-low check bekerja
                //   independen: kalau gudang habis sebelum waiter refill, signal
                //   tetap muncul. createOrUpdateRestockRequest sudah dedup
                //   per product+rack+pending sehingga aman dari double-fire.
                $needsRestock = false;
                $restockSource = null;
                $totalStorageQty = 0;
                $combinedAvailable = 0;

                if ($rackType === 'storage') {
                    if ($standardQty > 0 && $actualQty < $standardQty) {
                        $needsRestock = true;
                        $restockSource = 'storage_rack_shortage';
                    }
                } else {
                    // DISPLAY rack
                    $wasRefilled = (bool) ($item['was_refilled'] ?? false);
                    $isShort = $standardQty > 0 && $actualQty < $standardQty;

                    if ($isShort) {
                        $totalStorageQty = $productId !== ''
                            ? $this->product->getTotalStorageQtyForProduct($productId)
                            : 0;
                        $combinedAvailable = $totalStorageQty + $actualQty;

                        if ($wasRefilled) {
                            // Sudah refill tapi masih kurang → gudang tidak cukup.
                            $needsRestock = true;
                            $restockSource = 'display_rack_post_refill_short';
                        } elseif ($combinedAvailable < $standardQty) {
                            // Belum refill, dan gudang juga tidak cukup → signal harus naik
                            // sekarang (jangan tunggu waiter refill manual). Ini termasuk
                            // produk yang tidak ter-assign ke rak storage manapun
                            // (totalStorageQty = 0): tetap auto-PO supaya tidak ada
                            // shortage display yang silently di-skip.
                            $needsRestock = true;
                            $restockSource = 'display_rack_low_storage_low';
                        }
                    }
                }

                if (! $needsRestock) {
                    continue;
                }

                // Default qty_needed: shortage di rak ini.
                $qtyNeeded = $standardQty - $actualQty;

                // P0-5: untuk display + storage_low, qty_needed harus mengakomodasi
                // gap yang tidak bisa ditutup oleh stok gudang yang ada.
                if ($restockSource === 'display_rack_low_storage_low') {
                    $qtyNeeded = $standardQty - $combinedAvailable;
                }

                if ($qtyNeeded <= 0) {
                    $qtyNeeded = $standardQty > 0 ? $standardQty : 1;
                }
                $qtyNeeded = max(1, (int) $qtyNeeded);

                $productMaster = $productId !== '' ? $this->product->getProductById($productId) : null;
                $catId = $productMaster['category_id'] ?? null;
                $catName = ($catId && isset($productCategories[$catId]))
                    ? ($productCategories[$catId]['name'] ?? 'Tanpa Kategori')
                    : 'Tanpa Kategori';

                $noteParts = [];
                if ($restockSource === 'display_rack_low_storage_low') {
                    $noteParts[] = sprintf(
                        'Display "%s" kurang (%d/%d) dan stok gudang juga rendah (total %d).',
                        $rackName !== '' ? $rackName : $rackId,
                        $actualQty,
                        $standardQty,
                        $totalStorageQty
                    );
                }

                $this->firebase->createOrUpdateRestockRequest([
                    'product_id' => $productId,
                    'product_name' => $item['product_name'] ?? ($item['name'] ?? ''),
                    'product_category_id' => $catId,
                    'product_category_name' => $catName,
                    'rack_id' => $rackId,
                    'rack_name' => $rackName,
                    'reported_qty' => $actualQty,
                    'standard_qty' => $standardQty,
                    'min_qty' => $minQty,
                    'qty_needed' => $qtyNeeded,
                    'reported_by' => $waiterId,
                    'reported_by_name' => $waiterName,
                    'date' => $today,
                    'source' => $restockSource,
                    'note' => implode(' ', $noteParts),
                ]);
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        return ['success' => true];
    }

    /**
     * P1-3: auto-create restock request saat stok lewat threshold,
     * dipicu setiap movement commit (tidak bergantung penyelesaian task).
     */
    private function maybeAutoCreateRestockOnLowStock(
        string $rackId,
        string $productId,
        int $currentQty,
        array $rackProduct,
        array $movementMeta
    ): void {
        try {
            $rack = $this->getRackById($rackId);
            if (! $rack) {
                return;
            }

            $rackType = strtolower(trim((string) ($rack['rack_type'] ?? '')));
            if (! in_array($rackType, ['storage', 'display'], true)) {
                return;
            }

            $rackName = trim((string) ($rack['name'] ?? ($rack['rack_name'] ?? '')));
            $standardQty = max(0, (int) ($rackProduct['standard_qty'] ?? 0));
            $rackMinQty = max(0, (int) ($rackProduct['min_qty'] ?? 0));

            $productMaster = $this->product->getProductById($productId) ?? [];
            $masterMinQty = max(0, (int) ($productMaster['min_qty'] ?? 0));

            $threshold = 0;
            $source = '';
            $qtyNeeded = 0;
            $note = '';

            if ($rackType === 'storage') {
                $threshold = $rackMinQty > 0 ? $rackMinQty : $masterMinQty;
                if ($threshold <= 0) {
                    return;
                }

                if ($currentQty >= $threshold) {
                    return;
                }

                $targetQty = $standardQty > 0 ? $standardQty : ($threshold * 2);
                $qtyNeeded = max(1, $targetQty - $currentQty);
                $source = 'auto_threshold_storage';
                $note = sprintf(
                    'Auto threshold storage: stok rak "%s" (%d) di bawah batas minimum (%d).',
                    $rackName !== '' ? $rackName : $rackId,
                    $currentQty,
                    $threshold
                );
            } else {
                // DISPLAY: trigger pakai standard_qty. Jika gudang cukup refill, jangan naik jadi restock PO.
                $threshold = $standardQty;
                if ($threshold <= 0) {
                    return;
                }

                if ($currentQty >= $threshold) {
                    return;
                }

                $totalStorageQty = $this->product->getTotalStorageQtyForProduct($productId);
                $combinedAvailable = $currentQty + $totalStorageQty;
                if ($combinedAvailable >= $standardQty) {
                    return;
                }

                // Display short + storage juga tidak cukup → langsung PO. Ini juga
                // berlaku untuk produk yang belum ter-assign ke rak storage manapun
                // (totalStorageQty = 0): supervisor tetap perlu PO supaya stok bisa
                // dibeli dari supplier — tidak ada konsep pass-through.
                $qtyNeeded = max(1, $standardQty - $combinedAvailable);
                $source = 'auto_threshold_display_storage_low';
                $note = sprintf(
                    'Auto threshold display: stok display "%s" (%d/%d) dan total stok gudang (%d) belum cukup untuk refill.',
                    $rackName !== '' ? $rackName : $rackId,
                    $currentQty,
                    $standardQty,
                    $totalStorageQty
                );
            }

            $reportedBy = trim((string) ($movementMeta['waiter_id'] ?? ($movementMeta['reported_by'] ?? 'auto')));
            if ($reportedBy === '') {
                $reportedBy = 'auto';
            }

            $reportedByName = trim((string) ($movementMeta['waiter_name'] ?? ($movementMeta['reported_by_name'] ?? 'System (auto-threshold)')));
            if ($reportedByName === '') {
                $reportedByName = 'System (auto-threshold)';
            }

            $categoryId = $productMaster['category_id'] ?? null;
            $categoryName = trim((string) ($productMaster['category_name'] ?? ''));
            if ($categoryName === '') {
                $categoryName = 'Tanpa Kategori';
            }

            $restockRequestId = $this->firebase->createOrUpdateRestockRequest([
                'product_id' => $productId,
                'product_name' => trim((string) ($rackProduct['product_name'] ?? ($rackProduct['name'] ?? ($productMaster['name'] ?? '')))),
                'product_category_id' => $categoryId,
                'product_category_name' => $categoryName,
                'rack_id' => $rackId,
                'rack_name' => $rackName,
                'reported_qty' => $currentQty,
                'standard_qty' => $standardQty,
                'min_qty' => $rackMinQty > 0 ? $rackMinQty : $masterMinQty,
                'qty_needed' => $qtyNeeded,
                'reported_by' => $reportedBy,
                'reported_by_name' => $reportedByName,
                'date' => date('Y-m-d'),
                'source' => $source,
                'note' => $note,
            ]);

            // Capture untuk caller (post-submit summary di response API).
            $this->lastCreatedRestocks[] = [
                'product_id' => $productId,
                'product_name' => trim((string) ($rackProduct['product_name'] ?? ($rackProduct['name'] ?? ($productMaster['name'] ?? '')))),
                'rack_id' => $rackId,
                'rack_name' => $rackName,
                'rack_type' => $rackType,
                'source' => $source,
                'qty_needed' => $qtyNeeded,
                'reported_qty' => $currentQty,
                'standard_qty' => $standardQty,
                'restock_request_id' => $restockRequestId,
            ];
        } catch (\Throwable $e) {
            // Wajib non-blocking: auto-restock tidak boleh menggagalkan commit movement.
            report($e);
        }
    }

    /**
     * Get historical data for AI balancing scoring.
     * Cached per run to avoid repeated Firebase queries.
     */
    private function getRackBalancingHistoricalData(string $targetDate): array
    {
        if ($this->rackBalancingCache !== null
            && ($this->rackBalancingCache['target_date'] ?? '') === $targetDate) {
            return $this->rackBalancingCache;
        }

        $allTasks = $this->database->getReference('waiter_tasks')->getValue();
        $today = new \DateTimeImmutable($targetDate);
        $weekStart = $today->modify('-6 days');
        $yesterday = $today->modify('-1 day')->format('Y-m-d');

        $weeklyRackCount = [];
        $yesterdayCount = [];
        $completionTimes = [];
        $recheckScores = [];

        foreach ($allTasks ?? [] as $task) {
            if (($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            // BUG FIX (#8): Skip cancelled tasks from weekly count.
            // Previously, cancelled tasks counted toward workload, causing
            // unfair penalty: a waiter whose task was cancelled by admin
            // looked busier than they actually were.
            if (($task['status'] ?? '') === 'cancelled') {
                continue;
            }
            $wid = (string) ($task['assigned_waiter_id'] ?? '');
            if ($wid === '') {
                continue;
            }

            $schedDate = $task['scheduled_for_date'] ?? '';
            if ($schedDate === '') {
                continue;
            }

            try {
                $sched = new \DateTimeImmutable($schedDate);
            } catch (\Throwable $e) {
                continue;
            }

            // Weekly count (last 6 days, not including target date)
            if ($sched >= $weekStart && $sched < $today) {
                $weeklyRackCount[$wid] = ($weeklyRackCount[$wid] ?? 0) + 1;
            }

            // Yesterday count
            if ($schedDate === $yesterday) {
                $yesterdayCount[$wid] = ($yesterdayCount[$wid] ?? 0) + 1;
            }

            // Completion time (all historical done tasks)
            if (in_array($task['status'] ?? '', ['done', 'completed'], true)) {
                $createdAt = (int) ($task['created_at'] ?? 0);
                $completedAt = (int) ($task['completed_at'] ?? 0);
                if ($createdAt > 0 && $completedAt > $createdAt) {
                    $minutes = ($completedAt - $createdAt) / 60;
                    if ($minutes > 0 && $minutes < 1440) {
                        $completionTimes[$wid][] = $minutes;
                    }
                }
            }

            // Recheck scores
            $pts = $task['recheck_points'] ?? null;
            if ($pts !== null) {
                $recheckScores[$wid][] = (int) $pts;
            }
        }

        $this->rackBalancingCache = [
            'target_date' => $targetDate,
            'weekly_rack_count' => $weeklyRackCount,
            'yesterday_count' => $yesterdayCount,
            'completion_times' => $completionTimes,
            'recheck_scores' => $recheckScores,
        ];

        return $this->rackBalancingCache;
    }

    /**
     * Normalize template racks to a unified array format.
     * Supports both new format (racks[]) and old format (rack_id single field).
     *
     * @return array<int, array{id:string,name:string,location:string,barcode_value:string,rack_type:string}>
     */
    public function normalizeTemplateRacks(array $template): array
    {
        if (! empty($template['racks']) && is_array($template['racks'])) {
            return array_values(array_filter($template['racks'], function ($r) {
                return is_array($r) && ((string) ($r['id'] ?? '')) !== '';
            }));
        }

        $rid = (string) ($template['rack_id'] ?? '');
        if ($rid === '') {
            return [];
        }

        return [[
            'id' => $rid,
            'name' => (string) ($template['rack_name'] ?? ''),
            'location' => (string) ($template['rack_location'] ?? ''),
            'barcode_value' => (string) ($template['rack_barcode_value'] ?? ''),
            'rack_type' => (string) ($template['rack_type'] ?? 'storage'),
        ]];
    }

    /**
     * @param array|null $template Optional template with full_shift_daily_cap / partial_shift_daily_cap overrides.
     */
    public function getRackCheckDailyCap(string $waiterId, string $date, ?array $template = null): int
    {
        // Quick early-out: not working today = no rack tasks
        if (! $this->shift->isWorkingDay($waiterId, $date)) {
            return 0;
        }

        // Resolve custom caps from template (wizard setting), fallback ke hardcoded.
        $fullCap = null;
        $partialCap = null;
        if ($template !== null) {
            if (array_key_exists('full_shift_daily_cap', $template)) {
                $v = $template['full_shift_daily_cap'];
                if ($v !== null && $v !== '') {
                    $fullCap = max(0, (int) $v);
                }
            }
            if (array_key_exists('partial_shift_daily_cap', $template)) {
                $v = $template['partial_shift_daily_cap'];
                if ($v !== null && $v !== '') {
                    $partialCap = max(0, (int) $v);
                }
            }
        }

        try {
            $shift = $this->shift->getWaiterShiftForDate($waiterId, $date);
            if (! $shift || empty($shift['clock_in_time']) || empty($shift['clock_out_time'])) {
                // unknown shift: gunakan partial cap jika ada, else 1
                return $partialCap ?? 1;
            }

            // Compute shift duration in hours
            $start = strtotime($date . ' ' . $shift['clock_in_time']);
            $end = strtotime($date . ' ' . $shift['clock_out_time']);
            if ($end <= $start) {
                $end += 86400; // overnight
            }
            $durationHours = ($end - $start) / 3600.0;

            if ($durationHours >= 12) {
                return $fullCap ?? 2; // full shift: custom cap atau default 2
            }
            return $partialCap ?? 1; // partial shift: custom cap atau default 1
        } catch (\Throwable $e) {
            return $partialCap ?? 1;
        }
    }

    /**
     * Calculate AI balancing score for a waiter.
     * Higher score = higher priority to receive next rack_check task.
     *
     * Weights: balance=50%, quality=30%, speed=10%, recent=10%
     */
    private function calculateRackBalancingScore(string $waiterId, string $targetDate, array $todayAssignmentCount): float
    {
        $data = $this->getRackBalancingHistoricalData($targetDate);

        $weeklyRackCount = $data['weekly_rack_count'];
        $yesterdayCount = $data['yesterday_count'];
        $completionTimes = $data['completion_times'];
        $recheckScores = $data['recheck_scores'];

        // Include today's already-assigned count in weekly total
        $todayCount = $todayAssignmentCount[$waiterId] ?? 0;
        $waiterWeekly = ($weeklyRackCount[$waiterId] ?? 0) + $todayCount;

        // Calculate average weekly across all waiters that have any data
        $allWeekly = [];
        foreach ($todayAssignmentCount as $wid => $cnt) {
            $allWeekly[$wid] = ($weeklyRackCount[$wid] ?? 0) + $cnt;
        }
        // Include waiters with historical data but no today assignment
        foreach ($weeklyRackCount as $wid => $cnt) {
            if (! isset($allWeekly[$wid])) {
                $allWeekly[$wid] = $cnt;
            }
        }
        $avgWeekly = count($allWeekly) > 0 ? array_sum($allWeekly) / count($allWeekly) : 0;

        // Factor 1: Weekly balance (0-50) — fairness utama
        $balanceScore = min(50.0, max(0.0, ($avgWeekly - $waiterWeekly) * 12));

        // BUG FIX (#16): New waiter warm-up damping.
        // New waiter has 0 history -> avgWeekly delta is huge -> they'd get max
        // balance score and immediately receive most tasks (overload from day 1).
        // Damping factor (days_since_created / 7) clamped to [0.14, 1.0] gives
        // gradual ramp-up: day 1 = 14% balance, day 7 = 100%.
        try {
            $waiter = $this->firebase->getWaiterById($waiterId);
            $createdAt = (int) ($waiter['created_at'] ?? 0);
            if ($createdAt > 0) {
                $daysSinceCreated = max(1, (int) floor((time() - $createdAt) / 86400));
                if ($daysSinceCreated < 7) {
                    $dampingFactor = max(0.14, $daysSinceCreated / 7);
                    $balanceScore *= $dampingFactor;
                }
            }
        } catch (\Throwable $e) {
            // Fail open: skip damping if waiter lookup fails
        }

        // Penalty: strong diminishing return for each task already assigned TODAY
        // This ensures even distribution within a single day
        // Value calibrated so that after 1 assignment, score drops below most others' initial score
        $todayPenalty = $todayCount * 35.0;

        // Factor 2: Quality / recheck score (0-30)
        $waiterRecheckScores = $recheckScores[$waiterId] ?? [];
        $avgRecheck = ! empty($waiterRecheckScores)
            ? array_sum($waiterRecheckScores) / count($waiterRecheckScores)
            : 5.0; // default neutral
        $qualityScore = ($avgRecheck / 10) * 30;

        // Factor 3: Speed (0-10) — tiebreaker only
        $waiterTimes = $completionTimes[$waiterId] ?? [];
        $avgTime = ! empty($waiterTimes)
            ? array_sum($waiterTimes) / count($waiterTimes)
            : 300.0; // default neutral
        $speedScore = min(10.0, max(0.0, (480 - $avgTime) / 48));

        // Factor 4: Yesterday load (0-10) — anti burnout
        $waiterYesterday = $yesterdayCount[$waiterId] ?? 0;
        $recentScore = min(10.0, max(0.0, (3 - $waiterYesterday) * 5));

        return max(0.0, $balanceScore + $qualityScore + $speedScore + $recentScore - $todayPenalty);
    }

    public function getRackCheckOverflows(?string $date = null, string $status = 'pending'): array
    {
        try {
            $rows = $this->database->getReference('rack_check_overflows')->getValue();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $id => $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($date !== null && (string) ($row['target_date'] ?? '') !== $date) {
                continue;
            }
            if ($status !== '' && (string) ($row['status'] ?? '') !== $status) {
                continue;
            }
            $row['id'] = (string) ($row['id'] ?? $id);
            $items[] = $row;
        }

        usort($items, fn ($a, $b) => ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0)));

        return $items;
    }

    protected function getPendingRackCheckOverflowId(string $templateId, string $rackId, string $date): ?string
    {
        foreach ($this->getRackCheckOverflows($date, 'pending') as $overflow) {
            if ((string) ($overflow['template_id'] ?? '') === $templateId && (string) ($overflow['rack_id'] ?? '') === $rackId) {
                return (string) ($overflow['id'] ?? '');
            }
        }

        return null;
    }

    public function createRackCheckOverflow(array $template, string $targetDate, string $reason, array $evaluation = [], ?string $rackId = null): ?string
    {
        $templateId = (string) ($template['id'] ?? '');
        $rackId = (string) ($rackId ?? ($template['rack_id'] ?? ''));
        if ($templateId === '' || $targetDate === '' || $rackId === '') {
            return null;
        }

        $now = time();
        $existingId = $this->getPendingRackCheckOverflowId($templateId, $rackId, $targetDate);
        $payload = [
            'template_id' => $templateId,
            'template_title' => (string) ($template['title'] ?? $template['name'] ?? 'Cek Rak'),
            'rack_id' => $rackId,
            'rack_name' => (string) ($template['rack_name'] ?? $rackId),
            'target_date' => $targetDate,
            'status' => 'pending',
            'reason' => $reason,
            'mode' => (string) ($template['assignment_strategy'] ?? 'simple_lowest_load'),
            'evaluated_candidates' => $evaluation['evaluated'] ?? [],
            'rejected_candidates' => $evaluation['rejected_candidates'] ?? [],
            'updated_at' => $now,
        ];

        try {
            if ($existingId !== null && $existingId !== '') {
                $this->database->getReference('rack_check_overflows/'.$existingId)->update($payload);
                $overflowId = $existingId;
                $isNew = false;
            } else {
                $ref = $this->database->getReference('rack_check_overflows')->push(array_merge($payload, ['created_at' => $now]));
                $overflowId = (string) $ref->getKey();
                $this->database->getReference('rack_check_overflows/'.$overflowId)->update(['id' => $overflowId]);
                $isNew = true;
            }

            $this->firebase->writeSimpleLowestLoadLock($templateId, $targetDate, [
                'status' => 'skipped_no_eligible_waiter',
                'reason' => $reason,
                'overflow_id' => $overflowId,
                'overflow_status' => 'pending',
                'evaluated_candidates' => $payload['evaluated_candidates'],
                'rejected_candidates' => $payload['rejected_candidates'],
            ], $rackId);

            if ($isNew) {
                try {
                    app(FonnteService::class)->notifyRackCheckOverflow(array_merge($payload, ['id' => $overflowId]));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $overflowId;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function moveRackCheckOverflow(string $overflowId, string $newDate, string $status, ?string $note = null): array
    {
        $overflow = (array) ($this->database->getReference('rack_check_overflows/'.$overflowId)->getValue() ?? []);
        if (empty($overflow) || (string) ($overflow['status'] ?? '') !== 'pending') {
            return ['success' => false, 'message' => 'Overflow tidak ditemukan atau sudah diproses.'];
        }
        $template = $this->firebase->getRecurringWaiterTaskTemplateById((string) ($overflow['template_id'] ?? ''));
        if (! $template) {
            return ['success' => false, 'message' => 'Template overflow tidak ditemukan.'];
        }
        $template = array_merge($template, [
            'rack_id' => (string) ($overflow['rack_id'] ?? ''),
            'rack_name' => (string) ($overflow['rack_name'] ?? ''),
        ]);
        $newId = $this->createRackCheckOverflow($template, $newDate, (string) ($overflow['reason'] ?? 'no_eligible_waiter'), [], (string) ($overflow['rack_id'] ?? ''));
        $this->database->getReference('rack_check_overflows/'.$overflowId)->update([
            'status' => $status,
            'moved_to_date' => $newDate,
            'supervisor_note' => $note,
            'updated_at' => time(),
        ]);
        $this->firebase->logAuditAction('move', 'rack_check_overflow', $overflowId, [
            'new_overflow_id' => $newId,
            'new_date' => $newDate,
            'status' => $status,
            'note' => $note,
        ]);

        return ['success' => true, 'message' => 'Overflow berhasil dipindahkan.', 'overflow_id' => $newId];
    }

    /**
     * Count rack_check tasks for a waiter on a specific date (any status except cancelled).
     */
    public function countRackCheckTasksForWaiterOnDate(string $waiterId, string $date): int
    {
        if ($waiterId === '' || $date === '') {
            return 0;
        }
        try {
            $tasks = $this->firebase->getWaiterTasksForDate($waiterId, $date);
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }

        $count = 0;
        foreach ($tasks as $task) {
            if (($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if ((string) ($task['status'] ?? '') === 'cancelled') {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Count rack_check tasks for a waiter between two dates inclusive.
     */
    public function countRackCheckTasksForWaiterBetweenDates(string $waiterId, string $startDate, string $endDate): int
    {
        if ($waiterId === '' || $startDate === '' || $endDate === '') {
            return 0;
        }
        try {
            $allTasks = $this->firebase->getWaiterTasksByWaiterId($waiterId, $startDate, $endDate);
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }

        $count = 0;
        foreach ($allTasks as $task) {
            if (($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if ((string) ($task['status'] ?? '') === 'cancelled') {
                continue;
            }
            $taskDate = (string) ($task['scheduled_for_date'] ?? '');
            if ($taskDate === '') {
                $createdAt = (int) ($task['created_at'] ?? 0);
                if ($createdAt > 0) {
                    $taskDate = date('Y-m-d', $createdAt);
                }
            }
            if ($taskDate === '' || $taskDate < $startDate || $taskDate > $endDate) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Get last rack_check task created_at for a waiter (Unix timestamp).
     * Used as tie-breaker untuk lowest_load sort.
     */
    public function getLastRackCheckAssignedAt(string $waiterId): int
    {
        if ($waiterId === '') {
            return 0;
        }
        try {
            $tasks = $this->firebase->getWaiterTasksByWaiterId($waiterId);
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }

        $latest = 0;
        foreach ($tasks as $task) {
            if (($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            $createdAt = (int) ($task['created_at'] ?? 0);
            if ($createdAt > $latest) {
                $latest = $createdAt;
            }
        }
        return $latest;
    }

    /**
     * Get all racks due for checking on a specific date based on active templates.
     *
     * @param  string  $date  Format: YYYY-MM-DD
     * @return array  Array of rack items with template context and lock status
     */
    public function getRacksDueForDateFromTemplates(string $date): array
    {
        $result      = [];
        $dateCompact = str_replace('-', '', $date);

        try {
            $templates = $this->firebase->getRecurringWaiterTaskTemplates();
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] getRacksDueForDateFromTemplates: templates fetch failed', [
                'date'  => $date,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        foreach ($templates as $template) {
            if (! is_array($template)) {
                continue;
            }
            if (($template['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (! ($template['is_active'] ?? false)) {
                continue;
            }

            try {
                $isDue = $this->firebase->isTemplateDueForDate($template, $date);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $isDue) {
                continue;
            }

            try {
                $racks = $this->normalizeTemplateRacks($template);
            } catch (\Throwable $e) {
                continue;
            }

            $templateId    = (string) ($template['id'] ?? '');
            $templateTitle = (string) ($template['title'] ?? '');
            $scheduleTime  = (string) ($template['schedule_time'] ?? '');

            // Batch lock read: 1 Firebase read per template instead of N per rack
            $lockSnapshot = null;
            try {
                $lockBasePath = 'waiter_task_generation_locks/' . $templateId;
                $lockSnapshot = $this->database->getReference($lockBasePath)->getSnapshot();
            } catch (\Throwable $e) {
                // treat all racks as not locked on error
            }

            foreach ($racks as $rack) {
                if (! is_array($rack)) {
                    continue;
                }

                $rackId = (string) ($rack['rack_id'] ?? $rack['id'] ?? '');

                $alreadyExists = false;
                if ($lockSnapshot && $rackId) {
                    $alreadyExists = $lockSnapshot->hasChild($rackId . '/' . $dateCompact);
                }

                $result[] = [
                    'template_id'    => $templateId,
                    'template_title' => $templateTitle,
                    'rack_id'        => $rackId,
                    'rack_code'      => (string) ($rack['rack_code'] ?? $rack['code'] ?? ''),
                    'rack_name'      => (string) ($rack['rack_name'] ?? $rack['name'] ?? ''),
                    'schedule_time'  => $scheduleTime,
                    'already_exists' => $alreadyExists,
                ];
            }
        }

        return $result;
    }

    /**
     * Lightweight count of racks due for a date (no lock check, no detail).
     * Use for reminder/notification that only needs total count.
     */
    public function countRacksDueForDate(string $date): int
    {
        $count = 0;

        try {
            $templates = $this->firebase->getRecurringWaiterTaskTemplates();
        } catch (\Throwable $e) {
            return 0;
        }

        foreach ($templates as $template) {
            if (! is_array($template)) {
                continue;
            }
            if (($template['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (! ($template['is_active'] ?? false)) {
                continue;
            }

            try {
                if (! $this->firebase->isTemplateDueForDate($template, $date)) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            try {
                $racks = $this->normalizeTemplateRacks($template);
                $count += count($racks);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $count;
    }
}
