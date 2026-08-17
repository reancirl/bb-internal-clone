<?php

namespace Tests\Feature;

use App\Models\PriceItem;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\TakeoffLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A project whose takeoff computes to a known cost:
     * formula house_sqft (=100) + 0% waste, price 12.50 → qty 100, cost $1,250.00.
     */
    private function makeProjectWithEstimate(): Project
    {
        $project = Project::factory()->create(['house_sqft' => 100]);
        $project->takeoffLines()->delete();

        $price = PriceItem::factory()->create(['fast_price' => 12.50, 'material_cost' => null]);
        TakeoffLine::factory()->for($project)->create([
            'category' => 'FRAMING',
            'item' => 'Studs',
            'formula' => 'house_sqft',
            'waste_pct' => 0,
            'price_item_id' => $price->id,
        ]);

        return $project;
    }

    public function test_crew_cannot_access_proposals(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->get('/admin/proposals')->assertForbidden();
        $this->actingAs($crew)->post("/admin/projects/{$project->id}/proposals")->assertForbidden();
    }

    public function test_store_snapshots_estimate_into_cents(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();

        $response = $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");

        $proposal = ProjectProposal::first();
        $response->assertRedirect("/admin/proposals/{$proposal->id}");

        $this->assertSame('PROP-'.now()->year.'-001', $proposal->number);
        $this->assertSame(ProjectProposal::STATUS_DRAFT, $proposal->status);
        $this->assertSame(125000, $proposal->total_cents);

        $line = $proposal->lines()->first();
        $this->assertSame('Studs', $line->item);
        $this->assertSame('100.00', $line->qty);
        $this->assertSame(1250, $line->unit_price_cents);
        $this->assertSame(125000, $line->total_cents);
    }

    public function test_snapshot_is_immune_to_later_price_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();

        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        PriceItem::query()->update(['fast_price' => 999.99]);

        $this->assertSame(125000, ProjectProposal::first()->fresh()->total_cents);
        $this->assertSame(1250, ProjectProposal::first()->lines()->first()->unit_price_cents);
    }

    public function test_numbers_increment_within_a_year(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();

        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");

        $numbers = ProjectProposal::orderBy('id')->pluck('number')->all();
        $this->assertSame(['PROP-'.now()->year.'-001', 'PROP-'.now()->year.'-002'], $numbers);
    }

    public function test_unpriced_lines_are_kept_but_excluded_from_total(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        TakeoffLine::factory()->for($project)->create([
            'category' => 'CONCRETE',
            'item' => 'Rebar',
            'formula' => 'house_sqft',
            'waste_pct' => 0,
            'price_item_id' => null,
        ]);

        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");

        $proposal = ProjectProposal::first();
        $this->assertSame(125000, $proposal->total_cents);
        $this->assertSame(2, $proposal->lines()->count());
        $this->assertNull($proposal->lines()->where('item', 'Rebar')->first()->total_cents);
    }

    public function test_status_workflow_transitions_and_stamps(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $proposal = ProjectProposal::first();

        // draft → accepted is not allowed
        $this->actingAs($admin)
            ->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'accepted'])
            ->assertSessionHas('error');
        $this->assertSame('draft', $proposal->fresh()->status);

        // draft → sent
        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'sent']);
        $this->assertSame('sent', $proposal->fresh()->status);
        $this->assertNotNull($proposal->fresh()->sent_at);

        // sent → accepted
        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'accepted']);
        $this->assertSame('accepted', $proposal->fresh()->status);
        $this->assertNotNull($proposal->fresh()->accepted_at);

        // accepted is terminal
        $this->actingAs($admin)
            ->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'rejected'])
            ->assertSessionHas('error');
        $this->assertSame('accepted', $proposal->fresh()->status);
    }

    public function test_rejected_proposal_can_be_resent(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $proposal = ProjectProposal::first();

        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'sent']);
        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'rejected']);
        $this->assertNotNull($proposal->fresh()->rejected_at);

        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'sent']);
        $fresh = $proposal->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNull($fresh->rejected_at);
    }

    public function test_only_drafts_can_be_edited_or_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $proposal = ProjectProposal::first();

        // Draft: edit works
        $this->actingAs($admin)->put("/admin/proposals/{$proposal->id}", ['payment_terms' => '30% deposit']);
        $this->assertSame('30% deposit', $proposal->fresh()->payment_terms);

        // Sent: edit and delete blocked
        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'sent']);
        $this->actingAs($admin)
            ->put("/admin/proposals/{$proposal->id}", ['payment_terms' => 'changed'])
            ->assertSessionHas('error');
        $this->assertSame('30% deposit', $proposal->fresh()->payment_terms);

        $this->actingAs($admin)->delete("/admin/proposals/{$proposal->id}")->assertSessionHas('error');
        $this->assertNotNull(ProjectProposal::find($proposal->id));
    }

    public function test_draft_can_be_deleted_with_lines_cascading(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $proposal = ProjectProposal::first();

        $this->actingAs($admin)->delete("/admin/proposals/{$proposal->id}");

        $this->assertDatabaseCount('project_proposals', 0);
        $this->assertDatabaseCount('project_proposal_lines', 0);
    }

    public function test_pdf_downloads(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $proposal = ProjectProposal::first();

        $response = $this->actingAs($admin)->get("/admin/proposals/{$proposal->id}/pdf");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_index_lists_and_filters_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProjectWithEstimate();
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $this->actingAs($admin)->post("/admin/projects/{$project->id}/proposals");
        $proposal = ProjectProposal::first();
        $this->actingAs($admin)->post("/admin/proposals/{$proposal->id}/transition", ['status' => 'sent']);

        $this->actingAs($admin)
            ->get('/admin/proposals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/proposals/index')->has('proposals', 2));

        $this->actingAs($admin)
            ->get('/admin/proposals?status=sent')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('proposals', 1)
                ->where('proposals.0.status', 'sent'));
    }
}
