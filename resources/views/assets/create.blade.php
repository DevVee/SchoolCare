@extends('layouts.app')

@section('title', 'Add asset')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Add asset'" description="Record a piece of clinic equipment or a supply item."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Assets and equipment' => route('assets.index'), 'Add asset' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data">
        @csrf
        <x-ui.card>
            @include('assets._form')
            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('assets.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save asset</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
