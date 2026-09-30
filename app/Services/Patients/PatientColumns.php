<?php

namespace App\Services\Patients;

/**
 * Spreadsheet columns shared by the patient export, the import template and
 * the import header matcher. Header cells are the snake_case keys so an
 * exported file can be edited and imported again.
 */
class PatientColumns
{
    /** key => human label */
    public const COLUMNS = [
        'patient_number'           => 'Patient No.',
        'student_id'               => 'Student / Employee ID',
        'last_name'                => 'Last Name',
        'first_name'               => 'First Name',
        'middle_name'              => 'Middle Name',
        'suffix'                   => 'Suffix',
        'category'                 => 'Category',
        'sex'                      => 'Sex',
        'birthdate'                => 'Birthdate (YYYY-MM-DD)',
        'contact_number'           => 'Contact Number',
        'other_contact'            => 'Other Contact',
        'email'                    => 'Email',
        'address'                  => 'Address',
        'year_level'               => 'Year Level / Grade',
        'section'                  => 'Section',
        'program_strand'           => 'Program / Strand',
        'guardian_name'            => 'Guardian Name',
        'guardian_relationship'    => 'Guardian Relationship',
        'guardian_contact'         => 'Guardian Contact',
        'guardian_facebook'        => 'Guardian Facebook',
        'guardian_address'         => 'Guardian Address',
        'emergency_contact_name'   => 'Emergency Contact Name',
        'emergency_contact_number' => 'Emergency Contact Number',
        'blood_type'               => 'Blood Type',
        'pediatrician_name'        => 'Pediatrician Name',
        'pediatrician_contact'     => 'Pediatrician Contact',
        'allergies'                => 'Allergies',
        'medical_conditions'       => 'Medical Conditions',
        'current_medications'      => 'Current Medications',
        'notes'                    => 'Notes',
    ];

    /** Columns an import file must contain (SSCMS required the same four). */
    public const REQUIRED = ['last_name', 'first_name', 'sex', 'category'];

    /** Alternative header spellings (after normalisation) => column key. */
    public const ALIASES = [
        'gender'             => 'sex',
        'grade_year'         => 'year_level',
        'grade'              => 'year_level',
        'grade_level'        => 'year_level',
        'year'               => 'year_level',
        'program_section'    => 'program_section', // SSCMS combined column, split on import
        'program'            => 'program_strand',
        'strand'             => 'program_strand',
        'course'             => 'program_strand',
        'student_no'         => 'student_id',
        'student_number'     => 'student_id',
        'id_number'          => 'student_id',
        'employee_id'        => 'student_id',
        'lrn'                => 'student_id',
        'birthday'           => 'birthdate',
        'date_of_birth'      => 'birthdate',
        'dob'                => 'birthdate',
        'contact'            => 'contact_number',
        'phone'              => 'contact_number',
        'mobile'             => 'contact_number',
        'patient_no'         => 'patient_number',
        'guardian_fb'        => 'guardian_facebook',
        'lastname'           => 'last_name',
        'firstname'          => 'first_name',
        'middlename'         => 'middle_name',
        'surname'            => 'last_name',
        'medications'        => 'current_medications',
    ];

    /** Normalise a header cell: "Last Name" / "last-name" / "LAST_NAME" => "last_name". */
    public static function normalizeHeader(mixed $header): string
    {
        $h = mb_strtolower(trim((string) $header));
        $h = preg_replace('/\(.*?\)/', '', $h);          // drop "(YYYY-MM-DD)" hints
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        $h = trim((string) $h, '_');

        foreach (self::COLUMNS as $key => $label) {
            if ($h === $key || $h === trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(preg_replace('/\(.*?\)/', '', $label))), '_')) {
                return $key;
            }
        }

        return self::ALIASES[$h] ?? $h;
    }
}
