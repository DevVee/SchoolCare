{{--
    School details section. Grade / section / program choices depend on the selected
    category (Settings, Academic: per-category lists); categories without their own
    lists use the shared lists. The script below reads the map rendered here and
    rebuilds the three selects when the category changes. Server-side validation
    uses the same lists (App\Support\AcademicLists).
--}}
@php
    $academic   = $academic ?? \App\Support\AcademicLists::clientConfig();
    $category   = old('category', $patient->category ?? '');
    $lists      = \App\Support\AcademicLists::forCategory($category ?: null);
    $currentYr  = (string) old('year_level',     $patient->year_level     ?? '');
    $currentSec = (string) old('section',        $patient->section        ?? '');
    $currentPg  = (string) old('program_strand', $patient->program_strand ?? '');
    $fields = [
        ['name' => 'year_level',     'key' => 'levels',   'label' => 'Grade / year level',        'current' => $currentYr,  'placeholder' => 'Select grade or year level'],
        ['name' => 'section',        'key' => 'sections', 'label' => 'Section',                   'current' => $currentSec, 'placeholder' => 'Select section'],
        ['name' => 'program_strand', 'key' => 'programs', 'label' => 'Program, strand or course', 'current' => $currentPg,  'placeholder' => 'Select program or strand'],
    ];
@endphp

<x-ui.section title="School details" description="Choices follow the category above. Leave them empty for employees, visitors and others.">
    <div class="form-grid form-grid-3" id="academicFields" data-academic="{{ json_encode($academic, JSON_UNESCAPED_UNICODE) }}">
        @foreach ($fields as $f)
            @php $options = $lists[$f['key']]; @endphp
            <x-ui.field :label="$f['label']" :name="$f['name']">
                <select name="{{ $f['name'] }}" id="f-{{ $f['name'] }}" data-academic-field="{{ $f['key'] }}"
                        data-placeholder="{{ $f['placeholder'] }}" data-current="{{ $f['current'] }}"
                        class="form-select @error($f['name']) is-invalid @enderror" @disabled($options === [] && $f['current'] === '')>
                    <option value="">{{ $options === [] && $f['current'] === '' ? 'Not applicable' : $f['placeholder'] }}</option>
                    @foreach ($options as $opt)
                        <option value="{{ $opt }}" @selected($f['current'] === $opt)>{{ $opt }}</option>
                    @endforeach
                    @if ($f['current'] !== '' && ! in_array($f['current'], $options, true))
                        <option value="{{ $f['current'] }}" selected>{{ $f['current'] }} (current)</option>
                    @endif
                </select>
            </x-ui.field>
        @endforeach
        @can('manage-settings')
            <p class="form-text col-full mb-0">The lists are managed in <a href="{{ route('admin.settings.edit', 'academic') }}">Settings, Academic</a>.</p>
        @endcan
    </div>
</x-ui.section>

<script>
(function () {
    const wrap = document.getElementById('academicFields');
    if (!wrap || wrap.dataset.bound) return;
    wrap.dataset.bound = '1';

    let config = {};
    try { config = JSON.parse(wrap.dataset.academic || '{}'); } catch (e) { config = {}; }

    const form = wrap.closest('form') || document;
    const categorySelect = form.querySelector('[data-academic-category]') || form.querySelector('select[name="category"]');
    const selects = wrap.querySelectorAll('[data-academic-field]');

    function listsFor(category) {
        return (config.byCategory && config.byCategory[category]) || config.flat || {};
    }

    function rebuild(keepCurrent) {
        const lists = listsFor(categorySelect ? categorySelect.value : '');
        selects.forEach(function (sel) {
            const key = sel.dataset.academicField;
            const options = lists[key] || [];
            const previous = keepCurrent ? (sel.value || sel.dataset.current || '') : sel.value;
            const keep = previous && options.indexOf(previous) !== -1;

            sel.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = options.length ? sel.dataset.placeholder : 'Not applicable';
            sel.appendChild(blank);

            options.forEach(function (value) {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                if (keep && value === previous) opt.selected = true;
                sel.appendChild(opt);
            });

            // A saved value that is not in the list stays selectable when nothing changed.
            if (keepCurrent && previous && !keep) {
                const opt = document.createElement('option');
                opt.value = previous;
                opt.textContent = previous + ' (current)';
                opt.selected = true;
                sel.appendChild(opt);
            }

            sel.disabled = options.length === 0 && !(keepCurrent && previous);
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () { rebuild(false); });
    }
})();
</script>
