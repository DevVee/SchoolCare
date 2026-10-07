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
    // Spotlight (Ctrl K or /): pages for everyone, patients for those who may look them up.
    $canLookup = ($user?->can('view-patients') || $user?->can('create-patient-logs')) && Route::has('patients.lookup');
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

    {{-- Spotlight: search patients, jump to a page, or ask the assistant (resources/js/ui/spotlight.js) --}}
    <button type="button" class="btn-topbar btn-spotlight" data-spotlight-open aria-label="Search (Ctrl K)" title="Search (Ctrl K)">
        <x-ui.icon name="search" />
    </button>

    {{-- AI assistant: a capsule with the orb and a thin light running round it --}}
    @if ($showAi)
        <a href="{{ route('ai-assistant.index') }}" class="btn-cobi-pill" aria-label="Ask {{ $aiName }}">
            <span class="c-ask-ring" aria-hidden="true"></span>
            <x-ui.coco-orb size="xs" />
            <span class="d-none d-md-inline">Ask {{ $aiName }}</span>
        </a>
    @endif

    {{-- Account: a menu from sm up, a bottom sheet on phones (#accountSheet below) --}}
    @if ($user)
        <button type="button" class="user-trigger user-trigger-compact d-sm-none" data-bs-toggle="modal" data-bs-target="#accountSheet"
                aria-label="Account" title="Account">
            <x-ui.avatar :src="$avatarSrc" :name="$user->name" size="sm" />
        </button>
        <div class="dropdown d-none d-sm-block">
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

{{-- Phones: who is signed in, profile and sign out, as a sheet (outside the header: its blur would trap a fixed sheet) --}}
@if ($user)
    <x-ui.modal id="accountSheet" title="Account" sheet class="account-sheet">
        <div class="account-sheet-who">
            <x-ui.avatar :src="$avatarSrc" :name="$user->name" size="lg" />
            <div class="min-w-0">
                <p class="account-sheet-name text-truncate">{{ $user->name }}</p>
                @if ($user->email)<p class="account-sheet-sub text-truncate">{{ $user->email }}</p>@endif
                <p class="account-sheet-sub">{{ $roleLabel }}</p>
            </div>
        </div>
        <div class="sheet-actions">
            <a href="{{ route('profile.edit') }}" class="btn btn-secondary sheet-action"><x-ui.icon name="person-circle" />My profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary sheet-action sheet-action-danger w-100"><x-ui.icon name="box-arrow-right" />Sign out</button>
            </form>
        </div>
    </x-ui.modal>
@endif

{{-- Spotlight palette. A native <dialog> opens in the top layer, above modals and the topbar. --}}
<dialog class="spotlight" data-spotlight aria-label="Search"
    data-lookup-url="{{ $canLookup ? route('patients.lookup') : '' }}"
    data-patient-url="{{ $canLookup && Route::has('patients.show') ? route('patients.show', '__ID__') : '' }}"
    data-patients-url="{{ $canLookup ? route('patients.index') : '' }}"
    data-ask-url="{{ $showAi ? route('ai-assistant.index') : '' }}"
    data-ai-name="{{ $aiName }}">
    <div class="spotlight-panel">
        <div class="spotlight-field">
            <x-ui.icon name="search" />
            <input type="text" data-spotlight-input role="combobox" aria-expanded="true" aria-controls="spotlightList"
                   aria-autocomplete="list" autocomplete="off" spellcheck="false" enterkeyhint="go"
                   placeholder="{{ $canLookup ? 'Search patients, pages' : 'Search pages' }}{{ $showAi ? ', or ask '.$aiName : '' }}"
                   aria-label="Search">
            <kbd>Esc</kbd>
        </div>
        <div class="spotlight-results" id="spotlightList" role="listbox" aria-label="Results" data-spotlight-list></div>
        <div class="spotlight-foot" aria-hidden="true">
            <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> to move</span>
            <span><kbd>Enter</kbd> to open</span>
            <span class="ms-auto"><kbd>Ctrl</kbd><kbd>K</kbd> anywhere</span>
        </div>
    </div>
</dialog>
