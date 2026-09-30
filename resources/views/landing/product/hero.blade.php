{{--
    Product hero: left, a pill with the clinic's live status that opens the
    clinic page, the headline, one line, "Sign in" and "Visit the clinic page";
    right, a drawn preview of the dashboard. Expects: $page, $product.
--}}
@php
    $hero = $product['hero'];
    $open = $page['status']['open'];
@endphp
<section class="lp-hero lp-hero-product" aria-labelledby="lp-hero-title">
    <div class="lp-container lp-hero-grid">
        <div class="lp-hero-copy">
            <a href="{{ route('clinic') }}" @class(['lp-pill', 'lp-rise', 'is-open' => $open])>
                <span class="lp-dot" aria-hidden="true"></span>
                <span class="lp-pill-text"><span class="lp-pill-name">{{ $page['names']['clinic'] }}: </span>{{ $page['status']['label'] }}</span>
                <span class="lp-pill-link">Clinic page<x-ui.icon name="chevron-right" /></span>
            </a>
            <p class="lp-eyebrow lp-rise lp-rise-1">{{ $hero['eyebrow'] }}</p>
            <h1 class="lp-hero-title lp-rise lp-rise-1" id="lp-hero-title">{{ $hero['title'] }}</h1>
            <p class="lp-hero-lede lp-rise lp-rise-2">{{ $hero['lede'] }}</p>
            <div class="lp-hero-actions lp-rise lp-rise-3">
                <x-ui.button :href="route('login')" size="lg" class="lp-btn lp-btn-lg">{{ $hero['primary'] }}</x-ui.button>
                <x-ui.button :href="route('clinic')" variant="secondary" size="lg" class="lp-btn lp-btn-lg">{{ $hero['secondary'] }}</x-ui.button>
            </div>
            <p class="lp-hero-note lp-rise lp-rise-4">{{ $hero['note'] }}</p>
        </div>

        <div class="lp-hero-visual lp-rise lp-rise-2">
            @include('landing.product.preview', ['product' => $product])
        </div>
    </div>
</section>
