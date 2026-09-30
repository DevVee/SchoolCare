<?php

namespace App\Support;

/**
 * Vendor copy for the product page at "/" (what the app is and does). It is the
 * same for every school, so it lives here and not in Administration > Website.
 * Edit the text below. ":app" becomes the app name (settings('app_name'),
 * falling back to "SchoolCare") and ":ai" the assistant's name
 * (settings('ai_assistant_name'), falling back to "Coco").
 *
 * Everything under 'preview' and each feature's 'demo' fills the small drawn
 * product screens. They are illustrations: generic labels ("Grade 7 student"),
 * never real names, and the page says so under the hero preview.
 *
 * The school's own clinic page (/clinic) stays admin-driven: App\Services\LandingContent.
 */
final class ProductSite
{
    public static function appName(): string
    {
        return self::setting('app_name', 'SchoolCare');
    }

    public static function assistantName(): string
    {
        return self::setting('ai_assistant_name', 'Coco');
    }

    public static function content(): array
    {
        $app = self::appName();
        $ai = self::assistantName();

        $copy = [
            'seo' => [
                'title'       => ':app | School clinic management',
                'description' => ':app keeps a school clinic\'s visits, patients, appointments, medicines and reports in one place, on the school\'s own server.',
            ],

            'hero' => [
                'eyebrow'   => 'School clinic management',
                'title'     => 'Run your school clinic from one place.',
                'lede'      => ':app keeps patient records, clinic visits, appointments and medicine stock together, so the nurse spends less time on paperwork and more time with students.',
                'primary'   => 'Sign in',
                'secondary' => 'Visit the clinic page',
                'note'      => 'Students and parents: the clinic page has today\'s hours, services and appointment requests.',
            ],

            // The drawn dashboard beside the hero.
            'preview' => [
                'label'    => 'Illustration of the :app dashboard',
                'caption'  => 'Illustration. People and numbers are examples.',
                'title'    => 'Dashboard',
                'greeting' => 'Good morning',
                'heading'  => 'Today at the clinic',
                'stats'    => [
                    ['label' => 'Visits today', 'value' => 18],
                    ['label' => 'In the clinic now', 'value' => 3],
                    ['label' => 'Low stock items', 'value' => 2],
                ],
                'in_clinic_title' => 'Currently in clinic',
                'in_clinic' => [
                    ['who' => 'Grade 7 student', 'why' => 'Headache', 'time' => '9:42 AM', 'state' => 'Resting'],
                    ['who' => 'Grade 10 student', 'why' => 'Sprained ankle', 'time' => '10:05 AM', 'state' => 'With the nurse'],
                    ['who' => 'Staff member', 'why' => 'Blood pressure check', 'time' => '10:18 AM', 'state' => 'Waiting'],
                ],
                'brief_title' => ':ai\'s brief',
                'brief' => [
                    '18 visits so far today, most for headache and fever.',
                    'Paracetamol 500 mg is running low, 12 tablets left.',
                    '2 appointments this afternoon, SMS reminders sent.',
                ],
            ],

            'features_eyebrow' => 'Product',
            'features_title'   => 'Built for the way a school clinic works.',
            'features_lead'    => 'From the first visit of the morning to the report at the end of the month.',
            'features' => [
                [
                    'key'   => 'logbook',
                    'icon'  => 'journal-medical',
                    'title' => 'Daily logbook',
                    'body'  => 'Log each visit in seconds: who came in, why, what was given and when they went back to class.',
                    'demo'  => [
                        ['time' => '8:05 AM', 'who' => 'Grade 8 student', 'why' => 'Stomach ache', 'out' => 'Back to class'],
                        ['time' => '8:40 AM', 'who' => 'Grade 11 student', 'why' => 'Fever', 'out' => 'Sent home'],
                        ['time' => '9:12 AM', 'who' => 'Grade 7 student', 'why' => 'Minor cut', 'out' => 'Back to class'],
                        ['time' => '9:42 AM', 'who' => 'Grade 7 student', 'why' => 'Headache', 'out' => 'Resting'],
                    ],
                ],
                [
                    'key'   => 'patients',
                    'icon'  => 'people',
                    'title' => 'Patients',
                    'body'  => 'Find any student by name, course, grade or section, with allergies and visit history on one page.',
                    'demo'  => [
                        'search'  => 'Search patients',
                        'filters' => ['Grade 11', 'STEM', 'Section B'],
                        'results' => [
                            ['who' => 'Grade 11 student', 'where' => 'STEM, Section B', 'flag' => 'Allergy: penicillin'],
                            ['who' => 'Grade 11 student', 'where' => 'STEM, Section B', 'flag' => 'Asthma'],
                        ],
                    ],
                ],
                [
                    'key'   => 'appointments',
                    'icon'  => 'calendar2-check',
                    'title' => 'Appointments and SMS reminders',
                    'body'  => 'Students and parents request a time online. The clinic approves it and a reminder goes out by text.',
                    'demo'  => [
                        'slots' => [
                            ['time' => '1:00 PM', 'what' => 'Check-up', 'state' => 'Approved'],
                            ['time' => '1:30 PM', 'what' => 'Clearance', 'state' => 'Approved'],
                            ['time' => '2:00 PM', 'what' => 'Follow-up', 'state' => 'Pending'],
                        ],
                        'sms' => 'Reminder: your clinic appointment is today at 1:00 PM.',
                    ],
                ],
                [
                    'key'   => 'medicines',
                    'icon'  => 'capsule',
                    'title' => 'Medicines and inventory',
                    'body'  => 'Track every tablet in and out. Low stock and batches close to expiry show up before they become a problem.',
                    'demo'  => [
                        ['name' => 'Paracetamol 500 mg', 'left' => 12, 'of' => 100, 'alert' => 'Low stock'],
                        ['name' => 'Cetirizine 10 mg', 'left' => 64, 'of' => 100, 'alert' => ''],
                        ['name' => 'Oral rehydration salts', 'left' => 38, 'of' => 100, 'alert' => 'Expires in 3 weeks'],
                    ],
                ],
                [
                    'key'   => 'reports',
                    'icon'  => 'bar-chart-line',
                    'title' => 'Reports and PDF exports',
                    'body'  => 'Daily, monthly and yearly reports, medicine use and inventory, plus printable health report cards.',
                    'demo'  => [
                        'title'  => 'Visits by month',
                        'bars'   => [42, 58, 51, 74, 66, 88],
                        'labels' => ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
                        'button' => 'Export PDF',
                    ],
                ],
                [
                    'key'   => 'assistant',
                    'icon'  => 'stars',
                    'title' => ':ai, the AI assistant',
                    'body'  => 'Ask about the clinic in plain words. :ai works from a clinic summary without student names.',
                    'demo'  => [
                        'question' => 'Which grade visited the most this week?',
                        'answer'   => 'Grade 7, mostly for headaches after lunch. Do you want the list of days?',
                    ],
                ],
                [
                    'key'   => 'roles',
                    'icon'  => 'person-lock',
                    'title' => 'Roles, permissions and audit log',
                    'body'  => 'Nurses, doctors and staff see only what their role allows. Every change is written to the audit log.',
                    'demo'  => [
                        'roles' => ['Administrator', 'Nurse', 'Doctor', 'Staff'],
                        'log'   => [
                            ['who' => 'Nurse', 'what' => 'updated a visit', 'time' => '10:42 AM'],
                            ['who' => 'Administrator', 'what' => 'added a user', 'time' => '9:15 AM'],
                            ['who' => 'Doctor', 'what' => 'signed a clearance', 'time' => '8:58 AM'],
                        ],
                    ],
                ],
                [
                    'key'   => 'privacy',
                    'icon'  => 'shield-lock',
                    'title' => 'Data privacy',
                    'body'  => 'Patient records stay in your school\'s own database, on your school\'s own server. You decide who can see them.',
                    'demo'  => [
                        'Runs on your school\'s own server',
                        'Every staff member signs in with their own account',
                        'A privacy notice for students and parents',
                    ],
                ],
            ],

            'steps_eyebrow' => 'How it works',
            'steps_title'   => 'Up and running in three steps.',
            'steps' => [
                ['title' => 'Set up your clinic', 'body' => 'Add your school name, logo, clinic hours and staff accounts in Settings.'],
                ['title' => 'Log every visit', 'body' => 'Nurses record visits, medicines and notes as students come in, on a phone or a computer.'],
                ['title' => 'See the whole picture', 'body' => 'Reports, stock alerts and :ai\'s daily brief show what needs attention.'],
            ],

            'cta' => [
                'title'     => 'Your clinic records, in one place.',
                'body'      => 'Staff sign in with the account their administrator gave them. Students and parents can find hours, services and appointments on the clinic page.',
                'primary'   => 'Sign in',
                'secondary' => 'Visit the clinic page',
            ],

            'footer' => [
                'about' => ':app is a clinic management system for schools: visits, patients, appointments, medicines and reports in one place.',
            ],
        ];

        array_walk_recursive($copy, function (&$value) use ($app, $ai) {
            if (is_string($value)) {
                $value = str_replace([':app', ':ai'], [$app, $ai], $value);
            }
        });

        return ['app' => $app, 'assistant' => $ai] + $copy;
    }

    private static function setting(string $key, string $fallback): string
    {
        try {
            $value = trim((string) settings($key));
        } catch (\Throwable) {
            $value = '';
        }

        return $value !== '' ? $value : $fallback;
    }
}
