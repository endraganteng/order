<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

/**
 * DanaPaymentService
 *
 * Memproses notifikasi pembayaran DANA dari webhook PayHook:
 *  - Filter: hanya source = 'DANA' dengan package_name = 'id.dana'
 *  - Persist ke MySQL (dedup via payhook_reference unique)
 *  - Push ke Firebase RTDB /dana_payments untuk realtime listener
 */
class DanaPaymentService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Apakah payload ini notifikasi DANA yang valid untuk disimpan?
     *
     * @param array $json Body JSON dari webhook PayHook
     */
    public function isDanaPayment(array $json): bool
    {
        $source = strtoupper((string) ($json['source'] ?? ''));
        $package = strtolower((string) ($json['package_name'] ?? ''));
        $title = strtolower((string) ($json['notification_title'] ?? ''));

        if ($source !== 'DANA' && $package !== 'id.dana') {
            return false;
        }

        // Notifikasi DANA Bisnis selalu "Pembayaran Masuk".
        // Hindari notif lain (top-up pribadi, reminder, promo).
        if ($title !== '' && ! str_contains($title, 'pembayaran masuk')) {
            return false;
        }

        // Amount wajib > 0
        $amount = (int) ($json['amount'] ?? 0);
        if ($amount <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Simpan payload sebagai notifikasi pembayaran DANA + push ke Firebase.
     *
     * @return array{stored: bool, duplicate: bool, id: ?int, firebase_key: ?string}
     */
    public function record(array $json, array $rawPayload): array
    {
        $reference = (string) ($json['reference'] ?? '');
        if ($reference === '') {
            return ['stored' => false, 'duplicate' => false, 'id' => null, 'firebase_key' => null];
        }

        // Dedup check
        $existing = DB::table('dana_payment_notifications')
            ->where('payhook_reference', $reference)
            ->first();

        if ($existing) {
            return [
                'stored' => false,
                'duplicate' => true,
                'id' => (int) $existing->id,
                'firebase_key' => $existing->firebase_key,
            ];
        }

        $amount = (int) ($json['amount'] ?? 0);
        $notificationText = (string) ($json['notification_text'] ?? '');
        $senderName = $this->parseSenderName($notificationText);
        $notifiedAt = $this->parseTimestamp((string) ($json['timestamp'] ?? ''));
        $receivedAt = now();

        $id = DB::table('dana_payment_notifications')->insertGetId([
            'payhook_reference'   => $reference,
            'amount'              => $amount,
            'source'              => (string) ($json['source'] ?? ''),
            'package_name'        => (string) ($json['package_name'] ?? ''),
            'notification_title'  => (string) ($json['notification_title'] ?? ''),
            'notification_text'   => $notificationText,
            'sender_name'         => $senderName,
            'notified_at'         => $notifiedAt,
            'received_at'         => $receivedAt,
            'raw_payload'         => json_encode($rawPayload, JSON_UNESCAPED_UNICODE),
            'created_at'          => $receivedAt,
            'updated_at'          => $receivedAt,
        ]);

        // Push ke Firebase untuk realtime broadcast
        $firebaseKey = $this->pushToFirebase([
            'id'                  => $id,
            'payhook_reference'   => $reference,
            'amount'              => $amount,
            'source'              => (string) ($json['source'] ?? ''),
            'sender_name'         => $senderName,
            'notification_title'  => (string) ($json['notification_title'] ?? ''),
            'notification_text'   => $notificationText,
            'notified_at_ms'      => $notifiedAt ? strtotime($notifiedAt) * 1000 : null,
            'received_at_ms'      => (int) (round(microtime(true) * 1000)),
        ]);

        if ($firebaseKey) {
            DB::table('dana_payment_notifications')
                ->where('id', $id)
                ->update(['firebase_key' => $firebaseKey, 'updated_at' => now()]);
        }

        return [
            'stored' => true,
            'duplicate' => false,
            'id' => $id,
            'firebase_key' => $firebaseKey,
        ];
    }

    /**
     * Parse nama pengirim dari notification_text.
     * Pola umum:
     *   "Rp1.000 dari PT BANK JAGO TBK berhasil diterima DANA Bisnis."
     *   "Rp50.000 dari Endra Dwi Putra berhasil diterima..."
     */
    private function parseSenderName(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        // Pattern: "...dari <NAMA> berhasil..."
        if (preg_match('/dari\s+(.+?)\s+berhasil/iu', $text, $m)) {
            $name = trim($m[1]);
            return $name !== '' ? $name : null;
        }

        // Fallback pattern: "...dari <NAMA>." (akhir kalimat)
        if (preg_match('/dari\s+(.+?)[\.\!\?]/iu', $text, $m)) {
            $name = trim($m[1]);
            return $name !== '' ? $name : null;
        }

        return null;
    }

    /**
     * Parse timestamp dari payload PayHook ("YYYY-MM-DD HH:MM:SS") ke format DB.
     */
    private function parseTimestamp(string $ts): ?string
    {
        if ($ts === '') {
            return null;
        }
        try {
            return date('Y-m-d H:i:s', strtotime($ts));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Push ke Firebase RTDB /dana_payments.
     * Return key Firebase atau null kalau gagal (jangan throw — webhook tidak boleh fail).
     */
    private function pushToFirebase(array $data): ?string
    {
        try {
            $ref = $this->database
                ->getReference('dana_payments')
                ->push($data);

            return $ref->getKey();
        } catch (\Throwable $e) {
            Log::warning('DanaPaymentService: gagal push ke Firebase', [
                'error' => $e->getMessage(),
                'reference' => $data['payhook_reference'] ?? null,
            ]);
            return null;
        }
    }
}
