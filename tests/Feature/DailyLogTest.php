<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(User $author, Project $project, array $attributes = []): DailyLog
    {
        return DailyLog::create([
            'project_id' => $project->id,
            'user_id' => $author->id,
            'log_date' => '2026-08-14',
            'notes' => 'Poured footings on the north wall.',
            ...$attributes,
        ]);
    }

    public function test_crew_can_view_feed_and_create_log(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->get('/daily-logs')->assertOk()
            ->assertInertia(fn ($page) => $page->component('daily-logs/index'));

        $this->actingAs($crew)
            ->post('/daily-logs', [
                'project_id' => $project->id,
                'log_date' => '2026-08-14',
                'notes' => 'Framing west wall complete.',
                'weather' => 'Sunny',
                'temperature_f' => 78,
                'crew_present' => 'Wyatt, Matt',
            ])
            ->assertRedirect();

        $log = DailyLog::first();
        $this->assertSame($crew->id, $log->user_id);
        $this->assertSame('Sunny', $log->weather);
    }

    public function test_notes_are_required(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)
            ->from('/daily-logs')
            ->post('/daily-logs', ['project_id' => $project->id, 'log_date' => '2026-08-14'])
            ->assertSessionHasErrors('notes');
    }

    public function test_author_can_edit_own_log_but_not_others(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_CREW]);
        $other = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $log = $this->makeLog($author, $project);

        $this->actingAs($author)
            ->put("/daily-logs/{$log->id}", ['notes' => 'Updated notes.'])
            ->assertRedirect();
        $this->assertSame('Updated notes.', $log->refresh()->notes);

        $this->actingAs($other)
            ->put("/daily-logs/{$log->id}", ['notes' => 'Hijacked.'])
            ->assertForbidden();
        $this->assertSame('Updated notes.', $log->refresh()->notes);
    }

    public function test_admin_can_edit_and_delete_any_log(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $log = $this->makeLog($crew, $project);

        $this->actingAs($admin)
            ->put("/daily-logs/{$log->id}", ['issues' => 'Windows backordered.'])
            ->assertRedirect();
        $this->assertSame('Windows backordered.', $log->refresh()->issues);

        $this->actingAs($admin)->delete("/daily-logs/{$log->id}")->assertRedirect();
        $this->assertDatabaseMissing('daily_logs', ['id' => $log->id]);
    }

    public function test_crew_cannot_delete_others_log(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_CREW]);
        $other = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $log = $this->makeLog($author, $project);

        $this->actingAs($other)->delete("/daily-logs/{$log->id}")->assertForbidden();
        $this->assertDatabaseHas('daily_logs', ['id' => $log->id]);
    }

    public function test_feed_filters_by_project(): void
    {
        $admin = User::factory()->admin()->create();
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $this->makeLog($admin, $projectA);
        $this->makeLog($admin, $projectB, ['notes' => 'Project B log.']);

        $this->actingAs($admin)
            ->get("/daily-logs?project={$projectB->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.notes', 'Project B log.'));
    }

    public function test_invalid_weather_is_rejected(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)
            ->from('/daily-logs')
            ->post('/daily-logs', [
                'project_id' => $project->id,
                'log_date' => '2026-08-14',
                'notes' => 'Test.',
                'weather' => 'Meteor shower',
            ])
            ->assertSessionHasErrors('weather');
    }
}
