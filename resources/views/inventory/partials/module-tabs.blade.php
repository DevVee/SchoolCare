{{--
    Inventory module tabs (replace the sidebar sub-items).
    @include('inventory.partials.module-tabs', ['active' => 'levels'])   levels | ledger | disposals
--}}
@php
    $invTabs = [
        'levels' => ['label' => 'Stock levels', 'href' => route('inventory.index')],
        'ledger' => ['label' => 'Stock ledger', 'href' => route('inventory.transactions')],
        'disposals' => ['label' => 'Disposal history', 'href' => route('disposals.index')],
    ];
@endphp
<x-ui.tabs label="Inventory sections" :items="$invTabs" :active="$active" />
