<?php

namespace App\Notifications;

use App\Models\ChangeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChangeOrderDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ChangeOrder $changeOrder) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verb = $this->changeOrder->status === ChangeOrder::STATUS_APPROVED ? 'approved' : 'declined';
        $price = $this->changeOrder->price_cents !== null
            ? '$'.number_format($this->changeOrder->price_cents / 100, 2)
            : null;

        return (new MailMessage)
            ->subject($this->changeOrder->label()." {$verb}: ".$this->changeOrder->title)
            ->line($this->changeOrder->label()." on {$this->changeOrder->project?->name} was {$verb}.")
            ->lineIf($price !== null, "Amount: {$price}")
            ->lineIf((bool) $this->changeOrder->decision_comment, 'Comment: '.$this->changeOrder->decision_comment)
            ->action('View the budget', url('/projects/'.$this->changeOrder->project_id.'/budget'));
    }
}
