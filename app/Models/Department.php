<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'barcode',
        'name',
        'full_name',
        'division',
        'abbreviation',
    ];

    public function assigned_profiles() {
        return $this->belongsToMany(Profile::class, 'profile_department');
    }
}
