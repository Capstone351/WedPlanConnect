<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admin that a planner asked for a payment to be voided, so the admin can approve or keep it.
 */
class PaymentVoidRequested extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

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
            ->subject("Void request: {$this->amount()} for {$this->payment->booking->client_name}")
            ->line("{$this->payment->voidRequester->name} asked you to void a payment of {$this->amount()} ({$this->payment->methodLabel()}, {$this->payment->payment_date->format('F j, Y')}) for {$this->payment->booking->client_name}.")
            ->line("**Reason:** {$this->payment->void_request_reason}")
            ->action('Review the request', route('admin.dashboard'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Void request: {$this->amount()}",
            'body' => "{$this->payment->booking->client_name} · by {$this->payment->voidRequester->name} · Reason: {$this->payment->void_request_reason}",
            'url' => route('admin.dashboard'),
        ];
    }

    private function amount(): string
    {
        return '₱'.number_format((float) $this->payment->amount, 2);
    }
}
