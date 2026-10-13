<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request for Network Assistance (form ICT-15) printout
    |--------------------------------------------------------------------------
    |
    | The form's revision code changes when the PGO-IT/MIS form is revised,
    | so it's read from the environment instead of being hard-coded.
    |
    */

    'form_code' => env('NETWORK_ASSISTANCE_FORM_CODE', 'PLGU-BENGUET|ICT-15-V1|2026'),

];
