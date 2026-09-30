{{--
    Medicines module tabs (replace the sidebar sub-items).
    @include('medicines.partials.module-tabs', ['active' => 'all'])   all | low-stock | expiring | categories
--}}
@php
    $medTabs = [
        'all' => ['label' => 'All medicines', 'href' => route('medicines.index')],
        'low-stock' => ['label' => 'Low stock', 'href' => route('medicines.low-stock')],
        'expiring' => ['label' => 'Expiry and disposal', 'href' => route('medicines.expiring')],
    ];
    if (auth()->user()?->can('view-medicines')) {
        $medTabs['categories'] = ['label' => 'Categories', 'href' => route('medicine-categories.index')];
    }
@endphp
<x-ui.tabs label="Medicines sections" :items="$medTabs" :active="$active" />
