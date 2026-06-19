<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_cannot_access_employee_management(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)->get('/admin/employees')->assertForbidden();
    }

    public function test_admin_can_list_employees(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($admin)->get('/admin/employees')->assertOk()
            ->assertInertia(fn ($page) => $page->component('employees/index')->has('employees', 2));
    }

    public function test_admin_can_add_an_employee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/employees', [
            'name' => 'New Crew',
            'email' => 'newcrew@buffalobuilt.test',
            'role' => User::ROLE_CREW,
            'password' => 'password123',
        ])->assertRedirect();

        $user = User::firstWhere('email', 'newcrew@buffalobuilt.test');
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_CREW, $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@buffalobuilt.test']);

        $this->actingAs($admin)->post('/admin/employees', [
            'name' => 'Dupe',
            'email' => 'taken@buffalobuilt.test',
            'role' => User::ROLE_CREW,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_role(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create(['role' => User::ROLE_CREW, 'name' => 'Promote Me']);

        $this->actingAs($admin)->put('/admin/employees/'.$crew->id, [
            'name' => 'Promote Me',
            'email' => $crew->email,
            'role' => User::ROLE_ADMIN,
        ])->assertRedirect();

        $this->assertSame(User::ROLE_ADMIN, $crew->fresh()->role);
    }

    public function test_admin_can_reset_a_password(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($admin)->patch('/admin/employees/'.$crew->id.'/password', [
            'password' => 'brand-new-pass',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brand-new-pass', $crew->fresh()->password));
    }

    public function test_admin_can_deactivate_and_reactivate_an_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($admin)->delete('/admin/employees/'.$crew->id)->assertRedirect();
        $this->assertSoftDeleted($crew);

        $this->actingAs($admin)->patch('/admin/employees/'.$crew->id.'/restore')->assertRedirect();
        $this->assertNotSoftDeleted($crew);
    }

    public function test_deactivated_user_cannot_authenticate(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW, 'password' => 'secret-pass']);
        $crew->delete();

        $this->post('/login', ['email' => $crew->email, 'password' => 'secret-pass'])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // keep another admin so "last admin" rule isn't the blocker

        $this->actingAs($admin)->delete('/admin/employees/'.$admin->id)->assertRedirect();
        $this->assertNotSoftDeleted($admin);
    }

    public function test_cannot_demote_the_last_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/employees/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_CREW,
        ])->assertRedirect();

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }
}
