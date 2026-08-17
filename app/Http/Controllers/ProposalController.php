<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectProposal;
use App\Support\TakeoffCosting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $proposals = ProjectProposal::query()
            ->with('project:id,name,client_name')
            ->when(in_array($status, ProjectProposal::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProjectProposal $p) => [
                'id' => $p->id,
                'number' => $p->number,
                'title' => $p->title,
                'status' => $p->status,
                'total_cents' => $p->total_cents,
                'valid_until' => $p->valid_until?->toDateString(),
                'project_id' => $p->project_id,
                'project_name' => $p->project?->name,
                'client_name' => $p->project?->client_name,
                'created_at' => $p->created_at->toDateString(),
            ]);

        return Inertia::render('admin/proposals/index', [
            'proposals' => $proposals,
            'filters' => ['status' => $status ?: null],
            'statuses' => ProjectProposal::STATUSES,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'client_name']),
        ]);
    }

    /**
     * Snapshot the project's current takeoff estimate into a draft proposal.
     * Later price-book or takeoff edits never rewrite an existing proposal.
     */
    public function store(Project $project, Request $request): RedirectResponse
    {
        $project->load('takeoffLines.priceItem:id,fast_price,material_cost');
        $costing = new TakeoffCosting;
        $dimensions = $project->dimensionValues();

        $proposal = DB::transaction(function () use ($project, $request, $costing, $dimensions) {
            $proposal = $project->proposals()->create([
                'number' => ProjectProposal::nextNumber((int) now()->format('Y')),
                'status' => ProjectProposal::STATUS_DRAFT,
                'valid_until' => now()->addDays(30)->toDateString(),
                'created_by_user_id' => $request->user()->id,
            ]);

            $total = 0;
            $sort = 1;
            foreach ($project->takeoffLines as $line) {
                $calc = $costing->computeLine($line, $dimensions);
                if ($calc['qty'] === null && $calc['cost'] === null) {
                    continue; // nothing measurable to put in front of a customer
                }

                $lineCents = TakeoffCosting::toCents($calc['cost']);
                $total += $lineCents ?? 0;

                $proposal->lines()->create([
                    'category' => $line->category,
                    'item' => $line->item,
                    'qty' => $calc['qty'],
                    'unit' => $line->unit,
                    'unit_price_cents' => TakeoffCosting::toCents($calc['unit_price']),
                    'total_cents' => $lineCents,
                    'sort' => $sort++,
                ]);
            }

            $proposal->update(['total_cents' => $total]);

            return $proposal;
        });

        return redirect()
            ->route('admin.proposals.show', $proposal)
            ->with('success', "Proposal {$proposal->number} created from the current estimate.");
    }

    public function show(ProjectProposal $proposal): Response
    {
        $proposal->load(['project:id,name,client_name,address', 'createdBy:id,name']);

        return Inertia::render('admin/proposals/show', [
            'proposal' => [
                'id' => $proposal->id,
                'number' => $proposal->number,
                'title' => $proposal->title,
                'status' => $proposal->status,
                'total_cents' => $proposal->total_cents,
                'payment_terms' => $proposal->payment_terms,
                'notes' => $proposal->notes,
                'valid_until' => $proposal->valid_until?->toDateString(),
                'sent_at' => $proposal->sent_at?->toISOString(),
                'accepted_at' => $proposal->accepted_at?->toISOString(),
                'rejected_at' => $proposal->rejected_at?->toISOString(),
                'created_at' => $proposal->created_at->toISOString(),
                'created_by' => $proposal->createdBy?->name,
                'project' => $proposal->project?->only(['id', 'name', 'client_name', 'address']),
            ],
            'lines' => $proposal->lines()->orderBy('sort')->get()->map(fn ($l) => [
                'id' => $l->id,
                'category' => $l->category,
                'item' => $l->item,
                'qty' => $l->qty,
                'unit' => $l->unit,
                'unit_price_cents' => $l->unit_price_cents,
                'total_cents' => $l->total_cents,
            ]),
            'allowedTransitions' => ProjectProposal::TRANSITIONS[$proposal->status] ?? [],
        ]);
    }

    public function update(Request $request, ProjectProposal $proposal): RedirectResponse
    {
        if (! $proposal->isDraft()) {
            return back()->with('error', 'Only draft proposals can be edited.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
        ]);

        $proposal->update($data);

        return back()->with('success', 'Proposal updated.');
    }

    public function transition(Request $request, ProjectProposal $proposal): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ProjectProposal::STATUSES)],
        ]);

        if (! $proposal->canTransitionTo($data['status'])) {
            return back()->with('error', "Cannot move a {$proposal->status} proposal to {$data['status']}.");
        }

        $proposal->update(array_merge(['status' => $data['status']], match ($data['status']) {
            ProjectProposal::STATUS_SENT => ['sent_at' => now(), 'rejected_at' => null],
            ProjectProposal::STATUS_ACCEPTED => ['accepted_at' => now()],
            ProjectProposal::STATUS_REJECTED => ['rejected_at' => now()],
            default => [],
        }));

        return back()->with('success', "Proposal marked {$data['status']}.");
    }

    public function destroy(ProjectProposal $proposal): RedirectResponse
    {
        if (! $proposal->isDraft()) {
            return back()->with('error', 'Only draft proposals can be deleted.');
        }

        $proposal->delete();

        return redirect()->route('admin.proposals.index')->with('success', 'Draft proposal deleted.');
    }

    public function pdf(ProjectProposal $proposal): \Illuminate\Http\Response
    {
        $proposal->load(['project:id,name,client_name,address', 'lines' => fn ($q) => $q->orderBy('sort')]);

        return Pdf::loadView('pdf.proposal', ['proposal' => $proposal])
            ->setPaper('letter')
            ->download($proposal->number.'.pdf');
    }
}
