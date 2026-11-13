<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\BookingChange;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Tells the couple which booking details their planner changed (date, venue, package, price), from what to what, and why.
 */
class BookingChanged extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, BookingChange>  $changes
     */
    public function __construct(public Booking $booking, public Collection $changes, public ?string $reason, public string $changedBy) {}

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
            ->subject('Your wedding booking was updated')
            ->line("{$this->changedBy} updated your wedding booking:");

        foreach ($this->changes as $change) {
            $mail->line('**'.$change->label().':** '.BookingChange::display($change->field, $change->old_value).' → '.BookingChange::display($change->field, $change->new_value));
        }

        if ($this->reason) {
            $mail->line("**Reason:** {$this->reason}");
        }

        return $mail
            ->line("If you didn't agree to these changes, please contact your planner right away.")
            ->action('Open My Wedding', route('client.dashboard'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $body = $this->changes->map->summary()->implode(' · ');

        return [
            'title' => 'Your booking was updated',
            'body' => $body.($this->reason ? ' · Reason: '.$this->reason : ''),
            'url' => route('client.dashboard'),
        ];
    }
}
