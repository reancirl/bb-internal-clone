<?php

namespace App\Http\Controllers;

use App\Models\PriceItem;
use App\Models\Project;
use App\Models\TakeoffLine;
use App\Models\Vendor;
use App\Support\FormulaEvaluator;
use App\Support\TakeoffTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 20, 30], true)) {
            $perPage = 10;
        }

        $projects = Project::query()
            ->withCount([
                'takeoffLines',
                'takeoffLines as ordered_count' => fn ($q) => $q->where('ordered', true),
                'takeoffLines as on_site_count' => fn ($q) => $q->where('on_site', true),
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'client_name' => $p->client_name,
                'status' => $p->status,
                'lines_count' => $p->takeoff_lines_count,
                'ordered_count' => $p->ordered_count,
                'on_site_count' => $p->on_site_count,
            ]);

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => ['per_page' => $perPage],
            'statuses' => Project::STATUSES,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $project->load(['takeoffLines.supplier:id,name', 'takeoffLines.priceItem:id,fast_price,material_cost']);
        $evaluator = new FormulaEvaluator;
        $dimensions = $project->dimensionValues();

        $lines = $project->takeoffLines->map(function (TakeoffLine $line) use ($evaluator, $dimensions) {
            $calc = $this->computeLine($line, $dimensions, $evaluator);

            return [
                'id' => $line->id,
                'category' => $line->category,
                'item' => $line->item,
                'formula' => $line->formula,
                'unit' => $line->unit,
                'waste_pct' => $line->waste_pct,
                'price_item_id' => $line->price_item_id,
                'base_qty' => $calc['base'],
                'computed_qty' => $calc['qty'],
                'formula_error' => $calc['error'],
                'unit_price' => $calc['unit_price'],
                'line_cost' => $calc['cost'],
                'supplier_id' => $line->supplier_id,
                'supplier_name' => $line->supplier?->name,
                'ordered' => $line->ordered,
                'on_site' => $line->on_site,
                'notes' => $line->notes,
            ];
        });

        return Inertia::render('projects/show', [
            'summary' => $this->costSummary($lines),
            'project' => array_merge(
                ['id' => $project->id, 'name' => $project->name, 'client_name' => $project->client_name, 'address' => $project->address, 'status' => $project->status],
                $project->only(Project::DIMENSION_KEYS),
            ),
            'dimensionFields' => collect(Project::DIMENSIONS)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'value' => $project->{$key},
            ])->values(),
            'lines' => $lines,
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'priceItems' => PriceItem::query()
                ->with(['category:id,code,name', 'preferredVendor:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (PriceItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'unit' => $i->unit,
                    'category' => $i->category?->name,
                    'supplier_id' => $i->preferred_vendor_id,
                ]),
            'statuses' => Project::STATUSES,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * Stream the project takeoff (with computed quantities and costs) as CSV.
     * Available to anyone who can view the project — same gate as show().
     */
    public function export(Project $project): StreamedResponse
    {
        $project->load(['takeoffLines.supplier:id,name', 'takeoffLines.priceItem:id,fast_price,material_cost']);
        $evaluator = new FormulaEvaluator;
        $dimensions = $project->dimensionValues();
        $filename = Str::slug($project->name).'-takeoff.csv';

        return response()->streamDownload(function () use ($project, $evaluator, $dimensions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Category', 'Item', 'Formula', 'Qty', 'Unit', 'Waste %', 'Unit Price', 'Est. Cost', 'Supplier', 'Ordered', 'On Site']);

            $grandTotal = 0.0;
            foreach ($project->takeoffLines as $line) {
                $calc = $this->computeLine($line, $dimensions, $evaluator);
                $grandTotal += $calc['cost'] ?? 0.0;

                fputcsv($out, [
                    $line->category,
                    $line->item,
                    $line->formula,
                    $calc['qty'],
                    $line->unit,
                    $line->waste_pct,
                    $calc['unit_price'],
                    $calc['cost'],
                    $line->supplier?->name,
                    $line->ordered ? 'yes' : 'no',
                    $line->on_site ? 'yes' : 'no',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['', '', '', '', '', '', '', round($grandTotal, 2), 'TOTAL']);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Evaluate one takeoff line: quantity (with waste) and extended cost from
     * the linked price book item. Shared by show() and export() so the screen
     * and the CSV can never disagree.
     *
     * @param  array<string, float>  $dimensions
     * @return array{base: float|null, qty: float|null, error: string|null, unit_price: float|null, cost: float|null}
     */
    private function computeLine(TakeoffLine $line, array $dimensions, FormulaEvaluator $evaluator): array
    {
        $base = null;
        $error = null;

        if ($line->formula !== null && $line->formula !== '') {
            try {
                $base = $evaluator->evaluate($line->formula, $dimensions);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        $waste = (float) $line->waste_pct;
        $qty = $base === null ? null : round($base * (1 + $waste / 100), 2);

        // Use the best unit price we have on file: the quoted "fast price",
        // falling back to material cost when no fast price is set.
        $rawPrice = $line->priceItem?->fast_price ?? $line->priceItem?->material_cost;
        $unitPrice = $rawPrice !== null ? (float) $rawPrice : null;
        $cost = ($qty === null || $unitPrice === null) ? null : round($qty * $unitPrice, 2);

        return [
            'base' => $base === null ? null : round($base, 2),
            'qty' => $qty,
            'error' => $error,
            'unit_price' => $unitPrice,
            'cost' => $cost,
        ];
    }

    /**
     * Roll up per-line costs into category subtotals + a grand total, and count
     * how many priced lines fed the total vs. lines that have a quantity but no
     * price (so the UI can flag incomplete pricing rather than silently undercount).
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{categories: list<array{category: string, total: float}>, grand_total: float, priced_count: int, unpriced_count: int}
     */
    private function costSummary(Collection $lines): array
    {
        $categories = $lines
            ->filter(fn ($l) => $l['line_cost'] !== null)
            ->groupBy(fn ($l) => $l['category'] ?: 'Uncategorized')
            ->map(fn ($group, $category) => [
                'category' => (string) $category,
                'total' => round($group->sum('line_cost'), 2),
            ])
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'grand_total' => round($lines->sum(fn ($l) => $l['line_cost'] ?? 0), 2),
            'priced_count' => $lines->filter(fn ($l) => $l['line_cost'] !== null)->count(),
            'unpriced_count' => $lines->filter(fn ($l) => $l['line_cost'] === null && $l['computed_qty'] !== null)->count(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('projects', 'name')],
            'client_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(Project::STATUSES)],
        ]);

        $project = Project::create($data);

        // Start every project from the standard takeoff template so the user
        // only needs to fill in dimensions, not build the line list by hand.
        $sort = 1;
        foreach (TakeoffTemplate::lines() as $line) {
            $project->takeoffLines()->create([...$line, 'sort' => $sort++]);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Project created with a standard takeoff. Enter your dimensions to calculate quantities.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('projects', 'name')->ignore($project->id)],
            'client_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(Project::STATUSES)],
        ];
        foreach (Project::DIMENSION_KEYS as $key) {
            $rules[$key] = 'nullable|numeric|min:0';
        }

        $project->update($request->validate($rules));

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }
}
