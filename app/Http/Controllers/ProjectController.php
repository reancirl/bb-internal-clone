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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
        $project->load(['takeoffLines.supplier:id,name']);
        $evaluator = new FormulaEvaluator;
        $dimensions = $project->dimensionValues();

        $lines = $project->takeoffLines->map(function (TakeoffLine $line) use ($evaluator, $dimensions) {
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
            $computed = $base === null ? null : round($base * (1 + $waste / 100), 2);

            return [
                'id' => $line->id,
                'category' => $line->category,
                'item' => $line->item,
                'formula' => $line->formula,
                'unit' => $line->unit,
                'waste_pct' => $line->waste_pct,
                'price_item_id' => $line->price_item_id,
                'base_qty' => $base === null ? null : round($base, 2),
                'computed_qty' => $computed,
                'formula_error' => $error,
                'supplier_id' => $line->supplier_id,
                'supplier_name' => $line->supplier?->name,
                'ordered' => $line->ordered,
                'on_site' => $line->on_site,
                'notes' => $line->notes,
            ];
        });

        return Inertia::render('projects/show', [
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
