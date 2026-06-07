@props(['message' => 'Belum ada data', 'icon' => '📭'])

<div style="text-align:center;padding:48px 24px;color:#9ca3af;">
    <div style="font-size:48px;margin-bottom:12px;">{{ $icon }}</div>
    <p style="font-size:14px;margin:0;">{{ $slot->isEmpty() ? $message : $slot }}</p>
</div>
