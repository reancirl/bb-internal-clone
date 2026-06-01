<?php

namespace Tests\Feature;

use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_view_price_book(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        PriceItem::factory()->for(PriceCategory::factory(), 'category')->create();

        $this->actingAs($crew)
            ->get('/price-book')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('isAdmin', false));
    }

    public function test_filtering_by_category(): void
    {
        $admin = User::factory()->admin()->create();
        $concrete = PriceCategory::factory()->create(['name' => 'Concrete']);
        $framing = PriceCategory::factory()->create(['name' => 'Framing']);
        PriceItem::factory()->for($concrete, 'category')->create(['name' => 'Concrete Slab']);
        PriceItem::factory()->for($framing, 'category')->create(['name' => 'Framing Labor']);

        $this->actingAs($admin)
            ->get('/price-book?category='.$concrete->id)
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Concrete Slab'));
    }

    public function test_defaults_to_ten_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        PriceItem::factory()->count(15)->for(PriceCategory::factory(), 'category')->create();

        $this->actingAs($admin)
            ->get('/price-book')
            ->assertInertia(fn ($page) => $page->where('items.per_page', 10)->has('items.data', 10));
    }

    public function test_admin_can_create_item_with_vendor(): void
    {
        $admin = User::factory()->admin()->create();
        $category = PriceCategory::factory()->create();
        $vendor = Vendor::factory()->create();

        $this->actingAs($admin)->post('/price-book', [
            'price_category_id' => $category->id,
            'name' => 'Concrete Material',
            'unit' => 'YD3',
            'material_cost' => 230,
            'preferred_vendor_id' => $vendor->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('price_items', [
            'name' => 'Concrete Material',
            'price_category_id' => $category->id,
            'preferred_vendor_id' => $vendor->id,
        ]);
    }

    public function test_create_requires_valid_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/price-book', ['name' => 'Orphan', 'price_category_id' => 999])
            ->assertSessionHasErrors('price_category_id');
    }

    public function test_admin_can_update_and_delete_item(): void
    {
        $admin = User::factory()->admin()->create();
        $item = PriceItem::factory()->for(PriceCategory::factory(), 'category')->create();

        $this->actingAs($admin)->put('/price-book/'.$item->id, [
            'price_category_id' => $item->price_category_id,
            'name' => 'Renamed',
        ])->assertRedirect();
        $this->assertDatabaseHas('price_items', ['id' => $item->id, 'name' => 'Renamed']);

        $this->actingAs($admin)->delete('/price-book/'.$item->id)->assertRedirect();
        $this->assertDatabaseMissing('price_items', ['id' => $item->id]);
    }

    public function test_crew_cannot_create_item(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $category = PriceCategory::factory()->create();

        $this->actingAs($crew)
            ->post('/price-book', ['price_category_id' => $category->id, 'name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertDatabaseMissing('price_items', ['name' => 'Sneaky']);
    }
}
