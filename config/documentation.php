<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request for Documentation (form ICT-11) printout
    |--------------------------------------------------------------------------
    |
    | The form's revision code changes when the PGO-IT/MIS form is revised,
    | so it's read from the environment instead of being hard-coded.
    |
    */

    'form_code' => env('DOCUMENTATION_FORM_CODE', 'PLGU-BENGUET|ICT-11-V1|2025'),

];
