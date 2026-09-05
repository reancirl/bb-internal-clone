<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_DECLINED];

    protected $fillable = [
        'project_id',
        'number',
        'title',
        'description',
        'price_cents',
        'status',
        'decided_at',
        'decided_by_user_id',
        'decision_comment',
        'budget_line_id',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'price_cents' => 'integer',
            'decided_at' => 'datetime',
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
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * @return BelongsTo<ProjectBudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLine::class, 'budget_line_id');
    }

    public function label(): string
    {
        return 'CO-'.$this->number.' — '.$this->title;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Next per-project change order number. Callers run inside a transaction
     * and retry on the unique-constraint race, mirroring task/PO/proposal
     * numbering. The max is computed in PHP: Postgres rejects FOR UPDATE
     * combined with aggregate functions, so lock the rows and aggregate
     * client-side.
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
