{{--
    x-ui.skeleton: loading placeholder for ASYNC content only (AI answers, charts, pickers).
    <x-ui.skeleton />                              one bar
    <x-ui.skeleton width="40%" height="20px" />
    <x-ui.skeleton :lines="3" />                   paragraph
    <x-ui.skeleton circle size="40px" />           avatar
    <x-ui.skeleton :rows="5" />                    table / list rows (avatar + two bars)
--}}
@props([
    'width' => null,
    'height' => null,
    'lines' => 0,
    'rows' => 0,
    'circle' => false,
    'size' => '40px',
    'label' => 'Loading',
])
@if ($rows > 0)
    <div {{ $attributes->class('skeleton-list') }} role="status" aria-live="polite">
        <span class="visually-hidden">{{ $label }}</span>
        @for ($i = 0; $i < $rows; $i++)
            <div class="skeleton-row" aria-hidden="true">
                <span class="skeleton skeleton-circle" style="width: 32px; height: 32px; flex-shrink: 0"></span>
                <div class="skeleton-stack flex-grow-1">
                    <span class="skeleton" style="width: {{ [70, 55, 80, 60, 75][$i % 5] }}%"></span>
                    <span class="skeleton" style="width: {{ [40, 30, 45, 35, 25][$i % 5] }}%; height: .625rem"></span>
                </div>
            </div>
        @endfor
    </div>
@elseif ($lines > 0)
    <div {{ $attributes->class('skeleton-stack') }} role="status" aria-live="polite">
        <span class="visually-hidden">{{ $label }}</span>
        @for ($i = 0; $i < $lines; $i++)
            <span class="skeleton skeleton-text" aria-hidden="true"></span>
        @endfor
    </div>
@elseif ($circle)
    <span {{ $attributes->class(['skeleton', 'skeleton-circle'])->style(['width: '.$size, 'height: '.$size]) }} aria-hidden="true"></span>
@else
    @php
        $dims = array_filter([$width ? 'width: '.$width : null, $height ? 'height: '.$height : null]);
        $bag = $attributes->class('skeleton');
        if ($dims) { $bag = $bag->style($dims); }
    @endphp
    <span {{ $bag }} aria-hidden="true"></span>
@endif
