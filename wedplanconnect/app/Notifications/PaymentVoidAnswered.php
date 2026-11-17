<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the planner whether the admin voided the payment they asked about, or kept it.
 */
class PaymentVoidAnswered extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public bool $voided, public string $adminName, public ?string $note = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->line($this->summary());

        if ($this->note) {
            $mail->line("**Admin's note:** {$this->note}");
        }

        return $mail->action('Open payments', $this->url());
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->summary().($this->note ? " Note: {$this->note}" : ''),
            'url' => $this->url(),
        ];
    }

    private function title(): string
    {
        $amount = '₱'.number_format((float) $this->payment->amount, 2);

        return $this->voided ? "Payment voided: {$amount}" : "Payment kept: {$amount}";
    }

    private function summary(): string
    {
        return $this->voided
            ? "{$this->adminName} voided the payment for {$this->payment->booking->client_name}, as you asked. The couple has been told."
            : "{$this->adminName} kept the payment for {$this->payment->booking->client_name} on record.";
    }

    private function url(): string
    {
        return route('payments.index', ['booking_id' => $this->payment->booking_id]);
    }
}
