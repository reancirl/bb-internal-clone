<?php

namespace Tests\Feature;

use App\Models\TimeCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clock_in(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CREW]);

        $response = $this->actingAs($user)
            ->post('/time-card/clock-in', ['notes' => 'Foundation pour']);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Clocked in.');
        $this->assertDatabaseHas('time_cards', [
            'user_id' => $user->id,
            'clock_out_at' => null,
            'notes' => 'Foundation pour',
        ]);
    }

    public function test_user_cannot_clock_in_twice(): void
    {
        $user = User::factory()->create();
        TimeCard::create(['user_id' => $user->id, 'clock_in_at' => now()]);

        $response = $this->actingAs($user)->post('/time-card/clock-in');

        $response->assertSessionHas('error');
        $this->assertSame(1, TimeCard::where('user_id', $user->id)->count());
    }

    public function test_user_can_clock_out_and_extra_notes_are_appended(): void
    {
        $user = User::factory()->create();
        $card = TimeCard::create([
            'user_id' => $user->id,
            'clock_in_at' => now()->subHour(),
            'notes' => 'Started at Staebler',
        ]);

        $response = $this->actingAs($user)
            ->post('/time-card/clock-out', ['notes' => 'Wrapped slab work']);

        $response->assertSessionHas('success', 'Clocked out.');
        $card->refresh();
        $this->assertNotNull($card->clock_out_at);
        $this->assertSame("Started at Staebler\nWrapped slab work", $card->notes);
    }

    public function test_clock_out_with_no_open_card_flashes_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/time-card/clock-out');

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('time_cards', 0);
    }

    public function test_non_admin_cannot_view_another_users_cards(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CREW]);
        $other = User::factory()->create();

        TimeCard::create([
            'user_id' => $user->id,
            'clock_in_at' => now()->startOfDay()->addHours(8),
            'clock_out_at' => now()->startOfDay()->addHours(16),
        ]);
        TimeCard::create([
            'user_id' => $other->id,
            'clock_in_at' => now()->startOfDay()->addHours(8),
            'clock_out_at' => now()->startOfDay()->addHours(16),
        ]);

        $response = $this->actingAs($user)->get('/time-card?employee_id='.$other->id);

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->where('viewingUserId', $user->id)
                ->has('cards', 1),
        );
    }

    public function test_admin_can_view_another_users_cards(): void
    {
        $admin = User::factory()->admin()->create();
        $crew = User::factory()->create();

        TimeCard::create([
            'user_id' => $crew->id,
            'clock_in_at' => now()->startOfDay()->addHours(8),
            'clock_out_at' => now()->startOfDay()->addHours(16),
        ]);

        $response = $this->actingAs($admin)->get('/time-card?employee_id='.$crew->id);

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->where('viewingUserId', $crew->id)
                ->has('cards', 1),
        );
    }
}
