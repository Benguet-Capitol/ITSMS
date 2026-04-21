<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $with = ['department', 'position'];

    protected $fillable = [
      'department_id',
      'position_id',
      'uid',
      'firstname',
      'middlename',
      'lastname',
      'suffix',
      'full_name',
      'img_path',
    ];

    public function department() {
      return $this->belongsTo(Department::class);
    }

    public function position() {
      return $this->belongsTo(Position::class);
    } 
}
