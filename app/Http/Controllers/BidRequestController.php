<?php

namespace App\Http\Controllers;

use App\Models\BidRequest;
use App\Models\BidResponse;
use App\Models\BudgetSection;
use App\Models\Project;
use App\Models\TradePartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BidRequestController extends Controller
{
    public function index(Request $request): Response
    {
        // Non-string (array) params and unknown values are treated as "no
        // filter" so the page never claims a filter it did not apply.
        $rawStatus = $request->query('status');
        $status = is_string($rawStatus) && in_array($rawStatus, BidRequest::STATUSES, true) ? $rawStatus : null;

        $rawTrade = $request->query('trade');
        $trade = is_string($rawTrade) && in_array($rawTrade, TradePartner::TRADES, true) ? $rawTrade : null;

        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 20, 30], true)) {
            $perPage = 10;
        }

        $bids = BidRequest::query()
            ->with('project:id,name,client_name')
            ->withCount([
                'responses',
                'responses as received_count' => fn ($q) => $q->where('status', BidResponse::STATUS_RECEIVED),
            ])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($trade !== null, fn ($q) => $q->where('trade', $trade))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (BidRequest $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'trade' => $b->trade,
                'status' => $b->status,
                'due_date' => $b->due_date?->toDateString(),
                'overdue' => $b->status === BidRequest::STATUS_OPEN && $b->due_date !== null && $b->due_date->lt(today()),
                'project_id' => $b->project_id,
                'project_name' => $b->project?->name,
                'client_name' => $b->project?->client_name,
                'responses_count' => $b->responses_count,
                'received_count' => $b->received_count,
                'created_at' => $b->created_at->toDateString(),
            ]);

        return Inertia::render('admin/bids/index', [
            'bids' => $bids,
            'filters' => ['status' => $status, 'trade' => $trade, 'per_page' => $perPage],
            'statuses' => BidRequest::STATUSES,
            'trades' => TradePartner::TRADES,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'client_name']),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'trade' => ['nullable', Rule::in(TradePartner::TRADES)],
            'scope_description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $bid = $project->bidRequests()->create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.bids.show', $bid)->with('success', 'Bid request created — invite trade partners, then open it for quotes.');
    }

    public function show(BidRequest $bidRequest): Response
    {
        $bidRequest->load(['project:id,name,client_name', 'responses.tradePartner:id,name,phone,email', 'createdBy:id,name']);

        return Inertia::render('admin/bids/show', [
            'bid' => [
                'id' => $bidRequest->id,
                'title' => $bidRequest->title,
                'trade' => $bidRequest->trade,
                'scope_description' => $bidRequest->scope_description,
                'due_date' => $bidRequest->due_date?->toDateString(),
                'status' => $bidRequest->status,
                'awarded_response_id' => $bidRequest->awarded_response_id,
                'budget_line_id' => $bidRequest->budget_line_id,
                'opened_at' => $bidRequest->opened_at?->toISOString(),
                'awarded_at' => $bidRequest->awarded_at?->toISOString(),
                'canceled_at' => $bidRequest->canceled_at?->toISOString(),
                'created_at' => $bidRequest->created_at->toISOString(),
                'created_by' => $bidRequest->createdBy?->name,
                'project' => $bidRequest->project?->only(['id', 'name', 'client_name']),
            ],
            'responses' => $bidRequest->responses->map(fn (BidResponse $r) => [
                'id' => $r->id,
                'trade_partner_id' => $r->trade_partner_id,
                'name' => $r->trade_partner_name,
                'phone' => $r->tradePartner?->phone,
                'email' => $r->tradePartner?->email,
                'status' => $r->status,
                'amount_cents' => $r->amount_cents,
                'notes' => $r->notes,
                'received_at' => $r->received_at?->toISOString(),
            ])->values(),
            // Active partners for the invite picker; the page pre-filters by
            // the request's trade and hides already-invited partners.
            'partners' => TradePartner::query()
                ->where('do_not_use', false)
                ->with('trades:id,trade_partner_id,trade')
                ->orderBy('name')
                ->get()
                ->map(fn (TradePartner $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'phone' => $p->phone,
                    'trades' => $p->trades->pluck('trade')->values(),
                ]),
            'trades' => TradePartner::TRADES,
            'allowedTransitions' => BidRequest::TRANSITIONS[$bidRequest->status] ?? [],
        ]);
    }

    public function update(Request $request, BidRequest $bidRequest): RedirectResponse
    {
        // Open requests are being priced by subs — the scope is locked; cancel
        // and re-issue instead of editing it out from under their numbers.
        if (! $bidRequest->isDraft()) {
            return back()->with('error', 'Only draft bid requests can be edited.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'trade' => ['sometimes', 'nullable', Rule::in(TradePartner::TRADES)],
            'scope_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $bidRequest->update($data);

        return back()->with('success', 'Bid request updated.');
    }

    public function transition(Request $request, BidRequest $bidRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(BidRequest::STATUSES)],
            'response_id' => ['nullable', 'integer'],
        ]);

        if (! $bidRequest->canTransitionTo($data['status'])) {
            return back()->with('error', "Cannot move a {$bidRequest->status} bid request to {$data['status']}.");
        }

        if ($data['status'] === BidRequest::STATUS_OPEN && $bidRequest->responses()->doesntExist()) {
            return back()->with('error', 'Invite at least one trade partner before opening the request.');
        }

        if ($data['status'] === BidRequest::STATUS_AWARDED) {
            return $this->award($bidRequest, $data['response_id'] ?? null);
        }

        $bidRequest->update(array_merge(['status' => $data['status']], match ($data['status']) {
            BidRequest::STATUS_OPEN => ['opened_at' => now()],
            BidRequest::STATUS_CANCELED => ['canceled_at' => now()],
            default => [],
        }));

        return back()->with('success', "Bid request marked {$data['status']}.");
    }

    public function destroy(BidRequest $bidRequest): RedirectResponse
    {
        if (! $bidRequest->isDraft()) {
            return back()->with('error', 'Only draft bid requests can be deleted — cancel instead to keep the record.');
        }

        $bidRequest->delete();

        return redirect()->route('admin.bids.index')->with('success', 'Draft bid request deleted.');
    }

    public function storeResponse(Request $request, BidRequest $bidRequest): RedirectResponse
    {
        // Late invites while open are normal; awarded/canceled requests are history.
        if (! in_array($bidRequest->status, [BidRequest::STATUS_DRAFT, BidRequest::STATUS_OPEN], true)) {
            return back()->with('error', 'Partners can only be invited while the request is draft or open.');
        }

        $data = $request->validate([
            'trade_partner_id' => ['required', Rule::exists('trade_partners', 'id')],
        ]);

        $partner = TradePartner::findOrFail($data['trade_partner_id']);

        if ($bidRequest->responses()->where('trade_partner_id', $partner->id)->exists()) {
            return back()->with('error', "{$partner->name} has already been invited to this request.");
        }

        $bidRequest->responses()->create([
            'trade_partner_id' => $partner->id,
            'trade_partner_name' => $partner->name,
            'status' => BidResponse::STATUS_INVITED,
            'invited_at' => now(),
        ]);

        return back()->with('success', "{$partner->name} invited.");
    }

    public function updateResponse(Request $request, BidResponse $response): RedirectResponse
    {
        if ($response->bidRequest->status === BidRequest::STATUS_AWARDED) {
            return back()->with('error', 'Quotes are locked once the request is awarded.');
        }

        $data = $request->validate([
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'declined' => ['sometimes', 'boolean'],
        ]);

        $response->fill(array_intersect_key($data, array_flip(['amount_cents', 'notes'])));

        // Status is derived, never sent directly: declined wins, an amount
        // means the quote came in, otherwise we are still waiting.
        $declined = array_key_exists('declined', $data)
            ? (bool) $data['declined']
            : $response->status === BidResponse::STATUS_DECLINED;

        if ($declined) {
            $response->status = BidResponse::STATUS_DECLINED;
        } elseif ($response->amount_cents !== null) {
            $response->status = BidResponse::STATUS_RECEIVED;
            $response->received_at ??= now();
        } else {
            $response->status = BidResponse::STATUS_INVITED;
            $response->received_at = null;
        }

        $response->save();

        return back()->with('success', 'Quote updated.');
    }

    public function destroyResponse(BidResponse $response): RedirectResponse
    {
        if ($response->bidRequest->awarded_response_id === $response->id) {
            return back()->with('error', 'The awarded quote is part of the record and cannot be removed.');
        }

        $response->delete();

        return back()->with('success', 'Invitation removed.');
    }

    /**
     * Award one received quote: stamp the winner and create the committed
     * cost line in the budget's SUB BIDS section (mirrors the Change Order
     * approval flow; budget_line_id guards against a double write).
     */
    private function award(BidRequest $bidRequest, ?int $responseId): RedirectResponse
    {
        $response = $bidRequest->responses()->find($responseId);
        if ($response === null) {
            return back()->with('error', 'Pick which quote wins before awarding.');
        }

        if (! $response->isReceived() || $response->amount_cents === null) {
            return back()->with('error', 'Only a received quote with an amount can be awarded.');
        }

        DB::transaction(function () use ($bidRequest, $response) {
            $bidRequest->update([
                'status' => BidRequest::STATUS_AWARDED,
                'awarded_at' => now(),
                'awarded_response_id' => $response->id,
            ]);

            if ($bidRequest->budget_line_id === null) {
                $section = BudgetSection::firstOrCreate(
                    ['name' => 'SUB BIDS'],
                    ['sort_order' => BudgetSection::max('sort_order') + 1],
                );

                $line = $bidRequest->project->budgetLines()->create([
                    'budget_section_id' => $section->id,
                    'name' => $bidRequest->title,
                    'bid_sub_cents' => $response->amount_cents,
                ]);
                $bidRequest->update(['budget_line_id' => $line->id]);
            }
        });

        $amount = '$'.number_format($response->amount_cents / 100, 2);

        return back()->with('success', "Awarded to {$response->trade_partner_name} for {$amount} — budget line added to SUB BIDS.");
    }
}
