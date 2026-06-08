<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\CashierTaskRepositoryInterface;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase,
        protected CashierTaskRepositoryInterface $cashierTasks,
    ) {
    }

    /**
     * Show cashier view
     */
    public function index()
    {
        $lastSync = (int) session('cashier_last_sync', 0);
        $now = time();

        if ($now - $lastSync >= 30) {
            $this->cashierTasks->markOverdue();
            $this->firebase->generateDueRecurringTasks();
            session(['cashier_last_sync' => $now]);
        }

        $cashierWorkers = $this->firebase->getActiveCashierWorkers();
        $attendanceWaiters = $this->firebase->getAttendanceEligibleWaiters();
        $settings = $this->firebase->getSettings();

        // Get waiters who have shift today but haven't clocked in yet
        $today = date('Y-m-d');
        $waitersNotYetClocked = [];
        
        foreach ($attendanceWaiters as $waiter) {
            $waiterId = $waiter['id'] ?? '';
            $shift = $this->firebase->getWaiterShiftForDate($waiterId, $today);
            
            // Only include if waiter has shift today (not off)
            if ($shift) {
                $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
                
                // Check if not clocked in yet
                if (!$attendance || empty($attendance['clock_in'])) {
                    $waitersNotYetClocked[] = [
                        'id' => $waiterId,
                        'name' => $waiter['name'] ?? 'Unknown',
                        'shift_name' => $shift['name'] ?? 'Shift',
                        'clock_in_time' => $shift['clock_in_time'] ?? '-',
                    ];
                }
            }
        }

        return view('cashier.index', compact('cashierWorkers', 'attendanceWaiters', 'settings', 'waitersNotYetClocked'));
    }

    /**
     * Get current attendance QR data for selected waiter.
     */
    public function getAttendanceQr(Request $request)
    {
        $waiterId = trim((string) $request->query('waiter_id', ''));
        if ($waiterId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Waiter harus dipilih terlebih dahulu.',
            ], 422);
        }

        $payload = $this->firebase->getCashierAttendanceQrData($waiterId);
        if (empty($payload['found'])) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'] ?? 'Waiter tidak ditemukan.',
            ], 404);
        }

        return response()->json(array_merge(['success' => true], $payload));
    }

    /**
     * Get global attendance QR (scan-triggered rotating mode).
     */
    public function getGlobalAttendanceQr()
    {
        $qrData = $this->firebase->getCurrentGlobalAttendanceQr();
        $today = date('Y-m-d');
        
        // Calculate statistics and build waiters list
        $eligibleWaiters = $this->firebase->getAttendanceEligibleWaiters();
        $notYet = 0;
        $clockedIn = 0;
        $clockedOut = 0;
        $waitersNotYetClocked = [];
        
        foreach ($eligibleWaiters as $waiter) {
            $waiterId = $waiter['id'] ?? '';
            $attendance = $this->firebase->getAttendanceByDate($waiterId, $today);
            
            // Check if waiter has shift today (not day off)
            $shift = $this->firebase->getWaiterShiftForDate($waiterId, $today);
            
            // Skip if waiter is off today (no shift)
            if (!$shift) {
                continue;
            }
            
            if (empty($attendance['clock_in'])) {
                $notYet++;
                
                // Add to not-yet-clocked list with shift info
                $waitersNotYetClocked[] = [
                    'id' => $waiterId,
                    'name' => $waiter['name'] ?? 'Unknown',
                    'shift_name' => $shift['name'] ?? 'Shift',
                    'clock_in_time' => $shift['clock_in_time'] ?? '-',
                ];
            } elseif (empty($attendance['clock_out'])) {
                $clockedIn++;
            } else {
                $clockedOut++;
            }
        }
        
        // Get last scanned waiter name
        $lastScannedWaiterName = null;
        if (!empty($qrData['last_scanned_by'])) {
            $lastWaiter = $this->firebase->getWaiterById($qrData['last_scanned_by']);
            $lastScannedWaiterName = $lastWaiter['name'] ?? null;
        }
        
        return response()->json([
            'success' => true,
            'qr_value' => $qrData['qr_value'],
            'generated_at' => $qrData['generated_at'],
            'scan_count' => $qrData['scan_count'],
            'last_scanned_by' => $qrData['last_scanned_by'] ?? null,
            'last_scanned_waiter_name' => $lastScannedWaiterName,
            'date' => $today,
            'message' => 'Scan QR ini untuk absen masuk/pulang',
            'stats' => [
                'total_waiters' => count($eligibleWaiters),
                'not_yet' => $notYet,
                'clocked_in' => $clockedIn,
                'clocked_out' => $clockedOut,
            ],
            'waiters_not_yet_clocked' => $waitersNotYetClocked,
        ]);
    }

    /**
     * Get active cashier workers for cashier client
     */
    public function getCashierWorkers()
    {
        return response()->json([
            'success' => true,
            'workers' => $this->firebase->getActiveCashierWorkers(),
        ]);
    }

    /**
     * Generate due recurring tasks (polling endpoint)
     */
    public function syncDueTasks()
    {
        $lastSync = (int) session('cashier_last_sync', 0);
        $now = time();

        if ($now - $lastSync < 30) {
            return response()->json([
                'success' => true,
                'generated' => 0,
                'overdue' => 0,
                'skipped' => true,
            ]);
        }

        session(['cashier_last_sync' => $now]);

        $generated = $this->firebase->generateDueRecurringTasks();
        $overdue = $this->cashierTasks->markOverdue();

        return response()->json([
            'success' => true,
            'generated' => $generated,
            'overdue' => $overdue,
        ]);
    }

    /**
     * Update task status from cashier page
     */
    public function updateTaskStatus($id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:done',
            'note' => 'nullable|string|max:500',
            'cashier_worker_id' => 'required|string|max:100',
        ]);

        $worker = $this->firebase->getCashierWorkerById($request->cashier_worker_id);
        if (! $worker || empty($worker['is_active'])) {
            return response()->json([
                'success' => false,
                'message' => 'Nama kasir tidak valid atau sudah nonaktif',
            ], 422);
        }

        $result = $this->cashierTasks->updateStatus(
            $id,
            $request->status,
            $request->note,
            $worker['id'],
            $worker['name'] ?? null
        );

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Proxy Google Cloud Text-to-Speech API.
     * Browser POST {text, voice} → server panggil Google TTS → return MP3 audio bytes.
     * API key TIDAK pernah expose ke browser.
     *
     * Body JSON:
     *   - text (required, max 500 char): teks yang mau diucapkan
     *   - voice (optional): nama voice id-ID-Wavenet-A, id-ID-Neural2-B, dll. Default dari config.
     *   - speed (optional, 0.5-2.0): kecepatan bicara, default 1.0
     *
     * Response:
     *   - 200 audio/mpeg (binary MP3)
     *   - 422 kalau input invalid
     *   - 500 kalau Google API error
     */
    public function ttsSpeak(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:500',
            'voice' => 'nullable|string|max:50',
            'speed' => 'nullable|numeric|min:0.5|max:2.0',
        ]);

        $apiKey = (string) config('services.google_tts.api_key');
        if ($apiKey === '') {
            return response()->json([
                'success' => false,
                'message' => 'Google TTS belum dikonfigurasi (GOOGLE_TTS_API_KEY missing).',
            ], 500);
        }

        $text = (string) $request->input('text');
        $voice = (string) $request->input('voice', config('services.google_tts.default_voice'));
        $speed = (float) $request->input('speed', 1.0);

        // Cache 24 jam: text + voice + speed yang sama → return audio yang sudah di-cache.
        // Ini hemat quota Google untuk teks yang sering muncul (mis. test button, nominal serupa).
        // Audio bytes di-base64 sebelum cache karena cache driver (MySQL) tidak handle binary.
        $cacheKey = 'tts_google_' . sha1($text . '|' . $voice . '|' . $speed);
        $cachedB64 = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cachedB64 !== null) {
            $cachedBytes = base64_decode($cachedB64);
            if ($cachedBytes !== false) {
                return response($cachedBytes, 200, [
                    'Content-Type' => 'audio/mpeg',
                    'X-TTS-Cache' => 'HIT',
                    'Content-Length' => (string) strlen($cachedBytes),
                ]);
            }
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $apiKey, [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => 'id-ID',
                        'name' => $voice,
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                        'speakingRate' => $speed,
                    ],
                ]);

            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::warning('Google TTS API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Google TTS error: ' . $response->status(),
                ], 502);
            }

            $payload = $response->json();
            $audioBase64 = $payload['audioContent'] ?? null;
            if (! $audioBase64) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google TTS tidak return audio content.',
                ], 502);
            }

            // Cache pakai base64 (string) supaya safe untuk semua cache driver.
            \Illuminate\Support\Facades\Cache::put($cacheKey, $audioBase64, now()->addHours(24));

            $audioBytes = base64_decode($audioBase64);
            if ($audioBytes === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audio content tidak bisa di-decode.',
                ], 502);
            }

            return response($audioBytes, 200, [
                'Content-Type' => 'audio/mpeg',
                'X-TTS-Cache' => 'MISS',
                'Content-Length' => (string) strlen($audioBytes),
            ]);
        } catch (\Throwable $e) {
            // Sanitize: pastikan message valid UTF-8 sebelum di-JSON-encode
            $safeMessage = mb_convert_encoding(
                (string) $e->getMessage(),
                'UTF-8',
                'UTF-8'
            );
            \Illuminate\Support\Facades\Log::warning('Google TTS request failed', [
                'error' => $safeMessage,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'TTS service unavailable.',
            ], 503);
        }
    }

    /**
     * Diagnostic endpoint untuk troubleshoot TTS di production.
     * Cek:
     *  - apakah GOOGLE_TTS_API_KEY ke-load
     *  - apakah cache table ada
     *  - apakah server bisa reach Google TTS API
     *
     * Hanya tampilkan info teknis, TIDAK expose API key.
     */
    public function ttsHealth()
    {
        $checks = [];

        // 1. API key configured?
        $apiKey = (string) config('services.google_tts.api_key');
        $checks['api_key_configured'] = $apiKey !== '';
        $checks['api_key_length'] = strlen($apiKey);
        $checks['api_key_preview'] = $apiKey !== '' ? substr($apiKey, 0, 10) . '...' . substr($apiKey, -4) : null;

        // 2. Default voice
        $checks['default_voice'] = config('services.google_tts.default_voice');

        // 3. Cache table exists?
        try {
            \Illuminate\Support\Facades\DB::table('cache')->limit(1)->get();
            $checks['cache_table_ok'] = true;
        } catch (\Throwable $e) {
            $checks['cache_table_ok'] = false;
            $checks['cache_table_error'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
        }

        // 4. Cache write/read test
        try {
            $testKey = 'tts_health_' . time();
            \Illuminate\Support\Facades\Cache::put($testKey, 'hello', 60);
            $read = \Illuminate\Support\Facades\Cache::get($testKey);
            \Illuminate\Support\Facades\Cache::forget($testKey);
            $checks['cache_write_ok'] = $read === 'hello';
        } catch (\Throwable $e) {
            $checks['cache_write_ok'] = false;
            $checks['cache_write_error'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
        }

        // 5. Reach Google TTS API
        if ($apiKey !== '') {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $apiKey, [
                        'input' => ['text' => 'test'],
                        'voice' => ['languageCode' => 'id-ID', 'name' => 'id-ID-Wavenet-A'],
                        'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 1.0],
                    ]);
                $checks['google_reachable'] = true;
                $checks['google_status'] = $response->status();
                $checks['google_ok'] = $response->successful();
                if (! $response->successful()) {
                    $checks['google_error_body'] = substr($response->body(), 0, 500);
                }
            } catch (\Throwable $e) {
                $checks['google_reachable'] = false;
                $checks['google_error'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
            }
        } else {
            $checks['google_reachable'] = 'skipped (no api key)';
        }

        // 6. PHP & Laravel info
        $checks['php_version'] = PHP_VERSION;
        $checks['laravel_env'] = config('app.env');
        $checks['cache_driver'] = config('cache.default');

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], 200, [], JSON_PRETTY_PRINT);
    }
}
