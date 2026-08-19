<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Models\TaskPhoto;
use App\Support\TaskPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskPhotoController extends Controller
{
    public function store(Request $request, ProjectTask $task): RedirectResponse
    {
        abort_unless($task->workableBy($request->user()), 403);

        $data = $request->validate(TaskPhotoStorage::rules());

        $files = $request->file('photos');
        $existing = $task->photos()->count();
        if ($existing + count($files) > TaskPhotoStorage::MAX_PHOTOS_PER_TASK) {
            return back()->withErrors([
                'photos' => 'A task can hold at most '.TaskPhotoStorage::MAX_PHOTOS_PER_TASK.' photos ('.$existing.' already attached).',
            ]);
        }

        TaskPhotoStorage::attach($task, $files, $data['stage'], $request->user()->id);

        return back()->with('success', count($files).' photo'.(count($files) === 1 ? '' : 's').' added.');
    }

    /**
     * Private-disk photos; these auth-guarded routes are the only way in.
     */
    public function show(TaskPhoto $photo): StreamedResponse
    {
        return $this->stream($photo->path);
    }

    public function thumb(TaskPhoto $photo): StreamedResponse
    {
        return $this->stream($photo->thumb_path);
    }

    public function destroy(Request $request, TaskPhoto $photo): RedirectResponse
    {
        abort_unless($photo->task->workableBy($request->user()), 403);

        $photo->delete(); // model hook removes the files

        return back()->with('success', 'Photo removed.');
    }

    private function stream(string $path): StreamedResponse
    {
        $disk = Storage::disk(TaskPhoto::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
