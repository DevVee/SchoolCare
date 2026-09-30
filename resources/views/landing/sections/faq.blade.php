{{--
    Common questions: one white card of accordion buttons (aria-expanded /
    aria-controls via Bootstrap collapse). Several answers can be open at once.
    Expects: $page, $tinted.
--}}
@php $c = $page['contact']; @endphp
<section id="faq" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-faq-title">
    <div class="lp-container">
        <header class="lp-section-head lp-reveal">
            <h2 class="lp-h2" id="lp-faq-title">Common questions</h2>
            @if ($page['intros']['faq'] !== '')
                <p class="lp-lead">{{ $page['intros']['faq'] }}</p>
            @endif
        </header>

        <div class="lp-faq lp-card lp-reveal">
            @foreach ($page['faqs'] as $faq)
                <div class="lp-faq-item">
                    <h3 class="lp-faq-q">
                        <button class="lp-faq-btn collapsed" type="button" id="faq-q-{{ $faq['id'] }}"
                                data-bs-toggle="collapse" data-bs-target="#faq-a-{{ $faq['id'] }}"
                                aria-expanded="false" aria-controls="faq-a-{{ $faq['id'] }}">
                            <span>{{ $faq['title'] }}</span>
                            <x-ui.icon name="chevron-down" />
                        </button>
                    </h3>
                    <div id="faq-a-{{ $faq['id'] }}" class="collapse" role="region" aria-labelledby="faq-q-{{ $faq['id'] }}">
                        <p class="lp-faq-a">{{ $faq['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($c['phone'] !== '' || $c['email'] !== '')
            <p class="lp-faq-help lp-reveal">
                Still have a question?
                @if ($c['phone'] !== '')
                    Call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['phone']) }}">{{ $c['phone'] }}</a>@if ($c['email'] !== '') or email <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>@endif.
                @else
                    Email <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>.
                @endif
            </p>
        @endif
    </div>
</section>
