<?php

namespace Tests\Feature;

use App\Models\BudgetSection;
use App\Models\ChangeOrder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    // BUG-001 — numbering, decision guards, and the approval transaction.

    public function test_numbering_is_per_project(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Project::factory()->create();
        $b = Project::factory()->create();

        $this->makeChangeOrder($a); // CO-1 on A

        // Each project numbers independently: B starts at 1 even though A
        // already has change orders.
        $this->actingAs($admin)->post("/projects/{$a->id}/change-orders", ['title' => 'Second on A'])->assertRedirect();
        $this->actingAs($admin)->post("/projects/{$b->id}/change-orders", ['title' => 'First on B'])->assertRedirect();

        $this->assertSame([1, 2], $a->changeOrders()->pluck('number')->all());
        $this->assertSame([1], $b->changeOrders()->pluck('number')->all());
    }

    public function test_numbering_reuses_the_number_of_a_deleted_trailing_change_order(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->makeChangeOrder($project);
        $this->makeChangeOrder($project)->delete(); // CO-2 deleted

        $this->actingAs($admin)->post("/projects/{$project->id}/change-orders", ['title' => 'Next'])->assertRedirect();

        // Documented behavior: numbering is max+1 over surviving rows, so a
        // deleted trailing number is handed out again. That is acceptable here
        // because change orders are internal and only pending ones can be
        // deleted — unlike proposals and POs, whose numbers are customer- and
        // supplier-facing and are therefore never reissued.
        $this->assertSame([1, 2], $project->changeOrders()->pluck('number')->all());
    }

    public function test_an_approved_change_order_cannot_be_decided_again(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        $this->actingAs($admin)->post("/change-orders/{$co->id}/decide", [
            'status' => ChangeOrder::STATUS_APPROVED,
        ])->assertRedirect();

        $co->refresh();
        $firstDecider = $co->decided_by_user_id;
        $firstLineId = $co->budget_line_id;
        $this->assertNotNull($firstLineId);

        // A second decision is refused: it would restamp the decider and, after
        // a revert had cleared budget_line_id, write a duplicate budget line.
        $this->actingAs($other)
            ->from("/projects/{$project->id}/budget")
            ->post("/change-orders/{$co->id}/decide", ['status' => ChangeOrder::STATUS_DECLINED])
            ->assertRedirect()
            ->assertSessionHas('error');

        $co->refresh();
        $this->assertSame(ChangeOrder::STATUS_APPROVED, $co->status);
        $this->assertSame($firstDecider, $co->decided_by_user_id);
        $this->assertSame($firstLineId, $co->budget_line_id);
        $this->assertSame(1, $project->budgetLines()->count());
    }

    public function test_a_declined_change_order_cannot_be_decided_again(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        $this->actingAs($admin)->post("/change-orders/{$co->id}/decide", [
            'status' => ChangeOrder::STATUS_DECLINED,
        ])->assertRedirect();

        // Flipping a declined order straight to approved would skip the revert
        // step that the edit guard depends on.
        $this->actingAs($admin)
            ->from("/projects/{$project->id}/budget")
            ->post("/change-orders/{$co->id}/decide", ['status' => ChangeOrder::STATUS_APPROVED])
            ->assertSessionHas('error');

        $this->assertSame(ChangeOrder::STATUS_DECLINED, $co->refresh()->status);
        $this->assertNull($co->budget_line_id);
        $this->assertSame(0, $project->budgetLines()->count());
    }

    public function test_reverting_a_pending_change_order_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        $this->actingAs($admin)
            ->from("/projects/{$project->id}/budget")
            ->post("/change-orders/{$co->id}/revert")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(ChangeOrder::STATUS_PENDING, $co->refresh()->status);
    }

    public function test_reverting_then_approving_reuses_one_budget_line(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        $this->actingAs($admin)->post("/change-orders/{$co->id}/decide", [
            'status' => ChangeOrder::STATUS_APPROVED,
        ])->assertRedirect();
        $this->actingAs($admin)->post("/change-orders/{$co->id}/revert")->assertRedirect();
        $this->actingAs($admin)->post("/change-orders/{$co->id}/decide", [
            'status' => ChangeOrder::STATUS_APPROVED,
        ])->assertRedirect();

        // The empty line is removed on revert and one fresh line is written on
        // re-approval — never two.
        $this->assertSame(1, $project->budgetLines()->count());
        $this->assertNotNull($co->refresh()->budget_line_id);
        $this->assertSame(1, BudgetSection::where('name', 'CHANGE ORDERS')->count());
    }

    public function test_approval_rolls_back_entirely_when_the_budget_line_write_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $co = $this->makeChangeOrder($project);

        // Make the budget-line insert throw after the status update has already
        // run inside the transaction. Renaming the table is driver-agnostic:
        // dropping it fails on Postgres, where other tables reference it by
        // foreign key, while SQLite allows the drop.
        Schema::rename('project_budget_lines', 'project_budget_lines_hidden');

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post("/change-orders/{$co->id}/decide", [
                'status' => ChangeOrder::STATUS_APPROVED,
            ]);
            $this->fail('The budget-line write should have thrown.');
        } catch (QueryException) {
            // Expected: the request dies partway through the approval.
        }

        // Without the transaction the status update would have survived the
        // failed line write, leaving an approved CO with no cost line.
        $this->assertSame(ChangeOrder::STATUS_PENDING, $co->refresh()->status);
        $this->assertNull($co->decided_at);
        $this->assertNull($co->budget_line_id);
    }
}
