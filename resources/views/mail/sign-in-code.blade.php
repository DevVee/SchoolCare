{{-- Email sign-in code (App\Mail\SignInCodeMail). Branded layout: resources/views/vendor/mail. --}}
<x-mail::message>
<x-slot:preheader>Your sign-in code is {{ $code }}. It expires in {{ $minutes }} minutes.</x-slot:preheader>

# Your sign-in code

Your sign-in code is {{ $code }}. It expires in {{ $minutes }} minutes. If this wasn't you, change your password.

<x-mail::code>{{ $code }}</x-mail::code>

Regards,<br>
{{ \App\Support\MailBrand::senderName() }}
</x-mail::message>
