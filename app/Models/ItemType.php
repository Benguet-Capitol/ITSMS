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
        'part_number',
        'status',
    ];
}
