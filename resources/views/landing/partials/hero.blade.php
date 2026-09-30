{{--
    Clinic page hero: the clinic's name, who it serves, a short description, the
    two buttons and the health form note on the left; on the right, "Today at
    the clinic" with a live open (green) or closed (grey) indicator, today's
    hours, the nurse on duty, the number to call and where to find the clinic,
    under the clinic's own photo when there is one. All text comes from
    Administration > Website. Expects: $page.
--}}
@php
    $hero = $page['hero'];
    $c = $page['contact'];
    $open = $page['status']['open'];
    $todayRow = collect($page['week'])->firstWhere('today', true);
    $todayHours = $todayRow && $todayRow['hours'] ? $todayRow['text'] : 'Closed today';
    $eyebrow = $page['names']['school'] !== '' ? $page['names']['school'] : 'School clinic';
@endphp
<section class="lp-hero lp-hero-clinic" id="top" aria-labelledby="lp-hero-title">
    <div class="lp-container lp-hero-grid">
        <div class="lp-hero-copy">
            <p class="lp-eyebrow lp-rise">{{ $eyebrow }}</p>
            <h1 class="lp-hero-title lp-rise lp-rise-1" id="lp-hero-title">{{ $hero['heading'] }}</h1>
            @if ($hero['subheading'] !== '')
                <p class="lp-hero-lede lp-rise lp-rise-2">{{ $hero['subheading'] }}</p>
            @endif
            @if ($hero['description'] !== '')
                <p class="lp-hero-desc lp-rise lp-rise-2">{{ $hero['description'] }}</p>
            @endif

            <div class="lp-hero-actions lp-rise lp-rise-3">
                <x-ui.button :href="$hero['primary']['href']" size="lg" class="lp-btn lp-btn-lg"
                    :target="$hero['primary']['external'] ? '_blank' : null" :rel="$hero['primary']['external'] ? 'noopener noreferrer' : null">
                    {{ $hero['primary']['label'] }}
                </x-ui.button>
                @if ($hero['secondary'])
                    <x-ui.button :href="$hero['secondary']['href']" variant="secondary" size="lg" class="lp-btn lp-btn-lg"
                        :target="$hero['secondary']['external'] ? '_blank' : null" :rel="$hero['secondary']['external'] ? 'noopener noreferrer' : null">
                        {{ $hero['secondary']['label'] }}
                    </x-ui.button>
                @endif
            </div>

            @if ($hero['health_form'])
                <p class="lp-hero-note lp-rise lp-rise-4">
                    <x-ui.icon name="clipboard2-heart" />
                    <span>New student, or has something changed in your health? <a href="{{ $page['links']['health_form'] }}">Send the online health information form</a>.</span>
                </p>
            @endif
        </div>

        <div @class(['lp-hero-visual', 'lp-rise', 'lp-rise-2', 'has-photo' => $hero['image']])>
            @if ($hero['image'])
                <figure class="lp-hero-media">
                    <img src="{{ $hero['image']['url'] }}" alt="{{ $hero['image']['alt'] }}" class="lp-hero-photo"
                         width="{{ $hero['image']['width'] }}" height="{{ $hero['image']['height'] }}"
                         fetchpriority="high" decoding="async">
                </figure>
            @endif

            <aside class="lp-today" aria-labelledby="lp-today-title">
                <div class="lp-today-head">
                    <h2 class="lp-today-title" id="lp-today-title">Today at the clinic</h2>
                    <p class="lp-today-date"><time datetime="{{ $page['today']->toDateString() }}">{{ $page['today']->format('l, F j') }}</time></p>
                </div>
                <p @class(['lp-today-status', 'lp-status-tag', 'is-open' => $open])><span class="lp-dot" aria-hidden="true"></span>{{ $page['status']['label'] }}</p>
                <dl class="lp-today-list">
                    <div class="lp-today-row">
                        <dt><x-ui.icon name="clock" />Hours today</dt>
                        <dd>{{ $todayHours }}</dd>
                    </div>
                    @if ($hero['nurse'] !== '')
                        <div class="lp-today-row">
                            <dt><x-ui.icon name="person-badge" />Nurse on duty</dt>
                            <dd>{{ $hero['nurse'] }}</dd>
                        </div>
                    @endif
                    @if ($c['call'] !== '')
                        <div class="lp-today-row">
                            <dt><x-ui.icon name="telephone" />{{ $c['hotline'] !== '' ? 'Emergency number' : 'Clinic phone' }}</dt>
                            <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['call']) }}">{{ $c['call'] }}</a></dd>
                        </div>
                    @endif
                    @if ($c['location'] !== '' || $c['address'] !== '')
                        <div class="lp-today-row">
                            <dt><x-ui.icon name="geo-alt" />Where to find us</dt>
                            <dd>{{ $c['location'] !== '' ? $c['location'] : $c['address'] }}</dd>
                        </div>
                    @endif
                </dl>
                @if (in_array('schedule', $page['sections'], true))
                    <div class="lp-today-foot">
                        <a href="#schedule" class="lp-link">See the hours for the week<x-ui.icon name="chevron-right" /></a>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</section>
