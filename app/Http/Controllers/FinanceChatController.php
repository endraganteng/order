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
}
