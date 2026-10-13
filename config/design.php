<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request for Design/Lay-out/Editing (form ICT-10) printout
    |--------------------------------------------------------------------------
    |
    | The form's revision code changes when the PGO-IT/MIS form is revised,
    | so it's read from the environment instead of being hard-coded.
    |
    */

    'form_code' => env('DESIGN_FORM_CODE', 'PLGU-BENGUET|ICT-10-V1|2026'),

];
