<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the couple their booking was cancelled (by the planner, or because it wasn't confirmed in time) and why.
 */
class BookingCancelled extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking, public string $reason) {}

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
            ->subject('Your wedding booking was cancelled')
            ->line('Your booking for '.$this->booking->event_date->format('l, F j, Y').' has been cancelled.')
            ->line("**Reason:** {$this->reason}")
            ->line('You can choose a date and send a new booking at any time.')
            ->action('Open My Wedding', route('client.dashboard'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your booking was cancelled',
            'body' => $this->booking->event_date->format('M j, Y').' · Reason: '.$this->reason,
            'url' => route('client.dashboard'),
        ];
    }
}
