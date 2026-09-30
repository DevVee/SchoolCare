{{--
    x-ui.coco-orb: the assistant's face. A glossy brand-blue orb with a sparkle mark and a soft
    halo that turns slowly so it reads as AI (still for reduced motion). Colours follow the
    runtime brand, so a brand colour set in Settings recolours it.

    <x-ui.coco-orb />                     md, 40px
    <x-ui.coco-orb size="lg" online />    64px with a green online dot
    <x-ui.coco-orb size="xs" still />     28px, halo does not turn (message faces)

    size: 2xs 20 | xs 28 | sm 36 | md 40 | lg 64
--}}
@props([
    'size' => 'md',
    'still' => false,
    'online' => false,
])
<span {{ $attributes->class(['coco-orb', 'coco-orb-'.$size, 'is-still' => $still]) }} aria-hidden="true">
    <span class="coco-orb-core">
        <svg viewBox="0 0 24 24" fill="currentColor" focusable="false">
            <path d="M10.5 2.5C11 7.4 13.6 10 18.5 10.5 13.6 11 11 13.6 10.5 18.5 10 13.6 7.4 11 2.5 10.5 7.4 10 10 7.4 10.5 2.5Z"/>
            <path d="M18 14.5c.2 2.1 1.4 3.3 3.5 3.5-2.1.2-3.3 1.4-3.5 3.5-.2-2.1-1.4-3.3-3.5-3.5 2.1-.2 3.3-1.4 3.5-3.5Z" opacity=".8"/>
        </svg>
    </span>
    @if ($online)<span class="coco-orb-online"></span>@endif
</span>
