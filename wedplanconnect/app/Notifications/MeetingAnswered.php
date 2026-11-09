<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the couple that their planner confirmed or declined their meeting.
 */
class MeetingAnswered extends Notification
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
        $mail = (new MailMessage)
            ->subject("{$this->meeting->planner->name} {$this->meeting->status} your meeting")
            ->line("{$this->meeting->planner->name} {$this->meeting->status} your meeting on {$this->meeting->when()}.");

        if ($this->meeting->response_note) {
            $mail->line("Their note: \"{$this->meeting->response_note}\"");
        }

        return $mail->action('Open my meetings', route('client.meetings'));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->meeting->planner->name} {$this->meeting->status} your meeting",
            'body' => $this->meeting->when().($this->meeting->response_note ? " · \"{$this->meeting->response_note}\"" : ''),
            'url' => route('client.meetings'),
        ];
    }
}
