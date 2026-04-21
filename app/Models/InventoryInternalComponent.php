<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryInternalComponent extends Model
{
    // protected $with = ['inventory', 'brand_model'];

    protected $fillable = [
        'inventory_id', 'brand_model_id', 'specific_serial_number', 'slot', 'quantity', 'notes'
    ];

    public function inventory() {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
    public function brand_model() {
        return $this->belongsTo(BrandModel::class);
    }
}
