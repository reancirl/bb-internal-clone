<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyLog extends Model
{
    public const WEATHER_OPTIONS = ['Sunny', 'Partly cloudy', 'Overcast', 'Rain', 'Snow', 'Windy', 'Freezing'];

    protected $fillable = [
        'project_id',
        'user_id',
        'log_date',
        'notes',
        'weather',
        'temperature_f',
        'crew_present',
        'issues',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date:Y-m-d',
            'temperature_f' => 'integer',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<DailyLogPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(DailyLogPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    protected static function booted(): void
    {
        // Cascade file cleanup: DB-level FK cascade would bypass the photo
        // model's deleted hook, so delete photos through Eloquent first.
        static::deleting(function (DailyLog $log) {
            $log->photos()->get()->each->delete();
        });
    }

    public function editableBy(User $user): bool
    {
        return $user->isAdmin() || $this->user_id === $user->id;
    }
}
