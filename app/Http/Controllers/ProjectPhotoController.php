<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\DailyLogPhoto;
use App\Models\Project;
use App\Support\DailyLogPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectPhotoController extends Controller
{
    /**
     * Every photo on the project, newest working day first. Photos live on
     * daily logs, so this is a read view over the existing pipeline — the
     * gallery adds no second storage path.
     */
    public function index(Request $request, Project $project): Response
    {
        $user = $request->user();

        $photos = DailyLogPhoto::query()
            ->whereHas('log', fn ($q) => $q->where('project_id', $project->id))
            ->with(['log:id,project_id,log_date,user_id', 'log.author:id,name'])
            ->get()
            ->sortByDesc(fn (DailyLogPhoto $p) => [$p->log->log_date->toDateString(), $p->id])
            ->values()
            ->map(fn (DailyLogPhoto $p) => [
                'id' => $p->id,
                'thumb_url' => route('daily-logs.photos.thumb', $p),
                'full_url' => route('daily-logs.photos.show', $p),
                'taken_on' => $p->log->log_date->toDateString(),
                'month' => $p->log->log_date->format('Y-m'),
                'month_label' => $p->log->log_date->format('F Y'),
                'uploader' => $p->log->author?->name,
                'log_id' => $p->log->id,
                'original_name' => $p->original_name,
                'can_delete' => $p->log->editableBy($user),
            ]);

        return Inertia::render('projects/photos', [
            'project' => $project->only(['id', 'name', 'client_name']),
            'photos' => $photos,
        ]);
    }

    /**
     * Gallery uploads stay inside the daily-log pipeline: attach to today's
     * log by this user if it has room, otherwise start a fresh one. Every
     * photo keeps a documented who/when either way.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $request->validate(DailyLogPhotoStorage::rules(required: true));
        $files = $request->file('photos');

        $log = DailyLog::query()
            ->where('project_id', $project->id)
            ->where('user_id', $request->user()->id)
            ->whereDate('log_date', today())
            ->withCount('photos')
            ->orderByDesc('id')
            ->first();

        $hasRoom = $log !== null
            && $log->photos_count + count($files) <= DailyLogPhotoStorage::MAX_PHOTOS_PER_LOG;

        if (! $hasRoom) {
            $log = DailyLog::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'log_date' => today(),
                'notes' => 'Photos added from the project gallery.',
            ]);
        }

        DailyLogPhotoStorage::attach($log, $files);

        return back()->with('success', count($files).' photo'.(count($files) === 1 ? '' : 's').' added.');
    }
}
