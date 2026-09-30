{{-- Test email to the signed-in administrator. --}}
<x-ui.card title="Send a test email" :subtitle="'Sends a short email to '.auth()->user()->email.'. Save your changes first, because the test uses the saved settings.'">
    <form method="POST" action="{{ route('admin.settings.test-email') }}">
        @csrf
        <x-ui.button type="submit" variant="secondary" icon="send">Send test email</x-ui.button>
    </form>
</x-ui.card>
