<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HRIS Employee Search Filters
    |--------------------------------------------------------------------------
    |
    | Allow-list of query parameters that EmployeeController::search() will
    | pass through to the external HRIS API. This used to live under
    | config('services.employee_filters'), but the controller has always
    | read config('hris.employee_filters') — since no config/hris.php file
    | existed, that call silently fell back to [], so every filter except
    | the numeric employee_id auto-detect was a no-op. Moved here so the
    | lookup actually resolves.
    |
    */

    'employee_filters' => [
        'employee_id' => [
            'type' => 'string',
            'min' => 6,
        ],
        'office_id' => [
            'type' => 'int',
        ],
        'type' => [
            'type' => 'string',
            'allowed' => ['permanent', 'casual', 'contract', 'job_order'],
        ],

        // future:
        // 'fullname' => ['type' => 'string', 'min' => 2],
    ],

];
