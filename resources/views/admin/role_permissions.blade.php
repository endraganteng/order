@extends('admin.layout')

@section('title', 'Manajemen Permission')

@section('content')
<div class="fm-page-header">
    <h1 class="fm-page-title">🔐 Manajemen Permission</h1>
    <p class="fm-page-subtitle">Atur akses menu/fitur untuk setiap role</p>
</div>

<div class="fm-card" style="margin-bottom: 1.5rem;">
    <div class="fm-card-header">
        <h3>ℹ️ Informasi</h3>
    </div>
    <div class="fm-card-body">
        <p style="margin:0; color: #94a3b8; font-size: 0.9rem;">
            <strong>Supervisor</strong> selalu memiliki akses penuh ke semua fitur (tidak bisa dibatasi).<br>
            Pengaturan di bawah hanya berlaku untuk role selain supervisor.
        </p>
    </div>
</div>

@foreach($roles as $role)
<div class="fm-card" style="margin-bottom: 1.5rem;" id="card-{{ $role }}">
    <div class="fm-card-header" style="display: flex; align-items: center; justify-content: space-between;">
        <h3>👤 Role: <span style="text-transform: capitalize;">{{ $role }}</span></h3>
        <button type="button" class="fm-btn fm-btn-primary btn-save-permissions" data-role="{{ $role }}">
            💾 Simpan
        </button>
    </div>
    <div class="fm-card-body">
        <div class="permission-grid">
            @foreach($groups as $key => $label)
            <div class="permission-item">
                <label class="permission-toggle">
                    <input type="checkbox"
                           class="permission-checkbox"
                           data-role="{{ $role }}"
                           data-group="{{ $key }}"
                           {{ ($permissions[$role][$key] ?? false) ? 'checked' : '' }}>
                    <span class="toggle-slider"></span>
                    <span class="permission-label">{{ $label }}</span>
                </label>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endforeach

<div id="toast-container" style="position:fixed;top:1rem;right:1rem;z-index:9999;"></div>

<style>
.permission-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

@media (min-width: 768px) {
    .permission-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.permission-item {
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    transition: background 0.2s;
}

.permission-item:hover {
    background: rgba(255,255,255,0.06);
}

.permission-toggle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    user-select: none;
}

.permission-toggle input[type="checkbox"] {
    display: none;
}

.toggle-slider {
    position: relative;
    width: 44px;
    min-width: 44px;
    height: 24px;
    background: #374151;
    border-radius: 12px;
    transition: background 0.3s;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    background: #9ca3af;
    border-radius: 50%;
    transition: transform 0.3s, background 0.3s;
}

.permission-toggle input:checked + .toggle-slider {
    background: #059669;
}

.permission-toggle input:checked + .toggle-slider::after {
    transform: translateX(20px);
    background: #fff;
}

.permission-label {
    font-size: 0.875rem;
    color: #e2e8f0;
    line-height: 1.3;
}

.btn-save-permissions {
    white-space: nowrap;
}

.toast-msg {
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    color: #fff;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.toast-success { background: #059669; }
.toast-error { background: #dc2626; }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

@push('scripts')
<script>
document.querySelectorAll('.btn-save-permissions').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const role = this.dataset.role;
        const checkboxes = document.querySelectorAll('.permission-checkbox[data-role="' + role + '"]');
        const permissions = {};

        checkboxes.forEach(function(cb) {
            permissions[cb.dataset.group] = cb.checked ? 1 : 0;
        });

        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';

        fetch('{{ route("admin.permissions.save") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ role: role, permissions: permissions })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.error || 'Gagal menyimpan', 'error');
            }
        })
        .catch(function(err) {
            showToast('Network error: ' + err.message, 'error');
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = '💾 Simpan';
        });
    });
});

function showToast(msg, type) {
    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = 'toast-msg toast-' + type;
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(function() { el.remove(); }, 4000);
}
</script>
@endpush
@endsection
