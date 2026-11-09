<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One audit trail entry (docs/activity-log.md). Written only by
 * App\Services\ActivityLogger; immutable once written -- updating or
 * deleting a row throws.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'loggable_type',
        'loggable_id',
        'subject_label',
        'changes',
        'context',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Activity log entries cannot be changed.');
        });

        static::deleting(function () {
            throw new LogicException('Activity log entries cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
