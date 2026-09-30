{{--
    Active public advisories (hidden by the controller when there are none), as
    white cards with a plain glyph in the notice's tone. Expects: $page, $tinted.
--}}
<section id="advisories" @class(['lp-section', 'lp-section-compact', 'is-tinted' => $tinted]) aria-labelledby="lp-advisories-title">
    <div class="lp-container">
        <header class="lp-section-head lp-reveal">
            <h2 class="lp-h2" id="lp-advisories-title">Advisories</h2>
            <p class="lp-lead">Notices from the clinic. Please read these before you visit.</p>
        </header>
        <ul class="lp-advisories">
            @foreach ($page['advisories'] as $a)
                <li class="lp-advisory lp-reveal tone-{{ $a['tone'] }}">
                    <x-ui.icon :name="$a['icon']" class="lp-advisory-icon" />
                    <div class="min-w-0">
                        <p class="lp-advisory-meta">
                            <span class="lp-advisory-type">{{ $a['type_label'] }}</span>
                            @if ($a['date'])
                                <time datetime="{{ $a['date'] }}">{{ \Carbon\Carbon::parse($a['date'])->format('F j, Y') }}</time>
                            @endif
                        </p>
                        <h3 class="lp-h3">{{ $a['title'] }}</h3>
                        @if ($a['body'])
                            <p class="lp-advisory-body">{{ $a['body'] }}</p>
                        @endif
                        @if ($a['link_url'])
                            <a href="{{ $a['link_url'] }}" class="lp-link" target="_blank" rel="noopener noreferrer">
                                {{ $a['link_label'] ?: 'Read more' }}<x-ui.icon name="box-arrow-up-right" /><span class="visually-hidden"> (opens in a new tab)</span>
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
