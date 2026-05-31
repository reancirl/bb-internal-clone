<?php

namespace Tests\Feature;

use App\Models\TradePartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradePartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_view_the_directory(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        TradePartner::factory()->create(['name' => 'Total Concrete Works']);

        $this->actingAs($crew)
            ->get('/trade-partners')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('partners.data', 1)
                ->where('isAdmin', false));
    }

    public function test_filtering_by_trade_returns_matching_partners(): void
    {
        $admin = User::factory()->admin()->create();

        $electrician = TradePartner::factory()->create(['name' => 'Mountain Valley Electric']);
        $electrician->trades()->create(['trade' => 'Electrician']);

        $plumber = TradePartner::factory()->create(['name' => 'Cowboy State Plumbing']);
        $plumber->trades()->create(['trade' => 'Plumber']);

        $this->actingAs($admin)
            ->get('/trade-partners?trade=Electrician')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('partners.data', 1)
                ->where('partners.data.0.name', 'Mountain Valley Electric'));
    }

    public function test_admin_can_create_partner_with_trades(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/trade-partners', [
            'name' => 'TS Mechanical',
            'location' => 'Sheridan',
            'phone' => '(307) 655-5773',
            'used_before' => true,
            'trades' => ['HVAC', 'Plumber'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $partner = TradePartner::firstWhere('name', 'TS Mechanical');
        $this->assertNotNull($partner);
        $this->assertTrue($partner->used_before);
        $this->assertEqualsCanonicalizing(['HVAC', 'Plumber'], $partner->trades->pluck('trade')->all());
    }

    public function test_creating_partner_with_invalid_trade_fails_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/trade-partners', ['name' => 'Bad Trade Co', 'trades' => ['Not A Trade']])
            ->assertSessionHasErrors('trades.0');
    }

    public function test_updating_partner_replaces_its_trades(): void
    {
        $admin = User::factory()->admin()->create();
        $partner = TradePartner::factory()->create();
        $partner->trades()->create(['trade' => 'Concrete']);

        $this->actingAs($admin)->put('/trade-partners/'.$partner->id, [
            'name' => $partner->name,
            'trades' => ['Excavation'],
        ])->assertRedirect();

        $partner->refresh();
        $this->assertEquals(['Excavation'], $partner->trades->pluck('trade')->all());
    }

    public function test_admin_can_delete_partner_and_trades_cascade(): void
    {
        $admin = User::factory()->admin()->create();
        $partner = TradePartner::factory()->create();
        $partner->trades()->create(['trade' => 'Roofing']);

        $this->actingAs($admin)->delete('/trade-partners/'.$partner->id)->assertRedirect();

        $this->assertDatabaseMissing('trade_partners', ['id' => $partner->id]);
        $this->assertDatabaseMissing('trade_partner_trades', ['trade_partner_id' => $partner->id]);
    }

    public function test_crew_cannot_create_partner(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)
            ->post('/trade-partners', ['name' => 'Sneaky Sub'])
            ->assertForbidden();

        $this->assertDatabaseMissing('trade_partners', ['name' => 'Sneaky Sub']);
    }
}
