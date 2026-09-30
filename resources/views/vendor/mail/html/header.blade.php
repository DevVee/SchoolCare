@props(['url'])
@php($logo = \App\Support\MailBrand::logoUrl())
<tr>
<td class="header">
<a href="{{ $url }}" class="header-link">
@if ($logo)
<img src="{{ $logo }}" class="logo" width="40" height="40" alt="">
@endif
<span class="header-name">{{ $slot }}</span>
</a>
</td>
</tr>
