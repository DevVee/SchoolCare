{{--
    Server-rendered month calendar (Monday first). Styles: resources/scss/pages/_patients.scss.
    Expects:
      $month      CarbonImmutable (first day of the month)
      $weeks      App\Support\MonthGrid::weeks($month)
      $items      ['Y-m-d' => [['label' => 'Pending', 'count' => 3, 'tone' => 'warning', 'url' => '...', 'icon' => null, 'title' => null], ...]]
                  tone: a tone or module colour name (warning, success, brand, danger, neutral, teal, indigo...) shown as a small dot
      $navRoute   route name for prev / next links
      $navQuery   extra query parameters kept on prev / next (filters)
      $dayUrl     callable(string $date): ?string   link for the day number (optional)
      $highlight  callable(string $date): bool       mark busy days (optional)
      $closedDays list of lowercase weekday names shown muted (optional)
    Slot-like variable $toolbar (HtmlString, optional) renders to the right of the month switcher.
    Tablets and up show the grid; phones get an agenda list of the days that have items.
--}}
@php
    $navQuery   = $navQuery ?? [];
    $dayUrl     = $dayUrl ?? fn ($d) => null;
    $highlight  = $highlight ?? fn ($d) => false;
    $closedDays = $closedDays ?? [];
    $prev = $month->subMonthNoOverflow()->format('Y-m');
    $next = $month->addMonthNoOverflow()->format('Y-m');
    $isCurrent = $month->format('Y-m') === now()->format('Y-m');
    $agendaDays = collect($weeks)->flatten(1)->filter(fn ($d) => $d['inMonth'] && ! empty($items[$d['key']]));
@endphp

<x-ui.card flush>
    <x-slot:header>
        <div class="month-cal-head w-100">
            <div class="d-flex align-items-center gap-1">
                <x-ui.button size="sm" variant="ghost" icon-only icon="chevron-left" label="Previous month" :href="route($navRoute, $navQuery + ['month' => $prev])" />
                <h2 class="month-cal-title" aria-live="polite">{{ $month->format('F Y') }}</h2>
                <x-ui.button size="sm" variant="ghost" icon-only icon="chevron-right" label="Next month" :href="route($navRoute, $navQuery + ['month' => $next])" />
                @unless ($isCurrent)
                    <x-ui.button size="sm" variant="secondary" class="ms-1" :href="route($navRoute, $navQuery)">This month</x-ui.button>
                @endunless
            </div>
            @isset($toolbar)
                <div class="d-flex flex-wrap align-items-center gap-2">{{ $toolbar }}</div>
            @endisset
        </div>
    </x-slot:header>

    {{-- Grid (tablet and up) --}}
    <div class="d-none d-md-block">
        <table class="month-cal" aria-label="{{ $month->format('F Y') }}">
            <thead>
                <tr>
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                        <th scope="col">{{ $dow }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($weeks as $week)
                    <tr>
                        @foreach ($week as $day)
                            @php
                                $closed = in_array(strtolower($day['date']->englishDayOfWeek), $closedDays, true);
                                $url = $dayUrl($day['key']);
                                $busy = $highlight($day['key']);
                            @endphp
                            <td @class(['month-cal-day', 'is-outside' => ! $day['inMonth'], 'is-today' => $day['isToday'], 'is-closed' => $closed, 'is-busy' => $busy])
                                @if ($day['isToday']) aria-current="date" @endif>
                                <div class="month-cal-daytop">
                                    @if ($url)
                                        <a href="{{ $url }}" class="month-cal-num" aria-label="{{ $day['date']->format('l, F j') }}">{{ $day['date']->day }}</a>
                                    @else
                                        <span class="month-cal-num">{{ $day['date']->day }}</span>
                                    @endif
                                    @if ($closed && $day['inMonth'])<span>Closed</span>@elseif ($day['isToday'])<span>Today</span>@endif
                                </div>
                                @foreach ($items[$day['key']] ?? [] as $item)
                                    @php $tag = ! empty($item['url']) ? 'a' : 'span'; @endphp
                                    <{{ $tag }} @if (! empty($item['url'])) href="{{ $item['url'] }}" @endif
                                        class="month-cal-item" title="{{ $item['title'] ?? $item['label'] }}">
                                        @if (! empty($item['icon']))
                                            <x-ui.icon :name="$item['icon']" />
                                        @else
                                            <span class="month-cal-dot tone-{{ $item['tone'] ?? 'neutral' }}"></span>
                                        @endif
                                        @isset($item['count'])<strong>{{ $item['count'] }}</strong>@endisset
                                        <span>{{ $item['label'] }}</span>
                                    </{{ $tag }}>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Agenda (phones) --}}
    <div class="d-md-none month-agenda">
        @forelse ($agendaDays as $day)
            @php $url = $dayUrl($day['key']); $dateTag = $url ? 'a' : 'div'; @endphp
            <div @class(['month-agenda-day', 'is-today' => $day['isToday']])>
                <{{ $dateTag }} @if ($url) href="{{ $url }}" @endif class="month-agenda-date" aria-label="{{ $day['date']->format('l, F j') }}{{ $day['isToday'] ? ', today' : '' }}">
                    <span class="dow">{{ $day['isToday'] ? 'Today' : $day['date']->format('D') }}</span>
                    <span class="num">{{ $day['date']->day }}</span>
                </{{ $dateTag }}>
                <div class="month-agenda-items">
                    @foreach ($items[$day['key']] as $item)
                        @php $tag = ! empty($item['url']) ? 'a' : 'span'; @endphp
                        <{{ $tag }} @if (! empty($item['url'])) href="{{ $item['url'] }}" @endif class="month-cal-item" title="{{ $item['title'] ?? $item['label'] }}">
                            @if (! empty($item['icon']))
                                <x-ui.icon :name="$item['icon']" />
                            @else
                                <span class="month-cal-dot tone-{{ $item['tone'] ?? 'neutral' }}"></span>
                            @endif
                            @isset($item['count'])<strong>{{ $item['count'] }}</strong>@endisset
                            <span>{{ $item['label'] }}</span>
                        </{{ $tag }}>
                    @endforeach
                </div>
            </div>
        @empty
            <x-ui.empty-state quiet icon="calendar3" :title="'Nothing scheduled in '.$month->format('F Y').'.'" />
        @endforelse
    </div>
</x-ui.card>
