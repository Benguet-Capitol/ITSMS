<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request for Change or Modification of Information System printout
    |--------------------------------------------------------------------------
    |
    | The source .docx carries no PLGU-BENGUET|ICT-xx code (every other
    | PGO-IT/MIS form does), so none is printed until one is assigned and
    | set here.
    |
    */

    'form_code' => env('IS_CHANGE_FORM_CODE', ''),

];
