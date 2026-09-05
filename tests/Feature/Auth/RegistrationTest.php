<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public registration is deliberately absent (SEC-001). Accounts are created
 * by an admin at /admin/employees; the first admin comes from the seeder.
 * These tests fail if the starter-kit routes are ever restored.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_visitors_cannot_register(): void
    {
        $this->post('/register', [
            'name' => 'Walk In',
            'email' => 'walkin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'walkin@example.com']);
    }

    public function test_admins_create_accounts_instead(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/employees', [
            'name' => 'New Crew',
            'email' => 'crew@example.com',
            'role' => User::ROLE_CREW,
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'crew@example.com', 'role' => User::ROLE_CREW]);
    }
}
