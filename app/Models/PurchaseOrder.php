<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_CONFIRMED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELED,
    ];

    /**
     * Suppliers sometimes deliver without a formal confirmation, so sent can
     * jump straight to received. Received and canceled are terminal.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_CANCELED],
        self::STATUS_SENT => [self::STATUS_CONFIRMED, self::STATUS_RECEIVED, self::STATUS_CANCELED],
        self::STATUS_CONFIRMED => [self::STATUS_RECEIVED, self::STATUS_CANCELED],
        self::STATUS_RECEIVED => [],
        self::STATUS_CANCELED => [],
    ];

    /**
     * Statuses whose totals count as committed money: the order is out the
     * door and we have promised to pay it (drafts and canceled orders don't).
     *
     * @var list<string>
     */
    public const COMMITTED_STATUSES = [
        self::STATUS_SENT,
        self::STATUS_CONFIRMED,
        self::STATUS_RECEIVED,
    ];

    protected $fillable = [
        'project_id',
        'vendor_id',
        'vendor_name',
        'number',
        'status',
        'total_cents',
        'expected_delivery',
        'notes',
        'sent_at',
        'confirmed_at',
        'received_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'expected_delivery' => 'date',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
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
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort')->orderBy('id');
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
     * Next sequential number for the given year, e.g. "PO-2026-003". Same
     * contract as ProjectProposal::nextNumber — call inside a transaction and
     * retry on unique-constraint violation.
     */
    public static function nextNumber(int $year): string
    {
        $prefix = sprintf('PO-%d-', $year);

        $max = static::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, strlen($prefix)))
            ->max();

        return $prefix.str_pad((string) (($max ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }
}
