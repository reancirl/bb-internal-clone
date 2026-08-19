<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskPhoto extends Model
{
    public const DISK = 'local';

    public const STAGE_BEFORE = 'before';

    public const STAGE_AFTER = 'after';

    public const STAGES = [self::STAGE_BEFORE, self::STAGE_AFTER];

    protected $fillable = [
        'project_task_id',
        'stage',
        'path',
        'thumb_path',
        'original_name',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected static function booted(): void
    {
        // Databases cascade; files don't. Same rule as daily-log photos.
        static::deleted(function (TaskPhoto $photo) {
            Storage::disk(self::DISK)->delete([$photo->path, $photo->thumb_path]);
        });
    }

    /**
     * @return BelongsTo<ProjectTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
