{{-- Closing call to action: sign in, or go to the clinic page. Expects: $product. --}}
<section class="lp-section lp-section-cta" aria-labelledby="lp-cta-title">
    <div class="lp-container">
        <div class="lp-cta lp-reveal">
            <div class="lp-cta-copy">
                <h2 class="lp-cta-title" id="lp-cta-title">{{ $product['cta']['title'] }}</h2>
                <p>{{ $product['cta']['body'] }}</p>
            </div>
            <div class="lp-cta-actions">
                <a href="{{ route('login') }}" class="btn btn-lg lp-btn lp-btn-lg lp-btn-light">{{ $product['cta']['primary'] }}</a>
                <a href="{{ route('clinic') }}" class="btn btn-lg lp-btn lp-btn-lg lp-btn-ghost-light">{{ $product['cta']['secondary'] }}<x-ui.icon name="chevron-right" /></a>
            </div>
        </div>
    </div>
</section>
