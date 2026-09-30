{{-- How to get care: the clinic's numbered steps (Administration > Website). Expects: $page, $tinted. --}}
<section id="steps" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-steps-title">
    <div class="lp-container">
        <header class="lp-section-head lp-reveal">
            <h2 class="lp-h2" id="lp-steps-title">How to get care</h2>
        </header>
        <ol class="lp-steps">
            @foreach ($page['steps'] as $i => $step)
                <li class="lp-step lp-reveal">
                    <span class="lp-step-num" aria-hidden="true">{{ $i + 1 }}</span>
                    <div class="lp-step-text">
                        @if ($step['title'] !== '')
                            <h3 class="lp-h3"><span class="visually-hidden">Step {{ $i + 1 }}: </span>{{ $step['title'] }}</h3>
                        @endif
                        @if ($step['body'] !== '')
                            <p>{{ $step['body'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
