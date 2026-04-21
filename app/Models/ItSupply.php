<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItSupply extends Model
{
    protected $with = ['brand_model', 'measurement_unit'];

    protected $fillable = [
        'brand_model_id',
        'measurement_unit_id',
        'description',
        'item_number',
        'stock_number',
        'ics_number',
        'iar_number',
        'po_number',
        'quantity',
    ];

    public function brand_model() {
      return $this->belongsTo(BrandModel::class);
    }

    public function measurement_unit() {
      return $this->belongsTo(MeasurementUnit::class);
    }
}
