{{--
    Site footer: brand and one line about it, product links, the clinic's pages
    and online services, the clinic's contact details, then copyright, privacy
    notice and the optional credit line.
    Expects: $page. Optional: $clinicBrand (bool), $about (text under the brand;
    defaults to the clinic's footer text from Administration > Website).
--}}
@php
    $c = $page['contact'];
    $clinicBrand ??= false;
    $appName = \App\Support\ProductSite::appName();
    $about ??= $page['footer']['about'];
    $clinic = route('clinic');
@endphp
<footer class="lp-footer">
    <div class="lp-container">
        <div class="lp-footer-grid">
            <div>
                <a href="{{ $clinicBrand ? $clinic : route('home') }}" class="lp-brand">
                    <img src="{{ $page['logo'] }}" alt="" width="36" height="36" class="lp-brand-logo" loading="lazy" decoding="async">
                    <span class="lp-brand-text">
                        <span class="lp-brand-name">{{ $clinicBrand ? $page['names']['clinic'] : $appName }}</span>
                        @if ($clinicBrand && $page['names']['school'] !== '')
                            <span class="lp-brand-sub">{{ $page['names']['school'] }}</span>
                        @endif
                    </span>
                </a>
                @if ($about !== '')
                    <p class="lp-footer-about">{{ $about }}</p>
                @endif
            </div>

            <nav aria-labelledby="lpFooterProduct">
                <h2 class="lp-footer-title" id="lpFooterProduct">{{ $appName }}</h2>
                <ul class="lp-footer-links">
                    <li><a href="{{ route('home') }}#features">Features</a></li>
                    <li><a href="{{ route('home') }}#how">How it works</a></li>
                    <li><a href="{{ route('login') }}">Staff sign in</a></li>
                </ul>
            </nav>

            <nav aria-labelledby="lpFooterClinic">
                <h2 class="lp-footer-title" id="lpFooterClinic">Clinic</h2>
                <ul class="lp-footer-links">
                    <li><a href="{{ $clinic }}">Clinic page</a></li>
                    @if (in_array('schedule', $page['sections'], true))
                        <li><a href="{{ $clinic }}#schedule">Clinic hours</a></li>
                    @endif
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
                </ul>
            </nav>

            @if ($c['phone'] !== '' || $c['email'] !== '' || $c['hotline'] !== '' || $page['social'])
                <div>
                    <h2 class="lp-footer-title">Contact the clinic</h2>
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
