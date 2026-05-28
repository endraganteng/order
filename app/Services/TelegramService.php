<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $apiUrl = 'https://api.telegram.org/bot';
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Kirim pesan ke Telegram topic tertentu
     *
     * @param string $text        Isi pesan (support Markdown)
     * @param int|null $threadId  message_thread_id topic (null = General)
     * @return array              ['success' => bool, 'message' => string]
     */
    public function sendToTopic(string $text, ?int $threadId = null): array
    {
        $settings = $this->firebase->getSettings();
        $token = $settings['telegram_bot_token'] ?? '';
        $chatId = $settings['telegram_chat_id'] ?? '';

        if (empty($token) || empty($chatId)) {
            Log::warning('TelegramService: Token atau Chat ID belum dikonfigurasi.');

            return [
                'success' => false,
                'message' => 'Telegram belum dikonfigurasi.',
            ];
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ];

            if ($threadId !== null) {
                $payload['message_thread_id'] = $threadId;
            }

            $response = Http::timeout(15)
                ->post($this->apiUrl . $token . '/sendMessage', $payload);

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                Log::info('TelegramService: Pesan berhasil dikirim', [
                    'chat_id' => $chatId,
                    'thread_id' => $threadId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Pesan berhasil dikirim ke Telegram.',
                ];
            }

            Log::warning('TelegramService: Gagal mengirim pesan', [
                'chat_id' => $chatId,
                'thread_id' => $threadId,
                'response' => $body,
            ]);

            return [
                'success' => false,
                'message' => $body['description'] ?? 'Gagal mengirim pesan ke Telegram.',
            ];
        } catch (\Exception $e) {
            Log::error('TelegramService: Exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim ke topic Finance
     */
    public function sendToFinance(string $text): array
    {
        $settings = $this->firebase->getSettings();
        $threadId = (int) ($settings['telegram_finance_thread_id'] ?? 0);

        return $this->sendToTopic($text, $threadId ?: null);
    }

    /**
     * Kirim ke topic HRD
     */
    public function sendToHrd(string $text): array
    {
        $settings = $this->firebase->getSettings();
        $threadId = (int) ($settings['telegram_hrd_thread_id'] ?? 0);

        return $this->sendToTopic($text, $threadId ?: null);
    }

    /**
     * Kirim ke topic Gudang
     */
    public function sendToGudang(string $text): array
    {
        $settings = $this->firebase->getSettings();
        $threadId = (int) ($settings['telegram_gudang_thread_id'] ?? 0);

        return $this->sendToTopic($text, $threadId ?: null);
    }

    /**
     * Kirim ke topic LAPORAN SHIFT
     */
    public function sendToLaporanShift(string $text): array
    {
        $settings = $this->firebase->getSettings();
        $threadId = (int) ($settings['telegram_laporan_shift_thread_id'] ?? 99);

        return $this->sendToTopic($text, $threadId ?: null);
    }

    /**
     * Cek apakah Telegram sudah dikonfigurasi
     */
    public function isConfigured(): bool
    {
        $settings = $this->firebase->getSettings();

        return ! empty($settings['telegram_bot_token']) && ! empty($settings['telegram_chat_id']);
    }
}
