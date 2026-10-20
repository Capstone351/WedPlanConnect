<?php

namespace App\Notifications;

use App\Models\BookingSupplier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the booking's Wedding Planner that a supplier accepted or declined their order.
 */
class SupplierOrderAnswered extends Notification
{
    use Queueable;

    public function __construct(public BookingSupplier $assignment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->assignment->booking;
        $mail = (new MailMessage)
            ->subject("{$this->assignment->supplier->name} {$this->verb()} the order for {$booking->client_name}")
            ->line("{$this->assignment->supplier->name} {$this->verb()} the order for the {$booking->client_name} wedding on {$booking->event_date->format('M j, Y')}.");

        if ($this->assignment->response_note) {
            $mail->line("Their note: \"{$this->assignment->response_note}\"");
        }

        return $mail->action('Open the booking', route('bookings.show', $booking));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->assignment->booking;

        return [
            'title' => "{$this->assignment->supplier->name} {$this->verb()} the order",
            'body' => "{$booking->client_name} · {$booking->event_date->format('M j, Y')}".($this->assignment->response_note ? " · \"{$this->assignment->response_note}\"" : ''),
            'url' => route('bookings.show', $booking),
        ];
    }

    private function verb(): string
    {
        return $this->assignment->status === 'confirmed' ? 'accepted' : 'declined';
    }
}
