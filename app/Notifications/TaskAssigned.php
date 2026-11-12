<?php

namespace App\Notifications;

use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProjectTask $task) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->task;
        $where = array_filter([$task->project?->name, $task->location]);

        return (new MailMessage)
            ->subject('Task for you: '.$task->title)
            ->line("You've been assigned task #{$task->number}: {$task->title}".($where !== [] ? ' — '.implode(', ', $where) : '').'.')
            ->lineIf($task->due_date !== null, 'Due '.$task->due_date?->format('D, M j'))
            ->lineIf($task->is_punch, 'This is a punch-list item — the customer walkthrough is waiting on it.')
            ->lineIf((bool) $task->description, 'Details: '.$task->description)
            ->action('View tasks', url('/projects/'.$task->project_id.'/tasks'));
    }
}
