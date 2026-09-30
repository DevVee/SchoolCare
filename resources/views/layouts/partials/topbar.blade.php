{{--
    App topbar: sticky, translucent (layout/_topbar.scss), scroll-edge divider (ui/scroll-edge.js).
    Title: @section('page_title') if set, else @section('title').
    Breadcrumbs: @section('breadcrumb') with <li class="breadcrumb-item"> items (shown from md up).
--}}
@php
    $user = auth()->user();
    $roleName = $user?->getRoleNames()->first();
    $roleLabel = $roleName ? \Illuminate\Support\Str::headline($roleName) : 'User';
    $avatarSrc = $user?->avatar ? $user->avatarUrl() : null;
    $orgName = trim((string) settings('org_name'));
    $orgShort = trim((string) settings('org_short_name'));
    $showSchool = filter_var(settings('topbar_show_school', true), FILTER_VALIDATE_BOOLEAN) && $orgName !== '';
    // Only an uploaded logo: the default mark would duplicate the sidebar logo.
    $schoolLogo = filled(settings('brand_logo')) ? settings()->imageUrl('brand_logo') : null;
    $aiName = trim((string) settings('ai_assistant_name')) ?: 'AI assistant';
    $showAi = filter_var(settings('ai_enabled', true), FILTER_VALIDATE_BOOLEAN)
        && $user?->can('use-ai-assistant') && Route::has('ai-assistant.index');
    $showSearch = $user?->can('view-patients') ?? false;
    // Section content is already escaped by Blade.
    $pageTitle = trim($__env->yieldContent('page_title')) ?: trim($__env->yieldContent('title', 'Dashboard'));
@endphp
<header class="app-topbar">
    {{-- Drawer (below lg) / collapse rail (lg and up) --}}
    <button type="button" class="btn-topbar d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
            aria-controls="appSidebar" aria-label="Open menu">
        <x-ui.icon name="list" />
    </button>
    <button type="button" class="btn-topbar d-none d-lg-inline-flex" data-sidebar-toggle
            aria-controls="appSidebar" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
        <x-ui.icon name="layout-sidebar" />
    </button>
    <span class="topbar-divider d-none d-lg-block" aria-hidden="true"></span>

    {{-- Where am I --}}
    <div class="topbar-title">
        @hasSection('breadcrumb')
            <nav aria-label="Breadcrumb" class="topbar-breadcrumb-nav d-none d-md-block">
                <ol class="breadcrumb topbar-breadcrumb">
                    @yield('breadcrumb')
                </ol>
            </nav>
            <p class="title d-md-none">{!! $pageTitle !!}</p>
        @else
            <p class="title">{!! $pageTitle !!}</p>
        @endif
    </div>

    <div class="topbar-spacer" aria-hidden="true"></div>

    {{-- School identity (Settings > Branding) --}}
    @if ($showSchool)
        {{-- Shown only when the sidebar is collapsed to the rail (xl and up); the sidebar header shows it otherwise --}}
        <div class="topbar-school" title="{{ $orgName }}">
            @if ($schoolLogo)
                <img src="{{ $schoolLogo }}" alt="" width="24" height="24" class="topbar-school-logo" decoding="async">
            @endif
            @if ($orgShort !== '')
                <abbr class="topbar-school-name" title="{{ $orgName }}">{{ $orgShort }}</abbr>
            @else
                <span class="topbar-school-name">{{ $orgName }}</span>
            @endif
        </div>
        <span class="topbar-divider topbar-school-divider" aria-hidden="true"></span>
    @endif

    {{-- Global patient search --}}
    @if ($showSearch)
        <form class="topbar-search" data-topbar-search role="search" method="GET" action="{{ route('patients.index') }}">
            <label for="topbarSearch" class="visually-hidden">Search patients</label>
            <x-ui.icon name="search" />
            <input id="topbarSearch" type="search" name="search" class="form-control" placeholder="Search patients"
                   value="{{ request()->routeIs('patients.index') ? request('search') : '' }}" autocomplete="off" enterkeyhint="search">
            <kbd class="d-none d-xl-inline" aria-hidden="true">/</kbd>
            <button type="button" class="btn-topbar topbar-search-close" data-search-close aria-label="Close search">
                <x-ui.icon name="x-lg" />
            </button>
        </form>
        <button type="button" class="btn-topbar d-md-none" data-search-open aria-label="Search patients">
            <x-ui.icon name="search" />
        </button>
    @endif

    {{-- AI assistant --}}
    @if ($showAi)
        <a href="{{ route('ai-assistant.index') }}" class="btn-cobi-pill" aria-label="Ask {{ $aiName }}">
            <x-ui.icon name="chat-square-text" />
            <span class="d-none d-xl-inline">Ask {{ $aiName }}</span>
        </a>
    @endif

    {{-- Account --}}
    @if ($user)
        <div class="dropdown">
            <button type="button" class="user-trigger user-trigger-compact" data-bs-toggle="dropdown" data-bs-offset="0,8" aria-expanded="false">
                <x-ui.avatar :src="$avatarSrc" :name="$user->name" size="sm" />
                {{-- Name and role live in the sidebar profile card; avatar only here --}}
                <span class="visually-hidden">Account menu for {{ $user->name }}</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end dropdown-panel user-menu">
                @include('layouts.partials.user-menu')
            </div>
        </div>
    @endif
</header>
