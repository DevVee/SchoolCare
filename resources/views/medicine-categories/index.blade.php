@extends('layouts.app')

@section('title', 'Medicine categories')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Medicine categories'"
        :description="$categories->count().' '.\Illuminate\Support\Str::plural('category', $categories->count()).'. Group medicines to find them faster.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), 'Categories' => null]" />

    @include('medicines.partials.module-tabs', ['active' => 'categories'])

    <div class="row g-4">
        <div class="col-lg-8">
            <x-ui.card flush>
                <x-ui.table responsive="stack" caption="Medicine categories">
                    <x-slot:head>
                        <x-ui.th>Category</x-ui.th>
                        <x-ui.th priority="md">Description</x-ui.th>
                        <x-ui.th align="end">Medicines</x-ui.th>
                        <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                    </x-slot:head>

                    @foreach ($categories as $cat)
                        <tr>
                            <x-ui.td identity><span class="cell-title">{{ $cat->name }}</span></x-ui.td>
                            <x-ui.td priority="md" label="Description" muted truncate>{{ $cat->description ?: '-' }}</x-ui.td>
                            <x-ui.td numeric label="Medicines">
                                <a href="{{ route('medicines.index', ['category' => $cat->id]) }}" class="fw-semibold"
                                   aria-label="{{ $cat->medicines_count }} medicines in {{ $cat->name }}">{{ number_format($cat->medicines_count) }}</a>
                            </x-ui.td>
                            <x-ui.td actions>
                                <x-ui.action-menu :for="$cat->name">
                                    <x-ui.action-menu.item :href="route('medicines.index', ['category' => $cat->id])" icon="capsule">View medicines</x-ui.action-menu.item>
                                    @can('update-medicines')
                                        <x-ui.action-menu.item :href="route('medicine-categories.edit', $cat)" icon="pencil">Edit</x-ui.action-menu.item>
                                    @endcan
                                    @can('delete-medicines')
                                        <x-ui.action-menu.divider />
                                        @if ($cat->medicines_count > 0)
                                            <x-ui.action-menu.item icon="trash" danger
                                                data-blocked-message="{{ $cat->name }} has {{ $cat->medicines_count }} {{ \Illuminate\Support\Str::plural('medicine', $cat->medicines_count) }}. Move them to another category before deleting it.">Delete</x-ui.action-menu.item>
                                        @else
                                            <x-ui.action-menu.item :action="route('medicine-categories.destroy', $cat)" method="DELETE" icon="trash" danger
                                                confirm="This cannot be undone." :confirm-title="'Delete '.$cat->name.'?'" confirm-button="Delete category">Delete</x-ui.action-menu.item>
                                        @endif
                                    @endcan
                                </x-ui.action-menu>
                            </x-ui.td>
                        </tr>
                    @endforeach

                    <x-slot:empty>
                        <x-ui.empty-state icon="tags" title="No categories yet" description="Create one with the form." compact />
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>

        @can('create-medicines')
            <div class="col-lg-4">
                <form method="POST" action="{{ route('medicine-categories.store') }}">
                    @csrf
                    <x-ui.card title="New category">
                        <div class="vstack gap-3">
                            <x-ui.input name="name" label="Category name" required placeholder="For example: Analgesic" />
                            <x-ui.textarea name="description" label="Description" rows="3" optional />
                        </div>
                        <x-slot:footer>
                            <x-ui.button type="submit" icon="plus-lg">Add category</x-ui.button>
                        </x-slot:footer>
                    </x-ui.card>
                </form>
            </div>
        @endcan
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        var item = e.target.closest('[data-blocked-message]');
        if (!item) return;
        e.preventDefault();
        if (window.toast) window.toast(item.dataset.blockedMessage, 'warning');
    });
});
</script>
@endpush
