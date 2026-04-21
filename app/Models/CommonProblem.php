<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommonProblem extends Model
{
    protected $with = ['itemType'];
    protected $fillable = [
        'code',
        'general_term',
        'information',
        'item_type_id',
    ];

    public function itemType() {
      return $this->belongsTo(ItemType::class);
    }
}
