{{-- Email: sender identity. Server email status is shown above the form (email-status). --}}
<x-ui.section title="Sender" description="Who emails from the system appear to come from.">
    <div class="row g-3">
        @foreach ($fields as $key => $def)
            @include('admin.settings.partials.field', ['key' => $key, 'def' => $def])
        @endforeach
    </div>
</x-ui.section>
