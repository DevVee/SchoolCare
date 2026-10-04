{{--
    Generic settings field renderer.
    Expects: $key, $def (from config/settings.php), $settings (SettingsService).
    Optional: $col (grid column classes), $rows (textarea rows).
--}}
@php
    $type = $def['type'];
    $id = 'setting_'.$key;
    $col = $col ?? (in_array($type, ['text', 'json_list', 'options', 'image', 'boolean'], true) ? 'col-12' : 'col-12 col-md-6');
    $label = $def['label'] ?? $key;
    $help = ($def['help'] ?? '') !== '' ? $def['help'] : null;
    $required = in_array('required', $def['rules'] ?? [], true);
    $max = null;
    foreach ($def['rules'] ?? [] as $rule) {
        if (is_string($rule) && preg_match('/^max:(\d+)$/', $rule, $m)) {
            $max = (int) $m[1];
        }
    }

    $value = match ($type) {
        'boolean' => (bool) $settings->get($key),
        'json_list', 'options' => $settings->toText($key),
        'image' => (string) $settings->get($key, ''),
        'secret' => '', // never put a stored secret on the page
        default => (string) $settings->get($key, ''),
    };

    $selectOptions = [];
    if ($type === 'select') {
        $selectOptions = $def['options'] ?? [];
        if ($selectOptions === 'timezones') {
            $list = timezone_identifiers_list();
            $selectOptions = array_combine($list, $list);
        }
        // Keep a stored value selectable even if it is no longer offered.
        if ($value !== '' && ! array_key_exists($value, $selectOptions)) {
            $selectOptions = [$value => $value.' (current)'] + $selectOptions;
        }
    }
    $hasErr = $errors->has($key);
@endphp

@if ($key === 'clinic_weekly_hours')
    @include('admin.settings.partials.weekly-hours')

@elseif ($type === 'boolean')
    <div class="{{ $col }}">
        <x-ui.switch :name="$key" :id="$id" :label="$label" :description="$help" :checked="$value" />
    </div>

@elseif ($type === 'image')
    @php
        $isFavicon = $key === 'brand_favicon';
        $src = $value !== '' ? $settings->imageUrl($key) : '';
    @endphp
    <div class="{{ $col }}">
        <x-ui.field :label="$label" :name="$key" :for="$id" :help="$help">
            <div class="image-field" data-image-field>
                <div class="image-field-preview">
                    <img src="{{ $src }}" alt="{{ $src !== '' ? 'Current '.strtolower($label) : '' }}" width="64" height="64" data-image-preview
                         @if ($src === '') hidden @endif
                         onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                    <span class="image-field-empty" @if ($src !== '') hidden @endif aria-hidden="true"><x-ui.icon name="image" /></span>
                </div>
                <div class="image-field-body">
                    <input type="file" name="{{ $key }}" id="{{ $id }}" data-image-input
                           @class(['form-control', 'is-invalid' => $hasErr])
                           @if ($hasErr) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                           accept="{{ $isFavicon ? '.png,.ico,image/png,image/x-icon' : '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp' }}">
                    @if ($value !== '')
                        <div class="mt-2">
                            <x-ui.checkbox :name="'remove_'.$key" :id="$id.'_remove'" label="Remove it and use the default" />
                        </div>
                    @else
                        <p class="text-muted fs-sm mb-0 mt-1">{{ $isFavicon ? 'None uploaded. The school logo or the default icon is used.' : 'None uploaded. The default logo is used.' }}</p>
                    @endif
                </div>
            </div>
        </x-ui.field>
    </div>

@elseif ($type === 'text')
    <x-ui.textarea :wrapper-class="$col" :name="$key" :id="$id" :label="$label" :help="$help" :required="$required"
        :value="$value" :rows="$rows ?? 3" :maxlength="$max" :class="! empty($mono) ? 'font-monospace fs-sm' : null" :spellcheck="! empty($mono) ? 'false' : null" />

@elseif (in_array($type, ['json_list', 'options'], true))
    {{-- Friendly list editor (resources/js/ui/list-editor.js). The textarea keeps the stored format and is what gets saved. --}}
    @php $listText = (string) old($key, $value); @endphp
    <div class="{{ $col }}">
        <x-ui.field :label="$label" :name="$key" :for="$id" :help="$help" :required="$required">
            <div class="list-editor" data-list-editor data-mode="{{ $type === 'options' ? 'options' : 'list' }}"
                 data-label="{{ $label }}" data-noun="{{ $def['noun'] ?? 'choice' }}" data-nouns="{{ ($def['noun'] ?? 'choice').'s' }}">
                <textarea name="{{ $key }}" id="{{ $id }}" rows="{{ $rows ?? min(12, max(4, substr_count($listText, "\n") + 2)) }}"
                          @class(['form-control', 'is-invalid' => $hasErr]) spellcheck="false"
                          @if ($hasErr) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ $listText }}</textarea>
            </div>
        </x-ui.field>
    </div>

@elseif ($type === 'secret')
    {{-- Write-only: shows whether a value is saved and its last 4 characters, never the value. --}}
    @php
        $saved = (string) $settings->get($key, '');
        $tail = $saved !== '' ? mb_substr($saved, -4) : '';
        unset($saved);
    @endphp
    <div class="{{ $col }}">
        <x-ui.field :label="$label" :name="$key" :for="$id" :help="$help">
            <p class="fs-sm mb-2 {{ $tail !== '' ? 'text-ink-2' : 'text-muted' }}" id="{{ $id }}-state">
                {{ $tail !== '' ? 'Set, ends in ...'.$tail.'. Type a new key to replace it.' : 'Not set.' }}
            </p>
            <input type="password" name="{{ $key }}" id="{{ $id }}" value="" autocomplete="new-password" spellcheck="false"
                   @class(['form-control', 'is-invalid' => $hasErr]) placeholder="{{ $tail !== '' ? 'Leave empty to keep the saved key' : 'Paste the key' }}"
                   @if ($max) maxlength="{{ $max }}" @endif
                   aria-describedby="{{ $id }}-state{{ $hasErr ? ' '.$id.'-error' : '' }}" @if ($hasErr) aria-invalid="true" @endif>
            @if ($tail !== '')
                <div class="mt-2">
                    <x-ui.checkbox :name="'remove_'.$key" :id="$id.'_remove'" label="Remove the saved key" />
                </div>
            @endif
        </x-ui.field>
    </div>

@elseif ($type === 'select')
    <x-ui.select :wrapper-class="$col" :name="$key" :id="$id" :label="$label" :help="$help" :required="$required"
        :options="$selectOptions" :selected="$value" />

@elseif ($type === 'color')
    @php $hex = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) old($key, $value)) ? old($key, $value) : ($def['default'] ?? '#2563EB'); @endphp
    <div class="{{ $col }}">
        <x-ui.field :label="$label" :name="$key" :for="$id" :help="$help" :required="$required">
            <div class="d-flex align-items-center gap-2 color-field">
                <input type="color" class="form-control form-control-color" value="{{ $hex }}" aria-label="{{ $label }}: pick a colour" title="Pick a colour" data-color-picker="{{ $id }}">
                <input type="text" name="{{ $key }}" id="{{ $id }}" value="{{ old($key, $value) }}" maxlength="7" data-color-text
                       @class(['form-control', 'is-invalid' => $hasErr]) placeholder="#2563EB" style="max-width: 140px;"
                       @if ($hasErr) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
            </div>
        </x-ui.field>
    </div>

@elseif ($type === 'integer')
    <x-ui.input :wrapper-class="$col" type="number" step="1" :name="$key" :id="$id" :label="$label" :help="$help" :required="$required"
        :value="$value" inputmode="numeric" style="max-width: 220px;" />

@else
    <x-ui.input :wrapper-class="$col" :type="$type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')" :name="$key" :id="$id"
        :label="$label" :help="$help" :required="$required" :value="$value" :maxlength="$max" />
@endif
