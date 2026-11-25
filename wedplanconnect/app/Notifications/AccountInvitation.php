<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to an account the Admin created: a link where the person chooses their own password.
 */
class AccountInvitation extends Notification
{
    use Queueable;

    /** How long the link works, matching the "invites" password broker. */
    public const VALID_DAYS = 3;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = config('wedplan.business_name');

        return (new MailMessage)
            ->subject("Set up your {$business} account")
            ->greeting("Hello {$notifiable->name},")
            ->line("An account was created for you on WedPlanConnect, {$business}'s wedding planning system, as a {$notifiable->roleLabel()}.")
            ->line('Click the button below to choose your password. Then you can log in with this email address.')
            ->action('Set my password', $this->url($notifiable))
            ->line('This link works for '.self::VALID_DAYS.' days. If it expires, ask the administrator to send a new one.');
    }

    public function url(object $notifiable): string
    {
        return route('password.setup', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}
