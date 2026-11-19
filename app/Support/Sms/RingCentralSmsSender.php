<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * RingCentral SMS. A JWT credential is exchanged for an access token, then
 * the text is sent from a number on that same extension. The token is cached
 * until shortly before it expires so a burst of leads does not re-authenticate
 * on every message.
 */
class RingCentralSmsSender implements SmsSender
{
    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $jwt,
        private readonly ?string $from,
        private readonly string $serverUrl,
    ) {}

    public function send(string $to, string $message): void
    {
        if (blank($this->clientId) || blank($this->clientSecret) || blank($this->jwt) || blank($this->from)) {
            throw new RuntimeException('RingCentral is not configured: set RINGCENTRAL_CLIENT_ID, RINGCENTRAL_CLIENT_SECRET, RINGCENTRAL_JWT, and RINGCENTRAL_FROM.');
        }

        Http::acceptJson()
            ->timeout(15)
            ->withToken($this->accessToken())
            ->post($this->endpoint('/restapi/v1.0/account/~/extension/~/sms'), [
                'from' => ['phoneNumber' => $this->from],
                'to' => [['phoneNumber' => $to]],
                'text' => $message,
            ])
            ->throw();
    }

    private function accessToken(): string
    {
        $key = 'ringcentral.sms.token.'.hash('sha256', $this->clientId.'|'.$this->jwt);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->endpoint('/restapi/oauth/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt,
            ])
            ->throw();

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('RingCentral did not return an access token.');
        }

        $expiresIn = (int) $response->json('expires_in');
        Cache::put($key, $token, $expiresIn > 120 ? $expiresIn - 60 : max(1, $expiresIn));

        return $token;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->serverUrl, '/').$path;
    }
}
