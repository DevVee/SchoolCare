{{--
    x-ui.icon: Bootstrap Icons wrapper (swap the icon library here in one place).
    <x-ui.icon name="trash" />  <x-ui.icon name="bi-trash" size="18" />  <x-ui.icon name="info-circle" label="Info" />
--}}
@props([
    'name',
    'size' => null,    // px number or any CSS length
    'label' => null,   // accessible label; omit for decorative icons (aria-hidden)
])
@php
    $icon = preg_replace('/^bi[- ]/', '', trim((string) $name));
    $a11y = $label ? ['role' => 'img', 'aria-label' => $label] : ['aria-hidden' => 'true'];
    $bag = $attributes->class(['bi', 'bi-'.$icon, 'c-icon'])->merge($a11y);
    if ($size !== null && $size !== '') {
        $bag = $bag->style(['font-size: '.(is_numeric($size) ? $size.'px' : $size)]);
    }
@endphp
<i {{ $bag }}></i>
