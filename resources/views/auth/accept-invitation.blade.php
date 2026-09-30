<x-guest-layout>
    <x-slot:title>Accept your invitation</x-slot:title>

    <h1 class="auth-title">Set up your account</h1>
    <p class="auth-lead">Choose a password to finish setting up your {{ settings('app_name') ?: config('app.name') }} account. Use at least 10 characters with upper and lower case letters, a number and a symbol.</p>

    <form method="POST" action="{{ route('invitation.store') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.input name="email" id="email" type="email" label="Email address" icon="envelope"
                    :value="old('email', $email)" required autocomplete="username" inputmode="email" />

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'Password',
            'autocomplete' => 'new-password',
            'autofocus' => true,
        ])

        @include('auth.partials.password', [
            'name' => 'password_confirmation',
            'label' => 'Confirm password',
            'autocomplete' => 'new-password',
        ])

        <x-ui.button type="submit" size="lg" block>Set password</x-ui.button>

        <a href="{{ route('login') }}" class="auth-link auth-back">
            <x-ui.icon name="arrow-left" />Back to sign in
        </a>
    </form>
</x-guest-layout>
