<?php

namespace App\Services;

use App\Models\WaiterTask;
use Kreait\Firebase\Contract\Database;

class WaiterTaskFirebaseService
{
    protected $database;
    protected FirebaseService $firebase;
    protected RackStockFirebaseService $rack;
    protected ShiftScheduleFirebaseService $shift;
    protected array $requestCache = [];

    public function __construct(
        Database $database,
        FirebaseService $firebase,
        RackStockFirebaseService $rack,
        ShiftScheduleFirebaseService $shift
    ) {
        $this->database = $database;
        $this->firebase = $firebase;
        $this->rack = $rack;
        $this->shift = $shift;
    }

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

        // Filter out waiters who are off today (skip for single assignment — intentional direct assign)
        $scheduledDate = $data['scheduled_for_date'] ?? date('Y-m-d');
        if ($assignmentType !== 'single') {
            $targetWaiters = array_values(array_filter($targetWaiters, function ($waiter) use ($scheduledDate) {
                $wId = (string) ($waiter['id'] ?? '');
                if ($wId === '') {
                    return true;
                }
                return $this->shift->isWorkingDay($wId, $scheduledDate);
            }));
        }

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

            $newRef = $this->database->getReference('waiter_tasks')->push($taskData);
            $this->dualWriteWaiterTaskToMysql((string) $newRef->getKey(), $taskData);
            $createdEntries[] = ['waiter' => $waiter, 'task' => $taskData];
            $count++;
        }

        return ['count' => $count, 'entries' => $createdEntries];
    }

    /**
     * Bulk reassign pending/in_progress tasks from one waiter to another for a given date.
     */
    public function bulkReassignPendingTasks(string $fromWaiterId, string $toWaiterId, string $date): int
    {
        $toWaiter = $this->firebase->getWaiterById($toWaiterId);
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
     * Get all waiter tasks.
     * @deprecated Use getWaiterTasksByDateRange() instead to avoid downloading entire node.
     */
    public function getWaiterTasks()
    {
        return app(\App\Repositories\Contracts\WaiterTaskRepositoryInterface::class)->all();
    }

    /**
     * Get waiter tasks filtered by date range (uses scheduled_for_date index).
     * Much more efficient than getWaiterTasks() for bounded queries.
     */
    public function getWaiterTasksByDateRange(string $startDate, string $endDate): array
    {
        return app(\App\Repositories\Contracts\WaiterTaskRepositoryInterface::class)->forDateRange($startDate, $endDate);
    }

    /**
     * Get tasks assigned to one waiter.
     */
    public function getWaiterTasksByWaiterId($waiterId, ?string $dateFrom = null, ?string $dateTo = null)
    {
        return app(\App\Repositories\Contracts\WaiterTaskRepositoryInterface::class)
            ->forWaiter((string) $waiterId, $dateFrom, $dateTo);
    }

    /**
     * Get waiter tasks for a specific date only (uses scheduled_for_date query).
     * More efficient than getWaiterTasksByWaiterId + PHP filter for single-date lookups.
     */
    public function getWaiterTasksForDate(string $waiterId, string $date): array
    {
        return app(\App\Repositories\Contracts\WaiterTaskRepositoryInterface::class)->forWaiterOnDate($waiterId, $date);
    }

    /**
     * Get ALL tasks for a specific date (all waiters).
     */
    public function getWaiterTasksByDate(string $date): array
    {
        return app(\App\Repositories\Contracts\WaiterTaskRepositoryInterface::class)->forDate($date);
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

        $legacyKey = null;
        if (config('features.legacy_write_activity_reports')) {
            $legacyKey = (string) $this->database->getReference('waiter_activity_reports')->push($payload)->getKey();
        }

        if (config('features.mysql_activity_reports')) {
            try {
                $createdAt = $payload['created_at'] ?? null;
                $attrs = [
                    'waiter_id' => (string) ($payload['waiter_id'] ?? ''),
                    'waiter_name' => $payload['waiter_name'] ?? null,
                    'waiter_email' => $payload['waiter_email'] ?? null,
                    'report_date' => $payload['report_date'] ?? now()->format('Y-m-d'),
                    'activity_text' => $payload['activity_text'] ?? null,
                    'activity_items' => $payload['activity_items'] ?? null,
                    'event_timestamp' => is_numeric($createdAt) ? (int) $createdAt : null,
                ];
                if ($legacyKey !== null) {
                    \App\Models\WaiterActivityReport::updateOrCreate(['firebase_legacy_key' => $legacyKey], $attrs);
                } else {
                    \App\Models\WaiterActivityReport::create($attrs);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'success' => true,
            'id' => $legacyKey,
        ];
    }

    /**
     * Get all waiter activity reports.
     */
    public function getWaiterActivityReports(?string $dateFrom = null, ?string $dateTo = null): array
    {
        // MySQL read path (flag-gated). Source of truth; avoids full RTDB read.
        if (config('features.mysql_activity_reports')) {
            $query = \App\Models\WaiterActivityReport::query();
            if ($dateFrom !== null) {
                $query->where('report_date', '>=', $dateFrom);
            }
            if ($dateTo !== null) {
                $query->where('report_date', '<=', $dateTo);
            }

            return $query->orderByDesc('event_timestamp')
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->firebase_legacy_key ?: (string) $row->id,
                        'waiter_id' => $row->waiter_id,
                        'waiter_name' => $row->waiter_name,
                        'waiter_email' => $row->waiter_email,
                        'report_date' => optional($row->report_date)->format('Y-m-d'),
                        'activity_text' => $row->activity_text,
                        'activity_items' => $row->activity_items,
                        'created_at' => $row->event_timestamp,
                    ];
                })->all();
        }

        // Bound by report_date when a range is given (needs .indexOn ["report_date"]),
        // else fall back to full read (legacy callers).
        $reference = $this->database->getReference('waiter_activity_reports');
        if ($dateFrom !== null || $dateTo !== null) {
            $reference = $reference->orderByChild('report_date')
                ->startAt($dateFrom ?: '0000-00-00')
                ->endAt($dateTo ?: '9999-12-31');
        }
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
            // Distinguish: task never had an assigned waiter vs waiter mismatch (reassigned)
            if ($assignedWaiterId !== '' && $assignedWaiterId !== (string) $waiterId) {
                return [
                    'success'    => false,
                    'message'    => 'Tugas ini sudah tidak ditugaskan kepada Anda. Silakan refresh halaman tugas.',
                    'error_code' => 'not_assigned',
                ];
            }

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
                $rack = $this->rack->getRackById($rackId);
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
                $rackLabel = trim((string) ($task['rack_name'] ?? $task['rack_code'] ?? $rackId));
                return [
                    'success' => false,
                    'message' => "QR code tidak sesuai dengan rak target ({$rackLabel}). Pastikan Anda scan QR code pada rak yang benar.",
                    'error_code' => 'barcode_mismatch',
                    'expected_rack' => $rackLabel,
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
            // Offload to Storage; keep inline data URL only if upload fails.
            $uploadedProofUrl = $this->uploadTaskPhoto($validatedPhotoProofDataUrl, (string) $taskId, 'proof');
            $completionEntry['photo_proof_url'] = $uploadedProofUrl !== '' ? $uploadedProofUrl : $validatedPhotoProofDataUrl;
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
            // Reuse the per-completion uploaded URL if present, else upload now.
            $proofUrl = $completionEntry['photo_proof_url'] ?? '';
            if ($hasPhotoProof && $proofUrl === '') {
                $uploaded = $this->uploadTaskPhoto($validatedPhotoProofDataUrl, (string) $taskId, 'proof');
                $proofUrl = $uploaded !== '' ? $uploaded : $validatedPhotoProofDataUrl;
            }
            $updates['completed_photo_proof_url'] = $hasPhotoProof ? $proofUrl : null;
            $updates['completed_photo_proof_mime_type'] = $hasPhotoProof ? ($normalizedPhoto['mime_type'] ?? null) : null;
            $updates['completed_photo_proof_size_bytes'] = $hasPhotoProof ? (int) ($normalizedPhoto['size_bytes'] ?? 0) : null;
            $updates['photo_proof_uploaded_at'] = $hasPhotoProof ? $now : null;
        }

        // Store photo before (kondisi awal) if provided
        if ($validatedPhotoBeforeDataUrl !== '') {
            $uploadedBefore = $this->uploadTaskPhoto($validatedPhotoBeforeDataUrl, (string) $taskId, 'before');
            $updates['completed_photo_before_url'] = $uploadedBefore !== '' ? $uploadedBefore : $validatedPhotoBeforeDataUrl;
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
        $assignedWaiter = $assignedWaiterId ? $this->firebase->getWaiterById($assignedWaiterId) : null;

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
            // Multi-rak support
            'name' => $data['name'] ?? $data['title'] ?? '',
            'racks' => is_array($data['racks'] ?? null) ? array_values($data['racks']) : [],
            // Wizard flags
            'allow_note' => (bool) ($data['allow_note'] ?? false),
            'enable_empty_product_report' => (bool) ($data['enable_empty_product_report'] ?? false),
            'simple_lowest_load_enabled' => (bool) ($data['simple_lowest_load_enabled'] ?? false),
            'skip_when_no_eligible_waiter' => (bool) ($data['skip_when_no_eligible_waiter'] ?? true),
            'daily_cap_mode' => (string) ($data['daily_cap_mode'] ?? 'shift_aware'),
            'full_shift_daily_cap' => array_key_exists('full_shift_daily_cap', $data) ? $data['full_shift_daily_cap'] : null,
            'partial_shift_daily_cap' => array_key_exists('partial_shift_daily_cap', $data) ? $data['partial_shift_daily_cap'] : null,
        ];

        $this->database->getReference('waiter_task_templates')->push($templateData);
    }

    /**
     * Get all recurring waiter task templates.
     */
    public function getRecurringWaiterTaskTemplates(): array
    {
        $cacheKey = 'recurringWaiterTaskTemplates';
        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

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

        $this->requestCache[$cacheKey] = $templates;
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

        // ── Rack Check Wizard fields ──────────────────────────────────────
        // These are optional — only set when editing rack_check templates
        // from the new wizard (RackCheckTemplateController).
        $rackCheckFields = [
            'requires_barcode_scan',
            'requires_photo_before',
            'requires_photo_proof',
            'allow_note',
            'enable_empty_product_report',
            'assignment_strategy',
            'simple_lowest_load_enabled',
            'assigned_waiter_id',
            'assigned_waiter_role',
            'selected_waiter_ids',
            'rack_id',
            'rack_name',
            'rack_location',
            'rack_barcode_value',
            'rack_type',
            'rack_target_scope',
            'assignment_type',
            'schedule_mode',
            'deadline_mode',
            'deadline_before_end_minutes',
            'shift_offset_minutes',
            'skip_when_no_eligible_waiter',
            'daily_cap_mode',
            'full_shift_daily_cap',
            'partial_shift_daily_cap',
            'weekly_day',
            'interval_days',
            'updated_at',
        ];
        foreach ($rackCheckFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        $this->database->getReference('waiter_task_templates/'.$id)->update($updates);
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
            $totalMissedDays = 0;
            foreach ($period as $date) {
                $totalMissedDays++;
                $datesToProcess[] = $date->format('Y-m-d');
                $count++;
                if ($count >= $maxDays) {
                    break;
                }
            }

            // Log audit jika ada hari yang terlewat melebihi cap
            if ($totalMissedDays > $maxDays || $count >= $maxDays) {
                $skippedDays = $totalMissedDays - $count;
                if ($skippedDays > 0) {
                    $this->firebase->logAuditAction('catch_up_cap_exceeded', 'system', null, [
                        'last_run_at' => $lastRunAt,
                        'today' => $todayDate,
                        'total_missed_days' => $totalMissedDays,
                        'processed_days' => $count,
                        'skipped_days' => $skippedDays,
                        'message' => "Sistem tidak aktif {$totalMissedDays} hari. Hanya {$count} hari terakhir diproses, {$skippedDays} hari lebih lama dilewati.",
                    ]);
                }
            }
        }

        $generatedCount = 0;
        $processedDates = [];
        foreach ($datesToProcess as $targetDate) {
            try {
                $generatedCount += $this->generateRecurringTasksForDate($targetDate, $targetDate !== $todayDate, $force);
                $processedDates[] = $targetDate;
            } catch (\Throwable $e) {
                report($e);
                // Stop processing further dates — don't update last_run_at
                // so next run will retry from this date onwards.
                break;
            }
        }

        // Hanya update last_run_at ke tanggal terakhir yang berhasil diproses
        if (! empty($processedDates)) {
            $lastProcessedDate = end($processedDates);
            $lastRunRef->set($lastProcessedDate);
        }

        return [
            'generated' => $generatedCount,
            'dates' => $processedDates,
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

        // In-memory counters shared across all simple_lowest_load / round_robin_simple
        // templates in this run. Prevents stale Firebase reads from hiding assignments
        // made earlier in the same loop (eventual-consistency race).
        // $simpleLoadCounter[waiterId]  = total rack_check tasks assigned today (Firebase + this run)
        // $simpleLoadAssignedRacks[waiterId] = [rack_id, ...] assigned today (same-rack dedup)
        $simpleLoadCounter = null;      // null = not yet initialized
        $simpleLoadAssignedRacks = [];  // waiterId => rack_id[]

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

            // === SIMPLE LOWEST LOAD STRATEGY ===
            // Mode baru untuk wizard /admin/rack-check/templates.
            // Bypass AI balancing, peer fallback, reschedule. Pilih waiter dgn beban
            // rack_check paling ringan dari selected_waiter_ids. Lock-based dedupe.
            if ($assignmentStrategy === 'simple_lowest_load') {
                try {
                    // Init shared counter once per run from Firebase (only for this strategy block)
                    if ($simpleLoadCounter === null) {
                        $simpleLoadCounter = [];
                        $simpleLoadAssignedRacks = [];
                        try {
                            $todaySnap = $this->database->getReference('waiter_tasks')
                                ->orderByChild('scheduled_for_date')
                                ->equalTo($effectiveTargetDate)
                                ->getSnapshot()->getValue();
                            if (is_array($todaySnap)) {
                                foreach ($todaySnap as $t) {
                                    if (($t['task_type'] ?? '') !== 'rack_check') continue;
                                    if ((string)($t['status'] ?? '') === 'cancelled') continue;
                                    $wid = (string)($t['assigned_waiter_id'] ?? '');
                                    if ($wid === '') continue;
                                    $simpleLoadCounter[$wid] = ($simpleLoadCounter[$wid] ?? 0) + 1;
                                    $rid = (string)($t['rack_id'] ?? '');
                                    if ($rid !== '') {
                                        $simpleLoadAssignedRacks[$wid][] = $rid;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // Fallback: start from zero counts
                        }
                    }

                    $simpleResult = $this->processSimpleLowestLoadTemplate(
                        $template,
                        $effectiveTargetDate,
                        $isCatchUp,
                        $force,
                        $simpleLoadCounter,
                        $simpleLoadAssignedRacks
                    );
                    $producedCount = (int) ($simpleResult['generated_count'] ?? 0);
                    if ($producedCount > 0) {
                        $generatedCount += $producedCount;
                        $this->database->getReference('waiter_task_templates/'.$template['id'])->update([
                            'last_generated_date' => $effectiveTargetDate,
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
                continue;
            }

            // === ROUND ROBIN SIMPLE STRATEGY ===
            // Mode "Giliran Tetap": bergiliran sesuai urutan selected_waiter_ids.
            // Petugas libur dilewati ke giliran berikutnya. Counter persisten.
            if ($assignmentStrategy === 'round_robin_simple') {
                try {
                    $rrResult = $this->processRoundRobinSimpleTemplate(
                        $template,
                        $effectiveTargetDate,
                        $isCatchUp,
                        $force
                    );
                    $producedCount = (int) ($rrResult['generated_count'] ?? 0);
                    if ($producedCount > 0) {
                        $generatedCount += $producedCount;
                        $this->database->getReference('waiter_task_templates/'.$template['id'])->update([
                            'last_generated_date' => $effectiveTargetDate,
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
                continue;
            }

            // === LEGACY RACK_CHECK (role_round_robin) — DISABLED ===
            // Template rack_check lama (dibuat via /admin/tasks/rack-check) sudah digantikan
            // oleh wizard baru (/admin/rack-check/templates) dengan strategy simple_lowest_load
            // atau round_robin_simple. Skip agar tidak double-generate.
            if ((string) ($template['task_type'] ?? 'general') === 'rack_check'
                && $assignmentStrategy === 'role_round_robin') {
                continue;
            }

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

                    // BUG FIX (#14): Use persisted counter for fairness during
                    // roster changes. Falls back to calendar offset internally
                    // if counter is empty/inconsistent.
                    $pickedId = $this->resolveRollingWaiterIdByCounter(
                        (string) ($template['id'] ?? ''),
                        $rollingIds,
                        $period,
                        $effectiveTargetDate,
                        $anchor !== '' ? $anchor : null
                    );

                    // Calendar offset still needed for fallback iteration below
                    $offset = $this->resolveRotationOffsetForPeriod(
                        $effectiveTargetDate,
                        $period,
                        $anchor !== '' ? $anchor : null
                    );

                    $pickedWaiter = null;
                    foreach ($targetWaiters as $w) {
                        if ((string) ($w['id'] ?? '') === $pickedId) {
                            $pickedWaiter = $w;
                            break;
                        }
                    }
                    if (! $pickedWaiter) {
                        try {
                            $maybeWaiter = $this->firebase->getWaiterById($pickedId);
                            if ($maybeWaiter && ($maybeWaiter['is_active'] ?? true)) {
                                $pickedWaiter = $maybeWaiter;
                            }
                        } catch (\Throwable $e) {
                            $pickedWaiter = null;
                        }
                    }
                    if ($pickedWaiter) {
                        // Cek apakah picked waiter libur hari ini
                        $pickedWaiterId = (string) ($pickedWaiter['id'] ?? '');
                        if ($pickedWaiterId !== '' && ! $this->shift->isWorkingDay($pickedWaiterId, $effectiveTargetDate)) {
                            // Fallback: cari waiter berikutnya dalam daftar rolling yang masuk hari ini
                            $fallbackWaiter = null;
                            $rollingCount = count($rollingIds);
                            for ($fallbackIdx = 1; $fallbackIdx < $rollingCount; $fallbackIdx++) {
                                $nextIdx = ($offset + $fallbackIdx) % $rollingCount;
                                $nextId = $rollingIds[$nextIdx];
                                // Cek apakah waiter ini masuk hari ini
                                if ($this->shift->isWorkingDay($nextId, $effectiveTargetDate)) {
                                    foreach ($targetWaiters as $w) {
                                        if ((string) ($w['id'] ?? '') === $nextId) {
                                            $fallbackWaiter = $w;
                                            break 2;
                                        }
                                    }
                                    // Coba fetch langsung
                                    try {
                                        $maybeW = $this->firebase->getWaiterById($nextId);
                                        if ($maybeW && ($maybeW['is_active'] ?? true)) {
                                            $fallbackWaiter = $maybeW;
                                            break;
                                        }
                                    } catch (\Throwable $e) {
                                        // skip
                                    }
                                }
                            }
                            if ($fallbackWaiter) {
                                $pickedWaiter = $fallbackWaiter;
                                $pickedWaiter['is_rolling_fallback'] = true;
                                $pickedWaiter['original_rolling_waiter_id'] = $pickedWaiterId;
                            }
                            // Kalau tidak ada fallback sama sekali, tetap assign ke picked waiter
                            // dengan flag off-day (perilaku lama sebagai last resort)
                        }
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
                    $shift = $this->shift->getWaiterShiftForDate($wid, $effectiveTargetDate);

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
                    return $this->shift->isWorkingDay($wId, $effectiveTargetDate);
                }));
            }

            if (! $isGeneralRolling && empty($targetWaiters)) {
                // IDEMPOTENCY GUARD: Cek apakah template ini sudah pernah di-reschedule dari tanggal ini.
                // Mencegah spam reschedule setiap 5 menit untuk shift_relative templates.
                $rescheduleMarkerRef = $this->database->getReference(
                    'reschedule_markers/' . $template['id'] . '/' . str_replace('-', '', $targetDate)
                );
                if ($rescheduleMarkerRef->getValue() !== null) {
                    continue; // Already rescheduled for this template+date, skip
                }

                // PRIORITY 1: Peer fallback (Opsi E)
                // Kalau template assignment_type=single dan single-assignee libur,
                // coba cari peer dgn role sama yang masuk hari cycle asli.
                // Untuk role/selected mode, peer set sudah complete via $originalTargetWaiters.
                $peerFallbackUsed = false;
                if ($templateAssignmentType === 'single' && $assignedWaiterRole !== null && $assignedWaiterRole !== '') {
                    try {
                        $peerWaiters = $this->firebase->getActiveWaitersByRole($assignedWaiterRole);
                        // Exclude assignee asli (sudah dicek libur)
                        $assigneeId = (string) ($originalTargetWaiters[0]['id'] ?? '');
                        $peerCandidates = array_values(array_filter($peerWaiters, function ($w) use ($effectiveTargetDate, $assigneeId) {
                            $wId = (string) ($w['id'] ?? '');
                            if ($wId === '' || $wId === $assigneeId) {
                                return false;
                            }
                            return $this->shift->isWorkingDay($wId, $effectiveTargetDate);
                        }));

                        if (! empty($peerCandidates)) {
                            // BUG FIX (#6): Pick peer with LOWEST active task load,
                            // not always alphabetical [0]. Prevents same waiter
                            // from being overburdened every time backup is needed.
                            usort($peerCandidates, function ($a, $b) use ($effectiveTargetDate) {
                                $aId = (string) ($a['id'] ?? '');
                                $bId = (string) ($b['id'] ?? '');
                                $aTasks = $this->getWaiterTasksForDate($aId, $effectiveTargetDate);
                                $bTasks = $this->getWaiterTasksForDate($bId, $effectiveTargetDate);
                                $aActive = count(array_filter($aTasks, function ($t) {
                                    $s = (string) ($t['status'] ?? 'pending');
                                    return ! in_array($s, ['done', 'cancelled'], true);
                                }));
                                $bActive = count(array_filter($bTasks, function ($t) {
                                    $s = (string) ($t['status'] ?? 'pending');
                                    return ! in_array($s, ['done', 'cancelled'], true);
                                }));
                                if ($aActive !== $bActive) {
                                    return $aActive <=> $bActive;
                                }
                                // Tie-break: alphabetical (deterministic)
                                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                            });

                            // Untuk single assignment, pilih hanya 1 peer (load paling rendah)
                            $targetWaiters = [$peerCandidates[0]];
                            $peerFallbackUsed = true;
                            $rescheduledFromDate = null;

                            // Write reschedule marker to prevent repeated processing
                            $rescheduleMarkerRef->set([
                                'type' => 'peer_fallback',
                                'peer_waiter_id' => $peerCandidates[0]['id'] ?? '',
                                'peer_selection' => 'lowest_load',
                                'created_at' => time(),
                            ]);

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
                        // Write marker so we don't retry every 5 minutes
                        $rescheduleMarkerRef->set([
                            'type' => 'reschedule_failed',
                            'created_at' => time(),
                        ]);
                        continue;
                    }

                    $isEmergencyAssignment = (bool) ($rescheduleResult['is_emergency_assignment'] ?? false);

                    // Write reschedule marker to prevent repeated processing
                    $rescheduleMarkerRef->set([
                        'type' => $isEmergencyAssignment ? 'emergency_supervisor_fallback' : 'rescheduled',
                        'new_date' => $rescheduleResult['new_date'] ?? '',
                        'created_at' => time(),
                    ]);

                    $effectiveTargetDate = (string) ($rescheduleResult['new_date'] ?? $effectiveTargetDate);
                    $targetWaiters = $rescheduleResult['waiters'] ?? [];
                    $rescheduledFromDate = (string) ($rescheduleResult['original_date'] ?? $targetDate);
                    $isToday = $effectiveTargetDate === date('Y-m-d');
                    $existingRecurringMap = $this->getExistingWaiterRecurringMapForDate($effectiveTargetDate);
                }
            } else {
                $rescheduledFromDate = null;
                $isEmergencyAssignment = false;
            }

            if ($isRackRollingTemplate) {
                // Fair distribution: pick waiter with least rack_check tasks today
                // Uses $rackCheckAssignmentCount (tracked across all templates in this run)
                if (! isset($rackCheckAssignmentCount)) {
                    // Initialize from already-generated rack_check tasks today
                    $rackCheckAssignmentCount = [];
                    try {
                        $todayTasks = $this->database->getReference('waiter_tasks')
                            ->orderByChild('scheduled_for_date')
                            ->equalTo($effectiveTargetDate)
                            ->getSnapshot()->getValue();
                        if (is_array($todayTasks)) {
                            foreach ($todayTasks as $existingTask) {
                                if (($existingTask['task_type'] ?? '') === 'rack_check'
                                    && ($existingTask['status'] ?? '') !== 'cancelled') {
                                    $ewId = (string) ($existingTask['assigned_waiter_id'] ?? '');
                                    if ($ewId !== '') {
                                        $rackCheckAssignmentCount[$ewId] = ($rackCheckAssignmentCount[$ewId] ?? 0) + 1;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Fallback: empty counts, distribute evenly from scratch
                    }
                }

                // SHIFT-AWARE DAILY CAP: filter waiters yang sudah hit max rack_check hari ini.
                // FULL shift (14j+) max 2, PAGI/SORE (8-10j) max 1, LIBUR auto-skipped via line 4148.
                // Mencegah 1 waiter di-assign 5+ task sekaligus.
                $cappedWaiters = array_values(array_filter($targetWaiters, function ($waiter) use ($rackCheckAssignmentCount, $effectiveTargetDate, $template) {
                    $wId = (string) ($waiter['id'] ?? '');
                    if ($wId === '') {
                        return true;
                    }
                    $assigned = (int) ($rackCheckAssignmentCount[$wId] ?? 0);
                    $cap = $this->rack->getRackCheckDailyCap($wId, $effectiveTargetDate, $template);
                    return $assigned < $cap;
                }));
                if (! empty($cappedWaiters)) {
                    $targetWaiters = $cappedWaiters;
                } else {
                    // SEMUA waiter sudah hit cap. Skip template ini.
                    \Log::info('[RACK_DAILY_CAP] Semua waiter di template ini sudah hit cap rack_check hari ini', [
                        'template_id' => $template['id'] ?? '',
                        'rack' => $template['rack_name'] ?? '?',
                        'date' => $effectiveTargetDate,
                        'counts' => $rackCheckAssignmentCount,
                    ]);
                    continue;
                }

                // AI Balancing: sort by weighted score (balance 50%, quality 30%, speed 10%, recent 10%)
                // Higher score = higher priority to receive this task
                usort($targetWaiters, function ($a, $b) use ($rackCheckAssignmentCount, $effectiveTargetDate) {
                    $scoreA = $this->rack->calculateRackBalancingScore(
                        (string) ($a['id'] ?? ''),
                        $effectiveTargetDate,
                        $rackCheckAssignmentCount
                    );
                    $scoreB = $this->rack->calculateRackBalancingScore(
                        (string) ($b['id'] ?? ''),
                        $effectiveTargetDate,
                        $rackCheckAssignmentCount
                    );
                    if (abs($scoreA - $scoreB) > 0.01) {
                        return $scoreB <=> $scoreA; // Highest score first
                    }
                    // Tie-break: least assigned today, then alphabetical
                    $countA = $rackCheckAssignmentCount[(string) ($a['id'] ?? '')] ?? 0;
                    $countB = $rackCheckAssignmentCount[(string) ($b['id'] ?? '')] ?? 0;
                    if ($countA !== $countB) {
                        return $countA - $countB;
                    }
                    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                });

                // Pick the highest scored waiter
                $targetWaiters = [$targetWaiters[0]];

                // Track this assignment
                $pickedWaiterId = (string) ($targetWaiters[0]['id'] ?? '');
                if ($pickedWaiterId !== '') {
                    $rackCheckAssignmentCount[$pickedWaiterId] = ($rackCheckAssignmentCount[$pickedWaiterId] ?? 0) + 1;
                }

                $templateUpdates = [];
                if ((string) ($template['assignment_type'] ?? '') !== 'role') {
                    $templateUpdates['assignment_type'] = 'role';
                    $templateUpdates['assigned_waiter_id'] = null;
                    $templateUpdates['assigned_waiter_name'] = null;
                    $templateUpdates['assigned_waiter_email'] = null;
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
                // For rack_check rolling: idempotency is per template (not per waiter)
                // because fair distribution may assign to different waiter than original
                if ($isRackRollingTemplate) {
                    $templateOnlyKey = (string) $template['id'] . '::*';
                    if (isset($existingRecurringMap[$templateOnlyKey])) {
                        continue;
                    }
                    // DEBUG: log when template passes this guard
                    \Log::info('[RACK_DEBUG] Template passed templateOnlyKey guard', [
                        'template_id' => $template['id'],
                        'title' => $template['title'] ?? '',
                        'templateOnlyKey' => $templateOnlyKey,
                        'map_keys_for_template' => array_filter(array_keys($existingRecurringMap), fn($k) => str_contains($k, (string) $template['id'])),
                        'target_waiter' => $waiter['name'] ?? $waiter['id'] ?? '',
                    ]);
                }

                $mapKey = $this->buildWaiterRecurringInstanceKey($template['id'], $waiter['id'] ?? null);
                if (isset($existingRecurringMap[$mapKey])) {
                    continue;
                }

                // Resolve per-waiter schedule time and deadline based on schedule_mode
                $waiterScheduleTime = $scheduleTime;
                $waiterDeadlineAt = null;
                $waiterId = $waiter['id'] ?? '';

                if ($templateScheduleMode === 'shift_relative' && $waiterId !== '') {
                    $waiterShift = $this->shift->getWaiterShiftForDate($waiterId, $effectiveTargetDate);
                    if ($waiterShift) {
                        $shiftStart = $waiterShift['clock_in_time'] ?? '08:00';
                        $shiftEnd = $waiterShift['clock_out_time'] ?? '17:00';

                        // Calculate schedule time: shift start + offset
                        $shiftStartTimestamp = $this->shift->buildScheduledTimestamp($effectiveTargetDate, $shiftStart);
                        $waiterScheduleTimestamp = $shiftStartTimestamp + ($templateShiftOffsetMinutes * 60);
                        $waiterScheduleTime = date('H:i', $waiterScheduleTimestamp);

                        // Check if current time has reached this waiter's schedule time
                        // EXCEPTION: untuk rack_check rolling, tetap generate task meskipun
                        // waiter belum mulai shift. Task akan muncul di portal setelah schedule_time.
                        // Ini mencegah waiter shift siang selalu ketinggalan distribusi rak.
                        if ($isToday && $currentTime < $waiterScheduleTime && !$isRackRollingTemplate) {
                            continue; // Not yet time for this waiter
                        }

                        // Calculate deadline based on deadline_mode
                        if ($templateDeadlineMode === 'before_shift_end') {
                            $shiftEndTimestamp = $this->shift->buildScheduledTimestamp($effectiveTargetDate, $shiftEnd);
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
                            $scheduleTimestamp = $this->shift->buildScheduledTimestamp($effectiveTargetDate, $scheduleTime);
                            $waiterDeadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
                        }
                    }
                } else {
                    // Fixed mode: original behavior
                    if ($timeLimitMinutes > 0) {
                        $scheduleTimestamp = $this->shift->buildScheduledTimestamp($effectiveTargetDate, $scheduleTime);
                        $waiterDeadlineAt = $scheduleTimestamp + ($timeLimitMinutes * 60);
                    }
                }

                $recurringInstanceKey = $this->buildWaiterRecurringInstanceIdentity(
                    $template['id'],
                    // For rack_check rolling: use wildcard instead of waiter ID
                    // so the node key is deterministic per template+date (not per waiter).
                    // This prevents duplicate tasks when AI Balancing picks different waiter on re-run.
                    $isRackRollingTemplate ? '*' : ($waiter['id'] ?? null),
                    $effectiveTargetDate
                );
                $taskNodeKey = $this->buildWaiterRecurringTaskNodeKey($recurringInstanceKey);
                $taskReference = $this->database->getReference('waiter_tasks/'.$taskNodeKey);
                $existingTaskSnap = $taskReference->getSnapshot();

                // TRANSITION GUARD: For rack rolling, if wildcard node doesn't exist yet
                // but templateOnlyKey is already in existingRecurringMap (from old per-waiter nodes),
                // skip to prevent duplicates during migration from old to new node key format.
                if ($isRackRollingTemplate && !$existingTaskSnap->exists()) {
                    $templateOnlyKeyCheck = (string) $template['id'] . '::*';
                    if (isset($existingRecurringMap[$templateOnlyKeyCheck])) {
                        continue;
                    }
                }
                if ($existingTaskSnap->exists()) {
                    $existingTaskValue = (array) $existingTaskSnap->getValue();
                    $existingStatus = (string) ($existingTaskValue['status'] ?? '');
                    // Kalau task lama berstatus cancelled, allow overwrite (regenerate fresh).
                    // Kalau status lain (pending/in_progress/done/overdue), skip seperti biasa.
                    // EXCEPTION: cancelled dengan cancel_reason tertentu = final, jangan re-generate.
                    if ($existingStatus !== 'cancelled') {
                        $existingRecurringMap[$mapKey] = true;

                        continue;
                    }
                    $cancelReason = (string) ($existingTaskValue['cancel_reason'] ?? '');
                    $noRegenReasons = ['role_mismatch_fix', 'anomaly_from_role_mismatch_fix', 'admin_manual', 'bulk_cancel', 'duplicate_rack_fix', 'libur_off_day_correction'];
                    if (in_array($cancelReason, $noRegenReasons, true)) {
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

                // BELT-AND-SUSPENDERS GUARD: rack_check task tidak boleh assign ke waiter LIBUR.
                // Filter di line 4148 (filter $targetWaiters) seharusnya sudah skip LIBUR,
                // tapi defensive re-check di sini menutup edge cases (cache stale,
                // race condition antara filter dan persist, dll).
                $taskTypeForGuard = (string) ($template['task_type'] ?? 'general');
                $waiterIdForGuard = (string) ($waiter['id'] ?? '');
                if ($taskTypeForGuard === 'rack_check' && $waiterIdForGuard !== ''
                    && ! $this->shift->isWorkingDay($waiterIdForGuard, $effectiveTargetDate)) {
                    \Log::warning('[RACK_LIBUR_GUARD] Skip persist rack_check ke waiter LIBUR', [
                        'template_id' => $template['id'] ?? '',
                        'rack' => $template['rack_name'] ?? '?',
                        'waiter_id' => $waiterIdForGuard,
                        'waiter_name' => $waiter['name'] ?? '?',
                        'date' => $effectiveTargetDate,
                    ]);
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

                // BUG FIX (#7): Tag task as emergency assignment so admin
                // can identify supervisor-fallback tasks in dashboards.
                if (! empty($isEmergencyAssignment)) {
                    $taskData['is_emergency_assignment'] = true;
                }

                // === ROLLING / SHIFT FLAGS ===
                if ($isGeneralRolling) {
                    $taskData['is_rolling_assignment'] = true;
                    $taskData['rolling_period'] = (string) ($template['rolling_period'] ?? 'weekly');
                    if (! empty($template['rolling_anchor_date'])) {
                        $taskData['rolling_anchor_date'] = (string) $template['rolling_anchor_date'];
                    }
                    // Off-day flag: assigned but not scheduled to work today
                    if (($waiter['id'] ?? '') !== '' && ! $this->shift->isWorkingDay($waiter['id'], $effectiveTargetDate)) {
                        $taskData['is_off_day_assignment'] = true;
                    }
                }
                if ($targetShiftId !== '') {
                    $waiterShiftToday = ($waiter['id'] ?? '') !== ''
                        ? $this->shift->getWaiterShiftForDate($waiter['id'], $effectiveTargetDate)
                        : null;
                    $waiterShiftId = $waiterShiftToday ? (string) ($waiterShiftToday['id'] ?? '') : '';
                    $taskData['target_shift_id'] = $targetShiftId;
                    if ($waiterShiftId !== $targetShiftId) {
                        $taskData['is_shift_mismatch'] = true;
                        $taskData['actual_shift_id'] = $waiterShiftId;
                    }
                }

                if ($isCatchUp) {
                    // Catch-up tasks: jangan buat sebagai pending+overdue karena akan
                    // langsung kena penalti padahal bukan salah waiter.
                    // Buat sebagai 'skipped' (cancelled) dengan catatan informatif.
                    $taskData['status'] = 'cancelled';
                    $taskData['is_catch_up'] = true;
                    $taskData['original_scheduled_date'] = $effectiveTargetDate;
                    $taskData['cancel_reason'] = 'auto_catch_up';
                    $existingNote = trim((string) ($taskData['note'] ?? ''));
                    $prefix = '(Terlewat dari '.$effectiveTargetDate.' - sistem tidak aktif) ';
                    $taskData['note'] = $prefix.$existingNote;
                    // Hapus deadline supaya tidak di-mark overdue oleh markOverdueWaiterTasks()
                    $taskData['deadline_at'] = null;
                    $taskData['is_overdue'] = false;
                }

                $taskReference->set($taskData);
                $this->dualWriteWaiterTaskToMysql((string) $taskNodeKey, $taskData);
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

        $generatedCount += $this->enforceSimpleLowestLoadTasksForDate($targetDate);
        $this->cancelOffDayActiveTasksForDate($targetDate);

        return $generatedCount;
    }

    /**
     * Normalize legacy rack-check tasks produced before simple_lowest_load guard.
     *
     * Older runs could create one task per role waiter even when the template had
     * assignment_strategy=simple_lowest_load. Keep the current generator instance
     * (assignment_mode / assignment_reason present), create one if missing, and
     * cancel stale off-day or duplicate instances with final reasons.
     */
    private function enforceSimpleLowestLoadTasksForDate(string $targetDate): int
    {
        $tasks = $this->getWaiterTasksByDate($targetDate);
        $groups = [];

        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? 'pending');
            if (! in_array($status, ['pending', 'in_progress'], true)) {
                continue;
            }
            if ((string) ($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if ((string) ($task['assignment_strategy'] ?? '') !== 'simple_lowest_load') {
                continue;
            }

            $templateId = (string) ($task['source_template_id'] ?? '');
            if ($templateId === '') {
                continue;
            }

            $groups[$templateId][] = $task;
        }

        if (empty($groups)) {
            return 0;
        }

        $generatedCount = 0;

        foreach ($groups as $templateId => $items) {
            $hasCurrentInstance = false;
            foreach ($items as $task) {
                if ((string) ($task['assignment_mode'] ?? '') === 'simple_lowest_load'
                    || array_key_exists('assignment_reason', $task)) {
                    $hasCurrentInstance = true;
                    break;
                }
            }

            if (! $hasCurrentInstance) {
                $template = $this->getRecurringWaiterTaskTemplateById($templateId);
                if ($template && (string) ($template['assignment_strategy'] ?? '') === 'simple_lowest_load') {
                    $result = $this->processSimpleLowestLoadTemplate($template, $targetDate, false, true);
                    $generatedCount += (int) ($result['generated_count'] ?? 0);
                }
            }
        }

        // Re-read after any replacement generation so duplicate detection is exact.
        $tasks = $this->getWaiterTasksByDate($targetDate);
        $groups = [];
        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? 'pending');
            if (! in_array($status, ['pending', 'in_progress'], true)) {
                continue;
            }
            if ((string) ($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if ((string) ($task['assignment_strategy'] ?? '') !== 'simple_lowest_load') {
                continue;
            }
            $templateId = (string) ($task['source_template_id'] ?? '');
            if ($templateId !== '') {
                $groups[$templateId][] = $task;
            }
        }

        $updates = [];
        $now = time();

        foreach ($groups as $items) {
            $hasCurrentInstance = false;
            foreach ($items as $task) {
                if ((string) ($task['assignment_mode'] ?? '') === 'simple_lowest_load'
                    || array_key_exists('assignment_reason', $task)) {
                    $hasCurrentInstance = true;
                    break;
                }
            }

            foreach ($items as $task) {
                $taskId = (string) ($task['id'] ?? '');
                if ($taskId === '') {
                    continue;
                }

                $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
                $isWorking = $waiterId !== '' && $this->shift->isWorkingDay($waiterId, $targetDate);
                $isCurrentInstance = (string) ($task['assignment_mode'] ?? '') === 'simple_lowest_load'
                    || array_key_exists('assignment_reason', $task);

                if ($isCurrentInstance && $isWorking) {
                    continue;
                }

                if (! $isWorking) {
                    $reason = 'libur_off_day_correction';
                    $note = 'Dibatalkan otomatis: waiter libur / tidak eligible hari ini; memakai task hasil generator terbaru';
                } elseif ($hasCurrentInstance) {
                    $reason = 'duplicate_rack_fix';
                    $note = 'Dibatalkan otomatis: duplicate dari generator lama; memakai task hasil generator terbaru';
                } else {
                    continue;
                }

                $updates[$taskId.'/status'] = 'cancelled';
                $updates[$taskId.'/cancel_reason'] = $reason;
                $updates[$taskId.'/cancelled_at'] = $now;
                $updates[$taskId.'/cancelled_by_system'] = true;
                $updates[$taskId.'/completed_note'] = $note;
            }
        }

        if (! empty($updates)) {
            $this->database->getReference('waiter_tasks')->update($updates);
        }

        return $generatedCount;
    }

    /**
     * Final guard: no pending/in-progress task should stay assigned to an off-day waiter.
     */
    private function cancelOffDayActiveTasksForDate(string $targetDate): int
    {
        $tasks = $this->getWaiterTasksByDate($targetDate);
        $updates = [];
        $now = time();

        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? 'pending');
            if (! in_array($status, ['pending', 'in_progress'], true)) {
                continue;
            }

            $taskId = (string) ($task['id'] ?? '');
            $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
            if ($taskId === '' || $waiterId === '') {
                continue;
            }

            if ($this->shift->isWorkingDay($waiterId, $targetDate)) {
                continue;
            }

            $updates[$taskId.'/status'] = 'cancelled';
            $updates[$taskId.'/cancel_reason'] = 'libur_off_day_correction';
            $updates[$taskId.'/cancelled_at'] = $now;
            $updates[$taskId.'/cancelled_by_system'] = true;
            $updates[$taskId.'/completed_note'] = 'Dibatalkan otomatis: waiter libur / tidak eligible hari ini';
        }

        if (! empty($updates)) {
            $this->database->getReference('waiter_tasks')->update($updates);
        }

        return count($updates) > 0 ? (int) (count($updates) / 5) : 0;
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
                $periodStart = date('Y-m-d', strtotime('-29 days'));

                // Batch fetch ALL penalties for this period ONCE (not per-waiter)
                $allMonthPenalties = $bonusService->getPenaltiesByPeriod($periodStart, $today);

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
     * Cancel orphaned pending tasks whose source template no longer exists.
     * Safety net for tasks created after template deletion (e.g. overflow redistribution).
     *
     * @return int Number of tasks cancelled
     */
    public function cancelOrphanedPendingTasks(): int
    {
        $today = date('Y-m-d');
        $cancelledCount = 0;

        try {
            // Get all pending tasks for today
            $reference = $this->database->getReference('waiter_tasks')
                ->orderByChild('scheduled_for_date')
                ->equalTo($today);
            $snapshot = $reference->getSnapshot();

            if (! $snapshot->exists()) {
                return 0;
            }

            // Collect unique template IDs from pending tasks
            $templateIds = [];
            $pendingTasks = [];
            foreach ((array) $snapshot->getValue() as $taskId => $task) {
                $status = (string) ($task['status'] ?? 'pending');
                if (! in_array($status, ['pending', 'in_progress'], true)) {
                    continue;
                }
                $sourceId = (string) ($task['source_template_id'] ?? $task['template_id'] ?? '');
                if ($sourceId === '') {
                    continue;
                }
                $pendingTasks[$taskId] = $sourceId;
                $templateIds[$sourceId] = true;
            }

            if (empty($pendingTasks)) {
                return 0;
            }

            // Check which templates still exist
            $existingTemplates = [];
            foreach (array_keys($templateIds) as $tplId) {
                $tplSnap = $this->database->getReference('waiter_task_templates/' . $tplId)->getSnapshot();
                if ($tplSnap->exists()) {
                    $existingTemplates[$tplId] = true;
                }
            }

            // Cancel tasks whose template no longer exists
            $updates = [];
            $now = time();
            foreach ($pendingTasks as $taskId => $sourceId) {
                if (isset($existingTemplates[$sourceId])) {
                    continue; // Template still exists, skip
                }
                $updates[$taskId . '/status'] = 'cancelled';
                $updates[$taskId . '/cancelled_at'] = $now;
                $updates[$taskId . '/cancelled_by_template_delete'] = true;
                $updates[$taskId . '/completed_note'] = 'Template induk tidak ditemukan (auto-cleanup)';
                $cancelledCount++;
            }

            if (! empty($updates)) {
                $this->database->getReference('waiter_tasks')->update($updates);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $cancelledCount;
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

            $waiter = $this->firebase->getWaiterById($assignedWaiterId);
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
                $allActive = $this->firebase->getActiveWaiters();
                $selectedWaiterMap = array_fill_keys($selectedWaiterIds, true);

                $resolved = array_values(array_filter($allActive, function ($waiter) use ($selectedWaiterMap) {
                    $waiterId = trim((string) ($waiter['id'] ?? ''));

                    return $waiterId !== '' && isset($selectedWaiterMap[$waiterId]);
                }));

                // BUG FIX (#15): Log audit kalau ada waiter yang role-nya tidak match
                // dengan assignedWaiterRole template. Soft enforcement — tetap allow,
                // tapi catat untuk monitoring siapa yang dapat task cross-role.
                if ($assignedWaiterRole) {
                    $expectedRole = $this->normalizeWaiterRole($assignedWaiterRole);
                    $mismatched = array_filter($resolved, function ($waiter) use ($expectedRole) {
                        $actualRole = $this->normalizeWaiterRole((string) ($waiter['waiter_role'] ?? ''));
                        return $actualRole !== '' && $actualRole !== $expectedRole;
                    });

                    if (count($mismatched) > 0) {
                        \Log::info('[ROLE_MISMATCH] rack_check task assigned to waiter outside expected role', [
                            'expected_role' => $expectedRole,
                            'mismatched_waiters' => array_map(function ($w) {
                                return [
                                    'id' => $w['id'] ?? '',
                                    'name' => $w['name'] ?? '',
                                    'actual_role' => $w['waiter_role'] ?? '',
                                ];
                            }, array_values($mismatched)),
                        ]);
                    }
                }

                return $resolved;
            }

            // General task: tetap filter role-based seperti sebelumnya
            if (! $assignedWaiterRole) {
                return [];
            }

            $roleWaiters = $this->firebase->getActiveWaitersByRole($assignedWaiterRole);

            if (count($selectedWaiterIds) === 0) {
                return $roleWaiters;
            }

            $selectedWaiterMap = array_fill_keys($selectedWaiterIds, true);

            return array_values(array_filter($roleWaiters, function ($waiter) use ($selectedWaiterMap) {
                $waiterId = trim((string) ($waiter['id'] ?? ''));

                return $waiterId !== '' && isset($selectedWaiterMap[$waiterId]);
            }));
        }

        return $this->firebase->getActiveWaiters();
    }

    /**
     * Dual-write a freshly created Firebase waiter task into MySQL when the
     * flag is on. Idempotent via firebase_legacy_key. The full Firebase payload
     * is mirrored into firebase_payload so the portal keeps every field; the
     * structured columns serve querying. Failure is logged, never fatal — task
     * creation in Firebase must not break during rollout.
     */
    protected function dualWriteWaiterTaskToMysql(string $firebaseKey, array $payload): void
    {
        if (! config('features.mysql_waiter_tasks')) {
            return;
        }

        try {
            $createdAt = $payload['created_at'] ?? null;
            $rawType = $payload['task_type'] ?? 'general';
            $rawPriority = $payload['priority'] ?? 'normal';
            \App\Models\WaiterTask::updateOrCreate(
                ['firebase_legacy_key' => $firebaseKey],
                [
                    'deterministic_key' => 'wt_legacy_'.substr(hash('sha256', $firebaseKey), 0, 32),
                    'task_type' => in_array($rawType, ['general', 'rack_check'], true) ? $rawType : 'general',
                    'title' => (string) ($payload['title'] ?? 'Untitled'),
                    'description' => $payload['description'] ?? null,
                    'assigned_waiter_id' => (string) ($payload['assigned_waiter_id'] ?? ''),
                    'assigned_waiter_name' => $payload['assigned_waiter_name'] ?? null,
                    'scheduled_for_date' => $payload['scheduled_for_date'] ?? now()->format('Y-m-d'),
                    'status' => 'pending',
                    'publish_status' => 'published',
                    'sync_status' => 'synced',
                    'priority' => in_array($rawPriority, ['low', 'normal', 'high', 'urgent'], true) ? $rawPriority : 'normal',
                    'rack_id' => $payload['rack_id'] ?? null,
                    'rack_name' => $payload['rack_name'] ?? null,
                    'created_by' => $payload['assigned_by'] ?? ($payload['created_by'] ?? null),
                    'firebase_payload' => $payload,
                    'created_at' => is_numeric($createdAt) ? date('Y-m-d H:i:s', (int) $createdAt) : null,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

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
     * BUG FIX (#14): Resolve rolling waiter ID via persisted counter instead
     * of calendar-modulo offset.
     *
     * Why: When roster changes (waiter added/removed from rolling_waiter_ids),
     * `offset % count` shifts which waiter is "next", breaking fairness.
     * Persisted counter tracks last_assigned and rotates from there.
     *
     * Behavior:
     * 1. Within same period (e.g., same week) → return cached assignee
     *    (idempotent for cron reruns).
     * 2. New period → pick waiter after last_assignee in current rollingIds.
     * 3. last_assignee not in rollingIds (was removed) → fall back to
     *    calendar offset (preserves backward compat for new templates).
     *
     * Storage:
     *   /rotation_counters/{templateId} = {
     *     last_period_key: 'weekly_2026-W22',
     *     last_assigned_waiter_id: '-OipayVWnWj-Tr7BumNZ',
     *     period_assignees: { 'weekly_2026-W22': '-OipayVWnWj-Tr7BumNZ', ... },
     *     updated_at: int
     *   }
     */
    protected function resolveRollingWaiterIdByCounter(
        string $templateId,
        array $rollingIds,
        string $period,
        string $date,
        ?string $anchor = null
    ): string {
        if (empty($rollingIds)) {
            return '';
        }

        $periodKey = $this->buildRotationPeriodKey($date, $period);
        $counterRef = $this->database->getReference('rotation_counters/' . $templateId);

        try {
            $snapshot = $counterRef->getSnapshot();
            $current = $snapshot->exists() ? (array) $snapshot->getValue() : [];

            // 1. Idempotency: within same period, return cached assignee
            $cached = (string) ($current['period_assignees'][$periodKey] ?? '');
            if ($cached !== '' && in_array($cached, $rollingIds, true)) {
                return $cached;
            }

            // 2. New period: pick waiter after last_assignee
            $lastAssignee = (string) ($current['last_assigned_waiter_id'] ?? '');
            $nextId = '';

            if ($lastAssignee !== '') {
                $lastIdx = array_search($lastAssignee, $rollingIds, true);
                if ($lastIdx !== false) {
                    $nextIdx = ($lastIdx + 1) % count($rollingIds);
                    $nextId = $rollingIds[$nextIdx];
                }
            }

            // 3. Fallback to calendar offset (last_assignee removed or first run)
            if ($nextId === '') {
                $offset = $this->resolveRotationOffsetForPeriod($date, $period, $anchor);
                $nextId = $rollingIds[$offset % count($rollingIds)];
            }

            // Persist via atomic transaction
            try {
                $this->database->runTransaction(function ($transaction) use ($counterRef, $periodKey, $nextId) {
                    $snap = $transaction->snapshot($counterRef);
                    $existing = $snap->exists() ? (array) $snap->getValue() : [];

                    // Re-check inside transaction (race condition safety)
                    $alreadyAssigned = (string) ($existing['period_assignees'][$periodKey] ?? '');
                    if ($alreadyAssigned !== '') {
                        return; // someone else won the race; we'll re-read below
                    }

                    $existing['period_assignees'][$periodKey] = $nextId;
                    $existing['last_period_key'] = $periodKey;
                    $existing['last_assigned_waiter_id'] = $nextId;
                    $existing['updated_at'] = time();

                    // Cap history at 12 entries to prevent unbounded growth
                    $assignees = (array) ($existing['period_assignees'] ?? []);
                    if (count($assignees) > 12) {
                        ksort($assignees);
                        $assignees = array_slice($assignees, -12, null, true);
                        $existing['period_assignees'] = $assignees;
                    }

                    $transaction->set($counterRef, $existing);
                });
            } catch (\Throwable $e) {
                // If transaction fails, re-read to honor any race winner
                $reread = $counterRef->getValue();
                if (is_array($reread)) {
                    $rereadCached = (string) ($reread['period_assignees'][$periodKey] ?? '');
                    if ($rereadCached !== '' && in_array($rereadCached, $rollingIds, true)) {
                        return $rereadCached;
                    }
                }
            }

            return $nextId;
        } catch (\Throwable $e) {
            // Catastrophic fallback: pure calendar offset
            $offset = $this->resolveRotationOffsetForPeriod($date, $period, $anchor);
            return $rollingIds[$offset % count($rollingIds)];
        }
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
     * Upload a validated base64 image data URL to Firebase Storage and return a
     * public URL. Replaces storing multi-MB base64 blobs inside RTDB nodes
     * (the main bandwidth offender). Returns '' on empty/failure so callers can
     * fall back to the legacy inline data URL without breaking task completion.
     */
    protected function uploadTaskPhoto(string $dataUrl, string $taskId, string $kind): string
    {
        if ($dataUrl === '' || ! preg_match('/^data:(image\/[\w.+-]+);base64,(.+)$/i', $dataUrl, $m)) {
            return '';
        }

        $mime = strtolower($m[1]);
        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]) ?? '', true);
        if ($bytes === false || $bytes === '') {
            return '';
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $safeTask = preg_replace('/[^A-Za-z0-9_-]/', '', $taskId) ?: 'unknown';
        $object = sprintf('task_photos/%s/%s_%d.%s', $safeTask, $kind, time(), $ext);

        try {
            $bucketName = (string) config('firebase.web.storage_bucket') ?: null;
            $bucket = app('firebase.storage')->getBucket($bucketName);
            $bucket->upload($bytes, [
                'name' => $object,
                'metadata' => ['contentType' => $mime],
                'predefinedAcl' => 'publicRead',
            ]);

            return sprintf(
                'https://storage.googleapis.com/%s/%s',
                $bucket->name(),
                $object
            );
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
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
            // EXCEPTION: rack_check dengan role_round_robin TIDAK di-skip,
            // karena rule bisnis: 1 rak = 1 task/hari, cancel = final (tidak boleh re-generate).
            if ((string) ($task['status'] ?? '') === 'cancelled') {
                if (($task['task_type'] ?? '') === 'rack_check'
                    && ($task['assignment_strategy'] ?? '') === 'role_round_robin') {
                    // Tetap mark template-level key supaya tidak re-generate
                    $map[(string) $sourceTemplateId . '::*'] = true;
                }
                continue;
            }

            $map[$this->buildWaiterRecurringInstanceKey($sourceTemplateId, $assignedWaiterId)] = true;

            // For rack_check rolling: also mark template-level key so fair distribution
            // doesn't re-generate after manual reassignment changes waiter_id
            if (($task['task_type'] ?? '') === 'rack_check'
                && ($task['assignment_strategy'] ?? '') === 'role_round_robin') {
                $map[(string) $sourceTemplateId . '::*'] = true;
            }
        }

        return $map;
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
                $scheduleTimestamp = $this->shift->buildScheduledTimestamp($todayDate, $scheduleTime);
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

            $legacyKey = null;
            if (config('features.legacy_write_cashier_tasks')) {
                $legacyKey = (string) $this->database->getReference('cashier_tasks')->push($taskData)->getKey();
            } else {
                $legacyKey = 'ct_local_'.substr(hash('sha256', json_encode($taskData).$template['id']), 0, 24);
            }

            $this->dualWriteCashierTaskToMysql($legacyKey, $taskData);

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
     * Build a map of recurring instances already generated for a date
     */
    protected function getExistingRecurringMapForDate($date)
    {
        return app(\App\Repositories\Contracts\CashierTaskRepositoryInterface::class)->existingRecurringMap($date);
    }

    /**
     * Check whether a template already has a pending recurring instance for a date
     */
    protected function hasPendingRecurringInstanceForDate($templateId, $date)
    {
        return app(\App\Repositories\Contracts\CashierTaskRepositoryInterface::class)
            ->hasPendingRecurringInstance((string) $templateId, $date);
    }

    /**
     * Check whether a template already has a completed recurring instance for a date
     */
    protected function hasDoneRecurringInstanceForDate($templateId, $date)
    {
        return app(\App\Repositories\Contracts\CashierTaskRepositoryInterface::class)
            ->hasDoneRecurringInstance((string) $templateId, $date);
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
            $deadlineAt = $this->shift->buildScheduledTimestamp($date, $scheduleTime) + ($timeLimitMinutes * 60);
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
     * Decide if a recurring template should run on a given date
     */
    public function isTemplateDueForDate($template, $date)
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

            // Use DateTimeImmutable for DST-safe day difference calculation
            $anchorDt = new \DateTimeImmutable($anchorDate);
            $dateDt = new \DateTimeImmutable($date);
            if ($dateDt < $anchorDt) {
                return false;
            }

            $diffDays = (int) $anchorDt->diff($dateDt)->days;

            return $diffDays % $intervalDays === 0;
        }

        // Default mode: daily
        return true;
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
     * Get waiter task performance for a date range
     */
    public function getWaiterTaskPerformance(string $waiterId, string $fromDate, string $toDate): array
    {
        // Bound by scheduled_for_date server-side (needs .indexOn ["scheduled_for_date"]),
        // then filter to this waiter in PHP. Avoids reading the waiter's full history.
        $tasks = $this->database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->startAt($fromDate)
            ->endAt($toDate)
            ->getSnapshot()
            ->getValue();

        $dailyStats = [];
        $totalDone = 0;
        $totalOverdue = 0;
        $totalTasks = 0;

        if ($tasks) {
            foreach ($tasks as $task) {
                if (!is_array($task)) continue;
                if ((string) ($task['assigned_waiter_id'] ?? '') !== (string) $waiterId) continue;
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

            // Sync cancellation to MySQL (waiter portal reads from MySQL)
            if (config('features.mysql_waiter_tasks')) {
                // Extract task IDs from update keys (format: "taskId/field")
                $cancelledIds = array_unique(array_map(
                    fn ($key) => explode('/', $key)[0],
                    array_keys($updates)
                ));
                WaiterTask::whereIn('firebase_legacy_key', $cancelledIds)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->update(['status' => 'cancelled']);
            }
        }

        return $cancelled;
    }

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

    /**
     * Get max rack_check tasks per day for a waiter based on their shift today.
     * - LIBUR (off day): 0 tasks
     * - Short shift (PAGI/SORE/SHIFT_1/SHIFT_2 ~8-10 jam): 1 task
     * - FULL shift (12+ jam): 2 tasks
     * - No shift info / fallback: 1 task (conservative default)
     *
     * Used by generateRecurringTasksForDate() to prevent overloading single waiter.
     */
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

    /**
     * Process a single rack-check template with assignment_strategy=simple_lowest_load.
     * Picks one waiter dengan beban paling ringan dari selected_waiter_ids,
     * write lock supaya tidak re-generate setelah cancel.
     *
     * @return array{generated_count:int,status:string,reason?:string,task_id?:string,assigned_waiter_id?:string,rack_results?:array}
     */
    public function processSimpleLowestLoadTemplate(array $template, string $targetDate, bool $isCatchUp = false, bool $force = false, array &$inMemoryCounter = [], array &$inMemoryAssignedRacks = []): array
    {
        $templateId = (string) ($template['id'] ?? '');
        if ($templateId === '') {
            return ['generated_count' => 0, 'status' => 'invalid_template', 'reason' => 'missing_template_id'];
        }

        // Catch-up: untuk simple_lowest_load, skip catch-up untuk hari yang sudah lewat
        if ($isCatchUp) {
            return ['generated_count' => 0, 'status' => 'skipped_catch_up'];
        }

        // Resolve racks from template (supports multi-rak and legacy single-rak)
        $racks = $this->rack->normalizeTemplateRacks($template);
        if (empty($racks)) {
            return ['generated_count' => 0, 'status' => 'invalid_template', 'reason' => 'no_racks'];
        }

        $totalGenerated = 0;
        $rackResults = [];
        $lastAssignedWaiterId = null;
        $lastTaskId = null;

        foreach ($racks as $rack) {
            $rackId = (string) ($rack['id'] ?? '');
            if ($rackId === '') {
                continue;
            }

            // ── 1. Lock check per rak ──
            $existingLock = $this->getSimpleLowestLoadLock($templateId, $targetDate, $rackId);
            if ($existingLock !== null && ! $force) {
                $lockStatus = (string) ($existingLock['status'] ?? '');
                if (in_array($lockStatus, ['generated', 'skipped_no_eligible_waiter', 'cancelled_by_admin'], true)) {
                    $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'lock_exists', 'reason' => $lockStatus];
                    continue;
                }
            }

            // ── 2. Build virtual single-rack template for candidate evaluation ──
            $singleRackTemplate = array_merge($template, [
                'rack_id' => $rackId,
                'rack_name' => $rack['name'] ?? '',
                'rack_location' => $rack['location'] ?? '',
                'rack_barcode_value' => $rack['barcode_value'] ?? '',
                'rack_type' => $rack['rack_type'] ?? 'storage',
            ]);

            // ── 3. Resolve eligible candidates ──
            $evaluation = $this->evaluateSimpleLowestLoadCandidates($singleRackTemplate, $targetDate, $inMemoryCounter, $inMemoryAssignedRacks);
            $evaluated = $evaluation['evaluated'];
            $eligible = $evaluation['eligible'];

            if (empty($eligible)) {
                $rejected = array_values(array_filter($evaluated, fn ($row) => ! $row['eligible']));
                $rejectedSummary = array_map(fn ($r) => [
                    'waiter_id' => $r['waiter_id'],
                    'name' => $r['waiter']['name'] ?? '',
                    'reason' => $r['reject_reason'] ?? '',
                ], $rejected);

                $overflowId = $this->rack->createRackCheckOverflow($singleRackTemplate, $targetDate, 'no_eligible_waiter', [
                    'evaluated' => $evaluated,
                    'rejected_candidates' => $rejectedSummary,
                ], $rackId);
                $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'skipped_no_eligible_waiter', 'overflow_id' => $overflowId];
                continue;
            }

            // ── 4. Sort kandidat: today_count ASC, monthly_points ASC, weekly_count ASC ──
            usort($eligible, function ($a, $b) {
                return [
                    $a['today_count'], $a['monthly_points'], $a['weekly_count'], $a['last_assigned_at'], $a['waiter_id'],
                ] <=> [
                    $b['today_count'], $b['monthly_points'], $b['weekly_count'], $b['last_assigned_at'], $b['waiter_id'],
                ];
            });

            $selected = $eligible[0];
            $selectedWaiter = $selected['waiter'];
            $selectedWaiterId = (string) $selected['waiter_id'];

            // ── 5. Build assignment_reason ──
            $rejectedSummary = [];
            foreach ($evaluated as $row) {
                if ($row['eligible'] && $row['waiter_id'] === $selectedWaiterId) {
                    continue;
                }
                $rejectedSummary[] = [
                    'waiter_id' => $row['waiter_id'],
                    'name' => $row['waiter']['name'] ?? '',
                    'reason' => $row['eligible']
                        ? 'Beban hari ini lebih tinggi atau peringkat lebih rendah'
                        : ($row['reject_reason'] ?? ''),
                ];
            }

            $assignmentReason = [
                'mode' => 'simple_lowest_load',
                'selected_waiter_id' => $selectedWaiterId,
                'selected_waiter_name' => (string) ($selectedWaiter['name'] ?? ''),
                'reason' => sprintf(
                    '%s dipilih karena sedang kerja dan memiliki beban cek rak paling ringan.',
                    $selectedWaiter['name'] ?? 'Waiter'
                ),
                'today_rack_task_count_before' => (int) $selected['today_count'],
                'weekly_rack_task_count' => (int) $selected['weekly_count'],
                'monthly_points_before' => (int) ($selected['monthly_points'] ?? 0),
                'low_monthly_point_priority' => true,
                'daily_cap' => (int) $selected['daily_cap'],
                'candidate_count' => count($evaluated),
                'eligible_candidate_count' => count($eligible),
                'rejected_candidates' => $rejectedSummary,
            ];

            // ── 6. Build task payload + persist ──
            $createResult = $this->createSimpleLowestLoadTask(
                $singleRackTemplate,
                $selectedWaiter,
                $targetDate,
                $assignmentReason
            );

            if (! ($createResult['success'] ?? false)) {
                $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'create_failed', 'reason' => $createResult['reason'] ?? 'unknown'];
                continue;
            }

            // ── 7. Write lock per rak ──
            $this->writeSimpleLowestLoadLock($templateId, $targetDate, [
                'status' => 'generated',
                'task_id' => (string) ($createResult['task_node_key'] ?? ''),
                'assigned_waiter_id' => $selectedWaiterId,
                'assigned_waiter_name' => (string) ($selectedWaiter['name'] ?? ''),
                'evaluated_candidates' => $evaluated,
            ], $rackId);

            // ── 8. Update in-memory counters ──
            $inMemoryCounter[$selectedWaiterId] = ($inMemoryCounter[$selectedWaiterId] ?? 0) + 1;
            $inMemoryAssignedRacks[$selectedWaiterId][] = $rackId;

            $totalGenerated++;
            $lastAssignedWaiterId = $selectedWaiterId;
            $lastTaskId = (string) ($createResult['task_node_key'] ?? '');
            $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'generated', 'assigned_waiter_id' => $selectedWaiterId];
        }

        if ($totalGenerated === 0 && ! empty($rackResults)) {
            // All racks were either locked or skipped
            $firstResult = $rackResults[0];
            return ['generated_count' => 0, 'status' => $firstResult['status'] ?? 'skipped', 'reason' => $firstResult['reason'] ?? '', 'rack_results' => $rackResults];
        }

        return [
            'generated_count' => $totalGenerated,
            'status' => 'generated',
            'task_id' => $lastTaskId,
            'assigned_waiter_id' => $lastAssignedWaiterId,
            'rack_results' => $rackResults,
        ];
    }

    /**
     * Evaluate semua selected_waiter_ids: cek isWorkingDay, daily cap,
     * count today + weekly rack_check tasks, last_assigned_at.
     *
     * $inMemoryCounter    — [waiterId => int] tasks assigned in this cron run (not yet in Firebase)
     * $inMemoryAssignedRacks — [waiterId => rack_id[]] racks already assigned this run (same-rack dedup)
     *
     * @return array{evaluated:array<int,array>,eligible:array<int,array>}
     */
    protected function evaluateSimpleLowestLoadCandidates(array $template, string $targetDate, array $inMemoryCounter = [], array $inMemoryAssignedRacks = []): array
    {
        $selectedIdsRaw = $template['selected_waiter_ids'] ?? [];
        if (! is_array($selectedIdsRaw)) {
            $selectedIdsRaw = [];
        }
        $selectedIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $selectedIdsRaw
        ), fn ($id) => $id !== '')));

        // Resolve waiter records — only active waiters.
        $candidates = [];
        foreach ($selectedIds as $waiterId) {
            try {
                $waiter = $this->firebase->getWaiterById($waiterId);
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
            if (! $waiter || ($waiter['is_active'] ?? true) === false) {
                continue;
            }
            $candidates[] = $waiter;
        }

        // Compute weekStart (Monday) untuk weekly count.
        $weekStart = (new \DateTime($targetDate))->modify('Monday this week')->format('Y-m-d');

        // Rack ID being evaluated — used for same-rack dedup.
        $thisRackId = (string) ($template['rack_id'] ?? '');

        // ── Bulk-fetch all tasks for targetDate ONCE (avoid N+1 Firebase queries) ──
        $cacheKey = 'tasks_by_date_' . $targetDate;
        if (! isset($this->requestCache[$cacheKey])) {
            try {
                $allDateTasks = $this->getWaiterTasksByDate($targetDate);
            } catch (\Throwable $e) {
                report($e);
                $allDateTasks = [];
            }
            $this->requestCache[$cacheKey] = $allDateTasks;
        }
        $allDateTasks = $this->requestCache[$cacheKey];

        // Group by waiter_id for quick lookup
        $tasksByWaiter = [];
        foreach ($allDateTasks as $t) {
            $wid = (string) ($t['assigned_waiter_id'] ?? '');
            if ($wid !== '') {
                $tasksByWaiter[$wid][] = $t;
            }
        }

        $evaluated = [];
        foreach ($candidates as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            $isWorking = $waiterId !== '' ? $this->shift->isWorkingDay($waiterId, $targetDate) : false;
            $dailyCap = $isWorking ? $this->rack->getRackCheckDailyCap($waiterId, $targetDate, $template) : 0;

            // today_count: from bulk-fetched tasks + in-memory additions from this run.
            $waiterDateTasks = $tasksByWaiter[$waiterId] ?? [];
            $firebaseCount = 0;
            foreach ($waiterDateTasks as $t) {
                if (($t['task_type'] ?? '') !== 'rack_check') continue;
                if ((string) ($t['status'] ?? '') === 'cancelled') continue;
                $firebaseCount++;
            }
            $inRunCount = (int) ($inMemoryCounter[$waiterId] ?? 0);
            $todayCount = $firebaseCount + $inRunCount;

            // weekly_count: use cached per-waiter data
            $weeklyCacheKey = 'weekly_rack_count_' . $waiterId . '_' . $weekStart . '_' . $targetDate;
            if (! isset($this->requestCache[$weeklyCacheKey])) {
                $this->requestCache[$weeklyCacheKey] = $waiterId !== ''
                    ? $this->rack->countRackCheckTasksForWaiterBetweenDates($waiterId, $weekStart, $targetDate)
                    : 0;
            }
            $weeklyCount = $this->requestCache[$weeklyCacheKey];

            // last_assigned_at: cache per waiter
            $lastCacheKey = 'last_rack_assigned_' . $waiterId;
            if (! isset($this->requestCache[$lastCacheKey])) {
                $this->requestCache[$lastCacheKey] = $waiterId !== ''
                    ? $this->rack->getLastRackCheckAssignedAt($waiterId)
                    : 0;
            }
            $lastAssignedAt = $this->requestCache[$lastCacheKey];

            // monthly_points: cache per waiter
            $pointsCacheKey = 'monthly_points_' . $waiterId . '_' . $targetDate;
            if (! isset($this->requestCache[$pointsCacheKey])) {
                $this->requestCache[$pointsCacheKey] = $waiterId !== ''
                    ? $this->getWaiterMonthlyPointsForPriority($waiterId, $targetDate)
                    : 0;
            }
            $monthlyPoints = $this->requestCache[$pointsCacheKey];

            // Same-rack dedup: reject waiter already assigned this rack today
            $alreadyHasThisRack = false;
            if ($thisRackId !== '' && $waiterId !== '') {
                // Check from bulk-fetched tasks
                foreach ($waiterDateTasks as $t) {
                    if (($t['task_type'] ?? '') === 'rack_check'
                        && (string) ($t['rack_id'] ?? '') === $thisRackId
                        && (string) ($t['status'] ?? '') !== 'cancelled') {
                        $alreadyHasThisRack = true;
                        break;
                    }
                }
                // Check in-memory assignments from this run
                if (! $alreadyHasThisRack && in_array($thisRackId, $inMemoryAssignedRacks[$waiterId] ?? [], true)) {
                    $alreadyHasThisRack = true;
                }
            }

            $eligible = $isWorking && $todayCount < $dailyCap && ! $alreadyHasThisRack;

            $rejectReason = '';
            if (! $isWorking) {
                $rejectReason = 'LIBUR';
            } elseif ($alreadyHasThisRack) {
                $rejectReason = 'Sudah dapat rak ini hari ini';
            } elseif ($todayCount >= $dailyCap) {
                $rejectReason = "Sudah {$todayCount}/{$dailyCap} task cek rak hari ini";
            }

            $evaluated[] = [
                'waiter' => $waiter,
                'waiter_id' => $waiterId,
                'is_working_day' => $isWorking,
                'daily_cap' => $dailyCap,
                'today_count' => $todayCount,
                'weekly_count' => $weeklyCount,
                'monthly_points' => $monthlyPoints,
                'last_assigned_at' => $lastAssignedAt,
                'eligible' => $eligible,
                'reject_reason' => $rejectReason,
            ];
        }

        $eligibleList = array_values(array_filter($evaluated, fn ($row) => $row['eligible']));

        return [
            'evaluated' => $evaluated,
            'eligible' => $eligibleList,
        ];
    }

    /**
     * Persist task payload untuk simple_lowest_load. Reuse buildWaiterTaskPayload
     * + node key scheme yang sama dgn generator existing supaya kompatibel
     * dgn waiter portal, recheck, bonus pipeline.
     *
     * @return array{success:bool,reason?:string,task_node_key?:string}
     */
    protected function createSimpleLowestLoadTask(array $template, array $waiter, string $targetDate, array $assignmentReason): array
    {
        $templateId = (string) ($template['id'] ?? '');
        $waiterId = (string) ($waiter['id'] ?? '');
        if ($templateId === '' || $waiterId === '') {
            return ['success' => false, 'reason' => 'invalid_ids'];
        }

        // Schedule mengikuti shift waiter terpilih.
        // - scheduled_time = shift.clock_in_time
        // - deadline_at    = shift.clock_out_time (akhir shift; kalau no shift, fallback +8 jam dari sekarang)
        $shift = $this->shift->getWaiterShiftForDate($waiterId, $targetDate);
        $shiftStart = $shift && ! empty($shift['clock_in_time']) ? (string) $shift['clock_in_time'] : '08:00';
        $shiftEnd   = $shift && ! empty($shift['clock_out_time']) ? (string) $shift['clock_out_time'] : '';

        $scheduleTime = $shiftStart;

        $scheduleTimestamp = $this->shift->buildScheduledTimestamp($targetDate, $shiftStart);
        $waiterDeadlineAt = null;
        if ($shiftEnd !== '') {
            $endTimestamp = $this->shift->buildScheduledTimestamp($targetDate, $shiftEnd);
            // Handle overnight shift (end <= start)
            if ($endTimestamp <= $scheduleTimestamp) {
                $endTimestamp += 86400;
            }
            $waiterDeadlineAt = $endTimestamp;
        } else {
            // No shift end: fallback 8 jam dari sekarang (defensive)
            $waiterDeadlineAt = max(time(), $scheduleTimestamp) + (8 * 3600);
        }

        // Skip task hari ini kalau deadline sudah lewat (consistency dgn generator existing)
        if ($targetDate === date('Y-m-d') && $waiterDeadlineAt > 0 && $waiterDeadlineAt <= time()) {
            return ['success' => false, 'reason' => 'deadline_already_passed'];
        }

        $rackId = (string) ($template['rack_id'] ?? '');
        $recurringInstanceKey = $this->buildWaiterRecurringInstanceIdentity($templateId, $waiterId, $targetDate) . '::' . $rackId;
        $taskNodeKey = $this->buildWaiterRecurringTaskNodeKey($recurringInstanceKey);
        $taskReference = $this->database->getReference('waiter_tasks/'.$taskNodeKey);
        $existingSnap = $taskReference->getSnapshot();
        if ($existingSnap->exists()) {
            $existing = (array) $existingSnap->getValue();
            $status = (string) ($existing['status'] ?? '');
            if ($status !== 'cancelled') {
                return ['success' => false, 'reason' => 'task_already_exists'];
            }
            // cancelled with cancel_reason final → don't regenerate
            $cancelReason = (string) ($existing['cancel_reason'] ?? '');
            $finalReasons = ['admin_manual', 'bulk_cancel', 'duplicate_rack_fix', 'libur_off_day_correction'];
            if (in_array($cancelReason, $finalReasons, true)) {
                return ['success' => false, 'reason' => 'task_finally_cancelled'];
            }
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
            'scheduled_for_date' => $targetDate,
            'source_template_id' => $templateId,
            'recurring_instance_key' => $recurringInstanceKey,
            'time_limit_minutes' => null,
            'deadline_at' => $waiterDeadlineAt,
            'recurrence_type' => $template['recurrence_type'] ?? 'daily',
            'is_rescheduled' => false,
            'rescheduled_from_date' => null,
            'original_due_date' => null,
            'assignment_mode' => 'simple_lowest_load',
            'assignment_reason' => $assignmentReason,
            'shift_id_at_assignment' => $shift ? (string) ($shift['id'] ?? '') : '',
            'shift_clock_in_at_assignment' => $shiftStart,
            'shift_clock_out_at_assignment' => $shiftEnd,
        ]);

        $taskReference->set($taskData);
        $this->dualWriteWaiterTaskToMysql((string) $taskNodeKey, $taskData);

        return [
            'success' => true,
            'task_node_key' => $taskNodeKey,
        ];
    }

    /**
     * Get generation lock for a template+date.
     *
     * @return array|null Lock data or null if not exists.
     */
    public function getSimpleLowestLoadLock(string $templateId, string $date, ?string $rackId = null): ?array
    {
        if ($templateId === '' || $date === '') {
            return null;
        }
        $dateCompact = str_replace('-', '', $date);
        $path = $rackId !== null && $rackId !== ''
            ? 'waiter_task_generation_locks/'.$templateId.'/'.$rackId.'/'.$dateCompact
            : 'waiter_task_generation_locks/'.$templateId.'/'.$dateCompact;
        try {
            $snap = $this->database->getReference($path)->getSnapshot();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
        if (! $snap->exists()) {
            return null;
        }
        $value = $snap->getValue();
        return is_array($value) ? $value : null;
    }

    /**
     * Write generation lock.
     */
    public function writeSimpleLowestLoadLock(string $templateId, string $date, array $payload, ?string $rackId = null): void
    {
        if ($templateId === '' || $date === '') {
            return;
        }
        $dateCompact = str_replace('-', '', $date);
        $path = $rackId !== null && $rackId !== ''
            ? 'waiter_task_generation_locks/'.$templateId.'/'.$rackId.'/'.$dateCompact
            : 'waiter_task_generation_locks/'.$templateId.'/'.$dateCompact;
        $existing = $this->getSimpleLowestLoadLock($templateId, $date, $rackId);
        $forceCount = (int) ($existing['force_regenerate_count'] ?? 0);
        $base = [
            'template_id' => $templateId,
            'date' => $date,
            'rack_id' => $rackId ?? '',
            'cancelled_by_admin' => (bool) ($existing['cancelled_by_admin'] ?? false),
            'force_regenerate_count' => $forceCount,
            'generated_at' => time(),
        ];
        try {
            $this->database->getReference($path)->set(array_merge($base, $payload));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Process template dengan strategy=round_robin_simple.
     * Bergiliran urut sesuai selected_waiter_ids. Petugas libur skip ke berikutnya.
     * Counter persisten di /waiter_task_round_robin_counters/{templateId}.
     * Supports multi-rak: iterates over all racks, same rotation index for all.
     *
     * @return array{generated_count:int,status:string,reason?:string,task_id?:string,assigned_waiter_id?:string,rack_results?:array}
     */
    public function processRoundRobinSimpleTemplate(array $template, string $targetDate, bool $isCatchUp = false, bool $force = false): array
    {
        $templateId = (string) ($template['id'] ?? '');
        if ($templateId === '') {
            return ['generated_count' => 0, 'status' => 'invalid_template', 'reason' => 'missing_template_id'];
        }

        if ($isCatchUp) {
            return ['generated_count' => 0, 'status' => 'skipped_catch_up'];
        }

        // Resolve racks from template (supports multi-rak and legacy single-rak)
        $racks = $this->rack->normalizeTemplateRacks($template);
        if (empty($racks)) {
            return ['generated_count' => 0, 'status' => 'invalid_template', 'reason' => 'no_racks'];
        }

        // ── 1. Resolve selected waiters ──
        $selectedIdsRaw = $template['selected_waiter_ids'] ?? [];
        if (! is_array($selectedIdsRaw)) {
            $selectedIdsRaw = [];
        }
        $selectedIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $selectedIdsRaw
        ), fn ($id) => $id !== '')));

        if (empty($selectedIds)) {
            foreach ($racks as $rack) {
                $rackId = (string) ($rack['id'] ?? '');
                if ($rackId !== '') {
                    $this->rack->createRackCheckOverflow(array_merge($template, [
                        'rack_id' => $rackId,
                        'rack_name' => (string) ($rack['name'] ?? ''),
                    ]), $targetDate, 'empty_waiter_list', ['evaluated' => [], 'rejected_candidates' => []], $rackId);
                }
            }
            return ['generated_count' => 0, 'status' => 'skipped_no_eligible_waiter', 'reason' => 'empty_waiter_list'];
        }

        // ── 2. Resolve counter (current rotation index) ──
        $counterPath = 'waiter_task_round_robin_counters/'.$templateId;
        $counterSnap = $this->database->getReference($counterPath)->getSnapshot();
        $counter = $counterSnap->exists() ? (array) $counterSnap->getValue() : [];
        $currentIdx = (int) ($counter['next_index'] ?? 0);
        $loopCount = count($selectedIds);
        if ($currentIdx < 0 || $currentIdx >= $loopCount) {
            $currentIdx = 0;
        }

        // ── 3. Find eligible waiter from rotation (shared across all racks) ──
        $evaluated = [];
        $selectedWaiter = null;
        $pickedIdx = null;
        for ($i = 0; $i < $loopCount; $i++) {
            $idx = ($currentIdx + $i) % $loopCount;
            $candidateId = $selectedIds[$idx];

            try {
                $waiter = $this->firebase->getWaiterById($candidateId);
            } catch (\Throwable $e) {
                report($e);
                $waiter = null;
            }
            if (! $waiter || ($waiter['is_active'] ?? true) === false) {
                $evaluated[] = [
                    'waiter_id' => $candidateId,
                    'name' => '—',
                    'reason' => 'Tidak aktif atau tidak ditemukan',
                ];
                continue;
            }

            $isWorking = $this->shift->isWorkingDay($candidateId, $targetDate);
            $dailyCap = $isWorking ? $this->rack->getRackCheckDailyCap($candidateId, $targetDate, $template) : 0;
            $todayCount = $this->rack->countRackCheckTasksForWaiterOnDate($candidateId, $targetDate);

            if (! $isWorking) {
                $evaluated[] = [
                    'waiter_id' => $candidateId,
                    'name' => (string) ($waiter['name'] ?? ''),
                    'reason' => 'LIBUR',
                ];
                continue;
            }
            if ($todayCount >= $dailyCap) {
                $evaluated[] = [
                    'waiter_id' => $candidateId,
                    'name' => (string) ($waiter['name'] ?? ''),
                    'reason' => "Sudah {$todayCount}/{$dailyCap} task hari ini",
                ];
                continue;
            }

            $selectedWaiter = $waiter;
            $pickedIdx = $idx;
            break;
        }

        if ($selectedWaiter === null) {
            foreach ($racks as $rack) {
                $rackId = (string) ($rack['id'] ?? '');
                if ($rackId !== '') {
                    $this->rack->createRackCheckOverflow(array_merge($template, [
                        'rack_id' => $rackId,
                        'rack_name' => (string) ($rack['name'] ?? ''),
                    ]), $targetDate, 'no_eligible_in_rotation', [
                        'evaluated' => $evaluated,
                        'rejected_candidates' => $evaluated,
                    ], $rackId);
                }
            }
            return ['generated_count' => 0, 'status' => 'skipped_no_eligible_waiter', 'reason' => 'no_eligible_in_rotation'];
        }

        // ── 4. Build assignment_reason ──
        $assignmentReason = [
            'mode' => 'round_robin_simple',
            'selected_waiter_id' => (string) ($selectedWaiter['id'] ?? ''),
            'selected_waiter_name' => (string) ($selectedWaiter['name'] ?? ''),
            'reason' => sprintf(
                '%s mendapat giliran (slot ke-%d) dan masuk kerja hari ini.',
                $selectedWaiter['name'] ?? 'Waiter',
                $pickedIdx + 1
            ),
            'rotation_start_index' => $currentIdx,
            'rotation_picked_index' => $pickedIdx,
            'rotation_size' => $loopCount,
            'rotation_skipped' => count($evaluated),
            'rejected_candidates' => $evaluated,
        ];

        // ── 5. Iterate racks, create task + lock per rak ──
        $totalGenerated = 0;
        $rackResults = [];
        $lastTaskId = null;

        foreach ($racks as $rack) {
            $rackId = (string) ($rack['id'] ?? '');
            if ($rackId === '') {
                continue;
            }

            // Lock check per rak
            $existingLock = $this->getSimpleLowestLoadLock($templateId, $targetDate, $rackId);
            if ($existingLock !== null && ! $force) {
                $lockStatus = (string) ($existingLock['status'] ?? '');
                if (in_array($lockStatus, ['generated', 'skipped_no_eligible_waiter', 'cancelled_by_admin'], true)) {
                    $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'lock_exists', 'reason' => $lockStatus];
                    continue;
                }
            }

            // Build virtual single-rack template
            $singleRackTemplate = array_merge($template, [
                'rack_id' => $rackId,
                'rack_name' => $rack['name'] ?? '',
                'rack_location' => $rack['location'] ?? '',
                'rack_barcode_value' => $rack['barcode_value'] ?? '',
                'rack_type' => $rack['rack_type'] ?? 'storage',
            ]);

            $createResult = $this->createSimpleLowestLoadTask(
                $singleRackTemplate,
                $selectedWaiter,
                $targetDate,
                $assignmentReason
            );

            if (! ($createResult['success'] ?? false)) {
                $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'create_failed', 'reason' => $createResult['reason'] ?? 'unknown'];
                continue;
            }

            // Write lock per rak
            $this->writeSimpleLowestLoadLock($templateId, $targetDate, [
                'status' => 'generated',
                'mode' => 'round_robin_simple',
                'task_id' => (string) ($createResult['task_node_key'] ?? ''),
                'assigned_waiter_id' => (string) ($selectedWaiter['id'] ?? ''),
                'assigned_waiter_name' => (string) ($selectedWaiter['name'] ?? ''),
                'rotation_picked_index' => $pickedIdx,
                'rotation_size' => $loopCount,
            ], $rackId);

            $totalGenerated++;
            $lastTaskId = (string) ($createResult['task_node_key'] ?? '');
            $rackResults[] = ['rack_id' => $rackId, 'rack_name' => $rack['name'] ?? '', 'status' => 'generated', 'assigned_waiter_id' => (string) ($selectedWaiter['id'] ?? '')];
        }

        // ── 6. Advance counter ke slot setelah pickedIdx ──
        $nextIdx = ($pickedIdx + 1) % $loopCount;
        try {
            $this->database->getReference($counterPath)->set([
                'template_id' => $templateId,
                'next_index' => $nextIdx,
                'last_picked_index' => $pickedIdx,
                'last_picked_waiter_id' => (string) ($selectedWaiter['id'] ?? ''),
                'last_picked_at' => time(),
                'last_picked_date' => $targetDate,
                'rotation_size' => $loopCount,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($totalGenerated === 0 && ! empty($rackResults)) {
            $firstResult = $rackResults[0];
            return ['generated_count' => 0, 'status' => $firstResult['status'] ?? 'skipped', 'reason' => $firstResult['reason'] ?? '', 'rack_results' => $rackResults];
        }

        return [
            'generated_count' => $totalGenerated,
            'status' => 'generated',
            'task_id' => $lastTaskId,
            'assigned_waiter_id' => (string) ($selectedWaiter['id'] ?? ''),
            'rack_results' => $rackResults,
        ];
    }

    /**
     * Count tasks assigned to a waiter for a specific date.
     * Combines waiter_tasks and rack_check_planning counts.
     *
     * @param  string  $waiterId
     * @param  string  $date     Format: YYYY-MM-DD
     * @return int
     */
    public function getWaiterTaskCountForDate(string $waiterId, string $date): int
    {
        $count = 0;

        // Count from /waiter_tasks
        try {
            $snapshot = $this->database
                ->getReference('waiter_tasks')
                ->orderByChild('assigned_waiter_id')
                ->equalTo($waiterId)
                ->getSnapshot();

            if ($snapshot->exists()) {
                $waiterTasks = (array) ($snapshot->getValue() ?? []);
                foreach ($waiterTasks as $task) {
                    if (! is_array($task)) {
                        continue;
                    }
                    if (($task['scheduled_for_date'] ?? '') !== $date) {
                        continue;
                    }
                    $status = (string) ($task['status'] ?? '');
                    if ($status === 'cancelled') {
                        continue;
                    }
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[FirebaseService] getWaiterTaskCountForDate: waiter_tasks query failed', [
                'waiter_id' => $waiterId,
                'date'      => $date,
                'error'     => $e->getMessage(),
            ]);
        }

        // Count from /rack_check_planning
        try {
            $planningTasks = $this->getPlanningTasksForDate($date);
            foreach ($planningTasks as $task) {
                if (($task['assigned_to'] ?? '') !== $waiterId) {
                    continue;
                }
                $status = (string) ($task['status'] ?? '');
                if (in_array($status, ['planned', 'pending'], true)) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[FirebaseService] getWaiterTaskCountForDate: planning query failed', [
                'waiter_id' => $waiterId,
                'date'      => $date,
                'error'     => $e->getMessage(),
            ]);
        }

        return $count;
    }

    /**
     * Remove a waiter task by setting its node to null.
     */
    public function removeWaiterTask(string $waiterTaskId): void
    {
        try {
            $this->database->getReference('waiter_tasks/' . $waiterTaskId)->remove();
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] removeWaiterTask error', [
                'task_id' => $waiterTaskId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update specific fields of an existing waiter task.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateWaiterTask(string $waiterTaskId, array $data): void
    {
        try {
            $data['updated_at'] = time();
            $this->database->getReference('waiter_tasks/' . $waiterTaskId)->update($data);
        } catch (\Throwable $e) {
            \Log::error('[FirebaseService] updateWaiterTask error', [
                'task_id' => $waiterTaskId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
