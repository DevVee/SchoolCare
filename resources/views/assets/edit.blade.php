@extends('layouts.app')

@section('title', 'Edit asset')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Edit asset'" :description="$asset->name"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assets and equipment' => route('assets.index'), $asset->name => route('assets.show', $asset), 'Edit' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('assets.update', $asset) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <x-ui.card>
            @include('assets._form')
            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('assets.show', $asset)">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
