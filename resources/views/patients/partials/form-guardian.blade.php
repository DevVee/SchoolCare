{{-- Guardian / parent section (patients.create / patients.edit) --}}
<x-ui.section title="Guardian or parent" description="Needed for students who are minors (Kinder, Daycare, Elementary, Junior High). Optional for others." columns="2">
    <x-ui.input name="guardian_name" label="Guardian or parent name" :value="$patient->guardian_name ?? null" />
    <x-ui.input name="guardian_relationship" label="Relationship" placeholder="e.g. Mother, Father, Legal guardian" :value="$patient->guardian_relationship ?? null" />
    <x-ui.input name="guardian_contact" type="tel" label="Guardian contact number" placeholder="09XXXXXXXXX" :value="$patient->guardian_contact ?? null" />
    <x-ui.input name="guardian_facebook" label="Guardian Facebook" optional placeholder="Profile name or link" :value="$patient->guardian_facebook ?? null" />

    <div class="col-full">
        @include('patients.partials.address-picker', [
            'addrField'  => 'guardian_address',
            'addrValue'  => $patient->guardian_address ?? '',
            'addrLabel'  => 'Guardian address',
            'addrPrefix' => 'grd',
        ])
    </div>
</x-ui.section>
