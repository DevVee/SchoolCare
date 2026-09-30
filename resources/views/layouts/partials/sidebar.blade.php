{{--
    App sidebar (modeled on the owner's reference, ui_principles.md section 9).
      1. Header: school logo + school name + muted second line (x-ui.logo, Settings > Branding
         header_title) and a collapse chevron.
      2. Profile card: avatar with ring + online dot, name, role pill; opens the account menu.
      3. Groups from config/navigation.php (resolved by App\View\Composers\NavigationComposer),
         one item per module, outline icons in module tones. No sub-items: related pages use
         page-level tabs and keep the module item active.
    >= lg: fixed 260px, collapsible to a 72px rail (html.sidebar-collapsed).
    <  lg: Bootstrap offcanvas drawer (backdrop, Esc, focus trap).
--}}
@php
    $navigation = $navigation ?? [];
    $user = auth()->user();
    $roleName = $user?->getRoleNames()->first();
    $roleLabel = $roleName ? \Illuminate\Support\Str::headline($roleName) : 'User';
    $avatarSrc = $user?->avatar ? $user->avatarUrl() : null;
@endphp
<aside class="app-sidebar offcanvas-lg offcanvas-start" id="appSidebar" tabindex="-1" aria-label="Main menu">
    {{-- Header: logo + name; collapse chevron (desktop) or close (drawer) --}}
    <div class="sidebar-brand">
        <x-ui.logo variant="sidebar" size="38" :href="route('dashboard')" />
        <button type="button" class="sidebar-collapse-btn d-none d-lg-inline-flex" data-sidebar-toggle
                aria-controls="appSidebar" aria-expanded="true" aria-label="Collapse sidebar">
            <x-ui.icon name="chevron-left" />
        </button>
        <button type="button" class="sidebar-collapse-btn d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close menu">
            <x-ui.icon name="x-lg" />
        </button>
    </div>

    <div class="sidebar-scroll">
        {{-- Profile card --}}
        @if ($user)
            <div class="sidebar-profile dropdown">
                <button type="button" class="sidebar-profile-trigger" data-bs-toggle="dropdown" data-bs-offset="0,6"
                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" data-rail-tip="{{ $user->name }}">
                    <span class="sidebar-profile-avatar">
                        <x-ui.avatar :src="$avatarSrc" :name="$user->name" size="xl" />
                        <span class="online-dot" aria-hidden="true"></span>
                    </span>
                    <span class="sidebar-profile-name">{{ $user->name }}</span>
                    <span class="sidebar-profile-role">{{ $roleLabel }}</span>
                    <span class="visually-hidden">Open account menu</span>
                </button>
                <div class="dropdown-menu dropdown-panel user-menu">
                    @include('layouts.partials.user-menu')
                </div>
            </div>
        @endif

        <nav class="sidebar-nav" aria-label="Main">
            @foreach ($navigation as $group)
                @if (filled($group['label']))
                    <p class="sidebar-section">{{ $group['label'] }}</p>
                @endif
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['url'] }}"
                       @class(['sidebar-link', 'active' => $item['active']])
                       @if ($item['active']) aria-current="page" @endif
                       @if ($item['module']) data-module="{{ $item['module'] }}" @endif
                       data-rail-tip="{{ $item['label'] }}{{ $item['badge'] ? ' ('.$item['badge_label'].')' : '' }}">
                        <span class="sidebar-link-icon"><x-ui.icon :name="$item['icon']" /></span>
                        <span class="sidebar-link-label">{{ $item['label'] }}</span>
                        <x-ui.count :value="$item['badge']" :label="$item['badge_label']" />
                    </a>
                @endforeach
            @endforeach
        </nav>
    </div>
</aside>
