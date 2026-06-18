<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_sees_their_assigned_jobs_from_today(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $other = User::factory()->create(['role' => User::ROLE_CREW]);

        $mine = ProjectJob::factory()->create(['scheduled_date' => Carbon::today(), 'title' => 'My job']);
        $mine->crew()->attach($crew);

        $past = ProjectJob::factory()->create(['scheduled_date' => Carbon::yesterday(), 'title' => 'Old job']);
        $past->crew()->attach($crew);

        $someoneElses = ProjectJob::factory()->create(['scheduled_date' => Carbon::today(), 'title' => 'Not mine']);
        $someoneElses->crew()->attach($other);

        $this->actingAs($crew)->get('/jobs')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('jobs/index')
                ->has('jobs', 1)
                ->where('jobs.0.title', 'My job'));
    }

    public function test_assigned_crew_can_update_job_status(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $job = ProjectJob::factory()->create(['status' => ProjectJob::STATUS_SCHEDULED]);
        $job->crew()->attach($crew);

        $this->actingAs($crew)
            ->patch('/jobs/'.$job->id.'/status', ['status' => ProjectJob::STATUS_DONE])
            ->assertRedirect();

        $this->assertSame(ProjectJob::STATUS_DONE, $job->fresh()->status);
    }

    public function test_crew_cannot_update_status_of_a_job_they_are_not_on(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $job = ProjectJob::factory()->create(['status' => ProjectJob::STATUS_SCHEDULED]);

        $this->actingAs($crew)
            ->patch('/jobs/'.$job->id.'/status', ['status' => ProjectJob::STATUS_DONE])
            ->assertForbidden();

        $this->assertSame(ProjectJob::STATUS_SCHEDULED, $job->fresh()->status);
    }

    public function test_admin_can_schedule_a_job_with_crew(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $worker = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($admin)->post('/jobs', [
            'project_id' => $project->id,
            'title' => 'Framing',
            'scheduled_date' => Carbon::today()->toDateString(),
            'status' => ProjectJob::STATUS_SCHEDULED,
            'crew' => [$worker->id],
        ])->assertRedirect();

        $job = ProjectJob::firstWhere('title', 'Framing');
        $this->assertNotNull($job);
        $this->assertTrue($job->crew()->whereKey($worker->id)->exists());
    }

    public function test_admin_can_reassign_crew_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $job = ProjectJob::factory()->create();
        $first = User::factory()->create(['role' => User::ROLE_CREW]);
        $second = User::factory()->create(['role' => User::ROLE_CREW]);
        $job->crew()->attach($first);

        $this->actingAs($admin)->put('/jobs/'.$job->id, [
            'project_id' => $job->project_id,
            'scheduled_date' => $job->scheduled_date->toDateString(),
            'status' => ProjectJob::STATUS_IN_PROGRESS,
            'crew' => [$second->id],
        ])->assertRedirect();

        $job->refresh();
        $this->assertSame(ProjectJob::STATUS_IN_PROGRESS, $job->status);
        $this->assertFalse($job->crew()->whereKey($first->id)->exists());
        $this->assertTrue($job->crew()->whereKey($second->id)->exists());
    }

    public function test_crew_cannot_create_jobs(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->post('/jobs', [
            'project_id' => $project->id,
            'scheduled_date' => Carbon::today()->toDateString(),
            'status' => ProjectJob::STATUS_SCHEDULED,
        ])->assertForbidden();

        $this->assertDatabaseCount('project_jobs', 0);
    }

    public function test_calendar_lists_jobs_for_the_month(): void
    {
        $admin = User::factory()->admin()->create();
        ProjectJob::factory()->create(['scheduled_date' => Carbon::today()->startOfMonth(), 'title' => 'This month']);
        ProjectJob::factory()->create(['scheduled_date' => Carbon::today()->addMonth()->startOfMonth(), 'title' => 'Next month']);

        $this->actingAs($admin)->get('/calendar?month='.Carbon::today()->format('Y-m'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('calendar/index')
                ->has('jobs', 1)
                ->where('jobs.0.title', 'This month'));
    }

    public function test_crew_can_view_calendar_but_is_not_admin(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)->get('/calendar')->assertOk()
            ->assertInertia(fn ($page) => $page->where('isAdmin', false));
    }
}
