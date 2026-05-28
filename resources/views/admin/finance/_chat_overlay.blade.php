{{-- Finance AI Chat Overlay Widget --}}
<div id="financeChatOverlay" style="display:none;">
    <style>
        #financeChatOverlay .fc-fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            z-index: 9999;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        #financeChatOverlay .fc-fab:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 28px rgba(99,102,241,0.5);
        }
        #financeChatOverlay .fc-panel {
            position: fixed;
            bottom: 92px;
            right: 24px;
            width: 380px;
            max-height: 560px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.15);
            display: none;
            flex-direction: column;
            z-index: 9998;
            overflow: hidden;
            animation: fcSlideUp 0.25s ease-out;
        }
        @keyframes fcSlideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #financeChatOverlay .fc-panel.open { display: flex; }
        #financeChatOverlay .fc-header {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        #financeChatOverlay .fc-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        #financeChatOverlay .fc-header-actions {
            display: flex;
            gap: 6px;
        }
        #financeChatOverlay .fc-header-actions button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #financeChatOverlay .fc-header-actions button:hover {
            background: rgba(255,255,255,0.3);
        }
        #financeChatOverlay .fc-messages {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 200px;
            max-height: 380px;
        }
        #financeChatOverlay .fc-msg {
            max-width: 85%;
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        #financeChatOverlay .fc-msg.user {
            align-self: flex-end;
            background: #6366f1;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        #financeChatOverlay .fc-msg.assistant {
            align-self: flex-start;
            background: #f3f4f6;
            color: #1f2937;
            border-bottom-left-radius: 4px;
        }
        #financeChatOverlay .fc-empty {
            text-align: center;
            padding: 30px 16px;
            color: #6b7280;
            font-size: 12px;
        }
        #financeChatOverlay .fc-empty .fc-suggestions {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        #financeChatOverlay .fc-empty .fc-suggestions button {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 7px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            color: #374151;
            text-align: left;
            transition: background 0.15s;
        }
        #financeChatOverlay .fc-empty .fc-suggestions button:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
        }
        #financeChatOverlay .fc-composer {
            display: flex;
            gap: 6px;
            padding: 10px 12px;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
        }
        #financeChatOverlay .fc-composer input {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13px;
            outline: none;
            font-family: inherit;
        }
        #financeChatOverlay .fc-composer input:focus {
            border-color: #6366f1;
        }
        #financeChatOverlay .fc-composer button {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0 14px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
        }
        #financeChatOverlay .fc-composer button:disabled {
            background: #c7d2fe;
            cursor: not-allowed;
        }
        #financeChatOverlay .fc-typing {
            align-self: flex-start;
            background: #f3f4f6;
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            gap: 4px;
            align-items: center;
        }
        #financeChatOverlay .fc-typing-dot {
            width: 7px;
            height: 7px;
            background: #94a3b8;
            border-radius: 50%;
            animation: fcBounce 1.2s infinite ease-in-out;
        }
        #financeChatOverlay .fc-typing-dot:nth-child(2) { animation-delay: 0.15s; }
        #financeChatOverlay .fc-typing-dot:nth-child(3) { animation-delay: 0.3s; }
        @keyframes fcBounce {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
        #financeChatOverlay .fc-sessions-panel {
            position: absolute;
            top: 48px;
            left: 0;
            right: 0;
            bottom: 0;
            background: #fff;
            z-index: 10;
            overflow-y: auto;
            padding: 10px;
            display: none;
        }
        #financeChatOverlay .fc-sessions-panel.open { display: block; }
        #financeChatOverlay .fc-session-item {
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            color: #374151;
            border: 1px solid #e5e7eb;
            margin-bottom: 6px;
            transition: background 0.15s;
        }
        #financeChatOverlay .fc-session-item:hover { background: #eef2ff; }
        #financeChatOverlay .fc-session-item .fc-session-title {
            font-weight: 600;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #financeChatOverlay .fc-session-item .fc-session-time {
            font-size: 11px;
            color: #6b7280;
        }
        @media (max-width: 480px) {
            #financeChatOverlay .fc-panel {
                width: calc(100vw - 20px);
                right: 10px;
                bottom: 80px;
                max-height: 70vh;
            }
        }
    </style>

    {{-- FAB Button --}}
    <button class="fc-fab" onclick="fcToggle()" title="Finance AI Chat">
        🤖
    </button>

    {{-- Chat Panel --}}
    <div class="fc-panel" id="fcPanel">
        <div class="fc-header">
            <h3>🤖 Finance AI</h3>
            <div class="fc-header-actions">
                <button onclick="fcShowSessions()" title="Riwayat">📋</button>
                <button onclick="fcNewSession()" title="Chat Baru">+</button>
                <button onclick="fcToggle()" title="Tutup">✕</button>
            </div>
        </div>

        <div class="fc-sessions-panel" id="fcSessionsPanel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <strong style="font-size:12px;">Riwayat Chat</strong>
                <button onclick="fcHideSessions()" style="background:none;border:none;cursor:pointer;font-size:14px;">✕</button>
            </div>
            <div id="fcSessionsList"></div>
        </div>

        <div class="fc-messages" id="fcMessages">
            <div class="fc-empty">
                <div style="font-size:20px;margin-bottom:6px;">💬</div>
                <strong>Tanya apa saja tentang keuangan</strong>
                <p style="margin:4px 0 0;color:#9ca3af;font-size:11px;">Data real-time dari sistem finance</p>
                <div class="fc-suggestions">
                    <button onclick="fcSetMsg('Berapa saldo kas hari ini?')">💰 Berapa saldo kas hari ini?</button>
                    <button onclick="fcSetMsg('Ada selisih kas nggak hari ini?')">🔍 Ada selisih kas nggak hari ini?</button>
                    <button onclick="fcSetMsg('Ringkasan pengeluaran minggu ini')">📊 Ringkasan pengeluaran minggu ini</button>
                    <button onclick="fcSetMsg('QRIS yang belum cair berapa?')">⏳ QRIS yang belum cair berapa?</button>
                </div>
            </div>
        </div>

        <form class="fc-composer" onsubmit="fcSend(event)">
            <input type="text" id="fcInput" placeholder="Tanya tentang keuangan..." autocomplete="off" required>
            <button type="submit" id="fcSendBtn">Kirim</button>
        </form>
    </div>
</div>

<script>
(function() {
    const overlay = document.getElementById('financeChatOverlay');
    overlay.style.display = 'block';

    const panel = document.getElementById('fcPanel');
    const messagesEl = document.getElementById('fcMessages');
    const input = document.getElementById('fcInput');
    const sendBtn = document.getElementById('fcSendBtn');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let isOpen = false;
    let sessionId = null;
    let sending = false;

    window.fcToggle = function() {
        isOpen = !isOpen;
        panel.classList.toggle('open', isOpen);
        if (isOpen) input.focus();
    };

    window.fcNewSession = function() {
        sessionId = null;
        messagesEl.innerHTML = `
            <div class="fc-empty">
                <div style="font-size:20px;margin-bottom:6px;">💬</div>
                <strong>Tanya apa saja tentang keuangan</strong>
                <p style="margin:4px 0 0;color:#9ca3af;font-size:11px;">Data real-time dari sistem finance</p>
                <div class="fc-suggestions">
                    <button onclick="fcSetMsg('Berapa saldo kas hari ini?')">💰 Berapa saldo kas hari ini?</button>
                    <button onclick="fcSetMsg('Ada selisih kas nggak hari ini?')">🔍 Ada selisih kas nggak hari ini?</button>
                    <button onclick="fcSetMsg('Ringkasan pengeluaran minggu ini')">📊 Ringkasan pengeluaran minggu ini</button>
                    <button onclick="fcSetMsg('QRIS yang belum cair berapa?')">⏳ QRIS yang belum cair berapa?</button>
                </div>
            </div>`;
        fcHideSessions();
    };

    window.fcSetMsg = function(text) {
        input.value = text;
        input.focus();
    };

    window.fcShowSessions = async function() {
        const sessPanel = document.getElementById('fcSessionsPanel');
        const listEl = document.getElementById('fcSessionsList');
        sessPanel.classList.add('open');
        listEl.innerHTML = '<div style="text-align:center;color:#6b7280;font-size:12px;padding:20px;">Memuat...</div>';

        try {
            const res = await fetch("{{ route('admin.finance.ai_chat.sessions') }}", {
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
            });
            const data = await res.json();
            if (data.sessions && data.sessions.length > 0) {
                listEl.innerHTML = data.sessions.map(s => `
                    <div class="fc-session-item" onclick="fcLoadSession(${s.id})">
                        <div class="fc-session-title">${escHtml(s.title || 'Tanpa judul')}</div>
                        <div class="fc-session-time">${timeAgo(s.updated_at)}</div>
                    </div>
                `).join('');
            } else {
                listEl.innerHTML = '<div style="text-align:center;color:#6b7280;font-size:12px;padding:20px;">Belum ada riwayat chat</div>';
            }
        } catch (e) {
            listEl.innerHTML = '<div style="text-align:center;color:#ef4444;font-size:12px;padding:20px;">Gagal memuat</div>';
        }
    };

    window.fcHideSessions = function() {
        document.getElementById('fcSessionsPanel').classList.remove('open');
    };

    window.fcLoadSession = async function(id) {
        fcHideSessions();
        sessionId = id;
        messagesEl.innerHTML = '<div style="text-align:center;color:#6b7280;font-size:12px;padding:20px;">Memuat...</div>';

        try {
            const res = await fetch(`{{ url('/admin/finance/ai-chat/sessions') }}/${id}/messages`, {
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
            });
            const data = await res.json();
            messagesEl.innerHTML = '';
            if (data.messages) {
                data.messages.forEach(m => appendMsg(m.role, m.message));
            }
        } catch (e) {
            messagesEl.innerHTML = '<div style="text-align:center;color:#ef4444;font-size:12px;padding:20px;">Gagal memuat pesan</div>';
        }
    };

    window.fcSend = async function(e) {
        e.preventDefault();
        if (sending) return;
        const message = input.value.trim();
        if (!message) return;

        sending = true;
        sendBtn.disabled = true;
        sendBtn.textContent = '...';
        input.value = '';

        // Remove empty state
        const empty = messagesEl.querySelector('.fc-empty');
        if (empty) empty.remove();

        appendMsg('user', message);

        // Typing indicator
        const typing = document.createElement('div');
        typing.className = 'fc-typing';
        typing.id = 'fcTyping';
        typing.innerHTML = '<div class="fc-typing-dot"></div><div class="fc-typing-dot"></div><div class="fc-typing-dot"></div>';
        messagesEl.appendChild(typing);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        try {
            const res = await fetch("{{ route('admin.finance.ai_chat.send') }}", {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({message, session_id: sessionId})
            });
            const data = await res.json();
            document.getElementById('fcTyping')?.remove();

            if (data.session_id) sessionId = data.session_id;
            appendMsg('assistant', data.answer || 'Maaf, terjadi error.');
        } catch (err) {
            document.getElementById('fcTyping')?.remove();
            appendMsg('assistant', 'Error koneksi: ' + err.message);
        } finally {
            sending = false;
            sendBtn.disabled = false;
            sendBtn.textContent = 'Kirim';
            input.focus();
        }
    };

    // Enter to send
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.querySelector('#financeChatOverlay .fc-composer').requestSubmit();
        }
    });

    function appendMsg(role, text) {
        const div = document.createElement('div');
        div.className = 'fc-msg ' + role;
        div.innerHTML = role === 'assistant' ? formatMd(text) : escHtml(text);
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function formatMd(text) {
        let s = escHtml(text);
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*([^\s*][^*]*?)\*/g, '<em>$1</em>');
        s = s.replace(/^[\-•]\s+(.+)/gm, '<li>$1</li>');
        s = s.replace(/(<li>.*<\/li>)/gs, '<ul style="margin:4px 0;padding-left:16px;">$1</ul>');
        s = s.replace(/<\/ul>\s*<ul[^>]*>/g, '');
        s = s.replace(/`([^`]+)`/g, '<code style="background:#e5e7eb;padding:1px 4px;border-radius:3px;font-size:12px;">$1</code>');
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
        if (diff < 60) return 'baru saja';
        if (diff < 3600) return Math.floor(diff/60) + ' menit lalu';
        if (diff < 86400) return Math.floor(diff/3600) + ' jam lalu';
        return Math.floor(diff/86400) + ' hari lalu';
    }
})();
</script>
