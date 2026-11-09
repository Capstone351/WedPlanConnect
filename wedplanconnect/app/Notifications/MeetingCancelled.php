<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Wedding Planner that a couple cancelled their meeting.
 */
class MeetingCancelled extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $couple = $this->meeting->client->coupleNames() ?? $this->meeting->client->name;

        return (new MailMessage)
            ->subject("{$couple} cancelled their meeting")
            ->line("{$couple} cancelled the meeting on {$this->meeting->when()}.")
            ->action('Open meetings', route('meetings.index'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $couple = $this->meeting->client->coupleNames() ?? $this->meeting->client->name;

        return [
            'title' => "{$couple} cancelled their meeting",
            'body' => $this->meeting->when(),
            'url' => route('meetings.index'),
        ];
    }
}
