<?php

namespace App\Notifications;

use App\Models\ProjectJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProjectJob $job) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->job->title ?: 'a job';

        return (new MailMessage)
            ->subject("You're on: {$title}")
            ->line("You've been assigned to {$title} on ".($this->job->project?->name ?? 'a project').'.')
            ->line('Starts '.$this->job->scheduled_date?->format('D, M j').
                ($this->job->duration_days > 1 ? " · {$this->job->duration_days} days" : ''))
            ->lineIf((bool) $this->job->notes, 'Notes: '.$this->job->notes)
            ->action('View your jobs', url('/jobs'));
    }
}
