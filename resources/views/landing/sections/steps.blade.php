{{-- How to get care: three numbered steps in a row. Expects: $page, $tinted. --}}
<section id="steps" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-steps-title">
    <div class="lp-container">
        <div class="lp-section-head">
            <h2 class="lp-h2" id="lp-steps-title">How to get care</h2>
        </div>
        <ol class="lp-steps lp-reveal">
            @foreach ($page['steps'] as $i => $step)
                <li class="lp-step">
                    <span class="lp-step-num" aria-hidden="true">{{ $i + 1 }}</span>
                    @if ($step['title'] !== '')
                        <h3 class="lp-h3"><span class="visually-hidden">Step {{ $i + 1 }}: </span>{{ $step['title'] }}</h3>
                    @endif
                    @if ($step['body'] !== '')
                        <p>{{ $step['body'] }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
