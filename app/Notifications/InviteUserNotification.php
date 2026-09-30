<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Invitation for an administrator-created account: a link to choose a password
 * (an "invites" broker token, valid for auth.passwords.invites.expire minutes).
 *
 * Sent immediately rather than queued, so the administrator sees a delivery
 * failure right away. Usage from UserController:
 *     \App\Notifications\InviteUserNotification::sendTo($user);
 */
class InviteUserNotification extends Notification
{
    public function __construct(public string $token)
    {
    }

    /**
     * Issue a fresh invitation token (replacing any earlier one) and email it.
     * Throws when the email cannot be sent.
     */
    public static function sendTo(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        $user->notifyNow(new static($token));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app    = settings('app_name') ?: config('app.name');
        $clinic = settings('clinic_name') ?: $app;
        $days   = max(1, intdiv((int) config('auth.passwords.invites.expire', 4320), 1440));
        $url    = url(route('invitation.show', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject("You're invited to {$app}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$clinic} has invited you to {$app}. Choose a password to finish setting up your account.")
            ->line("Sign-in email: {$notifiable->email}")
            ->action('Accept invitation', $url)
            ->line("This link expires in {$days} ".($days === 1 ? 'day' : 'days').'. If it expires, ask an administrator to send a new invitation.')
            ->salutation("Regards, {$clinic}");
    }
}
