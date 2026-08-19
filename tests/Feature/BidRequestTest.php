<?php

namespace Tests\Feature;

use App\Models\BidRequest;
use App\Models\BidResponse;
use App\Models\BudgetSection;
use App\Models\Project;
use App\Models\TradePartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidRequestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * A draft request on its own project with one invited partner, so
     * transition tests can start from a valid open-able state.
     */
    private function makeBidRequest(array $attributes = []): BidRequest
    {
        $project = Project::factory()->create();

        return $project->bidRequests()->create([
            'title' => 'Excavation & grading',
            'trade' => 'Excavation',
            ...$attributes,
        ]);
    }

    private function invite(BidRequest $bid, string $name = 'Buchanan Excavating LLC'): BidResponse
    {
        $partner = TradePartner::factory()->create(['name' => $name]);

        return $bid->responses()->create([
            'trade_partner_id' => $partner->id,
            'trade_partner_name' => $partner->name,
            'invited_at' => now(),
        ]);
    }

    public function test_crew_cannot_access_bids(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);
        $project = Project::factory()->create();
        $bid = $this->makeBidRequest();

        $this->actingAs($crew)->get('/admin/bids')->assertForbidden();
        $this->actingAs($crew)->post("/admin/projects/{$project->id}/bids")->assertForbidden();
        $this->actingAs($crew)->get("/admin/bids/{$bid->id}")->assertForbidden();
        $this->actingAs($crew)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open'])->assertForbidden();
    }

    public function test_store_creates_draft_with_creator(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/projects/{$project->id}/bids", [
                'title' => 'Framing — full scope',
                'trade' => 'Framing/General Construction',
                'scope_description' => 'Frame per plan set dated 8/1.',
                'due_date' => '2026-09-15',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $bid = $project->bidRequests()->sole();
        $this->assertSame(BidRequest::STATUS_DRAFT, $bid->status);
        $this->assertSame('Framing — full scope', $bid->title);
        $this->assertSame($admin->id, $bid->created_by_user_id);
    }

    public function test_off_list_trade_is_rejected(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin())
            ->from('/admin/bids')
            ->post("/admin/projects/{$project->id}/bids", ['title' => 'Roof', 'trade' => 'Basket Weaving'])
            ->assertSessionHasErrors('trade');
    }

    public function test_inviting_a_partner_creates_an_invited_response_and_duplicates_are_rejected(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $partner = TradePartner::factory()->create(['name' => 'Big Horn Grading']);

        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/responses", ['trade_partner_id' => $partner->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $response = $bid->responses()->sole();
        $this->assertSame(BidResponse::STATUS_INVITED, $response->status);
        $this->assertSame('Big Horn Grading', $response->trade_partner_name);
        $this->assertNotNull($response->invited_at);

        // Second invite of the same partner is refused with a flash, not a 500.
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/responses", ['trade_partner_id' => $partner->id])
            ->assertSessionHas('error');
        $this->assertSame(1, $bid->responses()->count());
    }

    public function test_open_requires_at_least_one_invited_partner(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();

        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'open'])
            ->assertSessionHas('error');
        $this->assertSame(BidRequest::STATUS_DRAFT, $bid->fresh()->status);

        $this->invite($bid);

        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);
        $bid->refresh();
        $this->assertSame(BidRequest::STATUS_OPEN, $bid->status);
        $this->assertNotNull($bid->opened_at);
    }

    public function test_status_workflow_transitions(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $this->invite($bid);

        // draft → awarded is not allowed
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded'])
            ->assertSessionHas('error');
        $this->assertSame(BidRequest::STATUS_DRAFT, $bid->fresh()->status);

        // draft → canceled is allowed and stamps canceled_at
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'canceled']);
        $bid->refresh();
        $this->assertSame(BidRequest::STATUS_CANCELED, $bid->status);
        $this->assertNotNull($bid->canceled_at);

        // canceled is terminal
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'open'])
            ->assertSessionHas('error');
        $this->assertSame(BidRequest::STATUS_CANCELED, $bid->fresh()->status);
    }

    public function test_open_requests_can_be_canceled_and_invited_late(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $this->invite($bid);
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);

        // Late invite while open is normal.
        $late = TradePartner::factory()->create(['name' => 'Powder River Excavation']);
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/responses", ['trade_partner_id' => $late->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $bid->responses()->count());

        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'canceled']);
        $this->assertSame(BidRequest::STATUS_CANCELED, $bid->fresh()->status);
    }

    public function test_only_drafts_can_be_edited_or_deleted(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $this->invite($bid);
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);

        $this->actingAs($admin)
            ->put("/admin/bids/{$bid->id}", ['title' => 'Sneaky scope change'])
            ->assertSessionHas('error');
        $this->assertSame('Excavation & grading', $bid->fresh()->title);

        $this->actingAs($admin)
            ->delete("/admin/bids/{$bid->id}")
            ->assertSessionHas('error');
        $this->assertNotNull(BidRequest::find($bid->id));
    }

    public function test_draft_delete_cascades_responses(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $this->invite($bid);

        $this->actingAs($admin)->delete("/admin/bids/{$bid->id}")->assertRedirect('/admin/bids');

        $this->assertDatabaseCount('bid_requests', 0);
        $this->assertDatabaseCount('bid_responses', 0);
    }

    public function test_recording_an_amount_flips_status_to_received_and_stamps(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $response = $this->invite($bid);

        $this->actingAs($admin)
            ->put("/admin/bid-responses/{$response->id}", ['amount_cents' => 4120000, 'notes' => 'Includes materials'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $response->refresh();
        $this->assertSame(BidResponse::STATUS_RECEIVED, $response->status);
        $this->assertSame(4120000, $response->amount_cents);
        $this->assertNotNull($response->received_at);

        // Clearing the amount returns the row to awaiting.
        $this->actingAs($admin)->put("/admin/bid-responses/{$response->id}", ['amount_cents' => null]);
        $response->refresh();
        $this->assertSame(BidResponse::STATUS_INVITED, $response->status);
        $this->assertNull($response->received_at);
    }

    public function test_declined_wins_over_amount_and_can_be_undone(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $response = $this->invite($bid);

        $this->actingAs($admin)->put("/admin/bid-responses/{$response->id}", ['amount_cents' => 500000, 'declined' => true]);
        $this->assertSame(BidResponse::STATUS_DECLINED, $response->fresh()->status);

        // Un-declining with an amount still on file goes back to received.
        $this->actingAs($admin)->put("/admin/bid-responses/{$response->id}", ['declined' => false]);
        $this->assertSame(BidResponse::STATUS_RECEIVED, $response->fresh()->status);
    }

    public function test_award_stamps_winner_and_creates_budget_line(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $low = $this->invite($bid);
        $high = $this->invite($bid, 'Yellowstone Dirt Works');
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);
        $this->actingAs($admin)->put("/admin/bid-responses/{$low->id}", ['amount_cents' => 4120000]);
        $this->actingAs($admin)->put("/admin/bid-responses/{$high->id}", ['amount_cents' => 5250000]);

        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded', 'response_id' => $low->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $bid->refresh();
        $this->assertSame(BidRequest::STATUS_AWARDED, $bid->status);
        $this->assertSame($low->id, $bid->awarded_response_id);
        $this->assertNotNull($bid->awarded_at);
        $this->assertNotNull($bid->budget_line_id);
        $this->assertSame('Excavation & grading', $bid->budgetLine->name);
        $this->assertSame('SUB BIDS', $bid->budgetLine->section->name);
        $this->assertSame(4120000, $bid->budgetLine->bid_sub_cents);
    }

    public function test_award_requires_a_received_quote_from_this_request(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $awaiting = $this->invite($bid);
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);

        // A quote that never came in cannot win.
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded', 'response_id' => $awaiting->id])
            ->assertSessionHas('error');
        $this->assertSame(BidRequest::STATUS_OPEN, $bid->fresh()->status);

        // A response smuggled in from another request cannot win either.
        $other = $this->makeBidRequest(['title' => 'Roofing']);
        $foreign = $this->invite($other, 'High Plains Roofing');
        $this->actingAs($admin)->put("/admin/bid-responses/{$foreign->id}", ['amount_cents' => 900000]);

        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded', 'response_id' => $foreign->id])
            ->assertSessionHas('error');
        $this->assertSame(BidRequest::STATUS_OPEN, $bid->fresh()->status);
        $this->assertSame(0, $bid->project->budgetLines()->count());
    }

    public function test_awarded_is_terminal_and_writes_the_budget_line_once(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $response = $this->invite($bid);
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);
        $this->actingAs($admin)->put("/admin/bid-responses/{$response->id}", ['amount_cents' => 4120000]);
        $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded', 'response_id' => $response->id]);

        $lineId = $bid->fresh()->budget_line_id;

        // A retried award is refused by the transition guard and writes nothing new.
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded', 'response_id' => $response->id])
            ->assertSessionHas('error');

        $bid->refresh();
        $this->assertSame($lineId, $bid->budget_line_id);
        $this->assertSame(1, $bid->project->budgetLines()->count());

        // And the whole request is locked: quotes, invites, edits.
        $this->actingAs($admin)
            ->put("/admin/bid-responses/{$response->id}", ['amount_cents' => 1])
            ->assertSessionHas('error');
        $this->assertSame(4120000, $response->fresh()->amount_cents);

        $late = TradePartner::factory()->create();
        $this->actingAs($admin)
            ->post("/admin/bids/{$bid->id}/responses", ['trade_partner_id' => $late->id])
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->delete("/admin/bid-responses/{$response->id}")
            ->assertSessionHas('error');
        $this->assertNotNull(BidResponse::find($response->id));
    }

    public function test_sub_bids_section_is_reused_across_awards(): void
    {
        $admin = $this->admin();

        foreach (['Excavation — Staebler', 'Framing — Staebler'] as $title) {
            $bid = $this->makeBidRequest(['title' => $title]);
            $response = $this->invite($bid, "Partner for {$title}");
            $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'open']);
            $this->actingAs($admin)->put("/admin/bid-responses/{$response->id}", ['amount_cents' => 100000]);
            $this->actingAs($admin)->post("/admin/bids/{$bid->id}/transition", ['status' => 'awarded', 'response_id' => $response->id]);
        }

        $this->assertSame(1, BudgetSection::where('name', 'SUB BIDS')->count());
    }

    public function test_non_awarded_invites_can_be_removed(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $response = $this->invite($bid);

        $this->actingAs($admin)->delete("/admin/bid-responses/{$response->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bid_responses', 0);
    }

    public function test_index_lists_and_filters_by_status_and_trade(): void
    {
        $admin = $this->admin();
        $excavation = $this->makeBidRequest();
        $this->invite($excavation);
        $this->actingAs($admin)->post("/admin/bids/{$excavation->id}/transition", ['status' => 'open']);
        $this->makeBidRequest(['title' => 'Framing', 'trade' => 'Framing/General Construction']);

        $this->actingAs($admin)
            ->get('/admin/bids')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/bids/index')->has('bids.data', 2));

        $this->actingAs($admin)
            ->get('/admin/bids?status=open')
            ->assertInertia(fn ($page) => $page
                ->has('bids.data', 1)
                ->where('bids.data.0.status', 'open')
                ->where('bids.data.0.responses_count', 1)
                ->where('filters.status', 'open'));

        $this->actingAs($admin)
            ->get('/admin/bids?trade=Excavation')
            ->assertInertia(fn ($page) => $page->has('bids.data', 1)->where('bids.data.0.trade', 'Excavation'));

        // Unknown and array filter values are ignored, not echoed back.
        $this->actingAs($admin)
            ->get('/admin/bids?status=bogus&trade[]=x')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bids.data', 2)
                ->where('filters.status', null)
                ->where('filters.trade', null));
    }

    public function test_show_includes_responses_and_partner_picker(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $this->invite($bid);
        TradePartner::factory()->doNotUse()->create(['name' => 'Bad Actor Dirt']);

        $this->actingAs($admin)
            ->get("/admin/bids/{$bid->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/bids/show')
                ->where('bid.title', 'Excavation & grading')
                ->has('responses', 1)
                ->where('responses.0.name', 'Buchanan Excavating LLC')
                ->where('allowedTransitions', ['open', 'canceled'])
                // Do-not-use partners never appear in the invite picker.
                ->where('partners', fn ($partners) => ! collect($partners)->pluck('name')->contains('Bad Actor Dirt')));
    }

    public function test_deleting_a_partner_keeps_the_quote_history(): void
    {
        $admin = $this->admin();
        $bid = $this->makeBidRequest();
        $response = $this->invite($bid);
        $this->actingAs($admin)->put("/admin/bid-responses/{$response->id}", ['amount_cents' => 4120000]);

        $response->tradePartner->delete();

        $response->refresh();
        $this->assertNull($response->trade_partner_id);
        $this->assertSame('Buchanan Excavating LLC', $response->trade_partner_name);
        $this->assertSame(4120000, $response->amount_cents);
    }
}
