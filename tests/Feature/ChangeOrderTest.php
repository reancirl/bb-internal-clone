<?php

namespace Tests\Feature;

use App\Models\BudgetSection;
use App\Models\ChangeOrder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeChangeOrder(Project $project, array $attributes = []): ChangeOrder
    {
        return $project->changeOrders()->create([
            'number' => ($project->changeOrders()->max('number') ?? 0) + 1,
            'title' => 'Extra window in master bedroom',
            'price_cents' => 280000,
            ...$attributes,
        ]);
    }

    public function test_crew_cannot_manage_change_orders(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)
            ->post("/projects/{$project->id}/change-orders", ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_admin_can_create_with_auto_number(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post("/projects/{$project->id}/change-orders", [
            'title' => 'Extra window',
            'price_cents' => 280000,
        ])->assertRedirect();
        $this->actingAs($admin)->post("/projects/{$project->id}/change-orders", [
            'title' => 'Bigger patio',
        ])->assertRedirect();

        $this->assertSame([1, 2], $project->changeOrders()->pluck('number')->all());
        $this->assertSame(ChangeOrder::STATUS_PENDING, $project->changeOrders()->first()->status);
    }

    public function test_approval_stamps_decision_and_creates_budget_line(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        $this->actingAs($admin)
            ->post("/change-orders/{$co->id}/decide", [
                'status' => 'approved',
                'comment' => 'Customer signed revised quote',
            ])
            ->assertRedirect();

        $co->refresh();
        $this->assertSame(ChangeOrder::STATUS_APPROVED, $co->status);
        $this->assertSame($admin->id, $co->decided_by_user_id);
        $this->assertNotNull($co->decided_at);
        $this->assertNotNull($co->budget_line_id);
        $this->assertSame('CO-1 — Extra window in master bedroom', $co->budgetLine->name);
        $this->assertSame('CHANGE ORDERS', $co->budgetLine->section->name);
    }

    public function test_decline_does_not_create_budget_line(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        $this->actingAs($admin)
            ->post("/change-orders/{$co->id}/decide", ['status' => 'declined'])
            ->assertRedirect();

        $co->refresh();
        $this->assertSame(ChangeOrder::STATUS_DECLINED, $co->status);
        $this->assertNull($co->budget_line_id);
        $this->assertSame(0, $project->budgetLines()->count());
    }

    public function test_revert_removes_empty_budget_line_but_keeps_one_with_costs(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        // Empty line → removed on revert.
        $co1 = $this->makeChangeOrder($project);
        $this->actingAs($admin)->post("/change-orders/{$co1->id}/decide", ['status' => 'approved']);
        $this->actingAs($admin)->post("/change-orders/{$co1->id}/revert")->assertRedirect();
        $co1->refresh();
        $this->assertSame(ChangeOrder::STATUS_PENDING, $co1->status);
        $this->assertNull($co1->budget_line_id);
        $this->assertSame(0, $project->budgetLines()->count());

        // Line with recorded costs → kept.
        $co2 = $this->makeChangeOrder($project, ['title' => 'Patio']);
        $this->actingAs($admin)->post("/change-orders/{$co2->id}/decide", ['status' => 'approved']);
        $co2->refresh();
        $co2->budgetLine->update(['actual_material_cents' => 50000]);
        $this->actingAs($admin)->post("/change-orders/{$co2->id}/revert")->assertRedirect();
        $co2->refresh();
        $this->assertSame(ChangeOrder::STATUS_PENDING, $co2->status);
        $this->assertNotNull($co2->budget_line_id);
    }

    public function test_approved_change_order_cannot_be_edited_or_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);
        $this->actingAs($admin)->post("/change-orders/{$co->id}/decide", ['status' => 'approved']);

        $this->actingAs($admin)
            ->from("/projects/{$project->id}/budget")
            ->put("/change-orders/{$co->id}", ['title' => 'Sneaky rename'])
            ->assertSessionHasErrors('title');
        $this->assertSame('Extra window in master bedroom', $co->refresh()->title);

        $this->actingAs($admin)
            ->from("/projects/{$project->id}/budget")
            ->delete("/change-orders/{$co->id}")
            ->assertSessionHasErrors('title');
        $this->assertDatabaseHas('change_orders', ['id' => $co->id]);
    }

    public function test_contract_price_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->patch("/projects/{$project->id}/contract", ['contract_price_cents' => 45000000])
            ->assertRedirect();

        $this->assertSame(45000000, $project->refresh()->contract_price_cents);
    }
}
