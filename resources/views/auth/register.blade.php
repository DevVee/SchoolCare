{{-- Only reachable when config('auth.allow_registration') is true (routes/auth.php). --}}
<x-guest-layout>
    <x-slot:title>Create an account</x-slot:title>

    <h1 class="auth-title">Create an account</h1>
    <p class="auth-lead">For clinic staff. An administrator assigns your role after you register.</p>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <x-ui.input name="name" id="name" label="Full name" required autofocus autocomplete="name" />

        <x-ui.input name="email" id="email" type="email" label="Email address" icon="envelope"
                    required autocomplete="username" inputmode="email" />

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'Password',
            'autocomplete' => 'new-password',
        ])

        @include('auth.partials.password', [
            'name' => 'password_confirmation',
            'label' => 'Confirm password',
            'autocomplete' => 'new-password',
        ])

        <x-ui.button type="submit" size="lg" block>Create account</x-ui.button>

        <a href="{{ route('login') }}" class="auth-link auth-back">Already have an account? Sign in</a>
    </form>
</x-guest-layout>
