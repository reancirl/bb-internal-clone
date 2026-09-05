<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * SEC-002 — the mobile API's public surface.
 *
 * The web login has always been rate limited inside LoginRequest; the API
 * login had nothing, so the employee email list could be brute forced. Tokens
 * also never expired and outlived a deactivated account.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The limiter is keyed per email+IP and the cache persists within a
        // test run, so start each test from a clean budget.
        RateLimiter::clear('api-login');
    }

    public function test_api_login_issues_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'pixel-8',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role'], 'open_time_card']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'pixel-8',
        ]);
    }

    public function test_api_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // The sixth attempt is refused by the limiter, not the validator —
        // even with the correct password.
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(429);
    }

    public function test_throttle_is_keyed_per_email_so_one_account_cannot_lock_out_another(): void
    {
        $victim = User::factory()->create(['password' => 'password']);
        $other = User::factory()->create(['password' => 'password']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $victim->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => $other->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_issued_tokens_expire(): void
    {
        $minutes = config('sanctum.expiration');
        $this->assertIsInt($minutes, 'Sanctum tokens must expire (SEC-002).');

        $user = User::factory()->create(['password' => 'password']);

        // Use the plain-text token from the login response: it is only exposed
        // at creation, never on a token read back from the database.
        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Rejected once the token is older than the window. Sanctum compares
        // created_at against it on every request.
        //
        // Only one authenticated request is made here on purpose: within a
        // single test process the Sanctum guard is resolved once and cached in
        // the container, so a call made before travelling would keep the token
        // alive afterwards. Each real HTTP request boots a fresh container, so
        // that caching does not exist in production. The happy path inside the
        // window is covered by test_api_login_issues_a_token_for_valid_credentials
        // and every other API test.
        $this->travel($minutes + 1)->minutes();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_fresh_token_authenticates_inside_the_expiry_window(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_deactivating_an_employee_revokes_their_api_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create();
        $crew->createToken('phone', ['mobile']);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $crew->id]);

        $this->actingAs($admin)->delete('/admin/employees/'.$crew->id)->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $crew->id]);
        $this->assertSoftDeleted('users', ['id' => $crew->id]);
    }

    public function test_lead_intake_is_throttled(): void
    {
        $service = User::factory()->create();
        $token = $service->createToken('website', ['leads:create'])->plainTextToken;

        $payload = [
            'firstName' => 'Dana',
            'lastName' => 'Peterson',
            'email' => 'dana@example.com',
            'phone' => '307-555-0111',
            'createdAt' => now()->toIso8601String(),
        ];

        // 20 per minute is the budget; the 21st is refused.
        foreach (range(1, 20) as $attempt) {
            $this->withToken($token)->postJson('/api/leads', $payload)->assertCreated();
        }

        $this->withToken($token)->postJson('/api/leads', $payload)->assertStatus(429);
    }
}
