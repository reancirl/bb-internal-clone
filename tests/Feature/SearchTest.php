<?php

namespace Tests\Feature;

use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\TradePartner;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-003 — directory and price-book search must ignore case on every driver.
 *
 * `like` is case-sensitive on PostgreSQL (production) but not on SQLite, so
 * these assertions only fail on the engine that actually had the bug. TEST-002
 * runs the suite against Postgres in CI so that stays true.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_trade_partner_search_ignores_case(): void
    {
        TradePartner::factory()->create(['name' => 'Bloedorn Lumber', 'notes' => 'Reliable framing crew']);
        TradePartner::factory()->create(['name' => 'Sheridan Concrete']);

        foreach (['bloedorn', 'BLOEDORN', 'BloEdorn'] as $term) {
            $this->actingAs($this->admin())
                ->get('/trade-partners?search='.$term)
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('partners.data', 1)
                    ->where('partners.data.0.name', 'Bloedorn Lumber'));
        }
    }

    public function test_trade_partner_search_covers_notes_case_insensitively(): void
    {
        TradePartner::factory()->create(['name' => 'Sheridan Concrete', 'notes' => 'Pours FOUNDATIONS fast']);

        $this->actingAs($this->admin())
            ->get('/trade-partners?search=foundations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('partners.data', 1));
    }

    public function test_vendor_search_ignores_case_across_name_and_location(): void
    {
        Vendor::factory()->create(['name' => 'Bloedorn Lumber', 'location' => 'Sheridan, WY']);
        Vendor::factory()->create(['name' => 'Powder River Supply', 'location' => 'Gillette, WY']);

        $this->actingAs($this->admin())
            ->get('/vendors?search=BLOEDORN')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('vendors.data', 1));

        $this->actingAs($this->admin())
            ->get('/vendors?search=gillette')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vendors.data', 1)
                ->where('vendors.data.0.name', 'Powder River Supply'));
    }

    public function test_price_book_search_ignores_case(): void
    {
        $category = PriceCategory::factory()->create();
        PriceItem::factory()->for($category, 'category')->create(['name' => 'Spray Foam Insulation']);
        PriceItem::factory()->for($category, 'category')->create(['name' => 'Drywall Material']);

        $this->actingAs($this->admin())
            ->get('/price-book?search=SPRAY')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Spray Foam Insulation'));
    }

    public function test_search_still_excludes_non_matches(): void
    {
        Vendor::factory()->create(['name' => 'Bloedorn Lumber', 'location' => 'Sheridan, WY']);

        // A case-insensitive search must not become a match-everything search.
        $this->actingAs($this->admin())
            ->get('/vendors?search=zzz-no-such-vendor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('vendors.data', 0));
    }

    public function test_api_search_ignores_case_too(): void
    {
        $admin = $this->admin();
        Vendor::factory()->create(['name' => 'Bloedorn Lumber']);
        $token = $admin->createToken('phone', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/vendors?search=BLOEDORN')
            ->assertOk()
            ->assertJsonCount(1, 'vendors.data');
    }
}
