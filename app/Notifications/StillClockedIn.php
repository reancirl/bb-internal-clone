<?php

namespace App\Notifications;

use App\Models\TimeCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StillClockedIn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TimeCard $timeCard) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $since = $this->timeCard->clock_in_at;
        $hours = (int) $since->diffInHours(now());

        return (new MailMessage)
            ->subject('Still clocked in since '.$since->format('g:i A'))
            ->line("Your shift has been running for about {$hours} hours (clocked in ".$since->format('D, M j g:i A').').')
            ->line('If you forgot to clock out, do it now so your hours stay accurate — the office can correct the time if needed.')
            ->action('Open your time card', url('/time-card'));
    }
}
