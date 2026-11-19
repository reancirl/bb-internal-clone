<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default driver: writes the text to the application log instead of sending
 * it, so a dev server or test run never texts a real phone by accident.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('[sms] '.$to.': '.$message);
    }
}
