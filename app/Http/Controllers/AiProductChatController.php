<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AiProductFeedback;
use App\Services\AiProductChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AiProductChatController
 *
 * Dual-portal chat (admin + waiter) untuk rekomendasi produk.
 * Routing yang ada:
 *   GET  /admin/ai-chat            → page (admin.auth)
 *   POST /admin/ai-chat/send       → send message
 *   GET  /waiter/ai-chat           → page (waiter.auth)
 *   POST /waiter/ai-chat/send      → send message
 *
 * Identifikasi user diambil dari session (user_type 'admin'|'waiter').
 */
class AiProductChatController extends Controller
{
    public function __construct(protected AiProductChatService $chat)
    {
    }

    /**
     * Halaman chat admin.
     */
    public function adminIndex(Request $request)
    {
        return $this->renderPage('admin', 'admin.ai_chat.index', $request);
    }

    /**
     * Halaman chat waiter.
     */
    public function waiterIndex(Request $request)
    {
        return $this->renderPage('waiter', 'waiter.ai_chat', $request);
    }

    /**
     * Send message dari admin portal.
     */
    public function adminSend(Request $request): JsonResponse
    {
        return $this->doSend($request, 'admin');
    }

    /**
     * Send message dari waiter portal.
     */
    public function waiterSend(Request $request): JsonResponse
    {
        return $this->doSend($request, 'waiter');
    }

    /**
     * GET messages of session (untuk reload history saat user buka session lama).
     */
    public function adminMessages(int $sessionId): JsonResponse
    {
        return $this->doMessages($sessionId, 'admin');
    }

    public function waiterMessages(int $sessionId): JsonResponse
    {
        return $this->doMessages($sessionId, 'waiter');
    }

    /**
     * POST feedback (rating thumbs up/down + reason).
     */
    public function adminFeedback(Request $request): JsonResponse
    {
        return $this->doFeedback($request, 'admin');
    }

    public function waiterFeedback(Request $request): JsonResponse
    {
        return $this->doFeedback($request, 'waiter');
    }

    // ----------------------------------------------------------------------

    protected function renderPage(string $userType, string $view, Request $request)
    {
        [$userId] = $this->resolveUser($userType);

        $sessions = AiChatSession::where('user_type', $userType)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $activeSessionId = (int) $request->input('session_id', 0);
        $activeSession = $activeSessionId > 0 ? AiChatSession::find($activeSessionId) : null;
        $messages = [];
        if ($activeSession && $activeSession->user_type === $userType) {
            $messages = AiChatMessage::where('session_id', $activeSession->id)
                ->orderBy('id')->get();
        }

        return view($view, [
            'sessions' => $sessions,
            'activeSession' => $activeSession,
            'messages' => $messages,
            'userType' => $userType,
        ]);
    }

    protected function doSend(Request $request, string $userType): JsonResponse
    {
        $question = trim((string) $request->input('message', ''));
        $sessionId = (int) $request->input('session_id', 0) ?: null;
        if ($question === '') {
            return response()->json(['success' => false, 'message' => 'Pesan kosong.'], 422);
        }

        [$userId] = $this->resolveUser($userType);
        if ($sessionId) {
            $existing = AiChatSession::find($sessionId);
            if (! $existing || $existing->user_type !== $userType || ($existing->user_id !== null && $existing->user_id !== $userId)) {
                return response()->json(['success' => false, 'message' => 'Session tidak valid.'], 403);
            }
        }

        $result = $this->chat->ask($question, $sessionId, $userId, $userType);

        return response()->json([
            'success' => empty($result['error']),
            'session_id' => $result['session_id'],
            'user_message_id' => $result['user_message_id'],
            'assistant_message_id' => $result['assistant_message_id'],
            'answer' => $result['answer'],
            'recommended_products' => $result['recommended_products'],
            'error' => $result['error'] ?? null,
        ]);
    }

    protected function doMessages(int $sessionId, string $userType): JsonResponse
    {
        $session = AiChatSession::find($sessionId);
        if (! $session || $session->user_type !== $userType) {
            return response()->json(['success' => false, 'message' => 'Session tidak ditemukan.'], 404);
        }
        [$userId] = $this->resolveUser($userType);
        if ($session->user_id !== null && $session->user_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }
        $msgs = AiChatMessage::where('session_id', $session->id)->orderBy('id')->get();

        return response()->json(['success' => true, 'session' => $session, 'messages' => $msgs]);
    }

    protected function doFeedback(Request $request, string $userType): JsonResponse
    {
        $messageId = (int) $request->input('message_id', 0);
        $rating = (string) $request->input('rating', ''); // up|down
        $reason = trim((string) $request->input('reason', ''));
        $note = trim((string) $request->input('note', ''));

        if (! in_array($rating, ['up', 'down'], true)) {
            return response()->json(['success' => false, 'message' => 'rating harus up|down.'], 422);
        }

        $msg = AiChatMessage::find($messageId);
        if (! $msg || $msg->role !== 'assistant') {
            return response()->json(['success' => false, 'message' => 'Message tidak valid.'], 404);
        }
        $session = $msg->session;
        if (! $session || $session->user_type !== $userType) {
            return response()->json(['success' => false, 'message' => 'Session tidak valid.'], 403);
        }

        [$userId] = $this->resolveUser($userType);
        $userMsg = AiChatMessage::where('session_id', $session->id)
            ->where('role', 'user')
            ->where('id', '<', $msg->id)
            ->orderByDesc('id')
            ->first();

        $recommendedIds = is_array($msg->metadata['recommended'] ?? null)
            ? array_column($msg->metadata['recommended'], 'id')
            : [];

        AiProductFeedback::create([
            'session_id' => $session->id,
            'message_id' => $msg->id,
            'user_id' => $userId,
            'user_type' => $userType,
            'question' => $userMsg->message ?? ($msg->metadata['question'] ?? ''),
            'answer' => $msg->message,
            'product_ids' => $recommendedIds,
            'rating' => $rating,
            'reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
            'note' => $note !== '' ? $note : null,
        ]);

        return response()->json(['success' => true, 'message' => 'Feedback diterima.']);
    }

    /**
     * @return array{0: ?int, 1: string}  [userId, displayName]
     */
    protected function resolveUser(string $userType): array
    {
        if ($userType === 'admin') {
            $id = session('admin_id');
            $name = session('admin_email') ?? session('admin_name') ?? 'Admin';

            return [is_numeric($id) ? (int) $id : null, (string) $name];
        }
        $id = session('waiter_id');
        $name = session('waiter_name') ?? 'Waiter';

        return [is_numeric($id) ? (int) $id : null, (string) $name];
    }
}
