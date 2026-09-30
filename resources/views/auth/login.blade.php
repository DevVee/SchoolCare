<x-guest-layout>
    <x-slot:title>Sign in</x-slot:title>

    <h1 class="auth-title">Sign in</h1>
    <p class="auth-lead">Use your clinic staff account.</p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <x-ui.input name="email" id="email" type="email" label="Email address" icon="envelope"
                    required autofocus autocomplete="username" inputmode="email" />

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'Password',
            'autocomplete' => 'current-password',
            'aside' => Route::has('password.request')
                ? '<a href="'.e(route('password.request')).'" class="auth-link">Forgot password?</a>'
                : null,
        ])

        <x-ui.checkbox name="remember" id="remember_me" label="Keep me signed in on this device" />

        <x-ui.button type="submit" size="lg" block>Sign in</x-ui.button>
    </form>

    <p class="auth-note">
        <x-ui.icon name="shield-lock" />
        For clinic staff only. Ask your administrator if you need an account.
    </p>
</x-guest-layout>
