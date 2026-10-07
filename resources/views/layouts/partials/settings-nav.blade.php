{{--
    Settings panel (Shopify style): on admin.settings.* routes, lg and up, the sidebar folds to its
    icon rail (html.settings-mode) and this panel opens beside it.
      1. Header: back to the dashboard + "Settings".
      2. Search box that filters the areas as you type (resources/js/ui/settings-nav.js).
      3. The areas by section, current one highlighted (App\Http\Controllers\Admin\SettingsController::sections).
      4. Who is signed in.
    Below lg it is hidden: phones use the settings home as the menu.
    Styles: resources/scss/pages/_settings.scss.
--}}
@php
    $panelUser = auth()->user();
    $panelSections = $panelUser ? \App\Http\Controllers\Admin\SettingsController::sections($panelUser) : [];
    $panelGroup = request()->route('group');
    $panelHome = request()->routeIs('admin.settings.index');
@endphp
<aside class="settings-panel" aria-label="Settings menu" data-settings-panel>
    <div class="settings-panel-head">
        <a href="{{ route('dashboard') }}" class="settings-panel-back" aria-label="Back to the dashboard" title="Back to the dashboard">
            <x-ui.icon name="chevron-left" />
        </a>
        <span class="settings-panel-title">Settings</span>
    </div>

    <div class="settings-panel-scroll">
        <x-ui.search-input name="settings_search" value="" :clearable="false" size="sm" class="settings-panel-search"
            placeholder="Search settings" data-settings-search />

        <nav class="settings-panel-nav" aria-label="Settings">
            <a href="{{ route('admin.settings.index') }}" @class(['settings-panel-link', 'active' => $panelHome])
               @if ($panelHome) aria-current="page" @endif data-settings-home>
                <x-ui.icon name="grid" />
                <span class="settings-panel-label">All settings</span>
            </a>

            @foreach ($panelSections as $section)
                <div class="settings-panel-group" data-settings-group>
                    <p class="settings-panel-section">{{ $section['title'] }}</p>
                    @foreach ($section['items'] as $item)
                        @php $active = $item['key'] !== null && $item['key'] === $panelGroup; @endphp
                        <a href="{{ $item['href'] }}" @class(['settings-panel-link', 'active' => $active])
                           @if ($active) aria-current="page" @endif
                           data-settings-item data-search="{{ \Illuminate\Support\Str::lower($item['label'].' '.$section['title'].' '.($item['description'] ?? '')) }}">
                            <x-ui.icon :name="$item['icon']" />
                            <span class="settings-panel-label">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach

            <p class="settings-panel-empty" role="status" data-settings-empty hidden>No settings match.</p>
        </nav>
    </div>

    @if ($panelUser)
        <div class="settings-panel-foot">
            <p class="settings-panel-user">{{ $panelUser->name }}</p>
            <p class="settings-panel-email">{{ $panelUser->email }}</p>
        </div>
    @endif
</aside>
