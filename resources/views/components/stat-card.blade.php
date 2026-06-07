@props(['label' => '', 'value' => '', 'color' => 'var(--color-primary,#667eea)', 'icon' => null])

<div {{ $attributes->merge(['style' => 'background:#fff;border-radius:var(--radius-lg,12px);box-shadow:var(--shadow-sm,0 2px 8px rgba(0,0,0,0.06));padding:20px;text-align:center;']) }}>
    @if($icon)
        <div style="margin-bottom:8px;color:{{ $color }};">{!! $icon !!}</div>
    @endif
    <div class="num" style="font-size:32px;font-weight:700;color:{{ $color }};line-height:1.1;margin-bottom:6px;">{{ $value }}</div>
    <p style="color:var(--color-text-muted,#64748b);font-size:13px;margin:0;">{{ $label }}</p>
    @if(!$slot->isEmpty())
        <div style="margin-top:10px;">{{ $slot }}</div>
    @endif
</div>
