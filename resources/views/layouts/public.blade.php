{{--
    Public pages that need no sign in (health information form, appointment
    request, clinic schedule and their confirmation pages).
    A simple white top bar with the school logo and clinic name, the page
    content, and a quiet footer with the clinic contact details from Settings.

    @extends('layouts.public')
    @section('title', 'Request an appointment')     (browser tab title)
    @section('nav', 'request')                      (current top bar link: schedule | request)
    @section('content') ... @endsection
    @push('scripts') ... @endpush

    The landing page (landing/*) replaces the top bar and footer with its own
    and uses the full width: @section('header'), @section('footer'),
    @section('bare', '1') (no container around the content),
    @section('document_title') (the whole <title>), @section('body_class').
--}}
@php
    $appName    = settings('app_name');
    $clinicName = trim((string) settings('clinic_name')) ?: $appName;
    $orgName    = trim((string) settings('org_name'));
    $address    = trim((string) settings('clinic_address'));
    $phone      = trim((string) settings('clinic_contact'));
    $email      = trim((string) settings('clinic_email'));
    $booking    = (bool) settings('public_booking_enabled', false);
    $current    = trim($__env->yieldContent('nav'));
    $pageTitle  = trim($__env->yieldContent('title'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('document_title')@yield('document_title')@else{{ $pageTitle !== '' ? $pageTitle.' | ' : '' }}{{ $clinicName }}@endif</title>
    <link rel="icon" href="{{ settings()->imageUrl('brand_favicon') }}">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <x-ui.brand-style />
    @stack('styles')
</head>
<body class="pub-body @yield('body_class')">
    <a class="visually-hidden-focusable pub-skip" href="#main">Skip to content</a>

    @hasSection('header')
    @yield('header')
    @else
    <header class="pub-top">
        <div class="pub-container pub-top-inner">
            <a href="{{ url('/') }}" class="pub-brand">
                <img src="{{ settings()->imageUrl('brand_logo', '/sscms-icon.svg') }}" alt="" width="40" height="40" class="pub-brand-logo">
                <span class="pub-brand-text">
                    <span class="pub-brand-name">{{ $clinicName }}</span>
                    @if ($orgName !== '')<span class="pub-brand-sub">{{ $orgName }}</span>@endif
                </span>
            </a>
            <nav class="pub-nav" aria-label="Site">
                @if ($booking)
                    <a href="{{ route('public.schedule') }}" class="pub-nav-link" @if ($current === 'schedule') aria-current="page" @endif>Clinic schedule</a>
                    <a href="{{ route('public.appointments.create') }}" class="pub-nav-link" @if ($current === 'request') aria-current="page" @endif>Request appointment</a>
                @endif
                <a href="{{ route('login') }}" class="pub-nav-link">Staff sign in</a>
            </nav>
        </div>
    </header>
    @endif

    @hasSection('bare')
    <main id="main" class="pub-main-bare" tabindex="-1">
        @yield('content')
    </main>
    @else
    <main id="main" class="pub-main" tabindex="-1">
        <div class="pub-container">
            @yield('content')
        </div>
    </main>
    @endif

    @hasSection('footer')
    @yield('footer')
    @else
    <footer class="pub-footer">
        <div class="pub-container pub-footer-inner">
            <div class="pub-footer-contact">
                <p class="pub-footer-name">{{ $clinicName }}</p>
                @if ($address !== '')
                    <p class="pub-footer-line"><x-ui.icon name="geo-alt" />{{ $address }}</p>
                @endif
                @if ($phone !== '' || $email !== '')
                    <p class="pub-footer-line pub-footer-links">
                        @if ($phone !== '')
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"><x-ui.icon name="telephone" />{{ $phone }}</a>
                        @endif
                        @if ($email !== '')
                            <a href="mailto:{{ $email }}"><x-ui.icon name="envelope" />{{ $email }}</a>
                        @endif
                    </p>
                @endif
            </div>
            <p class="pub-footer-copy">&copy; {{ date('Y') }} {{ $orgName !== '' ? $orgName : $appName }}</p>
        </div>
    </footer>
    @endif

    <x-ui.flash-toasts :errors="false" />
    @stack('scripts')
</body>
</html>
