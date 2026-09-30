{{--
    Stock level badge for a medicine: soft, icon + label for the states that need action.
    @include('medicines.partials.stock-status', ['medicine' => $med])
--}}
@if ((int) $medicine->quantity <= 0)
    <x-ui.badge color="danger" icon="x-circle" size="sm">Out of stock</x-ui.badge>
@elseif ($medicine->is_low_stock)
    <x-ui.badge color="warning" icon="exclamation-triangle" size="sm">Low stock</x-ui.badge>
@else
    <x-ui.status-badge status="in_stock" type="stock" size="sm" label="In stock" />
@endif
