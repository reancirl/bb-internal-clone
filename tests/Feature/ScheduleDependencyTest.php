<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleDependencyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Foundation (5d) -> Framing (10d) -> Roofing (3d), gap-free chain.
     *
     * @return array{Project, ProjectJob, ProjectJob, ProjectJob}
     */
    private function chain(): array
    {
        $project = Project::factory()->create();
        $foundation = ProjectJob::factory()->create([
            'project_id' => $project->id,
            'title' => 'Foundation',
            'scheduled_date' => '2026-09-01',
            'duration_days' => 5,
        ]);
        $framing = ProjectJob::factory()->create([
            'project_id' => $project->id,
            'predecessor_job_id' => $foundation->id,
            'title' => 'Framing',
            'scheduled_date' => '2026-09-06',
            'duration_days' => 10,
        ]);
        $roofing = ProjectJob::factory()->create([
            'project_id' => $project->id,
            'predecessor_job_id' => $framing->id,
            'title' => 'Roofing',
            'scheduled_date' => '2026-09-16',
            'duration_days' => 3,
        ]);

        return [$project, $foundation, $framing, $roofing];
    }

    public function test_admin_can_create_job_with_predecessor_duration_and_trade(): void
    {
        $project = Project::factory()->create();
        // Pin the title: the factory picks random titles including "Framing",
        // which would collide with the firstWhere lookup below.
        $foundation = ProjectJob::factory()->create(['project_id' => $project->id, 'title' => 'Foundation']);

        $this->actingAs($this->admin())->post('/jobs', [
            'project_id' => $project->id,
            'predecessor_job_id' => $foundation->id,
            'title' => 'Framing',
            'scheduled_date' => '2026-09-06',
            'duration_days' => 10,
            'trade' => 'framing',
            'status' => ProjectJob::STATUS_SCHEDULED,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $job = ProjectJob::firstWhere('title', 'Framing');
        $this->assertSame($foundation->id, $job->predecessor_job_id);
        $this->assertSame(10, $job->duration_days);
        $this->assertSame('framing', $job->trade);
        $this->assertSame('2026-09-15', $job->endDate()->toDateString());
    }

    public function test_predecessor_from_another_project_is_rejected(): void
    {
        $project = Project::factory()->create();
        $otherProjectJob = ProjectJob::factory()->create();

        $this->actingAs($this->admin())->post('/jobs', [
            'project_id' => $project->id,
            'predecessor_job_id' => $otherProjectJob->id,
            'scheduled_date' => '2026-09-01',
            'status' => ProjectJob::STATUS_SCHEDULED,
        ])->assertSessionHasErrors('predecessor_job_id');
    }

    public function test_dependency_cycle_is_rejected(): void
    {
        [, $foundation, , $roofing] = $this->chain();

        $this->actingAs($this->admin())->put('/jobs/'.$foundation->id, [
            'project_id' => $foundation->project_id,
            'predecessor_job_id' => $roofing->id,
            'scheduled_date' => $foundation->scheduled_date->toDateString(),
            'duration_days' => $foundation->duration_days,
            'status' => $foundation->status,
        ])->assertSessionHasErrors('predecessor_job_id');

        $this->assertNull($foundation->fresh()->predecessor_job_id);
    }

    public function test_job_cannot_be_its_own_predecessor(): void
    {
        $job = ProjectJob::factory()->create();

        $this->actingAs($this->admin())->put('/jobs/'.$job->id, [
            'project_id' => $job->project_id,
            'predecessor_job_id' => $job->id,
            'scheduled_date' => $job->scheduled_date->toDateString(),
            'status' => $job->status,
        ])->assertSessionHasErrors('predecessor_job_id');
    }

    public function test_shift_preview_lists_downstream_jobs_with_new_dates(): void
    {
        [, $foundation, $framing, $roofing] = $this->chain();

        // Foundation slips 3 days: Sep 1 -> Sep 4, end Sep 5 -> Sep 8.
        $response = $this->actingAs($this->admin())
            ->getJson('/jobs/'.$foundation->id.'/shift-preview?scheduled_date=2026-09-04&duration_days=5')
            ->assertOk()
            ->json();

        $this->assertSame(3, $response['delta_days']);
        $this->assertCount(2, $response['affected']);
        $this->assertSame([$framing->id, $roofing->id], array_column($response['affected'], 'id'));
        $this->assertSame('2026-09-09', $response['affected'][0]['new_date']);
        $this->assertSame('2026-09-19', $response['affected'][1]['new_date']);
    }

    public function test_shift_preview_stops_at_non_shiftable_jobs(): void
    {
        [, $foundation, $framing] = $this->chain();
        $framing->update(['status' => ProjectJob::STATUS_DONE]);

        $response = $this->actingAs($this->admin())
            ->getJson('/jobs/'.$foundation->id.'/shift-preview?scheduled_date=2026-09-04&duration_days=5')
            ->assertOk()
            ->json();

        // Framing is done: it does not move, and roofing beneath it stays too.
        $this->assertSame([], $response['affected']);
    }

    public function test_update_with_shift_successors_moves_the_whole_chain(): void
    {
        [, $foundation, $framing, $roofing] = $this->chain();

        $this->actingAs($this->admin())->put('/jobs/'.$foundation->id, [
            'project_id' => $foundation->project_id,
            'scheduled_date' => '2026-09-04',
            'duration_days' => 5,
            'status' => $foundation->status,
            'shift_successors' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2026-09-04', $foundation->fresh()->scheduled_date->toDateString());
        $this->assertSame('2026-09-09', $framing->fresh()->scheduled_date->toDateString());
        $this->assertSame('2026-09-19', $roofing->fresh()->scheduled_date->toDateString());
    }

    public function test_duration_change_alone_also_shifts_successors(): void
    {
        [, $foundation, $framing] = $this->chain();

        // Same start, two extra days of curing: end moves +2.
        $this->actingAs($this->admin())->put('/jobs/'.$foundation->id, [
            'project_id' => $foundation->project_id,
            'scheduled_date' => '2026-09-01',
            'duration_days' => 7,
            'status' => $foundation->status,
            'shift_successors' => true,
        ])->assertRedirect();

        $this->assertSame('2026-09-08', $framing->fresh()->scheduled_date->toDateString());
    }

    public function test_update_without_shift_successors_moves_only_the_job(): void
    {
        [, $foundation, $framing, $roofing] = $this->chain();

        $this->actingAs($this->admin())->put('/jobs/'.$foundation->id, [
            'project_id' => $foundation->project_id,
            'scheduled_date' => '2026-09-04',
            'duration_days' => 5,
            'status' => $foundation->status,
        ])->assertRedirect();

        $this->assertSame('2026-09-06', $framing->fresh()->scheduled_date->toDateString());
        $this->assertSame('2026-09-16', $roofing->fresh()->scheduled_date->toDateString());
    }

    public function test_moving_job_to_another_project_is_blocked_while_it_has_successors(): void
    {
        [, $foundation] = $this->chain();
        $otherProject = Project::factory()->create();

        $this->actingAs($this->admin())->put('/jobs/'.$foundation->id, [
            'project_id' => $otherProject->id,
            'scheduled_date' => $foundation->scheduled_date->toDateString(),
            'duration_days' => $foundation->duration_days,
            'status' => $foundation->status,
        ])->assertSessionHasErrors('project_id');
    }

    public function test_crew_cannot_access_shift_preview(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $job = ProjectJob::factory()->create();

        $this->actingAs($crew)
            ->getJson('/jobs/'.$job->id.'/shift-preview?scheduled_date=2026-09-04&duration_days=1')
            ->assertForbidden();
    }

    public function test_deleting_a_predecessor_detaches_its_successors(): void
    {
        [, $foundation, $framing] = $this->chain();

        $this->actingAs($this->admin())->delete('/jobs/'.$foundation->id)->assertRedirect();

        $this->assertNull($framing->fresh()->predecessor_job_id);
    }

    public function test_crew_index_keeps_multi_day_job_underway(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $job = ProjectJob::factory()->create([
            'title' => 'Long pour',
            'scheduled_date' => Carbon::today()->subDays(2)->toDateString(),
            'duration_days' => 5,
        ]);
        $job->crew()->attach($crew);

        $this->actingAs($crew)->get('/jobs')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('jobs', 1)
                ->where('jobs.0.title', 'Long pour'));
    }
}
