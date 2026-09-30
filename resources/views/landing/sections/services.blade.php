{{--
    Services: white cards with a plain brand glyph (or a small photo), title and
    line. A card with a link is one big target: it lifts on hover and a soft
    light follows the pointer. Expects: $page, $tinted.
--}}
<section id="services" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-services-title">
    <div class="lp-container">
        <header class="lp-section-head lp-reveal">
            <h2 class="lp-h2" id="lp-services-title">Services</h2>
            @if ($page['intros']['services'] !== '')
                <p class="lp-lead">{{ $page['intros']['services'] }}</p>
            @endif
        </header>
        <ul class="lp-services">
            @foreach ($page['services'] as $s)
                <li @class(['lp-service', 'lp-reveal', 'lp-card-link lp-spot' => $s['link_url']])>
                    @if ($s['image'])
                        <img src="{{ $s['image'] }}" alt="" width="48" height="48" class="lp-service-img" loading="lazy" decoding="async">
                    @else
                        <x-ui.icon :name="$s['icon'] ?: 'plus-square'" class="lp-glyph" />
                    @endif
                    <h3 class="lp-h3">{{ $s['title'] }}</h3>
                    @if ($s['body'])
                        <p>{{ $s['body'] }}</p>
                    @endif
                    @if ($s['link_url'])
                        @php $external = ! str_starts_with($s['link_url'], url('/')); @endphp
                        <a href="{{ $s['link_url'] }}" class="lp-link lp-stretch" @if ($external) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $s['link_label'] ?: 'Learn more' }}<span class="visually-hidden"> about {{ $s['title'] }}{{ $external ? ' (opens in a new tab)' : '' }}</span><x-ui.icon name="chevron-right" />
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
