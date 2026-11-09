<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An inventory item matched to a PMS property by hand, because their property numbers differ. */
class PmsInventoryLink extends Model
{
    protected $fillable = [
        'inventory_id',
        'pms_property_id',
        'linked_by_profile_id',
    ];
}
