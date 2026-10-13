<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request for CCTV Footage (form ICT-08) printout
    |--------------------------------------------------------------------------
    |
    | The signatories change when officials change, so they're read from the
    | environment instead of being printed from code. Leaving a name blank
    | prints an empty signature line to be filled in by hand.
    |
    */

    'form_code' => env('CCTV_FORM_CODE', 'PLGU-BENGUET|ICT-08-V4|2026'),

    'signatories' => [
        'recommending' => [
            'name' => env('CCTV_DPO_NAME', ''),
            'title' => env('CCTV_DPO_TITLE', 'Data Protection Officer'),
        ],
        'approving' => [
            'name' => env('CCTV_APPROVER_NAME', ''),
            'title' => env('CCTV_APPROVER_TITLE', 'Provincial Administrator'),
        ],
    ],

];
