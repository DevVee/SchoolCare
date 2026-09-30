@extends('layouts.public')

@section('title', 'Student Health Information Form')

@php
    $sexOptions   = $sexLabels ?? \App\Models\Patient::sexLabels();
    $bloodOptions = array_combine($bloodTypes, $bloodTypes) ?: [];

    // School details: grade / section / program choices follow the selected category.
    $category   = (string) old('category', '');
    $lists      = \App\Support\AcademicLists::forCategory($category !== '' ? $category : null);
    $academicFields = [
        ['name' => 'year_level',     'key' => 'levels',   'label' => 'Grade or year level',       'placeholder' => 'Select grade or year level'],
        ['name' => 'section',        'key' => 'sections', 'label' => 'Section',                   'placeholder' => 'Select section'],
        ['name' => 'program_strand', 'key' => 'programs', 'label' => 'Program, strand or course', 'placeholder' => 'Select program or strand'],
    ];

    // Error summary: each message links to its field.
    $fieldIds = ['address' => 'pat-region', 'guardian_address' => 'grd-region', 'consent' => 'f-consent'];
    $consentText = trim((string) $consentText);
@endphp

@section('content')
<div class="pub-narrow">
    <header class="pub-head">
        <h1 class="pub-title">Student Health Information Form</h1>
        <p class="pub-intro">
            Please fill in this form so the school clinic has the health details it needs to care for the student.
            A parent or guardian should fill it in for younger students.
        </p>
        <p class="pub-intro small">
            Fields marked <span class="form-required" aria-hidden="true">*</span><span class="visually-hidden">with an asterisk</span> are required.
            Clinic staff review every form before it is added to the clinic records.
        </p>
    </header>

    @if ($errors->any())
        <div class="alert-c tone-danger pub-errors" role="alert" tabindex="-1" id="formErrors">
            <x-ui.icon name="exclamation-octagon-fill" class="alert-icon" />
            <div class="alert-content">
                <p class="alert-title">
                    {{ $errors->count() === 1 ? 'Please fix 1 field before sending the form.' : 'Please fix '.$errors->count().' fields before sending the form.' }}
                </p>
                <ul>
                    @foreach ($errors->messages() as $field => $messages)
                        <li><a href="#{{ $fieldIds[$field] ?? 'f-'.$field }}" data-error-link>{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('public.health-form.store') }}" novalidate id="healthForm" class="pub-form">
        @csrf
        {{-- Honeypot: hidden from people, filled in by bots. --}}
        <div class="hp-field" aria-hidden="true">
            <label for="website">Leave this empty</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="pub-steps">
            {{-- 1. Student information --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="Student information" description="Name and details as they appear on school records." columns="2">
                    <x-ui.input name="first_name" label="First name" required autocomplete="given-name" maxlength="100" />
                    <x-ui.input name="last_name" label="Last name" required autocomplete="family-name" maxlength="100" />
                    <x-ui.input name="middle_name" label="Middle name" optional autocomplete="additional-name" maxlength="100" />
                    <x-ui.input name="suffix" label="Suffix" optional placeholder="Jr., Sr., III" maxlength="20" />
                    <x-ui.select name="sex" label="Sex" :options="$sexOptions" placeholder="Select" required />
                    <x-ui.input name="birthdate" type="date" label="Birthdate" autocomplete="bday"
                        :max="now()->subDay()->format('Y-m-d')" help="Leave empty only if unknown." />
                    <x-ui.select name="category" label="Category" :options="$categoryLabels" placeholder="Select a category" required
                        data-academic-category />
                    <x-ui.input name="student_id" label="Student or employee ID" optional maxlength="50" autocomplete="off"
                        help="School ID or LRN." />

                    <p class="pub-subhead">School details <span>The choices follow the category. Leave them empty for employees and visitors.</span></p>
                    @foreach ($academicFields as $f)
                        @php
                            $options = $lists[$f['key']] ?? [];
                            $current = (string) old($f['name'], '');
                            $hasErr  = $errors->has($f['name']);
                        @endphp
                        <x-ui.field :label="$f['label']" :name="$f['name']" optional>
                            <select name="{{ $f['name'] }}" id="f-{{ $f['name'] }}" data-academic-field="{{ $f['key'] }}"
                                    data-placeholder="{{ $f['placeholder'] }}" data-current="{{ $current }}"
                                    class="form-select @if ($hasErr) is-invalid @endif"
                                    @if ($hasErr) aria-invalid="true" aria-describedby="f-{{ $f['name'] }}-error" @endif
                                    @disabled($options === [] && $current === '')>
                                <option value="">{{ $options === [] && $current === '' ? 'Not applicable' : $f['placeholder'] }}</option>
                                @foreach ($options as $opt)
                                    <option value="{{ $opt }}" @selected($current === $opt)>{{ $opt }}</option>
                                @endforeach
                                @if ($current !== '' && ! in_array($current, $options, true))
                                    <option value="{{ $current }}" selected>{{ $current }}</option>
                                @endif
                            </select>
                        </x-ui.field>
                    @endforeach
                </x-ui.section>
                <script type="application/json" id="academicConfig">@json($academic)</script>
            </x-ui.card>

            {{-- 2. Contact and address --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="Contact and address" description="How the clinic can reach the student or the family." columns="2">
                    <x-ui.input name="contact_number" type="tel" label="Mobile number" placeholder="09XXXXXXXXX" autocomplete="tel"
                        inputmode="tel" maxlength="20" />
                    <x-ui.input name="other_contact" type="tel" label="Other contact number" optional inputmode="tel" maxlength="30" />
                    <x-ui.input name="email" type="email" label="Email address" optional autocomplete="email" maxlength="150" wrapper-class="col-full" />
                    <div class="col-full">
                        @include('patients.partials.address-picker', [
                            'addrField'  => 'address',
                            'addrValue'  => '',
                            'addrLabel'  => 'Home address',
                            'addrPrefix' => 'pat',
                        ])
                    </div>
                    <x-ui.input name="emergency_contact_name" label="Emergency contact name" maxlength="150" />
                    <x-ui.input name="emergency_contact_number" type="tel" label="Emergency contact number" placeholder="09XXXXXXXXX"
                        inputmode="tel" maxlength="20" />
                </x-ui.section>
            </x-ui.card>

            {{-- 3. Parent or guardian --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="Parent or guardian" description="Needed for students who are minors. Optional for others." columns="2">
                    <x-ui.input name="guardian_name" label="Parent or guardian name" maxlength="150" />
                    <x-ui.input name="guardian_relationship" label="Relationship" placeholder="Mother, father, guardian" maxlength="50" />
                    <x-ui.input name="guardian_contact" type="tel" label="Mobile number" placeholder="09XXXXXXXXX" inputmode="tel" maxlength="20" />
                    <x-ui.input name="guardian_facebook" label="Facebook" optional placeholder="Profile name or link" maxlength="255" />
                    <div class="col-full">
                        @include('patients.partials.address-picker', [
                            'addrField'  => 'guardian_address',
                            'addrValue'  => '',
                            'addrLabel'  => 'Parent or guardian address',
                            'addrPrefix' => 'grd',
                        ])
                    </div>
                </x-ui.section>
            </x-ui.card>

            {{-- 4. Health information --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="Health information" description="Leave a box empty if there is nothing to report." columns="2">
                    <x-ui.textarea name="allergies" label="Known allergies" rows="3" wrapper-class="col-full" maxlength="5000"
                        placeholder="Medicine, food or other allergies" />
                    <x-ui.textarea name="medical_conditions" label="Existing medical conditions" rows="3" wrapper-class="col-full" maxlength="5000"
                        placeholder="For example asthma, diabetes, heart condition" />
                    <x-ui.textarea name="current_medications" label="Current medicines" rows="3" wrapper-class="col-full" maxlength="5000"
                        placeholder="Maintenance medicines and doses" />
                    <x-ui.select name="blood_type" label="Blood type" :options="$bloodOptions" placeholder="Unknown" optional />
                    <div class="d-none d-sm-block" aria-hidden="true"></div>
                    <x-ui.input name="pediatrician_name" label="Family doctor or pediatrician" optional maxlength="150" />
                    <x-ui.input name="pediatrician_contact" type="tel" label="Doctor's contact number" optional inputmode="tel" maxlength="30" />
                    <x-ui.textarea name="notes" label="Anything else the clinic should know" rows="3" wrapper-class="col-full" optional maxlength="5000" />
                </x-ui.section>
            </x-ui.card>

            {{-- 5. Consent --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="Consent" description="Please read this statement before sending the form.">
                    @if ($consentText !== '')
                        <div class="pub-consent-text" id="consentText" tabindex="0" aria-label="Consent statement">{{ $consentText }}</div>
                    @endif
                    <div>
                        <label for="f-consent" @class(['pub-consent-check', 'mt-3' => $consentText !== '', 'is-invalid' => $errors->has('consent')])>
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" value="1" id="f-consent"
                                   required @checked(old('consent'))
                                   @error('consent') aria-invalid="true" aria-describedby="f-consent-error" @enderror>
                            <span class="pub-consent-label">
                                @if ($consentText !== '')
                                    I have read and agree to the consent statement above.
                                @else
                                    I confirm that this information is true and I agree to the school clinic keeping it in the clinic records.
                                @endif
                                <span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span>
                            </span>
                        </label>
                        <x-ui.field-error name="consent" id="f-consent-error" />
                    </div>
                </x-ui.section>

                <div class="pub-submit">
                    <p class="pub-submit-hint" id="consentHint">Tick the consent box to send the form.</p>
                    <button type="submit" class="btn btn-primary" id="healthSubmit" aria-describedby="consentHint">
                        <x-ui.icon name="send" class="me-1" />Send form
                    </button>
                </div>
                <script>
                    (function () {
                        var box = document.getElementById('f-consent');
                        var btn = document.getElementById('healthSubmit');
                        var hint = document.getElementById('consentHint');
                        function sync() {
                            var ok = box.checked;
                            btn.disabled = !ok;
                            btn.setAttribute('aria-disabled', ok ? 'false' : 'true');
                            hint.textContent = ok ? 'The clinic staff will review your form.' : 'Tick the consent box to send the form.';
                        }
                        box.addEventListener('change', sync);
                        // Back/forward cache: run after the global double-submit guard restores the buttons.
                        window.addEventListener('pageshow', function () { setTimeout(sync, 0); });
                        sync();
                    })();
                </script>
            </x-ui.card>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@include('patients.partials.address-picker-script')
<script>
(function () {
    // Error summary: focus it on load, and move focus to the field when a message is clicked.
    var summary = document.getElementById('formErrors');
    if (summary) {
        summary.focus();
        summary.querySelectorAll('[data-error-link]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var el = document.getElementById(link.getAttribute('href').slice(1));
                if (!el) return;
                e.preventDefault();
                el.scrollIntoView({ block: 'center' });
                el.focus({ preventScroll: true });
            });
        });
    }

    // School details: rebuild the grade / section / program choices when the category changes.
    var form = document.getElementById('healthForm');
    var categorySelect = form.querySelector('[data-academic-category]');
    var selects = form.querySelectorAll('[data-academic-field]');
    var config = {};
    try { config = JSON.parse(document.getElementById('academicConfig').textContent || '{}'); } catch (e) { config = {}; }

    function listsFor(category) {
        return (config.byCategory && config.byCategory[category]) || config.flat || {};
    }

    function rebuild() {
        var lists = listsFor(categorySelect ? categorySelect.value : '');
        selects.forEach(function (sel) {
            var options = lists[sel.dataset.academicField] || [];
            var previous = sel.value;
            sel.innerHTML = '';
            var blank = document.createElement('option');
            blank.value = '';
            blank.textContent = options.length ? sel.dataset.placeholder : 'Not applicable';
            sel.appendChild(blank);
            options.forEach(function (value) {
                var opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                if (value === previous) opt.selected = true;
                sel.appendChild(opt);
            });
            sel.disabled = options.length === 0;
        });
    }

    if (categorySelect) categorySelect.addEventListener('change', rebuild);
})();
</script>
@endpush
