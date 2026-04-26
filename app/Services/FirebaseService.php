<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;

class FirebaseService
{
    protected $database;

    protected $auth;

    public function __construct(Database $database, Auth $auth)
    {
        $this->database = $database;
        $this->auth = $auth;
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
                $waiters[] = array_merge(['id' => $key], $waiter);
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
    public function addAllowedEmailWithPassword($email, $name, $passwordHash = null)
    {
        $payload = [
            'email' => strtolower(trim((string) $email)),
            'name' => trim((string) $name),
            'is_active' => true,
            'created_at' => time(),
        ];

        if ($passwordHash) {
            $payload['password_hash'] = $passwordHash;
        }

        $this->database->getReference('allowed_waiters')->push($payload);
    }

    /**
     * Update allowed email
     */
    public function updateAllowedEmail($id, $data)
    {
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
     */
    public function getRacks()
    {
        $reference = $this->database->getReference('waiter_racks');
        $snapshot = $reference->getSnapshot();

        $racks = [];
        if ($snapshot->exists()) {
            foreach ($snapshot->getValue() as $key => $rack) {
                $racks[] = array_merge(['id' => $key], $rack);
            }
        }

        usort($racks, function ($a, $b) {
            return ($a['name'] ?? '') <=> ($b['name'] ?? '');
        });

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
        $targetWaiters = $this->resolveTargetWaiters($assignmentType, $assignedWaiterId);
        $count = 0;

        foreach ($targetWaiters as $waiter) {
            $taskData = $this->buildWaiterTaskPayload($data, $waiter, [
                'is_recurring_instance' => false,
                'scheduled_time' => null,
                'scheduled_for_date' => null,
                'source_template_id' => null,
                'time_limit_minutes' => null,
                'deadline_at' => null,
                'recurrence_type' => null,
            ]);

            $this->database->getReference('waiter_tasks')->push($taskData);
            $count++;
        }

        return $count;
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
        return array_values(array_filter($this->getWaiterTasks(), function ($task) use ($waiterId) {
            return (string) ($task['assigned_waiter_id'] ?? '') === (string) $waiterId;
        }));
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

        return array_values(array_filter($this->getWaiterActivityReports(), function ($report) use ($waiterId, $date) {
            return (string) ($report['waiter_id'] ?? '') === (string) $waiterId
                && (string) ($report['report_date'] ?? '') === $date;
        }));
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
        $photoProofDataUrl = null
    ) {
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
        if ($currentStatus !== 'pending') {
            return [
                'success' => false,
                'message' => 'Tugas ini sudah tidak aktif.',
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
                        'message' => 'Barcode rak target untuk task ini belum terdaftar. Hubungi supervisor.',
                    ];
                }

                $expectedBarcode = $masterBarcode;
            }

            $providedBarcode = strtoupper(trim((string) $scannedBarcode));
            $validatedExpectedBarcode = $expectedBarcode;

            if ($expectedBarcode === '') {
                return [
                    'success' => false,
                    'message' => 'Barcode rak untuk tugas ini belum terdaftar. Hubungi supervisor.',
                ];
            }

            if ($providedBarcode === '') {
                return [
                    'success' => false,
                    'message' => 'Task ini wajib scan barcode rak sebelum verifikasi selesai.',
                ];
            }

            if ($providedBarcode !== $expectedBarcode) {
                return [
                    'success' => false,
                    'message' => 'Barcode tidak sesuai dengan rak target. Silakan scan ulang barcode rak yang benar.',
                ];
            }
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

        $updates = [
            'status' => $status,
            'completed_at' => $now,
            'completed_by_waiter_id' => (string) $waiterId,
            'completed_by_waiter_name' => (string) $waiterName,
            'completed_by_waiter_email' => (string) $waiterEmail,
        ];

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

        if ($requiresPhotoProof || $validatedPhotoProofDataUrl !== '') {
            $hasPhotoProof = $validatedPhotoProofDataUrl !== '';
            $updates['completed_photo_proof_url'] = $hasPhotoProof ? $validatedPhotoProofDataUrl : null;
            $updates['completed_photo_proof_mime_type'] = $hasPhotoProof ? ($normalizedPhoto['mime_type'] ?? null) : null;
            $updates['completed_photo_proof_size_bytes'] = $hasPhotoProof ? (int) ($normalizedPhoto['size_bytes'] ?? 0) : null;
            $updates['photo_proof_uploaded_at'] = $hasPhotoProof ? $now : null;
        }

        if (! empty($note)) {
            $updates['completed_note'] = $note;
        }

        $taskReference->update($updates);

        return [
            'success' => true,
            'message' => 'Tugas berhasil diverifikasi.',
        ];
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
        $recurrenceType = $data['recurrence_type'] ?? 'daily';
        $assignmentType = $data['assignment_type'] ?? 'all';
        $assignedWaiterId = $assignmentType === 'single' ? ($data['assigned_waiter_id'] ?? null) : null;
        $assignedWaiter = $assignedWaiterId ? $this->getWaiterById($assignedWaiterId) : null;

        $templateData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'assigned_by' => $data['assigned_by'] ?? 'Supervisor',
            'task_type' => $data['task_type'] ?? 'general',
            'requires_barcode_scan' => (bool) ($data['requires_barcode_scan'] ?? false),
            'requires_photo_proof' => (bool) ($data['requires_photo_proof'] ?? false),
            'rack_target_scope' => $data['rack_target_scope'] ?? null,
            'rack_id' => $data['rack_id'] ?? null,
            'rack_name' => $data['rack_name'] ?? null,
            'rack_location' => $data['rack_location'] ?? null,
            'rack_barcode_value' => $data['rack_barcode_value'] ?? null,
            'assignment_type' => $assignmentType,
            'assigned_waiter_id' => $assignmentType === 'single' ? ($assignedWaiter['id'] ?? $assignedWaiterId) : null,
            'assigned_waiter_name' => $assignmentType === 'single' ? ($assignedWaiter['name'] ?? null) : null,
            'assigned_waiter_email' => $assignmentType === 'single' ? ($assignedWaiter['email'] ?? null) : null,
            'schedule_time' => $data['schedule_time'],
            'time_limit_minutes' => (int) ($data['time_limit_minutes'] ?? 0),
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) ($data['weekly_day'] ?? date('N')) : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) ($data['interval_days'] ?? 1) : null,
            'recurrence_anchor_date' => $data['recurrence_anchor_date'] ?? date('Y-m-d'),
            'is_active' => true,
            'created_at' => time(),
            'last_generated_date' => null,
        ];

        $this->database->getReference('waiter_task_templates')->push($templateData);
    }

    /**
     * Get recurring waiter task templates.
     */
    public function getRecurringWaiterTaskTemplates()
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

        $updates = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'schedule_time' => $data['schedule_time'],
            'time_limit_minutes' => (int) ($data['time_limit_minutes'] ?? 0),
            'recurrence_type' => $recurrenceType,
            'weekly_day' => $recurrenceType === 'weekly' ? (int) ($data['weekly_day'] ?? date('N')) : null,
            'interval_days' => $recurrenceType === 'every_n_days' ? (int) ($data['interval_days'] ?? 1) : null,
            'recurrence_anchor_date' => $anchorDate,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ];

        $this->database->getReference('waiter_task_templates/'.$id)->update($updates);
    }

    /**
     * Generate due recurring waiter tasks.
     */
    public function generateDueRecurringWaiterTasks()
    {
        $templates = $this->getRecurringWaiterTaskTemplates();
        $generatedCount = 0;
        $todayDate = date('Y-m-d');
        $currentTime = date('H:i');
        $existingRecurringMap = $this->getExistingWaiterRecurringMapForDate($todayDate);

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
            $recurrenceMatchedToday = $this->isTemplateDueForDate($template, $todayDate);

            if ($alreadyGeneratedToday || ! $isDueToday || ! $recurrenceMatchedToday) {
                continue;
            }

            $targetWaiters = $this->resolveTargetWaiters(
                $template['assignment_type'] ?? 'all',
                $template['assigned_waiter_id'] ?? null
            );

            if (empty($targetWaiters)) {
                continue;
            }

            $timeLimitMinutes = (int) ($template['time_limit_minutes'] ?? 0);
            $deadlineAt = null;
            if ($timeLimitMinutes > 0) {
                $scheduleTimestamp = $this->buildScheduledTimestamp($todayDate, $scheduleTime);
                $deadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
            }

            $generatedForTemplate = 0;

            foreach ($targetWaiters as $waiter) {
                $mapKey = $this->buildWaiterRecurringInstanceKey($template['id'], $waiter['id'] ?? null);
                if (isset($existingRecurringMap[$mapKey])) {
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
                    'scheduled_time' => $scheduleTime,
                    'scheduled_for_date' => $todayDate,
                    'source_template_id' => $template['id'],
                    'time_limit_minutes' => $timeLimitMinutes > 0 ? $timeLimitMinutes : null,
                    'deadline_at' => $deadlineAt,
                    'recurrence_type' => $template['recurrence_type'] ?? 'daily',
                ]);

                $this->database->getReference('waiter_tasks')->push($taskData);
                $existingRecurringMap[$mapKey] = true;
                $generatedForTemplate++;
                $generatedCount++;
            }

            if ($generatedForTemplate > 0) {
                $this->database->getReference('waiter_task_templates/'.$template['id'])->update([
                    'last_generated_date' => $todayDate,
                ]);
            }
        }

        return $generatedCount;
    }

    /**
     * Mark overdue waiter tasks.
     */
    public function markOverdueWaiterTasks()
    {
        $reference = $this->database->getReference('waiter_tasks');
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return 0;
        }

        $now = time();
        $updates = [];
        $overdueCount = 0;

        foreach ($snapshot->getValue() as $taskId => $task) {
            $status = $task['status'] ?? 'pending';
            $deadlineAt = (int) ($task['deadline_at'] ?? 0);

            if ($status !== 'pending' || $deadlineAt <= 0 || $now <= $deadlineAt) {
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
            $reference->update($updates);
        }

        return $overdueCount;
    }

    /**
     * Delete recurring waiter template.
     */
    public function deleteRecurringWaiterTaskTemplate($id)
    {
        $this->database->getReference('waiter_task_templates/'.$id)->remove();
    }

    /**
     * Resolve target waiters from assignment.
     */
    protected function resolveTargetWaiters($assignmentType, $assignedWaiterId = null)
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

        return $this->getActiveWaiters();
    }

    /**
     * Build waiter task payload from base data + target waiter.
     */
    protected function buildWaiterTaskPayload(array $data, array $waiter, array $overrides = [])
    {
        $payload = [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'task_type' => $data['task_type'] ?? 'general',
            'requires_barcode_scan' => (bool) ($data['requires_barcode_scan'] ?? false),
            'requires_photo_proof' => (bool) ($data['requires_photo_proof'] ?? false),
            'rack_target_scope' => $data['rack_target_scope'] ?? null,
            'rack_id' => $data['rack_id'] ?? null,
            'rack_name' => $data['rack_name'] ?? null,
            'rack_location' => $data['rack_location'] ?? null,
            'rack_barcode_value' => $data['rack_barcode_value'] ?? null,
            'status' => 'pending',
            'assigned_by' => $data['assigned_by'] ?? 'Supervisor',
            'assignment_type' => $data['assignment_type'] ?? 'single',
            'assigned_waiter_id' => $waiter['id'] ?? null,
            'assigned_waiter_name' => $waiter['name'] ?? null,
            'assigned_waiter_email' => $waiter['email'] ?? null,
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
            'completed_photo_proof_url' => null,
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
        ];

        return array_merge($payload, $overrides);
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
     * Existing recurring waiter instances for a date.
     */
    protected function getExistingWaiterRecurringMapForDate($date)
    {
        $reference = $this->database->getReference('waiter_tasks');
        $snapshot = $reference->getSnapshot();
        $map = [];

        if (! $snapshot->exists()) {
            return $map;
        }

        foreach ($snapshot->getValue() as $task) {
            $sourceTemplateId = $task['source_template_id'] ?? null;
            $scheduledDate = $task['scheduled_for_date'] ?? null;
            $assignedWaiterId = $task['assigned_waiter_id'] ?? null;
            $status = $task['status'] ?? 'pending';

            if (! $sourceTemplateId || ! $assignedWaiterId || $scheduledDate !== $date || $status !== 'pending') {
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
        $reference = $this->database->getReference('cashier_tasks');
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return 0;
        }

        $now = time();
        $updates = [];
        $overdueCount = 0;

        foreach ($snapshot->getValue() as $taskId => $task) {
            $status = $task['status'] ?? 'pending';
            $deadlineAt = (int) ($task['deadline_at'] ?? 0);

            if ($status !== 'pending' || $deadlineAt <= 0 || $now <= $deadlineAt) {
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
            $reference->update($updates);
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
        $reference = $this->database->getReference('cashier_tasks');
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return false;
        }

        foreach ($snapshot->getValue() as $task) {
            $sourceTemplateId = $task['source_template_id'] ?? null;
            $scheduledDate = $task['scheduled_for_date'] ?? null;
            $status = $task['status'] ?? 'pending';

            if ($sourceTemplateId === $templateId && $scheduledDate === $date && $status === 'pending') {
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
        $reference = $this->database->getReference('cashier_tasks');
        $snapshot = $reference->getSnapshot();

        if (! $snapshot->exists()) {
            return false;
        }

        foreach ($snapshot->getValue() as $task) {
            $sourceTemplateId = $task['source_template_id'] ?? null;
            $scheduledDate = $task['scheduled_for_date'] ?? null;
            $status = $task['status'] ?? 'pending';

            if ($sourceTemplateId === $templateId && $scheduledDate === $date && $status === 'done') {
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
        $reference = $this->database->getReference('cashier_tasks');
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

        foreach ($snapshot->getValue() as $taskId => $task) {
            $sourceTemplateId = $task['source_template_id'] ?? null;
            $scheduledDate = $task['scheduled_for_date'] ?? null;
            $status = $task['status'] ?? 'pending';

            if ($sourceTemplateId !== $templateId || $scheduledDate !== $date || $status !== 'pending') {
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
            $reference->update($updates);
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
}
