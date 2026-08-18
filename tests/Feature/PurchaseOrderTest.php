<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\TakeoffLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Vendor $vendor, array $overrides = []): array
    {
        return array_merge([
            'vendor_id' => $vendor->id,
            'items' => [
                ['description' => 'OSB 7/16', 'qty' => 200, 'unit' => 'sheet', 'unit_price_cents' => 1450],
                ['description' => 'Delivery fee', 'qty' => 1, 'unit' => 'ea', 'unit_price_cents' => 7500],
            ],
        ], $overrides);
    }

    public function test_crew_cannot_access_purchase_orders(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->get("/projects/{$project->id}/purchase-orders")->assertForbidden();
        $this->actingAs($crew)->post("/projects/{$project->id}/purchase-orders", [])->assertForbidden();
    }

    public function test_store_creates_draft_with_number_and_totals(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create(['name' => 'Bloedorn Lumber']);

        $this->actingAs($this->admin())
            ->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor))
            ->assertRedirect()->assertSessionHasNoErrors();

        $po = PurchaseOrder::first();
        $this->assertSame('PO-'.now()->format('Y').'-001', $po->number);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertSame('Bloedorn Lumber', $po->vendor_name);
        $this->assertSame(200 * 1450 + 7500, $po->total_cents);
        $this->assertCount(2, $po->items);
    }

    public function test_numbers_increment(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor));
        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor));

        $this->assertSame(
            ['PO-'.now()->format('Y').'-001', 'PO-'.now()->format('Y').'-002'],
            PurchaseOrder::orderBy('id')->pluck('number')->all(),
        );
    }

    public function test_items_without_price_are_tbd_and_excluded_from_total(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin())->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor, [
            'items' => [
                ['description' => 'Priced', 'qty' => 10, 'unit_price_cents' => 100],
                ['description' => 'Unpriced', 'qty' => 5],
            ],
        ]));

        $po = PurchaseOrder::first();
        $this->assertSame(1000, $po->total_cents);
        $this->assertNull($po->items->firstWhere('description', 'Unpriced')->total_cents);
    }

    public function test_takeoff_line_from_another_project_is_rejected(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $foreignLine = TakeoffLine::factory()->create();

        $this->actingAs($this->admin())->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor, [
            'items' => [
                ['takeoff_line_id' => $foreignLine->id, 'description' => 'Sneaky', 'qty' => 1, 'unit_price_cents' => 100],
            ],
        ]))->assertStatus(422);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_sending_marks_linked_takeoff_lines_ordered(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $line = TakeoffLine::factory()->create(['project_id' => $project->id, 'ordered' => false, 'on_site' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor, [
            'items' => [['takeoff_line_id' => $line->id, 'description' => 'OSB', 'qty' => 10, 'unit_price_cents' => 1450]],
        ]));
        $po = PurchaseOrder::first();

        $this->actingAs($admin)->post("/purchase-orders/{$po->id}/transition", ['status' => 'sent'])->assertRedirect();

        $this->assertTrue($line->fresh()->ordered);
        $this->assertFalse($line->fresh()->on_site);
        $this->assertNotNull($po->fresh()->sent_at);
    }

    public function test_receiving_marks_lines_on_site(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $line = TakeoffLine::factory()->create(['project_id' => $project->id, 'ordered' => false, 'on_site' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor, [
            'items' => [['takeoff_line_id' => $line->id, 'description' => 'OSB', 'qty' => 10, 'unit_price_cents' => 1450]],
        ]));
        $po = PurchaseOrder::first();

        $this->actingAs($admin)->post("/purchase-orders/{$po->id}/transition", ['status' => 'sent']);
        $this->actingAs($admin)->post("/purchase-orders/{$po->id}/transition", ['status' => 'received']);

        $this->assertTrue($line->fresh()->on_site);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor));
        $po = PurchaseOrder::first();

        // Draft cannot jump straight to received.
        $this->actingAs($admin)->post("/purchase-orders/{$po->id}/transition", ['status' => 'received']);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->fresh()->status);

        // Received is terminal.
        $po->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
        $this->actingAs($admin)->post("/purchase-orders/{$po->id}/transition", ['status' => 'canceled']);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
    }

    public function test_only_drafts_can_be_edited_or_deleted(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor));
        $po = PurchaseOrder::first();
        $po->update(['status' => PurchaseOrder::STATUS_SENT]);

        $this->actingAs($admin)->put("/purchase-orders/{$po->id}", $this->payload($vendor));
        $this->assertCount(2, $po->fresh()->items); // unchanged

        $this->actingAs($admin)->delete("/purchase-orders/{$po->id}");
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }

    public function test_committed_total_counts_sent_but_not_draft_or_canceled(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        foreach ([1, 2, 3] as $i) {
            $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor, [
                'items' => [['description' => "Item {$i}", 'qty' => 1, 'unit_price_cents' => 1000 * $i]],
            ]));
        }
        [$draft, $sent, $canceled] = PurchaseOrder::orderBy('id')->get();
        $this->actingAs($admin)->post("/purchase-orders/{$sent->id}/transition", ['status' => 'sent']);
        $this->actingAs($admin)->post("/purchase-orders/{$canceled->id}/transition", ['status' => 'canceled']);

        $this->actingAs($admin)->get("/projects/{$project->id}/purchase-orders")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('projects/purchase-orders')
                ->where('committedCents', 2000)
                ->has('orders', 3));
    }

    public function test_vendor_deletion_keeps_po_with_name_snapshot(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create(['name' => 'Bloedorn Lumber']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor));
        $vendor->delete();

        $po = PurchaseOrder::first();
        $this->assertNull($po->fresh()->vendor_id);
        $this->assertSame('Bloedorn Lumber', $po->fresh()->vendor_name);
    }

    public function test_pdf_downloads(): void
    {
        $project = Project::factory()->create();
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/purchase-orders", $this->payload($vendor));
        $po = PurchaseOrder::first();

        $response = $this->actingAs($admin)->get("/purchase-orders/{$po->id}/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
