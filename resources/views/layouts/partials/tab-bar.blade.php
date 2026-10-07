{{--
    Phones and tablets: bottom tab bar, like an app (layout/_mobile.scss; hidden from lg up).
    Dashboard, Patients, Visits and Appointments come from config/navigation.php through the
    same NavigationComposer as the sidebar (same permissions, feature flags, active state and
    badge counts; memoised, so no extra queries). "More" opens the sidebar drawer.
--}}
@php
    $tabNav = collect(app(\App\View\Composers\NavigationComposer::class)->navigation())
        ->flatMap(fn ($group) => $group['items'])
        ->keyBy('key');
    // Short labels for the bar; the sidebar keeps the full names.
    $tabs = [
        'dashboard'          => ['label' => 'Dashboard',    'icon' => 'house',          'icon_on' => 'house-fill'],
        'patients.index'     => ['label' => 'Patients',     'icon' => 'people',         'icon_on' => 'people-fill'],
        'patient-logs.index' => ['label' => 'Visits',       'icon' => 'journal-medical', 'icon_on' => 'journal-medical'],
        'appointments.index' => ['label' => 'Appointments', 'icon' => 'calendar-check', 'icon_on' => 'calendar-check-fill'],
    ];
    $tabItems = collect($tabs)->filter(fn ($t, $key) => $tabNav->has($key))
        ->map(fn ($t, $key) => $t + $tabNav->get($key));
    $moreActive = $tabItems->doesntContain('active', true);
@endphp
@auth
<nav class="app-tabbar" aria-label="Main">
    <div class="app-tabbar-inner" style="--tabbar-cols: {{ $tabItems->count() + 1 }}">
        @foreach ($tabItems as $tab)
            <a href="{{ $tab['url'] }}" @class(['app-tab', 'active' => $tab['active']]) @if ($tab['active']) aria-current="page" @endif>
                <span class="app-tab-icon">
                    <x-ui.icon :name="$tab['active'] ? $tab['icon_on'] : $tab['icon']" />
                    @if ($tab['badge'])
                        <span class="app-tab-badge" aria-label="{{ $tab['badge_label'] }}">{{ $tab['badge'] > 9 ? '9+' : $tab['badge'] }}</span>
                    @endif
                </span>
                <span class="app-tab-label">{{ $tab['label'] }}</span>
            </a>
        @endforeach
        <button type="button" @class(['app-tab', 'active' => $moreActive]) data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                aria-controls="appSidebar" aria-label="More pages">
            <span class="app-tab-icon"><x-ui.icon name="grid" /></span>
            <span class="app-tab-label">More</span>
        </button>
    </div>
</nav>
@endauth
