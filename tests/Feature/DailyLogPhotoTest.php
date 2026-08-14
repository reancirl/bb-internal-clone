<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\DailyLogPhoto;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyLogPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(DailyLogPhoto::DISK);
    }

    private function makeLog(User $author): DailyLog
    {
        return DailyLog::create([
            'project_id' => Project::factory()->create()->id,
            'user_id' => $author->id,
            'log_date' => '2026-08-14',
            'notes' => 'Slab poured.',
        ]);
    }

    public function test_author_can_upload_photos_and_renditions_are_stored(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);

        $this->actingAs($crew)
            ->post("/daily-logs/{$log->id}/photos", [
                'photos' => [
                    UploadedFile::fake()->image('site-a.jpg', 3000, 2000),
                    UploadedFile::fake()->image('site-b.png', 800, 600),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $log->photos()->count());
        $photo = $log->photos()->first();
        Storage::disk(DailyLogPhoto::DISK)->assertExists($photo->path);
        Storage::disk(DailyLogPhoto::DISK)->assertExists($photo->thumb_path);
        $this->assertStringEndsWith('.jpg', $photo->path); // re-encoded
    }

    public function test_upload_creates_log_and_photos_in_one_request(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($crew)
            ->post('/daily-logs', [
                'project_id' => $project->id,
                'log_date' => '2026-08-14',
                'notes' => 'Framing complete.',
                'photos' => [UploadedFile::fake()->image('frame.jpg', 1200, 900)],
            ])
            ->assertRedirect();

        $log = DailyLog::first();
        $this->assertSame(1, $log->photos()->count());
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);

        $this->actingAs($crew)
            ->from('/daily-logs')
            ->post("/daily-logs/{$log->id}/photos", [
                'photos' => [UploadedFile::fake()->create('plans.pdf', 500, 'application/pdf')],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, $log->photos()->count());
    }

    public function test_photo_cap_is_enforced(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);
        for ($i = 0; $i < 10; $i++) {
            $log->photos()->create([
                'path' => "daily-log-photos/{$log->id}/p{$i}.jpg",
                'thumb_path' => "daily-log-photos/{$log->id}/p{$i}-thumb.jpg",
                'original_name' => "p{$i}.jpg",
                'size_bytes' => 100,
            ]);
        }

        $this->actingAs($crew)
            ->from('/daily-logs')
            ->post("/daily-logs/{$log->id}/photos", [
                'photos' => [UploadedFile::fake()->image('one-too-many.jpg')],
            ])
            ->assertSessionHasErrors('photos');

        $this->assertSame(10, $log->photos()->count());
    }

    public function test_guests_cannot_fetch_photos(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);
        $photo = $log->photos()->create([
            'path' => 'daily-log-photos/x.jpg',
            'thumb_path' => 'daily-log-photos/x-thumb.jpg',
            'original_name' => 'x.jpg',
            'size_bytes' => 100,
        ]);

        $this->get("/daily-log-photos/{$photo->id}")->assertRedirect('/login');
    }

    public function test_logged_in_users_can_fetch_photos(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $viewer = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);

        $this->actingAs($crew)->post("/daily-logs/{$log->id}/photos", [
            'photos' => [UploadedFile::fake()->image('site.jpg', 600, 400)],
        ]);
        $photo = $log->photos()->first();

        $this->actingAs($viewer)->get("/daily-log-photos/{$photo->id}")->assertOk();
        $this->actingAs($viewer)->get("/daily-log-photos/{$photo->id}/thumb")->assertOk();
    }

    public function test_non_author_cannot_upload_or_delete_photos(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_CREW]);
        $other = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($author);
        $photo = $log->photos()->create([
            'path' => 'daily-log-photos/x.jpg',
            'thumb_path' => 'daily-log-photos/x-thumb.jpg',
            'original_name' => 'x.jpg',
            'size_bytes' => 100,
        ]);

        $this->actingAs($other)
            ->post("/daily-logs/{$log->id}/photos", [
                'photos' => [UploadedFile::fake()->image('sneaky.jpg')],
            ])
            ->assertForbidden();

        $this->actingAs($other)->delete("/daily-log-photos/{$photo->id}")->assertForbidden();
        $this->assertDatabaseHas('daily_log_photos', ['id' => $photo->id]);
    }

    public function test_deleting_photo_removes_files_from_disk(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);

        $this->actingAs($crew)->post("/daily-logs/{$log->id}/photos", [
            'photos' => [UploadedFile::fake()->image('gone.jpg', 600, 400)],
        ]);
        $photo = $log->photos()->first();

        $this->actingAs($crew)->delete("/daily-log-photos/{$photo->id}")->assertRedirect();

        Storage::disk(DailyLogPhoto::DISK)->assertMissing($photo->path);
        Storage::disk(DailyLogPhoto::DISK)->assertMissing($photo->thumb_path);
        $this->assertDatabaseMissing('daily_log_photos', ['id' => $photo->id]);
    }

    public function test_deleting_log_removes_photo_files_too(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $log = $this->makeLog($crew);

        $this->actingAs($crew)->post("/daily-logs/{$log->id}/photos", [
            'photos' => [UploadedFile::fake()->image('orphan.jpg', 600, 400)],
        ]);
        $photo = $log->photos()->first();

        $this->actingAs($crew)->delete("/daily-logs/{$log->id}")->assertRedirect();

        Storage::disk(DailyLogPhoto::DISK)->assertMissing($photo->path);
        Storage::disk(DailyLogPhoto::DISK)->assertMissing($photo->thumb_path);
        $this->assertSame(0, DailyLogPhoto::count());
    }
}
