@extends('layouts.app')

@section('title', 'Settings')

{{-- Settings home: every settings area, by section. Each group page is full width with an "All settings" button back here (no side menu). --}}
@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Settings" :description="'Set up how '.settings('app_name').' works for your clinic.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Settings' => null]" />

    @foreach ($sections as $section)
        <x-ui.card :title="$section['title']" flush>
            <div class="vstack gap-1 p-2">
                @foreach ($section['items'] as $item)
                    <a href="{{ $item['href'] }}" class="list-row">
                        <x-ui.icon-chip :icon="$item['icon']" />
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-semibold text-ink">{{ $item['label'] }}</span>
                            @if (! empty($item['description']))
                                <span class="d-block text-muted fs-sm">{{ $item['description'] }}</span>
                            @endif
                        </span>
                        <x-ui.icon name="chevron-right" class="text-muted" />
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endforeach

</div>
@endsection
