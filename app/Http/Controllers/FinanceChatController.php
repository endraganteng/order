<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Services\FinanceChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FinanceChatController
 *
 * API endpoint untuk finance AI chat overlay.
 * Semua response JSON (dipakai oleh frontend overlay widget).
 */
class FinanceChatController extends Controller
{
    public function __construct(protected FinanceChatService $chat)
    {
    }

    /**
     * POST /admin/finance/ai-chat/send
     * Kirim pesan dan dapat jawaban AI.
     */
    public function send(Request $request): JsonResponse
    {
        $question = trim((string) $request->input('message', ''));
        $sessionId = (int) $request->input('session_id', 0) ?: null;

        if ($question === '') {
            return response()->json(['success' => false, 'message' => 'Pesan kosong.'], 422);
        }

        $userId = session('admin_id');
        $userId = is_numeric($userId) ? (int) $userId : null;

        $result = $this->chat->ask($question, $sessionId, $userId, 'admin');

        return response()->json([
            'success' => empty($result['error']),
            'session_id' => $result['session_id'],
            'answer' => $result['answer'],
            'assistant_message_id' => $result['assistant_message_id'],
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * GET /admin/finance/ai-chat/sessions
     * List sesi chat finance user ini.
     */
    public function sessions(): JsonResponse
    {
        $userId = session('admin_id');
        $userId = is_numeric($userId) ? (int) $userId : null;

        $sessions = AiChatSession::where('user_type', 'finance_admin')
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'title', 'updated_at']);

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    /**
     * GET /admin/finance/ai-chat/sessions/{id}/messages
     * Load messages dari session tertentu.
     */
    public function messages(int $id): JsonResponse
    {
        $session = AiChatSession::find($id);
        if (! $session || ! str_starts_with($session->user_type, 'finance_')) {
            return response()->json(['success' => false, 'message' => 'Session tidak ditemukan.'], 404);
        }

        $messages = AiChatMessage::where('session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'role', 'message', 'created_at']);

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    /**
     * DELETE /admin/finance/ai-chat/sessions/{id}
     * Hapus session.
     */
    public function deleteSession(int $id): JsonResponse
    {
        $session = AiChatSession::find($id);
        if (! $session || ! str_starts_with($session->user_type, 'finance_')) {
            return response()->json(['success' => false, 'message' => 'Session tidak ditemukan.'], 404);
        }

        AiChatMessage::where('session_id', $session->id)->delete();
        $session->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /admin/finance/ai-chat/models
     * Fetch available models dari provider yang dikonfigurasi.
     */
    public function models(Request $request): JsonResponse
    {
        $provider = trim((string) $request->input('provider', 'gemini'));
        $apiKey = trim((string) $request->input('api_key', ''));
        $baseUrl = trim((string) $request->input('base_url', ''));

        if ($apiKey === '') {
            return response()->json(['success' => false, 'message' => 'API key kosong.'], 422);
        }

        try {
            if ($provider === 'gemini') {
                $models = $this->fetchGeminiModels($apiKey);
            } else {
                $url = rtrim($baseUrl ?: 'https://openrouter.ai/api/v1', '/');
                $models = $this->fetchOpenRouterModels($apiKey, $url);
            }

            return response()->json(['success' => true, 'models' => $models]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch models: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch models dari Gemini API.
     */
    protected function fetchGeminiModels(string $apiKey): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $response->status());
        }

        $models = [];
        foreach ($response->json('models', []) as $m) {
            $name = $m['name'] ?? '';
            // Filter hanya model yang support generateContent
            $methods = $m['supportedGenerationMethods'] ?? [];
            if (! in_array('generateContent', $methods)) {
                continue;
            }
            // Strip "models/" prefix
            $id = str_replace('models/', '', $name);
            $models[] = [
                'id' => $id,
                'name' => $m['displayName'] ?? $id,
                'description' => $m['description'] ?? '',
            ];
        }

        // Sort: gemini-2.5 first, then 2.0, then others
        usort($models, function ($a, $b) {
            $scoreA = str_contains($a['id'], '2.5') ? 0 : (str_contains($a['id'], '2.0') ? 1 : 2);
            $scoreB = str_contains($b['id'], '2.5') ? 0 : (str_contains($b['id'], '2.0') ? 1 : 2);
            return $scoreA <=> $scoreB ?: strcmp($a['id'], $b['id']);
        });

        return $models;
    }

    /**
     * Fetch models dari OpenRouter/9router/custom (OpenAI-compatible).
     */
    protected function fetchOpenRouterModels(string $apiKey, string $baseUrl): array
    {
        $url = "{$baseUrl}/models";

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('API error: ' . $response->status());
        }

        $models = [];
        $data = $response->json('data', []);

        foreach ($data as $m) {
            $id = $m['id'] ?? '';
            if ($id === '') {
                continue;
            }
            $models[] = [
                'id' => $id,
                'name' => $m['name'] ?? $id,
                'description' => $m['description'] ?? '',
                'pricing' => $m['pricing'] ?? null,
            ];
        }

        // Sort alphabetically by id
        usort($models, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return $models;
    }
}
