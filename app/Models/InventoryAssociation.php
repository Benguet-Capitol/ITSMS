<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAssociation extends Model
{
    protected $fillable = [
        'source_asset_id', 'target_asset_id', 'association_type', 'connection_details'
    ];
    public function sourceAsset() {
        return $this->belongsTo(Inventory::class, 'source_asset_id');
    }
    public function targetAsset() {
        return $this->belongsTo(Inventory::class, 'target_asset_id');
    }
}
