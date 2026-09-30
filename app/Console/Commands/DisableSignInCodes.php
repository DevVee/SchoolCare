<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * Emergency switch: turns off Settings > Security > "Ask for a sign-in code by
 * email" from the server, for when codes cannot be delivered and nobody can
 * sign in to turn it off. Remembered devices are kept.
 *
 *     php artisan auth:otp-off
 */
class DisableSignInCodes extends Command
{
    protected $signature = 'auth:otp-off';

    protected $description = 'Turn off email sign-in codes (emergency switch when nobody can sign in)';

    public function handle(SettingsService $settings): int
    {
        if (! $settings->get('otp_enabled', false)) {
            $this->info('Sign-in codes are already off. People sign in with their email and password only.');

            return self::SUCCESS;
        }

        $settings->setMany(['otp_enabled' => false]);

        AuditLogService::log(
            action: 'updated',
            module: 'settings',
            description: 'Turned off sign-in codes by email from the server (php artisan auth:otp-off)',
            oldValues: ['otp_enabled' => 'true'],
            newValues: ['otp_enabled' => 'false'],
        );

        $this->info('Sign-in codes are now off. People sign in with their email and password only.');
        $this->line('Turn them on again in Admin > Settings > Security once email is working.');

        return self::SUCCESS;
    }
}
