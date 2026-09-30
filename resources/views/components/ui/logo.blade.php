{{--
    x-ui.logo: brand mark + name, driven entirely by Admin > Settings (never hardcode the product name).

    <x-ui.logo />                                  plain: logo + app_name
    <x-ui.logo variant="sidebar" :href="route('dashboard')" />
        title follows settings('header_title'): app (app_name + tagline) | school (org_name, falls back to app_name)
        | both (app_name + org_name below)
    <x-ui.logo variant="topbar" />
        when settings('topbar_show_school') and org_name is set: school logo + org_name; otherwise the
        brand mark + app_short_name
    <x-ui.logo variant="auth" />                   larger mark, app_name + app_tagline (+ org_name when set)
    <x-ui.logo :wordmark="false" size="28" />      mark only
    <x-ui.logo variant="auth" inverse />           white text for dark surfaces

    Sources: settings('app_name') (default from config app.name), app_short_name, app_tagline,
    org_name (hidden when empty), org_short_name, header_title, topbar_show_school,
    settings()->imageUrl('brand_logo', '/schoolcare-icon.svg'), settings()->imageUrl('school_logo', '').
--}}
@props([
    'variant' => 'plain',     // plain|sidebar|topbar|auth
    'size' => null,           // mark size in px (defaults per variant)
    'wordmark' => true,
    'href' => null,
    'inverse' => false,
])
@php
    $has = function_exists('settings');
    $get = function (string $key, $default = null) use ($has) {
        if (! $has) return $default;
        try { $v = settings($key, $default); } catch (\Throwable $e) { return $default; }
        return ($v === null || $v === '') ? $default : $v;
    };
    $img = function (string $key, string $fallback) use ($has) {
        if (! $has) return $fallback;
        try { return settings()->imageUrl($key, $fallback) ?: $fallback; } catch (\Throwable $e) { return $fallback; }
    };

    $appName   = (string) $get('app_name', config('app.name'));
    $shortName = (string) $get('app_short_name', $appName);
    $tagline   = (string) $get('app_tagline', '');
    $orgName   = trim((string) $get('org_name', ''));
    $orgShort  = trim((string) $get('org_short_name', ''));
    $markUrl   = $img('brand_logo', asset('schoolcare-icon.svg'));
    $schoolUrl = $img('school_logo', '') ?: $markUrl;

    $title = $appName;
    $sub = null;
    $src = $markUrl;
    switch ($variant) {
        case 'sidebar':
            $mode = $get('header_title', 'app');
            if ($mode === 'school' && $orgName !== '') {
                $title = $orgShort !== '' && mb_strlen($orgName) > 26 ? $orgShort : $orgName;
                $sub = $appName;
                $src = $schoolUrl;
            } elseif ($mode === 'both' && $orgName !== '') {
                $sub = $orgName;
            } else {
                $sub = $tagline !== '' ? $tagline : null;
            }
            $size ??= 32;
            break;
        case 'topbar':
            if ((bool) $get('topbar_show_school', true) && $orgName !== '') {
                $title = $orgName;
                $src = $schoolUrl;
            } else {
                $title = $shortName;
            }
            $size ??= 28;
            break;
        case 'auth':
            $sub = $tagline !== '' ? $tagline : null;
            $size ??= 40;
            break;
        default:
            $size ??= 32;
    }
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['c-logo', 'c-logo-'.$variant, 'c-logo-inverse' => $inverse]) }}>
    <img src="{{ $src }}" alt="{{ $wordmark ? '' : $title }}" width="{{ $size }}" height="{{ $size }}" class="c-logo-mark" decoding="async">
    @if ($wordmark)
        <span class="c-logo-text">
            <span class="c-logo-name">{{ $title }}</span>
            @if ($sub)<span class="c-logo-sub">{{ $sub }}</span>@endif
            @if ($variant === 'auth' && $orgName !== '')<span class="c-logo-sub">{{ $orgName }}</span>@endif
        </span>
    @endif
</{{ $tag }}>
