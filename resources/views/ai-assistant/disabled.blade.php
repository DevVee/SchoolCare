@extends('layouts.app')

@php $assistant = settings('ai_assistant_name') ?: 'The assistant'; @endphp

@section('title', $assistant)

@section('content')
<div class="vstack gap-3">
    <x-ui.page-header :title="$assistant" :breadcrumbs="['Dashboard' => route('dashboard'), $assistant => null]" />

    <x-ui.card>
        <x-ui.empty-state icon="chat-square" tone="neutral" :title="$assistant.' is turned off'"
            description="An administrator has turned off the assistant.">
            @can('manage-settings')
                <x-ui.button variant="secondary" size="sm" icon="gear" :href="route('admin.settings.edit', 'ai')">Open assistant settings</x-ui.button>
            @endcan
            <x-ui.button size="sm" :href="route('dashboard')">Back to dashboard</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
</div>
@endsection
