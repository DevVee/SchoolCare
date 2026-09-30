<?php

/*
|--------------------------------------------------------------------------
| Permission catalogue
|--------------------------------------------------------------------------
|
| Single source of truth for every permission the application checks.
| It drives:
|   - RolePermissionSeeder (creates missing permissions on every boot)
|   - the role permission matrix (admin/roles create + edit)
|
| Each module lists its standard CRUD columns under 'actions'
| (view / create / update / delete -> permission name) and any additional
| permissions under 'other' (permission name -> label).
|
| Permission names are verb-module strings (e.g. view-patients) and must
| never be renamed: they are stored in the database and referenced by
| controllers, FormRequests, policies and Blade @can directives.
|
*/

return [

    'guard' => 'web',

    // Column headings for the permission matrix.
    'columns' => [
        'view'   => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
    ],

    'modules' => [

        'patient-logs' => [
            'label'   => 'Clinic Logbook',
            'icon'    => 'journal-medical',
            'actions' => [
                'view'   => 'view-patient-logs',
                'create' => 'create-patient-logs',
                'update' => 'update-patient-logs',
                'delete' => 'delete-patient-logs',
            ],
            'other' => [],
        ],

        'patients' => [
            'label'   => 'Patients',
            'icon'    => 'person-lines-fill',
            'actions' => [
                'view'   => 'view-patients',
                'create' => 'create-patients',
                'update' => 'update-patients',
                'delete' => 'delete-patients',
            ],
            'other' => [
                'restore-patients' => 'Restore deleted',
                'import-patients'  => 'Import',
                'export-patients'  => 'Export',
                'review-intake'    => 'Review online health forms',
            ],
        ],

        'appointments' => [
            'label'   => 'Appointments',
            'icon'    => 'calendar-check-fill',
            'actions' => [
                'view'   => 'view-appointments',
                'create' => 'create-appointments',
                'update' => 'update-appointments',
                'delete' => 'delete-appointments',
            ],
            'other' => [
                'approve-appointments'     => 'Approve',
                'cancel-appointments'      => 'Cancel',
                'complete-appointments'    => 'Complete / No-show',
                'manage-appointment-slots' => 'Manage time slots',
            ],
        ],

        'specialist-visits' => [
            'label'   => 'Specialist Visits',
            'icon'    => 'person-badge-fill',
            'actions' => [
                'view' => 'view-specialist-visits',
            ],
            'other' => [
                'manage-specialist-visits' => 'Schedule / edit / cancel',
            ],
        ],

        'consultations' => [
            'label'   => 'Consultations',
            'icon'    => 'clipboard2-pulse-fill',
            'actions' => [
                'view'   => 'view-consultations',
                'create' => 'create-consultations',
                'update' => 'update-consultations',
                'delete' => 'delete-consultations',
            ],
            'other' => [],
        ],

        'medicines' => [
            'label'   => 'Medicines',
            'icon'    => 'capsule',
            'actions' => [
                'view'   => 'view-medicines',
                'create' => 'create-medicines',
                'update' => 'update-medicines',
                'delete' => 'delete-medicines',
            ],
            'other' => [
                'dispose-medicines' => 'Dispose expired batches',
            ],
        ],

        'inventory' => [
            'label'   => 'Inventory',
            'icon'    => 'box-seam-fill',
            'actions' => [
                'view' => 'view-inventory',
            ],
            'other' => [
                'manage-inventory' => 'Stock in / out',
            ],
        ],

        'assets' => [
            'label'   => 'Assets & Equipment',
            'icon'    => 'tools',
            'actions' => [
                'view' => 'view-assets',
            ],
            'other' => [
                'manage-assets' => 'Add, edit and remove assets',
            ],
        ],

        'dispensing' => [
            'label'   => 'Dispensing',
            'icon'    => 'prescription2',
            'actions' => [
                'view'   => 'view-dispensing',
                'create' => 'create-dispensing',
            ],
            'other' => [],
        ],

        'reports' => [
            'label'   => 'Reports',
            'icon'    => 'bar-chart-fill',
            'actions' => [
                'view' => 'view-reports',
            ],
            'other' => [
                'export-reports' => 'Export (PDF / CSV)',
            ],
        ],

        'sms' => [
            'label'   => 'SMS',
            'icon'    => 'chat-dots-fill',
            'actions' => [
                'view' => 'view-sms',
            ],
            'other' => [
                'send-sms' => 'Send SMS',
            ],
        ],

        'ai-assistant' => [
            'label'   => 'AI Assistant',
            'icon'    => 'stars',
            'actions' => [],
            'other'   => [
                'use-ai-assistant' => 'Use assistant',
            ],
        ],

        'users' => [
            'label'   => 'Users',
            'icon'    => 'people-fill',
            'actions' => [
                'view' => 'view-users',
            ],
            'other' => [
                'manage-users' => 'Manage (create, edit, deactivate, reset password, delete)',
            ],
        ],

        'roles' => [
            'label'   => 'Roles & Permissions',
            'icon'    => 'shield-lock-fill',
            'actions' => [],
            'other'   => [
                'manage-roles' => 'Manage roles',
            ],
        ],

        'settings' => [
            'label'   => 'Settings',
            'icon'    => 'gear-fill',
            'actions' => [],
            'other'   => [
                'manage-settings' => 'System settings',
                'manage-landing'  => 'Landing page content',
            ],
        ],

        'audit-logs' => [
            'label'   => 'Audit Logs',
            'icon'    => 'journal-text',
            'actions' => [
                'view' => 'view-audit-logs',
            ],
            'other' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default permission sets
    |--------------------------------------------------------------------------
    |
    | Applied by RolePermissionSeeder ONLY when the role is created for the
    | first time. Existing roles are never modified (admin edits survive
    | every deploy). The super-admin role (config('clinovia.super_admin_role'))
    | always receives every permission and is not listed here.
    |
    */
    'default_roles' => [

        'nurse' => [
            'view-patient-logs', 'create-patient-logs', 'update-patient-logs',
            'view-patients', 'create-patients', 'update-patients', 'import-patients', 'export-patients', 'review-intake',
            'view-appointments', 'create-appointments', 'update-appointments',
            'approve-appointments', 'cancel-appointments', 'complete-appointments',
            'view-specialist-visits', 'manage-specialist-visits',
            'view-consultations', 'create-consultations', 'update-consultations',
            'view-medicines', 'create-medicines', 'update-medicines', 'delete-medicines', 'dispose-medicines',
            'view-inventory', 'manage-inventory',
            'view-assets',
            'view-dispensing', 'create-dispensing',
            'view-reports', 'export-reports',
            'view-sms', 'send-sms',
            'use-ai-assistant',
        ],

        'staff' => [
            'view-patient-logs',
            'view-patients',
            'view-appointments', 'create-appointments',
            'view-specialist-visits',
            'view-consultations',
        ],

        'viewer' => [
            'view-patient-logs',
            'view-patients',
            'view-appointments',
            'view-specialist-visits',
            'view-consultations',
            'view-medicines',
            'view-inventory',
            'view-dispensing',
            'view-reports',
            'use-ai-assistant',
        ],

    ],

];
