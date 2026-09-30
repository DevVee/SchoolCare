{{--
    x-ui.flash-toasts: the toast stack. Render ONCE per page in the layout (end of <body>):
        <x-ui.flash-toasts />
    Converts session flashes success / error / warning / info / status (and a
    validation-error summary) into toasts shown on load by resources/js/ui/toast.js.
    It is also the container window.toast(message, type) appends to.

    Props: errors (bool, default true) adds "Please fix N highlighted fields" when validation failed.
    Note: the current layouts still render flashes as inline alerts; drop those when adding this.
--}}
@props([
    'errors' => true,
])
@php
    $icons = [
        'success' => 'check-circle-fill',
        'error' => 'exclamation-octagon-fill',
        'warning' => 'exclamation-triangle-fill',
        'info' => 'info-circle-fill',
    ];
    $messages = [];
    foreach (array_keys($icons) as $type) {
        $flash = session($type);
        foreach ((array) $flash as $msg) {
            if (is_string($msg) && trim($msg) !== '') {
                $messages[] = [$type, $msg];
            }
        }
    }
    // Laravel/Breeze "status" flashes are sometimes machine keys.
    $statusMap = [
        'password-updated' => 'Password updated.',
        'profile-updated' => 'Profile updated.',
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
    ];
    $status = session('status');
    if (is_string($status) && $status !== '') {
        $text = $statusMap[$status] ?? (str_contains($status, ' ') ? $status : null);
        if ($text) {
            $messages[] = ['success', $text];
        }
    }
    $viewErrors = $__env->shared('errors');
    if ($errors && $viewErrors && $viewErrors->any()) {
        $n = $viewErrors->count();
        $messages[] = ['error', $n === 1 ? $viewErrors->first() : "Please fix the {$n} highlighted fields."];
    }
@endphp
<div id="toastStack" {{ $attributes->class(['toast-container', 'c-toasts']) }} aria-live="polite" aria-atomic="false">
    @foreach ($messages as [$type, $text])
        <div class="toast fade toast-{{ $type }}" data-toast role="{{ $type === 'error' ? 'alert' : 'status' }}" aria-atomic="true"
             data-bs-delay="{{ $type === 'error' ? 8000 : 4000 }}" @if ($type === 'error') data-bs-autohide="false" @endif>
            <x-ui.icon :name="$icons[$type]" class="toast-icon" />
            <div class="toast-text"><p class="toast-title">{{ $text }}</p></div>
            <button type="button" class="btn-close-c btn-close-sm" data-bs-dismiss="toast" aria-label="Close"><x-ui.icon name="x-lg" /></button>
            @if ($type !== 'error')<span class="toast-progress"></span>@endif
        </div>
    @endforeach
</div>
