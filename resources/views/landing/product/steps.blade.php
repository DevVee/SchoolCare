{{-- How it works: three numbered steps. Expects: $product. --}}
<section id="how" class="lp-section" aria-labelledby="lp-how-title">
    <div class="lp-container">
        <header class="lp-section-head lp-reveal">
            <p class="lp-eyebrow">{{ $product['steps_eyebrow'] }}</p>
            <h2 class="lp-h2" id="lp-how-title">{{ $product['steps_title'] }}</h2>
        </header>
        <ol class="lp-steps">
            @foreach ($product['steps'] as $i => $step)
                <li class="lp-step lp-reveal">
                    <span class="lp-step-num" aria-hidden="true">{{ $i + 1 }}</span>
                    <div class="lp-step-text">
                        <h3 class="lp-h3"><span class="visually-hidden">Step {{ $i + 1 }}: </span>{{ $step['title'] }}</h3>
                        <p>{{ $step['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
