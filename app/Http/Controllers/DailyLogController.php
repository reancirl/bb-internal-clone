<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Project;
use App\Support\DailyLogPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailyLogController extends Controller
{
    /**
     * Global feed across all projects — the office's morning review.
     */
    public function index(Request $request): Response
    {
        $projectId = $request->integer('project') ?: null;

        $logs = DailyLog::query()
            ->with(['project:id,name', 'author:id,name', 'photos:id,daily_log_id'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DailyLog $log) => $this->serialize($log, $request));

        return Inertia::render('daily-logs/index', [
            'logs' => $logs,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'filters' => ['project' => $projectId],
            'weatherOptions' => DailyLog::WEATHER_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'log_date' => ['required', 'date'],
            'notes' => ['required', 'string'],
            'weather' => ['nullable', Rule::in(DailyLog::WEATHER_OPTIONS)],
            'temperature_f' => ['nullable', 'integer', 'between:-60,130'],
            'crew_present' => ['nullable', 'string', 'max:255'],
            'issues' => ['nullable', 'string'],
            ...DailyLogPhotoStorage::rules(),
        ]);

        $log = DailyLog::create([
            ...collect($data)->except('photos')->all(),
            'user_id' => $request->user()->id,
        ]);

        if ($request->hasFile('photos')) {
            DailyLogPhotoStorage::attach($log, $request->file('photos'));
        }

        return back()->with('success', 'Daily log added.');
    }

    public function update(Request $request, DailyLog $dailyLog): RedirectResponse
    {
        abort_unless($dailyLog->editableBy($request->user()), 403);

        $data = $request->validate([
            'log_date' => ['sometimes', 'required', 'date'],
            'notes' => ['sometimes', 'required', 'string'],
            'weather' => ['sometimes', 'nullable', Rule::in(DailyLog::WEATHER_OPTIONS)],
            'temperature_f' => ['sometimes', 'nullable', 'integer', 'between:-60,130'],
            'crew_present' => ['sometimes', 'nullable', 'string', 'max:255'],
            'issues' => ['sometimes', 'nullable', 'string'],
        ]);

        $dailyLog->update($data);

        return back()->with('success', 'Daily log updated.');
    }

    public function destroy(Request $request, DailyLog $dailyLog): RedirectResponse
    {
        abort_unless($dailyLog->editableBy($request->user()), 403);

        $dailyLog->delete();

        return back()->with('success', 'Daily log deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DailyLog $log, Request $request): array
    {
        return [
            'id' => $log->id,
            'project_id' => $log->project_id,
            'project_name' => $log->project->name ?? '—',
            'log_date' => $log->log_date->toDateString(),
            'notes' => $log->notes,
            'weather' => $log->weather,
            'temperature_f' => $log->temperature_f,
            'crew_present' => $log->crew_present,
            'issues' => $log->issues,
            'author' => $log->author->name ?? 'Unknown',
            'created_at' => $log->created_at->toISOString(),
            'editable' => $log->editableBy($request->user()),
            'photos' => $log->photos->map(fn ($p) => ['id' => $p->id])->values(),
        ];
    }
}
