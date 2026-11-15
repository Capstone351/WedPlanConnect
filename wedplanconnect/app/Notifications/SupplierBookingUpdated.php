<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a supplier about a change to a wedding they were assigned to: removed from it, the booking
 * was cancelled, or its date or venue changed. Suppliers with a vendor login also get it in-app.
 */
class SupplierBookingUpdated extends Notification
{
    use Queueable;

    /**
     * @param  string  $title  e.g. "Wedding cancelled"
     * @param  string  $message  what happened, in one sentence
     * @param  bool  $stillAssigned  false when the supplier no longer works on this wedding
     */
    public function __construct(public Booking $booking, public string $title, public string $message, public bool $stillAssigned = true) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$this->title}: {$this->booking->client_name} wedding")
            ->line($this->message);

        return $notifiable instanceof User
            ? $mail->action($this->stillAssigned ? 'Open the wedding' : 'Open my dashboard', $this->url())
            : $mail->line("For questions, contact {$this->booking->planner->name} ({$this->booking->planner->email}).");
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->message, 'url' => $this->url()];
    }

    private function url(): string
    {
        return $this->stillAssigned ? route('vendor.bookings.show', $this->booking) : route('vendor.dashboard');
    }
}
