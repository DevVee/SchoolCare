{{--
    x-ui.brand-style: put in <head> AFTER @vite(...) in every layout.
      1. Loads the UI fonts (Figtree + Noto Sans, display=swap).
      2. When settings('brand_primary_color') is a valid #RRGGBB that differs
         from the compiled default (#2563EB), emits :root overrides for the
         --brand-50 ... --brand-950 scale, --brand-rgb and --brand-contrast.
         Everything brand-coloured (buttons, links, focus rings, active nav,
         Bootstrap .text-primary / .bg-primary ...) follows at runtime.

    <x-ui.brand-style />
    <x-ui.brand-style :fonts="false" />          (fonts already loaded by the layout)
    <x-ui.brand-style color="#0F766E" />         (explicit colour, e.g. previews)
--}}
@props([
    'color' => null,
    'fonts' => true,
    'default' => '#2563EB',
])
@php
    $brandHex = $color;
    if ($brandHex === null && function_exists('settings')) {
        try {
            $brandHex = settings('brand_primary_color', $default);
        } catch (\Throwable $e) {
            $brandHex = null;
        }
    }
    $brandVars = \App\Support\ColorScale::cssVariables($brandHex, $default);
@endphp
@if ($fonts)
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Noto+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap">
@endif
@if ($brandVars)
<style id="brand-style">:root{@foreach ($brandVars as $var => $value){{ $var }}:{{ $value }};@endforeach}</style>
@endif
