@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    // Brand colour for the main action; fixed green/red for success/error.
    [$bg, $fg] = match ($color) {
        'success' => ['#15803D', '#FFFFFF'],
        'error'   => ['#B91C1C', '#FFFFFF'],
        default   => [\App\Support\MailBrand::color(), \App\Support\MailBrand::colorText()],
    };
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" bgcolor="{{ $bg }}" style="background-color: {{ $bg }}; border-radius: 8px;">
<a href="{{ $url }}" class="button" target="_blank" rel="noopener" style="background-color: {{ $bg }}; border: 1px solid {{ $bg }}; color: {{ $fg }};">{{ $slot }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
