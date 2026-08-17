<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TimeCard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function timeSummary(Request $request): Response
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('admin/reports/time-summary', [
            'rows' => $this->timeSummaryRows($from, $to),
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    public function timeSummaryExport(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->timeSummaryRows($from, $to);
        $filename = $from === null
            ? 'time-summary-all-time.csv'
            : 'time-summary-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee', 'Role', 'Cards', 'Open Cards', 'Minutes', 'Hours']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['role'],
                    $row['cards_count'],
                    $row['open_cards_count'],
                    $row['total_minutes'],
                    number_format($row['total_minutes'] / 60, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function materialStatus(): Response
    {
        return Inertia::render('admin/reports/material-status', [
            'projects' => $this->materialStatusProjects(),
        ]);
    }

    public function materialStatusExport(): StreamedResponse
    {
        $projects = $this->materialStatusProjects();

        return response()->streamDownload(function () use ($projects) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Project', 'Client', 'Status', 'Category', 'Lines', 'Ordered', 'On Site', 'Outstanding']);
            foreach ($projects as $project) {
                foreach ($project['categories'] as $cat) {
                    fputcsv($out, [
                        $project['name'],
                        $project['client_name'],
                        $project['status'],
                        $cat['category'],
                        $cat['total'],
                        $cat['ordered'],
                        $cat['on_site'],
                        $cat['outstanding'],
                    ]);
                }
            }
            fclose($out);
        }, 'material-status.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Both null means no date restriction (all time).
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function range(Request $request): array
    {
        if ($request->query('range') === 'all') {
            return [null, null];
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : Carbon::now()->startOfWeek();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : Carbon::now()->endOfWeek();

        return [$from, $to];
    }

    /**
     * Hours per employee in the range. Open cards (still clocked in) are
     * counted separately and excluded from the minute totals.
     *
     * @return Collection<int, array{user_id: int, name: string, role: string, cards_count: int, open_cards_count: int, total_minutes: int}>
     */
    private function timeSummaryRows(?Carbon $from, ?Carbon $to): Collection
    {
        return TimeCard::query()
            ->with('user:id,name,role')
            ->when($from !== null && $to !== null, fn ($q) => $q->whereBetween('clock_in_at', [$from, $to]))
            ->get()
            ->filter(fn (TimeCard $c) => $c->user !== null)
            ->groupBy('user_id')
            ->map(function (Collection $cards) {
                $closed = $cards->filter(fn (TimeCard $c) => ! $c->isOpen());

                return [
                    'user_id' => $cards->first()->user_id,
                    'name' => $cards->first()->user->name,
                    'role' => $cards->first()->user->role,
                    'cards_count' => $cards->count(),
                    'open_cards_count' => $cards->count() - $closed->count(),
                    'total_minutes' => (int) $closed->sum(fn (TimeCard $c) => $c->durationMinutes()),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Ordered / on-site / outstanding takeoff line counts per project and
     * category. Outstanding = not yet ordered.
     *
     * @return Collection<int, array{id: int, name: string, client_name: string|null, status: string, categories: list<array{category: string, total: int, ordered: int, on_site: int, outstanding: int}>, totals: array{total: int, ordered: int, on_site: int, outstanding: int}}>
     */
    private function materialStatusProjects(): Collection
    {
        return Project::query()
            ->whereHas('takeoffLines')
            ->with('takeoffLines:id,project_id,category,ordered,on_site,sort')
            ->orderBy('name')
            ->get()
            ->map(function (Project $project) {
                $categories = $project->takeoffLines
                    ->sortBy('sort')
                    ->groupBy(fn ($line) => $line->category ?? 'Uncategorized')
                    ->map(fn (Collection $lines, string $category) => [
                        'category' => $category,
                        'total' => $lines->count(),
                        'ordered' => $lines->where('ordered', true)->count(),
                        'on_site' => $lines->where('on_site', true)->count(),
                        'outstanding' => $lines->where('ordered', false)->count(),
                    ])
                    ->values();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_name' => $project->client_name,
                    'status' => $project->status,
                    'categories' => $categories->all(),
                    'totals' => [
                        'total' => $categories->sum('total'),
                        'ordered' => $categories->sum('ordered'),
                        'on_site' => $categories->sum('on_site'),
                        'outstanding' => $categories->sum('outstanding'),
                    ],
                ];
            })
            ->values();
    }
}
