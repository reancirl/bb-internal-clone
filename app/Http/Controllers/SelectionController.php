<?php

namespace App\Http\Controllers;

use App\Models\DecisionItem;
use App\Models\Project;
use App\Models\ProjectSelection;
use App\Models\SelectionChoice;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SelectionController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $selections = $project->selections()
            ->with(['item.category', 'choices.vendor:id,name', 'approvedChoice', 'approvedBy:id,name'])
            ->get()
            ->sortBy([
                fn (ProjectSelection $a, ProjectSelection $b) => $a->item->category->sort_order <=> $b->item->category->sort_order,
                fn (ProjectSelection $a, ProjectSelection $b) => $a->item->sort_order <=> $b->item->sort_order,
            ])
            ->values()
            ->map(fn (ProjectSelection $s) => [
                'id' => $s->id,
                'category' => [
                    'id' => $s->item->category->id,
                    'name' => $s->item->category->name,
                    'scope' => $s->item->category->scope,
                ],
                'item' => [
                    'id' => $s->item->id,
                    'label' => $s->item->label,
                    'recommended' => $s->item->recommended,
                    'guidance' => $s->item->guidance,
                ],
                'allowance_cents' => $s->allowance_cents,
                'deadline_date' => $s->deadline_date?->toDateString(),
                'notes' => $s->notes,
                'approved_choice_id' => $s->approved_choice_id,
                'approved_at' => $s->approved_at?->toISOString(),
                'approved_by' => $s->approvedBy?->name,
                'approval_comment' => $s->approval_comment,
                'variance_cents' => $s->varianceCents(),
                'choices' => $s->choices->map(fn (SelectionChoice $c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'description' => $c->description,
                    'price_cents' => $c->price_cents,
                    'vendor_id' => $c->vendor_id,
                    'vendor_name' => $c->vendor?->name,
                ]),
            ]);

        return Inertia::render('projects/selections', [
            'project' => ['id' => $project->id, 'name' => $project->name, 'client_name' => $project->client_name],
            'selections' => $selections,
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * Create one selection per active catalog item that this project does not
     * have yet. Idempotent — mirrors the "generate from catalog" pattern.
     */
    public function generate(Project $project): RedirectResponse
    {
        $existing = $project->selections()->pluck('decision_item_id');

        $items = DecisionItem::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->whereNotIn('id', $existing)
            ->get();

        foreach ($items as $item) {
            $project->selections()->create(['decision_item_id' => $item->id]);
        }

        $message = $items->isEmpty()
            ? 'Selections already up to date with the catalog.'
            : 'Added '.$items->count().' selections from the catalog.';

        return back()->with('success', $message);
    }

    public function update(Request $request, ProjectSelection $selection): RedirectResponse
    {
        $data = $request->validate([
            'allowance_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'deadline_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $selection->update($data);

        return back()->with('success', 'Selection updated.');
    }

    public function destroy(ProjectSelection $selection): RedirectResponse
    {
        $selection->delete();

        return back()->with('success', 'Selection removed.');
    }

    public function storeChoice(Request $request, ProjectSelection $selection): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);

        $selection->choices()->create($data);

        return back()->with('success', 'Choice added.');
    }

    public function updateChoice(Request $request, SelectionChoice $choice): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'vendor_id' => ['sometimes', 'nullable', 'exists:vendors,id'],
        ]);

        $choice->update($data);

        return back()->with('success', 'Choice updated.');
    }

    public function destroyChoice(SelectionChoice $choice): RedirectResponse
    {
        // approved_choice_id is nulled by the FK, but clear the approval
        // metadata too — an approval without its choice is meaningless.
        $selection = $choice->selection;
        $wasApproved = $selection->approved_choice_id === $choice->id;

        $choice->delete();

        if ($wasApproved) {
            $selection->update([
                'approved_choice_id' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'approval_comment' => null,
            ]);
        }

        return back()->with('success', 'Choice removed.');
    }

    /**
     * Approve a choice on behalf of the customer — Buildertrend's builder-side
     * approval flow. Approving a different choice re-approves; approving the
     * currently approved choice un-approves it.
     */
    public function approve(Request $request, ProjectSelection $selection): RedirectResponse
    {
        $data = $request->validate([
            'choice_id' => ['required', 'integer'],
            'comment' => ['nullable', 'string'],
        ]);

        if ($selection->approved_choice_id === (int) $data['choice_id']) {
            $selection->update([
                'approved_choice_id' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'approval_comment' => null,
            ]);

            return back()->with('success', 'Approval removed.');
        }

        $choice = $selection->choices()->findOrFail($data['choice_id']);

        $selection->update([
            'approved_choice_id' => $choice->id,
            'approved_at' => now(),
            'approved_by_user_id' => $request->user()->id,
            'approval_comment' => $data['comment'] ?? null,
        ]);

        return back()->with('success', '"'.$choice->label.'" approved.');
    }
}
