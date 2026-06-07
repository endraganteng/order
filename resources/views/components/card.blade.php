@props(['title' => null])

<div {{ $attributes->merge(['style' => 'background:#fff;border-radius:var(--radius-lg,12px);box-shadow:var(--shadow-sm,0 2px 8px rgba(0,0,0,0.06));padding:20px;margin-bottom:20px;']) }}>
    @if($title)
        <h3 style="margin-bottom:16px;color:var(--color-text,#0f172a);font-size:16px;font-weight:700;">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
