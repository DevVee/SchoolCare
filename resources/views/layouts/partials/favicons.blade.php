{{--
    Browser tab and home screen icons. An uploaded favicon (Admin > Settings > Branding), else an
    uploaded school logo, wins; otherwise the built-in icon set in public/brand is used.
    Never throws, so the error pages can include it too.
--}}
@php
    $iconUpload = null;
    $touchUpload = null;
    try {
        if (filled(settings('brand_favicon')) || filled(settings('brand_logo'))) {
            $iconUpload = settings()->imageUrl('brand_favicon');
        }
        if (filled(settings('brand_logo'))) {
            $touchUpload = settings()->imageUrl('brand_logo');
        }
    } catch (\Throwable $e) {
        $iconUpload = $touchUpload = null;
    }
@endphp
@if ($iconUpload)
    <link rel="icon" href="{{ $iconUpload }}">
@else
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('brand/favicon-16.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('brand/logo-192.png') }}">
@endif
    <link rel="apple-touch-icon" href="{{ $touchUpload ?: asset('brand/apple-touch-icon.png') }}">
