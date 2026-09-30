<x-guest-layout>
    <x-slot:title>Reset your password</x-slot:title>

    <h1 class="auth-title">Reset your password</h1>
    <p class="auth-lead">Enter the email address on your account. We will send you a link to choose a new password.</p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <x-ui.input name="email" id="email" type="email" label="Email address" icon="envelope"
                    required autofocus autocomplete="email" inputmode="email" />

        <x-ui.button type="submit" size="lg" block>Send reset link</x-ui.button>

        <a href="{{ route('login') }}" class="auth-link auth-back">
            <x-ui.icon name="arrow-left" />Back to sign in
        </a>
    </form>
</x-guest-layout>
