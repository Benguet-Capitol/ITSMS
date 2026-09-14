<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemType extends Model
{
    use HasFactory;

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

    public function brand_models()
    {
        return $this->hasMany(BrandModel::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function common_problems()
    {
        return $this->hasMany(CommonProblem::class);
    }
}
