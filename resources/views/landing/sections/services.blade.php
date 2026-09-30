{{-- Services: a two-column list with the icon (or a small photo) at the left. Expects: $page, $tinted. --}}
<section id="services" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-services-title">
    <div class="lp-container">
        <div class="lp-section-head">
            <h2 class="lp-h2" id="lp-services-title">Services</h2>
            @if ($page['intros']['services'] !== '')
                <p class="lp-lead">{{ $page['intros']['services'] }}</p>
            @endif
        </div>
        <ul class="lp-services lp-reveal">
            @foreach ($page['services'] as $s)
                <li class="lp-service">
                    @if ($s['image'])
                        <img src="{{ $s['image'] }}" alt="" width="40" height="40" class="lp-service-img" loading="lazy" decoding="async">
                    @else
                        <span class="lp-service-icon"><x-ui.icon :name="$s['icon'] ?: 'plus-square'" /></span>
                    @endif
                    <div class="min-w-0">
                        <h3 class="lp-h3">{{ $s['title'] }}</h3>
                        @if ($s['body'])
                            <p>{{ $s['body'] }}</p>
                        @endif
                        @if ($s['link_url'])
                            @php $external = ! str_starts_with($s['link_url'], url('/')); @endphp
                            <a href="{{ $s['link_url'] }}" class="lp-link" @if ($external) target="_blank" rel="noopener noreferrer" @endif>
                                {{ $s['link_label'] ?: 'Learn more' }}<span class="visually-hidden"> about {{ $s['title'] }}{{ $external ? ' (opens in a new tab)' : '' }}</span>
                                <x-ui.icon name="arrow-right" />
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
