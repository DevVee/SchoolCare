@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Inventory'" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('inventory.index'), 'Not available' => null]" />
    <x-ui.card>
        <x-ui.empty-state icon="box-seam" title="This page is not available" description="Use stock in or stock out from the inventory list." compact>
            <x-ui.button variant="secondary" size="sm" :href="route('inventory.index')">Go to inventory</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
</div>
@endsection
