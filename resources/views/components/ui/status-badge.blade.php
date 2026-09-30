{{--
    x-ui.status-badge: maps a domain status to a tone + readable label (config/ui.php).
    <x-ui.status-badge :status="$appointment->status" type="appointment" />
    <x-ui.status-badge :status="$log->status" type="sms" />
    <x-ui.status-badge :status="$patient->is_active" type="patient" />          (true/false -> Active/Inactive)
    <x-ui.status-badge status="low_stock" label="Low stock (4 left)" />

    Built-in statuses: pending, approved, completed, cancelled, no_show, active, inactive,
    sent, failed, skipped, expired, expiring, low_stock, out_of_stock, ...
    Types: appointment, disposition, audit, inventory, sms, patient, stock, role.
--}}
@props([
    'status',
    'type' => null,
    'label' => null,
    'variant' => 'soft',   // soft|solid|outline
    'size' => 'md',
    'dot' => true,
])
@php
    $key = match (true) {
        $status === true => 'active',
        $status === false => 'inactive',
        $status === null => 'unknown',
        default => strtolower(str_replace([' ', '-'], '_', trim((string) $status))),
    };
    $statusTone = ($type ? config("ui.status.$type.$key") : null)
        ?? config("ui.status.default.$key")
        ?? 'neutral';
    $text = $label ?? config("ui.status_labels.$key") ?? \Illuminate\Support\Str::headline($key);
@endphp
<x-ui.badge :color="$statusTone" :variant="$variant" :size="$size" :dot="$dot" {{ $attributes }}>{{ $text }}</x-ui.badge>
