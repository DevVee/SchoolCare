{{--
    Email sign-in code, the second step of signing in (SignInCodeController).
    Six one-digit boxes that move on as you type and take a pasted code
    (resources/js/ui/otp-input.js). The boxes have no name: the script copies
    the digits into the `code` input. Without JavaScript that single input is
    shown instead of the boxes, and "Resend code" is checked on the server.
--}}
<x-guest-layout>
    <x-slot:title>Check your email</x-slot:title>

    <h1 class="auth-title">Check your email</h1>
    <p class="auth-lead">We sent a 6-digit code to <span class="auth-lead-email">{{ $maskedEmail }}</span></p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php $hasError = $errors->has('code'); @endphp

    <form method="POST" action="{{ route('login.code.verify') }}" class="auth-form otp-form" data-otp-form>
        @csrf

        <div class="c-field otp-field">
            <label for="code" class="visually-hidden">Sign-in code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                   pattern="[0-9 ]*" maxlength="7" spellcheck="false" placeholder="6-digit code"
                   @class(['form-control', 'otp-single', 'is-invalid' => $hasError])
                   required autofocus
                   @if ($hasError) aria-invalid="true" aria-describedby="code-error" @endif>

            <div class="otp-boxes" role="group" aria-label="Sign-in code" data-otp-boxes hidden>
                @for ($i = 1; $i <= 6; $i++)
                    <input type="text" inputmode="numeric" pattern="[0-9]*" spellcheck="false"
                           autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}"
                           @class(['otp-box', 'is-invalid' => $hasError])
                           aria-label="Digit {{ $i }} of 6" data-otp-box>
                @endfor
            </div>

            <x-ui.field-error name="code" id="code-error" />
        </div>

        @if ($rememberDays > 0)
            <x-ui.checkbox name="remember_device" id="remember_device"
                           :label="'Remember this device for '.$rememberDays.' '.\Illuminate\Support\Str::plural('day', $rememberDays)" />
        @endif

        <x-ui.button type="submit" size="lg" block>Verify</x-ui.button>
    </form>

    <div class="otp-links">
        <form method="POST" action="{{ route('login.code.resend') }}" class="otp-resend">
            @csrf
            <span>Didn't get it?</span>
            <button type="submit" class="otp-resend-btn" data-otp-resend data-wait="{{ $resendIn }}">Resend code</button>
        </form>
        <a href="{{ route('login') }}" class="auth-link">Use a different account</a>
    </div>

    <p class="auth-note">
        <x-ui.icon name="shield-lock" />
        The code works once and expires in {{ $minutes }} minutes. If the email is not in your inbox, check the spam folder.
    </p>
</x-guest-layout>
