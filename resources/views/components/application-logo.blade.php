{{-- Brand mark as an image: the uploaded logo (Admin > Settings > Branding) or the built-in icon. --}}
@php
    try {
        $logoSrc = settings()->imageUrl('brand_logo', asset('brand/logo.png')) ?: asset('brand/logo.png');
        $logoAlt = (string) (settings('app_name') ?: config('app.name'));
    } catch (\Throwable $e) {
        $logoSrc = asset('brand/logo.png');
        $logoAlt = (string) config('app.name');
    }
@endphp
<img {{ $attributes->merge(['src' => $logoSrc, 'alt' => $logoAlt, 'decoding' => 'async']) }}>
