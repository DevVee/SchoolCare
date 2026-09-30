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

        @php
            $footOrg    = trim((string) settings('org_name')) ?: (trim((string) settings('clinic_name')) ?: 'School Clinic');
            $footClinic = trim((string) settings('clinic_name'));
            $footAiName = trim((string) settings('ai_assistant_name')) ?: 'Coco';
            $footAi     = filter_var(settings('ai_enabled', true), FILTER_VALIDATE_BOOLEAN)
                && auth()->user()?->can('use-ai-assistant') && Route::has('ai-assistant.index');
        @endphp
        <footer class="app-footer">
            <div class="app-footer-inner">
                <div class="app-footer-brand">
                    <img src="{{ settings()->imageUrl('brand_logo') }}" alt="" width="24" height="24" decoding="async">
                    <div class="min-w-0">
                        <p class="app-footer-name">{{ $footOrg }}</p>
                        @if ($footClinic !== '' && $footClinic !== $footOrg)<p class="app-footer-sub">{{ $footClinic }}</p>@endif
                    </div>
                </div>
                <nav class="app-footer-links" aria-label="Footer">
                    @if (Route::has('clinic'))<a href="{{ route('clinic') }}" target="_blank" rel="noopener">Clinic page</a>@endif
                    @if (Route::has('privacy'))<a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy</a>@endif
                    @if ($footAi)<a href="{{ route('ai-assistant.index') }}">Ask {{ $footAiName }}</a>@endif
                    <button type="button" class="app-footer-search" data-spotlight-open><kbd>Ctrl</kbd><kbd>K</kbd> Search</button>
                </nav>
            </div>
            <div class="app-footer-meta">
                <span>&copy; {{ date('Y') }} {{ $footOrg }}. Health records in this system are confidential.</span>
                <span>Powered by {{ settings('app_name') ?: 'SchoolCare' }}</span>
            </div>
        </footer>
    </div>

    <x-ui.confirm-dialog />
    <x-ui.flash-toasts />
    @stack('modals')
    @stack('scripts')
</body>
</html>
