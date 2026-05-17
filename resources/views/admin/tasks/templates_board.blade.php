@extends('admin.layout')

@section('title', 'Board Template Tugas Berulang - Admin')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $scopeLabel = $scope === 'rack_check' ? 'Cek Rak' : 'Tugas Umum';
    $otherScope = $scope === 'rack_check' ? 'general' : 'rack_check';
    $otherScopeLabel = $scope === 'rack_check' ? 'Tugas Umum' : 'Cek Rak';
    $scheduleUpdateUrl = fn($id) => route('admin.tasks.recurring.schedule_update', $id);
    $editUrl = fn($id) => route('admin.tasks.recurring.edit', $id);
@endphp

{{-- Flash error container --}}
<div id="tb-flash" style="display:none; position:fixed; top:16px; right:16px; z-index:9999; padding:12px 18px; border-radius:8px; font-size:14px; font-weight:600; box-shadow:var(--shadow-md); max-width:340px;"></div>

{{-- Header --}}
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <div>
        <h2 style="margin:0; color:var(--color-text); font-size:clamp(20px,4vw,28px);">
            📅 Board Template — <span style="color:var(--color-primary);">{{ $scopeLabel }}</span>
        </h2>
        <div style="font-size:13px; color:var(--color-text-muted); margin-top:4px;">
            Seret kartu antar kolom untuk mengubah jadwal. Perubahan disimpan otomatis.
        </div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('admin.tasks.templates.board', ['scope' => $otherScope]) }}"
           class="btn" style="background:#e2e8f0; color:var(--color-text);">
            🔀 Buka {{ $otherScopeLabel }}
        </a>
        @if($scope === 'rack_check')
            <a href="{{ route('admin.tasks.rack.index') }}" class="btn" style="background:#e2e8f0; color:var(--color-text);">← Daftar Tugas</a>
        @else
            <a href="{{ route('admin.tasks.index') }}" class="btn" style="background:#e2e8f0; color:var(--color-text);">← Daftar Tugas</a>
        @endif
    </div>
</div>

{{-- Legend --}}
<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; font-size:12px;">
    <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#667eea;"></span> Aktif</span>
    <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#94a3b8;"></span> Tidak Aktif</span>
    <span style="color:var(--color-text-muted);">Klik judul kartu → buka edit lengkap</span>
</div>

{{-- Board --}}
<div class="tb-board" id="tbBoard">
    @foreach($columns as $colKey => $col)
    @php
        $cards = $grouped[$colKey] ?? [];
        $isInactive = $colKey === 'inactive';
        $isDailyCol = $colKey === 'daily';
        $isEveryN = $colKey === 'every_n';
        $colHeaderColor = $isInactive ? '#94a3b8' : ($isDailyCol ? '#10b981' : ($isEveryN ? '#f59e0b' : '#667eea'));
    @endphp
    <div class="tb-col"
         data-col-key="{{ $colKey }}"
         data-recurrence="{{ $isInactive ? '' : ($isDailyCol ? 'daily' : ($isEveryN ? 'every_n_days' : 'weekly')) }}"
         data-weekly-day="{{ (is_numeric($colKey)) ? $colKey : '' }}"
         data-is-active="{{ $isInactive ? '0' : '1' }}">
        <div class="tb-col-header" style="border-top-color:{{ $colHeaderColor }};">
            <span class="tb-col-title">{{ $col['label'] }}</span>
            <span class="tb-col-count">{{ count($cards) }}</span>
        </div>
        <div class="tb-col-drop" id="tbDrop-{{ $colKey }}">
            @forelse($cards as $t)
            @php
                $tId = $t['id'] ?? '';
                $tTitle = $t['title'] ?? '-';
                $tTime = $t['schedule_time'] ?? '';
                $tType = $t['task_type'] ?? 'general';
                $tActive = (bool) ($t['is_active'] ?? true);
                $tRecurrence = $t['recurrence_type'] ?? 'daily';
                $tWeeklyDay = $t['weekly_day'] ?? null;
                $tInterval = $t['interval_days'] ?? null;
                $assignType = $t['assignment_type'] ?? 'all';
                $waiterId = $t['assigned_waiter_id'] ?? '';
                $waiterName = ($assignType === 'single' && $waiterId) ? ($waiterMap[$waiterId] ?? $waiterId) : 'Semua';
            @endphp
            <div class="tb-card"
                 draggable="true"
                 data-id="{{ $tId }}"
                 data-title="{{ e($tTitle) }}"
                 data-recurrence="{{ $tRecurrence }}"
                 data-weekly-day="{{ $tWeeklyDay ?? '' }}"
                 data-is-active="{{ $tActive ? '1' : '0' }}"
                 data-col="{{ $colKey }}">
                <a href="{{ $editUrl($tId) }}" class="tb-card-title" title="Buka edit lengkap">{{ $tTitle }}</a>
                <div class="tb-card-badges">
                    @if($tTime)
                    <span class="tb-badge tb-badge--time">🕐 {{ $tTime }}</span>
                    @endif
                    <span class="tb-badge tb-badge--type">{{ $tType === 'rack_check' ? '📦 Rak' : '📋 Umum' }}</span>
                    <span class="tb-badge tb-badge--waiter">👤 {{ $waiterName }}</span>
                    @if($tRecurrence === 'every_n_days' && $tInterval)
                    <span class="tb-badge tb-badge--interval">⏱ Tiap {{ $tInterval }}h</span>
                    @endif
                </div>
                @if($tActive)
                <button type="button"
                        class="tb-card-trigger js-tb-trigger"
                        data-template-id="{{ $tId }}"
                        data-template-title="{{ e($tTitle) }}"
                        title="Trigger template ini sekarang (bypass jadwal)">
                    🚀 Trigger sekarang
                </button>
                @endif
            </div>
            @empty
            <div class="tb-col-empty">Tidak ada template di kolom ini</div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>

<style>
/* ── Board Layout ── */
.tb-board {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 16px;
    align-items: flex-start;
    min-height: 60vh;
}
.tb-col {
    flex: 0 0 220px;
    min-width: 200px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.15s;
}
.tb-col.is-drag-over {
    box-shadow: 0 0 0 3px var(--color-primary);
    background: var(--color-primary-bg);
}
.tb-col-header {
    padding: 10px 12px 8px;
    border-top: 3px solid #667eea;
    border-radius: 10px 10px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.tb-col-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
}
.tb-col-count {
    font-size: 12px;
    background: #e2e8f0;
    color: var(--color-text-secondary);
    border-radius: 12px;
    padding: 1px 8px;
    font-weight: 600;
}
.tb-col-drop {
    padding: 8px;
    flex: 1;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tb-col-empty {
    font-size: 12px;
    color: var(--color-text-muted);
    text-align: center;
    padding: 20px 8px;
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    margin: 4px 0;
}

/* ── Cards ── */
.tb-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: grab;
    transition: box-shadow 0.15s, transform 0.1s;
    user-select: none;
}
.tb-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    transform: translateY(-1px);
}
.tb-card.is-dragging {
    opacity: 0.4;
    cursor: grabbing;
}
.tb-card-title {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text);
    text-decoration: none;
    margin-bottom: 6px;
    line-height: 1.3;
}
.tb-card-title:hover {
    color: var(--color-primary);
    text-decoration: underline;
}
.tb-card-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.tb-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    white-space: nowrap;
    font-weight: 600;
}
.tb-badge--time   { background: #dbeafe; color: #1d4ed8; }
.tb-badge--type   { background: #fef3c7; color: #92400e; }
.tb-badge--waiter { background: #d1fae5; color: #065f46; }
.tb-badge--interval { background: #fce7f3; color: #9d174d; }

/* ── Trigger button ── */
.tb-card-trigger {
    display: block;
    width: 100%;
    margin-top: 8px;
    padding: 6px 10px;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.1s, box-shadow 0.1s;
}
.tb-card-trigger:hover {
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.4);
    transform: translateY(-1px);
}
.tb-card-trigger:active { transform: translateY(0); }
.tb-card-trigger:disabled {
    background: #94a3b8;
    cursor: wait;
    opacity: 0.7;
    transform: none;
}

/* ── Touch clone ── */
.tb-drag-clone {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    opacity: 0.85;
    width: 200px;
    background: #fff;
    border: 2px solid var(--color-primary);
    border-radius: 8px;
    padding: 10px 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text);
}

@media (max-width: 640px) {
    .tb-board {
        flex-direction: column;
        overflow-x: unset;
    }
    .tb-col {
        flex: unset;
        min-width: unset;
        width: 100%;
    }
}
</style>

<script>
var SCHEDULE_UPDATE_BASE = "{{ rtrim(url('admin/tasks/recurring'), '/') }}";

(function () {
    "use strict";

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    var board = document.getElementById("tbBoard");

    // ── Flash helper ──
    function showFlash(msg, type) {
        var el = document.getElementById("tb-flash");
        el.textContent = msg;
        el.style.display = "block";
        el.style.background = type === "error" ? "#fee2e2" : "#d1fae5";
        el.style.color = type === "error" ? "#991b1b" : "#065f46";
        el.style.border = "1px solid " + (type === "error" ? "#fca5a5" : "#6ee7b7");
        clearTimeout(el._timer);
        el._timer = setTimeout(function () { el.style.display = "none"; }, 4000);
    }

    // ── Column count update ──
    function updateColCount(colEl) {
        var cards = colEl.querySelectorAll(".tb-card");
        var countEl = colEl.querySelector(".tb-col-count");
        if (countEl) countEl.textContent = cards.length;

        var dropEl = colEl.querySelector(".tb-col-drop");
        var emptyEl = dropEl.querySelector(".tb-col-empty");
        if (cards.length === 0) {
            if (!emptyEl) {
                var e = document.createElement("div");
                e.className = "tb-col-empty";
                e.textContent = "Tidak ada template di kolom ini";
                dropEl.appendChild(e);
            }
        } else {
            if (emptyEl) emptyEl.parentNode.removeChild(emptyEl);
        }
    }

    // ── Build patch payload from target column ──
    function buildPatch(targetCol) {
        var rt = targetCol.getAttribute("data-recurrence");
        var wd = targetCol.getAttribute("data-weekly-day");
        var active = targetCol.getAttribute("data-is-active");

        var patch = {};
        patch.is_active = active === "1";

        if (patch.is_active) {
            if (rt) patch.recurrence_type = rt;
            if (wd) patch.weekly_day = parseInt(wd, 10);
        }

        return patch;
    }

    // ── AJAX persist ──
    function persistMove(cardEl, targetColEl, sourceColEl) {
        var id = cardEl.getAttribute("data-id");
        var patch = buildPatch(targetColEl);

        var url = SCHEDULE_UPDATE_BASE + "/" + id + "/schedule";

        fetch(url, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify(patch),
        })
        .then(function (res) {
            return res.json().then(function (data) {
                data._status = res.status;
                return data;
            });
        })
        .then(function (data) {
            if (data.success) {
                // Update card data attrs
                if (patch.recurrence_type) cardEl.setAttribute("data-recurrence", patch.recurrence_type);
                if (patch.weekly_day) cardEl.setAttribute("data-weekly-day", patch.weekly_day);
                cardEl.setAttribute("data-is-active", patch.is_active ? "1" : "0");
                cardEl.setAttribute("data-col", targetColEl.getAttribute("data-col-key"));
                showFlash("✅ Jadwal berhasil diperbarui", "success");
            } else {
                // Rollback
                rollback(cardEl, sourceColEl, targetColEl);
                showFlash("❌ " + (data.message || "Gagal menyimpan"), "error");
            }
        })
        .catch(function () {
            rollback(cardEl, sourceColEl, targetColEl);
            showFlash("❌ Koneksi gagal — perubahan dibatalkan", "error");
        });
    }

    function rollback(cardEl, sourceColEl, targetColEl) {
        var sourceDropEl = sourceColEl.querySelector(".tb-col-drop");
        sourceDropEl.appendChild(cardEl);
        updateColCount(sourceColEl);
        updateColCount(targetColEl);
    }

    // ── HTML5 Drag & Drop ──
    var dragState = { card: null, sourceCol: null };

    function attachDragToCard(card) {
        card.setAttribute("draggable", "true");
        card.addEventListener("dragstart", function (e) {
            dragState.card = card;
            dragState.sourceCol = card.closest(".tb-col");
            e.dataTransfer.setData("text/plain", card.getAttribute("data-id"));
            e.dataTransfer.effectAllowed = "move";
            card.classList.add("is-dragging");
        });
        card.addEventListener("dragend", function () {
            card.classList.remove("is-dragging");
            dragState.card = null;
        });
    }

    board.querySelectorAll(".tb-card").forEach(attachDragToCard);

    board.querySelectorAll(".tb-col").forEach(function (col) {
        var dropEl = col.querySelector(".tb-col-drop");

        col.addEventListener("dragover", function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            col.classList.add("is-drag-over");
        });
        col.addEventListener("dragleave", function (e) {
            if (!col.contains(e.relatedTarget)) {
                col.classList.remove("is-drag-over");
            }
        });
        col.addEventListener("drop", function (e) {
            e.preventDefault();
            col.classList.remove("is-drag-over");

            var card = dragState.card;
            var sourceCol = dragState.sourceCol;
            if (!card || !sourceCol) return;
            if (sourceCol === col) return; // same col, noop

            // Optimistic move
            dropEl.appendChild(card);
            updateColCount(col);
            updateColCount(sourceCol);

            persistMove(card, col, sourceCol);
        });
    });

    // ── Touch Support ──
    var touchState = {
        active: false,
        clone: null,
        card: null,
        sourceCol: null,
        startX: 0,
        startY: 0,
    };

    function createTouchClone(card) {
        var clone = document.createElement("div");
        clone.className = "tb-drag-clone";
        clone.textContent = card.getAttribute("data-title");
        document.body.appendChild(clone);
        return clone;
    }

    board.querySelectorAll(".tb-card").forEach(function (card) {
        card.addEventListener("touchstart", function (e) {
            var touch = e.touches[0];
            touchState.startX = touch.clientX;
            touchState.startY = touch.clientY;
            touchState.card = card;
            touchState.sourceCol = card.closest(".tb-col");
            touchState.active = false;
            touchState.clone = null;
        }, { passive: true });

        card.addEventListener("touchmove", function (e) {
            if (!touchState.card) return;
            var touch = e.touches[0];
            var dx = Math.abs(touch.clientX - touchState.startX);
            var dy = Math.abs(touch.clientY - touchState.startY);

            if (!touchState.active && (dx > 8 || dy > 8)) {
                touchState.active = true;
                touchState.clone = createTouchClone(card);
            }

            if (touchState.active && touchState.clone) {
                e.preventDefault();
                touchState.clone.style.left = (touch.clientX - 100) + "px";
                touchState.clone.style.top = (touch.clientY - 24) + "px";

                board.querySelectorAll(".tb-col").forEach(function (c) { c.classList.remove("is-drag-over"); });
                var el = document.elementFromPoint(touch.clientX, touch.clientY);
                if (el) {
                    var targetCol = el.closest(".tb-col");
                    if (targetCol) targetCol.classList.add("is-drag-over");
                }
            }
        }, { passive: false });

        card.addEventListener("touchend", function (e) {
            board.querySelectorAll(".tb-col").forEach(function (c) { c.classList.remove("is-drag-over"); });

            if (touchState.clone) {
                document.body.removeChild(touchState.clone);
                touchState.clone = null;
            }

            if (touchState.active) {
                var touch = e.changedTouches[0];
                var el = document.elementFromPoint(touch.clientX, touch.clientY);
                if (el) {
                    var targetCol = el.closest(".tb-col");
                    if (targetCol && targetCol !== touchState.sourceCol && touchState.card) {
                        var dropEl = targetCol.querySelector(".tb-col-drop");
                        dropEl.appendChild(touchState.card);
                        updateColCount(targetCol);
                        updateColCount(touchState.sourceCol);
                        persistMove(touchState.card, targetCol, touchState.sourceCol);
                    }
                }
            }

            touchState.card = null;
            touchState.sourceCol = null;
            touchState.active = false;
        });
    });

    // Re-attach drag to any card appended dynamically
    window.tbAttachCard = attachDragToCard;

    // ── Trigger Sekarang (force-generate per template) ──
    var FORCE_GENERATE_BASE = "{{ rtrim(url('admin/tasks/recurring'), '/') }}";
    if (board) {
        board.addEventListener("click", function (event) {
            var btn = event.target.closest(".js-tb-trigger");
            if (!btn) return;
            event.preventDefault();
            event.stopPropagation();

            var tplId = btn.getAttribute("data-template-id") || "";
            var tplTitle = btn.getAttribute("data-template-title") || "(template)";
            if (!tplId) return;
            if (!window.confirm('Trigger generate task untuk "' + tplTitle + '" sekarang?\n\nIni akan langsung membuat task untuk waiter yang memenuhi syarat (mengabaikan jadwal jam dan flag last_generated_date).')) {
                return;
            }

            var origLabel = btn.textContent;
            btn.disabled = true;
            btn.textContent = "⏳ Triggering...";

            fetch(FORCE_GENERATE_BASE + "/" + encodeURIComponent(tplId) + "/force-generate", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: "{}"
            })
                .then(function (r) {
                    return r.json().then(function (body) { return { status: r.status, body: body }; });
                })
                .then(function (resp) {
                    if (resp.status >= 200 && resp.status < 300 && resp.body && resp.body.success) {
                        showFlash(resp.body.message || "Trigger berhasil", "success");
                    } else {
                        showFlash((resp.body && resp.body.message) || "Trigger gagal", "error");
                    }
                })
                .catch(function (err) {
                    showFlash("Network error: " + (err && err.message ? err.message : "unknown"), "error");
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = origLabel;
                });
        });
    }

}());
</script>

@endsection
