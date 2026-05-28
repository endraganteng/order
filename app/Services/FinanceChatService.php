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
            ->where('transaction_date', $today)
            ->orderBy('id')
            ->get();

        $parts[] = "\n=== MUTASI HARI INI ({$today}) ===";
        if ($todayMutations->isEmpty()) {
            $parts[] = "(Belum ada mutasi hari ini)";
        } else {
            foreach ($todayMutations as $m) {
                $status = $m->settlement_status ?? 'settled';
                $statusLabel = $status === 'pending' ? ' [PENDING]' : '';
                $parts[] = "- #{$m->id} | {$m->type} | Rp " . number_format($m->amount, 0, ',', '.') .
                    " | {$m->description} | akun #{$m->cash_account_id}{$statusLabel}";
            }
        }

        // 3. Pending settlement
        $pendingQris = DB::table('cash_mutations')
            ->where('settlement_status', 'pending')
            ->sum('amount');
        if ($pendingQris > 0) {
            $parts[] = "\n=== PENDING SETTLEMENT (QRIS belum cair) ===";
            $parts[] = "Total: Rp " . number_format($pendingQris, 0, ',', '.');
        }

        // 4. Ringkasan 7 hari terakhir
        $weekAgo = now()->subDays(7)->toDateString();
        $weekSummary = DB::table('cash_mutations')
            ->where('transaction_date', '>=', $weekAgo)
            ->where('settlement_status', 'settled')
            ->selectRaw("
                transaction_date,
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as total_expense
            ")
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get();

        $parts[] = "\n=== RINGKASAN 7 HARI TERAKHIR ===";
        foreach ($weekSummary as $day) {
            $net = $day->total_income - $day->total_expense;
            $parts[] = "- {$day->transaction_date}: Income Rp " . number_format($day->total_income, 0, ',', '.') .
                " | Expense Rp " . number_format($day->total_expense, 0, ',', '.') .
                " | Net Rp " . number_format($net, 0, ',', '.');
        }

        // 5. Top pengeluaran minggu ini
        $topExpenses = DB::table('cash_mutations')
            ->where('transaction_date', '>=', $weekAgo)
            ->where('type', 'expense')
            ->where('settlement_status', 'settled')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        if ($topExpenses->isNotEmpty()) {
            $parts[] = "\n=== TOP PENGELUARAN MINGGU INI ===";
            foreach ($topExpenses as $e) {
                $parts[] = "- {$e->transaction_date} | Rp " . number_format($e->amount, 0, ',', '.') . " | {$e->description}";
            }
        }

        // 6. Breakdown per loket (kemarin + hari ini)
        $yesterday = now()->subDay()->toDateString();
        $perLoket = DB::table('cash_mutations')
            ->whereIn('transaction_date', [$yesterday, $today])
            ->where('settlement_status', 'settled')
            ->selectRaw("
                transaction_date,
                cash_account_id,
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense
            ")
            ->groupBy('transaction_date', 'cash_account_id')
            ->orderBy('transaction_date')
            ->get();

        if ($perLoket->isNotEmpty()) {
            $accountNames = $accounts->pluck('name', 'id')->toArray();
            $parts[] = "\n=== BREAKDOWN PER AKUN (KEMARIN + HARI INI) ===";
            foreach ($perLoket as $row) {
                $accName = $accountNames[$row->cash_account_id] ?? "Akun #{$row->cash_account_id}";
                $parts[] = "- {$row->transaction_date} | {$accName}: Income Rp " . number_format($row->income, 0, ',', '.') .
                    " | Expense Rp " . number_format($row->expense, 0, ',', '.');
            }
        }

        // 7. Mutasi kemarin (detail untuk trace saldo)
        $yesterdayMutations = DB::table('cash_mutations')
            ->where('transaction_date', $yesterday)
            ->orderBy('id')
            ->get();

        if ($yesterdayMutations->isNotEmpty()) {
            $parts[] = "\n=== MUTASI KEMARIN ({$yesterday}) ===";
            foreach ($yesterdayMutations as $m) {
                $status = $m->settlement_status ?? 'settled';
                $statusLabel = $status === 'pending' ? ' [PENDING]' : '';
                $parts[] = "- #{$m->id} | {$m->type} | Rp " . number_format($m->amount, 0, ',', '.') .
                    " | {$m->description} | akun #{$m->cash_account_id}{$statusLabel}";
            }
        }

        // 8. Hutang supplier aktif
        $debts = DB::table('finance_debts')
            ->where('status', 'active')
            ->orderBy('due_date')
            ->get();

        if ($debts->isNotEmpty()) {
            $parts[] = "\n=== HUTANG SUPPLIER AKTIF ===";
            foreach ($debts as $d) {
                $sisa = $d->amount - $d->paid;
                $dueLabel = $d->due_date ? " | Jatuh tempo: {$d->due_date}" : '';
                $parts[] = "- {$d->supplier_name}: Total Rp " . number_format($d->amount, 0, ',', '.') .
                    " | Dibayar Rp " . number_format($d->paid, 0, ',', '.') .
                    " | Sisa Rp " . number_format($sisa, 0, ',', '.') . $dueLabel;
            }
            $totalSisa = $debts->sum(fn ($d) => $d->amount - $d->paid);
            $parts[] = "TOTAL SISA HUTANG: Rp " . number_format($totalSisa, 0, ',', '.');
        }

        // 9. Alokasi dana (budget allocation)
        $allocations = DB::table('finance_allocations')
            ->where('is_active', true)
            ->get();

        if ($allocations->isNotEmpty()) {
            $categoryNames = DB::table('finance_categories')
                ->pluck('name', 'id')
                ->toArray();
            $parts[] = "\n=== ALOKASI DANA (% dari pendapatan bersih) ===";
            foreach ($allocations as $a) {
                $catName = $categoryNames[$a->finance_category_id] ?? '?';
                $parts[] = "- {$a->percentage}% → {$catName}";
            }
        }

        // 10. Kategori finance
        $categories = DB::table('finance_categories')
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        if ($categories->isNotEmpty()) {
            $parts[] = "\n=== KATEGORI FINANCE ===";
            foreach ($categories as $c) {
                $parts[] = "- [{$c->type}] {$c->name} (#{$c->id})";
            }
        }

        // 11. Data harian (finance_daily_data) — 7 hari terakhir
        $dailyData = DB::table('finance_daily_data')
            ->where('tanggal', '>=', $weekAgo)
            ->orderBy('tanggal')
            ->get();

        if ($dailyData->isNotEmpty()) {
            $parts[] = "\n=== DATA HARIAN (RINGKASAN SHIFT) ===";
            foreach ($dailyData as $dd) {
                $parts[] = "- {$dd->tanggal}: Tunai Rp " . number_format($dd->penjualan_tunai, 0, ',', '.') .
                    " | QRIS Rp " . number_format($dd->penjualan_qris, 0, ',', '.') .
                    " | Pengeluaran Shift Rp " . number_format($dd->total_pengeluaran, 0, ',', '.') .
                    " | Retur Rp " . number_format($dd->total_retur ?? 0, 0, ',', '.') .
                    " | Net Rp " . number_format($dd->pendapatan_bersih, 0, ',', '.') .
                    " | Jumlah Shift: {$dd->jumlah_shift}";
            }
        }

        // 12. Detail shift (3 hari terakhir) — per kasir, per loket, selisih
        $recentShifts = DB::table('finance_shifts')
            ->where('tanggal', '>=', now()->subDays(3)->toDateString())
            ->orderBy('tanggal')
            ->orderBy('shift_number')
            ->get();

        if ($recentShifts->isNotEmpty()) {
            $parts[] = "\n=== DETAIL SHIFT (3 HARI TERAKHIR) ===";
            foreach ($recentShifts as $s) {
                $selisihLabel = $s->selisih != 0 ? " | SELISIH: Rp " . number_format($s->selisih, 0, ',', '.') : '';
                $parts[] = "- {$s->tanggal} Shift {$s->shift_number} {$s->loket} | Kasir: {$s->kasir}" .
                    " | Tunai Rp " . number_format($s->penjualan_tunai, 0, ',', '.') .
                    " | QRIS Rp " . number_format($s->penjualan_qris, 0, ',', '.') .
                    " | Pengeluaran Rp " . number_format($s->total_pengeluaran, 0, ',', '.') .
                    $selisihLabel;
            }
        }

        // 13. Transfer antar akun (7 hari terakhir)
        $transfers = DB::table('cash_transfers')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        if ($transfers->isNotEmpty()) {
            $accountNames = $accounts->pluck('name', 'id')->toArray();
            $parts[] = "\n=== TRANSFER ANTAR AKUN (7 HARI TERAKHIR) ===";
            foreach ($transfers as $t) {
                $from = $accountNames[$t->from_account_id] ?? "#{$t->from_account_id}";
                $to = $accountNames[$t->to_account_id] ?? "#{$t->to_account_id}";
                $notes = $t->notes ? " ({$t->notes})" : '';
                $parts[] = "- #{$t->id} | {$from} → {$to} | Rp " . number_format($t->amount, 0, ',', '.') .
                    " | Status: {$t->status}{$notes}";
            }
        }

        // 14. Info loket
        $parts[] = "\n=== INFO SISTEM ===";
        $parts[] = "- Kas Laci 1 (akun #1): loket utama";
        $parts[] = "- Kas Laci 2 (akun #11): loket kedua";
        $parts[] = "- QRIS/Bank Jago (akun #4): pembayaran digital, cair jam 22:00";
        $parts[] = "- Brankas (akun #2): penyimpanan uang besar";
        $parts[] = "- Receh (akun #10): uang kecil/kembalian";
        $parts[] = "- SUPERBANK (akun #3): rekening bank";
        $parts[] = "- Settlement QRIS: otomatis jam 22:00, sebelumnya status 'pending'";
        $parts[] = "- Sistem cash basis: expense dicatat saat uang keluar";
        $parts[] = "- Alokasi dana dihitung dari pendapatan bersih harian";
        $parts[] = "- Selisih kas = perbedaan antara kas fisik vs yang tercatat di sistem";

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
