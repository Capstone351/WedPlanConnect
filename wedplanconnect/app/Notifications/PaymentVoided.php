<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the couple a recorded payment was voided, by whom and why, so no payment disappears unnoticed.
 */
class PaymentVoided extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public string $voidedBy) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->payment->booking;

        return (new MailMessage)
            ->subject('A payment record was voided: '.$this->money($this->payment->amount))
            ->line('A payment on your wedding booking was voided:')
            ->line('**Amount:** '.$this->money($this->payment->amount).' ('.$this->payment->methodLabel().', '.$this->payment->payment_date->format('F j, Y').')')
            ->line('**Receipt #:** '.($this->payment->receipt_number ?: 'None'))
            ->line("**Voided by:** {$this->voidedBy}")
            ->line("**Reason:** {$this->payment->void_reason}")
            ->line('**Remaining balance now:** '.$this->money($booking->balance()))
            ->line("If you didn't expect this, please contact your planner right away.")
            ->action('Open My Wedding', route('client.dashboard'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment voided: '.$this->money($this->payment->amount),
            'body' => "Voided by {$this->voidedBy} · Reason: {$this->payment->void_reason} · Remaining balance: ".$this->money($this->payment->booking->balance()),
            'url' => route('client.dashboard'),
        ];
    }

    private function money(mixed $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }
}
