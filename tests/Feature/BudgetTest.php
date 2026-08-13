<?php

namespace Tests\Feature;

use App\Models\BudgetSection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeCatalog(): BudgetSection
    {
        $section = BudgetSection::create(['name' => 'BUILDING COSTS', 'sort_order' => 2]);
        $section->lineDefinitions()->create(['name' => 'Foundation']);
        $section->lineDefinitions()->create(['name' => 'Framing', 'sort_order' => 1]);

        return $section;
    }

    public function test_crew_cannot_view_budget(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->get("/projects/{$project->id}/budget")->assertForbidden();
    }

    public function test_admin_can_view_budget_page(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->get("/projects/{$project->id}/budget")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('projects/budget'));
    }

    public function test_generate_is_idempotent_and_copies_names(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->makeCatalog();

        $this->actingAs($admin)->post("/projects/{$project->id}/budget/generate")->assertRedirect();
        $this->assertSame(2, $project->budgetLines()->count());
        $this->assertTrue($project->budgetLines()->where('name', 'Foundation')->exists());

        $this->actingAs($admin)->post("/projects/{$project->id}/budget/generate")->assertRedirect();
        $this->assertSame(2, $project->budgetLines()->count());
    }

    public function test_line_amounts_update_and_variance_computes(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $section = $this->makeCatalog();
        $def = $section->lineDefinitions()->first();
        $line = $project->budgetLines()->create([
            'budget_section_id' => $section->id,
            'budget_line_definition_id' => $def->id,
            'name' => $def->name,
        ]);

        $this->actingAs($admin)
            ->put("/budget-lines/{$line->id}", [
                'bid_sub_cents' => 1000000,        // $10,000 bid
                'actual_sub_cents' => 1200000,     // $12,000 actual
                'estimated_material_cents' => 500000,
                'actual_material_cents' => 400000,
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(1500000, $line->budgetedCents());  // 10k + 5k
        $this->assertSame(1600000, $line->actualCents());    // 12k + 4k
        $this->assertSame(-100000, $line->varianceCents());  // $1,000 over
    }

    public function test_catalog_line_name_cannot_be_renamed_but_custom_can(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $section = $this->makeCatalog();
        $def = $section->lineDefinitions()->first();

        $catalogLine = $project->budgetLines()->create([
            'budget_section_id' => $section->id,
            'budget_line_definition_id' => $def->id,
            'name' => $def->name,
        ]);
        $customLine = $project->budgetLines()->create([
            'budget_section_id' => $section->id,
            'name' => 'Change Order #1',
        ]);

        $this->actingAs($admin)->put("/budget-lines/{$catalogLine->id}", ['name' => 'Renamed']);
        $this->actingAs($admin)->put("/budget-lines/{$customLine->id}", ['name' => 'Change Order #1 — revised']);

        $this->assertSame('Foundation', $catalogLine->refresh()->name);
        $this->assertSame('Change Order #1 — revised', $customLine->refresh()->name);
    }

    public function test_admin_can_add_custom_line_and_delete_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $section = BudgetSection::create(['name' => 'CHANGE ORDERS', 'sort_order' => 4]);

        $this->actingAs($admin)
            ->post("/projects/{$project->id}/budget/lines", [
                'budget_section_id' => $section->id,
                'name' => 'Change Order #1 — extra window',
            ])
            ->assertRedirect();

        $line = $project->budgetLines()->first();
        $this->assertNotNull($line);
        $this->assertNull($line->budget_line_definition_id);

        $this->actingAs($admin)->delete("/budget-lines/{$line->id}")->assertRedirect();
        $this->assertSame(0, $project->budgetLines()->count());
    }

    public function test_csv_export_streams_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'Test Build']);
        $section = $this->makeCatalog();
        $project->budgetLines()->create([
            'budget_section_id' => $section->id,
            'name' => 'Foundation',
            'bid_sub_cents' => 1000000,
            'actual_sub_cents' => 900000,
        ]);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}/budget/export");
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Foundation', $csv);
        $this->assertStringContainsString('10000.00', $csv);
        $this->assertStringContainsString('1000.00', $csv); // variance: 10k - 9k
    }

    public function test_reports_page_aggregates_projects(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $section = $this->makeCatalog();
        $project->budgetLines()->create([
            'budget_section_id' => $section->id,
            'name' => 'Foundation',
            'bid_sub_cents' => 500000,
            'actual_sub_cents' => 650000,
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/reports/index')
                ->has('projects', 1)
                ->where('projects.0.budgeted_cents', 500000)
                ->where('projects.0.actual_cents', 650000)
                ->where('projects.0.variance_cents', -150000));
    }
}
