<x-mail::message>
{{-- Inbox preview text: the first line of the message --}}
<x-slot:preheader>{{ $introLines[0] ?? '' }}</x-slot:preheader>

{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error')
# @lang('Something went wrong')
@else
# @lang('Hello,')
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ \App\Support\MailBrand::senderName() }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang('Button not working? Copy and paste this link into your browser:')<br>
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
