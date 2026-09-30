<?php

/*
|--------------------------------------------------------------------------
| UI kit: tone mapping for x-ui.badge / x-ui.status-badge
|--------------------------------------------------------------------------
|
| Tones (see resources/scss/_tokens.scss $tones). One colour theme: every tone
| renders as the brand blue (runtime-themable) except danger (red) and neutral
| (slate). The names are kept so statuses still say what they mean:
|   brand, success, warning, danger, info, neutral, orange, teal, cobi
|
| `bs_tone` lets components accept the Bootstrap colour names that existing
| model accessors return (Appointment::status_badge, AuditLog::action_badge,
| InventoryTransaction::type_badge, PatientLog::disposition_color, ...).
|
*/

return [

    'tones' => ['brand', 'success', 'warning', 'danger', 'info', 'neutral', 'orange', 'teal', 'cobi'],

    'bs_tone' => [
        'primary'   => 'brand',
        'secondary' => 'neutral',
        'light'     => 'neutral',
        'dark'      => 'neutral',
        'muted'     => 'neutral',
        'gray'      => 'neutral',
        'grey'      => 'neutral',
        'success'   => 'success',
        'warning'   => 'warning',
        'danger'    => 'danger',
        'info'      => 'info',
        'blue'      => 'brand',
        'green'     => 'success',
        'red'       => 'danger',
        'yellow'    => 'warning',
        'amber'     => 'warning',
        'cyan'      => 'info',
        'teal'      => 'teal',
        'orange'    => 'orange',
        'purple'    => 'cobi',
        'violet'    => 'cobi',
        'indigo'    => 'brand',
    ],

    /*
    | Module registry for x-ui.icon-chip / page-header / card / empty-state / stat-strip.
    | One colour theme: every module icon is the brand blue (.tone-{key},
    | .tone-chip-{key}, --m-{key} in resources/scss/components/_tones.scss).
    | `module_aliases` maps other names to a key.
    */
    'module_meta' => [
        'overview'      => ['label' => 'Dashboard',      'icon' => 'grid-1x2'],
        'logbook'       => ['label' => 'Clinic log',     'icon' => 'journal-medical'],
        'patients'      => ['label' => 'Patients',       'icon' => 'people'],
        'appointments'  => ['label' => 'Appointments',   'icon' => 'calendar-check'],
        'consultations' => ['label' => 'Consultations',  'icon' => 'clipboard2-pulse'],
        'medicines'     => ['label' => 'Medicines',      'icon' => 'capsule'],
        'inventory'     => ['label' => 'Inventory',      'icon' => 'box-seam'],
        'dispensing'    => ['label' => 'Dispensing',     'icon' => 'prescription2'],
        'reports'       => ['label' => 'Reports',        'icon' => 'bar-chart-line'],
        'sms'           => ['label' => 'Text messages',  'icon' => 'chat-dots'],
        'ai'            => ['label' => 'Assistant',      'icon' => 'robot'],
        'admin'         => ['label' => 'Administration', 'icon' => 'gear'],
    ],

    'module_aliases' => [
        'dashboard' => 'overview', 'patient-logs' => 'logbook', 'visits' => 'logbook', 'health-forms' => 'patients',
        'calendar' => 'appointments', 'specialists' => 'appointments', 'medicine-categories' => 'medicines',
        'expiry' => 'medicines', 'disposals' => 'inventory', 'assets' => 'inventory', 'ai-assistant' => 'ai',
        'cobi' => 'ai', 'users' => 'admin', 'roles' => 'admin', 'settings' => 'admin', 'audit-logs' => 'admin',
    ],

    /*
    | Status → tone. x-ui.status-badge looks in the `type` map first (when a
    | type is given), then in `default`. Unknown statuses render neutral.
    */
    'status' => [

        'default' => [
            'pending'    => 'warning',
            'approved'   => 'success',
            'completed'  => 'brand',
            'cancelled'  => 'danger',
            'canceled'   => 'danger',
            'no_show'    => 'neutral',
            'active'     => 'success',
            'inactive'   => 'neutral',
            'sent'       => 'success',
            'delivered'  => 'success',
            'failed'     => 'danger',
            'skipped'    => 'neutral',
            'queued'     => 'info',
            'expired'    => 'danger',
            'expiring'   => 'orange',
            'low_stock'  => 'warning',
            'out_of_stock' => 'danger',
            'in_stock'   => 'success',
            'ok'         => 'success',
            'draft'      => 'neutral',
            'scheduled'  => 'info',
            'rejected'   => 'danger',
            'enabled'    => 'success',
            'disabled'   => 'neutral',
        ],

        'appointment' => [
            'pending' => 'warning', 'approved' => 'success', 'completed' => 'brand',
            'cancelled' => 'danger', 'no_show' => 'neutral',
        ],

        'disposition' => [
            'rest_in_clinic' => 'info', 'returned_to_class' => 'success', 'sent_home' => 'warning',
            'referred_to_hospital' => 'danger', 'further_observation' => 'neutral',
        ],

        'audit' => [
            'created' => 'success', 'updated' => 'warning', 'deleted' => 'danger', 'logged_in' => 'brand',
            'logged_out' => 'neutral', 'exported' => 'info', 'approved' => 'success', 'cancelled' => 'danger',
            'code_sent' => 'info', 'code_not_sent' => 'warning', 'code_verified' => 'brand', 'code_failed' => 'warning',
            'code_locked' => 'warning',
        ],

        'inventory' => [
            'stock_in' => 'success', 'stock_out' => 'danger', 'dispensed' => 'orange', 'adjustment' => 'info',
        ],

        'sms' => [
            'sent' => 'success', 'failed' => 'danger', 'pending' => 'warning', 'skipped' => 'neutral',
        ],

        'patient' => [
            '1' => 'success', '0' => 'neutral', 'active' => 'success', 'inactive' => 'neutral',
        ],

        'stock' => [
            'ok' => 'success', 'in_stock' => 'success', 'low' => 'warning', 'low_stock' => 'warning',
            'out' => 'danger', 'out_of_stock' => 'danger', 'expiring' => 'orange', 'expired' => 'danger',
        ],

        'severity' => [
            'mild' => 'amber', 'low' => 'amber', 'moderate' => 'orange', 'medium' => 'orange',
            'severe' => 'danger', 'high' => 'danger', 'critical' => 'danger', 'emergency' => 'danger',
        ],

        'role' => [
            'administrator' => 'brand', 'admin' => 'brand', 'nurse' => 'info', 'doctor' => 'teal',
            'staff' => 'success', 'viewer' => 'neutral',
        ],
    ],

    /*
    | Human labels for statuses whose headline form reads oddly.
    */
    'status_labels' => [
        'no_show'  => 'No-show',
        'low_stock' => 'Low stock',
        'out_of_stock' => 'Out of stock',
        '1' => 'Active',
        '0' => 'Inactive',
    ],
];
