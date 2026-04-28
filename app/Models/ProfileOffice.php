<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileOffice extends Model
{
    protected $fillable = [
        'profile_id',
        'office_id',
        'office_code',
        'office_desc',
    ];

    public function profile() {
        return $this->belongsTo(Profile::class);
    }
}
