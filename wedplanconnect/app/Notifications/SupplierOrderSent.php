<?php

namespace App\Notifications;

use App\Models\BookingProduct;
use App\Models\BookingSupplier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Tells a supplier that a Wedding Planner has sent them an order to review and accept.
 * Suppliers with a vendor login also get it in-app; others only by email.
 */
class SupplierOrderSent extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, BookingProduct>  $items  this supplier's products ordered for the wedding
     */
    public function __construct(public BookingSupplier $assignment, public Collection $items) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->assignment->booking;
        $mail = (new MailMessage)
            ->subject("New order: {$booking->client_name} wedding on {$booking->event_date->format('M j, Y')}")
            ->greeting("Hello {$this->assignment->supplier->name},")
            ->line("{$booking->planner->name} of ".config('wedplan.business_name')." has sent you an order for a wedding on {$booking->event_date->format('l, F j, Y')} at {$booking->venue}.");

        foreach ($this->items as $item) {
            $mail->line("• {$item->product->name} × {$item->quantity}, ready by {$item->due_date?->format('M j, Y')}".($item->notes ? " ({$item->notes})" : ''));
        }

        if ($this->items->isEmpty()) {
            $mail->line('No specific products were listed. The planner is booking your services for this date.');
        }

        return $notifiable instanceof User
            ? $mail->action('Review and accept the order', route('vendor.bookings.show', $booking))
            : $mail->line("Please reply to {$booking->planner->name} ({$booking->planner->email}) to accept or decline.");
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->assignment->booking;

        return [
            'title' => 'New order to accept',
            'body' => "{$booking->client_name} · {$booking->event_date->format('M j, Y')} · {$this->items->count()} product(s)",
            'url' => route('vendor.bookings.show', $booking),
        ];
    }
}
