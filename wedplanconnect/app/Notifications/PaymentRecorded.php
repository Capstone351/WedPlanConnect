<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Gives the couple their own record of every payment the planner logs, so nothing can change unnoticed.
 */
class PaymentRecorded extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public string $recordedBy) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Every detail of the payment and where the contract stands after it.
     *
     * @return array<string, string>
     */
    public function details(): array
    {
        $booking = $this->payment->booking;

        return [
            'Amount' => $this->money($this->payment->amount),
            'Method' => $this->payment->methodLabel(),
            'Date' => $this->payment->payment_date->format('F j, Y'),
            'Receipt #' => $this->payment->receipt_number ?: 'None',
            'Notes' => $this->payment->notes ?: 'None',
            'Recorded by' => $this->recordedBy,
            'Wedding' => $booking->client_name.' · '.$booking->event_date->format('F j, Y'),
            'Contract amount' => $this->money($booking->total_amount),
            'Total paid' => $this->money($booking->totalPaid()),
            'Remaining balance' => $this->money($booking->balance()),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Payment received: '.$this->money($this->payment->amount))
            ->line('Your planner recorded a payment for your wedding. Here are the details:');

        foreach ($this->details() as $label => $value) {
            $mail->line("**{$label}:** {$value}");
        }

        return $mail
            ->line('If any of these details are wrong, please contact your planner right away.')
            ->action('View my wedding', route('client.dashboard'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $details = $this->details();

        return [
            'title' => 'Payment received: '.$details['Amount'],
            'body' => collect($details)->except(['Amount', 'Wedding'])
                ->reject(fn (string $value) => $value === 'None')
                ->map(fn (string $value, string $label) => "{$label}: {$value}")
                ->implode(' · '),
            'url' => route('client.dashboard'),
        ];
    }

    private function money(mixed $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }
}
