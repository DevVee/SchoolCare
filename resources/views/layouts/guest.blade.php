{{--
    Auth pages (login, forgot / reset / confirm password, verify email, register).
    Desktop (>= 992px): form column (45%) + photo panel (55%). The photo is
    Settings > Branding > "Sign-in page photo" (login_image) under a solid dark
    overlay; without a photo the panel is solid brand-700 with a faint logo
    watermark. Clinic name, school name, login_subtext, today's hours, phone,
    email and login_quote (only when set) sit bottom-left in white.
    Phones: photo hidden, the form block is centered with the logo on top.

    <x-guest-layout>
        <x-slot:title>Sign in</x-slot:title>   (optional, browser tab title)
        ...heading + form...
    </x-guest-layout>
--}}
@php
    $appName = settings('app_name');
    $orgName = trim((string) settings('org_name'));
    $clinicName = trim((string) settings('clinic_name'));
    $subtext = trim((string) settings('login_subtext'));
    $quote = trim((string) settings('login_quote'));
    $quoteAuthor = trim((string) settings('login_quote_author'));
    $phone = trim((string) settings('clinic_contact'));
    $email = trim((string) settings('clinic_email'));
    $logoUrl = settings()->imageUrl('brand_logo');
    $photoUrl = filled(settings('login_image')) ? settings()->imageUrl('login_image') : null;
    $pageTitle = isset($title) && trim(strip_tags((string) $title)) !== '' ? trim(strip_tags((string) $title)) : 'Sign in';

    // "Open today 7:30 AM to 5:00 PM" / "Closed today" (skipped when no hours are configured)
    $hoursLine = null;
    try {
        $today = \App\Support\ClinicHours::forDate(now());
        if ($today === null) {
            $hoursLine = 'Closed today';
        } elseif (! ($today['open'] === '00:00' && $today['close'] === '23:59')) {
            $fmt = fn ($t) => \Carbon\Carbon::createFromFormat('H:i', $t)->format('g:i A');
            $hoursLine = 'Open today '.$fmt($today['open']).' to '.$fmt($today['close']);
        }
    } catch (\Throwable $e) {
        $hoursLine = null;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} | {{ $appName }}</title>
    <link rel="icon" href="{{ settings()->imageUrl('brand_favicon') }}">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <x-ui.brand-style />
</head>
<body class="auth-body">
    <div class="auth-shell">
        <main class="auth-main" id="main">
            <div class="auth-center">
                <div class="auth-block">
                    <x-ui.logo variant="sidebar" size="44" :href="url('/')" class="auth-brand" />
                    {{ $slot }}
                </div>
            </div>
            <p class="auth-footer">&copy; {{ date('Y') }} {{ $orgName !== '' ? $orgName : $appName }}</p>
        </main>

        <aside @class(['auth-aside', 'has-photo' => $photoUrl, 'no-photo' => ! $photoUrl]) aria-label="About the clinic">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" width="1600" height="1067" class="auth-photo" decoding="async" fetchpriority="low">
            @else
                <img src="{{ $logoUrl }}" alt="" width="560" height="560" class="auth-watermark" decoding="async" aria-hidden="true">
            @endif
            <div class="auth-overlay" aria-hidden="true"></div>

            <div class="auth-aside-content">
                <div class="auth-aside-id">
                    <img src="{{ $logoUrl }}" alt="" width="48" height="48" class="auth-aside-logo" decoding="async">
                    <div class="min-w-0">
                        <p class="auth-aside-title">{{ $clinicName !== '' ? $clinicName : $appName }}</p>
                        @if ($orgName !== '')
                            <p class="auth-aside-org">{{ $orgName }}</p>
                        @endif
                    </div>
                </div>
                @if ($subtext !== '')
                    <p class="auth-aside-text">{{ $subtext }}</p>
                @endif
                @if ($hoursLine || $phone !== '' || $email !== '')
                    <ul class="auth-aside-info">
                        @if ($hoursLine)<li><x-ui.icon name="clock" />{{ $hoursLine }}</li>@endif
                        @if ($phone !== '')<li><x-ui.icon name="telephone" />{{ $phone }}</li>@endif
                        @if ($email !== '')<li><x-ui.icon name="envelope" />{{ $email }}</li>@endif
                    </ul>
                @endif
                @if ($quote !== '')
                    <figure class="auth-aside-quote">
                        <blockquote>{{ $quote }}</blockquote>
                        @if ($quoteAuthor !== '')
                            <figcaption>{{ $quoteAuthor }}</figcaption>
                        @endif
                    </figure>
                @endif
            </div>
        </aside>
    </div>
</body>
</html>
