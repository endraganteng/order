<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

class AttendanceFirebaseService
{
    protected $database;
    protected FirebaseService $firebase;

    public function __construct(Database $database, FirebaseService $firebase)
    {
        $this->database = $database;
        $this->firebase = $firebase;
    }

    /**
     * Get active waiters who are still required to attend.
     */
    public function getAttendanceEligibleWaiters(): array
    {
        $waiters = array_values(array_filter($this->firebase->getActiveWaiters(), function ($waiter) {
            return empty($waiter['attendance_exempt']);
        }));

        usort($waiters, function ($a, $b) {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $waiters;
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
     * Get current attendance QR payload for cashier display.
     */
    public function getCashierAttendanceQrData(string $waiterId): array
    {
        $today = date('Y-m-d');
        $waiter = $this->firebase->getWaiterById($waiterId);

        if (! $waiter || (($waiter['is_active'] ?? true) === false) || ! empty($waiter['attendance_exempt'])) {
            return [
                'found' => false,
                'available' => false,
                'message' => 'Waiter tidak tersedia untuk absensi QR.',
            ];
        }

        $attendance = $this->getAttendanceByDate($waiterId, $today) ?? [];
        $settings = $this->firebase->getSettings();
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
    public function processAttendanceQrScan(string $waiterId, string $purpose, string $scannedValue, string $method = 'qr_scan', ?int $clientTimestamp = null): array
    {
        $waiterId = trim($waiterId);
        $purpose = $purpose === 'clock_out' ? 'clock_out' : 'clock_in';
        $scannedValue = trim($scannedValue);

        if ($waiterId === '' || $scannedValue === '' || ! str_starts_with(strtoupper($scannedValue), 'ATTENDANCE:')) {
            return ['success' => false, 'message' => 'QR code absensi tidak valid'];
        }

        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter || (($waiter['is_active'] ?? true) === false)) {
            return ['success' => false, 'message' => 'Data waiter tidak ditemukan'];
        }

        if (! empty($waiter['attendance_exempt'])) {
            return ['success' => false, 'message' => 'Waiter ini tidak wajib menggunakan absensi QR'];
        }

        $settings = $this->firebase->getSettings();
        if ($purpose === 'clock_out' && empty($settings['clock_out_enabled'])) {
            return ['success' => false, 'message' => 'Fitur absen pulang tidak aktif'];
        }

        $today = date('Y-m-d');
        $nowTimestamp = time();
        $nowTime = date('H:i', $nowTimestamp);
        $status = 'present';
        $lateMinutes = 0;

        // BUG FIX (#11): Compute client-server clock skew for audit logging.
        // We KEEP using server time for late detection (prevent client clock manipulation),
        // but log the skew so we can detect server lag affecting waiters unfairly.
        $clientTimestampSeconds = $clientTimestamp !== null ? (int) round($clientTimestamp / 1000) : null;
        $clockSkewSeconds = $clientTimestampSeconds !== null ? ($nowTimestamp - $clientTimestampSeconds) : null;

        if ($purpose === 'clock_in') {
            $shift = $this->firebase->getWaiterShiftForDate($waiterId, $today);
            
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

        $this->database->runTransaction(function ($transaction) use ($attendancePath, $tokenPath, $waiterId, $today, $purpose, $scannedValue, $method, $nowTimestamp, $nowTime, $status, $lateMinutes, $clockSkewSeconds, &$result) {
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

                // BUG FIX (#11): Log clock skew for audit. >5s skew = potential server lag issue.
                if ($clockSkewSeconds !== null) {
                    $record['clock_in_client_skew_seconds'] = $clockSkewSeconds;
                    if (abs($clockSkewSeconds) > 5) {
                        \Log::warning('[ATTENDANCE_LAG] Clock skew detected at scan', [
                            'waiter_id' => $waiterId,
                            'date' => $today,
                            'purpose' => $purpose,
                            'skew_seconds' => $clockSkewSeconds,
                            'late_minutes' => $lateMinutes,
                            'status' => $status,
                        ]);
                    }
                }

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

                if ($clockSkewSeconds !== null) {
                    $record['clock_out_client_skew_seconds'] = $clockSkewSeconds;
                }

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
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (!$waiter || !($waiter['is_active'] ?? true)) {
            return ['success' => false, 'message' => 'Data waiter tidak ditemukan'];
        }
        
        if (!empty($waiter['attendance_exempt'])) {
            return ['success' => false, 'message' => 'Waiter ini tidak wajib absensi'];
        }
        
        // Check settings
        $settings = $this->firebase->getSettings();
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
                $shift = $this->firebase->getWaiterShiftForDate($waiterId, $today);
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

    /**
     * Mirror a Firebase attendance record into MySQL (flag-gated dual-write).
     * Reads the canonical node value and upserts by waiter+date. Never fatal.
     */
    protected function syncAttendanceToMysql(string $waiterId, string $date): void
    {
        try {
            $snap = $this->database->getReference('waiter_attendance/'.$waiterId.'/'.$date)->getSnapshot();
            $record = $snap->exists() ? (array) $snap->getValue() : null;
            if ($record === null) {
                return;
            }

            \App\Models\WaiterAttendance::updateOrCreate(
                ['waiter_id' => $waiterId, 'date' => $date],
                [
                    'status' => $record['status'] ?? null,
                    'late_minutes' => (int) ($record['late_minutes'] ?? 0),
                    'clock_in' => $record['clock_in'] ?? null,
                    'clock_out' => $record['clock_out'] ?? null,
                    'data' => $record,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getAttendanceByDate(string $waiterId, string $date): ?array
    {
        return app(\App\Repositories\Contracts\AttendanceRepositoryInterface::class)->forWaiterOnDate($waiterId, $date);
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
        return app(\App\Repositories\Contracts\AttendanceRepositoryInterface::class)->forWaiterInMonth($waiterId, $yearMonth);
    }

    /**
     * Get all waiters' attendance for a specific date.
     */
    public function getAllAttendanceByDate(string $date): array
    {
        return app(\App\Repositories\Contracts\AttendanceRepositoryInterface::class)->allOnDate($date);
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

        if (config('features.mysql_attendance')) {
            $this->syncAttendanceToMysql($waiterId, $date);
        }
    }

    /**
     * Get attendance summary for a waiter in a given month.
     */
    public function getAttendanceSummary(string $waiterId, string $yearMonth): array
    {
        $records = $this->getAttendanceByMonth($waiterId, $yearMonth);
        $schedule = $this->firebase->getWaiterSchedule($waiterId);

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
}
