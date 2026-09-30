{{--
    x-ui.search-input: search box with icon and a clear (x) link when it has a value.
    <x-ui.search-input />                                            (name "search", value from the request)
    <x-ui.search-input name="q" placeholder="Name, patient no. or contact" size="sm" />

    Put it inside a GET form (x-ui.filters does this for you).
--}}
@props([
    'name' => 'search',
    'value' => null,          // default: request($name)
    'placeholder' => 'Search',
    'label' => null,          // accessible label (default: the placeholder)
    'size' => null,           // sm|lg
    'clearable' => true,
    'clearUrl' => null,       // default: current URL without this param and page
    'id' => null,
])
@php
    $current = $value ?? request($name);
    $current = is_string($current) ? $current : '';
    $searchId = $id ?? 'search-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $clearHref = $clearUrl ?? request()->fullUrlWithoutQuery([$name, 'page']);
@endphp
<div {{ $attributes->only('class')->class(['search-input']) }}>
    <x-ui.icon name="search" />
    <label for="{{ $searchId }}" class="visually-hidden">{{ $label ?? $placeholder }}</label>
    <input {{ $attributes->except('class')->class(['form-control', 'form-control-'.$size => in_array($size, ['sm', 'lg'], true)]) }}
        type="search" name="{{ $name }}" id="{{ $searchId }}" value="{{ $current }}" placeholder="{{ $placeholder }}"
        autocomplete="off" enterkeyhint="search">
    @if ($clearable && $current !== '')
        <a href="{{ $clearHref }}" class="search-clear" aria-label="Clear search" title="Clear search"><x-ui.icon name="x-lg" /></a>
    @endif
</div>
