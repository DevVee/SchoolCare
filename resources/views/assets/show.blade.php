@extends('layouts.app')

@section('title', $asset->name)

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="$asset->name" :description="$asset->category ?: 'No category'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assets and equipment' => route('assets.index'), $asset->name => null]">
        <x-ui.badge :color="$asset->condition_color" size="sm">{{ $asset->condition }}</x-ui.badge>
        @can('manage-assets')
            <x-slot:actions>
                <x-ui.dropdown label="More" variant="secondary">
                    <x-ui.dropdown-item :action="route('assets.destroy', $asset)" method="DELETE" icon="trash" tone="danger"
                        confirm="It is removed from the asset list. The record is kept in the audit trail."
                        :confirm-title="'Remove '.$asset->name.'?'" confirm-button="Remove asset">Remove asset</x-ui.dropdown-item>
                </x-ui.dropdown>
                <x-ui.button :href="route('assets.edit', $asset)" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <div class="row g-4">
        <div class="col-lg-4">
            <x-ui.card>
                @if ($asset->image_url)
                    <a href="{{ $asset->image_url }}" target="_blank" rel="noopener" aria-label="Open the full picture of {{ $asset->name }}">
                        <img src="{{ $asset->image_url }}" alt="Picture of {{ $asset->name }}" class="img-fluid rounded-2 border w-100" loading="lazy">
                    </a>
                @else
                    <x-ui.empty-state icon="image" tone="neutral" title="No picture" compact>
                        @can('manage-assets')
                            <x-ui.button variant="secondary" size="sm" :href="route('assets.edit', $asset)">Add a picture</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endif
            </x-ui.card>
        </div>
        <div class="col-lg-8">
            <x-ui.card title="Details">
                <x-ui.description-list empty="-">
                    <x-ui.description-item label="Quantity"><span class="tabular">{{ number_format($asset->quantity) }}</span></x-ui.description-item>
                    <x-ui.description-item label="Condition"><x-ui.badge :color="$asset->condition_color" size="sm">{{ $asset->condition }}</x-ui.badge></x-ui.description-item>
                    <x-ui.description-item label="Property or serial no." empty="-">@if ($asset->property_number)<span class="font-monospace">{{ $asset->property_number }}</span>@endif</x-ui.description-item>
                    <x-ui.description-item label="Location" empty="-">{{ $asset->location }}</x-ui.description-item>
                    <x-ui.description-item label="Date acquired" empty="-">{{ $asset->acquired_at?->format('M j, Y') }}</x-ui.description-item>
                    <x-ui.description-item label="Cost per unit" empty="-">{{ $asset->cost !== null ? number_format((float) $asset->cost, 2) : '' }}</x-ui.description-item>
                    <x-ui.description-item label="Notes" empty="-" style="white-space: pre-line;">{{ $asset->notes }}</x-ui.description-item>
                    <x-ui.description-item label="Added">{{ $asset->created_at->format('M j, Y, g:i A') }} by {{ $asset->createdBy->name }}</x-ui.description-item>
                    @if ($asset->updatedBy && $asset->updated_at->ne($asset->created_at))
                        <x-ui.description-item label="Last updated">{{ $asset->updated_at->format('M j, Y, g:i A') }} by {{ $asset->updatedBy->name }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
