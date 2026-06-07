@props(['status' => ''])

@php
    $map = [
        'pending'     => 'background:#fef3c7;color:#92400e;',
        'in_progress' => 'background:#dbeafe;color:#1e40af;',
        'done'        => 'background:#dcfce7;color:#166534;',
        'completed'   => 'background:#dcfce7;color:#166534;',
        'overdue'     => 'background:#fee2e2;color:#991b1b;',
        'cancelled'   => 'background:#f3f4f6;color:#6b7280;',
        'failed'      => 'background:#fee2e2;color:#991b1b;',
    ];
    $style = $map[strtolower($status)] ?? 'background:#f3f4f6;color:#6b7280;';
@endphp

<span style="{{ $style }} padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;">
    {{ $slot->isEmpty() ? $status : $slot }}
</span>
