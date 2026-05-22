<?php

namespace App\Http\Controllers;

use App\Services\DanaPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DanaWebhookController
 *
 * Endpoint sederhana untuk testing webhook DANA notification listener.
 * - POST /webhooks/dana-listener  → terima payload apapun, log ke file.
 * - GET  /webhooks/dana-listener/inspect → halaman lihat semua payload masuk.
 *
 * Selain logging, kalau payload dikenali sebagai notifikasi DANA Bisnis
 * (source=DANA, title="Pembayaran Masuk"), akan di-persist ke MySQL +
 * di-push ke Firebase /dana_payments untuk realtime broadcast ke kasir.
 */
class DanaWebhookController extends Controller
{
    public function __construct(private DanaPaymentService $danaPayment)
    {
    }

    /**
     * Path file log relatif ke storage/app/private.
     * Format: 1 baris JSON per request (NDJSON).
     */
    private const LOG_FILE = 'webhooks/dana-listener.ndjson';

    /**
     * Max payload size yang disimpan (bytes), antisipasi spam.
     */
    private const MAX_LOG_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * Terima POST dari app listener.
     */
    public function receive(Request $request)
    {
        $jsonBody = $this->tryJson($request);

        $entry = [
            'id'           => (string) Str::uuid(),
            'received_at'  => now()->toIso8601String(),
            'received_ts'  => time(),
            'ip'           => $request->ip(),
            'method'       => $request->method(),
            'url'          => $request->fullUrl(),
            'user_agent'   => (string) $request->userAgent(),
            'content_type' => (string) $request->header('Content-Type'),
            'headers'      => $this->safeHeaders($request),
            'query'        => $request->query(),
            'json'         => $jsonBody,
            'form'         => $request->post(),
            'raw_preview'  => $this->rawPreview($request),
        ];

        $this->appendLog($entry);

        // Persist + broadcast realtime kalau ini notifikasi DANA Bisnis valid.
        $danaResult = ['handled' => false, 'duplicate' => false, 'id' => null];
        if (is_array($jsonBody) && $this->danaPayment->isDanaPayment($jsonBody)) {
            $result = $this->danaPayment->record($jsonBody, $entry);
            $danaResult = [
                'handled'   => $result['stored'] || $result['duplicate'],
                'duplicate' => $result['duplicate'],
                'id'        => $result['id'],
            ];
        }

        return response()->json([
            'ok'          => true,
            'received'    => true,
            'message'     => 'Payload diterima dan dicatat. Lihat /webhooks/dana-listener/inspect.',
            'entry_id'    => $entry['id'],
            'received_at' => $entry['received_at'],
            'dana'        => $danaResult,
        ]);
    }

    /**
     * Halaman inspect — tampilkan semua payload masuk (newest first).
     */
    public function inspect(Request $request)
    {
        $entries = $this->readLog();
        $entries = array_reverse($entries); // newest first

        $autoRefresh = $request->query('autorefresh') !== '0';

        return response()->view('webhooks.dana_inspect', [
            'entries'     => $entries,
            'logFile'     => self::LOG_FILE,
            'autoRefresh' => $autoRefresh,
            'totalCount'  => count($entries),
        ]);
    }

    /**
     * JSON feed — dipakai polling dari halaman inspect tanpa reload.
     */
    public function feed(Request $request)
    {
        $entries = $this->readLog();
        $entries = array_reverse($entries); // newest first

        return response()->json([
            'ok'         => true,
            'count'      => count($entries),
            'server_ts'  => time(),
            'entries'    => $entries,
        ]);
    }

    /**
     * Reset log (kosongkan).
     */
    public function reset(Request $request)
    {
        Storage::disk('local')->delete(self::LOG_FILE);
        return redirect()->route('webhooks.dana_listener.inspect')
            ->with('flash', 'Log dikosongkan.');
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function appendLog(array $entry): void
    {
        $disk = Storage::disk('local');

        // Rotate kalau terlalu besar.
        if ($disk->exists(self::LOG_FILE) && $disk->size(self::LOG_FILE) > self::MAX_LOG_BYTES) {
            $rotateName = 'webhooks/dana-listener.' . date('Ymd_His') . '.ndjson';
            $disk->move(self::LOG_FILE, $rotateName);
        }

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        if ($disk->exists(self::LOG_FILE)) {
            $disk->append(self::LOG_FILE, rtrim($line, "\n"));
        } else {
            $disk->put(self::LOG_FILE, $line);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readLog(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::LOG_FILE)) {
            return [];
        }

        $content = (string) $disk->get(self::LOG_FILE);
        if ($content === '') {
            return [];
        }

        $lines = preg_split("/\r?\n/", $content) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            } else {
                $out[] = ['_unparseable' => $line];
            }
        }

        return $out;
    }

    /**
     * Filter header sensitif sebelum disimpan.
     */
    private function safeHeaders(Request $request): array
    {
        $headers = collect($request->headers->all())
            ->mapWithKeys(fn ($v, $k) => [strtolower((string) $k) => is_array($v) && count($v) === 1 ? $v[0] : $v])
            ->all();

        // Mask cookie/authorization kalau ada (jaga-jaga).
        foreach (['cookie', 'authorization'] as $sensitive) {
            if (isset($headers[$sensitive])) {
                $headers[$sensitive] = '***masked***';
            }
        }

        return $headers;
    }

    private function tryJson(Request $request): mixed
    {
        $contentType = (string) $request->header('Content-Type');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $decoded = json_decode((string) $request->getContent(), true);
            return is_array($decoded) ? $decoded : null;
        }

        // Coba paksa decode kalau body terlihat seperti JSON.
        $raw = trim((string) $request->getContent());
        if ($raw !== '' && (str_starts_with($raw, '{') || str_starts_with($raw, '['))) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function rawPreview(Request $request): string
    {
        $raw = (string) $request->getContent();
        if ($raw === '') {
            return '';
        }
        return mb_substr($raw, 0, 2000);
    }
}
