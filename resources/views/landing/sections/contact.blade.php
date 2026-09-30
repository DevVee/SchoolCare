{{--
    Contact: address, where the clinic is on campus, phone, emergency number, email,
    hours, map link (new tab, no embedded map) and filled social links only.
    Expects: $page, $tinted.
--}}
@php $c = $page['contact']; @endphp
<section id="contact" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-contact-title">
    <div class="lp-container lp-split">
        <div class="lp-split-aside">
            <h2 class="lp-h2" id="lp-contact-title">Contact the clinic</h2>
            @if ($page['intros']['contact'] !== '')
                <p class="lp-lead">{{ $page['intros']['contact'] }}</p>
            @endif
            @if ($c['map_url'] !== '' || $page['links']['request'])
                <div class="lp-contact-actions">
                    @if ($page['links']['request'])
                        <x-ui.button :href="$page['links']['request']" class="lp-btn">Request an appointment</x-ui.button>
                    @endif
                    @if ($c['map_url'] !== '')
                        <x-ui.button :href="$c['map_url']" variant="secondary" icon="map" class="lp-btn" target="_blank" rel="noopener noreferrer">
                            Open in maps<span class="visually-hidden"> (opens in a new tab)</span>
                        </x-ui.button>
                    @endif
                </div>
            @endif
        </div>

        <div class="lp-reveal">
            <dl class="lp-contact">
                @if ($c['address'] !== '')
                    <div class="lp-contact-row">
                        <x-ui.icon name="geo-alt" />
                        <div><dt>Address</dt><dd>{{ $c['address'] }}</dd></div>
                    </div>
                @endif
                @if ($c['location'] !== '')
                    <div class="lp-contact-row">
                        <x-ui.icon name="signpost" />
                        <div><dt>Where the clinic is</dt><dd>{{ $c['location'] }}</dd></div>
                    </div>
                @endif
                @if ($c['phone'] !== '')
                    <div class="lp-contact-row">
                        <x-ui.icon name="telephone" />
                        <div><dt>Clinic phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['phone']) }}">{{ $c['phone'] }}</a></dd></div>
                    </div>
                @endif
                @if ($c['hotline'] !== '')
                    <div class="lp-contact-row">
                        <x-ui.icon name="exclamation-circle" />
                        <div><dt>Emergency</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['hotline']) }}">{{ $c['hotline'] }}</a></dd></div>
                    </div>
                @endif
                @if ($c['email'] !== '')
                    <div class="lp-contact-row">
                        <x-ui.icon name="envelope" />
                        <div><dt>Email</dt><dd><a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></dd></div>
                    </div>
                @endif
                <div class="lp-contact-row">
                    <x-ui.icon name="clock" />
                    <div>
                        <dt>Hours</dt>
                        <dd>
                            <ul class="lp-hours-summary">
                                @foreach ($page['hours'] as $h)
                                    <li>{{ $h['days'] }}: {{ $h['text'] }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                </div>
            </dl>

            @if ($page['social'])
                <div class="lp-social">
                    @foreach ($page['social'] as $s)
                        <a href="{{ $s['url'] }}" class="lp-social-link" target="_blank" rel="noopener noreferrer">
                            <x-ui.icon :name="$s['icon']" />{{ $s['label'] }}<span class="visually-hidden"> (opens in a new tab)</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
