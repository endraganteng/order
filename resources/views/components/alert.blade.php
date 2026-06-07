@props(['type' => 'success'])

@php
    $styles = [
        'success' => 'background: var(--color-success-bg,#f0fdf4); border: 1px solid var(--color-success-border,#bbf7d0); color: var(--color-success,#16a34a);',
        'error'   => 'background: var(--color-danger-bg,#fef2f2); border: 1px solid var(--color-danger-border,#fecaca); color: var(--color-danger,#dc2626);',
        'warning' => 'background: var(--color-warning-bg,#fffbeb); border: 1px solid var(--color-warning-border,#fde68a); color: var(--color-warning,#d97706);',
        'info'    => 'background: var(--color-info-bg,#f0f9ff); border: 1px solid var(--color-info-border,#bae6fd); color: var(--color-info,#0284c7);',
    ];
    $style = $styles[$type] ?? $styles['success'];
@endphp

<div style="{{ $style }} padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;">
    {{ $slot }}
</div>
