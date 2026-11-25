<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Wedding Planner or Supplier whether the Admin approved their sign-up.
 * A rejected account is deleted afterwards, so that answer goes by email only.
 */
class RegistrationAnswered extends Notification
{
    use Queueable;

    public function __construct(public User $applicant, public bool $approved, public ?string $reason = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->approved ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = config('wedplan.business_name');

        if ($this->approved) {
            return (new MailMessage)
                ->subject("Your {$business} account is approved")
                ->greeting("Hello {$this->applicant->name},")
                ->line("Your {$this->applicant->roleLabel()} account on WedPlanConnect has been approved. You can now log in.")
                ->action('Log in', route('login'));
        }

        $mail = (new MailMessage)
            ->subject("Your {$business} sign-up was not approved")
            ->greeting("Hello {$this->applicant->name},")
            ->line("We're sorry, your {$this->applicant->roleLabel()} sign-up on WedPlanConnect was not approved.");

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        return $mail->line("If you think this is a mistake, please contact {$business}.");
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your account is approved',
            'body' => 'Welcome to WedPlanConnect! You can now use your '.$this->applicant->roleLabel().' account.',
            'url' => route($this->applicant->dashboardRoute()),
        ];
    }
}
