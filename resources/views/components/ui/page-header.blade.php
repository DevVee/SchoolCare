{{--
    x-ui.page-header
    <x-ui.page-header title="Patient records" description="134 patients, 120 active"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => null]">
        <x-slot:actions>
            <x-ui.button :href="route('patients.create')" icon="person-plus">New patient</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-header title="Edit patient" :back="route('patients.index')" back-label="Patients" />

    breadcrumbs: ['Label' => url|null, ...] or [['label' => 'X', 'url' => '...'], ...]; the last item is the current page.
    Default slot: extra meta under the description (badges, timestamps).
--}}
@props([
    'title',
    'description' => null,
    'subtitle' => null,       // alias of description
    'breadcrumbs' => [],
    'back' => null,           // URL for a "back" link above the title
    'backLabel' => 'Back',
])
@php
    $desc = $description ?? $subtitle;
    $crumbs = [];
    foreach ((array) $breadcrumbs as $key => $value) {
        if (is_array($value)) {
            $crumbs[] = ['label' => $value['label'] ?? '', 'url' => $value['url'] ?? null];
        } elseif (is_int($key)) {
            $crumbs[] = ['label' => (string) $value, 'url' => null];
        } else {
            $crumbs[] = ['label' => (string) $key, 'url' => $value];
        }
    }
@endphp
<header {{ $attributes->class('page-header') }}>
    <div class="page-header-main">
        @if (count($crumbs))
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    @foreach ($crumbs as $crumb)
                        @if ($loop->last)
                            <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                        @else
                            <li class="breadcrumb-item">
                                @if ($crumb['url'])<a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>@else{{ $crumb['label'] }}@endif
                            </li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @elseif ($back)
            <a href="{{ $back }}" class="page-back"><x-ui.icon name="arrow-left" />{{ $backLabel }}</a>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if ($desc)
            <p class="page-subtitle">{{ $desc }}</p>
        @endif
        @if (trim($slot) !== '')
            <div class="page-header-meta">{{ $slot }}</div>
        @endif
    </div>
    @isset($actions)
        <div {{ $actions->attributes->class('page-actions') }}>{{ $actions }}</div>
    @endisset
</header>
