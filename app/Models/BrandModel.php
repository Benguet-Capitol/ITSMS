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

    public function brand() {
      return $this->belongsTo(Brand::class);
    }

    public function item_type() {
      return $this->belongsTo(ItemType::class);
    }
}
