{{--
    Public website footer: clinic + about, links to page sections, online services,
    contact, then copyright, privacy notice and the optional credit line.
    Expects: $page, $base.
--}}
@php
    $c = $page['contact'];
@endphp
<footer class="lp-footer">
    <div class="lp-container">
        <div class="lp-footer-grid">
            <div>
                <a href="{{ $base === '' ? '#top' : $base }}" class="lp-brand">
                    <img src="{{ $page['logo'] }}" alt="" width="40" height="40" class="lp-brand-logo" loading="lazy" decoding="async">
                    <span class="lp-brand-text">
                        <span class="lp-brand-name">{{ $page['names']['clinic'] }}</span>
                        @if ($page['names']['school'] !== '')
                            <span class="lp-brand-sub">{{ $page['names']['school'] }}</span>
                        @endif
                    </span>
                </a>
                @if ($page['footer']['about'] !== '')
                    <p class="lp-footer-about">{{ $page['footer']['about'] }}</p>
                @endif
            </div>

            @if ($page['nav'])
                <nav aria-labelledby="lpFooterPage">
                    <h2 class="lp-footer-title" id="lpFooterPage">On this page</h2>
                    <ul class="lp-footer-links">
                        @foreach ($page['nav'] as $key => $label)
                            <li><a href="{{ $base }}#{{ $key }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <nav aria-labelledby="lpFooterOnline">
                <h2 class="lp-footer-title" id="lpFooterOnline">Online services</h2>
                <ul class="lp-footer-links">
                    @if ($page['links']['request'])
                        <li><a href="{{ $page['links']['request'] }}">Request an appointment</a></li>
                    @endif
                    @if ($page['links']['schedule'])
                        <li><a href="{{ $page['links']['schedule'] }}">Clinic schedule board</a></li>
                    @endif
                    @if ($page['links']['health_form'])
                        <li><a href="{{ $page['links']['health_form'] }}">Online health form</a></li>
                    @endif
                    <li><a href="{{ route('privacy') }}">Privacy notice</a></li>
                    <li><a href="{{ route('login') }}">Staff sign in</a></li>
                </ul>
            </nav>

            @if ($c['phone'] !== '' || $c['email'] !== '' || $c['hotline'] !== '' || $page['social'])
                <div>
                    <h2 class="lp-footer-title">Contact</h2>
                    <ul class="lp-footer-links">
                        @if ($c['phone'] !== '')
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['phone']) }}"><x-ui.icon name="telephone" />{{ $c['phone'] }}</a></li>
                        @endif
                        @if ($c['hotline'] !== '')
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['hotline']) }}"><x-ui.icon name="exclamation-circle" />Emergency: {{ $c['hotline'] }}</a></li>
                        @endif
                        @if ($c['email'] !== '')
                            <li><a href="mailto:{{ $c['email'] }}"><x-ui.icon name="envelope" />{{ $c['email'] }}</a></li>
                        @endif
                        @foreach ($page['social'] as $s)
                            <li><a href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer"><x-ui.icon :name="$s['icon']" />{{ $s['label'] }}<span class="visually-hidden"> (opens in a new tab)</span></a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="lp-footer-bottom">
            <p>{{ $page['footer']['copyright'] }}</p>
            <p class="lp-footer-legal">
                <a href="{{ route('privacy') }}">Privacy notice</a>
                @if ($page['footer']['credit'] !== '')
                    <span class="lp-footer-credit">{{ $page['footer']['credit'] }}</span>
                @endif
            </p>
        </div>
    </div>
</footer>
