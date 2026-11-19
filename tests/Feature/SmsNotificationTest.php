<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\ProjectJob;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\JobAssigned;
use App\Notifications\LeadReceived;
use App\Support\Sms\LogSmsSender;
use App\Support\Sms\RingCentralSmsSender;
use App\Support\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{to: string, message: string}> */
    private array $sent = [];

    /**
     * Swap the real sender for one that records what would have been texted.
     */
    private function recordTexts(): void
    {
        $this->app->bind(SmsSender::class, fn () => new class($this->sent) implements SmsSender
        {
            /** @param list<array{to: string, message: string}> $sent */
            public function __construct(private array &$sent) {}

            public function send(string $to, string $message): void
            {
                $this->sent[] = ['to' => $to, 'message' => $message];
            }
        });
    }

    private function postLead(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CREW]), ['leads:create']);

        $this->postJson('/api/leads', [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '307-555-0100',
            'buildLocation' => 'Sheridan',
            'createdAt' => now()->toIso8601String(),
        ])->assertCreated();
    }

    public function test_new_lead_texts_opted_in_admins_and_respects_each_channel(): void
    {
        Notification::fake();

        $emailOnly = User::factory()->admin()->create();
        $both = User::factory()->admin()->create(['phone' => '+639171234567', 'sms_notifications' => true]);
        $smsOnly = User::factory()->admin()->create([
            'email_notifications' => false, 'phone' => '+639181234567', 'sms_notifications' => true,
        ]);
        $smsWithoutPhone = User::factory()->admin()->create(['email_notifications' => false, 'sms_notifications' => true]);
        $crewWithSms = User::factory()->create([
            'role' => User::ROLE_CREW, 'phone' => '+639191234567', 'sms_notifications' => true,
        ]);

        $this->postLead();

        Notification::assertSentTo($emailOnly, LeadReceived::class, fn ($n, $c) => $c === ['mail']);
        Notification::assertSentTo($both, LeadReceived::class, fn ($n, $c) => $c === ['mail', SmsChannel::class]);
        Notification::assertSentTo($smsOnly, LeadReceived::class, fn ($n, $c) => $c === [SmsChannel::class]);
        Notification::assertNotSentTo($smsWithoutPhone, LeadReceived::class);
        Notification::assertNotSentTo($crewWithSms, LeadReceived::class);
    }

    public function test_lead_text_reaches_the_sender_with_name_phone_and_link(): void
    {
        $this->recordTexts();
        User::factory()->admin()->create([
            'email_notifications' => false, 'phone' => '+639171234567', 'sms_notifications' => true,
        ]);

        $this->postLead();

        $this->assertCount(1, $this->sent);
        $this->assertSame('+639171234567', $this->sent[0]['to']);
        $lead = Lead::sole();
        $this->assertSame(
            'New lead: Jane Doe, Sheridan. 307-555-0100 '.url('/admin/leads/'.$lead->id),
            $this->sent[0]['message'],
        );
    }

    public function test_sms_preference_does_not_resurrect_opted_out_email(): void
    {
        // An admin who turned email off but texts on is now a recipient of
        // Notify::admins(); email-only notifications must still skip them.
        $user = User::factory()->admin()->create([
            'email_notifications' => false, 'phone' => '+639171234567', 'sms_notifications' => true,
        ]);

        $this->assertSame([], (new JobAssigned(ProjectJob::factory()->create()))->via($user));
    }

    public function test_log_driver_is_the_default(): void
    {
        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));
    }

    public function test_ringcentral_driver_exchanges_a_jwt_and_posts_the_text(): void
    {
        config([
            'services.sms.driver' => 'ringcentral',
            'services.ringcentral.client_id' => 'client-id',
            'services.ringcentral.client_secret' => 'client-secret',
            'services.ringcentral.jwt' => 'jwt-1',
            'services.ringcentral.from' => '+13075550199',
            'services.ringcentral.server_url' => 'https://platform.ringcentral.com',
        ]);
        Http::fake([
            'https://platform.ringcentral.com/restapi/oauth/token' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 7199,
                'token_type' => 'bearer',
            ]),
            'https://platform.ringcentral.com/restapi/v1.0/*' => Http::response(['id' => 'msg-1']),
        ]);

        $sender = app(SmsSender::class);
        $this->assertInstanceOf(RingCentralSmsSender::class, $sender);
        $sender->send('+639171234567', 'hello');
        $sender->send('+639171234567', 'again');

        $tokenCalls = 0;
        Http::assertSent(function ($request) use (&$tokenCalls) {
            if ($request->url() !== 'https://platform.ringcentral.com/restapi/oauth/token') {
                return false;
            }

            $tokenCalls++;

            return $request->method() === 'POST'
                && $request->header('Authorization') === ['Basic '.base64_encode('client-id:client-secret')]
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $request['assertion'] === 'jwt-1';
        });
        $this->assertSame(1, $tokenCalls, 'The access token is reused until it expires.');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/sms'
            && $request->header('Authorization') === ['Bearer token-1']
            && $request['from'] === ['phoneNumber' => '+13075550199']
            && $request['to'] === [['phoneNumber' => '+639171234567']]
            && $request['text'] === 'hello');

        Http::assertSentCount(3);
    }

    public function test_ringcentral_errors_and_missing_config_are_raised_not_swallowed(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        try {
            (new RingCentralSmsSender('bad-id', 'bad-secret', 'bad-jwt', '+13075550199', 'https://platform.ringcentral.com'))
                ->send('+639171234567', 'x');
            $this->fail('A rejected request must throw so the queued job is retried and logged.');
        } catch (RequestException $e) {
            $this->assertSame(401, $e->response->status());
        }

        $this->expectException(RuntimeException::class);
        (new RingCentralSmsSender(null, null, null, null, 'https://platform.ringcentral.com'))
            ->send('+639171234567', 'x');
    }

    public function test_ringcentral_rejects_a_token_response_without_an_access_token(): void
    {
        Http::fake([
            'https://platform.ringcentral.com/restapi/oauth/token' => Http::response(['token_type' => 'bearer'], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('access token');

        (new RingCentralSmsSender('other-id', 'secret', 'jwt', '+13075550199', 'https://platform.ringcentral.com'))
            ->send('+639171234567', 'x');
    }

    public function test_unknown_driver_fails_loudly(): void
    {
        config(['services.sms.driver' => 'carrier-pigeon']);

        $this->expectException(InvalidArgumentException::class);
        app(SmsSender::class);
    }

    public function test_profile_saves_normalised_phone_and_sms_preference(): void
    {
        $user = User::factory()->admin()->create();
        $this->assertFalse($user->fresh()->sms_notifications, 'Texts default to off');

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+63 (917) 123-4567',
            'sms_notifications' => true,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('+639171234567', $user->phone);
        $this->assertTrue($user->sms_notifications);
    }

    public function test_profile_rejects_bad_phone_and_requires_one_for_texts(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name, 'email' => $user->email, 'phone' => '09171234567',
        ])->assertSessionHasErrors('phone');

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name, 'email' => $user->email, 'phone' => '', 'sms_notifications' => true,
        ])->assertSessionHasErrors('phone');

        $this->assertNull($user->fresh()->phone);
        $this->assertFalse($user->fresh()->sms_notifications);
    }

    public function test_send_test_text_uses_the_saved_number(): void
    {
        $this->recordTexts();
        $user = User::factory()->admin()->create(['phone' => '+639171234567']);

        $this->actingAs($user)->from('/settings/profile')->post('/settings/profile/test-sms')
            ->assertRedirect('/settings/profile')
            ->assertSessionHas('success', 'Test text sent to +639171234567.');

        $this->assertCount(1, $this->sent);
        $this->assertSame('+639171234567', $this->sent[0]['to']);
    }

    public function test_send_test_text_without_a_number_or_on_provider_error_flashes_an_error(): void
    {
        $this->recordTexts();
        $noPhone = User::factory()->admin()->create();

        $this->actingAs($noPhone)->post('/settings/profile/test-sms')
            ->assertSessionHas('error', 'Save a mobile number first.');
        $this->assertSame([], $this->sent);

        // Provider failure: RingCentral selected but not configured.
        $this->app->bind(SmsSender::class, fn () => new RingCentralSmsSender(null, null, null, null, 'https://platform.ringcentral.com'));

        $withPhone = User::factory()->admin()->create(['phone' => '+639171234567']);
        $this->actingAs($withPhone)->post('/settings/profile/test-sms')
            ->assertSessionHas('error', fn (string $msg) => str_contains($msg, 'RINGCENTRAL_CLIENT_ID'));
    }

    public function test_send_test_text_is_throttled_and_requires_login(): void
    {
        $this->post('/settings/profile/test-sms')->assertRedirect('/login');

        $this->recordTexts();
        $user = User::factory()->admin()->create(['phone' => '+639171234567']);

        foreach (range(1, 3) as $_) {
            $this->actingAs($user)->post('/settings/profile/test-sms')->assertRedirect();
        }
        $this->actingAs($user)->post('/settings/profile/test-sms')->assertStatus(429);
        $this->assertCount(3, $this->sent);
    }
}
