<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConversionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLead(array $attributes = []): Lead
    {
        return Lead::create([
            'first_name' => 'John',
            'last_name' => 'Staebler',
            'email' => 'john@example.com',
            'phone' => '(307) 555-0101',
            'source' => 'website',
            'submitted_at' => now(),
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_wizard_prefills_from_the_lead(): void
    {
        $lead = $this->makeLead([
            'first_name' => 'Dana',
            'last_name' => 'Staebler',
            'build_location' => 'Big Horn, WY',
            'estimated_value_cents' => 42500000,
        ]);

        $this->actingAs($this->admin())->get("/admin/leads/{$lead->id}/convert")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/leads/convert')
                ->where('lead.client_name', 'Dana Staebler')
                ->where('suggestedName', 'Dana Staebler — Big Horn, WY')
                ->where('lead.estimated_value_cents', 42500000)
                ->has('dimensionLabels')
                ->where('takeoffLineCount', fn (int $count) => $count > 0));
    }

    public function test_wizard_creates_project_with_dimensions_price_and_takeoff(): void
    {
        $lead = $this->makeLead(['first_name' => 'Dana', 'last_name' => 'Staebler']);

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", [
            'name' => 'Staebler Custom Home',
            'client_name' => 'Dana Staebler',
            'address' => '12 Piney Creek Rd',
            'status' => Project::STATUS_ACTIVE,
            'start_date' => '2026-09-07',
            'contract_price_cents' => 42500000,
            'generate_takeoff' => true,
            'dimensions' => ['house_sqft' => 2400, 'wall_height' => 9],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = Project::firstWhere('name', 'Staebler Custom Home');
        $this->assertNotNull($project);
        $this->assertSame('Dana Staebler', $project->client_name);
        $this->assertSame('2026-09-07', $project->start_date->toDateString());
        $this->assertSame(42500000, $project->contract_price_cents);
        $this->assertSame('2400.00', $project->house_sqft);
        $this->assertSame('9.00', $project->wall_height);
        $this->assertTrue($project->takeoffLines()->exists());

        $this->assertSame($project->id, $lead->fresh()->converted_project_id);
        $this->assertSame($project->id, $project->lead->converted_project_id);
    }

    public function test_takeoff_can_be_skipped(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", [
            'name' => 'Bare Project',
            'generate_takeoff' => false,
        ])->assertRedirect();

        $this->assertFalse(Project::firstWhere('name', 'Bare Project')->takeoffLines()->exists());
    }

    public function test_one_click_convert_still_works_without_wizard_fields(): void
    {
        $lead = $this->makeLead([
            'first_name' => 'Ann',
            'last_name' => 'Miller',
            'build_location' => 'Sheridan',
        ]);

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", [])
            ->assertRedirect()->assertSessionHasNoErrors();

        $project = Project::firstWhere('name', 'Ann Miller — Sheridan');
        $this->assertNotNull($project);
        // Defaults to the standard takeoff, matching a hand-created project.
        $this->assertTrue($project->takeoffLines()->exists());
    }

    public function test_duplicate_project_name_is_rejected(): void
    {
        Project::factory()->create(['name' => 'Taken Name']);
        $lead = $this->makeLead();

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", ['name' => 'Taken Name'])
            ->assertSessionHasErrors('name');

        $this->assertNull($lead->fresh()->converted_project_id);
    }

    public function test_negative_dimension_is_rejected(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", [
            'name' => 'Bad Dimensions',
            'dimensions' => ['house_sqft' => -5],
        ])->assertSessionHasErrors('dimensions.house_sqft');

        $this->assertDatabaseMissing('projects', ['name' => 'Bad Dimensions']);
    }

    public function test_already_converted_lead_redirects_instead_of_converting_twice(): void
    {
        $project = Project::factory()->create();
        $lead = $this->makeLead(['converted_project_id' => $project->id]);

        $this->actingAs($this->admin())->get("/admin/leads/{$lead->id}/convert")
            ->assertRedirect(route('projects.show', $project));

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", ['name' => 'Second Project'])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('projects', ['name' => 'Second Project']);
    }

    public function test_conversion_is_logged_as_an_activity(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/convert", ['name' => 'Logged Project']);

        $this->assertTrue($lead->activities()->where('title', 'Converted to project')->exists());
    }

    public function test_crew_cannot_use_the_wizard(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $lead = $this->makeLead();

        $this->actingAs($crew)->get("/admin/leads/{$lead->id}/convert")->assertForbidden();
        $this->actingAs($crew)->post("/admin/leads/{$lead->id}/convert", ['name' => 'Nope'])->assertForbidden();
    }
}
