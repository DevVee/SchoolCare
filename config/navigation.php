<?php

/*
|--------------------------------------------------------------------------
| App shell navigation (sidebar)
|--------------------------------------------------------------------------
|
| Rendered by resources/views/layouts/partials/sidebar.blade.php. Resolved per
| request (permissions, feature flags, active state, badge counts) by
| App\View\Composers\NavigationComposer.
|
| Group:  ['label' => 'Clinic', 'items' => [ ...items ]]
|         A group whose items are all hidden is not rendered. 'label' => null
|         renders no section heading.
|
| Item keys:
|   label     (required) Text. ':name' placeholders are filled from
|             'label_settings' => ['name' => 'settings_key'] (e.g. the AI name).
|   route     (required) Route name. Items whose route does not exist are skipped.
|   params    Route parameters (array), optional.
|   icon      Bootstrap Icons name without the "bi-" prefix.
|   module    Module key from config('ui.module_meta') (overview, logbook, patients,
|             appointments, consultations, medicines, inventory, dispensing, reports,
|             sms, ai, admin).
|   active    Route name patterns that mark the item active (default: [route]).
|             Wildcards allowed: 'patients.*'.
|   except    Route name patterns that must NOT mark it active.
|   can       Permission: a string, or an array meaning "any of".
|   feature   Settings key that must be truthy (e.g. 'ai_enabled'), or an
|             array of keys that must all be truthy.
|   badge     Count key computed by NavigationComposer::badgeCounts():
|             todayLogs | pendingAppointments | pendingIntake.
|             Shown as a neutral <x-ui.count> (99+ cap).
|   badge_can    Permission needed to see the count (default: the item's 'can').
|   badge_label  Accessible text for the count, ':count' is replaced.
|
| One item per module, no sub-items: related pages (calendar, categories,
| disposals, health forms...) are reached through page-level tabs, and the
| module item stays active on them through its 'active' patterns.
|
| To add a page: add an item to the right group. No Blade changes are needed.
|
*/

return [

    [
        'label' => 'Overview',
        'items' => [
            [
                'label'  => 'Dashboard',
                'route'  => 'dashboard',
                'module' => 'overview',
                'icon'   => 'house',
                'active' => ['dashboard'],
            ],
        ],
    ],

    [
        'label' => 'Clinic',
        'items' => [
            [
                'label'       => 'Daily patient log',
                'route'       => 'patient-logs.index',
                'module' => 'logbook',
                'icon'        => 'journal-medical',
                'active'      => ['patient-logs.*'],
                'can'         => 'view-patient-logs',
                'badge'       => 'todayLogs',
                'badge_label' => ':count visits today',
            ],
            [
                'label'    => 'Patients',
                'route'    => 'patients.index',
                'module' => 'patients',
                'icon'     => 'people',
                'active'   => ['patients.*'],
                'can'      => 'view-patients',
                // Online health forms waiting for review (only for users who review them)
                'badge'       => 'pendingIntake',
                'badge_can'   => 'review-intake',
                'badge_label' => ':count health forms to review',
            ],
            [
                'label'       => 'Appointments',
                'route'       => 'appointments.index',
                'module' => 'appointments',
                'icon'        => 'calendar-check',
                'active'      => ['appointments.*'],
                'can'         => 'view-appointments',
                'badge'       => 'pendingAppointments',
                'badge_label' => ':count pending',
            ],
            [
                'label'  => 'Specialist visits',
                'route'  => 'specialist-visits.index',
                'module' => 'appointments',
                'icon'   => 'person-badge',
                'active' => ['specialist-visits.*'],
                'can'    => 'view-specialist-visits',
            ],
            [
                'label'  => 'Consultations',
                'route'  => 'consultations.index',
                'module' => 'consultations',
                'icon'   => 'clipboard2-pulse',
                'active' => ['consultations.*'],
                'can'    => 'view-consultations',
            ],
        ],
    ],

    [
        'label' => 'Pharmacy and inventory',
        'items' => [
            [
                'label'    => 'Medicines',
                'route'    => 'medicines.index',
                'module' => 'medicines',
                'icon'     => 'capsule',
                'active'   => ['medicines.*', 'medicine-categories.*'],
                'can'      => 'view-medicines',
            ],
            [
                'label'    => 'Inventory',
                'route'    => 'inventory.index',
                'module' => 'inventory',
                'icon'     => 'box-seam',
                'active'   => ['inventory.*', 'disposals.*'],
                'can'      => 'view-inventory',
            ],
            [
                'label'  => 'Dispensing',
                'route'  => 'dispensing.index',
                'module' => 'dispensing',
                'icon'   => 'prescription2',
                'active' => ['dispensing.*'],
                'can'    => 'view-dispensing',
            ],
            [
                'label'  => 'Assets and equipment',
                'route'  => 'assets.index',
                'module' => 'inventory',
                'icon'   => 'tools',
                'active' => ['assets.*'],
                'can'    => 'view-assets',
            ],
        ],
    ],

    [
        'label' => 'Reports and messaging',
        'items' => [
            [
                'label'  => 'Reports',
                'route'  => 'reports.index',
                'module' => 'reports',
                'icon'   => 'bar-chart',
                'active' => ['reports.*'],
                'can'    => 'view-reports',
            ],
            [
                'label'  => 'SMS',
                'route'  => 'sms.index',
                'module' => 'sms',
                'icon'   => 'chat-dots',
                'active' => ['sms.*'],
                'can'    => 'view-sms',
            ],
            [
                'label'          => 'Ask :name',
                'label_settings' => ['name' => 'ai_assistant_name'],
                'route'          => 'ai-assistant.index',
                'module' => 'ai',
                'icon'           => 'chat-square-text',
                'active'         => ['ai-assistant.*'],
                'can'            => 'use-ai-assistant',
                'feature'        => 'ai_enabled',
            ],
        ],
    ],

    [
        'label' => 'Administration',
        'items' => [
            [
                'label'  => 'Users',
                'route'  => 'admin.users.index',
                'module' => 'admin',
                'icon'   => 'person-gear',
                'active' => ['admin.users.*'],
                'can'    => 'view-users',
            ],
            [
                'label'  => 'Roles and permissions',
                'route'  => 'admin.roles.index',
                'module' => 'admin',
                'icon'   => 'shield-lock',
                'active' => ['admin.roles.*'],
                'can'    => 'manage-roles',
            ],
            [
                'label'  => 'Appointment time slots',
                'route'  => 'admin.appointment-slots.index',
                'module' => 'admin',
                'icon'   => 'clock',
                'active' => ['admin.appointment-slots.*'],
                'can'    => 'manage-appointment-slots',
            ],
            [
                'label'  => 'Settings',
                'route'  => 'admin.settings.index',
                'module' => 'admin',
                'icon'   => 'gear',
                'active' => ['admin.settings.*'],
                'can'    => 'manage-settings',
            ],
            [
                'label'  => 'Website',
                'route'  => 'admin.website.edit',
                'module' => 'admin',
                'icon'   => 'globe2',
                'active' => ['admin.website.*'],
                'can'    => 'manage-landing',
            ],
            [
                'label'  => 'Audit logs',
                'route'  => 'admin.audit-logs.index',
                'module' => 'admin',
                'icon'   => 'journal-text',
                'active' => ['admin.audit-logs.*'],
                'can'    => 'view-audit-logs',
            ],
            [
                'label'  => 'UI kit',
                'route'  => 'ui.kit',
                'module' => 'admin',
                'icon'   => 'grid-1x2',
                'can'    => 'manage-settings',
            ],
        ],
    ],

];
