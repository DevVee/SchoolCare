@php($sender = \App\Support\MailBrand::senderName())
<x-mail::layout>
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
            {{ $sender }}
@foreach (\App\Support\MailBrand::contactLines() as $line)
            {{ $line }}
@endforeach

            This is an automated message from {{ \App\Support\MailBrand::appName() }}.@if (! \App\Support\MailBrand::replyTo()) Please do not reply to this email.@endif
            © {{ date('Y') }} {{ $sender }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
