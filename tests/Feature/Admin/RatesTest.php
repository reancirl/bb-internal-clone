<?php

namespace Tests\Feature\Admin;

use App\Models\EquipmentRate;
use App\Models\LaborRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_cannot_view_rates(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)->get('/admin/rates')->assertForbidden();
    }

    public function test_admin_can_view_rates(): void
    {
        $admin = User::factory()->admin()->create();
        LaborRate::factory()->create();
        EquipmentRate::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/rates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('laborRates', 1)
                ->has('equipmentRates', 1));
    }

    public function test_admin_can_manage_labor_rates(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/labor-rates', [
            'class_name' => 'Dirt Crew',
            'base_rate' => 24.50,
            'total' => 62.71,
        ])->assertRedirect();
        $this->assertDatabaseHas('labor_rates', ['class_name' => 'Dirt Crew']);

        $rate = LaborRate::firstWhere('class_name', 'Dirt Crew');
        $this->actingAs($admin)->put('/admin/labor-rates/'.$rate->id, [
            'class_name' => 'Dirt Crew',
            'base_rate' => 25.00,
        ])->assertRedirect();
        $this->assertEquals('25.00', $rate->fresh()->base_rate);

        $this->actingAs($admin)->delete('/admin/labor-rates/'.$rate->id)->assertRedirect();
        $this->assertDatabaseMissing('labor_rates', ['id' => $rate->id]);
    }

    public function test_admin_can_manage_equipment_rates(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/equipment-rates', [
            'name' => 'Skidsteer',
            'day_rate' => 303,
            'week_rate' => 1210,
        ])->assertRedirect();

        $this->assertDatabaseHas('equipment_rates', ['name' => 'Skidsteer']);
    }

    public function test_crew_cannot_create_labor_rate(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)
            ->post('/admin/labor-rates', ['class_name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertDatabaseMissing('labor_rates', ['class_name' => 'Sneaky']);
    }
}
