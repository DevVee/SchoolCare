{{--
    Administration > Website: top card and the tabs shared by the list pages
    (Page text, Services, Common questions, Clinic team, Advisories).
    Expects: $active (content | services | faqs | team | advisories), $counts (WebsiteController::tabCounts()).
    Optional: $addUrl + $addLabel (primary button beside "View the clinic page").
--}}
@php
    $segments = \App\Models\LandingItem::SECTIONS;
    $tabs = [
        'content' => ['label' => 'Page text', 'href' => route('admin.website.edit')],
    ];
    foreach ($segments as $key => $meta) {
        $tabs[$meta['segment']] = [
            'label' => $meta['plural'],
            'href'  => route('admin.website.items.index', $meta['segment']),
            'count' => (int) ($counts[$key] ?? 0),
        ];
    }
    $tabs['advisories'] = [
        'label' => 'Advisories',
        'href'  => route('admin.website.advisories.index'),
        'count' => (int) ($counts['advisories'] ?? 0),
    ];
    $current = $tabs[$active]['label'] ?? 'Website';
@endphp

<x-ui.page-header title="Website"
    description="What visitors see on the clinic page. Changes are live as soon as you save them."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Website' => $active === 'content' ? null : route('admin.website.edit')] + ($active === 'content' ? [] : [$current => null])">
    <x-slot:actions>
        <x-ui.button variant="secondary" icon-right="box-arrow-up-right" :href="route('clinic')" target="_blank" rel="noopener">
            View the clinic page<span class="visually-hidden"> (opens in a new tab)</span>
        </x-ui.button>
        @isset($addUrl)
            <x-ui.button icon="plus-lg" :href="$addUrl">{{ $addLabel }}</x-ui.button>
        @endisset
    </x-slot:actions>
</x-ui.page-header>

<x-ui.tabs :items="$tabs" :active="$active" label="Website sections" class="website-tabs" />
