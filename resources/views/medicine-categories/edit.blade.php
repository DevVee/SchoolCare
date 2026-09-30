@extends('layouts.app')

@section('title', 'Edit category: '.$medicineCategory->name)

@php $medicineCount = $medicineCategory->medicines()->count(); @endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Edit category'" :description="$medicineCategory->name"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), 'Categories' => route('medicine-categories.index'), 'Edit' => null]" />

    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <form method="POST" action="{{ route('medicine-categories.update', $medicineCategory) }}">
                @csrf @method('PUT')
                <x-ui.card>
                    <div class="vstack gap-3">
                        <x-ui.input name="name" label="Category name" required :value="$medicineCategory->name" />
                        <x-ui.textarea name="description" label="Description" rows="3" optional :value="$medicineCategory->description" />
                        @if ($medicineCount > 0)
                            <x-ui.alert variant="info">
                                {{ $medicineCount }} {{ \Illuminate\Support\Str::plural('medicine', $medicineCount) }} use this category.
                                <a href="{{ route('medicines.index', ['category' => $medicineCategory->id]) }}">View them</a>.
                            </x-ui.alert>
                        @endif
                    </div>
                    <x-slot:footer>
                        <x-ui.button variant="secondary" :href="route('medicine-categories.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>
        </div>
    </div>
</div>
@endsection
