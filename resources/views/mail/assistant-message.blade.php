{{-- Email prepared by the assistant and confirmed by a staff member (App\Mail\AssistantMessageMail). Branded layout: resources/views/vendor/mail. --}}
<x-mail::message>
@foreach ($paragraphs as $paragraph)
{!! nl2br(e($paragraph), false) !!}

@endforeach
<x-mail::subcopy>
Sent by {{ $senderName }} from {{ \App\Support\MailBrand::senderName() }}.
</x-mail::subcopy>
</x-mail::message>
