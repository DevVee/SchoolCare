{{--
    One field of the Website settings group (config/settings.php, group "landing").
    Expects: $key, $fields, $settings (SettingsService), $fallbacks (LandingContent::fallbacks()).
    Optional: $col (grid wrapper class), $rows (textarea rows), $placeholder.
    When the field has a fallback (text used when it is left empty), that text is shown as the placeholder.
--}}
@php
    $def = $fields[$key] ?? null;
@endphp
@if ($def)
    @php
        $type = $def['type'];
        $id = 'setting_'.$key;
        $label = $def['label'] ?? $key;
        $help = ($def['help'] ?? '') !== '' ? $def['help'] : null;
        $required = in_array('required', $def['rules'] ?? [], true);
        $max = null;
        foreach ($def['rules'] ?? [] as $rule) {
            if (is_string($rule) && preg_match('/^max:(\d+)$/', $rule, $m)) {
                $max = (int) $m[1];
            }
        }
        $hint = $placeholder ?? ($fallbacks[$key] ?? null);
        $col = $col ?? null;
    @endphp

    @if ($type === 'boolean')
        <div @class([$col])>
            <x-ui.switch :name="$key" :id="$id" :label="$label" :description="$help" :checked="(bool) $settings->get($key)" />
        </div>
    @elseif ($type === 'text')
        <x-ui.textarea :wrapper-class="$col" :name="$key" :id="$id" :label="$label" :help="$help" :required="$required"
            :value="(string) $settings->get($key, '')" :rows="$rows ?? 3" :maxlength="$max" :placeholder="$hint" />
    @elseif ($type === 'select')
        @php
            $options = (array) ($def['options'] ?? []);
            $current = (string) $settings->get($key, '');
            if ($current !== '' && ! array_key_exists($current, $options)) {
                $options = [$current => $current] + $options;
            }
        @endphp
        <x-ui.select :wrapper-class="$col" :name="$key" :id="$id" :label="$label" :help="$help" :required="$required"
            :options="$options" :selected="$current" />
    @else
        <x-ui.input :wrapper-class="$col" :type="$type === 'url' ? 'url' : ($type === 'email' ? 'email' : 'text')" :name="$key" :id="$id"
            :label="$label" :help="$help" :required="$required" :value="(string) $settings->get($key, '')" :maxlength="$max"
            :placeholder="$hint ?: ($type === 'url' ? 'https://' : null)" />
    @endif
@endif
