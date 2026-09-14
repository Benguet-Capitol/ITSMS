<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'code',
    ];
}
