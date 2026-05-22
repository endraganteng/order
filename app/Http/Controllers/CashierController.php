<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Show cashier view
     */
    public function index()
    {
        $lastSync = (int) session('cashier_last_sync', 0);
        $now = time();

        if ($now - $lastSync >= 30) {
            $this->firebase->generateDueRecurringTasks();
            $this->firebase->markOverdueTasks();
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
        $overdue = $this->firebase->markOverdueTasks();

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

        $result = $this->firebase->updateTaskStatus(
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
     * List notifikasi pembayaran DANA Bisnis (untuk tab "DANA Masuk").
     * Default: hari ini, newest-first, max 100 entries.
     * Query params:
     *   - date=YYYY-MM-DD (default: today)
     *   - limit=N (default 100, max 500)
     */
    public function getDanaPayments(Request $request)
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 500));

        // Validasi format tanggal sederhana
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $rows = DB::table('dana_payment_notifications')
            ->whereDate('received_at', $date)
            ->orderBy('received_at', 'desc')
            ->limit($limit)
            ->get([
                'id',
                'payhook_reference',
                'amount',
                'source',
                'sender_name',
                'notification_title',
                'notification_text',
                'notified_at',
                'received_at',
                'firebase_key',
            ]);

        $total = (int) DB::table('dana_payment_notifications')
            ->whereDate('received_at', $date)
            ->sum('amount');

        $count = (int) DB::table('dana_payment_notifications')
            ->whereDate('received_at', $date)
            ->count();

        return response()->json([
            'success'   => true,
            'date'      => $date,
            'count'     => $count,
            'total'     => $total,
            'payments'  => $rows->map(function ($r) {
                return [
                    'id'                  => (int) $r->id,
                    'payhook_reference'   => $r->payhook_reference,
                    'amount'              => (int) $r->amount,
                    'source'              => $r->source,
                    'sender_name'         => $r->sender_name,
                    'notification_title'  => $r->notification_title,
                    'notification_text'   => $r->notification_text,
                    'notified_at'         => $r->notified_at,
                    'received_at'         => $r->received_at,
                    'firebase_key'        => $r->firebase_key,
                ];
            })->values(),
        ]);
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
}
