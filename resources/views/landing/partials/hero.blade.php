{{--
    Hero: clinic name, one line about who it serves, a short description and two
    buttons. Right side: the clinic's own photo, or a "Today at the clinic" card
    with today's hours and how to reach the clinic. Expects: $page.
--}}
@php
    $hero = $page['hero'];
    $c = $page['contact'];
    $todayRow = collect($page['week'])->firstWhere('today', true);
    $todayHours = $todayRow && $todayRow['hours'] ? $todayRow['text'] : 'Closed today';
@endphp
<section class="lp-hero" id="top" aria-labelledby="lp-hero-title">
    <div class="lp-container lp-hero-grid">
        <div>
            <h1 class="lp-hero-title" id="lp-hero-title">{{ $hero['heading'] }}</h1>
            @if ($hero['subheading'] !== '')
                <p class="lp-hero-sub">{{ $hero['subheading'] }}</p>
            @endif
            @if ($hero['description'] !== '')
                <p class="lp-hero-desc">{{ $hero['description'] }}</p>
            @endif

            <div class="lp-hero-actions">
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
                <p class="lp-hero-note">
                    <x-ui.icon name="clipboard2-heart" />
                    <span>New student, or has something changed in your health? <a href="{{ $page['links']['health_form'] }}">Send the online health information form</a>.</span>
                </p>
            @endif
        </div>

        @if ($hero['image'])
            <div>
                <figure class="lp-hero-media">
                    <img src="{{ $hero['image']['url'] }}" alt="{{ $hero['image']['alt'] }}" class="lp-hero-photo"
                         width="{{ $hero['image']['width'] }}" height="{{ $hero['image']['height'] }}"
                         fetchpriority="high" decoding="async">
                </figure>
                <p class="lp-hero-today">
                    <span><x-ui.icon name="clock" />Today: {{ $todayHours }}</span>
                    <span @class(['lp-open-tag', 'is-open' => $page['status']['open'], 'is-closed' => ! $page['status']['open']])>{{ $page['status']['label'] }}</span>
                </p>
            </div>
        @else
            <aside class="lp-today" aria-labelledby="lp-today-title">
                <div class="lp-today-head">
                    <h2 class="lp-today-title" id="lp-today-title">Today at the clinic</h2>
                    <p class="lp-today-date"><time datetime="{{ $page['today']->toDateString() }}">{{ $page['today']->format('l, F j') }}</time></p>
                </div>
                <dl class="lp-today-list">
                    <div class="lp-today-row">
                        <x-ui.icon name="clock" />
                        <div>
                            <dt>Hours today</dt>
                            <dd>{{ $todayHours }}</dd>
                        </div>
                    </div>
                    <div class="lp-today-row">
                        <x-ui.icon name="door-open" />
                        <div>
                            <dt>Right now</dt>
                            <dd><span @class(['lp-open-tag', 'is-open' => $page['status']['open'], 'is-closed' => ! $page['status']['open']])>{{ $page['status']['label'] }}</span></dd>
                        </div>
                    </div>
                    @if ($hero['nurse'] !== '')
                        <div class="lp-today-row">
                            <x-ui.icon name="person-badge" />
                            <div>
                                <dt>Nurse on duty</dt>
                                <dd>{{ $hero['nurse'] }}</dd>
                            </div>
                        </div>
                    @endif
                    @if ($c['call'] !== '')
                        <div class="lp-today-row">
                            <x-ui.icon name="telephone" />
                            <div>
                                <dt>{{ $c['hotline'] !== '' ? 'Emergency number' : 'Clinic phone' }}</dt>
                                <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['call']) }}">{{ $c['call'] }}</a></dd>
                            </div>
                        </div>
                    @endif
                    @if ($c['location'] !== '' || $c['address'] !== '')
                        <div class="lp-today-row">
                            <x-ui.icon name="geo-alt" />
                            <div>
                                <dt>Where to find us</dt>
                                <dd>{{ $c['location'] !== '' ? $c['location'] : $c['address'] }}</dd>
                            </div>
                        </div>
                    @endif
                </dl>
                @if (in_array('schedule', $page['sections'], true))
                    <div class="lp-today-foot">
                        <a href="#schedule" class="lp-link">See the hours for the week <x-ui.icon name="arrow-right" /></a>
                    </div>
                @endif
            </aside>
        @endif
    </div>
</section>
