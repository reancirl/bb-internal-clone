<?php

namespace App\Models;

use Database\Factories\ProjectJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectJob extends Model
{
    /** @use HasFactory<ProjectJobFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELED,
    ];

    protected $fillable = [
        'project_id',
        'title',
        'scheduled_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Crew members assigned to this job.
     *
     * @return BelongsToMany<User, $this>
     */
    public function crew(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
