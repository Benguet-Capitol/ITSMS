<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandModel extends Model
{
    protected $with = ['brand', 'item_type'];

    protected $fillable = [
        'brand_id',
        'item_type_id',
        'name',
        'specification',
        'image',
        'year_released',
        'status',
    ];

    protected $appends = [
        'display_name',
    ];

    public function brand() {
        return $this->belongsTo(Brand::class);
    }

    public function item_type() {
        return $this->belongsTo(ItemType::class);
    }

    public function getDisplayNameAttribute(): string {
        $itemType = trim((string) data_get($this, 'item_type.type'));
        $specification = trim((string) $this->specification);
        $name = trim((string) $this->name);
        $brand = trim((string) data_get($this, 'brand.name'));

        $parts = array_filter([
            $itemType,
            $specification,
            $name,
            $brand,
        ], fn ($value) => $value !== '');

        return implode(', ', $parts);
    }
}