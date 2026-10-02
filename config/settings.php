<?php

/*
|--------------------------------------------------------------------------
| Application Settings Registry
|--------------------------------------------------------------------------
|
| Single source of truth for every admin-editable setting. Each group is a
| page under Admin → Settings. Each field declares:
|
|   type     string | text | email | url | boolean | integer | select | color (#RRGGBB) |
|            json_list (one item per line) | options (value | Label per line) |
|            image (uploaded to the public disk under branding/)
|   label    Form label
|   help     Help text under the field
|   rules    Laravel validation rules (applied only when that group is saved)
|   default  Value used when the DB row is missing (also what syncDefaults()
|            inserts). Defaults mirror the values the app used before
|            settings existed, so nothing changes visually.
|   options  For select: [value => label] or a provider name ('timezones')
|
| Read values with settings('key'); never read this file directly for values.
| Secrets (API keys, SMTP passwords) NEVER belong here; they stay in .env.
|
*/

$smsGlobals = ['clinic', 'clinic_contact', 'app'];

return [

    'groups' => [

        // ─────────────────────────────────────────────────────────────────
        'general' => [
            'label'       => 'General',
            'icon'        => 'bi-sliders',
            'description' => 'System name, organization and regional formats.',
            'fields'      => [
                'app_name' => [
                    'type' => 'string', 'label' => 'System Name', 'default' => 'SchoolCare',
                    'help' => 'Shown in the browser title, sidebar, login page and emails.',
                    'rules' => ['required', 'string', 'max:100'],
                ],
                'app_short_name' => [
                    'type' => 'string', 'label' => 'Short Name', 'default' => 'SchoolCare',
                    'help' => 'Compact name used where space is limited.',
                    'rules' => ['nullable', 'string', 'max:30'],
                ],
                'app_tagline' => [
                    'type' => 'string', 'label' => 'Tagline', 'default' => 'Smart School Clinic Management System',
                    'help' => 'Shown under the system name on the login page, footers and error pages.',
                    'rules' => ['nullable', 'string', 'max:150'],
                ],
                'org_name' => [
                    'type' => 'string', 'label' => 'School Name', 'default' => '',
                    'help' => 'Full name of the school. Shown in the top bar, login page, reports and SMS.',
                    'rules' => ['nullable', 'string', 'max:200'],
                ],
                'org_short_name' => [
                    'type' => 'string', 'label' => 'School Abbreviation', 'default' => '',
                    'help' => 'e.g. the school acronym.',
                    'rules' => ['nullable', 'string', 'max:20'],
                ],
                'timezone' => [
                    'type' => 'select', 'label' => 'Timezone', 'default' => 'Asia/Manila',
                    'help' => 'Used for all dates and times shown in the system.',
                    'options' => 'timezones',
                    'rules' => ['required', 'timezone:all'],
                ],
                'date_format' => [
                    'type' => 'select', 'label' => 'Date Format', 'default' => 'M d, Y',
                    'help' => 'Preferred display format for dates.',
                    'options' => [
                        'M d, Y' => 'Jan 05, 2026',
                        'F d, Y' => 'January 05, 2026',
                        'd M Y'  => '05 Jan 2026',
                        'm/d/Y'  => '01/05/2026',
                        'd/m/Y'  => '05/01/2026',
                        'Y-m-d'  => '2026-01-05',
                    ],
                    'rules' => ['required', 'string', 'max:20'],
                ],
                'time_format' => [
                    'type' => 'select', 'label' => 'Time Format', 'default' => 'h:i A',
                    'help' => 'Preferred display format for times.',
                    'options' => ['h:i A' => '09:30 AM (12-hour)', 'H:i' => '09:30 (24-hour)'],
                    'rules' => ['required', 'string', 'max:20'],
                ],
                'records_per_page' => [
                    'type' => 'integer', 'label' => 'Records Per Page', 'default' => 15,
                    'help' => 'Default number of rows in paginated tables.',
                    'rules' => ['required', 'integer', 'min:5', 'max:200'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'clinic' => [
            'label'       => 'Clinic',
            'icon'        => 'bi-hospital',
            'description' => 'Clinic details and the choice lists used in clinical forms.',
            'fields'      => [
                'clinic_name' => [
                    'type' => 'string', 'label' => 'Clinic Name', 'default' => 'School Clinic',
                    'help' => 'Printed on reports, SMS messages and forms.',
                    'rules' => ['required', 'string', 'max:150'],
                ],
                'clinic_address' => [
                    'type' => 'text', 'label' => 'Clinic Address', 'default' => '',
                    'help' => 'Printed in report headers.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'clinic_contact' => [
                    'type' => 'string', 'label' => 'Clinic Phone', 'default' => '',
                    'help' => 'Available as {clinic_contact} in SMS templates.',
                    'rules' => ['nullable', 'string', 'max:30'],
                ],
                'clinic_email' => [
                    'type' => 'email', 'label' => 'Clinic Email', 'default' => '',
                    'help' => 'Public contact email for the clinic.',
                    'rules' => ['nullable', 'email', 'max:100'],
                ],
                'patient_categories' => [
                    'type' => 'options', 'label' => 'Patient Categories',
                    'help' => 'Categories offered when adding a patient. Renaming a category keeps existing patients in it.',
                    'default' => [
                        'college'     => 'College',
                        'senior_high' => 'Senior High School',
                        'junior_high' => 'Junior High School',
                        'elementary'  => 'Elementary',
                        'kinder'      => 'Kinder',
                        'daycare'     => 'Daycare',
                        'teacher'     => 'Teacher',
                        'employee'    => 'Employee',
                        'visitor'     => 'Visitor',
                        'alumni'      => 'Alumni',
                        'other'       => 'Other',
                    ],
                    'rules' => ['required', 'string', 'max:5000'],
                ],
                'patient_genders' => [
                    'type' => 'options', 'label' => 'Sex / Gender Options',
                    'help' => 'Choices for sex or gender on the patient form.',
                    'default' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'],
                    'rules' => ['required', 'string', 'max:1000'],
                ],
                'blood_types' => [
                    'type' => 'json_list', 'label' => 'Blood Types',
                    'help' => 'Choices for blood type on the patient form.',
                    'default' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'],
                    'rules' => ['required', 'string', 'max:1000'],
                ],
                'medicine_units' => [
                    'type' => 'json_list', 'label' => 'Medicine Units',
                    'help' => 'Units offered in the medicine form.',
                    'default' => ['tablet', 'capsule', 'ml', 'vial', 'piece', 'box', 'bottle', 'sachet', 'other'],
                    'rules' => ['required', 'string', 'max:2000'],
                ],
                'visit_reasons' => [
                    'type' => 'json_list', 'label' => 'Quick Complaints / Visit Reasons',
                    'help' => 'Quick-pick buttons on the clinic log form.',
                    'default' => [
                        'Headache', 'Fever', 'Stomachache', 'Toothache', 'Cough & Colds', 'Dizziness',
                        'Wound / Injury', 'Chest Pain', 'Vomiting', 'Diarrhea', 'Fainting', 'Eye Pain',
                        'Ear Pain', 'Allergic Reaction', 'Menstrual Cramps', 'Body Pain', 'Asthma Attack',
                        'High Blood Pressure',
                    ],
                    'rules' => ['nullable', 'string', 'max:5000'],
                ],
                'visit_severity_levels' => [
                    'type' => 'json_list', 'label' => 'Visit Severity Levels',
                    'help' => 'How serious a visit is, chosen when logging it.',
                    'default' => ['Mild', 'Moderate', 'Severe'],
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
                'dispositions' => [
                    'type' => 'options', 'label' => 'Clinic Log Dispositions',
                    'help' => 'Outcome choices when logging a visit. Renaming an outcome keeps past visits linked to it.',
                    'default' => [
                        'rest_in_clinic'       => 'Rest in Clinic',
                        'returned_to_class'    => 'Returned to Class / Work',
                        'sent_home'            => 'Sent Home',
                        'referred_to_hospital' => 'Referred to Hospital',
                        'further_observation'  => 'Under Observation',
                    ],
                    'rules' => ['required', 'string', 'max:3000'],
                ],
                'appointment_providers' => [
                    'type' => 'json_list', 'label' => 'Appointment Types / Providers',
                    'help' => 'Who the appointment is with.',
                    'default' => ['Doctor', 'Nurse', 'Dentist'],
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
                'specialist_types' => [
                    'type' => 'json_list', 'label' => 'Specialist Types',
                    'help' => 'Kinds of specialist visits, such as doctor or dentist.',
                    'default' => ['Doctor', 'Dentist'],
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'academic' => [
            'label'       => 'Academic',
            'icon'        => 'bi-mortarboard',
            'description' => 'Dropdown choices in the patient registration form.',
            'partial'     => 'admin.settings.partials.academic',
            'fields'      => [
                'year_levels' => [
                    'type' => 'json_list', 'label' => 'Year Levels / Grade Levels',
                    'help' => 'For example Grade 7 or 1st Year.',
                    'default' => ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12', '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
                    'rules' => ['nullable', 'string', 'max:5000'],
                ],
                'sections' => [
                    'type' => 'json_list', 'label' => 'Sections',
                    'help' => 'For example Section A, Rizal or Block 2.',
                    'default' => ['Section A', 'Section B', 'Section C', 'Section D', 'Block 1', 'Block 2', 'Block 3', 'Block 4'],
                    'rules' => ['nullable', 'string', 'max:5000'],
                ],
                'program_strands' => [
                    'type' => 'json_list', 'label' => 'Programs / Strands / Courses',
                    'help' => 'For example BSIT, ABM or STEM.',
                    'default' => ['BSIT', 'BSCS', 'BSN', 'BSED', 'BSHM', 'BSBA', 'ABM', 'STEM', 'HUMSS', 'GAS', 'TVL', 'SPORTS', 'A&D'],
                    'rules' => ['nullable', 'string', 'max:5000'],
                ],
                // Per-category lists (SSCMS grade_years / program_sections). JSON:
                // {"category_value": {"levels": [...], "sections": [...], "programs": [...]}}.
                // A category missing from the map uses the flat lists above; an
                // empty list means the field does not apply to that category.
                'academic_levels_by_category' => [
                    'type' => 'text', 'label' => 'Year Levels and Sections per Category',
                    'help' => 'Limits the year level, section and program choices to the selected patient category. Categories not listed use the lists above. An empty list means the field does not apply.',
                    'default' => json_encode([
                        'college'     => ['levels' => ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'], 'sections' => ['Block 1', 'Block 2', 'Block 3', 'Block 4'], 'programs' => ['BSIT', 'BSCS', 'BSN', 'BSED', 'BSHM', 'BSBA']],
                        'senior_high' => ['levels' => ['Grade 11', 'Grade 12'], 'sections' => ['Section A', 'Section B', 'Section C', 'Section D'], 'programs' => ['ABM', 'STEM', 'HUMSS', 'GAS', 'TVL', 'SPORTS', 'A&D']],
                        'junior_high' => ['levels' => ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'], 'sections' => ['Section A', 'Section B', 'Section C', 'Section D'], 'programs' => []],
                        'elementary'  => ['levels' => ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'], 'sections' => ['Section A', 'Section B', 'Section C', 'Section D'], 'programs' => []],
                        'kinder'      => ['levels' => ['Kinder 1', 'Kinder 2'], 'sections' => ['Section A', 'Section B'], 'programs' => []],
                        'daycare'     => ['levels' => [], 'sections' => [], 'programs' => []],
                        'teacher'     => ['levels' => [], 'sections' => [], 'programs' => []],
                        'employee'    => ['levels' => [], 'sections' => [], 'programs' => []],
                        'visitor'     => ['levels' => [], 'sections' => [], 'programs' => []],
                        'alumni'      => ['levels' => ['Alumni'], 'sections' => ['Alumni'], 'programs' => []],
                    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'rules' => ['nullable', 'string', 'json', 'max:20000'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'branding' => [
            'label'       => 'Branding',
            'icon'        => 'bi-palette',
            'description' => 'Logo, favicon and login page text.',
            'fields'      => [
                'brand_logo' => [
                    'type' => 'image', 'label' => 'School Logo', 'default' => '', 'fallback' => '/brand/logo.png',
                    'help' => 'PNG, JPG or WebP, up to 2 MB. Square images look best. Used everywhere: sidebar, top bar, login page, PDF reports and health cards. Leave empty to use the default SchoolCare mark.',
                    'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                ],
                'header_title' => [
                    'type' => 'select', 'label' => 'Sidebar Title', 'default' => 'app',
                    'help' => 'What the sidebar shows next to the logo.',
                    'options' => ['app' => 'System name (e.g. SchoolCare)', 'school' => 'School name or abbreviation', 'both' => 'System name with school name below'],
                    'rules' => ['required', 'in:app,school,both'],
                ],
                'topbar_show_school' => [
                    'type' => 'boolean', 'label' => 'Show school name in the top bar', 'default' => true,
                    'rules' => ['boolean'],
                ],
                'brand_favicon' => [
                    'type' => 'image', 'label' => 'Favicon', 'default' => '', 'fallback' => '/brand/favicon-32.png', 'fallback_key' => 'brand_logo',
                    'help' => 'PNG or ICO, up to 256 KB. Shown in the browser tab.',
                    'rules' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
                ],
                'brand_primary_color' => [
                    'type' => 'color', 'label' => 'Primary brand color', 'default' => '#2563EB',
                    'help' => 'Used for buttons, links and highlights across the app.',
                    'rules' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                ],
                'login_headline' => [
                    'type' => 'text', 'label' => 'Login Headline', 'default' => 'Welcome back.',
                    'help' => 'Large headline on the login page. Line breaks are kept.',
                    'rules' => ['nullable', 'string', 'max:120'],
                ],
                'login_subtext' => [
                    'type' => 'text', 'label' => 'Login Sub-text',
                    'default' => 'Sign in to manage patient records, clinic visits, appointments and medicine inventory.',
                    'help' => 'Shown below the login headline.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'login_quote' => [
                    'type' => 'text', 'label' => 'Login Quote',
                    'default' => '',
                    'help' => 'Quote at the bottom of the login brand panel. Leave empty to hide.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'login_quote_author' => [
                    'type' => 'string', 'label' => 'Quote Attribution', 'default' => '',
                    'help' => 'Who said the quote.',
                    'rules' => ['nullable', 'string', 'max:100'],
                ],
                'login_image' => [
                    'type' => 'image', 'label' => 'Sign-in page photo', 'default' => '', 'fallback' => '',
                    'help' => 'A photo of your school or clinic shown beside the sign-in form. Landscape, at least 1600px wide.',
                    'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // Printed documents: letterhead banner, signatories and footer of the PDF
        // reports (reports/pdf) and the patient health record (patients/pdf), and
        // of their browser print versions. Read through App\Support\PrintBranding.
        // Defaults print exactly what the documents printed before this page
        // existed: no banner, no signatures, the same footers.
        // 'resize' on an image field: [max width, max height] in pixels; the upload
        // is made smaller and re-saved without metadata (App\Services\ImageResizer).
        'printing' => [
            'label'       => 'Printing',
            'icon'        => 'bi-printer',
            'description' => 'Letterhead, signatures and footer of printed reports and health records.',
            'partial'     => 'admin.settings.partials.printing',
            'fields'      => [
                'print_banner' => [
                    'type' => 'image', 'label' => 'Letterhead banner', 'default' => '', 'fallback' => '',
                    'resize' => [2400, 2400],
                    'help' => 'PNG, JPG or WebP, up to 5 MB. A wide image works best, such as the school seal and name side by side. Transparent PNGs stay transparent. Large images are made smaller when you save.',
                    'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                ],
                'print_banner_height' => [
                    'type' => 'integer', 'label' => 'Banner height (mm)', 'default' => 30,
                    'help' => 'From 15 to 60 millimetres.',
                    'rules' => ['required', 'integer', 'between:15,60'],
                ],
                'print_banner_align' => [
                    'type' => 'select', 'label' => 'Banner position', 'default' => 'center',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                    'rules' => ['required', 'in:left,center,right'],
                ],
                'print_banner_fit' => [
                    'type' => 'select', 'label' => 'Banner size', 'default' => 'width',
                    'options' => ['width' => 'Full page width', 'natural' => 'Natural size'],
                    'rules' => ['required', 'in:width,natural'],
                ],

                // "Prepared by" with the name of whoever prints the document.
                'print_prepared_by_label' => [
                    'type' => 'string', 'label' => 'Signed-in user caption', 'default' => 'Prepared by',
                    'rules' => ['nullable', 'string', 'max:40'],
                ],
                'print_prepared_by_on_reports' => [
                    'type' => 'boolean', 'label' => 'Signed-in user on reports', 'default' => false,
                    'rules' => ['boolean'],
                ],
                'print_prepared_by_on_health' => [
                    'type' => 'boolean', 'label' => 'Signed-in user on health records', 'default' => false,
                    'rules' => ['boolean'],
                ],

                // Up to three signatories. One prints only when its name is filled in.
                ...(function (): array {
                    $fields = [];
                    foreach ([1 => 'Prepared by', 2 => 'Noted by', 3 => 'Approved by'] as $n => $caption) {
                        $fields += [
                            "print_sig{$n}_label" => [
                                'type' => 'string', 'label' => "Signatory {$n} caption", 'default' => $caption,
                                'rules' => ['nullable', 'string', 'max:40'],
                            ],
                            "print_sig{$n}_name" => [
                                'type' => 'string', 'label' => "Signatory {$n} name", 'default' => '',
                                'rules' => ['nullable', 'string', 'max:100'],
                            ],
                            "print_sig{$n}_position" => [
                                'type' => 'string', 'label' => "Signatory {$n} position", 'default' => '',
                                'rules' => ['nullable', 'string', 'max:100'],
                            ],
                            "print_sig{$n}_license" => [
                                'type' => 'string', 'label' => "Signatory {$n} license number", 'default' => '',
                                'rules' => ['nullable', 'string', 'max:50'],
                            ],
                            "print_sig{$n}_image" => [
                                'type' => 'image', 'label' => "Signatory {$n} signature image", 'default' => '', 'fallback' => '',
                                'resize' => [1200, 600],
                                'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                            ],
                            "print_sig{$n}_height" => [
                                'type' => 'integer', 'label' => "Signatory {$n} signature height (mm)", 'default' => 15,
                                'rules' => ['required', 'integer', 'between:8,30'],
                            ],
                            "print_sig{$n}_on_reports" => [
                                'type' => 'boolean', 'label' => "Signatory {$n} on reports", 'default' => true,
                                'rules' => ['boolean'],
                            ],
                            "print_sig{$n}_on_health" => [
                                'type' => 'boolean', 'label' => "Signatory {$n} on health records", 'default' => true,
                                'rules' => ['boolean'],
                            ],
                        ];
                    }

                    return $fields;
                })(),

                'print_footer_text' => [
                    'type' => 'text', 'label' => 'Footer text',
                    'default' => 'Confidential health information from the records of {clinic}.',
                    'placeholders' => ['school'],
                    'help' => 'One short line at the bottom of the page. {clinic} is replaced with the clinic name and {school} with the school name.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'print_footer_on_reports' => [
                    'type' => 'boolean', 'label' => 'Footer text on reports', 'default' => false,
                    'rules' => ['boolean'],
                ],
                'print_footer_on_health' => [
                    'type' => 'boolean', 'label' => 'Footer text on health records', 'default' => true,
                    'rules' => ['boolean'],
                ],
                'print_page_numbers_reports' => [
                    'type' => 'boolean', 'label' => 'Page numbers on reports', 'default' => true,
                    'rules' => ['boolean'],
                ],
                'print_page_numbers_health' => [
                    'type' => 'boolean', 'label' => 'Page numbers on health records', 'default' => false,
                    'rules' => ['boolean'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'appointments' => [
            'label'       => 'Appointments',
            'icon'        => 'bi-calendar-check',
            'description' => 'Booking limits and reminders.',
            'fields'      => [
                'max_daily_appointments' => [
                    'type' => 'integer', 'label' => 'Maximum Appointments Per Day', 'default' => 50,
                    'help' => 'New bookings are rejected once a day has this many pending/approved appointments.',
                    'rules' => ['required', 'integer', 'min:1', 'max:9999'],
                ],
                'booking_max_days_ahead' => [
                    'type' => 'integer', 'label' => 'Booking Window (days ahead)', 'default' => 0,
                    'help' => 'How far in advance appointments may be booked. 0 = no limit.',
                    'rules' => ['required', 'integer', 'min:0', 'max:365'],
                ],
                'allow_weekend_booking' => [
                    'type' => 'boolean', 'label' => 'Allow booking on weekends', 'default' => true,
                    'help' => 'When off, Saturday and Sunday dates are rejected.',
                    'rules' => ['boolean'],
                ],
                'reminder_hours_before' => [
                    'type' => 'integer', 'label' => 'Send reminder (hours before)', 'default' => 24,
                    'help' => 'Approved appointments get one reminder this many hours before they start.',
                    'rules' => ['required', 'integer', 'min:1', 'max:168'],
                ],
                'appointment_cancel_reason_required' => [
                    'type' => 'boolean', 'label' => 'Require a reason when cancelling', 'default' => true,
                    'help' => 'Staff must type a reason when cancelling an appointment.',
                    'rules' => ['boolean'],
                ],
                'appointment_slot_minutes' => [
                    'type' => 'integer', 'label' => 'Default slot duration (minutes)', 'default' => 30,
                    'help' => 'Default length of an appointment slot.',
                    'rules' => ['required', 'integer', 'min:5', 'max:240'],
                ],
                'clinic_weekly_hours' => [
                    'type' => 'options', 'label' => 'Weekly Opening Hours',
                    'help' => 'The days and hours the clinic is open.',
                    'default' => [
                        'monday'    => '07:30-17:00',
                        'tuesday'   => '07:30-17:00',
                        'wednesday' => '07:30-17:00',
                        'thursday'  => '07:30-17:00',
                        'friday'    => '07:30-17:00',
                        'saturday'  => 'closed',
                        'sunday'    => 'closed',
                    ],
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
                'public_booking_enabled' => [
                    'type' => 'boolean', 'label' => 'Accept online appointment requests', 'default' => false,
                    'help' => 'Turns on the public "Request an appointment" form and the public clinic schedule board. Requests arrive as pending and must be linked to a patient before approval.',
                    'rules' => ['boolean'],
                ],
                'appointment_purposes' => [
                    'type' => 'json_list', 'label' => 'Appointment Reasons',
                    'help' => 'Reasons offered on the online request form. A reason that starts with "Other" asks the person to type their own. Leave the list empty to let people type any reason.',
                    'default' => ['General Checkup', 'Dental Consultation', 'Follow-up Visit', 'Vaccination', 'Medical Certificate', 'Illness or Injury', 'Mental Health Consultation', 'Other'],
                    'rules' => ['nullable', 'string', 'max:2000'],
                ],

                // -- Online request form (App\Services\OnlineBooking) ----------------------
                'public_booking_intro' => [
                    'type' => 'text', 'label' => 'Text at the top of the form',
                    'default' => 'Send a request to the school clinic. The clinic staff will review it and confirm by text message.',
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
                'public_booking_success_message' => [
                    'type' => 'text', 'label' => 'Message after sending',
                    'default' => 'Thank you. Your appointment request is now with the clinic staff. You do not need to send it again.',
                    'help' => 'Shown on the confirmation page.',
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
                'public_booking_closed_message' => [
                    'type' => 'text', 'label' => 'Message while online requests are off',
                    'default' => 'The clinic is not taking online appointment requests right now. Please visit the clinic during clinic hours or call the clinic.',
                    'help' => 'Shown to anyone who opens the request page while online requests are turned off.',
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
                'public_booking_days_ahead' => [
                    'type' => 'integer', 'label' => 'How far ahead (days)', 'default' => 30,
                    'help' => 'The form offers dates from today up to this many days ahead. A shorter booking window above wins.',
                    'rules' => ['required', 'integer', 'min:1', 'max:365'],
                ],
                'public_booking_min_notice_hours' => [
                    'type' => 'integer', 'label' => 'Minimum notice (hours)', 'default' => 0,
                    'help' => 'Times sooner than this are not offered. 0 allows any open time later today; 24 starts from the same time tomorrow.',
                    'rules' => ['required', 'integer', 'min:0', 'max:720'],
                ],
                'public_booking_slot_limit' => [
                    'type' => 'integer', 'label' => 'Online requests per time slot', 'default' => 0,
                    'help' => 'How many places of each time slot online requests may take, so the rest stay free for walk-ins and staff bookings. 0 = all places. The places per slot are set in Administration > Appointment Slots.',
                    'rules' => ['required', 'integer', 'min:0', 'max:500'],
                ],
                'public_booking_specialist_days_only' => [
                    'type' => 'boolean', 'label' => 'Doctor and dentist requests only on their visit days', 'default' => false,
                    'help' => 'When someone chooses a specialist (Specialist Types under Clinic), the form offers only the days that specialist is scheduled to visit, within the visit hours.',
                    'rules' => ['boolean'],
                ],
                'public_booking_closed_dates' => [
                    'type' => 'json_list', 'label' => 'Closed dates', 'default' => [], 'noun' => 'date',
                    'help' => 'Days with no online requests, such as holidays and school events. One per line: the date as YYYY-MM-DD, then an optional note. Example: 2026-12-25 Christmas Day',
                    // Every non-empty line must start with a YYYY-MM-DD date.
                    'rules' => ['nullable', 'string', 'max:5000', 'not_regex:/^(?!\s*$)(?!\s*\d{4}-\d{2}-\d{2}(?:\s|$)).*$/m'],
                ],
                'public_booking_field_category' => [
                    'type' => 'select', 'label' => 'Category', 'default' => 'required',
                    'options' => ['required' => 'Required', 'optional' => 'Optional', 'hidden' => 'Hidden'],
                    'help' => 'Choices come from Patient Categories under Clinic.',
                    'rules' => ['required', 'in:required,optional,hidden'],
                ],
                'public_booking_field_school' => [
                    'type' => 'select', 'label' => 'Grade, program and section', 'default' => 'optional',
                    'options' => ['required' => 'Required', 'optional' => 'Optional', 'hidden' => 'Hidden'],
                    'help' => 'Choices follow the category (Settings > Academic).',
                    'rules' => ['required', 'in:required,optional,hidden'],
                ],
                'public_booking_field_student_id' => [
                    'type' => 'select', 'label' => 'Student or employee ID', 'default' => 'optional',
                    'options' => ['required' => 'Required', 'optional' => 'Optional', 'hidden' => 'Hidden'],
                    'help' => 'With a matching mobile number, the request is linked to the patient automatically.',
                    'rules' => ['required', 'in:required,optional,hidden'],
                ],
                'public_booking_field_email' => [
                    'type' => 'select', 'label' => 'Email', 'default' => 'optional',
                    'options' => ['required' => 'Required', 'optional' => 'Optional', 'hidden' => 'Hidden'],
                    'rules' => ['required', 'in:required,optional,hidden'],
                ],
                'public_booking_field_provider' => [
                    'type' => 'select', 'label' => 'Appointment with', 'default' => 'optional',
                    'options' => ['required' => 'Required', 'optional' => 'Optional', 'hidden' => 'Hidden'],
                    'help' => 'Choices come from Appointment Types / Providers under Clinic.',
                    'rules' => ['required', 'in:required,optional,hidden'],
                ],
                'public_booking_field_details' => [
                    'type' => 'select', 'label' => 'Notes for the nurse', 'default' => 'optional',
                    'options' => ['required' => 'Required', 'optional' => 'Optional', 'hidden' => 'Hidden'],
                    'rules' => ['required', 'in:required,optional,hidden'],
                ],
                'public_booking_consent_required' => [
                    'type' => 'boolean', 'label' => 'Ask people to agree to the privacy statement', 'default' => true,
                    'help' => 'A required tick box above the Send button, with a link to the privacy notice.',
                    'rules' => ['boolean'],
                ],
                'public_booking_consent_text' => [
                    'type' => 'text', 'label' => 'Privacy statement',
                    'default' => 'I agree that the school clinic may use the details in this request to schedule my visit and to contact me about it, as described in the privacy notice.',
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'intake' => [
            'label'       => 'Online Health Form',
            'icon'        => 'bi-clipboard2-heart',
            'description' => 'Public Student Health Information Form. Submissions wait for staff review before they become patient records.',
            'fields'      => [
                'public_intake_enabled' => [
                    'type' => 'boolean', 'label' => 'Accept online health information forms', 'default' => false,
                    'help' => 'Turns on the public form at /health-form. Nothing is added to patient records until staff approve a submission.',
                    'rules' => ['boolean'],
                ],
                'intake_consent_text' => [
                    'type' => 'text', 'label' => 'Consent Statement',
                    'default' => 'I confirm that the information I give is true and complete. I agree that the school clinic may collect, store and use this information to provide health services, contact my parent or guardian, and keep clinic records, in line with the Data Privacy Act of 2012 (Republic Act No. 10173). I understand that only authorized clinic staff can view it and that I may ask the clinic to correct it.',
                    'help' => 'Shown above the required consent checkbox.',
                    'rules' => ['nullable', 'string', 'max:3000'],
                ],
                'intake_success_message' => [
                    'type' => 'text', 'label' => 'Message After Submitting',
                    'default' => 'Thank you. Your health information form was received. The clinic staff will review it and add it to your clinic record.',
                    'help' => 'Shown on the confirmation page.',
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'inventory' => [
            'label'       => 'Inventory',
            'icon'        => 'bi-capsule',
            'description' => 'Stock and expiry alert thresholds.',
            'fields'      => [
                'low_stock_threshold' => [
                    'type' => 'integer', 'label' => 'Default Low Stock Threshold', 'default' => 10,
                    'help' => 'Pre-filled threshold for new medicines. Each medicine can override it.',
                    'rules' => ['required', 'integer', 'min:0', 'max:9999'],
                ],
                'expiry_warning_days' => [
                    'type' => 'integer', 'label' => 'Expiry Warning (days ahead)', 'default' => 30,
                    'help' => 'Medicines expiring within this many days are flagged on the dashboard and expiring list.',
                    'rules' => ['required', 'integer', 'min:1', 'max:365'],
                ],
                'asset_conditions' => [
                    'type' => 'json_list', 'label' => 'Asset Conditions',
                    'help' => 'Condition choices for clinic equipment and supplies.',
                    'default' => ['Good', 'Needs repair', 'Damaged', 'Disposed'],
                    'rules' => ['required', 'string', 'max:1000'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'notifications' => [
            'label'       => 'Notifications',
            'icon'        => 'bi-bell',
            'description' => 'Which events send SMS and email.',
            'fields'      => [
                'sms_enabled' => [
                    'type' => 'boolean', 'label' => 'Send text messages (SMS)', 'default' => false,
                    'help' => 'Main switch. When off, no text messages are sent and each attempt is listed in the SMS log as Skipped.',
                    'rules' => ['boolean'],
                ],
                'notify_sms_appointment_created' => [
                    'type' => 'boolean', 'label' => 'SMS: appointment booked', 'default' => true,
                    'help' => 'Sent to the patient (or guardian) when an appointment is booked.', 'rules' => ['boolean'],
                ],
                'notify_sms_appointment_approved' => [
                    'type' => 'boolean', 'label' => 'SMS: appointment approved', 'default' => true,
                    'help' => '', 'rules' => ['boolean'],
                ],
                'notify_sms_appointment_rescheduled' => [
                    'type' => 'boolean', 'label' => 'SMS: appointment rescheduled', 'default' => true,
                    'help' => 'Sent when the date or time of an appointment changes.', 'rules' => ['boolean'],
                ],
                'notify_sms_appointment_cancelled' => [
                    'type' => 'boolean', 'label' => 'SMS: appointment cancelled', 'default' => true,
                    'help' => '', 'rules' => ['boolean'],
                ],
                'notify_sms_appointment_reminder' => [
                    'type' => 'boolean', 'label' => 'SMS: appointment reminder', 'default' => true,
                    'help' => 'Sent once before an approved appointment. The timing is set under Appointments.', 'rules' => ['boolean'],
                ],
                'sms_log_guardian_enabled' => [
                    'type' => 'boolean', 'label' => 'SMS: guardian visit notice (clinic log)', 'default' => true,
                    'help' => 'Allows staff to notify the guardian when logging a clinic visit.', 'rules' => ['boolean'],
                ],
                'notify_sms_clinic_discharge' => [
                    'type' => 'boolean', 'label' => 'SMS: guardian discharge notice', 'default' => true,
                    'help' => 'Sent to the guardian when a logged patient is discharged.', 'rules' => ['boolean'],
                ],
                'notify_sms_intake_approved' => [
                    'type' => 'boolean', 'label' => 'SMS: online health form approved', 'default' => false,
                    'help' => 'Sent to the number on the form when staff approve an online health information form.', 'rules' => ['boolean'],
                ],
                'notify_email_appointments' => [
                    'type' => 'boolean', 'label' => 'Email: appointment updates to patient', 'default' => false,
                    'help' => 'Booked, approved, moved, cancelled and reminder emails, when the patient has an email address on file.', 'rules' => ['boolean'],
                ],
                'notify_email_online_request' => [
                    'type' => 'boolean', 'label' => 'Email: new online request to the clinic', 'default' => false,
                    'help' => 'Sent when someone requests an appointment on the website, with a link to review it.', 'rules' => ['boolean'],
                ],
                'online_request_notify_email' => [
                    'type' => 'email', 'label' => 'Send new request emails to', 'default' => '',
                    'help' => 'Leave empty to use the clinic email under Clinic.',
                    'rules' => ['nullable', 'email', 'max:150'],
                ],
                // Invitation and password-reset emails always send: accounts cannot be set up without them.
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'sms' => [
            'label'       => 'SMS',
            'icon'        => 'bi-phone',
            'description' => 'Turn text messages on or off, set the sender name and edit the messages that are sent.',
            'partial'     => 'admin.settings.partials.sms',
            'fields'      => [
                'sms_sender_name' => [
                    'type' => 'string', 'label' => 'Sender Name', 'default' => env('SEMAPHORE_SENDER_NAME', 'SchoolCare'),
                    'help' => 'The name people see instead of a phone number. Up to 11 letters or numbers. It must be approved by the SMS provider first. Leave empty to use the name set up on the server.',
                    'rules' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]*$/'],
                ],
                'sms_template_appointment_created' => [
                    'type' => 'text', 'label' => 'Appointment Booked', 'event' => 'appointment_created',
                    'default' => 'Dear {name}, your appointment request at {clinic} on {date} at {time} has been received and is pending approval. - {app}',
                    'placeholders' => ['name', 'full_name', 'date', 'time', 'purpose'],
                    'help' => 'Sent when an appointment is booked.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_approval' => [
                    'type' => 'text', 'label' => 'Appointment Approved', 'event' => 'appointment_approved',
                    'default' => 'Dear {name}, your appointment at the school clinic on {date} at {time} has been approved. Please arrive 10 minutes early. - {clinic}',
                    'placeholders' => ['name', 'full_name', 'date', 'time', 'purpose'],
                    'help' => "Sent when a patient's appointment is confirmed.",
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_rescheduled' => [
                    'type' => 'text', 'label' => 'Appointment Rescheduled', 'event' => 'appointment_rescheduled',
                    'default' => 'Dear {name}, your appointment at {clinic} has been moved to {date} at {time}. - {app}',
                    'placeholders' => ['name', 'full_name', 'date', 'time', 'purpose'],
                    'help' => 'Sent when the date or time changes.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_cancellation' => [
                    'type' => 'text', 'label' => 'Appointment Cancelled', 'event' => 'appointment_cancelled',
                    'default' => 'Dear {name}, your appointment on {date} at the school clinic has been cancelled. Reason: {reason}. Please contact us to reschedule. - {clinic}',
                    'placeholders' => ['name', 'full_name', 'date', 'time', 'reason'],
                    'help' => 'Sent when an appointment is cancelled.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_reminder' => [
                    'type' => 'text', 'label' => 'Appointment Reminder', 'event' => 'appointment_reminder',
                    'default' => 'Reminder: Dear {name}, you have an appointment at {clinic} on {date} at {time}. Please arrive 10 minutes early. - {app}',
                    'placeholders' => ['name', 'full_name', 'date', 'time', 'purpose'],
                    'help' => 'Sent once before an approved appointment.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_clinic_log' => [
                    'type' => 'text', 'label' => 'Guardian Visit Notice', 'event' => 'clinic_log',
                    'default' => 'Dear {guardian}, your ward {name} visited the school clinic at {time} for {complaint}. Action taken: {treatment}. - {clinic}',
                    'placeholders' => ['guardian', 'name', 'full_name', 'date', 'time', 'complaint', 'treatment', 'disposition'],
                    'help' => 'Sent to the guardian when a student visits the clinic.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_discharge' => [
                    'type' => 'text', 'label' => 'Guardian Discharge Notice', 'event' => 'clinic_discharge',
                    'default' => 'Dear {guardian}, your ward {name} was released from {clinic} at {time}. Disposition: {disposition}. - {app}',
                    'placeholders' => ['guardian', 'name', 'full_name', 'date', 'time', 'complaint', 'treatment', 'disposition'],
                    'help' => 'Sent to the guardian when a logged patient is discharged.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
                'sms_template_intake_approved' => [
                    'type' => 'text', 'label' => 'Online Health Form Approved', 'event' => 'intake_approved',
                    'default' => 'Hello {name}, the health information form you sent to {clinic} was reviewed and added to your clinic record. - {app}',
                    'placeholders' => ['name', 'full_name', 'date'],
                    'help' => 'Sent when staff approve an online health information form.',
                    'rules' => ['nullable', 'string', 'max:480'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'email' => [
            'label'       => 'Email',
            'icon'        => 'bi-envelope',
            'description' => 'The name and address that emails from the system come from.',
            'partial'     => 'admin.settings.partials.email',
            'fields'      => [
                'mail_from_name' => [
                    'type' => 'string', 'label' => 'From Name', 'default' => '',
                    'help' => 'Leave empty to use the system name.',
                    'rules' => ['nullable', 'string', 'max:100'],
                ],
                'mail_from_address' => [
                    'type' => 'email', 'label' => 'From Address', 'default' => '',
                    'help' => 'Leave empty to use the address set up on the server. Your email provider must allow this address.',
                    'rules' => ['nullable', 'email', 'max:150'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // Email sign-in codes (App\Services\SignInCodes). Off by default. It cannot
        // be turned on while email sending is not set up (UpdateSettingsRequest),
        // and `php artisan auth:otp-off` turns it off from the server.
        'security' => [
            'label'       => 'Security',
            'icon'        => 'bi-shield-lock',
            'description' => 'Extra checks when people sign in.',
            'partial'     => 'admin.settings.partials.security',
            'fields'      => [
                'otp_enabled' => [
                    'type' => 'boolean', 'label' => 'Ask for a sign-in code by email', 'default' => false,
                    'help' => 'After the password, people type a 6-digit code that is emailed to them. Email must be working first: send yourself a test email from Settings > Email. In an emergency, the person who manages the server can turn this off with the command php artisan auth:otp-off.',
                    'rules' => ['boolean'],
                ],
                'otp_applies_to' => [
                    'type' => 'select', 'label' => 'Who is asked for a code', 'default' => 'everyone',
                    'options' => ['everyone' => 'Everyone', 'admins' => 'Administrators only'],
                    'rules' => ['required', 'in:everyone,admins'],
                ],
                'otp_remember_days' => [
                    'type' => 'integer', 'label' => 'Remember this device for (days)', 'default' => 30,
                    'help' => 'After a correct code, people can choose to skip the code on that browser for this many days. 0 asks every time.',
                    'rules' => ['required', 'integer', 'min:0', 'max:365'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        'ai' => [
            'label'       => 'AI Assistant',
            'icon'        => 'bi-chat-dots',
            'description' => 'Built-in AI assistant for clinic staff.',
            'partial'     => 'admin.settings.partials.ai',
            'fields'      => [
                'ai_enabled' => [
                    'type' => 'boolean', 'label' => 'Enable AI assistant', 'default' => true,
                    'help' => 'When turned off, the assistant is hidden from all users.',
                    'rules' => ['boolean'],
                ],
                'ai_assistant_name' => [
                    'type' => 'string', 'label' => 'Assistant Name', 'default' => 'Coco',
                    'help' => 'Name shown for the AI assistant in the menu and chat.',
                    'rules' => ['nullable', 'string', 'max:30'],
                ],
                // Groq chat models. A saved model that is no longer listed here
                // falls back to the default at runtime (AiAssistantService::model).
                'ai_model' => [
                    'type' => 'select', 'label' => 'AI Model', 'default' => 'openai/gpt-oss-120b',
                    'help' => 'Bigger models give better answers; smaller ones answer faster.',
                    'options' => [
                        'openai/gpt-oss-120b' => 'GPT-OSS 120B (best answers, recommended)',
                        'openai/gpt-oss-20b'  => 'GPT-OSS 20B (faster)',
                        'qwen/qwen3.8-27b'    => 'Qwen 3.8 27B (fastest, cannot search the web)',
                    ],
                    'rules' => ['required', 'string', 'max:80'],
                ],
                // Stored encrypted (type 'secret'); never shown back on the page.
                // Empty means the server's GROQ_API_KEY is used, if there is one.
                'ai_groq_api_key' => [
                    'type' => 'secret', 'label' => 'Groq API key', 'default' => '',
                    'help' => 'From console.groq.com, under API Keys. It is saved encrypted and never shown again.',
                    'rules' => ['nullable', 'string', 'max:200'],
                ],
                // Off by default: one search can read many pages and use a large part of
                // Groq's free daily allowance (seen: about 190,000 tokens for one question).
                'ai_web_search' => [
                    'type' => 'boolean', 'label' => 'Let the assistant search the web', 'default' => false,
                    'help' => 'Lets it look up news, advisories and other recent information. Searching uses far more of your Groq allowance, and on the free plan one or two searches can use up the day, so turn this on with a paid Groq plan. Works with the GPT-OSS models.',
                    'rules' => ['boolean'],
                ],
                // Records and actions (App\Services\Coco). Reading is on by default (owner),
                // actions are off until an administrator turns them on.
                'ai_read_patients' => [
                    'type' => 'boolean', 'label' => 'Let the assistant read patient records', 'default' => true,
                    'help' => 'When someone asks about a specific patient, it may look up the name, patient number, course or grade and section, the last few clinic visits, allergies and existing conditions, and send them to Groq to answer. Every lookup is recorded in the audit logs. When off, no patient details are sent.',
                    'rules' => ['boolean'],
                ],
                'ai_actions_enabled' => [
                    'type' => 'boolean', 'label' => 'Let the assistant take actions', 'default' => false,
                    'help' => 'It can prepare a text message, an email, an appointment or a settings change. Nothing happens until the person chatting checks the details and taps Confirm, and each confirmed action is recorded in the audit logs.',
                    'rules' => ['boolean'],
                ],
                'ai_actions_messages' => [
                    'type' => 'boolean', 'label' => 'Text messages and emails', 'default' => true,
                    'help' => 'One person at a time: a patient, a guardian or a staff member. Needs the Send SMS permission.',
                    'rules' => ['boolean'],
                ],
                'ai_actions_appointments' => [
                    'type' => 'boolean', 'label' => 'Appointments', 'default' => true,
                    'help' => 'Book, move or cancel an appointment, with the same permissions and limits as the Appointments page.',
                    'rules' => ['boolean'],
                ],
                'ai_actions_settings' => [
                    'type' => 'boolean', 'label' => 'Settings changes', 'default' => true,
                    'help' => 'Administrators only. Everyday settings such as clinic hours, contact details, SMS messages and website text. API keys, security and sign-in settings can never be changed this way.',
                    'rules' => ['boolean'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // Public website (landing page). Edited under Administration → Website
        // (permission manage-landing), not in the Settings pages ('standalone').
        // Services, FAQs, team and advisories live in their own tables.
        // Empty text fields fall back to wording built from the clinic and
        // school names (App\Services\LandingContent), shown as the input hint.
        'landing' => [
            'label'       => 'Website',
            'icon'        => 'bi-globe2',
            'description' => 'Text and sections of the public website.',
            'standalone'  => 'admin.website.edit',
            'fields'      => [
                // -- Sections (order + on/off) --------------------------------
                'landing_section_order' => [
                    'type' => 'json_list', 'label' => 'Section order',
                    'default' => ['advisories', 'services', 'schedule', 'steps', 'team', 'faq', 'contact'],
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
                'landing_show_advisories' => ['type' => 'boolean', 'label' => 'Advisories', 'default' => true, 'rules' => ['boolean']],
                'landing_show_services'   => ['type' => 'boolean', 'label' => 'Services', 'default' => true, 'rules' => ['boolean']],
                'landing_show_schedule'   => ['type' => 'boolean', 'label' => 'Clinic hours and visit days', 'default' => true, 'rules' => ['boolean']],
                'landing_show_steps'      => ['type' => 'boolean', 'label' => 'How to get care', 'default' => true, 'rules' => ['boolean']],
                'landing_show_team'       => ['type' => 'boolean', 'label' => 'Clinic team', 'default' => true, 'rules' => ['boolean']],
                'landing_show_faq'        => ['type' => 'boolean', 'label' => 'Common questions', 'default' => true, 'rules' => ['boolean']],
                'landing_show_contact'    => ['type' => 'boolean', 'label' => 'Contact', 'default' => true, 'rules' => ['boolean']],

                // -- Top of the page -------------------------------------------
                'landing_hero_heading' => [
                    'type' => 'string', 'label' => 'Main heading', 'default' => '',
                    'help' => 'Leave empty to use the clinic name.',
                    'rules' => ['nullable', 'string', 'max:100'],
                ],
                'landing_hero_subheading' => [
                    'type' => 'string', 'label' => 'Line under the heading', 'default' => '',
                    'help' => 'Leave empty to use the suggested line shown in the box.',
                    'rules' => ['nullable', 'string', 'max:160'],
                ],
                'landing_hero_description' => [
                    'type' => 'text', 'label' => 'Short description',
                    'default' => 'The clinic gives first aid, nurse consultations and basic medicines to students and staff during school hours, and keeps each student\'s health record up to date.',
                    'help' => 'One or two sentences about what the clinic does.',
                    'rules' => ['nullable', 'string', 'max:400'],
                ],
                'landing_primary_cta_label' => [
                    'type' => 'string', 'label' => 'Main button text', 'default' => 'Request an appointment',
                    'rules' => ['nullable', 'string', 'max:40'],
                ],
                'landing_primary_cta_target' => [
                    'type' => 'select', 'label' => 'Main button opens', 'default' => 'request',
                    'help' => 'If that page is turned off, the button opens the contact details instead.',
                    'options' => [
                        'request'     => 'Appointment request form',
                        'health_form' => 'Online health form',
                        'schedule'    => 'Clinic hours on this page',
                        'services'    => 'Services on this page',
                        'contact'     => 'Contact details on this page',
                        'url'         => 'Another web address',
                    ],
                    'rules' => ['required', 'string', 'max:20'],
                ],
                'landing_primary_cta_url' => [
                    'type' => 'url', 'label' => 'Main button web address', 'default' => '',
                    'help' => 'Only used when the button opens another web address.',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],
                'landing_secondary_cta_label' => [
                    'type' => 'string', 'label' => 'Second button text', 'default' => 'See clinic hours',
                    'help' => 'Leave empty to hide the second button.',
                    'rules' => ['nullable', 'string', 'max:40'],
                ],
                'landing_secondary_cta_target' => [
                    'type' => 'select', 'label' => 'Second button opens', 'default' => 'schedule',
                    'options' => [
                        'request'     => 'Appointment request form',
                        'health_form' => 'Online health form',
                        'schedule'    => 'Clinic hours on this page',
                        'services'    => 'Services on this page',
                        'contact'     => 'Contact details on this page',
                        'url'         => 'Another web address',
                    ],
                    'rules' => ['required', 'string', 'max:20'],
                ],
                'landing_secondary_cta_url' => [
                    'type' => 'url', 'label' => 'Second button web address', 'default' => '',
                    'help' => 'Only used when the button opens another web address.',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],
                'landing_hero_image' => [
                    'type' => 'image', 'label' => 'Photo', 'default' => '', 'fallback' => '',
                    'help' => 'Optional. A real photo of the clinic or the clinic staff, PNG, JPG or WebP up to 2 MB, landscape works best. Without a photo, a "Today at the clinic" card with today\'s hours is shown.',
                    'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                ],
                'landing_hero_image_alt' => [
                    'type' => 'string', 'label' => 'Photo description', 'default' => '',
                    'help' => 'What the photo shows, for people who use screen readers. Example: The clinic nurse checking a student\'s blood pressure.',
                    'rules' => ['nullable', 'string', 'max:160'],
                ],
                'landing_nurse_on_duty' => [
                    'type' => 'string', 'label' => 'Nurse on duty', 'default' => '',
                    'help' => 'Optional. Shown on the "Today at the clinic" card.',
                    'rules' => ['nullable', 'string', 'max:100'],
                ],
                'landing_location' => [
                    'type' => 'string', 'label' => 'Where the clinic is on campus', 'default' => '',
                    'help' => 'Optional. Example: Ground floor, Main Building, beside the guidance office.',
                    'rules' => ['nullable', 'string', 'max:160'],
                ],

                // -- How to get care (3 steps) -----------------------------------
                'landing_step1_title' => [
                    'type' => 'string', 'label' => 'Step 1 title', 'default' => 'Go to the clinic or book a visit',
                    'rules' => ['nullable', 'string', 'max:80'],
                ],
                'landing_step1_body' => [
                    'type' => 'text', 'label' => 'Step 1 text',
                    'default' => 'Walk in during clinic hours when you feel unwell or get hurt. For a check-up, clearance or follow-up, you can book a time.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_step2_title' => [
                    'type' => 'string', 'label' => 'Step 2 title', 'default' => 'The nurse checks you',
                    'rules' => ['nullable', 'string', 'max:80'],
                ],
                'landing_step2_body' => [
                    'type' => 'text', 'label' => 'Step 2 text',
                    'default' => 'The school nurse asks what happened, checks your vital signs and gives first aid or medicine when needed. Every visit is written in your clinic record.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_step3_title' => [
                    'type' => 'string', 'label' => 'Step 3 title', 'default' => 'Your parent or guardian is informed',
                    'rules' => ['nullable', 'string', 'max:80'],
                ],
                'landing_step3_body' => [
                    'type' => 'text', 'label' => 'Step 3 text',
                    'default' => 'When a guardian\'s mobile number is on file, they get a text message about the visit. If you need to go home or see a doctor, the clinic calls them.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],

                // -- Section introductions ------------------------------------------
                'landing_services_intro' => [
                    'type' => 'text', 'label' => 'Services introduction',
                    'default' => 'What the clinic can do for students and staff during school hours.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_schedule_intro' => [
                    'type' => 'text', 'label' => 'Clinic hours introduction',
                    'default' => 'The clinic is open on these days. Changes, such as holiday closings, are posted under Advisories.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_team_intro' => [
                    'type' => 'text', 'label' => 'Clinic team introduction',
                    'default' => 'The people you will meet at the clinic.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_faq_intro' => [
                    'type' => 'text', 'label' => 'Common questions introduction',
                    'default' => 'Answers to what students and parents ask the clinic most often.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],

                // -- Contact and social ------------------------------------------------
                'landing_hotline' => [
                    'type' => 'string', 'label' => 'Emergency number', 'default' => '',
                    'help' => 'Shown at the very top of every public page. Leave empty to use the clinic phone.',
                    'rules' => ['nullable', 'string', 'max:30'],
                ],
                'landing_contact_intro' => [
                    'type' => 'text', 'label' => 'Contact introduction',
                    'default' => 'Call or email the clinic during clinic hours. Messages sent after hours are answered on the next school day.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_map_url' => [
                    'type' => 'url', 'label' => 'Map link', 'default' => '',
                    'help' => 'Optional. Paste the share link of the school from Google Maps. It opens in a new tab.',
                    'rules' => ['nullable', 'url:http,https', 'max:500'],
                ],
                'landing_facebook_url' => [
                    'type' => 'url', 'label' => 'Facebook page', 'default' => '',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],
                'landing_messenger_url' => [
                    'type' => 'url', 'label' => 'Messenger link', 'default' => '',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],
                'landing_instagram_url' => [
                    'type' => 'url', 'label' => 'Instagram', 'default' => '',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],
                'landing_youtube_url' => [
                    'type' => 'url', 'label' => 'YouTube', 'default' => '',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],
                'landing_school_website_url' => [
                    'type' => 'url', 'label' => 'School website', 'default' => '',
                    'rules' => ['nullable', 'url:http,https', 'max:300'],
                ],

                // -- Footer and privacy -------------------------------------------------
                'landing_footer_about' => [
                    'type' => 'text', 'label' => 'About the clinic (footer)',
                    'default' => 'The school clinic looks after the health of students and staff during school hours: first aid, nurse consultations, medicines and health records.',
                    'rules' => ['nullable', 'string', 'max:300'],
                ],
                'landing_copyright' => [
                    'type' => 'string', 'label' => 'Copyright line', 'default' => '',
                    'help' => 'Leave empty to use the year and the school name.',
                    'rules' => ['nullable', 'string', 'max:160'],
                ],
                'landing_show_credit' => [
                    'type' => 'boolean', 'label' => 'Show who made the system in the footer', 'default' => true,
                    'rules' => ['boolean'],
                ],
                'landing_credit_text' => [
                    'type' => 'string', 'label' => 'Credit line', 'default' => 'Designed and built by Prince Arvee Avena',
                    'rules' => ['nullable', 'string', 'max:120'],
                ],
                'landing_privacy_text' => [
                    'type' => 'text', 'label' => 'Privacy notice',
                    'default' => "The school clinic collects health information from students, employees and their parents or guardians so that it can give first aid and health services, keep clinic records, and contact a parent or guardian when needed.\n\nWhat we collect: your name, student or employee number, grade or department, contact numbers, parent or guardian details, health history, allergies, vaccinations, and the details of each clinic visit, including any medicine given.\n\nHow we use it: to assess and treat you at the clinic, to issue health clearances and medical certificates, to send appointment and visit notices by text message, and to prepare the clinic's health reports for the school.\n\nWho can see it: only authorized clinic staff, each with their own account. Changes to clinic records are logged. Your information is not sold or shared for marketing.\n\nHow long we keep it: while you are enrolled or employed at the school and for the period required by school policy and the law. After that it is disposed of securely.\n\nYour rights: under the Data Privacy Act of 2012 (Republic Act No. 10173), you may ask to see your clinic record, ask the clinic to correct it, and file a complaint with the National Privacy Commission. To make a request, visit the clinic or use the contact details on this website.",
                    'help' => 'Shown on the Privacy notice page. Leave a blank line between paragraphs.',
                    'rules' => ['nullable', 'string', 'max:10000'],
                ],

                // -- Search engines -------------------------------------------------------
                'landing_seo_title' => [
                    'type' => 'string', 'label' => 'Page title in search results', 'default' => '',
                    'help' => 'Also shown on the browser tab. Leave empty to use the clinic and school names.',
                    'rules' => ['nullable', 'string', 'max:70'],
                ],
                'landing_seo_description' => [
                    'type' => 'text', 'label' => 'Description in search results', 'default' => '',
                    'help' => 'One or two sentences. Leave empty to use the suggested text shown in the box.',
                    'rules' => ['nullable', 'string', 'max:200'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS events
    |--------------------------------------------------------------------------
    | event => [toggle setting key, template setting key, label]
    | {clinic}, {clinic_contact} and {app} are always available in templates.
    */
    'sms_globals' => $smsGlobals,

    'sms_events' => [
        'appointment_created'     => ['toggle' => 'notify_sms_appointment_created',     'template' => 'sms_template_appointment_created', 'label' => 'Appointment booked'],
        'appointment_approved'    => ['toggle' => 'notify_sms_appointment_approved',    'template' => 'sms_template_approval',            'label' => 'Appointment approved'],
        'appointment_rescheduled' => ['toggle' => 'notify_sms_appointment_rescheduled', 'template' => 'sms_template_rescheduled',         'label' => 'Appointment rescheduled'],
        'appointment_cancelled'   => ['toggle' => 'notify_sms_appointment_cancelled',   'template' => 'sms_template_cancellation',        'label' => 'Appointment cancelled'],
        'appointment_reminder'    => ['toggle' => 'notify_sms_appointment_reminder',    'template' => 'sms_template_reminder',            'label' => 'Appointment reminder'],
        'clinic_log'              => ['toggle' => 'sms_log_guardian_enabled',           'template' => 'sms_template_clinic_log',          'label' => 'Guardian visit notice'],
        'clinic_discharge'        => ['toggle' => 'notify_sms_clinic_discharge',        'template' => 'sms_template_discharge',           'label' => 'Guardian discharge notice'],
        'intake_approved'         => ['toggle' => 'notify_sms_intake_approved',         'template' => 'sms_template_intake_approved',     'label' => 'Online health form approved'],
    ],

    /*
    | Friendly names for SMS template placeholders. Staff see and insert these
    | ("[Patient name]"); the stored template keeps the {token}. Presentation only.
    */
    'sms_placeholder_labels' => [
        'name' => 'Patient name', 'full_name' => 'Patient full name', 'guardian' => 'Guardian name',
        'date' => 'Date', 'time' => 'Time', 'reason' => 'Reason', 'purpose' => 'Purpose',
        'complaint' => 'Complaint', 'treatment' => 'Treatment', 'disposition' => 'Outcome',
        'clinic' => 'Clinic name', 'clinic_contact' => 'Clinic phone', 'app' => 'System name',
    ],

    /*
    | Sample values for the live SMS preview in Admin → Settings → SMS.
    */
    'sms_samples' => [
        'name' => 'Maria', 'full_name' => 'Maria L. Santos', 'guardian' => 'Mrs. Santos',
        'date' => 'June 7, 2026', 'time' => '09:00 AM', 'reason' => 'Clinic closed',
        'purpose' => 'Annual check-up', 'complaint' => 'Headache', 'treatment' => 'Rest + Paracetamol',
        'disposition' => 'Sent Home',
    ],
];
