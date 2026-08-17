<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectJob;
use App\Models\User;
use App\Support\ScheduleShifter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /**
     * Upper bound for duration_days; also sizes the calendar overlap window.
     */
    private const MAX_DURATION_DAYS = 90;

    /**
     * Crew-facing "my jobs" — the signed-in user's assigned jobs from today on.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        // Include multi-day jobs already underway: started up to one max
        // duration ago but not yet past their end date.
        $jobs = $user->jobs()
            ->with('project:id,name')
            ->whereDate('scheduled_date', '>=', $today->copy()->subDays(self::MAX_DURATION_DAYS))
            ->orderBy('scheduled_date')
            ->get()
            ->filter(fn (ProjectJob $job) => $job->endDate()->gte($today))
            ->values()
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

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        // Multi-day jobs may start before the month but run into it, so look
        // back one max-duration window and filter by actual overlap.
        $jobs = ProjectJob::query()
            ->with(['project:id,name', 'crew:id,name'])
            ->withCount('successors')
            ->whereBetween('scheduled_date', [$monthStart->copy()->subDays(self::MAX_DURATION_DAYS), $monthEnd])
            ->orderBy('scheduled_date')
            ->get()
            ->filter(fn (ProjectJob $job) => $job->endDate()->gte($monthStart))
            ->values()
            ->map(fn (ProjectJob $job) => $this->toRow($job));

        return Inertia::render('calendar/index', [
            'month' => $month->format('Y-m'),
            'jobs' => $jobs,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'crew' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            'statuses' => ProjectJob::STATUSES,
            'trades' => ProjectJob::query()->whereNotNull('trade')->distinct()->orderBy('trade')->pluck('trade'),
            'jobOptions' => $this->jobOptions(),
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $job = ProjectJob::create(Arr::except($data, ['crew', 'shift_successors']));
        $job->crew()->sync($data['crew'] ?? []);

        return back()->with('success', 'Job scheduled.');
    }

    public function update(Request $request, ProjectJob $job): RedirectResponse
    {
        $data = $this->validateData($request, $job);

        $oldEnd = $job->endDate();
        $job->update(Arr::except($data, ['crew', 'shift_successors']));
        $job->crew()->sync($data['crew'] ?? []);

        $shifted = 0;
        if ($request->boolean('shift_successors')) {
            $delta = (int) $oldEnd->diffInDays($job->refresh()->endDate(), false);
            if ($delta !== 0) {
                $shifted = app(ScheduleShifter::class)->apply($job, $delta);
            }
        }

        $message = $shifted > 0
            ? "Job updated; {$shifted} downstream ".($shifted === 1 ? 'job' : 'jobs').' shifted.'
            : 'Job updated.';

        return back()->with('success', $message);
    }

    /**
     * Downstream impact of a proposed date/duration change, for the
     * confirm-shift dialog. Read-only; nothing is written here.
     */
    public function shiftPreview(Request $request, ProjectJob $job): JsonResponse
    {
        $data = $request->validate([
            'scheduled_date' => 'required|date',
            'duration_days' => 'required|integer|min:1|max:'.self::MAX_DURATION_DAYS,
        ]);

        $newEnd = Carbon::parse($data['scheduled_date'])->addDays($data['duration_days'] - 1);
        $delta = (int) $job->endDate()->diffInDays($newEnd, false);

        return response()->json([
            'delta_days' => $delta,
            'affected' => app(ScheduleShifter::class)->preview($job, $delta),
        ]);
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
    private function validateData(Request $request, ?ProjectJob $job = null): array
    {
        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'predecessor_job_id' => ['nullable', 'integer', Rule::exists('project_jobs', 'id')],
            'title' => 'nullable|string|max:255',
            'scheduled_date' => 'required|date',
            'duration_days' => 'nullable|integer|min:1|max:'.self::MAX_DURATION_DAYS,
            'status' => ['required', Rule::in(ProjectJob::STATUSES)],
            'trade' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'crew' => 'array',
            'crew.*' => [Rule::exists('users', 'id')],
        ]);

        $data['duration_days'] = $data['duration_days'] ?? $job?->duration_days ?? 1;

        if ($job && (int) $data['project_id'] !== $job->project_id && $job->successors()->exists()) {
            throw ValidationException::withMessages([
                'project_id' => 'This job has dependent jobs. Clear or reassign them before moving it to another project.',
            ]);
        }

        if (! empty($data['predecessor_job_id'])) {
            $predecessor = ProjectJob::find($data['predecessor_job_id']);

            if (! $predecessor || $predecessor->project_id !== (int) $data['project_id']) {
                throw ValidationException::withMessages([
                    'predecessor_job_id' => 'The predecessor must be a job on the same project.',
                ]);
            }

            if ($job && ($predecessor->id === $job->id || app(ScheduleShifter::class)->wouldCreateCycle($job, $predecessor->id))) {
                throw ValidationException::withMessages([
                    'predecessor_job_id' => 'This would create a dependency cycle.',
                ]);
            }
        }

        return $data;
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
            'predecessor_job_id' => $job->predecessor_job_id,
            'title' => $job->title,
            'scheduled_date' => $job->scheduled_date?->toDateString(),
            'duration_days' => $job->duration_days,
            'end_date' => $job->endDate()->toDateString(),
            'status' => $job->status,
            'trade' => $job->trade,
            'notes' => $job->notes,
            'has_successors' => ($job->successors_count ?? 0) > 0,
            'crew' => $job->relationLoaded('crew')
                ? $job->crew->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                : [],
        ];
    }

    /**
     * Every non-canceled job, for the predecessor picker (filtered client-side
     * by the selected project).
     *
     * @return list<array<string, mixed>>
     */
    private function jobOptions(): array
    {
        return ProjectJob::query()
            ->where('status', '!=', ProjectJob::STATUS_CANCELED)
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get(['id', 'project_id', 'predecessor_job_id', 'title', 'scheduled_date', 'duration_days', 'status'])
            ->map(fn (ProjectJob $job) => [
                'id' => $job->id,
                'project_id' => $job->project_id,
                'title' => $job->title,
                'scheduled_date' => $job->scheduled_date->toDateString(),
                'end_date' => $job->endDate()->toDateString(),
                'status' => $job->status,
            ])
            ->all();
    }
}
