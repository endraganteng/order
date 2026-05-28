<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
    }

    public function webhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();

        // Handle callback query (button clicks)
        if (isset($data['callback_query'])) {
            $this->handleCallbackQuery($data['callback_query']);
            return response()->json(['ok' => true]);
        }

        $message = $data['message'] ?? null;
        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $text = trim($message['text'] ?? '');
        $chatId = $message['chat']['id'] ?? null;
        $threadId = $message['message_thread_id'] ?? null;

        if (!$text || !$chatId) {
            return response()->json(['ok' => true]);
        }

        // Respond to /menu or /start with inline buttons
        if (str_starts_with($text, '/')) {
            $parts = explode(' ', $text, 2);
            $command = strtolower(str_replace('@mataramfinance_bot', '', $parts[0]));
            $args = $parts[1] ?? '';

            match ($command) {
                '/menu', '/start' => $this->sendMenu($chatId, $threadId),
                '/kas' => $this->sendResponse($chatId, $threadId, $this->commandKas()),
                '/omzet' => $this->sendResponse($chatId, $threadId, $this->commandOmzet()),
                '/hutang' => $this->sendResponse($chatId, $threadId, $this->commandHutang()),
                '/help' => $this->sendMenu($chatId, $threadId),
                default => null,
            };
        }

        return response()->json(['ok' => true]);
    }

    protected function handleCallbackQuery(array $callback): void
    {
        $callbackId = $callback['id'] ?? '';
        $data = $callback['data'] ?? '';
        $message = $callback['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $threadId = $message['message_thread_id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if (!$chatId || !$data) {
            $this->answerCallback($callbackId);
            return;
        }

        try {
            $response = match ($data) {
                'kas' => $this->commandKas(),
                'omzet' => $this->commandOmzet(),
                'hutang' => $this->commandHutang(),
                'menu' => null,
                default => null,
            };

            if ($data === 'menu') {
                $this->answerCallback($callbackId);
                $this->sendMenu($chatId, $threadId);
                return;
            }

            if ($response) {
                $this->answerCallback($callbackId);
                $this->sendResponse($chatId, $threadId, $response, withBackButton: true);
            } else {
                $this->answerCallback($callbackId, '⚠️ Command tidak dikenali');
            }
        } catch (\Throwable $e) {
            Log::error('TelegramBot callback error', ['data' => $data, 'error' => $e->getMessage()]);
            $this->answerCallback($callbackId, '❌ Error');
            $this->sendResponse($chatId, $threadId, "❌ Terjadi error: " . $e->getMessage());
        }
    }

    protected function sendMenu(int|string $chatId, ?int $threadId): void
    {
        $text = "🤖 *MATARAM FINANCE BOT*\n\nPilih menu:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💰 Saldo Kas', 'callback_data' => 'kas'],
                    ['text' => '📊 Omzet Hari Ini', 'callback_data' => 'omzet'],
                ],
                [
                    ['text' => '💳 Hutang Supplier', 'callback_data' => 'hutang'],
                ],
            ],
        ];

        $this->sendTelegramMessage($chatId, $text, $threadId, $keyboard);
    }

    protected function sendResponse(int|string $chatId, ?int $threadId, string $text, bool $withBackButton = false): void
    {
        $keyboard = null;
        if ($withBackButton) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '◀️ Kembali ke Menu', 'callback_data' => 'menu'],
                    ],
                ],
            ];
        }

        $this->sendTelegramMessage($chatId, $text, $threadId, $keyboard);
    }

    protected function sendTelegramMessage(int|string $chatId, string $text, ?int $threadId = null, ?array $replyMarkup = null): void
    {
        $firebase = app(\App\Services\FirebaseService::class);
        $settings = $firebase->getSettings();
        $token = $settings['telegram_bot_token'] ?? '';

        if (!$token) {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($threadId) {
            $payload['message_thread_id'] = $threadId;
        }

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
    }

    protected function answerCallback(string $callbackId, ?string $text = null): void
    {
        $firebase = app(\App\Services\FirebaseService::class);
        $settings = $firebase->getSettings();
        $token = $settings['telegram_bot_token'] ?? '';

        if (!$token) {
            return;
        }

        $payload = ['callback_query_id' => $callbackId];
        if ($text) {
            $payload['text'] = $text;
            $payload['show_alert'] = false;
        }

        Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", $payload);
    }

    protected function commandKas(): string
    {
        $accounts = DB::table('cash_accounts')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($accounts->isEmpty()) {
            return "💰 Belum ada akun kas aktif.";
        }

        $lines = ["💰 *SALDO KAS*", "━━━━━━━━━━━━━━━━━━━━━"];

        foreach ($accounts as $a) {
            $icon = match (true) {
                str_contains(strtolower($a->name), 'bank') || str_contains(strtolower($a->name), 'rekening') => '🏦',
                str_contains(strtolower($a->name), 'brankas') => '🔒',
                str_contains(strtolower($a->name), 'qris') || str_contains(strtolower($a->name), 'jago') => '📱',
                default => '💵',
            };
            $balance = number_format($a->balance, 0, ',', '.');
            $lines[] = "{$icon} {$a->name}: Rp {$balance}";
        }

        $total = number_format($accounts->sum('balance'), 0, ',', '.');
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "📊 *Total: Rp {$total}*";
        $lines[] = "";
        $lines[] = "🕐 Update: " . now()->format('d/m/Y H:i');

        return implode("\n", $lines);
    }

    protected function commandOmzet(): string
    {
        $firebase = app(\App\Services\FirebaseService::class);
        $settings = $firebase->getSettings();

        // Olsera API credentials
        $storeSlug = 'matarampetshop';
        $apiBase = "https://permissions-api-dash.olsera.co.id/api/{$storeSlug}/admin/v1/id";

        // Login to get token (oauth endpoint is at root, not under slug)
        $loginResponse = Http::withHeaders(['Accept' => 'application/json'])
            ->post("https://permissions-api-dash.olsera.co.id/oauth/token", [
                'grant_type' => 'password',
                'username' => $settings['olsera_email'] ?? 'Matarampetshop@gmail.com',
                'password' => $settings['olsera_password'] ?? '',
                'client_id' => 2,
            ]);

        if (!$loginResponse->successful()) {
            return "❌ Gagal login ke Olsera.";
        }

        $token = $loginResponse->json('access_token');
        if (!$token) {
            return "❌ Token Olsera tidak ditemukan.";
        }

        // Fetch today's closed orders
        $today = date('Y-m-d');
        $salesResponse = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->get("{$apiBase}/closeorder", [
            'start_date' => $today,
            'end_date' => $today,
            'per_page' => 100,
            'page' => 1,
            'sort_column' => 'order_date',
            'sort_type' => 'desc',
        ]);

        if (!$salesResponse->successful()) {
            // 404 means no data for today (empty sales)
            if ($salesResponse->status() === 404) {
                return "📊 *OMZET HARI INI*\n📅 " . date('d/m/Y') . "\n━━━━━━━━━━━━━━━━━━━━━\n\n🧾 Transaksi: 0\n💰 *Total: Rp 0*\n\n🕐 Update: " . now()->format('d/m/Y H:i');
            }
            return "❌ Gagal ambil data penjualan Olsera.";
        }

        // Fetch all pages
        $allOrders = [];
        $page = 1;
        do {
            $resp = ($page === 1) ? $salesResponse : Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->get("{$apiBase}/closeorder", [
                'start_date' => $today,
                'end_date' => $today,
                'per_page' => 100,
                'page' => $page,
                'sort_column' => 'order_date',
                'sort_type' => 'desc',
            ]);

            $json = $resp->json();
            $pageData = $json['data'] ?? [];
            $allOrders = array_merge($allOrders, $pageData);

            $lastPage = $json['meta']['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage);

        $totalOrders = count($allOrders);
        $totalOmzet = 0;
        $paymentBreakdown = [];

        foreach ($allOrders as $order) {
            $amount = (float) ($order['total_amount'] ?? 0);
            $totalOmzet += $amount;

            $paymentType = match ($order['payment_type_name'] ?? '') {
                'Kartu Debit' => 'QRIS',
                '' => 'Lainnya',
                default => $order['payment_type_name'],
            };
            if (!isset($paymentBreakdown[$paymentType])) {
                $paymentBreakdown[$paymentType] = 0;
            }
            $paymentBreakdown[$paymentType] += $amount;
        }

        $lines = [
            "📊 *OMZET HARI INI*",
            "📅 " . date('d/m/Y'),
            "━━━━━━━━━━━━━━━━━━━━━",
            "",
            "🧾 Transaksi: {$totalOrders}",
            "💰 *Total: Rp " . number_format($totalOmzet, 0, ',', '.') . "*",
        ];

        if (!empty($paymentBreakdown)) {
            $lines[] = "";
            $lines[] = "*Per metode bayar:*";
            arsort($paymentBreakdown);
            foreach ($paymentBreakdown as $type => $amount) {
                $lines[] = "• {$type}: Rp " . number_format($amount, 0, ',', '.');
            }
        }

        $lines[] = "";
        $lines[] = "🕐 Update: " . now()->format('d/m/Y H:i');

        return implode("\n", $lines);
    }

    protected function commandHutang(): string
    {
        $debts = DB::table('finance_debts')
            ->whereIn('status', ['unpaid', 'partial'])
            ->orderBy('due_date')
            ->get();

        if ($debts->isEmpty()) {
            return "✅ Tidak ada hutang supplier outstanding.";
        }

        $lines = ["💳 *HUTANG SUPPLIER*", "━━━━━━━━━━━━━━━━━━━━━"];

        $totalOutstanding = 0;
        foreach ($debts as $d) {
            $outstanding = (float) $d->amount - (float) $d->paid;
            $totalOutstanding += $outstanding;

            $dueDate = date('d/m/Y', strtotime($d->due_date));
            $isOverdue = strtotime($d->due_date) < time();
            $icon = $isOverdue ? '🚨' : '📄';

            $lines[] = "";
            $lines[] = "{$icon} *{$d->supplier_name}*";
            $lines[] = "   Sisa: Rp " . number_format($outstanding, 0, ',', '.');
            $lines[] = "   Jatuh tempo: {$dueDate}" . ($isOverdue ? ' ⚠️ LEWAT' : '');
            if ($d->description) {
                $lines[] = "   Ket: {$d->description}";
            }
        }

        $lines[] = "";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "📊 *Total hutang: Rp " . number_format($totalOutstanding, 0, ',', '.') . "*";
        $lines[] = "";
        $lines[] = "🕐 Update: " . now()->format('d/m/Y H:i');

        return implode("\n", $lines);
    }
}
