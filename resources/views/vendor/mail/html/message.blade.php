@php
    $sender  = \App\Support\MailBrand::senderName();
    $contact = \App\Support\MailBrand::contactLines();
@endphp
<x-mail::layout>
{{-- Inbox preview text --}}
<x-slot:preheader>{{ $preheader ?? '' }}</x-slot:preheader>

{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ \App\Support\MailBrand::appName() }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
<strong>{{ $sender }}</strong>
@foreach ($contact as $line)
<br>{{ $line }}
@endforeach

<span class="footer-note">This is an automated message from {{ \App\Support\MailBrand::appName() }}.@if (! \App\Support\MailBrand::replyTo()) Please do not reply to this email.@endif<br>© {{ date('Y') }} {{ $sender }}</span>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
