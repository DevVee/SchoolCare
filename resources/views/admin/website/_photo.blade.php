{{--
    Image upload with a live preview and a "remove" box (Website page photo, service picture, team photo).
    Expects: $name (file input name), $label, $url (current image URL or ''), $removeName, $removeLabel, $emptyText.
    Optional: $help, $shape (wide | square | round), $col (wrapper class).
--}}
@php
    $shape = $shape ?? 'square';
    $inputId = 'f-'.$name;
    $hasErr = $errors->has($name);
    $url = (string) ($url ?? '');
@endphp
<x-ui.field :class="$col ?? null" :label="$label" :name="$name" :for="$inputId" :help="$help ?? null" optional>
    <div class="wa-photo" data-photo-field>
        <div @class(['wa-photo-preview', 'is-'.$shape])>
            <img src="{{ $url }}" alt="{{ $url !== '' ? 'Current '.strtolower($label) : '' }}" data-photo-preview @if ($url === '') hidden @endif
                 onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
            <span class="wa-photo-empty" @if ($url !== '') hidden @endif aria-hidden="true"><x-ui.icon :name="$shape === 'round' ? 'person' : 'image'" /></span>
        </div>
        <div class="wa-photo-body">
            <input type="file" name="{{ $name }}" id="{{ $inputId }}" data-photo-input
                   @class(['form-control', 'is-invalid' => $hasErr])
                   accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                   @if ($hasErr) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif>
            @if ($url !== '')
                <div class="mt-2">
                    <x-ui.checkbox :name="$removeName" :id="$inputId.'-remove'" :label="$removeLabel" data-photo-remove />
                </div>
            @else
                <p class="wa-photo-note">{{ $emptyText }}</p>
            @endif
        </div>
    </div>
</x-ui.field>

@once
    @push('scripts')
    <script>
    (function () {
        function init() {
            document.querySelectorAll('[data-photo-field]').forEach(function (wrap) {
                var input = wrap.querySelector('[data-photo-input]');
                var img = wrap.querySelector('[data-photo-preview]');
                var empty = wrap.querySelector('.wa-photo-empty');
                var remove = wrap.querySelector('[data-photo-remove]');
                var original = img ? img.getAttribute('src') : '';
                if (!input || !img) return;

                input.addEventListener('change', function () {
                    var file = input.files && input.files[0];
                    if (!file) return;
                    img.src = URL.createObjectURL(file);
                    img.alt = 'Selected image';
                    img.hidden = false;
                    if (empty) empty.hidden = true;
                    if (remove) remove.checked = false;
                });

                if (remove) {
                    remove.addEventListener('change', function () {
                        var gone = remove.checked;
                        if (gone) input.value = '';
                        img.hidden = gone || !original;
                        if (!gone && original) img.src = original;
                        if (empty) empty.hidden = !img.hidden;
                    });
                }
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
    </script>
    @endpush
@endonce
