{{-- Test email to the signed-in administrator, or to any address typed here (e.g. a Gmail inbox, to see where it lands). --}}
<x-ui.card title="Send a test email" subtitle="Save your changes first, because the test uses the saved settings. Try a Gmail or Yahoo address too: they are the strictest about unauthenticated senders.">
    <form method="POST" action="{{ route('admin.settings.test-email') }}" class="d-flex flex-wrap align-items-end gap-2">
        @csrf
        <div class="flex-grow-1" style="max-width: 360px;">
            <x-ui.input name="test_to" type="email" label="Send to" :value="old('test_to', auth()->user()->email)" placeholder="name@example.com" />
        </div>
        <x-ui.button type="submit" variant="secondary" icon="send">Send test email</x-ui.button>
    </form>
</x-ui.card>
