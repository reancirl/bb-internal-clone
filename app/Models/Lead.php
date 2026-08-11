<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_MEETING_SCHEDULED = 'meeting_scheduled';

    public const STATUS_PROPOSAL_SENT = 'proposal_sent';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_NEW, self::STATUS_CONTACTED, self::STATUS_QUALIFIED,
        self::STATUS_MEETING_SCHEDULED, self::STATUS_PROPOSAL_SENT,
        self::STATUS_WON, self::STATUS_LOST,
    ];

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const SOURCES = ['website', 'referral', 'social_media', 'email_campaign', 'trade_show', 'other'];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'build_location',
        'project_details',
        'source',
        'submitted_at',
        'status',
        'priority',
        'estimated_value_cents',
        'next_follow_up_date',
        'assigned_to_user_id',
        'lost_reason',
        'won_at',
        'lost_at',
        'converted_project_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'estimated_value_cents' => 'integer',
            'next_follow_up_date' => 'date:Y-m-d',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
