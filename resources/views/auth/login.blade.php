{{--
    Sign in. The big title and the line under it come from Settings > Branding
    (login_headline, login_subtext); empty settings fall back to plain copy.
--}}
@php
    $headline = trim((string) settings('login_headline'));
    $subtext = trim((string) settings('login_subtext'));
@endphp
<x-guest-layout>
    <x-slot:title>Sign in</x-slot:title>

    <h1 class="auth-title">{!! nl2br(e($headline !== '' ? $headline : 'Sign in'), false) !!}</h1>
    <p class="auth-lead">{{ $subtext !== '' ? $subtext : 'Use your clinic staff account.' }}</p>

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

        {{-- Settings > Security > "Keep me signed in" lasts (Off hides it) --}}
        @if (\App\Support\SessionPolicy::rememberEnabled())
            <x-ui.checkbox name="remember" id="remember_me"
                label="Keep me signed in on this device for {{ \App\Support\SessionPolicy::count(\App\Support\SessionPolicy::rememberDays(), 'day') }}" />
        @endif

        <x-ui.button type="submit" size="lg" block>Sign in</x-ui.button>
    </form>

    <p class="auth-note">For clinic staff only. Ask your administrator if you need an account.</p>
</x-guest-layout>
