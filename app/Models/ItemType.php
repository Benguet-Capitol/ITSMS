<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemType extends Model
{
    protected $fillable = [
        'type',
        'classification',
        'purpose',
        'is_main_inventory',
        'is_component',
        'supports_internal_components',
        'part_number',
        'status',
    ];

    protected $casts = [
        'is_main_inventory' => 'boolean',
        'is_component' => 'boolean',
        'supports_internal_components' => 'boolean',
    ];
}
