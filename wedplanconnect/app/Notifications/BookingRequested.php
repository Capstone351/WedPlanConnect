<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

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
            ->line("{$this->booking->client_name} sent you a booking for {$this->booking->event_date->format('F j, Y')} at {$this->booking->venueLabel()} ({$this->booking->packageLabel()}).")
            ->line($this->booking->missingBeforeConfirm()
                ? 'Meet the couple to agree on the '.Arr::join($this->booking->missingBeforeConfirm(), ', ', ' and ').', then confirm the booking.'
                : 'Package price: ₱'.number_format((float) $this->booking->total_amount, 2).'. Confirm it to generate their QR code.')
            ->action('Open the booking', route('bookings.show', $this->booking));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "New booking from {$this->booking->client_name}",
            'body' => "{$this->booking->event_date->format('M j, Y')} · {$this->booking->venueLabel()} · {$this->booking->packageLabel()} · ".((float) $this->booking->total_amount > 0 ? '₱'.number_format((float) $this->booking->total_amount, 0) : 'price to be discussed'),
            'url' => route('bookings.show', $this->booking),
        ];
    }
}
