<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Support\Facades\Log;

/**
 * AiProductChatService
 *
 * Service utama untuk chat AI rekomendasi produk.
 * Dipakai oleh dua portal: admin & waiter (sama service, beda controller).
 *
 * Alur per pesan:
 *   1. Embed pertanyaan user via Gemini.
 *   2. Vector search via Supabase RPC (top-K, default K=10).
 *   3. Re-rank kandidat berdasarkan: similarity, knowledge.status, target_hewan match,
 *      gejala_terkait match, category match, confidence_score.
 *   4. Ambil top-N (config chat_max_products, default 5).
 *   5. Build chat prompt (system + context produk + history) dan panggil Gemini chat.
 *   6. Simpan history (user msg + assistant msg) ke MySQL.
 *   7. Return jawaban + daftar produk yang direkomendasikan.
 */
class AiProductChatService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected GeminiService $gemini,
        protected SupabaseVectorService $vectors,
    ) {
    }

    /**
     * Public entry: kirim pesan user, dapat balasan.
     *
     * @return array{
     *   session_id: int,
     *   user_message_id: int,
     *   assistant_message_id: int,
     *   answer: string,
     *   recommended_products: array<int, array{id: string, name: string, category_id: ?string, score: float, similarity: float, status: ?string}>,
     *   error?: string
     * }
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

        // Resolve session
        $session = $sessionId ? AiChatSession::find($sessionId) : null;
        if (! $session) {
            $session = AiChatSession::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'title' => mb_substr($question, 0, 100),
                'last_product_ids' => [],
                'primary_product_id' => null,
            ]);
        }

        // 1. Save user message
        $userMsg = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'message' => $question,
            'metadata' => null,
        ]);

        // 2. Embed question
        $queryEmbedding = $this->gemini->embed($question);
        if (! $queryEmbedding) {
            $assistantMsg = $this->saveAssistant($session->id, 'Maaf, sistem AI sedang tidak tersedia (gagal membuat embedding). Coba lagi sebentar.', []);

            return [
                'session_id' => $session->id,
                'user_message_id' => $userMsg->id,
                'assistant_message_id' => $assistantMsg->id,
                'answer' => $assistantMsg->message,
                'recommended_products' => [],
                'error' => 'embed_failed',
            ];
        }

        // 3. Vector search (top-K)
        $topK = (int) config('ai_product_assistant.vector_top_k', 10);
        $matches = $this->vectors->match($queryEmbedding, $topK);

        if (count($matches) === 0) {
            $assistantMsg = $this->saveAssistant(
                $session->id,
                'Maaf, saya belum punya data produk yang cocok untuk pertanyaan tersebut. Mungkin pertanyaan bisa lebih spesifik (jenis hewan, gejala, atau nama produk)?',
                []
            );

            return [
                'session_id' => $session->id,
                'user_message_id' => $userMsg->id,
                'assistant_message_id' => $assistantMsg->id,
                'answer' => $assistantMsg->message,
                'recommended_products' => [],
            ];
        }

        // 4. Rerank
        $reranked = $this->rerank($matches, $question);

        // 4b. Filter: hanya tampilkan produk dengan score >= threshold
        $minScore = (float) config('ai_product_assistant.chat_min_score', 160);
        $reranked = array_values(array_filter($reranked, fn ($p) => $p['score'] >= $minScore));

        if (count($reranked) === 0) {
            $assistantMsg = $this->saveAssistant(
                $session->id,
                'Maaf, saya tidak menemukan produk yang cukup relevan untuk pertanyaan tersebut. Coba lebih spesifik, misalnya sebutkan jenis hewan, gejala, atau nama produk yang dicari.',
                []
            );

            return [
                'session_id' => $session->id,
                'user_message_id' => $userMsg->id,
                'assistant_message_id' => $assistantMsg->id,
                'answer' => $assistantMsg->message,
                'recommended_products' => [],
            ];
        }

        // 5. Take top-N
        $topN = (int) config('ai_product_assistant.chat_max_products', 5);
        $top = array_slice($reranked, 0, $topN);

        // 6. Build prompt + call Gemini
        $prompt = $this->buildChatPrompt($question, $top, $session);
        $answer = $this->gemini->chat($prompt, 0.4);
        if (! $answer) {
            $answer = 'Maaf, sistem AI sedang sibuk. Silakan coba lagi.';
        }

        // 7. Save assistant message + update session memory
        $recommended = array_map(fn ($p) => [
            'id' => $p['product_id'],
            'name' => $p['product']['name'] ?? '',
            'category_id' => $p['product']['category_id'] ?? null,
            'score' => round($p['score'], 2),
            'similarity' => round($p['similarity'], 4),
            'status' => $p['knowledge']['status'] ?? null,
        ], $top);

        $assistantMsg = $this->saveAssistant($session->id, $answer, [
            'recommended' => $recommended,
            'question' => $question,
        ]);

        // Update session last products
        $session->update([
            'last_product_ids' => array_column($recommended, 'id'),
            'primary_product_id' => $recommended[0]['id'] ?? null,
        ]);

        return [
            'session_id' => $session->id,
            'user_message_id' => $userMsg->id,
            'assistant_message_id' => $assistantMsg->id,
            'answer' => $answer,
            'recommended_products' => $recommended,
        ];
    }

    /**
     * Re-rank kandidat dari vector search.
     *
     * @param  array<int, array{product_id: string, content: string, similarity: float}>  $matches
     * @return array<int, array{product_id: string, similarity: float, content: string, product: array, knowledge: ?array, score: float}>
     */
    public function rerank(array $matches, string $question): array
    {
        $qLower = mb_strtolower($question);
        $qTokens = $this->tokenize($qLower);

        $allCategories = [];
        try {
            $allCategories = $this->firebase->getProductCategoriesMap();
        } catch (\Throwable $e) {
            // ignore
        }

        $augmented = [];
        foreach ($matches as $m) {
            $productId = (string) $m['product_id'];
            if ($productId === '') {
                continue;
            }
            $product = $this->firebase->getProductById($productId);
            if (! $product) {
                continue; // produk dihapus, skip.
            }
            if (($product['is_active'] ?? true) === false) {
                continue;
            }
            $knowledge = $this->knowledge->get($productId);

            $similarity = (float) $m['similarity'];
            $score = $similarity * 100.0;

            // +40 jika knowledge approved
            if (is_array($knowledge) && ($knowledge['status'] ?? '') === 'approved') {
                $score += 40;
            } elseif (! $knowledge) {
                // -50 jika tidak ada knowledge sama sekali
                $score -= 50;
            }

            // +30 target_hewan match
            if (is_array($knowledge['target_hewan'] ?? null)) {
                foreach ($knowledge['target_hewan'] as $hewan) {
                    if ($this->fuzzyContains($qLower, mb_strtolower((string) $hewan))) {
                        $score += 30;
                        break;
                    }
                }
            }

            // +25 gejala_terkait match
            if (is_array($knowledge['gejala_terkait'] ?? null)) {
                foreach ($knowledge['gejala_terkait'] as $gejala) {
                    if ($this->fuzzyContains($qLower, mb_strtolower((string) $gejala))) {
                        $score += 25;
                        break;
                    }
                }
            }

            // +20 kategori match
            $categoryName = '';
            $catId = $product['category_id'] ?? '';
            if ($catId && isset($allCategories[$catId])) {
                $entry = $allCategories[$catId];
                $categoryName = is_array($entry) ? (string) ($entry['name'] ?? '') : (string) $entry;
            }
            if ($categoryName && $this->fuzzyContains($qLower, mb_strtolower($categoryName))) {
                $score += 20;
            }

            // +10 confidence high
            $confidence = (float) ($knowledge['confidence_score'] ?? 0);
            if ($confidence >= 0.8) {
                $score += 10;
            }

            // -50 manfaat kosong
            if (empty($knowledge['manfaat'] ?? '')) {
                $score -= 50;
            }

            $augmented[] = [
                'product_id' => $productId,
                'similarity' => $similarity,
                'content' => (string) ($m['content'] ?? ''),
                'product' => $product,
                'knowledge' => $knowledge,
                'score' => $score,
            ];
        }

        usort($augmented, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $augmented;
    }

    /**
     * Build chat prompt: system rules + product context + history + question.
     *
     * @param  array<int, array{product_id: string, product: array, knowledge: ?array, score: float, similarity: float}>  $candidates
     */
    public function buildChatPrompt(string $question, array $candidates, AiChatSession $session): string
    {
        $system = <<<SYS
Anda adalah asisten produk untuk toko serbaguna di Indonesia (peternakan, perikanan, petshop, perlengkapan pancing).
TUGAS: Bantu pengguna memilih produk yang tepat dari daftar yang diberikan.

ATURAN KETAT (WAJIB DITAATI):
1. Jawab HANYA dari daftar produk yang diberikan di bawah. JANGAN menyebut produk di luar daftar.
2. Sesuaikan gaya jawaban dengan TIPE PRODUK:
   - medical (obat/vitamin): JANGAN diagnosis pasti, JANGAN klaim "menyembuhkan" kecuali ditulis data, JANGAN dosis di luar "Aturan pakai".
   - food (pakan/umpan): jelaskan fase/target hewan dan kandungan utama jika ada.
   - equipment (alat pancing/aksesoris/kandang/pasir): fokus pada spesifikasi teknis, ukuran, kompatibilitas, target ikan.
   - livestock (hewan hidup): jelaskan ras/jenis dan tujuan budidaya.
   - pest_control (racun/perangkap): WAJIB sertakan peringatan keamanan.
3. Jika user tanya HARGA: jawab "Informasi harga tidak tersedia di data, silakan cek di kasir/POS".
4. Jika tidak ada produk yang cocok di daftar, katakan dengan jujur.
5. Bahasa: Indonesia, sopan, ringkas, mudah dipahami pelanggan toko.

FORMAT JAWABAN:
- Mulai dengan rekomendasi singkat (1-2 kalimat).
- Untuk setiap produk yang relevan, beri:
  • Nama produk + kategori (singkat).
  • Alasan cocok untuk pertanyaan user.
  • Cara menawarkan/penjelasan singkat (≤2 kalimat).
  • Spesifikasi/aturan pakai/peringatan jika ada.
- Tutup dengan 1-2 pertanyaan lanjutan jika sesuai (mis. "Untuk hewan apa?", "Berapa ukuran yang dibutuhkan?", "Apakah untuk pancingan air laut?").
SYS;

        $contextLines = [];
        if (count($candidates) === 0) {
            $contextLines[] = '(tidak ada produk relevan)';
        } else {
            foreach ($candidates as $i => $c) {
                $idx = $i + 1;
                $contextLines[] = $this->formatProductForPrompt($idx, $c);
            }
        }
        $context = "DAFTAR PRODUK YANG TERSEDIA:\n".implode("\n\n", $contextLines);

        // History 4 pesan terakhir (selain user msg yg baru)
        $history = AiChatMessage::where('session_id', $session->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->values();
        $historyLines = [];
        foreach ($history as $msg) {
            $role = $msg->role === 'user' ? 'User' : 'Assistant';
            $historyLines[] = "{$role}: ".trim($msg->message);
        }
        $historyBlock = count($historyLines) > 0 ? ("RIWAYAT PERCAKAPAN:\n".implode("\n", $historyLines)) : '';

        return implode("\n\n", array_filter([
            $system,
            $context,
            $historyBlock,
            "PERTANYAAN USER:\n{$question}\n\nJAWABAN:",
        ]));
    }

    protected function formatProductForPrompt(int $idx, array $c): string
    {
        $product = $c['product'];
        $knowledge = $c['knowledge'];
        $name = (string) ($product['name'] ?? '-');

        $catName = '-';
        $catId = $product['category_id'] ?? '';
        if ($catId) {
            $map = $this->firebase->getProductCategoriesMap();
            $entry = $map[$catId] ?? null;
            $catName = is_array($entry) ? (string) ($entry['name'] ?? '-') : (string) ($entry ?? '-');
        }

        $lines = ["[{$idx}] {$name} (kategori: {$catName})"];
        if ($knowledge) {
            if (! empty($knowledge['tipe_produk'])) {
                $lines[] = 'Tipe: '.$knowledge['tipe_produk'];
            }
            if (! empty($knowledge['brand'])) {
                $lines[] = "Brand: {$knowledge['brand']}";
            }
            if (! empty($knowledge['manfaat'])) {
                $lines[] = "Manfaat: {$knowledge['manfaat']}";
            }
            if (! empty($knowledge['fungsi']) && is_array($knowledge['fungsi'])) {
                $lines[] = 'Fungsi: '.implode(', ', $knowledge['fungsi']);
            }
            if (! empty($knowledge['target_hewan']) && is_array($knowledge['target_hewan'])) {
                $lines[] = 'Target hewan: '.implode(', ', $knowledge['target_hewan']);
            }
            if (! empty($knowledge['gejala_terkait']) && is_array($knowledge['gejala_terkait'])) {
                $lines[] = 'Gejala terkait: '.implode(', ', $knowledge['gejala_terkait']);
            }
            if (! empty($knowledge['aturan_pakai'])) {
                $lines[] = "Aturan pakai: {$knowledge['aturan_pakai']}";
            }
            if (! empty($knowledge['peringatan'])) {
                $lines[] = "Peringatan: {$knowledge['peringatan']}";
            }
            if (! empty($knowledge['ukuran_varian']) && is_array($knowledge['ukuran_varian'])) {
                $lines[] = 'Ukuran/varian: '.implode(', ', $knowledge['ukuran_varian']);
            }
            if (! empty($knowledge['spesifikasi']) && is_array($knowledge['spesifikasi'])) {
                $specPairs = [];
                foreach ($knowledge['spesifikasi'] as $k => $v) {
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $specPairs[] = str_replace('_', ' ', $k).': '.(is_array($v) ? implode(', ', $v) : $v);
                }
                if (count($specPairs) > 0) {
                    $lines[] = 'Spesifikasi: '.implode('; ', $specPairs);
                }
            }
        } else {
            $lines[] = '(detail knowledge belum tersedia, hanya nama produk)';
        }

        return implode("\n", $lines);
    }

    protected function saveAssistant(int $sessionId, string $message, array $metadata): AiChatMessage
    {
        return AiChatMessage::create([
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    protected function errorResult(string $msg): array
    {
        return [
            'session_id' => 0,
            'user_message_id' => 0,
            'assistant_message_id' => 0,
            'answer' => $msg,
            'recommended_products' => [],
            'error' => $msg,
        ];
    }

    protected function tokenize(string $s): array
    {
        $tokens = preg_split('/\s+/', $s);
        $tokens = array_filter($tokens, fn ($t) => mb_strlen($t) >= 3);

        return array_values($tokens);
    }

    /**
     * Loose token match: cari salah satu kata di needle muncul di haystack.
     */
    protected function fuzzyContains(string $haystack, string $needle): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }
        if (str_contains($haystack, $needle)) {
            return true;
        }
        $tokens = preg_split('/\s+/', $needle);
        foreach ($tokens as $t) {
            $t = trim($t);
            if (mb_strlen($t) >= 3 && str_contains($haystack, $t)) {
                return true;
            }
        }

        return false;
    }
}
