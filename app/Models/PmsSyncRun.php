<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One `pms:sync` run: when, by whom (null for the nightly schedule), and how it went. */
class PmsSyncRun extends Model
{
    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'triggered_by_profile_id');
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'row_count' => $this->row_count,
            'skipped_count' => $this->skipped_count,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'triggered_by' => $this->triggeredBy?->display_name,
        ];
    }
}
