<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Wedding Planner that a couple asked to meet them.
 */
class MeetingRequested extends Notification
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
            ->subject("Meeting request from {$couple}")
            ->line("{$couple} would like to meet on {$this->meeting->when()} ({$this->meeting->modeLabel()}).")
            ->line($this->meeting->agenda ? "Agenda: \"{$this->meeting->agenda}\"" : 'Please confirm or decline it.')
            ->action('Open meetings', route('meetings.index'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $couple = $this->meeting->client->coupleNames() ?? $this->meeting->client->name;

        return [
            'title' => "Meeting request from {$couple}",
            'body' => "{$this->meeting->when()} · {$this->meeting->modeLabel()}".($this->meeting->agenda ? " · \"{$this->meeting->agenda}\"" : ''),
            'url' => route('meetings.index'),
        ];
    }
}
