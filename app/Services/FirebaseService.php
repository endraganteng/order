<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Exception\Database\TransactionFailed;
use RuntimeException;

class FirebaseService
{
    protected $database;

    protected $auth;

    /**
     * Per-request memo cache untuk hemat Firebase download.
     * Cleared otomatis di akhir request (PHP-FPM lifecycle).
     * Key: cache identifier, Value: any.
     */
    protected array $requestCache = [];

    /**
     * Capture-bag untuk restock_request yg ter-create otomatis dalam transaksi top-level
     * (e.g. submitStandaloneStockTake). Caller reset di awal, baca di akhir untuk expose
     * ke response API. Internal struct: [{product_id, product_name, source, qty_needed,
     * rack_id, rack_name, rack_type, restock_request_id}, ...]
     */
    protected array $lastCreatedRestocks = [];

    public function __construct(Database $database, Auth $auth)
    {
        $this->database = $database;
        $this->auth = $auth;
    }

    /**
     * Bust per-request cache. Call setelah write yang invalidate cache.
     */
    public function bustRequestCache(?string $key = null): void
    {
        if ($key === null) {
            $this->requestCache = [];
        } else {
            unset($this->requestCache[$key]);
        }
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
     * Build per-product live stock snapshot for one rack from latest rack_check records.
     *
     * @param  array<int, array<string, mixed>>|null  $rackProducts
     * @return array<string, array<string, mixed>>
     */
    public function getRackProductLiveStock(string $rackId, ?array $rackProducts = null): array
    {
        $assignedProducts = is_array($rackProducts) ? $rackProducts : $this->getRackProducts($rackId);
        $assignedMap = [];
        foreach ($assignedProducts as $product) {
            $productId = trim((string) ($product['id'] ?? ''));
            if ($productId === '') {
                continue;
            }
            $assignedMap[$productId] = $product;
        }

        $liveMap = [];
        foreach (array_keys($assignedMap) as $productId) {
            $storedCurrentQty = array_key_exists('current_qty', $assignedMap[$productId]) && $assignedMap[$productId]['current_qty'] !== null
                ? max(0, (int) $assignedMap[$productId]['current_qty'])
                : null;

            if ($storedCurrentQty !== null) {
                $standardQty = max(0, (int) ($assignedMap[$productId]['standard_qty'] ?? 0));
                $liveMap[$productId] = [
                    'product_id' => $productId,
                    'current_qty' => $storedCurrentQty,
                    'last_updated_at' => isset($assignedMap[$productId]['last_updated_at']) ? (int) $assignedMap[$productId]['last_updated_at'] : (isset($assignedMap[$productId]['updated_at']) ? (int) $assignedMap[$productId]['updated_at'] : null),
                    'is_shortage' => $standardQty > 0 ? $storedCurrentQty < $standardQty : false,
                ];
                continue;
            }

            $liveMap[$productId] = [
                'product_id' => $productId,
                'current_qty' => null,
                'last_updated_at' => null,
                'is_shortage' => null,
            ];
        }

        if (count($assignedMap) === 0) {
            return $liveMap;
        }

        $history = $this->getRackCheckHistory($rackId, 300);
        foreach ($history as $task) {
            $completedAt = $this->normalizeUnixTimestampToSeconds((int) ($task['completed_at'] ?? 0));
            $checklist = $task['completed_product_checklist'] ?? [];
            if (! is_array($checklist) || count($checklist) === 0) {
                continue;
            }

            foreach ($checklist as $checklistKey => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $productId = trim((string) ($item['product_id'] ?? $checklistKey));
                if ($productId === '' || ! isset($assignedMap[$productId])) {
                    continue;
                }

                if (($liveMap[$productId]['last_updated_at'] ?? null) !== null) {
                    continue;
                }

                $currentQty = max(0, (int) ($item['actual_qty'] ?? 0));
                $standardQty = max(0, (int) ($item['standard_qty'] ?? ($assignedMap[$productId]['standard_qty'] ?? 0)));
                $liveMap[$productId] = [
                    'product_id' => $productId,
                    'current_qty' => $currentQty,
                    'last_updated_at' => $completedAt > 0 ? $completedAt : null,
                    'is_shortage' => $standardQty > 0 ? $currentQty < $standardQty : false,
                ];
            }
        }

        return $liveMap;
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
            $completedAt = $this->normalizeUnixTimestampToSeconds((int) ($task['completed_at'] ?? 0));
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

    protected function normalizeUnixTimestampToSeconds(int $timestamp): int
    {
        if ($timestamp <= 0) {
            return 0;
        }

        // Compatibility for legacy millisecond timestamps.
        if ($timestamp > 1000000000000) {
            return (int) floor($timestamp / 1000);
        }

        return $timestamp;
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

            if (count($this->getRackProducts($rackId)) > 0) {
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

        $rackProducts = $this->getRackProducts($rackId);
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
     * Resolve storage rack by barcode and return assigned products.
     *
     * @return array<string,mixed>
     */
    public function getStorageRackProductsByBarcode(string $barcodeValue): array
    {
        $rack = $this->resolveStorageRackByBarcode($barcodeValue);
        if (! $rack) {
            return [
                'success' => false,
                'message' => 'Rak tidak ditemukan atau tidak aktif.',
            ];
        }

        $rackId = trim((string) ($rack['id'] ?? ''));
        $rackType = 'storage';

        return [
            'success' => true,
            'rack' => [
                'id' => $rackId,
                'name' => (string) ($rack['name'] ?? ''),
                'barcode_value' => strtoupper(trim((string) ($rack['barcode_value'] ?? ''))),
                'rack_type' => $rackType,
                'location' => (string) ($rack['location'] ?? ''),
            ],
            'products' => $this->getRackProducts($rackId),
        ];
    }

    /**
     * Resolve storage rack by barcode and return active product list.
     *
     * @return array<string, mixed>
     */
    public function getStorageRackByBarcode(string $barcodeValue): array
    {
        $barcode = strtoupper(trim($barcodeValue));
        if ($barcode === '') {
            return ['success' => false, 'message' => 'Barcode rak wajib diisi.'];
        }

        $rack = $this->findActiveRackByBarcode($barcode);
        if (! $rack) {
            return ['success' => false, 'message' => 'Rak tidak ditemukan atau tidak aktif.'];
        }

        $rackType = trim((string) ($rack['rack_type'] ?? 'storage'));
        if ($rackType !== 'storage') {
            return ['success' => false, 'message' => 'Rak ini bukan tipe storage/gudang.'];
        }

        $rackId = trim((string) ($rack['id'] ?? ''));

        return [
            'success' => true,
            'rack' => [
                'id' => $rackId,
                'name' => (string) ($rack['name'] ?? ''),
                'location' => (string) ($rack['location'] ?? ''),
                'barcode_value' => (string) ($rack['barcode_value'] ?? ''),
                'rack_type' => $rackType,
            ],
            'products' => $this->getRackProducts($rackId),
        ];
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
                if ($productId === '') {
                    continue;
                }

                $masterProduct = $masterMap[$productId] ?? [];
                $fallbackName = trim((string) ($assignment['product_name'] ?? ''));
                $fallbackUnit = trim((string) ($assignment['product_unit'] ?? 'pcs'));
                $products[] = [
                    'id' => $productId,
                    'name' => (string) ($masterProduct['name'] ?? ($fallbackName !== '' ? $fallbackName : ('Produk #'.$productId))),
                    'standard_qty' => isset($assignment['standard_qty'])
                        ? max(0, (int) $assignment['standard_qty'])
                        : max(0, (int) ($masterProduct['standard_qty'] ?? 0)),
                    'min_qty' => max(0, (int) ($assignment['min_qty'] ?? 0)),
                    'current_qty' => array_key_exists('current_qty', $assignment) && $assignment['current_qty'] !== null
                        ? max(0, (int) $assignment['current_qty'])
                        : null,
                    'last_updated_at' => $assignment['last_updated_at'] ?? ($assignment['updated_at'] ?? null),
                    'unit' => (string) ($masterProduct['unit'] ?? ($fallbackUnit !== '' ? $fallbackUnit : 'pcs')),
                    'is_active' => ! isset($masterProduct['is_active']) || ($masterProduct['is_active'] !== false),
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
                'current_qty' => array_key_exists('current_qty', $existingProducts[$productId] ?? []) && $existingProducts[$productId]['current_qty'] !== null
                    ? max(0, (int) $existingProducts[$productId]['current_qty'])
                    : null,
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
                    'current_qty' => array_key_exists('current_qty', $existingProducts[$productId] ?? []) && $existingProducts[$productId]['current_qty'] !== null
                        ? max(0, (int) $existingProducts[$productId]['current_qty'])
                        : null,
                    'assigned_at' => $existingProducts[$productId]['assigned_at'] ?? $now,
                    'updated_at' => $now,
                ];
            }

            $reference->set($payload);
        }
    }

    /**
     * Additively assign ONE product to a rack without overwriting other entries.
     * Path-level set so we never replace the whole `products` node.
     *
     * @return array{success:bool,message:string,product?:array}
     */
    public function addSingleProductToRack(string $rackId, string $productId, ?int $standardQty = null, int $minQty = 0): array
    {
        $rackId = trim($rackId);
        $productId = trim($productId);
        if ($rackId === '' || $productId === '') {
            return ['success' => false, 'message' => 'Rak atau produk tidak valid.'];
        }

        $masterProduct = $this->getProductById($productId);
        if (! $masterProduct) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan di master.'];
        }

        $existingRef = $this->database->getReference('waiter_racks/' . $rackId . '/products/' . $productId);
        $existingSnap = $existingRef->getSnapshot();
        $existing = $existingSnap->exists() ? (array) $existingSnap->getValue() : [];

        $now = time();
        $resolvedStandard = $standardQty !== null
            ? max(0, (int) $standardQty)
            : max(0, (int) ($existing['standard_qty'] ?? $masterProduct['standard_qty'] ?? 0));
        $resolvedMin = max(0, (int) ($minQty ?? $existing['min_qty'] ?? 0));

        $payload = [
            'product_id'   => $productId,
            'standard_qty' => $resolvedStandard,
            'min_qty'      => $resolvedMin,
            'current_qty'  => array_key_exists('current_qty', $existing) && $existing['current_qty'] !== null
                ? max(0, (int) $existing['current_qty'])
                : null,
            'assigned_at'  => $existing['assigned_at'] ?? $now,
            'updated_at'   => $now,
        ];

        $existingRef->set($payload);

        return [
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke rak.',
            'product' => [
                'id'           => $productId,
                'name'         => (string) ($masterProduct['name'] ?? ('Produk #' . $productId)),
                'unit'         => (string) ($masterProduct['unit'] ?? 'pcs'),
                'standard_qty' => $resolvedStandard,
                'min_qty'      => $resolvedMin,
                'current_qty'  => $payload['current_qty'],
                'is_active'    => ! isset($masterProduct['is_active']) || ($masterProduct['is_active'] !== false),
                'assigned_at'  => $payload['assigned_at'],
                'updated_at'   => $payload['updated_at'],
            ],
        ];
    }

    /**
     * Search master products by name/barcode, with optional exclusion of already-assigned IDs.
     *
     * @param array<int,string> $excludeIds
     * @return array<int,array>
     */
    public function searchMasterProducts(string $query, array $excludeIds = [], int $limit = 30): array
    {
        $query = trim($query);
        $excludeMap = [];
        foreach ($excludeIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $excludeMap[$id] = true;
            }
        }

        $results = [];
        foreach ($this->getProducts() as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = (string) ($p['id'] ?? '');
            if ($id === '' || isset($excludeMap[$id])) {
                continue;
            }
            if (isset($p['is_active']) && $p['is_active'] === false) {
                continue;
            }
            if ($query !== '') {
                $name = (string) ($p['name'] ?? '');
                $barcode = (string) ($p['barcode'] ?? '');
                if (stripos($name, $query) === false && stripos($barcode, $query) === false) {
                    continue;
                }
            }
            $results[] = [
                'id'           => $id,
                'name'         => (string) ($p['name'] ?? '-'),
                'unit'         => (string) ($p['unit'] ?? 'pcs'),
                'barcode'      => (string) ($p['barcode'] ?? ''),
                'standard_qty' => (int) ($p['standard_qty'] ?? 0),
                'category_name' => (string) ($p['category_name'] ?? ''),
            ];
            if (count($results) >= max(1, $limit)) {
                break;
            }
        }

        usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $results;
    }

    /**
     * Get warehouse stock availability for a list of product IDs.
     * Returns map: { productId => { total_qty, racks: [{rack_id, rack_name, qty}], status } }
     * Status values: 'available' (total>0), 'empty' (registered in storage but qty=0), 'missing' (not in any storage rack).
     *
     * @param array<int,string> $productIds
     * @return array<string,array>
     */
    public function getStorageInfoForProducts(array $productIds): array
    {
        $cleanIds = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $cleanIds[$id] = true;
            }
        }
        if (empty($cleanIds)) {
            return [];
        }

        $info = [];
        foreach (array_keys($cleanIds) as $pid) {
            $info[$pid] = [
                'total_qty' => 0,
                'racks' => [],
                'status' => 'missing',
            ];
        }

        foreach ($this->getActiveRacks() as $rack) {
            $rackType = (string) ($rack['rack_type'] ?? 'storage');
            if ($rackType !== 'storage') {
                continue;
            }
            $rackId = (string) ($rack['id'] ?? '');
            $rackName = trim((string) ($rack['name'] ?? ($rack['rack_name'] ?? '')));
            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts)) {
                continue;
            }

            foreach (array_keys($cleanIds) as $pid) {
                if (! array_key_exists($pid, $rackProducts)) {
                    continue;
                }
                $rp = $rackProducts[$pid];
                $qty = is_array($rp) ? max(0, (int) ($rp['current_qty'] ?? 0)) : 0;
                $info[$pid]['racks'][] = [
                    'rack_id' => $rackId,
                    'rack_name' => $rackName !== '' ? $rackName : $rackId,
                    'qty' => $qty,
                ];
                $info[$pid]['total_qty'] += $qty;
                // If we found product in any storage rack, upgrade status from 'missing' to 'empty' or 'available'
                if ($info[$pid]['status'] === 'missing') {
                    $info[$pid]['status'] = 'empty';
                }
            }
        }

        foreach ($info as $pid => $data) {
            if ($data['total_qty'] > 0) {
                $info[$pid]['status'] = 'available';
            }
            // Sort racks by qty desc so the most-stocked rack appears first
            usort($info[$pid]['racks'], fn($a, $b) => $b['qty'] <=> $a['qty']);
        }

        return $info;
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
                if ($productId === '') {
                    continue;
                }

                $masterProduct = $masterMap[$productId] ?? [];
                if (isset($masterProduct['is_active']) && ($masterProduct['is_active'] === false)) {
                    continue;
                }

                $fallbackName = trim((string) ($assignment['product_name'] ?? ''));
                $fallbackUnit = trim((string) ($assignment['product_unit'] ?? 'pcs'));

                $products[] = [
                    'id' => $productId,
                    'name' => (string) ($masterProduct['name'] ?? ($fallbackName !== '' ? $fallbackName : ('Produk #'.$productId))),
                    'standard_qty' => isset($assignment['standard_qty'])
                        ? max(0, (int) $assignment['standard_qty'])
                        : max(0, (int) ($masterProduct['standard_qty'] ?? 0)),
                    'min_qty' => max(0, (int) ($assignment['min_qty'] ?? 0)),
                    'current_qty' => array_key_exists('current_qty', $assignment) && $assignment['current_qty'] !== null
                        ? max(0, (int) $assignment['current_qty'])
                        : null,
                    'last_updated_at' => $assignment['last_updated_at'] ?? ($assignment['updated_at'] ?? null),
                    'unit' => (string) ($masterProduct['unit'] ?? ($fallbackUnit !== '' ? $fallbackUnit : 'pcs')),
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
     * Get total current qty for one product across all active storage racks.
     */
    public function getTotalStorageQtyForProduct(string $productId): int
    {
        $productId = trim($productId);
        if ($productId === '') {
            return 0;
        }

        $total = 0;
        foreach ($this->getActiveRacks() as $rack) {
            $rackType = (string) ($rack['rack_type'] ?? 'storage');
            if ($rackType !== 'storage') {
                continue;
            }

            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts) || ! array_key_exists($productId, $rackProducts)) {
                continue;
            }

            $currentQty = $rackProducts[$productId]['current_qty'] ?? 0;
            $total += max(0, (int) $currentQty);
        }

        return $total;
    }

    /**
     * Get detailed stock summary untuk produk: total per type + breakdown per rak.
     *
     * @return array{
     *   total_storage: int,        // Stok di gudang
     *   total_display: int,        // Stok di rak jualan
     *   total_all: int,            // Total keseluruhan
     *   by_rack: array<int, array{rack_id: string, rack_name: string, rack_type: string, current_qty: int, standard_qty: int}>,
     * }
     */
    public function getProductStockSummary(string $productId): array
    {
        $productId = trim($productId);
        $result = [
            'total_storage' => 0,
            'total_display' => 0,
            'total_all' => 0,
            'by_rack' => [],
        ];
        if ($productId === '') {
            return $result;
        }

        foreach ($this->getActiveRacks() as $rack) {
            $rackId = trim((string) ($rack['id'] ?? ''));
            if ($rackId === '') {
                continue;
            }
            $rackType = (string) ($rack['rack_type'] ?? 'storage');
            $rackName = (string) ($rack['name'] ?? '-');

            $rackProducts = $rack['products'] ?? [];
            if (! is_array($rackProducts) || ! array_key_exists($productId, $rackProducts)) {
                continue;
            }

            $rp = (array) $rackProducts[$productId];
            $currentQty = max(0, (int) ($rp['current_qty'] ?? 0));
            $standardQty = max(0, (int) ($rp['standard_qty'] ?? 0));

            if ($currentQty === 0 && $standardQty === 0) {
                continue;
            }

            $result['by_rack'][] = [
                'rack_id' => $rackId,
                'rack_name' => $rackName,
                'rack_type' => $rackType,
                'current_qty' => $currentQty,
                'standard_qty' => $standardQty,
            ];

            if ($rackType === 'storage') {
                $result['total_storage'] += $currentQty;
            } else {
                $result['total_display'] += $currentQty;
            }
            $result['total_all'] += $currentQty;
        }

        // Sort: storage racks dulu, lalu display, alphabetic dalam grup
        usort($result['by_rack'], function ($a, $b) {
            if ($a['rack_type'] !== $b['rack_type']) {
                return $a['rack_type'] === 'storage' ? -1 : 1;
            }

            return strcasecmp($a['rack_name'], $b['rack_name']);
        });

        return $result;
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
        $taskTypeForResolve = (string) ($data['task_type'] ?? 'general');
        $targetWaiters = $this->resolveTargetWaiters($assignmentType, $assignedWaiterId, $assignedWaiterRole, $selectedWaiterIds, $taskTypeForResolve);
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

    public function getWaiterTaskById(string $taskId): ?array
    {
        $snapshot = $this->database->getReference('waiter_tasks/'.$taskId)->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }

        return array_merge(['id' => $taskId], $snapshot->getValue());
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
     * @return array
     */
    public function getRackCheckPendingReview(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $tasks = $this->getWaiterTasksByDate($date);
        $pending = array_values(array_filter($tasks, function ($t) {
            return ($t['task_type'] ?? '') === 'rack_check'
                && ($t['status'] ?? '') === 'done'
                && ! empty($t['recheck_pending']);
        }));
        usort($pending, function ($a, $b) {
            return ($b['completed_at'] ?? 0) - ($a['completed_at'] ?? 0);
        });
        return $pending;
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
        $photoBeforeDataUrl = null,
        ?string $idempotencyKey = null
    ) {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey !== '') {
            $idempotencySnapshot = $this->database->getReference('waiter_task_idempotency/'.$idempotencyKey)->getSnapshot();
            if ($idempotencySnapshot->exists()) {
                $stored = $idempotencySnapshot->getValue();
                if (is_array($stored) && isset($stored['response']) && is_array($stored['response'])) {
                    return $stored['response'];
                }
            }
        }

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
        $assignmentType = (string) ($task['assignment_type'] ?? 'single');
        if ($assignmentType !== 'single') {
            $claimedBy = trim((string) ($task['claimed_by'] ?? ''));
            $claimExpiresAt = (int) ($task['claim_expires_at'] ?? 0);
            $claimStillValid = $claimedBy !== '' && $claimExpiresAt > $now;
            if ($claimStillValid && $claimedBy !== (string) $waiterId) {
                return [
                    'success' => false,
                    'message' => 'Tugas sedang dikerjakan oleh '.((string) ($task['claimed_by_name'] ?? 'waiter lain')).'.',
                ];
            }
        }
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
        $stockMovements = [];

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
            // Untuk task rack_check, tandai pending review oleh Finance.
            // Waiter belum dapat poin operasional/recheck sampai Finance review.
            if ($taskType === 'rack_check' && $status === 'done') {
                $updates['recheck_pending'] = true;
                $updates['recheck_points'] = null;
                $updates['recheck_notes'] = null;
                $updates['recheck_by'] = null;
                $updates['recheck_by_name'] = null;
                $updates['recheck_at'] = null;
            }
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

                $stockMovements[] = [
                    'rack_id' => (string) ($task['rack_id'] ?? ''),
                    'product_id' => $productId,
                    'movement_type' => 'stock_take',
                    'source' => 'waiter_task',
                    'task_id' => (string) $taskId,
                    'waiter_id' => (string) $waiterId,
                    'waiter_name' => (string) $waiterName,
                    'product_name' => trim((string) ($checkData['product_name'] ?? '')),
                    'product_unit' => trim((string) ($checkData['product_unit'] ?? 'pcs')),
                    'standard_qty' => $standardQty,
                    'actual_qty' => $actualQty,
                    'note' => trim((string) ($note ?? '')),
                    // P0-3: per-product idempotency_key derived dari task idempotency_key.
                    // Stabil antar retry → recordRackStockMovement bisa pakai cache untuk
                    // produk yang sudah berhasil di attempt sebelumnya.
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey.':sm:'.$productId : '',
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

        // P0-3: ATOMICITY GUARANTEE — shortage signal harus persist sebelum task
        // ditandai 'done'. Urutan write:
        //   STAGE 1: semua stock_movements (atomic CAS per produk + idempotent).
        //   STAGE 2: semua restock_requests (dedup by product+rack+pending).
        //   STAGE 3: barulah update task status.
        // Kalau STAGE 1 atau 2 gagal: ABORT — task tetap pending/in_progress,
        // idempotency_key TIDAK di-cache → waiter retry akan re-process.
        // Stable idempotency keys (per task instance) memastikan retry tidak
        // duplikasi movement/restock yang sudah berhasil.
        foreach ($stockMovements as $movement) {
            $movementResult = $this->recordRackStockMovement($movement);
            if (! ($movementResult['success'] ?? false)) {
                $errMsg = (string) ($movementResult['message'] ?? 'Gagal mencatat movement stok.');
                report(new \RuntimeException(sprintf(
                    '[completeTask P0-3] Stock movement gagal: rack=%s product=%s task=%s err=%s',
                    $movement['rack_id'] ?? '',
                    $movement['product_id'] ?? '',
                    $taskId,
                    $errMsg
                )));

                return [
                    'success' => false,
                    'message' => 'Gagal menyimpan stok produk: '.$errMsg.' Silakan coba lagi.',
                ];
            }
        }

        if ($taskType === 'rack_check' && is_array($productChecklist) && count($productChecklist) > 0) {
            $restockResult = $this->writeRestockRequestsForCompletion(
                (string) $taskId,
                $task,
                $productChecklist,
                (string) $waiterId,
                (string) $waiterName
            );
            if (! ($restockResult['success'] ?? false)) {
                $errMsg = (string) ($restockResult['message'] ?? 'Gagal mencatat restock request.');
                report(new \RuntimeException(sprintf(
                    '[completeTask P0-3] Restock request gagal: task=%s err=%s',
                    $taskId,
                    $errMsg
                )));

                return [
                    'success' => false,
                    'message' => 'Gagal menyimpan permintaan restock: '.$errMsg.' Silakan coba lagi.',
                ];
            }
        }

        // STAGE 3: semua shortage signal aman, sekarang flip status task.
        $taskReference->update($updates);

        $response = null;

        if ($isRepeatTask && ! $isFullyDone) {
            $response = [
                'success' => true,
                'partial' => true,
                'completed_count' => $newCompletedCount,
                'repeat_count' => $repeatCount,
                'message' => "Pengulangan #{$newCompletedCount} dari {$repeatCount} selesai.",
            ];

            if ($idempotencyKey !== '') {
                $this->database->getReference('waiter_task_idempotency/'.$idempotencyKey)->set([
                    'task_id' => (string) $taskId,
                    'response' => $response,
                    'created_at' => $now,
                ]);
            }

            return $response;
        }

        $response = [
            'success' => true,
            'partial' => false,
            'completed_count' => $newCompletedCount,
            'repeat_count' => $repeatCount,
            'message' => 'Tugas berhasil diverifikasi.',
        ];

        if ($idempotencyKey !== '') {
            $this->database->getReference('waiter_task_idempotency/'.$idempotencyKey)->set([
                'task_id' => (string) $taskId,
                'response' => $response,
                'created_at' => $now,
            ]);
        }

        return $response;
    }

    /**
     * Claim a task with expiry window.
     */
    public function claimWaiterTask(string $taskId, string $waiterId, string $waiterName): array
    {
        if ($taskId === '' || $waiterId === '') {
            return ['success' => false, 'message' => 'Data klaim tidak lengkap.'];
        }

        $now = time();
        $claimDuration = 15 * 60;
        $expiresAt = $now + $claimDuration;
        $taskRef = $this->database->getReference('waiter_tasks/'.$taskId);

        $claimResult = ['success' => false, 'message' => 'Gagal klaim tugas.'];

        try {
            $this->database->runTransaction(function ($transaction) use ($taskRef, $waiterId, $waiterName, $now, $expiresAt, &$claimResult) {
                $snap = $transaction->snapshot($taskRef);
                if (! $snap->exists()) {
                    $claimResult = ['success' => false, 'message' => 'Tugas tidak ditemukan.'];
                    return;
                }

                $task = (array) $snap->getValue();
                $assignmentType = (string) ($task['assignment_type'] ?? 'single');
                if ($assignmentType === 'single') {
                    $claimResult = [
                        'success' => true,
                        'message' => 'Task assignment single tidak perlu klaim.',
                        'expires_at' => null,
                        'claimed_by_name' => null,
                    ];
                    return;
                }

                $assignedWaiterId = (string) ($task['assigned_waiter_id'] ?? '');
                if ($assignedWaiterId === '' || $assignedWaiterId !== $waiterId) {
                    $claimResult = ['success' => false, 'message' => 'Tugas ini bukan milik akun waiter Anda.'];
                    return;
                }

                $status = (string) ($task['status'] ?? 'pending');
                if (! in_array($status, ['pending', 'in_progress'], true)) {
                    $claimResult = ['success' => false, 'message' => 'Tugas ini sudah '.$status.'.'];
                    return;
                }

                $existingClaimer = trim((string) ($task['claimed_by'] ?? ''));
                $existingExpiry = (int) ($task['claim_expires_at'] ?? 0);
                if ($existingClaimer !== '' && $existingClaimer !== $waiterId && $existingExpiry > $now) {
                    $claimResult = [
                        'success' => false,
                        'message' => 'Tugas sedang dikerjakan oleh '.((string) ($task['claimed_by_name'] ?? 'waiter lain')).'.',
                        'claimed_by_name' => $task['claimed_by_name'] ?? null,
                        'expires_at' => $existingExpiry,
                    ];
                    return;
                }

                $task['claimed_by'] = $waiterId;
                $task['claimed_by_name'] = $waiterName;
                $task['claimed_at'] = $now;
                $task['claim_expires_at'] = $expiresAt;
                $task['status'] = 'in_progress';
                $transaction->set($taskRef, $task);

                $claimResult = [
                    'success' => true,
                    'message' => 'Tugas berhasil di-klaim.',
                    'expires_at' => $expiresAt,
                    'claimed_by_name' => $waiterName,
                ];
            });
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Konflik klaim. Coba lagi.'];
        }

        return $claimResult;
    }

    public function releaseWaiterTask(string $taskId, string $waiterId): array
    {
        if ($taskId === '' || $waiterId === '') {
            return ['success' => false, 'message' => 'Data pelepasan klaim tidak lengkap.'];
        }

        $taskRef = $this->database->getReference('waiter_tasks/'.$taskId);
        $snapshot = $taskRef->getSnapshot();
        if (! $snapshot->exists()) {
            return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];
        }

        $task = (array) $snapshot->getValue();
        $assignmentType = (string) ($task['assignment_type'] ?? 'single');
        if ($assignmentType === 'single') {
            return ['success' => true, 'message' => 'Task assignment single tidak memakai klaim.'];
        }

        $claimedBy = trim((string) ($task['claimed_by'] ?? ''));
        $claimExpiresAt = (int) ($task['claim_expires_at'] ?? 0);
        $now = time();
        $expired = $claimExpiresAt > 0 && $claimExpiresAt <= $now;

        if ($claimedBy === '') {
            return ['success' => true, 'message' => 'Klaim sudah kosong.'];
        }

        if (! $expired && $claimedBy !== $waiterId) {
            return ['success' => false, 'message' => 'Hanya waiter yang klaim yang bisa melepas klaim.'];
        }

        $updates = [
            'claimed_by' => null,
            'claimed_by_name' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
        ];
        if ((string) ($task['status'] ?? 'pending') === 'in_progress') {
            $updates['status'] = 'pending';
        }

        $taskRef->update($updates);

        return ['success' => true, 'message' => 'Klaim tugas berhasil dilepas.'];
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
            'rolling_enabled' => (bool) ($data['rolling_enabled'] ?? false),
            'rolling_period' => in_array(strtolower((string) ($data['rolling_period'] ?? 'weekly')), ['daily', 'weekly', 'monthly'], true)
                ? strtolower((string) ($data['rolling_period'] ?? 'weekly'))
                : 'weekly',
            'rolling_waiter_ids' => array_values(array_filter(
                array_map('strval', is_array($data['rolling_waiter_ids'] ?? null) ? $data['rolling_waiter_ids'] : []),
                function ($v) {
                    return $v !== '';
                }
            )),
            'rolling_anchor_date' => (string) ($data['rolling_anchor_date'] ?? ''),
            'target_shift_id' => (string) ($data['target_shift_id'] ?? ''),
            'is_active' => true,
            'created_at' => time(),
            'last_generated_date' => null,
        ];

        $this->database->getReference('waiter_task_templates')->push($templateData);
    }

    /**
     * Get all recurring waiter task templates.
     */
    public function getRecurringWaiterTaskTemplates(): array
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

        if (array_key_exists('rolling_enabled', $data)) {
            $updates['rolling_enabled'] = (bool) $data['rolling_enabled'];
        }
        if (array_key_exists('rolling_period', $data)) {
            $rp = strtolower((string) $data['rolling_period']);
            $updates['rolling_period'] = in_array($rp, ['daily', 'weekly', 'monthly'], true) ? $rp : 'weekly';
        }
        if (array_key_exists('rolling_waiter_ids', $data)) {
            $ids = $data['rolling_waiter_ids'];
            if (! is_array($ids)) {
                $ids = [];
            }
            $updates['rolling_waiter_ids'] = array_values(array_filter(
                array_map('strval', $ids),
                function ($v) {
                    return $v !== '';
                }
            ));
        }
        if (array_key_exists('rolling_anchor_date', $data)) {
            $updates['rolling_anchor_date'] = (string) $data['rolling_anchor_date'];
        }
        if (array_key_exists('target_shift_id', $data)) {
            $updates['target_shift_id'] = (string) $data['target_shift_id'];
        }

        $this->database->getReference('waiter_task_templates/'.$id)->update($updates);
    }

    /**
     * Partial schedule update untuk board drag-drop.
     * Hanya mengubah recurrence_type, weekly_day, is_active — tidak menyentuh field lain.
     *
     * @param  string  $id
     * @param  array   $patch  Keys: recurrence_type?, weekly_day?, is_active?
     * @return array   ['success' => bool, 'template' => array|null, 'error' => string|null]
     */
    public function updateRecurringScheduleDays(string $id, array $patch): array
    {
        $existing = $this->getRecurringWaiterTaskTemplateById($id);
        if (! $existing) {
            return ['success' => false, 'template' => null, 'error' => 'Template tidak ditemukan'];
        }

        $allowed = ['daily', 'weekly', 'every_n_days'];
        $allowedAssignment = ['single', 'all', 'role'];
        $allowedRoles = ['kasir', 'pelayan', 'backup', 'finance', 'supervisor'];
        $updates = [];

        if (array_key_exists('recurrence_type', $patch)) {
            $rt = (string) $patch['recurrence_type'];
            if (! in_array($rt, $allowed, true)) {
                return ['success' => false, 'template' => null, 'error' => 'recurrence_type tidak valid'];
            }
            $updates['recurrence_type'] = $rt;
        }

        if (array_key_exists('weekly_day', $patch)) {
            $day = (int) $patch['weekly_day'];
            if ($day < 1 || $day > 7) {
                return ['success' => false, 'template' => null, 'error' => 'weekly_day harus 1-7 (ISO: Senin=1, Minggu=7)'];
            }
            $updates['weekly_day'] = $day;
        }

        if (array_key_exists('interval_days', $patch)) {
            $updates['interval_days'] = max(1, (int) $patch['interval_days']);
        }

        if (array_key_exists('schedule_time', $patch)) {
            $updates['schedule_time'] = (string) $patch['schedule_time'];
        }

        if (array_key_exists('title', $patch)) {
            $title = trim((string) $patch['title']);
            if ($title === '') {
                return ['success' => false, 'template' => null, 'error' => 'title tidak boleh kosong'];
            }
            $updates['title'] = $title;
        }

        if (array_key_exists('assignment_type', $patch)) {
            $at = (string) $patch['assignment_type'];
            if (! in_array($at, $allowedAssignment, true)) {
                return ['success' => false, 'template' => null, 'error' => 'assignment_type tidak valid'];
            }
            $updates['assignment_type'] = $at;
        }

        if (array_key_exists('assigned_waiter_role', $patch)) {
            $role = strtolower(trim((string) $patch['assigned_waiter_role']));
            if ($role !== '' && ! in_array($role, $allowedRoles, true)) {
                return ['success' => false, 'template' => null, 'error' => 'assigned_waiter_role tidak valid'];
            }
            $updates['assigned_waiter_role'] = $role;
        }

        if (array_key_exists('assigned_waiter_id', $patch)) {
            $updates['assigned_waiter_id'] = (string) $patch['assigned_waiter_id'];
        }

        if (array_key_exists('is_active', $patch)) {
            $updates['is_active'] = (bool) $patch['is_active'];
        }

        if (array_key_exists('rolling_enabled', $patch)) {
            $updates['rolling_enabled'] = (bool) $patch['rolling_enabled'];
        }

        if (array_key_exists('rolling_period', $patch)) {
            $rp = strtolower(trim((string) $patch['rolling_period']));
            if (! in_array($rp, ['daily', 'weekly', 'monthly'], true)) {
                return ['success' => false, 'template' => null, 'error' => 'rolling_period tidak valid'];
            }
            $updates['rolling_period'] = $rp;
        }

        if (array_key_exists('rolling_waiter_ids', $patch)) {
            $ids = $patch['rolling_waiter_ids'];
            if (is_string($ids)) {
                $decoded = json_decode($ids, true);
                $ids = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($ids)) {
                $ids = [];
            }
            $updates['rolling_waiter_ids'] = array_values(array_filter(array_map('strval', $ids), function ($v) {
                return $v !== '';
            }));
        }

        if (array_key_exists('rolling_anchor_date', $patch)) {
            $updates['rolling_anchor_date'] = (string) $patch['rolling_anchor_date'];
        }

        if (array_key_exists('target_shift_id', $patch)) {
            $updates['target_shift_id'] = (string) $patch['target_shift_id'];
        }

        if (empty($updates)) {
            return ['success' => false, 'template' => null, 'error' => 'Tidak ada field yang diupdate'];
        }

        $this->database->getReference('waiter_task_templates/'.$id)->update($updates);

        $updated = array_merge($existing, $updates);

        return ['success' => true, 'template' => $updated, 'error' => null];
    }

    /**
     * Generate due recurring waiter tasks.
     */
    public function generateDueRecurringWaiterTasks(bool $force = false)
    {
        $todayDate = date('Y-m-d');
        $lastRunRef = $this->database->getReference('system/scanner_last_run_at');
        $lastRunAt = $lastRunRef->getValue();
        $datesToProcess = [];

        if ($force || empty($lastRunAt) || ! is_string($lastRunAt)) {
            $datesToProcess = [$todayDate];
        } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastRunAt)) {
            $datesToProcess = [$todayDate];
        } elseif ($lastRunAt === $todayDate) {
            // Already ran today — still process today (templates may have been added/changed)
            $datesToProcess = [$todayDate];
        } else {
            $startDate = new \DateTimeImmutable($lastRunAt);
            $startDate = $startDate->modify('+1 day');
            $endDate = new \DateTimeImmutable($todayDate);

            if ($startDate > $endDate) {
                return [
                    'generated' => 0,
                    'dates' => [],
                    'today' => $todayDate,
                ];
            }

            $maxDays = 14;
            $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->modify('+1 day'));
            $count = 0;
            foreach ($period as $date) {
                $datesToProcess[] = $date->format('Y-m-d');
                $count++;
                if ($count >= $maxDays) {
                    break;
                }
            }
        }

        $generatedCount = 0;
        foreach ($datesToProcess as $targetDate) {
            $generatedCount += $this->generateRecurringTasksForDate($targetDate, $targetDate !== $todayDate, $force);
        }

        $lastRunRef->set($todayDate);

        return [
            'generated' => $generatedCount,
            'dates' => $datesToProcess,
            'today' => $todayDate,
        ];
    }

    /**
     * Force-generate recurring task instances for a single template, scoped to today.
     * Bypasses last_generated_date and schedule_time gating, but still respects
     * recurrence_type / days_of_week / is_active. Used by supervisor "Trigger Now".
     */
    public function forceGenerateForTemplate(string $templateId): array
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            return ['success' => false, 'message' => 'Template ID kosong.', 'generated' => 0];
        }

        $template = $this->getRecurringWaiterTaskTemplateById($templateId);
        if (! $template) {
            return ['success' => false, 'message' => 'Template tidak ditemukan.', 'generated' => 0];
        }
        if (empty($template['is_active'])) {
            return ['success' => false, 'message' => 'Template tidak aktif. Aktifkan dulu sebelum trigger.', 'generated' => 0];
        }

        $today = date('Y-m-d');
        $generated = $this->generateRecurringTasksForDate($today, false, true, $templateId);

        return [
            'success' => true,
            'message' => $generated > 0
                ? "Berhasil generate {$generated} task untuk template ini."
                : 'Tidak ada task baru di-generate (semua sudah ada atau tidak ada waiter yang eligible hari ini).',
            'generated' => $generated,
            'date' => $today,
            'template_title' => (string) ($template['title'] ?? ''),
        ];
    }

    /**
     * Generate recurring waiter tasks for specific date.
     */
    private function generateRecurringTasksForDate(string $targetDate, bool $isCatchUp, bool $force = false, ?string $templateIdFilter = null): int
    {
        $templates = $this->getRecurringWaiterTaskTemplates();
        if ($templateIdFilter !== null) {
            $templates = array_values(array_filter($templates, function ($tpl) use ($templateIdFilter) {
                return (string) ($tpl['id'] ?? '') === $templateIdFilter;
            }));
        }
        $generatedCount = 0;
        $currentTime = date('H:i');
        $isToday = $targetDate === date('Y-m-d');
        $existingRecurringMap = $this->getExistingWaiterRecurringMapForDate($targetDate);

        foreach ($templates as $template) {
            $effectiveTargetDate = $targetDate;
            $rescheduledFromDate = null;
            if (empty($template['is_active'])) {
                continue;
            }

            $scheduleTime = $template['schedule_time'] ?? null;
            $templateScheduleModeCheck = (string) ($template['schedule_mode'] ?? 'fixed');

            $lastGeneratedDate = $template['last_generated_date'] ?? null;
            // For shift_relative mode, don't skip based on last_generated_date because
            // different waiters may have different shift start times throughout the day.
            // Saat $force=true (Force Generate manual), bypass juga untuk re-generate task
            // yg mungkin sudah di-cancel admin.
            $alreadyGeneratedToday = $force
                ? false
                : ($templateScheduleModeCheck === 'shift_relative' ? false : ($lastGeneratedDate === $effectiveTargetDate));
            // For shift_relative mode, skip the global time check (handled per-waiter in loop).
            // Saat $force=true, bypass juga schedule_time check.
            $isDueToday = $force
                ? true
                : ($templateScheduleModeCheck === 'shift_relative' ? true : (! $isToday || ! $scheduleTime || $currentTime >= $scheduleTime));
            $recurrenceMatchedToday = $force ? true : $this->isTemplateDueForDate($template, $effectiveTargetDate);

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
                $template['selected_waiter_ids'] ?? [],
                (string) ($template['task_type'] ?? 'general')
            );

            if (empty($targetWaiters)) {
                continue;
            }

            // === ROLLING (rolling_enabled=true; pick 1 waiter from rolling_waiter_ids by period offset) ===
            // Works for BOTH general AND rack_check templates created via studio.
            // Legacy rack_check rolling via assignment_strategy='role_round_robin' tetap dihandle
            // di branch $isRackRollingTemplate di bawah (tidak konflik karena flag-nya beda).
            $isGeneralRolling = ! $isRackRollingTemplate
                && ! empty($template['rolling_enabled'])
                && is_array($template['rolling_waiter_ids'] ?? null)
                && count((array) $template['rolling_waiter_ids']) > 0;

            if ($isGeneralRolling) {
                $rollingIds = array_values(array_filter(
                    array_map('strval', (array) $template['rolling_waiter_ids']),
                    function ($v) {
                        return $v !== '';
                    }
                ));
                if (! empty($rollingIds)) {
                    $period = (string) ($template['rolling_period'] ?? 'weekly');
                    $anchor = trim((string) ($template['rolling_anchor_date'] ?? ''));
                    $offset = $this->resolveRotationOffsetForPeriod(
                        $effectiveTargetDate,
                        $period,
                        $anchor !== '' ? $anchor : null
                    );
                    $pickedId = $rollingIds[$offset % count($rollingIds)];
                    $pickedWaiter = null;
                    foreach ($targetWaiters as $w) {
                        if ((string) ($w['id'] ?? '') === $pickedId) {
                            $pickedWaiter = $w;
                            break;
                        }
                    }
                    if (! $pickedWaiter) {
                        try {
                            $maybeWaiter = $this->getWaiterById($pickedId);
                            if ($maybeWaiter && ($maybeWaiter['is_active'] ?? true)) {
                                $pickedWaiter = $maybeWaiter;
                            }
                        } catch (\Throwable $e) {
                            $pickedWaiter = null;
                        }
                    }
                    if ($pickedWaiter) {
                        $targetWaiters = [$pickedWaiter];
                    } else {
                        // Invalid rolling waiter ID, skip this template for today
                        continue;
                    }
                } else {
                    $isGeneralRolling = false;
                }
            }

            // === SHIFT TARGET FILTER (narrow to waiters whose shift today matches) ===
            // Only narrow when assignment is role/all and not in rolling mode.
            // For single + rolling, target_shift_id is informational only (flag).
            $targetShiftId = trim((string) ($template['target_shift_id'] ?? ''));
            if ($targetShiftId !== '' && ! $isGeneralRolling && $templateAssignmentType !== 'single') {
                $shiftFiltered = array_values(array_filter($targetWaiters, function ($w) use ($targetShiftId, $effectiveTargetDate) {
                    $wid = (string) ($w['id'] ?? '');
                    if ($wid === '') {
                        return false;
                    }
                    $shift = $this->getWaiterShiftForDate($wid, $effectiveTargetDate);

                    return $shift && (string) ($shift['id'] ?? '') === $targetShiftId;
                }));
                if (! empty($shiftFiltered)) {
                    $targetWaiters = $shiftFiltered;
                }
                // If filter empties: keep targetWaiters as-is, downstream loop will flag mismatch
            }

            // Filter out waiters who are off today (not scheduled to work)
            // SKIPPED for general rolling (per user: still assign, flag instead)
            $originalTargetWaiters = $targetWaiters;
            if (! $isGeneralRolling) {
                $targetWaiters = array_values(array_filter($targetWaiters, function ($waiter) use ($effectiveTargetDate) {
                    $wId = $waiter['id'] ?? '';
                    if ($wId === '') {
                        return true;
                    }
                    return $this->isWorkingDay($wId, $effectiveTargetDate);
                }));
            }

            if (! $isGeneralRolling && empty($targetWaiters)) {
                // PRIORITY 1: Peer fallback (Opsi E)
                // Kalau template assignment_type=single dan single-assignee libur,
                // coba cari peer dgn role sama yang masuk hari cycle asli.
                // Untuk role/selected mode, peer set sudah complete via $originalTargetWaiters.
                $peerFallbackUsed = false;
                if ($templateAssignmentType === 'single' && $assignedWaiterRole !== null && $assignedWaiterRole !== '') {
                    try {
                        $peerWaiters = $this->getActiveWaitersByRole($assignedWaiterRole);
                        // Exclude assignee asli (sudah dicek libur)
                        $assigneeId = (string) ($originalTargetWaiters[0]['id'] ?? '');
                        $peerCandidates = array_values(array_filter($peerWaiters, function ($w) use ($effectiveTargetDate, $assigneeId) {
                            $wId = (string) ($w['id'] ?? '');
                            if ($wId === '' || $wId === $assigneeId) {
                                return false;
                            }
                            return $this->isWorkingDay($wId, $effectiveTargetDate);
                        }));

                        if (! empty($peerCandidates)) {
                            $targetWaiters = $peerCandidates;
                            $peerFallbackUsed = true;
                            $rescheduledFromDate = null;

                            // Notify admin singkat: peer fallback (bukan reschedule, hari sama)
                            try {
                                $fonnte = app(\App\Services\FonnteService::class);
                                $fonnte->notifyTaskRescheduled(
                                    $template,
                                    $originalTargetWaiters[0] ?? [],
                                    $peerCandidates[0],
                                    $effectiveTargetDate,
                                    $effectiveTargetDate // same date, beda waiter
                                );
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                if (! $peerFallbackUsed) {
                    // PRIORITY 2: Reschedule ke hari kerja terdekat (max +7) dgn cap load
                    $rescheduleResult = $this->tryRescheduleRecurringTask(
                        $template,
                        $originalTargetWaiters,
                        $effectiveTargetDate
                    );

                    if (! ($rescheduleResult['rescheduled'] ?? false)) {
                        // PRIORITY 3: Failed (sudah handled di tryReschedule: log audit + WA URGENT)
                        continue;
                    }

                    $effectiveTargetDate = (string) ($rescheduleResult['new_date'] ?? $effectiveTargetDate);
                    $targetWaiters = $rescheduleResult['waiters'] ?? [];
                    $rescheduledFromDate = (string) ($rescheduleResult['original_date'] ?? $targetDate);
                    $isToday = $effectiveTargetDate === date('Y-m-d');
                    $existingRecurringMap = $this->getExistingWaiterRecurringMapForDate($effectiveTargetDate);
                }
            } else {
                $rescheduledFromDate = null;
            }

            if ($isRackRollingTemplate) {
                usort($targetWaiters, function ($a, $b) {
                    $nameCompare = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                    if ($nameCompare !== 0) {
                        return $nameCompare;
                    }

                    return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
                });

                $rotationOffset = $this->resolveDailyRotationOffset($effectiveTargetDate);
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
                    $waiterShift = $this->getWaiterShiftForDate($waiterId, $effectiveTargetDate);
                    if ($waiterShift) {
                        $shiftStart = $waiterShift['clock_in_time'] ?? '08:00';
                        $shiftEnd = $waiterShift['clock_out_time'] ?? '17:00';

                        // Calculate schedule time: shift start + offset
                        $shiftStartTimestamp = $this->buildScheduledTimestamp($effectiveTargetDate, $shiftStart);
                        $waiterScheduleTimestamp = $shiftStartTimestamp + ($templateShiftOffsetMinutes * 60);
                        $waiterScheduleTime = date('H:i', $waiterScheduleTimestamp);

                        // Check if current time has reached this waiter's schedule time
                        if ($isToday && $currentTime < $waiterScheduleTime) {
                            continue; // Not yet time for this waiter
                        }

                        // Calculate deadline based on deadline_mode
                        if ($templateDeadlineMode === 'before_shift_end') {
                            $shiftEndTimestamp = $this->buildScheduledTimestamp($effectiveTargetDate, $shiftEnd);
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
                            $scheduleTimestamp = $this->buildScheduledTimestamp($effectiveTargetDate, $scheduleTime);
                            $waiterDeadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
                        }
                    }
                } else {
                    // Fixed mode: original behavior
                    if ($timeLimitMinutes > 0) {
                        $scheduleTimestamp = $this->buildScheduledTimestamp($effectiveTargetDate, $scheduleTime);
                        $waiterDeadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
                    }
                }

                $recurringInstanceKey = $this->buildWaiterRecurringInstanceIdentity(
                    $template['id'],
                    $waiter['id'] ?? null,
                    $effectiveTargetDate
                );
                $taskNodeKey = $this->buildWaiterRecurringTaskNodeKey($recurringInstanceKey);
                $taskReference = $this->database->getReference('waiter_tasks/'.$taskNodeKey);
                $existingTaskSnap = $taskReference->getSnapshot();
                if ($existingTaskSnap->exists()) {
                    $existingTaskValue = (array) $existingTaskSnap->getValue();
                    $existingStatus = (string) ($existingTaskValue['status'] ?? '');
                    // Kalau task lama berstatus cancelled, allow overwrite (regenerate fresh).
                    // Kalau status lain (pending/in_progress/done/overdue), skip seperti biasa.
                    if ($existingStatus !== 'cancelled') {
                        $existingRecurringMap[$mapKey] = true;

                        continue;
                    }
                }

                // GUARD: skip task hari ini kalau deadline-nya sudah lewat.
                // Mencegah kasus "buat template siang hari, langsung tergenerate
                // dengan deadline sudah expired (09:30 padahal sekarang 12:00)
                // → langsung overdue + apply penalty otomatis."
                // Tetap mark last_generated_date supaya besok jalan normal.
                if ($isToday && $waiterDeadlineAt !== null && $waiterDeadlineAt > 0 && $waiterDeadlineAt <= time()) {
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
                    'scheduled_for_date' => $effectiveTargetDate,
                    'source_template_id' => $template['id'],
                    'recurring_instance_key' => $recurringInstanceKey,
                    'time_limit_minutes' => $timeLimitMinutes > 0 ? $timeLimitMinutes : null,
                    'deadline_at' => $waiterDeadlineAt,
                    'recurrence_type' => $template['recurrence_type'] ?? 'daily',
                    'is_rescheduled' => $rescheduledFromDate !== null,
                    'rescheduled_from_date' => $rescheduledFromDate,
                    'original_due_date' => $rescheduledFromDate,
                ]);

                // === ROLLING / SHIFT FLAGS ===
                if ($isGeneralRolling) {
                    $taskData['is_rolling_assignment'] = true;
                    $taskData['rolling_period'] = (string) ($template['rolling_period'] ?? 'weekly');
                    if (! empty($template['rolling_anchor_date'])) {
                        $taskData['rolling_anchor_date'] = (string) $template['rolling_anchor_date'];
                    }
                    // Off-day flag: assigned but not scheduled to work today
                    if (($waiter['id'] ?? '') !== '' && ! $this->isWorkingDay($waiter['id'], $effectiveTargetDate)) {
                        $taskData['is_off_day_assignment'] = true;
                    }
                }
                if ($targetShiftId !== '') {
                    $waiterShiftToday = ($waiter['id'] ?? '') !== ''
                        ? $this->getWaiterShiftForDate($waiter['id'], $effectiveTargetDate)
                        : null;
                    $waiterShiftId = $waiterShiftToday ? (string) ($waiterShiftToday['id'] ?? '') : '';
                    $taskData['target_shift_id'] = $targetShiftId;
                    if ($waiterShiftId !== $targetShiftId) {
                        $taskData['is_shift_mismatch'] = true;
                        $taskData['actual_shift_id'] = $waiterShiftId;
                    }
                }

                if ($isCatchUp) {
                    $taskData['is_overdue'] = true;
                    $taskData['original_scheduled_date'] = $effectiveTargetDate;
                    $existingNote = trim((string) ($taskData['note'] ?? ''));
                    $prefix = '(Tertunda dari '.$effectiveTargetDate.') ';
                    $taskData['note'] = $prefix.$existingNote;
                }

                $taskReference->set($taskData);
                $existingRecurringMap[$mapKey] = true;
                $generatedForTemplate++;
                $generatedCount++;
            }

            if ($generatedForTemplate > 0) {
                $markGeneratedDate = $rescheduledFromDate !== null ? $rescheduledFromDate : $effectiveTargetDate;
                $this->database->getReference('waiter_task_templates/'.$template['id'])->update([
                    'last_generated_date' => $markGeneratedDate,
                ]);
            }
        }

        return $generatedCount;
    }

    /**
     * Cari hari kerja terdekat (max +7 hari) untuk waiter assignee.
     * Cap load 5 task pending per waiter per hari kandidat (Opsi E - distribusi adil).
     * Kalau gagal: log audit + notif WA URGENT ke admin.
     *
     * Return ['rescheduled' => bool, 'new_date' => Y-m-d, 'original_date' => Y-m-d, 'waiters' => array]
     */
    private function tryRescheduleRecurringTask(array $template, array $originalTargetWaiters, string $originalDate): array
    {
        $maxDaysAhead = 7;
        $loadCap = 5; // max task pending per waiter per hari

        for ($offset = 1; $offset <= $maxDaysAhead; $offset++) {
            $candidateDate = date('Y-m-d', strtotime($originalDate.' +'.$offset.' days'));

            $availableWaiters = array_values(array_filter($originalTargetWaiters, function ($waiter) use ($candidateDate, $loadCap) {
                $wId = $waiter['id'] ?? '';
                if ($wId === '') {
                    return true;
                }

                if (! $this->isWorkingDay($wId, $candidateDate)) {
                    return false;
                }

                // Cap load: skip waiter kalau sudah punya >= $loadCap task pending/in_progress di hari kandidat
                try {
                    $existingTasks = $this->getWaiterTasksForDate($wId, $candidateDate);
                    $activeCount = count(array_filter($existingTasks, function ($t) {
                        $status = (string) ($t['status'] ?? 'pending');

                        return in_array($status, ['pending', 'in_progress'], true);
                    }));

                    return $activeCount < $loadCap;
                } catch (\Throwable $e) {
                    report($e);

                    // Fail open: kalau cek load gagal, izinkan supaya task tidak hilang
                    return true;
                }
            }));

            if (empty($availableWaiters)) {
                continue;
            }

            try {
                $fonnte = app(\App\Services\FonnteService::class);
                $fonnte->notifyTaskRescheduled(
                    $template,
                    $originalTargetWaiters[0] ?? [],
                    $availableWaiters[0],
                    $originalDate,
                    $candidateDate
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return [
                'rescheduled' => true,
                'new_date' => $candidateDate,
                'original_date' => $originalDate,
                'waiters' => $availableWaiters,
            ];
        }

        try {
            $this->database->getReference('audit_logs/reschedule_failures')->push([
                'template_id' => $template['id'] ?? null,
                'template_title' => $template['title'] ?? '',
                'rack_name' => $template['rack_name'] ?? '',
                'original_date' => $originalDate,
                'reason' => 'Tidak ada waiter available dgn load < '.$loadCap.' task dalam '.$maxDaysAhead.' hari ke depan',
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // Notif WA URGENT ke admin
        try {
            $fonnte = app(\App\Services\FonnteService::class);
            $fonnte->notifyTaskUrgentNoCoverage($template, $originalDate, $maxDaysAhead);
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'rescheduled' => false,
        ];
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
                $lateArrivalKeys = [];
                foreach ($allMonthPenalties as $p) {
                    if (($p['penalty_type'] ?? '') === 'mandatory_task_missed') {
                        $key = ($p['related_task_id'] ?? '') . '::' . ($p['waiter_id'] ?? '');
                        $existingPenaltyKeys[$key] = true;
                    }

                    if (($p['penalty_type'] ?? '') === 'late_arrival') {
                        $key = ($p['waiter_id'] ?? '') . '::' . ($p['date'] ?? '');
                        $lateArrivalKeys[$key] = true;
                    }
                }

                $attendanceLookup = [];
                $attendancePairs = [];
                foreach ($overdueTasks as $task) {
                    $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
                    if ($waiterId === '') {
                        continue;
                    }

                    $taskDate = (string) ($task['scheduled_for_date'] ?? $today);
                    if ($taskDate === '') {
                        $taskDate = $today;
                    }

                    $cacheKey = $taskDate.'::'.$waiterId;
                    $attendancePairs[$cacheKey] = [
                        'date' => $taskDate,
                        'waiter_id' => $waiterId,
                    ];
                }

                if (! empty($attendancePairs)) {
                    $attendanceLookup = $this->getAttendanceForBatch(array_values($attendancePairs));
                }

                foreach ($overdueTasks as $task) {
                    $taskId = (string) ($task['id'] ?? '');
                    if ($taskId === '') {
                        continue;
                    }
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

                    $taskDate = (string) ($task['scheduled_for_date'] ?? $today);
                    if ($taskDate === '') {
                        $taskDate = $today;
                    }

                    $attendance = $attendanceLookup[$taskDate.'::'.$waiterId] ?? null;
                    $attendanceStatus = strtolower((string) ($attendance['status'] ?? ''));
                    $attendanceExempt = ! empty($attendance['attendance_exempt']);

                    if (in_array($attendanceStatus, ['absent', 'sick', 'day_off'], true) || $attendanceExempt) {
                        // Skip penalty: waiter sedang absent/sick/day_off di tanggal ini
                        continue;
                    }

                    $lateKey = $waiterId . '::' . $taskDate;
                    if (isset($lateArrivalKeys[$lateKey])) {
                        // Skip penalty: waiter sudah kena late_arrival di tanggal ini, tidak double-penalty
                        continue;
                    }

                    $bonusService->applyPenalty([
                        'waiter_id' => $waiterId,
                        'waiter_name' => $waiterName,
                        'penalty_type' => 'mandatory_task_missed',
                        'date' => $taskDate,
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
     * Delete recurring waiter template + soft-cancel pending tasks linked to it.
     *
     * Task done/overdue/cancelled tetap utuh (audit trail). Hanya pending dan
     * in_progress yang dialihkan ke status='cancelled' dgn note.
     *
     * @return array ['deleted_template' => bool, 'cancelled_tasks' => int]
     */
    public function deleteRecurringWaiterTaskTemplate($id)
    {
        $cancelledCount = 0;

        // Soft-cancel pending tasks linked ke template ini
        try {
            $reference = $this->database->getReference('waiter_tasks')
                ->orderByChild('source_template_id')
                ->equalTo((string) $id);
            $snapshot = $reference->getSnapshot();

            if ($snapshot->exists()) {
                $now = time();
                $updates = [];
                foreach ((array) $snapshot->getValue() as $taskId => $task) {
                    $status = (string) ($task['status'] ?? 'pending');
                    if (! in_array($status, ['pending', 'in_progress'], true)) {
                        continue;
                    }
                    $updates[$taskId.'/status'] = 'cancelled';
                    $updates[$taskId.'/cancelled_at'] = $now;
                    $updates[$taskId.'/cancelled_by_template_delete'] = true;
                    $existingNote = (string) ($task['completed_note'] ?? '');
                    $cancelNote = 'Template induk dihapus oleh admin';
                    $updates[$taskId.'/completed_note'] = $existingNote !== ''
                        ? $existingNote.' | '.$cancelNote
                        : $cancelNote;
                    $cancelledCount++;
                }

                if (! empty($updates)) {
                    $this->database->getReference('waiter_tasks')->update($updates);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $this->database->getReference('waiter_task_templates/'.$id)->remove();

        return [
            'deleted_template' => true,
            'cancelled_tasks' => $cancelledCount,
        ];
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
    protected function resolveTargetWaiters($assignmentType, $assignedWaiterId = null, $assignedWaiterRole = null, $selectedWaiterIdsInput = [], $taskType = 'general')
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
            if (! is_array($selectedWaiterIdsInput)) {
                $selectedWaiterIdsInput = explode(',', (string) $selectedWaiterIdsInput);
            }

            $selectedWaiterIds = array_values(array_unique(array_filter(array_map(function ($waiterId) {
                return trim((string) $waiterId);
            }, $selectedWaiterIdsInput), function ($waiterId) {
                return $waiterId !== '';
            })));

            // Untuk rack_check dgn selected waiters, BYPASS filter role.
            // Builder rack_check support multi-role (kasir + pelayan + backup di lane berbeda),
            // jadi resolveTargetWaiters harus return semua selected waiter terlepas role mereka.
            if ($taskType === 'rack_check' && count($selectedWaiterIds) > 0) {
                $allActive = $this->getActiveWaiters();
                $selectedWaiterMap = array_fill_keys($selectedWaiterIds, true);

                return array_values(array_filter($allActive, function ($waiter) use ($selectedWaiterMap) {
                    $waiterId = trim((string) ($waiter['id'] ?? ''));

                    return $waiterId !== '' && isset($selectedWaiterMap[$waiterId]);
                }));
            }

            // General task: tetap filter role-based seperti sebelumnya
            if (! $assignedWaiterRole) {
                return [];
            }

            $roleWaiters = $this->getActiveWaitersByRole($assignedWaiterRole);

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

        return in_array($role, ['kasir', 'pelayan', 'backup', 'supervisor', 'finance'], true) ? $role : 'pelayan';
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
     * Resolve rotation offset (slot index) for waiter rolling on a given period.
     *
     * @param string $date         Target date YYYY-MM-DD
     * @param string $period       'daily' | 'weekly' | 'monthly'
     * @param string|null $anchor  Anchor date YYYY-MM-DD (start of rotation cycle)
     * @return int                 Number of completed periods since anchor (>=0)
     */
    protected function resolveRotationOffsetForPeriod(string $date, string $period, ?string $anchor = null): int
    {
        $targetTs = strtotime($date.' 00:00:00');
        if ($targetTs === false) {
            return 0;
        }

        $anchorTs = $anchor ? strtotime($anchor.' 00:00:00') : false;
        if ($anchorTs === false || $anchorTs > $targetTs) {
            // No anchor / anchor in future → fall back to absolute period bucket
            $period = strtolower($period);
            if ($period === 'monthly') {
                return ((int) date('Y', $targetTs)) * 12 + ((int) date('n', $targetTs)) - 1;
            }
            $diffDays = (int) floor($targetTs / 86400);

            return $period === 'weekly' ? (int) floor($diffDays / 7) : $diffDays;
        }

        $period = strtolower($period);
        if ($period === 'monthly') {
            $monthsTarget = ((int) date('Y', $targetTs)) * 12 + ((int) date('n', $targetTs));
            $monthsAnchor = ((int) date('Y', $anchorTs)) * 12 + ((int) date('n', $anchorTs));

            return max(0, $monthsTarget - $monthsAnchor);
        }

        $diffDays = (int) floor(($targetTs - $anchorTs) / 86400);
        if ($period === 'weekly') {
            return (int) floor($diffDays / 7);
        }

        // daily (default)
        return $diffDays;
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

            // Skip cancelled tasks — supaya scanner bisa re-generate kalau template
            // tetap aktif setelah admin cancel pending task lama.
            if ((string) ($task['status'] ?? '') === 'cancelled') {
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
            
            // Check if waiter is off today (libur)
            if (!$shift) {
                return [
                    'success' => false,
                    'message' => 'Anda sedang libur hari ini dan tidak perlu absen',
                ];
            }
            
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

        // Check if waiter is off today (libur)
        $shift = $this->getWaiterShiftForDate($waiterId, $today);
        if (!$shift) {
            return [
                'success' => false,
                'message' => 'Anda sedang libur hari ini dan tidak perlu absen',
            ];
        }

        // Determine late status based on today's schedule template (not static shift_id)
        $status = 'present';
        $lateMinutes = 0;

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
    // GLOBAL QR ATTENDANCE (SCAN-TRIGGERED ROTATING)
    // ===================================================================

    /**
     * Get current global attendance QR (scan-triggered rotating).
     */
    public function getCurrentGlobalAttendanceQr(): array
    {
        $ref = $this->database->getReference('attendance_config/global_qr');
        $snapshot = $ref->getSnapshot();
        $data = $snapshot->exists() ? $snapshot->getValue() : [];
        $today = date('Y-m-d');
        
        $qrValue = $data['qr_value'] ?? '';
        $generatedAt = $data['generated_at'] ?? 0;
        $scanCount = $data['scan_count'] ?? 0;
        $lastScannedBy = $data['last_scanned_by'] ?? null;
        
        // Generate a fresh daily QR so scan_count matches the displayed date.
        if ($qrValue === '' || ($data['date'] ?? null) !== $today) {
            return $this->regenerateGlobalAttendanceQr();
        }
        
        return [
            'qr_value' => $qrValue,
            'generated_at' => $generatedAt,
            'scan_count' => $scanCount,
            'last_scanned_by' => $lastScannedBy,
            'date' => $today,
        ];
    }

    /**
     * Regenerate global attendance QR (called after successful scan).
     */
    public function regenerateGlobalAttendanceQr(): array
    {
        $now = time();
        $today = date('Y-m-d', $now);
        $qrValue = 'ATTENDANCE:GLOBAL:' . bin2hex(random_bytes(16));
        
        $data = [
            'qr_value' => $qrValue,
            'generated_at' => $now,
            'scan_count' => 0,
            'updated_at' => $now,
            'date' => $today,
        ];
        
        $this->database->getReference('attendance_config/global_qr')->set($data);
        
        return $data;
    }

    /**
     * Process global QR scan with auto-regeneration (scan-triggered).
     */
    public function processGlobalQrScanWithRegeneration(
        string $waiterId, 
        string $purpose, 
        string $scannedValue
    ): array
    {
        $today = date('Y-m-d');
        $now = time();
        
        // Validate waiter
        $waiter = $this->getWaiterById($waiterId);
        if (!$waiter || !($waiter['is_active'] ?? true)) {
            return ['success' => false, 'message' => 'Data waiter tidak ditemukan'];
        }
        
        if (!empty($waiter['attendance_exempt'])) {
            return ['success' => false, 'message' => 'Waiter ini tidak wajib absensi'];
        }
        
        // Check settings
        $settings = $this->getSettings();
        if ($purpose === 'clock_out' && empty($settings['clock_out_enabled'])) {
            return ['success' => false, 'message' => 'Fitur absen pulang tidak aktif'];
        }
        
        // Use transaction for atomic operation
        $attendancePath = "waiter_attendance/{$waiterId}/{$today}";
        $globalQrPath = "attendance_config/global_qr";
        $result = ['success' => false, 'message' => 'Gagal memproses absensi'];
        
        $this->database->runTransaction(function($transaction) use (
            $attendancePath, 
            $globalQrPath, 
            $waiterId, 
            $today, 
            $purpose, 
            $scannedValue, 
            $now, 
            &$result
        ) {
            // 1. Validate QR
            $qrRef = $this->database->getReference($globalQrPath);
            $qrSnapshot = $transaction->snapshot($qrRef);
            
            if (!$qrSnapshot->exists()) {
                $result = ['success' => false, 'message' => 'QR code tidak ditemukan'];
                return;
            }
            
            $qrData = $qrSnapshot->getValue();
            $currentQr = $qrData['qr_value'] ?? '';
            $currentQrDate = $qrData['date'] ?? null;
            
            if ($currentQr !== $scannedValue || $currentQrDate !== $today) {
                $result = ['success' => false, 'message' => 'QR code tidak valid atau sudah berubah. Silakan scan ulang.'];
                return;
            }
            
            // 2. Check attendance record
            $attendanceRef = $this->database->getReference($attendancePath);
            $attendanceSnapshot = $transaction->snapshot($attendanceRef);
            $record = $attendanceSnapshot->exists() ? (array) $attendanceSnapshot->getValue() : [];
            
            // 3. Validate based on purpose
            if ($purpose === 'clock_in') {
                if (!empty($record['clock_in'])) {
                    $result = [
                        'success' => false,
                        'message' => 'Sudah absen masuk hari ini pada ' . $record['clock_in']
                    ];
                    return;
                }
                
                // Check if waiter is off today (libur)
                $shift = $this->getWaiterShiftForDate($waiterId, $today);
                if (!$shift) {
                    $result = [
                        'success' => false,
                        'message' => 'Anda sedang libur hari ini dan tidak perlu absen'
                    ];
                    return;
                }
                
                // Calculate late status
                $status = 'present';
                $lateMinutes = 0;
                
                if (!empty($shift['clock_in_time'])) {
                    $expectedTs = strtotime($today . ' ' . $shift['clock_in_time']);
                    $tolerance = ($shift['late_tolerance_minutes'] ?? 0) * 60;
                    
                    if ($now > ($expectedTs + $tolerance)) {
                        $status = 'late';
                        $lateMinutes = (int) round(($now - $expectedTs) / 60);
                    }
                }
                
                $record['clock_in'] = date('H:i', $now);
                $record['clock_in_timestamp'] = $now;
                $record['status'] = $status;
                $record['late_minutes'] = $lateMinutes;
                $record['method'] = 'qr_scan_global';
                $record['note'] = $record['note'] ?? '';
                $record['updated_at'] = $now;
                
                $result = [
                    'success' => true,
                    'message' => $status === 'late' 
                        ? "Absen masuk tercatat (terlambat {$lateMinutes} menit)"
                        : 'Absen masuk tercatat tepat waktu',
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'new_qr_generated' => true,
                ];
                
            } else { // clock_out
                if (empty($record['clock_in'])) {
                    $result = ['success' => false, 'message' => 'Belum absen masuk hari ini'];
                    return;
                }
                
                if (!empty($record['clock_out'])) {
                    $result = [
                        'success' => false,
                        'message' => 'Sudah absen keluar hari ini pada ' . $record['clock_out']
                    ];
                    return;
                }
                
                $record['clock_out'] = date('H:i', $now);
                $record['clock_out_timestamp'] = $now;
                $record['updated_at'] = $now;
                
                $result = [
                    'success' => true,
                    'message' => 'Absen keluar tercatat pada ' . date('H:i', $now),
                    'new_qr_generated' => true,
                ];
            }
            
            // 4. Save attendance
            $transaction->set($attendanceRef, $record);
            
            // 5. REGENERATE QR (key part!)
            $newQrValue = 'ATTENDANCE:GLOBAL:' . bin2hex(random_bytes(16));
            $newQrData = [
                'qr_value' => $newQrValue,
                'generated_at' => $now,
                'scan_count' => ($qrData['scan_count'] ?? 0) + 1,
                'updated_at' => $now,
                'date' => $today,
                'last_scanned_by' => $waiterId,
            ];
            
            $transaction->set($qrRef, $newQrData);
        });
        
        return $result;
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
        // PRIORITY 1: Check rotation pattern first
        $pattern = $this->getRotationPattern($waiterId);
        if ($pattern) {
            if ($pattern['role'] === 'primary') {
                // Primary kasir with rotation
                $isOffDay = $this->isRotationOffDay($pattern, $date);
                if ($isOffDay) {
                    return null; // Libur karena rotation
                }
                // Working - return default shift
                $shiftId = $pattern['default_shift_id'];
                if ($shiftId) {
                    return $this->getShiftById($shiftId);
                }
            } elseif ($pattern['role'] === 'backup') {
                // Backup kasir - check if covering someone
                $coverage = $this->getBackupCoverage($waiterId, $date);
                if ($coverage) {
                    return $this->getShiftById($coverage['shift_id']);
                }
                // Backup not covering anyone today = off
                return null;
            }
        }
        
        // PRIORITY 2: Fallback to template (existing logic for waiter)
        $dayOfWeek = strtolower(date('l', strtotime($date)));
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

    // ===================================================================
    // ROTATION PATTERN (for kasir with rotating schedule)
    // ===================================================================

    /**
     * Set rotation pattern for kasir.
     */
    public function setRotationPattern(string $waiterId, array $pattern): void
    {
        $payload = [
            'enabled' => true,
            'role' => $pattern['role'] ?? 'primary', // primary | backup
            'default_shift_id' => $pattern['default_shift_id'] ?? null,
            'rotation_days' => $pattern['rotation_days'] ?? [],
            'start_week' => $pattern['start_week'] ?? date('o-\WW'),
            'start_day' => $pattern['start_day'] ?? 'monday',
            'updated_at' => time(),
        ];
        
        $this->database->getReference("rotation_patterns/{$waiterId}")->set($payload);
    }

    /**
     * Get rotation pattern for waiter/kasir.
     */
    public function getRotationPattern(string $waiterId): ?array
    {
        $ref = $this->database->getReference("rotation_patterns/{$waiterId}");
        $snapshot = $ref->getSnapshot();
        
        if (!$snapshot->exists()) {
            return null;
        }
        
        $pattern = $snapshot->getValue();
        return ($pattern['enabled'] ?? false) ? $pattern : null;
    }

    /**
     * Calculate if today is rotation off day.
     */
    private function isRotationOffDay(array $pattern, string $date): bool
    {
        $rotationDays = $pattern['rotation_days'] ?? [];
        if (empty($rotationDays)) {
            return false;
        }
        
        $startWeek = $pattern['start_week'];
        
        // Calculate week offset
        list($startYear, $startWeekNum) = explode('-W', $startWeek);
        $currentWeek = date('o-\WW', strtotime($date));
        list($currentYear, $currentWeekNum) = explode('-W', $currentWeek);
        
        $yearDiff = (int)$currentYear - (int)$startYear;
        $weekOffset = ($yearDiff * 52) + ((int)$currentWeekNum - (int)$startWeekNum);
        
        // Determine off day this week
        $rotationIndex = $weekOffset % count($rotationDays);
        $offDayThisWeek = $rotationDays[$rotationIndex];
        
        $currentDayOfWeek = strtolower(date('l', strtotime($date)));
        
        return $currentDayOfWeek === $offDayThisWeek;
    }

    /**
     * Get backup coverage for date.
     */
    public function getBackupCoverage(string $backupId, string $date): ?array
    {
        $ref = $this->database->getReference("backup_coverage/{$date}/{$backupId}");
        $snapshot = $ref->getSnapshot();
        
        if (!$snapshot->exists()) {
            return null;
        }
        
        return $snapshot->getValue();
    }

    /**
     * Calculate and set backup coverage for date.
     */
    public function calculateBackupCoverageForDate(string $date): void
    {
        // Get all kasir with rotation
        $allWaiters = $this->getAllowedEmails();
        $primaryKasir = [];
        $backupKasir = null;
        
        foreach ($allWaiters as $waiter) {
            $pattern = $this->getRotationPattern($waiter['id']);
            if (!$pattern) continue;
            
            if ($pattern['role'] === 'primary') {
                $primaryKasir[] = [
                    'id' => $waiter['id'],
                    'pattern' => $pattern,
                ];
            } elseif ($pattern['role'] === 'backup') {
                $backupKasir = $waiter['id'];
            }
        }
        
        if (!$backupKasir) return;
        
        // Check which primary is off today
        foreach ($primaryKasir as $kasir) {
            $isOff = $this->isRotationOffDay($kasir['pattern'], $date);
            if ($isOff) {
                // Backup covers this kasir
                $this->database->getReference("backup_coverage/{$date}/{$backupKasir}")->set([
                    'covering_for' => $kasir['id'],
                    'shift_id' => $kasir['pattern']['default_shift_id'],
                    'calculated_at' => time(),
                ]);
                return; // Only cover one kasir per day
            }
        }
        
        // No one off = backup is off
        $this->database->getReference("backup_coverage/{$date}/{$backupKasir}")->remove();
    }

    /**
     * Batch fetch attendance per waiter+date map.
     *
     * @param  array<int, array{date:string, waiter_id:string}>  $waiterDatePairs
     * @return array<string, array|null>
     */
    private function getAttendanceForBatch(array $waiterDatePairs): array
    {
        $result = [];

        foreach ($waiterDatePairs as $pair) {
            $date = (string) ($pair['date'] ?? '');
            $waiterId = (string) ($pair['waiter_id'] ?? '');
            if ($date === '' || $waiterId === '') {
                continue;
            }

            $cacheKey = $date.'::'.$waiterId;
            if (array_key_exists($cacheKey, $result)) {
                continue;
            }

            $result[$cacheKey] = $this->getAttendanceByDate($waiterId, $date);
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
     * Delete attendance record for a waiter on a specific date.
     */
    public function deleteAttendance(string $waiterId, string $date): void
    {
        $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$date)->remove();
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

        $productCategories = $this->getProductCategoriesMap();
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
                            ? $this->getTotalStorageQtyForProduct($productId)
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

                $productMaster = $productId !== '' ? $this->getProductById($productId) : null;
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

                $this->createOrUpdateRestockRequest([
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

            $productMaster = $this->getProductById($productId) ?? [];
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

                $totalStorageQty = $this->getTotalStorageQtyForProduct($productId);
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

            $restockRequestId = $this->createOrUpdateRestockRequest([
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

    // ========================================================================
    // PURCHASE ORDER DRAFTS (server-side persistence untuk PO Manual)
    // Path Firebase: purchase_order_drafts/{push_id}
    // Schema: { id, supplier_id, supplier_name, rack_id, notes, items: [{product_id, product_name, qty, note}], created_by, created_by_name, created_at, updated_at }
    // ========================================================================

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
     * Normalize draft items: pastikan setiap item punya {product_id, product_name, qty, note}.
     */
    protected function normalizeDraftItems(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $pid = trim((string) ($item['product_id'] ?? ''));
            $qty = (int) ($item['qty'] ?? 0);
            if ($pid === '' || $qty < 1) {
                continue;
            }
            $clean[] = [
                'product_id' => $pid,
                'product_name' => trim((string) ($item['product_name'] ?? '')),
                'qty' => $qty,
                'note' => trim((string) ($item['note'] ?? '')),
            ];
        }

        return array_values($clean);
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

    public function getProductAuditTrail(string $productId, int $limit = 200): array
    {
        $productId = trim($productId);
        if ($productId === '') return [];
        $rackMap = [];
        try {
            foreach ($this->getRacks() as $rack) {
                $rid = trim((string) ($rack['id'] ?? ''));
                if ($rid !== '') $rackMap[$rid] = (string) ($rack['name'] ?? $rack['rack_name'] ?? $rid);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        $timeline = [];
        try {
            $snap = $this->database->getReference('rack_stock_movements')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $id => $mv) {
                if (!is_array($mv)) continue;
                $createdAt = $this->normalizeUnixTimestampToSeconds((int) ($mv['created_at'] ?? 0));
                $rackId = trim((string) ($mv['rack_id'] ?? ''));
                $rackName = $rackMap[$rackId] ?? (string) ($mv['rack_name'] ?? $rackId ?: '-');
                $actor = (string) ($mv['actor_name'] ?? $mv['waiter_name'] ?? 'Sistem');
                $delta = (int) ($mv['delta'] ?? $mv['delta_qty'] ?? 0);
                // Real schema: no 'prev'/'result' field. Derive from to_qty/current_qty/actual_qty.
                if (array_key_exists('result', $mv)) {
                    $result = (int) $mv['result'];
                } elseif (array_key_exists('to_qty', $mv)) {
                    $result = (int) $mv['to_qty'];
                } elseif (array_key_exists('current_qty', $mv)) {
                    $result = (int) $mv['current_qty'];
                } elseif (array_key_exists('actual_qty', $mv)) {
                    $result = (int) $mv['actual_qty'];
                } else {
                    $result = 0;
                }
                $prev = array_key_exists('prev', $mv) ? (int) $mv['prev'] : ($result - $delta);
                $type = (string) ($mv['type'] ?? $mv['movement_type'] ?? 'stock_take');
                $summary = 'Movement stok';
                if ($type === 'stock_take') $summary = ucfirst("Cek rak: {$prev}→{$result} (" . sprintf('%+d', $delta) . ") oleh {$actor} di {$rackName}");
                elseif ($type === 'po_receive') $summary = ucfirst("Terima PO: +" . max(0, $delta) . " pcs ke {$rackName} oleh {$actor}");
                elseif ($type === 'storage_out') $summary = ucfirst("Ambil stok: -" . abs($delta) . " pcs dari {$rackName} oleh {$actor}");
                $timeline[] = ['kind' => 'movement', 'created_at' => $createdAt, 'event_id' => (string) $id, 'data' => $mv, 'rack_id' => $rackId, 'rack_name' => $rackName, 'actor_name' => $actor, 'summary' => $summary];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail movement: ' . $e->getMessage()));
        }
        try {
            $snap = $this->database->getReference('restock_requests')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $id => $req) {
                if (!is_array($req)) continue;
                $createdAt = $this->normalizeUnixTimestampToSeconds((int) ($req['created_at'] ?? $req['reported_at'] ?? 0));
                $rackId = trim((string) ($req['rack_id'] ?? ''));
                $rackName = $rackMap[$rackId] ?? (string) ($req['rack_name'] ?? $rackId ?: '-');
                $qtyNeeded = (int) ($req['qty_needed'] ?? 0);
                $source = (string) ($req['source'] ?? '-');
                $status = (string) ($req['status'] ?? '-');
                $timeline[] = ['kind' => 'restock_request', 'created_at' => $createdAt, 'event_id' => (string) $id, 'data' => $req, 'rack_id' => $rackId, 'rack_name' => $rackName, 'summary' => ucfirst("Restock request: {$qtyNeeded} pcs untuk {$rackName} (source: {$source}, status: {$status})")];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail restock_requests: ' . $e->getMessage()));
        }
        try {
            $cutoff = time() - (90 * 86400);
            foreach ($this->getPurchaseOrders() as $po) {
                if (!is_array($po)) continue;
                $createdAt = $this->normalizeUnixTimestampToSeconds((int) ($po['created_at'] ?? 0));
                if ($createdAt < $cutoff) continue;
                $matchQty = 0;
                foreach ((array) ($po['items'] ?? []) as $item) if (is_array($item) && (string) ($item['product_id'] ?? '') === $productId) $matchQty += (int) ($item['qty_ordered'] ?? 0);
                if ($matchQty <= 0) continue;
                $poId = (string) ($po['id'] ?? $po['po_id'] ?? '');
                $poNumber = (string) ($po['po_number'] ?? $poId ?: '-');
                $supplier = (string) ($po['supplier_name'] ?? $po['supplier'] ?? '-');
                $status = (string) ($po['status'] ?? '-');
                $timeline[] = ['kind' => 'purchase_order', 'created_at' => $createdAt, 'event_id' => ($poId !== '' ? $poId : $poNumber), 'data' => $po, 'summary' => ucfirst("PO #{$poNumber}: {$matchQty} pcs dari {$supplier} ({$status})")];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail purchase_orders: ' . $e->getMessage()));
        }
        try {
            $snap = $this->database->getReference('audit_logs/stock_anomalies')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $id => $an) {
                if (!is_array($an)) continue;
                $createdAt = $this->normalizeUnixTimestampToSeconds((int) ($an['created_at'] ?? 0));
                $rackId = trim((string) ($an['rack_id'] ?? ''));
                $rackName = $rackMap[$rackId] ?? (string) ($an['rack_name'] ?? $rackId ?: '-');
                $prev = (int) ($an['prev'] ?? 0);
                $result = (int) ($an['result'] ?? 0);
                $severity = (string) ($an['severity'] ?? 'low');
                $timeline[] = ['kind' => 'anomaly', 'created_at' => $createdAt, 'event_id' => (string) $id, 'data' => $an, 'rack_id' => $rackId, 'rack_name' => $rackName, 'actor_name' => (string) ($an['actor_name'] ?? ''), 'summary' => ucfirst("⚠️ Anomali: {$prev}→{$result} (severity: {$severity})")];
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductAuditTrail stock_anomalies: ' . $e->getMessage()));
        }
        usort($timeline, fn($a, $b) => ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0)));
        return array_slice($timeline, 0, max(1, $limit));
    }

    public function getProductStats(string $productId): array
    {
        $productId = trim($productId);
        $stats = ['total_in' => 0, 'total_out' => 0, 'last_movement_at' => 0, 'active_restock_requests' => 0, 'open_pos_containing' => 0, 'racks_holding' => []];
        if ($productId === '') return $stats;
        $cutoff30 = time() - (30 * 86400);
        try {
            $snap = $this->database->getReference('rack_stock_movements')->orderByChild('product_id')->equalTo($productId)->getSnapshot();
            if ($snap->exists()) foreach ((array) $snap->getValue() as $mv) {
                if (!is_array($mv)) continue;
                $createdAt = $this->normalizeUnixTimestampToSeconds((int) ($mv['created_at'] ?? 0));
                $delta = (int) ($mv['delta'] ?? $mv['delta_qty'] ?? 0);
                if ($createdAt > $stats['last_movement_at']) $stats['last_movement_at'] = $createdAt;
                if ($createdAt >= $cutoff30) {
                    if ($delta > 0) $stats['total_in'] += $delta;
                    if ($delta < 0) $stats['total_out'] += abs($delta);
                }
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats movements: ' . $e->getMessage()));
        }
        try {
            $pending = $this->database->getReference('restock_requests')->orderByChild('status')->equalTo('pending')->getSnapshot();
            if ($pending->exists()) foreach ((array) $pending->getValue() as $req) if (is_array($req) && (string) ($req['product_id'] ?? '') === $productId) $stats['active_restock_requests']++;
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats restocks: ' . $e->getMessage()));
        }
        try {
            foreach ($this->getPurchaseOrders() as $po) {
                if (!is_array($po) || !in_array((string) ($po['status'] ?? ''), ['ordered', 'partial'], true)) continue;
                foreach ((array) ($po['items'] ?? []) as $item) if (is_array($item) && (string) ($item['product_id'] ?? '') === $productId) {
                    $stats['open_pos_containing']++;
                    break;
                }
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats purchase_orders: ' . $e->getMessage()));
        }
        try {
            foreach ($this->getRacks() as $rack) {
                $rackId = trim((string) ($rack['id'] ?? ''));
                if ($rackId === '') continue;
                $snap = $this->database->getReference("waiter_racks/{$rackId}/products/{$productId}")->getSnapshot();
                $row = $snap->exists() ? $snap->getValue() : null;
                if (is_array($row) && (int) ($row['current_qty'] ?? 0) > 0) $stats['racks_holding'][] = $rackId;
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Warning getProductStats racks_holding: ' . $e->getMessage()));
        }
        return $stats;
    }

    /**
     * Jalankan rekonsiliasi stok mingguan berbasis ledger movements.
     *
     * @return array{report_id:string,total_racks_checked:int,total_products_checked:int,anomalies:array<int,array<string,mixed>>,iso_year_week:string}
     */
    public function runWeeklyReconciliation(int $windowDays = 7): array
    {
        $windowDays = max(1, $windowDays);
        $nowTs = time();
        $windowStartTs = $nowTs - ($windowDays * 86400);
        $isoYearWeek = date('o_W', $nowTs);

        $totalRacksChecked = 0;
        $totalProductsChecked = 0;
        $anomalies = [];

        $racks = $this->getRacks();
        foreach ($racks as $rack) {
            $isActive = (bool) ($rack['is_active'] ?? true);
            if (! $isActive) {
                continue;
            }

            $rackId = trim((string) ($rack['id'] ?? ''));
            if ($rackId === '') {
                continue;
            }

            $totalRacksChecked++;
            $rackName = trim((string) ($rack['name'] ?? ''));
            $products = is_array($rack['products'] ?? null) ? $rack['products'] : [];
            $movements = $this->getRackStockMovements($rackId, 1000);

            foreach ($products as $pid => $product) {
                if (! is_array($product)) {
                    continue;
                }

                try {
                    $productId = trim((string) ($product['id'] ?? $pid));
                    if ($productId === '') {
                        continue;
                    }

                    $currentQtyRaw = $product['current_qty'] ?? null;
                    if ($currentQtyRaw === null) {
                        continue;
                    }

                    $actualQty = (int) $currentQtyRaw;
                    $totalProductsChecked++;

                    $productMovements = array_values(array_filter($movements, function ($movement) use ($productId, $windowStartTs) {
                        if (! is_array($movement)) {
                            return false;
                        }

                        $movementProductId = trim((string) ($movement['product_id'] ?? ''));
                        $movementTs = $this->normalizeUnixTimestampToSeconds((int) ($movement['created_at'] ?? ($movement['completed_at'] ?? 0)));

                        return $movementProductId === $productId && $movementTs >= $windowStartTs;
                    }));

                    if (count($productMovements) === 0) {
                        continue;
                    }

                    usort($productMovements, fn ($a, $b) => ((int) ($a['created_at'] ?? $a['completed_at'] ?? 0)) <=> ((int) ($b['created_at'] ?? $b['completed_at'] ?? 0)));

                    $latestStockTakeIndex = null;
                    foreach ($productMovements as $index => $movement) {
                        $type = (string) ($movement['movement_type'] ?? $movement['type'] ?? '');
                        if ($type === 'stock_take') {
                            $latestStockTakeIndex = $index;
                        }
                    }

                    if ($latestStockTakeIndex === null) {
                        continue;
                    }

                    $stockTakeMovement = $productMovements[$latestStockTakeIndex];
                    $expectedQty = (int) ($stockTakeMovement['result_qty'] ?? $stockTakeMovement['result'] ?? $stockTakeMovement['actual_qty'] ?? $stockTakeMovement['current_qty'] ?? 0);

                    for ($i = $latestStockTakeIndex + 1; $i < count($productMovements); $i++) {
                        $movement = $productMovements[$i];
                        $type = (string) ($movement['movement_type'] ?? $movement['type'] ?? '');
                        $delta = (int) ($movement['delta_qty'] ?? $movement['delta'] ?? 0);

                        if ($type === 'po_receive' || $type === 'storage_out') {
                            $expectedQty += $delta;
                        }
                    }

                    $driftQty = $actualQty - $expectedQty;
                    $driftPct = abs($driftQty) / max(1, abs($expectedQty)) * 100;

                    if ($driftPct <= 5) {
                        continue;
                    }

                    $severity = 'warning';
                    if ($driftPct > 50) {
                        $severity = 'severe';
                    } elseif ($driftPct > 15) {
                        $severity = 'critical';
                    }

                    $resolvedProduct = $this->getProductById($productId);
                    $productName = trim((string) ($product['name'] ?? $product['product_name'] ?? ($resolvedProduct['name'] ?? 'Produk')));

                    $anomalies[] = [
                        'rack_id' => $rackId,
                        'rack_name' => $rackName,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'expected' => $expectedQty,
                        'actual' => $actualQty,
                        'drift_qty' => $driftQty,
                        'drift_pct' => round($driftPct, 2),
                        'severity' => $severity,
                    ];
                } catch (RuntimeException $e) {
                    continue;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        usort($anomalies, fn ($a, $b) => (($b['drift_pct'] ?? 0) <=> ($a['drift_pct'] ?? 0)));

        $payload = [
            'iso_year_week' => $isoYearWeek,
            'window_days' => $windowDays,
            'window_start_at' => $windowStartTs,
            'window_end_at' => $nowTs,
            'total_racks_checked' => $totalRacksChecked,
            'total_products_checked' => $totalProductsChecked,
            'anomalies_count' => count($anomalies),
            'anomalies' => $anomalies,
            'generated_at' => $nowTs,
            'created_at' => ['.sv' => 'timestamp'],
            'generated_by' => (string) (session('admin_id') ?: 'system_scheduler'),
        ];

        $reportRef = $this->database->getReference("reconciliation_reports/{$isoYearWeek}")->push($payload);
        $reportId = (string) $reportRef->getKey();

        return [
            'report_id' => $reportId,
            'total_racks_checked' => $totalRacksChecked,
            'total_products_checked' => $totalProductsChecked,
            'anomalies' => $anomalies,
            'iso_year_week' => $isoYearWeek,
        ];
    }

    public function getReconciliationReports(?string $isoYearWeek = null, int $limit = 10): array
    {
        $limit = max(1, $limit);

        if ($isoYearWeek !== null && trim($isoYearWeek) !== '') {
            $snapshot = $this->database->getReference('reconciliation_reports/'.trim($isoYearWeek))->getSnapshot();
            if (! $snapshot->exists()) {
                return [];
            }

            $reports = [];
            foreach ((array) $snapshot->getValue() as $reportId => $report) {
                if (! is_array($report)) {
                    continue;
                }
                $report['id'] = (string) $reportId;
                $report['iso_year_week'] = (string) ($report['iso_year_week'] ?? trim($isoYearWeek));
                $reports[] = $report;
            }

            usort($reports, fn ($a, $b) => ((int) ($b['generated_at'] ?? 0)) <=> ((int) ($a['generated_at'] ?? 0)));

            return array_slice($reports, 0, $limit);
        }

        $rootSnapshot = $this->database->getReference('reconciliation_reports')
            ->orderByKey()
            ->limitToLast($limit)
            ->getSnapshot();

        if (! $rootSnapshot->exists()) {
            return [];
        }

        $all = [];
        foreach ((array) $rootSnapshot->getValue() as $week => $reports) {
            if (! is_array($reports)) {
                continue;
            }

            foreach ($reports as $reportId => $report) {
                if (! is_array($report)) {
                    continue;
                }
                $report['id'] = (string) $reportId;
                $report['iso_year_week'] = (string) ($report['iso_year_week'] ?? $week);
                $all[] = $report;
            }
        }

        usort($all, fn ($a, $b) => ((int) ($b['generated_at'] ?? 0)) <=> ((int) ($a['generated_at'] ?? 0)));

        return array_slice($all, 0, $limit);
    }

    public function getReconciliationReportById(string $isoYearWeek, string $reportId): ?array
    {
        $isoYearWeek = trim($isoYearWeek);
        $reportId = trim($reportId);
        if ($isoYearWeek === '' || $reportId === '') {
            return null;
        }

        $snapshot = $this->database->getReference("reconciliation_reports/{$isoYearWeek}/{$reportId}")->getSnapshot();
        if (! $snapshot->exists()) {
            return null;
        }

        $report = (array) $snapshot->getValue();
        $report['id'] = $reportId;
        $report['iso_year_week'] = (string) ($report['iso_year_week'] ?? $isoYearWeek);

        return $report;
    }

    /**
     * Tandai task butuh recompute bonus poin (worker akan retry).
     */
    public function flagTaskBonusPending(string $taskId, string $waiterId, array $context = []): void
    {
        try {
            if ($taskId === '' || $waiterId === '') {
                return;
            }
            $payload = [
                'bonus_pending_recompute' => true,
                'bonus_pending_at' => time(),
                'bonus_pending_waiter_id' => $waiterId,
                'bonus_pending_context' => $context,
            ];
            $this->database->getReference('waiter_tasks/'.$taskId)->update($payload);
            // Index lookup: bonus_pending_recompute_index/{waiterId}/{date}/{taskId} = true
            $date = $context['date'] ?? date('Y-m-d');
            $this->database->getReference('bonus_pending_recompute_index/'.$waiterId.'/'.$date.'/'.$taskId)->set([
                'created_at' => time(),
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Tandai waiter butuh recompute bonus (untuk event non-task seperti activity_report / clock_in).
     */
    public function flagWaiterBonusPending(string $waiterId, string $date, array $context = []): void
    {
        try {
            if ($waiterId === '' || $date === '') {
                return;
            }
            $key = sha1(($context['source'] ?? 'unknown').'|'.$waiterId.'|'.$date.'|'.time());
            $this->database->getReference('bonus_pending_waiter_index/'.$waiterId.'/'.$date.'/'.$key)->set([
                'created_at' => time(),
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Ambil semua bonus pending recompute (max N).
     *
     * @return array list of ['type'=>'task'|'waiter','waiter_id','date','task_id','context','created_at']
     */
    public function getBonusPendingRecomputes(int $limit = 100): array
    {
        $items = [];

        try {
            // Task-based
            $taskSnap = $this->database->getReference('bonus_pending_recompute_index')->getSnapshot();
            if ($taskSnap->exists()) {
                foreach ((array) $taskSnap->getValue() as $waiterId => $byDate) {
                    foreach ((array) $byDate as $date => $byTask) {
                        foreach ((array) $byTask as $taskId => $payload) {
                            $items[] = [
                                'type' => 'task',
                                'waiter_id' => (string) $waiterId,
                                'date' => (string) $date,
                                'task_id' => (string) $taskId,
                                'context' => (array) ($payload['context'] ?? []),
                                'created_at' => (int) ($payload['created_at'] ?? 0),
                            ];
                            if (count($items) >= $limit) {
                                return $items;
                            }
                        }
                    }
                }
            }

            // Waiter-event-based
            $waiterSnap = $this->database->getReference('bonus_pending_waiter_index')->getSnapshot();
            if ($waiterSnap->exists()) {
                foreach ((array) $waiterSnap->getValue() as $waiterId => $byDate) {
                    foreach ((array) $byDate as $date => $byKey) {
                        foreach ((array) $byKey as $key => $payload) {
                            $items[] = [
                                'type' => 'waiter',
                                'waiter_id' => (string) $waiterId,
                                'date' => (string) $date,
                                'task_id' => '',
                                'context_key' => (string) $key,
                                'context' => (array) ($payload['context'] ?? []),
                                'created_at' => (int) ($payload['created_at'] ?? 0),
                            ];
                            if (count($items) >= $limit) {
                                return $items;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $items;
    }

    /**
     * Hapus flag bonus pending setelah recompute sukses.
     */
    public function clearBonusPendingFlag(array $item): void
    {
        try {
            $waiterId = (string) ($item['waiter_id'] ?? '');
            $date = (string) ($item['date'] ?? '');
            if ($waiterId === '' || $date === '') {
                return;
            }
            $type = (string) ($item['type'] ?? '');

            if ($type === 'task') {
                $taskId = (string) ($item['task_id'] ?? '');
                if ($taskId === '') {
                    return;
                }
                $this->database->getReference('bonus_pending_recompute_index/'.$waiterId.'/'.$date.'/'.$taskId)->remove();
                $this->database->getReference('waiter_tasks/'.$taskId)->update([
                    'bonus_pending_recompute' => false,
                    'bonus_pending_cleared_at' => time(),
                ]);
            } elseif ($type === 'waiter') {
                $key = (string) ($item['context_key'] ?? '');
                if ($key === '') {
                    return;
                }
                $this->database->getReference('bonus_pending_waiter_index/'.$waiterId.'/'.$date.'/'.$key)->remove();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Ambil seluruh active_sessions waiter (presence) untuk Live Monitor.
     * Backend pakai service account, bypass Firebase Auth rules.
     *
     * @return array  shape: [rackId => [sessionId => sessionData, ...], ...]
     */
    public function getActiveSessions(): array
    {
        try {
            $snap = $this->database->getReference('active_sessions')->getSnapshot();
            if (! $snap->exists()) {
                return [];
            }
            $value = $snap->getValue();

            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Bulk cancel waiter tasks by ID list. Status pending/in_progress -> cancelled.
     * Task yg sudah done/overdue/cancelled tidak disentuh.
     *
     * @param  array  $taskIds  list of waiter_tasks IDs
     * @param  string $note     reason note untuk completed_note
     * @return int    jumlah task yg ter-cancel
     */
    public function bulkCancelWaiterTasks(array $taskIds, string $note = 'Dibatalkan admin'): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $cancelled = 0;
        $now = time();
        $updates = [];

        foreach ($taskIds as $taskId) {
            $taskId = trim((string) $taskId);
            if ($taskId === '') {
                continue;
            }

            $taskRef = $this->database->getReference('waiter_tasks/'.$taskId);
            $snapshot = $taskRef->getSnapshot();
            if (! $snapshot->exists()) {
                continue;
            }

            $task = (array) $snapshot->getValue();
            $status = (string) ($task['status'] ?? 'pending');
            if (! in_array($status, ['pending', 'in_progress'], true)) {
                continue;
            }

            $existingNote = (string) ($task['completed_note'] ?? '');

            $updates[$taskId.'/status'] = 'cancelled';
            $updates[$taskId.'/cancelled_at'] = $now;
            $updates[$taskId.'/cancelled_by_admin_bulk'] = true;
            $updates[$taskId.'/completed_note'] = $existingNote !== ''
                ? $existingNote.' | '.$note
                : $note;

            $cancelled++;
        }

        if (! empty($updates)) {
            $this->database->getReference('waiter_tasks')->update($updates);
        }

        return $cancelled;
    }

    /**
     * Bulk cancel pending/in_progress waiter tasks by date and optional task_type filter.
     *
     * @param  string  $date          Y-m-d
     * @param  string|null  $taskType  filter (e.g. 'rack_check') or null for all
     * @param  string  $note          reason note
     * @return int     jumlah task yg ter-cancel
     */
    /**
     * Reset all tasks: hapus semua waiter_tasks + waiter_task_templates plus
     * cache yang refer ke task ID. Operasi destruktif & atomic-best-effort:
     * setiap path di-remove independen, hasilnya berisi count pre-state per path.
     *
     * @return array{counts: array<string,int>, total: int}
     */
    public function resetAllTasks(): array
    {
        $paths = [
            'waiter_tasks',
            'waiter_task_templates',
            'waiter_task_idempotency',
            'waiter_task_reminder_state',
        ];

        $counts = [];
        $total = 0;

        foreach ($paths as $path) {
            $value = $this->database->getReference($path)->getValue();
            $count = is_array($value) ? count($value) : 0;
            $counts[$path] = $count;
            $total += $count;

            if ($count > 0) {
                $this->database->getReference($path)->remove();
            }
        }

        return [
            'counts' => $counts,
            'total' => $total,
        ];
    }

    public function bulkCancelPendingTasksForDate(string $date, ?string $taskType = null, string $note = 'Dibatalkan admin (bulk cancel)'): int
    {
        $tasks = $this->getWaiterTasksByDate($date);
        $cancelTaskIds = [];

        foreach ($tasks as $task) {
            if ($taskType !== null && ($task['task_type'] ?? '') !== $taskType) {
                continue;
            }
            $status = (string) ($task['status'] ?? 'pending');
            if (! in_array($status, ['pending', 'in_progress'], true)) {
                continue;
            }
            $taskId = (string) ($task['id'] ?? '');
            if ($taskId !== '') {
                $cancelTaskIds[] = $taskId;
            }
        }

        return $this->bulkCancelWaiterTasks($cancelTaskIds, $note);
    }
}
