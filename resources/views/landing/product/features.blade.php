{{--
    Features: a bento grid. Each card names one part of the app and shows a
    small drawn screen of it (landing.product.feature-demo), which moves a
    little when the card scrolls into view. Expects: $product.
--}}
<section id="features" class="lp-section is-tinted" aria-labelledby="lp-features-title">
    <div class="lp-container">
        <header class="lp-section-head lp-reveal">
            <p class="lp-eyebrow">{{ $product['features_eyebrow'] }}</p>
            <h2 class="lp-h2" id="lp-features-title">{{ $product['features_title'] }}</h2>
            <p class="lp-lead">{{ $product['features_lead'] }}</p>
        </header>

        <ul class="lp-bento">
            @foreach ($product['features'] as $f)
                <li class="lp-bento-card lp-bento-{{ $f['key'] }} lp-reveal lp-live">
                    <div class="lp-bento-text">
                        <x-ui.icon :name="$f['icon']" class="lp-glyph" />
                        <h3 class="lp-h3">{{ $f['title'] }}</h3>
                        <p>{{ $f['body'] }}</p>
                    </div>
                    <div class="lp-demo" aria-hidden="true">
                        @include('landing.product.feature-demo', ['f' => $f])
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
