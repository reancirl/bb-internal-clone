<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ProjectJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'predecessor_job_id',
        'title',
        'scheduled_date',
        'duration_days',
        'status',
        'trade',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'duration_days' => 'integer',
        ];
    }

    /**
     * Last day of the job: scheduled_date + duration_days - 1.
     */
    public function endDate(): Carbon
    {
        return $this->scheduled_date->copy()->addDays(max(1, $this->duration_days) - 1);
    }

    /**
     * Only not-yet-started jobs move when a predecessor's dates change.
     */
    public function isShiftable(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectJob, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_job_id');
    }

    /**
     * @return HasMany<ProjectJob, $this>
     */
    public function successors(): HasMany
    {
        return $this->hasMany(self::class, 'predecessor_job_id');
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
