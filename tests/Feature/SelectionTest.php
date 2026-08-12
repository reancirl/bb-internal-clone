<?php

namespace Tests\Feature;

use App\Models\DecisionCategory;
use App\Models\DecisionItem;
use App\Models\Project;
use App\Models\ProjectSelection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCatalog(): DecisionItem
    {
        $category = DecisionCategory::create(['name' => 'KITCHEN', 'scope' => 'living']);

        return $category->items()->create(['label' => 'Countertops', 'recommended' => 'Quartz or epoxy']);
    }

    public function test_crew_can_view_selections_page(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)
            ->get("/projects/{$project->id}/selections")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('projects/selections')
                ->where('isAdmin', false));
    }

    public function test_generate_creates_selections_and_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->makeCatalog();

        $this->actingAs($admin)->post("/projects/{$project->id}/selections/generate")->assertRedirect();
        $this->assertSame(1, $project->selections()->count());

        // Second run adds nothing.
        $this->actingAs($admin)->post("/projects/{$project->id}/selections/generate")->assertRedirect();
        $this->assertSame(1, $project->selections()->count());
    }

    public function test_generate_skips_archived_items(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $item = $this->makeCatalog();
        $item->update(['is_active' => false]);

        $this->actingAs($admin)->post("/projects/{$project->id}/selections/generate")->assertRedirect();
        $this->assertSame(0, $project->selections()->count());
    }

    public function test_crew_cannot_generate_or_update(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $item = $this->makeCatalog();
        $selection = $project->selections()->create(['decision_item_id' => $item->id]);

        $this->actingAs($crew)->post("/projects/{$project->id}/selections/generate")->assertForbidden();
        $this->actingAs($crew)->put("/selections/{$selection->id}", ['allowance_cents' => 100])->assertForbidden();
    }

    public function test_admin_can_set_allowance_and_deadline(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $item = $this->makeCatalog();
        $selection = $project->selections()->create(['decision_item_id' => $item->id]);

        $this->actingAs($admin)
            ->put("/selections/{$selection->id}", [
                'allowance_cents' => 450000,
                'deadline_date' => '2026-09-01',
            ])
            ->assertRedirect();

        $selection->refresh();
        $this->assertSame(450000, $selection->allowance_cents);
        $this->assertSame('2026-09-01', $selection->deadline_date->toDateString());
    }

    public function test_approval_flow_and_variance(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $item = $this->makeCatalog();
        $selection = $project->selections()->create([
            'decision_item_id' => $item->id,
            'allowance_cents' => 400000,
        ]);
        $choice = $selection->choices()->create(['label' => 'Quartz — Calacatta', 'price_cents' => 510000]);

        // Approve stamps who/when and computes variance.
        $this->actingAs($admin)
            ->post("/selections/{$selection->id}/approve", ['choice_id' => $choice->id, 'comment' => 'Confirmed by phone'])
            ->assertRedirect();

        $selection->refresh();
        $this->assertSame($choice->id, $selection->approved_choice_id);
        $this->assertSame($admin->id, $selection->approved_by_user_id);
        $this->assertNotNull($selection->approved_at);
        $this->assertSame('Confirmed by phone', $selection->approval_comment);
        $this->assertSame(110000, $selection->varianceCents()); // 5,100 - 4,000 = +1,100 over

        // Approving the same choice again un-approves.
        $this->actingAs($admin)
            ->post("/selections/{$selection->id}/approve", ['choice_id' => $choice->id])
            ->assertRedirect();

        $selection->refresh();
        $this->assertNull($selection->approved_choice_id);
        $this->assertNull($selection->approved_at);
    }

    public function test_deleting_approved_choice_clears_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $item = $this->makeCatalog();
        $selection = $project->selections()->create(['decision_item_id' => $item->id]);
        $choice = $selection->choices()->create(['label' => 'Epoxy', 'price_cents' => 300000]);

        $this->actingAs($admin)->post("/selections/{$selection->id}/approve", ['choice_id' => $choice->id]);
        $this->actingAs($admin)->delete("/selection-choices/{$choice->id}")->assertRedirect();

        $selection->refresh();
        $this->assertNull($selection->approved_choice_id);
        $this->assertNull($selection->approved_at);
        $this->assertNull($selection->approved_by_user_id);
    }

    public function test_catalog_allows_same_name_in_different_scopes(): void
    {
        $admin = User::factory()->admin()->create();
        DecisionCategory::create(['name' => 'FLOORING', 'scope' => 'living']);

        // Same name, different scope — allowed.
        $this->actingAs($admin)
            ->post('/admin/decision-categories', ['name' => 'FLOORING', 'scope' => 'garage'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Same name, same scope — rejected.
        $this->actingAs($admin)
            ->from('/admin/decision-catalog')
            ->post('/admin/decision-categories', ['name' => 'FLOORING', 'scope' => 'living'])
            ->assertSessionHasErrors('name');

        $this->assertSame(2, DecisionCategory::count());
    }

    public function test_selection_survives_catalog_item_archive(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $item = $this->makeCatalog();
        $selection = $project->selections()->create(['decision_item_id' => $item->id]);

        $this->actingAs($admin)
            ->put("/admin/decision-items/{$item->id}", ['is_active' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('project_selections', ['id' => $selection->id]);
    }
}
