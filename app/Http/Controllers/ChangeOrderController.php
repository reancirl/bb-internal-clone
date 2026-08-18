<?php

namespace App\Http\Controllers;

use App\Models\BudgetSection;
use App\Models\ChangeOrder;
use App\Models\Project;
use App\Notifications\ChangeOrderDecided;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChangeOrderController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $project->changeOrders()->create([
            ...$data,
            'number' => ($project->changeOrders()->max('number') ?? 0) + 1,
        ]);

        return back()->with('success', 'Change order created — pending customer approval.');
    }

    public function update(Request $request, ChangeOrder $changeOrder): RedirectResponse
    {
        // Approved/declined orders are a record of an agreement; revoke the
        // decision first if the terms need to change.
        if ($changeOrder->status !== ChangeOrder::STATUS_PENDING) {
            return back()->withErrors(['title' => 'Only pending change orders can be edited — revert it to pending first.']);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $changeOrder->update($data);

        return back()->with('success', 'Change order updated.');
    }

    /**
     * Record the customer's decision (office acts on their behalf, mirroring
     * the Selections approval flow). Approving creates the cost-tracking
     * line in the budget's CHANGE ORDERS section.
     */
    public function decide(Request $request, ChangeOrder $changeOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ChangeOrder::STATUS_APPROVED, ChangeOrder::STATUS_DECLINED])],
            'comment' => ['nullable', 'string'],
        ]);

        $changeOrder->update([
            'status' => $data['status'],
            'decided_at' => now(),
            'decided_by_user_id' => $request->user()->id,
            'decision_comment' => $data['comment'] ?? null,
        ]);

        if ($data['status'] === ChangeOrder::STATUS_APPROVED && $changeOrder->budget_line_id === null) {
            $section = BudgetSection::firstOrCreate(
                ['name' => 'CHANGE ORDERS'],
                ['sort_order' => BudgetSection::max('sort_order') + 1],
            );

            $line = $changeOrder->project->budgetLines()->create([
                'budget_section_id' => $section->id,
                'name' => $changeOrder->label(),
            ]);
            $changeOrder->update(['budget_line_id' => $line->id]);
        }

        $verb = $data['status'] === ChangeOrder::STATUS_APPROVED ? 'approved' : 'declined';

        Notify::admins(new ChangeOrderDecided($changeOrder->load('project:id,name')), except: $request->user());

        return back()->with('success', $changeOrder->label().' '.$verb.'.');
    }

    /**
     * Revert a decision back to pending. The linked budget line is removed
     * only if no costs were recorded on it yet.
     */
    public function revert(ChangeOrder $changeOrder): RedirectResponse
    {
        $line = $changeOrder->budgetLine;
        if ($line !== null && $line->budgetedCents() === 0 && $line->actualCents() === 0) {
            $changeOrder->update(['budget_line_id' => null]);
            $line->delete();
        }

        $changeOrder->update([
            'status' => ChangeOrder::STATUS_PENDING,
            'decided_at' => null,
            'decided_by_user_id' => null,
            'decision_comment' => null,
        ]);

        return back()->with('success', $changeOrder->label().' reverted to pending.');
    }

    public function destroy(ChangeOrder $changeOrder): RedirectResponse
    {
        if ($changeOrder->status === ChangeOrder::STATUS_APPROVED) {
            return back()->withErrors(['title' => 'Approved change orders are part of the contract — revert the approval before deleting.']);
        }

        $changeOrder->delete();

        return back()->with('success', 'Change order deleted.');
    }

    public function updateContract(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'contract_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $project->update($data);

        return back()->with('success', 'Contract price updated.');
    }
}
