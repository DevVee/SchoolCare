<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds the password-reset email with the clinic's branding. Wired in
 * AppServiceProvider via ResetPassword::toMailUsing() so the framework's
 * ResetPassword notification (and its tests) keep working unchanged.
 */
class BrandedResetPassword
{
    public static function mail(object $notifiable, string $token): MailMessage
    {
        $app    = settings('app_name') ?: config('app.name');
        $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $url    = url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject("{$app}: reset your password")
            ->greeting('Hello'.(isset($notifiable->name) ? " {$notifiable->name}" : '').',')
            ->line("We received a request to reset the password for your {$app} account.")
            ->action('Reset password', $url)
            ->line("This link expires in {$expire} minutes.")
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
