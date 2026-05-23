@extends('admin.layout')

@section('title', 'AI Chat Produk')

@push('styles')
<style>
    .chat-shell { display:grid; grid-template-columns: 260px 1fr; gap:14px; height: calc(100vh - 140px); min-height: 480px; }
    .chat-shell .sidebar { background:#fff; border-radius:8px; box-shadow:var(--shadow-sm); display:flex; flex-direction:column; overflow:hidden; }
    .chat-shell .sidebar header { padding:12px; border-bottom:1px solid var(--color-border); font-weight:600; }
    .chat-shell .sidebar .session-list { flex:1; overflow-y:auto; }
    .chat-shell .sidebar .session-item { padding:10px 12px; border-bottom:1px solid var(--color-border); cursor:pointer; font-size:13px; }
    .chat-shell .sidebar .session-item:hover { background:#f8fafc; }
    .chat-shell .sidebar .session-item.active { background:#eef2ff; border-left:3px solid var(--color-primary); }
    .chat-shell .sidebar .session-item small { display:block; color:var(--color-text-muted); font-size:11px; margin-top:2px; }
    .chat-shell .pane { background:#fff; border-radius:8px; box-shadow:var(--shadow-sm); display:flex; flex-direction:column; overflow:hidden; }
    .chat-shell .messages { flex:1; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:14px; }
    .chat-shell .msg { max-width:80%; padding:12px 14px; border-radius:14px; font-size:14px; line-height:1.5; white-space:pre-wrap; }
    .chat-shell .msg.user { align-self:flex-end; background:var(--color-primary); color:#fff; border-bottom-right-radius:4px; }
    .chat-shell .msg.assistant { align-self:flex-start; background:#f1f5f9; color:var(--color-text); border-bottom-left-radius:4px; }
    .chat-shell .msg.assistant .recommend { margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1; font-size:12px; }
    .chat-shell .msg.assistant .recommend a { color:var(--color-primary); text-decoration:none; }
    .chat-shell .msg .feedback-bar { margin-top:6px; display:flex; gap:6px; font-size:11px; }
    .chat-shell .msg .feedback-bar button { background:transparent; border:1px solid var(--color-border); padding:2px 8px; border-radius:4px; cursor:pointer; }
    .chat-shell .msg .feedback-bar button.up:hover { color:var(--color-success); border-color:var(--color-success); }
    .chat-shell .msg .feedback-bar button.down:hover { color:var(--color-danger); border-color:var(--color-danger); }
    .chat-shell .composer { display:flex; gap:8px; padding:14px; border-top:1px solid var(--color-border); }
    .chat-shell .composer textarea { flex:1; border:1px solid var(--color-border); border-radius:8px; padding:10px; resize:none; font-family:inherit; font-size:14px; min-height:48px; max-height:140px; }
    .chat-shell .empty { color:var(--color-text-muted); font-size:14px; padding:30px; text-align:center; }

    /* Typing indicator */
    .chat-shell .msg.typing {
        align-self:flex-start;
        background:#f1f5f9;
        color:var(--color-text);
        border-bottom-left-radius:4px;
        padding:14px 16px;
        display:flex;
        gap:5px;
        align-items:center;
    }
    .chat-shell .typing-dot {
        width:8px; height:8px;
        background:#94a3b8;
        border-radius:50%;
        animation: typingBounce 1.2s infinite ease-in-out;
    }
    .chat-shell .typing-dot:nth-child(2) { animation-delay: 0.15s; }
    .chat-shell .typing-dot:nth-child(3) { animation-delay: 0.3s; }
    @keyframes typingBounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
        30% { transform: translateY(-6px); opacity: 1; }
    }

    @media (max-width: 800px) { .chat-shell { grid-template-columns: 1fr; height: auto; } .chat-shell .sidebar { max-height:200px; } }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">AI Chat Produk</h2>
        <p style="color:var(--color-text-muted); font-size:13px;">Tanyakan apapun tentang produk toko. AI hanya menjawab dari knowledge yang sudah di-approve.</p>
    </div>
    <div>
        <a href="{{ route('admin.ai_chat.index') }}" class="btn btn-primary">+ Sesi Baru</a>
    </div>
</div>

<div class="chat-shell">
    <aside class="sidebar">
        <header>Riwayat Chat</header>
        <div class="session-list">
            @forelse($sessions as $s)
                <a href="{{ route('admin.ai_chat.index', ['session_id' => $s->id]) }}" class="session-item {{ $activeSession && $activeSession->id === $s->id ? 'active' : '' }}" style="display:block; text-decoration:none; color:inherit;">
                    <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $s->title ?? 'Tanpa judul' }}</div>
                    <small>{{ $s->updated_at?->diffForHumans() }}</small>
                </a>
            @empty
                <div class="empty">Belum ada chat. Mulai pertanyaan di kanan.</div>
            @endforelse
        </div>
    </aside>

    <main class="pane">
        <div class="messages" id="messages">
            @if(count($messages) === 0)
                <div class="empty">Tanyakan: "Ada vitamin untuk anak ayam?", "Pakan kucing kering merek apa saja?", dst.</div>
            @endif
            @foreach($messages as $m)
                @php $meta = is_array($m->metadata) ? $m->metadata : (json_decode($m->metadata ?? '{}', true) ?: []); @endphp
                <div class="msg {{ $m->role }}" data-msg-id="{{ $m->id }}">
                    {!! nl2br(e($m->message)) !!}
                    @if($m->role === 'assistant' && !empty($meta['recommended']))
                        <div class="recommend">
                            <strong>Produk yang dirujuk:</strong>
                            @foreach($meta['recommended'] as $r)
                                <div>· {{ $r['name'] ?? '-' }} <span style="color:var(--color-text-muted);">(score {{ number_format((float)($r['score'] ?? 0), 1) }})</span></div>
                            @endforeach
                        </div>
                    @endif
                    @if($m->role === 'assistant')
                        <div class="feedback-bar">
                            <button class="up" onclick="rate({{ $m->id }}, 'up')">👍 Membantu</button>
                            <button class="down" onclick="rate({{ $m->id }}, 'down')">👎 Kurang tepat</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <form class="composer" onsubmit="send(event)">
            <textarea id="msgInput" placeholder="Tulis pertanyaan... (Enter untuk kirim)" required></textarea>
            <button type="submit" class="btn btn-primary" id="sendBtn">Kirim</button>
        </form>
    </main>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let activeSessionId = {{ $activeSession?->id ?? 'null' }};

const msgsEl = document.getElementById('messages');
msgsEl.scrollTop = msgsEl.scrollHeight;

document.getElementById('msgInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.querySelector('.composer').requestSubmit();
    }
});

function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function appendMsg(role, text, recommended, msgId) {
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    if (msgId) div.dataset.msgId = msgId;
    div.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
    if (role === 'assistant' && Array.isArray(recommended) && recommended.length) {
        const rec = document.createElement('div');
        rec.className = 'recommend';
        rec.innerHTML = '<strong>Produk yang dirujuk:</strong>' + recommended.map(r => `<div>· ${escapeHtml(r.name||'-')} <span style="color:#64748b;">(score ${(r.score||0).toFixed(1)})</span></div>`).join('');
        div.appendChild(rec);
    }
    if (role === 'assistant' && msgId) {
        const fb = document.createElement('div');
        fb.className = 'feedback-bar';
        fb.innerHTML = `<button class="up" onclick="rate(${msgId},'up')">👍 Membantu</button><button class="down" onclick="rate(${msgId},'down')">👎 Kurang tepat</button>`;
        div.appendChild(fb);
    }
    msgsEl.appendChild(div);
    msgsEl.scrollTop = msgsEl.scrollHeight;
}

async function send(e) {
    e.preventDefault();
    const input = document.getElementById('msgInput');
    const btn = document.getElementById('sendBtn');
    const message = input.value.trim();
    if (!message) return;
    appendMsg('user', message);
    input.value = '';
    btn.disabled = true; const orig = btn.textContent; btn.textContent = '...';
    const empty = msgsEl.querySelector('.empty'); if (empty) empty.remove();

    // Tambah typing indicator
    const typing = document.createElement('div');
    typing.className = 'msg typing';
    typing.id = 'typingIndicator';
    typing.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
    msgsEl.appendChild(typing);
    msgsEl.scrollTop = msgsEl.scrollHeight;

    try {
        const res = await fetch(`{{ route('admin.ai_chat.send') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({message: message, session_id: activeSessionId})
        });
        const data = await res.json();
        activeSessionId = data.session_id;
        // Hapus typing indicator
        document.getElementById('typingIndicator')?.remove();
        appendMsg('assistant', data.answer || 'Maaf, terjadi error.', data.recommended_products, data.assistant_message_id);
    } catch (err) {
        document.getElementById('typingIndicator')?.remove();
        appendMsg('assistant', 'Error koneksi: ' + err.message);
    } finally {
        btn.disabled = false; btn.textContent = orig;
        input.focus();
    }
}

async function rate(messageId, rating) {
    const reason = rating === 'down' ? (prompt('Alasan jawaban kurang tepat? (boleh dikosongkan)') ?? '') : '';
    const res = await fetch(`{{ route('admin.ai_chat.feedback') }}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({message_id: messageId, rating: rating, reason: reason})
    });
    const data = await res.json();
    if (data.success) {
        const target = document.querySelector(`.msg[data-msg-id="${messageId}"] .feedback-bar`);
        if (target) target.innerHTML = '<span style="color:#16a34a;">✓ Terima kasih atas feedbacknya.</span>';
    } else {
        alert('Gagal kirim feedback.');
    }
}
</script>
@endsection
