<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
    ];

    public function assigned_profiles()
    {
        return $this->belongsToMany(Profile::class, 'profile_agency');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
