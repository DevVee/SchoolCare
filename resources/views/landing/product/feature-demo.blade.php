{{--
    The small drawn screen inside each feature card (decorative: the card's own
    title and text say what it is). Items carry --i so they can arrive one after
    another once the card is in view (.lp-live.is-live, landing.js).
    Expects: $f (one entry of ProductSite::content()['features']).
--}}
@php $d = $f['demo']; @endphp
@switch($f['key'])
    @case('logbook')
        <ul class="lp-demo-log">
            @foreach ($d as $i => $row)
                <li class="lp-anim" style="--i: {{ $i }}">
                    <span class="lp-demo-time">{{ $row['time'] }}</span>
                    <span class="lp-demo-main">{{ $row['who'] }}<small>{{ $row['why'] }}</small></span>
                    <span @class(['lp-demo-tag', 'is-active' => $row['out'] === 'Resting'])>{{ $row['out'] }}</span>
                </li>
            @endforeach
        </ul>
        @break

    @case('patients')
        <div class="lp-demo-search"><i class="bi bi-search"></i>{{ $d['search'] }}</div>
        <div class="lp-demo-chips">
            @foreach ($d['filters'] as $chip)
                <span>{{ $chip }}</span>
            @endforeach
        </div>
        <ul class="lp-demo-list">
            @foreach ($d['results'] as $i => $r)
                <li class="lp-anim" style="--i: {{ $i }}">
                    <span class="lp-demo-avatar"><i class="bi bi-person"></i></span>
                    <span class="lp-demo-main">{{ $r['who'] }}<small>{{ $r['where'] }}</small></span>
                    <span class="lp-demo-flag">{{ $r['flag'] }}</span>
                </li>
            @endforeach
        </ul>
        @break

    @case('appointments')
        <ul class="lp-demo-list">
            @foreach ($d['slots'] as $i => $s)
                <li class="lp-anim" style="--i: {{ $i }}">
                    <span class="lp-demo-time">{{ $s['time'] }}</span>
                    <span class="lp-demo-main">{{ $s['what'] }}</span>
                    <span @class(['lp-demo-tag', 'is-active' => $s['state'] === 'Approved'])>{{ $s['state'] }}</span>
                </li>
            @endforeach
        </ul>
        <p class="lp-demo-sms lp-anim" style="--i: 3"><i class="bi bi-chat-dots"></i>{{ $d['sms'] }}</p>
        @break

    @case('medicines')
        <ul class="lp-demo-stock">
            @foreach ($d as $i => $m)
                <li class="lp-anim" style="--i: {{ $i }}">
                    <span class="lp-demo-main">{{ $m['name'] }}<small>{{ $m['left'] }} left</small></span>
                    <span @class(['lp-demo-bar', 'is-low' => $m['alert'] === 'Low stock'])><i class="lp-grow-x" style="--w: {{ round($m['left'] / max(1, $m['of']), 2) }}"></i></span>
                    @if ($m['alert'] !== '')
                        <span @class(['lp-demo-alert', 'is-danger' => $m['alert'] === 'Low stock'])>{{ $m['alert'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @break

    @case('reports')
        <div class="lp-demo-chart-head">
            <span>{{ $d['title'] }}</span>
            <span class="lp-demo-button"><i class="bi bi-file-earmark-pdf"></i>{{ $d['button'] }}</span>
        </div>
        <div class="lp-demo-chart">
            @php $max = max($d['bars']) ?: 1; @endphp
            @foreach ($d['bars'] as $i => $bar)
                <span class="lp-demo-col">
                    <i class="lp-grow-y" style="--h: {{ round($bar / $max, 2) }}; --i: {{ $i }}"></i>
                    <small>{{ $d['labels'][$i] ?? '' }}</small>
                </span>
            @endforeach
        </div>
        @break

    @case('assistant')
        <div class="lp-demo-chat">
            <p class="lp-demo-bubble is-me lp-anim" style="--i: 0">{{ $d['question'] }}</p>
            <p class="lp-demo-bubble lp-anim" style="--i: 3"><i class="bi bi-stars"></i>{{ $d['answer'] }}</p>
        </div>
        @break

    @case('roles')
        <div class="lp-demo-chips is-roles">
            @foreach ($d['roles'] as $i => $role)
                <span @class(['is-on' => $i === 1])>{{ $role }}</span>
            @endforeach
        </div>
        <ul class="lp-demo-list is-log">
            @foreach ($d['log'] as $i => $row)
                <li class="lp-anim" style="--i: {{ $i }}">
                    <span class="lp-demo-main"><strong>{{ $row['who'] }}</strong> {{ $row['what'] }}</span>
                    <span class="lp-demo-time">{{ $row['time'] }}</span>
                </li>
            @endforeach
        </ul>
        @break

    @case('privacy')
        <ul class="lp-demo-checks">
            @foreach ($d as $i => $line)
                <li class="lp-anim" style="--i: {{ $i }}"><i class="bi bi-check-circle-fill"></i>{{ $line }}</li>
            @endforeach
        </ul>
        @break
@endswitch
