<x-guest-layout>
    <x-slot:title>Verify your email</x-slot:title>

    <h1 class="auth-title">Verify your email</h1>
    <p class="auth-lead">We sent a link to your email address. Open it to finish setting up your account. If it did not arrive, send a new one.</p>

    @if (session('status') === 'verification-link-sent')
        <x-ui.alert variant="success" class="mb-4">A new verification link has been sent to your email address.</x-ui.alert>
    @endif

    <div class="auth-form">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" size="lg" block>Send a new link</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="lg" block>Sign out</x-ui.button>
        </form>
    </div>
</x-guest-layout>
