<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherItServiceRequest extends Model
{
    protected $fillable = [
        'control_number',
        'status',
        'date_of_request',
        'department_office',
        'requestor_name',

        'service_printing',
        'service_information_material',
        'service_program_paper',
        'service_brochure',
        'service_iec_material',
        'service_handbook',
        'service_certificates',
        'service_others',
        'service_qty',
        'service_laptop_tv_setup',
        'service_others_specify',

        'program_activity_details',
        'activity_date_text',
        'activity_time',

        'assigned_personnel',
        'date_received',
        'action_taken',

        'feedback_rating',
        'feedback_name',
        'feedback_date',
    ];

    protected $casts = [
        'date_of_request'              => 'date',
        'date_received'                => 'date',
        'feedback_date'                => 'date',
        'service_printing'             => 'boolean',
        'service_information_material' => 'boolean',
        'service_program_paper'        => 'boolean',
        'service_brochure'             => 'boolean',
        'service_iec_material'         => 'boolean',
        'service_handbook'             => 'boolean',
        'service_certificates'         => 'boolean',
        'service_others'               => 'boolean',
        'service_laptop_tv_setup'      => 'boolean',
    ];
}
