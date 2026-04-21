<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solution extends Model
{

    protected $with = [ 'author'];

    protected $fillable = [
        'author_id',
        'title',
        'description',
        'error_code',
        'reference_url',
        'description_updated_at',
    ];

    // public function tickets() {
    //     return $this->hasMany(Ticket::class);
    // }

    public function author() {
        return $this->belongsTo(Profile::class, 'author_id');
    }
}
