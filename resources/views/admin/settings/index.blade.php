@extends('layouts.app')

@section('title', 'Settings')

{{-- Settings home: every settings area, by section, as a grouped list (like iOS Settings): a small
     label per section, one rounded card per section, a row per area with a chevron. On phones this
     is the settings menu; on desktop the settings panel beside the rail lists the same areas and this
     page is the overview (two columns from xl). Styles: resources/scss/pages/_settings.scss. --}}
@section('content')
<div class="vstack gap-3 settings-home">

    <x-ui.page-header title="Settings" :description="'Set up how '.settings('app_name').' works for your clinic.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Settings' => null]" />

    <div class="settings-home-sections">
        @foreach ($sections as $section)
            <section class="settings-home-section" aria-labelledby="settings-home-{{ $loop->index }}">
                <h2 class="settings-home-label" id="settings-home-{{ $loop->index }}">{{ $section['title'] }}</h2>
                <div class="settings-home-card">
                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['href'] }}" class="settings-home-row">
                            <x-ui.icon-chip :icon="$item['icon']" />
                            <span class="settings-home-text">
                                <span class="settings-home-name">{{ $item['label'] }}</span>
                                @if (! empty($item['description']))
                                    <span class="settings-home-desc">{{ $item['description'] }}</span>
                                @endif
                            </span>
                            <x-ui.icon name="chevron-right" class="settings-home-chevron" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

</div>
@endsection
