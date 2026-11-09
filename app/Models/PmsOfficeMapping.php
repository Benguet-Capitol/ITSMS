<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Which HRIS office a PMS office name stands for ("NORTHERN BENGUET DISTRICT HOSPITAL" is NBDH). */
class PmsOfficeMapping extends Model
{
    protected $fillable = [
        'pms_office_code',
        'office_id',
        'office_code',
        'office_name',
        'updated_by_profile_id',
    ];

    protected $casts = [
        'office_id' => 'integer',
    ];
}
