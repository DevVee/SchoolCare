@extends('layouts.app')

@section('title', 'Website: Advisories')

{{--
    Administration > Website, Advisories tab.
    From AnnouncementController@index: $advisories (ordered, with createdBy), $counts.
--}}

@php
    $now = now();
    $total = $advisories->count();
    $showing = $advisories->filter(fn ($a) => $a->windowStatus($now) === 'showing')->count();
    $addUrl = route('admin.website.advisories.create');
    $statusBadge = [
        'showing'   => ['success', 'Showing'],
        'scheduled' => ['brand', 'Scheduled'],
        'ended'     => ['neutral', 'Ended'],
        'off'       => ['neutral', 'Off'],
    ];
    $when = function ($a) {
        $fmt = fn ($d) => $d->format('M j, Y g:i A');
        return match (true) {
            $a->starts_at && $a->ends_at => $fmt($a->starts_at).' to '.$fmt($a->ends_at),
            (bool) $a->starts_at         => 'From '.$fmt($a->starts_at),
            (bool) $a->ends_at           => 'Until '.$fmt($a->ends_at),
            default                      => 'Until turned off',
        };
    };
    $summary = $total === 0
        ? 'Nothing posted yet.'
        : $total.' '.\Illuminate\Support\Str::plural('advisory', $total).', '.$showing.' showing right now. The top one is shown first.';
@endphp

@section('content')
<div class="vstack gap-3 website-admin">

    @include('admin.website._header', ['active' => 'advisories', 'addUrl' => $addUrl, 'addLabel' => 'Post advisory'])

    <x-ui.card flush title="Advisories" :subtitle="$summary">
        <x-ui.table caption="Advisories" responsive="stack">
            <x-slot:head>
                <x-ui.th width="120">Order</x-ui.th>
                <x-ui.th>Advisory</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th priority="lg">When</x-ui.th>
                <x-ui.th>Turned on</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($advisories as $advisory)
                @php
                    $name = \Illuminate\Support\Str::limit($advisory->title, 60);
                    $editUrl = route('admin.website.advisories.edit', $advisory);
                    [$tone, $statusLabel] = $statusBadge[$advisory->windowStatus($now)] ?? ['neutral', 'Off'];
                @endphp
                <tr @class(['wa-row-off' => ! $advisory->is_enabled])>
                    <x-ui.td label="Order">
                        @include('admin.website._move', [
                            'position' => $loop->iteration,
                            'action' => route('admin.website.advisories.move', $advisory),
                            'name' => $name,
                            'first' => $loop->first,
                            'last' => $loop->last,
                        ])
                    </x-ui.td>

                    <x-ui.td identity wrap>
                        <div class="identity">
                            <span @class(['wa-glyph', 'is-urgent' => $advisory->type === 'urgent']) aria-hidden="true"><x-ui.icon :name="$advisory->icon" /></span>
                            <div class="identity-text">
                                <a href="{{ $editUrl }}" class="identity-title">{{ $advisory->title }}</a>
                                <span class="identity-sub wa-sub">{{ $advisory->type_label }}, {{ strtolower($advisory->audience_label) }}</span>
                            </div>
                        </div>
                    </x-ui.td>

                    <x-ui.td label="Status"><x-ui.badge :color="$tone">{{ $statusLabel }}</x-ui.badge></x-ui.td>

                    <x-ui.td priority="lg" label="When" muted>{{ $when($advisory) }}</x-ui.td>

                    <x-ui.td label="Turned on">
                        @include('admin.website._visibility', [
                            'action' => route('admin.website.advisories.toggle', $advisory),
                            'on' => $advisory->is_enabled,
                            'id' => 'advisory-on-'.$advisory->id,
                            'name' => $name,
                            'onText' => 'On',
                            'offText' => 'Off',
                        ])
                    </x-ui.td>

                    <x-ui.td actions>
                        <x-ui.action-menu :for="$name">
                            <x-ui.action-menu.item :href="$editUrl" icon="pencil">Edit</x-ui.action-menu.item>
                            @if ($advisory->is_enabled)
                                <x-ui.action-menu.item :action="route('admin.website.advisories.toggle', $advisory)" method="PATCH" icon="eye-slash">Turn off</x-ui.action-menu.item>
                            @else
                                <x-ui.action-menu.item :action="route('admin.website.advisories.toggle', $advisory)" method="PATCH" icon="eye">Turn on</x-ui.action-menu.item>
                            @endif
                            <x-ui.action-menu.divider />
                            <x-ui.action-menu.item :action="route('admin.website.advisories.destroy', $advisory)" method="DELETE" icon="trash" danger
                                confirm="It is taken off the clinic page and the staff dashboard right away. This cannot be undone."
                                :confirm-title="'Delete '.$name.'?'" confirm-button="Delete advisory">Delete</x-ui.action-menu.item>
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                <x-ui.empty-state icon="megaphone" compact title="No advisories yet"
                    description="Post clinic closures, health reminders and other notices for visitors and staff.">
                    <x-ui.button size="sm" icon="plus-lg" :href="$addUrl">Post advisory</x-ui.button>
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    <p class="wa-note mb-0">An advisory shows only while it is turned on and inside its start and end times. Ended advisories stay here until you delete them.</p>
</div>
@endsection
