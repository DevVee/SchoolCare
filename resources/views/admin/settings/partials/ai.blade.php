{{-- AI assistant settings. Status (on/off, connection) is shown above the form (ai-status). --}}
@php $assistant = settings('ai_assistant_name') ?: 'the assistant'; @endphp
<x-ui.section title="Assistant" description="Turn the assistant on or off and choose the name staff see.">
    <div class="row g-3">
        @foreach (['ai_enabled', 'ai_assistant_name'] as $key)
            @isset($fields[$key])
                @include('admin.settings.partials.field', ['key' => $key, 'def' => $fields[$key]])
            @endisset
        @endforeach
    </div>
</x-ui.section>
@isset($fields['ai_groq_api_key'])
    <x-ui.section title="Connection" description="The key that lets {{ $assistant }} answer. Answers come from Groq, an online AI service.">
        <div class="row g-3">
            @include('admin.settings.partials.field', ['key' => 'ai_groq_api_key', 'def' => $fields['ai_groq_api_key'], 'col' => 'col-12 col-lg-8'])
        </div>
    </x-ui.section>
@endisset
@if (isset($fields['ai_model']) || isset($fields['ai_web_search']))
    <x-ui.section title="Answer quality" description="Pick the model that writes the answers, and whether it may look things up online.">
        <div class="row g-3">
            @isset($fields['ai_model'])
                @include('admin.settings.partials.field', ['key' => 'ai_model', 'def' => $fields['ai_model'], 'col' => 'col-12'])
            @endisset
            @isset($fields['ai_web_search'])
                @include('admin.settings.partials.field', ['key' => 'ai_web_search', 'def' => ['label' => 'Let '.$assistant.' search the web'] + $fields['ai_web_search']])
            @endisset
        </div>
    </x-ui.section>
@endif
@isset($fields['ai_read_patients'])
    <x-ui.section title="Patient records" description="Whether {{ $assistant }} may look up a patient when someone asks about them by name.">
        <div class="row g-3">
            @include('admin.settings.partials.field', ['key' => 'ai_read_patients', 'def' => ['label' => 'Let '.$assistant.' read patient records'] + $fields['ai_read_patients'], 'col' => 'col-12'])
        </div>
    </x-ui.section>
@endisset
@isset($fields['ai_actions_enabled'])
    <x-ui.section title="Actions" description="{{ ucfirst($assistant) }} only prepares an action and shows it as a card with every detail. It happens when the person chatting taps Confirm, never on its own, and only if their own permissions allow it.">
        <div class="row g-3">
            @include('admin.settings.partials.field', ['key' => 'ai_actions_enabled', 'def' => ['label' => 'Let '.$assistant.' take actions'] + $fields['ai_actions_enabled'], 'col' => 'col-12'])
            @foreach (['ai_actions_messages', 'ai_actions_appointments', 'ai_actions_settings'] as $key)
                @isset($fields[$key])
                    @include('admin.settings.partials.field', ['key' => $key, 'def' => $fields[$key]])
                @endisset
            @endforeach
        </div>
    </x-ui.section>
@endisset
