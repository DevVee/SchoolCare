<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * @param  User|null  $actor  who the entry is about when nobody is signed in yet
     *                            (e.g. an email sign-in code before the sign-in completes);
     *                            defaults to the signed-in user.
     */
    public static function log(
        string  $action,
        string  $module,
        ?string $description = null,
        ?array  $oldValues   = null,
        ?array  $newValues   = null,
        ?User   $actor       = null,
    ): void {
        $user = $actor ?? auth()->user();

        AuditLog::create([
            'user_id'     => $user?->id,
            'user_name'   => $user?->name ?? 'System',
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
        ]);
    }
}
