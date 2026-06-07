@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php
    $variants = [
        'primary'   => 'background: var(--color-primary,#667eea); color:#fff;',
        'success'   => 'background: var(--color-success,#16a34a); color:#fff;',
        'danger'    => 'background: var(--color-danger,#dc2626); color:#fff;',
        'warning'   => 'background: var(--color-warning,#d97706); color:#fff;',
        'secondary' => 'background:#fff; color: var(--color-text,#0f172a); border:1px solid var(--color-border,#e2e8f0);',
    ];
    $style = $variants[$variant] ?? $variants['primary'];
    $base = 'display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:var(--radius-md,8px);font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:opacity .15s;';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['style' => $base.$style]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['style' => $base.$style]) }}>{{ $slot }}</button>
@endif
