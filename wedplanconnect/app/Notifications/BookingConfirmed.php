<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the couple their planner confirmed the booking and their QR code is ready.
 */
class BookingConfirmed extends Notification
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
            ->subject('Your wedding booking is confirmed')
            ->line("{$this->booking->planner->name} confirmed your wedding on {$this->booking->event_date->format('l, F j, Y')}.")
            ->line('Your QR code is ready. Save it to your phone or print it to follow your wedding preparations.')
            ->action('View my QR code', route('client.qr', $this->booking));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your booking is confirmed',
            'body' => $this->booking->event_date->format('M j, Y').' · Your QR code is ready',
            'url' => route('client.qr', $this->booking),
        ];
    }
}
