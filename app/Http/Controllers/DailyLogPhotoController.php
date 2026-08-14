<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\DailyLogPhoto;
use App\Support\DailyLogPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyLogPhotoController extends Controller
{
    public function store(Request $request, DailyLog $dailyLog): RedirectResponse
    {
        abort_unless($dailyLog->editableBy($request->user()), 403);

        $request->validate(DailyLogPhotoStorage::rules(required: true));

        $files = $request->file('photos');
        $existing = $dailyLog->photos()->count();
        if ($existing + count($files) > DailyLogPhotoStorage::MAX_PHOTOS_PER_LOG) {
            return back()->withErrors([
                'photos' => 'A log can hold at most '.DailyLogPhotoStorage::MAX_PHOTOS_PER_LOG.' photos ('.$existing.' already attached).',
            ]);
        }

        DailyLogPhotoStorage::attach($dailyLog, $files);

        return back()->with('success', count($files).' photo'.(count($files) === 1 ? '' : 's').' added.');
    }

    /**
     * Photos live on the private disk; these routes are the only way to
     * reach them, and they sit behind the auth middleware.
     */
    public function show(DailyLogPhoto $photo): StreamedResponse
    {
        return $this->stream($photo->path);
    }

    public function thumb(DailyLogPhoto $photo): StreamedResponse
    {
        return $this->stream($photo->thumb_path);
    }

    public function destroy(Request $request, DailyLogPhoto $photo): RedirectResponse
    {
        abort_unless($photo->log->editableBy($request->user()), 403);

        $photo->delete(); // model hook removes the files

        return back()->with('success', 'Photo removed.');
    }

    private function stream(string $path): StreamedResponse
    {
        $disk = Storage::disk(DailyLogPhoto::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
