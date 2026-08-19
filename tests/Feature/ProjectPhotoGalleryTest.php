<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\Project;
use App\Models\User;
use App\Support\DailyLogPhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectPhotoGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeLog(Project $project, User $user, string $date = '2026-08-10'): DailyLog
    {
        return DailyLog::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'log_date' => $date,
            'notes' => 'Test log',
        ]);
    }

    private function attachPhoto(DailyLog $log): void
    {
        DailyLogPhotoStorage::attach($log, [UploadedFile::fake()->image('site.jpg', 800, 600)]);
    }

    public function test_gallery_lists_only_this_projects_photos_grouped_by_month(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $other = Project::factory()->create();

        $this->attachPhoto($this->makeLog($project, $crew, '2026-08-10'));
        $this->attachPhoto($this->makeLog($project, $crew, '2026-07-02'));
        $this->attachPhoto($this->makeLog($other, $crew, '2026-08-10'));

        $this->actingAs($crew)->get("/projects/{$project->id}/photos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('projects/photos')
                ->has('photos', 2)
                ->where('photos.0.month_label', 'August 2026')
                ->where('photos.1.month_label', 'July 2026')
                ->where('photos.0.uploader', $crew->name));
    }

    public function test_upload_reuses_todays_own_log(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $todaysLog = $this->makeLog($project, $crew, today()->toDateString());

        $this->actingAs($crew)->post("/projects/{$project->id}/photos", [
            'photos' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $todaysLog->photos()->count());
        $this->assertSame(1, DailyLog::count());
    }

    public function test_upload_creates_a_log_when_none_exists_today(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->post("/projects/{$project->id}/photos", [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = DailyLog::first();
        $this->assertNotNull($log);
        $this->assertSame($project->id, $log->project_id);
        $this->assertSame($crew->id, $log->user_id);
        $this->assertSame(today()->toDateString(), $log->log_date->toDateString());
        $this->assertSame(2, $log->photos()->count());
    }

    public function test_upload_overflows_to_a_fresh_log_when_todays_is_full(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $full = $this->makeLog($project, $crew, today()->toDateString());
        DailyLogPhotoStorage::attach(
            $full,
            array_map(fn () => UploadedFile::fake()->image('x.jpg'), range(1, DailyLogPhotoStorage::MAX_PHOTOS_PER_LOG)),
        );

        $this->actingAs($crew)->post("/projects/{$project->id}/photos", [
            'photos' => [UploadedFile::fake()->image('overflow.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, DailyLog::count());
        $this->assertSame(DailyLogPhotoStorage::MAX_PHOTOS_PER_LOG, $full->photos()->count());
        $this->assertSame(1, DailyLog::orderByDesc('id')->first()->photos()->count());
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)->post("/projects/{$project->id}/photos", [
            'photos' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        $this->assertSame(0, DailyLog::count());
    }

    public function test_can_delete_flag_follows_log_ownership(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_CREW]);
        $other = User::factory()->create(['role' => User::ROLE_CREW]);
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->attachPhoto($this->makeLog($project, $author));

        $this->actingAs($author)->get("/projects/{$project->id}/photos")
            ->assertInertia(fn ($page) => $page->where('photos.0.can_delete', true));
        $this->actingAs($other)->get("/projects/{$project->id}/photos")
            ->assertInertia(fn ($page) => $page->where('photos.0.can_delete', false));
        $this->actingAs($admin)->get("/projects/{$project->id}/photos")
            ->assertInertia(fn ($page) => $page->where('photos.0.can_delete', true));
    }

    public function test_guests_cannot_view_the_gallery(): void
    {
        $project = Project::factory()->create();

        $this->get("/projects/{$project->id}/photos")->assertRedirect('/login');
    }
}
