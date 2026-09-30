{{--
    Site header for the product page ("/"), the clinic page ("/clinic") and the
    privacy notice: brand, "Product" and "Clinic" links and a "Sign in" button.
    Sticky and translucent; the divider shows once content scrolls under it.
    On the clinic page a second row lists the page's sections (from
    Administration > Website), with the one in view highlighted.

    Expects: $page. Optional: $active ('product' | 'clinic'), $clinicBrand (bool,
    show the clinic's name instead of the app's), $subnav (bool), $appName.
--}}
@php
    $active ??= null;
    $clinicBrand ??= false;
    $subnav ??= false;
    $appName ??= \App\Support\ProductSite::appName();
    $brandName = $clinicBrand ? $page['names']['clinic'] : $appName;
    $brandSub = $clinicBrand ? $page['names']['school'] : '';
@endphp
<header @class(['lp-header', 'has-subnav' => $subnav && $page['nav']]) data-lp-header>
    <div class="lp-container lp-header-inner">
        <a href="{{ $clinicBrand ? route('clinic') : route('home') }}" class="lp-brand">
            <img src="{{ $page['logo'] }}" alt="" width="36" height="36" class="lp-brand-logo">
            <span class="lp-brand-text">
                <span class="lp-brand-name">{{ $brandName }}</span>
                @if ($brandSub !== '')
                    <span class="lp-brand-sub">{{ $brandSub }}</span>
                @endif
            </span>
        </a>

        <nav class="lp-nav" aria-label="Site">
            <a href="{{ route('home') }}" class="lp-nav-link" @if ($active === 'product') aria-current="page" @endif>Product</a>
            <a href="{{ route('clinic') }}" class="lp-nav-link" @if ($active === 'clinic') aria-current="page" @endif>Clinic</a>
        </nav>

        <x-ui.button :href="route('login')" size="sm" class="lp-header-cta">Sign in</x-ui.button>
    </div>

    @if ($subnav && $page['nav'])
        <div class="lp-subnav">
            <div class="lp-container">
                <nav class="lp-subnav-inner" aria-label="On this page" data-lp-nav>
                    @foreach ($page['nav'] as $key => $label)
                        <a href="#{{ $key }}" class="lp-subnav-link">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
    @endif
</header>
