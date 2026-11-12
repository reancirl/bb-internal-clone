<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
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

    public function test_admin_can_view_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeLead();

        $this->actingAs($admin)
            ->get('/admin/leads')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/leads/index')
                ->has('leads', 1)
                ->where('funnel', Lead::FUNNEL_GENERAL)
                ->has('users'));
    }

    public function test_crew_cannot_view_pipeline(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)->get('/admin/leads')->assertForbidden();
    }

    public function test_admin_can_create_lead_and_creation_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/leads', [
            'first_name' => 'Maria',
            'last_name' => 'Gonzales',
            'email' => 'maria@example.com',
            'phone' => '(307) 555-0102',
            'priority' => 'high',
            'source' => 'referral',
            'estimated_value_cents' => 4500000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $lead = Lead::firstWhere('email', 'maria@example.com');
        $this->assertNotNull($lead);
        $this->assertSame(Lead::STATUS_NEW, $lead->status);
        $this->assertSame(Lead::FUNNEL_GENERAL, $lead->funnel);
        $this->assertSame('high', $lead->priority);
        $this->assertSame(1, $lead->activities()->count());
    }

    public function test_status_change_sets_won_timestamp_and_logs_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead(['status' => Lead::STATUS_PROPOSAL_SENT]);

        $this->actingAs($admin)
            ->patch("/admin/leads/{$lead->id}", ['status' => Lead::STATUS_WON])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(Lead::STATUS_WON, $lead->status);
        $this->assertNotNull($lead->won_at);
        $this->assertNull($lead->lost_at);
        $this->assertSame(1, $lead->activities()->count());
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead();

        $this->actingAs($admin)
            ->from('/admin/leads')
            ->patch("/admin/leads/{$lead->id}", ['status' => 'bogus'])
            ->assertSessionHasErrors('status');
    }

    public function test_admin_can_log_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead();

        $this->actingAs($admin)
            ->post("/admin/leads/{$lead->id}/activities", [
                'activity_type' => 'call',
                'title' => 'Initial contact call',
                'description' => 'Discussed scope.',
                'completed_at' => now()->toISOString(),
            ])
            ->assertRedirect();

        $activity = $lead->activities()->first();
        $this->assertNotNull($activity);
        $this->assertSame('call', $activity->activity_type);
        $this->assertSame($admin->id, $activity->user_id);
    }

    public function test_convert_creates_project_once_and_links_it(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead([
            'status' => Lead::STATUS_WON,
            'won_at' => now(),
            'build_location' => 'Buffalo, WY',
        ]);

        $this->actingAs($admin)
            ->post("/admin/leads/{$lead->id}/convert")
            ->assertRedirect();

        $lead->refresh();
        $this->assertNotNull($lead->converted_project_id);
        $project = Project::find($lead->converted_project_id);
        $this->assertSame('John Staebler', $project->client_name);

        // Converting again must not create a second project.
        $this->actingAs($admin)->post("/admin/leads/{$lead->id}/convert")->assertRedirect();
        $this->assertSame(1, Project::count());
    }

    public function test_admin_can_delete_lead_and_activities_cascade(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead();
        $lead->activities()->create([
            'user_id' => $admin->id,
            'activity_type' => 'note',
            'title' => 'Lead created',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete("/admin/leads/{$lead->id}")
            ->assertRedirect(route('admin.leads.index'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('lead_activities', ['lead_id' => $lead->id]);
    }

    public function test_crew_cannot_delete_lead(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $lead = $this->makeLead();

        $this->actingAs($crew)->delete("/admin/leads/{$lead->id}")->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_moving_to_lost_stores_the_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead(['status' => Lead::STATUS_PROPOSAL_SENT]);

        $this->actingAs($admin)
            ->patch("/admin/leads/{$lead->id}", [
                'status' => Lead::STATUS_LOST,
                'lost_reason' => 'Went with competitor.',
            ])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(Lead::STATUS_LOST, $lead->status);
        $this->assertSame('Went with competitor.', $lead->lost_reason);
        $this->assertNotNull($lead->lost_at);
    }

    public function test_show_page_returns_lead_with_activities(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead();
        $lead->activities()->create([
            'user_id' => $admin->id,
            'activity_type' => 'note',
            'title' => 'Lead created',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/admin/leads/{$lead->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/leads/show')
                ->where('lead.id', $lead->id)
                ->has('activities', 1)
                ->where('activities.0.created_by.name', $admin->name));
    }

    public function test_pipeline_keeps_each_funnel_on_its_own_board(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeLead(['email' => 'sales@example.com']);
        $cornerstone = $this->makeLead([
            'email' => 'spec@example.com',
            'funnel' => Lead::FUNNEL_CORNERSTONE,
            'status' => Lead::STATUS_QUALIFIED,
        ]);

        $this->actingAs($admin)
            ->get('/admin/leads')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('funnel', Lead::FUNNEL_GENERAL)
                ->has('leads', 1)
                ->where('leads.0.email', 'sales@example.com'));

        $this->actingAs($admin)
            ->get('/admin/leads?funnel=cornerstone')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('funnel', Lead::FUNNEL_CORNERSTONE)
                ->has('leads', 1)
                ->where('leads.0.id', $cornerstone->id)
                ->where('leads.0.status', Lead::STATUS_QUALIFIED));

        $this->actingAs($admin)
            ->get('/admin/leads/analytics?funnel=cornerstone')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/leads/analytics')
                ->where('funnel', Lead::FUNNEL_CORNERSTONE)
                ->has('leads', 1));
    }

    public function test_unknown_funnel_returns_to_the_sales_board(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/leads?funnel=nope')
            ->assertRedirect('/admin/leads');
    }

    public function test_moving_a_lead_changes_funnel_and_keeps_its_stage(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = $this->makeLead(['status' => Lead::STATUS_PROPOSAL_SENT]);

        $this->actingAs($admin)
            ->patch("/admin/leads/{$lead->id}", ['funnel' => Lead::FUNNEL_CORNERSTONE])
            ->assertRedirect()
            ->assertSessionHas('success', 'Lead moved to Cornerstone / spec homes.');

        $lead->refresh();
        $this->assertSame(Lead::FUNNEL_CORNERSTONE, $lead->funnel);
        $this->assertSame(Lead::STATUS_PROPOSAL_SENT, $lead->status);
        $this->assertNull($lead->won_at);

        $activity = $lead->activities()->first();
        $this->assertNotNull($activity);
        $this->assertSame('Moved to Cornerstone / spec homes', $activity->title);

        $this->actingAs($admin)
            ->get('/admin/leads')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('leads', 0));
    }

    public function test_lead_created_on_the_cornerstone_board_lands_there(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/leads', [
            'first_name' => 'Spec',
            'last_name' => 'Home',
            'email' => 'spec-home@example.com',
            'phone' => '(307) 555-0199',
            'funnel' => Lead::FUNNEL_CORNERSTONE,
            'status' => Lead::STATUS_CONTACTED,
        ])->assertRedirect();

        $lead = Lead::firstWhere('email', 'spec-home@example.com');
        $this->assertNotNull($lead);
        $this->assertSame(Lead::FUNNEL_CORNERSTONE, $lead->funnel);
        $this->assertSame(Lead::STATUS_CONTACTED, $lead->status);
    }

    public function test_website_intake_stays_on_the_sales_board(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create();
        $token = $user->createToken('website', ['leads:create'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/leads', [
                'firstName' => 'Website',
                'lastName' => 'Visitor',
                'email' => 'visitor@example.com',
                'phone' => '(307) 555-0108',
                'buildLocation' => 'Cody',
                'projectDetails' => 'Custom home',
                'createdAt' => now()->toISOString(),
            ])
            ->assertCreated()
            ->assertJson(['success' => true]);

        $lead = Lead::firstWhere('email', 'visitor@example.com');
        $this->assertNotNull($lead);
        $this->assertSame('website', $lead->source);
        $this->assertSame(Lead::FUNNEL_GENERAL, $lead->funnel);
    }
}
