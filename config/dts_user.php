<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request for New DTS User / Update of User Details printout
    |--------------------------------------------------------------------------
    |
    | The source .docx is inconsistent about its own code (file name
    | "ICT-14-V2 2025", printed headers "ICT-13-V1|2026", a leftover box
    | "ICT-14-V1|2025"), so the code is configurable. The default is what
    | the current headers print.
    |
    */

    'form_code' => env('DTS_USER_FORM_CODE', 'PLGU-BENGUET|ICT-13-V1|2026'),

];
