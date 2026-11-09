<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return Notify::channels($notifiable, ['mail', 'sms']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($this->lead->first_name.' '.$this->lead->last_name);

        return (new MailMessage)
            ->subject('New lead: '.$name.($this->lead->build_location ? ' — '.$this->lead->build_location : ''))
            ->line("{$name} just submitted an inquiry through the website.")
            ->line('Phone: '.$this->lead->phone.' · Email: '.$this->lead->email)
            ->lineIf((bool) $this->lead->project_details, 'Details: '.str($this->lead->project_details)->limit(200))
            ->action('View in the pipeline', url('/admin/leads/'.$this->lead->id));
    }

    /**
     * One short text: who, where, how to reach them, and a link. Kept near a
     * single 160-character segment so it is cheap and readable on a lock screen.
     */
    public function toSms(object $notifiable): string
    {
        $name = trim($this->lead->first_name.' '.$this->lead->last_name);
        $where = $this->lead->build_location ? ', '.str($this->lead->build_location)->limit(40) : '';

        return "New lead: {$name}{$where}. {$this->lead->phone} ".url('/admin/leads/'.$this->lead->id);
    }
}
