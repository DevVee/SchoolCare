{{--
    Hero, centered: a big headline whose words rise out of a mask (landing.js),
    the line about who the clinic serves, a short description, capsule buttons
    and the health form note, over a faint grid. Below, on a soft stage that
    settles flat as it scrolls in: the clinic's own photo with today's hours, or
    a "Today at the clinic" panel. All text comes from Administration > Website.
    Expects: $page.
--}}
@php
    $hero = $page['hero'];
    $c = $page['contact'];
    $open = $page['status']['open'];
    $todayRow = collect($page['week'])->firstWhere('today', true);
    $todayHours = $todayRow && $todayRow['hours'] ? $todayRow['text'] : 'Closed today';
@endphp
<section class="lp-hero" id="top" aria-labelledby="lp-hero-title">
    <span class="lp-grid" aria-hidden="true"></span>
    <div class="lp-container">
        <div class="lp-hero-head">
            <h1 class="lp-hero-title" id="lp-hero-title" data-lp-words>{{ $hero['heading'] }}</h1>
            @if ($hero['subheading'] !== '')
                <p class="lp-hero-sub lp-rise lp-rise-1">{{ $hero['subheading'] }}</p>
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

        <div class="lp-stage lp-rise lp-rise-5">
            <div class="lp-stage-inner">
                @if ($hero['image'])
                    <figure class="lp-hero-media">
                        <img src="{{ $hero['image']['url'] }}" alt="{{ $hero['image']['alt'] }}" class="lp-hero-photo"
                             width="{{ $hero['image']['width'] }}" height="{{ $hero['image']['height'] }}"
                             fetchpriority="high" decoding="async">
                    </figure>
                    <p class="lp-hero-today">
                        <span class="lp-hero-today-hours"><x-ui.icon name="clock" />Today: {{ $todayHours }}</span>
                        <span @class(['lp-status-tag', 'is-open' => $open])><span class="lp-dot" aria-hidden="true"></span>{{ $page['status']['label'] }}</span>
                    </p>
                @else
                    <aside class="lp-today" aria-labelledby="lp-today-title">
                        <div class="lp-today-head">
                            <h2 class="lp-today-title" id="lp-today-title">Today at the clinic</h2>
                            <p class="lp-today-date"><time datetime="{{ $page['today']->toDateString() }}">{{ $page['today']->format('l, F j') }}</time></p>
                        </div>
                        <dl class="lp-today-list">
                            <div class="lp-today-cell">
                                <dt><x-ui.icon name="clock" />Hours today</dt>
                                <dd>{{ $todayHours }}</dd>
                            </div>
                            <div class="lp-today-cell">
                                <dt><x-ui.icon name="door-open" />Right now</dt>
                                <dd><span @class(['lp-status-tag', 'is-open' => $open])><span class="lp-dot" aria-hidden="true"></span>{{ $page['status']['label'] }}</span></dd>
                            </div>
                            @if ($hero['nurse'] !== '')
                                <div class="lp-today-cell">
                                    <dt><x-ui.icon name="person-badge" />Nurse on duty</dt>
                                    <dd>{{ $hero['nurse'] }}</dd>
                                </div>
                            @endif
                            @if ($c['call'] !== '')
                                <div class="lp-today-cell">
                                    <dt><x-ui.icon name="telephone" />{{ $c['hotline'] !== '' ? 'Emergency number' : 'Clinic phone' }}</dt>
                                    <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['call']) }}">{{ $c['call'] }}</a></dd>
                                </div>
                            @endif
                            @if ($c['location'] !== '' || $c['address'] !== '')
                                <div class="lp-today-cell">
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
                @endif
            </div>
        </div>
    </div>
</section>
