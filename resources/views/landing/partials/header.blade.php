{{--
    Utility bar (open status + emergency number) and the sticky, translucent header
    with section links, "Staff sign in" and "Request appointment". On phones the
    links move into a bottom sheet.
    Expects: $page, $base ('' on the landing page, the home URL elsewhere).
--}}
@php
    $call = $page['contact']['call'];
    $callLabel = $page['contact']['hotline'] !== '' || $call !== $page['contact']['phone'] ? 'Emergency' : 'Clinic phone';
    $tel = preg_replace('/[^0-9+]/', '', $call);
    $home = $base === '' ? '#top' : $base;
@endphp

<div class="lp-utility">
    <div class="lp-container lp-utility-inner">
        <p @class(['lp-status', 'is-open' => $page['status']['open']])>
            <span class="lp-status-dot" aria-hidden="true"></span>
            <span><span class="visually-hidden">Clinic status: </span>{{ $page['status']['label'] }}</span>
        </p>
        @if ($call !== '')
            <a class="lp-utility-call" href="tel:{{ $tel }}">
                <x-ui.icon name="telephone" />
                <span class="d-none d-sm-inline">{{ $callLabel }}:</span>
                <span class="visually-hidden d-sm-none">{{ $callLabel }}:</span>
                <strong>{{ $call }}</strong>
            </a>
        @endif
    </div>
</div>

<header class="lp-header" data-lp-header>
    <div class="lp-container lp-header-inner">
        <a href="{{ $home }}" class="lp-brand">
            <img src="{{ $page['logo'] }}" alt="" width="40" height="40" class="lp-brand-logo">
            <span class="lp-brand-text">
                <span class="lp-brand-name">{{ $page['names']['clinic'] }}</span>
                @if ($page['names']['school'] !== '')
                    <span class="lp-brand-sub">{{ $page['names']['school'] }}</span>
                @endif
            </span>
        </a>

        @if ($page['nav'])
            <nav class="lp-nav" aria-label="On this page" data-lp-nav>
                @foreach ($page['nav'] as $key => $label)
                    <a href="{{ $base }}#{{ $key }}" class="lp-nav-link">{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        <div class="lp-header-actions">
            <a href="{{ route('login') }}" class="lp-signin">Staff sign in</a>
            @if ($page['links']['request'])
                <x-ui.button :href="$page['links']['request']" class="lp-btn lp-header-cta">Request appointment</x-ui.button>
            @endif
            <button type="button" class="lp-menu-btn" data-bs-toggle="offcanvas" data-bs-target="#lpMenu" aria-controls="lpMenu" aria-label="Open menu">
                <x-ui.icon name="list" />
            </button>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-bottom lp-sheet" tabindex="-1" id="lpMenu" aria-labelledby="lpMenuTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title" id="lpMenuTitle">Menu</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
    </div>
    <div class="offcanvas-body">
        @if ($page['nav'])
            <nav class="lp-sheet-nav" aria-label="On this page">
                @foreach ($page['nav'] as $key => $label)
                    <a href="{{ $base }}#{{ $key }}" class="lp-sheet-link">{{ $label }}<x-ui.icon name="chevron-right" /></a>
                @endforeach
            </nav>
        @endif
        <div class="lp-sheet-actions">
            @if ($page['links']['request'])
                <x-ui.button :href="$page['links']['request']" class="lp-btn lp-btn-lg" block>Request appointment</x-ui.button>
            @endif
            @if ($page['links']['health_form'])
                <x-ui.button :href="$page['links']['health_form']" variant="secondary" class="lp-btn lp-btn-lg" block>Online health form</x-ui.button>
            @endif
            <x-ui.button :href="route('login')" variant="secondary" class="lp-btn lp-btn-lg" block>Staff sign in</x-ui.button>
        </div>
    </div>
</div>
