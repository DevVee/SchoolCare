@extends('layouts.app')

@section('title', 'Assets and equipment')

@php
    $hasFilters = $filters['search'] !== '' || $filters['condition'] !== '' || $filters['category'] !== '';
    $conditionOptions = collect(\App\Models\Asset::conditions())->mapWithKeys(fn ($c) => [$c => $c])->all();
    $categoryOptions = collect($categories)->mapWithKeys(fn ($c) => [$c => $c])->all();
    $sortOptions = ['name' => 'Name (A to Z)', 'recent' => 'Newest first'];
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Assets and equipment'"
        description="Clinic equipment and supplies that are not medicines: beds, BP monitors, stretchers, first aid kits."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assets and equipment' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('assets.export', request()->query())">Export CSV</x-ui.button>
            @can('manage-assets')
                <x-ui.button :href="route('assets.create')" icon="plus-lg">Add asset</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3">
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Items" :value="number_format($summary['items'])" icon="tools" tone="warning" sub="Kinds of equipment" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Total units" :value="number_format($summary['units'])" icon="boxes" tone="brand" sub="Pieces on record" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Recorded value" :value="number_format($summary['value'], 2)" icon="cash-stack" tone="success" sub="Cost times quantity" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Not in good condition" :value="number_format($summary['attention'])" icon="wrench-adjustable"
            :tone="$summary['attention'] ? 'danger' : 'success'" :sub="$summary['attention'] ? 'Needs repair or replacement' : 'All in good condition'" /></div>
    </div>

    <x-ui.filters :action="route('assets.index')" search-placeholder="Name, property no. or location"
        :labels="['condition' => 'Condition', 'category' => 'Category', 'sort' => 'Sort']"
        :options="['condition' => $conditionOptions, 'category' => $categoryOptions, 'sort' => $sortOptions]">
        <x-slot:inline>
            <x-ui.select name="condition" size="sm" aria-label="Condition" :options="$conditionOptions" placeholder="Any condition" :selected="$filters['condition']" />
        </x-slot:inline>
        <x-ui.select name="category" label="Category" size="sm" :options="$categoryOptions" placeholder="All categories" :selected="$filters['category']" />
        <x-ui.select name="sort" label="Sort" size="sm" :options="$sortOptions" :selected="$filters['sort']" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$assets" noun="assets" caption="Assets and equipment">
            <x-slot:head>
                <x-ui.th>Asset</x-ui.th>
                <x-ui.th priority="md">Category</x-ui.th>
                <x-ui.th align="end">Quantity</x-ui.th>
                <x-ui.th>Condition</x-ui.th>
                <x-ui.th priority="lg">Location</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($assets as $asset)
                <tr>
                    <x-ui.td identity>
                        <div class="identity">
                            @if ($asset->image_url)
                                <img src="{{ $asset->image_url }}" alt="" width="36" height="36" loading="lazy" class="rounded-2 border flex-shrink-0" style="object-fit: cover;">
                            @else
                                <span class="rounded-2 border bg-surface-2 d-inline-flex align-items-center justify-content-center text-muted flex-shrink-0" style="width: 36px; height: 36px;">
                                    <x-ui.icon name="tools" />
                                </span>
                            @endif
                            <div class="identity-text">
                                <a href="{{ route('assets.show', $asset) }}" class="identity-title">{{ $asset->name }}</a>
                                @if ($asset->property_number)
                                    <span class="identity-sub font-monospace">{{ $asset->property_number }}</span>
                                @endif
                            </div>
                        </div>
                    </x-ui.td>
                    <x-ui.td priority="md" label="Category">{{ $asset->category ?: '-' }}</x-ui.td>
                    <x-ui.td numeric label="Quantity"><span class="fw-semibold">{{ number_format($asset->quantity) }}</span></x-ui.td>
                    <x-ui.td label="Condition"><x-ui.badge :color="$asset->condition_color" size="sm">{{ $asset->condition }}</x-ui.badge></x-ui.td>
                    <x-ui.td priority="lg" label="Location" muted truncate>{{ $asset->location ?: '-' }}</x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$asset->name">
                            <x-ui.action-menu.item :href="route('assets.show', $asset)" icon="eye">View</x-ui.action-menu.item>
                            @can('manage-assets')
                                <x-ui.action-menu.item :href="route('assets.edit', $asset)" icon="pencil">Edit</x-ui.action-menu.item>
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item :action="route('assets.destroy', $asset)" method="DELETE" icon="trash" danger
                                    confirm="It is removed from the asset list. The record is kept in the audit trail."
                                    :confirm-title="'Remove '.$asset->name.'?'" confirm-button="Remove asset">Remove</x-ui.action-menu.item>
                            @endcan
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($hasFilters)
                    <x-ui.empty-state icon="search" title="No assets match these filters" description="Try a different name or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('assets.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="tools" title="No assets recorded yet" description="Record clinic equipment such as beds, BP monitors and first aid kits." compact>
                        @can('manage-assets')
                            <x-ui.button size="sm" icon="plus-lg" :href="route('assets.create')">Add the first asset</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
