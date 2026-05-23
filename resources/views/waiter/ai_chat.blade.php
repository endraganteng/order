<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Chat — Asisten Produk</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f6fb;
            color: #273444;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .top {
            background: #fff;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }
        .top h1 { margin: 0; font-size: 1rem; font-weight: 700; display:flex; gap:6px; align-items:center; }
        .top .muted { color: #6b7280; font-size: 11px; }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 9px 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-back { background: #e5e7eb; color:#1f2937; }
        .btn-new { background: #6366f1; color:#fff; }

        .wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 720px;
            width: 100%;
            margin: 0 auto;
            padding: 12px;
            gap: 10px;
        }
        .session-pick {
            background: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .session-pick select {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 13px;
            background: #fff;
        }
        .messages {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 4px 2px;
            -webkit-overflow-scrolling: touch;
        }
        .msg {
            max-width: 88%;
            padding: 10px 12px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .msg.user {
            align-self: flex-end;
            background: #6366f1;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .msg.assistant {
            align-self: flex-start;
            background: #fff;
            color: #1f2937;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.05);
        }
        .msg.assistant .recommend {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #e5e7eb;
            font-size: 12px;
            color: #4b5563;
        }
        .msg.assistant .recommend strong { color:#1f2937; display:block; margin-bottom:4px; }
        .msg .feedback-bar {
            margin-top: 8px;
            display: flex;
            gap: 6px;
            font-size: 11px;
        }
        .msg .feedback-bar button {
            background: transparent;
            border: 1px solid #e5e7eb;
            padding: 4px 9px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
        }
        .empty {
            text-align: center;
            padding: 30px 20px;
            color: #6b7280;
            font-size: 13px;
        }
        .empty .examples {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .empty .examples button {
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            color: #374151;
            text-align: left;
        }

        .composer {
            display: flex;
            gap: 8px;
            background: #fff;
            border-radius: 14px;
            padding: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            position: sticky;
            bottom: 0;
        }
        .composer textarea {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
            resize: none;
            font-family: inherit;
            font-size: 14px;
            min-height: 44px;
            max-height: 130px;
            outline: none;
        }
        .composer textarea:focus { border-color: #6366f1; }
        .composer button {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0 18px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
        }
        .composer button:disabled { background: #c7d2fe; cursor: not-allowed; }

        /* Typing indicator */
        .msg.typing {
            align-self: flex-start;
            background: #fff;
            color: #1f2937;
            border-bottom-left-radius: 4px;
            padding: 14px 16px;
            display: flex;
            gap: 5px;
            align-items: center;
            box-shadow: 0 1px 6px rgba(0,0,0,0.05);
        }
        .typing-dot {
            width: 8px; height: 8px;
            background: #94a3b8;
            border-radius: 50%;
            animation: waiterTypingBounce 1.2s infinite ease-in-out;
        }
        .typing-dot:nth-child(2) { animation-delay: 0.15s; }
        .typing-dot:nth-child(3) { animation-delay: 0.3s; }
        @keyframes waiterTypingBounce {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
            30% { transform: translateY(-6px); opacity: 1; }
        }

        @media (max-width: 480px) {
            .wrap { padding: 8px; }
            .msg { max-width: 92%; font-size: 13px; }
            .composer button { padding: 0 12px; }
        }
    </style>
</head>
<body>

<div class="top">
    <div>
        <h1>🤖 AI Asisten Produk</h1>
        <div class="muted">Tanya rekomendasi produk untuk pelanggan</div>
    </div>
    <div style="display:flex; gap:6px;">
        <a href="{{ route('waiter.tasks', [], false) }}" class="btn btn-back">← Kembali</a>
        <a href="{{ route('waiter.ai_chat.index', [], false) }}" class="btn btn-new">+ Baru</a>
    </div>
</div>

<div class="wrap">
    @if($sessions->isNotEmpty())
        <div class="session-pick">
            <span style="color:#6b7280;">Sesi:</span>
            <select onchange="if(this.value) location.href = this.value;">
                <option value="">— sesi baru —</option>
                @foreach($sessions as $s)
                    <option value="{{ route('waiter.ai_chat.index', ['session_id' => $s->id], false) }}" @selected($activeSession && $activeSession->id === $s->id)>
                        {{ \Illuminate\Support\Str::limit($s->title ?? 'Tanpa judul', 50) }} · {{ $s->updated_at?->diffForHumans() }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="messages" id="messages">
        @if(count($messages) === 0)
            <div class="empty">
                <strong>Mulai percakapan</strong>
                <p style="margin:6px 0 0; color:#6b7280; font-size:13px;">Contoh pertanyaan:</p>
                <div class="examples">
                    <button onclick="setMsg('Ada vitamin untuk anak ayam yang lemas?')">Ada vitamin untuk anak ayam yang lemas?</button>
                    <button onclick="setMsg('Pakan kucing kering untuk kucing dewasa yang ada?')">Pakan kucing kering untuk kucing dewasa yang ada?</button>
                    <button onclick="setMsg('Obat cacing ikan koi apa saja?')">Obat cacing ikan koi apa saja?</button>
                </div>
            </div>
        @endif
        @foreach($messages as $m)
            @php $meta = is_array($m->metadata) ? $m->metadata : (json_decode($m->metadata ?? '{}', true) ?: []); @endphp
            <div class="msg {{ $m->role }}" data-msg-id="{{ $m->id }}">
                {!! nl2br(e($m->message)) !!}
                @if($m->role === 'assistant' && !empty($meta['recommended']))
                    <div class="recommend">
                        <strong>Produk yang dirujuk:</strong>
                        @foreach($meta['recommended'] as $r)
                            <div>· {{ $r['name'] ?? '-' }}</div>
                        @endforeach
                    </div>
                @endif
                @if($m->role === 'assistant')
                    <div class="feedback-bar">
                        <button onclick="rate({{ $m->id }}, 'up')">👍 Membantu</button>
                        <button onclick="rate({{ $m->id }}, 'down')">👎 Kurang</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <form class="composer" onsubmit="send(event)">
        <textarea id="msgInput" placeholder="Tanyakan tentang produk... (Enter untuk kirim)" required></textarea>
        <button type="submit" id="sendBtn">Kirim</button>
    </form>
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

function setMsg(t) { document.getElementById('msgInput').value = t; document.getElementById('msgInput').focus(); }

function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function appendMsg(role, text, recommended, msgId) {
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    if (msgId) div.dataset.msgId = msgId;
    div.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
    if (role === 'assistant' && Array.isArray(recommended) && recommended.length) {
        const rec = document.createElement('div');
        rec.className = 'recommend';
        rec.innerHTML = '<strong>Produk yang dirujuk:</strong>' + recommended.map(r => `<div>· ${escapeHtml(r.name||'-')}</div>`).join('');
        div.appendChild(rec);
    }
    if (role === 'assistant' && msgId) {
        const fb = document.createElement('div');
        fb.className = 'feedback-bar';
        fb.innerHTML = `<button onclick="rate(${msgId},'up')">👍 Membantu</button><button onclick="rate(${msgId},'down')">👎 Kurang</button>`;
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
    btn.disabled = true; btn.textContent = '...';
    const empty = msgsEl.querySelector('.empty'); if (empty) empty.remove();

    // Tambah typing indicator
    const typing = document.createElement('div');
    typing.className = 'msg typing';
    typing.id = 'typingIndicator';
    typing.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
    msgsEl.appendChild(typing);
    msgsEl.scrollTop = msgsEl.scrollHeight;

    try {
        const res = await fetch(`{{ route('waiter.ai_chat.send') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({message: message, session_id: activeSessionId})
        });
        const data = await res.json();
        activeSessionId = data.session_id;
        document.getElementById('typingIndicator')?.remove();
        appendMsg('assistant', data.answer || 'Maaf, terjadi error.', data.recommended_products, data.assistant_message_id);
    } catch (err) {
        document.getElementById('typingIndicator')?.remove();
        appendMsg('assistant', 'Error koneksi: ' + err.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Kirim';
        input.focus();
    }
}

async function rate(messageId, rating) {
    const reason = rating === 'down' ? (prompt('Alasan jawaban kurang tepat? (boleh dikosongkan)') ?? '') : '';
    const res = await fetch(`{{ route('waiter.ai_chat.feedback') }}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({message_id: messageId, rating: rating, reason: reason})
    });
    const data = await res.json();
    if (data.success) {
        const target = document.querySelector(`.msg[data-msg-id="${messageId}"] .feedback-bar`);
        if (target) target.innerHTML = '<span style="color:#16a34a;">✓ Terima kasih.</span>';
    }
}
</script>
</body>
</html>
