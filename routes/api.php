<?php

use App\Enums\TicketStatus;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BrandModelController;
use App\Http\Controllers\CommonProblemController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\ItServiceController;
use App\Http\Controllers\ItSupplyController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OtherItServiceRequestController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SolutionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthGates;
use App\Http\Resources\UserResource;
use App\Services\HrisClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Development-only debug routes
|--------------------------------------------------------------------------
*/

// Route::middleware([
//     'auth:sanctum',
//     AuthGates::class,
//     'can:employees.view',
// ])->get('hris/debug/employees', function (HrisClientService $hris) {
//     $data = $hris->getEmployees();
//
//     return response()->json([
//         'count' => count($data),
//         'keys' => array_keys($data[0] ?? []),
//         'sample' => $data[0] ?? null,
//     ]);
// });

/*
|--------------------------------------------------------------------------
| Authenticated application routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    AuthGates::class,
])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authenticated user
    |--------------------------------------------------------------------------
    */

    Route::get('user', function (Request $request) {
        return UserResource::make($request->user());
    });

    /*
    |--------------------------------------------------------------------------
    | Resource routes
    |--------------------------------------------------------------------------
    |
    | Each controller method should also authorize its operation:
    | index/view/store/update/destroy.
    |
    */

    Route::apiResource('users', UserController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('positions', PositionController::class);

    Route::apiResource('employees', EmployeeController::class)
        ->only(['index', 'show']);

    Route::apiResource('brands', BrandController::class);
    Route::apiResource('brand-models', BrandModelController::class);
    Route::apiResource('item-types', ItemTypeController::class);
    Route::apiResource('common-problems', CommonProblemController::class);
    Route::apiResource('inventories', InventoryController::class);
    Route::apiResource('it-services', ItServiceController::class);
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('solutions', SolutionController::class);
    Route::apiResource('agencies', AgencyController::class);
    Route::apiResource('measurement-units', MeasurementUnitController::class);
    Route::apiResource('it-supplies', ItSupplyController::class);
    Route::apiResource(
        'other-it-service-requests',
        OtherItServiceRequestController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Lookup/select routes
    |--------------------------------------------------------------------------
    |
    | These are separate from administration/list permissions.
    | Example: a user may select an item type while editing inventory
    | without being allowed to manage item types.
    */

    Route::prefix('lookups')->group(function () {
        Route::get('roles', [
            RoleController::class,
            'select',
        ])->middleware('can:roles.select');

        Route::get('departments', [
            DepartmentController::class,
            'select',
        ])->middleware('can:departments.select');

        Route::get('positions', [
            PositionController::class,
            'select',
        ])->middleware('can:positions.select');

        Route::get('brands', [
            BrandController::class,
            'select',
        ])->middleware('can:brands.select');

        Route::get('brand-models', [
            BrandModelController::class,
            'select',
        ])->middleware('can:brand_models.select');

        Route::get('item-types', [
            ItemTypeController::class,
            'select',
        ])->middleware('can:item_types.select');

        Route::get('it-services', [
            ItServiceController::class,
            'select',
        ])->middleware('can:it_services.select');

        Route::get('solutions', [
            SolutionController::class,
            'select',
        ])->middleware('can:solutions.select');

        Route::get('agencies', [
            AgencyController::class,
            'select',
        ])->middleware('can:agencies.select');

        Route::get('measurement-units', [
            MeasurementUnitController::class,
            'select',
        ])->middleware('can:measurement_units.select');
        
        Route::get('offices', [
            OfficeController::class,
            'index',
        ])->middleware('can:offices.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Search routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('search')->group(function () {
        Route::get('employees', [
            EmployeeController::class,
            'search',
        ])->middleware('can:employees.search');

        Route::get('inventories', [
            InventoryController::class,
            'search',
        ])->middleware('can:inventories.search');

        Route::get('inventories/main-asset', [
            InventoryController::class,
            'searchMainAsset',
        ])->middleware('can:inventories.search');

        Route::get('agencies', [
            AgencyController::class,
            'search',
        ])->middleware('can:agencies.search');

        Route::get('it-supplies', [
            ItSupplyController::class,
            'search',
        ])->middleware('can:it_supplies.search');

        Route::get('brand-models', [
            BrandModelController::class,
            'search',
        ])->middleware('can:brand_models.search');

        Route::get('offices', [
            OfficeController::class,
            'search',
        ])->middleware('can:offices.search');
    });

    /*
    |--------------------------------------------------------------------------
    | Office routes
    |--------------------------------------------------------------------------
    */

    Route::get('offices', [
        OfficeController::class,
        'index',
    ])->middleware('can:offices.view');

    /*
    |--------------------------------------------------------------------------
    | Additional permission/role endpoints
    |--------------------------------------------------------------------------
    */

    Route::get('permissions-all', [
        PermissionController::class,
        'permissionAll',
    ])->middleware('can:permissions.view');

    /*
    |--------------------------------------------------------------------------
    | Ticket actions
    |--------------------------------------------------------------------------
    */

    Route::prefix('tickets/{ticket}')->group(function () {
        Route::post('accept', [
            TicketController::class,
            'accept',
        ])->middleware('can:tickets.accept');

        Route::post('unaccept', [
            TicketController::class,
            'unaccept',
        ])->middleware('can:tickets.unaccept');

        Route::post('check-stock', [
            TicketController::class,
            'checkStock',
        ])->middleware('can:tickets.check_stock');

        Route::post('await-part', [
            TicketController::class,
            'awaitPart',
        ])->middleware('can:tickets.await_part');

        Route::post('resolve', [
            TicketController::class,
            'resolve',
        ])->middleware('can:tickets.resolve');

        Route::post('cancel', [
            TicketController::class,
            'cancel',
        ])->middleware('can:tickets.cancel');

        Route::post('reopen', [
            TicketController::class,
            'reopen',
        ])->middleware('can:tickets.reopen');

        Route::post('set-service-method', [
            TicketController::class,
            'setServiceMethod',
        ])->middleware('can:tickets.set_service_method');

        Route::post('set-release-date', [
            TicketController::class,
            'setReleaseDate',
        ])->middleware('can:tickets.set_release_date');

        Route::post('assess', [
            TicketController::class,
            'assess',
        ])->middleware('can:tickets.assess');

        Route::get('assessment-report', [
            TicketController::class,
            'assessmentReport',
        ])->middleware('can:tickets.print_assessment');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::prefix('inventories/reports')->group(function () {
        Route::get('excel', [
            InventoryReportController::class,
            'exportExcel',
        ])->middleware('can:inventories.report');

        Route::get('pdf', [
            InventoryReportController::class,
            'exportPdf',
        ])->middleware('can:inventories.report');
    });

    /*
    |--------------------------------------------------------------------------
    | Other IT service request actions
    |--------------------------------------------------------------------------
    */

    Route::post(
        'other-it-service-requests/{otherItServiceRequest}/print',
        [
            OtherItServiceRequestController::class,
            'print',
        ]
    )->middleware('can:requests.other_it_services.print');

    /*
    |--------------------------------------------------------------------------
    | Current-user heartbeat
    |--------------------------------------------------------------------------
    */

    Route::put('me/heartbeat', [
        ProfileController::class,
        'updateStatus',
    ]);

    Route::put('me/stop-heartbeat', [
        ProfileController::class,
        'setStatusOffline',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Enum/reference endpoints
    |--------------------------------------------------------------------------
    */

    Route::get('query-statuses', function () {
        return collect(TicketStatus::cases())
            ->map(fn (TicketStatus $status) => [
                'value' => $status->value,
                'label' => str_replace(
                    '_',
                    ' ',
                    ucfirst($status->name)
                ),
            ]);
    })->middleware('can:tickets.view');
});