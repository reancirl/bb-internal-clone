<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /**
     * Crew-facing "my jobs" — the signed-in user's assigned jobs from today on.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        $jobs = $user->jobs()
            ->with('project:id,name')
            ->whereDate('scheduled_date', '>=', $today)
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (ProjectJob $job) => $this->toRow($job));

        return Inertia::render('jobs/index', [
            'today' => $today->toDateString(),
            'jobs' => $jobs,
            'statuses' => ProjectJob::STATUSES,
        ]);
    }

    /**
     * Month calendar of every scheduled job. Everyone reads; admins manage.
     */
    public function calendar(Request $request): Response
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->value())->startOfMonth()
            : Carbon::today()->startOfMonth();

        $jobs = ProjectJob::query()
            ->with(['project:id,name', 'crew:id,name'])
            ->whereBetween('scheduled_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (ProjectJob $job) => $this->toRow($job));

        return Inertia::render('calendar/index', [
            'month' => $month->format('Y-m'),
            'jobs' => $jobs,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'crew' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            'statuses' => ProjectJob::STATUSES,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $job = ProjectJob::create(Arr::except($data, 'crew'));
        $job->crew()->sync($data['crew'] ?? []);

        return back()->with('success', 'Job scheduled.');
    }

    public function update(Request $request, ProjectJob $job): RedirectResponse
    {
        $data = $this->validateData($request);
        $job->update(Arr::except($data, 'crew'));
        $job->crew()->sync($data['crew'] ?? []);

        return back()->with('success', 'Job updated.');
    }

    public function destroy(ProjectJob $job): RedirectResponse
    {
        $job->delete();

        return back()->with('success', 'Job removed.');
    }

    /**
     * Status toggle available to assigned crew (and admins) so field staff can
     * update their own jobs without edit rights to the whole record.
     */
    public function updateStatus(Request $request, ProjectJob $job): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ProjectJob::STATUSES)],
        ]);

        $user = $request->user();
        if (! $user->isAdmin() && ! $job->crew()->whereKey($user->id)->exists()) {
            abort(403);
        }

        $job->update(['status' => $data['status']]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'title' => 'nullable|string|max:255',
            'scheduled_date' => 'required|date',
            'status' => ['required', Rule::in(ProjectJob::STATUSES)],
            'notes' => 'nullable|string|max:1000',
            'crew' => 'array',
            'crew.*' => [Rule::exists('users', 'id')],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(ProjectJob $job): array
    {
        return [
            'id' => $job->id,
            'project_id' => $job->project_id,
            'project_name' => $job->project?->name,
            'title' => $job->title,
            'scheduled_date' => $job->scheduled_date?->toDateString(),
            'status' => $job->status,
            'notes' => $job->notes,
            'crew' => $job->relationLoaded('crew')
                ? $job->crew->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                : [],
        ];
    }
}
