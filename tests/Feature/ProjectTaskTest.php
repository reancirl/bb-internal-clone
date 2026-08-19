<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTaskTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function crew(): User
    {
        return User::factory()->create(['role' => User::ROLE_CREW]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTask(Project $project, User $creator, array $overrides = []): ProjectTask
    {
        return $project->tasks()->create([
            'number' => ProjectTask::nextNumber($project->id),
            'title' => 'Touch up paint',
            'priority' => 'medium',
            'created_by_user_id' => $creator->id,
            ...$overrides,
        ]);
    }

    public function test_crew_can_capture_a_task_and_numbers_increment_per_project(): void
    {
        $crew = $this->crew();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();

        $this->actingAs($crew)->post("/projects/{$project->id}/tasks", [
            'title' => 'Door rubs in master bath',
            'location' => 'Master bath',
            'category' => 'doors',
            'priority' => 'high',
            'is_punch' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($crew)->post("/projects/{$project->id}/tasks", [
            'title' => 'Second item',
            'priority' => 'low',
        ]);
        $this->actingAs($crew)->post("/projects/{$otherProject->id}/tasks", [
            'title' => 'Other project item',
            'priority' => 'low',
        ]);

        $this->assertSame([1, 2], $project->tasks()->orderBy('number')->pluck('number')->all());
        $this->assertSame([1], $otherProject->tasks()->pluck('number')->all());
        $this->assertTrue($project->tasks()->first()->is_punch);
    }

    public function test_assignment_sends_notification_but_not_to_self_or_opted_out(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $assignee = $this->crew();
        $optedOut = User::factory()->create(['role' => User::ROLE_CREW, 'email_notifications' => false]);
        $project = Project::factory()->create();

        $this->actingAs($admin)->post("/projects/{$project->id}/tasks", [
            'title' => 'Fix outlet cover',
            'priority' => 'medium',
            'assigned_to_user_id' => $assignee->id,
        ]);
        Notification::assertSentTo($assignee, TaskAssigned::class);

        $this->actingAs($admin)->post("/projects/{$project->id}/tasks", [
            'title' => 'Self-assigned',
            'priority' => 'medium',
            'assigned_to_user_id' => $admin->id,
        ]);
        Notification::assertNotSentTo($admin, TaskAssigned::class);

        $this->actingAs($admin)->post("/projects/{$project->id}/tasks", [
            'title' => 'To opted out',
            'priority' => 'medium',
            'assigned_to_user_id' => $optedOut->id,
        ]);
        Notification::assertNotSentTo($optedOut, TaskAssigned::class);
    }

    public function test_reassignment_notifies_only_the_new_assignee(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $first = $this->crew();
        $second = $this->crew();
        $project = Project::factory()->create();
        $task = $this->makeTask($project, $admin, ['assigned_to_user_id' => $first->id]);

        $this->actingAs($admin)->put("/tasks/{$task->id}", [
            'title' => $task->title,
            'priority' => $task->priority,
            'assigned_to_user_id' => $second->id,
        ])->assertRedirect();

        Notification::assertSentTo($second, TaskAssigned::class);
        Notification::assertNotSentTo($first, TaskAssigned::class);
    }

    public function test_assignee_can_update_status_but_stranger_cannot(): void
    {
        $creator = $this->crew();
        $assignee = $this->crew();
        $stranger = $this->crew();
        $project = Project::factory()->create();
        $task = $this->makeTask($project, $creator, ['assigned_to_user_id' => $assignee->id]);

        $this->actingAs($stranger)->patch("/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();

        $this->actingAs($assignee)->patch("/tasks/{$task->id}/status", ['status' => 'done'])->assertRedirect();
        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($assignee->id, $task->completed_by_user_id);

        // Reopening clears the completion stamp.
        $this->actingAs($assignee)->patch("/tasks/{$task->id}/status", ['status' => 'open']);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_only_creator_or_admin_can_edit_and_delete(): void
    {
        $creator = $this->crew();
        $assignee = $this->crew();
        $project = Project::factory()->create();
        $task = $this->makeTask($project, $creator, ['assigned_to_user_id' => $assignee->id]);

        // Assignee can work it but not edit or delete it.
        $this->actingAs($assignee)->put("/tasks/{$task->id}", [
            'title' => 'Renamed', 'priority' => 'low',
        ])->assertForbidden();
        $this->actingAs($assignee)->delete("/tasks/{$task->id}")->assertForbidden();

        $this->actingAs($creator)->put("/tasks/{$task->id}", [
            'title' => 'Renamed', 'priority' => 'low',
        ])->assertRedirect();
        $this->assertSame('Renamed', $task->fresh()->title);

        $this->actingAs($this->admin())->delete("/tasks/{$task->id}")->assertRedirect();
        $this->assertDatabaseMissing('project_tasks', ['id' => $task->id]);
    }

    public function test_checklist_syncs_and_toggles(): void
    {
        $creator = $this->crew();
        $project = Project::factory()->create();
        $task = $this->makeTask($project, $creator);

        $this->actingAs($creator)->put("/tasks/{$task->id}", [
            'title' => $task->title,
            'priority' => $task->priority,
            'checklist' => [
                ['label' => 'Sand', 'done' => true],
                ['label' => 'Repaint'],
            ],
        ])->assertRedirect();

        $items = $task->checklistItems()->get();
        $this->assertSame(['Sand', 'Repaint'], $items->pluck('label')->all());
        $this->assertTrue($items[0]->done);
        $this->assertFalse($items[1]->done);

        $this->actingAs($creator)->patch("/tasks/{$task->id}/checklist/{$items[1]->id}")->assertRedirect();
        $this->assertTrue($items[1]->fresh()->done);
    }

    public function test_before_and_after_photos_upload_and_files_clean_up_on_task_delete(): void
    {
        Storage::fake('local');
        $creator = $this->crew();
        $project = Project::factory()->create();
        $task = $this->makeTask($project, $creator);

        $this->actingAs($creator)->post("/tasks/{$task->id}/photos", [
            'stage' => 'before',
            'photos' => [UploadedFile::fake()->image('scuff.jpg', 800, 600)],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($creator)->post("/tasks/{$task->id}/photos", [
            'stage' => 'after',
            'photos' => [UploadedFile::fake()->image('fixed.jpg', 800, 600)],
        ])->assertRedirect();

        $photos = $task->photos()->get();
        $this->assertSame(['before', 'after'], $photos->pluck('stage')->all());
        foreach ($photos as $photo) {
            Storage::disk('local')->assertExists($photo->path);
            Storage::disk('local')->assertExists($photo->thumb_path);
        }

        $this->actingAs($this->admin())->delete("/tasks/{$task->id}");
        foreach ($photos as $photo) {
            Storage::disk('local')->assertMissing($photo->path);
            Storage::disk('local')->assertMissing($photo->thumb_path);
        }
    }

    public function test_invalid_photo_stage_is_rejected(): void
    {
        Storage::fake('local');
        $creator = $this->crew();
        $project = Project::factory()->create();
        $task = $this->makeTask($project, $creator);

        $this->actingAs($creator)->post("/tasks/{$task->id}/photos", [
            'stage' => 'during',
            'photos' => [UploadedFile::fake()->image('x.jpg')],
        ])->assertSessionHasErrors('stage');
    }

    public function test_punch_sign_off_requires_all_punch_items_done(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create();

        // No punch items at all: refused.
        $this->actingAs($admin)->post("/projects/{$project->id}/punch-sign-off");
        $this->assertNull($project->fresh()->punch_signed_off_at);

        $punchOpen = $this->makeTask($project, $admin, ['is_punch' => true]);
        $this->makeTask($project, $admin, ['is_punch' => true, 'status' => 'done']);
        $this->makeTask($project, $admin, ['is_punch' => false]); // ordinary open task must not block

        // One punch item still open: refused.
        $this->actingAs($admin)->post("/projects/{$project->id}/punch-sign-off");
        $this->assertNull($project->fresh()->punch_signed_off_at);

        $punchOpen->update(['status' => 'done']);
        $this->actingAs($admin)->post("/projects/{$project->id}/punch-sign-off")->assertRedirect();
        $this->assertNotNull($project->fresh()->punch_signed_off_at);
    }

    public function test_crew_cannot_sign_off_punch_list(): void
    {
        $crew = $this->crew();
        $project = Project::factory()->create();
        $this->makeTask($project, $crew, ['is_punch' => true, 'status' => 'done']);

        $this->actingAs($crew)->post("/projects/{$project->id}/punch-sign-off")->assertForbidden();
    }

    public function test_guests_are_redirected(): void
    {
        $project = Project::factory()->create();

        $this->get("/projects/{$project->id}/tasks")->assertRedirect('/login');
    }
}
