<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super-admin role
    |--------------------------------------------------------------------------
    |
    | Users holding this role pass every plain permission check
    | (Gate::before in AppServiceProvider). Model-policy checks that receive
    | arguments (e.g. @can('update', $appointment)) still run, so state rules
    | such as "only pending appointments can be edited" keep applying.
    | This role always holds every permission and cannot be edited or deleted.
    |
    */
    'super_admin_role' => 'administrator',

    /*
    |--------------------------------------------------------------------------
    | System roles
    |--------------------------------------------------------------------------
    |
    | Built-in roles that cannot be deleted from Roles & Permissions.
    | Their permissions (except the super-admin role) remain editable.
    |
    */
    'system_roles' => ['administrator', 'nurse', 'staff', 'viewer'],

];
