<x-guest-layout>
    <x-slot:title>Choose a new password</x-slot:title>

    <h1 class="auth-title">Choose a new password</h1>
    <p class="auth-lead">Use at least 10 characters with upper and lower case letters, a number and a symbol.</p>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.input name="email" id="email" type="email" label="Email address" icon="envelope"
                    :value="$request->email" required autocomplete="username" inputmode="email" />

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'New password',
            'autocomplete' => 'new-password',
            'autofocus' => true,
        ])

        @include('auth.partials.password', [
            'name' => 'password_confirmation',
            'label' => 'Confirm new password',
            'autocomplete' => 'new-password',
        ])

        <x-ui.button type="submit" size="lg" block>Save new password</x-ui.button>

        <a href="{{ route('login') }}" class="auth-link auth-back">
            <x-ui.icon name="arrow-left" />Back to sign in
        </a>
    </form>
</x-guest-layout>
