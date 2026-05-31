<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_view_vendors(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        Vendor::factory()->create(['name' => 'Home Depot']);

        $this->actingAs($crew)
            ->get('/vendors')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vendors.data', 1)
                ->where('isAdmin', false));
    }

    public function test_filtering_by_type(): void
    {
        $admin = User::factory()->admin()->create();
        Vendor::factory()->create(['name' => 'Home Depot', 'type' => Vendor::TYPE_STORE]);
        Vendor::factory()->create(['name' => 'Croell', 'type' => Vendor::TYPE_SUPPLIER]);

        $this->actingAs($admin)
            ->get('/vendors?type=supplier')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vendors.data', 1)
                ->where('vendors.data.0.name', 'Croell'));
    }

    public function test_results_default_to_ten_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        Vendor::factory()->count(25)->create();

        $this->actingAs($admin)
            ->get('/vendors')
            ->assertInertia(fn ($page) => $page
                ->where('vendors.per_page', 10)
                ->where('vendors.total', 25)
                ->has('vendors.data', 10)
                ->where('filters.per_page', 10));
    }

    public function test_per_page_option_is_respected(): void
    {
        $admin = User::factory()->admin()->create();
        Vendor::factory()->count(25)->create();

        $this->actingAs($admin)
            ->get('/vendors?per_page=20')
            ->assertInertia(fn ($page) => $page->where('vendors.per_page', 20)->has('vendors.data', 20));
    }

    public function test_invalid_per_page_falls_back_to_ten(): void
    {
        $admin = User::factory()->admin()->create();
        Vendor::factory()->count(15)->create();

        $this->actingAs($admin)
            ->get('/vendors?per_page=999')
            ->assertInertia(fn ($page) => $page->where('vendors.per_page', 10)->has('vendors.data', 10));
    }

    public function test_admin_can_create_vendor(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/vendors', [
            'name' => 'Bloedorn Lumber',
            'type' => 'store',
            'location' => 'Sheridan',
        ])->assertRedirect();

        $this->assertDatabaseHas('vendors', ['name' => 'Bloedorn Lumber', 'type' => 'store']);
    }

    public function test_creating_vendor_with_invalid_type_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/vendors', ['name' => 'X', 'type' => 'bogus'])
            ->assertSessionHasErrors('type');
    }

    public function test_crew_cannot_create_vendor(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)
            ->post('/vendors', ['name' => 'Sneaky Store', 'type' => 'store'])
            ->assertForbidden();

        $this->assertDatabaseMissing('vendors', ['name' => 'Sneaky Store']);
    }
}
