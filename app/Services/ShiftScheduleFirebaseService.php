<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class ShiftScheduleFirebaseService
{
    protected $database;
    protected FirebaseService $firebase;
    protected array $requestCache = [];
    protected ?array $scheduleTemplateCache = null;

    public function __construct(Database $database, FirebaseService $firebase)
    {
        $this->database = $database;
        $this->firebase = $firebase;
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
        $existing = $this->firebase->getRecurringWaiterTaskTemplateById($id);
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
                    $existingTasks = $this->firebase->getWaiterTasksForDate($wId, $candidateDate);
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

        // BUG FIX (#7): PRIORITY 4 — Emergency supervisor fallback.
        // Before giving up, try to assign to an active supervisor working today
        // (or tomorrow). Tagged with is_emergency_assignment=true so admin can
        // identify these in dashboards. Skip if waiter is attendance_exempt.
        try {
            $supervisors = $this->firebase->getActiveWaitersByRole('supervisor');
            $candidateDates = [$originalDate, date('Y-m-d', strtotime($originalDate.' +1 day'))];

            foreach ($supervisors as $supervisor) {
                $supId = (string) ($supervisor['id'] ?? '');
                if ($supId === '' || ! empty($supervisor['attendance_exempt'])) {
                    continue;
                }

                foreach ($candidateDates as $candidateDate) {
                    if (! $this->isWorkingDay($supId, $candidateDate)) {
                        continue;
                    }

                    try {
                        $fonnte = app(\App\Services\FonnteService::class);
                        $fonnte->notifyTaskRescheduled(
                            $template,
                            $originalTargetWaiters[0] ?? [],
                            $supervisor,
                            $originalDate,
                            $candidateDate
                        );
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    try {
                        $this->database->getReference('audit_logs/emergency_assignments')->push([
                            'template_id' => $template['id'] ?? null,
                            'template_title' => $template['title'] ?? '',
                            'supervisor_id' => $supId,
                            'supervisor_name' => $supervisor['name'] ?? '',
                            'original_date' => $originalDate,
                            'assigned_date' => $candidateDate,
                            'reason' => 'No regular waiter available — fallback to supervisor',
                            'created_at' => time(),
                        ]);
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    return [
                        'rescheduled' => true,
                        'new_date' => $candidateDate,
                        'original_date' => $originalDate,
                        'waiters' => [$supervisor],
                        'is_emergency_assignment' => true,
                    ];
                }
            }
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
        try {
            $targetDt = new \DateTimeImmutable($date);
        } catch (\Throwable $e) {
            return 0;
        }

        $anchorDt = null;
        if ($anchor) {
            try {
                $anchorDt = new \DateTimeImmutable($anchor);
            } catch (\Throwable $e) {
                $anchorDt = null;
            }
        }

        if ($anchorDt === null || $anchorDt > $targetDt) {
            // No anchor / anchor in future → fall back to absolute period bucket
            $period = strtolower($period);
            if ($period === 'monthly') {
                return ((int) $targetDt->format('Y')) * 12 + ((int) $targetDt->format('n')) - 1;
            }
            // Use ISO week number for weekly to align with Monday-based weeks
            if ($period === 'weekly') {
                return ((int) $targetDt->format('o')) * 52 + ((int) $targetDt->format('W'));
            }
            // Daily: days since epoch using date_diff for DST safety
            $epoch = new \DateTimeImmutable('1970-01-01');
            return (int) $epoch->diff($targetDt)->days;
        }

        $period = strtolower($period);
        if ($period === 'monthly') {
            $monthsTarget = ((int) $targetDt->format('Y')) * 12 + ((int) $targetDt->format('n'));
            $monthsAnchor = ((int) $anchorDt->format('Y')) * 12 + ((int) $anchorDt->format('n'));

            return max(0, $monthsTarget - $monthsAnchor);
        }

        // DST-safe day difference
        $diffDays = (int) $anchorDt->diff($targetDt)->days;
        if ($period === 'weekly') {
            return (int) floor($diffDays / 7);
        }

        // daily (default)
        return $diffDays;
    }

    /**
     * Compute period key for rotation counter persistence.
     * Format: '{period}_{key}' (e.g., 'weekly_2026-W22', 'monthly_2026-05', 'daily_2026-05-31').
     */
    protected function buildRotationPeriodKey(string $date, string $period): string
    {
        try {
            $dt = new \DateTimeImmutable($date);
        } catch (\Throwable $e) {
            return strtolower($period) . '_' . $date;
        }

        $period = strtolower($period);
        if ($period === 'monthly') {
            return 'monthly_' . $dt->format('Y-m');
        }
        if ($period === 'weekly') {
            return 'weekly_' . $dt->format('o-\WW');
        }

        return 'daily_' . $dt->format('Y-m-d');
    }

    /**
     * Convert YYYY-MM-DD and HH:MM to Unix timestamp
     */
    protected function buildScheduledTimestamp($date, $time)
    {
        return strtotime($date.' '.$time);
    }

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
     * Get waiter's shift for a specific date.
     *
     * Multi-source check (priority order):
     *  1. Rotation pattern (legacy primary kasir / backup with rotation)
     *  2. Retail schedule tool (kalau dia retail employee) — convert to /work_shifts schema
     *  3. Kasir schedule tool (kalau dia kasir/backup finance) — convert to /work_shifts schema
     *  4. Fallback ke /waiter_schedule_template/{wid}/{day} (admin/exempt/legacy)
     *
     * Returns array kompatibel /work_shifts schema:
     *   { id, name, clock_in_time, clock_out_time, late_tolerance_minutes }
     * Atau null kalau libur/no schedule.
     *
     * Result is cached in-request to minimize Firebase reads dalam 1 cron cycle.
     */
    public function getWaiterShiftForDate(string $waiterId, string $date): ?array
    {
        $cacheKey = 'waiterShift:'.$waiterId.'|'.$date;
        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

        $shift = $this->resolveWaiterShiftForDate($waiterId, $date);
        $this->requestCache[$cacheKey] = $shift;

        return $shift;
    }

    private function resolveWaiterShiftForDate(string $waiterId, string $date): ?array
    {
        // PRIORITY 1: Check rotation pattern first (legacy)
        $pattern = $this->getRotationPattern($waiterId);
        if ($pattern) {
            if ($pattern['role'] === 'primary') {
                $isOffDay = $this->isRotationOffDay($pattern, $date);
                if ($isOffDay) {
                    return null;
                }
                $shiftId = $pattern['default_shift_id'];
                if ($shiftId) {
                    return $this->getShiftById($shiftId);
                }
            } elseif ($pattern['role'] === 'backup') {
                $coverage = $this->firebase->getBackupCoverage($waiterId, $date);
                if ($coverage) {
                    return $this->getShiftById($coverage['shift_id']);
                }
                return null;
            }
        }

        // PRIORITY 2: Retail schedule tool
        try {
            $retailService = app(\App\Services\RetailScheduleService::class);
            if ($retailService->isRetailEmployee($waiterId)) {
                $shift = $this->buildShiftFromRetailTool($retailService, $waiterId, $date);
                if ($shift !== null) {
                    return $shift; // hit (working) or null (libur)
                }
                // null + retail employee = libur
                return null;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // PRIORITY 3: Kasir schedule tool
        try {
            $kasirService = app(\App\Services\KasirScheduleService::class);
            if ($kasirService->isKasirOrBackup($waiterId)) {
                $shift = $this->buildShiftFromKasirTool($kasirService, $waiterId, $date);
                if ($shift !== null) {
                    return $shift;
                }
                return null;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // PRIORITY 4: Fallback /waiter_schedule_template (admin/exempt/legacy)
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        $template = $this->getScheduleTemplate();
        $shiftId = $template[$waiterId][$dayOfWeek] ?? null;

        if (!$shiftId || $shiftId === 'off') {
            return null;
        }

        return $this->getShiftById($shiftId);
    }

    /**
     * Convert retail tool shift_today into /work_shifts schema for late detection.
     */
    private function buildShiftFromRetailTool(\App\Services\RetailScheduleService $retailService, string $waiterId, string $date): ?array
    {
        $weekStart = \Carbon\Carbon::parse($date)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $generator = app(\App\Services\ScheduleGeneratorService::class);
        $weekSched = $retailService->getWaiterWeekSchedule($waiterId, $weekStart, $generator);

        if (! is_array($weekSched) || empty($weekSched['days'])) {
            return null;
        }

        // Find day matching $date
        $matched = null;
        foreach ($weekSched['days'] as $d) {
            if (($d['date'] ?? '') === $date) {
                $matched = $d;
                break;
            }
        }
        if (! $matched) {
            return null;
        }

        $shiftCode = (string) ($matched['shift'] ?? '');
        if ($shiftCode === '' || $shiftCode === \App\Services\ScheduleGeneratorService::SHIFT_LIBUR) {
            return null;
        }

        $meta = $matched['shift_meta'] ?? null;
        if (! is_array($meta) || empty($meta['start']) || empty($meta['end'])) {
            return null;
        }

        return [
            'id' => 'retail:'.$shiftCode,
            'name' => 'Retail '.($meta['label'] ?? $shiftCode),
            'clock_in_time' => $meta['start'],
            'clock_out_time' => $meta['end'],
            'late_tolerance_minutes' => 0,
            'source' => 'retail_tool',
        ];
    }

    /**
     * Convert kasir tool shift_today into /work_shifts schema for late detection.
     */
    private function buildShiftFromKasirTool(\App\Services\KasirScheduleService $kasirService, string $waiterId, string $date): ?array
    {
        $weekStart = \Carbon\Carbon::parse($date)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $weekSched = $kasirService->getWaiterWeekSchedule($waiterId, $weekStart);

        if (! is_array($weekSched) || empty($weekSched['days'])) {
            return null;
        }

        $matched = null;
        foreach ($weekSched['days'] as $d) {
            if (($d['date'] ?? '') === $date) {
                $matched = $d;
                break;
            }
        }
        if (! $matched) {
            return null;
        }

        $shiftCode = (string) ($matched['shift'] ?? '');
        if ($shiftCode === '' || $shiftCode === \App\Services\KasirScheduleService::SHIFT_LIBUR) {
            return null;
        }

        $meta = $matched['shift_meta'] ?? null;
        if (! is_array($meta) || empty($meta['start']) || empty($meta['end'])) {
            return null;
        }

        return [
            'id' => 'kasir:'.$shiftCode,
            'name' => 'Kasir '.($meta['label'] ?? $shiftCode),
            'clock_in_time' => $meta['start'],
            'clock_out_time' => $meta['end'],
            'late_tolerance_minutes' => 0,
            'source' => 'kasir_tool',
        ];
    }

    /**
     * Check if a waiter is working on a specific date (from template).
     *
     * Multi-source check (priority order):
     *  1. Retail schedule (kalau dia retail employee) — /retail_schedule_preferences + /retail_schedules/{week_iso}
     *  2. Kasir schedule (kalau dia kasir/backup finance) — /kasir_schedule_preferences + /kasir_schedules/{week_iso}
     *  3. Existing /waiter_schedule_template/{wid} (default fallback)
     *
     * Result is cached in-request to minimize Firebase reads dalam 1 task generation cycle.
     */
    public function isWorkingDay(string $waiterId, string $date): bool
    {
        if (! $waiterId) {
            return false;
        }

        // In-request cache via existing $requestCache
        $cacheKey = 'isWorkingDay:'.$waiterId.'|'.$date;
        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

        $result = $this->resolveIsWorkingDay($waiterId, $date);
        $this->requestCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Internal multi-source resolver untuk isWorkingDay().
     */
    private function resolveIsWorkingDay(string $waiterId, string $date): bool
    {
        // Short-circuit: attendance_exempt waiter (admin/on-call) selalu OFF
        // → AI Balancing skip, task generator skip, cron audit skip
        try {
            $waiter = $this->firebase->getWaiterById($waiterId);
            if ($waiter && ! empty($waiter['attendance_exempt'])) {
                return false;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $isManagedEmployee = false;

        // Source 1: Retail schedule
        try {
            $retailService = app(\App\Services\RetailScheduleService::class);
            if ($retailService->isRetailEmployee($waiterId)) {
                $isManagedEmployee = true;
                $generator = app(\App\Services\ScheduleGeneratorService::class);
                $weekStart = (new \DateTime($date))->modify('Monday this week')->format('Y-m-d');
                $sched = $retailService->getWaiterWeekSchedule($waiterId, $weekStart, $generator);
                if ($sched && ! empty($sched['days'])) {
                    foreach ($sched['days'] as $day) {
                        if ($day['date'] === $date) {
                            return $day['shift'] !== \App\Services\ScheduleGeneratorService::SHIFT_LIBUR;
                        }
                    }
                }
                // Schedule not yet generated for this week — assume OFF (safe).
                // Do NOT fall through to template fallback which knows nothing about retail shifts.
                return false;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Source 2: Kasir schedule
        try {
            $kasirService = app(\App\Services\KasirScheduleService::class);
            if ($kasirService->isKasirOrBackup($waiterId)) {
                $isManagedEmployee = true;
                $weekStart = (new \DateTime($date))->modify('Monday this week')->format('Y-m-d');
                $sched = $kasirService->getWaiterWeekSchedule($waiterId, $weekStart);
                if ($sched && ! empty($sched['days'])) {
                    foreach ($sched['days'] as $day) {
                        if ($day['date'] === $date) {
                            return $day['shift'] !== \App\Services\KasirScheduleService::SHIFT_LIBUR;
                        }
                    }
                }
                // Schedule not yet generated for this week — assume OFF (safe).
                // Do NOT fall through to template fallback which knows nothing about kasir shifts.
                return false;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Source 3: Existing /waiter_schedule_template/ (fallback for non-retail, non-kasir)
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
}
