<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the Admin that a Wedding Planner or Supplier signed up and is waiting for approval.
 */
class StaffRegistered extends Notification
{
    use Queueable;

    public function __construct(public User $applicant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New {$this->applicant->roleLabel()} sign-up: {$this->applicant->name}")
            ->line("{$this->applicant->name} ({$this->applicant->email}) signed up as a {$this->applicant->roleLabel()}.")
            ->line($this->details())
            ->line('They cannot log in until you approve their account.')
            ->action('Review sign-ups', route('admin.users.index', ['status' => 'pending']));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "New {$this->applicant->roleLabel()} sign-up: {$this->applicant->name}",
            'body' => $this->details().' Waiting for your approval.',
            'url' => route('admin.users.index', ['status' => 'pending']),
        ];
    }

    private function details(): string
    {
        $business = $this->applicant->pending_supplier;

        return $business
            ? "Business: {$business['name']} ({$business['category']})."
            : "Email: {$this->applicant->email} · Mobile: {$this->applicant->phone}.";
    }
}
