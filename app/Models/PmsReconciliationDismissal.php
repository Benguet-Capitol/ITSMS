<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PMS difference someone checked and chose to keep. It only hides the
 * difference while the fingerprint (the values compared) is unchanged.
 */
class PmsReconciliationDismissal extends Model
{
    protected $fillable = [
        'issue',
        'inventory_id',
        'pms_property_id',
        'fingerprint',
        'note',
        'dismissed_by_profile_id',
    ];

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'dismissed_by_profile_id');
    }
}
