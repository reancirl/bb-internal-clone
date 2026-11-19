<?php

namespace App\Support\Sms;

/**
 * Sends one text message. The driver is chosen by `services.sms.driver`
 * (SMS_DRIVER) and bound in AppServiceProvider, so callers never know which
 * provider is behind it.
 */
interface SmsSender
{
    /**
     * @param  string  $to  E.164 number, e.g. +639171234567
     *
     * @throws \Throwable when the provider rejects or cannot be reached
     */
    public function send(string $to, string $message): void;
}
