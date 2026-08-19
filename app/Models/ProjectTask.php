<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE];

    public const PRIORITIES = ['low', 'medium', 'high'];

    /**
     * Suggested categories for walkthrough capture; free text is allowed.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'paint', 'drywall', 'doors', 'trim', 'electrical', 'plumbing',
        'flooring', 'cabinets', 'exterior', 'cleanup', 'other',
    ];

    protected $fillable = [
        'project_id',
        'number',
        'title',
        'description',
        'location',
        'category',
        'priority',
        'is_punch',
        'assigned_to_user_id',
        'due_date',
        'status',
        'completed_at',
        'completed_by_user_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'is_punch' => 'boolean',
            'due_date' => 'date',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<TaskPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TaskPhoto::class)->orderBy('id');
    }

    protected static function booted(): void
    {
        // Cascade file cleanup through Eloquent so TaskPhoto's deleted hook
        // runs (a DB-level cascade would leave orphaned files on disk).
        static::deleting(function (ProjectTask $task) {
            $task->photos->each->delete();
        });
    }

    /**
     * Full edits and deletion: the creator or an admin.
     */
    public function editableBy(User $user): bool
    {
        return $user->isAdmin() || $this->created_by_user_id === $user->id;
    }

    /**
     * Status changes, checklist ticks, and photos: also the assignee, so the
     * person doing the fix can work the task without edit rights to it.
     */
    public function workableBy(User $user): bool
    {
        return $this->editableBy($user) || $this->assigned_to_user_id === $user->id;
    }

    /**
     * Next per-project task number. Callers run inside a transaction and
     * retry on the unique-constraint race, mirroring PO/proposal numbering.
     * The max is computed in PHP: Postgres rejects FOR UPDATE combined with
     * aggregate functions, so lock the rows and aggregate client-side.
     */
    public static function nextNumber(int $projectId): int
    {
        $max = static::query()
            ->where('project_id', $projectId)
            ->lockForUpdate()
            ->pluck('number')
            ->max();

        return (int) $max + 1;
    }
}
