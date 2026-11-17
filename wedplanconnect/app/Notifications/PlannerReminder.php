<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A nudge from the Admin to a Wedding Planner about overdue tasks and past weddings left open.
 */
class PlannerReminder extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $items
     */
    public function __construct(public array $items, public string $adminName) {}

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
            ->subject('Reminder from '.$this->adminName)
            ->line("{$this->adminName} asked you to follow up on:");

        foreach ($this->items as $item) {
            $mail->line('• '.$item);
        }

        return $mail->action('Open your dashboard', route('planner.dashboard'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Reminder from '.$this->adminName,
            'body' => implode("\n", $this->items),
            'url' => route('planner.dashboard'),
        ];
    }
}
