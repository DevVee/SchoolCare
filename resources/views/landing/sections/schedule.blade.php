{{--
    Clinic hours for the week (today marked) and the next doctor / dentist visit
    days (type, date and time only). Expects: $page, $tinted.
--}}
<section id="schedule" @class(['lp-section', 'is-tinted' => $tinted]) aria-labelledby="lp-schedule-title">
    <div class="lp-container lp-split">
        <div class="lp-split-aside">
            <h2 class="lp-h2" id="lp-schedule-title">Clinic hours</h2>
            @if ($page['intros']['schedule'] !== '')
                <p class="lp-lead">{{ $page['intros']['schedule'] }}</p>
            @endif
            <p @class(['lp-now', 'lp-open-tag', 'is-open' => $page['status']['open'], 'is-closed' => ! $page['status']['open']])>{{ $page['status']['label'] }}</p>
            @if ($page['links']['request'] || $page['links']['schedule'])
                <div class="lp-aside-links">
                    @if ($page['links']['request'])
                        <a href="{{ $page['links']['request'] }}" class="lp-link">Request an appointment <x-ui.icon name="arrow-right" /></a>
                    @endif
                    @if ($page['links']['schedule'])
                        <a href="{{ $page['links']['schedule'] }}" class="lp-link">See open appointment times <x-ui.icon name="arrow-right" /></a>
                    @endif
                </div>
            @endif
        </div>

        <div class="vstack gap-4 lp-reveal">
            <div class="lp-card">
                <table class="lp-hours">
                    <caption class="visually-hidden">Clinic hours for each day of the week</caption>
                    <tbody>
                        @foreach ($page['week'] as $row)
                            <tr @class(['is-today' => $row['today'], 'is-closed' => ! $row['hours']])>
                                <th scope="row">
                                    {{ $row['label'] }}
                                    @if ($row['today'])<span class="lp-today-mark">Today</span>@endif
                                </th>
                                <td>{{ $row['text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($page['visits'])
                <div class="lp-card">
                    <div class="lp-card-head">
                        <h3 class="lp-h3">Doctor and dentist visit days</h3>
                    </div>
                    <ul class="lp-visits">
                        @foreach ($page['visits'] as $v)
                            <li class="lp-visit">
                                <span class="lp-visit-type">{{ $v['type'] }}</span>
                                <span class="lp-visit-time">{{ $v['time'] }}</span>
                                <time class="lp-visit-date" datetime="{{ $v['date_iso'] }}">{{ $v['date'] }}</time>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</section>
