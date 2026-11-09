<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Wedding Planner that a couple sent them a booking to price and confirm.
 */
class BookingRequested extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking) {}

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
            ->subject("New booking from {$this->booking->client_name}")
            ->line("{$this->booking->client_name} sent you a booking for {$this->booking->event_date->format('F j, Y')} at {$this->booking->venue} ({$this->booking->package}).")
            ->line('Set the price, then confirm it to generate their QR code.')
            ->action('Open the booking', route('bookings.show', $this->booking));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "New booking from {$this->booking->client_name}",
            'body' => "{$this->booking->event_date->format('M j, Y')} · {$this->booking->venue} · {$this->booking->package} · price to be set",
            'url' => route('bookings.show', $this->booking),
        ];
    }
}
