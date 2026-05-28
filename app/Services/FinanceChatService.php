<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FinanceChatService
 *
 * AI assistant untuk diskusi keuangan toko.
 * Mengambil data real-time dari cash_mutations, cash_accounts, finance_daily_data
 * lalu kirim ke Gemini sebagai context untuk menjawab pertanyaan user.
 */
class FinanceChatService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Entry point: user kirim pertanyaan finance.
     *
     * @return array{session_id: int, user_message_id: int, assistant_message_id: int, answer: string, error?: string}
     */
    public function ask(
        string $question,
        ?int $sessionId,
        ?int $userId,
        string $userType,
    ): array {
        $question = trim($question);
        if ($question === '') {
            return $this->errorResult('Pertanyaan kosong.');
        }

        if (! $this->gemini->isConfigured()) {
            return $this->errorResult('AI belum dikonfigurasi.');
        }

        // Resolve or create session
        $session = $sessionId ? AiChatSession::find($sessionId) : null;
        if ($session && $session->user_type !== 'finance_' . $userType) {
            $session = null;
        }
        if (! $session) {
            $session = AiChatSession::create([
                'user_id' => $userId,
                'user_type' => 'finance_' . $userType,
                'title' => mb_substr($question, 0, 100),
                'last_product_ids' => [],
                'primary_product_id' => null,
            ]);
        }

        // Save user message
        $userMsg = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'message' => $question,
            'metadata' => null,
        ]);

        // Build context from finance data
        $financeContext = $this->buildFinanceContext($question);

        // Build conversation history (last 10 messages)
        $history = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $userMsg->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => "{$m->role}: {$m->message}")
            ->implode("\n");

        // Build full prompt
        $prompt = $this->buildPrompt($question, $financeContext, $history);

        // Call Gemini
        $answer = $this->gemini->chat($prompt, 0.3);
        if (! $answer) {
            $answer = 'Maaf, sistem AI sedang tidak tersedia. Coba lagi sebentar.';
        }

        // Save assistant message
        $assistantMsg = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'message' => $answer,
            'metadata' => ['type' => 'finance'],
        ]);

        $session->update(['title' => mb_substr($question, 0, 100)]);

        return [
            'session_id' => $session->id,
            'user_message_id' => $userMsg->id,
            'assistant_message_id' => $assistantMsg->id,
            'answer' => $answer,
        ];
    }

    /**
     * Ambil data finance yang relevan berdasarkan pertanyaan.
     */
    protected function buildFinanceContext(string $question): string
    {
        $parts = [];

        // 1. Saldo semua akun
        $accounts = DB::table('cash_accounts')
            ->select('id', 'name', 'balance')
            ->where('is_active', true)
            ->get();
        $parts[] = "=== SALDO AKUN ===";
        foreach ($accounts as $acc) {
            $parts[] = "- {$acc->name} (#{$acc->id}): Rp " . number_format($acc->balance, 0, ',', '.');
        }

        // 2. Mutasi hari ini
        $today = now()->toDateString();
        $todayMutations = DB::table('cash_mutations')
            ->where('tanggal', $today)
            ->orderBy('id')
            ->get();

        $parts[] = "\n=== MUTASI HARI INI ({$today}) ===";
        if ($todayMutations->isEmpty()) {
            $parts[] = "(Belum ada mutasi hari ini)";
        } else {
            foreach ($todayMutations as $m) {
                $status = $m->settlement_status ?? 'settled';
                $statusLabel = $status === 'pending' ? ' [PENDING]' : '';
                $parts[] = "- #{$m->id} | {$m->type} | Rp " . number_format($m->nominal, 0, ',', '.') .
                    " | {$m->keterangan} | akun #{$m->akun_id}{$statusLabel}";
            }
        }

        // 3. Pending settlement
        $pendingQris = DB::table('cash_mutations')
            ->where('settlement_status', 'pending')
            ->sum('nominal');
        if ($pendingQris > 0) {
            $parts[] = "\n=== PENDING SETTLEMENT (QRIS belum cair) ===";
            $parts[] = "Total: Rp " . number_format($pendingQris, 0, ',', '.');
        }

        // 4. Ringkasan 7 hari terakhir
        $weekAgo = now()->subDays(7)->toDateString();
        $weekSummary = DB::table('cash_mutations')
            ->where('tanggal', '>=', $weekAgo)
            ->where('settlement_status', 'settled')
            ->selectRaw("
                tanggal,
                SUM(CASE WHEN type='income' THEN nominal ELSE 0 END) as total_income,
                SUM(CASE WHEN type='expense' THEN nominal ELSE 0 END) as total_expense
            ")
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        $parts[] = "\n=== RINGKASAN 7 HARI TERAKHIR ===";
        foreach ($weekSummary as $day) {
            $net = $day->total_income - $day->total_expense;
            $parts[] = "- {$day->tanggal}: Income Rp " . number_format($day->total_income, 0, ',', '.') .
                " | Expense Rp " . number_format($day->total_expense, 0, ',', '.') .
                " | Net Rp " . number_format($net, 0, ',', '.');
        }

        // 5. Top pengeluaran minggu ini
        $topExpenses = DB::table('cash_mutations')
            ->where('tanggal', '>=', $weekAgo)
            ->where('type', 'expense')
            ->where('settlement_status', 'settled')
            ->orderByDesc('nominal')
            ->limit(10)
            ->get();

        if ($topExpenses->isNotEmpty()) {
            $parts[] = "\n=== TOP PENGELUARAN MINGGU INI ===";
            foreach ($topExpenses as $e) {
                $parts[] = "- {$e->tanggal} | Rp " . number_format($e->nominal, 0, ',', '.') . " | {$e->keterangan}";
            }
        }

        // 6. Info loket
        $parts[] = "\n=== INFO SISTEM ===";
        $parts[] = "- Kas Laci 1 (akun #1): loket utama";
        $parts[] = "- Kas Laci 2 (akun #11): loket kedua";
        $parts[] = "- QRIS/Bank Jago (akun #4): pembayaran digital, cair jam 22:00";
        $parts[] = "- Brankas (akun #2): penyimpanan uang besar";
        $parts[] = "- Receh (akun #10): uang kecil/kembalian";
        $parts[] = "- SUPERBANK (akun #3): rekening bank";
        $parts[] = "- Settlement QRIS: otomatis jam 22:00, sebelumnya status 'pending'";
        $parts[] = "- Sistem cash basis: expense dicatat saat uang keluar";

        return implode("\n", $parts);
    }

    /**
     * Build prompt lengkap untuk Gemini.
     */
    protected function buildPrompt(string $question, string $context, string $history): string
    {
        $systemPrompt = <<<SYSTEM
Kamu adalah asisten keuangan AI untuk Mataram Petshop. Tugasmu:
- Menjawab pertanyaan tentang kondisi keuangan toko berdasarkan DATA REAL yang diberikan
- Menganalisis selisih, anomali, atau pola pengeluaran
- Memberikan ringkasan yang mudah dipahami
- Mendeteksi potensi masalah (pengeluaran tidak wajar, selisih kas, dll)

ATURAN:
- Jawab HANYA berdasarkan data yang diberikan, jangan mengarang angka
- Gunakan Bahasa Indonesia yang natural dan ringkas
- Format angka dengan Rp dan titik pemisah ribuan
- Jika data tidak cukup untuk menjawab, katakan dengan jujur
- Jangan berikan saran investasi atau keuangan pribadi
- Fokus pada operasional keuangan toko

KONTEKS SISTEM:
- Toko pet shop dengan 2 loket kasir
- Pembayaran: tunai (langsung masuk kas) dan QRIS (pending sampai jam 22:00)
- Setiap shift tutup, data otomatis sync dari aplikasi kasir
- Cash basis accounting: expense = saat uang keluar
SYSTEM;

        $prompt = $systemPrompt . "\n\n";
        $prompt .= "=== DATA KEUANGAN REAL-TIME ===\n";
        $prompt .= $context . "\n\n";

        if ($history !== '') {
            $prompt .= "=== RIWAYAT PERCAKAPAN ===\n";
            $prompt .= $history . "\n\n";
        }

        $prompt .= "=== PERTANYAAN USER ===\n";
        $prompt .= $question;

        return $prompt;
    }

    protected function errorResult(string $message): array
    {
        return [
            'session_id' => 0,
            'user_message_id' => 0,
            'assistant_message_id' => 0,
            'answer' => $message,
            'error' => $message,
        ];
    }
}
