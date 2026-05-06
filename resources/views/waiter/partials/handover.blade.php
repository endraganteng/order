{{-- Shift Handover Notes --}}
{{-- Variables: $handoverNotes (array), $waiterId (string) --}}

@php
    $hasNotes = !empty($handoverNotes);
@endphp

<div id="handover-section" style="margin: 0 1rem; padding: 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-md, 8px);">

    {{-- Previous Shift Notes --}}
    @if($hasNotes)
        <div style="margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span style="font-size: 1.1rem;">📋</span>
                <strong style="font-size: 0.9rem; color: #92400e;">Catatan Shift Sebelumnya</strong>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                @foreach($handoverNotes as $note)
                    <div style="padding: 0.6rem 0.75rem; background: #fff; border: 1px solid #fde68a; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem;">
                            <span style="font-size: 0.8rem; font-weight: 600; color: #78350f;">{{ $note['waiter_name'] ?? 'Waiter' }}</span>
                            <span style="font-size: 0.7rem; color: #a16207;">{{ isset($note['created_at']) ? date('d/m H:i', $note['created_at']) : '' }}</span>
                        </div>
                        <p style="margin: 0; font-size: 0.85rem; color: #451a03; line-height: 1.4; white-space: pre-wrap;">{{ $note['note'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Handover Form --}}
    <details id="handover-form-details" style="cursor: pointer;">
        <summary style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #92400e; user-select: none; outline: none; list-style: none; padding: 0.25rem 0;">
            <span>✍️</span>
            <span>Tulis Catatan Serah Terima</span>
            <span style="font-size: 0.75rem; font-weight: 400; color: #a16207; margin-left: auto;">(opsional)</span>
        </summary>
        <div style="margin-top: 0.75rem;">
            <textarea id="handover-note-input" placeholder="Contoh: Stok gula tinggal 2 bungkus, rak C perlu ditata ulang..." maxlength="2000"
                style="width: 100%; min-height: 80px; padding: 0.6rem; border: 1px solid #fde68a; border-radius: 6px; font-family: inherit; font-size: 0.85rem; resize: vertical; background: #fff; color: #451a03; box-sizing: border-box;"></textarea>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                <span id="handover-char-count" style="font-size: 0.7rem; color: #a16207;">0/2000</span>
                <button type="button" id="handover-submit-btn" onclick="submitHandoverNote()"
                    style="padding: 0.4rem 1rem; background: #d97706; color: #fff; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">
                    📤 Kirim
                </button>
            </div>
            <div id="handover-feedback" style="display: none; margin-top: 0.5rem; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem;"></div>
        </div>
    </details>
</div>

<script>
(function() {
    const textarea = document.getElementById('handover-note-input');
    const charCount = document.getElementById('handover-char-count');

    if (textarea && charCount) {
        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length + '/2000';
        });
    }
})();

function submitHandoverNote() {
    const textarea = document.getElementById('handover-note-input');
    const btn = document.getElementById('handover-submit-btn');
    const feedback = document.getElementById('handover-feedback');
    const note = textarea.value.trim();

    if (!note) {
        showHandoverFeedback('Catatan tidak boleh kosong.', false);
        return;
    }

    btn.disabled = true;
    btn.style.opacity = '0.6';
    btn.textContent = '⏳ Mengirim...';

    fetch('{{ route("waiter.handover.submit") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ note: note })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            showHandoverFeedback('✅ Catatan berhasil disimpan!', true);
            textarea.value = '';
            document.getElementById('handover-char-count').textContent = '0/2000';
        } else {
            showHandoverFeedback('❌ ' + (data.message || 'Gagal menyimpan.'), false);
        }
    })
    .catch(function(err) {
        showHandoverFeedback('❌ Terjadi kesalahan jaringan.', false);
    })
    .finally(function() {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.textContent = '📤 Kirim';
    });
}

function showHandoverFeedback(msg, success) {
    const feedback = document.getElementById('handover-feedback');
    feedback.style.display = 'block';
    feedback.style.background = success ? '#f0fdf4' : '#fef2f2';
    feedback.style.border = '1px solid ' + (success ? '#bbf7d0' : '#fecaca');
    feedback.style.color = success ? '#166534' : '#991b1b';
    feedback.textContent = msg;
    setTimeout(function() { feedback.style.display = 'none'; }, 4000);
}
</script>
