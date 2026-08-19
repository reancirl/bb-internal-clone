<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\TaskPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Task photo processing — same renditions and limits as daily-log photos so
 * the two pipelines behave identically for users.
 */
class TaskPhotoStorage
{
    public const MAX_PHOTOS_PER_TASK = 10;

    private const WEB_MAX_EDGE = 1600;

    private const THUMB_MAX_EDGE = 400;

    /**
     * @param  list<UploadedFile>  $files
     */
    public static function attach(ProjectTask $task, array $files, string $stage, int $userId): void
    {
        $manager = new ImageManager(new Driver);
        $dir = 'task-photos/'.$task->id;

        foreach (array_values($files) as $file) {
            $basename = Str::uuid()->toString();

            $web = $manager->read($file->getPathname())
                ->scaleDown(self::WEB_MAX_EDGE, self::WEB_MAX_EDGE)
                ->toJpeg(82);
            $thumb = $manager->read($file->getPathname())
                ->scaleDown(self::THUMB_MAX_EDGE, self::THUMB_MAX_EDGE)
                ->toJpeg(75);

            $path = "{$dir}/{$basename}.jpg";
            $thumbPath = "{$dir}/{$basename}-thumb.jpg";
            Storage::disk(TaskPhoto::DISK)->put($path, (string) $web);
            Storage::disk(TaskPhoto::DISK)->put($thumbPath, (string) $thumb);

            $task->photos()->create([
                'stage' => $stage,
                'path' => $path,
                'thumb_path' => $thumbPath,
                'original_name' => $file->getClientOriginalName(),
                'size_bytes' => strlen((string) $web),
                'uploaded_by_user_id' => $userId,
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'max:'.self::MAX_PHOTOS_PER_TASK],
            'photos.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:15360'],
            'stage' => ['required', 'in:'.implode(',', TaskPhoto::STAGES)],
        ];
    }
}
