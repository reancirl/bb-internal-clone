<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DailyLogPhoto extends Model
{
    public const DISK = 'local';

    protected $fillable = [
        'daily_log_id',
        'path',
        'thumb_path',
        'original_name',
        'size_bytes',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // Databases cascade; files don't. Remove both renditions from disk
        // whenever the row goes away (direct delete or log delete).
        static::deleted(function (DailyLogPhoto $photo) {
            Storage::disk(self::DISK)->delete([$photo->path, $photo->thumb_path]);
        });
    }

    /**
     * @return BelongsTo<DailyLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class, 'daily_log_id');
    }
}
