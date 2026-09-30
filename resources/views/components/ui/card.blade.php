{{--
    x-ui.card
    <x-ui.card title="Recent visits" subtitle="Last 7 days">
        <x-slot:actions><x-ui.button size="sm" variant="secondary">View all</x-ui.button></x-slot:actions>
        (body)
        <x-slot:footer>...</x-slot:footer>
    </x-ui.card>
    <x-ui.card flush> <x-ui.table>...</x-ui.table> </x-ui.card>      (no body padding: tables)
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'description' => null,   // alias of subtitle
    'icon' => null,          // optional icon square before the title
    'iconTone' => 'brand',
    'module' => null,        // module key: tone icon chip before the title (widget headers)
    'padding' => 'md',       // none|sm|md|lg
    'flush' => false,        // shorthand for padding="none"
    'variant' => 'default',  // default|flat|interactive
    'quiet' => false,        // empty panel: flat, tinted, compact padding, muted text
    'href' => null,          // whole card is a link
    'bodyClass' => null,
])
@php
    $sub = $subtitle ?? $description;
    $pad = $flush ? 'none' : ($quiet && $padding === 'md' ? 'sm' : $padding);
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'card',
    'card-flat' => $variant === 'flat',
    'card-interactive' => $variant === 'interactive' || $href,
    'card-flush' => $pad === 'none',
    'card-quiet' => $quiet,
]) }}>
    @isset($header)
        <div {{ $header->attributes->class(['card-header', 'c-card-header']) }}>{{ $header }}</div>
    @elseif ($title || isset($actions))
        <div class="card-header c-card-header">
            <div class="d-flex align-items-center gap-2 min-w-0">
                @if ($module)
                    <x-ui.icon-chip :module="$module" :icon="$icon" />
                @elseif ($icon)
                    <x-ui.icon-chip :tone="$iconTone" :icon="$icon" />
                @endif
                <div class="min-w-0">
                    @if ($title)<h2 class="card-heading">{{ $title }}</h2>@endif
                    @if ($sub)<p class="card-description">{{ $sub }}</p>@endif
                </div>
            </div>
            @isset($actions)
                <div class="card-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endisset
    <div @class(['card-body', 'card-body-'.$pad, $bodyClass])>
        {{ $slot }}
    </div>
    @isset($footer)
        <div {{ $footer->attributes->class(['card-footer', 'c-card-footer']) }}>{{ $footer }}</div>
    @endisset
</{{ $tag }}>
