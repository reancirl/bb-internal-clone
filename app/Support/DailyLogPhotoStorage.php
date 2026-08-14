<?php

namespace App\Support;

use App\Models\DailyLog;
use App\Models\DailyLogPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class DailyLogPhotoStorage
{
    public const MAX_PHOTOS_PER_LOG = 10;

    private const WEB_MAX_EDGE = 1600;

    private const THUMB_MAX_EDGE = 400;

    /**
     * Process and attach uploaded images to a log: auto-orient (EXIF),
     * cap the long edge, re-encode as JPEG, store web + thumb renditions.
     *
     * @param  list<UploadedFile>  $files
     */
    public static function attach(DailyLog $log, array $files): void
    {
        $manager = new ImageManager(new Driver);
        $dir = 'daily-log-photos/'.$log->id;
        $existing = $log->photos()->count();

        foreach (array_values($files) as $i => $file) {
            $basename = Str::uuid()->toString();

            $web = $manager->read($file->getPathname())
                ->scaleDown(self::WEB_MAX_EDGE, self::WEB_MAX_EDGE)
                ->toJpeg(82);
            $thumb = $manager->read($file->getPathname())
                ->scaleDown(self::THUMB_MAX_EDGE, self::THUMB_MAX_EDGE)
                ->toJpeg(75);

            $path = "{$dir}/{$basename}.jpg";
            $thumbPath = "{$dir}/{$basename}-thumb.jpg";
            Storage::disk(DailyLogPhoto::DISK)->put($path, (string) $web);
            Storage::disk(DailyLogPhoto::DISK)->put($thumbPath, (string) $thumb);

            $log->photos()->create([
                'path' => $path,
                'thumb_path' => $thumbPath,
                'original_name' => $file->getClientOriginalName(),
                'size_bytes' => strlen((string) $web),
                'sort_order' => $existing + $i,
            ]);
        }
    }

    /**
     * Shared validation rules for incoming photo uploads.
     *
     * @return array<string, list<string>>
     */
    public static function rules(bool $required = false): array
    {
        return [
            'photos' => [$required ? 'required' : 'sometimes', 'array', 'max:'.self::MAX_PHOTOS_PER_LOG],
            // GD has no HEIC support — phones convert to JPEG when the file
            // input requests these types. 15MB in; we shrink on receipt.
            'photos.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:15360'],
        ];
    }
}
