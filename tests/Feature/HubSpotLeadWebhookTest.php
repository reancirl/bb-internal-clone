<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HubSpotLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-hubspot-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hubspot.client_secret' => self::SECRET,
            'services.hubspot.access_token' => 'test-hubspot-token',
        ]);
    }

    public function test_a_google_ads_contact_becomes_a_sales_board_lead(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->fakeContact('99001', [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '307-555-0199',
            'city' => 'Sheridan',
            'hs_analytics_source' => 'PAID_SEARCH',
            'hs_analytics_source_data_1' => 'Google',
        ]);

        $this->postSigned($this->creationEvent('99001'))->assertOk()->assertJson(['success' => true]);

        $lead = Lead::firstWhere('email', 'jane@example.com');
        $this->assertNotNull($lead);
        $this->assertSame('google_ads', $lead->source);
        $this->assertSame(Lead::FUNNEL_GENERAL, $lead->funnel);
        $this->assertSame('new', $lead->status);
        $this->assertSame('hubspot', $lead->external_source);
        $this->assertSame('99001', $lead->external_id);
        $this->assertSame('Sheridan', $lead->build_location);

        Notification::assertSentTo($admin, LeadReceived::class);
        $mail = (new LeadReceived($lead))->toMail($admin);
        $this->assertStringContainsString('Google Ads', implode(' ', $mail->introLines));
    }

    public function test_a_meta_contact_is_stored_as_meta_ads(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $this->fakeContact('99002', [
            'firstname' => 'Ann',
            'lastname' => 'Lee',
            'email' => 'ann@example.com',
            'phone' => '307-555-0100',
            'hs_analytics_source' => 'PAID_SOCIAL',
            'hs_analytics_source_data_1' => 'Facebook',
        ]);

        $this->postSigned($this->creationEvent('99002'))->assertOk();

        $this->assertSame('meta_ads', Lead::firstWhere('external_id', '99002')->source);
    }

    public function test_the_same_contact_is_not_created_twice(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $this->fakeContact('99001', [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '307-555-0199',
            'hs_analytics_source' => 'PAID_SEARCH',
        ]);

        $event = $this->creationEvent('99001');
        $this->postSigned($event)->assertOk();
        $this->postSigned($event)->assertOk();

        $this->assertSame(1, Lead::count());
        Notification::assertSentTimes(LeadReceived::class, 1);
        Http::assertSentCount(1);
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        Notification::fake();
        Http::fake();

        $this->postJson('/api/webhooks/hubspot', [$this->creationEvent('99001')])
            ->assertUnauthorized();

        $this->assertSame(0, Lead::count());
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_a_contact_without_an_email_is_skipped(): void
    {
        Notification::fake();
        $this->fakeContact('99003', [
            'firstname' => 'No',
            'lastname' => 'Email',
            'email' => '',
            'phone' => '307-555-0101',
        ]);

        $this->postSigned($this->creationEvent('99003'))->assertOk();

        $this->assertSame(0, Lead::count());
        Notification::assertNothingSent();
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function fakeContact(string $id, array $properties): void
    {
        Http::fake([
            "api.hubapi.com/crm/v3/objects/contacts/{$id}*" => Http::response([
                'id' => $id,
                'properties' => $properties,
            ]),
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function creationEvent(string $objectId): array
    {
        return [
            'subscriptionType' => 'object.creation',
            'objectTypeId' => '0-1',
            'objectId' => (int) $objectId,
            'occurredAt' => now()->getTimestampMs(),
        ];
    }

    /**
     * @param  array<string, int|string>  $event
     */
    private function postSigned(array $event): TestResponse
    {
        $body = json_encode([$event]);
        $timestamp = (string) now()->getTimestampMs();
        $uri = 'http://localhost/api/webhooks/hubspot';
        $signature = base64_encode(hash_hmac('sha256', 'POST'.$uri.$body.$timestamp, self::SECRET, true));

        return $this->call('POST', '/api/webhooks/hubspot', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUBSPOT_SIGNATURE_V3' => $signature,
            'HTTP_X_HUBSPOT_REQUEST_TIMESTAMP' => $timestamp,
        ], $body);
    }
}
