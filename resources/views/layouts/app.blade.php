<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        {{-- resources/js/ui/session.js: CSRF refresh + session keep-alive every 20 minutes --}}
        <meta name="session-keepalive" content="{{ route('session.token') }}">
    @endauth

    <title>@yield('title', 'Dashboard') | {{ settings('app_name') }}</title>

    @include('layouts.partials.favicons')

    {{-- Apply the remembered collapsed sidebar before first paint (no flash) --}}
    <script>try{if(localStorage.getItem('schoolcare.sidebar')==='collapsed'){document.documentElement.classList.add('sidebar-collapsed')}}catch(e){}</script>
    {{-- Cards wait for resources/js/ui/life.js to reveal them (a CSS safety net shows them anyway) --}}
    <script>if('IntersectionObserver' in window&&'animate' in document.documentElement){document.documentElement.classList.add('js-reveal')}</script>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <x-ui.brand-style />
    @stack('styles')
</head>
<body class="app">
    <a href="#main" class="skip-link">Skip to main content</a>

    @include('layouts.partials.sidebar')

    <div class="app-column">
        @include('layouts.partials.topbar')

        <main class="app-main" id="main" tabindex="-1">
            <div class="app-content">
                @yield('content')
            </div>
        </main>

        <footer class="app-footer">
            &copy; {{ date('Y') }} {{ filled(settings('org_name')) ? settings('org_name') : settings('app_name') }}
        </footer>
    </div>

    <x-ui.confirm-dialog />
    <x-ui.flash-toasts />
    @stack('modals')
    @stack('scripts')
</body>
</html>
