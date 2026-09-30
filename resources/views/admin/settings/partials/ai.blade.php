{{-- AI assistant settings. Status (on/off, connection) is shown above the form (ai-status). --}}
<x-ui.section title="Assistant" description="Turn the assistant on or off and choose the name staff see.">
    <div class="row g-3">
        @foreach (['ai_enabled', 'ai_assistant_name'] as $key)
            @isset($fields[$key])
                @include('admin.settings.partials.field', ['key' => $key, 'def' => $fields[$key]])
            @endisset
        @endforeach
    </div>
</x-ui.section>
@isset($fields['ai_model'])
    <x-ui.section title="Answer quality" description="Pick the model that writes the answers.">
        <div class="row g-3">
            @include('admin.settings.partials.field', ['key' => 'ai_model', 'def' => $fields['ai_model'], 'col' => 'col-12'])
        </div>
    </x-ui.section>
@endisset
