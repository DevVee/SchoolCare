{{--
    Branded error page shell. Overrides the framework's errors::minimal, so the
    default 401, 402, 429 (and any page extending it) are branded too.
    Sections: title, code, message, optional actions (buttons) and hint.
    Settings and the Vite stylesheet are loaded defensively: an error page must
    never throw. 500 and 503 use errors/partials/static.blade.php instead.
--}}
@php
    $get = function (string $key, $default = '') {
        try {
            $v = settings($key, $default);
            return ($v === null || $v === '') ? $default : $v;
        } catch (\Throwable $e) {
            return $default;
        }
    };
    $appName = (string) $get('app_name', config('app.name'));
    $orgName = trim((string) $get('org_name', ''));
    try {
        $favicon = settings()->imageUrl('brand_favicon') ?: '/schoolcare-icon.svg';
        $mark = settings()->imageUrl('brand_logo') ?: '/schoolcare-icon.svg';
    } catch (\Throwable $e) {
        $favicon = $mark = '/schoolcare-icon.svg';
    }
    try {
        $styles = app(\Illuminate\Foundation\Vite::class)(['resources/scss/app.scss'])->toHtml();
    } catch (\Throwable $e) {
        $styles = '';
    }
    $signedIn = false;
    try { $signedIn = auth()->check(); } catch (\Throwable $e) {}
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @yield('head')
    <title>@yield('title') | {{ $appName }}</title>
    <link rel="icon" href="{{ $favicon }}">
    {!! $styles !!}
    @php
        try { echo \Illuminate\Support\Facades\Blade::render('<x-ui.brand-style />'); } catch (\Throwable $e) {}
    @endphp
    <style>
        .error-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#F8FAFC;color:#0F172A;font-family:Figtree,"Noto Sans",system-ui,-apple-system,"Segoe UI",sans-serif}
        .error-box{width:100%;max-width:30rem}
        .error-mark{width:40px;height:40px;border-radius:8px;object-fit:contain;display:block;margin-bottom:2rem}
        .error-code{font-size:.8125rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#475569;margin:0 0 .5rem}
        .error-title{font-size:1.75rem;font-weight:700;letter-spacing:-.02em;line-height:1.2;margin:0 0 .75rem}
        .error-message{font-size:1rem;line-height:1.55;color:#475569;margin:0 0 1.75rem}
        .error-actions{display:flex;flex-wrap:wrap;gap:.75rem}
        .error-actions .btn{min-height:2.75rem;display:inline-flex;align-items:center;gap:.5rem}
        .error-hint{margin:1.5rem 0 0;font-size:.875rem;color:#64748B}
        .error-foot{margin:3rem 0 0;font-size:.8125rem;color:#64748B}
    </style>
</head>
<body>
    <main class="error-page">
        <div class="error-box">
            <img src="{{ $mark }}" alt="{{ $appName }}" width="40" height="40" class="error-mark">
            <p class="error-code">Error @yield('code')</p>
            <h1 class="error-title">@yield('title')</h1>
            <p class="error-message">@yield('message')</p>
            <div class="error-actions">
                @hasSection('actions')
                    @yield('actions')
                @else
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>Go back
                    </a>
                    <a href="{{ $signedIn ? url('/dashboard') : url('/login') }}" class="btn btn-primary">
                        {{ $signedIn ? 'Go to dashboard' : 'Sign in' }}
                    </a>
                @endif
            </div>
            @hasSection('hint')
                <p class="error-hint">@yield('hint')</p>
            @endif
            <p class="error-foot">{{ $orgName !== '' ? $orgName : $appName }}</p>
        </div>
    </main>
</body>
</html>
