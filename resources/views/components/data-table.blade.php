@props(['headers' => [], 'empty' => 'Belum ada data'])

<div style="overflow-x:auto;border-radius:var(--radius-md,8px);border:1px solid var(--color-border,#e2e8f0);">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        @if(count($headers))
            <thead>
                <tr style="background:var(--color-bg,#f8fafc);text-align:left;">
                    @foreach($headers as $h)
                        <th style="padding:10px 14px;font-weight:600;color:var(--color-text-secondary,#475569);border-bottom:1px solid var(--color-border,#e2e8f0);white-space:nowrap;">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>

    @if(isset($emptyCheck) && $emptyCheck)
        <x-empty-state :message="$empty" />
    @endif
</div>
