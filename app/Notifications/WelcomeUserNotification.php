<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Welcome email for an administrator-created account, with a link to set a
 * password (a standard password-reset token, valid for auth.passwords.*.expire).
 *
 * Usage from UserController::store():
 *     \App\Notifications\WelcomeUserNotification::sendTo($user);
 */
class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $token)
    {
        $this->afterCommit();
    }

    /**
     * Send the welcome email if "notify_email_user_created" is enabled.
     * Returns true when the notification was dispatched.
     */
    public static function sendTo(User $user): bool
    {
        if (! settings('notify_email_user_created', true) || empty($user->email)) {
            return false;
        }

        try {
            $token = Password::broker()->createToken($user);
            $user->notify(new static($token));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Welcome email could not be dispatched', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app    = settings('app_name') ?: config('app.name');
        $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $url    = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject("Welcome to {$app}")
            ->greeting("Hello {$notifiable->name},")
            ->line("An account has been created for you on {$app}.")
            ->line("Sign-in email: {$notifiable->email}")
            ->action('Set Your Password', $url)
            ->line("This link expires in {$expire} minutes. After that, use \"Forgot password\" on the login page to get a new one.")
            ->salutation('Regards, '.(settings('clinic_name') ?: $app));
    }
}
