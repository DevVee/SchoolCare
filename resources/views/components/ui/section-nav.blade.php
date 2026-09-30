{{--
    x-ui.section-nav: settings-style section navigation. Sticky vertical list on desktop
    (top = topbar height + 16px), horizontal scrollable tab row on phones and tablets.

    <div class="row g-4">
        <div class="col-lg-3">
            <x-ui.section-nav title="Settings" :active="$group" :items="[
                'general'  => ['label' => 'General', 'icon' => 'sliders', 'href' => route('admin.settings.edit', 'general')],
                'branding' => ['label' => 'Branding', 'icon' => 'palette', 'href' => route('admin.settings.edit', 'branding')],
                'sms'      => ['label' => 'SMS', 'icon' => 'chat-dots', 'href' => route('admin.settings.edit', 'sms'), 'count' => 2],
            ]" />
        </div>
        <div class="col-lg-9">...</div>
    </div>

    items:  key => label string or ['label', 'href', 'icon', 'count']; without href the link is
            "#key" (in-page anchors) or ?{param}=key when `param` is set.
    active: active key (default: request($param) or the first key)
--}}
@props([
    'items' => [],
    'active' => null,
    'title' => null,
    'param' => null,
    'label' => 'Sections',
])
@php
    $normalized = [];
    foreach ($items as $key => $item) {
        $item = is_array($item) ? $item : ['label' => $item];
        $item['href'] = $item['href'] ?? ($param ? request()->fullUrlWithQuery([$param => $key]) : '#'.$key);
        $normalized[(string) $key] = $item;
    }
    $current = (string) ($active ?? ($param ? request($param) : null) ?? array_key_first($normalized));
@endphp
<nav {{ $attributes->class('section-nav') }} aria-label="{{ $title ?? $label }}">
    @if ($title)<p class="section-nav-title">{{ $title }}</p>@endif
    <ul class="section-nav-list">
        @foreach ($normalized as $key => $item)
            @php $isActive = $key === $current; @endphp
            <li>
                <a href="{{ $item['href'] }}" @class(['section-nav-link', 'active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                    @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" />@endif
                    <span>{{ $item['label'] ?? $key }}</span>
                    @isset($item['count'])<x-ui.count :value="$item['count']" />@endisset
                </a>
            </li>
        @endforeach
    </ul>
</nav>
