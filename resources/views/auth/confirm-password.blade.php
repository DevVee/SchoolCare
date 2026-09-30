<x-guest-layout>
    <x-slot:title>Confirm your password</x-slot:title>

    <h1 class="auth-title">Confirm your password</h1>
    <p class="auth-lead">This area holds sensitive information. Enter your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'Password',
            'autocomplete' => 'current-password',
            'autofocus' => true,
        ])

        <x-ui.button type="submit" size="lg" block>Continue</x-ui.button>
    </form>
</x-guest-layout>
