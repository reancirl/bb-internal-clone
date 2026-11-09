<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * textbee.dev: an Android phone running the textbee app sends the text from
 * its own SIM. Free apart from the SIM's load, which suits local testing.
 * The phone must be on and online for messages to go out.
 */
class TextbeeSmsSender implements SmsSender
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $deviceId,
        private readonly string $baseUrl,
    ) {}

    public function send(string $to, string $message): void
    {
        if (blank($this->apiKey) || blank($this->deviceId)) {
            throw new RuntimeException('textbee is not configured: set TEXTBEE_API_KEY and TEXTBEE_DEVICE_ID.');
        }

        Http::acceptJson()
            ->timeout(15)
            ->withHeaders(['x-api-key' => $this->apiKey])
            ->post(rtrim($this->baseUrl, '/').'/gateway/devices/'.rawurlencode($this->deviceId).'/send-sms', [
                'recipients' => [$to],
                'message' => $message,
            ])
            ->throw();
    }
}
