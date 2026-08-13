<?php

namespace App\Http\Controllers;

use App\Models\BudgetLineDefinition;
use App\Models\BudgetSection;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetController extends Controller
{
    public function index(Project $project): Response
    {
        $lines = $project->budgetLines()
            ->with('section:id,name,sort_order')
            ->get()
            ->sortBy([
                fn (ProjectBudgetLine $a, ProjectBudgetLine $b) => $a->section->sort_order <=> $b->section->sort_order,
                fn (ProjectBudgetLine $a, ProjectBudgetLine $b) => $a->id <=> $b->id,
            ])
            ->values();

        return Inertia::render('projects/budget', [
            'project' => ['id' => $project->id, 'name' => $project->name, 'client_name' => $project->client_name],
            'sections' => BudgetSection::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'lines' => $lines->map(fn (ProjectBudgetLine $l) => [
                'id' => $l->id,
                'section_id' => $l->budget_section_id,
                'section_name' => $l->section->name,
                'definition_id' => $l->budget_line_definition_id,
                'name' => $l->name,
                'notes' => $l->notes,
                'bid_sub_cents' => $l->bid_sub_cents,
                'actual_sub_cents' => $l->actual_sub_cents,
                'estimated_material_cents' => $l->estimated_material_cents,
                'actual_material_cents' => $l->actual_material_cents,
                'estimated_labor_cents' => $l->estimated_labor_cents,
                'actual_labor_cents' => $l->actual_labor_cents,
                'budgeted_cents' => $l->budgetedCents(),
                'actual_cents' => $l->actualCents(),
                'variance_cents' => $l->varianceCents(),
            ]),
        ]);
    }

    /**
     * Create one budget line per active catalog definition the project does
     * not have yet. Idempotent — same pattern as selections/orders.
     */
    public function generate(Project $project): RedirectResponse
    {
        $existing = $project->budgetLines()->whereNotNull('budget_line_definition_id')->pluck('budget_line_definition_id');

        $definitions = BudgetLineDefinition::query()
            ->where('is_active', true)
            ->whereHas('section', fn ($q) => $q->where('is_active', true))
            ->whereNotIn('id', $existing)
            ->get();

        foreach ($definitions as $def) {
            $project->budgetLines()->create([
                'budget_section_id' => $def->budget_section_id,
                'budget_line_definition_id' => $def->id,
                'name' => $def->name,
            ]);
        }

        $message = $definitions->isEmpty()
            ? 'Budget already up to date with the catalog.'
            : 'Added '.$definitions->count().' budget lines from the catalog.';

        return back()->with('success', $message);
    }

    public function storeLine(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'budget_section_id' => ['required', 'exists:budget_sections,id'],
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $project->budgetLines()->create($data);

        return back()->with('success', 'Budget line added.');
    }

    public function updateLine(Request $request, ProjectBudgetLine $line): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'bid_sub_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_sub_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_material_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_material_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_labor_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_labor_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        // Renaming only makes sense for ad-hoc lines; catalog lines keep the
        // catalog name so reports aggregate consistently.
        if ($line->budget_line_definition_id !== null) {
            unset($data['name']);
        }

        $line->update($data);

        return back()->with('success', 'Budget line updated.');
    }

    public function destroyLine(ProjectBudgetLine $line): RedirectResponse
    {
        $line->delete();

        return back()->with('success', 'Budget line removed.');
    }

    public function export(Project $project): StreamedResponse
    {
        $lines = $project->budgetLines()->with('section:id,name,sort_order')->get()
            ->sortBy(fn (ProjectBudgetLine $l) => [$l->section->sort_order, $l->id]);

        $filename = 'budget-'.\Illuminate\Support\Str::slug($project->name).'.csv';

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Section', 'Budget Item', 'Notes',
                'Sub Bid', 'Sub Actual',
                'Material Estimated', 'Material Actual',
                'Labor Estimated', 'Labor Actual',
                'Budgeted Total', 'Actual Total', 'Variance',
            ]);
            foreach ($lines as $l) {
                fputcsv($out, [
                    $l->section->name,
                    $l->name,
                    $l->notes,
                    self::dollars($l->bid_sub_cents),
                    self::dollars($l->actual_sub_cents),
                    self::dollars($l->estimated_material_cents),
                    self::dollars($l->actual_material_cents),
                    self::dollars($l->estimated_labor_cents),
                    self::dollars($l->actual_labor_cents),
                    self::dollars($l->budgetedCents()),
                    self::dollars($l->actualCents()),
                    self::dollars($l->varianceCents()),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Cross-project budget variance report — first real report on the
     * admin Reports page.
     */
    public function report(): Response
    {
        $projects = Project::query()
            ->whereHas('budgetLines')
            ->with('budgetLines')
            ->orderBy('name')
            ->get()
            ->map(function (Project $p) {
                $budgeted = $p->budgetLines->sum(fn (ProjectBudgetLine $l) => $l->budgetedCents());
                $actual = $p->budgetLines->sum(fn (ProjectBudgetLine $l) => $l->actualCents());

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'client_name' => $p->client_name,
                    'status' => $p->status,
                    'lines_count' => $p->budgetLines->count(),
                    'budgeted_cents' => $budgeted,
                    'actual_cents' => $actual,
                    'variance_cents' => $budgeted - $actual,
                ];
            });

        return Inertia::render('admin/reports/index', [
            'projects' => $projects,
        ]);
    }

    private static function dollars(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
