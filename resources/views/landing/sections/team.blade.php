{{-- Clinic team (hidden by the controller when empty). Expects: $page, $tinted. --}}
<section id="team" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-team-title">
    <div class="lp-container">
        <div class="lp-section-head">
            <h2 class="lp-h2" id="lp-team-title">Clinic team</h2>
            @if ($page['intros']['team'] !== '')
                <p class="lp-lead">{{ $page['intros']['team'] }}</p>
            @endif
        </div>
        <ul class="lp-team lp-reveal">
            @foreach ($page['team'] as $person)
                <li class="lp-person">
                    @if ($person['image'])
                        <img src="{{ $person['image'] }}" alt="" width="64" height="64" class="lp-person-photo" loading="lazy" decoding="async">
                    @else
                        <x-ui.avatar :name="$person['title']" size="xl" />
                    @endif
                    <div class="min-w-0">
                        <h3 class="lp-h3">{{ $person['title'] }}</h3>
                        @if ($person['subtitle'])
                            <p class="lp-person-role">{{ $person['subtitle'] }}</p>
                        @endif
                    </div>
                    @if ($person['body'])
                        <p class="lp-person-note">{{ $person['body'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
