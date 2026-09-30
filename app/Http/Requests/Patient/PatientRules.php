<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
use App\Support\AcademicLists;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One rule set for every way a patient record is written: the staff create
 * and edit forms, the spreadsheet import and the public health form review.
 */
class PatientRules
{
    /**
     * @param  Patient|null  $current  the record being edited (its stored choice values stay valid)
     */
    public static function rules(?Patient $current = null): array
    {
        $keep = fn (?string $value) => $value !== null && $value !== '' ? [$value] : [];

        return [
            // Personal
            'category'          => ['required', 'string', Rule::in([...Patient::categories(), ...$keep($current?->category)])],
            'student_id'        => [
                'nullable', 'string', 'max:50',
                Rule::unique('patients', 'student_id')->whereNull('deleted_at')->ignore($current?->id),
            ],
            'first_name'        => ['required', 'string', 'max:100'],
            'middle_name'       => ['nullable', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'suffix'            => ['nullable', 'string', 'max:20'],
            'sex'               => ['required', 'string', Rule::in([...array_keys(Patient::sexLabels()), ...$keep($current?->sex)])],
            // Optional: records imported from SSCMS have no birthdate.
            'birthdate'         => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'contact_number'    => ['nullable', 'string', 'max:20'],
            'other_contact'     => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'address'           => ['nullable', 'string', 'max:500'],
            'emergency_contact_name'   => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:20'],

            // Academic (checked against the category's lists in after())
            'year_level'        => ['nullable', 'string', 'max:50'],
            'program_strand'    => ['nullable', 'string', 'max:100'],
            'section'           => ['nullable', 'string', 'max:50'],

            // Guardian
            'guardian_name'         => ['nullable', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_contact'      => ['nullable', 'string', 'max:20'],
            'guardian_facebook'     => ['nullable', 'string', 'max:255'],
            'guardian_address'      => ['nullable', 'string', 'max:500'],

            // Medical
            'blood_type'           => ['nullable', 'string', Rule::in([...Patient::bloodTypes(), ...$keep($current?->blood_type)])],
            'pediatrician_name'    => ['nullable', 'string', 'max:150'],
            'pediatrician_contact' => ['nullable', 'string', 'max:30'],
            'allergies'            => ['nullable', 'string', 'max:5000'],
            'medical_conditions'   => ['nullable', 'string', 'max:5000'],
            'current_medications'  => ['nullable', 'string', 'max:5000'],
            'notes'                => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Validation closure: year level / section / program must fit the category. */
    public static function academicCheck(callable $input, ?Patient $current = null): \Closure
    {
        return function (Validator $validator) use ($input, $current) {
            if ($validator->errors()->has('category')) {
                return;
            }
            $values = [
                'year_level'     => $input('year_level'),
                'section'        => $input('section'),
                'program_strand' => $input('program_strand'),
            ];
            $stored = $current && $current->category === $input('category')
                ? $current->only(['year_level', 'section', 'program_strand'])
                : [];

            foreach (AcademicLists::errors($input('category'), $values, $stored) as $attr => $message) {
                $validator->errors()->add($attr, $message);
            }
        };
    }

    public static function messages(): array
    {
        return [
            'category.required'   => 'Please select a patient category.',
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'sex.required'        => 'Sex is required.',
            'birthdate.before'    => 'Birthdate must be in the past.',
            'student_id.unique'   => 'Another patient already has this student / employee ID.',
        ];
    }

    /** Attributes collected by the forms (and accepted from imports). */
    public static function fields(): array
    {
        return array_keys(self::rules());
    }
}
