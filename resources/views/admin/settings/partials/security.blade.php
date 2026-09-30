{{-- Security: email sign-in codes (App\Services\SignInCodes). Status is shown above the form (security-status). --}}
<x-ui.section title="Sign-in code by email" description="After the password, people type a 6-digit code that is emailed to them. The code expires in 10 minutes.">
    <div class="row g-3">
        @isset($fields['otp_enabled'])
            @include('admin.settings.partials.field', ['key' => 'otp_enabled', 'def' => $fields['otp_enabled']])
        @endisset
        @isset($fields['otp_applies_to'])
            @include('admin.settings.partials.field', ['key' => 'otp_applies_to', 'def' => $fields['otp_applies_to'], 'col' => 'col-12 col-md-6'])
        @endisset
        @isset($fields['otp_remember_days'])
            @include('admin.settings.partials.field', ['key' => 'otp_remember_days', 'def' => $fields['otp_remember_days'], 'col' => 'col-12 col-md-6'])
        @endisset
    </div>
</x-ui.section>
