<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Wedding Planner that a booking was given to them by someone else (the admin created it for
 * them or moved it to them from another planner).
 */
class BookingAssigned extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking, public string $byName) {}

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
            ->subject("New booking for you: {$this->booking->client_name}")
            ->line("{$this->byName} assigned you the {$this->booking->client_name} wedding on {$this->booking->event_date->format('l, F j, Y')}.")
            ->line('Its open tasks are now yours.')
            ->action('Open the booking', route('bookings.show', $this->booking));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'A booking was assigned to you',
            'body' => "{$this->booking->client_name} · {$this->booking->event_date->format('M j, Y')} · by {$this->byName}",
            'url' => route('bookings.show', $this->booking),
        ];
    }
}
