{{--
    Password field with a show/hide toggle (resources/js/ui/password-toggle.js).
    @include('auth.partials.password', ['name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password'])
    Optional: id, required (default true), autofocus, help, aside (HTML placed right of the label, e.g. a "Forgot password?" link).
--}}
@php
    $id = $id ?? $name;
    $hasError = $errors->has($name);
    $describedBy = $hasError ? $id.'-error' : (! empty($help) ? $id.'-help' : null);
@endphp
<div class="c-field">
    <div class="auth-label-row">
        <x-ui.label :for="$id" :value="$label" />
        @if (! empty($aside)){!! $aside !!}@endif
    </div>
    <div class="input-icon password-field">
        <x-ui.icon name="lock" />
        <input id="{{ $id }}" type="password" name="{{ $name }}"
               @class(['form-control', 'is-invalid' => $hasError])
               autocomplete="{{ $autocomplete ?? 'current-password' }}"
               @if ($required ?? true) required @endif
               @if (! empty($autofocus)) autofocus @endif
               @if ($hasError) aria-invalid="true" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
        <button type="button" class="password-toggle" data-password-toggle="{{ $id }}"
                aria-label="Show password" aria-pressed="false" aria-controls="{{ $id }}">
            <x-ui.icon name="eye" />
        </button>
    </div>
    @if (! empty($help) && ! $hasError)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
    <x-ui.field-error :name="$name" :id="$id.'-error'" />
</div>
