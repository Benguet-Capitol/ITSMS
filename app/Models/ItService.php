<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItService extends Model
{
    protected $fillable = [
        'name',
        'description',
        'code',
    ];
}
