@extends('layouts.app')

@section('title', 'Website: '.$meta['plural'])

{{--
    Administration > Website, Services / Common questions / Clinic team tab.
    From LandingItemController@index: $section (service | faq | team), $segment (services | faqs | team),
    $meta (LandingItem::SECTIONS entry), $items (ordered), $counts.
--}}

@php
    $singular = $meta['singular'];
    $total = $items->count();
    $shown = $items->where('is_enabled', true)->count();
    $addUrl = route('admin.website.items.create', $segment);
    $addLabel = 'Add '.$singular;
    $copy = [
        'service' => [
            'column' => 'Service',
            'icon'   => 'bandaid',
            'empty'  => 'Add what the clinic can do for students and staff. Each service shows as a card on the clinic page.',
        ],
        'faq' => [
            'column' => 'Question',
            'icon'   => 'question-circle',
            'empty'  => 'Add the questions students and parents ask most often, with their answers.',
        ],
        'team' => [
            'column' => 'Name',
            'icon'   => 'people',
            'empty'  => 'Add the nurses, doctors and staff that visitors will meet at the clinic.',
        ],
    ][$section];
    $summary = $total === 0
        ? 'Nothing added yet.'
        : $total.' '.\Illuminate\Support\Str::plural($singular, $total).', '
            .($shown === $total ? 'all shown' : $shown.' shown').' on the clinic page in this order.';
@endphp

@section('content')
<div class="vstack gap-3 website-admin">

    @include('admin.website._header', ['active' => $segment, 'addUrl' => $addUrl, 'addLabel' => $addLabel])

    <x-ui.card flush :title="$meta['plural']" :subtitle="$summary">
        <x-ui.table :caption="$meta['plural']" responsive="stack">
            <x-slot:head>
                <x-ui.th width="120">Order</x-ui.th>
                <x-ui.th>{{ $copy['column'] }}</x-ui.th>
                @if ($section === 'service')
                    <x-ui.th priority="lg">Link</x-ui.th>
                @endif
                <x-ui.th>On the website</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($items as $item)
                @php
                    $name = \Illuminate\Support\Str::limit($item->title, 60);
                    $image = $item->imageUrl();
                    $editUrl = route('admin.website.items.edit', [$segment, $item]);
                    $sub = $section === 'team' ? $item->subtitle : \Illuminate\Support\Str::limit((string) $item->body, 90);
                @endphp
                <tr @class(['wa-row-off' => ! $item->is_enabled])>
                    <x-ui.td label="Order">
                        @include('admin.website._move', [
                            'position' => $loop->iteration,
                            'action' => route('admin.website.items.move', [$segment, $item]),
                            'name' => $name,
                            'first' => $loop->first,
                            'last' => $loop->last,
                        ])
                    </x-ui.td>

                    <x-ui.td identity wrap>
                        <div class="identity">
                            @if ($section === 'team')
                                <x-ui.avatar :name="$item->title" :src="$image" size="md" />
                            @elseif ($image)
                                <img src="{{ $image }}" alt="" width="40" height="40" class="wa-thumb" loading="lazy">
                            @elseif ($section === 'service')
                                <span class="wa-glyph" aria-hidden="true"><x-ui.icon :name="$item->icon ?: 'plus-square'" /></span>
                            @endif
                            <div class="identity-text">
                                <a href="{{ $editUrl }}" class="identity-title">{{ $item->title }}</a>
                                @if (filled($sub))
                                    <span class="identity-sub wa-sub">{{ $sub }}</span>
                                @endif
                            </div>
                        </div>
                    </x-ui.td>

                    @if ($section === 'service')
                        <x-ui.td priority="lg" label="Link" truncate muted>{{ $item->link_url ? ($item->link_label ?: 'Learn more') : 'None' }}</x-ui.td>
                    @endif

                    <x-ui.td label="On the website">
                        @include('admin.website._visibility', [
                            'action' => route('admin.website.items.toggle', [$segment, $item]),
                            'on' => $item->is_enabled,
                            'id' => 'visible-'.$item->id,
                            'name' => $name,
                            'onText' => 'Shown',
                            'offText' => 'Hidden',
                        ])
                    </x-ui.td>

                    <x-ui.td actions>
                        <x-ui.action-menu :for="$name">
                            <x-ui.action-menu.item :href="$editUrl" icon="pencil">Edit</x-ui.action-menu.item>
                            @if ($item->is_enabled)
                                <x-ui.action-menu.item :action="route('admin.website.items.toggle', [$segment, $item])" method="PATCH" icon="eye-slash">Hide from the website</x-ui.action-menu.item>
                            @else
                                <x-ui.action-menu.item :action="route('admin.website.items.toggle', [$segment, $item])" method="PATCH" icon="eye">Show on the website</x-ui.action-menu.item>
                            @endif
                            <x-ui.action-menu.divider />
                            <x-ui.action-menu.item :action="route('admin.website.items.destroy', [$segment, $item])" method="DELETE" icon="trash" danger
                                confirm="It is taken off the clinic page right away. This cannot be undone."
                                :confirm-title="'Delete '.$name.'?'" :confirm-button="'Delete '.$singular">Delete</x-ui.action-menu.item>
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                <x-ui.empty-state :icon="$copy['icon']" compact :title="'No '.\Illuminate\Support\Str::plural($singular).' yet'" :description="$copy['empty']">
                    <x-ui.button size="sm" icon="plus-lg" :href="$addUrl">{{ $addLabel }}</x-ui.button>
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    <p class="wa-note mb-0">Hidden {{ \Illuminate\Support\Str::plural($singular) }} stay here but are left off the clinic page. To turn off the whole section, use Page text.</p>
</div>
@endsection
