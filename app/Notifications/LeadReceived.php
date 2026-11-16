<?php

namespace App\Notifications;

use App\Models\Lead;
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($this->lead->first_name.' '.$this->lead->last_name);

        return (new MailMessage)
            ->subject('New lead: '.$name.($this->lead->build_location ? ' — '.$this->lead->build_location : ''))
            ->line("{$name} just submitted an inquiry through {$this->origin()}.")
            ->line('Phone: '.$this->lead->phone.' · Email: '.$this->lead->email)
            ->lineIf((bool) $this->lead->project_details, 'Details: '.str($this->lead->project_details)->limit(200))
            ->action('View in the pipeline', url('/admin/leads/'.$this->lead->id));
    }

    private function origin(): string
    {
        return match ($this->lead->source) {
            'google_ads' => 'Google Ads',
            'meta_ads' => 'Meta Ads',
            'hubspot' => 'HubSpot',
            'website' => 'the website',
            default => str_replace('_', ' ', (string) $this->lead->source),
        };
    }
}
