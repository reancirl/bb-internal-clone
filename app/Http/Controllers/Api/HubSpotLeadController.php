<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Notifications\LeadReceived;
use App\Support\Notify;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubSpotLeadController extends Controller
{
    /**
     * HubSpot posts one event per new contact. The event carries only the
     * contact id, so the contact is read back from HubSpot. The id is the
     * idempotency key: a retry does not create a second lead or email.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $events = json_decode($request->getContent(), true);

        if (! is_array($events)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        if (! array_is_list($events)) {
            $events = [$events];
        }

        foreach ($events as $event) {
            if (! is_array($event) || ! $this->isContactCreation($event)) {
                continue;
            }

            $hubspotId = (string) ($event['objectId'] ?? '');

            if ($hubspotId === '' || $this->findExisting($hubspotId) !== null) {
                continue;
            }

            $contact = $this->fetchContact($hubspotId);

            if ($contact instanceof JsonResponse) {
                return $contact;
            }

            if ($contact === null) {
                continue;
            }

            $this->createLead($hubspotId, $contact);
        }

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function createLead(string $hubspotId, array $contact): void
    {
        $properties = is_array($contact['properties'] ?? null) ? $contact['properties'] : [];
        $email = trim((string) ($properties['email'] ?? ''));

        if ($email === '') {
            Log::info('HubSpot contact skipped because it has no email.', ['hubspot_id' => $hubspotId]);

            return;
        }

        $phone = trim((string) ($properties['phone'] ?? ''));

        if ($phone === '') {
            $phone = trim((string) ($properties['mobilephone'] ?? ''));
        }

        try {
            $lead = Lead::create([
                'first_name' => trim((string) ($properties['firstname'] ?? '')) ?: 'Unknown',
                'last_name' => trim((string) ($properties['lastname'] ?? '')),
                'email' => $email,
                'phone' => $phone !== '' ? $phone : '—',
                'build_location' => trim((string) ($properties['city'] ?? '')) ?: null,
                'source' => $this->source($properties),
                'submitted_at' => now(),
                'external_source' => 'hubspot',
                'external_id' => $hubspotId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        Notify::admins(new LeadReceived($lead));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function source(array $properties): string
    {
        $channel = strtoupper((string) ($properties['hs_analytics_source'] ?? ''));

        if ($channel === '') {
            $channel = strtoupper((string) ($properties['hs_latest_source'] ?? ''));
        }

        $detail = strtolower(trim(
            (string) ($properties['hs_analytics_source_data_1'] ?? '').' '
            .(string) ($properties['hs_latest_source_data_1'] ?? '')
        ));

        if ($channel === 'PAID_SEARCH' || str_contains($detail, 'google')) {
            return 'google_ads';
        }

        if ($channel === 'PAID_SOCIAL' || str_contains($detail, 'facebook') || str_contains($detail, 'instagram') || str_contains($detail, 'meta')) {
            return 'meta_ads';
        }

        return 'hubspot';
    }

    /**
     * @return array<string, mixed>|JsonResponse|null
     */
    private function fetchContact(string $hubspotId): array|JsonResponse|null
    {
        $token = config('services.hubspot.access_token');

        if (! is_string($token) || $token === '') {
            Log::error('HubSpot access token is not configured.');

            return response()->json(['message' => 'HubSpot is not configured'], 500);
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(10)
            ->get("https://api.hubapi.com/crm/v3/objects/contacts/{$hubspotId}", [
                'properties' => implode(',', [
                    'firstname', 'lastname', 'email', 'phone', 'mobilephone', 'city',
                    'hs_analytics_source', 'hs_analytics_source_data_1',
                    'hs_latest_source', 'hs_latest_source_data_1',
                ]),
            ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            Log::warning('HubSpot contact read failed.', [
                'hubspot_id' => $hubspotId,
                'status' => $response->status(),
            ]);

            return response()->json(['message' => 'HubSpot contact could not be read'], 502);
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function isContactCreation(array $event): bool
    {
        $type = $event['subscriptionType'] ?? '';

        if ($type === 'contact.creation') {
            return true;
        }

        if ($type !== 'object.creation') {
            return false;
        }

        $objectType = $event['objectType'] ?? null;
        $objectTypeId = $event['objectTypeId'] ?? null;

        if ($objectType === null && $objectTypeId === null) {
            return true;
        }

        return $objectType === 'contact' || $objectTypeId === '0-1';
    }

    private function findExisting(string $hubspotId): ?Lead
    {
        return Lead::query()
            ->where('external_source', 'hubspot')
            ->where('external_id', $hubspotId)
            ->first();
    }

    private function signatureIsValid(Request $request): bool
    {
        $secret = config('services.hubspot.client_secret');
        $signature = $request->header('X-HubSpot-Signature-v3');
        $timestamp = $request->header('X-HubSpot-Request-Timestamp');

        if (! is_string($secret) || $secret === '' || ! is_string($signature) || ! is_string($timestamp)) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->getTimestampMs() - (int) $timestamp) > 300_000) {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $request->getMethod().$request->fullUrl().$request->getContent().$timestamp,
            $secret,
            true,
        ));

        return hash_equals($expected, $signature);
    }
}
