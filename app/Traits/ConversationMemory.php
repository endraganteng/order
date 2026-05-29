<?php

namespace App\Traits;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ConversationMemory trait
 *
 * Provides conversation compression for AI services.
 * When history exceeds threshold, older messages are summarized
 * and stored in session.summary. New requests get:
 * [summary of old context] + [recent N messages verbatim] + [new question]
 *
 * Usage: use this trait in any AI service that needs persistent memory.
 * Requires: $this->getApiKey(), $this->getBaseUrl(), $this->provider, $this->model
 */
trait ConversationMemory
{
    protected int $recentMessageLimit = 20;
    protected int $summarizeThreshold = 20;

    /**
     * Get or create a session for the given user.
     */
    protected function resolveSession(?int $sessionId, ?int $userId, string $userType): AiChatSession
    {
        $session = $sessionId ? AiChatSession::find($sessionId) : null;

        if ($session && $session->user_type !== $userType) {
            $session = null;
        }

        if (! $session) {
            $session = AiChatSession::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'title' => 'New conversation',
                'last_product_ids' => [],
                'primary_product_id' => null,
                'summary' => null,
                'summarized_up_to' => 0,
            ]);
        }

        return $session;
    }

    /**
     * Save a message to the session.
     */
    protected function saveMessage(int $sessionId, string $role, string $content, ?array $metadata = null): AiChatMessage
    {
        return AiChatMessage::create([
            'session_id' => $sessionId,
            'role' => $role,
            'message' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Build conversation history with compression.
     * Returns: [summary context] + [recent messages formatted]
     */
    protected function buildConversationHistory(AiChatSession $session, int $beforeMessageId): string
    {
        $parts = [];

        // Check if we need to summarize
        $this->maybeSummarize($session, $beforeMessageId);

        // Reload session to get fresh summary
        $session->refresh();

        // Include summary if exists
        if (! empty($session->summary)) {
            $parts[] = "=== RINGKASAN PERCAKAPAN SEBELUMNYA ===";
            $parts[] = $session->summary;
            $parts[] = "";
        }

        // Get recent messages (after summarized_up_to)
        $query = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $beforeMessageId);

        if ($session->summarized_up_to > 0) {
            $query->where('id', '>', $session->summarized_up_to);
        }

        $recentMessages = $query->orderByDesc('id')
            ->limit($this->recentMessageLimit)
            ->get()
            ->reverse();

        if ($recentMessages->isNotEmpty()) {
            $parts[] = "=== RIWAYAT PERCAKAPAN TERKINI ===";
            foreach ($recentMessages as $msg) {
                $role = $msg->role === 'user' ? 'User' : 'Assistant';
                $parts[] = "{$role}: {$msg->message}";
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Check if summarization is needed and perform it.
     */
    protected function maybeSummarize(AiChatSession $session, int $beforeMessageId): void
    {
        // Count unsummarized messages
        $query = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $beforeMessageId);

        if ($session->summarized_up_to > 0) {
            $query->where('id', '>', $session->summarized_up_to);
        }

        $unsummarizedCount = $query->count();

        if ($unsummarizedCount <= $this->summarizeThreshold) {
            return;
        }

        // Get messages to summarize (all except the most recent N)
        $messagesToKeep = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $beforeMessageId)
            ->orderByDesc('id')
            ->limit($this->recentMessageLimit)
            ->pluck('id');

        $keepMinId = $messagesToKeep->min() ?? $beforeMessageId;

        $messagesToSummarize = AiChatMessage::where('session_id', $session->id)
            ->where('id', '<', $keepMinId);

        if ($session->summarized_up_to > 0) {
            $messagesToSummarize->where('id', '>', $session->summarized_up_to);
        }

        $oldMessages = $messagesToSummarize->orderBy('id')->get();

        if ($oldMessages->isEmpty()) {
            return;
        }

        // Build text to summarize
        $conversationText = '';
        if (! empty($session->summary)) {
            $conversationText .= "Ringkasan sebelumnya:\n{$session->summary}\n\n";
        }
        $conversationText .= "Percakapan baru yang perlu diringkas:\n";
        foreach ($oldMessages as $msg) {
            $role = $msg->role === 'user' ? 'User' : 'Assistant';
            $conversationText .= "{$role}: {$msg->message}\n";
        }

        // Call AI to summarize
        $summary = $this->callSummarize($conversationText);

        if ($summary) {
            $session->update([
                'summary' => $summary,
                'summarized_up_to' => $oldMessages->last()->id,
            ]);
        }
    }

    /**
     * Call AI to create a conversation summary.
     */
    protected function callSummarize(string $conversationText): ?string
    {
        $prompt = <<<PROMPT
Ringkas percakapan berikut menjadi poin-poin penting yang perlu diingat untuk konteks percakapan selanjutnya.

ATURAN:
- Simpan fakta, angka, keputusan, dan konteks penting
- Buang basa-basi, pengulangan, dan detail tidak relevan
- Gunakan Bahasa Indonesia
- Maksimal 500 kata
- Format: bullet points yang padat

PERCAKAPAN:
{$conversationText}

RINGKASAN:
PROMPT;

        return $this->callAIForSummary($prompt);
    }

    /**
     * Call AI provider for summarization (uses same provider config).
     */
    protected function callAIForSummary(string $prompt): ?string
    {
        try {
            if ($this->provider === 'gemini') {
                $apiKey = $this->getApiKey();
                $baseUrl = $this->getBaseUrl();
                $url = "{$baseUrl}/models/{$this->model}:generateContent?key={$apiKey}";

                $response = Http::timeout(30)
                    ->acceptJson()
                    ->asJson()
                    ->post($url, [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 1024,
                        ],
                    ]);

                if (! $response->successful()) {
                    Log::warning('ConversationMemory summarize failed (Gemini)', [
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                return $response->json('candidates.0.content.parts.0.text');
            }

            // OpenRouter / custom provider
            $apiKey = $this->getApiKey();
            $baseUrl = $this->getBaseUrl();
            $url = "{$baseUrl}/chat/completions";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('ConversationMemory summarize failed (OpenRouter)', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Throwable $e) {
            Log::error('ConversationMemory summarize exception: ' . $e->getMessage());
            return null;
        }
    }
}
