{{-- Personal details + contact and address sections (patients.create / patients.edit) --}}
@php
    $categoryLabels = $categoryLabels ?? \App\Models\Patient::categoryLabels();
    if (! empty($patient?->category) && ! isset($categoryLabels[$patient->category])) {
        $categoryLabels[$patient->category] = \Illuminate\Support\Str::headline($patient->category);
    }
    $sexOptions = \App\Models\Patient::sexLabels();
    if (! empty($patient?->sex) && ! isset($sexOptions[$patient->sex])) {
        $sexOptions[$patient->sex] = ucfirst($patient->sex);
    }
@endphp

<x-ui.section title="Personal details" description="Name and details as they appear on school records." columns="3" id="personal">
    <x-ui.select name="category" label="Category" :options="$categoryLabels" placeholder="Select a category" required
        :selected="$patient->category ?? null" data-academic-category />
    <x-ui.input name="student_id" label="Student or employee ID" maxlength="50" autocomplete="off" optional
        :value="$patient->student_id ?? null" help="School ID or LRN. Used to spot duplicate records." />
    <x-ui.select name="sex" label="Sex" :options="$sexOptions" placeholder="Select" required :selected="$patient->sex ?? null" />

    <x-ui.input name="first_name" label="First name" required autocomplete="given-name" :value="$patient->first_name ?? null" />
    <x-ui.input name="middle_name" label="Middle name" optional autocomplete="additional-name" :value="$patient->middle_name ?? null" />
    <x-ui.input name="last_name" label="Last name" required autocomplete="family-name" :value="$patient->last_name ?? null" />

    <x-ui.input name="suffix" label="Suffix" optional placeholder="Jr., Sr., III" :value="$patient->suffix ?? null" />
    <x-ui.input name="birthdate" type="date" label="Birthdate" autocomplete="bday" :value="$patient->birthdate ?? null"
        :max="now()->subDay()->format('Y-m-d')" help="Recommended. Leave empty only if unknown." />
</x-ui.section>

<x-ui.section title="Contact and address" description="How the clinic can reach the patient or their family." columns="3">
    <x-ui.input name="contact_number" type="tel" label="Contact number" placeholder="09XXXXXXXXX" autocomplete="tel" :value="$patient->contact_number ?? null" />
    <x-ui.input name="other_contact" type="tel" label="Other contact number" optional :value="$patient->other_contact ?? null" />
    <x-ui.input name="email" type="email" label="Email address" optional autocomplete="email" :value="$patient->email ?? null" />

    <div class="col-full">
        @include('patients.partials.address-picker', [
            'addrField'  => 'address',
            'addrValue'  => $patient->address ?? '',
            'addrLabel'  => 'Home address',
            'addrPrefix' => 'pat',
        ])
    </div>

    <x-ui.input name="emergency_contact_name" label="Emergency contact name" :value="$patient->emergency_contact_name ?? null" />
    <x-ui.input name="emergency_contact_number" type="tel" label="Emergency contact number" placeholder="09XXXXXXXXX" :value="$patient->emergency_contact_number ?? null" />
</x-ui.section>
