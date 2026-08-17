<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectProposal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
    ];

    /**
     * Allowed status transitions. Rejected proposals can be re-sent after a
     * revision conversation; accepted is terminal.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SENT],
        self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED],
        self::STATUS_REJECTED => [self::STATUS_SENT],
        self::STATUS_ACCEPTED => [],
    ];

    protected $fillable = [
        'project_id',
        'number',
        'title',
        'status',
        'total_cents',
        'payment_terms',
        'notes',
        'valid_until',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
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
     * @return HasMany<ProjectProposalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ProjectProposalLine::class);
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

    /**
     * Next sequential number for the given year, e.g. "PROP-2026-003".
     */
    public static function nextNumber(int $year): string
    {
        $prefix = sprintf('PROP-%d-', $year);

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
