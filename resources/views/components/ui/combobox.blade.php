{{--
    x-ui.combobox: type-to-search picker backed by a JSON endpoint (behaviour: resources/js/ui/combobox.js).
    The form submits a hidden input holding the chosen id, so validation and saving work
    exactly as they did with a <select>.

    <x-ui.combobox name="patient_id" label="Patient" :source="route('patients.lookup')"
        :selected="$patient?->toPickerItem()" placeholder="Search by name or ID number" required />

    source:   GET {source}?q=...  ->  {"results": [{"id", "label", "detail", "meta", ...}], "more": bool}
    selected: the pre-selected item in the same shape (or null)

    Page scripts: listen for `combobox:change` on the [data-combobox] wrapper (event.detail.item,
    null when cleared); the hidden input also fires a bubbling `change`. The current item is
    mirrored as JSON in data-combobox-item, readable before the module runs.
    For patients use x-ui.patient-picker, which fills in the source and the pre-selected patient.
--}}
@props([
    'name',
    'source',
    'selected' => null,
    'label' => null,
    'id' => null,
    'placeholder' => 'Search',
    'help' => null,
    'required' => false,
    'optional' => false,
    'minChars' => 2,
    'nouns' => 'results',          // "No {nouns} match 'xyz'."
    'hint' => null,                // shown while fewer than minChars characters are typed
    'clearLabel' => 'Clear selection',
    'wrapperClass' => null,
    'bag' => 'default',
])
@php
    $errorKey    = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $inputId     = $id ?? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $hasError    = isset($errors) && $errors->getBag($bag)->has($errorKey);
    $describedBy = $hasError ? $inputId.'-error' : ($help ? $inputId.'-help' : null);
    $selected    = is_array($selected) && filled($selected['id'] ?? null) ? $selected : null;
    $hint      ??= "Type at least {$minChars} characters to search.";
@endphp
<x-ui.field :label="$label" :name="$name" :for="$inputId" :required="$required" :optional="$optional"
    :help="$help" :bag="$bag" :class="$wrapperClass">
    <div {{ $attributes->class(['combobox', 'has-selection' => $selected]) }} data-combobox
         data-source="{{ $source }}" data-min-chars="{{ (int) $minChars }}" data-nouns="{{ $nouns }}" data-hint="{{ $hint }}"
         data-combobox-item="{{ $selected ? json_encode($selected, JSON_UNESCAPED_UNICODE) : '' }}">
        <input type="hidden" name="{{ $name }}" value="{{ $selected['id'] ?? '' }}" autocomplete="off" data-combobox-value>

        <div class="combobox-field" @if ($selected) hidden @endif>
            <x-ui.icon name="search" class="combobox-icon" />
            <input type="text" id="{{ $inputId }}"
                   @class(['form-control', 'combobox-input', 'is-invalid' => $hasError])
                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $inputId }}-listbox"
                   autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search"
                   placeholder="{{ $placeholder }}"
                   @if ($hasError) aria-invalid="true" @endif
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
            <span class="combobox-spinner spinner-border spinner-border-sm" aria-hidden="true"></span>
        </div>

        <div class="combobox-selection" data-combobox-selection tabindex="-1" role="group" aria-label="{{ $label ? 'Selected '.\Illuminate\Support\Str::lower($label) : 'Selected' }}"
             @unless ($selected) hidden @endunless>
            <span class="combobox-text">
                <span class="combobox-label" data-combobox-selection-label>{{ $selected['label'] ?? '' }}</span>
                <span class="combobox-detail" data-combobox-selection-detail @if (blank($selected['detail'] ?? null)) hidden @endif>{{ $selected['detail'] ?? '' }}</span>
            </span>
            <span class="combobox-meta tabular" data-combobox-selection-meta @if (blank($selected['meta'] ?? null)) hidden @endif>{{ $selected['meta'] ?? '' }}</span>
            <button type="button" class="combobox-clear" data-combobox-clear aria-label="{{ $clearLabel }}" title="{{ $clearLabel }}">
                <x-ui.icon name="x-lg" />
            </button>
        </div>

        <div class="combobox-menu" data-combobox-menu hidden>
            <ul class="combobox-list" role="listbox" id="{{ $inputId }}-listbox" @if ($label) aria-label="{{ $label }}" @endif></ul>
            <p class="combobox-message" data-combobox-message hidden></p>
        </div>

        <div class="visually-hidden" role="status" aria-live="polite" data-combobox-status></div>
    </div>
</x-ui.field>
