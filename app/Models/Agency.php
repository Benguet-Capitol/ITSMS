<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    protected $fillable = [
        'name',
        'abbreviation',
    ];

    public function assigned_profiles() {
        return $this->belongsToMany(Profile::class, 'profile_agency');
    }
}
