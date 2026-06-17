<?php

namespace Tests\Feature;

use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\TakeoffLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_view_projects_and_a_project(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create(['name' => 'Staebler Residence']);

        $this->actingAs($crew)->get('/projects')->assertOk()
            ->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('isAdmin', false));

        $this->actingAs($crew)->get('/projects/'.$project->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('project.name', 'Staebler Residence'));
    }

    public function test_takeoff_quantity_is_computed_from_dimensions_with_waste(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['ext_wall_lft' => 166.5]);
        TakeoffLine::factory()->for($project)->create([
            'item' => '2x6 plates',
            'formula' => 'ext_wall_lft * 2 / 16',
            'waste_pct' => 25,
        ]);

        // 166.5 * 2 / 16 = 20.8125, +25% = 26.015625 -> round 26.02
        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.base_qty', 20.81)
                ->where('lines.0.computed_qty', 26.02));
    }

    public function test_updating_dimensions_recomputes_quantities(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['ext_wall_lft' => 100]);
        TakeoffLine::factory()->for($project)->create(['formula' => 'ext_wall_lft', 'waste_pct' => 0]);

        $payload = array_merge(
            ['name' => $project->name, 'status' => $project->status],
            ['ext_wall_lft' => 250],
        );

        $this->actingAs($admin)->put('/projects/'.$project->id, $payload)->assertRedirect();

        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertInertia(fn ($page) => $page->where('lines.0.computed_qty', 250));
    }

    public function test_bad_formula_does_not_break_the_page(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        TakeoffLine::factory()->for($project)->create(['formula' => 'bogus_var * 2']);

        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.computed_qty', null)
                ->whereNot('lines.0.formula_error', null));
    }

    public function test_admin_can_create_project_and_is_redirected_to_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/projects', [
            'name' => 'New Build',
            'status' => 'lead',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'New Build']);
    }

    public function test_duplicate_project_name_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'Staebler Residence']);

        $this->actingAs($admin)
            ->post('/projects', ['name' => 'Staebler Residence', 'status' => 'lead'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Project::where('name', 'Staebler Residence')->count());
    }

    public function test_new_project_starts_with_template_takeoff_lines(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/projects', ['name' => 'Templated', 'status' => 'lead'])->assertRedirect();

        $project = Project::firstWhere('name', 'Templated');
        $this->assertGreaterThan(0, $project->takeoffLines()->count());
        $this->assertDatabaseHas('takeoff_lines', ['project_id' => $project->id, 'item' => '2x6 studs (16" OC)']);
    }

    public function test_takeoff_line_can_link_to_a_price_book_item(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $item = PriceItem::factory()->for(PriceCategory::factory(), 'category')->create();

        $this->actingAs($admin)->post('/projects/'.$project->id.'/lines', [
            'price_item_id' => $item->id,
            'item' => $item->name,
            'unit' => $item->unit,
            'formula' => 'roof_sqft',
        ])->assertRedirect();

        $this->assertDatabaseHas('takeoff_lines', [
            'project_id' => $project->id,
            'price_item_id' => $item->id,
        ]);
    }

    public function test_crew_can_toggle_ordered_but_cannot_add_lines(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $line = TakeoffLine::factory()->for($project)->create(['ordered' => false]);

        $this->actingAs($crew)
            ->patch('/takeoff-lines/'.$line->id.'/toggle', ['field' => 'ordered', 'value' => true])
            ->assertRedirect();
        $this->assertTrue($line->fresh()->ordered);

        $this->actingAs($crew)
            ->post('/projects/'.$project->id.'/lines', ['item' => 'Sneaky'])
            ->assertForbidden();
        $this->assertDatabaseMissing('takeoff_lines', ['item' => 'Sneaky']);
    }

    public function test_admin_can_add_and_delete_takeoff_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post('/projects/'.$project->id.'/lines', [
            'category' => 'FRAMING',
            'item' => 'Studs',
            'formula' => 'ext_wall_lft',
            'unit' => 'EA',
            'waste_pct' => 25,
        ])->assertRedirect();
        $this->assertDatabaseHas('takeoff_lines', ['item' => 'Studs', 'project_id' => $project->id]);

        $line = TakeoffLine::firstWhere('item', 'Studs');
        $this->actingAs($admin)->delete('/takeoff-lines/'.$line->id)->assertRedirect();
        $this->assertDatabaseMissing('takeoff_lines', ['id' => $line->id]);
    }
}
