<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BidRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_AWARDED,
        self::STATUS_CANCELED,
    ];

    /**
     * Opening locks the scope — subs are pricing it, so changing it mid-flight
     * would invalidate their numbers; cancel and re-issue instead. Awarded and
     * canceled are terminal so the record stays as history.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_OPEN, self::STATUS_CANCELED],
        self::STATUS_OPEN => [self::STATUS_AWARDED, self::STATUS_CANCELED],
        self::STATUS_AWARDED => [],
        self::STATUS_CANCELED => [],
    ];

    protected $fillable = [
        'project_id',
        'title',
        'trade',
        'scope_description',
        'due_date',
        'status',
        'budget_line_id',
        'opened_at',
        'awarded_at',
        'canceled_at',
        'created_by_user_id',
        'awarded_response_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'opened_at' => 'datetime',
            'awarded_at' => 'datetime',
            'canceled_at' => 'datetime',
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
     * @return HasMany<BidResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(BidResponse::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<BidResponse, $this>
     */
    public function awardedResponse(): BelongsTo
    {
        return $this->belongsTo(BidResponse::class, 'awarded_response_id');
    }

    /**
     * @return BelongsTo<ProjectBudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLine::class, 'budget_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
