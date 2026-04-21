<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAccessory extends Model
{
    protected $fillable = [
        'inventory_id', 'brand_model_id', 'notes'
    ];
    public function inventory() {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
    public function brandModel() {
        return $this->belongsTo(BrandModel::class);
    }
}
