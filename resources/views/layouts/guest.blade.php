{{--
    Auth pages (login, forgot / reset / confirm password, verify email, register).
    Apple-style sign-in: a calm #F5F5F7 canvas with a faint grid and one centered
    white card (logo, big title, one muted line, the form) that materializes on
    load. Nothing else sits under the card (owner, 2026-09-30).
    With Settings > Branding > "Sign-in page photo" (login_image) the card grows
    a photo pane on desktop (>= 992px) with the clinic name and login_quote over
    a dark scrim. Without a photo, login_quote (when set) sits under the card.
    Phones: one full-width card with a 16px gutter, no photo.

    <x-guest-layout>
        <x-slot:title>Sign in</x-slot:title>   (optional, browser tab title)
        ...heading + form...
    </x-guest-layout>
--}}
@php
    $appName = settings('app_name');
    $orgName = trim((string) settings('org_name'));
    $clinicName = trim((string) settings('clinic_name'));
    $quote = trim((string) settings('login_quote'));
    $quoteAuthor = trim((string) settings('login_quote_author'));
    $logoUrl = settings()->imageUrl('brand_logo');
    $photoUrl = filled(settings('login_image')) ? settings()->imageUrl('login_image') : null;
    $pageTitle = isset($title) && trim(strip_tags((string) $title)) !== '' ? trim(strip_tags((string) $title)) : 'Sign in';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} | {{ $appName }}</title>
    @include('layouts.partials.favicons')

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <x-ui.brand-style />
</head>
<body class="auth-body">
    <div class="auth-shell">
        <span class="auth-grid" aria-hidden="true"></span>
        <main class="auth-main" id="main">
            <div @class(['auth-card', 'has-photo' => $photoUrl])>
                <div class="auth-block">
                    <x-ui.logo variant="sidebar" size="44" :href="url('/')" class="auth-brand" />
                    {{ $slot }}
                </div>

                @if ($photoUrl)
                <aside class="auth-aside" aria-label="About the clinic">
                    <img src="{{ $photoUrl }}" alt="" width="1600" height="1067" class="auth-photo" decoding="async" fetchpriority="low">
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
                @endif
            </div>

            @if ($quote !== '' && ! $photoUrl)
                <figure class="auth-quote">
                    <blockquote>{{ $quote }}</blockquote>
                    @if ($quoteAuthor !== '')
                        <figcaption>{{ $quoteAuthor }}</figcaption>
                    @endif
                </figure>
            @endif
        </main>
    </div>
</body>
</html>
