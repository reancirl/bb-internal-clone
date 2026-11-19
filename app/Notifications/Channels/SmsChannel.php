<?php

namespace App\Notifications\Channels;

use App\Support\Sms\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel for text messages. A notification opts in by
 * listing this class in via() and defining toSms(); the recipient's number
 * comes from routeNotificationForSms().
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (blank($to) || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sender->send($to, $notification->toSms($notifiable));
    }
}
