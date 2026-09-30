{{-- Academic lists: dropdown choices in the patient registration form. --}}
@php
    $lists = array_filter(['year_levels', 'sections', 'program_strands'], fn ($k) => isset($fields[$k]));
    $advancedKey = 'academic_levels_by_category';
    $categoryOptions = settings()->options('patient_categories');
    $perCategory = (string) old($advancedKey, (string) $settings->get($advancedKey, ''));
@endphp

<x-ui.section title="General lists" description="These appear as dropdown choices when you add or edit a patient.">
    <div class="row g-3">
        @foreach ($lists as $key)
            @include('admin.settings.partials.field', ['key' => $key, 'def' => $fields[$key], 'col' => 'col-12 col-md-6 col-xxl-4', 'rows' => 10])
        @endforeach
    </div>
</x-ui.section>

@isset($fields[$advancedKey])
    <x-ui.section title="Choices per patient category" description="Optional. Show only the year levels, sections and programs that fit a category, for example Grade 11 and 12 for Senior High School.">
        <x-ui.field :name="$advancedKey" :for="'setting_'.$advancedKey">
            {{-- Category editor (resources/js/ui/list-editor.js). The textarea keeps the stored format and is what gets saved. --}}
            <div class="category-lists-root" data-category-lists data-categories='@json($categoryOptions)'>
                <textarea name="{{ $advancedKey }}" id="setting_{{ $advancedKey }}" rows="12" spellcheck="false"
                          @class(['form-control', 'font-monospace', 'fs-sm', 'is-invalid' => $errors->has($advancedKey)])
                          aria-label="Choices per patient category">{{ $perCategory }}</textarea>
            </div>
        </x-ui.field>
    </x-ui.section>
@endisset
