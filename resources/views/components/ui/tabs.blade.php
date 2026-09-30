{{--
    x-ui.tabs: link-based tabs (server side, deep-linkable). Segmented by default.
    <x-ui.tabs :items="[
        'all'       => ['label' => 'All', 'count' => $counts['all']],
        'pending'   => ['label' => 'Pending', 'count' => $counts['pending'], 'icon' => 'hourglass-split'],
        'completed' => 'Completed',
    ]" param="status" />                                  (href = current URL with ?status=key, page reset)

    <x-ui.tabs :items="['profile' => ['label' => 'Profile', 'href' => route('profile.edit')], ...]" active="profile" variant="underline" />

    items:   key => label string, or ['label', 'href', 'icon', 'count']
    active:  active key (default: request($param), else the first key)
    param:   query-string key used to build hrefs when an item has no href (default "tab")
    variant: segmented | underline
    For same-page panes with Bootstrap's tab JS use plain .nav.nav-segmented markup (see README).
--}}
@props([
    'items' => [],
    'active' => null,
    'param' => 'tab',
    'variant' => 'segmented',
    'block' => false,         // segmented: full width
    'label' => 'Sections',    // aria-label of the nav
])
@php
    $normalized = [];
    foreach ($items as $key => $item) {
        $item = is_array($item) ? $item : ['label' => $item];
        $item['href'] = $item['href'] ?? request()->fullUrlWithQuery([$param => $key, 'page' => null]);
        $normalized[(string) $key] = $item;
    }
    $current = (string) ($active ?? request($param) ?? array_key_first($normalized));
@endphp
<nav aria-label="{{ $label }}" {{ $attributes->class(['c-tabs']) }}>
    <div @class([
        'nav',
        'nav-segmented' => $variant === 'segmented',
        'nav-segmented-block' => $variant === 'segmented' && $block,
        'nav-underline' => $variant === 'underline',
    ])>
        @foreach ($normalized as $key => $item)
            @php $isActive = $key === $current; @endphp
            <a href="{{ $item['href'] }}" @class(['nav-link', 'active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" />@endif
                <span>{{ $item['label'] ?? $key }}</span>
                @if (isset($item['count']) && $item['count'] !== null)
                    <span class="nav-count">{{ is_numeric($item['count']) ? number_format($item['count']) : $item['count'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
