<?php

namespace Tests\Feature;

use App\Console\Commands\RemindOpenTimeCards;
use App\Models\Project;
use App\Models\ProjectJob;
use App\Models\TimeCard;
use App\Models\User;
use App\Notifications\ChangeOrderDecided;
use App\Notifications\JobAssigned;
use App\Notifications\LeadReceived;
use App\Notifications\StillClockedIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function leadPayload(): array
    {
        return [
            'firstName' => 'John',
            'lastName' => 'Smith',
            'email' => 'john@example.com',
            'phone' => '307-555-0142',
            'buildLocation' => 'Gillette',
            'createdAt' => now()->toIso8601String(),
        ];
    }

    public function test_new_lead_notifies_admins_but_not_crew_or_opted_out(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $optedOutAdmin = User::factory()->admin()->create(['email_notifications' => false]);
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CREW]), ['leads:create']);
        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        Notification::assertSentTo($admin, LeadReceived::class);
        Notification::assertNotSentTo($optedOutAdmin, LeadReceived::class);
        Notification::assertNotSentTo($crew, LeadReceived::class);
    }

    public function test_job_store_notifies_assigned_crew(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $this->actingAs($admin)->post('/jobs', [
            'project_id' => $project->id,
            'title' => 'Pour slab',
            'scheduled_date' => now()->addDay()->toDateString(),
            'status' => ProjectJob::STATUS_SCHEDULED,
            'crew' => [$crew->id],
        ]);

        Notification::assertSentTo($crew, JobAssigned::class);
        Notification::assertNotSentTo($admin, JobAssigned::class);
    }

    public function test_job_update_notifies_only_newly_added_crew(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['role' => User::ROLE_CREW]);
        $added = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();

        $job = ProjectJob::factory()->create(['project_id' => $project->id]);
        $job->crew()->sync([$existing->id]);

        $this->actingAs($admin)->put("/jobs/{$job->id}", [
            'project_id' => $project->id,
            'title' => $job->title,
            'scheduled_date' => $job->scheduled_date->toDateString(),
            'status' => $job->status,
            'crew' => [$existing->id, $added->id],
        ]);

        Notification::assertSentTo($added, JobAssigned::class);
        Notification::assertNotSentTo($existing, JobAssigned::class);
    }

    public function test_change_order_decision_notifies_other_admins_not_the_actor(): void
    {
        Notification::fake();

        $actor = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $changeOrder = $project->changeOrders()->create(['number' => 1, 'title' => 'Upgrade cabinets', 'price_cents' => 500000]);

        $this->actingAs($actor)->post("/change-orders/{$changeOrder->id}/decide", ['status' => 'approved']);

        Notification::assertSentTo($otherAdmin, ChangeOrderDecided::class);
        Notification::assertNotSentTo($actor, ChangeOrderDecided::class);
    }

    public function test_long_open_time_card_reminds_once(): void
    {
        Notification::fake();

        $worker = User::factory()->create(['role' => User::ROLE_CREW]);
        $card = TimeCard::factory()->for($worker)->open()->create([
            'clock_in_at' => now()->subHours(RemindOpenTimeCards::REMIND_AFTER_HOURS + 1),
        ]);

        $this->artisan('bb:remind-open-time-cards')->assertSuccessful();
        Notification::assertSentTo($worker, StillClockedIn::class);
        $this->assertNotNull($card->fresh()->reminder_sent_at);

        // Second run: already stamped, no duplicate nudge.
        Notification::fake();
        $this->artisan('bb:remind-open-time-cards')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_short_or_opted_out_open_cards_are_not_emailed(): void
    {
        Notification::fake();

        $recent = User::factory()->create(['role' => User::ROLE_CREW]);
        TimeCard::factory()->for($recent)->open()->create(['clock_in_at' => now()->subHours(2)]);

        $optedOut = User::factory()->create(['role' => User::ROLE_CREW, 'email_notifications' => false]);
        $optedOutCard = TimeCard::factory()->for($optedOut)->open()->create([
            'clock_in_at' => now()->subHours(RemindOpenTimeCards::REMIND_AFTER_HOURS + 2),
        ]);

        $this->artisan('bb:remind-open-time-cards')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNotNull($optedOutCard->fresh()->reminder_sent_at, 'Opted-out cards are stamped so they are not re-examined');
    }

    public function test_user_can_opt_out_from_profile_settings(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CREW]);
        $this->assertTrue($user->fresh()->email_notifications, 'Notifications default to on');

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'email_notifications' => false,
        ]);

        $this->assertFalse($user->fresh()->email_notifications);
    }
}
