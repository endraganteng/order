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

    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Get all allowed waiter emails
     */
    public function getAllowedEmails()
    {
        return app(\App\Repositories\Contracts\WaiterRepositoryInterface::class)->all();
    }

    /**
     * Add new waiter account (with optional password hash).
     */
    public function addAllowedEmailWithPassword($email, $name, $passwordHash = null, $waiterRole = 'pelayan', $shiftId = null, $phone = null, $attendanceExempt = false)
    {
        app(\App\Repositories\Contracts\WaiterRepositoryInterface::class)
            ->add((string) $email, (string) $name, $passwordHash, (string) $waiterRole, $shiftId, $phone, (bool) $attendanceExempt);
    }

    /**
     * Update allowed email
     */
    public function updateAllowedEmail($id, $data)
    {
        app(\App\Repositories\Contracts\WaiterRepositoryInterface::class)->update((string) $id, $data);
    }

    /**
     * Get active waiter accounts only.
     */
    public function getActiveWaiters()
    {
        return app(\App\Repositories\Contracts\WaiterRepositoryInterface::class)->allActive();
    }

    /**
     * Get active waiters by role.
     */
    public function getActiveWaitersByRole($waiterRole)
    {
        return app(\App\Repositories\Contracts\WaiterRepositoryInterface::class)->activeByRole((string) $waiterRole);
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
        app(\App\Repositories\Contracts\WaiterRepositoryInterface::class)->delete((string) $id);
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
     * =========================================================================
     *  PRODUCT CATEGORIES
     * =========================================================================
     */

    /**
     * =========================================================================
     *  MASTER PRODUCTS
     * =========================================================================
     */

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

        return $newCounter;
    }

    /**
     * Get orders for a specific date filtered by waiter_id.
     */
    public function getOrdersByDateAndWaiter(string $date, string $waiterId): array
    {
        $orders = $this->getOrdersByDate($date);

        return array_values(array_filter($orders, fn($order) => ($order['waiter_id'] ?? '') === $waiterId));
    }

    // ========================================
    // Waiter Task Management (Supervisor → Waiter)
    // ========================================

    /**
     * Get a single waiter task by ID.
     */
    // ========================================
    // Task Management (Supervisor Tasks)
    // ========================================

    // ═══════════════════════════════════════════════════════════════════════════
    //  ATTENDANCE SYSTEM
    // ═══════════════════════════════════════════════════════════════════════════

    // ===================================================================
    // GLOBAL QR ATTENDANCE (SCAN-TRIGGERED ROTATING)
    // ===================================================================

    // ===================================================================
    // SHIFT MANAGEMENT
    // ===================================================================

    // ===================================================================
    // SCHEDULE TEMPLATE (permanent, no per-week)
    // ===================================================================

    /**
     * In-memory cache for schedule template (avoids repeated Firebase reads within same request).
     */
    private ?array $scheduleTemplateCache = null;

    // ===================================================================
    // ROTATION PATTERN (for kasir with rotating schedule)
    // ===================================================================

    // ===== BONUS SYSTEM METHODS =====

    /**
     * Get all penalties, optionally filtered by month and/or waiter
     */
    public function getPenalties(?string $month = null, ?string $waiterId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        return app(\App\Repositories\Contracts\BonusRepositoryInterface::class)
            ->penalties($month, $waiterId, $startDate, $endDate);
    }

    // ==========================================
    // RESTOCK & PURCHASE ORDER SYSTEM
    // ==========================================

    // ========================================================================
    // PURCHASE ORDER DRAFTS (server-side persistence untuk PO Manual)
    // Path Firebase: purchase_order_drafts/{push_id}
    // Schema: { id, supplier_id, supplier_name, rack_id, notes, items: [{product_id, product_name, qty, note}], created_by, created_by_name, created_at, updated_at }
    // ========================================================================

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

        $legacyKey = null;
        if (config('features.legacy_write_audit_logs')) {
            $legacyKey = (string) $this->database->getReference('audit_logs')->push($entry)->getKey();
        }

        if (config('features.mysql_audit_logs')) {
            try {
                $attrs = [
                    'action' => $action,
                    'entity' => $entity,
                    'entity_id' => $entityId,
                    'admin_id' => (string) $adminId,
                    'admin_name' => (string) $adminName,
                    'details' => $details ?: null,
                    'ip' => $entry['ip'],
                    'event_timestamp' => $entry['timestamp'],
                    'event_date' => $entry['date'],
                ];
                if ($legacyKey !== null) {
                    \App\Models\AuditLog::updateOrCreate(['firebase_legacy_key' => $legacyKey], $attrs);
                } else {
                    \App\Models\AuditLog::create($attrs);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Get audit logs with optional filters
     */
    public function getAuditLogs(?string $date = null, ?string $entity = null, ?string $adminId = null, int $limit = 100): array
    {
        // MySQL read path (flag-gated). Source of truth for audit history;
        // avoids pulling the unbounded RTDB node on every admin page load.
        if (config('features.mysql_audit_logs')) {
            $query = \App\Models\AuditLog::query();
            if ($date) {
                $query->where('event_date', $date);
            }
            if ($entity) {
                $query->where('entity', $entity);
            }
            if ($adminId) {
                $query->where('admin_id', $adminId);
            }

            return $query->orderByDesc('event_timestamp')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->firebase_legacy_key ?: (string) $row->id,
                        'action' => $row->action,
                        'entity' => $row->entity,
                        'entity_id' => $row->entity_id,
                        'admin_id' => $row->admin_id,
                        'admin_name' => $row->admin_name,
                        'details' => $row->details,
                        'ip' => $row->ip,
                        'timestamp' => $row->event_timestamp,
                        'date' => optional($row->event_date)->format('Y-m-d'),
                    ];
                })->all();
        }

        // Bound the transfer server-side instead of reading the whole node.
        // - date given  -> query by 'date' child (needs .indexOn ["date"])
        // - no date     -> only the latest $limit by timestamp
        $reference = $this->database->getReference('audit_logs');
        if ($date) {
            $reference = $reference->orderByChild('date')->equalTo($date);
        } else {
            $reference = $reference->orderByChild('timestamp')->limitToLast($limit);
        }
        $snapshot = $reference->getSnapshot();

        $logs = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $id => $log) {
                if (!is_array($log)) continue;

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

    // ==========================================
    // SHIFT HANDOVER NOTES
    // ==========================================

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

    // ========================================
    // SUPPLIER MANAGEMENT
    // ========================================

    /**
     * Get supplier by ID
     */
    public function getSupplierById(string $id): ?array
    {
        return app(\App\Repositories\Contracts\SupplierRepositoryInterface::class)->find($id);
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
     * Bulk cancel pending/in_progress waiter tasks by date and optional task_type filter.
     *
     * @param  string  $date          Y-m-d
     * @param  string|null  $taskType  filter (e.g. 'rack_check') or null for all
     * @param  string  $note          reason note
     * @return int     jumlah task yg ter-cancel
     */
    // ========================================================================
    // AI BALANCING: Weighted scoring for rack_check assignment
    // Weights: balance=50%, quality=30%, speed=10%, recent=10%
    // ========================================================================

    /**
     * Cache for historical balancing data (per scanner run).
     */
    private ?array $rackBalancingCache = null;

    // ─────────────────────────────────────────────────────────────────────────
    // SIMPLE LOWEST LOAD (rack-check wizard)
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // ROUND ROBIN SIMPLE (rack-check wizard, mode "Giliran Tetap")
    // ─────────────────────────────────────────────────────────────────────────

    // =========================================================================
    // Planning Cek Rak Methods
    // =========================================================================

    protected function getWaiterMonthlyPointsForPriority(string $waiterId, string $date): int
    {
        if ($waiterId === '' || $date === '') {
            return 0;
        }

        try {
            $progress = app(BonusService::class)->getWaiterProgress($waiterId);

            return max(0, (int) ($progress['net_points'] ?? 0));
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

}
