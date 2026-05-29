@extends('admin.layout')

@section('title', 'Pengaturan Sinkronisasi')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">⚙️ Pengaturan Sinkronisasi</h1>
            <p class="fm-page-subtitle">Konfigurasi koneksi API dan jadwal auto sync</p>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,400px),1fr));gap:20px;">
        {{-- API Configuration --}}
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">🔗 Koneksi API</h3>
            <form id="settingsForm">
                <div class="fm-form-group">
                    <label class="fm-label">Domain API (Base URL)</label>
                    <input type="url" class="fm-input" name="api_domain" value="{{ $settings['api_domain'] ?? '' }}" placeholder="https://domain-kasir.com">
                    <small style="color:#64748b;font-size:12px;">Contoh: https://app.petshop.com</small>
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">API Token (X-Internal-Token)</label>
                    <input type="password" class="fm-input" name="api_token" value="{{ $settings['api_token'] ?? '' }}" placeholder="Token rahasia">
                    <small style="color:#64748b;font-size:12px;">Hanya supervisor yang boleh mengatur token ini</small>
                </div>
                <div style="display:flex;gap:8px;margin-top:16px;">
                    <button type="submit" class="fm-btn fm-btn-primary">💾 Simpan</button>
                    <button type="button" class="fm-btn fm-btn-outline" id="btnTestConn">🔌 Test Koneksi</button>
                </div>
            </form>
            <div id="testResult" style="margin-top:12px;"></div>
        </div>

        {{-- Auto Sync Configuration --}}
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">⏰ Jadwal Auto Sync</h3>
            <form id="syncConfigForm">
                <div class="fm-form-group">
                    <label class="fm-label">Auto Sync</label>
                    <select class="fm-select" name="auto_sync_enabled">
                        <option value="0" {{ ($settings['auto_sync_enabled'] ?? '0') === '0' ? 'selected' : '' }}>Nonaktif</option>
                        <option value="1" {{ ($settings['auto_sync_enabled'] ?? '0') === '1' ? 'selected' : '' }}>Aktif</option>
                    </select>
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">Jam Sync</label>
                    <input type="time" class="fm-input" name="auto_sync_time" value="{{ $settings['auto_sync_time'] ?? '00:00' }}">
                    <small style="color:#64748b;font-size:12px;">Default: 00:00 (mengambil data hari sebelumnya)</small>
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">Mode Sync</label>
                    <select class="fm-select" name="sync_mode">
                        <option value="manual" {{ ($settings['sync_mode'] ?? 'manual') === 'manual' ? 'selected' : '' }}>Manual saja</option>
                        <option value="daily" {{ ($settings['sync_mode'] ?? '') === 'daily' ? 'selected' : '' }}>Daily (1x sehari)</option>
                        <option value="hourly" {{ ($settings['sync_mode'] ?? '') === 'hourly' ? 'selected' : '' }}>Hourly (setiap jam)</option>
                        <option value="daily_hourly" {{ ($settings['sync_mode'] ?? '') === 'daily_hourly' ? 'selected' : '' }}>Daily + Hourly</option>
                    </select>
                </div>
                <div class="fm-form-group">
                    <label class="fm-label">Data yang Diambil (Auto Sync)</label>
                    <select class="fm-select" name="sync_data_target">
                        <option value="yesterday" {{ ($settings['sync_data_target'] ?? 'yesterday') === 'yesterday' ? 'selected' : '' }}>Kemarin</option>
                        <option value="today" {{ ($settings['sync_data_target'] ?? '') === 'today' ? 'selected' : '' }}>Hari ini</option>
                        <option value="retry_failed" {{ ($settings['sync_data_target'] ?? '') === 'retry_failed' ? 'selected' : '' }}>Retry yang gagal</option>
                    </select>
                </div>
                <button type="submit" class="fm-btn fm-btn-primary" style="margin-top:10px;">💾 Simpan Jadwal</button>
            </form>
        </div>
    </div>

    {{-- AI Finance Chat Configuration --}}
    <div style="margin-top:20px;">
        <div class="fm-table-wrap" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">🤖 AI Finance Chat</h3>
            <p style="font-size:12px;color:#64748b;margin-bottom:16px;">Konfigurasi AI assistant untuk analisis keuangan. Support Gemini langsung, OpenRouter, 9router, atau endpoint OpenAI-compatible lainnya.</p>
            <form id="aiSettingsForm">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;">
                    <div class="fm-form-group">
                        <label class="fm-label">Provider</label>
                        <select class="fm-select" name="ai_provider" id="aiProvider" onchange="toggleAiFields()">
                            <option value="gemini" {{ ($settings['ai_provider'] ?? 'gemini') === 'gemini' ? 'selected' : '' }}>Gemini (Google AI langsung)</option>
                            <option value="openrouter" {{ ($settings['ai_provider'] ?? '') === 'openrouter' ? 'selected' : '' }}>OpenRouter / 9router / Custom</option>
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Model</label>
                        <input type="text" class="fm-input" name="ai_model" value="{{ $settings['ai_model'] ?? 'gemini-2.5-flash' }}" placeholder="gemini-2.5-flash">
                        <small style="color:#64748b;font-size:11px;">Gemini: gemini-2.5-flash | OpenRouter: google/gemini-2.5-flash, anthropic/claude-3.5-haiku, dll</small>
                    </div>
                </div>

                {{-- Gemini fields --}}
                <div id="aiGeminiFields" style="margin-top:12px;">
                    <div class="fm-form-group">
                        <label class="fm-label">Gemini API Key</label>
                        <input type="password" class="fm-input" name="ai_gemini_key" value="{{ $settings['ai_gemini_key'] ?? '' }}" placeholder="AIza...">
                        <small style="color:#64748b;font-size:11px;">Dari <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a></small>
                    </div>
                </div>

                {{-- OpenRouter/Custom fields --}}
                <div id="aiOpenRouterFields" style="margin-top:12px;display:none;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;">
                        <div class="fm-form-group">
                            <label class="fm-label">API Key</label>
                            <input type="password" class="fm-input" name="ai_api_key" value="{{ $settings['ai_api_key'] ?? '' }}" placeholder="sk-or-v1-...">
                        </div>
                        <div class="fm-form-group">
                            <label class="fm-label">Base URL</label>
                            <input type="url" class="fm-input" name="ai_base_url" value="{{ $settings['ai_base_url'] ?? 'https://openrouter.ai/api/v1' }}" placeholder="https://openrouter.ai/api/v1">
                            <small style="color:#64748b;font-size:11px;">OpenRouter: https://openrouter.ai/api/v1 | 9router: https://9router.com/api/v1</small>
                        </div>
                    </div>
                </div>

                {{-- Advanced --}}
                <details style="margin-top:14px;">
                    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#475569;">⚙️ Advanced Settings</summary>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:12px;margin-top:10px;">
                        <div class="fm-form-group">
                            <label class="fm-label">Temperature</label>
                            <input type="number" class="fm-input" name="ai_temperature" value="{{ $settings['ai_temperature'] ?? '0.3' }}" min="0" max="1" step="0.1">
                            <small style="color:#64748b;font-size:11px;">0 = fokus, 1 = kreatif</small>
                        </div>
                        <div class="fm-form-group">
                            <label class="fm-label">Max Tokens</label>
                            <input type="number" class="fm-input" name="ai_max_tokens" value="{{ $settings['ai_max_tokens'] ?? '2048' }}" min="256" max="8192" step="256">
                        </div>
                        <div class="fm-form-group">
                            <label class="fm-label">Timeout (detik)</label>
                            <input type="number" class="fm-input" name="ai_timeout" value="{{ $settings['ai_timeout'] ?? '60' }}" min="10" max="120">
                        </div>
                    </div>
                </details>

                <div style="display:flex;gap:8px;margin-top:16px;">
                    <button type="submit" class="fm-btn fm-btn-primary">💾 Simpan AI Settings</button>
                    <button type="button" class="fm-btn fm-btn-outline" id="btnTestAi">🧪 Test AI</button>
                </div>
            </form>
            <div id="aiTestResult" style="margin-top:12px;"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'fm-toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

document.getElementById('settingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const body = Object.fromEntries(fd);
    const res = await fetch('{{ route("admin.finance.settings.save") }}', {
        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)
    });
    const data = await res.json();
    showToast(data.message || 'Disimpan!', data.success ? 'success' : 'error');
});

document.getElementById('syncConfigForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const body = Object.fromEntries(fd);
    const res = await fetch('{{ route("admin.finance.settings.save") }}', {
        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)
    });
    const data = await res.json();
    showToast(data.message || 'Disimpan!', data.success ? 'success' : 'error');
});

document.getElementById('btnTestConn').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '⏳ Testing...';
    document.getElementById('testResult').innerHTML = '';

    try {
        const res = await fetch('{{ route("admin.finance.test_connection") }}', {
            method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const data = await res.json();
        const cls = data.success ? 'fm-alert-success' : 'fm-alert-error';
        document.getElementById('testResult').innerHTML = '<div class="fm-alert ' + cls + '">' + (data.success ? '✅ ' : '❌ ') + data.message + '</div>';
    } catch (e) {
        document.getElementById('testResult').innerHTML = '<div class="fm-alert fm-alert-error">❌ ' + e.message + '</div>';
    }

    btn.disabled = false;
    btn.textContent = '🔌 Test Koneksi';
});

// === AI Settings ===

function toggleAiFields() {
    const provider = document.getElementById('aiProvider').value;
    document.getElementById('aiGeminiFields').style.display = provider === 'gemini' ? 'block' : 'none';
    document.getElementById('aiOpenRouterFields').style.display = provider === 'openrouter' ? 'block' : 'none';
}
toggleAiFields();

document.getElementById('aiSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const body = Object.fromEntries(fd);
    const res = await fetch('{{ route("admin.finance.settings.save") }}', {
        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)
    });
    const data = await res.json();
    showToast(data.message || 'AI Settings disimpan!', data.success ? 'success' : 'error');
});

document.getElementById('btnTestAi').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '⏳ Testing AI...';
    document.getElementById('aiTestResult').innerHTML = '';

    try {
        const res = await fetch('{{ route("admin.finance.ai_chat.send") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({message: 'Berapa total saldo kas saat ini? Jawab singkat.'})
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('aiTestResult').innerHTML = '<div class="fm-alert fm-alert-success">✅ AI Connected! Jawaban: ' + data.answer.substring(0, 200) + '...</div>';
        } else {
            document.getElementById('aiTestResult').innerHTML = '<div class="fm-alert fm-alert-error">❌ ' + (data.error || data.answer || 'Gagal') + '</div>';
        }
    } catch (e) {
        document.getElementById('aiTestResult').innerHTML = '<div class="fm-alert fm-alert-error">❌ ' + e.message + '</div>';
    }

    btn.disabled = false;
    btn.textContent = '🧪 Test AI';
});
</script>
@endpush
